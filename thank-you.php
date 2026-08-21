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
            ['type' => 'link', 'icon' => '📄', 'title' => '先看一份增长报告样本', 'desc' => '在等待期间，可以先查看同行业脱敏增长报告。', 'url' => '/downloads.php', 'btn' => '查看资料'],
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
            ['type' => 'link', 'icon' => '⬇️', 'title' => '下载资料', 'desc' => '点击下载你申请的资料文件。', 'url' => '/downloads.php', 'btn' => '下载资料'],
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($cfg['title'])?> | <?=htmlspecialchars($siteName)?></title>
<link rel="stylesheet" href="/assets/tokens.css?v=20260816">
<link rel="stylesheet" href="/assets/modules.css?v=20260816">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body>
<script src="/assets/site-shell.js?v=20260823" data-cfasync="false" data-page="home"></script> class="min-h-screen">
  

  <div class="mx-auto px-5 py-14 text-center" style="max-width:720px">
    <div class="text-6xl mb-4"><?=$cfg['emoji']?></div>
    <h1 class="text-3xl font-bold text-gray-900"><?=htmlspecialchars($cfg['title'])?></h1>
    <p class="mt-3 text-gray-600 text-lg"><?=htmlspecialchars($cfg['sub'])?></p>

    <div class="mt-10 grid gap-4">
      <?php foreach ($cfg['blocks'] as $b): ?>
      <div class="bg-white border border-[var(--border)] rounded-2xl p-6 text-left flex gap-4" style="box-shadow:0 4px 16px rgba(0,0,0,.05)">
        <div class="text-3xl flex-shrink-0"><?=$b['icon']?></div>
        <div class="flex-1">
          <h3 class="font-bold text-gray-900 text-lg"><?=htmlspecialchars($b['title'])?></h3>
          <p class="mt-1 text-gray-600 text-sm"><?=htmlspecialchars($b['desc'])?></p>
          <?php if (($b['type'] ?? '') === 'link' && !empty($b['url'])): ?>
          <a href="<?=htmlspecialchars($b['url'])?>" class="mt-4 inline-flex items-center gap-2 rounded-full bg-[var(--accent)] text-[var(--accent)] px-6 py-2.5 font-semibold text-sm hover:bg-[#333] transition">
            <?=htmlspecialchars($b['btn'] ?? '前往')?> →
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <a href="/" class="mt-10 inline-block text-[#2b5f7e] text-sm hover:underline">← 返回首页</a>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:720px">
      <div class="mb-2"><?=htmlspecialchars($siteName)?> · <?=htmlspecialchars($site['site_slogan'] ?? '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)">&copy; 2026 <?=htmlspecialchars($site['company_name'] ?? $siteName)?></div>
    </div>
  </footer>
</body>
</html>
