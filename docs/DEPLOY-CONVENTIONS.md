# 部署与多会话协作约定

> 背景：产品矩阵多个项目（OpenFlow / UserLoop / LearnFlow / PayFlow / MFlow / Webs Flow / inFlow）
> 在各自仓库、各自会话中开发，但**部署层汇聚在主域 nownexts.com 与 OpenFlow 目录**。
> 已发生过一次交叉：一个会话改了 OpenFlow 的 `plugins/` 与 `.htaccess`，另一个会话部署时把
> 这些未提交改动一起带上了线——没有丢数据，但两边都不知情。本文档把规矩写下来，避免重复。

---

## 一、共享资源清单（**动手前必须对齐，由 seveno 手动确认**）

| 共享资源 | 本地位置 | 服务器位置 | 谁会碰 |
|---|---|---|---|
| OpenFlow 路由规则 | `OpenFlow Dev/.htaccess` | `/www/wwwroot/nownexts_com/.htaccess` | OpenFlow 会话；产品接入会话（加 301/Alias 例外） |
| Apache 站点/别名 | — | `/www/server/panel/vhost/apache/*.conf`、`extension/nownexts.com/*.conf` | 产品接入（`Alias /<product>`） |
| OpenFlow 插件 | `OpenFlow Dev/plugins/` | `/www/wwwroot/nownexts_com/plugins/` | OpenFlow；产品集成（如 `userloop-tracker`） |
| 共享凭证 | `OpenFlow Dev/data/cloudflare.json` | — | 各产品 `deploy/sync.sh` **读取**（只读，勿改） |
| Cloudflare zone / DNS | — | Zone `8135597542c2723a06a91a7e14a6e747` | 各产品 purge / 新增 DNS 记录 |
| 主域路径命名空间 | — | `nownexts.com/<product>` | 各产品 app（Alias 挂载） |

**规则**：同一时间只允许一个会话改上表资源；改之前找 seveno 确认没有别的会话在动。

---

## 二、部署方式：路径别名 vs 子域名

### A. 路径别名（当前采用）

```
Alias /<product>  /www/wwwroot/<product>     # 挂在 nownexts.com 下
```

- **优点**：统一主域（共享 Cloudflare/证书/缓存策略）、管理集中、**数据按目录切割**、备份/迁移简单
- **代价**：路径命名空间共享 → `.htaccess` 规则容易冲突
  （实例：`/learnflow` 既要给应用，又要兼容旧的营销页 301，最后靠
  `RewriteRule ^learnflow(/.*)?$ - [L]` 例外让位给应用）

### B. 子域名（对外建议他人采用）

```
<product>.nownexts.com → /www/wwwroot/<product>
```

- **优点**：命名空间天然隔离、会话之间不会因为 `.htaccess` 打架
- **代价**：证书/DNS/vhost 配置分散，逐站维护

> 现状取舍：自家产品为了**便于管理 + 数据切割**先用路径别名；**建议外部/他人部署走子域名**。
> 后续若隔离成本超过收益，再考虑把路径别名逐个迁移到子域名。

---

## 三、安全部署命令（推荐所有会话统一用这条）

```bash
# *.bak* / *.old-* 的保护：避免 --delete 误删其他会话的备份与中间产物
rsync -az --delete -e "ssh -p 28766" \
  --exclude='.git/'      --exclude='data/'       --exclude='uploads/' \
  --exclude='drafts/'    --exclude='vendor/'     --exclude='.env' \
  --exclude='.user.ini'  --exclude='node_modules/' \
  --exclude='.DS_Store'  --exclude='.phpunit.result.cache' \
  --exclude='*.bak*'     --exclude='*.old-*' \
  ./ "root@172.96.253.73:/www/wwwroot/nownexts_com/"
```

要点：
- **不要**用不排除 `*.bak*` / `*.old-*` 的 `--delete`，否则会把别人正在用的备份/中间产物删掉
- `.htaccess` 会随部署覆盖服务器版本——如果服务器上有别会话加的规则，先 `diff` 再部署
- 部署前后各清一次缓存：`rm -f data/cache/*.cache`；改了前端资源再 purge CF（见 `docs/DEPLOY-CONFIG.md`）

---

## 四、如何察觉"别人动过"

```bash
# 本地：工作区出现不是你改的改动
git status --short
git diff <可疑文件>

# 服务器：出现带时间戳的备份/改名残留，说明有会话刚动过
ls -l /www/wwwroot/nownexts_com/.htaccess.bak-* /www/wwwroot/nownexts_com/*.old-*

# 服务器 Apache：别名/例外由谁加的
grep -rn "Alias\|RewriteRule" /www/server/panel/vhost/apache/extension/nownexts.com/
```

发现交叉时：**不要硬覆盖**，先看 `git diff` / 服务器备份，确认内容后再决定合并还是回退，并告知 seveno。

---

## 五、给各产品会话的最小建议

1. 你的产品 app 部署到 `/www/wwwroot/<product>`，由**主站的 Alias** 挂载（当前方式）——不要自己往主站 `.htaccess` 里加规则，需要规则时找 seveno 确认后加，并在此文档登记
2. 只**读**`OpenFlow Dev/data/cloudflare.json` 取凭证，不要写
3. 需要独占路径时，优先申请**子域名**（隔离最好）
4. 每次同步后自检：`nownexts.com/<your-product>/` 200、`nownexts.com/` 200、其他产品路径未被误伤

---

## 六、前端资产版本号纪律（P0，2026-09-17 补充）

### 现状
- 前台 CSS/JS 走 R2 + Cloudflare，缓存头 `immutable, max-age=604800`（**7 天**）
- 版本号只有一处真源：`includes/site-nav.php` 的 **`OF_SHELL_VER`**
  （`includes/site-head.php` 的 `of_head_assets()` 与 shell 脚本都用它）

### 铁律
1. **页面不许硬编码 `?v=`**——一律 `require includes/site-head.php` + `of_head_assets()`
   （已用 `scripts/unify-head-assets.py` 把 18 页硬编码清零，该脚本可复跑）
2. **改了 `tokens.css` / `modules.css` / `fonts.css` / `site-shell.js` / `inject.js`，
   必须 bump `OF_SHELL_VER`**，否则浏览器 7 天不回源，出现「改了看不到」
3. bump 之后照例：`python3 sync-r2.py` → purge CF 对应 URL → 清 `data/cache/*.cache`
4. 后台资产同理，版本常量是 `OF_ADMIN_UI_VER`

### 自查
```bash
grep -rn "tokens.css?v=\|modules.css?v=" --include="*.php" . | grep -v includes/   # 应为 0 行
grep -n "OF_SHELL_VER" includes/site-nav.php                                        # 单一真源
```

### 附：PWA Service Worker（后台专用，低风险）
`assets/pwa/sw.js` 只在 `admin_header()`（后台）注册，scope = `/assets/pwa/`；
SHELL 清单里列了 scope 外的 `tokens/modules/site-shell/inject`（缓存了但拦不到，属冗余）。
若将来要把 SW 扩到前台，需要 `Service-Worker-Allowed` 头 + 版本感知的缓存策略，
否则会变成「改了看不到」的第二个来源。
