<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$key=getenv('MIS_BACKUP_KEY_FILE')?:MIS_ROOT.'/config/backup.key';
$directory=getenv('MIS_BACKUP_DIR')?:MIS_ROOT.'/storage/backups';
$path=App\Services\BackupService::create(App\Core\Database::connection(),$key,$directory);
echo $path.PHP_EOL;
