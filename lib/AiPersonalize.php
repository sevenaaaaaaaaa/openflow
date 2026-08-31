<?php
/**
 * AiPersonalize —— 规则个性化 → AI 个性化（AUDIT-03 / BACKLOG T1-1）
 *
 * 【为什么】原 personalizedCta/DynamicContent 是"规则/相似度"驱动：能对不同分群显示
 * 不同东西，但文案是人预先写死的。升级为"AI 按这个人的画像现写文案"，是把个性化
 * 从"分群"推进到"逐人"。
 *
 * 【原则】① 规则先行做兜底与结构（action 等机制仍由规则定，保证有效）；AI 只改写
 * 文案(title/desc)。② 默认关闭、需在设置里开 + 配置 AI 才生效——不改变现有行为。
 * ③ 带 TTL 文件缓存(按画像特征签名)，避免每次渲染都打 AI。④ AI 失败/未配→原样回落。
 * 发送可注入($GLOBALS['AI_PERSONALIZE_FN'])便于测试。
 */

if (!function_exists('ai_personalize_enabled')) {

    function ai_personalize_enabled(): bool {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        if (empty($s['personalize']['ai_cta'])) return false;
        if (isset($GLOBALS['AI_PERSONALIZE_FN']) && is_callable($GLOBALS['AI_PERSONALIZE_FN'])) return true; // 测试/自定义供给
        return class_exists('AiCenter') && \AiCenter::isConfigured();
    }

    function ai_personalize_cache_file(): string { return DATA_DIR . '/cdp/cta_cache.json'; }

    /** 画像特征签名：动作 + 权重前几标签 + 会员等级 + 消费档。相同签名共享一份 AI 文案。 */
    function ai_personalize_signature(array $base, array $pref): string {
        $tags = $pref['tags'] ?? [];
        arsort($tags);
        $top = implode(',', array_slice(array_keys($tags), 0, 3));
        $spendBucket = ($pref['total_spent'] ?? 0) >= 1000 ? 'hi' : (($pref['total_spent'] ?? 0) > 0 ? 'mid' : 'lo');
        return md5(($base['action'] ?? '') . '|' . $top . '|' . ($pref['member_level'] ?? '') . '|' . $spendBucket);
    }

    /**
     * 用 AI 改写 CTA 文案（title/desc），保留 action。未开启/未配置/失败 → 原样返回。
     * 带 24h 文件缓存。$ttl 可调。
     */
    function ai_personalize_cta(array $base, array $pref, int $ttl = 86400): array {
        if (!ai_personalize_enabled()) return $base;
        $sig = ai_personalize_signature($base, $pref);

        // 缓存命中
        $cacheFile = ai_personalize_cache_file();
        $cache = function_exists('json_read') ? json_read($cacheFile) : [];
        if (isset($cache[$sig]) && (time() - (int)($cache[$sig]['at'] ?? 0)) < $ttl) {
            $c = $cache[$sig];
            return array_merge($base, ['title' => $c['title'] ?? $base['title'], 'desc' => $c['desc'] ?? $base['desc']]);
        }

        // 生成
        $tags = $pref['tags'] ?? []; arsort($tags);
        $ctx = json_encode([
            'interests' => array_slice(array_keys($tags), 0, 5),
            'member_level' => $pref['member_level'] ?? '',
            'total_spent' => $pref['total_spent'] ?? 0,
            'source' => $pref['source'] ?? '',
            'base_title' => $base['title'] ?? '',
            'goal_action' => $base['action'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $ai = null;
        try {
            if (isset($GLOBALS['AI_PERSONALIZE_FN']) && is_callable($GLOBALS['AI_PERSONALIZE_FN'])) {
                $ai = call_user_func($GLOBALS['AI_PERSONALIZE_FN'], $ctx);
            } elseif (class_exists('AiCenter')) {
                $r = \AiCenter::json(
                    '你是转化文案专家。根据这个访客的画像，为一条站内 CTA 写更打动他的文案。'
                    . '保持与 goal_action 一致的意图，中文，输出 JSON：{"title":"标题(<14字)","desc":"副文案(<30字)"}，不要多余文字。',
                    $ctx, ['max_tokens' => 200, 'feature' => 'ai_personalize', 'tier' => 'public']
                );
                $ai = (!empty($r['ok']) && is_array($r['data'] ?? null)) ? $r['data'] : null;
            }
        } catch (\Throwable $e) { $ai = null; }

        if (!is_array($ai) || (empty($ai['title']) && empty($ai['desc']))) return $base;

        $out = array_merge($base, [
            'title' => (string)($ai['title'] ?? $base['title']),
            'desc'  => (string)($ai['desc'] ?? $base['desc']),
            'ai'    => true,
        ]);
        // 落缓存
        $cache[$sig] = ['title' => $out['title'], 'desc' => $out['desc'], 'at' => time()];
        if (function_exists('json_write')) { @mkdir(dirname($cacheFile), 0755, true); json_write($cacheFile, $cache); }
        return $out;
    }
}
