#!/usr/bin/env bash
#
# ship.sh —— 把 Claude 在云端做好的提交，安全地落到 GitHub + 服务器
#
# 用法（在你自己的电脑上跑，或由 Claude 通过设备桥跑）：
#   REPO_DIR=~/code/openflow BUNDLE=~/Downloads/of-ship.bundle \
#   DEPLOY_CMD='./deploy.sh' bash ship.sh
#
# 设计原则：宁可中止，不可搞乱。
#   - 只做 fast-forward（--ff-only）：对不上就停，绝不产生合并混乱或覆盖。
#   - 推送前后都可复核；任何一步失败立即退出。
#   - 不碰你的凭据：git push 用你电脑上已有的 GitHub 登录。
#
set -euo pipefail

REPO_DIR="${REPO_DIR:?请设置 REPO_DIR＝本地仓库路径}"
BUNDLE="${BUNDLE:?请设置 BUNDLE＝of-ship.bundle 的路径}"
BRANCH="${BRANCH:-main}"
DEPLOY_CMD="${DEPLOY_CMD:-}"        # 留空则跳过部署

say() { printf '\033[1;36m▶ %s\033[0m\n' "$*"; }
die() { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[ -d "$REPO_DIR/.git" ] || die "REPO_DIR 不是 git 仓库：$REPO_DIR"
[ -f "$BUNDLE" ]        || die "找不到 bundle：$BUNDLE"

cd "$REPO_DIR"
say "仓库：$REPO_DIR  分支：$BRANCH"

# 1. bundle 完整性校验（内含 sha1 校验，损坏会被发现）
git bundle verify "$BUNDLE" >/dev/null || die "bundle 校验失败，可能下载损坏"

# 2. 工作区必须干净，且当前就在目标分支
[ -z "$(git status --porcelain)" ] || die "工作区有未提交改动，先处理干净再来（git status 看看）"
cur="$(git rev-parse --abbrev-ref HEAD)"
[ "$cur" = "$BRANCH" ] || die "当前在分支 $cur，请先 git checkout $BRANCH"

# 3. 先和 GitHub 对齐（把别处的提交拉下来，仍是 ff-only）
say "与 GitHub 对齐…"
git fetch origin "$BRANCH"
git merge --ff-only "origin/$BRANCH" || die "本地与 origin/$BRANCH 分叉了，需要人工处理"

before="$(git rev-parse HEAD)"

# 4. 取出 bundle 里的提交，只允许 fast-forward。
#    若本地缺 bundle 的基线提交，git fetch 会直接报 "prerequisite commits" 并退出；
#    若不是干净 ff，--ff-only 会中止且不改动本地。两种失败都安全。
say "应用云端提交（fast-forward only）…"
git fetch "$BUNDLE" HEAD 2>&1 || die "bundle 与本地版本对不上（缺基线提交）——请让 Claude 基于最新 main 重新生成 bundle"
git merge --ff-only FETCH_HEAD || die "不是干净的 fast-forward，已中止（本地未改动）。让 Claude 基于最新 main 重做 bundle。"

after="$(git rev-parse HEAD)"
if [ "$before" = "$after" ]; then
  say "没有新提交（已经是最新），跳过推送与部署。"
  exit 0
fi

say "新增提交："
git --no-pager log --oneline "$before..$after"

# 6. 推送
say "推送到 GitHub…"
git push origin "$BRANCH"
say "已推送：${before:0:8} → ${after:0:8}"

# 7. 部署
if [ -n "$DEPLOY_CMD" ]; then
  say "执行部署：$DEPLOY_CMD"
  eval "$DEPLOY_CMD"
  say "部署完成。"
else
  say "未设置 DEPLOY_CMD，跳过部署。"
fi

say "全部完成 ✅"
