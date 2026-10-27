<?php
// Run only against an empty disposable MariaDB database named trust_test, with schema.php --sql applied.
require __DIR__ . '/security.php';
define('_DB_PREFIX_', 'test_shop_');
define('_DB_NAME_', 'trust_test');
$dsn = getenv('MARCLAND_TEST_DSN');
if (!$dsn || strpos($dsn, 'dbname=trust_test') === false) {
    throw new RuntimeException('Provide MARCLAND_TEST_DSN for the disposable trust_test database.');
}
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$other = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function pSQL($text, $html = false) {
    global $pdo;
    return substr($pdo->quote($text), 1, -1);
}
final class TestMasterDb
{
    public $pdo;
    public $failTranslations = false;
    public $onBegin;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function execute($sql) {
        $ok = $this->pdo->exec($sql) !== false;
        if ($sql === 'START TRANSACTION' && $this->onBegin) { ($this->onBegin)(); }
        return $ok;
    }
    public function executeS($sql, $array = true, $cache = true) { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    public function getValue($sql, $cache = true) { return $this->pdo->query($sql)->fetchColumn(); }
    private function assignments($data) {
        $pairs = [];
        foreach ($data as $key => $value) { $pairs[] = '`' . $key . '` = ' . ($value === null ? 'NULL' : "'" . $value . "'"); }
        return implode(', ', $pairs);
    }
    public function insert($table, $data, $null = false) {
        if ($this->failTranslations && $table === 'marclandtrust_zone_lang') { return false; }
        return $this->execute('INSERT INTO `' . _DB_PREFIX_ . $table . '` SET ' . $this->assignments($data));
    }
    public function update($table, $data, $where, $limit = 0, $null = false) {
        return $this->execute('UPDATE `' . _DB_PREFIX_ . $table . '` SET ' . $this->assignments($data) . ' WHERE ' . $where);
    }
    public function Insert_ID() { return $this->pdo->lastInsertId(); }
}
$db = new TestMasterDb($pdo);
$directory = sys_get_temp_dir() . '/marcland-icons-' . bin2hex(random_bytes(6));
mkdir($directory);
$store = new MarclandTrustConfiguration($db, $directory);
function postFrom($store): array {
    $saved = $store->load();
    $post = ['trust_signals_present' => '1', 'trust_payload_complete' => '1', 'trust_revision' => MarclandTrustConfiguration::revision($saved), 'trust_signals' => []];
    foreach ($saved['zones'] as $index => $zone) {
        $row = requestFixture()['trust_signals'][0];
        foreach (['id_zone','position','active','icon_type','icon_name','custom_icon_path','link_type','link_target'] as $field) {
            $row[$field] = (string) ($zone[$field] ?? '');
        }
        $row['icon_name'] = $row['icon_name'] ?: 'truck';
        $row['text'] = [17 => '', 29 => ''];
        foreach ($saved['translations'] as $text) {
            if ((int) $text['id_zone'] === (int) $zone['id_zone']) { $row['text'][(int) $text['id_lang']] = $text['text']; }
        }
        $post['trust_signals'][$index] = $row;
    }
    return $post;
}
$validate = static function ($post) { return MarclandTrustConfiguration::validate($post, [17,29], static function ($id) { return $id === 42; }); };
$save = static function ($post, $icons = []) use ($store, $validate) { $store->save($validate($post), $icons); };
$lock = substr('marclandtrust_' . hash('sha256', _DB_NAME_ . ':' . _DB_PREFIX_), 0, 64);
$db->onBegin = static function () use ($other, $lock) {
    ensure((string) $other->query("SELECT GET_LOCK('" . $lock . "',0)")->fetchColumn() === '0', 'second connection bypassed writer lock');
};
try {
    ensure($store->load()['zones'] === [], 'database must start empty');
    $post = postFrom($store);
    $post['trust_signals'][] = requestFixture()['trust_signals'][0];
    $post['trust_signals'][0]['text'][29] = 'Safe delivery';
    $stale = $post;
    $save($post);
    $saved = $store->load();
    ensure(count($saved['zones']) === 1 && (int) $saved['zones'][0]['id_zone'] > 0, 'generated zone ID');
    ensure(count($saved['translations']) === 2 && $saved['translations'][0]['text'] === $post['trust_signals'][0]['text'][17], 'Unicode roundtrip');
    rejects(static function () use ($save,$stale) { $save($stale); }, 'stale save accepted');
    $post = postFrom($store); $post['trust_signals'][0]['id_zone'] = '9999';
    rejects(static function () use ($save,$post) { $save($post); }, 'unknown ID accepted');
    $post = postFrom($store); $post['trust_signals'] = [];
    rejects(static function () use ($save,$post) { $save($post); }, 'implicit deletion accepted');
    $post = postFrom($store); $id = $post['trust_signals'][0]['id_zone'];
    $post['trust_signals'][0]['active'] = '0'; $post['trust_signals'][0]['text'][29] = '';
    $save($post);
    ensure((string) $store->load()['zones'][0]['active'] === '0' && count($store->load()['translations']) === 1, 'update/empty translation removal');
    $post = postFrom($store);
    for ($index = 1; $index < 3; ++$index) {
        $row = requestFixture()['trust_signals'][0]; $row['position'] = (string) ($index + 1); $post['trust_signals'][] = $row;
    }
    $save($post);
    ensure(count($store->load()['zones']) === 3, 'three zones saved including inactive');
    $before = $store->load();
    $post = postFrom($store); $post['trust_signals'][0]['text'][17] = 'must roll back';
    $post['trust_signals'][0]['icon_type'] = 'custom';
    $svg = MarclandTrustSvg::sanitize(file_get_contents(dirname(__DIR__) . '/views/img/icons/truck.svg'));
    $db->failTranslations = true;
    try { $save($post, [0 => $svg]); throw new LogicException('write failure not surfaced'); }
    catch (RuntimeException $expected) { ensure(!($expected instanceof LogicException), 'write failure not surfaced'); }
    $db->failTranslations = false;
    ensure($store->load() === $before, 'failed write changed database');
    ensure(glob($directory . '/*.svg') === [], 'rollback left new icon');
    $save($post, [0 => $svg]);
    $oldPath = $store->load()['zones'][0]['custom_icon_path'];
    ensure(is_file($directory . '/' . basename($oldPath)), 'sanitized custom icon missing');
    $post = postFrom($store); $save($post); // Existing custom icon without a replacement.
    ensure($store->load()['zones'][0]['custom_icon_path'] === $oldPath, 'existing icon lost');
    $post = postFrom($store); $save($post, [0 => $svg]);
    ensure(!is_file($directory . '/' . basename($oldPath)), 'replaced icon not retired');
    $post = postFrom($store); $post['trust_signals'][1]['custom_icon_path'] = $store->load()['zones'][0]['custom_icon_path'];
    rejects(static function () use ($save,$post) { $save($post); }, 'cross-zone icon path accepted');
    $post = postFrom($store); $custom = $post['trust_signals'][0]['custom_icon_path'];
    $post['trust_deleted'] = array_column($post['trust_signals'], 'id_zone'); $post['trust_signals'] = [];
    $save($post);
    ensure($store->load() === ['zones'=>[], 'translations'=>[]], 'explicit deletion did not remove all rows');
    ensure(!is_file($directory . '/' . basename($custom)), 'deleted icon not retired');
    echo "PASS: MariaDB create/update/delete, Unicode, lock contention, stale/unknown IDs, rollback and icon lifecycle\n";
} finally {
    foreach (glob($directory . '/*.svg') as $file) { unlink($file); }
    rmdir($directory);
}
