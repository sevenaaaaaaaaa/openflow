# OpenFlow 部署配置（线上服务器）

> 供 Claude/开发者部署代码到线上服务器使用。请妥善保管，勿公开。

---

## 1. 仓库与分支

- **GitHub 仓库**：`https://github.com/sevenaaaaaaaaa/openflow`
- **分支**：`main`
- **线上基线**：`origin/main @ 72425ed`
- **本地部署命令**：`./deploy.sh`

---

## 2. 线上服务器 SSH 连接

| 项 | 值 |
|---|---|
| 主机 IP | `172.96.253.73` |
| SSH 端口 | `28766` |
| 用户 | `root` |
| 认证方式 | SSH Key（`~/.ssh/id_ed25519`）|
| 站点根目录 | `/www/wwwroot/nownexts_com` |
| 运行用户 | `www:www` |
| Web 服务器 | Apache（宝塔面板）|

连接测试：
```bash
ssh -p 28766 -o StrictHostKeyChecking=no root@172.96.253.73
```

---

## 3. 部署流程

### 3.1 本地代码 → 生产

项目根目录有 `deploy.sh`，是标准部署脚本：

```bash
cd /path/to/openflow
./deploy.sh
```

`deploy.sh` 内部逻辑：
1. **rsync 同步代码**（排除 `.git/`、`data/`、`uploads/`、`vendor/`、`.env` 等）
2. **同步 data 关键文件**（`articles/index.json`、`knowledge/index.json`、`settings.json`、`site-nav-content.php`、`skills/index.json` —— 这些是"种子/默认"内容，不含线上用户数据）
3. **清缓存 + 修复权限**：`rm -rf data/cache/* && chown -R www:www .`
4. **同步静态资源到 Cloudflare R2**：`python3 sync-r2.py`

### 3.2 关键原则：绝不覆盖线上 data/

`deploy.sh` 已通过 `--exclude='data/'` 排除整个 data 目录。
**但注意第 2 步会覆盖 5 个"种子"文件**（articles/index.json、settings.json 等）——如果本地改了这些文件且想部署，会覆盖线上对应文件。

> ⚠️ 若本次部署**只改代码**，建议跳过第 2 步的 data 覆盖，或先备份线上 data/：
> ```bash
> ssh -p 28766 root@172.96.253.73 "cd /www/wwwroot/nownexts_com && tar czf /tmp/backup-$(date +%Y%m%d).tgz data/"
> ```

---

## 4. 部署后验证

```bash
# 1. 首页正常
curl -s -o /dev/null -w "%{http_code}\n" https://nownexts.com/

# 2. 后台页面无 500（需要登录 session，用浏览器确认）
# 打开：https://nownexts.com/xmp/brain
#      https://nownexts.com/xmp/quotes
#      https://nownexts.com/xmp/crm

# 3. 检查 PHP 错误日志
ssh -p 28766 root@172.96.253.73 "tail -20 /www/wwwlogs/nownexts.com-error_log"
```

---

## 5. Cloudflare 配置（CF 在前面代理）

| 项 | 值 |
|---|---|
| Zone ID | `8135597542c2723a06a91a7e14a6e747` |
| API Token | 见本机 `docs/DEPLOY-SECRETS.md`（**勿推 GitHub**）|
| SSL 模式 | `full` |

部署后清 CF 缓存（否则浏览器可能拿到旧资源）：
```bash
# Zone ID 与 Token 从 docs/DEPLOY-SECRETS.md 读取
curl -s -X POST "https://api.cloudflare.com/client/v4/zones/<ZONE_ID>/purge_cache" \
  -H "Authorization: Bearer <API_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"purge_everything":true}'
```

---

## 6. Cloudflare R2（静态资源边缘存储）

`sync-r2.py` 内硬编码了凭据（Endpoint/Access/Secret/Bucket）。**注意：R2 密钥也是敏感信息，如推 GitHub 可能触发推送保护拦截**。凭据清单见本机 `docs/DEPLOY-SECRETS.md`（勿推 GitHub）。

| 项 | 值 |
|---|---|
| R2 Endpoint | 见 `docs/DEPLOY-SECRETS.md` |
| Access Key | 见 `docs/DEPLOY-SECRETS.md` |
| Secret Key | 见 `docs/DEPLOY-SECRETS.md` |
| Bucket | `nownexts-static` |

运行：`python3 sync-r2.py`（需要 boto3：`pip3 install boto3`）

R2 Worker（`r2-assets`）拦截 `nownexts.com/assets/*` 从 R2 返回；`img-opt` Worker 处理图片 WebP；`api-cache` Worker 缓存公开 API。

---

## 7. 生产数据目录结构

```
/www/wwwroot/nownexts_com/data/
├── articles/index.json      # 文章（500+）
├── shop/                    # 商城订单/设置/提现
├── skills/                  # Skills
├── navigation.json          # 导航站 417 站
├── courses/index.json       # 课程
├── settings.json            # 全局设置
├── cdp/                     # CDP 画像
├── crm.json                 # CRM
├── lang/                    # 11 语言包
├── notion-sync.json         # Notion 同步配置
├── cloudflare.json          # CF API 配置
└── ...（其他运行时数据）
```

---

## 8. 部署注意事项

1. **只部署代码**，保留服务器 `data/`（JSON + SQLite）。不要用仓库里的 `data/` 覆盖线上。
2. 新功能数据文件在运行时自动生成（`data/growth/` 等），无需手工迁移。
3. 新增 SQLite 表/索引用 `CREATE ... IF NOT EXISTS`，无破坏性迁移。
4. 新后台路由 `/xmp/brain` 与权限 `brain`：代码已加入 admin/sales/marketing 角色；`data/roles.json` 自定义角色默认不含 `brain`，需要时后台勾选（不阻断）。
5. 部署后跑 `php -l` 验证语法 + 测试套件。
6. 服务器 PHP 版本：`PHP 8.0.26`，已装扩展：`gd`、`pdo_sqlite`、`sqlite3`、`mbstring`、`curl`、`openssl`（**注意：未装 fileinfo，代码不要依赖 finfo_* 函数**）。
