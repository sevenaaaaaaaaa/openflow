<?php
/**
 * 审核中心 — 讲师申请 + 文章投稿
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_login();
require_perm('settings');

$members = member_get_all();
$submissions = json_read(DATA_DIR . '/member-submissions.json');
$message = '';

// 讲师审核
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_teacher'])) {
    csrf_verify();
    $mid = $_POST['member_id'] ?? '';
    $approve = $_POST['approve'] === '1';
    foreach ($members as &$m) {
        if ($m['id'] === $mid) {
            $m['teacher_status'] = $approve ? 'approved' : 'rejected';
            break;
        }
    }
    unset($m);
    json_write(member_file(), $members);
    flash('success', $approve ? '讲师申请已通过' : '讲师申请已驳回');
    header('Location: approvals.php?type=teacher');
    exit;
}

// 投稿审核
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_article'])) {
    csrf_verify();
    $sid = $_POST['submission_id'] ?? '';
    $approve = $_POST['approve'] === '1';
    $subMemberId = ''; $subTitle = '';
    foreach ($submissions as &$s) {
        if ($s['id'] === $sid) {
            $s['status'] = $approve ? 'approved' : 'rejected';
            $subMemberId = $s['member_id'] ?? '';
            $subTitle = $s['title'] ?? '';
            if ($approve) {
                // 创建文章
                $article = [
                    'id' => 'article_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
                    'title' => $s['title'],
                    'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $s['title']),
                    'content' => $s['content'],
                    'editor_mode' => 'richtext',
                    'category' => $s['category'] ?? 'insight',
                    'tags' => [],
                    'cover' => '',
                    'author' => $s['author'] ?? '',
                    'status' => 'published',
                    'seo_title' => $s['title'],
                    'seo_desc' => $s['excerpt'] ?? '',
                    'seo_keywords' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'review_status' => 'approved',
                ];
                $all = get_articles();
                $all[] = $article;
                json_write(ARTICLES_DIR . '/index.json', $all);
            }
            break;
        }
    }
    unset($s);
    json_write(DATA_DIR . '/member-submissions.json', $submissions);
    inbox_notify_event('submission_reviewed', ['member_id' => $subMemberId, 'title' => $subTitle, 'result' => $approve ? '通过并发布' : '驳回']);
    flash('success', $approve ? '投稿已通过并发布' : '投稿已驳回');
    header('Location: approvals.php?type=article');
    exit;
}

$type = $_GET['type'] ?? 'teacher';
$pendingTeachers = array_values(array_filter($members, fn($m) => ($m['teacher_status'] ?? '') === 'pending'));
$pendingSubs = array_values(array_filter($submissions, fn($s) => ($s['status'] ?? '') === 'pending'));

admin_header('审核中心');
?>
<div class="admin-layout">
  <?php admin_sidebar('approvals'); ?>
  <div class="main">
    <h1>🛡️ 审核中心</h1>
    <p class="sub">审核讲师申请与用户投稿</p>

    <div class="flex gap-2 mb-4">
      <a href="approvals.php?type=teacher" class="btn btn-sm <?=$type==='teacher'?'btn-primary':'btn-ghost'?>">👨‍🏫 讲师申请 <?php if($pendingTeachers): ?><span class="badge badge-red"><?=count($pendingTeachers)?></span><?php endif; ?></a>
      <a href="approvals.php?type=article" class="btn btn-sm <?=$type==='article'?'btn-primary':'btn-ghost'?>">✍️ 文章投稿 <?php if($pendingSubs): ?><span class="badge badge-red"><?=count($pendingSubs)?></span><?php endif; ?></a>
    </div>

    <?php if ($type === 'teacher'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>申请人</th><th>邮箱</th><th>擅长方向</th><th>简介</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pendingTeachers)): ?><tr><td colspan="5" class="empty">暂无待审核讲师申请</td></tr><?php endif; ?>
          <?php foreach ($pendingTeachers as $t): ?>
          <tr>
            <td><strong><?=htmlspecialchars($t['name'])?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($t['email'])?></td>
            <td><?=htmlspecialchars($t['teacher_expertise'] ?? '—')?></td>
            <td class="text-sm" style="max-width:260px"><?=htmlspecialchars($t['teacher_intro'] ?? '')?></td>
            <td style="white-space:nowrap">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>作者</th><th>标题</th><th>分类</th><th>摘要</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pendingSubs)): ?><tr><td colspan="5" class="empty">暂无待审核投稿</td></tr><?php endif; ?>
          <?php foreach ($pendingSubs as $s): ?>
          <tr>
            <td><strong><?=htmlspecialchars($s['author'])?></strong></td>
            <td style="max-width:200px"><strong><?=htmlspecialchars($s['title'])?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($s['category'])?></td>
            <td class="text-sm text-muted" style="max-width:220px"><?=htmlspecialchars(mb_substr($s['excerpt'] ?? '',0,40))?></td>
            <td style="white-space:nowrap">
              <button class="btn btn-ghost btn-sm" onclick="previewSubmission('<?=htmlspecialchars($s['id'])?>')">👁 预览</button>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<div id="submPreview" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:16px;padding:28px;width:700px;max-width:92vw;max-height:80vh;overflow-y:auto"></div>
</div>
<script>
var SUBMISSIONS = <?=json_encode(array_merge($submissions,[]), JSON_UNESCAPED_UNICODE)?>;
function previewSubmission(id) {
  var s = SUBMISSIONS.find(function(x){ return x.id === id; });
  if (!s) return;
  var box = document.querySelector('#submPreview > div');
  box.innerHTML = '<h2>' + s.title + '</h2><p style="color:#666;margin:8px 0 16px">' + s.excerpt + '</p><div style="line-height:1.8">' + s.content + '</div>';
  document.getElementById('submPreview').style.display = 'flex';
}
</script>
<?php admin_footer(); ?>
