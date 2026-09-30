<?php
require dirname(__DIR__).'/app/web.php';requireAuth();requireAnyPermission(['settings.manage']);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireValidCsrf();$org=trim((string)($_POST['organisation']??''));$email=trim((string)($_POST['support_email']??''));$minutes=filter_var($_POST['session_minutes']??null,FILTER_VALIDATE_INT);
 if($org===''||mb_strlen($org)>180||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))||$minutes<5||$minutes>240)$error='Enter an organisation, valid support email (or leave blank), and a session duration between 5 and 240 minutes.';
 else{
  db()->beginTransaction();try{foreach(['organisation'=>$org,'support_email'=>$email,'session_minutes'=>(string)$minutes] as $k=>$v)db()->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?')->execute([$v,$k]);audit('settings_updated');db()->commit();header('Location: /settings.php?saved=1');exit;}catch(Throwable $e){db()->rollBack();throw $e;}
 }
}
$s=settings();pageStart('Settings','settings.php');
?><?php if($error):?><p class="notice is-error"><?=e($error)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p class="notice">Settings saved.</p><?php endif;?><section class="card" style="max-width:640px"><form method="post"><?=csrfField()?><label>Organisation<input name="organisation" value="<?=e($s['organisation'])?>" required maxlength="180"></label><label>Support email<input type="email" name="support_email" value="<?=e($s['support_email'])?>"></label><label>Session inactivity timeout (minutes)<input type="number" name="session_minutes" min="5" max="240" value="<?=e($s['session_minutes'])?>" required></label><button>Save settings</button></form></section><section class="card"><h2>Reporting configuration</h2><p>Fiscal years, components and measurement rules follow the existing reporting framework. The source PAD review is retained with this application; unresolved methodology questions have not been silently rewritten.</p><p>Backups and deployment are managed separately from the website. See the operations runbook supplied with the source code.</p></section><?php pageEnd();?>
