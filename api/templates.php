<?php
/**
 * Templates API — save/load/list
 */
require_once __DIR__ . '/../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$tplFile = DATA_DIR . '/templates.json';
$data = json_read($tplFile);

if ($action === 'save' && isset($input['template'])) {
    $data['templates'][] = $input['template'];
    if (count($data['templates']) > 50) $data['templates'] = array_slice($data['templates'], -50);
    json_write($tplFile, $data);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'list') {
    echo json_encode(['ok' => true, 'templates' => $data['templates'] ?? []]);
    exit;
}

if ($action === 'delete' && isset($input['id'])) {
    $data['templates'] = array_values(array_filter($data['templates'] ?? [], fn($t) => $t['id'] !== $input['id']));
    json_write($tplFile, $data);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);
