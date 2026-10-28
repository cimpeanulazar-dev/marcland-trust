<?php
// Standalone lifecycle checks; this does not boot PrestaShop.
define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'test_shop_');
define('_DB_NAME_', 'trust_test');
function pSQL($value) { return addslashes($value); }
class Module
{
    public $name, $tab, $version, $author, $displayName, $description;
    public $_errors = [];
    public static $parentInstall = true;
    public static $hook = true;
    public static $uninstalls = 0;
    public static $registeredHooks = [];
    public function __construct() {}
    public function install() { return self::$parentInstall; }
    public function uninstall() { ++self::$uninstalls; return true; }
    public function registerHook($hook) {
        self::$registeredHooks[] = $hook;
        check(in_array($hook, ['displayProductAdditionalInfo', 'actionFrontControllerSetMedia', 'displayHome', 'displayCheckoutSummaryTop'], true), 'supported hook');
        return self::$hook;
    }
    public function l($message) { return $message; }
}
class Db
{
    public static $queries = [];
    public static $failAt = 0;
    public static $throw = false;
    public static function getInstance() { return new self(); }
    public function getValue(...$args) { return 1; }
    public function getRow($sql,...$args) { check(strpos($sql,'SELECT')===0,'getRow must accept core LIMIT 1 suffix'); return ['Type'=>'int unsigned']; }
    public function execute($sql) {
        self::$queries[] = $sql;
        if (count(self::$queries) === self::$failAt) {
            if (self::$throw) { throw new RuntimeException('database failure'); }
            return false;
        }
        return true;
    }
}
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
}
require dirname(__DIR__) . '/marclandtrust.php';
$module = new MarclandTrust();
check($module->install(), 'install success');
check(Module::$registeredHooks === ['displayProductAdditionalInfo', 'actionFrontControllerSetMedia', 'displayHome', 'displayCheckoutSummaryTop'], 'both frontend hooks registered');
$schema = Db::$queries;
check(count($schema) === 4, 'three tables and additive backfill');
foreach (array_slice($schema, 0, 3) as $sql) {
    check(strpos($sql, '`test_shop_marclandtrust_') !== false, 'configured prefix');
    check(strpos($sql, 'IF NOT EXISTS') !== false, 'idempotent DDL');
    check(strpos($sql, 'ENGINE=InnoDB') !== false, 'transactional tables');
}
check($module->installSchema(), 'repeat schema setup');
check($module->uninstall(), 'uninstall');
check(count(Db::$queries) === 8, 'uninstall does not execute SQL');
foreach ([false, true] as $throws) {
    foreach ([1, 2, 3, 4] as $failAt) {
        Db::$queries = []; Db::$failAt = $failAt; Db::$throw = $throws;
        $failed = new MarclandTrust();
        $before = Module::$uninstalls;
        check(!$failed->install(), 'failed DDL fails installation');
        check(count($failed->_errors) > 0, 'error reported');
        check(Module::$uninstalls === $before + 1, 'registration rollback');
        check(count(Db::$queries) === $failAt, 'no destructive DDL cleanup');
    }
}
Db::$queries = []; Db::$failAt = 0; Module::$hook = false;
check(!(new MarclandTrust())->install(), 'hook failure');
Module::$parentInstall = false; Db::$queries = [];
check(!(new MarclandTrust())->install(), 'parent failure');
check(Db::$queries === [], 'no DDL after parent failure');
if (in_array('--sql', $argv, true)) {
    echo implode(";\n", $schema) . ";\n";
} else {
    echo "PASS: schema and lifecycle checks (standalone PrestaShop doubles)\n";
}
