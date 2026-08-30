<?php
/**
 * 站内营销投放验收
 *
 *   php tests/promo_test.php
 *
 * 重点是匹配引擎对不对——页面/人群/定时/频次的每条规则都要能挡住
 * 不该看到的人，放进该看到的人。这才是"投放"好不好的关键。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-promo-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function json_write(string $f, array $d): bool { return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

$src = file_get_contents(__DIR__ . '/../lib/PromoSystem.php');
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
foreach (['promos_file','promo_types','promo_slots','promo_all','promo_get','promo_save','promo_delete',
          'promo_path_match','promo_match','promo_serve','promo_hit'] as $fn) {
    eval(extract_fn($src, $fn));
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
// 基础投放模板
function base(array $o = []): array {
    return array_merge([
        'enabled'=>true,'type'=>'bar','page_mode'=>'all','aud_login'=>'any','aud_visitor'=>'any',
        'aud_segment'=>'','aud_utm'=>'','start'=>'','end'=>'','page_paths'=>[],'page_types'=>[],
    ], $o);
}
$now = '2026-06-15 12:00:00';
function ctx(array $o = []): array {
    return array_merge(['path'=>'/','page_type'=>'home','logged_in'=>false,'visitor'=>'new','segments'=>[],'utm_source'=>'','now'=>'2026-06-15 12:00:00'], $o);
}

echo "\n── 1. 路径匹配 ──\n";
check('精确匹配', promo_path_match('/about', '/about'));
check('前缀通配 /article/*', promo_path_match('/article/*', '/article/hello'));
check('通配不误伤', !promo_path_match('/article/*', '/about'));
check('根路径', promo_path_match('/', '/'));
check('空模式不匹配', !promo_path_match('', '/x'));

echo "\n── 2. 启用 / 定时 ──\n";
check('停用的不命中', !promo_match(base(['enabled'=>false]), ctx()));
check('未到开始时间不命中', !promo_match(base(['start'=>'2026-07-01 00:00:00']), ctx()));
check('已过结束时间不命中', !promo_match(base(['end'=>'2026-06-01 00:00:00']), ctx()));
check('在窗口内命中', promo_match(base(['start'=>'2026-06-01 00:00:00','end'=>'2026-12-31 00:00:00']), ctx()));

echo "\n── 3. 页面定向 ──\n";
check('all 全命中', promo_match(base(), ctx(['path'=>'/anything'])));
check('include 命中列表内', promo_match(base(['page_mode'=>'include','page_paths'=>['/article/*']]), ctx(['path'=>'/article/x'])));
check('include 挡掉列表外', !promo_match(base(['page_mode'=>'include','page_paths'=>['/article/*']]), ctx(['path'=>'/about'])));
check('exclude 挡掉列表内', !promo_match(base(['page_mode'=>'exclude','page_paths'=>['/about']]), ctx(['path'=>'/about'])));
check('exclude 放行列表外', promo_match(base(['page_mode'=>'exclude','page_paths'=>['/about']]), ctx(['path'=>'/'])));
check('type 命中类型', promo_match(base(['page_mode'=>'type','page_types'=>['article','landing']]), ctx(['page_type'=>'article'])));
check('type 挡掉别的类型', !promo_match(base(['page_mode'=>'type','page_types'=>['article']]), ctx(['page_type'=>'home'])));

echo "\n── 4. 人群：登录态 / 访客 ──\n";
check('限已登录：登录命中', promo_match(base(['aud_login'=>'in']), ctx(['logged_in'=>true])));
check('限已登录：未登录挡掉', !promo_match(base(['aud_login'=>'in']), ctx(['logged_in'=>false])));
check('限未登录：登录挡掉', !promo_match(base(['aud_login'=>'out']), ctx(['logged_in'=>true])));
check('限新访客命中', promo_match(base(['aud_visitor'=>'new']), ctx(['visitor'=>'new'])));
check('限新访客挡掉老访客', !promo_match(base(['aud_visitor'=>'new']), ctx(['visitor'=>'return'])));

echo "\n── 5. 人群：分群 / UTM ──\n";
check('分群命中', promo_match(base(['aud_segment'=>'seg1']), ctx(['segments'=>['seg1','seg2']])));
check('不在分群挡掉', !promo_match(base(['aud_segment'=>'seg9']), ctx(['segments'=>['seg1']])));
check('UTM 命中（大小写不敏感）', promo_match(base(['aud_utm'=>'Weibo']), ctx(['utm_source'=>'weibo'])));
check('UTM 不符挡掉', !promo_match(base(['aud_utm'=>'weibo']), ctx(['utm_source'=>'google'])));

echo "\n── 6. serve 排序 + 类型过滤 ──\n";
json_write(DATA_DIR . '/promos.json', [
    base(['id'=>'b1','type'=>'bar','name'=>'B1','priority'=>1]),
    base(['id'=>'b2','type'=>'bar','name'=>'B2','priority'=>5]),
    base(['id'=>'p1','type'=>'popup','name'=>'P1','priority'=>3]),
    base(['id'=>'off','type'=>'bar','enabled'=>false]),
]);
$bars = promo_serve(ctx(), 'bar');
check('只返回 bar 且启用的', count($bars) === 2);
check('按优先级降序（B2 在前）', $bars[0]['id'] === 'b2', $bars[0]['id'] ?? '');
check('类型过滤 popup', count(promo_serve(ctx(), 'popup')) === 1);
check('不传类型返回全部启用', count(promo_serve(ctx())) === 3);

echo "\n── 7. CRUD + 校验 ──\n";
json_write(DATA_DIR . '/promos.json', []);
$r = promo_save(['name'=>'夏促通知条','type'=>'bar','enabled'=>true]);
check('创建成功', $r['ok'] === true);
check('空名被拒', promo_save(['name'=>''])['ok'] === false);
check('未知类型被拒', promo_save(['name'=>'x','type'=>'炸弹'])['ok'] === false);
$id = $r['promo']['id'];
check('默认可关闭', $r['promo']['dismissible'] === true);
$u = promo_save(array_merge($r['promo'], ['priority'=>9]));
check('更新不新增（仍 1 条）', count(promo_all()) === 1);
check('更新生效', promo_get($id)['priority'] === 9);

echo "\n── 8. 埋点统计 ──\n";
promo_hit($id, 'impression'); promo_hit($id, 'impression'); promo_hit($id, 'click'); promo_hit($id, 'dismiss');
$p = promo_get($id);
check('曝光 +2', $p['impressions'] === 2, (string)$p['impressions']);
check('点击 +1', $p['clicks'] === 1);
check('关闭 +1', $p['dismissed'] === 1);
check('非法 kind 不计', !promo_hit($id, '瞎搞'));
promo_delete($id);
check('删除生效', promo_get($id) === null);

foreach (glob(DATA_DIR . '/*') ?: [] as $f) @unlink($f); @rmdir(DATA_DIR);
echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
