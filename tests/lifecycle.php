<?php
/** Real MariaDB DDL with minimal Module doubles; never a real PrestaShop lifecycle test. */
$dsn = getenv('MARCLAND_TEST_DSN');
if (!$dsn || strpos($dsn, 'dbname=trust_test') === false) {
    throw new RuntimeException('Use only the disposable trust_test database.');
}
define('_PS_VERSION_', '9.0.0'); define('_DB_PREFIX_', 'audit_lifecycle_' . bin2hex(random_bytes(4)) . '_');
define('_DB_NAME_', 'trust_test');
function pSQL($value) { global $pdo; return substr($pdo->quote($value),1,-1); }
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
class Module {
    public $name,$tab,$version,$author,$displayName,$description,$_errors=[];
    public $hooks=[];
    public function __construct() {}
    public function install() {return true;}
    public function uninstall() {return true;}
    public function registerHook($name) {$this->hooks[$name]=true;return true;}
    public function l($text) {return $text;}
}
class Db {
    public static function getInstance() {return new self();}
    public function getValue($sql,...$args) {global $pdo;return $pdo->query($sql)->fetchColumn();}
    public function getRow($sql,...$args) {global $pdo;return $pdo->query($sql.' LIMIT 1')->fetch(PDO::FETCH_ASSOC);}
    public function execute($sql) {global $pdo; return $pdo->exec($sql)!==false;}
}
function verify($ok,$message) {if(!$ok){throw new RuntimeException($message);}}
require dirname(__DIR__).'/marclandtrust.php';
require dirname(__DIR__).'/upgrade/upgrade-0.2.0.php';
$m=new MarclandTrust();
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='trust_test' AND TABLE_NAME LIKE '" . _DB_PREFIX_ . "%'")->fetchColumn()===0,'requires fresh disposable prefix');
verify(upgrade_module_0_2_0($m),'schema-less 0.1.0 upgrade failed');
verify(count($m->hooks)===2,'upgrade hooks missing');
// This prefix contains no merchant fixtures yet: reset only this run's empty tables
// so fresh install is also exercised independently of the upgrade path.
$pdo->exec('DROP TABLE ' . _DB_PREFIX_ . 'marclandtrust_zone_placement');
$pdo->exec('DROP TABLE ' . _DB_PREFIX_ . 'marclandtrust_zone_lang');
$pdo->exec('DROP TABLE ' . _DB_PREFIX_ . 'marclandtrust_zone');
verify($m->install(),'fresh installation failed');
$pdo->exec("INSERT INTO " . _DB_PREFIX_ . "marclandtrust_zone (id_zone,position) VALUES (42,1)");
$pdo->exec("INSERT INTO " . _DB_PREFIX_ . "marclandtrust_zone_lang VALUES (42,17,'Existing merchant data 🚚')");
$snapshot=static function () use ($pdo) {return [$pdo->query('SELECT * FROM ' . _DB_PREFIX_ . 'marclandtrust_zone')->fetchAll(PDO::FETCH_ASSOC),$pdo->query('SELECT * FROM ' . _DB_PREFIX_ . 'marclandtrust_zone_lang')->fetchAll(PDO::FETCH_ASSOC)];};
$before=$snapshot();
verify(upgrade_module_0_2_0($m)&&upgrade_module_0_2_0($m),'repeat upgrade failed');
verify($snapshot()===$before,'upgrade changed merchant rows');
verify($m->uninstall()&&$snapshot()===$before,'uninstall deleted data');
verify($m->install()&&$snapshot()===$before,'reinstall changed IDs/data');
$engines=$pdo->query("SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='trust_test' AND TABLE_NAME LIKE '" . _DB_PREFIX_ . "%'")->fetchAll(PDO::FETCH_COLUMN);
verify($engines===['InnoDB'],'nontransactional schema');
echo "PASS: MariaDB schema-less upgrade, installation, repeat upgrade, uninstall/reinstall data and ID retention\n";

// Remove only this run's randomly named synthetic tables, never store tables.
$pdo->exec('DROP TABLE ' . _DB_PREFIX_ . 'marclandtrust_zone_placement');
$pdo->exec('DROP TABLE ' . _DB_PREFIX_ . 'marclandtrust_zone_lang');
$pdo->exec('DROP TABLE ' . _DB_PREFIX_ . 'marclandtrust_zone');
