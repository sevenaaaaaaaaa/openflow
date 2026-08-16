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
?>
# 芭乐派 · OpenFlow
> 帮一人公司设计 Agent 能跑的增长系统
> 芭乐派增长操作系统的开源底座：TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长

## About
芭乐派（OpenFlow 科技有限公司）成立于 2026 年，专注帮一人公司设计 Agent 能跑的增长系统。OpenFlow 是其增长操作系统的开源底座，理论（芭乐派方法论）、工具（TIPS 平台）、落地（Agent 增长引擎）三位一体。

- Website: https://nownexts.com
- Contact: admin@nownexts.com
- Phone: 13166373667

## Pages
- Home: https://nownexts.com/
- Products: https://nownexts.com/product
- Capability: https://nownexts.com/capability
- Courses: https://nownexts.com/courses
- About: https://nownexts.com/about
- Academy: https://nownexts.com/academy
- Community: https://nownexts.com/community

## Articles
<?php foreach ($articles as $a):
  if (($a['status'] ?? 'draft') !== 'published') continue;
  $slug = $a['slug'] ?? '';
  if (empty($slug)) continue;
?>
- <?=htmlspecialchars($a['title'] ?? '')?>: https://nownexts.com/article/<?=htmlspecialchars($slug)?>
<?php endforeach; ?>

## Topics
<?php foreach ($topics as $t):
  if (($t['status'] ?? 'draft') !== 'published') continue;
?>
- <?=htmlspecialchars($t['title'] ?? '')?>: https://nownexts.com/topic/<?=htmlspecialchars($t['slug'])?>
<?php endforeach; ?>
