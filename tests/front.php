<?php
require __DIR__ . '/security.php';
require dirname(__DIR__) . '/classes/TrustFront.php';
define('_DB_PREFIX_', 'shop_test_');
final class FrontDb
{
    public $rows = [];
    public $sql;
    public function executeS($sql, $array, $cache) {
        $this->sql = $sql;
        ensure(!$cache, 'stale result caching');
        return $this->rows;
    }
}
define('_PS_VERSION_', '9.0.0');
require dirname(__DIR__) . '/upgrade/upgrade-0.2.0.php';
$upgradeModule = new class {
    public $hooks = [];
    public function registerHook($name) { $this->hooks[] = $name; return true; }
};
ensure(upgrade_module_0_2_0($upgradeModule) === true && $upgradeModule->hooks === ['actionFrontControllerSetMedia'], 'upgrade registration');
$db = new FrontDb();
$link = new class {
    public $calls = [];
    public $bad = false;
    public function getCMSLink(...$args) {
        $this->calls[] = $args;
        return $this->bad ? 'javascript:alert(1)' : 'https://shop.example/content/42?a=1&b=2';
    }
};
$context = (object) ['language'=>(object)['id'=>17], 'shop'=>(object)['id'=>4], 'link'=>$link];
$directory = sys_get_temp_dir() . '/marcland-front-' . bin2hex(random_bytes(6));
mkdir($directory); mkdir($directory . '/uploads');
$render = static function () use ($db,$context,$directory) { return MarclandTrustFront::zones($db,$context,$directory,'/modules/marclandtrust/'); };
$row = ['id_zone'=>8,'active'=>'1','text'=>'<script> & "merchant" 🚚','icon_type'=>'predefined','icon_name'=>'truck','custom_icon_path'=>null,'link_type'=>'anchor','link_target'=>'details','cms_id'=>null];
try {
    ensure($render() === [], 'empty configuration');
    ensure(strpos($db->sql, 'l.id_lang = 17') !== false && strpos($db->sql, 'cs.id_shop = 4') !== false, 'context selection');
    ensure(strpos($db->sql, 'shop_test_marclandtrust_zone') !== false && strpos($db->sql, 'ORDER BY z.position, z.id_zone') !== false, 'prefix/order');
    $db->rows = [$row]; $zones = $render();
    ensure($zones[0]['href'] === '#details' && $zones[0]['icon'] === null, 'anchor/missing icon');
    $db->rows[0]['link_target'] = 'javascript:alert(1)'; ensure($render()[0]['href'] === null, 'unsafe anchor');
    $db->rows[0]['link_type'] = 'cms'; $db->rows[0]['link_target'] = '42';
    ensure($render()[0]['href'] === null, 'unavailable CMS linked');
    $db->rows[0]['cms_id'] = '42'; ensure($render()[0]['href'] !== null, 'CMS link missing');
    ensure($link->calls[0] === [42,null,null,17,4], 'CMS language/shop not passed');
    $link->bad = true; ensure($render()[0]['href'] === null, 'unsafe generated URL');
    $path = 'uploads/' . str_repeat('a',32) . '.svg';
    $db->rows = [array_merge($row,['icon_type'=>'custom','custom_icon_path'=>$path])];
    file_put_contents($directory . '/' . $path, MarclandTrustSvg::sanitize(file_get_contents(dirname(__DIR__) . '/views/img/icons/truck.svg')));
    ensure($render()[0]['icon'] === '/modules/marclandtrust/' . $path, 'sanitized icon missing');
    file_put_contents($directory . '/' . $path, '<svg onload="alert(1)"/>'); ensure($render()[0]['icon'] === null, 'tampered icon served');
    $db->rows[0]['custom_icon_path'] = '../views/img/icons/truck.svg'; ensure($render()[0]['icon'] === null, 'traversal served');
    $db->rows = [array_merge($row,['text'=>'  ']),array_merge($row,['active'=>'0'])]; ensure($render() === [], 'empty/inactive zones shown');
    $db->rows = array_fill(0,5,$row); ensure(count($render()) === 3, 'more than three shown');
    $db->rows = false; ensure($render() === [], 'failed query not empty');
    echo "PASS: storefront context, ordering query, empty/inactive/limit filtering, CMS/anchor safety and missing/tampered icons\n";
} finally {
    @unlink($directory . '/' . $path); rmdir($directory . '/uploads'); rmdir($directory);
}
