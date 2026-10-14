<?php
/**
 * 运行与 Token 统计 —— 自动化任务观测 + AI token 每日/每小时消耗
 * 后台首页的「自动化观测」入口指向这里；清晨/傍晚的管理员简报也在这页配置。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/AiBudget.php';
require_once __DIR__ . '/../lib/OpsDigest.php';
require_login();
require_perm('automation');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save_digest'])) {
        $cfg = ops_digest_save([
            'recipients_raw' => (string)($_POST['recipients'] ?? ''),
            'morning_hour'   => (int)($_POST['morning_hour'] ?? 8),
            'evening_hour'   => (int)($_POST['evening_hour'] ?? 18),
        ]);
        audit('更新运行简报设置', 'ops-stats');
        $msg = '简报设置已保存。每天 ' . $cfg['morning_hour'] . ' 点与 ' . $cfg['evening_hour'] . ' 点后（由定时任务）各发一封给：' . htmlspecialchars(implode('、', $cfg['recipients'] ?: ['（未配置）']));
    }
    if (isset($_POST['send_now'])) {
        $r = ops_digest_send(($_POST['slot'] ?? 'm') === 'e' ? 'e' : 'm');
        $msg = $r['ok'] ? '简报已发送（' . $r['sent'] . ' 个收件人）。' : '发送失败：' . htmlspecialchars($r['error']);
        if (!$r['ok']) $msg .= '　注意：手动发送成功后，当前时点今天的自动简报不会再重发。';
    }
}

$cfg        = ops_digest_settings();
$running    = ops_running_tasks();
$todayFlows = ops_today_flow_stats();
$tokens     = ai_spend();
$b          = ai_budget_settings();
$cur        = $b['currency'];
$hourly     = ops_token_hourly();
$byDay      = ops_token_daily(14);
$sentMark   = json_read(DATA_DIR . '/ops-digest-sent.json');
if (!is_array($sentMark)) $sentMark = [];
$logTail    = json_read(automation_log_file());
if (!is_array($logTail)) $logTail = [];
$logTail    = array_reverse(array_slice($logTail, -12));
$hourlyMax  = 0;
foreach ($hourly as $r) $hourlyMax = max($hourlyMax, (int)$r['in_tokens'] + (int)$r['out_tokens']);

admin_header('⚡ 运行与 Token 统计');
?>
<div style="max-width:1080px">
  <h1 style="margin:0 0 4px">⚡ 运行与 Token 统计</h1>
  <p style="color:#666;margin:0 0 18px">
    正在跑的自动化任务、每日 / 每小时 token 消耗。每天清晨与傍晚会把执行、流量、转化、token
    情况汇总成简报邮件发给管理员（底部可配置）。
  </p>
  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px"><?= $msg ?></div><?php endif; ?>

  <!-- 顶部速览 -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:22px">
    <a href="#running" style="text-decoration:none;color:inherit">
      <div class="wb-stat" style="border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--surface)">
        <div class="l" style="font-size:12px;color:var(--text-2)">正在跑 / 排队</div>
        <div class="n" style="font-size:24px;font-weight:800"><?= count($running) ?></div>
      </div>
    </a>
    <div style="border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--surface)">
      <div style="font-size:12px;color:var(--text-2)">启用流程</div>
      <div style="font-size:24px;font-weight:800"><?= count(array_filter(automation_get(), fn($f) => !empty($f['enabled']))) ?> / <?= count(automation_get()) ?></div>
    </div>
    <div style="border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--surface)">
      <div style="font-size:12px;color:var(--text-2)">今日 token</div>
      <div style="font-size:24px;font-weight:800"><?= number_format((float)$tokens['tokens']) ?></div>
    </div>
    <div style="border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--surface)">
      <div style="font-size:12px;color:var(--text-2)">今日花费</div>
      <div style="font-size:24px;font-weight:800"><?= htmlspecialchars($cur) . number_format((float)$tokens['cost'], 4) ?></div>
    </div>
  </div>

  <!-- 正在执行的任务 -->
  <h2 style="font-size:16px;margin:22px 0 8px" id="running">正在执行的自动化任务</h2>
  <p style="font-size:12.5px;color:#888;margin:0 0 8px">
    「delay 延时」节点会把流程挂到队列里到点续流——这里列的就是还在等待 / 待执行的续流任务。
  </p>
  <table class="table">
    <tr><th>流程</th><th>续流起点（第 N 步）</th><th>计划执行时间</th><th>状态</th></tr>
    <?php if (!$running): ?>
      <tr><td colspan="4"><span style="color:#999;font-size:13px">当前没有排队中的延时任务。流程触发延时节点后会出现在这里。</span></td></tr>
    <?php endif; ?>
    <?php foreach ($running as $q): ?>
      <tr>
        <td><?= htmlspecialchars($q['flow_name'] ?: $q['flow_id']) ?></td>
        <td>第 <?= $q['step'] + 1 ?> 步</td>
        <td><?= htmlspecialchars($q['run_at']) ?></td>
        <td><?= $q['state'] === 'delaying' ? '⏳ 延时等待' : '▶️ 到期待执行' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- 每小时 token -->
  <h2 style="font-size:16px;margin:26px 0 8px">今日 token 消耗（每小时）</h2>
  <?php if (!$hourly): ?>
    <div class="empty" style="padding:18px;font-size:13px;color:var(--text-3)">今天还没有 AI 调用记录。AI 被调用（创作台、助手、Agent、自动化）后会自动记账；若一直为空，先到 <a href="/xmp/ai-config">AI 配置</a> 确认已接入模型。</div>
  <?php else: ?>
    <div style="display:flex;align-items:flex-end;gap:6px;height:130px;margin:10px 0 4px">
      <?php foreach ($hourly as $r): $tk = (int)$r['in_tokens'] + (int)$r['out_tokens'];
          $pct = $hourlyMax > 0 ? max(3, round($tk / $hourlyMax * 100)) : 3; ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?= htmlspecialchars((string)$r['h']) ?>:00 · <?= number_format($tk) ?> token · <?= htmlspecialchars($cur) . number_format((float)$r['cost'], 4) ?>">
          <span style="font-size:10px;color:var(--text-3)"><?= number_format($tk) ?></span>
          <div style="width:100%;height:<?=$pct?>%;background:var(--accent);opacity:.85;border-radius:5px 5px 0 0"></div>
          <span style="font-size:10px;color:var(--text-3)"><?= htmlspecialchars((string)$r['h']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <table class="table" style="margin-top:10px">
      <tr><th>小时</th><th>调用</th><th>输入 token</th><th>输出 token</th><th>花费</th><th>失败</th></tr>
      <?php foreach ($hourly as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)$r['h']) ?>:00 – <?= htmlspecialchars((string)$r['h']) ?>:59</td>
          <td><?= number_format((float)$r['calls']) ?></td>
          <td><?= number_format((float)$r['in_tokens']) ?></td>
          <td><?= number_format((float)$r['out_tokens']) ?></td>
          <td><?= htmlspecialchars($cur) . number_format((float)$r['cost'], 4) ?></td>
          <td><?= (int)$r['failed'] > 0 ? '<span style="color:#c0392b">' . (int)$r['failed'] . '</span>' : '0' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <!-- 每日 token -->
  <h2 style="font-size:16px;margin:26px 0 8px">每日 token 消耗（近 14 天）</h2>
  <table class="table">
    <tr><th>日期</th><th>调用</th><th>token</th><th>花费</th><th>失败</th></tr>
    <?php if (!$byDay): ?><tr><td colspan="5"><span style="color:#999;font-size:13px">暂无记录</span></td></tr><?php endif; ?>
    <?php foreach ($byDay as $r): ?>
      <tr>
        <td><?= htmlspecialchars((string)$r['k']) ?></td>
        <td><?= number_format((float)$r['calls']) ?></td>
        <td><?= number_format((int)$r['in_tokens'] + (int)$r['out_tokens']) ?></td>
        <td><?= htmlspecialchars($cur) . number_format((float)$r['cost'], 4) ?></td>
        <td><?= (int)$r['failed'] > 0 ? '<span style="color:#c0392b">' . (int)$r['failed'] . '</span>' : '0' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- 当日流程执行 -->
  <h2 style="font-size:16px;margin:26px 0 8px">今日流程执行情况</h2>
  <table class="table">
    <tr><th>流程</th><th>执行步数</th><th>失败</th><th>最近动作</th><th>最近时间</th></tr>
    <?php if (!$todayFlows): ?><tr><td colspan="5"><span style="color:#999;font-size:13px">今天还没有流程被触发。</span></td></tr><?php endif; ?>
    <?php foreach ($todayFlows as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f['name']) ?></td>
        <td><?= (int)$f['steps'] ?></td>
        <td><?= $f['errors'] > 0 ? '<span style="color:#c0392b">' . (int)$f['errors'] . '</span>' : '0' ?></td>
        <td style="font-size:12.5px;color:var(--text-2)"><?= htmlspecialchars($f['last_msg']) ?></td>
        <td style="font-size:12px;color:var(--text-3)"><?= htmlspecialchars(substr($f['last_at'], 11)) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- 最近日志 -->
  <h2 style="font-size:16px;margin:26px 0 8px">自动化日志（最近 12 条）</h2>
  <table class="table">
    <tr><th style="width:150px">时间</th><th style="width:140px">流程</th><th>消息</th></tr>
    <?php foreach ($logTail as $e): ?>
      <tr>
        <td style="font-size:12px;color:var(--text-3)"><?= htmlspecialchars((string)($e['time'] ?? '')) ?></td>
        <td style="font-size:12.5px"><?= htmlspecialchars((string)($e['flow'] ?? '')) ?></td>
        <td style="font-size:12.5px<?= ($e['level'] ?? '') === 'error' ? ';color:#c0392b' : '' ?>"><?= htmlspecialchars((string)($e['message'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- 简报设置 -->
  <h2 style="font-size:16px;margin:26px 0 8px">📮 管理员简报（清晨 / 傍晚）</h2>
  <?php
    $mk = 'm:' . date('Y-m-d'); $ek = 'e:' . date('Y-m-d');
  ?>
  <p style="font-size:12.5px;color:#888;margin:0 0 10px">
    由 <code>api/cron.php</code> 定时任务驱动（请确保外部 cron 每分钟在调它）。今天：清晨档 <?= !empty($sentMark[$mk]) ? '✅ 已发送' : '未发送' ?> · 傍晚档 <?= !empty($sentMark[$ek]) ? '✅ 已发送' : '未发送' ?>。
  </p>
  <form method="post" style="margin-bottom:14px">
    <?= csrf_field() ?>
    <table class="table" style="max-width:720px">
      <tr><th style="width:200px">收件人邮箱</th>
          <td><textarea name="recipients" rows="2" style="width:100%" placeholder="多个邮箱用逗号或换行分隔"><?= htmlspecialchars(implode(', ', $cfg['recipients'])) ?></textarea>
              <span style="font-size:11px;color:#999">留空则回落到站点管理员邮箱（系统设置里的 admin_email）</span></td></tr>
      <tr><th>清晨简报时点</th>
          <td><input name="morning_hour" type="number" min="0" max="23" value="<?= (int)$cfg['morning_hour'] ?>" style="width:80px"> 点后发送（当天第一次）</td></tr>
      <tr><th>傍晚简报时点</th>
          <td><input name="evening_hour" type="number" min="0" max="23" value="<?= (int)$cfg['evening_hour'] ?>" style="width:80px"> 点后发送（当天第二次）</td></tr>
    </table>
    <div style="display:flex;gap:10px;margin-top:12px">
      <button class="btn btn-s" name="save_digest" value="1">保存设置</button>
      <button class="btn btn-ghost btn-s" name="send_now" value="1" onclick="this.form.slot.value='m'">发清晨测试简报</button>
      <button class="btn btn-ghost btn-s" name="send_now" value="1" onclick="this.form.slot.value='e'">发傍晚测试简报</button>
    </div>
    <input type="hidden" name="slot" value="m">
  </form>
</div>
<?php admin_footer(); ?>
