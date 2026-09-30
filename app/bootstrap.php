<?php
declare(strict_types=1);
define('MIS_ROOT',dirname(__DIR__));
define('MIS_STORAGE',getenv('MIS_STORAGE_DIR')?:MIS_ROOT.'/storage');
spl_autoload_register(static function(string $class):void {
 if(str_starts_with($class,'App\\')) { $path=MIS_ROOT.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($path))require $path; }
});
date_default_timezone_set('Africa/Mogadishu');
