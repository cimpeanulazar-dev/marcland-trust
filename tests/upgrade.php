<?php
define('_PS_VERSION_','9.0.0');
class Tools {public static $clears=0;public static function clearSmartyCache(){++self::$clears;}}
class Media {public static $clears=0;public static function clearCache(){++self::$clears;}}
require dirname(__DIR__).'/upgrade/upgrade-0.3.0.php';
$m=new class{public $schema=true;public $hooks=[];public function installSchema(){return $this->schema;}public function registerHook($name){$this->hooks[]=$name;return true;}};
if(!upgrade_module_0_3_0($m)||count($m->hooks)!==4||Tools::$clears!==1||Media::$clears!==1){throw new RuntimeException('Upgrade/cache registration failed');}
$m->schema=false;$m->hooks=[];
if(upgrade_module_0_3_0($m)!==false||$m->hooks!==[]||Tools::$clears!==1||Media::$clears!==1){throw new RuntimeException('Failed migration continued upgrade');}
echo "PASS: v0.3 upgrade registers all hooks and refreshes Smarty only after migration success\n";
