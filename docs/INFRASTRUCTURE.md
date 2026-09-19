# 基础设施配置（服务器 + Cloudflare）

> 一页看清线上到底有什么。密钥不在本文（见 gitignored 的 `docs/DEPLOY-SECRETS.md`）。

---

## 一、服务器

| 项 | 值 |
|---|---|
| 主机 | `root@172.96.253.73`，SSH 端口 **28766** |
| 面板 | 宝塔面板（Apache） |
| 系统盘 | 39G（已用 ~44%） |
| PHP | **8.3.33**（CLI：`/www/server/php/83/bin/php`） |
| 数据库 | SQLite **3.7.17**（旧：无 FTS5 / UPSERT / 部分索引 → 代码需降级兼容） |

### 1.1 站点与别名（Apache）

| 站点 | 根目录 | 入口 |
|---|---|---|
| 主站 | `/www/wwwroot/nownexts_com` | `nownexts.com`（`ServerAlias` 含 `www`） |
| PayFlow | `/www/wwwroot/payflow` | `Alias /payflow`；vhost `payflow.nownexts.com` |
| LearnFlow | `/www/wwwroot/learnflow` | `Alias /learnflow` |
| Webs Flow | `/www/wwwroot/websflow` | `Alias /webflow` |

配置文件位置：`/www/server/panel/vhost/apache/*.conf` 与 `…/extension/nownexts.com/*.conf`
（**改动需对齐**：影响所有站点）

### 1.2 常驻服务（systemd）

| 服务 | 说明 |
|---|---|
| `userloop.service` | UserLoop 全域用户运营（Python） |
| `mflow-console.service` | MFlow 交互式工作台（Python） |

```bash
systemctl status userloop           # 查看
journalctl -u userloop -n 50        # 日志
```

### 1.3 定时任务
- **OpenFlow cron**：`/api/cron`（订阅续费、提醒、过期订单清理等），由宝塔计划任务调用
- 各 Python 服务自带内部调度

---

## 二、Cloudflare

| 项 | 值 |
|---|---|
| 账号 | NowX（账户 ID 前缀 `00d02a54…`） |
| Zone | `nownexts.com` |
| Zone ID | `8135597542c2723a06a91a7e14a6e747` |
| SSL 模式 | **full**（非 strict：源站证书主机名不匹配也可） |
| 边缘证书 | Universal SSL（自动覆盖 `*.nownexts.com`） |

### 2.1 DNS（A / CNAME）

| 类型 | 名称 | 指向 | 代理 |
|---|---|---|---|
| A | `nownexts.com` | `172.96.253.73` | ✅ |
| CNAME | `www` | `nownexts.com` | ✅ |
| A | `payflow.nownexts.com` | `172.96.253.73` | ✅ |
| CNAME | `notion.nownexts.com` | vercel | ❌ |
| CNAME | `oneletters.nownexts.com` | 外部 | ❌ |
| CNAME | `743d83a5….nownexts.com` | verify.bing.com | ❌ |

（另有 MX/TXT：ProtonMail、DMARC/SPF/DKIM、各验证记录）

### 2.2 Workers 路由

| 路由 | Worker | 作用 |
|---|---|---|
| `nownexts.com/assets/*` | `r2-assets` | 静态资产从 R2 分发 |
| `nownexts.com/assets/images/*` | `img-opt` | 图片优化（WebP） |
| `nownexts.com/api/*` | `api-cache` | 公开 API 边缘缓存 |
| `nownexts.com/media/*` | `r2-media` | 视频/大文件，**支持 Range(206)** |

Worker 源码/部署脚本在仓库 `deploy/`（如 `r2-media-worker.js`、`deploy-r2-media.py`）。

### 2.3 R2

| 项 | 值 |
|---|---|
| 桶 | `nownexts-static` |
| Endpoint | `https://00d02a54a3c0f7a3f6c3fc75068e29c5.r2.cloudflarestorage.com` |
| 同步工具 | `python3 sync-r2.py`（本地 → R2，仅传变更）**需环境变量**（凭据已从代码移除） |
| 视频上传 | `python3 deploy/upload-r2-video.py 本地.mp4` → `nownexts.com/media/…`（同样读环境变量） |

```bash
# 凭据从环境变量注入（值见 gitignored 的 docs/DEPLOY-SECRETS.md）
export R2_KEY=... R2_SECRET=...
python3 sync-r2.py
```

### 2.4 常用操作

```bash
CF_TOKEN="<见 DEPLOY-SECRETS.md>"; ZONE="8135597542c2723a06a91a7e14a6e747"

# 定向清缓存
curl -s -X POST -H "Authorization: Bearer $CF_TOKEN" -H "Content-Type: application/json" \
  --data '{"files":["https://nownexts.com/product","https://nownexts.com/assets/modules.css"]}' \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/purge_cache"

# 全站清缓存（慎用）
curl -s -X POST -H "Authorization: Bearer $CF_TOKEN" -H "Content-Type: application/json" \
  --data '{"purge_everything":true}' \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/purge_cache"
```

---

## 三、部署与资产链路（一句话版）

```
本地代码 ──rsync(排除 data/)──▶ 服务器 /www/wwwroot/nownexts_com ──Apache/PHP──▶ 用户
静态资产 ──sync-r2.py──▶ R2 桶 ──Workers(r2-assets/img-opt)──▶ 用户
改资产后：bump OF_SHELL_VER ─▶ sync-r2 ─▶ purge CF ─▶ 清 data/cache
```

详见：`AGENT-DEPLOY.md`（部署步骤）、`DEPLOY-CONVENTIONS.md`（多会话约定 + 资产版本纪律）。

---

## 四、安全要点

| 项 | 做法 |
|---|---|
| 密钥存放 | 只在 `docs/DEPLOY-SECRETS.md`（gitignored）与服务器 `data/*.json`；**禁止入库** |
| `.env` / `data/` | Apache 已拒绝直连（`.htaccess`） |
| `Authorization` 头 | `.htaccess` 的 `CGIPassAuth On` 保证 Bearer 认证可用 |
| 后台登录 | `/xmp/login`（用户名 + 密码 + 算术验证码；2FA 可开） |
| Git 推送 | 含密钥会被 GitHub Push Protection 拦截（已踩过，密钥改环境变量） |

---

## 五、数据备份（现状 + 待办）

| 项 | 现状 |
|---|---|
| 自动备份 | ✅ 已启用：`data/backups/auto_YYYYmmdd_His/`（含 `data/` + `config/admin/config.php` + `meta.json`） |
| 触发者 | `/api/cron.php`（每分钟，内部按频率判定到期；后台「⏰ 定时备份」设置频率与保留份数） |
| 保留策略 | 只清理 `auto_` 前缀，保留最近 N 份（默认 3） |
| 巡检告警 | ✅ 每日一次抽检 10 个核心 URL（源站直连）；异常走 `notify_channels_send()`（企微/飞书/WhatsApp） |
| ⚠️ 待办 | **异地备份**：当前备份与本站在同一块盘，整机故障即同时丢失。建议用宝塔「计划任务 → 备份到云端」(S3/OSS/R2) 把 `data/backups/` 同步出去 |

修复记录（2026-09-17）：`BackupSystem` 曾使用从未定义的 `ROOT_DIR` → 备份功能**从未成功执行过**；后台「定时备份」表单也**没有处理器**（死表单）。两处均已修。

## Cloudflare：只读分享页必须绕过缓存（2026-09-19）

zone 的 Cache Rules（ruleset `e1d4b108650e409daff68bbc3f0f1b82`，"Cache Dynamic HTML for Anonymous Visitors"）
对匿名 HTML 统一缓存，edge TTL 覆盖源站（`override_origin`, 600s）。这条规则会把 `/s/{token}`
（只读分享页）也缓存下来，导致**撤销后外部还能打开最多 10 分钟**——与页面上「可随时撤销」的承诺不符。

处置：在该宽规则的表达式中加入 `and not starts_with(http.request.uri.path, "/s/")`。
注意 Cache Rules 的语义是**最后匹配者优先**：因此在前置位置加一条 `cache:false` 规则会被后面的
宽规则覆盖（实测仍 HIT），必须写成宽规则的排除条件。

验证：`cf-cache-status: DYNAMIC`（三次连续请求都不缓存）；撤销 token 后 CF 立即返回 404。
