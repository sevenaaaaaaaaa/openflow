# OpenFlow 文章重写 · 交接文档（给 Cursor）

> 生成时间：2026-10-20
> 目标：继续把「母版/流水账」文章按统一标准重写成案例驱动的深度稿，并保证线上生效。

---

## 1. 环境与命令

| 项 | 值 |
|---|---|
| 本地仓库 | `/Users/sevenaaaaaaa/OpenFlowDev` |
| 本地预览 | `php -S 127.0.0.1:8080 router-dev.php` |
| 线上 | `https://nownexts.com` |
| 服务器 | `root@172.96.253.73 -p 28766` |
| 私钥 | `.ssh/172.96.253.73_id_ed25519` |
| 站点根 | `/www/wwwroot/nownexts_com` |
| 部署 | `bash bin/deploy.sh --live`（同步代码/静态资源，**不同步 data/**） |

**重要**：本地预览必须用 `router-dev.php` 当 router。用 `index.php` 当 router 会导致 `/article/*` 全部渲染成首页（这是之前「看不到链接 / 排版乱」的原因）。

---

## 2. 数据与「安全编辑」规范（必读，曾出过事故）

### 数据真源
- 文章数据：`data/articles/index.json`（**4727 篇，其中 published 502 篇**，约 36MB）
- **服务器上的这份就是线上真源**；本地和服务器的内容应保持一致。
- 字段：`id / slug / title / content(HTML) / excerpt / seo_desc / status / category / tags / created_at ...`

### ⚠️ 事故教训（务必遵守）
之前用 `mb_strpos`（字符偏移）+ `substr`（字节偏移）混用，截断中文多字节字符 → 非法 UTF-8 → `json_encode` 返回 `false` → `file_put_contents` 写入空串 → **整个 index.json 变 0 字节**。

**安全写法（照抄）**：

```bash
php -d memory_limit=1024M -r '
$f="data/articles/index.json";
$arts=json_decode(file_get_contents($f),true);
if(!is_array($arts)){fwrite(STDERR,"read fail\n");exit(1);}
// ... 修改 $arts ...
$json=json_encode($arts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
if($json===false){fwrite(STDERR,"encode fail: ".json_last_error_msg()."\n");exit(1);}
$chk=json_decode($json,true);
if(!is_array($chk)||count($chk)!==count($arts)){fwrite(STDERR,"verify fail\n");exit(1);}
file_put_contents($f.".tmp",$json); rename($f.".tmp",$f);
'
```

规则：
1. 必须 `-d memory_limit=1024M`（默认 128M 会 OOM）。
2. 插入/切分 HTML 用 `strpos`（字节偏移），**不要**用 `mb_strpos` + `substr`。
3. 写前 `json_encode` 判 `false`、回读校验数量，再 `rename`。
4. 改前先 `cp` 备份（`data/articles/index.json.backup-$(date +%H%M%S)`）。

### 本地 → 线上
改完本地后，必须让服务器也生效，二选一：
- **A（推荐）**：本地改完 `scp data/articles/index.json root@...:/www/wwwroot/nownexts_com/data/articles/index.json`
- **B**：直接在服务器上用同样的安全脚本改 `data/articles/index.json`

> 注意：服务器上还有「图片本地化」的改写（`/assets/articles/_ext/`）。如果本地版本较旧、直接覆盖服务器，会丢掉这些改写。**同步前先确认本地是从服务器拉下来的最新版**。

---

## 3. 当前状态（已核实）

### ✅ 已完成且已上线
1. **3 篇重写**（案例驱动，无母版词）：
   - `ai-automation-content` — 2730 字，2 表，1 图
   - `quickrecorder-macos-screencapture-kit-deep-review-screen-recording` — 2457 字
   - `duix-avatar-deep-review-tools-development-open-source-digital-human-worth-it` — 2527 字
2. **47 篇重写**：已全部在线且无母版词（清单见 `data/list47-status.md`）。唯一残留的 Whisper 篇已修复。
3. **图片本地化**：94 篇文章的 462 个外链图，已下载 415 张到 `assets/articles/_ext/`，并改写 src；失败 47 张已移除对应 figure（避免破图）。
4. **线上图片 404 已修复**：根因是服务器防盗链不允许空 Referer（详见第 7 节），已改配置并验证公网 200。

### ⏳ 待办（按优先级）

**P0-1：重写这 2 篇旧版（母版稿）** —— ✅ 已完成（2026-10-26）
| slug | id | 结果 |
|---|---|---|
| `shopify-ai-workflow-taobao` | `art-69a0a73ccb` | 已重写（约 2840 中文字、3 表 4 图），线上验证 0 母版词 |
| `ai-5`（AI 短剧） | `art-ea10481579` | 已重写（约 3070 中文字、3 表 5 图），线上验证 0 母版词 |

> 按 `docs/handbook/gana-tool-writing-skill.md` v6 推倒重写，`deploy.py --with-data` 上线，线上图片全 200。excerpt/seo_desc 已同步重写。

**P0-2：审计抽出的「母版残渣」文章（优先重写，共 20 篇）——2026-10-26 起逐批处理，进度与最新分级见 `docs/handbook/PRODUCT-DEBT.md`**
```
✅ 已重写上线（2026-10-26/28/29，共 33 篇）：P0-1 两篇 + P0-2 十八篇 + P1 长而母版化七篇 + P1 薄五篇 + stub 重写三篇（cyberverse / otterhub / violin）
⬇️ 下架（status=draft，2026-10-29，共 3 篇）：nano-banana-is-here…（营销邮件骨架）/ video-candy（标题与内容不符）/ deep-printfilm（仓库已删除）
✅ 转轻修清单：ai-tools-design-branding-4（仅 emoji，0 图 0 表）
✅ 剔除（满分样例误报）：seedance-video
⬜ P2 纯格式（4 篇）：claude-code-humanizer / geoflow-ai-content / recorder-online-windows-mac / listsec-owasp-top-2025
⬜ 轻修（1 篇）：ai-tools-design-branding-4
（清完以上 5 项，42 篇清单全清）
```

**P1：偏薄/无现场（补深度）**
```
violin
deep-printfilm
ai-beginner
otterhub-cloudflare-telegram-serverless-tools
airtranslate-macos-audio-translate
brat-generator-charli-xcx
voicss-ai-tools
screenity-open-source-ai-agent
karpathy-ai-agent
clipsketch-ai-deep-review-video
feisou-deep-review
yana-obsidian-deep-review-alternative
libtv-vs-tapnow-2
lumimi-ai-deep-review-free
real-esrgan-stars-ai-image-open-source
```

**P2：格式清理（emoji / 口径腔 / 无案例）**
```
claude-code-humanizer-ai-writing
cyberverse-agent-digital-human
geoflow-ai-content-open-source
recorder-online-windows-mac-mp4-tools-download-free
listsec-owasp-top-2025
nano-banana-is-here-supercharge-your-design-workflow
video-candy
```

完整 83 条问题清单：`data/audit-sample-report.md`。

---

## 4. 写作标准（已写入 skill，必须遵守）

文件：`docs/handbook/gana-tool-writing-skill.md`

核心几条：
1. **禁流水账**：工作流/教程文不许写成「第一步…（痛点/做法/关键细节）→ 第二步…」的步骤清单。要**按真实案例串**：先讲一个具体项目/批次发生了什么问题 → 怎么归因 → 用什么工具解决 → 翻车点。
2. **长文分次写**：一篇写不透就分 2–3 次（先前半，再补后半/深化案例/补图），不要为「一次成型」降质成流水账。
3. **篇幅**：目标 ≥2800 字（工具测评可 2000–3000，观点/工作流 2800–3200）。
4. **结构**：2–4 张图（**必须用站内本地图**，见第 5 节）、1–2 个对比表、具体场景/数字/坑。
5. **禁用**：emoji、`<ol>`、堆 `<ul>`、`<br />-` 列表、字段标签（「它帮我干的活：」）、「先说结论/先给结论」、「测评人背景」、「专栏质量自检」、「给执行者的下一动作」、「量化目标」、「相关阅读：」、「我们团队/我们做」。
6. **三品牌**：`Liblib`（探索，非交付）、`Lovart`（品牌/场景图）、`Moodio`（版本/模卡/审批，内测，官网 moodio.art）在合适环节自然出现。
7. 图注写「图里有什么」，不写空话。

参考已完成的 3 篇（`ai-automation-content` 等）作为风格样板。

---

## 5. 图片规范

- **只用站内本地图**，不要用外链（GitHub/ahhhhfs 等会 429/热链失败）。
- 本地图目录：
  - 已有专题图：`assets/articles/<tool>/xxx.webp`（如 `n8n/n8n.webp`、`screenrec/obs.webp`）
  - 外链下载图：`assets/articles/_ext/<md5>.<ext>`
  - 上传图：`/uploads/articles/...`
- `<figure>` 模板：
```html
<figure><img src="/assets/articles/n8n/n8n.webp" alt="图注" loading="lazy" style="max-width:100%;border-radius:12px;margin:16px 0"><figcaption style="font-size:12px;color:var(--muted);text-align:center">图注：这里写图里有什么</figcaption></figure>
```
- 没有合适图宁可不放，**不要放外链/破图**。

---

## 6. 服务器配置变更记录（2026-10-20）

修复线上图片 404（防盗链不允许空 Referer）：
- Apache `/www/server/panel/vhost/apache/nownexts.com.conf` 两处，各在 `RewriteCond %{HTTP_REFERER} !nownexts.com [NC]` 前加了一行：
  ```apache
  RewriteCond %{HTTP_REFERER} !^$
  ```
  （备份：`nownexts.com.conf.bak-hotlink-*`；`/www/server/apache/bin/httpd -t` → Syntax OK；`/etc/init.d/httpd reload`）
- nginx `/www/server/panel/vhost/nginx/nownexts.com.conf`：
  ```nginx
  valid_referers none blocked server_names nownexts.com www.nownexts.com *.nownexts.com;
  ```
- 验证：`/assets/articles/n8n/n8n.webp`、`/assets/articles/_ext/*.jpg`、`/assets/moodio/*.webp`、`/uploads/articles/*.jpeg` 公网均 200。

> 若图片再次 404：先测「带 Referer」是否 200（`curl -e https://nownexts.com/ ...`）。带 Ref 200、不带 404 = 防盗链问题。

---

## 7. 验证清单（每批改完必做）

1. 本地：`php -S 127.0.0.1:8080 router-dev.php`，打开 `http://127.0.0.1:8080/article/<slug>`，确认标题/正文/图/表正常。
2. 母版词检查：
```bash
curl -s "http://127.0.0.1:8080/article/<slug>" | grep -c "先说结论\|测评人背景\|专栏质量自检"
```
3. 同步服务器后，线上复查（带 cache-buster）：
```bash
curl -s "https://nownexts.com/article/<slug>?v=$(date +%s)" | grep -c "先说结论\|测评人背景"
```
4. 图片抽测：从页面提取 `<img src>`，逐个 `curl -o /dev/null -w "%{http_code}"`，应全 200。

---

## 8. 产出文件索引
- `data/articles/index.json` — 文章数据（真源）
- `data/list47-status.md` — 47 篇线上核对
- `data/audit-sample-report.md` — 83 条抽样问题
- `docs/handbook/gana-tool-writing-skill.md` — 写作标准
- `router-dev.php` — 本地路由（预览用）
- `bin/fix-ext-images.php` — 外链图本地化脚本（服务器端跑）
- `HANDOFF-progress.md` — 较早的精简交接记录
