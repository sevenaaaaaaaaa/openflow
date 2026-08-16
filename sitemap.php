<?php
/**
 * Dynamic XML Sitemap — 全站 URL 索引
 * Access via: sitemap.xml (needs URL rewrite or direct sitemap.php)
 * 包含：静态页 / 文章 / 专题 / 课程 / 咨询师 / 直播 / 下载 / 市场 / 事件 / 落地页
 */
header('Content-Type: application/xml; charset=utf-8');

$dataDir = __DIR__ . '/data';

function jread(string $p): array {
    return file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : [];
}
function jget(string $f, string $k, $default = '') {
    $d = jread($f);
    return $d[$k] ?? $default;
}

// Base URL：优先 settings.json 的 site_url，其次自动检测
$settings = jread($dataDir . '/settings.json');
$siteUrl = rtrim($settings['site_url'] ?? '', '/');
if (empty($siteUrl) || str_contains($siteUrl, 'localhost')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $siteUrl = $protocol . '://' . $host;
}
$base = $siteUrl;

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <!-- Static pages -->
  <url><loc><?=$base?>/</loc><priority>1.0</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/about.html</loc><priority>0.8</priority><changefreq>monthly</changefreq></url>
  <url><loc><?=$base?>/capability.html</loc><priority>0.8</priority><changefreq>monthly</changefreq></url>
  <url><loc><?=$base?>/courses.html</loc><priority>0.8</priority><changefreq>monthly</changefreq></url>
  <url><loc><?=$base?>/academy.php</loc><priority>0.7</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/reviews.php</loc><priority>0.6</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/navigation.php</loc><priority>0.6</priority><changefreq>monthly</changefreq></url>
  <url><loc><?=$base?>/podcasts.php</loc><priority>0.6</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/marketplace.php</loc><priority>0.6</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/thank-you</loc><priority>0.3</priority><changefreq>yearly</changefreq></url>

  <!-- Category 分类页 -->
  <?php
  $navContent = file_exists($dataDir . '/site-nav-content.php') ? require $dataDir . '/site-nav-content.php' : [];
  foreach ($navContent as $secKey => $sec):
    foreach ($sec['subs'] ?? [] as $sub):
  ?>
  <url><loc><?=$base?>/category/<?=htmlspecialchars($secKey)?>/<?=htmlspecialchars($sub['key'])?></loc><priority>0.7</priority><changefreq>weekly</changefreq></url>
  <?php endforeach; endforeach; ?>

  <!-- Articles -->
  <?php
  $articles = jread($dataDir . '/articles/index.json');
  foreach ($articles as $a):
    if (($a['status'] ?? 'draft') !== 'published') continue;
    $slug = $a['slug'] ?? '';
    if (empty($slug)) continue;
  ?>
  <url>
    <loc><?=$base?>/article/<?=htmlspecialchars($slug)?></loc>
    <priority>0.6</priority>
    <changefreq>monthly</changefreq>
    <lastmod><?=htmlspecialchars(substr($a['updated_at'] ?? $a['created_at'] ?? '', 0, 10))?></lastmod>
  </url>
  <?php endforeach; ?>

  <!-- Courses -->
  <?php
  foreach (jread($dataDir . '/courses/index.json') as $c):
    if (($c['status'] ?? 'published') !== 'published') continue;
    $cslug = $c['slug'] ?? $c['id'] ?? '';
    if (!$cslug) continue;
  ?>
  <url><loc><?=$base?>/course/<?=htmlspecialchars($cslug)?></loc><priority>0.7</priority><changefreq>weekly</changefreq><lastmod><?=htmlspecialchars(substr($c['updated_at'] ?? '', 0, 10))?></lastmod></url>
  <?php endforeach; ?>

  <!-- Skills -->
  <?php
  foreach (jread($dataDir . '/skills/index.json') as $s):
    if (($s['status'] ?? 'published') !== 'published') continue;
    $sid = $s['id'] ?? '';
    if (!$sid) continue;
  ?>
  <url><loc><?=$base?>/skill/<?=htmlspecialchars($sid)?></loc><priority>0.6</priority><changefreq>monthly</changefreq></url>
  <?php endforeach; ?>

  <!-- Consultants -->
  <?php
  foreach (jread($dataDir . '/consultation/mentors.json') as $m):
    $mid = $m['id'] ?? '';
    if (!$mid) continue;
  ?>
  <url><loc><?=$base?>/consultation.php#mentor-<?=htmlspecialchars($mid)?></loc><priority>0.6</priority><changefreq>monthly</changefreq></url>
  <?php endforeach; ?>

  <!-- Live rooms -->
  <?php
  foreach (jread($dataDir . '/live/index.json') as $l):
    $lid = $l['id'] ?? '';
    if (!$lid) continue;
  ?>
  <url><loc><?=$base?>/live.php?id=<?=htmlspecialchars($lid)?></loc><priority>0.6</priority><changefreq>weekly</changefreq></url>
  <?php endforeach; ?>

  <!-- Downloads -->
  <?php
  foreach (jread($dataDir . '/downloads.json') as $d):
    if (($d['status'] ?? 'published') !== 'published') continue;
    $dslug = $d['slug'] ?? $d['id'] ?? '';
    if (!$dslug) continue;
  ?>
  <url><loc><?=$base?>/download/<?=htmlspecialchars($dslug)?></loc><priority>0.6</priority><changefreq>weekly</changefreq><lastmod><?=htmlspecialchars(substr($d['updated_at'] ?? '', 0, 10))?></lastmod></url>
  <?php endforeach; ?>

  <!-- Podcast episodes -->
  <?php
  $pods = jread($dataDir . '/podcasts.json');
  foreach (($pods['items'] ?? []) as $ep):
    if (($ep['status'] ?? 'published') !== 'published') continue;
  ?>
  <url><loc><?=$base?>/podcasts?play=<?=htmlspecialchars($ep['id'] ?? '')?></loc><priority>0.5</priority><changefreq>weekly</changefreq></url>
  <?php endforeach; ?>

  <!-- Skills / Marketplace -->
  <?php
  foreach (jread($dataDir . '/skills/index.json') as $sk):
    $skid = $sk['id'] ?? '';
    if (!$skid) continue;
  ?>
  <url><loc><?=$base?>/marketplace?view=skill&amp;id=<?=htmlspecialchars($skid)?></loc><priority>0.6</priority><changefreq>weekly</changefreq></url>
  <?php endforeach; ?>

  <!-- Events -->
  <?php
  $events = jread($dataDir . '/events/index.json');
  foreach ($events as $ev):
    if (($ev['status'] ?? 'draft') !== 'published') continue;
    $evslug = $ev['slug'] ?? '';
    if (!$evslug) continue;
  ?>
  <url><loc><?=$base?>/event/<?=htmlspecialchars($evslug)?></loc><priority>0.6</priority><changefreq>weekly</changefreq></url>
  <?php endforeach; ?>

  <!-- Topics -->
  <?php
  $topics = jread($dataDir . '/topics.json');
  foreach ($topics as $t):
    if (($t['status'] ?? 'draft') !== 'published') continue;
    $tslug = $t['slug'] ?? '';
    if (!$tslug) continue;
  ?>
  <url><loc><?=$base?>/topic/<?=htmlspecialchars($tslug)?></loc><priority>0.5</priority><changefreq>monthly</changefreq></url>
  <?php endforeach; ?>

  <!-- Landing Pages -->
  <?php
  $landings = jread($dataDir . '/landing-pages.json');
  foreach ($landings as $lp):
    if (($lp['status'] ?? 'draft') !== 'published') continue;
    $lslug = $lp['slug'] ?? '';
    if (!$lslug) continue;
  ?>
  <url><loc><?=$base?>/<?=htmlspecialchars($lslug)?></loc><priority>0.5</priority><changefreq>monthly</changefreq></url>
  <?php endforeach; ?>
</urlset>
