<?php
/**
 * API 文档生成器 — OpenAPI 3.0 规范
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/ApiPolicy.php';   // 档位表：自动生成的 security 依赖它

class ApiDocs {
    /**
     * 生成 OpenAPI 3.0 JSON
     */
    public static function generate(): array {
        $openapi = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => site_config_get('site_name', 'OpenFlow XMP') . ' API',
                'description' => 'OpenFlow XMP 开放接口文档',
                'version' => site_config_get('version', '2.0.0'),
                'contact' => [
                    'name' => 'OpenFlow',
                    'url' => site_config_get('site_url', '/'),
                ],
            ],
            'servers' => [
                ['url' => SITE_URL, 'description' => '当前服务器'],
            ],
            'components' => self::getComponents(),
            'paths' => self::getPaths(),
            'tags' => self::getTags(),
        ];

        return $openapi;
    }

    /**
     * 获取组件定义（schemas, securitySchemes）
     */
    private static function getComponents(): array {
        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
                'apiKeyAuth' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-API-Key',
                ],
                'memberSession' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'PHPSESSID',
                    'description' => '前台会员登录态',
                ],
                'adminSession' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'PHPSESSID',
                    'description' => '后台登录态（/xmp 会话）',
                ],
            ],
            'schemas' => [
                'Article' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'example' => 'article_20240101_120000'],
                        'title' => ['type' => 'string', 'example' => '文章标题'],
                        'slug' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'archived']],
                        'category' => ['type' => 'string'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'cover' => ['type' => 'string'],
                        'seo_title' => ['type' => 'string'],
                        'seo_desc' => ['type' => 'string'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'Member' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'example' => 'mem_abc123'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'phone' => ['type' => 'string'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'Course' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'price' => ['type' => 'number'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                        'chapters' => ['type' => 'array'],
                    ],
                ],
                'Lead' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'stage' => ['type' => 'string'],
                        'score' => ['type' => 'integer'],
                        'owner' => ['type' => 'string'],
                    ],
                ],
                'Error' => [
                    'type' => 'object',
                    'properties' => [
                        'ok' => ['type' => 'boolean', 'example' => false],
                        'error' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }

    /**
     * 获取 API 路径定义
     */
    /**
     * 路径定义：从 api/*.php **自动生成**（真实端点），鉴权档位取自 ApiPolicy。
     * 人工整理的域分组与用途说明见 docs/API-INVENTORY.md。
     */
    private static function getPaths(): array {
        $paths = [];
        foreach (glob(__DIR__ . '/../api/*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            if (in_array($slug, ['plugin'], true)) continue;   // 插件路由：/api/plugin/{id}/{path}
            $paths['/api/' . $slug] = self::buildPathItem($slug, $file);
        }
        ksort($paths);
        return $paths;
    }

    /** 单个端点的 OpenAPI Path Item */
    private static function buildPathItem(string $slug, string $file): array {
        $src = @file_get_contents($file) ?: '';
        $summary = self::endpointSummary($src, $slug);
        $tier = ['tier' => 'public', 'perm' => ''];
        if (function_exists('api_policy_for')) {
            try { $tier = api_policy_for($slug); } catch (\Throwable $e) {}
        }
        $security = self::securityForTier((string)($tier['tier'] ?? 'public'));
        $params = self::endpointParams($src);
        $tag = self::apiTagFor($slug);

        $responses = [
            '200' => ['description' => '成功（统一形状 {ok:true,data:…}）'],
            '400' => ['description' => '参数或业务错误（{ok:false,error:…}）'],
        ];
        if ($security) $responses['401'] = ['description' => '未认证'];
        if (($tier['tier'] ?? '') === 'admin') $responses['403'] = ['description' => '权限不足'];

        $item = [];
        $get = ['tags' => [$tag], 'summary' => $summary, 'parameters' => $params['query'], 'responses' => $responses];
        if ($security) $get['security'] = $security;
        $item['get'] = $get;

        if (str_contains($src, '$_POST')) {
            $props = [];
            foreach ($params['body'] as $p) $props[$p['name']] = ['type' => 'string'];
            $post = [
                'tags' => [$tag],
                'summary' => $summary . '（POST）',
                'responses' => array_merge(['201' => ['description' => '创建/提交成功']], $responses),
            ];
            if ($security) $post['security'] = $security;
            if ($props) $post['requestBody'] = ['required' => false, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => $props]]]];
            $item['post'] = $post;
        }
        return $item;
    }

    /** 文件 docblock 首句作为 summary */
    private static function endpointSummary(string $src, string $slug): string {
        if (preg_match('/\/\*\*(.*?)\*\//s', $src, $m)) {
            foreach (preg_split('/\R/', $m[1]) as $line) {
                $t = trim(preg_replace('#^\s*\*\s?#', '', $line));
                if ($t === '' || str_starts_with($t, '@')) continue;
                return mb_substr(preg_replace('/\s+/', ' ', $t), 0, 80);
            }
        }
        return $slug;
    }

    /** 扫描 $_GET / $_POST 键名 → 参数清单（启发式，最多各 12 个） */
    private static function endpointParams(string $src): array {
        $q = []; $b = [];
        if (preg_match_all('/\$_GET\[\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*\]/', $src, $m)) {
            foreach (array_unique($m[1]) as $k) if (count($q) < 12) $q[] = ['name' => $k, 'in' => 'query', 'schema' => ['type' => 'string']];
        }
        if (preg_match_all('/\$_POST\[\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*\]/', $src, $m2)) {
            foreach (array_unique($m2[1]) as $k) if (count($b) < 12) $b[] = ['name' => $k];
        }
        return ['query' => $q, 'body' => $b];
    }

    /** 鉴权档位 → OpenAPI security */
    private static function securityForTier(string $tier): array {
        return match ($tier) {
            'member' => [['memberSession' => []]],
            'admin'  => [['adminSession' => []]],
            'token'  => [['apiKeyAuth' => []]],
            default  => [],
        };
    }

    /** 端点 → 域标签 */
    private static function apiTagFor(string $slug): string {
        $map = [
            '内容/CMS' => ['article', 'import', 'rss', 'ingest', 'pages', 'landing', 'builder', 'templates', 'internal-links', 'calendar', 'cover', 'assign-covers', 'download', 'data-export', 'push', 'publish', 'content'],
            'AI/Agent' => ['ai-', 'agent', 'assistant', 'mainline', 'morning', 'build-skill', 'geo-citable', 'create'],
            '数据/CDP' => ['cdp', 'track', 'sdk', 'scripts', 'segments', 'realtime', 'reports', 'report', 'ask-data', 'trend-radar', 'growth-signal', 'evolution', 'recommend', 'dynamic-content', 'conversion', 'ab-event', 'click-tracks'],
            '营销/触达' => ['newsletter', 'promo', 'ads', 'form-submit', 'leads', 'consultation', 'tob', 'wechat', 'wecom', 'campaigns', 'automation', 'mail-track', 'unsubscribe', 'notifications', 'message'],
            '商业/交易' => ['cart', 'shop', 'mall', 'marketplace', 'activation', 'stock', 'subscription'],
            '社区/活动/课程' => ['community', 'comment', 'event-register', 'course', 'survey', 'nps', 'help-feedback', 'nav-submit', 'nav-click', 'share'],
            '账户' => ['member', 'sso', 'mp-login', 'consent', 'address', 'bookmark', 'follow'],
            'SEO/搜索' => ['seo', 'indexnow', 'sitemap', 'search'],
            '站点/运维' => ['site-', 'theme', 'lang', 'provision', 'plugin', 'developer', 'oauth', 'webhook', 'cron', 'health'],
        ];
        foreach ($map as $tag => $keys) {
            foreach ($keys as $k) if (str_contains($slug, $k)) return $tag;
        }
        return '其他';
    }


    /**
     * 获取标签分组
     */
    private static function getTags(): array {
        return array_map(
            fn($t) => ['name' => $t, 'description' => $t . '类端点'],
            ['内容/CMS', 'AI/Agent', '数据/CDP', '营销/触达', '商业/交易', '社区/活动/课程', '账户', 'SEO/搜索', '站点/运维', '其他']
        );
    }

    /**
     * 输出 HTML 文档页面
     */
    public static function renderHtml(): void {
        $json = json_encode(self::generate(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=site_config_get('site_name')?> API 文档</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>body{margin:0;padding:0}</style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
    SwaggerUIBundle({
        spec: <?=$json?>,
        dom_id: '#swagger-ui',
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIBundle.SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout"
    });
    </script>
</body>
</html>
        <?php
    }
}
