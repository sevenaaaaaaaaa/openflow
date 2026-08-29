<?php
/**
 * Activity Log Viewer
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$logFile = DATA_DIR . '/activity.json';
$log = array_reverse(json_read($logFile));

$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user'] ?? '';
$targetFilter = $_GET['target'] ?? '';
if ($actionFilter) $log = array_values(array_filter($log, fn($l) => $l['action'] === $actionFilter));
if ($userFilter) $log = array_values(array_filter($log, fn($l) => $l['user'] === $userFilter));
if ($targetFilter) $log = array_values(array_filter($log, fn($l) => $l['target_type'] === $targetFilter));

$page = max(1, (int)($_GET['page'] ?? 1));
$pag = paginate($log, $page, 100);
$log = $pag['items'];

// Get unique users and actions for filters
$users = array_unique(array_map(fn($l) => $l['user'], json_read($logFile)));
$actions = array_unique(array_map(fn($l) => $l['action'], json_read($logFile)));
$targets = array_unique(array_map(fn($l) => $l['target_type'], json_read($logFile)));

if (!defined('OF_EMBED')) admin_header('操作日志');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('activity'); ?>
  <div class="main">
<?php endif; ?>
    <h1>操作日志</h1>
    <p class="sub">记录所有内容变更操作 · 最近 500 条</p>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap;align-items:center">
      <select onchange="location.href='?action='+this.value+'&user=<?=$userFilter?>&target=<?=$targetFilter?>'" style="padding:6px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
        <option value="">全部操作</option>
        <?php foreach ($actions as $a): ?><option value="<?=htmlspecialchars($a)?>" <?=$actionFilter===$a?'selected':''?>><?=htmlspecialchars($a)?></option><?php endforeach; ?>
      </select>
      <select onchange="location.href='?action=<?=$actionFilter?>&user='+this.value+'&target=<?=$targetFilter?>'" style="padding:6px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
        <option value="">全部用户</option>
        <?php foreach ($users as $u): ?><option value="<?=htmlspecialchars($u)?>" <?=$userFilter===$u?'selected':''?>><?=htmlspecialchars($u)?></option><?php endforeach; ?>
      </select>
      <select onchange="location.href='?action=<?=$actionFilter?>&user=<?=$userFilter?>&target='+this.value" style="padding:6px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
        <option value="">全部类型</option>
        <?php foreach ($targets as $t): ?><option value="<?=htmlspecialchars($t)?>" <?=$targetFilter===$t?'selected':''?>><?=htmlspecialchars($t)?></option><?php endforeach; ?>
      </select>
      <span class="text-sm text-muted"><?=count($log)?> 条</span>
      <?php if ($actionFilter||$userFilter||$targetFilter): ?><a href="activity.php" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>时间</th><th>用户</th><th>操作</th><th>类型</th><th>详情</th></tr></thead>
        <tbody>
          <?php if (empty($log)): ?><tr><td colspan="5" class="empty">暂无日志</td></tr><?php endif; ?>
          <?php foreach ($log as $l): ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($l['time']??'',0,16))?></td>
            <td><code style="font-size:12px"><?=htmlspecialchars($l['user_name']??$l['user']??'')?></code></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($l['action']??'')?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['target_type']??'')?></td>
            <td style="max-width:300px;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($l['details']??'')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?=pagination_html($pag, 'activity.php?action=' . urlencode($actionFilter) . '&user=' . urlencode($userFilter) . '&target=' . urlencode($targetFilter))?>
    </div>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
