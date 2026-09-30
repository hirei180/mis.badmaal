<?php
declare(strict_types=1);
$local=is_file(__DIR__.'/local.php')?require __DIR__.'/local.php':[];
$db=$local['database']??[];
return ['default'=>'mysql','connections'=>['mysql'=>[
'driver'=>'mysql','host'=>getenv('MIS_DB_HOST')?:($db['host']??'127.0.0.1'),
'port'=>getenv('MIS_DB_PORT')?:($db['port']??'3306'),
'database'=>getenv('MIS_DB_NAME')?:($db['database']??'badmaal_mis'),
'username'=>getenv('MIS_DB_USER')?:($db['username']??'badmaal_mis_app'),
'password'=>getenv('MIS_DB_PASS')!==false?getenv('MIS_DB_PASS'):($db['password']??''),
'charset'=>'utf8mb4']]];
