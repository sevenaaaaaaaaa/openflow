<?php
declare(strict_types=1);
/**
 * 桌宠市场（松萝纸偶形象 .lianapet）契约测试
 *
 *   php tests/pet_market_test.php
 *
 * 全程离线，验证：
 *   1) 结构校验：只收 zip、只含图片与 JSON、无越界路径、renderer=puppet、rights=shareable、有平时图、id 合法
 *   2) 投稿：入队为 pending、文件与预览图落盘、每人每日限额、他人不能占用同一形象 id、审核中不能再投
 *   3) 审核：通过即上架、新版本通过后旧版本 superseded 且下载量累计、驳回附理由、下架
 *   4) 公开目录：只含上架的、免费给下载地址、付费只给详情页、不泄露作者 id
 *   5) 付费：上架时同步 pet 商品；未购买不能下载，作者本人可以
 *   6) 生态市场：pet 出现在 mkt_assets，待审核的技能不再混进市场
 */

$tmp = sys_get_temp_dir() . '/of-pets-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_once __DIR__ . '/../lib/MarketplaceSystem.php';
require_once __DIR__ . '/../lib/PetMarket.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

function png(): string
{
    $im = imagecreatetruecolor(40, 80);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 80));
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

/** 造一个 .lianapet；$mutate 可以改写清单或追加文件 */
function make_pack(string $id, array $over = [], array $extraFiles = []): string
{
    global $tmp;
    $pet = ['renderer' => 'puppet', 'puppet' => ['format' => 1, 'rights' => 'shareable', 'images' => ['idle' => ['src' => 'idle.png']]]];
    $pet = array_replace_recursive($pet, $over['pet'] ?? []);
    $plugin = ['id' => $id, 'name' => '测试形象', 'version' => '1.0.0', 'kind' => 'pet', 'description' => '包内描述'];
    $path = $tmp . '/' . bin2hex(random_bytes(4)) . '.lianapet';
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE);
    $z->addFromString('pet.json', json_encode($pet));
    $z->addFromString('plugin.json', json_encode($plugin));
    if (empty($over['no_idle'])) $z->addFromString('idle.png', png());
    foreach ($extraFiles as $name => $body) $z->addFromString($name, $body);
    $z->close();
    return $path;
}

$alice = ['id' => 'm_alice', 'name' => 'Alice'];
$bob = ['id' => 'm_bob', 'name' => 'Bob'];

echo "结构校验\n";
$bad = $tmp . '/not-zip.lianapet';
file_put_contents($bad, 'hello');
ok(pet_inspect($bad)['ok'] === false, '非 zip 被拒');
ok(pet_inspect(make_pack('puppet-a', [], ['run.sh' => 'rm -rf /']))['ok'] === false, '含脚本文件被拒');
ok(str_contains((string) (pet_inspect(make_pack('puppet-a', [], ['../evil.png' => png()]))['error'] ?? ''), '路径'), '越界路径被拒');
ok(pet_inspect(make_pack('puppet-a', ['pet' => ['renderer' => 'vrm']]))['ok'] === false, '非纸偶被拒');
ok(str_contains((string) (pet_inspect(make_pack('puppet-a', ['pet' => ['puppet' => ['rights' => 'personal']]]))['error'] ?? ''), '原创'), '未声明原创被拒，且提示怎么改');
ok(pet_inspect(make_pack('puppet-a', ['no_idle' => true]))['ok'] === false, '缺平时图被拒');
ok(pet_inspect(make_pack('pet-vrm-sample'))['ok'] === false, '非 puppet- 前缀的 id 被拒');
$good = pet_inspect(make_pack('puppet-fox'));
ok($good['ok'] === true && $good['pack_id'] === 'puppet-fox', '合法形象包通过');
ok(@getimagesizefromstring((string) ($good['preview'] ?? '')) !== false, '生成了预览图');

echo "投稿\n";
$r1 = pet_submit($alice, make_pack('puppet-fox'), ['price' => 0, 'license' => 'CC-BY-4.0', 'tags' => '动物，原创']);
ok($r1['ok'] === true, '投稿成功', (string) ($r1['error'] ?? ''));
$sub1 = pet_sub_find((string) ($r1['sub_id'] ?? ''));
ok(($sub1['status'] ?? '') === 'pending', '投稿进入待审核');
ok(is_file(pet_file_path($sub1)) && is_file(pet_preview_path($sub1)), '文件与预览图落盘');
ok(($sub1['tags'] ?? []) === ['动物', '原创'], '中文逗号也能分隔标签');
ok(pet_submit($alice, make_pack('puppet-fox'), [])['ok'] === false, '审核中不能再投同一形象');
ok(str_contains((string) (pet_submit($bob, make_pack('puppet-fox'), [])['error'] ?? ''), '其他作者'), '他人不能占用同一形象 id');
ok(pet_submit($alice, make_pack('puppet-x'), ['license' => 'WTFPL'])['ok'] === false, '许可证不在白名单被拒');
ok(pet_find('puppet-fox') === null, '未审核的不上架');

echo "审核\n";
ok(pet_review((string) $r1['sub_id'], 'approve')['ok'] === true, '通过');
ok(pet_find('puppet-fox') !== null, '通过后上架');
pet_hit_download((string) $r1['sub_id']);
pet_hit_download((string) $r1['sub_id']);
ok(pet_submit($alice, make_pack('puppet-fox'), [])['ok'] === false, '与上架版本完全一样的内容被拒');
$r2 = pet_submit($alice, make_pack('puppet-fox', [], ['blink.png' => png()]), ['price' => 0]);
ok($r2['ok'] === true && (pet_sub_find((string) $r2['sub_id'])['revision'] ?? 0) === 2, '新版本 revision 递增');
ok(pet_find('puppet-fox')['sub_id'] === $r1['sub_id'], '新版本审核期间旧版本继续上架');
pet_review((string) $r2['sub_id'], 'approve');
ok(pet_find('puppet-fox')['sub_id'] === $r2['sub_id'], '新版本通过后替换旧版本');
ok((pet_sub_find((string) $r1['sub_id'])['status'] ?? '') === 'superseded', '旧版本标记为 superseded');
ok((int) (pet_find('puppet-fox')['downloads'] ?? 0) === 2, '下载量随版本累计');
$rb = pet_submit($bob, make_pack('puppet-bad'), []);
ok(pet_review((string) $rb['sub_id'], 'reject', '含未授权角色')['ok'] === true, '驳回');
ok((pet_sub_find((string) $rb['sub_id'])['review_note'] ?? '') === '含未授权角色', '驳回理由作者可见');
ok(pet_review((string) $rb['sub_id'], 'approve')['ok'] === false, '已驳回的不能再直接通过');

echo "每日限额\n";
$carol = ['id' => 'm_carol', 'name' => 'Carol'];
for ($i = 0; $i < PET_PER_SUBMITTER_DAILY; $i++) pet_submit($carol, make_pack('puppet-c' . $i), []);
ok(str_contains((string) (pet_submit($carol, make_pack('puppet-c9'), [])['error'] ?? ''), '次数'), '超过每日限额被拒');

echo "付费\n";
$rp = pet_submit($bob, make_pack('puppet-paid'), ['price' => 9.9, 'license' => 'All-Rights-Reserved']);
pet_review((string) $rp['sub_id'], 'approve');
$prod = pet_product('puppet-paid');
ok($prod !== null && (float) $prod['pricing']['price'] === 9.9 && $prod['author'] === 'm_bob', '上架时同步成 pet 商品（作者 = 投稿人）');
$paid = pet_find('puppet-paid');
ok(pet_can_download($paid, null) === false, '未登录不能下载付费形象');
ok(pet_can_download($paid, $alice) === false, '未购买不能下载');
ok(pet_can_download($paid, $bob) === true, '作者本人可以下载');
ok(pet_can_download(pet_find('puppet-fox'), null) === true, '免费形象谁都能下载');
pet_review((string) $rp['sub_id'], 'unlist', '作者申请下架');
ok(pet_find('puppet-paid') === null && pet_product('puppet-paid') === null, '下架后不在目录、商品不再在售');

echo "公开目录\n";
$index = pet_index();
$ids = array_column($index['packs'], 'id');
ok($index['format'] === 1 && $ids === ['puppet-fox'], '只含当前上架的形象', implode(',', $ids));
$entry = $index['packs'][0];
ok(str_contains((string) ($entry['url'] ?? ''), 'action=download') && strlen((string) $entry['sha256']) === 64, '免费形象给下载地址与 sha256');
ok(!str_contains(json_encode($index), 'm_alice'), '目录里没有作者内部 id');
$paidEntry = pet_public_entry(['pack_id' => 'puppet-p', 'name' => 'p', 'sha256' => str_repeat('a', 64), 'price' => 5]);
ok(!isset($paidEntry['url']) && str_contains((string) $paidEntry['page'], '/pet/puppet-p'), '付费形象不给下载地址，只给详情页');

echo "生态市场\n";
$types = array_column(mkt_assets(), 'type');
ok(in_array('pet', $types, true), '桌宠出现在生态市场');
ok(isset(mkt_categories()['pet']), '市场有「桌宠」分类');
skill_publish(['id' => 'skill_pending_x', 'title' => '待审技能', 'status' => 'pending', 'content' => 'x']);
ok(!in_array('skill_pending_x', array_column(mkt_assets(), 'id'), true), '待审核的技能不出现在前台市场');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
