<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\MisRepository;
use PDO;
use RuntimeException;

/** Drafts remain separate from the records used by the public dashboard. */
final class MisWorkflowService
{
    public function __construct(private PDO $pdo, private int $userId, private array $permissions) {}

    public function canEnter(string $module): bool
    {
        return in_array('mis.'.$module.'.enter', $this->permissions, true);
    }

    public function canApprove(): bool
    {
        return in_array('mis.approve', $this->permissions, true);
    }

    public function installed(): bool
    {
        try { $this->pdo->query('SELECT 1 FROM mis_submissions LIMIT 1'); return true; }
        catch (\Throwable) { return false; }
    }

    public function saveDraft(string $module, array $data): int
    {
        if (!$this->canEnter($module)) throw new RuntimeException('You cannot enter data for this module.');
        $data=MisInputValidator::normalize($module,$data,new MisRepository($this->pdo));
        $key = self::entityKey($module, $data);
        return $this->transaction(function () use ($module, $data, $key) {
            $s = $this->pdo->prepare('INSERT IGNORE INTO mis_publications(entity_key,module) VALUES(?,?)');
            $s->execute([$key,$module]);
            $s = $this->pdo->prepare('SELECT revision FROM mis_publications WHERE entity_key=? FOR UPDATE');
            $s->execute([$key]);
            $revision = (int)$s->fetchColumn();
            $s = $this->pdo->prepare('INSERT INTO mis_submissions(entity_key,module,payload,base_revision,created_by) VALUES(?,?,?,?,?)');
            $s->execute([$key,$module,json_encode($data,JSON_THROW_ON_ERROR),$revision,$this->userId]);
            $id = (int)$this->pdo->lastInsertId();
            $this->event($id,'draft_created');
            return $id;
        });
    }

    public static function entityKey(string $module, array $d): string
    {
        $parts = match ($module) {
            'indicators' => [(int)$d['indicator_id'],$d['fiscal_year'],$d['reporting_period'],$d['state']],
            'contracts' => [mb_strtolower(trim($d['reference_no']))],
            'finance' => [$d['fiscal_year'],$d['quarter'],$d['component']],
            'framework' => ['PDO5'],
            default => throw new RuntimeException('Unknown dashboard module.'),
        };
        return hash('sha256',json_encode([$module,$parts],JSON_THROW_ON_ERROR));
    }

    public function transition(int $id, string $action, string $note = ''): void
    {
        if (mb_strlen($note)>10000) throw new RuntimeException('Review note is too long.');
        $this->transaction(function () use ($id,$action,$note) {
            $s=$this->pdo->prepare('SELECT * FROM mis_submissions WHERE id=? FOR UPDATE');
            $s->execute([$id]); $row=$s->fetch();
            if (!$row) throw new RuntimeException('Submission not found.');
            if ($action==='submit') {
                if ((int)$row['created_by']!==$this->userId || !$this->canEnter($row['module']) || $row['status']!=='draft') {
                    throw new RuntimeException('Only the author can submit their draft.');
                }
                $s=$this->pdo->prepare("UPDATE mis_submissions SET status='submitted',submitted_at=NOW() WHERE id=?");
                $s->execute([$id]);
            } else {
                if (!$this->canApprove() || (int)$row['created_by']===$this->userId || $row['status']!=='submitted') {
                    throw new RuntimeException('A different authorized reviewer must review a submitted record.');
                }
                if (!in_array($action,['approve','return'],true)) throw new RuntimeException('Invalid review action.');
                if ($action==='return' && trim($note)==='') throw new RuntimeException('Explain which changes are needed.');
                if ($action==='approve') {
                    $s=$this->pdo->prepare('SELECT revision FROM mis_publications WHERE entity_key=? FOR UPDATE');
                    $s->execute([$row['entity_key']]);
                    if ((int)$s->fetchColumn()!==(int)$row['base_revision']) throw new RuntimeException('A newer version was approved. Return this submission and create an updated draft.');
                    $data=MisInputValidator::normalize($row['module'],json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR),new MisRepository($this->pdo));
                    $recordId=$this->publish($row['module'],$data);
                    $s=$this->pdo->prepare('UPDATE mis_publications SET record_id=?,revision=revision+1 WHERE entity_key=?');
                    $s->execute([$recordId,$row['entity_key']]);
                }
                $s=$this->pdo->prepare('UPDATE mis_submissions SET status=?,reviewed_by=?,review_note=?,reviewed_at=NOW() WHERE id=?');
                $s->execute([$action==='approve'?'approved':'changes_requested',$this->userId,$note,$id]);
            }
            $this->event($id,$action,$note);
        });
    }

    private function publish(string $module, array $data): int
    {
        $repo=new MisRepository($this->pdo);
        switch ($module) {
            case 'indicators':
                // Recalculate using the current approved framework at approval time.
                $i=$repo->indicatorById((int)$data['indicator_id']);
                if (!$i) throw new RuntimeException('Indicator is no longer active.');
                $achievement=PdoDashboardService::achievement($data['actual_value'],$data['target_value'],$i['baseline_value']===null?null:(float)$i['baseline_value'],$i['calculation_method'],$i['direction']);
                $data['status']=PdoDashboardService::status($achievement,(float)$i['on_track_threshold'],!empty($i['allow_overachievement'])?(float)$i['exceeded_threshold']:null);
                $repo->saveIndicatorPeriod($data,$this->userId);
                $sql='SELECT id FROM mis_indicator_periods WHERE indicator_id=? AND fiscal_year=? AND reporting_period=? AND state=?';
                $args=[$data['indicator_id'],$data['fiscal_year'],$data['reporting_period'],$data['state']]; break;
            case 'contracts':
                $repo->saveContract($data,$this->userId);
                $sql='SELECT id FROM mis_contracts WHERE reference_no=?'; $args=[$data['reference_no']]; break;
            case 'finance':
                $repo->saveFinance($data,$this->userId);
                $sql='SELECT id FROM mis_financial_records WHERE fiscal_year=? AND quarter=? AND component=?';
                $args=[$data['fiscal_year'],$data['quarter'],$data['component']]; break;
            case 'framework':
                $repo->updatePdo5Baseline($data['baseline_value'],$data['baseline_date'],$data['baseline_source'],$this->userId);
                $sql="SELECT id FROM mis_indicators WHERE code='PDO5'"; $args=[]; break;
            default: throw new RuntimeException('Unknown module.');
        }
        $s=$this->pdo->prepare($sql); $s->execute($args);
        $id=(int)$s->fetchColumn();
        if (!$id) throw new RuntimeException('Published record could not be located.');
        return $id;
    }

    public function visibleSubmissions(string $status='', int $page=1): array
    {
        $sql='SELECT s.*,u.username author FROM mis_submissions s JOIN users u ON u.id=s.created_by WHERE 1=1';
        $args=[];
        if (!$this->canApprove()) { $sql.=' AND s.created_by=?'; $args[]=$this->userId; }
        if(in_array($status,['draft','submitted','changes_requested','approved'],true)) { $sql.=' AND s.status=?'; $args[]=$status; }
        $offset=(max(1,$page)-1)*20;
        $s=$this->pdo->prepare($sql." ORDER BY (s.status='submitted') DESC,s.id DESC LIMIT 21 OFFSET $offset"); $s->execute($args);
        return $s->fetchAll();
    }

    public function findVisible(int $id): ?array
    {
        $s=$this->pdo->prepare('SELECT * FROM mis_submissions WHERE id=?');$s->execute([$id]);$row=$s->fetch();
        return $row&&($this->canApprove()||(int)$row['created_by']===$this->userId)?$row:null;
    }

    public function history(int $id): array
    {
        if(!$this->findVisible($id)) return [];
        $s=$this->pdo->prepare('SELECT e.action,e.note,e.created_at,u.username FROM mis_submission_events e JOIN users u ON u.id=e.actor_id WHERE submission_id=? ORDER BY e.id');
        $s->execute([$id]);return $s->fetchAll();
    }

    private function event(int $id,string $action,string $note=''): void
    {
        $s=$this->pdo->prepare('INSERT INTO mis_submission_events(submission_id,actor_id,action,note) VALUES(?,?,?,?)');
        $s->execute([$id,$this->userId,$action,$note]);
    }

    private function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try { $result=$operation(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }
}
