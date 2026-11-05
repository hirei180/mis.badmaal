<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$db=App\Core\Database::connection();$db->beginTransaction();
try{
    $db->exec("INSERT IGNORE INTO permissions(name,slug,module,action,description) VALUES('Approve own submissions','mis.approve.own','mis','approve_own','Approve and publish submissions entered by the same user')");
    $added=$db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('mis-admin','me-officer') AND p.slug IN ('mis.approve','mis.approve.own','grm.view','grm.manage')");
    if($added)$db->prepare('INSERT INTO audit_logs(username_snapshot,action,entity_type,details)VALUES(?,?,?,?)')->execute(['deployment','staff_tools_enabled','roles',json_encode(['roles'=>['mis-admin','me-officer'],'permissions'=>['mis.approve','mis.approve.own','grm.view','grm.manage']],JSON_THROW_ON_ERROR)]);
    $db->commit();echo "Both staff roles can approve, including their own submissions, and archive/restore GRM cases.\n";
}catch(Throwable $ex){if($db->inTransaction())$db->rollBack();throw $ex;}
