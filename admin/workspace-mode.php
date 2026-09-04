<?php
require_once __DIR__ . '/config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
$mode = req_str('mode', '', true);
$result = workspace_mode_set($mode);
if (!$result['ok']) { http_response_code(422); exit('无效的工作台模式'); }
$next = req_str('next', $mode === 'loop' ? '/xmp/loop-workspace' : '/xmp/workspace', true);
if (!str_starts_with($next, '/xmp/') || str_contains($next, '//')) $next = '/xmp/workspace';
header('Location: ' . $next); exit;
