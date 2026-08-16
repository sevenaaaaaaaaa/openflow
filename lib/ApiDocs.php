<?php
/**
 * API 文档生成器 — OpenAPI 3.0 规范
 */
require_once __DIR__ . '/../admin/config.php';

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
    private static function getPaths(): array {
        return [
            // 文章 API
            '/api/v1/articles' => [
                'get' => [
                    'tags' => ['Articles'],
                    'summary' => '获取文章列表',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['draft', 'published']]],
                        ['name' => 'category', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Article']]]],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Articles'],
                    'summary' => '创建文章',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Article']]],
                    ],
                    'responses' => [
                        '201' => ['description' => '创建成功'],
                        '401' => ['description' => '未授权'],
                    ],
                ],
            ],
            '/api/v1/articles/{id}' => [
                'get' => [
                    'tags' => ['Articles'],
                    'summary' => '获取文章详情',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => '成功', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Article']]]],
                        '404' => ['description' => '不存在'],
                    ],
                ],
                'put' => [
                    'tags' => ['Articles'],
                    'summary' => '更新文章',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => '更新成功'],
                    ],
                ],
                'delete' => [
                    'tags' => ['Articles'],
                    'summary' => '删除文章',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => '删除成功'],
                    ],
                ],
            ],

            // 会员 API
            '/api/v1/members' => [
                'get' => [
                    'tags' => ['Members'],
                    'summary' => '获取会员列表',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => ['description' => '成功', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Member']]]]],
                    ],
                ],
            ],
            '/api/v1/members/{id}' => [
                'get' => [
                    'tags' => ['Members'],
                    'summary' => '获取会员详情',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => '成功'],
                    ],
                ],
            ],

            // 课程 API
            '/api/v1/courses' => [
                'get' => [
                    'tags' => ['Courses'],
                    'summary' => '获取课程列表',
                    'responses' => [
                        '200' => ['description' => '成功', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Course']]]]],
                    ],
                ],
            ],

            // CRM API
            '/api/v1/leads' => [
                'get' => [
                    'tags' => ['CRM'],
                    'summary' => '获取线索列表',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => ['description' => '成功'],
                    ],
                ],
                'post' => [
                    'tags' => ['CRM'],
                    'summary' => '创建线索',
                    'responses' => [
                        '201' => ['description' => '创建成功'],
                    ],
                ],
            ],

            // 行为追踪 API
            '/api/v1/track' => [
                'post' => [
                    'tags' => ['CDP'],
                    'summary' => '上报用户行为',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                            'event' => ['type' => 'string'],
                            'properties' => ['type' => 'object'],
                        ]]]],
                    ],
                    'responses' => [
                        '200' => ['description' => '成功'],
                    ],
                ],
            ],

            // 文件上传
            '/api/v1/upload' => [
                'post' => [
                    'tags' => ['Media'],
                    'summary' => '上传文件',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'content' => ['multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => [
                            'file' => ['type' => 'string', 'format' => 'binary'],
                            'dir' => ['type' => 'string'],
                        ]]]],
                    ],
                    'responses' => [
                        '200' => ['description' => '上传成功'],
                    ],
                ],
            ],
        ];
    }

    /**
     * 获取标签分组
     */
    private static function getTags(): array {
        return [
            ['name' => 'Articles', 'description' => '文章管理'],
            ['name' => 'Members', 'description' => '会员管理'],
            ['name' => 'Courses', 'description' => '课程管理'],
            ['name' => 'CRM', 'description' => 'CRM 线索'],
            ['name' => 'CDP', 'description' => '用户行为追踪'],
            ['name' => 'Media', 'description' => '媒体文件'],
        ];
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
