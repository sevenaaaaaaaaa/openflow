<?php
/**
 * A3 验收：CDP 分群 → CRM 批量建线索
 *
 *   php tests/bulk_leads_test.php
 *
 * 重点不只是「能建出线索」，而是三件容易做错的事：
 *   1. 全程只读一次、只写一次（循环调 crm_ensure_lead 是 O(n²)）；
 *   2. 已存在的线索绝不覆盖销售填过的内容；
 *   3. 钩子在落盘之后才发，且插件抛错不影响已经写好的数据。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-bulk-test-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);
@mkdir(DATA_DIR . '/members', 0777, true);

$GLOBALS['READS'] = 0;
$GLOBALS['WRITES'] = 0;

function json_read(string $f): array {
    if (strpos($f, 'crm.json') !== false) $GLOBALS['READS']++;
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    if (strpos($f, 'crm.json') !== false) $GLOBALS['WRITES']++;
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

require_once __DIR__ . '/../lib/PluginSystem.php';

// 只抽需要的几个函数，避开 CrmSystem 顶部的一堆依赖。
// 按花括号配平截取，单行函数与多行函数都能正确取到。
$src = file_get_contents(__DIR__ . '/../lib/CrmSystem.php');
function extract_fn(string $src, string $name): string {
    $at = strpos($src, "\nfunction {$name}(");
    if ($at === false) { fwrite(STDERR, "无法定位 {$name}()\n"); exit(2); }
    $open = strpos($src, '{', $at);
    $depth = 0; $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $at, $i - $at + 1); }
    }
    fwrite(STDERR, "{$name}() 花括号不配平\n"); exit(2);
}
foreach (['crm_file', 'crm_get', 'crm_save', 'crm_stages',
          'crm_bulk_create_leads', 'crm_rows_from_profiles'] as $fn) {
    eval(extract_fn($src, $fn));
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
function reset_crm(array $leads = []) {
    json_write(DATA_DIR . '/crm.json', ['leads' => $leads]);
    $GLOBALS['READS'] = 0; $GLOBALS['WRITES'] = 0;
}

echo "\n── 1. 基本导入 ──\n";
reset_crm();
$stat = crm_bulk_create_leads([
    ['email' => 'a@t.com', 'name' => '甲'],
    ['email' => 'b@t.com', 'name' => '乙', 'phone' => '13800000000'],
], ['source' => '分群导入', 'owner' => '张三']);
check('新建 2 条', $stat['created'] === 2, (string)$stat['created']);
$leads = json_read(DATA_DIR . '/crm.json')['leads'];
check('落盘了', count($leads) === 2, (string)count($leads));
check('统一来源生效', ($leads['a@t.com']['source'] ?? '') === '分群导入');
check('统一负责人生效', ($leads['b@t.com']['owner'] ?? '') === '张三');
check('默认阶段为 new', ($leads['a@t.com']['stage'] ?? '') === 'new');
check('结构与 crm_ensure_lead 一致', isset($leads['a@t.com']['follow_ups'], $leads['a@t.com']['created_at']));

echo "\n── 2. 只读一次、只写一次（不能是 O(n²)）──\n";
reset_crm();
$rows = [];
for ($i = 0; $i < 500; $i++) $rows[] = ['email' => "u{$i}@t.com", 'name' => "用户{$i}"];
$t0 = microtime(true);
$stat = crm_bulk_create_leads($rows, ['fire_hooks' => false]);
$ms = round((microtime(true) - $t0) * 1000);
check('建出 500 条', $stat['created'] === 500, (string)$stat['created']);
check('crm.json 只读了 1 次', $GLOBALS['READS'] === 1, (string)$GLOBALS['READS']);
check('crm.json 只写了 1 次', $GLOBALS['WRITES'] === 1, (string)$GLOBALS['WRITES']);
check('500 条耗时 < 2s', $ms < 2000, "{$ms}ms");

echo "\n── 3. 邮箱去重与规范化 ──\n";
reset_crm();
$stat = crm_bulk_create_leads([
    ['email' => 'Dup@T.com', 'name' => '大写'],
    ['email' => 'dup@t.com', 'name' => '小写'],
    ['email' => '  dup@t.com  ', 'name' => '带空格'],
], ['fire_hooks' => false]);
check('同一邮箱只建一条', $stat['created'] === 1, (string)$stat['created']);
check('后两条算跳过', $stat['skipped'] === 2, (string)$stat['skipped']);
check('key 已小写去空格', isset(json_read(DATA_DIR . '/crm.json')['leads']['dup@t.com']));

echo "\n── 4. 无效邮箱不造垃圾线索 ──\n";
reset_crm();
$stat = crm_bulk_create_leads([
    ['email' => '', 'name' => '空'],
    ['email' => 'not-an-email', 'name' => '非法'],
    ['email' => 'ok@t.com', 'name' => '正常'],
], ['fire_hooks' => false]);
check('只建 1 条', $stat['created'] === 1, (string)$stat['created']);
check('2 条记为无邮箱', $stat['no_email'] === 2, (string)$stat['no_email']);

echo "\n── 5. 已存在线索：默认跳过，绝不覆盖销售填的内容 ──\n";
reset_crm(['old@t.com' => [
    'email'=>'old@t.com','name'=>'销售填的名字','phone'=>'139','company'=>'',
    'stage'=>'opportunity','score'=>80,'owner'=>'李四','value'=>50000,
    'source'=>'展会','tags'=>['重点'],'follow_ups'=>[['x']],
    'created_at'=>'2020-01-01 00:00:00','updated_at'=>'2020-01-01 00:00:00',
]]);
$stat = crm_bulk_create_leads([['email'=>'old@t.com','name'=>'导入的名字','company'=>'某公司']],
                              ['stage'=>'new','owner'=>'王五','fire_hooks'=>false]);
$lead = json_read(DATA_DIR . '/crm.json')['leads']['old@t.com'];
check('记为跳过', $stat['skipped'] === 1 && $stat['created'] === 0);
check('姓名没被覆盖', $lead['name'] === '销售填的名字', $lead['name']);
check('阶段没被打回 new', $lead['stage'] === 'opportunity', $lead['stage']);
check('负责人没被改', $lead['owner'] === '李四', $lead['owner']);
check('金额没被清零', (float)$lead['value'] === 50000.0);
check('跟进记录还在', count($lead['follow_ups']) === 1);

echo "\n── 6. update_existing：只补空字段 ──\n";
reset_crm(['old@t.com' => [
    'email'=>'old@t.com','name'=>'销售填的名字','phone'=>'','company'=>'',
    'stage'=>'opportunity','score'=>0,'owner'=>'李四','value'=>0,
    'source'=>'展会','tags'=>['重点'],'follow_ups'=>[],
    'created_at'=>'2020-01-01 00:00:00','updated_at'=>'2020-01-01 00:00:00',
]]);
$stat = crm_bulk_create_leads(
    [['email'=>'old@t.com','name'=>'导入名','phone'=>'138','company'=>'某公司','source'=>'分群']],
    ['update_existing'=>true, 'tags'=>['分群:高价值'], 'fire_hooks'=>false]);
$lead = json_read(DATA_DIR . '/crm.json')['leads']['old@t.com'];
check('记为更新', $stat['updated'] === 1, (string)$stat['updated']);
check('空的电话被补上', $lead['phone'] === '138', $lead['phone']);
check('空的公司被补上', $lead['company'] === '某公司', $lead['company']);
check('非空的姓名仍不覆盖', $lead['name'] === '销售填的名字', $lead['name']);
check('非空的来源仍不覆盖', $lead['source'] === '展会', $lead['source']);
check('标签是追加不是替换', in_array('重点', $lead['tags'], true) && in_array('分群:高价值', $lead['tags'], true));

echo "\n── 7. 试运行不落盘 ──\n";
reset_crm();
$stat = crm_bulk_create_leads([['email'=>'dry@t.com']], ['dry_run'=>true]);
check('统计照常给出', $stat['created'] === 1);
check('一次都没写盘', $GLOBALS['WRITES'] === 0, (string)$GLOBALS['WRITES']);
check('文件里确实没有', !isset(json_read(DATA_DIR . '/crm.json')['leads']['dry@t.com']));

echo "\n── 8. 钩子：落盘之后才发，逐条 + 一次汇总 ──\n";
reset_crm();
$fired = []; $bulk = [];
PluginSystem::add_action('crm_lead_created', function ($email, $lead) use (&$fired) { $fired[] = $email; });
PluginSystem::add_action('crm_leads_bulk_imported', function ($stat, $opts) use (&$bulk) { $bulk[] = $stat; });
crm_bulk_create_leads([['email'=>'h1@t.com'], ['email'=>'h2@t.com']]);
check('每条新线索都发了 crm_lead_created', count($fired) === 2, (string)count($fired));
check('汇总钩子发了一次', count($bulk) === 1, (string)count($bulk));
check('汇总里带统计', ($bulk[0]['created'] ?? 0) === 2);

echo "\n── 9. 插件炸了不影响已落盘的数据 ──\n";
reset_crm();
PluginSystem::add_action('crm_lead_created', function () { throw new RuntimeException('插件炸'); });
$broke = false;
try { $stat = crm_bulk_create_leads([['email'=>'safe@t.com']]); }
catch (\Throwable $e) { $broke = true; }
check('没有抛到调用方', !$broke);
check('线索照样建成', isset(json_read(DATA_DIR . '/crm.json')['leads']['safe@t.com']));

echo "\n── 10. 画像 → 线索行：邮箱三级回退 ──\n";
json_write(DATA_DIR . '/members/index.json', [
    ['id' => 'm9', 'email' => 'from-member@t.com', 'username' => '会员九'],
]);
$rows = crm_rows_from_profiles([
    ['visitor_id'=>'v1','properties'=>['email'=>'from-prop@t.com','name'=>'甲','company'=>'A公司'],'tags'=>['活跃']],
    ['visitor_id'=>'v2','email'=>'from-top@t.com'],
    ['visitor_id'=>'v3','member_id'=>'m9','properties'=>[]],
    ['visitor_id'=>'v4','properties'=>[]],
]);
check('取画像属性里的邮箱', $rows[0]['email'] === 'from-prop@t.com', $rows[0]['email']);
check('带出公司', $rows[0]['company'] === 'A公司');
check('画像标签带进来', $rows[0]['tags'] === ['活跃']);
check('回退到顶层字段', $rows[1]['email'] === 'from-top@t.com', $rows[1]['email']);
check('回退到会员表', $rows[2]['email'] === 'from-member@t.com', $rows[2]['email']);
check('会员用户名当姓名', $rows[2]['name'] === '会员九', $rows[2]['name']);
check('全都没有则留空（后续记为 no_email）', $rows[3]['email'] === '');

// 清理
foreach (['cdp', 'members'] as $d) {
    foreach (glob(DATA_DIR . "/{$d}/*") ?: [] as $f) @unlink($f);
    @rmdir(DATA_DIR . '/' . $d);
}
foreach (glob(DATA_DIR . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
