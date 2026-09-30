<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
foreach(['storage','storage/sessions','storage/logs','storage/backups','storage/releases'] as $dir){if(!is_dir($root.'/'.$dir)&&!mkdir($root.'/'.$dir,0700,true))throw new RuntimeException('Cannot create '.$dir);}
if(!is_file($root.'/config/backup.key')){file_put_contents($root.'/config/backup.key',random_bytes(32));chmod($root.'/config/backup.key',0600);echo "Created backup encryption key. Keep a separate, secure recovery copy.\n";}
if(!is_file($root.'/config/local.php'))echo "Create config/local.php from config/local.example.php before running the application.\n";
echo "Runtime directories ready. No existing key or configuration was overwritten.\n";
