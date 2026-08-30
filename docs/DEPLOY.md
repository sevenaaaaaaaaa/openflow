# 发布流程：从云端到 GitHub + 服务器

> 解决"Claude 在云端做好，却推不上去"的问题。

## 背景

Claude 的云端会话通过一个 git 代理访问 GitHub，代理只对**已授权的仓库**注入
推送凭据。本仓库不在该会话的授权集里，所以云端**能读不能推**（这是防止
会话乱推任意仓库的安全边界，不是 bug）。

因此约定：**云端只管做提交，落地由你的电脑完成**——用你电脑上已有的 GitHub
登录推送，顺带跑部署脚本。整个过程用 `git bundle` 搬运提交，比打补丁安全得多：
bundle 自带 sha1 校验、只做 fast-forward、对不上就中止且不改动本地，不会像
`git am` 那样把补丁打歪或产生冲突残留。

## 每一轮怎么走

**1. 云端（Claude）**：做完提交后生成一个增量 bundle

```
git bundle create of-ship.bundle <上次已发布的commit>..HEAD
```

Claude 会把这个 `of-ship.bundle` 通过设备桥写进你的仓库目录，或作为文件发给你。

**2. 你的电脑**：跑发布脚本（Claude 也可通过设备桥替你跑）

```
REPO_DIR=~/你的/openflow \
BUNDLE=~/路径/of-ship.bundle \
DEPLOY_CMD='./你的部署脚本.sh' \
bash scripts/ship.sh
```

脚本会依次：校验 bundle → 确认工作区干净且在 main → 与 GitHub 对齐
→ fast-forward 应用云端提交 → `git push` → 跑部署脚本。任何一步不对就停，
本地不会被改坏。

## 安全保证

- **只 fast-forward**：`--ff-only`。云端提交若不是干净地接在当前 main 之后，
  脚本中止、本地零改动，并提示 Claude 基于最新 main 重做 bundle。
- **推送前对齐**：先 `git fetch` + ff-only 合并 origin，别处的提交不会被覆盖。
- **不碰凭据**：`git push` 用你电脑上已登录的 GitHub 身份，Claude 全程不接触
  你的密码或 token。
- **可复核**：推送前脚本会打印将要推送的提交列表。

## 换成"服务器自己拉"（可选，进一步省事）

如果给服务器配一个 GitHub webhook 或定时 `git pull`，那么第 2 步 push 成功后
服务器会自动更新，`DEPLOY_CMD` 就能留空。是否要这样取决于你的服务器能否
被 GitHub 触达。
