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
sha=$(git rev-parse HEAD)
git fetch -q origin || true
if git merge-base --is-ancestor "$sha" "origin/$branch"; then
  echo "已推送并校验：$sha 已在 origin/$branch（允许他人并发提交）"
else
  echo "推送未生效：$sha 不在 origin/$branch" >&2
  exit 1
fi
