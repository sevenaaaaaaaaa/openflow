#!/usr/bin/env bash
#
# 本地预览后台页（绕开登录与验证码），用于「看得见」的视觉核对。
#
#   scripts/preview-admin.sh [端口] [数据目录]
#
# 例：scripts/preview-admin.sh 8899
#     → http://127.0.0.1:8899/xmp/today?view=team
#     → http://127.0.0.1:8899/xmp/table-views?type=dept&view=tree
#
# 说明：起一个 php 内置服务器（文档根=仓库根，静态资源照常加载），
# 路由脚本里预置管理会话，所以不需要登录。数据目录默认为临时目录，
# 想用真实数据可传自己的 data 路径（只读浏览更安全）。
set -euo pipefail

PORT="${1:-8899}"
DATA="${2:-/tmp/of-admin-preview}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

mkdir -p "$DATA"
cat > "$DATA/router.php" <<'PHPEOF'
<?php
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// 静态资源交给 php -S 自己处理（css/js/字体/图片）
if (preg_match('#\.(css|js|mjs|woff2?|png|jpe?g|svg|ico|webp|map)$#i', $path)) return false;

putenv('OF_ENV=dev');
putenv('OF_DATA_DIR=' . getenv('OF_PREVIEW_DATA'));

require getenv('OF_PREVIEW_ROOT') . '/admin/config.php';
$_SESSION['admin_login'] = true;
$_SESSION['admin_role']  = 'admin';
$_SESSION['admin_user']  = getenv('OF_PREVIEW_USER') ?: 'Seven';

$rel = ltrim(basename($path), '/');
if ($rel !== '' && !str_contains($rel, '.')) $rel .= '.php';   // /xmp/teams → teams.php
$file = getenv('OF_PREVIEW_ROOT') . '/admin/' . $rel;
// 根目录页（share.php / changelog.php 等）也支持预览
if (!is_file($file) && $rel !== '') $file = getenv('OF_PREVIEW_ROOT') . '/' . $rel;
if ($path === '/' || !is_file($file)) $file = getenv('OF_PREVIEW_ROOT') . '/index.php';
require $file;
PHPEOF

export OF_PREVIEW_ROOT="$ROOT"
export OF_PREVIEW_DATA="$DATA"
export OF_PREVIEW_USER="${OF_PREVIEW_USER:-Seven}"

echo "预览地址："
echo "  http://127.0.0.1:${PORT}/xmp/today?view=team"
echo "  http://127.0.0.1:${PORT}/xmp/table-views"
echo "数据目录：${DATA}（可用 OF_PREVIEW_USER=marketing 切换身份）"
echo "按 Ctrl+C 停止。"
exec php -S "127.0.0.1:${PORT}" -t "$ROOT" "$DATA/router.php"
