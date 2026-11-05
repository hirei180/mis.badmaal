<?php
require dirname(__DIR__).'/app/web.php';
requireAuth();
use App\Services\AccountSessionService as Accounts;
$user=getCurrentUser();$error='';$timeout=(int)(settings()['session_minutes']??60)*60;
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireValidCsrf();
    try{
        $from=(int)$user['id'];$action=$_POST['action']??'';
        if($action==='switch'){
            Accounts::switchTo(db(),(int)($_POST['account_id']??0),$timeout);
            audit('account_switched',['from_user_id'=>$from]);
        }elseif($action==='add'){
            $login=is_string($_POST['username']??null)?trim($_POST['username']):'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if(strlen($login)>80||strlen($password)>200)throw new RuntimeException('Invalid MIS username or password.');
            $throttle=new App\Services\LoginThrottle(MIS_STORAGE.'/login-throttle.json');
            $retry=$throttle->reserve($login,$_SERVER['REMOTE_ADDR']??'');
            if($retry){http_response_code(429);header('Retry-After: '.$retry);throw new RuntimeException('Too many attempts. Please try again later.');}
            $s=db()->prepare("SELECT * FROM users WHERE username=? AND status='active' AND legacy_identity=0");$s->execute([$login]);$target=$s->fetch();
            if(!$target||!password_verify($password,$target['password_hash'])){
                audit('account_add_failed',['login'=>$login]);throw new RuntimeException('Invalid MIS username or password.');
            }
            Accounts::activate($target);$throttle->clearAccount($login);
            db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$target['id']]);
            audit('account_added',['from_user_id'=>$from]);
        }else{throw new RuntimeException('Unknown account action.');}
        header('Location: /dashboard.php',true,303);exit;
    }catch(RuntimeException $ex){$error=$ex instanceof PDOException?'Unable to switch accounts right now.':$ex->getMessage();}
}
$accounts=Accounts::available(db(),$timeout);
pageStart('Switch user','switch-user.php');
?>
<section class="card"><h2>Accounts signed in on this browser</h2><p class="muted">Sign in to the second account once, then switch here with one click. Signing out ends all accounts in this browser. Inactive accounts expire after <?=intdiv($timeout,60)?> minutes.</p>
<?php if($error):?><p class="notice is-error" role="alert"><?=e($error)?></p><?php endif;?>
<div class="account-list"><?php foreach($accounts as $account):?><div class="account-choice"><div><strong><?=e($account['username'])?></strong><br><small><?=e($account['full_name'])?></small></div>
<?php if((int)$account['id']===(int)$user['id']):?><span class="badge">Current user</span><?php else:?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="switch"><input type="hidden" name="account_id" value="<?=(int)$account['id']?>"><button>Switch to <?=e($account['username'])?></button></form><?php endif;?></div><?php endforeach;?></div></section>
<section class="card" style="max-width:580px"><h2>Sign in to another account</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="add"><label>Username<input name="username" autocomplete="username" required maxlength="80" value="<?=e($user['username']==='mis.admin'?'me.officer':'mis.admin')?>"></label><label>Password<input type="password" name="password" autocomplete="current-password" required maxlength="200"></label><button>Sign in and switch</button></form></section>
<?php pageEnd();?>
