<?php
/**
 * 新手引导向导 — 首次登录后的欢迎与基础配置引导
 */
require_once __DIR__ . '/config.php';
require_login();

$onboardFile = DATA_DIR . '/onboarding.json';
$onboard = json_read($onboardFile);

// 完成向导
if (isset($_GET['done'])) {
    $onboard['completed'] = true;
    $onboard['completed_at'] = date('Y-m-d H:i:s');
    json_write($onboardFile, $onboard);
    header('Location: /xmp/workspace');
    exit;
}

// 收集当前配置完成度
$settings = json_read(DATA_DIR . '/settings.json');

// 支付/邮件是否已配置
$payReady = false; $mailReady = false;
try {
    $pc = json_read(DATA_DIR . '/payment-channels.json');
    foreach ($pc as $k => $v) { if ($k === '_default') continue; if (!empty($v['enabled'])) { $payReady = true; break; } }
} catch (Exception $e) {}
try {
    $mc = json_read(DATA_DIR . '/mail-channels.json');
    foreach ($mc as $k => $v) { if ($k === '_default') continue; if (!empty($v['enabled'])) { $mailReady = true; break; } }
} catch (Exception $e) {}

$steps = [
    'site' => [
        'title' => '基本信息',
        'desc' => '站点名称、联系方式',
        'done' => !empty($settings['site_name']) && !empty($settings['phone']),
        'link' => 'settings.php',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17v4M14 14h7v7h-7"/></svg>',
    ],
    'ai' => [
        'title' => '配置 AI',
        'desc' => '接入 DeepSeek/Kimi 等，让增长引擎能跑',
        'done' => false,
        'link' => 'ai-config.php',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9.5 2a2.5 2.5 0 0 1 5 0c.3 2.4 1.7 3.8 4.1 4.1a2.5 2.5 0 0 1 0 5c-2.4.3-3.8 1.7-4.1 4.1a2.5 2.5 0 0 1-5 0c-.3-2.4-1.7-3.8-4.1-4.1a2.5 2.5 0 0 1 0-5c2.4-.3 3.8-1.7 4.1-4.1Z"/><path d="M19 16.5 20.5 18 22 16"/></svg>',
    ],
    'pay' => [
        'title' => '支付与邮件',
        'desc' => '虎皮椒收款 + 通知邮件，接通转化链路',
        'done' => $payReady || $mailReady,
        'link' => 'payment-settings.php',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="m6 15 2 2 4-4"/></svg>',
    ],
    'content' => [
        'title' => '发布第一篇内容',
        'desc' => '让增长飞轮开始转起来',
        'done' => count(get_articles()) > 0,
        'link' => 'article-edit.php',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>',
    ],
];
$aiCfg = json_read(DATA_DIR . '/ai-config.json');
$aiReady = false;
foreach (($aiCfg['providers'] ?? []) as $p) if (!empty($p['enabled']) && !empty($p['api_key'])) { $aiReady = true; break; }
$steps['ai']['done'] = $aiReady;

$doneCount = count(array_filter($steps, fn($s) => $s['done']));

admin_header('欢迎使用');
?>
<style>
.wizard-hero{text-align:center;padding:34px 0 20px}
.wizard-hero .logo{width:64px;height:64px;border-radius:18px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;margin:0 auto}
.wizard-hero .logo svg{width:30px;height:30px}
.wizard-progress{display:flex;align-items:center;justify-content:center;gap:0;margin:22px 0 30px;flex-wrap:wrap}
.wstep{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--faint)}
.wstep .dot{width:22px;height:22px;border-radius:50%;background:var(--hover);border:2px solid var(--border);display:grid;place-items:center;font-family:var(--font-mono);font-size:11px;color:var(--faint)}
.wstep.done .dot{background:var(--ok-soft);border-color:var(--ok);color:var(--ok)}
.wstep .line{width:28px;height:2px;background:var(--border)}
.wstep.done .line{background:var(--ok)}
.wizard-card{display:flex;gap:16px;padding:20px;border:1px solid var(--border);border-radius:14px;background:var(--surface);margin-bottom:14px;transition:.15s;align-items:center}
.wizard-card:hover{border-color:var(--accent);box-shadow:var(--shadow-sm)}
.wizard-card .ic{width:44px;height:44px;border-radius:12px;background:var(--hover);color:var(--muted);display:grid;place-items:center;flex-shrink:0}
.wizard-card .ic svg{width:22px;height:22px}
.wizard-card .info{flex:1}
.wizard-card .info h3{font-size:16px;font-weight:600}
.wizard-card .info p{font-size:13px;color:var(--muted);margin:0}
.wizard-card .status{font-size:12px;font-weight:600;white-space:nowrap}
</style>
<div class="admin-layout">
  <?php admin_sidebar(''); ?>
  <div class="main">
    <div class="wizard-hero">
      <div class="logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2.2-.7-3 .8Z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.9A12.9 12.9 0 0 1 22 2c0 2.7-.9 7-4 11-2.9 3.8-6.1 5.7-9 7.6"/><path d="M9 12H4s.5-3 2-4c1.6-1.2 5 0 5 0"/><path d="M12 15v5s3.5-.5 4-2c.5-1.4 0-5 0-5"/></svg></div>
      <h1 style="font-size:26px;margin-top:14px;font-family:var(--font-display)">欢迎使用 OpenFlow</h1>
      <p style="color:var(--muted);max-width:480px;margin:10px auto 0;font-size:14px">4 个步骤，让增长系统跑起来。完成后，增长引擎会自动爬热点、写草稿、驱动转化。</p>
    </div>

    <div class="wizard-progress">
      <?php $i = 0; foreach ($steps as $key => $s): if ($i > 0) echo '<span class="line' . ($s['done'] ? ' done' : '') . '" style="' . ($s['done'] ? 'background:var(--ok)' : '') . '"></span>'; ?>
      <span class="wstep <?=$s['done']?'done':''?>"><span class="dot"><?=$s['done']?'✓':($i+1)?></span><?=htmlspecialchars($s['title'])?></span>
      <?php $i++; endforeach; ?>
    </div>

    <p style="text-align:center;font-size:14px;color:var(--muted);margin-bottom:22px">已完成 <strong style="color:<?=$doneCount===4?'var(--ok)':'var(--fg)'?>"><?=$doneCount?>/4</strong> 步</p>

    <?php foreach ($steps as $key => $s): ?>
    <a href="<?=htmlspecialchars($s['link'])?>" class="wizard-card" style="text-decoration:none;color:inherit">
      <div class="ic"><?=$s['icon']?></div>
      <div class="info">
        <h3><?=htmlspecialchars($s['title'])?> <?php if ($key==='ai'): ?><span class="st st-warn" style="font-size:10px">推荐</span><?php endif; ?></h3>
        <p><?=htmlspecialchars($s['desc'])?></p>
      </div>
      <div class="status" style="color:<?=$s['done']?'var(--ok)':'var(--warn)'?>"><?=$s['done']?'✓ 已完成':'→ 去完成'?></div>
    </a>
    <?php endforeach; ?>

    <div style="text-align:center;margin-top:28px">
      <a href="onboarding.php?done=1" class="btn btn-p" style="padding:12px 40px"><?=$doneCount===4?'进入工作台 →':'跳过引导，先看看 →'?></a>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
