# CLAUDE.md — OpenFlow Dev

## 项目简介

OpenFlow（开务，林下 Understory 出品）：面向 OPC / DRI 的「增长操作系统」，以 TIPS（触达 · 洞察 · 个性化 · 销售）为骨架，含内容中心、CDP、自动化画布、AI 岗位/Agent、商城、学院、导航站等。线上站点 https://nownexts.com 。
这是主开发仓库（远端 `github.com/sevenaaaaaaaaa/openflow`，分支 `main`）。同一远端还有一个较旧的检出在 `/Users/seveno/openflow`（停在 2026-09-02，勿在那边开发/部署）。

## 技术栈

- PHP 8.1+（生产 PHP 8.3 / Apache），无框架单体；存储为 JSON + SQLite（`data/`），无 MySQL/Redis
- 路由：`.htaccess`（生产）/ `router.php`（本地 `php -S`，镜像 .htaccess）
- Composer（仅开发依赖：PHPUnit 10、PHPStan 2）；PSR-4 `App\\` → `lib/`
- 前端少量 TypeScript：`src/` 经 esbuild 构建产物写回 `assets/site-shell.js`（npm，Node ≥ 22，`package-lock.json`）
- 静态资源走 Cloudflare R2 + CDN；Python 辅助脚本（ruff.toml）

## 目录结构

- 根目录 `*.php` — 前台页面（index / product / courses / member …）
- `admin/` — 后台（/xmp）；`api/` — API 端点（含 `api/cron.php` 心跳）
- `lib/` — 核心业务类；`includes/` — 公共头部/导航（`includes/site-nav.php` 中的 `OF_SHELL_VER` 是前端资源版本号唯一真源）
- `assets/` — CSS/JS/图片；`src/` — TS 源码
- `data/` — 运行时数据与配置（服务器为源）；`uploads/` — 上传文件
- `plugins/`、`bin/`（CLI/种子/旧部署脚本）、`scripts/`（构建、CI、部署、i18n、种子脚本）
- `deploy/` — nginx/宝塔伪静态、R2 Worker、docker-entrypoint；`.deploy/` — deploy.py 的备份与清单（gitignored）
- `tests/` — ~180 个契约测试 + PHPUnit；`docs/` — 产品/架构/部署文档（`docs/DEPLOY-CONVENTIONS.md` 必读）

## 常用命令

```bash
# 安装
composer install          # 仓库自带 ./composer 可执行文件也可用
npm install

# 本地运行
php -S 127.0.0.1:8080 router.php
docker compose up -d      # http://127.0.0.1:8080（Apache + PHP 8.3）

# 前端构建 / 类型检查
npm run build
npm run typecheck
npm run ci                # typecheck + TS 单测 + build + 校验 assets/site-shell.js 已提交

# 测试
composer test             # PHPUnit
php tests/contract_suite.php   # 全部契约测试（CI 阻断项）
npm test                  # TS 单测（node --test）
bash scripts/ci.sh --fast # 一键质量门禁（测试 + 故事线审计 + 设计护栏）

# 静态分析
vendor/bin/phpstan analyse   # phpstan.neon + baseline
```

## 部署方式

- 生产：宝塔面板（Apache）服务器，`root@172.96.253.73`，SSH 端口 `28766`，目录 `/www/wwwroot/nownexts_com`，域名 nownexts.com（前置 Cloudflare，Cache Everything 规则）。
- **唯一入口（双轨同步，2026-10 起）**：`bash deploy/sync.sh`
  - GitHub 轨（公开仓库，只放干净代码）：gitleaks 扫描未提交改动（有泄露即中止）→ `git add -A`（`.gitignore` 已排除 `drafts/`、`leads.csv`、`data/`、`uploads/`、密钥等）→ commit（Conventional Commits，`-m` 指定）→ `git push origin main`；无改动则跳过 commit。
  - 服务器轨（完整内容）：调用 `scripts/deploy.py` 从**工作区**上传（逐文件 scp + md5 核验 + 远端备份 + 清缓存 / R2 / CF purge），自动清单 = 相对 `origin/main` 的改动文件 + 显式传入的文件/目录（gitignored 但服务器需要的文件，如 `assets/images/nav-sites/`，需显式传入）。不删远端文件，`data/` 默认不推。

```bash
bash deploy/sync.sh -m "feat: xxx" [文件或目录...]   # 提交推送 + 部署
bash deploy/sync.sh --dry-run                        # 只看计划（不提交/不推送/不上传）
bash deploy/sync.sh --server-only [文件...]          # 跳过 git，只同步服务器
bash deploy/sync.sh --ci ...                         # 先跑 scripts/ci.sh --fast
# 透传给 deploy.py：--with-data <file> --no-r2 --no-purge --no-cache --from-git <REF>
```

- 本地 `.git/hooks/pre-commit` 会用 gitleaks 扫描暂存区（误报 fingerprint 记在 `.gitleaksignore`）。
- 底层部署器（仍可直接用；2026-09-18 起的标准做法，逐文件 scp + md5 校验 + 可回滚）：

```bash
python3 scripts/deploy.py --dry-run <文件...>   # 先看计划
python3 scripts/deploy.py <文件...>             # 部署（自动清缓存 / R2 同步 / CF purge）
python3 scripts/deploy.py --from-git HEAD       # 部署上一提交改动的文件
python3 scripts/deploy.py --rollback <时间戳>
```

- 旧方式：`./deploy.sh`（rsync 全量，macOS openrsync 有静默失败问题）、`bin/deploy.sh --live`。
- 云端会话产出的提交通过 `scripts/ship.sh`（git bundle + ff-only → push → 可选部署）落地，见 `docs/DEPLOY.md`。
- 无 CI 自动部署：GitHub Actions `ci.yml` 只在 push/PR 到 `main` 时跑 lint + PHPUnit + 契约测试；部署均为手动。
- `vercel.json` / Dockerfile 存在，但 Vercel 仅适合演示（数据不持久）。

## 开发工具 vs 使用工具（硬性规则）

OpenFlow 既是在开发的产品，也是每天在用的工具。两类产物必须分开：

| 类别 | 例子 | 放哪 | GitHub | 服务器 |
|---|---|---|---|---|
| 开发工具（产品代码） | `admin/`、`api/`、`lib/`、`includes/`、`scripts/` 等 | 原目录 | ✅ | ✅（Web 根 `/www/wwwroot/nownexts_com`） |
| 使用工具的产物 | 运营/SEO 批处理脚本、批次文件、一次性修复脚本、导出数据 | **`ops/`** | ❌ | ✅ 仅 `/www/ops/openflow/`（Web 根之外，只增不删） |

- `/ops/` 已 gitignore；`deploy/sync.sh` 单独用 `rsync --update` 把它送到 `/www/ops/openflow/`（不走 deploy.py 白名单，绝不进 Web 根，`--dry-run` 只预览）。
- 拿不准时按第二类处理（放 `ops/`，不进 GitHub），再问用户。

## 注意事项

- 环境变量（仅名称）：`OF_ENV`、`SITE_URL`、`OF_DATA_DIR`、`OF_UPLOAD_DIR`、`OF_ROOT`、`OF_MULTI_TENANT`、`OF_TENANT_ROOT`、`OF_TENANT_UPLOAD_ROOT`、`OF_OFFSITE_KEY`、`OF_OFFSITE_SECRET`、`GITHUB_TOKEN`、`API_KEY`；部署脚本用 `OF_DEPLOY_HOST`、`OF_DEPLOY_PORT`、`OF_DEPLOY_ROOT`、`CF_TOKEN`、`OF_CF_ZONE`、`R2_KEY`、`R2_SECRET`、`R2_ENDPOINT`、`R2_BUCKET`。示例见 `.env.example`。
- 改了 `tokens.css` / `modules.css` / `fonts.css` / `site-shell.js` / `inject.js` 必须 bump `OF_SHELL_VER`（后台为 `OF_ADMIN_UI_VER`），页面不得硬编码 `?v=`；换图片需改 URL。
- 部署默认不推 `data/`（服务器为源），需显式 `--with-data`；`.htaccess`、`plugins/` 是多产品共享资源，改前先确认（见 `docs/DEPLOY-CONVENTIONS.md`）。
- `data/cloudflare.json` 等含凭证，勿读取/修改/提交；不要提交 `.env`、`vendor/`、`node_modules/`。
- 生产需配置 cron 每分钟请求 `/api/cron.php`。
