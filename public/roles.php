<?php
require dirname(__DIR__).'/app/web.php';requireAuth();requireAnyPermission(['roles.manage']);$error='';
$permissions=db()->query('SELECT id,slug,description FROM permissions ORDER BY slug')->fetchAll();$allowed=array_map('intval',array_column($permissions,'id'));
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireValidCsrf();
 try{
  $id=(int)($_POST['role_id']??0);$s=db()->prepare('SELECT slug FROM roles WHERE id=?');$s->execute([$id]);$slug=$s->fetchColumn();
  if(!$slug||in_array($slug,['mis-admin','legacy-archive'],true))throw new RuntimeException('Administrator and historical roles have fixed permissions.');
  $ids=array_unique(array_map('intval',(array)($_POST['permissions']??[])));if(array_diff($ids,$allowed))throw new RuntimeException('Invalid permission.');
  db()->beginTransaction();db()->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$id]);
  foreach($ids as $pid)db()->prepare('INSERT INTO role_permissions(role_id,permission_id)VALUES(?,?)')->execute([$id,$pid]);
  db()->prepare('UPDATE users SET session_version=session_version+1 WHERE role_id=?')->execute([$id]);audit('role_permissions_updated',['permission_ids'=>$ids],$id);db()->commit();header('Location: /roles.php?saved=1');exit;
 }catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();$error=$ex instanceof PDOException?'Unable to update role permissions.':$ex->getMessage();}
}
$roles=db()->query("SELECT * FROM roles WHERE slug<>'legacy-archive' ORDER BY id")->fetchAll();pageStart('Roles & permissions','roles.php');
?><p class="muted">These roles belong only to this MIS. Website administrator privileges do not apply here. M&E users can enter and approve data, but cannot approve their own submissions.</p><?php if($error):?><p class="notice is-error"><?=e($error)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p class="notice">Permissions updated. Affected users must sign in again.</p><?php endif;?><?php foreach($roles as $r):$s=db()->prepare('SELECT permission_id FROM role_permissions WHERE role_id=?');$s->execute([$r['id']]);$granted=$s->fetchAll(PDO::FETCH_COLUMN);$fixed=$r['slug']==='mis-admin';?><section class="card"><h2><?=e($r['name'])?></h2><form method="post"><?=csrfField()?><input type="hidden" name="role_id" value="<?=$r['id']?>"><div class="permissions"><?php foreach($permissions as $p):?><label><input type="checkbox" name="permissions[]" value="<?=$p['id']?>" <?=in_array($p['id'],$granted)?'checked':''?> <?=$fixed?'disabled':''?>><?=e($p['slug'])?></label><?php endforeach;?></div><?php if(!$fixed):?><button>Save permissions</button><?php else:?><p class="muted">Fixed administrator permissions prevent accidental lockout.</p><?php endif;?></form></section><?php endforeach;?><?php pageEnd();?>
