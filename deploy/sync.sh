#!/usr/bin/env bash
# OpenFlow 双轨同步（唯一入口）：
#   GitHub 轨 = 干净代码：gitleaks 扫描 → git add -A（.gitignore 已排除草稿/数据/密钥）→ commit → push origin main
#   服务器轨 = 完整内容：调用 scripts/deploy.py（逐文件 scp + md5 核验 + 远端备份 + 清缓存 / R2 / CF purge）
#                       从工作区上传，所以被 gitignore 但服务器需要的文件（如 assets/images/nav-sites/）可显式传入
#
# 用法：
#   bash deploy/sync.sh [-m "feat: xxx"] [文件或目录...]    # 提交推送 + 部署（自动包含相对 origin/main 的改动文件）
#   bash deploy/sync.sh --dry-run [文件...]                  # 只看计划：不提交、不推送、deploy.py --dry-run（不上传/不清缓存）
#   bash deploy/sync.sh --server-only [文件或目录...]        # 跳过 git，只同步服务器（不给文件则用相对 origin/main 的改动）
#   bash deploy/sync.sh --ci ...                             # 先跑 scripts/ci.sh --fast 质量门禁
#   透传给 deploy.py：--with-data <data/文件>  --no-r2  --no-purge  --no-cache  --from-git <REF>
#
# 旧命令照常可用：python3 scripts/deploy.py <文件...> / --rollback <时间戳>
# 安全约定：不使用 --delete（deploy.py 只增改不删）；data/ 默认不上传（服务器为源）。
set -euo pipefail
cd "$(dirname "$0")/.."

GITLEAKS="${GITLEAKS:-$HOME/.local/bin/gitleaks}"
BRANCH=main
MSG=""
DRY=0
SERVER_ONLY=0
RUN_CI=0
PASS=()      # 透传给 deploy.py 的参数
ARGS=()      # 用户显式给出的文件/目录

usage() { sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; }
step()  { printf '\n\033[1;36m═══ %s ═══\033[0m\n' "$*"; }
die()   { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
  case "$1" in
    -m|--message) MSG="${2:?-m 需要提交信息}"; shift 2 ;;
    --dry-run)     DRY=1; shift ;;
    --server-only) SERVER_ONLY=1; shift ;;
    --ci)          RUN_CI=1; shift ;;
    --no-r2|--no-purge|--no-cache) PASS+=("$1"); shift ;;
    --with-data|--from-git) PASS+=("$1" "${2:?$1 需要参数}"); shift 2 ;;
    -h|--help)     usage; exit 0 ;;
    --)            shift; ARGS+=("$@"); break ;;
    -*)            die "未知参数：$1（--help 查看用法）" ;;
    *)             ARGS+=("$1"); shift ;;
  esac
done
[ -n "$MSG" ] || MSG="chore: sync $(date '+%Y-%m-%d %H:%M')"

leaks_in_worktree() {
  # 已跟踪文件的未暂存 + 已暂存改动，以及未跟踪（且未被忽略）的新文件
  local rc=0 f
  "$GITLEAKS" git --pre-commit --redact --no-banner . || rc=1
  "$GITLEAKS" git --pre-commit --staged --redact --no-banner . || rc=1
  while IFS= read -r -d '' f; do
    "$GITLEAKS" dir --redact --no-banner "$f" >/dev/null 2>&1 || { echo "  ✗ 疑似密钥：$f"; rc=1; }
  done < <(git ls-files -o --exclude-standard -z)
  return $rc
}

# 自动部署清单：相对 origin/main 改动（含已提交未推送 + 工作区）的文件 + 未跟踪新文件，按 deploy.py 白名单过滤
auto_files() {
  local base
  base="$(git rev-parse --verify -q "origin/$BRANCH" || true)"
  [ -n "$base" ] || return 0
  { git diff --name-only --diff-filter=ACMRT "$base"; git ls-files -o --exclude-standard; } \
    | sort -u \
    | python3 -c '
import sys
sys.path.insert(0, "scripts")
from deploy import is_deployable
from pathlib import Path
for line in sys.stdin:
    rel = line.strip()
    if rel and Path(rel).is_file() and is_deployable(rel)[0]:
        print(rel)
'
}

# 显式参数：目录展开为文件（跳过 .DS_Store）
explicit_files() {
  local a
  for a in ${ARGS[@]+"${ARGS[@]}"}; do
    a="${a#./}"; a="${a%/}"
    if [ -d "$a" ]; then
      find "$a" -type f ! -name .DS_Store | sort
    else
      echo "$a"
    fi
  done
}

# ─── 1. 质量门禁（可选）───
if [ "$RUN_CI" = 1 ]; then
  step "1/4 质量门禁 scripts/ci.sh --fast"
  bash scripts/ci.sh --fast || die "质量门禁未通过，已中止"
fi

# 部署清单必须在 push 之前算（push 后 origin/main 前移，差异就没了）
FILES=()
while IFS= read -r f; do [ -n "$f" ] && FILES+=("$f"); done < <({ explicit_files; auto_files; } | awk '!seen[$0]++')

if [ "$SERVER_ONLY" = 0 ]; then
  [ -x "$GITLEAKS" ] || die "找不到 gitleaks：$GITLEAKS"
  cur="$(git rev-parse --abbrev-ref HEAD)"
  [ "$cur" = "$BRANCH" ] || die "当前分支是 ${cur}，GitHub 轨只推 ${BRANCH}（或用 --server-only）"

  # ─── 2. gitleaks ───
  step "2/4 gitleaks 扫描未提交改动"
  leaks_in_worktree || die "发现疑似密钥，已中止（误报把 fingerprint 加入 .gitleaksignore；真密钥改为读环境变量）"
  echo "✓ 未发现泄露"

  # ─── 3. git 提交 + 推送 ───
  step "3/4 GitHub：commit + push origin $BRANCH"
  if [ "$DRY" = 1 ]; then
    echo "（dry-run）将提交的改动："
    git status --short | head -40
    echo "（dry-run）将推送的提交："
    git --no-pager log --oneline "origin/$BRANCH..HEAD" 2>/dev/null | head -20 || true
  else
    if [ -n "$(git status --porcelain)" ]; then
      git add -A
      if ! "$GITLEAKS" git --pre-commit --staged --redact --no-banner . ; then
        git reset -q   # 只撤销暂存，不动工作区文件
        die "暂存内容中发现疑似密钥，已撤销暂存并中止"
      fi
      git commit -m "$MSG"
    else
      echo "无未提交改动（跳过 commit）"
    fi
    if [ -n "$(git rev-list "origin/$BRANCH..HEAD" 2>/dev/null)" ]; then
      git push origin "$BRANCH" || die "push 失败，未部署服务器（修复后可用 --server-only 补同步）"
    else
      echo "与 origin/$BRANCH 一致（无需 push）"
    fi
  fi
else
  step "跳过 GitHub 轨（--server-only）"
fi

# ─── 4. 服务器同步（deploy.py：备份 → scp → md5 核验 → 清缓存 → R2 → CF purge）───
step "4/4 服务器同步（scripts/deploy.py）"
DEPLOY_ARGS=(${PASS[@]+"${PASS[@]}"})
[ "$DRY" = 1 ] && DEPLOY_ARGS=(--dry-run ${DEPLOY_ARGS[@]+"${DEPLOY_ARGS[@]}"})
if [ ${#FILES[@]} -eq 0 ] && [[ " ${PASS[*]-} " != *" --from-git "* ]]; then
  echo "没有需要部署的文件（可显式传入路径，或 --from-git HEAD）"
  exit 0
fi
python3 scripts/deploy.py ${DEPLOY_ARGS[@]+"${DEPLOY_ARGS[@]}"} -- ${FILES[@]+"${FILES[@]}"}

step "完成 ✅"
