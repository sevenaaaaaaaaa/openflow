<?php
/**
 * ProductScout — 每日新产品发现 Loop（E1）
 *
 * 每天自动跑一遍：多源发现（GitHub 新仓库 / 可选 RSS）→ 三重去重（文章 / 导航站 / 历史）
 * → AI 结构化撰写 → 存为 draft 文章进待审列表。人在后台「产品发现」页一键发布或丢弃。
 *
 * 设计约束：
 * - 只产草稿，绝不自动发布（发不出去 = 待审，由人把关）
 * - AI 未配置时降级为「模板草稿」（仓库元数据直排），Loop 不断
 * - 所有外部请求带超时，单源失败不影响其他源
 */
class ProductScout {

    private static function stateFile(): string { return DATA_DIR . '/product-scout.json'; }

    public static function state(): array {
        return json_read(self::stateFile());
    }

    public static function config(): array {
        $s = self::state();
        return array_merge([
            'enabled'      => true,
            'per_day'      => 3,                 // 每天最多产几篇
            'min_stars'    => 50,                // GitHub 最低星数（过滤噪音）
            'max_age_days' => 30,                // 只发现最近 N 天创建的项目
            'keywords'     => ['ai agent', 'llm tool', 'ai video', 'ai image', 'devtool', 'marketing automation'],
            'sources'      => ['github'],        // github | rss（rss 预留）
        ], $s['config'] ?? []);
    }

    public static function saveConfig(array $cfg): void {
        $s = self::state();
        $s['config'] = array_merge(self::config(), $cfg);
        json_write(self::stateFile(), $s);
    }

    /* ═══════════════ 1. 发现 ═══════════════ */

    /** 从各来源发现候选产品（统一结构） */
    public static function discover(): array {
        $cfg = self::config();
        $found = [];
        if (in_array('github', $cfg['sources'], true)) {
            foreach ((array)$cfg['keywords'] as $kw) {
                foreach (self::githubNewRepos(trim($kw), $cfg) as $item) $found[] = $item;
            }
        }
        // 按星数降序 + 按 repo 名去重
        $uniq = [];
        foreach ($found as $it) $uniq[$it['key']] = $it;
        usort($uniq, fn($a, $b) => ($b['stars'] ?? 0) <=> ($a['stars'] ?? 0));
        return array_values($uniq);
    }

    /** GitHub：最近创建 + 星数排序的新仓库 */
    private static function githubNewRepos(string $keyword, array $cfg): array {
        if ($keyword === '') return [];
        $since = date('Y-m-d', time() - max(1, (int)$cfg['max_age_days']) * 86400);
        $q = urlencode($keyword . ' created:>' . $since . ' stars:>=' . (int)$cfg['min_stars']);
        $url = "https://api.github.com/search/repositories?q={$q}&sort=stars&order=desc&per_page=5";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'OpenFlow-ProductScout',
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);   // PHP 8.0+ curl_close 已无效果，8.5 起废弃
        if ($code !== 200 || !$resp) return [];
        $data = json_decode($resp, true);
        $items = [];
        foreach ((array)($data['items'] ?? []) as $r) {
            $items[] = [
                'key'         => 'gh:' . strtolower($r['full_name'] ?? ''),
                'name'        => (string)($r['full_name'] ?? ''),
                'url'         => (string)($r['html_url'] ?? ''),
                'homepage'    => (string)($r['homepage'] ?? ''),
                'description' => (string)($r['description'] ?? ''),
                'stars'       => (int)($r['stargazers_count'] ?? 0),
                'forks'       => (int)($r['forks_count'] ?? 0),
                'language'    => (string)($r['language'] ?? ''),
                'topics'      => array_values(array_slice((array)($r['topics'] ?? []), 0, 8)),
                'created_at'  => (string)($r['created_at'] ?? ''),
                'source'      => 'github',
                'keyword'     => $keyword,
            ];
        }
        return $items;
    }

    /* ═══════════════ 2. 去重 ═══════════════ */

    /** 三重去重：scout 历史 / 文章（标题+正文含名）/ 导航站（URL） */
    public static function alreadyCovered(array $item): bool {
        $s = self::state();
        if (!empty($s['covered'][$item['key']])) return true;

        $repoName = strtolower(basename($item['name']));   // owner/repo → repo
        if ($repoName === '') return true;

        // 文章：标题或正文前 500 字含项目名
        foreach (get_articles() as $a) {
            $hay = mb_strtolower(($a['title'] ?? '') . ' ' . mb_substr(strip_tags($a['content'] ?? ''), 0, 500));
            if (str_contains($hay, $repoName)) return true;
        }
        // 导航站：URL 或名字命中
        $nav = json_read(DATA_DIR . '/navigation.json');
        foreach ((array)($nav['sites'] ?? $nav ?? []) as $site) {
            if (!is_array($site)) continue;
            if (!empty($site['url']) && str_contains(strtolower($site['url']), strtolower($item['name']))) return true;
            if (!empty($site['name']) && mb_strtolower($site['name']) === $repoName) return true;
        }
        return false;
    }

    /* ═══════════════ 3. 生成草稿 ═══════════════ */

    /** AI 结构化撰写；AI 未配置时降级为模板草稿（Loop 不断） */
    public static function generateDraft(array $item): ?string {
        $d = null;
        if (class_exists('AiCenter') && AiCenter::isConfigured()) {
            try {
                $resp = AiCenter::json(
                    '你是资深产品观察编辑，运营一个面向一人公司/独立开发者的产品发现专栏。'
                    . '基于给定的新产品仓库信息，写一篇「新产品速览」中文文章。要求：信息密度高、不吹水、'
                    . '明确写出它解决什么问题、适合谁、和主流替代品比的差异。'
                    . '输出 JSON（不要多余文字），字段：'
                    . '{"title":"标题(20字内,含产品名)","slug":"英文URL后缀(纯小写字母数字连字符)","summary":"80字内摘要",'
                    . '"seo_title":"30字内","seo_desc":"80字内","seo_keywords":"3-5个,逗号分隔",'
                    . '"tags":["2-4个"],"content":"800-1200字HTML:<h2>它是干什么的/<h2>核心亮点(列表)/<h2>上手与生态/<h2>适合谁·不适合谁/<h2>和同类比怎么样/结尾CTA引导访问项目"}',
                    json_encode($item, JSON_UNESCAPED_UNICODE),
                    ['max_tokens' => 2500, 'feature' => 'product_scout', 'tier' => 'batch']
                );
                if (!empty($resp['ok']) && !empty($resp['data'])) $d = $resp['data'];
            } catch (\Throwable $e) { /* 降级到模板 */ }
        }
        return self::saveDraft($item, $d);
    }

    /** 存草稿（AI 结构化 或 模板兜底），返回文章 id */
    private static function saveDraft(array $item, ?array $d): ?string {
        $repoName = basename($item['name']) ?: $item['name'];
        $title = trim((string)($d['title'] ?? '')) ?: "新产品速览：{$repoName}";
        $content = trim((string)($d['content'] ?? ''));
        if (mb_strlen(strip_tags($content)) < 200) {
            // 模板兜底：仓库元数据直排，保证 Loop 有产出
            $stars = class_exists('NavGithub') ? NavGithub::compactNum((int)$item['stars']) : (string)$item['stars'];
            $topics = $item['topics'] ? ' · ' . implode(' / ', array_slice($item['topics'], 0, 5)) : '';
            $content = "<h2>它是干什么的</h2><p>" . htmlspecialchars($item['description'] ?: '（作者未写简介）') . "</p>"
                . "<h2>关键数据</h2><ul><li>Stars：{$stars}</li><li>Forks：{$item['forks']}</li>"
                . "<li>主要语言：{$item['language']}</li><li>创建于：{$item['created_at']}</li></ul>"
                . "<h2>标签</h2><p>" . trim($topics, ' ·') . "</p>"
                . "<h2>去看看</h2><p><a href=\"{$item['url']}\">{$item['url']}</a></p>"
                . "<p><strong>编辑注：</strong>本条由产品发现 Loop 自动生成模板稿，发布前请补充体验与观点。</p>";
        }
        $slug = strtolower(trim((string)($d['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        if ($slug === '' || strlen($slug) < 4) $slug = 'product-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($repoName));

        $articles = json_read(ARTICLES_DIR . '/index.json');
        $id = 'scout_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $articles[] = [
            'id' => $id,
            'title' => $title,
            'slug' => $slug . '-' . date('Ymd'),
            'content' => $content,
            'excerpt' => trim((string)($d['summary'] ?? '')) ?: mb_substr(strip_tags($content), 0, 160),
            'status' => 'draft',
            'author' => '产品发现 Loop',
            'category' => 'product',
            'tags' => array_values(array_filter((array)($d['tags'] ?? ['新产品', 'AI']))),
            'seo_title' => trim((string)($d['seo_title'] ?? '')) ?: $title,
            'seo_desc' => trim((string)($d['seo_desc'] ?? '')) ?: '',
            'seo_keywords' => trim((string)($d['seo_keywords'] ?? '')),
            'source' => 'product_scout',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        json_write(ARTICLES_DIR . '/index.json', $articles);
        return $id;
    }

    /* ═══════════════ 4. 每日运行 ═══════════════ */

    /** 每日 Loop 主入口（cron 或后台手动触发） */
    public static function dailyRun(bool $force = false): array {
        $cfg = self::config();
        $s = self::state();
        $today = date('Y-m-d');

        if (!$cfg['enabled'] && !$force) return ['status' => 'skipped', 'detail' => 'Loop 已停用'];
        if (!$force && ($s['last_run_date'] ?? '') === $today) {
            return ['status' => 'skipped', 'detail' => '今天已跑过'];
        }

        $found = self::discover();
        $made = [];
        $skipped = 0;
        foreach ($found as $item) {
            if (count($made) >= (int)$cfg['per_day']) break;
            if (self::alreadyCovered($item)) { $skipped++; continue; }
            $id = self::generateDraft($item);
            if ($id) {
                $made[] = ['id' => $id, 'name' => $item['name'], 'stars' => $item['stars']];
                $s['covered'][$item['key']] = $today;   // 无论 AI 成败都记为已覆盖，防重复
            }
        }

        $s['last_run_date'] = $today;
        $s['runs'][] = ['ts' => time(), 'found' => count($found), 'made' => count($made), 'skipped_dup' => $skipped, 'items' => $made];
        $s['runs'] = array_slice((array)$s['runs'], -60);   // 只留 60 次运行记录
        json_write(self::stateFile(), $s);

        return ['status' => 'ok', 'found' => count($found), 'made' => $made, 'skipped_dup' => $skipped];
    }
}
