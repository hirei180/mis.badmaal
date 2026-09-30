<?php
require dirname(__DIR__).'/app/web.php';
if(getCurrentUser()){header('Location: /dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireValidCsrf();$login=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
 $throttle=new App\Services\LoginThrottle(MIS_STORAGE.'/login-throttle.json');
 $retry=$throttle->reserve($login,$_SERVER['REMOTE_ADDR']??'');
 if($retry){http_response_code(429);header('Retry-After: '.$retry);$error='Too many attempts. Please try again later.';}
 else{
  $s=db()->prepare('SELECT * FROM users WHERE username=? AND status=\'active\' AND legacy_identity=0');$s->execute([$login]);$u=$s->fetch();
  if($u&&password_verify($password,$u['password_hash'])){
   session_regenerate_id(true);$_SESSION=['mis_user_id'=>(int)$u['id'],'version'=>(int)$u['session_version'],'last_active'=>time()];
   db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);$throttle->clearAccount($login);audit('login');header('Location: /dashboard.php');exit;
  }
  $error='Invalid MIS username or password.';audit('login_failed',['login'=>$login]);
 }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | BADMAAL MIS</title><link rel="stylesheet" href="/assets/app.css"></head><body><main class="auth card"><img class="auth-logo" src="/assets/logo.png" alt="BADMAAL"><span class="eyebrow">Management Information System</span><h1>Sign in to the MIS</h1><p class="muted">Use your independent MIS account.</p><?php if($error):?><p class="notice is-error" role="alert"><?=e($error)?></p><?php endif;?><form method="post"><?=csrfField()?><label>Username<input name="username" autocomplete="username" required maxlength="80"></label><label>Password<input name="password" type="password" autocomplete="current-password" required maxlength="200"></label><button>Sign in</button></form><p style="margin-top:20px"><a href="/index.php">View public results →</a></p></main></body></html>
