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
    header('Location: /xmp/index');
    exit;
}

// 收集当前配置完成度
$settings = json_read(DATA_DIR . '/settings.json');
$steps = [
    'site' => [
        'title' => '基本信息',
        'desc' => '站点名称、联系方式',
        'done' => !empty($settings['site_name']) && !empty($settings['phone']),
        'link' => 'settings.php',
        'icon' => '🏢',
    ],
    'ai' => [
        'title' => '配置 AI 助手',
        'desc' => '接入 DeepSeek/Kimi 等供应商',
        'done' => false,
        'link' => 'ai-config.php',
        'icon' => '🤖',
    ],
    'content' => [
        'title' => '发布第一篇文章',
        'desc' => '开始创建内容',
        'done' => count(get_articles()) > 0,
        'link' => 'article-edit.php',
        'icon' => '📝',
    ],
    'media' => [
        'title' => '上传素材',
        'desc' => '准备图片等素材',
        'done' => count(glob(UPLOAD_DIR . '/*/*')) > 3,
        'link' => 'media.php',
        'icon' => '🖼️',
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
.wizard-hero{text-align:center;padding:40px 0 28px}
.wizard-hero .logo{font-size:64px;line-height:1}
.wizard-progress{display:flex;align-items:center;justify-content:center;gap:8px;margin:24px 0 32px;flex-wrap:wrap}
.wstep{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-3)}
.wstep .dot{width:10px;height:10px;border-radius:50%;background:var(--surface-2);border:2px solid var(--border)}
.wstep.done .dot{background:var(--ok);border-color:var(--ok)}
.wstep .line{width:28px;height:2px;background:var(--border)}
.wstep.done .line{background:var(--ok)}
.wizard-card{display:flex;gap:16px;padding:20px;border:1px solid var(--border);border-radius:14px;background:var(--surface);margin-bottom:14px;transition:.15s;align-items:center}
.wizard-card:hover{border-color:var(--accent);box-shadow:0 4px 16px rgba(0,0,0,.06)}
.wizard-card .ic{font-size:30px;flex-shrink:0}
.wizard-card .info{flex:1}
.wizard-card .info h3{font-size:16px;font-weight:700;margin-bottom:4px}
.wizard-card .info p{font-size:13px;color:var(--text-3);margin:0}
.wizard-card .status{font-size:12px;font-weight:600;white-space:nowrap}
.wizard-card .status.ok{color:var(--ok)}
.wizard-card .status.todo{color:var(--warn)}
</style>
<div class="admin-layout">
  <?php admin_sidebar(''); ?>
  <div class="main">
    <div class="wizard-hero">
      <div class="logo">🚀</div>
      <h1 style="font-size:28px;margin-top:12px">欢迎使用 OpenFlow CMS</h1>
      <p class="text-muted" style="max-width:480px;margin:10px auto 0">用 4 个小步骤快速上手，完成后即可开始管理你的网站内容。</p>
    </div>

    <div class="wizard-progress">
      <?php $i = 0; foreach ($steps as $key => $s): if ($i > 0) echo '<span class="line" style="width:28px;height:2px;background:' . ($s['done'] ? 'var(--ok)' : 'var(--border)') . '"></span>'; ?>
      <span class="wstep <?=$s['done']?'done':''?>"><span class="dot"></span><?=htmlspecialchars($s['title'])?></span>
      <?php $i++; endforeach; ?>
    </div>

    <p style="text-align:center;font-size:14px;color:var(--text-3);margin-bottom:24px">已完成 <strong style="color:<?=$doneCount===4?'var(--ok)':'var(--text)'?>"><?=$doneCount?>/4</strong> 步</p>

    <?php foreach ($steps as $key => $s): ?>
    <a href="<?=htmlspecialchars($s['link'])?>" class="wizard-card" style="text-decoration:none;color:inherit">
      <div class="ic"><?=$s['icon']?></div>
      <div class="info">
        <h3><?=htmlspecialchars($s['title'])?> <?php if ($key==='ai'): ?><span class="badge badge-green" style="font-size:10px">推荐</span><?php endif; ?></h3>
        <p><?=htmlspecialchars($s['desc'])?></p>
      </div>
      <div class="status <?=$s['done']?'ok':'todo'?>"><?=$s['done']?'✓ 已完成':'→ 去完成'?></div>
    </a>
    <?php endforeach; ?>

    <div style="text-align:center;margin-top:28px">
      <a href="onboarding.php?done=1" class="btn btn-primary" style="padding:12px 40px">我已了解，开始使用 →</a>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
