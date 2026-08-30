<?php
/**
 * GrowthFlywheel —— 内容自动线（Content Autopilot）
 *
 * 【它是什么】一条以"内容"为核心的自动化流水线：
 *   采集热点 → AI 总结 → AI 撰写草稿 → 内容更新 → SEO 优化 → （统计）转化/激活 → 洞察 → 报告
 * 真正"动手"的环节是 采集→写稿→发布；"转化/激活"两步目前只做**资产计数**
 * （数落地页/表单/自动化的条数）写进日报，并不跨模块决策或执行。
 *
 * 【它不是什么 —— 重要】它**不是**跨模块的"增长大脑"。它不读单个用户的实时
 * 画像去逐人决策，也不把成交/事件回流给其它模块。别被旧名"增长驱动引擎/飞轮"
 * 误导：跨模块的逐人决策中枢是独立的 GrowthBrain（见 docs/AUDIT-07-SYNTHESIS.md
 * 的"中枢神经 + 大脑"），本类只承担其中"内容生成"这一个器官的自动化。
 *
 * 【与 GrowthEngine 的边界】GrowthEngine 是**自进化/信号**系统（记录使用指纹、
 * 建议采纳/忽略、实例"体型"成长），与本类是两件事，不要混用：
 *   - GrowthFlywheel = 主动产出内容（对外）
 *   - GrowthEngine   = 记录并塑形实例自身（对内）
 *
 * 支持：cron 周期自动执行 / 后台人工介入 / AI 未配置时优雅降级（只发现建议）。
 * 数据：data/growth-driver.json（历史文件名，保持不变以免丢状态）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/SentimentSystem.php';

class GrowthFlywheel {
    private static string $file = DATA_DIR . '/growth-driver.json';

    /* ─── 状态 ─── */
    public static function state(): array {
        $s = json_read(self::$file);
        if (empty($s)) {
            $s = [
                'enabled' => true,
                'last_cycle' => 0,
                'cycle_count' => 0,
                'ai_configured' => AiCenter::isConfigured(),
                'steps' => [],        // 各环节最近一次结果
                'history' => [],      // 周期历史
                'pending_review' => [], // 待人工确认的动作
            ];
            json_write(self::$file, $s);
        }
        return $s;
    }

    /* ─── 驱动步骤定义 ─── */
    public static function steps(): array {
        return [
            'collect'    => ['name' => '爬取热点', 'icon' => '🕸️', 'desc' => '抓取行业舆情与搜索热点', 'needs_ai' => false],
            'analyze'    => ['name' => '热点总结', 'icon' => '🧠', 'desc' => '总结热点主题与洞察', 'needs_ai' => true],
            'draft'      => ['name' => 'AI 撰写', 'icon' => '✍️', 'desc' => '生成文章草稿', 'needs_ai' => true],
            'update'     => ['name' => '内容更新', 'icon' => '🔄', 'desc' => '发布/更新内容', 'needs_ai' => false],
            'optimize'   => ['name' => 'SEO 优化', 'icon' => '🔍', 'desc' => '优化标题/描述/关键词', 'needs_ai' => false],
            'convert'    => ['name' => '转化链路', 'icon' => '🎯', 'desc' => '配置落地页/表单/CTA', 'needs_ai' => false],
            'activate'   => ['name' => '激活触达', 'icon' => '📣', 'desc' => '配置 MA/推送/消息', 'needs_ai' => false],
            'insight'    => ['name' => '数据洞察', 'icon' => '📊', 'desc' => 'CDP 数据分析', 'needs_ai' => true],
            'report'     => ['name' => '增长报告', 'icon' => '📈', 'desc' => '产出增长反馈', 'needs_ai' => false],
        ];
    }

    /* ─── 运行一轮完整驱动 ─── */
    public static function runCycle(): array {
        $results = [];

        // 1. 爬取热点（无需 AI）
        $results['collect'] = self::stepCollect();

        // 2. 热点总结（AI，无 key 则降级）
        $results['analyze'] = self::stepAnalyze($results['collect']);

        // 3. AI 撰写（AI）
        $results['draft'] = self::stepDraft($results['analyze']);

        // 4. 内容更新
        $results['update'] = self::stepUpdate($results['draft']);

        // 5. SEO 优化
        $results['optimize'] = self::stepOptimize();

        // 6. 转化链路
        $results['convert'] = self::stepConvert();

        // 7. 激活触达
        $results['activate'] = self::stepActivate();

        // 8. 数据洞察
        $results['insight'] = self::stepInsight();

        // 9. 增长报告
        $results['report'] = self::stepReport($results);

        // 重新读取最新 state（draft 步骤可能已写入 pending_review），合并后写入
        $latest = self::state();
        $latest['last_cycle'] = time();
        $latest['cycle_count'] = ($latest['cycle_count'] ?? 0) + 1;
        $latest['ai_configured'] = AiCenter::isConfigured();
        foreach ($results as $k => $v) $latest['steps'][$k] = $v;
        $latest['history'][] = ['ts' => time(), 'summary' => self::summarize($results)];
        if (count($latest['history']) > 50) $latest['history'] = array_slice($latest['history'], -40);
        if (count($latest['pending_review'] ?? []) > 100) $latest['pending_review'] = array_slice($latest['pending_review'], -80);

        // 时间线
        try { GrowthEngine::timeline('driver', '增长驱动引擎第 ' . $latest['cycle_count'] . ' 轮', self::summarize($results)); } catch (\Throwable $e) {}

        json_write(self::$file, $latest);
        return $results;
    }

    /* ─── 各环节实现 ─── */

    /** 1. 爬取热点：舆情系统搜索行业关键词 */
    private static function stepCollect(): array {
        $topics = sent_topics();
        $keywords = [];
        foreach ($topics as $t) $keywords[] = $t['name'] ?? '';
        if (empty($keywords)) $keywords = ['网站增长', 'SEO 优化', '营销自动化', 'AI 内容'];

        $results = [];
        foreach (array_slice($keywords, 0, 3) as $kw) {
            try {
                $items = sent_search($kw, ['rss', 'baidu']);
                $results[$kw] = array_slice($items, 0, 5);
            } catch (\Throwable $e) {
                $results[$kw] = [];
            }
        }
        $total = array_sum(array_map('count', $results));
        return [
            'status' => 'ok', 'total' => $total, 'topics' => array_slice($keywords, 0, 3),
            'detail' => "抓到 {$total} 条热点内容，覆盖 " . count($results) . " 个主题",
            'ts' => time(),
        ];
    }

    /** 2. 热点总结：AI 总结，未配置则降级 */
    private static function stepAnalyze(array $collect): array {
        if (!AiCenter::isConfigured()) {
            return [
                'status' => 'degraded', 'needs_ai' => true,
                'detail' => 'AI 未配置，跳过总结。配置 AI Key 后自动生成热点洞察',
                'suggestion' => '前往 设置 → AI Agent 配置模型 Key',
                'ts' => time(),
            ];
        }
        $topics = $collect['topics'] ?? [];
        try {
            $resp = AiCenter::json(
                '你是一个增长分析专家，请总结以下行业热点，输出每条：主题、核心观点、机会点',
                json_encode($topics, JSON_UNESCAPED_UNICODE),
                ['max_tokens' => 800]
            );
            return ['status' => 'ok', 'detail' => '已生成 ' . count($topics) . ' 个主题的热点洞察', 'data' => $resp, 'ts' => time()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'detail' => '总结失败：' . $e->getMessage(), 'ts' => time()];
        }
    }

    /** 3. AI 撰写：生成结构化文章草稿（选题精选 + 标题 + slug + SEO，进待审） */
    private static function stepDraft(array $analyze): array {
        if (!AiCenter::isConfigured() || ($analyze['status'] ?? '') !== 'ok') {
            return ['status' => 'skipped', 'detail' => '跳过撰写（需 AI + 热点总结）', 'ts' => time()];
        }
        try {
            $topics = (array)($analyze['topics'] ?? ['网站增长']);
            $topic = is_string($topics[0] ?? '') ? $topics[0] : '网站增长';
            // 结构化生成：选题理由 + 标题 + slug + 正文 + SEO（一次产出，保证质量）
            $resp = AiCenter::json(
                '你是资深网站增长内容主编。基于热点主题，撰写一篇可直接发布的 SEO 文章。'
                . '输出 JSON（不要多余文字），字段：'
                . '{"title":"吸引点击的标题(20字内,含关键词)","slug":"英文URL后缀(纯小写字母数字连字符,3-6个词)","summary":"核心洞察(80字内)","seo_title":"SEO标题(30字内,含主关键词)","seo_desc":"SEO描述(80字内)","seo_keywords":"3-5个关键词,逗号分隔","category":"insight|seo|product|growth 之一","tags":["2-4个标签"],"content":"800-1200字HTML正文,含<h2>小标题x3、有序/无序列表、<strong>重点、结尾行动号召CTA"}',
                '热点主题：' . $topic,
                ['max_tokens' => 2500]
            );
            if (empty($resp['ok']) || empty($resp['data'])) {
                return ['status' => 'error', 'detail' => 'AI 生成失败：' . ($resp['error'] ?? '解析失败'), 'ts' => time()];
            }
            $d = $resp['data'];
            $text = (string)($d['content'] ?? '');
            if (mb_strlen(strip_tags($text)) < 200) {
                return ['status' => 'error', 'detail' => 'AI 正文过短，已跳过保存', 'ts' => time()];
            }
            $draftId = self::saveDraft($topic, $text, $d);
            $s = self::state();
            $s['pending_review'][] = ['id' => $draftId, 'title' => ($d['title'] ?? $topic) . '·AI草稿', 'ts' => time()];
            json_write(self::$file, $s);
            return ['status' => 'ok', 'detail' => '已生成结构化草稿（待审核发布）', 'draft_id' => $draftId, 'ts' => time()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'detail' => '撰写失败：' . $e->getMessage(), 'ts' => time()];
        }
    }

    /** 保存 AI 草稿（含标题/slug/SEO 结构化字段） */
    private static function saveDraft(string $topic, string $text, array $d = []): string {
        $articles = json_read(ARTICLES_DIR . '/index.json');
        $id = 'ai_draft_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $title = trim($d['title'] ?? '') ?: $topic;
        // slug：AI 给的英文 slug；空则拼音兜底（中文可读）或时间戳
        $slug = strtolower(trim($d['slug'] ?? ''));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        if ($slug === '' || strlen($slug) < 4) {
            $pinyin = function_exists('pinyin_convert') ? pinyin_convert($title) : '';
            $slug = $pinyin !== '' ? $pinyin : ('ai-growth-' . date('YmdHis'));
        }
        $articles[] = [
            'id' => $id,
            'title' => $title,
            'slug' => $slug . '-' . date('Ymd'),
            'content' => $text,
            'excerpt' => trim($d['summary'] ?? '') ?: mb_substr(strip_tags($text), 0, 160),
            'status' => 'draft',
            'author' => 'AI 增长引擎',
            'category' => in_array($d['category'] ?? '', ['insight','seo','product','growth'], true) ? $d['category'] : 'insight',
            'tags' => array_values(array_filter((array)($d['tags'] ?? ['AI', '增长']))),
            'seo_title' => trim($d['seo_title'] ?? '') ?: $title,
            'seo_desc' => trim($d['seo_desc'] ?? '') ?: ($d['summary'] ?? ''),
            'seo_keywords' => trim($d['seo_keywords'] ?? ''),
            'source' => 'growth_driver',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        json_write(ARTICLES_DIR . '/index.json', $articles);
        return $id;
    }

    /** 4. 内容更新：检查待发布草稿（有定时发布的做更新） */
    private static function stepUpdate(array $draft): array {
        $articles = json_read(ARTICLES_DIR . '/index.json');
        $scheduled = array_filter($articles, fn($a) => ($a['status'] ?? '') === 'draft' && !empty($a['publish_at']) && ($a['publish_at'] ?? '') <= date('Y-m-d H:i:s'));
        $count = 0;
        foreach ($scheduled as $a) {
            $a['status'] = 'published';
            $a['published_at'] = date('Y-m-d H:i:s');
            foreach ($articles as &$x) if ($x['id'] === $a['id']) { $x = $a; break; }
            unset($x);
            $count++;
        }
        if ($count > 0) json_write(ARTICLES_DIR . '/index.json', $articles);
        return ['status' => 'ok', 'detail' => $count > 0 ? "自动发布了 {$count} 篇定时文章" : '无到期定时内容', 'published' => $count, 'ts' => time()];
    }

    /** 5. SEO 优化：检查缺 SEO 标题/描述的文章 */
    private static function stepOptimize(): array {
        $articles = json_read(ARTICLES_DIR . '/index.json');
        $missing = array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published' && (empty($a['seo_title']) || empty($a['seo_desc'])));
        $ids = array_slice(array_column($missing, 'id'), 0, 10);
        return ['status' => 'ok', 'detail' => '发现 ' . count($missing) . ' 篇文章缺 SEO 元数据', 'ids' => $ids, 'ts' => time()];
    }

    /** 6. 转化链路：检查落地页/表单/CTA 覆盖 */
    private static function stepConvert(): array {
        $landings = json_read(DATA_DIR . '/landing-pages.json');
        $forms = json_read(DATA_DIR . '/forms.json');
        $count = (is_array($landings) ? count($landings) : 0) + (is_array($forms) ? count($forms) : 0);
        return ['status' => 'ok', 'detail' => '转化资产：' . $count . ' 个（落地页+表单）', 'assets' => $count, 'ts' => time()];
    }

    /** 7. 激活触达：检查自动化/消息配置 */
    private static function stepActivate(): array {
        $auto = json_read(DATA_DIR . '/automation.json');
        $count = is_array($auto) ? count($auto) : 0;
        return ['status' => 'ok', 'detail' => '激活配置：' . $count . ' 条自动化', 'automations' => $count, 'ts' => time()];
    }

    /** 8. 数据洞察：CDP 画像/事件 */
    private static function stepInsight(): array {
        try {
            $events = (int)Database::query("SELECT COUNT(*) c FROM events")[0]['c'] ?? 0;
            $customers = (int)Database::query("SELECT COUNT(*) c FROM cdp_customers")[0]['c'] ?? 0;
            return ['status' => 'ok', 'detail' => "CDP 洞察：{$customers} 个画像，{$events} 条行为事件", 'customers' => $customers, 'events' => $events, 'ts' => time()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'detail' => '洞察失败：' . $e->getMessage(), 'ts' => time()];
        }
    }

    /** 9. 增长报告 */
    private static function stepReport(array $results): array {
        $ok = count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'ok'));
        $total = count($results);
        return [
            'status' => 'ok',
            'detail' => "本轮 {$ok}/{$total} 环节正常，AI 驱动" . (AiCenter::isConfigured() ? '已启用' : '待配置'),
            'health' => "{$ok}/{$total}",
            'ts' => time(),
        ];
    }

    /** 汇总本轮 */
    private static function summarize(array $results): string {
        $parts = [];
        foreach ($results as $k => $v) {
            if (($v['status'] ?? '') === 'ok') $parts[] = $v['detail'] ?? $k;
        }
        return implode('；', array_slice($parts, 0, 4));
    }
}
