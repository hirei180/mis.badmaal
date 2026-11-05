<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class GrmCleanupService
{
    public static function revision(array $case): string {return hash('sha256',json_encode($case,JSON_THROW_ON_ERROR));}

    public static function apply(PDO $db,array $actor,string $operation,array $snapshot): int
    {
        if(!in_array('grm.manage',$actor['permissions']??[],true)||!in_array('grm.view',$actor['permissions']??[],true))throw new RuntimeException('GRM management is not permitted.');
        if(!in_array($operation,['archive','restore'],true)||!$snapshot||count($snapshot)>1000)throw new RuntimeException('Select between 1 and 1,000 cases.');
        ksort($snapshot,SORT_NUMERIC);$db->beginTransaction();
        try{
            foreach($snapshot as $id=>$revision){
                $s=$db->prepare('SELECT * FROM grievances WHERE id=? FOR UPDATE');$s->execute([$id]);$before=$s->fetch();
                if(!$before||!is_string($revision)||!hash_equals(self::revision($before),$revision))throw new RuntimeException('A selected case changed. Review the selection again before continuing.');
                if(($operation==='archive'&&$before['status']==='archived')||($operation==='restore'&&$before['status']!=='archived'))throw new RuntimeException('A selected case no longer matches this action.');
                $status='archived';$archivedAt=date('Y-m-d H:i:s');
                if($operation==='restore'){
                    $s=$db->prepare("SELECT details FROM audit_logs WHERE entity_type='grievance' AND entity_id=? AND action='grm_case_cleared' ORDER BY id DESC LIMIT 1");$s->execute([$id]);
                    $details=json_decode($s->fetchColumn()?:'{}',true);$status=$details['before']['status']??($before['date_resolved']?'resolved':'received');
                    if(!in_array($status,GrmCaseService::STATUSES,true)||$status==='archived')$status='received';$archivedAt=null;
                }
                $db->prepare('UPDATE grievances SET status=?,archived_at=?,updated_at=NOW(),updated_by=? WHERE id=?')->execute([$status,$archivedAt,$actor['id'],$id]);
                $s=$db->prepare('SELECT * FROM grievances WHERE id=?');$s->execute([$id]);$after=$s->fetch();
                $db->prepare('INSERT INTO audit_logs(user_id,username_snapshot,action,entity_type,entity_id,details,ip_address)VALUES(?,?,?,?,?,?,?)')->execute([$actor['id'],$actor['username'],$operation==='archive'?'grm_case_cleared':'grm_case_restored','grievance',$id,json_encode(['before'=>$before,'after'=>$after],JSON_THROW_ON_ERROR),$_SERVER['REMOTE_ADDR']??null]);
            }
            $db->commit();return count($snapshot);
        }catch(\Throwable $ex){if($db->inTransaction())$db->rollBack();throw $ex;}
    }
}
