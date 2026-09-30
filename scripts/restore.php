<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$options=getopt('',['file:','database:','evidence-dir:']);$database=$options['database']??'';
if(!preg_match('/^[a-zA-Z0-9_]+$/',$database)||!is_file($options['file']??''))throw new RuntimeException('Usage: php scripts/restore.php --file=/private/backup --database=EMPTY_DATABASE');
$user=getenv('MIS_RESTORE_USER');if(!$user)throw new RuntimeException('Set MIS_RESTORE_USER and MIS_RESTORE_PASS for the empty recovery database.');
$config=require MIS_ROOT.'/config/database.php';$runtime=$config['connections']['mysql']['database'];if($database===$runtime)throw new RuntimeException('Refusing to restore over the running application database.');
$pdo=new PDO('mysql:host='.(getenv('MIS_RESTORE_HOST')?:'localhost').';port='.(getenv('MIS_RESTORE_PORT')?:'3306').';dbname='.$database.';charset=utf8mb4',$user,getenv('MIS_RESTORE_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$payload=App\Services\BackupService::read($options['file'],getenv('MIS_BACKUP_KEY_FILE')?:MIS_ROOT.'/config/backup.key');
$counts=App\Services\BackupService::restore($pdo,$payload,$options['evidence-dir']??null);echo json_encode(['restored_database'=>$database,'verified_tables'=>$counts],JSON_PRETTY_PRINT).PHP_EOL;
