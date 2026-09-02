<?php
/**
 * 表单感谢页 — 提交成功后展示
 * 支持 /thank-you?from=lead|course|consult|download|subscribe
 * 感谢内容可含：上手视频 / 二维码 / 资料下载
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$from = trim($_GET['from'] ?? 'lead');
$site = site_config();
$siteName = $site['site_name'] ?? 'OpenFlow';
$siteUrl = $site['site_url'] ?? 'https://example.com';

// 按来源定制感谢内容
$configs = [
    'lead' => [
        'emoji' => '🎉',
        'title' => '提交成功',
        'sub'   => '你的信息已收到，我们会在 1 个工作日内与你联系。',
        'blocks' => [
            ['type' => 'tip', 'icon' => '📌', 'title' => '接下来会发生什么', 'desc' => '我们的增长顾问会通过你留下的联系方式联系你，安排 30 分钟一对一沟通。'],
            ['type' => 'link', 'icon' => '📄', 'title' => '先看一份增长报告样本', 'desc' => '在等待期间，可以先查看同行业脱敏增长报告。', 'url' => '/downloads', 'btn' => '查看资料'],
            ['type' => 'link', 'icon' => '📚', 'title' => '浏览社区', 'desc' => '网站增长洞察、AI 运营实践与专题报告。', 'url' => '/community', 'btn' => '去社区'],
        ],
    ],
    'course' => [
        'emoji' => '🎓',
        'title' => '报名成功',
        'sub'   => '你已成功报名，课程信息已发送到你的邮箱。',
        'blocks' => [
            ['type' => 'link', 'icon' => '🎬', 'title' => '观看课程上手视频', 'desc' => '第一步先看这个 5 分钟的上手视频。', 'url' => '/courses.html', 'btn' => '去学习'],
            ['type' => 'tip', 'icon' => '💡', 'title' => '学习建议', 'desc' => '建议每周投入 3 小时，结合工作实操效果最佳。'],
        ],
    ],
    'consult' => [
        'emoji' => '🤝',
        'title' => '预约成功',
        'sub'   => '咨询时段已锁定，顾问会与你确认具体安排。',
        'blocks' => [
            ['type' => 'tip', 'icon' => '📅', 'title' => '预约确认', 'desc' => '请留意你的邮箱或微信，顾问会确认最终时间。'],
        ],
    ],
    'download' => [
        'emoji' => '📥',
        'title' => '资料下载',
        'sub'   => '你需要的资料已准备好，点击下方按钮获取。',
        'blocks' => [
            ['type' => 'link', 'icon' => '⬇️', 'title' => '下载资料', 'desc' => '点击下载你申请的资料文件。', 'url' => '/downloads', 'btn' => '下载资料'],
        ],
    ],
    'subscribe' => [
        'emoji' => '✉️',
        'title' => '订阅成功',
        'sub'   => '感谢订阅！每周内容更新会准时送达你的邮箱。',
        'blocks' => [
            ['type' => 'tip', 'icon' => '💌', 'title' => '请查收邮箱', 'desc' => '一封确认邮件已发送，请留意收件箱或垃圾箱。'],
            ['type' => 'link', 'icon' => '📚', 'title' => '先读几篇好文', 'desc' => '在等待期间，可以先看看社区精选文章。', 'url' => '/community', 'btn' => '去社区'],
        ],
    ],
];

$cfg = $configs[$from] ?? $configs['lead'];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($cfg['title'])?> | <?=htmlspecialchars($siteName)?></title>
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 感谢页：全部来自 modules.css（hero-center + link-it.top 列表 + cta-row），只收窄一列。 */
.ty{max-width:720px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
.ty .link-it .ic{font-size:18px}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('home'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="ty-hero">
    <div class="hero-center">
      <span class="badge ok" style="height:32px;padding:0 14px;font-size:13px"><span class="dot"></span><?=['lead'=>'已收到','course'=>'已报名','consult'=>'已预约','download'=>'已就绪','subscribe'=>'已订阅'][$from] ?? '已收到'?></span>
      <h1><?=htmlspecialchars($cfg['title'])?></h1>
      <p class="lead"><?=htmlspecialchars($cfg['sub'])?></p>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="ty-next">
    <div class="ty">
      <?php foreach ($cfg['blocks'] as $b): ?>
      <div class="card link-it top" style="padding:22px 24px;gap:16px;cursor:default">
        <span class="ic" style="width:42px;height:42px;border-radius:12px"><?=$b['icon']?></span>
        <span class="lt">
          <b style="font-size:16px"><?=htmlspecialchars($b['title'])?></b>
          <span style="font-size:13.5px;line-height:1.7;color:var(--muted);margin-top:4px"><?=htmlspecialchars($b['desc'])?></span>
          <?php if (($b['type'] ?? '') === 'link' && !empty($b['url'])): ?><a href="<?=htmlspecialchars($b['url'])?>" class="btn primary" style="height:40px;padding:0 18px;font-size:14px;margin-top:14px"><?=htmlspecialchars($b['btn'] ?? '前往')?> →</a><?php endif; ?>
        </span>
      </div>
      <?php endforeach; ?>
      <div class="cta-row" style="justify-content:center;margin-top:12px"><a href="/" class="btn subtle">← 返回首页</a></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
