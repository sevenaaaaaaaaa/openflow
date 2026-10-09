# 品牌命名落地 + 官网文案优化执行计划（2026-10-09）

> **背景**：矩阵与定位三层校准后，品牌林下各产品已定名（笔记工具、输入法、邮箱工具有了正式名字，
> MFlow / WebsFlow 也确定了官方写法），并新增了若干产品。官网需要：
> **① 现有页面文案优化（不动结构）② 新增产品页面 ③ 命名体系贯穿全站**。
>
> **与 `SITE-UPGRADE-PLAN.md` 的关系**：本计划是其中的「阶段 1 文案层」+「新产品页」的细化，
> 不改变该计划的阶段 2-4（SEO/GEO/投放、i18n、数据报表）。
>
> **硬约束不变**：`docs/GTM.md` §2「可以说/不能说」；`docs/BRAND-VOICE.md` CTA 词表；
> 「增量不破版」（`PAGE-STORYLINES.md` §六·五）——**本次只改文案与新增页面，不改既有布局结构**。

---

## 一、命名基线表（从各产品 GitHub 仓库 README 核实 · 2026-10-09 二次更新）

> **品牌体系：林下 Understory**——品牌与产品矩阵之名（地衣本就长在林下）。
> 各产品命名均取自林下生态（鹿蕊/松萝）或呼应「林」（林可兔），官网各页面需体现该体系。

| 产品 | 官方名 | 定位一句话（来源：仓库 README） | 矩阵层级 | 官网现状 |
|---|---|---|---|---|
| 增长操作系统 | **OpenFlow** | AI 时代的网站增长操作系统 | 入口层 | ✅ `/product/openflow` |
| 内容营销工作流 | **MFlow · GEO Agent** | 让品牌在 AI 回答中被引用：信号→生产→门禁→分发→洞察闭环 | 进阶层 | ✅ 页面已有，hero 口径待对齐新 tagline |
| 页面工场 | **WebsFlow 魔块** | 面向投放的落地页工场：出页/上线/看数据/放量当天闭环 | 进阶层 | ⚠️ 页面已有；全站「Webs Flow」写法需统一 |
| 全域数据 | **UserLoop** | 独立全域营销数据中枢 | 进阶层 | ✅ `/product/userloop` |
| 外部情报 | **inFlow** | Insight Flow — 增长情报与策略 OS | 进阶层 | ✅ `/product/inflow` |
| 变现引擎 | **PayFlow** | 收款+订阅+推荐裂变+佣金结算，一行嵌入 | 进阶层 | ✅ `/product/payflow` |
| 课程交付 | **LearnFlow** | 课程与训练营交付引擎 | 进阶层 | ✅ `/product/learnflow` |
| 知识 OS（笔记） | **鹿蕊 Litmus** | AI 时代的知识操作系统：人随手记、AI 帮你建，一切落在磁盘普通文件上（代号 thirdc） | 工作台层（Studio） | ❌ 无页面 |
| 输入法 | **松萝 Liana** | 隐私优先跨平台输入法：纯本地引擎、零服务器、零遥测、P2P 端到端加密 | 工作台层（Studio） | ❌ 无页面 |
| 视频⇄HTML | **ConFlow** | 看完一条视频，得到一堆可上线的内容（文章+幻灯片） | 工作台层（Studio） | ❌ 无页面 |
| 邮件工作台 | **林可兔 LinkTo** | AI 原生邮件工作台：分类降噪/摘要起草/Agent 记忆成长/知识库（本地 SQLite） | 工作台层（Studio） | ❌ 无页面 |
| 广告净化扩展 | **零真 ZeroZen** | 跨浏览器广告与弹窗净化：规则引擎+AI 识别+下载工具箱 | 工作台层（Studio） | ❌ 无页面 |

**✅ 已拍板（2026-10-09）**：
1. **新增页面范围**：5 件全建（鹿蕊 Litmus / 松萝 Liana / ConFlow / 林可兔 LinkTo / 零真 ZeroZen）。
2. **层级归属**：LinkTo、ZeroZen 归工作台层 Studio（与鹿蕊 / 松萝 同层）。
3. **WebsFlow URL**：`/product/webs-flow/` 保持不变，只改展示文案为「WebsFlow」——避免 301 与收录波动。
4. **品牌体系**：林下 Understory——页面文案需体现品牌命名叙事（鹿蕊=真相指示剂、松萝=随宿主生长、林可兔=动若脱兔）。

---

## 二、任务 A：现有页面文案优化（不动结构）

**原则**：只改文案字符串与数据 JSON，不改模板布局、不动区块序列（`storyline-audit` 应保持 ERROR 0）。

### A1. 品牌名全站统一

| # | 改动 | 落点 |
|---|---|---|
| A1.1 | 「Webs Flow」→「WebsFlow」 | 全站字符串替换（PHP 模板 + `builder-pages.json` 数据）；**URL slug 不变**（已拍板） |
| A1.2 | 「七个独立产品」口径更新 | 现网此说法出现在每个产品页的「产品矩阵」tool-grid（×7）、首页 bento、demo-products 等；新口径按命名基线表重写（如「按三层定位展示」或「林下 Understory 产品家族 N 件」，见 A2） |
| A1.3 | 新成员名字进矩阵文案 | ThirdC / InputFlow / ConFlow / Linkto（/ ZeroZen）在「产品矩阵」「与谁互通」区块获得一行介绍 |
| A1.4 | 三层叙事入文案 | 「入口层→进阶层→工作台层」升级叙事（`PRODUCT-MATRIX.md` v2 的口径）体现在首页三条路、矩阵聚合页的引导语，**但不得重排版面** |

### A2. 逐页文案清单（按优先级）

| 优先 | 页面 | 文案动作 |
|---|---|---|
| P0 | `index.php`（首页） | 首屏与「三条路」FAQ 的口径复核；「七件独立产品」bento 补新成员（或改口径）；hero 文案与 GTM §5 CTA 对齐 |
| P0 | `hub-products.php`（矩阵聚合页） | 矩阵表增补新成员行；引导语按三层叙事改写 |
| P0 | `product.php`（OpenFlow 详情页） | 兄弟项目提及处补新名字；确认无「模块」口径残留 |
| P1 | `builder-pages.json` 7 个既有产品页 | 每页「产品矩阵」tool-grid 同步新口径与新成员（数据通道改 JSON，走后台建站面板或脚本） |
| P1 | `hub-capabilities.php` / `capability.php` | 「承载产品」行补 ThirdC（知识管理场景）、Linkto（邮件触达场景）等映射 |
| P1 | `pricing.php` / `about.php` / `docs.php` | 提及矩阵处同步命名与新口径 |
| P2 | `topics.php`、navigation 站、`footer`/`nav` | 出现旧名处逐一替换 |

### A3. 文案护栏检查（每批完成时跑）

- `grep` 清单：无「全家桶当作模块」口径、无旧写法「Webs Flow」残留
- `docs/GTM.md` §2「可以说/不能说」逐条对照新增文案
- CTA 词表符合 `BRAND-VOICE.md`（主 CTA「在 GitHub 上开始」等）
- `scripts/storyline-audit.php` 保持 ERROR 0（证明只动文案未破结构）

---

## 三、任务 B：新增产品页面

**机制**（复用现有数据通道，无需新框架）：
```
新页面 = ① .htaccess / router.php 路由（/product/{slug}）
       + ② builder-pages.json 新条目（25 种区块类型，按 product-标准 故事线序列）
       + ③ 内容素材（从各产品 GitHub README/截图取材）
       + ④ sitemap / hreflang / i18n 词典六语接线
```

**页面序列**（`PAGE-STORYLINES.md` 3.2 product-标准，与 MFlow 等既有页一致）：
首屏 hero → 证明 proof → 痛点 cluster → 旅程 journey → 特性 features → （对比 cmp）→ FAQ → 收口 CTA。

| # | 新页面 | 素材源 | 文案要点 | 备注 |
|---|---|---|---|---|
| B1 | `/product/litmus`（鹿蕊） | thirdc 仓库 README | 人随手记、AI 帮你建，一切落在磁盘普通文件；本地优先·AGPL·无锁定 | Studio·首个页面 |
| B2 | `/product/liana`（松萝） | InputFlow 仓库 README | 隐私优先三承诺：纯本地/零服务器/零遥测；P2P 端到端同步 | 矩阵口径「不是 Flow，归 Studio」 |
| B3 | `/product/conflow` | V2HTML 仓库 README | 贴一条链接，拿回可发布的文章+可放映的幻灯片；在线演示可用 | 产品名 ConFlow（勿写 V2HTML） |
| B4 | `/product/linkto`（林可兔） | linkto 仓库 README | AI 原生邮件工作台：Agent 记忆成长/一键授权/本地加密备份 | 全新产品，先建 |
| B5 | `/product/zerozen`（零真） | zerozen 仓库 README | 41 规则包 7283 条规则本地拦截 + AI 识别 + 下载工具箱 | 三浏览器 · 纯本地 |

**每页验收**：六语切换无漏译键；`storyline-audit` ERROR 0；CTA 单 primary；FAQ 不踩「可以说/不能说」红线；进 `sitemap.php` 与「产品矩阵」互链。

---

## 四、执行顺序与批次（2026-10-09 调序：B 先 A 后）

```
第 1 步  ✅ 命名基线表已拍板 → 同步写进 PRODUCT-MATRIX.md（Linkto/ZeroZen 入 Studio 层）
第 2 步  ✅ 任务 B 新产品页 5 件全部上线（2026-10-09，commit 21dcc2c + 5c087ad：
         /product/linkto 林可兔 · /product/litmus 鹿蕊 · /product/liana 松萝 · /product/conflow · /product/zerozen 零真；
         路由/sitemap/审计归型齐备，storyline-audit 5 页全 [产品页] ✓ ERROR 0；
         数据真源 = scripts/seed-studio-pages.php，data/builder-pages.json 走数据通道）
第 3 步  ✅ 任务 A1 全站品牌名统一（2026-10-09：
         「Webs Flow」→「WebsFlow」全站清零（前台 index / hub-products / product / topics、
         data/nav.json 导航 + 新增「工作台 · Studio」导航列、builder-pages 全量、docs 六文档）；
         「七个独立产品」口径全站改「林下三层」；旧产品页矩阵块统一为 understory_matrix_block；
         PRODUCT-MATRIX.md v3 命名贯穿；storyline-audit ERROR 0）
第 4 步  ◐ 任务 A2 逐页文案优化（2026-10-09：P0 三页 ✅ + P1 ✅——TIPS 承载产品行补林可兔映射、
         about 品牌叙事带出林下 Understory 体系、footer 品牌行升级「林下 Understory · OpenFlow」
         （understory-up 主品牌化第一步）；核查 pricing / docs / hub-capabilities 无矩阵提及无需改；
         剩余：P2 长尾 + footer 词典串（连六语词典一起）在收口批）
第 5 步  收口：sitemap/llms/词典/矩阵互链全量复查 → 交回 SITE-UPGRADE-PLAN 阶段 2
第 6 步  ✅ 主品牌切换（2026-10-09 晚 · 七o 拍板 understory-up）：
         「芭乐派」→「林下 Understory」全站切换——前台 24 页 PHP + assets/src JS + SiteConfig
         （site_name/site_desc/site_keywords；company_name 注册名暂留待确认）+ llms/seo-head +
         builder-pages 15 个 seo_title + nav.json + index.json 文章 + gtm-onepager 一页纸 +
         gtm-deck 幻灯片 + 四课 PPT（Obsidian 源→sync-decks.sh 上线）+ docs 护栏文档；
         保留不动：LICENSE 版权行 / legacy 历史快照 / tests mock / seed 历史脚本 / 匹配词表留双名
```

> **为什么 B 先 A 后（2026-10-09 拍板）**：品牌名统一与「七个产品」口径重写要覆盖全部页面，
> 若先做 A 再上新产品页，新产品页还得按新口径重写一遍矩阵区块——先把 5 页建齐，
> 再对所有页面一次性贯穿，文案只改一遍、矩阵互链一次成型。

---

## 五、i18n 与 SEO 连带项（不在本计划展开，但改动时同步）

- 新产品名与新增文案：进前台词典（当前 82 键 × 6 语）→ 词典扩容
- 新页面：`SeoHead` 元数据、`sitemap.php` 收录、llms.txt 可引用段落
- 「WebsFlow」URL 保持 `/product/webs-flow/` 不变（已拍板），文案层统一即可

---

*基线核验：GitHub 仓库描述抓取于 2026-10-09（`gh repo list`）；官网现状来自 `router.php`、`.htaccess`、`data/builder-pages.json`（10 个 builder 页，其中产品页 6 + 聚合/演示页 4）。*