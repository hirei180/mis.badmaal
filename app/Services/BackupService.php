<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;
final class BackupService
{
 public static function create(PDO $pdo,string $keyFile,string $directory):string {
  if(!is_file($keyFile))throw new RuntimeException('Backup encryption key is missing.');
  $key=file_get_contents($keyFile);if(strlen($key)!==32)throw new RuntimeException('Invalid backup key.');
  if(!is_dir($directory))throw new RuntimeException('Backup directory is missing.');
  $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$data=[];
  $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
  try{
   foreach($tables as $table){
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$table))throw new RuntimeException('Unsupported table name.');
    $schema=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $rows=$pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $data[$table]=['schema'=>$schema,'rows'=>$rows,'count'=>count($rows),'sha256'=>self::rowHash($rows)];
   }
   $pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  $files=[];
  foreach($data['grm_evidence_files']['rows']??[] as $row){
   $name=$row['storage_name'];if(!preg_match('/^\d+-[a-f0-9]{64}$/',$name))throw new RuntimeException('Invalid evidence name.');
   $path=MIS_STORAGE.'/grm-evidence/'.$name;
   if(!is_file($path)||is_link($path)||hash_file('sha256',$path)!==$row['sha256'])throw new RuntimeException('Evidence missing or changed; backup aborted.');
   $files[$name]=['sha256'=>$row['sha256'],'data'=>base64_encode(file_get_contents($path))];
  }
  $payload=gzencode(json_encode(['format'=>2,'created_at'=>gmdate('c'),'tables'=>$data,'evidence_files'=>$files],JSON_THROW_ON_ERROR),9);
  $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($payload,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'BADMAAL-MIS-v1');
  if($cipher===false)throw new RuntimeException('Encryption failed.');
  $envelope=json_encode(['format'=>'BADMAAL-MIS-v1','iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($cipher)],JSON_THROW_ON_ERROR);
  $path=$directory.'/mis-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.backup';
  $tmp=$path.'.tmp';if(file_put_contents($tmp,$envelope,LOCK_EX)!==strlen($envelope))throw new RuntimeException('Backup write failed.');chmod($tmp,0600);rename($tmp,$path);return $path;
 }
 public static function read(string $file,string $keyFile):array {
  $key=file_get_contents($keyFile);if(strlen($key)!==32)throw new RuntimeException('Invalid backup key.');
  $e=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  if(($e['format']??'')!=='BADMAAL-MIS-v1')throw new RuntimeException('Unknown backup format.');
  $plain=openssl_decrypt(base64_decode($e['data'],true),'aes-256-gcm',$key,OPENSSL_RAW_DATA,base64_decode($e['iv'],true),base64_decode($e['tag'],true),'BADMAAL-MIS-v1');
  if($plain===false)throw new RuntimeException('Backup authentication failed (wrong key or damaged backup).');
  $payload=json_decode(gzdecode($plain),true,512,JSON_THROW_ON_ERROR);
  foreach($payload['tables'] as $name=>$t){if(!preg_match('/^[a-zA-Z0-9_]+$/',$name)||count($t['rows'])!==$t['count']||self::rowHash($t['rows'])!==$t['sha256'])throw new RuntimeException('Backup content verification failed.');}
  $files=$payload['evidence_files']??[];
  if(count($files)!==count($payload['tables']['grm_evidence_files']['rows']??[]))throw new RuntimeException('Evidence manifest count mismatch.');
  foreach($payload['tables']['grm_evidence_files']['rows']??[] as $row){
   $name=$row['storage_name'];$file=$files[$name]??null;
   if(!preg_match('/^\d+-[a-f0-9]{64}$/',$name)||!$file||$file['sha256']!==$row['sha256'])throw new RuntimeException('Invalid evidence manifest.');
   $bytes=base64_decode($file['data'],true);
   if($bytes===false||strlen($bytes)!==(int)$row['byte_size']||hash('sha256',$bytes)!==$row['sha256'])throw new RuntimeException('Evidence checksum mismatch.');
  }
  return $payload;
 }
 public static function restore(PDO $target,array $payload,?string $evidenceDirectory=null):array {
  if($target->query('SHOW TABLES')->fetch())throw new RuntimeException('Restore requires an empty target database.');
  $files=$payload['evidence_files']??[];$written=[];
  if($files){
   if(!$evidenceDirectory||is_link($evidenceDirectory)||(is_dir($evidenceDirectory)&&count(scandir($evidenceDirectory))>2))throw new RuntimeException('Restore requires a separate empty evidence directory.');
   if(!is_dir($evidenceDirectory)&&!mkdir($evidenceDirectory,0700,true))throw new RuntimeException('Cannot create recovery evidence directory.');
  }
  // Topological schema creation keeps foreign-key checks enabled throughout import.
  $pending=$payload['tables'];$created=[];
  while($pending){$progress=false;
   foreach($pending as $name=>$table){preg_match_all('/REFERENCES `([^`]+)`/',$table['schema'],$m);if(array_diff($m[1],$created))continue;
    $target->exec($table['schema']);$created[]=$name;unset($pending[$name]);$progress=true;
   }
   if(!$progress)throw new RuntimeException('Unresolved table dependencies.');
  }
  $target->beginTransaction();$counts=[];
  try{
   foreach($created as $name){$table=$payload['tables'][$name];foreach($table['rows'] as $row){
    $columns=implode(',',array_map(static fn($c)=>'`'.$c.'`',array_keys($row)));
    $target->prepare("INSERT INTO `$name` ($columns) VALUES (".implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
   }
   $actual=$target->query("SELECT * FROM `$name`")->fetchAll(PDO::FETCH_ASSOC);
   if(self::rowHash($actual)!==$table['sha256'])throw new RuntimeException("Restored checksum mismatch: $name");$counts[$name]=count($actual);
   }
   foreach($files as $name=>$file){
    if(!preg_match('/^\d+-[a-f0-9]{64}$/',$name))throw new RuntimeException('Unsafe evidence path.');
    $bytes=base64_decode($file['data'],true);if($bytes===false||hash('sha256',$bytes)!==$file['sha256'])throw new RuntimeException('Invalid evidence bytes.');
    $path=$evidenceDirectory.'/'.$name;$written[]=$path;
    if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)||hash_file('sha256',$path)!==$file['sha256'])throw new RuntimeException('Evidence restore failed.');chmod($path,0600);
   }
   $target->commit();return $counts;
  }catch(\Throwable $e){if($target->inTransaction())$target->rollBack();foreach($written as $path)if(is_file($path))unlink($path);throw $e;}
 }
 public static function rowHash(array $rows):string {
  $encoded=array_map(static function($row){ksort($row);return json_encode($row,JSON_THROW_ON_ERROR);},$rows);sort($encoded,SORT_STRING);return hash('sha256',implode("\n",$encoded));
 }
}
