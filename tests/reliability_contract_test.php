<?php
/**
 * 可靠性契约 —— php tests/reliability_contract_test.php
 *
 * 盯两件「界面看起来正常、实际在骗人」的历史问题：
 *
 *  A. Webhook 投递：表单一直让人填「重试次数」，但发送函数里没有重试循环——
 *     对方抖一下事件就永久丢了，界面还显示一切正常。现在必须有
 *     幂等键 / 退避重投 / 死信 / cron 处理入口，缺一不可。
 *
 *  B. A/B 结论：统计量（z、p、置信区间、最小样本量）本来就算得对，
 *     但展示层不管显著与否都用大字报「+X% 转化提升」，等于教用户把噪声当结论。
 *     现在未显著必须中性呈现，并给出还差多少样本。
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
ob_start(); require_once "$root/admin/config.php"; ob_end_clean();
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

/* ══════════ A. Webhook 投递保障 ══════════ */
$whSrc  = file_get_contents("$root/lib/WebhookSystem.php");
$delSrc = file_get_contents("$root/lib/WebhookDelivery.php");
$cron   = file_get_contents("$root/api/cron.php");

ok(is_file("$root/lib/WebhookDelivery.php"), '缺少 lib/WebhookDelivery.php（投递保障层）');
ok(str_contains($whSrc, 'wh_enqueue('), 'trigger() 首投失败后没有入重试队列——「重试次数」又变成了摆设');
ok(str_contains($whSrc, 'X-Webhook-Delivery'), '投递没有带幂等键请求头，接收方无法去重');
ok(str_contains($whSrc, "'delivery_id' => \$deliveryId"), '请求体里没有幂等键');
ok(str_contains($whSrc, 'wh_log_attempt('), '每次投递尝试没有留痕，出问题查不了');
ok(preg_match('/public static function deliver\(/', $whSrc) === 1, 'WebhookSystem 缺少可带幂等键与尝试次数的 deliver()');
ok(!preg_match('/private static function send\(array \$webhook, string \$event, array \$payload\)/', $whSrc),
   '旧的无重试 send() 还在，应已被 deliver() 取代');
ok(str_contains($cron, 'wh_process_queue('), 'cron 没有处理重投队列——入了队也永远不会重发');

foreach (['wh_backoff', 'wh_enqueue', 'wh_to_dead', 'wh_process_queue', 'wh_replay_dead', 'wh_new_delivery_id'] as $fn) {
    ok(str_contains($delSrc, "function $fn("), "投递保障层缺少 $fn()");
}

// 退避必须是递增的，且首档不小于 30s（否则等于没有退避）
require_once "$root/lib/WebhookDelivery.php";
$prev = 0; $mono = true;
for ($i = 1; $i <= 5; $i++) { $d = wh_backoff($i); if ($d <= $prev) $mono = false; $prev = $d; }
ok($mono, '退避不是递增的');
ok(wh_backoff(1) >= 30, '第一档退避太短（' . wh_backoff(1) . 's），起不到退避作用');
ok(wh_backoff(99) === wh_backoff(5), '超出阶梯时应停在最后一档，不应无限增长');

// 幂等键要稳定可辨识
$id1 = wh_new_delivery_id(); $id2 = wh_new_delivery_id();
ok($id1 !== $id2, '幂等键重复了');
ok(str_starts_with($id1, 'whd_'), '幂等键缺少可辨识前缀');

// 后台要能看见并重放，否则死信等于黑洞
$adm = file_get_contents("$root/admin/webhooks.php");
ok(str_contains($adm, 'wh_dead_list('), '后台看不到死信');
ok(str_contains($adm, 'wh_queue_list('), '后台看不到待重投队列');
ok(str_contains($adm, "value=\"replay\""), '后台没有死信重放入口');
ok(str_contains($adm, 'wh_deliveries('), '后台看不到投递明细');

/* ══════════ B. A/B 结论呈现 ══════════ */
$ab = file_get_contents("$root/admin/abtests-stats.php");
ok(str_contains($ab, 'function ab_compute'), 'A/B 统计函数缺失');
ok(str_contains($ab, 'normalCdf'), '缺少正态分布 CDF，算不出 p 值');
ok(str_contains($ab, "'min_sample'"), '没有最小样本量估算');
ok(str_contains($ab, '还不能下结论'), '未显著时没有中性结论——又会把噪声当结论展示');
ok(str_contains($ab, '$sig      = $result[\'significant\'] === true;') || str_contains($ab, "\$sig = \$result['significant'] === true;"),
   '结论呈现没有跟着显著性走');
ok(str_contains($ab, '还需约'), '未显著时没有告诉用户还差多少样本');

// 把统计函数抽出来实算，确保数学没被改坏
preg_match('/function ab_compute.*?\n\}/s', $ab, $m1);
preg_match('/function normalCdf.*?\n\}/s', $ab, $m2);
ok(!empty($m1[0]) && !empty($m2[0]), '无法从页面提取统计函数（结构变了？）');
if (!empty($m1[0]) && !empty($m2[0]) && !function_exists('ab_compute')) {
    eval($m2[0]); eval($m1[0]);
    $mk = function (int $ai, int $ac, int $bi, int $bc) {
        return ab_compute(['t' => ['A' => ['impression' => ['_' => $ai], 'conversion' => ['_' => $ac]],
                                   'B' => ['impression' => ['_' => $bi], 'conversion' => ['_' => $bc]]]], 't');
    };
    ok(abs(normalCdf(1.96) - 0.975) < 0.002, 'normalCdf(1.96) 偏差过大，p 值不可信');
    $big = $mk(5000, 250, 5000, 400);
    ok($big['significant'] === true, '大样本明显差异竟判为不显著');
    ok($big['ci'][0] > 0, '大样本正向差异的置信区间不应跨 0');
    $small = $mk(50, 3, 50, 5);
    ok($small['significant'] === false, '小样本被误判为显著——这正是要防的事');
    ok($small['min_sample'] > 0, '未显著时没有给出所需样本量');
    $same = $mk(1000, 100, 1000, 100);
    ok($same['p'] > 0.9, '两组完全相同时 p 值应接近 1');
    $none = $mk(0, 0, 0, 0);
    ok($none['significant'] === false && $none['p'] === null, '无数据时不应给出显著结论');
}

/* ══════════ C. 内容修订与还原 ══════════ */
require_once "$root/lib/RevisionSystem.php";
$cfg = file_get_contents("$root/admin/config.php");
$edit = file_get_contents("$root/admin/article-edit.php");

// 必须挂在写入的咽喉处，而不是某个页面——光文章就有 30+ 个写入点
ok(substr_count($cfg, 'rev_record(') >= 2, '修订没有挂在 save_article / save_page_content 里，写入路径会漏');
ok(preg_match('/function save_article.*?rev_record\(/s', $cfg) === 1, 'save_article 里没有记版');
ok(preg_match('/function save_page_content.*?rev_record\(/s', $cfg) === 1, 'save_page_content 里没有记版');
ok(!str_contains($edit, "DATA_DIR . '/versions/articles'"), '编辑页里只覆盖单条路径的旧版本写入还在，会和新层重复记录');
ok(str_contains($edit, '/xmp/revisions'), '编辑页没有通往修订历史的入口');
ok(is_file("$root/admin/revisions.php"), '缺少修订历史页面');

$rvPage = file_get_contents("$root/admin/revisions.php");
ok(str_contains($rvPage, 'rev_restore('), '修订页没有还原动作——只能看不能还原等于没做');
ok(str_contains($rvPage, 'rev_field_diff('), '修订页不能比对两版');
ok(str_contains($rvPage, 'rev_source_label('), '修订页没有区分改动来源（人 / AI / 外部协作者）');

// 真跑一遍：记版、去重、还原、还原可撤销、上限
$tid = 'ctest_' . bin2hex(random_bytes(3));
@unlink(rev_file('article', $tid));
$GLOBALS['of_actor'] = ['name' => '契约测试', 'source' => 'admin'];
save_article($tid, ['id' => $tid, 'title' => 'v1', 'content' => "A\nB", 'status' => 'draft']);
ok(rev_count('article', $tid) === 1, '新建后应有 1 版');
save_article($tid, ['title' => 'v2']);
ok(rev_count('article', $tid) === 2, '改动后应记新版');
save_article($tid, ['title' => 'v2']);
ok(rev_count('article', $tid) === 2, '内容无变化不应重复记版');
$revs = rev_all('article', $tid);
ok(end($revs)['changed'] === ['title'], '没有正确记录变更字段');
ok(end($revs)['by'] === '契约测试', '没有记录改动人');

$GLOBALS['of_actor'] = ['name' => 'AI', 'source' => 'mcp'];
save_article($tid, ['content' => "A\nB\nC"]);
$revs = rev_all('article', $tid);
ok(end($revs)['source'] === 'mcp', 'Agent 改动没有被标记来源，无法区分人改还是 AI 改');

$GLOBALS['of_actor'] = ['name' => '契约测试', 'source' => 'admin'];
$r = rev_restore('article', $tid, 1);
ok($r['ok'] === true, '还原失败：' . $r['error']);
$cur = get_article($tid);
ok(($cur['title'] ?? '') === 'v1' && ($cur['content'] ?? '') === "A\nB", '还原没有把所有字段带回去');
// 新建1 + 改标题2 + 无变化(不记) + AI 改正文3 + 还原4 = 4 版
ok(rev_count('article', $tid) === 4, '还原本身没有记版，导致还原不可撤销（实际 ' . rev_count('article', $tid) . ' 版）');
ok(rev_restore('article', $tid, 99999)['ok'] === false, '还原不存在的版本应失败而不是崩');

// diff 要在裁剪之前验，否则被 REV_KEEP 裁掉的旧版本本来就取不到
$d = rev_field_diff('article', $tid, 1, 2, 'title');
ok(is_array($d) && $d !== [], '取不到字段级 diff');

for ($i = 0; $i < REV_KEEP + 5; $i++) save_article($tid, ['title' => 'bulk' . $i]);
ok(rev_count('article', $tid) <= REV_KEEP, '版本数超过上限 ' . REV_KEEP . '，版本库会无限膨胀');

// 收尾：不留测试数据
$__all = json_read(ARTICLES_DIR . '/index.json');
json_write(ARTICLES_DIR . '/index.json', array_values(array_filter($__all, fn($a) => ($a['id'] ?? '') !== $tid)));
@unlink(rev_file('article', $tid));

/* ══════════ D. 多步写入的一致性（P0-05）══════════
 * 退款要连着改：订单状态、分销佣金、作者分成、订阅权益、技能解锁、购物积分。
 * 改造前每一步都套着 `try { ... } catch (Exception $e) {}`——佣金没收回来被静默吞掉，
 * 函数照样往下跑，最后返回 ok=true。于是账面「已退款」，钱还在推广人余额里，
 * 没有日志、没有提示。这里钉住三件事：核心写入在事务里、金额步骤不许吞异常、失败要能看见。
 */
$shop = file_get_contents("$root/lib/ShopSystem.php");
ok(is_file("$root/lib/Txn.php"), '缺少 lib/Txn.php（多步写入一致性层）');
require_once "$root/lib/Txn.php";
foreach (['txn_run', 'txn_active'] as $fn) ok(function_exists($fn), "缺少 $fn()");
ok(str_contains($shop, "require_once __DIR__ . '/Txn.php'"), 'ShopSystem 没有引入事务层');

// 取出两个函数各自的事务闭包体，逐条检查
// 第三项：积分变动的**实际调用**（只查函数名会被 function_exists 守卫蒙混过去）
foreach ([['shop_refund_order', '退款', "gamification_award(\$order['member_id'], -\$backPoints"],
         ['shop_mark_paid',   '支付', "gamification_award(\$order['member_id'], \$points"]] as [$fn, $cn, $ptsCall]) {
    ok(preg_match('/function ' . $fn . '\(.*?txn_run\(function/s', $shop) === 1,
       "{$cn}的核心写入没有包在事务里，中途失败会留半截状态");
    if (!preg_match('/function ' . $fn . '\(.*?txn_run\(function.*?\n        \}, \$jsonTouched\);/s', $shop, $mm)) {
        ok(false, "无法定位{$cn}的事务闭包（结构变了？）");
        continue;
    }
    $body = $mm[0];
    // 吞异常是这次要修掉的根因，不能让它回来
    ok(!preg_match('/catch\s*\(\s*\\\\?Exception\s+\$e\s*\)\s*\{\s*\}/', $body),
       "{$cn}的事务里还有 `catch (Exception \$e) {}` 空吞——失败会被伪装成成功");
    // 余额与权益必须都在同一个事务里，少一样就还是半截
    ok(str_contains($body, 'balance = balance'), "{$cn}的余额变动不在事务里");
    ok(str_contains($body, $ptsCall), "{$cn}的积分变动不在事务里");
    // 回滚要覆盖三处 JSON 存储，否则 SQLite 回滚了、文件还是新的
    ok(preg_match('/function ' . $fn . '.*?\$jsonTouched = shop_txn_json_files\(\);/s', $shop) === 1,
       "{$cn}没有用统一的 JSON 快照清单");
}

// 快照清单只能有一份，且必须覆盖全部四处存储。
// 少一处就是「SQLite 回滚了、文件还是新的」——最难查的那种不一致。
ok(str_contains($shop, 'function shop_txn_json_files'), '缺少统一的 JSON 快照清单');
preg_match('/function shop_txn_json_files.*?\n\}/s', $shop, $sf);
foreach (['shop_orders_file()' => '订单', 'sub_state_file()' => '订阅状态',
          'members/index.json' => '购物积分', 'messages/index.json' => '积分变动站内信'] as $needle => $what) {
    ok(str_contains($sf[0] ?? '', $needle), "JSON 快照没覆盖{$what}，回滚会留下不一致");
}

// 通知与旁路必须在事务外：插件抛错不该把已经完成的退款again撤回来
ok(preg_match('/\}, \$jsonTouched\);.*?catch \(\\\\Throwable \$e\).*?return \[\'ok\'=>false/s', $shop) === 1,
   '退款失败没有返回 ok=false，调用方无从判断');
ok(preg_match('/\}, \$jsonTouched\);.*?inbox_send/s', $shop) === 1, '站内信应在事务提交之后再发');
ok(preg_match('/\}, \$jsonTouched\);.*?flow_handle\(\'refund\'/s', $shop) === 1, '营销事件应在事务提交之后再触发');

// 失败必须能被人看见
$ord = file_get_contents("$root/admin/orders.php");
ok(str_contains($ord, "\$error = \$r['error']"), '后台没有把退款失败原因显示出来，用户只会看到「没反应」');
$txn = file_get_contents("$root/lib/Txn.php");
ok(str_contains($txn, 'txn_log_rollback'), '回滚没有留痕，事后无法排查');
ok(!preg_match("/txn_log_rollback.*?require_once.*?AuditLog/s", $txn),
   '回滚留痕里不该 require AuditLog——会把 admin/config.php 整个拉起来（开 session、发 header）');

/* ══════════ E. 落地页的版本记录（2026-09-04）══════════
 * 文章和普通页面早就有修订，唯独落地页漏着：admin/page-builder.php 和
 * api/ai-landing.php 各自直接 json_write，改错了退不回去、也说不清是谁改的。
 * 这个洞直接卡住了外部协作——上一版只能给落地页批注权限，不能给编辑权限。
 * 这里钉住：写入口只有一个、它一定记版、还原可用、且还原不会毁掉块身份。
 */
require_once "$root/lib/BuilderPages.php";
$bp = file_get_contents("$root/lib/BuilderPages.php");
$pb = file_get_contents("$root/admin/page-builder.php");
$ail = file_get_contents("$root/api/ai-landing.php");

ok(function_exists('save_builder_page'), '缺少落地页的唯一写入口');
ok(str_contains($bp, "rev_record('landing'"), '落地页写入口没有记版');
ok(str_contains($pb, 'save_builder_page('), '构建器没走写入口');
ok(str_contains($ail, 'save_builder_page('), 'AI 生成没走写入口');
// 绕过写入口的直接写盘不能再有，否则「挂在咽喉处」就白挂了
foreach ([['page-builder', $pb], ['ai-landing', $ail]] as [$nm, $src]) {
    ok(!preg_match('/json_write\s*\(\s*(\$builderFile|DATA_DIR\s*\.\s*.\/builder-pages\.json.)/', $src),
       "{$nm} 里还有绕过写入口的直接写盘，那条路径不会记版");
}
ok(in_array('blocks', rev_tracked_fields('landing'), true), '落地页的可比对字段里没有区块');
ok(rev_tracked_fields('landing') !== rev_tracked_fields('page'),
   '落地页和普通页面共用了字段表——两者是不同存储，id 可能重名，历史不能混');

// 真跑一遍：记版、归属、还原、块身份
$lid = save_builder_page('', ['title' => 'rel 契约页', 'slug' => 'rel-ctest', 'status' => 'draft',
    'blocks' => [['_type' => 'hero', 'title' => '原标题'], ['_type' => 'cta', 'title' => '第二块']]]);
ok($lid !== '', '新建落地页失败');
$keysBefore = array_map('block_key_of', builder_page_get($lid)['blocks']);
ok(rev_count('landing', $lid) === 1, '新建后应有 1 版');

$GLOBALS['of_actor'] = ['name' => '王编辑', 'source' => 'external'];
$blks = builder_page_get($lid)['blocks'];
$blks[0]['title'] = '外部改过的标题';
save_builder_page($lid, ['blocks' => $blks]);
ok(rev_count('landing', $lid) === 2, '改动后应记新版');
$rv = rev_all('landing', $lid);
ok(end($rv)['source'] === 'external', '外部协作者的改动没有被标成 external');
ok(end($rv)['by'] === '王编辑', '没有记录是谁改的');

$GLOBALS['of_actor'] = ['name' => '作者', 'source' => 'admin'];
$rr = rev_restore('landing', $lid, 1);
ok($rr['ok'] === true, '落地页还原失败：' . $rr['error']);
ok((builder_page_get($lid)['blocks'][0]['title'] ?? '') === '原标题', '还原没有把内容带回去');
ok(array_map('block_key_of', builder_page_get($lid)['blocks']) === $keysBefore,
   '还原把块的 _key 换掉了——挂在块上的批注会集体变成孤儿');
ok(rev_count('landing', $lid) === 3, '还原本身没有记版，还原就撤销不了');
ok(rev_restore('landing', 'nonexistent_lp', 1)['ok'] === false, '还原不存在的落地页应当失败而不是崩');

builder_page_delete($lid);
@unlink(rev_file('landing', $lid));

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
