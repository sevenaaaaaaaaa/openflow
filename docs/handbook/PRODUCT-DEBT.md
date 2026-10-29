# OpenFlow 欠账台账（Product Debt Ledger）

> 建立时间：2026-10-26。**内容轨道 42 篇清单已于 2026-10-29 全清**（33 重写上线 + 3 下架 + 1 剔除 + 5 轻量清理）。
> **2026-10-29 启动全量战役**：42 篇为工作流验证样本；全站 499 篇已发布文章全量体检完成，战役分期执行中（见「内容全量战役」节）。

---

## 一、内容轨道：文章重写（源：HANDOFF-for-cursor.md，2026-10-20）

判定标准：按 `docs/handbook/gana-tool-writing-skill.md` 重写，线上验证无母版词（"先说结论/测评人背景/专栏质量自检"等）。

### 1a. 42 篇验证样本：✅ 全清（2026-10-29，12 批次）

33 重写上线 + 3 下架 + 1 剔除 + 5 轻量清理，全部双端验证 0 母版词。明细见本文件历史版本（git 1b128ac / 11f61e5）与 HANDOFF-for-cursor.md。

### 1b. 内容全量战役（2026-10-29 启动，进行中）

**全量体检结果（499 篇已发布）**：RED 重写级 359 → **Phase 1 后 205**；YELLOW 清理级 146；THIN 29；OK 119。

**Phase 1 · 轻残渣手术：✅ 完成（2026-10-29）**——159 篇（152 批量 + 7 逐篇），全部为文末公式化残渣（延伸阅读段 134、相关阅读段 14、采集站 debris 含转载声明/上一篇下一篇 27、标签行 1），正文零损伤（逐篇头 500 字比对）。线上抽验 0 残渣。清单与备份：`data/articles/index.json.backup-*-phase1*`。

**剩余战役地图**（体检器 `/tmp/audit-all.php`，报告 `/tmp/audit-full.tsv`）：
- [ ] **深测母版 63 篇**：先说结论+测评人背景+配图待补+BLOCK 自检（同 postiz/visioncull 母版），需逐篇重写
- [ ] **选型母版 28 篇**：how-to-choose 系列同母版，需逐篇重写
- [ ] **YELLOW 146 篇**：emoji / `<ol>` / 外站链接 / 我们立场，逐篇清理
- [ ] **母版词无配图待补 19 篇**：先说结论/测评人背景但无配图待补，中度手术
- [ ] **THIN 29 篇**：<1000 字，逐篇定去留
- [ ] **观点长文 8 篇**（1.9 万字级 3 篇）：尾部残渣手术 + 结构梳理
- [ ] **libtv-vs 系列 6 篇**：流水账结构，同 tapnow-2 手术法
- [ ] **配图待补型 11 篇 + 转义HTML采集 6 篇**：补图或评估下架
- 满分样例误报 3 篇已白名单（OpenCut / Veo 3.1 / 可灵即梦 Seedance）

**战役规则**：每批 3–10 篇，每批走全流程（重写/手术 → 本地验证 → deploy.py 上线 → 线上抽验 → 台账划线）；检出误报白名单（满分样例、行文中正常使用"下一篇"等）。

- [x] ~~P0-1：`shopify-ai-workflow-taobao`、`ai-5` 两篇旧版重写~~（2026-10-26 完成并上线，双端验证 0 母版词、签名句在、图全 200）
- **P0-2 逐篇体检（2026-10-26，本地 index.json + 线上抽查互证）**：
  - [x] ~~P0-2 全清（2026-10-26）~~：20 篇处理完毕——18 篇重度全部重写上线（下表），`ai-tools-design-branding-4` 转轻修清单，`seedance-video` 剔除（满分样例误报）
  - [x] ~~`n8n-zapier-deep-review-workflow-automation-open-source`~~（2026-10-26 重写上线：约 2480 中文字、3 图 1 表，双端验证 0 母版词）
  - [x] ~~`ai-tools-roi`~~（2026-10-26 重写上线：约 2760 中文字、4 图 1 表，双端验证 0 母版词）
  - [x] ~~`postiz-deep-review-tools-open-source-worth-it`~~（2026-10-26 重写上线：约 2060 中文字、3 图 1 表，双端验证 0 母版词）
  - [x] ~~`visioncull-pro-ai-deep-review`~~（2026-10-26 重写上线：约 2100 中文字、3 图 1 表，新图 assets/articles/visioncull/ 已部署，双端验证 0 母版词）
  - [x] ~~`ai-tools-site-builder-how-to-choose`~~（2026-10-26 重写上线：约 2425 中文字、3 图 1 表，新图 assets/articles/sitebuilder/ 已部署，双端验证 0 母版词）
  - [x] ~~`ai-design`~~（2026-10-26 重构上线：约 3080 中文字、3 图 1 表，保留旧稿茶饮项目等真实案例、拆除流水账骨架，双端验证 0 母版词）
  - [x] ~~`chosic-deep-review-free`~~（2026-10-26 重写上线：约 2035 中文字、3 图 2 表，Content ID 惊魂主线，新图 assets/articles/chosic/ 已部署，双端验证 0 母版词）
  - [x] ~~`uniclipboard-icloud-deep-review`~~（2026-10-26 重写上线：约 2006 中文字、3 图 1 表，E2EE 边界主线，新图 assets/articles/uniclipboard/ 已部署，双端验证 0 母版词）
  - [x] ~~`airtranslate-mac-deep-review-audio-subtitle`~~（2026-10-26 重写上线：约 2000 中文字、3 图 1 表，"音频从哪来"主线，新图 assets/articles/airtranslate/ 已部署，双端验证 0 母版词）
  - [x] ~~`deep-review-video-browser`~~（2026-10-26 重写上线：约 2000 中文字、3 图 1 表，"音频的哪一段去了哪"主线，新图 assets/articles/voicetext/ 已部署，双端验证 0 母版词）
  - [x] ~~`browserwing-mcp-skill-deep-review-automation-browser`~~（2026-10-26 重写上线：约 2000 中文字、3 图 1 表，MCP/Skill 复用主线，新图 assets/articles/browserwing/ 已部署，双端验证 0 母版词）
  - [x] ~~`boltdiy-deep-review-bolt-diy`~~（2026-10-26 重写上线：约 1940 中文字、3 图 1 表，可点原型对齐会议主线，新图 assets/articles/cursor/ 已部署，双端验证 0 母版词）
  - [x] ~~`voice-pro-gradio-win-nvidia-deep-review-voiceover`~~（2026-10-26 重写上线：约 1915 中文字、3 图 1 表，"停更项目当快照用"主线，新图 assets/articles/voicepro/ 已部署，双端验证 0 母版词）
  - [x] ~~`design-prompts-ui-prompt-deep-review`~~（2026-10-26 重写上线：约 1915 中文字、3 图 1 表，同源可比性主线，新图 assets/articles/designprompts/ 已部署，双端验证 0 母版词）
  - [x] ~~`morphic-ai-deep-review-tools-open-source-search-worth-it`~~（2026-10-26 重写上线：约 1908 中文字、3 图 1 表，"引用链所有权"主线，新图 assets/articles/morphic/ 已部署，双端验证 0 母版词）
  - [x] ~~`shizucallrecorder-root-android-shizuku-deep-review`~~（2026-10-26 重写上线：约 1929 中文字、3 图 1 表，"非确定性的诚实"主线，新图 assets/articles/shizucall/ 已部署，双端验证 0 母版词）
  - [x] ~~`openbidkit-ai-deep-review-paid-alternative`~~（2026-10-26 重写上线：约 1930 中文字、3 图 1 表，"垂直场景的价值在工序"主线，新图 assets/articles/openbidkit/ 已部署，双端验证 0 母版词）
  - [x] ~~`refly-agent-skills-n8n-mcp-deep-review-open-source`~~（2026-10-26 重写上线：约 1826 中文字、3 图 1 表，"Skill 是持久化资产"主线，新图 assets/articles/refly/ 已部署，双端验证 0 母版词）
  - [ ] **轻修（1 篇）**：`ai-tools-design-branding-4`（仅 emoji，2765 字但 0 图 0 表，去 emoji + 补 3–5 图即可，不必整篇重写）
  - [x] ~~`seedance-video`~~ **剔除**——即满分样例 art-7a97f47f10；"先给结论"为样例风格、检测误报，线上标准检查 0 命中。注意：该豁免仅限三篇样例（OpenCut / Veo 3.1 / 可灵即梦 Seedance），其他文章出现"先给结论"仍算违规。
- **P1 重分级（2026-10-26 体检，15 篇实为三类）**：
  - [x] ~~**stub 级（<350 字）：处理完毕（2026-10-29）**——两重写一下架：
    - [x] ~~`violin`~~（2026-10-29 重写上线：约 1790 中文字、3 图 1 表，"装进 Claude Code 的视频翻译 Skill"主线，与 pyVideoTrans 分工，新图 assets/articles/violin/ 已部署，双端验证 0 母版词）
    - [x] ~~`otterhub-cloudflare-telegram-serverless-tools`~~（2026-10-29 重写上线：约 1850 中文字、3 图 1 表，"KV 存元数据、Telegram 存文件"主线，新图 assets/articles/otterhub/ 已部署，双端验证 0 母版词）
    - [x] ~~`deep-printfilm`~~ **下架**（2026-10-29：GitHub 仓库已删除，重写无从谈起；status 改 draft 可恢复）
  - [x] **薄（<1500 字，补深度为主）：全清（2026-10-26/28）**——五篇全部重写上线：
    - [x] ~~`screenity-open-source-ai-agent`~~（2026-10-28 上线：约 1900 中文字、3 图 1 表，"画面即语言"主线，清掉延伸阅读与 Moodio 硬塞段，双端验证 0 母版词）
    - [x] ~~`karpathy-ai-agent`~~（2026-10-28 上线：约 1855 中文字、3 图 1 表，"给 Agent 立规矩"主线，清掉 wiki 链接与表格符号，新图 assets/articles/karpathy-rules/ 已部署，双端验证 0 母版词）
    - [x] ~~`airtranslate-macos-audio-translate`~~（2026-10-28 上线：约 1762 中文字、3 图 1 表，场景速通定位，与深测篇互链分工，双端验证 0 母版词）
    - [x] ~~`brat-generator-charli-xcx`~~（2026-10-28 上线：约 1762 中文字、3 图 1 表，清掉采集站导航/转载声明残渣，商用三问主线，双端验证 0 母版词）
    - [x] ~~`voicss-ai-tools`~~（2026-10-28 上线：约 1813 中文字、3 图 1 表，"频次决定归属"主线，新图 assets/articles/voicss/ 已部署，双端验证 0 母版词）
  - [x] **长但母版化：全清（2026-10-26）**——7 篇全部重写上线：
    - [x] ~~`clipsketch-ai-deep-review-video`~~（约 1954 中文字、3 图 1 表，"人选帧模型拼"主线，新图 assets/articles/clipsketch/ 已部署，双端验证 0 母版词）
    - [x] ~~`feisou-deep-review`~~（约 1905 中文字、3 图 1 表，"把人脉问题降级成检索问题"主线，新图 assets/articles/feisou/ 已部署，双端验证 0 母版词）
    - [x] ~~`lumimi-ai-deep-review-free`~~（约 1887 中文字、3 图 1 表，"四问核图法"主线，新图 assets/articles/pexels/ 已部署，双端验证 0 母版词）
    - [x] ~~`ai-beginner`~~（约 1915 中文字、3 图 1 表，保留删库/空消息真实案例、拆除"第一步/解决什么/踩的坑"流水账，双端验证 0 母版词）
    - [x] ~~`real-esrgan-stars-ai-image-open-source`~~（约 1947 中文字、3 图 2 表，原理深读定位，与 real-esrgan-deep-review 姊妹分工，双端验证 0 母版词）
    - [x] ~~`yana-obsidian-deep-review-alternative`~~（约 1804 中文字、3 图 1 表，"笔记三流派"主线，新图 assets/articles/yana/ 已部署，双端验证 0 母版词）
    - [x] ~~`libtv-vs-tapnow-2`~~（约 2332 中文字、3 图 1 表，保留决策表与三个真实案例、清除表格符号与"先给结论"，双端验证 0 母版词）
- **P2 重分级（2026-10-26 体检）→ 全清（2026-10-29）**：
  - [x] ~~**纯格式清理（4 篇）：全清**~~（2026-10-29 上线：humanizer 约 1624 中文字 / geoflow 约 1688 / recorder-online 约 1546 / listsec 约 1647，各 3 图，清除 emoji/ol/wiki 链接/延伸阅读残渣，双端验证 0 母版词）
  - [x] ~~**实为 stub（3 篇）：处理完毕（2026-10-29）**——一重写两下架：
    - [x] ~~`cyberverse-agent-digital-human`~~（2026-10-29 重写上线：约 1810 中文字、3 图 1 表，"一张照片让角色开口"主线，新图 assets/articles/cyberverse/ 已部署，双端验证 0 母版词）
    - [x] ~~`nano-banana-is-here-supercharge-your-design-workflow`~~ **下架**（营销邮件骨架，零内容）
    - [x] ~~`video-candy`~~ **下架**（标题与内容不符：正文抓的是 YouTube 下载器 README）
  - [x] ~~轻修~~ **`ai-tools-design-branding-4`**（2026-10-29 轻修上线：保留全文优质内容，补 3 图（lovart/midjourney/coolors），清"配图待补"槽位与 Moodio 硬塞尾段，织入 penpot/branding 内链，双端验证 0 母版词）
- 全量 83 条问题清单：`data/audit-sample-report.md`
- **工作量结论（2026-10-29 终版 · 42 篇清单全清）**：42 篇 = **33 重写上线** + 3 下架 + 1 剔除 + 5 轻量清理（4 格式清理 + 1 轻修）；另 P0-1 两篇已完成。**总计 37 篇重写上线，双端验证全部 0 母版词。**执行周期：2026-10-26 至 10-29，共 12 批。
- **遗留提醒**：部分新增图片目录未入 git（assets/articles/ 下历批新增），下次 `deploy/sync.sh` 时会随 git 轨入库；data/ 按规范不入库（服务器为源）。
- 提醒：数据真源在服务器（`data/articles/index.json`），改完必须 `python3 scripts/deploy.py --with-data data/articles/index.json data/articles/index.json` 推送并线上验证（deploy 后属主需为 `www:www`）。

## 二、产品 P0 未完成（源：docs/handbook/PRODUCT-PLAN.md 未勾选项）

- [ ] 知识库语义检索升级：embedding 向量化替换关键词匹配（中文效果提升明显）
- [ ] AI Agent 工具调用：AI 助手直接执行后台操作（改状态/建文章/发通知），需操作确认
- [ ] 线索去重合并：同一邮箱/手机号自动合并多来源线索
- [ ] 漏斗看板：落地页→表单→线索→订单全链路聚合视图（数据已具备）
- [ ] 渠道数据回传：自动化触达后回收率/转化率回写画布节点
- [ ] 调研结果自动出分析报告（survey-agent 雏形 + AI 解读）
- [ ] 资料下载门禁表单：填写线索后才可下载（leads 联动）
- [ ] 直播 RTMP 部署文档（nginx-rtmp / SRS 配置脚本）
- [ ] 直播中一键发放优惠券/福利
- [ ] 点评收益：点评数/优质点评驱动导航站 SEO 与信任背书
- [ ] GEO 话题→自动成文→自动提交一键流完善（GeoSystem 已支持，补闭环）
- [ ] 内容日历联动：未发布文章自动进入排期；文章发布状态自动回填日历
- [ ] 专题页自动生成 AI 导读（知识库 RAG 已就绪）
- [ ] 1v1 咨询与直播转化：课程详情页推荐相关咨询师与直播（底层已建）
- [ ] 权限细化到操作（当前到模块）
- [ ] 课程页/直播回放按权益解锁 VIP 全通（course-player 已支持 order 鉴权）
- [ ] 课程页「VIP 免费看」提示
- [ ] 站内信顶部导航全局未读角标

## 三、有代码、缺证据/缺配置（源：docs/handbook/ROADMAP.md 2026-09-20 校正节）

- [ ] **S3 后台窄屏适配**：88 页无窄屏线索，先做 `admin-ui.css` 全局兜底再逐页（最大可用性欠账）
- [ ] **异地备份上线**：`lib/OffsiteBackup.php` 已有，线上需配置凭据并跑一次 `--test`（未配置前备份与站点同盘）
- [ ] 开发者生态缺口：`CONTRIBUTING.md`、适配者指南对外版、API Key→首个请求引导路径、主导航入口
- [ ] 自我进化可核验：动作前后的**指标回归对比**；无提升要能标记为"无效"
- [ ] 埋点数据积累：`lib/PageUsage.php` 需攒 2–4 周数据，之后才谈"按真实使用裁剪导航"（H2）
- [ ] 四条端到端链路未验证（各补一条可跑路径 + 契约测试）：
  - [ ] CRM 事件 → FlowSystem（🟡 有代码痕迹，端到端未验证）
  - [ ] MA 流程读取 CRM 字段（🟡 同上）
  - [ ] CDP 分群 → CRM 批量操作（⬜ 无证据）
  - [ ] 营销活动 ROI 归因（⬜ 无证据）
- [ ] Skills marketplace API：⬜ 未核实到证据
- [ ] **测试状态污染**：多个测试直写仓库 `data/`，全套跑第二遍会红（design_grid_contract_test）
- [ ] 指标纳管：把更多对外页面的规模数字纳入 `scripts/metrics.php` 单一来源

## 四、生态市场 P1 深化（源：PRODUCT-PLAN.md 十五，四步全部未动）

- [ ] ① developer 角色 + 余额字段（MemberSystem）
- [ ] ② 付费 Skill 下单 API（复用 shop_create_order，goods_type=skill）
- [ ] ③ 作者收益入账 + 提现审批（复用 ambassador 提现流程）
- [ ] ④ 后台作者管理（admin/marketplace.php?tab=developers）
- 并行项：付费 Skill 价格字段与购买门禁；作者主页（/author.php + bio/avatar/website 字段）；市场 AI（选品/质量预检/推荐/周报，入口 lib/MarketAnalyzer.php）

## 五、其他 P1 建议（源：PRODUCT-PLAN.md 各节"下一步"）

- 会员：到期自动降级 + 站内信提醒已接线，缺开通页统一（subscribe / shop 分离）
- 站内信：低频邮件摘要同步
- 存储：comments/messages 从 JSON 迁 SQLite（接口已双写准备）；素材接对象存储
- 风控：社区发帖接入 mod_review_content；投稿审核接 AI 预筛；用户行为风控（IP/设备/频率）
- 主线页：运营主线"待办优先级"提示（如"有 3 条线索未跟进"）；行为事件驱动自动化补触发条件；主线页 AI 周报
- AB 测试自动选显著胜者并回写；舆情周报自动生成；NPS 低分自动进 CRM 挽回
- 咨询：咨询师端入口（登录看自己日程）；确认时间写入 content-calendar
- 直播：弹幕互动运营、数据看板、回放转课程章节
- 点评：用户头像/等级、楼中楼回复、AI 审核预筛
- 后台前端组件化（admin-ui.css + PHP 组件库），与窄屏 S3 合并做

## 六、P2 / P3（不展开，源：ROADMAP.md §P2/P3）

插件付费市场、付费 Skill 订阅制、插件依赖管理+沙箱、官方认证体系；多 Agent 分工、AI 文案节点、预测式转化、全链路归因；多租户 SaaS、Headless API、低代码平台、全自动增长引擎。

## 七、环境与文档卫生（2026-10-26 盘点）

- [ ] 未提交内容：两份 HANDOFF 文档未跟踪；`assets/articles/*` 大量文章图未跟踪（服务器已有、线上 200，但不在 git）；`docs/METRICS.md` 有改动
- [x] ~~HANDOFF-progress.md 待办与正文矛盾（"图片 404"待办 vs 正文已解决）~~（2026-10-26 已修正该文档）
- [ ] `data/articles/index.json` 属主曾有 root 文件（2026-10-26 已修正 index.json 与旧 .bak 为 www:www；下次部署后留意）
- 维护提醒：改动 `tokens.css/modules.css/fonts.css/site-shell.js/inject.js` 必须 bump `OF_SHELL_VER`；部署默认不推 `data/`

---

**使用方式**：做任何一项前，先在本文件定位条目 → 做完 → 划线并注明日期与证据（线上 URL / 测试名 / 配置截图位置）。新欠账随时追加，别让它在三个文档里漂。
