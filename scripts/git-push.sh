#!/usr/bin/env bash
# 本机到 github.com 时好时坏：push 失败自动重试，最后用 ls-remote 校验远端 SHA。
# 用法: bash scripts/git-push.sh [branch]
set -uo pipefail
branch=${1:-main}
for i in 1 2 3 4 5; do
  if git push origin "$branch"; then break; fi
  echo "push 失败（第 $i 次），$((i*3))s 后重试…" >&2
  sleep $((i*3))
done
local_sha=$(git rev-parse HEAD)
remote_sha=$(git ls-remote origin "refs/heads/$branch" | awk '{print $1}')
if [ "$local_sha" != "$remote_sha" ]; then
  echo "推送未生效：local=$local_sha remote=$remote_sha" >&2
  exit 1
fi
echo "已推送并校验：origin/$branch = $local_sha"
