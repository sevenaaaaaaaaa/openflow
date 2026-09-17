# Agent 部署 OpenFlow 指南

> 面向：接手开发的 AI 会话 / 新同事。
> 目标：**照着做就能安全部署**，不碰别人的东西，不丢数据，能验证，能回滚。

---

## 一、部署拓扑（先搞清"线上是什么"）

| 项 | 值 |
|---|---|
| 服务器 | `root@172.96.253.73`，SSH 端口 **28766** |
| 面板 | 宝塔（Apache + PHP 8.3.33） |
| 站点根 | `/www/wwwroot/nownexts_com` |
| PHP CLI | `/www/server/php/83/bin/php` |
| 数据库 | SQLite（`data/db/openflow.db`）+ JSON（`data/*.json`），**服务器为源** |
| 静态资产 | Cloudflare R2（桶 `nownexts-static`）经 Workers 分发 |
| 兄弟产品 | 同域 Alias 挂载：`/payflow` `/learnflow` `/webflow`；`userloop`/`mflow-console` 为 systemd 服务 |

⚠️ **`data/` 是运行时数据**（用户、订单、内容、配置）：**部署时永远排除，永远不删**。

---

## 二、前置条件

1. 本机可 SSH 到服务器（密钥已配）
2. 本机有 `rsync`
3. 密钥/令牌见 `docs/DEPLOY-SECRETS.md`（**gitignored**，含 CF token / R2 key / DeepSeek key）
4. 若改了前端资产：本机有 Python + boto3（跑 `sync-r2.py`）

---

## 三、标准部署（一次跑完）

```bash
cd "/Users/seveno/OpenFlow Dev"

# 1) 代码同步（排除运行时数据与别人的 WIP）
rsync -az --delete -e "ssh -p 28766" \
  --exclude='.git/'      --exclude='data/'        --exclude='uploads/' \
  --exclude='drafts/'    --exclude='vendor/'      --exclude='.env' \
  --exclude='.user.ini'  --exclude='node_modules/' \
  --exclude='.DS_Store'  --exclude='.phpunit.result.cache' \
  --exclude='*.bak*'     --exclude='*.old-*' \
  ./ "root@172.96.253.73:/www/wwwroot/nownexts_com/"

# 2) 若改了 assets/tokens.css|modules.css|site-shell.js|inject.js 等前端资产：
#    a. bump includes/site-nav.php 的 OF_SHELL_VER（否则用户 7 天看不到新样式）
#    b. export R2_KEY=... R2_SECRET=...   # 值见 DEPLOY-SECRETS.md（脚本已不硬编码）
#       python3 sync-r2.py                 # 上传到 R2
#    c. purge CF（见第 4 步）

# 3) 清页面缓存
ssh -p 28766 root@172.96.253.73 "rm -f /www/wwwroot/nownexts_com/data/cache/*.cache"

# 4) 清理 Cloudflare 缓存（改了页面/资产时）
CF_TOKEN="<见 DEPLOY-SECRETS.md>"
curl -s -X POST -H "Authorization: Bearer $CF_TOKEN" -H "Content-Type: application/json" \
  --data '{"files":["https://nownexts.com/","https://nownexts.com/product"]}' \
  "https://api.cloudflare.com/client/v4/zones/8135597542c2723a06a91a7e14a6e747/purge_cache"
# 或全站： --data '{"purge_everything":true}'
```

**需要单独同步 `data/` 里的某个文件时**（如 `nav.json`、`builder-pages.json`），**只 scp 那一个文件**，绝不做目录级同步：

```bash
scp -P 28766 data/nav.json root@172.96.253.73:/www/wwwroot/nownexts_com/data/nav.json
```

---

## 四、部署后验证（必做）

```bash
# 1) 核心页面 200
for u in / /product /capability /academy /events /pricing /help /docs; do
  printf "%s %s\n" "$(curl -s -o /dev/null -w '%{http_code}' https://nownexts.com$u)" "$u"
done

# 2) 内容真伪（不是错误页冒充 200）
curl -s https://nownexts.com/ | grep -oE "<title>[^<]*" | head -1

# 3) 关键 API
curl -s "https://nownexts.com/api/public-content?type=articles&limit=1" | head -c 200

# 4) 全仓测试（本地跑）
ok=0; for t in tests/*_test.php; do php "$t" >/dev/null 2>&1 && ok=$((ok+1)); done; echo "$ok 通过"

# 5) 若有后台可见的改动：登录 /xmp 抽查目标页
```

---

## 五、绝对规则（踩过的坑，别再犯）

| 规则 | 原因 |
|---|---|
| **不碰 `data/`**（除明确单个文件） | 那是生产数据；目录级 `--delete` 会毁站 |
| **不动别的会话的 WIP**：排除 `*.bak*` / `*.old-*` | 多会话并行（见 `DEPLOY-CONVENTIONS.md`） |
| **改共享资源前先对齐**：`.htaccess` / `plugins/` / Apache vhost / CF zone | 别的产品会话也在改 |
| **改前端资产必须 bump `OF_SHELL_VER`** | R2+CF 长缓存 7 天，不 bump 用户看不到 |
| **不修改 Apache 配置**（除非明确要求） | `/www/server/panel/vhost/apache/*` 影响所有站点 |
| **部署 ≠ 提交**：先 `git status` 看清自己的改动 | 避免把别人的改动一起提交 |

---

## 六、常见故障与处理

| 现象 | 原因 | 处理 |
|---|---|---|
| `/xmp` 或 MCP 返回 401 | Apache 默认剥离 `Authorization` 头 | `.htaccess` 已有 `CGIPassAuth On`，确认它还在 |
| 改了 CSS/JS 但浏览器还是旧的 | R2/CF 缓存 + `?v=` 未变 | bump `OF_SHELL_VER` → `sync-r2.py` → purge |
| curl 拿到 Cloudflare 挑战页 | CF 对高频/无头请求挑战 | 用真实浏览器验证；或 `--resolve` 直连源站 |
| 页面出现「系统开小差了」 | 某处 PHP 异常（如调用了不存在的类方法） | 服务器上直接跑该文件看异常：`php -r '$_SERVER["REQUEST_URI"]="/x"; require "x.php";'` |
| CLI 报 `Module "zip" is already loaded` | php.ini 重复加载（已知） | 无害，忽略 |
| 本地 CLI 渲染页面拿不到 `<link>` | 页面用了 `of_seo_bootstrap()` 输出缓冲 | **改用 HTTP 验证**，别用 CLI 判断 |
| 生产 SQLite 报不支持 FTS5/UPSERT | 服务器 SQLite 3.7.17（旧） | 代码需降级兼容；相关测试在服务器跳过 |

---

## 七、回滚

```bash
# 代码：git 回到上一个好版本 → 重新 rsync
git log --oneline -5
git checkout <good_commit>        # 或 git revert
# 再跑第三节的 rsync（data/ 未动，无需恢复）

# 数据：data/ 有每日备份（如需恢复，从服务器备份目录取）
# 资产：R2 上的旧版本仍在（sync-r2 只上传变更），必要时重传
```

---

## 八、一键自检脚本（建议部署后跑）

```bash
ssh -p 28766 root@172.96.253.73 '
cd /www/wwwroot/nownexts_com
echo "PHP 语法: $(find . -maxdepth 1 -name "*.php" -newer VERSION 2>/dev/null | head -0; echo ok)"
echo "页面: $(curl -sk -o /dev/null -w "%{http_code}" -H "Host: nownexts.com" https://127.0.0.1/)"
echo "缓存文件数: $(ls data/cache/*.cache 2>/dev/null | wc -l)"
echo "磁盘: $(df -h /www | tail -1 | awk "{print \$5}")"
'
```
