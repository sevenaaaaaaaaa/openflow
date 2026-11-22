<?php
/**
 * 邮件设置 — 多渠道邮件配置
 * SMTP（完整）/ BillionMail / Ghost / 自定义 Webhook
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MailChannel.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $channels = mail_channels();
    $enabled = $_POST['enabled'] ?? [];
    $params = $_POST['params'] ?? [];
    foreach ($channels as $key => $ch) {
        if ($key === '_default') continue;
        $channels[$key]['enabled'] = !empty($enabled[$key]);
        $channels[$key]['params'] = $params[$key] ?? [];
    }
    if (!empty($_POST['default_channel'])) $channels['_default'] = $_POST['default_channel'];
    mail_channels_save($channels);
    $message = '邮件设置已保存';
}

$channels = mail_channels();
$defs = mail_channel_defs();

if (!defined('OF_EMBED')) admin_header('邮件设置');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('mail-settings'); ?>
  <div class="main">
<?php endif; ?>
    <div class="v-head">
      <div><h1>邮件设置</h1><p class="v-sub">配置邮件渠道，用于表单提交等通知邮件。SMTP 已完整实现，其余渠道 HTTP 接入。</p></div>
      <div class="v-actions"></div>
    </div>
    <?php if ($message): ?><div class="msg msg-success" style="margin-bottom:16px"><?=htmlspecialchars($message)?></div><?php endif; ?>

    <?php
    // ── 发信域名 DNS 健康检查（SPF/A 记录，决定进箱率）──
    $fromEmail = (string)($channels['smtp']['params']['from'] ?? '');
    $fromDomain = str_contains($fromEmail, '@') ? substr(strrchr($fromEmail, '@'), 1) : '';
    $dnsA = $fromDomain ? @dns_get_record($fromDomain, DNS_A) : [];
    $dnsTxt = $fromDomain ? @dns_get_record($fromDomain, DNS_TXT) : [];
    $hasA = !empty($dnsA);
    $hasSpf = false;
    foreach ($dnsTxt as $r) if (str_starts_with($r['txt'] ?? '', 'v=spf1')) $hasSpf = true;
    ?>
    <?php if ($fromDomain): ?>
    <div class="card" style="padding:16px 20px;margin-bottom:16px;border-color:<?=$hasSpf?'oklch(from var(--ok) l c h / 0.4)':'oklch(0.72 0.15 85 / 0.5)'?>">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:8px">📡 发信域名 DNS 健康（<?=htmlspecialchars($fromDomain)?>）</h3>
      <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;margin-bottom:10px">
        <span><?=$hasA?'ℹ️':'·'?> A 记录（<?=$hasA ? htmlspecialchars($dnsA[0]['ip'] ?? '') : '未解析，发信不依赖此项'?>）</span>
        <span><?=$hasSpf?'✅':'❌'?> SPF 记录<?=$hasSpf?'':'（未配置）'?></span>
      </div>
      <?php if (!$hasSpf): ?>
      <div style="font-size:12px;color:var(--muted);line-height:1.8;background:var(--hover);border-radius:10px;padding:10px 14px">
        <b style="color:var(--fg)">请在 DNS 服务商处按邮件服务商文档补齐（与本站服务器地址无关）：</b><br>
        ① SPF：按服务商给出的 include 写一条 TXT<br>
        ② DKIM：在邮件服务商后台生成并添加对应 TXT<br>
        ③ 检查 DMARC 策略是否与 SPF/DKIM 对齐<br>
        <span style="color:var(--fg)">A 记录只影响网站访问，不参与发信；不要为发信给域名加 A 记录。</span>
      </div>
      <?php else: ?>
      <div style="font-size:12px;color:var(--ok)">SPF 已配置；DKIM / DMARC 请在邮件服务商后台确认。</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($defs as $key => $def): $ch = $channels[$key] ?? []; ?>
      <div class="card" style="margin-bottom:16px;padding:20px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div style="min-width:0">
            <h3 style="font-size:15px;font-weight:600"><?=htmlspecialchars($def['label'])?></h3>
            <p style="font-size:12px;color:var(--muted);margin-top:2px"><?=htmlspecialchars($def['desc'])?></p>
          </div>
          <label style="margin-left:auto;display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--muted)">
            启用
            <input type="checkbox" name="enabled[<?=$key?>]" value="1" <?=!empty($ch['enabled'])?'checked':''?> style="width:18px;height:18px">
          </label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:14px">
          <?php foreach ($def['fields'] as $f => $label): $isSecret = in_array($f, ['pass','api_key']); ?>
          <div class="field">
            <label style="font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px"><?=htmlspecialchars($label)?></label>
            <input type="<?=$isSecret?'password':'text'?>" name="params[<?=$key?>][<?=$f?>]" value="<?=htmlspecialchars($ch['params'][$f] ?? '')?>" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:var(--surface);color:var(--fg)" placeholder="<?=htmlspecialchars($label)?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="card" style="padding:20px;margin-bottom:16px">
        <h3 style="font-size:15px;font-weight:600;margin-bottom:12px">默认邮件渠道</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php foreach ($defs as $key => $def): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:var(--muted)">
            <input type="radio" name="default_channel" value="<?=$key?>" <?=(($channels['_default'] ?? 'smtp') === $key)?'checked':''?> style="width:16px;height:16px">
            <?=htmlspecialchars($def['label'])?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-p">保存邮件设置</button>
    </form>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
