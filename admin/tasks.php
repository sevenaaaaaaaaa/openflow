<?php
/**
 * 任务分配 — 管理员给其他角色分配任务
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$tasksFile = DATA_DIR . '/tasks.json';
$tasks = json_read($tasksFile);
$users = get_users();

$message = '';
$error = '';

// 创建任务（管理员）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    csrf_verify();
    $title = trim($_POST['title'] ?? '');
    $assignee = trim($_POST['assignee'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due = $_POST['due_date'] ?? '';
    $desc = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $error = '任务标题不能为空';
    } elseif (empty($assignee) || !isset($users[$assignee])) {
        $error = '请选择有效的负责人';
    } else {
        $task = [
            'id' => 'task_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'title' => $title,
            'description' => $desc,
            'assignee' => $assignee,
            'assigner' => $_SESSION['admin_name'] ?? $_SESSION['admin_user'] ?? 'admin',
            'priority' => $priority,
            'status' => 'pending', // pending / in_progress / done / cancelled
            'progress' => 0,       // 0-100
            'due_date' => $due,
            'comments' => [],      // 评论数组 [{user, content, time}]
            'created_at' => date('Y-m-d H:i:s'),
            'completed_at' => '',
        ];
        $tasks[] = $task;
        json_write($tasksFile, $tasks);
        notify('任务', '您有新任务：' . $title, ($desc ?: '请尽快处理'), 'tasks.php');
        flash('success', '任务已创建并通知 ' . ($users[$assignee]['name'] ?? $assignee));
        header('Location: /xmp/tasks');
        exit;
    }
}

// 状态更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    foreach ($tasks as &$t) {
        if ($t['id'] === $id) {
            $t['status'] = $status;
            $t['completed_at'] = ($status === 'done') ? date('Y-m-d H:i:s') : '';
            if ($status === 'done') $t['progress'] = 100;
            if ($status === 'pending') $t['progress'] = 0;
            break;
        }
    }
    unset($t);
    json_write($tasksFile, $tasks);
    flash('success', '任务状态已更新');
    header('Location: /xmp/tasks');
    exit;
}

// 更新进度
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $progress = max(0, min(100, (int)($_POST['progress'] ?? 0)));
    foreach ($tasks as &$t) {
        if ($t['id'] === $id) {
            $t['progress'] = $progress;
            if ($progress >= 100) { $t['status'] = 'done'; $t['completed_at'] = date('Y-m-d H:i:s'); }
            elseif ($progress > 0 && $t['status'] === 'pending') { $t['status'] = 'in_progress'; }
            elseif ($progress === 0 && $t['status'] !== 'cancelled') { $t['status'] = 'pending'; }
            break;
        }
    }
    unset($t);
    json_write($tasksFile, $tasks);
    flash('success', '进度已更新');
    header('Location: /xmp/tasks');
    exit;
}

// 添加评论
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $content = trim($_POST['comment'] ?? '');
    if (!empty($content)) {
        foreach ($tasks as &$t) {
            if ($t['id'] === $id) {
                $t['comments'] = $t['comments'] ?? [];
                $t['comments'][] = [
                    'user' => $_SESSION['admin_name'] ?? $_SESSION['admin_user'] ?? '',
                    'content' => $content,
                    'time' => date('Y-m-d H:i:s'),
                ];
                break;
            }
        }
        unset($t);
        json_write($tasksFile, $tasks);
        flash('success', '评论已添加');
    }
    header('Location: /xmp/tasks');
    exit;
}

// 删除任务
if (isset($_GET['delete'])) {
    $tasks = array_values(array_filter($tasks, fn($t) => $t['id'] !== $_GET['delete']));
    json_write($tasksFile, $tasks);
    flash('success', '任务已删除');
    header('Location: /xmp/tasks');
    exit;
}

// 过滤视图
$me = $_SESSION['admin_user'] ?? '';
$myRole = $_SESSION['admin_role'] ?? '';
$view = $_GET['view'] ?? ($myRole === 'admin' ? 'all' : 'mine');
$filterStatus = $_GET['status'] ?? '';

$displayTasks = $tasks;
if ($view === 'mine') $displayTasks = array_values(array_filter($tasks, fn($t) => $t['assignee'] === $me));
if ($filterStatus) $displayTasks = array_values(array_filter($displayTasks, fn($t) => $t['status'] === $filterStatus));
usort($displayTasks, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? '') ?: strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$statusLabels = ['pending' => '待处理', 'in_progress' => '进行中', 'done' => '已完成', 'cancelled' => '已取消'];
$statusColors = ['pending' => 'var(--warn)', 'in_progress' => 'var(--accent)', 'done' => 'var(--ok)', 'cancelled' => 'var(--faint)'];
$prioLabels = ['high' => '高', 'medium' => '中', 'low' => '低'];
$prioColors = ['high' => 'var(--danger)', 'medium' => 'var(--warn)', 'low' => 'var(--ok)'];

admin_header('任务分配');
?>
<div class="admin-layout">
  <?php admin_sidebar('tasks'); ?>
  <div class="main">
    <h1>任务分配</h1>
    <p class="sub">管理员分配任务给其他角色 · 负责人可在「我的任务」中更新进度</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 创建任务 -->
    <?php if ($myRole === 'admin'): ?>
    <div class="card">
      <h2>➕ 分配新任务</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field"><label>任务标题 <span class="hint">· 必填</span></label><input type="text" name="title" required placeholder="如：撰写「GEO 优化白皮书」宣传文案"></div>
          <div class="field"><label>负责人</label><select name="assignee">
            <option value="">— 选择用户 —</option>
            <?php foreach ($users as $uk => $u): ?>
            <option value="<?=htmlspecialchars($uk)?>" <?=($uk === $me ? 'selected' : '')?>><?=htmlspecialchars($u['name'] ?? $uk)?>（<?=htmlspecialchars(['admin'=>'超管','marketing'=>'市场','sales'=>'销售'][$u['role'] ?? ''] ?? $u['role'])?>）</option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>优先级</label><select name="priority"><option value="high">🔴 高</option><option value="medium" selected>🟡 中</option><option value="low">🟢 低</option></select></div>
          <div class="field"><label>截止日期</label><input type="date" name="due_date"></div>
        </div>
        <div class="field"><label>任务描述</label><textarea name="description" rows="2" placeholder="补充任务细节、交付要求等"></textarea></div>
        <button type="submit" name="add_task" class="btn btn-primary">分配任务</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- 视图切换 -->
    <div class="flex gap-3 mb-4" style="align-items:center;flex-wrap:wrap">
      <?php if ($myRole === 'admin'): ?>
      <a href="tasks.php?view=all<?=$filterStatus?'&status='.$filterStatus:''?>" class="btn btn-sm <?=$view==='all'?'btn-primary':'btn-ghost'?>">全部任务</a>
      <?php endif; ?>
      <a href="tasks.php?view=mine<?=$filterStatus?'&status='.$filterStatus:''?>" class="btn btn-sm <?=$view==='mine'?'btn-primary':'btn-ghost'?>">我的任务</a>
      <?php foreach ($statusLabels as $sk => $sl): ?>
      <a href="tasks.php?view=<?=$view?>&status=<?=$sk?>" class="btn btn-sm <?=$filterStatus===$sk?'btn-primary':'btn-ghost'?>"><?=$sl?></a>
      <?php endforeach; ?>
      <?php if ($filterStatus): ?><a href="tasks.php?view=<?=$view?>" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
      <span class="text-sm text-muted" style="margin-left:auto">共 <?=count($displayTasks)?> 个任务</span>
    </div>

    <!-- 任务列表 -->
    <div class="card" style="padding:0;overflow-x:auto">
      <table>
        <thead><tr><th>任务</th><th>负责人</th><th>优先级</th><th>截止</th><th>进度</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($displayTasks)): ?>
          <tr><td colspan="6" class="empty">暂无任务</td></tr>
          <?php endif; ?>
          <?php foreach ($displayTasks as $t): $progress = (int)($t['progress'] ?? 0); $commentCount = count($t['comments'] ?? []); ?>
          <tr onclick="toggleTaskDetail('<?=htmlspecialchars($t['id'])?>')" style="cursor:pointer">
            <td>
              <strong><?=htmlspecialchars($t['title'])?></strong>
              <?php if (!empty($t['description'])): ?><div class="text-sm text-muted" style="font-size:12px;margin-top:2px"><?=htmlspecialchars(mb_substr($t['description'], 0, 60))?></div><?php endif; ?>
              <?php if ($commentCount > 0): ?><div class="text-sm" style="font-size:11px;color:var(--accent);margin-top:2px">💬 <?=$commentCount?> 条评论</div><?php endif; ?>
            </td>
            <td><?=htmlspecialchars($users[$t['assignee']]['name'] ?? $t['assignee'])?></td>
            <td><span style="color:<?=$prioColors[$t['priority']]?>;font-weight:600"><?=$prioLabels[$t['priority']]?></span></td>
            <td class="text-sm <?=($t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done') ? '' : 'text-muted'?>" style="<?=($t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done') ? 'color:var(--danger);font-weight:600' : ''?>"><?=htmlspecialchars($t['due_date'] ?: '—')?><?=($t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done') ? ' ⚠️' : ''?></td>
            <td style="min-width:130px">
              <div style="display:flex;align-items:center;gap:6px">
                <div style="flex:1;height:6px;background:var(--surface-2);border-radius:99px;overflow:hidden"><div style="height:100%;width:<?=$progress?>%;background:<?=$progress>=100?'var(--ok)':($progress>=50?'var(--warn)':'var(--danger)')?>"></div></div>
                <span class="text-sm" style="font-size:12px;font-weight:600;width:36px"><?=$progress?>%</span>
              </div>
            </td>
            <td style="white-space:nowrap" onclick="event.stopPropagation()">
              <?php if ($t['assignee'] === $me || $myRole === 'admin'): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>">
                <input type="hidden" name="progress" value="<?=$progress>=100?0:$progress+25?>" />
                <button type="submit" name="update_progress" class="btn btn-ghost btn-sm" title="进度 +25%">+</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>">
                <select name="status" onchange="this.form.submit()" style="padding:4px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:12px">
                  <?php foreach ($statusLabels as $sk => $sl): ?>
                  <option value="<?=$sk?>" <?=$t['status']===$sk?'selected':''?>><?=$sl?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="update_status" value="1">
              </form>
              <?php endif; ?>
              <?php if ($myRole === 'admin'): ?>
              <a href="tasks.php?delete=<?=urlencode($t['id'])?>" class="btn btn-danger btn-sm" data-confirm="确认删除该任务?">删除</a>
              <?php endif; ?>
            </td>
          </tr>
          <tr id="taskDetail-<?=htmlspecialchars($t['id'])?>" style="display:none">
            <td colspan="6" style="padding:16px 20px;background:var(--surface-2)">
              <!-- 评论 -->
              <div style="font-weight:600;font-size:13px;margin-bottom:10px">💬 评论</div>
              <?php if (!empty($t['comments'])): ?>
              <div style="margin-bottom:12px">
                <?php foreach ($t['comments'] as $c): ?>
                <div style="background:var(--surface);border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:13px">
                  <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-3);margin-bottom:4px"><strong><?=htmlspecialchars($c['user'])?></strong><span><?=htmlspecialchars($c['time'])?></span></div>
                  <div><?=htmlspecialchars($c['content'])?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?><p class="text-sm text-muted" style="margin-bottom:10px">暂无评论</p><?php endif; ?>
              <form method="post" style="display:flex;gap:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>">
                <input type="text" name="comment" placeholder="添加评论…" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
                <button type="submit" name="add_comment" class="btn btn-primary btn-sm">发送</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function toggleTaskDetail(id) {
  var el = document.getElementById('taskDetail-' + id);
  if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
<?php admin_footer(); ?>
