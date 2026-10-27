<?php
// Lightweight controller-boundary doubles, not a real PrestaShop installation.
require __DIR__ . '/security.php';
define('_PS_VERSION_', '9.0.0'); define('_DB_PREFIX_', 'test_shop_');
class Module
{
    public $name, $tab, $version, $author, $displayName, $description, $context, $_path = '/modules/marclandtrust/';
    public $allowed = true;
    public function __construct() {}
    public function getPermission($action) { return $action === 'configure' && $this->allowed; }
    public function l($text) { return $text; }
    public function displayError($text) { return 'ERROR:' . $text; }
    public function displayConfirmation($text) { return 'OK:' . $text; }
    public function fetch($template) { return 'FORM'; }
}
class Tools
{
    public static function isSubmit($key) { return isset($_POST[$key]); }
    public static function getValue($key) { return $_POST[$key] ?? null; }
    public static function getAdminTokenLite($controller) { return 'test-admin-csrf'; }
    public static function safeOutput($text) { return htmlspecialchars($text, ENT_QUOTES); }
}
class Language { public static function getLanguages($active) { return [['id_lang'=>17,'name'=>'Romanian'],['id_lang'=>29,'name'=>'English']]; } }
class CMS { public static function getCMSPages(...$args) { return []; } }
class Db
{
    public static $reads = 0;
    public static function getInstance($master) { ensure($master === true, 'not master'); return new self(); }
    public function executeS(...$args) { ++self::$reads; return []; }
}
class TestSmarty { public $data; public function assign($data) { $this->data = $data; } }
require dirname(__DIR__) . '/marclandtrust.php';
$module = new MarclandTrust();
$smarty = new TestSmarty();
$module->context = (object) [
    'employee' => new class { public function isLoggedBack() { return true; } },
    'controller' => new class { public function addJS($path) {} public function addCSS($path) {} },
    'link' => new class { public function getAdminLink(...$args) { return '/admin/configure?token=test'; } },
    'smarty' => $smarty, 'shop' => (object) ['id'=>4], 'language' => (object) ['id'=>17],
];
$_POST = requestFixture() + ['submitMarclandTrust'=>'1','trust_token'=>'wrong'];
ensure(strpos($module->getContent(), 'ERROR:') === 0 && Db::$reads === 0, 'CSRF did not reject before database access');
$_POST['trust_token'] = 'test-admin-csrf'; $module->allowed = false;
ensure(strpos($module->getContent(), 'ERROR:') === 0 && Db::$reads === 0, 'permission did not reject before database access');
$module->allowed = true;
$_POST['trust_signals'][0]['text'][17] = str_repeat('ă', 501); // Invalid but redisplayed, not discarded.
$result = $module->getContent();
ensure(strpos($result, 'ERROR:') === 0 && strpos($result, 'FORM') !== false, 'no error/form redisplay');
$data = json_decode($smarty->data['trust_editor_data'], true);
ensure($data['zones'][0]['texts'][17] === $_POST['trust_signals'][0]['text'][17], 'submitted translation lost');
ensure($data['zones'][0]['id_zone'] === null && $smarty->data['trust_revision'] === $_POST['trust_revision'], 'submission identity/revision lost');
echo "PASS: admin CSRF, configure permission, master selection and validation-error redisplay\n";
