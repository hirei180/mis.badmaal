<?php
require dirname(__DIR__).'/app/web.php';requireAuth();requireAnyPermission(['grm.view']);requireAnyPermission(['grm.manage']);
use App\Services\GrmCaseService;
$id=max(0,(int)($_GET['id']??0));$case=[];$categories=[];$error='';
if($id){$s=db()->prepare('SELECT * FROM grievances WHERE id=?');$s->execute([$id]);$case=$s->fetch();if(!$case){http_response_code(404);exit('Case not found.');}$s=db()->prepare('SELECT category FROM grievance_categories WHERE grievance_id=? ORDER BY category');$s->execute([$id]);$categories=$s->fetchAll(PDO::FETCH_COLUMN);}
$values=$case+['status'=>'received','priority'=>'medium'];$values['categories']=implode("\n",$categories);$revision=$id?GrmCaseService::revision($case,$categories):'';
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireValidCsrf();
 try{$saved=GrmCaseService::save(db(),getCurrentUser(),$id,$_POST);header('Location: /grievances.php?id='.$saved.'&saved=1');exit;}
 catch(InvalidArgumentException|RuntimeException $ex){$error=$ex instanceof PDOException?'The case could not be saved. Please try again.':$ex->getMessage();$values=array_merge($values,array_filter($_POST,'is_string'));$revision=is_string($_POST['revision']??null)?$_POST['revision']:'';}
}
pageStart($id?'Edit GRM case #'.$id:'New GRM case','grievances.php');
$labels=['subject'=>'Subject','complainant_name'=>'Complainant name (optional)','email'=>'Email','phone'=>'Phone','region'=>'Region / state','district'=>'District','preferred_followup'=>'Preferred follow-up'];
?>
<section class="card"><p><a href="/grievances.php<?=$id?'?id='.$id:''?>">← Back to cases</a></p><p class="muted">Personal details and responses stay private. Saved status, priority and categories update the public summary counts immediately. Every save is recorded in the audit history.</p>
<?php if($error):?><p class="notice is-error" role="alert"><?=e($error)?></p><?php endif;?>
<form method="post"><?=csrfField()?><input type="hidden" name="revision" value="<?=e($revision)?>">
<div class="two"><?php foreach($labels as $key=>$label):?><label><?=e($label)?><input name="<?=$key?>" type="<?=$key==='email'?'email':'text'?>" maxlength="<?=GrmCaseService::FIELDS[$key]?>" value="<?=e($values[$key]??'')?>" <?=$key==='subject'?'required':''?>></label><?php endforeach;?>
<label>Incident date<input type="date" name="incident_date" max="<?=date('Y-m-d')?>" value="<?=e($values['incident_date']??'')?>"></label><label>People affected<input type="number" name="people_affected" min="0" max="4294967295" step="1" value="<?=e($values['people_affected']??'')?>"></label>
<?php foreach(['status'=>GrmCaseService::STATUSES,'priority'=>GrmCaseService::PRIORITIES] as $key=>$options):?><label><?=ucfirst($key)?><select name="<?=$key?>"><?php foreach($options as $option):?><option value="<?=$option?>" <?=($values[$key]??'')===$option?'selected':''?>><?=e(ucwords(str_replace('_',' ',$option)))?></option><?php endforeach;?></select></label><?php endforeach;?></div>
<?php foreach(['description'=>'Description','categories'=>'Categories (one per line)','response'=>'Response / resolution','review_comments'=>'Internal review notes'] as $key=>$label):?><label><?=e($label)?><textarea name="<?=$key?>" rows="4" maxlength="<?=$key==='categories'?2500:12000?>" <?=$key==='description'?'required':''?>><?=e($values[$key]??'')?></textarea></label><?php endforeach;?>
<div class="actions"><button><?=$id?'Save changes':'Create case'?></button><a href="/grievances.php<?=$id?'?id='.$id:''?>">Cancel</a></div>
</form></section><?php pageEnd();?>
