# OpenFlow XMP — 最低配置要求与运维注意事项

---

## 一、最低系统要求

### 服务器

| 项目 | 最低要求 | 推荐配置 |
|------|---------|----------|
| PHP 版本 | 8.0+ | 8.2+ |
| Web 服务器 | Nginx / Apache / Caddy | Nginx 1.24+ |
| 操作系统 | Linux / macOS / Windows | Ubuntu 22.04+ / Debian 12+ |
| 内存 | 256 MB | 512 MB+ |
| 磁盘 | 100 MB（不含上传文件） | 1 GB+ |

### PHP 扩展（必装）

- `json` — JSON 数据处理（核心存储格式）
- `pdo_sqlite` — SQLite 数据库（高频写入层）
- `curl` — HTTP 请求（AI API、舆情采集、通知推送）
- `mbstring` — 多字节字符串处理（中文支持）
- `fileinfo` — 文件上传类型检测
- `gd` 或 `imagick` — 图片处理（缩略图、海报生成）

### PHP 扩展（推荐）

- `openssl` — SMTP 加密 / 支付签名
- `zip` — 批量导入 / 导出
- `sockets` — 直播 RTMP / WebSocket 事件监听
- `redis` — 可选缓存加速

### 可选外部服务

| 服务 | 用途 | 是否必需 |
|------|------|---------|
| SMTP 服务器 | 邮件发送（通知 / 营销） | 推荐 |
| AI API Key | AI Agent 与智能生成 | 推荐（OpenAI / Claude / DeepSeek 任一） |
| 支付渠道 | 虎皮椒 / 支付宝 / 微信支付 | 仅需线上销售时 |
| 企业微信 / 飞书 | 通知推送 | 可选 |
| OBS / nginx-rtmp | 直播推流 | 仅需直播时 |

---

## 二、部署检查清单

### Nginx 配置要点

参考项目根目录的 `nginx.conf.example`：

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/openflow;

    index index.html index.php;

    # 禁止直接访问 data 目录
    location /data/ { deny all; }

    # 禁止访问配置文件
    location ~ \.(json|ini|log|db)$ { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 目录权限

```bash
# 确保以下目录可写
chmod -R 755 /var/www/openflow/data
chmod -R 755 /var/www/openflow/uploads

# Web 服务器用户（www-data/nginx）需要对 data/ 有写权限
chown -R www-data:www-data /var/www/openflow/data
chown -R www-data:www-data /var/www/openflow/uploads
```

### 部署后验证

```bash
# 1. 检查 PHP 版本
php -v

# 2. 检查必要扩展
php -m | grep -E "json|pdo_sqlite|curl|mbstring|fileinfo|gd"

# 3. 访问健康检测页面
curl https://your-domain.com/admin/health-check.php

# 4. 访问 admin 确认后台可用
curl https://your-domain.com/admin/
```

---

## 三、运维注意事项

### 安全

1. **修改默认密码** — 部署后第一时间修改 `admin` 账户密码
2. **data/ 目录保护** — `data/` 目录包含所有业务数据，务必配置 Web 服务器拒绝直接访问
3. **HTTPS** — 生产环境必须启用 HTTPS，避免表单/登录信息明文传输
4. **定期备份** — 至少每天备份 `data/` 和 `uploads/` 目录
5. **文件权限** — 运行 PHP 的进程用户应有 data/ 和 uploads/ 的读写权限，但不应有代码文件的写权限
6. **禁⽤危险函数** — `php.ini` 中建议禁用 `exec`、`system`、`shell_exec` 等函数（系统不依赖它们）

### 性能

1. **SQLite 并发** — SQLite 适合中等并发（日 PV < 50 万）。高并发场景建议使用文件锁或升级至 MySQL
2. **OPcache** — 生产环境启用 PHP OPcache，提升 PHP 加载速度
3. **日志清理** — 定期使用后台「存储与性能」页面检查数据文件大小，执行一键清理（日志/聊天/埋点/回收站）
4. **Cron 定时任务** — 建议配置以下定时任务：
   ```bash
   # 每 6 小时：自动存储维护
   0 */6 * * * php /var/www/openflow/bin/of cron run
   ```

### 数据备份

```bash
# 全量备份（推荐每日凌晨执行）
tar -czf backup-$(date +%Y%m%d).tar.gz /var/www/openflow/data/ /var/www/openflow/uploads/

# 仅备份核心数据
tar -czf backup-core-$(date +%Y%m%d).tar.gz \
  /var/www/openflow/data/articles/ \
  /var/www/openflow/data/pages/ \
  /var/www/openflow/data/settings.json \
  /var/www/openflow/data/db/openflow.db
```

### 升级

1. 升级前**务必全量备份** `data/` 目录
2. 上传新版本文件覆盖（**不要覆盖** `data/` 和 `uploads/` 目录）
3. 访问 `/admin/health-check.php` 验证
4. 检查 `CHANGELOG.md` 了解是否有不兼容变更

### 故障排查

| 症状 | 可能原因 | 解决方案 |
|------|---------|----------|
| 白屏 / 500 错误 | PHP 扩展缺失 | 检查 `php -m`，安装缺失扩展 |
| 文章保存失败 | data/ 目录无写权限 | `chmod -R 777 data/` |
| AI Agent 无响应 | API Key 未配置或过期 | 检查「AI Agent 配置」页面 |
| 邮件未发送 | SMTP 未配置 | 检查「邮件配置」页面 |
| 直播推流失败 | RTMP 服务器未部署 | 安装 nginx-rtmp 或 SRS |
| SQLite 锁 | 并发写入过高 | 优化写入频率或升级至 MySQL |

### 日志位置

- PHP 错误日志：按 Web 服务器配置（常见 `/var/log/nginx/error.log`）
- 应用数据日志：`data/activity.json`
- 风控审核日志：后台「风控中心」
- 操作日志：后台「操作日志」

---

> 更多技术细节见 [DEVELOPER.md](DEVELOPER.md)
