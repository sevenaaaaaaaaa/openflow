<?php
/**
 * 导航站跳转统计 — 记录点击 + 302 跳转目标
 */
require_once __DIR__ . '/../admin/config.php';

$id = trim($_GET['site'] ?? '');
$nav = json_read(DATA_DIR . '/navigation.json');
$target = '';
foreach (($nav['sites'] ?? []) as &$s) {
    if (($s['id'] ?? '') === $id) {
        $s['hits'] = (int)($s['hits'] ?? 0) + 1;
        $target = $s['url'] ?? '';
        break;
    }
}
unset($s);
if ($target !== '') json_write(DATA_DIR . '/navigation.json', $nav);

if ($target !== '' && preg_match('#^https?://#i', $target)) {
    header('Location: ' . $target, true, 302);
} else {
    header('Location: /navigation.php', true, 302);
}
exit;
