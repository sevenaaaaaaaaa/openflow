<?php
/**
 * 流量分类器 — 把访问分成 真实用户 / 开发访问 / AI 爬虫 / 搜索爬虫 / 其他爬虫
 *
 * 解决两个问题：
 *  1. 开发/测试浏览被当成用户行为污染统计（page_view、DAU、漏斗全失真）
 *  2. AI 爬虫与 SEO 爬虫的抓取量看不见，无法评估收录与 AI 引用价值
 *
 * 分类结果 channel：
 *   human      真实访客（默认）
 *   dev        开发/内部访问（管理员 cookie、后台会话、内网 IP）
 *   bot_ai     AI 爬虫（GPTBot/ClaudeBot/PerplexityBot…，UA 特征见 CrawlerDetect）
 *   bot_search 搜索引擎爬虫（Googlebot/Baiduspider/Bingbot…）
 *   bot_other  其他机器人（curl/headless/通用 bot）
 *
 * 用法：
 *   $t = traffic_classify();          // ['channel'=>'human', 'bot'=>null, 'ua'=>...]
 *   if ($t['channel'] !== 'human') { 写 events_bot 表 }
 */
require_once __DIR__ . '/CrawlerDetect.php';

if (!function_exists('traffic_classify')) {

function traffic_classify(): array {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // ── 开发/内部访问 ──
    // 1) 管理员登录后台时种的 of_dev cookie（30 天，覆盖外网测试线上的场景）
    if (($_COOKIE['of_dev'] ?? '') === '1') {
        return ['channel' => 'dev', 'bot' => null, 'ua' => $ua];
    }
    // 2) 当前请求带着后台会话（管理员直接在后台预览等）
    if (!empty($_SESSION['admin_login'])) {
        return ['channel' => 'dev', 'bot' => null, 'ua' => $ua];
    }
    // 3) 内网/本地 IP（开发机直连）
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['channel' => 'dev', 'bot' => null, 'ua' => $ua];
    }

    // ── 爬虫 ──
    $c = CrawlerDetect::detect();
    if ($c['is_crawler']) {
        $ch = ['ai' => 'bot_ai', 'search' => 'bot_search'][$c['type']] ?? 'bot_other';
        return ['channel' => $ch, 'bot' => $c['name'], 'ua' => $ua];
    }

    return ['channel' => 'human', 'bot' => null, 'ua' => $ua];
}

}
