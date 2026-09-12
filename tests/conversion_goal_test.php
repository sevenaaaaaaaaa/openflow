<?php
/**
 * 转化目标对象 + 落地页绑定 契约
 *   php tests/conversion_goal_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cg-' . getmypid());
@mkdir(DATA_DIR . '/conversion', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
$GLOBALS['CG'] = ['form_submit' => 7, 'purchase' => 3];
class Database {
    public static function query(string $sql, array $p = []): array {
        if (stripos($sql, 'COUNT(*)') === false) return [];
        if (strpos($sql, "event = 'page_view'") !== false) return [['c' => 100]];
        return [['c' => (int)($GLOBALS['CG'][$p[0] ?? ''] ?? 0)]];
    }
}
$GLOBALS['ORDERS'] = [
    ['status' => 'paid', 'amount' => 199, 'paid_at' => date('Y-m-d H:i:s')],
    ['status' => 'paid', 'amount' => 301, 'paid_at' => date('Y-m-d H:i:s')],
    ['status' => 'pending', 'amount' => 999, 'paid_at' => date('Y-m-d H:i:s')],
];
function shop_all_orders(): array { return $GLOBALS['ORDERS']; }

require_once __DIR__ . '/../lib/ConversionGoal.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "转化目标对象\n";

// 创建 / 校验
check('缺名称/事件被拒', cg_save(['name' => '', 'event' => ''] )['ok'] === false);
$r = cg_save(['name' => '落地页预约', 'event' => 'form_submit', 'value' => 50]);
check('创建目标', !empty($r['ok']) && $r['goal']['id'] !== '');
$gid = $r['goal']['id'];
check('可读取', (cg_get($gid)['name'] ?? '') === '落地页预约');

// 统计（普通目标：次数×价值，转化率相对 page_view）
$s = cg_stats($gid, 30);
check('转化数按事件', $s['conversions'] === 7);
check('价值=次数×单价', $s['value'] === 350.0);
check('基准为浏览数', $s['baseline'] === 100);
check('转化率计算', $s['rate'] === 7.0);

// 成交类目标：价值取订单实付
$r2 = cg_save(['name' => '成交', 'event' => 'purchase', 'revenue_mode' => true]);
$s2 = cg_stats($r2['goal']['id'], 30);
check('成交类金额取订单实付', $s2['value'] === 500.0, (string)$s2['value']);
check('成交类转化数按事件', $s2['conversions'] === 3);

// 更新不重复建
$before = count(cg_all());
cg_save(['id' => $gid, 'name' => '落地页预约V2', 'event' => 'form_submit', 'value' => 80]);
check('更新不新增', count(cg_all()) === $before && (cg_get($gid)['name'] ?? '') === '落地页预约V2');

// 删除
cg_delete($gid);
check('删除目标', cg_get($gid) === null);

// 结构守卫：落地页绑定 + 导航
$lp = file_get_contents(__DIR__ . '/../admin/landing-pages.php');
check('落地页可绑定目标+CTA', strpos($lp, 'goal_id') !== false && strpos($lp, 'cta_button') !== false);
$fr = file_get_contents(__DIR__ . '/../landing.php');
check('前台 CTA 可追踪目标', strpos($fr, 'lpGoal') !== false && strpos($fr, 'goal_id') !== false);
$nav = file_get_contents(__DIR__ . '/../includes/admin-nav.php');
check('导航含转化目标', strpos($nav, 'conversion-goals') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
