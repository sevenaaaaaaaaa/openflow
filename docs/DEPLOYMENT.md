# OpenFlow 部署指南（总入口）

> 一页覆盖五种部署形态：**本地开发 / Docker / 服务器原生（宝塔·Apache·Nginx）/ Vercel / Cloudflare**。
> 面向自托管用户与二次部署者。公开文档中心的精简版见 `docs/handbook/deployment.md`。
>
> 技术底座：**PHP 8 + JSON/SQLite，无 MySQL / Redis / Node 运行时依赖**——
> `data/` 放全部业务数据，`uploads/` 放媒体，备份=打包这两个目录。

---

## 0. 五种形态怎么选

| 形态 | 适用 | 数据持久 | 推荐度 |
|---|---|---|---|
| 本地开发（php -S） | 改代码、预览 | ✅ | 开发者日常 |
| Docker | 自托管服务器 / NAS / 家用主机 / 内网 | ✅（卷） | **生产推荐**，升级回滚最干净 |
| 服务器原生（Apache/Nginx + PHP-FPM） | 传统云主机、与现有站点共存 | ✅ | 生产推荐（当前 nownexts.com 即此形态） |
| Cloudflare | 边缘加速 / 静态资产 R2 / 内网穿透 Tunnel | — | 叠加在源站之上，**不能独立跑 PHP** |
| Vercel | 快速演示、给投资人看一版 | ❌（每次冷启动归零） | 仅演示，勿当生产 |

---

## 1. 运行要求（版本与环境矩阵）

### 1.1 必需

| 组件 | 版本要求 | 生产实测 | 说明 |
|---|---|---|---|
| PHP | **≥ 8.1**（composer 声明 ≥8.0，CI 测 8.1/8.2/8.3） | 8.3.33（CLI `/www/server/php/83/bin/php`） | 推荐 8.2 / 8.3 |
| PHP 扩展 | `pdo_sqlite` `sqlite3` `gd` `mbstring` `curl` `openssl` `fileinfo` | 服务器未装 fileinfo，代码不依赖 `finfo_*` | gd 用于分享卡/封面生成 |
| SQLite | 随 PHP 的 pdo_sqlite | 服务器系统库 3.7.17（代码已降级兼容：不依赖 FTS5/UPSERT） | 越新越好，3.7 也能跑 |
| Web 服务器 | Apache（mod_rewrite + .htaccess）**或** Nginx（php-fpm，规则见 `deploy/nginx.site.conf`） | Apache（宝塔） | 仓库根目录即 Web 根目录 |
| 磁盘 | `data/` + `uploads/` 需 Web 用户可写 | — | 权限见 §2 |

### 1.2 按需（运行时不需要，开发/发布才用）

| 组件 | 版本 | 什么时候需要 |
|---|---|---|
| Composer | 2.x | 跑 PHPUnit/PHPStan（`composer install` 装开发依赖；**运行时不需要 vendor/**，`lib/` 有 spl_autoload 兜底） |
| Node.js | ≥ 22 | 改前端 `src/*.ts`（`npm ci && npm run build` 产物写回 `assets/site-shell.js`） |
| Python 3 + boto3 | 任意较新版本 | 用 `deploy/sync-r2.py` 同步静态资产到 Cloudflare R2 时 |
| Docker / Docker Compose | 24+ / v2 | 走 §3 容器部署 |

### 1.3 环境变量

加载顺序：真实环境变量 > 项目根 `.env` > 默认值（实现见 `admin/config.php`，模板见 `.env.example`）。

| 变量 | 作用 | 默认 |
|---|---|---|
| `OF_ENV` | `dev` 显示错误 / `prod` 只记日志 | 按 Host 是否 localhost 自动判定 |
| `OF_DATA_DIR` | 数据目录 | `<项目根>/data` |
| `OF_UPLOAD_DIR` | 上传目录 | `<项目根>/uploads` |
| `SITE_URL` | 站点地址 | 建议部署后在后台「设置 → 站点配置」填写（落盘 `data/settings.json`） |
| `OF_MULTI_TENANT` / `OF_TENANT_ROOT` / `OF_TENANT_UPLOAD_ROOT` | 一台实例按域名切数据目录 | 关闭 |

---

## 2. 路径与目录（Web 根 = 仓库根）

部署时把**整个仓库根目录**设为站点根目录（Apache `DocumentRoot` / Nginx `root`）。PHP 页面就在根下平铺，这是刻意的架构——路由由 `.htaccess`（235 行重写规则）承担，Web 根即仓库根。

```
/var/www/openflow/          ← 站点根 = 仓库根（路径自定，生产实例为 /www/wwwroot/nownexts_com）
├── index.php …*.php        ← 前台页面（公开）
├── admin/                  ← 后台页面（/xmp/* 美化 URL 指向这里，登录在 /xmp/login）
├── api/                    ← 全部 API 端点（/api/xxx → api/xxx.php）
├── assets/                 ← 前端静态资产（生产建议走 R2+CDN，见 §5）
├── includes/ lib/ plugins/ src/ ← 页面壳 / 类库 / 插件 / TS 源码
├── docs/handbook/          ← 公开「文档中心」渲染源（docs.php 读取，勿删）
├── data/                   ← ★ 全部业务数据（JSON + SQLite + 备份）——必须可写、绝不入镜像/不覆盖
├── uploads/                ← ★ 上传媒体——必须可写
├── .htaccess               ← 全部路由规则（Apache；Nginx 等价物在 deploy/nginx.site.conf）
├── router.php              ← 仅本地 php -S 用（生产不用）
├── Dockerfile / docker-compose.yml / .dockerignore / vercel.json
└── deploy/                 ← 部署资产集中地：nginx 配置、R2 同步脚本、宝塔伪静态、容器 entrypoint
```

**权限（关键，出错症状是「后台保存看似成功、数据没变」）**：

```bash
# Apache 传统：www-data；宝塔：www。目录 755、data/uploads 下文件 644 + 属主可写
chown -R www-data:www-data /var/www/openflow/data /var/www/openflow/uploads
# 任何以 root 身份动过 data/ 的操作之后，都要重跑一次上面这条（生产踩过 1589 个 root 文件的坑）
```

`.htaccess` 已拒绝 `/data/` 与 `.env` 的直连访问；`.env`、`data/`、`uploads/` 均在 `.gitignore` 与 `.dockerignore` 中。

---

## 3. 本地部署（开发）

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git
cd openflow
php -S 127.0.0.1:8080 router.php     # router.php 镜像 .htaccess 路由，美化 URL 与线上一致
```

- 首次访问 `http://127.0.0.1:8080/` 会进入安装向导（环境检查 → 站点名 → 创建管理员）。
- localhost 域名自动 `OF_ENV=dev`（显示错误详情）；后台 `/xmp/login`。
- 想用真实数据预览后台页面：`scripts/preview-admin.sh 8899 [data目录]`（免登录，临时数据目录）。
- 也可以直接 `docker compose up -d`（见下节），与生产最接近。

---

## 4. Docker 部署

镜像 `php:8.3-apache`：gd 编译装好、mod_rewrite/headers/expires 开启、AllowOverride All、
opcache 与上传上限调好；entrypoint 启动前把数据卷属主交还 www-data。

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git && cd openflow
docker compose up -d                  # http://127.0.0.1:8080
# 换端口：OF_PORT=9000 docker compose up -d
```

- 数据落宿主机 `./data`、`./uploads`（bind mount），**备份 = tar 这两个目录**；容器可以随时删掉重建。
- 升级：`git pull && docker compose up -d --build`——`data/` 在卷里不受影响。
- 回滚：`git checkout <上一个 tag/commit> && docker compose up -d --build`。
- 生产对外：在前面加反代上 HTTPS（Nginx/Caddy/Cloudflare Tunnel 均可，见 §5.4），或 `OF_PORT` 绑内网端口。
- 单容器无数据库服务——SQLite 就是数据库，不要照搬网上「PHP+MySQL compose 模板」。

健康检查已内置（每 30s 请求首页）；容器日志：`docker logs openflow`，PHP 错误也在 `data/php-error.log`。

---

## 5. 服务器原生部署

### 5.1 服务器配置要求

OpenFlow 是轻量 PHP+SQLite 应用（无数据库服务、无常驻进程），配置门槛低：

| 档位 | 配置 | 能扛什么 |
|---|---|---|
| 最低 | 1 vCPU / 1 GB 内存 / 20 GB 盘 / 任意 Linux | 个人站、日均几千 PV、试运行 |
| 推荐 | 2 vCPU / 2 GB 内存 / 40 GB 盘 | 日均数万 PV、定时任务+AI 生成齐开 |

依据：生产实例 39 GB 盘用 44%（大头是数据与备份，静态资产走 R2 后磁盘增长慢）；
内存主要被 PHP-FPM/Apache 进程占用。带宽按访问量常规评估即可。

系统：任意主流 Linux（Ubuntu/Debian/CentOS/Alma 均可）；需开放 80/443；
SSH 建议密钥登录。时区应用内已设 `Asia/Shanghai`。

### 5.2 宝塔面板（生产实证路径）

当前 nownexts.com 即此形态，步骤照抄：

1. 宝塔新建站点，**运行目录就选项目根**（不指向 public 子目录），PHP 选 8.2/8.3（扩展装 `pdo_sqlite` `gd` `mbstring` `curl` `openssl`）。
2. 代码就位：`git clone` 或 rsync 上传到 `/www/wwwroot/你的站点`。
3. 伪静态：选「不使用」——Apache 直接吃仓库自带 `.htaccess`；若是 Nginx 站点，把 `deploy/nginx.site.conf` 内容（或 `deploy/baota-rewrites.conf`）贴进伪静态框。
4. 权限：`chown -R www:www 站点目录/data 站点目录/uploads`。
5. SSL：宝塔申请 Let's Encrypt，或源站用 Cloudflare Origin 证书（配合 §5.4 CF 代理）。
6. **装心跳（不装则定时发布/自动化队列/AI 岗位/备份巡检都不转）**：

```cron
* * * * * curl -fsS --max-time 55 "https://你的域名/api/cron.php?secret=你的密钥" >/dev/null 2>&1
```

7. 首次访问进安装向导；后台 `/xmp/login`。

### 5.3 通用 Linux（Apache / Nginx）

Apache（vhost 要点）：

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/openflow
    <Directory /var/www/openflow>
        AllowOverride All        # .htaccess 必须生效
        Require all granted
    </Directory>
</VirtualHost>
# 需要 mod_rewrite；API 的 Bearer 认证依赖 .htaccess 里的 CGIPassAuth On
```

Nginx：完整可抄配置在 **`deploy/nginx.site.conf`**（rewrite 规则、静态缓存、`/data` 拒绝、
php-fpm 对接），基础版在 `deploy/nginx.conf.example`。配 php-fpm（`www-data` 用户），
`fastcgi_param PHP_VALUE` 不必特殊设置。

### 5.4 Cloudflare（前置代理生产模式）

当前生产的完整形态，自建站照此复刻：

1. Cloudflare 添加站点 → DNS：`A @ <服务器IP> ✅已代理`，`CNAME www @ ✅`。
2. SSL/TLS 模式选 **Full**（源站有证书即可；要 Strict 需源站装 CF Origin 证书或有效公共证书）。
3. 缓存：HTML 默认遵源站；后台 `/xmp/*`、只读分享 `/s/*`、登录/支付回跳不要边缘缓存
   （我们踩过：宽泛的匿名 HTML 缓存规则把 `/s/{token}` 缓存住，撤销后外部还能开 10 分钟——
   解决办法是把 `/s/` 写进该规则的排除条件，而不是另加 cache:false 规则，后者会被宽规则覆盖）。
4. **静态资产走 R2**（可选但强烈推荐，源站磁盘和带宽大幅下降）：
   - R2 建桶（如 `your-static`）→ 生成 API Token（权限：该桶 Object Read & Write）；
   - 本机 `export R2_KEY=… R2_SECRET=… R2_ENDPOINT=… R2_BUCKET=…` 后跑 `python3 deploy/sync-r2.py`
     （仅传变更，boto3 依赖：`pip3 install boto3`）；
   - Worker 路由 `你的域名/assets/*` 从 R2 返回（参考 `deploy/r2-media-worker.js` 的写法，
     视频/大文件要支持 Range 206）；
   - 改了 `assets/` 下的 CSS/JS 必须 bump `includes/site-nav.php` 的 `OF_SHELL_VER`，
     再 sync → purge，否则浏览器 7 天 immutable 缓存不回源。
5. 清缓存：

```bash
curl -s -X POST -H "Authorization: Bearer $CF_TOKEN" -H "Content-Type: application/json" \
  --data '{"files":["https://your-domain.com/assets/modules.css"]}' \
  "https://api.cloudflare.com/client/v4/zones/<ZONE_ID>/purge_cache"
```

**Cloudflare Pages / Workers 不能运行 PHP**——它们只能托管静态导出或 Worker 脚本，
别把仓库直接连 Pages（得到的只是源码文件浏览）。Cloudflare 与 OpenFlow 的正确关系是上面这种
「边缘层」：DNS 代理 + 缓存 + R2 资产 + Workers 路由，源站仍是 Apache/Nginx/Docker。

**Cloudflare Tunnel（无公网 IP 自托管，推荐给 NAS/家用机）**：

```bash
cloudflared tunnel login
cloudflared tunnel create openflow
cloudflared tunnel route dns openflow app.your-domain.com
cloudflared tunnel run --url http://127.0.0.1:8080 openflow   # 指向 Docker 实例
```

docker-compose.yml 里已预留 cloudflared 服务注释块，填 token 取消注释即可。免开端口、自动 HTTPS。

### 5.5 部署后验证清单

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.com/          # 200
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.com/articles  # 200（.htaccess 路由生效）
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.com/data/settings.json   # 403/404（必须非 200）
```

再浏览器确认：安装向导/首页正常 → `/xmp/login` 能登录 → 后台保存一条设置后刷新仍在（验证 data/ 可写）
→ `tail data/php-error.log` 无刷屏报错。

---

## 6. Vercel（仅演示，读清限制再上车）

**先说清楚限制（诚实纪律）**：Vercel 是 Serverless 函数化托管——
①文件系统除 `/tmp` 外只读且**每次冷启动归零**：文章、订单、会员数据不会留存，后台改完一刷新就没；
②没有每分钟 cron：定时发布/自动化队列不转；③`.htaccess` 不生效，美化 URL 靠 `vercel.json`
重写（仓库已带，覆盖前台核心页，未含 `/xmp/*` 后台与 `/api/*`）；④按函数计费，页面文件多时注意额度。
**适合**：给客户/投资人演示前台、验证 SEO 结构。**不适合**：真实运营。真要跑起来请用 §4/§5。

步骤（仓库已含 `vercel.json`：`vercel-php` 运行时 + 前台核心页重写）：

```bash
npm i -g vercel
cd openflow
vercel            # 首次：关联/创建项目，一路默认（框架选 Other，不覆盖 vercel.json）
vercel --prod     # 交付生产域名 <project>.vercel.app
```

自定义域名：Vercel 控制台 → Settings → Domains → 添加域名 → 按提示在 DNS 服务商加 CNAME。
想每次部署后数据从零开始是特性不是 bug（`/tmp` 可写，会话内演示够用）。

---

## 7. 升级 / 回滚（所有形态通用）

- **铁律：永不覆盖线上 `data/`**。代码与数据彻底分离，升级只动代码。
- 原生部署升级：rsync 代码（排除 `data/ uploads/ .env`，参考 `bin/deploy.sh` 的排除清单）→
  清 `data/cache/*.cache` → 改过 `assets/` 则跑 sync-r2 + CF purge → §5.5 验证。
- 回滚：git checkout 到上一版本重新 rsync / rebuild；数据结构变更全部 `CREATE ... IF NOT EXISTS`，向后兼容无破坏性迁移。
- 备份：后台自带「定时备份」（`data/backups/auto_*`，cron 心跳触发）；**建议再配异地**
  （把 `data/backups/` 定期同步到 R2/S3/OSS——与本机同盘的备份在整机故障时会一起丢）。

---

## 8. 常见问题

| 症状 | 原因与解法 |
|---|---|
| 后台保存「成功」但数据没变 | `data/` 属主不是 Web 用户（root 动过）→ `chown -R www-data:www data/` |
| 全站 404 / 只有首页能开 | `.htaccess` 没生效：Apache 查 `AllowOverride All` + mod_rewrite；Nginx 用 `deploy/nginx.site.conf` |
| 改了 CSS/JS 浏览器还是旧的 | 7 天 immutable 缓存：bump `OF_SHELL_VER` → `deploy/sync-r2.py` → CF purge → 清 `data/cache/` |
| 数据库锁定（SQLite busy） | 并发写入高峰；代码已用即开即关短事务，仍遇到就确认没有常驻连接挂着事务 |
| 上传失败 | `uploads/` 不可写或 PHP `upload_max_filesize` 偏小（Docker 镜像已设 32M） |
| 页面 500 无详情 | 生产不显示错误，看 `data/php-error.log`；本地 `OF_ENV=dev` 复现 |
| 服务器 PHP 较老（8.0/8.1） | composer 声明 ≥8.0，CI 测到 8.3；推荐 8.2+，低于 8.1 不在测试矩阵内 |

---

## 附：本仓库部署相关文件速查

| 文件 | 用途 |
|---|---|
| `Dockerfile` / `docker-compose.yml` / `.dockerignore` | 容器部署三件套 |
| `deploy/docker-entrypoint.sh` | 容器启动前 chown 数据卷 |
| `deploy/nginx.site.conf` / `deploy/nginx.conf.example` | Nginx 完整/基础配置 |
| `deploy/baota-rewrites.conf` | 宝塔 Nginx 伪静态 |
| `deploy/sync-r2.py` | 静态资产同步 Cloudflare R2（读 `R2_KEY/R2_SECRET` 环境变量） |
| `deploy/r2-media-worker.js` / `deploy-r2-media.py` / `upload-r2-video.py` | R2 媒体 Worker 与上传 |
| `vercel.json` | Vercel 演示部署 |
| `router.php` | 本地 `php -S` 路由器 |
| `bin/deploy.sh` / `scripts/deploy.py` | 维护者自己的发布脚本（rsync / scp+md5 断言） |
| `docs/INFRASTRUCTURE.md` | 生产基础设施实录（服务器/CF/DNS/Workers） |
| `docs/AGENT-DEPLOY.md` / `docs/DEPLOY-CONVENTIONS.md` | 维护者发布流程与多会话约定 |
| `docs/releases/` | 历史发布说明 |
