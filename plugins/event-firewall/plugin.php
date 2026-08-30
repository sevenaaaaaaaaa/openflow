<?php
/**
 * 埋点防火墙（官方示例 3/3）
 *
 * 演示重点：cdp_event_received 是全站唯一能「丢数据」的钩子——返回
 * null/false 该事件就永远不会入库。所以这个示例的重心不是怎么丢，
 * 而是怎么丢得让人放心：
 *
 *   1. 默认全放行，所有拦截规则都要显式开启；
 *   2. 每丢一条都记日志，能回溯「我的数据去哪了」；
 *   3. 判断逻辑全部包在 try/catch 里——防火墙自己写错的时候，
 *      应该放行，而不是把全站埋点一起丢掉（fail-open）。
 *
 * 一个只会丢数据的钩子，最危险的失败方式是它自己出 bug。
 */

require_once __DIR__ . '/../../lib/PluginSDK.php';

$p = plugin('event-firewall');

$p->filter('cdp_event_received', function ($entry) use ($p) {
    // 不是数组就别管它，交回给下一个过滤器
    if (!is_array($entry)) return $entry;

    try {
        $event = (string)($entry['event'] ?? '');
        $ua    = (string)($entry['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ip    = (string)($entry['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));

        // ── 1. 爬虫 ──
        if ($p->get('block_bots', false) && $ua !== '') {
            $botRe = '/(bot|crawler|spider|slurp|curl|wget|headless|python-requests|scrapy)/i';
            if (preg_match($botRe, $ua)) {
                $p->log("丢弃爬虫事件 {$event} ua=" . mb_substr($ua, 0, 80));
                return null;
            }
        }

        // ── 2. 内部 IP（公司自己人把数据刷歪）──
        $internal = array_filter(array_map('trim',
            explode(',', (string)$p->get('internal_ips', ''))));
        if ($internal && $ip !== '') {
            foreach ($internal as $prefix) {
                if ($prefix !== '' && str_starts_with($ip, $prefix)) {
                    $p->log("丢弃内部 IP 事件 {$event} ip={$ip}");
                    return null;
                }
            }
        }

        // ── 3. 噪音事件名 ──
        $noisy = array_filter(array_map('trim',
            explode(',', (string)$p->get('blocked_events', ''))));
        if ($event !== '' && in_array($event, $noisy, true)) {
            $p->log("丢弃噪音事件 {$event}");
            return null;
        }

        // ── 4. 不丢，但清洗：URL 上的敏感查询参数 ──
        if ($p->get('strip_query', false) && !empty($entry['url'])) {
            $strip = array_filter(array_map('trim',
                explode(',', (string)$p->get('strip_params', 'token,password,secret,code'))));
            $parts = parse_url((string)$entry['url']);
            if ($strip && !empty($parts['query'])) {
                parse_str($parts['query'], $q);
                $hit = false;
                foreach ($strip as $k) if (isset($q[$k])) { unset($q[$k]); $hit = true; }
                if ($hit) {
                    $entry['url'] = ($parts['path'] ?? '') . ($q ? '?' . http_build_query($q) : '');
                    $p->log("已从 URL 剥离敏感参数：{$event}");
                }
            }
        }

        return $entry;

    } catch (\Throwable $e) {
        // fail-open：防火墙自己坏了就放行，绝不因为规则写错而丢掉全站数据
        $p->logError('cdp_event_received', $e);
        return $entry;
    }
}, 5);   // 优先级 5：排在默认的 10 之前，先过滤再让别的插件加工

$p->menu('埋点防火墙', $p->pageUrl(), '🛡', 'cdp');
