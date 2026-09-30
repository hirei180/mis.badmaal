<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
ini_set('log_errors','1');
ini_set('error_log',MIS_STORAGE.'/logs/application.log');
use App\Core\Database;
$local=is_file(MIS_ROOT.'/config/local.php')?require MIS_ROOT.'/config/local.php':[];
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
if(($local['environment']??'production')==='production'&&!$secure&&PHP_SAPI!=='cli'){http_response_code(400);exit('HTTPS is required. Configure TLS for this application.');}
if(PHP_SAPI!=='cli'){
 header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');
 $publicMap=defined('MIS_PUBLIC_DASHBOARD')&&MIS_PUBLIC_DASHBOARD?' https://*.tile.openstreetmap.org':'';
 header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:{$publicMap}; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
 header('Cache-Control: no-store');if($secure)header('Strict-Transport-Security: max-age=31536000');
}
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_save_path(MIS_STORAGE.'/sessions');session_name('BADMAAL_MIS');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
set_exception_handler(static function(Throwable $e):void {
 error_log(date('c').' '.get_class($e).': '.$e->getMessage()."\n",3,MIS_STORAGE.'/logs/application.log');
 http_response_code(500);echo 'The MIS could not complete this request. Please contact the MIS administrator.';
});
function e(mixed $value):string{return htmlspecialchars((string)($value??''),ENT_QUOTES,'UTF-8');}
function db():PDO{return Database::connection();}
function settings():array {static $s=null;return $s??=db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);}
function getCurrentUser():?array {
 if(empty($_SESSION['mis_user_id']))return null;
 $s=db()->prepare('SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.status=\'active\' AND u.legacy_identity=0');$s->execute([$_SESSION['mis_user_id']]);$u=$s->fetch();
 if(!$u||(int)$u['session_version']!==(int)($_SESSION['version']??0)||time()-(int)($_SESSION['last_active']??0)>(int)(settings()['session_minutes']??60)*60){$_SESSION=[];return null;}
 $s=db()->prepare('SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=?');$s->execute([$u['role_id']]);$u['permissions']=$s->fetchAll(PDO::FETCH_COLUMN);unset($u['password_hash']);$_SESSION['last_active']=time();return $u;
}
function requireAuth():void {
 $u=getCurrentUser();if(!$u){header('Location: /login.php');exit;}
 if($u['must_change_password']&&basename($_SERVER['SCRIPT_NAME'])!=='password.php'){header('Location: /password.php');exit;}
}
function hasAnyPermission(array $permissions):bool{return (bool)array_intersect($permissions,getCurrentUser()['permissions']??[]);}
function requireAnyPermission(array $permissions):void{if(!hasAnyPermission($permissions)){http_response_code(403);exit('Your MIS role does not permit this action.');}}
function csrfField():string {$_SESSION['csrf']??=bin2hex(random_bytes(32));return '<input type="hidden" name="csrf_token" value="'.e($_SESSION['csrf']).'">';}
function requireValidCsrf():void {if(!is_string($_POST['csrf_token']??null)||!isset($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf_token'])){http_response_code(403);exit('Your form expired. Reload the page and try again.');}}
function audit(string $action,array $details=[],?int $entityId=null):void {
 $u=getCurrentUser();$s=db()->prepare('INSERT INTO audit_logs(user_id,username_snapshot,action,entity_type,entity_id,details,ip_address)VALUES(?,?,?,?,?,?,?)');
 $s->execute([$u['id']??null,$u['username']??null,$action,'mis',$entityId,json_encode($details,JSON_THROW_ON_ERROR),$_SERVER['REMOTE_ADDR']??null]);
}
function nav(string $active=''):void {
 $items=['dashboard.php'=>'Dashboard','data-entry.php'=>'Data Entry','data-entry.php?module=review'=>'Approvals'];
 if(hasAnyPermission(['grm.view']))$items['grievances.php']='GRM cases';
 if(hasAnyPermission(['users.manage']))$items['users.php']='Users';
 if(hasAnyPermission(['roles.manage']))$items['roles.php']='Roles';
 if(hasAnyPermission(['settings.manage']))$items['settings.php']='Settings';
 if(hasAnyPermission(['audit.view']))$items['audit.php']='Audit history';
 $items['password.php']='Password';
 echo '<aside class="sidebar"><a class="brand" href="/dashboard.php"><img src="/assets/logo.png" alt="BADMAAL"><span>Management Information System</span></a><nav aria-label="MIS navigation">';
 foreach($items as $url=>$label)echo '<a '.($active===$url?'class="active" aria-current="page"':'').' href="/'.e($url).'">'.e($label).'</a>';
 echo '</nav><form method="post" action="/logout.php">'.csrfField().'<button class="logout">Sign out</button></form></aside>';
}
function pageStart(string $title,string $active=''):void {
 $u=getCurrentUser();echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | BADMAAL MIS</title><link rel="stylesheet" href="/assets/app.css"></head><body>';nav($active);
 echo '<main class="main"><header class="top"><div><span class="eyebrow">BADMAAL · MIS</span><h1>'.e($title).'</h1></div><span class="identity">'.e($u['username']??'').' · '.e($u['role_name']??'').'</span></header>';
}
function pageEnd():void{echo '</main></body></html>';}
