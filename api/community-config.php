<?php
/**
 * 社区首页配置 API
 * GET /api/community-config.php → 返回 community.json 内容
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$configFile = DATA_DIR . '/community.json';
$config = json_read($configFile);

// 默认值
$config += [
    'featured_article' => '',
    'floors' => [
        'insight' => ['enabled' => true, 'title' => '增长洞察', 'desc' => '把复杂的内容、流量与转化逻辑，翻译成一线运营者能用的判断与动作。'],
        'leadership' => ['enabled' => true, 'title' => '内容与 SEO 实践', 'desc' => '写给内容与运营负责人：从选题到收录，可直接照做的方法与话术。'],
        'ai_ops' => ['enabled' => true, 'title' => 'AI 运营实践', 'desc' => '写给每一位运营者：Agent 自动化、内容生成、舆情监测——让运营不再是消耗。'],
        'industry' => ['enabled' => true, 'title' => '行业实践', 'desc' => '不同行业的增长短板完全不同。方案按行业语境重写，而不是换个封面。'],
    ],
    'hot_read_count' => 5,
    'show_events_section' => false,
    'show_report_section' => true,
];

// 5个 floor 入口，并加上 .enabled = true 确保不丢
$floorKeys = ['insight', 'leadership', 'ai_ops', 'industry'];
foreach ($floorKeys as $k) {
    $config['floors'][$k]['enabled'] = $config['floors'][$k]['enabled'] ?? true;
    $config['floors'][$k]['categories'] = $config['floors'][$k]['categories'] ?? [$k];
}

echo json_encode(['ok' => true, 'config' => $config], JSON_UNESCAPED_UNICODE);
