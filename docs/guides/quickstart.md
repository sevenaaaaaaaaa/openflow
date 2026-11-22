# 快速开始（一台服务器 + 一个域名）

> 主线：Linux 服务器（或 NAS 容器）+ 一个域名。其他形态见[索引](README.md)。预计 20–40 分钟，每步都有可判定的完成信号。

## S1 前置盘点

- 系统：Linux（Ubuntu/Debian/CentOS）或支持 Docker 的 NAS。
- PHP 8.1+ 与扩展（原生部署才需要）：pdo_sqlite、sqlite3、gd、mbstring、curl、openssl。
- 端口：80/443 可用；Docker 方案再留一个本地端口。
- 域名：能改 DNS，知道服务器公网 IP。

完成信号：四项都能明确回答；有任一项不满足，先解决再继续。

## S2 取代码

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git
cd openflow
```

完成信号：目录里能看到 index.php、docker-compose.yml、.htaccess。

## S3 起服务

Docker（推荐）：

```bash
docker compose up -d
```

原生 Apache/Nginx：把**仓库根目录**设为站点根并开启重写；Nginx 用 `deploy/nginx.site.conf`，Apache 确认 AllowOverride All。

完成信号：访问 http://服务器IP:端口/ 返回 200，标题是站点名而不是错误页。

## S4 安装向导

打开 `/setup-wizard.php`：环境检查 → 站点名称与地址 → 创建管理员。

注意：未安装时直接访问首页不会自动跳向导，请手动访问上面的地址。

完成信号：能用刚创建的管理员登录 `/xmp/login`。

## S5 域名与 HTTPS

- DNS 指向服务器（用 Cloudflare 时先「仅 DNS」验证，再决定是否开代理）。
- 反代或 Cloudflare Tunnel 配好证书，强制 https。

完成信号：外网 https://你的域名/ 可访问，浏览器无混合内容警告。

## S6 定时任务

加一条每分钟请求 `/api/cron.php` 的 cron（容器内或宿主机均可，按形态选择）。

完成信号：cron 日志有成功记录；后台定时/队列状态不再是等待。

## S7 静态资产（可选）

要把 CSS/JS/图片放 CDN 时，按部署文档配置对象存储与缓存刷新；改前端资源要同步更新版本号。

完成信号：Network 里资产来自 CDN 域名，刷新后是新版本。

## S8 备份与回滚

```bash
tar czf backup-$(date +%F).tgz data uploads   # 数据与上传就是全部业务数据
```

完成信号：备份能解开；你知道升级失败时怎么退回上一版（重装旧包或切回旧 tag，再恢复 data/uploads）。

下一步：[上线验收清单](go-live-checklist.md)。
