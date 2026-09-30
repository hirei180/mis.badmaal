<?php
require dirname(__DIR__).'/app/web.php';requireAuth();requireAnyPermission(['users.manage']);$error='';$notice='';$me=getCurrentUser();
$roles=db()->query("SELECT id,name,slug FROM roles WHERE slug<>'legacy-archive' ORDER BY id")->fetchAll();$roleIds=array_column($roles,'id');
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireValidCsrf();
 try{
  $id=(int)($_POST['id']??0);$role=(int)($_POST['role_id']??0);$name=trim((string)($_POST['full_name']??''));$login=trim((string)($_POST['username']??''));$email=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');$status=(string)($_POST['status']??'active');
  if(!in_array($role,array_map('intval',$roleIds),true)||!in_array($status,['active','inactive'],true))throw new RuntimeException('Select a valid role and status.');
  if($name===''||mb_strlen($name)>180||!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$login)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)throw new RuntimeException('Enter a name, valid username (3–80 characters), and email.');
  if(($id===0||$password!=='')&&(strlen($password)<12||strlen($password)>72))throw new RuntimeException('Temporary passwords need 12–72 characters.');
  db()->beginTransaction();
  if($id){
   $s=db()->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$s->execute([$id]);$user=$s->fetch();
   if(!$user||$user['legacy_identity'])throw new RuntimeException('Historical attribution records cannot become login accounts.');
   if($id===(int)$me['id']&&($status!=='active'||$role!==(int)$me['role_id']))throw new RuntimeException('You cannot deactivate or change your own administrator role.');
   db()->prepare('UPDATE users SET full_name=?,username=?,email=?,role_id=?,status=?,session_version=session_version+1 WHERE id=?')->execute([$name,$login,$email,$role,$status,$id]);
   if($password!=='')db()->prepare('UPDATE users SET password_hash=?,must_change_password=1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
   if($id===(int)$me['id'])$_SESSION['version']++;
  }else{
   db()->prepare('INSERT INTO users(full_name,username,email,password_hash,role_id,status)VALUES(?,?,?,?,?,?)')->execute([$name,$login,$email,password_hash($password,PASSWORD_DEFAULT),$role,$status]);$id=(int)db()->lastInsertId();
  }
  audit('user_saved',['username'=>$login,'role_id'=>$role,'status'=>$status,'password_reset'=>$password!==''],$id);db()->commit();header('Location: /users.php?saved=1');exit;
 }catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();$error=$ex instanceof PDOException?'Could not save. The username or email may already be in use.':$ex->getMessage();}
}
$edit=null;if(isset($_GET['edit'])){$s=db()->prepare('SELECT * FROM users WHERE id=? AND legacy_identity=0');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch()?:null;}
$users=db()->query('SELECT u.id,u.full_name,u.username,u.email,u.status,u.legacy_identity,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.legacy_identity,u.id')->fetchAll();
pageStart('Users','users.php');
?><?php if($error):?><p class="notice is-error"><?=e($error)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p class="notice">User saved. Temporary passwords must be changed at first sign-in.</p><?php endif;?><div class="two"><section class="card"><h2><?=$edit?'Edit MIS user':'Create MIS user'?></h2><form method="post"><?=csrfField()?><input type="hidden" name="id" value="<?=e($edit['id']??0)?>"><label>Full name<input name="full_name" value="<?=e($edit['full_name']??'')?>" required maxlength="180"></label><label>Username<input name="username" value="<?=e($edit['username']??'')?>" required pattern="[A-Za-z0-9._-]{3,80}"></label><label>Email<input name="email" type="email" value="<?=e($edit['email']??'')?>" required></label><label>Role<select name="role_id"><?php foreach($roles as $r):?><option value="<?=$r['id']?>" <?=($edit['role_id']??0)==$r['id']?'selected':''?>><?=e($r['name'])?></option><?php endforeach;?></select></label><label>Status<select name="status"><?php foreach(['active','inactive'] as $status):?><option <?=($edit['status']??'active')===$status?'selected':''?>><?=$status?></option><?php endforeach;?></select></label><label><?=$edit?'Reset password (leave blank to keep existing)':'Temporary password'?><input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" <?=$edit?'':'required'?>></label><button>Save user</button> <a href="/users.php">New user</a></form></section><section class="card"><h2>MIS accounts</h2><div class="table-scroll"><table><thead><tr><th>User</th><th>Role / status</th><th>Action</th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><?=e($u['username'])?><br><small><?=e($u['full_name'])?></small></td><td><?=e($u['role_name'])?><br><span class="badge"><?=e($u['status'])?></span></td><td><?php if(!$u['legacy_identity']):?><a href="?edit=<?=$u['id']?>">Edit</a><?php else:?>Attribution only<?php endif;?></td></tr><?php endforeach;?></tbody></table></div><p class="muted">Historical identities preserve authorship. They have no login credentials and cannot be activated.</p></section></div><?php pageEnd();?>
