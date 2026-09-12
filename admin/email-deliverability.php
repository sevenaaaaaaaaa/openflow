<?php
/**
 * 邮件送达率中心 — 发件域认证自检 / 抑制名单 / 退信投诉率 / 邮件收入归因
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/EmailDeliverability.php';
require_login();
require_perm('email');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string)($_POST['act'] ?? '');
    $em = (string)($_POST['email'] ?? '');
    if ($act === 'unsuppress' && $em !== '') email_unsuppress($em);
    elseif ($act === 'suppress' && $em !== '') email_suppress($em, 'manual');
    header('Location: /xmp/email-deliverability');
    exit;
}

$dns = email_dns_auth();
$stats = email_deliverability_stats();
$supp = email_suppression_list();
$attr = email_attribution_report();
$reasonLabel = ['hard_bounce' => '硬退信', 'complaint' => '投诉', 'unsubscribe' => '退订', 'manual' => '手动'];
$authPill = fn($s) => $s === 'ok' ? ['st-ok', '已配置'] : ($s === 'unknown' ? ['st-faint', '未检测到'] : ['st-danger', '未配置']);

admin_header('邮件送达率');
?>
<div class="admin-layout">
  <?php admin_sidebar('email-deliverability'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>邮件送达率</h1><p class="v-sub">发件域认证自检 · 抑制名单（硬退信/投诉永久不发） · 退信投诉率 · 邮件收入归因。对标专业 ESP 的送达与合规。</p></div>
      <div class="v-actions"><a class="btn btn-s btn-sm" href="/xmp/email">← 邮件营销</a></div>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">已发送</div><div class="k-val mono"><?=$stats['sent']?></div><div class="k-sub">累计营销邮件</div></div>
      <div class="kpi"><div class="k-label">打开</div><div class="k-val mono"><?=$stats['opens']?></div><div class="k-sub">去重人数</div></div>
      <div class="kpi"><div class="k-label">点击</div><div class="k-val mono"><?=$stats['clicks']?></div><div class="k-sub">去重人数</div></div>
      <div class="kpi"><div class="k-label">退信率</div><div class="k-val mono"><?=$stats['bounce_rate']?>%</div><div class="k-sub"><?=$stats['bounces']?> 封退信 · 目标 &lt;2%</div></div>
      <div class="kpi"><div class="k-label">投诉率</div><div class="k-val mono"><?=$stats['complaint_rate']?>%</div><div class="k-sub"><?=$stats['complaints']?> 次投诉 · 目标 &lt;0.1%</div></div>
      <div class="kpi"><div class="k-label">抑制名单</div><div class="k-val mono"><?=$stats['suppressed']?></div><div class="k-sub">不再发送的地址</div></div>
    </div>

    <div class="panel" style="margin-top:16px">
      <div class="p-head"><h3>发件域认证</h3><span class="p-sub mono"><?=htmlspecialchars($dns['domain'] ?: '未识别发件域')?></span></div>
      <div class="p-body" style="display:flex;gap:14px;flex-wrap:wrap">
        <?php foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC', 'mx' => 'MX'] as $k => $label): [$cls, $txt] = $authPill($dns[$k]); ?>
        <div style="flex:1;min-width:150px;border:1px solid var(--border);border-radius:10px;padding:12px 14px">
          <div style="font-weight:700"><?=$label?> <span class="st <?=$cls?>" style="font-size:10.5px;padding:1px 7px;border-radius:999px"><?=$txt?></span></div>
          <div class="text-muted" style="font-size:11.5px;margin-top:4px"><?=['spf'=>'发件服务器授权','dkim'=>'邮件签名','dmarc'=>'反伪造策略','mx'=>'邮件交换记录'][$k]?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($dns['spf'] !== 'ok' || $dns['dmarc'] !== 'ok'): ?>
      <div class="p-body text-muted" style="font-size:12.5px;border-top:1px solid var(--border-soft,var(--border))">建议：先在 DNS 配置 SPF（含发信服务商）与 DMARC（p=none 起步），再逐步收紧；新域名先低频预热 2–4 周。</div>
      <?php endif; ?>
    </div>

    <div class="panels" style="margin-top:16px;display:grid;grid-template-columns:1.2fr 1fr;gap:16px;align-items:start">
      <div class="panel">
        <div class="p-head"><h3>抑制名单</h3><span class="p-sub mono">硬退信/投诉永久不发</span></div>
        <div class="p-body">
          <form method="post" style="display:flex;gap:8px;margin-bottom:12px" data-no-guard>
            <input type="hidden" name="_csrf_token" value="<?=csrf_token()?>">
            <input class="inp sm" name="email" placeholder="手动加入抑制（邮箱）" style="flex:1">
            <button class="btn btn-s btn-sm" name="act" value="suppress">加入</button>
          </form>
          <?php if (!$supp): ?><div class="text-muted" style="font-size:13px">暂无。硬退信与投诉会自动进入这里。</div><?php endif; ?>
          <?php foreach (array_slice($supp, 0, 60, true) as $em => $row): ?>
          <div style="display:flex;gap:10px;align-items:center;padding:6px 0;border-bottom:1px solid var(--border-soft,var(--border));font-size:13px">
            <span class="st st-faint" style="font-size:10.5px;padding:1px 7px;border-radius:999px"><?=htmlspecialchars($reasonLabel[$row['reason'] ?? ''] ?? ($row['reason'] ?? '其他'))?></span>
            <span style="flex:1;min-width:0;word-break:break-all"><?=htmlspecialchars((string)$em)?></span>
            <span class="text-muted mono" style="font-size:11px"><?=htmlspecialchars(substr((string)($row['at'] ?? ''), 0, 10))?></span>
            <form method="post" style="display:inline" data-no-guard>
              <input type="hidden" name="_csrf_token" value="<?=csrf_token()?>">
              <input type="hidden" name="email" value="<?=htmlspecialchars((string)$em)?>">
              <button class="btn btn-s btn-sm" name="act" value="unsuppress" style="color:var(--muted)">移出</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="panel">
        <div class="p-head"><h3>邮件收入归因</h3><span class="p-sub mono">点击→成交</span></div>
        <div class="p-body">
          <?php if (!$attr): ?><div class="text-muted" style="font-size:13px">暂无邮件带来的成交。邮件里的链接点击会打上活动标记，成交后自动归因到这里。</div><?php endif; ?>
          <?php foreach (array_slice($attr, 0, 20) as $a): ?>
          <div style="display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--border-soft,var(--border));font-size:13px">
            <span style="flex:1;min-width:0;word-break:break-all"><?=htmlspecialchars((string)($a['campaign'] ?? ''))?></span>
            <span class="text-muted" style="font-size:11.5px"><?=(int)($a['orders'] ?? 0)?> 单</span>
            <b>¥<?=number_format((float)($a['revenue'] ?? 0), 0)?></b>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
