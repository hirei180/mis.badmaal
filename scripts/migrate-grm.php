<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\BackupService;
$options=getopt('',['source-root:']);$root=realpath($options['source-root']??'');
if(!$root||!is_file($root.'/config/database.php'))throw new RuntimeException('Pass --source-root=/path/to/website');
$c=require $root.'/config/database.php';$c=$c['connections'][$c['default']];
$local=require MIS_ROOT.'/config/database.php';$target=$local['connections']['mysql'];
$connect=static fn($host,$port,$db,$user,$pass)=>new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$source=$connect($c['host'],$c['port'],$c['database'],$c['username'],$c['password']);
$dest=$connect($target['host'],$target['port'],$target['database'],getenv('MIS_MIGRATION_USER')?:$c['username'],getenv('MIS_MIGRATION_PASS')!==false?getenv('MIS_MIGRATION_PASS'):$c['password']);
if($c['database']===$target['database'])throw new RuntimeException('Source and target must be separate.');
foreach(['grievances','grievance_categories','grm_evidence_files'] as $table){$s=$dest->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);if($s->fetchColumn()&&(int)$dest->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()>0)throw new RuntimeException('GRM target is populated. Refusing to overwrite it.');}
$backup=BackupService::create(App\Core\Database::connection(),MIS_ROOT.'/config/backup.key',MIS_ROOT.'/storage/backups');
echo "Pre-migration encrypted backup created.\n";
$source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$cases=$source->query('SELECT * FROM grievances ORDER BY id')->fetchAll();$categories=$source->query('SELECT * FROM grievance_categories ORDER BY grievance_id,category')->fetchAll();
$evidence=[];$actors=[];
foreach($cases as $case){
 foreach(['response_by','created_by','updated_by','reviewed_by','approved_by','published_by'] as $key)if($case[$key]!==null)$actors[(int)$case[$key]]=true;
 if(!empty($case['evidence_path'])){$file=realpath($root.'/'.ltrim($case['evidence_path'],'/'));if(!$file||!str_starts_with($file,$root.'/')||!is_file($file)||!is_readable($file))throw new RuntimeException('Missing or unsafe evidence for case #'.$case['id']);$hash=hash_file('sha256',$file);$evidence[]=['grievance_id'=>(int)$case['id'],'storage_name'=>$case['id'].'-'.$hash,'sha256'=>$hash,'byte_size'=>filesize($file),'source'=>$file];}
}
$logs=$source->query("SELECT * FROM audit_logs WHERE action LIKE 'grievance%' OR entity_type IN ('grievances','grievance','grm') ORDER BY id")->fetchAll();foreach($logs as $log)if($log['user_id']!==null)$actors[(int)$log['user_id']]=true;
$missingUsers=[];
foreach(array_keys($actors) as $id){$s=$source->prepare('SELECT * FROM users WHERE id=?');$s->execute([$id]);$u=$s->fetch();if(!$u)throw new RuntimeException('Missing source actor.');$s=$dest->prepare('SELECT username,legacy_identity FROM users WHERE id=?');$s->execute([$id]);$old=$s->fetch();if($old){if(!$old['legacy_identity']||$old['username']!==$u['username'])throw new RuntimeException('Actor identity conflict; explicit mapping required.');}else{$missingUsers[]=$u;}}
foreach(['grievances','grievance_categories'] as $table){$ddl=$source->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];$dest->exec(str_replace('CREATE TABLE `','CREATE TABLE IF NOT EXISTS `',$ddl));}
$dest->exec('CREATE TABLE IF NOT EXISTS grm_evidence_files (grievance_id BIGINT UNSIGNED PRIMARY KEY, storage_name VARCHAR(100) NOT NULL UNIQUE,sha256 CHAR(64) NOT NULL,byte_size BIGINT UNSIGNED NOT NULL,FOREIGN KEY(grievance_id) REFERENCES grievances(id)) ENGINE=InnoDB');
function insertRow(PDO $db,string $table,array $row):void{$cols=implode(',',array_map(static fn($k)=>"`$k`",array_keys($row)));$db->prepare("INSERT INTO `$table` ($cols) VALUES (".implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));}
$directory=MIS_STORAGE.'/grm-evidence';if(!is_dir($directory))mkdir($directory,0700,true);$written=[];$manifest=[];
$dest->beginTransaction();
try{
 $archiveRole=(int)$dest->query("SELECT id FROM roles WHERE slug='legacy-archive'")->fetchColumn();
 foreach($missingUsers as $u){$u['password_hash']='!disabled-legacy-identity';$u['role_id']=$archiveRole;$u['status']='inactive';$u['last_login']=null;$u['legacy_identity']=1;insertRow($dest,'users',$u);}
 foreach(['grievances'=>$cases,'grievance_categories'=>$categories] as $table=>$rows){foreach($rows as $row)insertRow($dest,$table,$row);$copied=$dest->query("SELECT * FROM `$table`")->fetchAll();$hash=BackupService::rowHash($rows);if(BackupService::rowHash($copied)!==$hash)throw new RuntimeException('Record verification failed: '.$table);$manifest[$table]=['rows'=>count($rows),'sha256'=>$hash];}
 foreach($evidence as $file){$path=$directory.'/'.$file['storage_name'];if(file_exists($path))throw new RuntimeException('Evidence destination already exists.');if(!copy($file['source'],$path))throw new RuntimeException('Could not copy evidence.');$written[]=$path;chmod($path,0600);if(hash_file('sha256',$path)!==$file['sha256'])throw new RuntimeException('Evidence checksum mismatch.');unset($file['source']);insertRow($dest,'grm_evidence_files',$file);}
 $added=0;foreach($logs as $row){$s=$dest->prepare('SELECT * FROM legacy_audit_logs WHERE id=?');$s->execute([$row['id']]);$existing=$s->fetch();if($existing){if(BackupService::rowHash([$existing])!==BackupService::rowHash([$row]))throw new RuntimeException('Historical audit conflict.');}else{insertRow($dest,'legacy_audit_logs',$row);$added++;}}
 $dest->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug='mis-admin' AND p.slug='grm.view'");
 $manifest['evidence_files']=['count'=>count($evidence),'verified'=>true];$manifest['grm_audit']=['verified'=>count($logs),'added'=>$added];
 $dest->prepare('INSERT INTO migration_runs(source_database,manifest) VALUES(?,?)')->execute([$c['database'],json_encode($manifest,JSON_THROW_ON_ERROR)]);
 $dest->commit();$source->commit();
}catch(Throwable $e){if($dest->inTransaction())$dest->rollBack();if($source->inTransaction())$source->rollBack();foreach($written as $path)unlink($path);throw $e;}
file_put_contents(MIS_STORAGE.'/grm-migration-manifest.json',json_encode($manifest,JSON_PRETTY_PRINT));chmod(MIS_STORAGE.'/grm-migration-manifest.json',0600);
echo json_encode($manifest,JSON_PRETTY_PRINT)."\nSource unchanged. GRM migration verified.\n";
