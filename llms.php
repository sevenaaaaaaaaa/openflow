<?php
/**
 * Dynamic llms.txt — LLMs.txt protocol
 * https://llmstxt.org/
 */
header('Content-Type: text/plain; charset=utf-8');

$dataDir = __DIR__ . '/data';

function jread(string $p): array {
    return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : [];
}

$articles = jread($dataDir . '/articles/index.json');
$topics = jread($dataDir . '/topics.json');
$settings = jread($dataDir . '/settings.json');
$siteUrl = rtrim($settings['site_url'] ?? 'https://example.com', '/');
$siteName = $settings['site_name'] ?? 'OpenFlow';
$siteEmail = $settings['email'] ?? 'admin@example.com';
$sitePhone = $settings['phone'] ?? '';
?>
# <?=htmlspecialchars($siteName)?>
> 帮一人公司设计 Agent 能跑的增长系统
> 芭乐派增长操作系统的开源底座：TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长

## About
芭乐派增长操作系统的开源底座：理论（方法论）、工具（TIPS 平台）、落地（Agent 增长引擎）三位一体，帮一人公司设计 Agent 能跑的增长系统。

- Website: <?=$siteUrl?>
- Contact: <?=htmlspecialchars($siteEmail)?><?php if ($sitePhone): ?>- Phone: <?=htmlspecialchars($sitePhone)?><?php endif; ?>

## Pages
- Home: <?=$siteUrl?>/

## Articles
<?php foreach ($articles as $a):
  if (($a['status'] ?? 'draft') !== 'published') continue;
  $slug = $a['slug'] ?? '';
  if (empty($slug)) continue;
?>
- <?=htmlspecialchars($a['title'] ?? '')?>: https://example.com/article/<?=htmlspecialchars($slug)?>
<?php endforeach; ?>

## Topics
<?php foreach ($topics as $t):
  if (($t['status'] ?? 'draft') !== 'published') continue;
?>
- <?=htmlspecialchars($t['title'] ?? '')?>: https://example.com/topic/<?=htmlspecialchars($t['slug'])?>
<?php endforeach; ?>
