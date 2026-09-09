<?php
/**
 * CoverRenderer — 生成式封面（无图文章 / 课程 / 资产用）
 *
 * v9（2026-09-10）：「统一框架 + 内容符号 + 种子变体」产品化。
 *  - 框架统一：分类决定色相 / 代号 / 分类标签（不变），网格 + 光晕基底（不变）。
 *  - 内容符号：从标题 / 标签 / 摘要里识别业务关键词，抽象成一个大型线框符号水印，
 *    让每张封面「看得出这篇在讲什么」，而不是全站只有分类差异。
 *  - 种子变体：cover_seed 决定版式（v1 标志 / v2 水印 / v3 回响 / v4 轨道）、旋转角、
 *    光晕落点、网格密度。同种子 = 同封面（可复现、列表与详情一致）；
 *    编辑在后台「换一张」= 换种子重新抽卡，cover_motif 可手动指定符号。
 *
 *   CoverRenderer::renderCardCover($article)    列表卡封面（放进 .a-card .cov 或任何 16:9 容器）
 *   CoverRenderer::renderDetailCover($article)  正文页大图（有图出 <img>，无图出 .gcov.lg 横幅）
 *   CoverRenderer::usesCssCover($article)       是否走生成式封面
 *   CoverRenderer::palette($category)           ['hue','code','name','icon']
 *   CoverRenderer::motif($item)                 内容符号 key
 *   CoverRenderer::seed($item)                  封面种子
 */
class CoverRenderer {

    /** 分类 → 色相（accent / ok / warn / danger）、短代号、名称、线框图标 path */
    const PALETTE = [
        'ai-create'    => ['hue' => 'accent', 'code' => 'CREATE', 'name' => 'AI 创作',    'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>'],
        'ai-marketing' => ['hue' => 'ok',     'code' => 'MKT',    'name' => 'AI 营销',    'icon' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6M18 6a7 7 0 0 1 0 12"/>'],
        'ai-build'     => ['hue' => 'ok',     'code' => 'BUILD',  'name' => 'AI 建站',    'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 20V9"/>'],
        'ai-code'      => ['hue' => 'accent', 'code' => 'CODE',   'name' => 'AI 编程',    'icon' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>'],
        'ai-ops'       => ['hue' => 'warn',   'code' => 'OPS',    'name' => 'AI 运营',    'icon' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>'],
        'ai-sell'      => ['hue' => 'ok',     'code' => 'SELL',   'name' => 'AI 销售',    'icon' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>'],
        'ai-data'      => ['hue' => 'accent', 'code' => 'DATA',   'name' => '数据分析',   'icon' => '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>'],
        'ai-user'      => ['hue' => 'danger', 'code' => 'USER',   'name' => '用户运营',   'icon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>'],
        'agent'        => ['hue' => 'accent', 'code' => 'AGENT',  'name' => 'Agent 生态', 'icon' => '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01M9 17h6"/>'],
        'trend'        => ['hue' => 'warn',   'code' => 'TREND',  'name' => '行业趋势',   'icon' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>'],
        // 旧数据里的分类 key
        'insight'      => ['hue' => 'warn',   'code' => 'INSIGHT','name' => '增长洞察',   'icon' => '<path d="M12 3a6 6 0 0 0-4 10.5V16h8v-2.5A6 6 0 0 0 12 3ZM10 20h4"/>'],
        'content'      => ['hue' => 'ok',     'code' => 'CONTENT','name' => '内容',       'icon' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>'],
        'ai-agent'     => ['hue' => 'accent', 'code' => 'AGENT',  'name' => 'Agent 实践', 'icon' => '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01M9 17h6"/>'],
    ];
    const FALLBACK = ['hue' => 'neutral', 'code' => 'NOTE', 'name' => '文章', 'icon' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/>'];

    /**
     * 内容符号库：业务关键词 → 抽象线框符号。
     * 按顺序匹配（前面的优先），命中标题 / 标签 / 摘要即采用。
     */
    const MOTIFS = [
        'growth'     => ['kw' => ['增长', 'growth', '飞轮', '复利'],           'icon' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>'],
        'convert'    => ['kw' => ['转化', '成交', '收款', '下单', 'convers'],   'icon' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/>'],
        'traffic'    => ['kw' => ['流量', '获客', '引流', 'traffic'],           'icon' => '<path d="M2 12c2.5-4 5-6 7.5-6S15 8.5 17 12c-2 3.5-4.5 6-7.5 6S4.5 16 2 12Z" transform="rotate(90 12 12)"/><path d="M8 4c1.2 1.5 1.2 3 0 4.5M16 15.5c1.2 1.5 1.2 3 0 4.5"/>'],
        'automation' => ['kw' => ['自动化', '工作流', 'workflow', 'automat'],   'icon' => '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01M9 17h6"/>'],
        'data'       => ['kw' => ['数据', '报表', '指标', '分析', 'data', 'cdp'], 'icon' => '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>'],
        'seo'        => ['kw' => ['seo', '搜索', '收录', '关键词', '排名'],      'icon' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>'],
        'email'      => ['kw' => ['邮件', 'edm', 'newsletter', '订阅'],         'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
        'community'  => ['kw' => ['社群', '社区', '私域', '会员', 'communit'],  'icon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>'],
        'sales'      => ['kw' => ['销售', '带货', '成单', 'sales'],             'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9 9.5c0-1.4 1.3-2.5 3-2.5s3 1.1 3 2.5-1.3 2-3 2.5-3 1.1-3 2.5 1.3 2.5 3 2.5 3-1.1 3-2.5M12 5.5v13"/>'],
        'writing'    => ['kw' => ['写作', '文案', '内容', '创作', '选题', 'writ'], 'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>'],
        'ai'         => ['kw' => ['ai', '人工智能', '大模型', 'gpt', 'claude', 'agent', '智能体'], 'icon' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>'],
        'tool'       => ['kw' => ['工具', '插件', '效率', 'tool'],              'icon' => '<path d="M14.7 6.3a4.5 4.5 0 0 0-6 6L3 18l3 3 5.7-5.7a4.5 4.5 0 0 0 6-6L14 13l-3-3 3.7-3.7Z"/>'],
        'case'       => ['kw' => ['案例', '复盘', '拆解', '实战'],              'icon' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10h8M8 14h5M8 7h2"/>'],
        'video'      => ['kw' => ['视频', '直播', '播客', '短视频'],            'icon' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3V9Z"/>'],
        'shop'       => ['kw' => ['电商', '商城', '店铺', 'shop'],              'icon' => '<path d="M4 7h16l-1.5 12a2 2 0 0 1-2 1.8h-9A2 2 0 0 1 5.5 19L4 7Z"/><path d="M8 10V6a4 4 0 0 1 8 0v4"/>'],
        'brand'      => ['kw' => ['品牌', 'ip ', '人设'],                       'icon' => '<circle cx="12" cy="9" r="5"/><path d="m8.5 13-1.5 8 5-3 5 3-1.5-8"/>'],
        'page'       => ['kw' => ['落地页', '页面', '建站', '官网', 'landing'], 'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 20V9"/>'],
        'funnel'     => ['kw' => ['漏斗', '路径', '旅程', 'funnel'],            'icon' => '<path d="M3 4h18l-7 8v6l-4 2v-8L3 4Z"/>'],
        'retention'  => ['kw' => ['留存', '复购', '召回', '活跃'],              'icon' => '<path d="M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.2L3 16M3 21v-5h5"/>'],
        'launch'     => ['kw' => ['冷启动', '启动', '从 0', '从0', '入门', '新手'], 'icon' => '<path d="M12 15c5-3 7-8 7-11-3 0-8 2-11 7l-3 1 2 2-2 4 4-2 2 2 1-3Z"/><path d="M9 15l-4 4"/>'],
        'api'        => ['kw' => ['api', '接口', '开发', '代码', '编程'],        'icon' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>'],
        'trend'      => ['kw' => ['趋势', '风口', '预测', '观察'],              'icon' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>'],
        'money'      => ['kw' => ['定价', '价格', '收入', '利润', '变现'],       'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v12M15.5 8.5c-.8-1-2-1.5-3.5-1.5-2 0-3.5 1-3.5 2.5 0 3.5 7 1.5 7 5 0 1.5-1.5 2.5-3.5 2.5-1.5 0-2.7-.5-3.5-1.5"/>'],
        'team'       => ['kw' => ['团队', '管理', '协作', '招聘'],              'icon' => '<circle cx="8" cy="9" r="3"/><circle cx="16" cy="9" r="3"/><path d="M2.5 19a5.5 5.5 0 0 1 11 0M13.5 14.5a5.5 5.5 0 0 1 8 4.5"/>'],
    ];

    /** 分类 key（含 a/b 子分类）→ 调色 */
    public static function palette(string $category): array {
        $cat = explode('/', $category)[0] ?? '';
        return self::PALETTE[$cat] ?? self::FALLBACK;
    }

    public static function svg(string $path): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }

    /** 内容符号：手动指定（cover_motif）优先，否则从标题/标签/摘要识别 */
    public static function motif(array $item): string {
        $m = trim((string)($item['cover_motif'] ?? ''));
        if ($m !== '' && isset(self::MOTIFS[$m])) return $m;
        $hay = mb_strtolower(($item['title'] ?? '') . ' ' . implode(' ', (array)($item['tags'] ?? [])) . ' ' . ($item['excerpt'] ?? ''));
        foreach (self::MOTIFS as $key => $def) {
            foreach ($def['kw'] as $kw) if (mb_strpos($hay, $kw) !== false) return $key;
        }
        return '';
    }

    /** 封面种子：cover_seed 优先，否则由标题哈希（稳定可复现） */
    public static function seed(array $item): int {
        $s = (int)($item['cover_seed'] ?? 0);
        if ($s > 0) return $s;
        return (int)(crc32(($item['title'] ?? '') . '|' . ($item['id'] ?? '')) % 100000);
    }

    /** 种子 → 版式参数（确定性） */
    private static function variantVars(int $seed): array {
        mt_srand($seed);
        $v = mt_rand(1, 4);                       // 版式：1 标志 / 2 水印 / 3 回响 / 4 轨道
        $r = mt_rand(-14, 14);                    // 符号旋转角
        $gx = mt_rand(0, 3);                      // 光晕落点：右上 / 左上 / 右下 / 左下
        $grid = [24, 28, 36][mt_rand(0, 2)];      // 网格密度
        mt_srand();                               // 不污染全局随机序列
        return ['v' => $v, 'r' => $r, 'gx' => $gx, 'grid' => $grid];
    }

    /** 光晕落点 → CSS 变量 */
    private static function glowPos(int $gx): array {
        return [[88, 13], [12, 10], [85, 88], [15, 85]][$gx % 4];
    }

    /** 符号层 HTML（v3 回响带两个缩小副本，v4 轨道带圆环和卫星点） */
    private static function symbolLayer(string $motifKey, array $p, array $vars): string {
        $icon = $motifKey !== '' ? self::MOTIFS[$motifKey]['icon'] : $p['icon'];
        $svg = self::svg($icon);
        $h = '<span class="gc-sym" aria-hidden="true">' . $svg . '</span>';
        if ($vars['v'] === 3) {
            $h .= '<span class="gc-sym e1" aria-hidden="true">' . $svg . '</span>'
                . '<span class="gc-sym e2" aria-hidden="true">' . $svg . '</span>';
        }
        if ($vars['v'] === 4) {
            $h = '<span class="gc-orbit" aria-hidden="true"><i></i><i></i>' . $svg . '</span>';
        }
        return $h;
    }

    /** gcov 容器通用属性（class + 种子变量 inline style） */
    private static function frame(array $item, string $extraClass = ''): array {
        $p = self::palette($item['category'] ?? '');
        $seed = self::seed($item);
        $vars = self::variantVars($seed);
        [$gx, $gy] = self::glowPos($vars['gx']);
        $style = '--sym-r:' . $vars['r'] . 'deg;--glow-x:' . $gx . '%;--glow-y:' . $gy . '%;--grid-s:' . $vars['grid'] . 'px';
        $class = 'gcov h-' . $p['hue'] . ' v' . $vars['v'] . ($extraClass ? ' ' . $extraClass : '');
        return [$p, $vars, $class, $style];
    }

    /** 分类名：优先站点分类表里的名字，没有再用内置名 */
    private static function catName(array $item, array $p): string {
        $key = $item['category'] ?? '';
        if ($key !== '' && function_exists('get_categories')) {
            foreach (get_categories('article') as $c) if (($c['key'] ?? '') === $key) return (string)$c['name'];
        }
        return $p['name'];
    }

    /** 列表卡封面（16:9）。卡片正文已有标题，默认不重复；独立使用（无正文）时传 $withTitle = true */
    public static function renderCard(array $item, bool $withTitle = false): string {
        [$p, $vars, $class, $style] = self::frame($item);
        $title = htmlspecialchars(mb_substr($item['title'] ?? '', 0, 48));
        return '<div class="' . $class . '" style="' . $style . '">'
            . self::symbolLayer(self::motif($item), $p, $vars)
            . '<span class="gc-code" aria-hidden="true">' . $p['code'] . '</span>'
            . '<span class="gc-k">' . self::svg($p['icon']) . htmlspecialchars(self::catName($item, $p)) . '</span>'
            . ($withTitle ? '<span class="gc-t">' . $title . '</span>' : '')
            . '</div>';
    }

    /** 正文页横幅（无图时） */
    public static function renderDetail(array $item): string {
        [$p, $vars, $class, $style] = self::frame($item, 'lg');
        $title = htmlspecialchars($item['title'] ?? '');
        $excerpt = htmlspecialchars(mb_substr(strip_tags($item['excerpt'] ?? $item['content'] ?? ''), 0, 80));
        $author = htmlspecialchars($item['author'] ?? '');
        $date = htmlspecialchars(substr($item['created_at'] ?? '', 0, 10));
        $meta = array_filter([$author, $date, !empty($item['_read_mins']) ? (int)$item['_read_mins'] . ' 分钟阅读' : '']);
        return '<div class="' . $class . '" style="' . $style . '">'
            . self::symbolLayer(self::motif($item), $p, $vars)
            . '<span class="gc-code" aria-hidden="true">' . $p['code'] . '</span>'
            . '<span class="gc-k">' . self::svg($p['icon']) . htmlspecialchars(self::catName($item, $p)) . '</span>'
            . '<h1 class="gc-t">' . $title . '</h1>'
            . ($excerpt ? '<p class="gc-d">' . $excerpt . '</p>' : '')
            . ($meta ? '<span class="gc-m">' . implode(' · ', $meta) . '</span>' : '')
            . '</div>';
    }

    /** 是否走生成式封面（没图，或用的是循环分配的资产池占位图） */
    public static function usesCssCover(array $item): bool {
        $cover = $item['cover'] ?? '';
        if (empty($cover)) return true;
        if (strpos($cover, 'assets/images/') === 0) return true;
        return false;
    }

    private static function coverUrl(string $cover): string {
        return strpos($cover, 'http') === 0 ? $cover : (defined('SITE_URL') ? SITE_URL : '') . '/' . ltrim($cover, '/');
    }

    /** 正文封面：优先图片，无图出横幅 */
    public static function renderDetailCover(array $item): string {
        if (self::usesCssCover($item)) return self::renderDetail($item);
        return '<img class="art-cover" src="' . htmlspecialchars(self::coverUrl($item['cover'])) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" loading="lazy">';
    }

    /** 列表卡封面：优先图片，无图出生成式封面 */
    public static function renderCardCover(array $item): string {
        if (self::usesCssCover($item)) return self::renderCard($item);
        return '<img src="' . htmlspecialchars(self::coverUrl($item['cover'])) . '" alt="" loading="lazy">';
    }
}
