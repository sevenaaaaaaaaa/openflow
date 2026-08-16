<?php
/**
 * 主题 API — 输出当前激活主题的 CSS 变量
 * GET /api/theme.php        输出当前激活主题的完整 CSS
 * GET /api/theme.php?id=xxx 输出指定主题
 * GET /api/theme.php?list=1 输出全部主题元信息
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ThemeSystem.php';

// 列表模式
if (isset($_GET['list'])) {
    header('Content-Type: application/json; charset=utf-8');
    $out = [];
    foreach (ThemeSystem::all() as $id => $t) {
        $out[] = [
            'id' => $id, 'name' => $t['name'] ?? $id, 'desc' => $t['desc'] ?? '',
            'preset' => ThemeSystem::isPreset($id), 'active' => ThemeSystem::activeId() === $id,
            'accent' => ($t['light']['accent'] ?? ''),
            'radius' => ($t['layout']['r-lg'] ?? ''),
            'glass' => ($t['layout']['glass-strength'] ?? ''),
        ];
    }
    echo json_encode(['ok' => true, 'themes' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSS 输出模式
$id = isset($_GET['id']) ? trim($_GET['id']) : ThemeSystem::activeId();
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 分钟缓存
echo "/* OpenFlow 主题：{$id} */\n";
echo ThemeSystem::cssFor($id);
