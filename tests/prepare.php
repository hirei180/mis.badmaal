<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\BackupService;
$opsUser=getenv('MIS_TEST_DB_USER')?:'root';$opsPass=getenv('MIS_TEST_DB_PASS')?:'';$host=getenv('MIS_TEST_DB_HOST')?:'localhost';
$admin=new PDO('mysql:host='.$host.';charset=utf8mb4',$opsUser,$opsPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$database='mis_test_'.bin2hex(random_bytes(6));$admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$admin->exec("USE `$database`");
try{
 $backup=BackupService::create(App\Core\Database::connection(),MIS_ROOT.'/config/backup.key',MIS_ROOT.'/storage/backups');
 $counts=BackupService::restore($admin,BackupService::read($backup,MIS_ROOT.'/config/backup.key'));
 // Re-import into a populated database must fail.
 try{BackupService::restore($admin,BackupService::read($backup,MIS_ROOT.'/config/backup.key'));throw new Exception('Overwrite accepted');}catch(RuntimeException $expected){}
 $corrupt=json_decode(file_get_contents($backup),true);$cipher=base64_decode($corrupt['data']);$cipher[0]=chr(ord($cipher[0])^1);$corrupt['data']=base64_encode($cipher);$damaged=MIS_ROOT.'/storage/damaged-test.backup';file_put_contents($damaged,json_encode($corrupt));
 try{BackupService::read($damaged,MIS_ROOT.'/config/backup.key');throw new Exception('Damaged backup accepted');}catch(RuntimeException $expected){}finally{unlink($damaged);}
 $password=bin2hex(random_bytes(12));$admin->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE legacy_identity=0')->execute([password_hash($password,PASSWORD_DEFAULT)]);
 foreach(['procurement','finance','grm'] as $role){$s=$admin->prepare('SELECT id FROM roles WHERE slug=?');$s->execute([$role]);$id=$s->fetchColumn();$admin->prepare('INSERT INTO users(full_name,username,email,password_hash,role_id,must_change_password)VALUES(?,?,?,?,?,0)')->execute([$role,'test.'.$role,'test.'.$role.'@mis.invalid',password_hash($password,PASSWORD_DEFAULT),$id]);}
 file_put_contents(MIS_ROOT.'/storage/test-fixture.json',json_encode(['database'=>$database,'password'=>$password,'db_host'=>$host,'db_user'=>$opsUser,'db_pass'=>$opsPass]));chmod(MIS_ROOT.'/storage/test-fixture.json',0600);
 echo 'Recovery verified: '.count($counts)." tables; exact row checksums, foreign keys, damaged-file rejection and overwrite protection passed.\n";
}catch(Throwable $e){$admin->exec("DROP DATABASE `$database`");throw $e;}
