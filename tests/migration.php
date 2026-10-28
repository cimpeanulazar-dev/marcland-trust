<?php
/** Real v0.2 schema migration on a random prefix in disposable trust_test only. */
require dirname(__DIR__) . '/classes/TrustSchema.php';
$dsn=getenv('MARCLAND_TEST_DSN');
if(!$dsn||strpos($dsn,'dbname=trust_test')===false){throw new RuntimeException('Use disposable trust_test only.');}
define('_DB_NAME_','trust_test');define('_DB_PREFIX_','audit_migrate_'.bin2hex(random_bytes(5)).'_');
$pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function pSQL($value){global $pdo;return substr($pdo->quote($value),1,-1);}
function verifyMigration($ok,$message){if(!$ok){throw new RuntimeException($message);}}
$db=new class($pdo){
    private $pdo;public $fail=false;
    public function __construct($pdo){$this->pdo=$pdo;}
    public function execute($sql){if($this->fail&&strpos($sql,'INSERT IGNORE')===0){return false;}return $this->pdo->exec($sql)!==false;}
    public function getValue($sql,...$args){return $this->pdo->query($sql)->fetchColumn();}
    public function getRow($sql,...$args){return $this->pdo->query($sql.' LIMIT 1')->fetch(PDO::FETCH_ASSOC);}
};
$z=_DB_PREFIX_.'marclandtrust_zone';$l=$z.'_lang';$p=$z.'_placement';
try{
    $pdo->exec("CREATE TABLE `$z` (id_zone INT UNSIGNED NOT NULL AUTO_INCREMENT,position TINYINT UNSIGNED NOT NULL DEFAULT 0,active TINYINT UNSIGNED NOT NULL DEFAULT 1,icon_type VARCHAR(16) NOT NULL DEFAULT 'predefined',icon_name VARCHAR(128) DEFAULT NULL,custom_icon_path VARCHAR(255) DEFAULT NULL,link_type VARCHAR(16) NOT NULL DEFAULT 'none',link_target VARCHAR(2048) DEFAULT NULL,PRIMARY KEY(id_zone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE `$l` (id_zone INT UNSIGNED NOT NULL,id_lang INT UNSIGNED NOT NULL,text TEXT NOT NULL,PRIMARY KEY(id_zone,id_lang)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT INTO `$z` VALUES(101,0,1,'predefined','truck',NULL,'cms','42'),(207,2,1,'custom',NULL,'uploads/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.svg','anchor','product-details'),(309,3,0,'predefined','lock',NULL,'none',NULL)");
    $pdo->exec("INSERT INTO `$l` VALUES(101,17,'Message 🚚'),(101,29,'Traduction'),(207,17,'Second'),(309,29,'Inactive')");
    $content=static function()use($pdo,$z,$l){return[$pdo->query("SELECT * FROM `$z` ORDER BY id_zone")->fetchAll(PDO::FETCH_ASSOC),$pdo->query("SELECT * FROM `$l` ORDER BY id_zone,id_lang")->fetchAll(PDO::FETCH_ASSOC)];};
    $before=$content();verifyMigration(MarclandTrustSchema::migrate($db),'first migration failed');
    verifyMigration($content()===$before,'legacy IDs/content/SVG references changed');
    $rows=$pdo->query("SELECT * FROM `$p` ORDER BY id_zone")->fetchAll(PDO::FETCH_ASSOC);
    verifyMigration(count($rows)===3&&array_unique(array_column($rows,'placement'))===['product'],'unrequested homepage/checkout exposure');
    verifyMigration($rows[0]['link_type']==='cms'&&(string)$rows[0]['link_target']==='42'&&(int)$rows[0]['position']===0,'CMS/zero position lost');
    verifyMigration($rows[1]['link_type']==='anchor'&&$rows[1]['link_target']==='product-details','anchor lost');
    $column=$pdo->query("SHOW COLUMNS FROM `$z` LIKE 'position'")->fetch(PDO::FETCH_ASSOC);
    verifyMigration(stripos($column['Type'],'tinyint')===false,'editor order not widened');
    $pdo->exec("UPDATE `$p` SET enabled=0,position=300,link_type='none',link_target=NULL WHERE id_zone=101");
    $pdo->exec("INSERT INTO `$p` VALUES(101,'home',1,7,'none',NULL)");
    $choices=$pdo->query("SELECT * FROM `$p` ORDER BY id_zone,placement")->fetchAll(PDO::FETCH_ASSOC);
    verifyMigration(MarclandTrustSchema::migrate($db)&&MarclandTrustSchema::migrate($db),'repeat migration failed');
    verifyMigration($choices===$pdo->query("SELECT * FROM `$p` ORDER BY id_zone,placement")->fetchAll(PDO::FETCH_ASSOC),'repeat reset edited placements');
    $pdo->exec("INSERT INTO `$z`(id_zone,position) VALUES(999,4)");
    $db->fail=true;verifyMigration(!MarclandTrustSchema::migrate($db),'failure not surfaced');$db->fail=false;
    verifyMigration(MarclandTrustSchema::migrate($db),'partial failure retry failed');
    verifyMigration((int)$pdo->query("SELECT COUNT(*) FROM `$p` WHERE id_zone=999 AND placement='product'")->fetchColumn()===1,'retry did not initialize missing product');
    echo "PASS: v0.2 IDs/translations/SVG/CMS/anchors retained, product-only migration, widened ordering, repeat safety and partial-failure retry\n";
}finally{foreach([$p,$l,$z]as$table){$pdo->exec("DROP TABLE IF EXISTS `$table`");}}
