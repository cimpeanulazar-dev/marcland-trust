<?php
require dirname(__DIR__) . '/classes/TrustConfiguration.php';
function ensure($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function rejects(callable $operation, $message) {
    try { $operation(); } catch (InvalidArgumentException $expected) { return; }
    throw new RuntimeException($message);
}
function requestFixture(): array {
    return [
        'trust_signals_present' => '1', 'trust_payload_complete' => '1', 'trust_revision' => str_repeat('a', 64),
        'trust_signals' => [[
            'id_zone' => '', 'position' => '1', 'active' => '1', 'icon_type' => 'predefined',
            'icon_name' => 'truck', 'custom_icon_path' => '', 'link_type' => 'none', 'link_target' => '',
            'upload_selected' => '0', 'text' => [17 => 'Livrare sigură 🚚 & "rapidă"', 29 => ''],
        ]],
    ];
}
$validate = static function ($post) { return MarclandTrustConfiguration::validate($post, [17,29], static function ($id) { return $id === 42; }); };
$valid = $validate(requestFixture());
ensure($valid['zones'][0]['id_zone'] === null, 'new zone has no ID');
ensure(!isset($valid['zones'][0]['texts'][29]), 'empty translation removed');
foreach ([
    ['id_zone', 'tmp-1'], ['id_zone', '0'], ['id_zone', '4294967296'], ['active', 'yes'],
    ['position', '2'], ['icon_type', 'script'], ['icon_name', '../truck'], ['custom_icon_path', '../file.svg'],
    ['link_type', 'url'], ['link_target', 'javascript:alert(1)'], ['text', [17 => '<b>HTML</b>', 29 => '']],
    ['text', [17 => str_repeat('ă', 501), 29 => '']], ['text', [99 => 'unknown']], ['text', [17 => 'truncated']],
    ['text', [17 => "invalid\xFF", 29 => '']], ['text', [17 => "control\x00", 29 => '']],
] as [$key, $value]) {
    $post = requestFixture(); $post['trust_signals'][0][$key] = $value;
    rejects(static function () use ($validate, $post) { $validate($post); }, 'invalid field accepted: ' . $key);
}
$post = requestFixture(); unset($post['trust_payload_complete']);
rejects(static function () use ($validate,$post) { $validate($post); }, 'truncated payload accepted');
$post = requestFixture(); $post['trust_signals'][0]['unexpected'] = 'value';
rejects(static function () use ($validate,$post) { $validate($post); }, 'unknown field accepted');
$post = requestFixture(); $post['trust_signals'] = array_fill(0, 4, $post['trust_signals'][0]);
rejects(static function () use ($validate,$post) { $validate($post); }, 'four zones accepted');
$post = requestFixture(); $post['trust_signals'][0]['link_type'] = 'anchor'; $post['trust_signals'][0]['link_target'] = 'product-details';
$validate($post);
$post['trust_signals'][0]['link_target'] = 'javascript:bad';
rejects(static function () use ($validate,$post) { $validate($post); }, 'bad anchor accepted');
$post['trust_signals'][0]['link_type'] = 'cms'; $post['trust_signals'][0]['link_target'] = '42'; $validate($post);
$post['trust_signals'][0]['link_target'] = '43';
rejects(static function () use ($validate,$post) { $validate($post); }, 'unknown CMS accepted');
$post = requestFixture(); $post['trust_signals'][0]['id_zone'] = '8'; $post['trust_deleted'] = ['8'];
rejects(static function () use ($validate,$post) { $validate($post); }, 'conflicting delete accepted');
$post = requestFixture(); $post['trust_signals'][0]['id_zone'] = '8';
$post['trust_signals'][1] = $post['trust_signals'][0]; $post['trust_signals'][1]['position'] = '2';
rejects(static function () use ($validate,$post) { $validate($post); }, 'duplicate ID accepted');
foreach (glob(dirname(__DIR__) . '/views/img/icons/*.svg') as $icon) {
    $clean = MarclandTrustSvg::sanitize(file_get_contents($icon));
    ensure(strpos($clean, '<svg') === 0, 'bundled icon rejected');
    ensure(MarclandTrustSvg::sanitize($clean) === $clean, 'sanitizer not stable');
}
$bad = [
    '<svg><script>alert(1)</script></svg>', '<svg onload="alert(1)"/>',
    '<svg><foreignObject/></svg>', '<svg><image href="https://example.com/a"/></svg>',
    '<svg><use href="#x"/></svg>', '<svg><style>svg{fill:red}</style></svg>',
    '<svg style="fill:red"/>', '<svg fill="url(https://example.com/a)"/>',
    '<svg><animate attributeName="href"/></svg>', '<svg xmlns="https://example.com"/>',
    '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>',
    '<?xml-stylesheet href="https://example.com"?><svg/>', '<svg><?bad x?></svg>',
    '<svg xmlns:x="https://example.com"><x:path/></svg>', '<svg><title><script/></title></svg>',
    '<html/>', '<svg>', str_repeat('x', 102401),
    mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE svg [<!ENTITY x "entity">]><svg>&x;</svg>', 'UTF-16', 'UTF-8'),
    '<svg>' . str_repeat('<g>', 34) . str_repeat('</g>',34) . '</svg>',
];
foreach ($bad as $xml) {
    rejects(static function () use ($xml) { MarclandTrustSvg::sanitize($xml); }, 'unsafe SVG accepted');
}
rejects(static function () { MarclandTrustConfiguration::uploads(['error' => 'bad'], []); }, 'bad upload structure');
rejects(static function () { MarclandTrustConfiguration::uploads([
    'name' => ['bad.svg'], 'tmp_name' => ['/tmp/not-an-http-upload'], 'error' => [UPLOAD_ERR_OK], 'size' => [5], 'type' => ['image/svg+xml'],
], [['icon_type'=>'custom','upload_selected'=>'1']]); }, 'non-uploaded file accepted');
rejects(static function () { MarclandTrustConfiguration::uploads([], [['icon_type'=>'custom','upload_selected'=>'1']]); }, 'missing selected upload accepted');
echo "PASS: validation, identity, Unicode, translation policy and SVG security cases\n";
