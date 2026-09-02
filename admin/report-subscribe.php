<?php
/**
 * 报表邮件订阅 — 每日/每周经营报表推送
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DashboardSystem.php';
require_login();
require_perm('settings');

$file = DATA_DIR . '/report-subscribers.json';
$subs = json_read($file);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $period = $_POST['period'] ?? 'daily';
    if ($email === '') { $message = '邮箱必填'; }
    else {
        $subs[] = ['id' => 'rs_' . bin2hex(random_bytes(4)), 'email' => $email, 'period' => $period, 'created_at' => date('Y-m-d H:i:s')];
        json_write($file, $subs);
        $message = '已订阅报表推送';
    }
}
if (isset($_GET['del'])) {
    $subs = array_values(array_filter($subs, fn($s) => ($s['id'] ?? '') !== $_GET['del']));
    json_write($file, $subs);
    header('Location: /xmp/report-subscribe');
    exit;
}
// 立即发送测试
if (isset($_GET['send'])) {
    require_once __DIR__ . '/../lib/MailCampaign.php';
    $html = report_build_html();
    $subject = '【经营报表】' . date('Y-m-d');
    foreach ($subs as $s) {
        if (($s['email'] ?? '') !== '') report_send_mail($s['email'], $subject, $html);
    }
    $message = '报表已发送给 ' . count($subs) . ' 位订阅者';
}

admin_header('报表订阅');
?>
<div class="admin-layout">
  <?php admin_sidebar('report-subscribe'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>📮 报表订阅</h1><p class="v-sub">每日/每周自动推送经营报表到邮箱 · cron 执行</p></div>
    </div>
    <?php if ($message): ?><?=msg(strpos($message, '必填') !== false ? 'error' : 'success', $message)?><?php endif; ?>

    <form method="post" class="card" style="margin-bottom:16px">
      <?= csrf_field() ?>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="email" name="email" placeholder="接收报表的邮箱" style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px">
        <select name="period" style="padding:9px;border:1.5px solid var(--border);border-radius:10px;font-size:13px"><option value="daily">每日</option><option value="weekly">每周</option></select>
        <button class="btn btn-s btn-sm">添加订阅</button>
      </div>
    </form>

    <div class="card">
      <h2 style="margin-bottom:12px">订阅列表 (<?=count($subs)?>)</h2>
      <?php if (empty($subs)): ?><p class="text-sm text-muted">暂无订阅者</p>
      <?php else: ?>
      <table>
        <thead><tr><th>邮箱</th><th>周期</th><th>订阅时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($subs as $s): ?>
          <tr>
            <td><?=htmlspecialchars($s['email'])?></td>
            <td><span class="badge badge-gray"><?=$s['period']==='weekly'?'每周':'每日'?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($s['created_at'] ?? '', 0, 16))?></td>
            <td><a href="?send=1" class="btn btn-s btn-sm" data-confirm="立即发送报表给所有订阅者?">📤 立即发送</a><a href="?del=<?=urlencode($s['id'])?>" class="btn btn-danger btn-sm">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <p class="text-sm text-muted" style="margin-top:12px">cron 每天/每周自动推送：UV/线索/订单/收入/会员 核心指标 + 来源归因。</p>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
