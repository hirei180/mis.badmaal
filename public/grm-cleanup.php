<?php
require dirname(__DIR__).'/app/web.php';requireAuth();requireAnyPermission(['grm.view']);requireAnyPermission(['grm.manage']);
use App\Services\GrmCleanupService as Cleanup;
$error='';$preview=[];$mode=($_GET['mode']??'')==='restore'?'restore':'archive';
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireValidCsrf();
    try{
        $action=$_POST['action']??'';
        if($action==='preview'){
            unset($_SESSION['grm_cleanup']);
            $mode=($_POST['mode']??'')==='restore'?'restore':'archive';$condition=$mode==='restore'?"status='archived'":"status<>'archived'";
            if(($_POST['scope']??'')==='all'){
                $preview=db()->query('SELECT * FROM grievances WHERE '.$condition.' ORDER BY id LIMIT 1001')->fetchAll();
            }else{
                $ids=$_POST['case_ids']??[];
                if(!is_array($ids)||!$ids||count($ids)>1000)throw new RuntimeException('Select at least one case.');
                foreach($ids as $id)if(!is_string($id)||!ctype_digit($id)||(int)$id<1)throw new RuntimeException('Invalid case selection.');
                $ids=array_unique(array_map('intval',$ids));
                $s=db()->prepare('SELECT * FROM grievances WHERE '.$condition.' AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');$s->execute(array_values($ids));$preview=$s->fetchAll();
                if(count($preview)!==count($ids))throw new RuntimeException('A selected case changed. Select the cases again.');
            }
            if(!$preview||count($preview)>1000)throw new RuntimeException('Select between 1 and 1,000 cases.');
            $snapshot=[];foreach($preview as $row)$snapshot[(int)$row['id']]=Cleanup::revision($row);
            $_SESSION['grm_cleanup']=['actor_id'=>getCurrentUser()['id'],'operation'=>$mode,'snapshot'=>$snapshot,'expires'=>time()+900];
        }elseif($action==='confirm'){
            $pending=$_SESSION['grm_cleanup']??null;unset($_SESSION['grm_cleanup']);
            if(!$pending||$pending['expires']<time()||(int)$pending['actor_id']!==(int)getCurrentUser()['id'])throw new RuntimeException('This confirmation expired. Review the cases again.');
            $count=Cleanup::apply(db(),getCurrentUser(),$pending['operation'],$pending['snapshot']);
            $_SESSION['grm_cleanup_notice']=$count.' case(s) '.($pending['operation']==='archive'?'archived. You can restore them from Archived cases.':'restored.');
            header('Location: /grievances.php',true,303);exit;
        }else{throw new RuntimeException('Unknown action.');}
    }catch(RuntimeException $ex){$preview=[];$error=$ex instanceof PDOException?'Unable to update cases. Please try again.':$ex->getMessage();}
}
$condition=$mode==='restore'?"status='archived'":"status<>'archived'";
$count=(int)db()->query('SELECT COUNT(*) FROM grievances WHERE '.$condition)->fetchColumn();
$rows=$preview?:db()->query('SELECT id,external_id,subject,region,status FROM grievances WHERE '.$condition.' ORDER BY id DESC LIMIT 100')->fetchAll();
pageStart($mode==='restore'?'Restore GRM cases':'Clear GRM register','grievances.php');
?>
<section class="card"><div class="actions"><a href="/grievances.php">← GRM cases</a><a href="?mode=archive">Clear register</a><a href="?mode=restore">Restore archived cases</a></div><p style="margin-top:16px">Clearing archives cases and removes them from the current register and public dashboard counts. Case details, evidence and audit history remain available for restoration.</p>
<?php if($error):?><p class="notice is-error" role="alert"><?=e($error)?></p><?php endif;?>
<?php if($preview):?><h2>Review <?=count($preview)?> cases to <?=$mode==='restore'?'restore':'archive'?></h2><p>Only the cases listed below will change. Restoring a case returns it to its previous status.</p><?php else:?><h2><?=$count?> <?=$mode==='restore'?'archived':'current'?> cases</h2><p class="muted">Select cases below or review all <?=$count?> cases. Up to 100 cases are shown; each action supports 1,000 cases.</p><?php endif;?>
<form method="post"><?=csrfField()?><input type="hidden" name="mode" value="<?=$mode?>"><input type="hidden" name="action" value="<?=$preview?'confirm':'preview'?>">
<div class="table-scroll"><table><thead><tr><?php if(!$preview):?><th>Select</th><?php endif;?><th>Reference / subject</th><th>Region</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php if(!$preview):?><td><input class="case-select" type="checkbox" name="case_ids[]" value="<?=(int)$row['id']?>" aria-label="Select case <?=(int)$row['id']?>"></td><?php endif;?><td><?=e($row['external_id'])?><br><?=e($row['subject'])?></td><td><?=e($row['region'])?></td><td><?=e($row['status'])?></td></tr><?php endforeach;?></tbody></table></div>
<div class="actions" style="margin-top:20px"><?php if($preview):?><button><?=$mode==='restore'?'Restore':'Archive'?> <?=count($preview)?> cases</button><a href="?mode=<?=$mode?>">Cancel</a><?php elseif($rows):?><button name="scope" value="selected">Review selected cases</button><button name="scope" value="all">Review all <?=$count?> cases</button><?php else:?><p>No cases to <?=$mode==='restore'?'restore':'archive'?>.</p><?php endif;?></div></form></section>
<?php pageEnd();?>
