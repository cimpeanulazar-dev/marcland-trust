<?php
/** Public PS9 token-manager branch; real token/session behavior is also tested over HTTP. */
namespace PrestaShop\PrestaShop\Adapter {
    final class SymfonyContainer {
        public static $container;
        public static function getInstance() {return self::$container;}
    }
}
namespace {
    require __DIR__.'/admin.php';
    $manager=new class {
        public $calls=0;
        public $valid=true;
        public function isTokenValid() {++$this->calls;return $this->valid;}
    };
    \PrestaShop\PrestaShop\Adapter\SymfonyContainer::$container=new class($manager) {
        private $manager;
        public function __construct($manager) {$this->manager=$manager;}
        public function get($id) {ensure($id==='PrestaShopBundle\\Security\\Admin\\UserTokenManager','wrong validator');return $this->manager;}
    };
    $_GET['_token']='valid-masked-value-from-prior-request';
    $_POST['trust_token']=$_GET['_token'];
    // Invalid text proves the request reaches validation, without a write in this double.
    $result=$module->getContent();
    ensure(strpos($result,'Messages must be plain text')!==false,'valid prior-request token rejected');
    ensure($manager->calls===1,'framework session validator not used');
    $reads=Db::$reads;
    $_POST['trust_token']='tampered';
    ensure(strpos($module->getContent(),'Invalid admin session or security token')!==false&&Db::$reads===$reads,'mismatched token reached database');
    $_POST['trust_token']=$_GET['_token'];$manager->valid=false;
    ensure(strpos($module->getContent(),'Invalid admin session or security token')!==false,'invalid session token accepted');
    \PrestaShop\PrestaShop\Adapter\SymfonyContainer::$container=new class {public function get($id){throw new \RuntimeException('unavailable');}};
    ensure(strpos($module->getContent(),'Invalid admin session or security token')!==false,'validator failure did not fail closed');
    echo "PASS: prior-request randomized tokens use framework validation; mismatches, invalid sessions and service failures fail closed\n";
}
