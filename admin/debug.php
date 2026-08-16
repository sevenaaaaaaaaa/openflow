<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('devops');

header('Content-Type: text/plain; charset=utf-8');

echo "=== SESSION DATA ===\n";
foreach ($_SESSION as $k => $v) {
    echo "$k => " . var_export($v, true) . "\n";
}

echo "\n=== has_perm('pages') => " . (has_perm('pages') ? 'TRUE' : 'FALSE') . "\n";
echo "=== has_perm('leads') => " . (has_perm('leads') ? 'TRUE' : 'FALSE') . "\n";
echo "=== has_perm('articles') => " . (has_perm('articles') ? 'TRUE' : 'FALSE') . "\n";

echo "\n=== role_perms() for current role ===\n";
$role = $_SESSION['admin_role'] ?? 'none';
$perms = role_perms()[$role] ?? [];
echo "Current role: $role\n";
echo "Permissions: " . implode(', ', $perms) . "\n";

echo "\n=== USERS FILE ===\n";
$users = get_users();
echo "User count: " . count($users) . "\n";
foreach ($users as $uk => $uv) {
    echo "  $uk => role={$uv['role']}, name={$uv['name']}\n";
}
