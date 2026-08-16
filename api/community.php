<?php
/**
 * 社区 API — 发帖/评论/点赞/投票
 * 继承全站用户体系（member）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/Gamification.php';

header('Content-Type: application/json; charset=utf-8');

$member = member_current();
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// 公共：话题列表
if ($action === 'topics') {
    echo json_encode(['ok'=>true, 'topics'=>json_read(DATA_DIR . '/community-topics.json')], JSON_UNESCAPED_UNICODE);
    exit;
}

// 公共：帖子列表（含评论数/投票，供前端展示）
if ($action === 'posts') {
    $posts = json_read(DATA_DIR . '/community-posts.json');
    $comments = json_read(DATA_DIR . '/community-comments.json');
    $posts = array_values(array_filter($posts, fn($p) => ($p['status'] ?? 'published') === 'published'));
    // 排序：按时间倒序
    usort($posts, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    foreach ($posts as &$p) {
        $p['comment_count'] = count(array_filter($comments, fn($c) => ($c['post_id'] ?? '') === ($p['id'] ?? '')));
        $p['comment_list'] = array_values(array_filter($comments, fn($c) => ($c['post_id'] ?? '') === ($p['id'] ?? '')));
    }
    unset($p);
    echo json_encode(['ok'=>true, 'posts'=>$posts], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

switch ($action) {
    // ─── 发帖 ───
    case 'create_post':
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $topic = trim($_POST['topic'] ?? 'general');
        if (empty($title) || empty($content)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'标题和内容不能为空']); exit; }
        $posts = json_read(DATA_DIR . '/community-posts.json');
        $posts[] = [
            'id' => 'post_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'title' => $title,
            'content' => $content,
            'topic' => $topic,
            'author_id' => $member['id'],
            'author_name' => $member['name'],
            'author_avatar' => $member['avatar'] ?? '',
            'votes' => 0,
            'voted' => [],
            'comments' => 0,
            'status' => 'published', // published / hidden
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/community-posts.json', $posts);
        gamification_award($member['id'], 5, '发布帖子');
        notify('社区', $member['name'] . ' 发布了新帖：' . mb_substr($title, 0, 20), '', 'admin/community-mod.php');
        echo json_encode(['ok'=>true, 'message'=>'发布成功']);
        break;

    // ─── 投票（点赞/点踩）───
    case 'vote':
        $postId = trim($_POST['post_id'] ?? '');
        $delta = (int)($_POST['delta'] ?? 1); // +1 / -1
        $posts = json_read(DATA_DIR . '/community-posts.json');
        foreach ($posts as &$p) {
            if ($p['id'] === $postId) {
                $already = $p['voted'][$member['id']] ?? 0;
                // 已投相同票则取消
                if ($already === $delta) {
                    $p['votes'] -= $delta;
                    unset($p['voted'][$member['id']]);
                } else {
                    $p['votes'] += $delta - $already;
                    $p['voted'][$member['id']] = $delta;
                }
                json_write(DATA_DIR . '/community-posts.json', $posts);
                echo json_encode(['ok'=>true, 'votes'=>$p['votes']]);
                exit;
            }
        }
        http_response_code(404); echo json_encode(['ok'=>false,'error'=>'帖子不存在']);
        break;

    // ─── 评论 ───
    case 'comment':
        $postId = trim($_POST['post_id'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'评论不能为空']); exit; }
        $comments = json_read(DATA_DIR . '/community-comments.json');
        $comments[] = [
            'id' => 'cmt_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'post_id' => $postId,
            'author_id' => $member['id'],
            'author_name' => $member['name'],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/community-comments.json', $comments);
        gamification_award($member['id'], 2, '发表评论');
        // 更新评论数
        $posts = json_read(DATA_DIR . '/community-posts.json');
        foreach ($posts as &$p) if ($p['id'] === $postId) { $p['comments'] = count(array_filter($comments, fn($c) => $c['post_id'] === $postId)); break; }
        json_write(DATA_DIR . '/community-posts.json', $posts);
        echo json_encode(['ok'=>true, 'message'=>'评论成功']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
