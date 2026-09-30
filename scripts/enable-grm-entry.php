<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$db=App\Core\Database::connection();$db->beginTransaction();
try{
 $db->exec("INSERT IGNORE INTO permissions(name,slug,module,action,description)VALUES('Manage GRM cases','grm.manage','grm','manage','Create and update private GRM cases with audit history')");
 $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('mis-admin','me-officer','grm') AND p.slug IN ('grm.view','grm.manage')");
 $db->commit();echo "GRM view and entry enabled for MIS Administrator, M&E Officer and GRM Officer.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
