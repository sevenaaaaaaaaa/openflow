<?php
/**
 * 内容审核 — 待审核列表 + 审核操作
 * 仅管理员(admin)与市场总监(marketing)可审核
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/review-lib.php';
require_login();
require_perm('reviews');

$queue = review_get_queue();
$message = '';

// 审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $rid = $_POST['id'] ?? '';
    $action = $_POST['action']; // approve / reject
    $note = trim($_POST['note'] ?? '');
    foreach ($queue as &$r) {
        if ($r['id'] === $rid) {
            $r['status'] = $action === 'approve' ? 'approved' : 'rejected';
            $r['reviewed_by'] = $_SESSION['admin_name'] ?? $_SESSION['admin_user'] ?? '';
            $r['reviewed_at'] = date('Y-m-d H:i:s');
            $r['review_note'] = $note;
            // 保存用于通知的信息（在 unset 引用后仍可用）
            $reviewedTitle = $r['title'] ?? '';
            $reviewedType = $r['type'] ?? '';
            $reviewedBy = $r['submitted_by'] ?? '';

            // 通过审核时，如果是文章且状态为 draft+review pending，改为 published
            if ($action === 'approve' && $r['type'] === 'article') {
                $a = get_article($r['target_id']);
                if ($a && (($a['review_status'] ?? '') === 'pending')) {
                    $a['status'] = 'published';
                    $a['review_status'] = 'approved';
                    save_article($a['id'], $a);
                    // IndexNow
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
                    indexnow_ping($protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . $a['slug']);
                }
            }
            // 通过审核时，页面标记 approved
            if ($action === 'approve' && $r['type'] === 'page') {
                // 页面已保存内容，仅标记审核通过记录
            }
            break;
        }
    }
    unset($r);
    json_write(review_queue_file(), $queue);
    // 通知审核人（管理员/市场总监）+ 定向通知提交人
    $titleShort = mb_substr($reviewedTitle ?? '', 0, 20);
    $reviewNote = $note ? ('\n驳回原因：' . $note) : '';
    notify('review', ($action === 'approve' ? '✅ 已通过：' : '⛔ 已驳回：') . $titleShort, ($action === 'approve' ? '内容已通过审核并发布' : '内容未通过审核') . $reviewNote, 'admin/reviews.php', ['admin', 'marketing']);
    if ($action === 'reject' && !empty($reviewedBy ?? '')) {
        // 定向通知提交人
        $typeLabel = ($reviewedType ?? '') === 'article' ? '文章' : (($reviewedType ?? '') === 'page' ? '页面' : '邮件');
        notify('review', '⛔ 你的内容被驳回：' . $titleShort, $typeLabel . '未通过审核' . $reviewNote, 'admin/reviews.php?status=rejected', ['user:' . $reviewedBy]);
    }
    flash('success', $action === 'approve' ? '已通过，内容已发布' : '已驳回，已通知提交人');
    header('Location: reviews.php');
    exit;
}

// 筛选
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? 'pending';
$display = $queue;
if ($typeFilter) $display = array_values(array_filter($display, fn($r) => $r['type'] === $typeFilter));
if ($statusFilter) $display = array_values(array_filter($display, fn($r) => ($r['status'] ?? '') === $statusFilter));
$display = array_reverse($display);

$ruleLabels = ['banned' => '违禁词', 'competitor' => '竞品词', 'low_quality' => '低质量', 'off_topic' => '偏离定位'];
$ruleColors = ['banned' => 'var(--danger)', 'competitor' => 'var(--warn)', 'low_quality' => '#9ca3af', 'off_topic' => '#7c3aed'];

admin_header('内容审核');
?>
<div class="admin-layout">
  <?php admin_sidebar('reviews'); ?>
  <div class="main">
    <h1>🛡️ 内容审核</h1>
    <p class="sub">检测违禁、竞品词、低质量、偏离产品定位的内容 · 由管理员与市场总监审核</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 筛选 -->
    <div class="flex gap-3 mb-4" style="flex-wrap:wrap;align-items:center">
      <a href="reviews.php?status=pending" class="btn btn-sm <?=$statusFilter==='pending'?'btn-primary':'btn-ghost'?>">⏳ 待审核 <span class="badge badge-red" style="margin-left:4px"><?=review_pending_count()?></span></a>
      <a href="reviews.php?status=approved" class="btn btn-sm <?=$statusFilter==='approved'?'btn-primary':'btn-ghost'?>">✅ 已通过</a>
      <a href="reviews.php?status=rejected" class="btn btn-sm <?=$statusFilter==='rejected'?'btn-primary':'btn-ghost'?>">⛔ 已驳回</a>
      <span style="width:12px"></span>
      <a href="reviews.php?type=article&status=<?=$statusFilter?>" class="btn btn-sm btn-ghost <?=$typeFilter==='article'?'btn-primary':''?>">文章</a>
      <a href="reviews.php?type=page&status=<?=$statusFilter?>" class="btn btn-sm btn-ghost <?=$typeFilter==='page'?'btn-primary':''?>">页面</a>
      <a href="reviews.php?type=email&status=<?=$statusFilter?>" class="btn btn-sm btn-ghost <?=$typeFilter==='email'?'btn-primary':''?>">邮件</a>
      <?php if ($typeFilter || $statusFilter !== 'pending'): ?><a href="reviews" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
    </div>

    <!-- 列表 -->
    <div class="card" style="padding:0;overflow-x:auto">
      <table>
        <thead><tr><th>类型</th><th>标题</th><th>命中问题</th><th>提交人</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($display)): ?>
          <tr><td colspan="7" class="empty">暂无记录</td></tr>
          <?php endif; ?>
          <?php foreach ($display as $r):
            $typeLabel = ['article' => '📝 文章', 'page' => '📄 页面', 'email' => '📧 邮件'][$r['type']] ?? $r['type'];
          ?>
          <tr>
            <td><span class="badge badge-gray" style="font-size:11px"><?=$typeLabel?></span></td>
            <td style="max-width:220px"><strong><?=htmlspecialchars($r['title'] ?? $r['target_id'])?></strong>
              <?php if ($r['type'] === 'article'): ?><div class="text-sm text-muted" style="font-size:11px"><a href="article-edit.php?id=<?=urlencode($r['target_id'])?>">编辑 →</a></div><?php endif; ?>
            </td>
            <td>
              <?php foreach ($r['issues'] ?? [] as $issue): ?>
              <span class="badge" style="font-size:11px;color:#fff;background:<?=$ruleColors[$issue['rule']]?>;margin:2px"><?=htmlspecialchars($issue['desc'])?></span>
              <?php endforeach; ?>
            </td>
            <td class="text-sm text-muted"><?=htmlspecialchars($r['submitted_by'] ?? '')?></td>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($r['submitted_at'] ?? '', 0, 16))?></td>
            <td>
              <?php if (($r['status'] ?? '') === 'pending'): ?><span class="badge badge-yellow" style="padding:4px 10px">⏳ 待审核</span>
              <?php elseif (($r['status'] ?? '') === 'approved'): ?><span class="badge badge-green" style="padding:4px 10px">✅ 已通过</span>
              <?php else: ?><span class="badge badge-red" style="padding:4px 10px">⛔ 已驳回</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <?php if (($r['status'] ?? '') === 'pending'): ?>
              <button class="btn btn-primary btn-sm" onclick="doReview('<?=htmlspecialchars($r['id'])?>','approve')">通过</button>
              <button class="btn btn-danger btn-sm" onclick="doReview('<?=htmlspecialchars($r['id'])?>','reject')">驳回</button>
              <?php else: ?>
              <span class="text-sm text-muted"><?=htmlspecialchars($r['reviewed_by'] ?? '')?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<form method="post" id="reviewForm" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="rfId">
  <input type="hidden" name="action" id="rfAction">
  <input type="hidden" name="note" id="rfNote">
</form>
<script>
function doReview(id, action) {
  var note = '';
  if (action === 'reject') {
    note = prompt('请输入驳回原因（可选）：');
    if (note === null) return;
  }
  document.getElementById('rfId').value = id;
  document.getElementById('rfAction').value = action;
  document.getElementById('rfNote').value = note || '';
  document.getElementById('reviewForm').submit();
}
</script>
<?php admin_footer(); ?>
