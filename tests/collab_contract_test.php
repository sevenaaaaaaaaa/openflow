<?php
/**
 * 外部临时协作契约 —— php tests/collab_contract_test.php
 *
 * 这是**对外开放的入口**，所以这套测试的重点不是功能好不好用，
 * 而是「拿到一条链接的人，能不能做他不该做的事」：
 * 换个对象、换个类型、伪造 token、链接过期或吊销之后继续用、
 * 靠猜 token 爆破、把批注写成脚本、绕过 CSRF。
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
ob_start(); require_once "$root/admin/config.php"; ob_end_clean();
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

require_once "$root/lib/CollabAccess.php";
require_once "$root/lib/CollabReview.php";

// 限流是按 IP 计数的，连着跑几遍测试会把自己挡在外面。
// 开跑前先把这个计数清掉，让测试可重复——限流本身在最后一节单独验。
require_once "$root/lib/Cache.php";
$rlKey = 'rate:collab:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
Cache::forget($rlKey);

// 用干净的授权库跑，跑完还原
$gf = collab_grants_file();
$backup = is_file($gf) ? file_get_contents($gf) : null;
@mkdir(dirname($gf), 0777, true);
file_put_contents($gf, '[]');

/* ── 1. 发链接 ── */
$r = collab_create(['label' => '王编辑', 'type' => 'article', 'target_id' => 'a1',
                    'caps' => ['view', 'comment', 'edit'], 'days' => 7]);
ok($r['ok'] === true, '发链接失败：' . $r['error']);
$token = $r['token']; $gid = $r['grant']['id'];
ok(str_contains($token, '.'), 'token 应该是 id.secret 两段');
ok(strlen(explode('.', $token)[1]) >= 48, 'secret 熵不够，容易被猜');

// 明文 token 绝不能落盘——泄漏一次就等于一直泄漏
$raw = file_get_contents($gf);
ok(!str_contains($raw, explode('.', $token)[1]), '明文 token 被存进了文件！泄漏备份就等于泄漏访问权');
ok(str_contains($raw, hash('sha256', explode('.', $token)[1])), '没有存 secret 的哈希，无法校验');

/* ── 2. 正常校验 ── */
$g = collab_verify($token);
ok($g !== null && $g['id'] === $gid, '正确的 token 竟然验不过');

/* ── 3. 伪造与越权 ── */
ok(collab_verify('') === null, '空 token 应当拒绝');
ok(collab_verify('乱写') === null, '没有分隔符的 token 应当拒绝');
ok(collab_verify($gid . '.' . str_repeat('0', 64)) === null, '猜错 secret 竟然验过了');
ok(collab_verify('clb_nonexistent.' . explode('.', $token)[1]) === null, '不存在的 id 竟然验过了');
// 拿别人的 secret 配自己的 id
$r2 = collab_create(['label' => '李顾问', 'type' => 'article', 'target_id' => 'a2', 'caps' => ['view'], 'days' => 1]);
ok(collab_verify($r2['grant']['id'] . '.' . explode('.', $token)[1]) === null, 'id 与 secret 交叉组合竟然验过了');

$g = collab_verify($token);
ok(collab_scope_ok($g, 'article', 'a1') === true, '授权对象本身应当放行');
ok(collab_scope_ok($g, 'article', 'a2') === false, '换一个 id 竟然放行了——可以看别人的内容');
ok(collab_scope_ok($g, 'page', 'a1') === false, '换一个类型竟然放行了');
ok(collab_scope_ok($g, 'article', '') === false, '空 id 竟然放行了');

/* ── 4. 能力白名单：高危动作根本不存在 ── */
foreach (['delete', 'publish', 'refund', 'export', 'admin', 'settings'] as $bad) {
    ok(!isset(collab_caps()[$bad]), "能力白名单里出现了高危项 {$bad}");
    ok(collab_can($g, $bad) === false, "竟然能授予高危能力 {$bad}");
}
ok(collab_can($g, 'comment') === true, '授予的批注权限没生效');
// 就算硬把高危能力塞进 grant，也必须问不出来
$forged = $g; $forged['caps'] = ['view', 'delete', 'edit'];
ok(collab_can($forged, 'delete') === false, '伪造的 caps 竟然被认了');

/* ── 5. 落地页不给编辑（它没有版本记录，给不出「谁改的、能不能退回」）── */
$rp = collab_create(['label' => '客户', 'type' => 'page', 'target_id' => 'lp1',
                     'caps' => ['view', 'comment', 'edit'], 'days' => 3]);
ok(!in_array('edit', $rp['grant']['caps'], true), '落地页竟然发出了可编辑的链接');
ok(collab_can($rp['grant'], 'edit') === false, '落地页的编辑权限没有被兜住');
// 历史 grant 里塞了 edit 也不行
$oldPage = $rp['grant']; $oldPage['caps'] = ['view', 'edit'];
ok(collab_can($oldPage, 'edit') === false, '历史 grant 带着 edit，落地页竟然能改');

/* ── 6. 有效期 ── */
ok(collab_create(['label'=>'x','type'=>'article','target_id'=>'a1','days'=>9999])['grant']['expires_at']
   <= date('Y-m-d H:i:s', time() + 91 * 86400), '有效期没有被夹到上限，等于发了永久链接');
ok(collab_usable(['revoked'=>false,'expires_at'=>date('Y-m-d H:i:s', time()+3600)]) === true, '未过期的链接应当可用');
ok(collab_usable(['revoked'=>false,'expires_at'=>date('Y-m-d H:i:s', time()-1)]) === false, '过期的链接仍然可用');
ok(collab_usable(['revoked'=>true,'expires_at'=>date('Y-m-d H:i:s', time()+3600)]) === false, '吊销的链接仍然可用');
ok(collab_usable(['revoked'=>false,'expires_at'=>'']) === false, '没有有效期竟被当成永久有效——这是个后门');

/* ── 7. 吊销立刻生效 ── */
ok(collab_get($gid) !== null, '吊销前应当能取到');
collab_revoke($gid);
ok(collab_get($gid) === null, '吊销后仍然能取到 grant');
ok(collab_verify($token) === null, '吊销后 token 仍然有效——收不回来的链接等于没有收回');

/* ── 8. 批注 ── */
$T = 'article'; $ID = 'ctest_' . bin2hex(random_bytes(3));
$actor = ['name' => '王编辑', 'kind' => 'external'];
$n = note_add($T, $ID, 'anchor1', '这段的数据来源要补', $actor, '原文引用');
ok($n['ok'] === true, '加批注失败');
ok(note_open_count($T, $ID) === 1, '待处理批注数不对');
ok(note_add($T, $ID, '', '', $actor)['ok'] === false, '空批注竟然被接受');
$nid = $n['note']['id'];
ok(note_reply($T, $ID, $nid, '已补上', ['name'=>'作者','kind'=>'admin'])['ok'] === true, '回复失败');
ok(note_reply($T, $ID, 'nonexistent', 'x', $actor)['ok'] === false, '回复不存在的批注应当失败');
ok(note_resolve($T, $ID, $nid, '作者')['ok'] === true, '标记已处理失败');
ok(note_open_count($T, $ID) === 0, '标记已处理后仍算待处理');
ok(note_resolve($T, $ID, $nid, '作者', false)['ok'] === true, '重新打开失败');
ok(note_open_count($T, $ID) === 1, '重新打开后应当重回待处理');
// 来源不可伪造成 admin 以外的东西，且 by_kind 只能是两种
$fake = note_add($T, $ID, '', '伪装', ['name'=>'x','kind'=>'superadmin']);
ok($fake['note']['by_kind'] === 'external', '未知身份没有被降级成 external');
// 长度与条数上限
$long = note_add($T, $ID, '', str_repeat('长', 5000), $actor);
ok(mb_strlen($long['note']['text']) <= NOTE_MAX_LEN, '超长批注没有被截断，外部人可以灌爆磁盘');

/* ── 9. 批注独立存储，绝不进公开评论 API ── */
$apiSrc = file_get_contents("$root/api/comment.php");
ok(!str_contains($apiSrc, 'review'), '公开评论 API 里出现了 review，内部批注有泄漏风险');
ok(str_contains(note_file($T, $ID), 'collab/review'), '批注没有独立存储');
$cmtFile = function_exists('comment_file') ? comment_file() : '';
ok($cmtFile === '' || note_file($T, $ID) !== $cmtFile, '批注和公开评论共用了一个库');

/* ── 10. 页面本身的安全姿势 ── */
$page = file_get_contents("$root/collab.php");
// 只看真正的调用，不看注释（注释里正说明「这里绝不 require_login()」）
ok(!preg_match('/^\s*require_login\(\)\s*;/m', $page), '外部协作页不该依赖后台登录');
ok(!preg_match('/^\s*require_perm\(/m', $page), '外部协作页不该依赖后台权限体系');
ok(str_contains($page, 'csrf_guard_auto()'), '外部协作页没有做 CSRF 收口');
ok(str_contains($page, 'session_regenerate_id'), '换会话时没有重新生成 session id，存在会话固定风险');
ok(str_contains($page, "header('Location: /c/')"), 'token 没有从地址栏里去掉，会留在历史与 referrer 里');
ok(str_contains($page, 'noindex'), '协作页没有禁止搜索引擎收录');
ok(substr_count($page, 'ctext(') >= 8, '输出转义太少，存在 XSS 风险');
ok(!preg_match('/<\?=\s*\$(n|r)\[/', $page), '有未经转义就直接输出的外部内容');
// 每个动作都必须先检查能力
foreach (['comment', 'reply', 'edit'] as $act) {
    ok(preg_match("/\\\$act === '{$act}'.{0,200}?(canComment|canEdit)/s", $page) === 1,
       "{$act} 动作没有检查权限");
}
ok(str_contains($page, 'collab_get($gid)'), '会话里的 grant 没有每次重新校验，吊销不会立刻生效');

/* ── 11. 路由 ── */
$ht = file_get_contents("$root/.htaccess");
ok(str_contains($ht, 'collab.php?t=$1'), '缺少 /c/{token} 路由');

/* ── 12. 归属：外部改动必须标成 external 并进修订历史 ── */
ok(str_contains($page, 'collab_set_actor'), '外部编辑没有设置操作者，改动会算在系统头上');
$rs = file_get_contents("$root/lib/RevisionSystem.php");
ok(str_contains($rs, "'external' => '外部协作者'"), '修订层没有外部协作者这个来源标签');
$acc = file_get_contents("$root/lib/CollabAccess.php");
ok(str_contains($acc, "'source' => 'external'"), '协作者身份没有标成 external');
ok(str_contains($acc, 'hash_equals'), '没有用常数时间比较，存在时序攻击面');
ok(str_contains($acc, 'RateLimiter'), '没有限流，token 可以被爆破');

/* ── 13. 限流：token 是可猜的东西，必须给爆破上闸 ── */
// 放在最后，因为它会把本次进程的配额用光
Cache::forget($rlKey);
$blocked = false;
for ($i = 0; $i < 40; $i++) {
    if (collab_verify('clb_guess' . $i . '.' . str_repeat('a', 64)) === null && $i > 25) {
        // 到这里配额早该用光了；再拿一个**正确**的 token 去验，应当也被挡住
        $probe = collab_create(['label'=>'限流探针','type'=>'article','target_id'=>'a1','days'=>1]);
        if (collab_verify($probe['token']) === null) { $blocked = true; }
        break;
    }
}
ok($blocked, '连续猜了几十次 token 都没有被限流——可以离线爆破');
Cache::forget($rlKey);
ok(collab_verify(collab_create(['label'=>'恢复','type'=>'article','target_id'=>'a1','days'=>1])['token']) !== null,
   '限流窗口过去后应当恢复正常，不能把正常协作者永久挡在外面');

// 收尾
Cache::forget($rlKey);
@unlink(note_file($T, $ID));
if ($backup === null) @unlink($gf); else file_put_contents($gf, $backup);

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
