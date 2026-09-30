<?php
require dirname(__DIR__).'/app/web.php';requireAuth();$u=getCurrentUser();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireValidCsrf();$old=(string)($_POST['current']??'');$new=(string)($_POST['password']??'');
 $s=db()->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$u['id']]);
 if(!password_verify($old,$s->fetchColumn()))$error='Current password is incorrect.';
 elseif(strlen($new)<12||strlen($new)>72||$new!==($_POST['confirm']??''))$error='Use 12–72 characters and matching confirmation.';
 elseif($old===$new)$error='Choose a new password.';
 else{db()->prepare('UPDATE users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);$_SESSION['version']++;session_regenerate_id(true);audit('password_changed');header('Location: /dashboard.php');exit;}
}
pageStart('Change password','password.php');
?><section class="card" style="max-width:580px"><?php if($u['must_change_password']):?><p class="notice">Set your own password before using the MIS.</p><?php endif;?><?php if($error):?><p class="notice is-error"><?=e($error)?></p><?php endif;?><form method="post"><?=csrfField()?><label>Current password<input type="password" name="current" autocomplete="current-password" required></label><label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Confirm password<input type="password" name="confirm" autocomplete="new-password" required></label><button>Save password</button></form></section><?php pageEnd();?>
