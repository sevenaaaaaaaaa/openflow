# Agent 部署指南（NAS）

> 面向：接手开发的 AI 会话 / 新同事。目标：**照着做就能安全部署**，不碰别人的东西、不丢数据、能验证、能回滚。
> **现行流程只走 NAS。** 旧 VPS 已于 2026-11-23 退役，相关命令全部收进文末「历史附录」，**不要再执行**。
> 文中 `<NAS_HOST>` / `<WEB_ROOT_HOST>` / `<DEPLOY_USER>` 都是占位符：真实值只在本机 `.env`（`OF_DEPLOY_*`），不写进仓库。

---

## 0. 一分钟流程

```bash
python3 scripts/deploy.py --init-env   # 首次：把缺失的部署目标写进 .env（600，只补不覆盖）
bash deploy/sync.sh --dry-run          # 只看计划：不提交、不推送、不上传
bash deploy/sync.sh -m "feat: xxx"     # 提交推送 + 部署
```

---

## 1. 拓扑

| 项 | 值 |
|---|---|
| 主机 | NAS，`<DEPLOY_USER>````<NAS_HOST>`:22（免密 + sudo NOPASSWD） |
| Web 栈 | Apache + PHP，容器 `<WEB_CONTAINER>` 监听 `<LOOPBACK_BIND>` |
| 站点根 | 宿主机 `<WEB_ROOT_HOST>`（容器内挂载为 `/www/wwwroot`） |
| 数据 | SQLite（`data/db/openflow.db`）+ JSON（`data/*.json`），**服务器为源** |
| 静态资产 | Cloudflare R2（桶由 `R2_BUCKET` 指定）经 Workers 分发 |
| 入口 | Cloudflare Tunnel |

```
⚠ data/ 是运行时数据（用户、订单、内容、配置）：部署默认排除，永远不删。
```

---

## 2. 前置条件

1. 本机可 SSH 到 NAS（密钥已配；异地走 Tailscale / Cloudflare Tunnel）。
2. 本机有 `.env`（模板 `.env.example`）：至少 `OF_DEPLOY_HOST` / `OF_DEPLOY_USER` / `OF_DEPLOY_ROOT`；同步 `ops/` 还需要 `OF_DEPLOY_OPS_ROOT`。
3. R2 / Cloudflare 凭据：`R2_*`、`CF_TOKEN`（需 Zone.Cache Purge），可放 `.env` 或系统钥匙串。
4. 改了前端资产：本机有 Python + boto3（跑 `deploy/sync-r2.py`）。

---

## 3. 标准部署（唯一入口）

```bash
bash deploy/sync.sh -m "feat: xxx" [文件或目录...]
```

它做两件事：

1. **GitHub 轨**：gitleaks 扫未提交改动（有泄露即中止）→ `git add -A` → commit → push `origin main`。
2. **服务器轨**：`scripts/deploy.py` 从工作区上传（逐文件 scp + md5 核验 + 远端备份 + 清缓存 + R2 同步 + CF purge），**不删远端文件**。

常用变体：

```bash
bash deploy/sync.sh --server-only [文件...]   # 跳过 git，只同步服务器
bash deploy/sync.sh --ci ...                  # 先跑 scripts/ci.sh --fast
bash deploy/sync.sh --no-r2 --no-purge ...    # 应急：跳过 CDN 环节（会以退出码 3 失败，除非加 --allow-incomplete）
```

底层部署器（排障时直接用）：

```bash
python3 scripts/deploy.py --dry-run <文件...>
python3 scripts/deploy.py <文件...>
python3 scripts/deploy.py --from-git HEAD
python3 scripts/deploy.py --rollback <时间戳>
```

**单独改 `data/` 里某个文件**（如 `nav.json`）：只传那一个文件，绝不做目录级同步。

```bash
python3 scripts/deploy.py --with-data data/nav.json
```

---

## 4. 部署后验证（必做）

```bash
# 1) 核心页面 200
for u in / /product /capability /academy /events /pricing /help /docs; do
  printf "%s %s\n" "$(curl -s -o /dev/null -w '%{http_code}' https://nownexts.com$u)" "$u"
done

# 2) 内容真伪（不是错误页冒充 200）
curl -s https://nownexts.com/ | grep -oE "<title>[^<]*" | head -1

# 3) 关键 API
curl -s "https://nownexts.com/api/public-content?type=articles&limit=1" | head -c 200

# 4) 有后台可见改动：登录 /xmp 抽查目标页
```

---

## 5. 绝对规则（踩过的坑）

| 规则 | 原因 |
|---|---|
| **不碰 `data/`**（除明确单文件） | 生产数据；目录级同步会毁站 |
| **不动别的会话的 WIP** | 多会话并行；提交前 `git status` 看清是谁的改动 |
| **改共享资源前先对齐** | `.htaccess` / `plugins/` / vhost / CF zone 多产品共用 |
| **改前端资产必须 bump `OF_SHELL_VER`** | R2+CF 长缓存，不 bump 用户看不到 |
| **不修改 Apache 配置**（除非明确要求） | vhost 影响所有站点 |
| **部署 ≠ 提交** | 先看清改动，再决定 `-m` 信息与文件清单 |

---

## 6. 常见故障

| 现象 | 原因 | 处理 |
|---|---|---|
| 部署器报"缺少部署目标" | 本机没配 `OF_DEPLOY_*` | 跑 `python3 scripts/deploy.py --init-env` |
| `/xmp` 或 MCP 返回 401 | Apache 剥离 `Authorization` 头 | 确认 `.htaccess` 的 `CGIPassAuth On` 还在 |
| 改了 CSS/JS 浏览器仍旧 | R2/CF 缓存 + `?v=` 未变 | bump `OF_SHELL_VER` → 重跑部署（会自动同步 R2 + purge） |
| curl 拿到 Cloudflare 挑战页 | CF 对高频/无头请求挑战 | 用真实浏览器验证，或 `--resolve` 直连源站 |
| 页面"系统开小差了" | 某处 PHP 异常 | 服务器上单文件执行看异常栈 |
| SQLite 报不支持 FTS5/UPSERT | 服务器 SQLite 版本旧 | 代码需降级兼容，相关测试在服务器跳过 |
| 部署以退出码 3 结束 | R2/purge 未闭环 | 按提示补 `R2_*` / `CF_TOKEN` / `OF_CF_ZONE`，或应急 `--allow-incomplete` |

---

## 7. 回滚

```bash
# 代码：回到部署器记录的备份时间戳
python3 scripts/deploy.py --rollback <时间戳>

# 或 git 回退后重新部署
git checkout <good_commit>
bash deploy/sync.sh --server-only index.php ...
```

数据：`data/` 有每日备份；资产：R2 只增不删，旧文件仍在。

---

## 附录 A：历史（旧 VPS，2026-11-23 退役，**勿执行**）

以下内容保留仅为解释历史记录；主机、端口、面板路径、站根都已换占位符，实际值不应再出现。

- 迁移公告：2026-11-22 从旧 VPS 迁到 NAS；旧机 2026-11-23 到期退役。
- 旧方式 1：`rsync --delete` 全量同步到旧站根（macOS openrsync 有静默失败问题，已弃用）。
- 旧方式 2：`./deploy.sh`、`bin/deploy.sh --live`（rsync 全量，已弃用）。
- 旧验证：`ssh -p <RETIRED_SSH_PORT> ...` 清缓存、`scp` 单文件、`curl` 手动 purge。
- 旧自检：远端 shell 里拼一段 `curl -H "Host: ..."` 与 `df` 的输出。

> 结论：旧方式全部不要再用；现行流程见第 0–7 节。
