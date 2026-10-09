# OpenFlow 站点整体升级执行计划（2026-10-09）

> **背景**：矩阵与定位已完成三层校准（`docs/PRODUCT-MATRIX.md` v2 · 2026-09-27：
> 入口层 OpenFlow / 进阶层 Flow 家族 / 工作台层 Studio 套件），官网需要整体提升，
> 并为 **SEO / GEO / 投放 / i18n / 数据报表** 五条线做好建设准备。
>
> **硬约束**：`docs/GTM.md` §2「可以说 / 不能说」优先于本文所有表述；
> `docs/PAGE-STORYLINES.md` 是页面骨架 SSOT；`docs/BRAND-VOICE.md` 约束 CTA 词表。
>
> **基线**：`main` @ `1048c00`，与 `origin/main` 同步，工作区干净（2026-10-09 核验）。

---

## 〇、总原则

1. **页面结构先行**：首页/页面/结构是 SEO、GEO、i18n、埋点的共同载体，先定骨架，其余四线一次接到位，避免返工。
2. **增量不破版**（`PAGE-STORYLINES.md` §六·五）：改文案不改布局语义；新增区块先进 `BlockRegistry` 再上页面。
3. **每步过门禁**：`storyline-audit`（36 营销页 ERROR 0）／`design-guard`／内容契约／CI 全绿才算完成。
4. **文案改动先改 SSOT**：先 `GTM.md` / `PAGE-STORYLINES.md`，再改页面；核实不了的说法不上线。

### 现有资产盘点（升级的起点，不是从零开始）

| 线 | 已有资产 | 主要缺口 |
|---|---|---|
| 定位/叙事 | PRODUCT-MATRIX v2、POSITIONING、GTM 母版、USAGE-GUIDE；首页已换新口径（「七件独立产品」「三条路」） | 逐页复核三层口径残留；导航信息架构未按三层重排 |
| SEO | `SeoHead.php`、`sitemap.php`（含 hreflang）、`robots.php`、admin seo-center/console/batch/tools/image-seo | 新页元数据/结构化数据补齐；内链体系；归档页收录 |
| GEO | `llms.php`（动态 llms.txt）、`admin/geo.php` | llms 内容质量（问答式、可引用）；AI 检索实测验证流程缺失 |
| 投放 | `lp/solo-growth.html`、`utm-builder.php`、`ad-campaigns.php`、`data/ad-campaigns.json` | 落地页矩阵未与路径 A/B/C 收口对齐；UTM 规范未成文 |
| i18n | 六语路由（`en/ja/zh-CN/zh-TW/ru/fr`）+ 前台词典 82 键×6 语 + `ContentI18n.php`（内容级多语 + AI 初译）+ hreflang | 新页面文案入词典；内容翻译覆盖率低；页面级 hreflang 补全 |
| 数据 | TRACKING-PLAN v1.0（三层事件模型）、`analytics.php`、`reports.php`、`ecom-reports.php`、`path-analysis.php`、METRICS 口径 SSOT | 埋点落地度未审计；漏斗/归因/留存报表未成体系；周报自动化缺失 |

---

## 一、阶段 1 · 首页与页面结构整体提升（P0 · 其余阶段的载体）

**目标**：三层定位口径贯穿全站；每个访客 10 秒内知道「这是什么、给谁用、下一步做什么」。

| # | 任务 | 验收 |
|---|---|---|
| 1.1 | **首页复核**：首屏 / 四力 / 三条路 / FAQ 区块对照 PRODUCT-MATRIX v2 与 GTM §5 三条转化路径（A 开发者→GitHub，B 托管试用，C 企业方案），CTA 词表按 GTM 统一（主 CTA=「在 GitHub 上开始」） | 首屏三问可答；`grep` 无「全家桶 / 某产品的模块」口径残留 |
| 1.2 | **逐页口径复核**（36 营销页）：把 Flow 家族 / Studio 套件写成「OpenFlow 的模块」的残留清零（文档层 commit `241e798` 已做，页面层待查） | 抽查 product/pricing/capability/demo 页通过；`storyline-audit` ERROR 0 保持 |
| 1.3 | **导航信息架构重排**：`site-nav.php` / `site-footer.php` 按三层定位组织（入口层 OpenFlow 能力 → 进阶层 Flow 家族 → 工作台层 Studio） | 导航分组与 PRODUCT-MATRIX 一一对应；footer 同步 |
| 1.4 | **收口对齐**：每页收口 CTA 指向该页所属转化路径的下一步，不都堆到首页 | GTM §5 路径 A/B/C 各页落点正确 |
| 1.5 | **FRONTEND-GAPS 文档更新**：topics/category/downloads/events 等缺口页已建，2026-08 的盘点过期，重盘一遍剩余缺口 | 文档反映现状，剩余缺口进 BACKLOG |

**门禁**：CI 全绿 + `scripts/storyline-audit.php` ERROR 0 + `scripts/design-guard.php` 0 风险。

---

## 二、阶段 2 · SEO / GEO / 投放就绪化（P0–P1 · 随阶段 1 逐页推进）

**目标**：优化后的每个页面同时是 SEO 资产、GEO 可引用源、投放可收口落地页。

| # | 任务 | 验收 |
|---|---|---|
| 2.1 | **元数据补齐**：新/改页面逐页过 `SeoHead`（title/desc/OG/结构化数据）；`admin/seo-console.php` 全站体检 | seo-console 无红项；OG 卡片渲染正常 |
| 2.2 | **sitemap 与收录面**：`sitemap.php` 覆盖全部公开路由（含新增归档/专题页）；`robots.php` 与六语前缀一致 | sitemap 条目数 = 公开路由数；Search Console 无新增 404 |
| 2.3 | **内链体系**：内容枢纽（articles/topics/category）→ 产品/能力页互链，每篇内容至少一个「下一步」链接 | 抽查 10 篇文章均含转化路径链接 |
| 2.4 | **GEO 就绪**：`llms.php` 输出改为「问答式、可引用」结构（每能力一问一答 + 事实出处）；关键页加 FAQ Schema；`admin/geo.php` 增加六大 AI 引擎（ChatGPT/Perplexity/Gemini/Claude/Copilot/文心）实测记录 | 用统一提问清单在 ≥2 个 AI 引擎验证可被引用 |
| 2.5 | **投放物料收口**：`lp/solo-growth.html` 表单（slug `solo-growth-handbook`）确认已发布；`gtm-onepager.html` 页脚三占位符填完；路径 A/B/C 各配一个投放落地页（信息流 H5 / 托管试用 / 企业申请） | 三条路径均有可投页面且表单进 leads 通道 |
| 2.6 | **UTM 规范成文**：渠道/媒介/活动命名规范一页纸（utm-builder 为执行工具），投放链接必须带 UTM | 规范入 `docs/handbook/TRACKING-PLAN.md` 附录；ad-campaigns 链接抽查通过 |

---

## 三、阶段 3 · i18n 收尾（P1 · 与阶段 2 并行铺开）

**目标**：六语站点「上线即完整」——界面全译、内容可译、SEO 全 hreflang。

| # | 任务 | 验收 |
|---|---|---|
| 3.1 | **词典扩容**：前台词典从 82 键扩到覆盖阶段 1 全部页面文案（含导航重排后的新分组名） | 切换六语抽查 10 页无中文漏出 |
| 3.2 | **页面级 hreflang 补全**：`SeoHead` 为每个前台页输出六语 + x-default alternates（文章已走 `ContentI18n::hreflang`，静态页待接） | hreflang 校验器 0 错误；与 sitemap alternates 一致 |
| 3.3 | **内容翻译流水线**：AI 初译（`ContentI18n` 已有）+ 人工审校流程文档化；P0 内容（首页叙事、定价、产品页）先行 | P0 页面六语就绪；译文状态在 admin 列表角标可见 |
| 3.4 | **语言切换器 UX**：`site-shell` 切换器与重排后的导航一致；跨语言跳转保持当前页而非回首页 | 六语互相跳转 10 页全通 |
| 3.5 | **六语 llms/GEO**：`llms.php` 按语言输出对应语料 | `/en/llms.txt` 等六语可用 |

---

## 四、阶段 4 · 数据报表体系化（P1–P2 · 在埋点稳定后收口）

**目标**：从「有埋点」到「能回答问题」——三条转化路径的转化率可按天/按渠道读出。

| # | 任务 | 验收 |
|---|---|---|
| 4.1 | **埋点落地度审计**：对照 TRACKING-PLAN v1.0 三层事件模型（自动/预置/自定义），盘点缺事件、缺公共属性（utm/locale）、user 属性未回流 CDP 的项 | 审计报告入 `docs/AUDIT-09-TRACKING.md`（或并入 AUDIT-02 增补） |
| 4.2 | **转化漏斗报表**：路径 A（首页→GitHub→激活）、路径 B（首页/定价→试用→订阅）、路径 C（企业页→申请→成交）三条漏斗进 `admin/reports.php` | 每条漏斗有逐步转化率 + 环比 |
| 4.3 | **渠道归因**：UTM → session → lead → order 全链路串联；`path-analysis.php` 接入漏斗上下文 | 任一订单可回溯到投放渠道/活动 |
| 4.4 | **留存与内容效果**：内容页（articles/topics）阅读 → 订阅/留资贡献；会员留存曲线 | 内容 ROI 一页可见 |
| 4.5 | **报表自动化**：`report-subscribe.php` 周报（三条路径核心指标）cron 化；指标口径变更只改 `METRICS.md` | 每周一自动出上周报表；口径有据可查 |

---

## 五、依赖关系与排期

```
阶段 1 首页/结构 ──→ 阶段 2 SEO/GEO/投放（逐页并行推进）
        │
        └──────→ 阶段 3 i18n（词典扩容依赖新文案定稿）
                      │
                      └──→ 阶段 4 数据报表（漏斗定义依赖最终转化路径收口）
```

- 阶段 2.4/2.6、阶段 3.2 可与阶段 1 并行（不动文案只接机制）。
- 阶段 4.1（埋点审计）应尽早做——阶段 1 改版期间就该让新埋点上线，改完才有对照数据。

## 六、风险与护栏

| 风险 | 护栏 |
|---|---|
| 口径回潮（「全家桶/模块」说法重现） | GTM §2 硬约束 + 上线前 `grep` 清单 + 文案先改 SSOT |
| 静态物料乱码/noindex 事故复发 | GTM §七 两条硬约束（charset 前 1KB / 销售物料 noindex）纳入检查表 |
| 六语一致性破版 | 词典键缺失即 CI 检查（模板接线已 57 处，新增接线同步入词典） |
| 改版期间数据断层 | 阶段 4.1 埋点审计前置，改版前后事件口径不变 |
| 多人/多线并行互相踩 | 每阶段一分支，合并前过全部门禁；`assets/**` 改动必须 bump `OF_SHELL_VER` |

## 七、开工前检查（已完成 ✅）

- [x] `main` 与 `origin/main` 同步（HEAD `1048c00`），工作区干净
- [x] 无 stash、无未推送提交；旧分支 `backup/*`、`bundle-copy` 为历史快照，不动
- [x] i18n 六语路由白名单在 `.htaccess` 生效
- [x] 基线门禁状态记录：`storyline-audit` ERROR 0（2026-09-18 复核）