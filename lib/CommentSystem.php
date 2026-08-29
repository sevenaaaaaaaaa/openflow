<?php
/**
 * 评论 + 点评系统 — 文章评论 / 导航站（网站/产品/书籍/活动）点评打分
 * 支持：多类型挂载、星级打分、点赞、置顶、审核
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ModerationSystem.php';

function comment_file(): string { return DATA_DIR . '/comments.json'; }
function comments_all(): array { return json_read(comment_file()); }
function comments_save(array $list): void {
    if (!is_dir(dirname(comment_file()))) mkdir(dirname(comment_file()), 0755, true);
    json_write(comment_file(), $list);
}

// 类型归一化：article / site / product / book / event
function comment_target_label(string $type): string {
    return ['article' => '文章', 'site' => '网站', 'product' => '产品', 'book' => '书籍', 'event' => '活动', 'plugin' => '插件', 'skill' => '技能'][$type] ?? $type;
}

// 获取某目标的评论（含审核通过的）
function comments_for(string $type, string $targetId): array {
    $all = comments_all();
    $list = array_values(array_filter($all, fn($c) => ($c['target_type'] ?? '') === $type && ($c['target_id'] ?? '') === $targetId && ($c['status'] ?? 'approved') === 'approved'));
    // 置顶优先 → 点赞数 → 时间
    usort($list, function ($a, $b) {
        if (($a['pinned'] ?? false) !== ($b['pinned'] ?? false)) return ($a['pinned'] ?? false) ? -1 : 1;
        $da = (int)($a['likes'] ?? 0); $db = (int)($b['likes'] ?? 0);
        return $db <=> $da ?: strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
    });
    return $list;
}

// 计算评分汇总
function comment_rating_summary(string $type, string $targetId): array {
    $list = comments_for($type, $targetId);
    $rated = array_values(array_filter($list, fn($c) => !empty($c['rating'])));
    $n = count($rated);
    $avg = $n ? round(array_sum(array_map(fn($c) => (float)$c['rating'], $rated)) / $n, 1) : 0;
    // 分布
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($rated as $c) { $r = (int)$c['rating']; if (isset($dist[$r])) $dist[$r]++; }
    return ['count' => $n, 'avg' => $avg, 'dist' => $dist, 'total' => count($list)];
}

// 添加评论/点评
function comment_add(string $type, string $targetId, array $data, ?array $member = null): array {
    $member = $member ?: member_current();
    $author = $member ? ($member['name'] ?? '用户') : (trim($data['guest_name'] ?? '') ?: '匿名');
    $text = trim($data['text'] ?? '');
    $rating = isset($data['rating']) ? (int)$data['rating'] : 0;
    if ($rating < 0 || $rating > 5) $rating = 0;
    if ($text === '') return ['ok' => false, 'error' => '评论内容不能为空'];
    if (mb_strlen($text) < 2) return ['ok' => false, 'error' => '评论太短'];

    // 风控引擎（规则 + 可选 AI）
    if (function_exists('mod_review_content')) {
        $mod = mod_review_content($text, ['target_type' => $type, 'target_id' => $targetId, 'author' => $author]);
        if ($mod['action'] === 'block') {
            return ['ok' => false, 'error' => '内容包含敏感信息，请修改后提交'];
        }
    }

    $comment = [
        'id' => 'c_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 5),
        'target_type' => $type,
        'target_id' => $targetId,
        'member_id' => $member['id'] ?? '',
        'author' => $author,
        'rating' => $rating,           // 0 = 纯评论；1-5 = 打分
        'text' => $text,
        'parent_id' => trim($data['parent_id'] ?? ''),  // 回复
        'likes' => 0,
        'liked_by' => [],
        'pinned' => false,
        'status' => 'approved',        // approved / pending / hidden
        'created_at' => date('Y-m-d H:i:s'),
    ];
    // 若入队待审（中风险），标记 pending 不立即展示
    if (isset($mod) && $mod['action'] === 'queue') $comment['status'] = 'pending';
    $all = comments_all();
    $all[] = $comment;
    comments_save($all);

    // 评论落库 → 插件钩子（旁路）。带评分的走 review_added，纯评论走 comment_added。
    if (class_exists('PluginSystem')) {
        PluginSystem::do_action('comment_added', $type, $targetId, $comment);
        if ($rating > 0) PluginSystem::do_action('review_added', $type, $targetId, $rating, $comment);
    }
    return ['ok' => true, 'comment' => $comment];
}

function comment_like(string $commentId, ?array $member = null): array {
    $member = $member ?: member_current();
    $uid = $member['id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'anon');
    $all = comments_all();
    foreach ($all as &$c) {
        if ($c['id'] === $commentId) {
            $liked = $c['liked_by'] ?? [];
            if (in_array($uid, $liked)) {
                $c['likes'] = max(0, (int)($c['likes'] ?? 0) - 1);
                $c['liked_by'] = array_values(array_filter($liked, fn($u) => $u !== $uid));
            } else {
                $c['likes'] = (int)($c['likes'] ?? 0) + 1;
                $c['liked_by'][] = $uid;
            }
            comments_save($all);
            return ['ok' => true, 'likes' => $c['likes'], 'liked' => in_array($uid, $c['liked_by'])];
        }
    }
    return ['ok' => false, 'error' => '评论不存在'];
}

// 管理操作
function comment_admin(string $commentId, string $action): bool {
    $all = comments_all();
    foreach ($all as &$c) {
        if ($c['id'] === $commentId) {
            if ($action === 'approve') $c['status'] = 'approved';
            elseif ($action === 'hide') $c['status'] = 'hidden';
            elseif ($action === 'pin') $c['pinned'] = !($c['pinned'] ?? false);
            elseif ($action === 'delete') { unset($c); }
            break;
        }
    }
    unset($c);
    comments_save(array_values(array_filter($all, fn($c) => !empty($c['id']))));
    return true;
}

// 评论/点赞统计（文章页互动数据）
function comment_stats(string $type, string $targetId): array {
    $list = comments_for($type, $targetId);
    $likes = array_sum(array_map(fn($c) => (int)($c['likes'] ?? 0), $list));
    return ['count' => count($list), 'likes' => $likes];
}
