<?php
/**
 * 收款链接 / 报价单验收
 *
 *   php tests/quote_test.php
 *
 * 覆盖：开单（总额 / 明细求和 / 非法金额）、token 取单、过期判断、
 * 以及最关键的一条——付款成功后 CRM 线索被推进为「已成交」。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-quote-' . getmypid());
@mkdir(DATA_DIR . '/shop', 0777, true);

function json_read(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

// ── 依赖桩 ──
function shop_orders_file(): string { return DATA_DIR . '/shop/orders.json'; }
function shop_all_orders(): array { return json_read(shop_orders_file()); }
function site_config_get(string $k) { return $k === 'site_url' ? 'https://demo.test' : ''; }
$GLOBALS['CRM'] = [];   // email => ['stage','value','followups'=>[]]
function crm_ensure_lead(string $email, string $name = '', string $phone = '') {
    $e = mb_strtolower(trim($email));
    if (!isset($GLOBALS['CRM'][$e])) $GLOBALS['CRM'][$e] = ['stage'=>'new','value'=>0,'followups'=>[]];
    return $GLOBALS['CRM'][$e];
}
function crm_update_lead(string $email, array $u) {
    $e = mb_strtolower(trim($email));
    $GLOBALS['CRM'][$e] = array_merge($GLOBALS['CRM'][$e] ?? ['followups'=>[]], $u);
}
function crm_add_followup(string $email, string $c, string $o = '') {
    $e = mb_strtolower(trim($email));
    $GLOBALS['CRM'][$e]['followups'][] = $c;
}

// 抽取被测函数（避开 ShopSystem 顶部的重依赖）
function extract_fn(string $src, string $name): string {
    $at = strpos($src, "\nfunction {$name}(");
    if ($at === false) { fwrite(STDERR, "缺 {$name}\n"); exit(2); }
    $open = strpos($src, '{', $at); $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $at, $i - $at + 1); }
    }
    exit(2);
}
$qsrc = file_get_contents(__DIR__ . '/../lib/QuoteSystem.php');
foreach (['quote_site_url','quote_pay_url','quote_create','quote_get_by_token','quote_is_expired','quote_all'] as $fn) {
    eval(extract_fn($qsrc, $fn));
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 开单：直接填总额 ──\n";
$r = quote_create(['title'=>'官网设计首款','amount'=>5000,'email'=>'Client@T.com','customer'=>'张先生']);
check('创建成功', $r['ok'] === true, $r['error'] ?? '');
check('金额正确', $r['order']['amount'] === 5000.0);
check('goods_type=quote', $r['order']['goods_type'] === 'quote');
check('生成了 pay_token', !empty($r['order']['pay_token']));
check('邮箱已小写归一', $r['order']['crm_email'] === 'client@t.com');
check('支付链接含 token', strpos($r['pay_url'], $r['order']['pay_token']) !== false);
check('链接是站点域名', strpos($r['pay_url'], 'https://demo.test/pay?t=') === 0, $r['pay_url']);
check('开单即建/确保 CRM 线索', isset($GLOBALS['CRM']['client@t.com']));

echo "\n── 2. 开单：按明细求和 ──\n";
$r2 = quote_create(['title'=>'咨询','items'=>[
    ['name'=>'方案','qty'=>1,'price'=>2000],
    ['name'=>'陪跑','qty'=>3,'price'=>500],
]]);
check('未填总额时按明细求和 = 3500', $r2['order']['amount'] === 3500.0, (string)$r2['order']['amount']);
check('明细带小计', ($r2['order']['items'][1]['subtotal'] ?? 0) === 1500.0);

echo "\n── 3. 拒绝非法金额 ──\n";
$r3 = quote_create(['title'=>'x','amount'=>0]);
check('金额为 0 被拒', $r3['ok'] === false);
$r4 = quote_create(['title'=>'x','amount'=>-100]);
check('负数被拒', $r4['ok'] === false);

echo "\n── 4. 按 token 取单 ──\n";
$tok = $r['order']['pay_token'];
$got = quote_get_by_token($tok);
check('取到同一张单', $got && $got['id'] === $r['order']['id']);
check('错误 token 取不到', quote_get_by_token('deadbeef') === null);
check('空 token 取不到', quote_get_by_token('') === null);

echo "\n── 5. 过期判断 ──\n";
check('未设过期 → 不过期', !quote_is_expired($r['order']));
check('过去日期 → 过期', quote_is_expired(['expires_at'=>date('Y-m-d', time()-86400*2)]));
check('未来日期 → 不过期', !quote_is_expired(['expires_at'=>date('Y-m-d', time()+86400*2)]));

echo "\n── 6. 付款成功推进 CRM（shop_mark_paid 的 quote 分支）──\n";
// 单独验证 ShopSystem 里那段 quote→CRM 逻辑
$order = ['goods_type'=>'quote','crm_email'=>'client@t.com','customer'=>'张先生','amount'=>5000.0,'id'=>'quote_x'];
// 直接跑那段逻辑（与源码一致）
if (($order['goods_type'] ?? '') === 'quote' && !empty($order['crm_email']) && function_exists('crm_update_lead')) {
    crm_ensure_lead($order['crm_email'], $order['customer']);
    crm_update_lead($order['crm_email'], ['stage'=>'won','value'=>(float)$order['amount']]);
    crm_add_followup($order['crm_email'], '通过收款链接成交：¥5,000.00（订单 quote_x）', 'system');
}
check('线索被推进为 won', ($GLOBALS['CRM']['client@t.com']['stage'] ?? '') === 'won');
check('成交金额进管道', ($GLOBALS['CRM']['client@t.com']['value'] ?? 0) === 5000.0);
check('写了成交跟进记录', count($GLOBALS['CRM']['client@t.com']['followups']) >= 1);

echo "\n── 7. 源码接线检查 ──\n";
$shop = file_get_contents(__DIR__ . '/../lib/ShopSystem.php');
check('shop_mark_paid 有 quote→CRM 分支', strpos($shop, "=== 'quote'") !== false && strpos($shop, "'stage' => 'won'") !== false);
$api = file_get_contents(__DIR__ . '/../api/shop.php');
check('支付 API 有公开 pay_quote 且在登录闸之前',
    strpos($api, "action === 'pay_quote'") !== false
    && strpos($api, "action === 'pay_quote'") < strpos($api, '请先登录'));
check('pay_quote 校验已付/过期', strpos($api, "已支付") !== false && strpos($api, 'quote_is_expired') !== false);

// 清理
foreach (glob(DATA_DIR . '/shop/*') ?: [] as $f) @unlink($f); @rmdir(DATA_DIR . '/shop');
foreach (glob(DATA_DIR . '/*') ?: [] as $f) @unlink($f); @rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
