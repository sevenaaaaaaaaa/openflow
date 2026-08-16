<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('export');

$data = [
    'exported_at' => date('Y-m-d H:i:s'),
    'site_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'articles' => get_articles(),
    'pages' => [],
    'events' => json_read(DATA_DIR . '/events/index.json'),
    'downloads' => json_read(DATA_DIR . '/downloads.json'),
    'courses' => json_read(DATA_DIR . '/courses/index.json'),
    'landing_pages' => get_landing_pages(),
    'topics' => get_topics(),
    'forms' => json_read(DATA_DIR . '/forms/index.json'),
    'submissions' => json_read(DATA_DIR . '/submissions/index.json'),
    'redirects' => get_redirects(),
];

$pages = ['index', 'about', 'capability', 'courses'];
foreach ($pages as $p) $data['pages'][$p] = page_content($p);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="openflow-full-export-' . date('Ymd-His') . '.json"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
