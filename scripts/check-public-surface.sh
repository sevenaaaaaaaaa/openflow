#!/usr/bin/env bash
# 公开面守卫：本仓库是公开的，禁止把内网地址、本机 home 路径、Cloudflare 账号/Zone ID、私钥文件名写进来。
# 用法：bash scripts/check-public-surface.sh   （CI 与本地 pre-commit 都可调用）
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

# 只做"结构性"匹配，不列具体值（避免守卫本身成为泄露源）
PATTERN='(^|[^0-9])(10\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}|192\.168\.[0-9]{1,3}\.[0-9]{1,3}|172\.(1[6-9]|2[0-9]|3[01])\.[0-9]{1,3}\.[0-9]{1,3})([^0-9]|$)|/Users/[A-Za-z0-9._-]+|/home/[A-Za-z0-9._-]+|zones/[0-9a-f]{32}|[0-9a-f]{32}\.r2\.cloudflarestorage\.com|_id_(ed25519|rsa)'

# 允许清单：测试夹具与 UI 示例需要私网地址作为输入；它们不是运维信息。
HITS=$(git grep -nEI -- "$PATTERN" -- . \
  ':(exclude)scripts/check-public-surface.sh' \
  ':(exclude)tests/*' \
  ':(exclude)admin/api-keys.php' \
  ':(exclude)plugins/event-firewall/view.php' 2>/dev/null || true)
if [ -n "$HITS" ]; then
  echo "✗ 公开面守卫：发现不该进公开仓库的内容（内网地址 / 本机路径 / 账号或 Zone ID / 私钥文件名）" >&2
  echo "$HITS" >&2
  echo "→ 改成占位符；真实值放本机 .env（gitignored），并在文档里指向 .env。" >&2
  exit 1
fi
echo "✓ 公开面守卫：未发现内网地址 / 本机路径 / 账号 ID"
