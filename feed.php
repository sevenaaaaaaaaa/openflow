<?php
/**
 * RSS Feed — outputs published articles as RSS 2.0
 * Access: /feed.xml or /feed.php
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host;

$articles = get_articles();
$published = array_values(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?=site_config_get("site_name")?> · <?=site_config_get("site_slogan", '帮一人公司设计 Agent 能跑的增长系统')?></title>
    <link><?=$base?>/</link>
    <description><?=site_config_get("site_name")?> · <?=site_config_get("site_desc")?></description>
    <language>zh-CN</language>
    <atom:link href="<?=$base?>/feed.xml" rel="self" type="application/rss+xml"/>
    <lastBuildDate><?=date('r', strtotime($published[0]['updated_at'] ?? 'now'))?></lastBuildDate>
    <?php foreach ($published as $a): $slug = $a['slug'] ?? ''; if (empty($slug)) continue; ?>
    <item>
      <title><![CDATA[<?=$a['title'] ?? ''?>]]></title>
      <link><?=$base?>/article/<?=htmlspecialchars($slug)?></link>
      <guid isPermaLink="true"><?=$base?>/article/<?=htmlspecialchars($slug)?></guid>
      <description><![CDATA[<?=mb_substr(strip_tags($a['content'] ?? ''), 0, 500)?>]]></description>
      <pubDate><?=date('r', strtotime($a['created_at'] ?? 'now'))?></pubDate>
      <author><?=htmlspecialchars($a['author'] ?: site_config_get('site_name'))?></author>
      <?php foreach (($a['tags'] ?? []) as $tag): ?>
      <category><?=htmlspecialchars($tag)?></category>
      <?php endforeach; ?>
    </item>
    <?php endforeach; ?>
  </channel>
</rss>
