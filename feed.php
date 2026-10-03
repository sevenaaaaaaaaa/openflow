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
// 当前语言有已发布译文的，用译文字段出条目（RSS 订阅者拿到的就是所订语言）
$__loc = function_exists('i18n_current') ? i18n_current() : 'zh-CN';
$__base = function_exists('ci18n_base_locale') ? ci18n_base_locale() : 'zh-CN';
if ($__loc !== $__base && function_exists('ci18n_resolve')) {
    $__loc_articles = [];
    foreach ($published as $__a) {
        $__r = ci18n_resolve($__a, $__loc);
        if (($__r['_locale'] ?? '') === $__loc) $__loc_articles[] = $__r;
    }
    if ($__loc_articles) $published = $__loc_articles;
}

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?=site_config_get("site_name")?> · <?=site_config_get("site_slogan", '帮一人公司设计 Agent 能跑的增长系统')?></title>
    <link><?=$base?>/</link>
    <description><?=site_config_get("site_name")?> · <?=site_config_get("site_desc")?></description>
    <language><?=htmlspecialchars($__loc)?></language>
    <atom:link href="<?=$base?>/feed.xml" rel="self" type="application/rss+xml"/>
    <lastBuildDate><?=date('r', strtotime($published[0]['updated_at'] ?? 'now'))?></lastBuildDate>
    <?php foreach ($published as $a): $slug = $a['slug'] ?? ''; if (empty($slug)) continue; ?>
    <item>
      <title><![CDATA[<?=$a['title'] ?? ''?>]]></title>
      <?php $__prefix = ($__loc !== $__base) ? '/' . $__loc : ''; ?>
      <link><?=$base?><?=htmlspecialchars($__prefix)?>/article/<?=htmlspecialchars($slug)?></link>
      <guid isPermaLink="true"><?=$base?><?=htmlspecialchars($__prefix)?>/article/<?=htmlspecialchars($slug)?></guid>
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
