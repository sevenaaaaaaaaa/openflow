# 一键部署指南

> 本页是精简版。完整版（含服务器配置要求、路径与权限、Cloudflare R2/Workers 全套步骤、
> 升级回滚、常见问题）在仓库 `docs/DEPLOYMENT.md`。

## 0. 部署前先知道三件事

- 技术底座是 **PHP 8 + JSON/SQLite**，没有 MySQL / Redis / Node 运行时依赖。
- **仓库根目录就是 Web 根目录**，路由由自带的 `.htaccess`（Apache）承担；Nginx 用户用 `deploy/nginx.site.conf`。
- 全部业务数据在 `data/` 与 `uploads/` 两个目录，**备份 = 打包这两个目录**，升级永不覆盖它们。

## 一、本地部署（开发预览）

要求：PHP 8.1+，扩展 `pdo_sqlite / gd / mbstring / curl / openssl`（推荐加 `fileinfo`）。

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git
cd openflow
php -S 127.0.0.1:8080 router.php
```

`router.php` 会镜像 `.htaccess` 的路由规则，美化 URL（如 `/articles`、`/xmp/today`）与线上行为一致。
首次访问进入安装向导：环境检查 → 站点名称 → 创建管理员。后台入口 `/xmp/login`。

## 二、Docker 部署（生产推荐）

仓库已带 `Dockerfile` 与 `docker-compose.yml`（Apache + PHP 8.3 + gd + opcache，数据卷持久化）：

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git
cd openflow
docker compose up -d          # http://127.0.0.1:8080
```

- 数据落在宿主机 `./data` 与 `./uploads`，容器随时可删可重建。
- 升级：`git pull && docker compose up -d --build`；回滚：checkout 回上一版本再 build。
- 对外服务：前面加 Nginx/Caddy 反代上 HTTPS，或用 Cloudflare Tunnel（免公网 IP）。

## 三、服务器原生部署

**配置要求**：最低 1 核 1G 内存 20G 盘；推荐 2 核 2G 40G。任意主流 Linux，
开 80/443。静态资产走 Cloudflare R2 后磁盘增长很慢。

**三步**：

1. **代码就位**：git clone 或上传到站点目录（如 `/www/wwwroot/openflow`），
   Web 根指向该目录（Apache 需 `AllowOverride All` + mod_rewrite）。
2. **权限**：`chown -R www-data:www-data data/ uploads/`
   （宝塔是 `www:www`。属主不对的典型症状：后台保存看似成功、数据没变）。
3. **装心跳**（不装则定时发布、自动化队列、AI 岗位、定时备份都不转）：

```cron
* * * * * curl -fsS --max-time 55 "https://你的域名/api/cron.php?secret=你的密钥" >/dev/null 2>&1
```

然后访问站点完成安装向导，进 `/xmp/login` 建站点。SSL 用 Let's Encrypt 或 Cloudflare。

**宝塔用户**：站点运行目录选项目根；Apache 直接吃 `.htaccess`，Nginx 把
`deploy/nginx.site.conf`（或 `deploy/baota-rewrites.conf`）贴进伪静态。

## 四、Cloudflare（边缘层，不是 PHP 主机）

Cloudflare Pages / Workers **不能运行 PHP**——别把仓库连到 Pages。正确用法是「边缘层」：

- **DNS 代理**：A 记录指向源站 IP 并开橙色云，SSL 模式选 Full。
- **R2 静态资产**（可选）：建桶后 `python3 deploy/sync-r2.py` 同步 `assets/`
  （凭据走 `R2_KEY / R2_SECRET` 环境变量，需 `pip3 install boto3`），
  再用 Worker 把 `你的域名/assets/*` 指向桶。
- **Tunnel 内网穿透**（NAS/家用机免公网 IP）：

```bash
cloudflared tunnel create openflow
cloudflared tunnel route dns openflow app.你的域名.com
cloudflared tunnel run --url http://127.0.0.1:8080 openflow
```

注意 `/xmp/*`（后台）与 `/s/*`（只读分享页）不要开边缘缓存；改 `assets/` 下的
CSS/JS 要 bump `includes/site-nav.php` 的 `OF_SHELL_VER` 再 purge，否则浏览器 7 天不回源。

## 五、Vercel（仅演示，读清限制）

仓库已带 `vercel.json`（`vercel-php` 运行时 + 前台核心页重写）：

```bash
npm i -g vercel
vercel --prod
```

**限制（诚实声明）**：Serverless 文件系统只读且冷启动归零——文章、订单、会员数据不会留存；
没有每分钟 cron，自动化不转；`.htaccess` 不生效（靠 vercel.json 重写，未含后台与 API）。
适合给客户演示前台，**不适合真实运营**。真跑起来请用 Docker 或服务器原生部署。

## 六、部署后验证

```bash
curl -s -o /dev/null -w "%{http_code}" https://你的域名/          # 期望 200
curl -s -o /dev/null -w "%{http_code}" https://你的域名/articles  # 期望 200（路由生效）
curl -s -o /dev/null -w "%{http_code}" https://你的域名/data/settings.json  # 期望 403/404
```

再浏览器确认 `/xmp/login` 能登录、后台保存一条设置刷新仍在、`data/php-error.log` 无刷屏报错。

## 七、常见问题

- **全站 404**：`.htaccess` 未生效，查 `AllowOverride All` 与 mod_rewrite；Nginx 用 `deploy/nginx.site.conf`。
- **保存成功但数据没变**：`data/` 属主不是 Web 用户，chown 后重试。
- **改了样式浏览器不更新**：7 天 immutable 缓存，bump `OF_SHELL_VER` 并清 CDN 缓存。
- **上传失败**：`uploads/` 不可写，或 PHP 上传上限偏小（Docker 镜像已设 32M）。
- **想看报错详情**：本地默认 dev 模式直接显示；生产看 `data/php-error.log`。
