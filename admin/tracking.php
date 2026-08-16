<?php
/**
 * 行为追踪 — Webhook 回传配置 + 事件查看
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_login();
require_perm('settings');

$trackFile = DATA_DIR . '/tracking.json';
$cfg = json_read($trackFile);
$message = '';

// 保存 webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $cfg['webhooks'] = array_filter(array_map('trim', explode("\n", $_POST['webhooks'] ?? '')));
    json_write($trackFile, $cfg);
    $message = '追踪配置已保存';
}

// 删除事件
if (isset($_GET['clear'])) {
    try { Database::execute("DELETE FROM events"); } catch (Exception $e) {}
    flash('success', '事件已清空');
    header('Location: tracking.php');
    exit;
}

// 查看事件
$events = [];
$eventFilter = $_GET['event'] ?? '';
try {
    $sql = "SELECT * FROM events ORDER BY id DESC LIMIT 100";
    if ($eventFilter) $sql = "SELECT * FROM events WHERE event = ? ORDER BY id DESC LIMIT 100";
    $events = $eventFilter ? Database::query($sql, [$eventFilter]) : Database::query($sql);
} catch (Exception $e) { $events = []; }

// 事件统计
$eventCounts = [];
try { foreach (Database::query("SELECT event, COUNT(*) as cnt FROM events GROUP BY event ORDER BY cnt DESC") as $r) $eventCounts[$r['event']] = $r['cnt']; } catch (Exception $e) {}

admin_header('行为追踪');
?>
<div class="admin-layout">
  <?php admin_sidebar('tracking'); ?>
  <div class="main">
    <h1>📡 行为追踪</h1>
    <p class="sub">统一埋点 fcTrack · 写入 SQLite · Webhook 回传第三方工具</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <h2>🔌 接入方式</h2>
      <p class="text-sm text-muted mb-4">前端已自动注入 fcTrack，页面/按钮行为可上报。自定义埋点调用：</p>
      <pre style="background:#1e1e1e;color:#fff;padding:12px;border-radius:8px;font-size:13px">fcTrack('click', { element: 'download_btn', product: 'whitepaper' });
fcTrack('form_submit', { form: 'appointment' });</pre>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🔗 Webhook 回传</h2>
        <p class="text-sm text-muted mb-4">每个事件实时 POST 到以下地址（每行一个），可用于对接 CRM、数据分析工具等</p>
        <textarea name="webhooks" rows="4" placeholder="https://your-crm.example.com/webhook/openflow&#10;https://api.amplitude.com/..." style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars(implode("\n", $cfg['webhooks'] ?? []))?></textarea>
        <div style="margin-top:10px"><button type="submit" name="save" class="btn btn-primary">保存配置</button></div>
      </div>
    </form>

    <!-- 事件统计 -->
    <div class="card">
      <h2>📊 事件概览</h2>
      <?php if (empty($eventCounts)): ?><div class="empty" style="padding:24px">暂无事件数据</div>
      <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($eventCounts as $ev => $cnt): ?>
        <a href="?event=<?=urlencode($ev)?>" class="btn btn-sm <?=$eventFilter===$ev?'btn-primary':'btn-ghost'?>"><?=htmlspecialchars($ev)?> (<?=$cnt?>)</a>
        <?php endforeach; ?>
        <?php if ($eventFilter): ?><a href="tracking.php" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
        <a href="?clear=1" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="return confirm('清空全部事件?')">清空</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- 事件明细 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📋 事件明细（最近 100 条）</h2>
      <table>
        <thead><tr><th>时间</th><th>事件</th><th>用户</th><th>页面</th><th>属性</th></tr></thead>
        <tbody>
          <?php if (empty($events)): ?><tr><td colspan="5" class="empty">暂无事件</td></tr><?php endif; ?>
          <?php foreach ($events as $e): ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($e['created_at']??'',0,16))?></td>
            <td><span class="badge badge-gray" style="font-size:11px"><?=htmlspecialchars($e['event']??'')?></span></td>
            <td class="text-sm text-muted" style="max-width:120px"><?=htmlspecialchars($e['member_email'] ?: $e['uid'] ?? '')?></td>
            <td class="text-sm text-muted" style="max-width:160px"><?=htmlspecialchars($e['page']??'')?></td>
            <td class="text-sm" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($e['props']??'')?>"><?=htmlspecialchars($e['props'] ?? ($e['label'] ?? ''))?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
