<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$config=require MIS_ROOT.'/config/database.php';$c=$config['connections'][$config['default']];
$db=getenv('MIS_MIGRATION_USER')?new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",getenv('MIS_MIGRATION_USER'),getenv('MIS_MIGRATION_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]):App\Core\Database::connection();
// Additive and safe to rerun; existing register values are preserved.
$tables=[
 'mis_contracts'=>['category'=>'VARCHAR(120) NOT NULL DEFAULT \'\'','procurement_status'=>'VARCHAR(60) NOT NULL DEFAULT \'\''],
 'grievances'=>['gender'=>'VARCHAR(60) NOT NULL DEFAULT \'\'','contact'=>'VARCHAR(190) NOT NULL DEFAULT \'\'','site_location'=>'VARCHAR(255) NOT NULL DEFAULT \'\'','grievance_type'=>'VARCHAR(120) NOT NULL DEFAULT \'\'','assigned_to'=>'VARCHAR(180) NOT NULL DEFAULT \'\'','date_received'=>'DATE NULL','date_resolved'=>'DATE NULL']
];
foreach($tables as $table=>$columns){
 $existing=$db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
 foreach($columns as $column=>$definition){
  if(!in_array($column,$existing,true))$db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
 }
}
// Administrators can prepare procurement drafts; self-approval remains prohibited.
$db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug='mis-admin' AND p.slug='mis.contracts.enter'");
echo "Procurement and GRM register fields enabled.\n";
