<?php
declare(strict_types=1);
// CLI only, one-time import into an EMPTY, independently provisioned MIS database.
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$options=getopt('',['source-config:','target:','provision']);
$sourceFile=$options['source-config']??'';
if(!is_file($sourceFile))throw new RuntimeException('Pass --source-config=/path/to/website/config/database.php');
$config=require $sourceFile;$c=$config['connections'][$config['default']];
$target=$options['target']??'badmaal_mis';
if(!preg_match('/^[a-zA-Z0-9_]+$/',$target)||$target===$c['database'])throw new RuntimeException('Use a different, valid target database.');
$connect=static fn($db)=>new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$db;charset=utf8mb4",$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$source=$connect($c['database']);
if(isset($options['provision']))$source->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$dest=getenv('MIS_IMPORT_TARGET_USER')
 ? new PDO('mysql:host='.(getenv('MIS_IMPORT_TARGET_HOST')?:$c['host']).';port='.(getenv('MIS_IMPORT_TARGET_PORT')?:$c['port']).';dbname='.$target.';charset=utf8mb4',getenv('MIS_IMPORT_TARGET_USER'),getenv('MIS_IMPORT_TARGET_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC])
 : $connect($target);
if($dest->query('SHOW TABLES')->fetch())throw new RuntimeException('Import refuses a nonempty target. No records have been overwritten.');
$tables=['mis_indicators','mis_indicator_periods','mis_contracts','mis_financial_records','mis_publications','mis_submissions','mis_submission_events'];
$ddlTables=['roles','permissions','role_permissions','users',...$tables,'audit_logs'];
foreach($ddlTables as $table){$ddl=$source->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];$dest->exec($ddl);}
$dest->exec("ALTER TABLE users ADD must_change_password TINYINT NOT NULL DEFAULT 1, ADD session_version INT NOT NULL DEFAULT 1, ADD legacy_identity TINYINT NOT NULL DEFAULT 0");
$dest->exec("CREATE TABLE settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB");
$dest->exec("INSERT INTO settings VALUES ('organisation','BADMAAL'),('support_email',''),('session_minutes','60')");
$dest->exec("CREATE TABLE migration_runs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_database VARCHAR(100),manifest JSON NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$dest->exec('CREATE TABLE legacy_audit_logs LIKE audit_logs');
$roleConfig=require MIS_ROOT.'/config/roles.php';$roleIds=[];$permIds=[];
foreach($roleConfig as $slug=>[$name,$perms]){
 $s=$dest->prepare('INSERT INTO roles(name,slug,description)VALUES(?,?,?)');$s->execute([$name,$slug,'Independent MIS role']);$roleIds[$slug]=(int)$dest->lastInsertId();
 foreach($perms as $permission){
  if(!isset($permIds[$permission])){[$module,$action]=explode('.',$permission,2);$s=$dest->prepare('INSERT INTO permissions(name,slug,module,action)VALUES(?,?,?,?)');$s->execute([$permission,$permission,$module,$action]);$permIds[$permission]=(int)$dest->lastInsertId();}
  $dest->prepare('INSERT INTO role_permissions(role_id,permission_id)VALUES(?,?)')->execute([$roleIds[$slug],$permIds[$permission]]);
 }
}
// A consistent snapshot preserves IDs, decimal strings, timestamps, JSON payloads and relationships.
$source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$dest->beginTransaction();$manifest=[];
function insertRow(PDO $db,string $table,array $row):void {
 $columns=implode(',',array_map(static fn($key)=>"`$key`",array_keys($row)));
 $db->prepare("INSERT INTO `$table` ($columns) VALUES (".implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
}
try{
 foreach($source->query('SELECT * FROM users ORDER BY id')->fetchAll() as $user){
  // Never copy credentials, sessions or website roles. Attribution only.
  $user['password_hash']='!disabled-legacy-identity';$user['role_id']=$roleIds['legacy-archive'];$user['status']='inactive';$user['last_login']=null;$user['legacy_identity']=1;
  insertRow($dest,'users',$user);
 }
 foreach([...$tables,'audit_logs'] as $table){
  $rows=$source->query("SELECT * FROM `$table` ORDER BY ".($table==='mis_publications'?'entity_key':'id'))->fetchAll();
  $targetTable=$table==='audit_logs'?'legacy_audit_logs':$table;
  foreach($rows as $row)insertRow($dest,$targetTable,$row);
  $copied=$dest->query("SELECT * FROM `$targetTable` ORDER BY ".($table==='mis_publications'?'entity_key':'id'))->fetchAll();
  $hash=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
  if($hash!==hash('sha256',json_encode($copied,JSON_THROW_ON_ERROR)))throw new RuntimeException("Verification failed for $table");
  $manifest[$table]=['target'=>$targetTable,'rows'=>count($rows),'sha256'=>$hash];
 }
 $dest->prepare('INSERT INTO migration_runs(source_database,manifest) VALUES(?,?)')->execute([$c['database'],json_encode($manifest,JSON_THROW_ON_ERROR)]);
 $dest->commit();$source->commit();
}catch(Throwable $e){if($dest->inTransaction())$dest->rollBack();if($source->inTransaction())$source->rollBack();throw $e;}
$credentials=[];
foreach(['mis.admin'=>['MIS Administrator','mis-admin'],'me.officer'=>['M&E Officer','me-officer']] as $login=>[$name,$role]){
 $password=bin2hex(random_bytes(12));
 $dest->prepare('INSERT INTO users(full_name,username,email,password_hash,role_id,status)VALUES(?,?,?,?,?,?)')->execute([$name,$login,$login.'@mis.invalid',password_hash($password,PASSWORD_DEFAULT),$roleIds[$role],'active']);
 $credentials[$login]=$password;
}
if(isset($options['provision'])){
 $appPassword=bin2hex(random_bytes(24));
 // Local provisioning account is dedicated to this database, never the website.
 $dest->exec("CREATE USER 'badmaal_mis_app'@'localhost' IDENTIFIED BY ".$dest->quote($appPassword));
 $dest->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `$target`.* TO 'badmaal_mis_app'@'localhost'");
 $local=['environment'=>'local','base_url'=>'http://localhost:8093','database'=>['host'=>'localhost','port'=>$c['port'],'database'=>$target,'username'=>'badmaal_mis_app','password'=>$appPassword]];
 file_put_contents(MIS_ROOT.'/config/local.php',"<?php\nreturn ".var_export($local,true).";\n");chmod(MIS_ROOT.'/config/local.php',0600);
}
file_put_contents(MIS_ROOT.'/storage/initial-credentials.txt',"LOCAL INITIAL ACCOUNTS — change passwords on first sign-in.\n".implode("\n",array_map(static fn($user,$pass)=>"$user: $pass",array_keys($credentials),$credentials))."\n");chmod(MIS_ROOT.'/storage/initial-credentials.txt',0600);
file_put_contents(MIS_ROOT.'/storage/migration-manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Verified import completed into $target. Source database unchanged.\n";
foreach($manifest as $table=>$result)echo "$table: {$result['rows']} records, checksum matched.\n";
echo "New account credentials: storage/initial-credentials.txt (private).\n";
