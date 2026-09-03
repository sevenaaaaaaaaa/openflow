<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/WebhookSystem.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $wh = WebhookSystem::create($_POST);
        $message = "Webhook 已创建 ({$wh['id']})";
    } elseif ($action === 'delete') {
        WebhookSystem::delete($_POST['id']);
        $message = '已删除';
    } elseif ($action === 'toggle') {
        WebhookSystem::update($_POST['id'], ['enabled' => isset($_POST['enabled'])]);
        $message = '已更新';
    } elseif ($action === 'replay') {
        $message = wh_replay_dead($_POST['delivery_id'] ?? '') ? '已重新排队，稍后由定时任务重投' : '重放失败：该死信记录已不存在';
    } elseif ($action === 'retry_now') {
        $st = wh_process_queue(50);
        $message = "已处理 {$st['processed']} 条待重投：成功 {$st['ok']} · 重新排队 {$st['requeued']} · 转死信 {$st['dead']}";
    } elseif ($action === 'test') {
        $results = WebhookSystem::trigger('webhook.test', ['message' => '测试触发', 'time' => date('Y-m-d H:i:s')]);
        $message = '测试已发送: ' . (count($results) > 0 ? ($results[0]['success'] ? '成功' : '失败') : '无匹配 Webhook');
    }
    header('Location: /xmp/webhooks' . ($message ? '?msg=' . urlencode($message) : ''));
    exit;
}

if (!empty($_GET['msg'])) $message = $_GET['msg'];
$webhooks = WebhookSystem::all();
$events = WebhookSystem::availableEvents();
$logs = WebhookSystem::logs(20);
$deliveries = wh_deliveries(30);
$deadList   = wh_dead_list(30);
$queueList  = wh_queue_list(30);

admin_header('Webhook 管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">Webhook 管理</h1>
      <div style="display:flex;gap:8px;margin-left:auto">
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="test"><button class="btn btn-ghost btn-sm">测试触发</button></form>
        <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary btn-sm">+ 创建 Webhook</button>
      </div>
    </div>
    <p class="sub">接收系统事件通知，自动推送到外部服务</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <table>
        <thead><tr><th>名称</th><th>URL</th><th>事件</th><th>最后触发</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($webhooks as $wh): ?>
        <tr>
          <td style="font-weight:600"><?=htmlspecialchars($wh['name'])?></td>
          <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($wh['url'])?>"><?=htmlspecialchars($wh['url'])?></td>
          <td><?=implode(', ', $wh['events'])?></td>
          <td style="font-size:12px;color:var(--muted)"><?=$wh['last_triggered'] ?: '从未'?></td>
          <td><span class="pill <?=$wh['enabled'] ? 'ok' : 'err'?>"><?=$wh['enabled'] ? '启用' : '禁用'?></span></td>
          <td>
            <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$wh['id']?>">
            <input type="hidden" name="enabled" value="<?=$wh['enabled'] ? '' : '1'?>"><button class="btn btn-sm btn-ghost"><?=$wh['enabled'] ? '禁用' : '启用'?></button></form>
            <form method="post" style="display:inline" data-confirm="确定删除？"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$wh['id']?>">
            <button class="btn btn-sm btn-danger">删除</button></form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($webhooks)): ?>
        <tr><td colspan="6" class="empty">暂无 Webhook</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($logs)): ?>
    <div class="card">
      <h2>触发日志（最近20条）</h2>
      <table>
        <thead><tr><th>时间</th><th>事件</th><th>触发数</th><th>成功</th><th>失败</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($logs) as $log): ?>
        <tr>
          <td style="font-size:12px"><?=$log['timestamp']?></td>
          <td><span class="pill gray"><?=htmlspecialchars($log['event'])?></span></td>
          <td><?=$log['webhooks_triggered']?></td>
          <td><span style="color:var(--ok)"><?=$log['success']?></span></td>
          <td><span style="color:var(--danger)"><?=$log['failed']?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>可用事件</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px">
        <?php foreach ($events as $event => $desc): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--hover);border-radius:8px;font-size:12px">
          <code style="font-size:11px"><?=htmlspecialchars($event)?></code>
          <span style="color:var(--muted);margin-left:auto"><?=htmlspecialchars($desc)?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<h2 style="margin-top:34px">投递保障</h2>
<p class="sub">首投失败不再直接丢弃：按 60s / 5min / 30min / 2h / 6h 退避重投，耗尽转入死信可手动重放。每次投递带稳定的 <code>X-Webhook-Delivery</code> 幂等键，重试时不变，接收方据此去重。</p>

<div class="card" style="margin-bottom:18px">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
    <h3 style="margin:0">待重投 <span class="pill gray"><?=count($queueList)?></span></h3>
    <?php if ($queueList): ?>
    <form method="post" style="margin-left:auto"><?=csrf_field()?><input type="hidden" name="action" value="retry_now">
      <button class="btn btn-ghost btn-sm">立即重投一轮</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$queueList): ?>
    <div class="of-empty" style="border:0;margin:0">没有待重投的投递。失败的事件会自动排到这里，由定时任务按退避重发。</div>
  <?php else: ?>
  <table class="lst-table"><thead><tr><th>事件</th><th style="width:110px">第几次</th><th style="width:170px">下次重投</th><th>上次错误</th></tr></thead><tbody>
    <?php foreach ($queueList as $q): ?>
    <tr><td><b><?=htmlspecialchars($q['event'] ?? '')?></b><div class="lst-slug" style="font-size:11.5px"><?=htmlspecialchars($q['delivery_id'] ?? '')?></div></td>
        <td class="mono"><?=(int)($q['attempt'] ?? 0)?> / <?=(int)($q['max_retry'] ?? 0)?></td>
        <td class="mono" style="font-size:12px"><?=date('m-d H:i:s', (int)($q['next_at'] ?? time()))?></td>
        <td style="font-size:12.5px;color:var(--muted)"><?=htmlspecialchars(mb_substr((string)($q['last_error'] ?? ''),0,60))?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:18px">
  <h3 style="margin:0 0 12px">死信 <span class="pill <?=$deadList?'hl':'gray'?>"><?=count($deadList)?></span></h3>
  <?php if (!$deadList): ?>
    <div class="of-empty" style="border:0;margin:0">没有死信。重试全部耗尽的投递才会落到这里。</div>
  <?php else: ?>
  <table class="lst-table"><thead><tr><th>事件</th><th style="width:90px">尝试</th><th style="width:150px">失败时间</th><th>最后错误</th><th class="c-act" style="width:90px"></th></tr></thead><tbody>
    <?php foreach ($deadList as $d): ?>
    <tr><td><b><?=htmlspecialchars($d['event'] ?? '')?></b><div class="lst-slug" style="font-size:11.5px"><?=htmlspecialchars($d['delivery_id'] ?? '')?></div></td>
        <td class="mono"><?=(int)($d['attempts'] ?? 0)?> 次</td>
        <td class="lst-when" style="font-size:12px"><?=htmlspecialchars(substr((string)($d['died_at'] ?? ''),5,14))?></td>
        <td style="font-size:12.5px;color:var(--danger)"><?=htmlspecialchars(mb_substr((string)($d['last_error'] ?? ''),0,60))?></td>
        <td class="c-act"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="replay">
          <input type="hidden" name="delivery_id" value="<?=htmlspecialchars($d['delivery_id'] ?? '')?>">
          <button class="btn btn-ghost btn-sm">重放</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:18px">
  <h3 style="margin:0 0 12px">投递明细 <span class="hint" style="font-weight:400">· 最近 <?=count($deliveries)?> 次尝试</span></h3>
  <?php if (!$deliveries): ?>
    <div class="of-empty" style="border:0;margin:0">还没有投递记录。</div>
  <?php else: ?>
  <table class="lst-table"><thead><tr><th style="width:150px">时间</th><th>Webhook</th><th>事件</th><th style="width:80px">第几次</th><th style="width:90px">HTTP</th><th style="width:80px">耗时</th></tr></thead><tbody>
    <?php foreach ($deliveries as $d): ?>
    <tr><td class="lst-when" style="font-size:12px"><?=htmlspecialchars(substr((string)($d['at'] ?? ''),5,14))?></td>
        <td><?=htmlspecialchars($d['webhook_name'] ?: ($d['webhook_id'] ?? ''))?></td>
        <td><?=htmlspecialchars($d['event'] ?? '')?></td>
        <td class="mono"><?=((int)($d['attempt'] ?? 0)) === 0 ? '首投' : '重投 '.(int)$d['attempt']?></td>
        <td><span class="badge <?=!empty($d['success'])?'badge-green':'badge-red'?>"><?=(int)($d['http_code'] ?? 0) ?: '—'?></span></td>
        <td class="mono" style="font-size:12px"><?=(int)($d['duration_ms'] ?? 0)?>ms</td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</div>

<div id="createDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:90%;max-width:500px">
    <h2 style="margin-bottom:16px">创建 Webhook</h2>
    <form method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="create">
      <div class="field"><label>名称</label><input type="text" name="name" required placeholder="如：Slack 通知"></div>
      <div class="field"><label>URL</label><input type="url" name="url" required placeholder="https://hooks.slack.com/..."></div>
      <div class="field"><label>事件 <span class="hint">按住 Ctrl 多选</span></label>
        <select name="events[]" multiple size="6" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--surface-strong)">
          <?php foreach ($events as $event => $desc): ?>
          <option value="<?=$event?>"><?=$event?> — <?=$desc?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>重试次数</label><input type="number" name="retry_count" value="3" min="0" max="10"></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">创建</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
