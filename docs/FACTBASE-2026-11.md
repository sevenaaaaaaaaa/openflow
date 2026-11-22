# 林下 Understory · 史实底座（2026-11-23）

> **这份文档的用途**：公司战略、产品规划、案例的唯一事实来源。对外任何一份 pitch / 落地页 / 新闻稿，
> 数字与说法都从这里取；这里没有的，先补证据再用出去。
>
> **它为什么存在**：2026-11 的对外材料里，同一个事实出现过多套数字（后台页数、API 数、
> 产品件数、版本号），有的来自半年前的文档，有的来自 README 徽章。客户只要随手打开仓库就能查，
> 一句对不上就整册失信。这份文档做两件事：**把可核验的事实钉死**，**把过期口径逐条列出来并给出处置**。
>
> 采集时点：2026-11-23（本地工作区 `/Users/seveno/openflow`，分支 `main`）。
> 采集方法：读代码与仓库文件本身，不引用二手转述；指标用 `php scripts/metrics.php`（2026-11-22 输出）。

---

## 〇、三种标记（读法）

| 标记 | 含义 | 对外怎么用 |
|---|---|---|
| ✅ | 已核验：能在本仓库 / 对应产品仓库里指出出处 | 可以直接对外，页脚注明出处 |
| ⚠️ | 口径冲突：两处出处不一致 | 对外只取一个口径并注明；冲突清单见第四节 |
| ○ | 缺口：没有证据（或只有内部口径） | **不对外引用**；要么补证据，要么换说法 |

硬约束（沿用 `docs/GTM.md` 第二节）：禁用「唯一 / 首个 / 最强 / 领先」；禁用任何「提升 X% / 增长 Y 倍」；
没有真实客户数据之前，一个数都不编。比较级只在访客能自己验证时才允许出现。

---

## 一、公司战略

### 1.1 一句话与人群 ✅
> 别人的增长工具，默认你有一支团队。**林下 Understory 做的，是给没有团队的人的那一套。**

- 主品牌：**林下 Understory**；开源底座产品：**OpenFlow**（MIT）。
- 优先人群：一人公司 / 超级个体 / 知识博主与个人品牌 / 三五人的无团队小组织。
- 明确边界：**不是中大型公司成熟市场团队与数据团队的战场**——对他们我们是重复建设。这句话写进对外材料，比含糊更能建立信任。
- 出处：`docs/GTM.md` 第一节（定位屋）、第三节「目标人群」。

### 1.2 三层矩阵：入口层 / 进阶层 / 工作台层 ✅
| 层 | 角色 | 产品 | 关系 |
|---|---|---|---|
| 第 1 层 · 入口 | 门槛最低的 all-in-one，账号真源 | **OpenFlow 开务** | 矩阵的 Hub：签发短票，各产品自持会话 |
| 第 2 层 · 进阶 | 用上之后按深度需求单点升级 | **MFlow 风媒 / WebsFlow 魔块 / UserLoop 触级 / inFlow 洞澜 / PayFlow 芭乐 Pay / LearnFlow 林下学院** | 每件独立可用、单独收费；**不是 OpenFlow 的模块** |
| 第 3 层 · 工作台 | 本地工具，引流面 | **Litmus 鹿蕊 / Liana 松萝 / ConFlow 成章 / LinkTo 林可兔 / ZeroZen 零真** | 长期方向：本地工作台，打通全部 Flow 产品 |

- 命名总表共 **12 件**（含不在本次方案的 Liana / LinkTo / ZeroZen）。出处：`docs/NAMING.md` 第二节。
- 本次客户方案覆盖 **9 件**：OpenFlow、MFlow、WebsFlow、UserLoop、inFlow、PayFlow、LearnFlow、Litmus、ConFlow。
  其余 3 件在材料里只作为「矩阵完整性」被提及，不展开。
- 升级叙事：**从 OpenFlow 开始（门槛最低）→ 在 Flow 里变深（单点更强）→ 用 Studio 工具工作（未来打通全部 Flow）**。
  出处：`docs/PRODUCT-MATRIX.md` 第〇节。

### 1.3 方法论：TIPS ✅
Touch（触达）· Insight（洞察）· Personalize（个性化）· Sell（销售/成交）。
TIPS 不是四个功能区，而是一条回路：成交与丢单的真相回流洞察，洞察调整内容与渠道。
它同时是信息架构、Flow 的能力分类、Agent 的推理边界与工作流质量标准。出处：`docs/VISION.md`。

### 1.4 两种运行范式：Flow 与 Loop ✅
- **Flow（手动挡）**：人设计节点、画布、规则；AI 只当 Copilot；系统按批准流程确定性执行。适合成熟 SOP 与强合规场景。
- **Loop（自动挡）**：人定目标、预算与边界；Agent 读真实业务状态 → 按 TIPS 制定策略 → 调 Skills 与成熟 Flow → 在 Harness/Policy 约束下执行或请求审批 → 用业务结果进下一轮。适合「知道想要什么，但不知道最佳路径」。
- 两者没有高低：成熟 Flow 可成为 Agent 的 Skill；验证过的 Loop 可固化为新 Flow。**方向盘永远在人手里。**
- 出处：`docs/VISION.md`「两种长期并存的运行范式」「两者没有高低之分」。

### 1.5 四个不变量（对外可承诺）✅
1. **核心永久开源（MIT）**，可商用、可二次开发、可私有部署 —— `LICENSE`。
2. **数据 100% 在自己的服务器**：纯 PHP + JSON/SQLite，不要求 MySQL / Redis / Node，无云端回传 —— `docs/GTM.md`「可以说」。
3. **API 互通、可移除**：矩阵产品之间只走公开 API / 事件；拔掉任何一个，其余照常运行 —— `docs/BRAND-VOICE.md` 固定话术。
4. **AI 不可用时核心照常跑**：不配模型只是不自动写稿，不会白屏 —— `docs/VISION.md`「产品首先服从业务」第 1 条。

### 1.6 商业模式（四条，分清「已实现」与「规划」）⚠️
| 路径 | 状态 | 证据 |
|---|---|---|
| 开源自助（自部署） | ✅ 已可用 | 仓库自带 `Dockerfile` / `docker-compose.yml` / 一条命令本地起服务 |
| 托管订阅（SaaS） | ⚠️ 口径存在于 `docs/GTM.md`「SaaS 标价 + 私有化议价」，但**官网暂无产品价格表** | `docs/handbook/PRODUCT-STRATEGY.md` 有商业中心设计，属规划 |
| 私有化 / 企业方案 | ⚠️ 有企业页与「申请方案」流程，**无公开报价与 SLA** | `enterprise.php` |
| 咨询 / 训练营 / 课程 | ✅ 课程与训练营真实在跑（LearnFlow 自用） | 见第三节案例 |

> 对客户的正确说法：**价格按部署方式与规模出，先给范围再给价**——不编一个不存在的价目表。

### 1.7 战略上不做的事（写进材料，减少误会）✅
- 不替代中大型企业的全套 BI / 重型 CRM / 通用电商；单点深度不是我们的战场。
- 不做「又一个等你来喂的系统」；默认前提是系统自己干活、人只做判断。
- 不用绝对化最高级与编造的效果百分比（GTM 硬约束）。

---

## 二、产品规划（9 件）

### 2.0 总表
| 产品 | 层 | 版本（采集日） | 一句话 | 证据 |
|---|---|---|---|---|
| OpenFlow 开务 | 入口 | 2.5.0 | 基于 TIPS 的开源增长操作系统（Flow + Loop） | `VERSION`、`CHANGELOG.md` |
| MFlow 风媒 | 进阶 | 1.2.0 | GEO Agent：信号 → 生产 → 分发 → 回流的内容闭环 | `mflow/VERSION` |
| WebsFlow 魔块 | 进阶 | 4.42.0 ⚠️ | 投放落地页工场：出页 → 上线 → 数据 → 放量 | `websflow/VERSION`（README 徽章仍写 4.40.0） |
| UserLoop 触级 | 进阶 | 0.6.2 ⚠️ | 独立的全域营销数据中枢（CDP + 旅程 + 触达 + 验证） | `userloop/VERSION`（README 徽章仍写 0.6.1） |
| inFlow 洞澜 | 进阶 | 1.0.0 | 外部情报站：采集 → 洞察 → 建议动作 → 14 天验证 | `inflow/README.md`、`RELEASE_NOTES_1.0.md` |
| PayFlow 芭乐 Pay | 进阶 | 1.0.7 | 一行嵌入的收款 / 订阅 / 裂变 / 佣金结算 | `payflow/README.md` |
| LearnFlow 林下学院 | 进阶 | 1.0.0 | 课程与训练营交付引擎（上课 → 进度 → 测验 → 证书） | `learnflow/README.md` |
| Litmus 鹿蕊 | 工作台 | 0.34.1 | AI Native 知识 OS：文件即真相，本地优先 | `gh release list -R sevenaaaaaaaaa/litmus`（2026-11-20） |
| ConFlow 成章 | 工作台 | 引擎仓库 V2HTML ⚠️ | 视频 ⇄ HTML 双向内容引擎（YouTube → 文章/幻灯片） | `V2HTML/README.md`；NAMING：已并入 Litmus「加工」 |

### 2.1 OpenFlow 开务 · 2.5.0
- **定位**：门槛最低的入口层；四力合一，账号真源与矩阵 Hub。
- **今天可交付的规模 ✅**（`php scripts/metrics.php`，2026-11-22）：
  - 197 个后台真实页面 · 121 个 API 端点 · 243 个核心类文件 · 23 个 MCP 工具 · 9 个插件 · 170 个测试文件 · 80 个前台页面。
  - 纯 PHP 8.1+，JSON + SQLite 存储；不装数据库也能跑。
- **已具备的关键能力 ✅**：内容 CMS、CDP 实时画像、营销自动化画布、CRM、商城/会员/订阅/分销、课程与直播、SEO/GEO、插件系统（六类扩展点）、MCP Server、Flow 工作台与 Loop 工作台（ADR-001~005）、自我体检（只给建议，不自动改）。
- **状态**：`VERSION` 2.5.0（2026-09-13 正式版）；`CHANGELOG.md` 的 Unreleased 已包含矩阵产品页、全站多语言、团队协作与多维表格、开源工具适配、Docker 部署等（**线上已含，尚未发版**）。
- **缺口 ○**：没有公开的性能基准；没有独立安全审计；API 层权限覆盖仍是已知薄弱点（`docs/EVOLUTION.md` 自述：端点权限判断不足）。
- **建议下一步（决策项，非事实）**：把 Unreleased 收口成 2.6.0 发版，让对外材料与线上一致。

### 2.2 MFlow 风媒 · 1.2.0
- **定位**：GEO（生成式引擎优化）Agent——让品牌在 AI 回答里被引用，而不只是被搜索引擎收录。
- **可交付 ✅**：搜索控制台真实查询接入；22 源舆情监控；多语言「重写非直译」（10 语言：en/zh/zh-TW/ja/ko/de/fr/pt/ru/it）；生成 → 质检 → 带反馈重写（≤3 轮，失败熔断）；四轨道站外分发（发布前人工闸门）；SEO 周报/月报强制环比；AI 引用感知探测；内容衰减 → 回炉重写。
- **规模口径**：自述「已管 8,200 文档 / 10 语言」——⚠️ 内部计数（`mflow/docs/product.md`「8,200 文档的真实数据飞轮」），无第三方核验，对外只写「8,200+ 篇（内部计数）」或改用「10 语言产线已真实运转」。
- **缺口 ○**：GEO 效果目前没有可对外引用的大样本实验数据（引用率提升幅度）。客户要 ROI，只能给「引用探测」这个可观测口径。

### 2.3 WebsFlow 魔块 · 4.42.0
- **定位**：面向投放的落地页工场——出页、上线、看数据、放量，当天闭环。
- **可交付 ✅**：44 个成品模块、13 套场景模板；三栏编辑器 + 自动保存 + 版本快照；导出单文件 HTML（Meta/OG/JSON-LD 全内嵌）；一键托管到 `/webflow/p/<token>`；页面自带埋点与转化看板；块级人群定向（新/老访客、UTM、设备、时段）；故事线 → A/B → 质检 → 交付包的批量流水线；MCP 入口。
- **边界 ✅**：不做全站建站与 SEO，那一段交给 OpenFlow；账号互通，线索可流转。
- **⚠️ 口径冲突**：README 徽章 4.40.0，`VERSION` 4.42.0——以 `VERSION` 为准。
- **缺口 ○**：没有公开的转化率提升基线（不同行业差异太大，不编）。

### 2.4 UserLoop 触级 · 0.6.2
- **定位**：不租外部 CDP 的全域数据中枢：自收数 → 自建档 → 旅程断点 → AI 决定下一步 → 触达 → 到期验证。
- **可交付 ✅**：`track.js` 一行接入；GA4 / Segment / Shopify / HubSpot 报文直接 ingest（原报文落盘可回放）；find-or-create 建档 + 匿名/实名身份合并 + 多租户隔离；8 阶段旅程引擎与三类断点检测；Loop 引擎（pending → running → verifying → verified/failed）+ 5 个内置模板；Canvas 编排（trigger/condition/action/delay/exit）；邮件 SMTP 真发 / 飞书 / MA Webhook / HubSpot lifecycle；跨渠道频控与发前质检；自然语言分群 + churn/LTV 可解释规则；AI 风险分级（低自动 / 中审批 / 高风险阻断）；A/B 双比例 z 检验；209 项自动化测试。
- **⚠️ 口径冲突**：README 徽章 0.6.1，`VERSION` 0.6.2。
- **缺口 ○**：0.x 阶段，没有 1.0 承诺；升级迁移路径未写。

### 2.5 inFlow 洞澜 · 1.0.0
- **定位**：外部情报站。回答「所以呢」——每条洞察强制携带**推荐动作**，14 天窗口回查效果。
- **可交付 ✅**：12 个数据源插件（Source 接口，源不绑定）；8 个内置模型 + 自定义 JSON DSL；6 个动作适配器（OpenFlow / MFlow / 飞书 / Slack / Webhook 等）；数据洞察 Agent 6 个内置 Skill；数据成熟度评估 + 增长阶段判定 + 策略剧本；REST 33 条路径；MCP 10 工具；Webhook HMAC；800+ 自动化测试。
- **商业证据 ✅（有边界）**：README 明写「v1.0.0 已完成商业交付（首个付费客户签约）」。
  → 对客户可说的版本：「已有首个付费客户，交付内容与合同细节不公开」。**不要写客户名，不要写金额。**
- **缺口 ○**：没有可公开的验证命中率统计（模型按命中率自我调优，但缺少对外样本）。

### 2.6 PayFlow 芭乐 Pay · 1.0.7
- **定位**：把「收钱」这一步做成独立小产品：一行 `<script>` 嵌进任何页面。
- **可交付 ✅**：一次性付款与订阅；到期自动生成续费单 + 扣款失败按 1/3/5 天重试 + 宽限期降级；优惠券与兑换券；推荐链接 → 归因 → 佣金冻结/解冻 → 提现审批 + `/partner` 业绩页；虚拟货自动发货（文件签名限时下载 / License / 发票）；四种收款通道（人工确认 / 支付宝 RSA2 / 微信 APIv3 / 加密货币）；`/api/v1` + Webhook（HMAC 签名 + 重试）+ 按 Key 限流；137 项自检。
- **生产状态 ✅**：README 明写「生产已上线（2026-09-17 起，当前 1.0.7）」，并与 LearnFlow 完成联调（买课即入学、退款即撤权）。
- **缺口 ○**：没有公开的 GMV / 续费率数字。

### 2.7 LearnFlow 林下学院 · 1.0.0
- **定位**：讲师与训练营主理人的课程交付单件。收款归 PayFlow，上课归它。
- **可交付 ✅**：写课（富文本 / Markdown 导入 / AI 内容工厂）；章节课时（图文 / 视频 / HLS / 直播位）+ 定时发布；断点续播、字幕、自动连播、签名 URL 加防盗水印；章节测验 + 结业测验；可校验防伪的结业证书；训练营节奏（开营 / 每日任务 / 作业双点评 / 打卡 / 圈子 / 风险学员名单）；优惠券 / 推荐码 / 多级分销 / 会员等级；营收与漏斗看板 + CSV 导出；API Key 三档授权，REST + MCP 共 42 个工具；每日自我体检 → 提案 → 护栏内执行；154 项领域回归测试；PHP 8.3 + JSON 文件，零框架零 composer 依赖。
- **案例 ✅**：R.B.E 训练营就是自家真实开营案例（`learnflow/README.md` 自述）。
- **缺口 ○**：没有外部讲师客户的公开案例授权。

### 2.8 Litmus 鹿蕊 · 0.34.1
- **定位**：AI Native 知识 OS。「文件即真相，画布即界面，agent 即同事」。
- **可交付 ✅**：Rust 单二进制；五模式（Flow 画布 / Feynman 学习与展示 / Memo 随手记 / Agent-L 对话式知识库 / Studio 发布分发）是同一套内核的不同界面配置；本地优先，数据是磁盘上的 Markdown / HTML / 附件，任何编辑器可开、任何 agent 可读；HTTP API + MCP 双向；插件生态；50 款设计史风格实时换肤；macOS / Web / CLI / Android / iOS。
- **许可 ⚠️**：AGPL-3.0（与 OpenFlow 的 MIT 不同——对外必须分开说，不能笼统说「全矩阵开源」）。
- **发布节奏 ✅**：v0.33.1（11-13）→ v0.34.0（11-14）→ v0.34.1（11-20），仍在 0.x。
- **缺口 ○**：0.x 阶段，插件准入与 ABI 稳定性仍在演进（`docs/spec/os.md` 有宪法但未承诺冻结）。

### 2.9 ConFlow 成章 · 引擎 V2HTML
- **定位**：视频 ⇄ HTML 双向内容引擎。贴一条 YouTube 链接 → 20 分钟拿回一篇按论证结构重写的文章 + 一套可放映的幻灯片；反方向 `v2video` 把文章变成分镜提示词包或 HTML 动画。
- **可交付 ✅**：四种文体各有写作法（教程 / 科普 / 口播评论 / 评测访谈）；Python 3.12 + FastAPI；30 种主题浏览器直接放映、按 P 导出 PDF（⚠️ 徽章写 32，正文写 30）；文章可自动推进 OpenFlow 内容库草稿；真实产物可查（`output/aircAruvnKk/slides.html` 等 5 个已完成任务目录）。
- **归属 ✅**：GitHub 描述与 NAMING 均写明「引擎已并入 Litmus 的『加工』」；产品页保留但标注引擎归属。
- **⚠️ 口径冲突**：主题数 30（README 正文）vs 32（徽章）；引擎仓库不在 `Projects/` 下而在 `openflow-migrate/wwwroot/V2HTML`（迁移备份），本地开发位置需确认。

### 2.10 路线图：只写有依据的
| 时段 | 有依据的（✅/⚠️） | 缺依据的（○，需拍板） |
|---|---|---|
| 现在（已上线） | OpenFlow 2.5.0 + Unreleased 能力；MFlow 1.2.0；WebsFlow 4.42.0；UserLoop 0.6.2；inFlow 1.0.0；PayFlow 1.0.7；LearnFlow 1.0.0；Litmus 0.34.1；ConFlow 引擎可用 | —— |
| 90 天内（有文档依据） | OpenFlow 发版收口（Unreleased → 2.6）；Litmus 继续 0.x 周更 | 各产品对外**统一的价格表**；统一登录 / 计费口径；inFlow 验证命中率对外样本 |
| 更远（仅方向） | MFlow 内核开源 + demo 品牌包（`mflow/docs/product.md` 长期路线） | UserLoop 1.0；LearnFlow 外部讲师案例；ConFlow 与 Litmus 的入口合并节奏 |

> 这一节的写法本身是纪律：**没有文档依据的「下一步」，一律进右列**，不写进客户材料当承诺。

---

## 三、案例与交付证据

### 3.1 可核验的自用案例（客户材料主力）
| # | 案例 | 事实 | 可被客户核验的入口 |
|---|---|---|---|
| 1 | **主站 nownexts.com** | OpenFlow 2.5.0 线上运行；197 后台页 / 121 API / 23 MCP | 直接打开站点；仓库 `VERSION` + `scripts/metrics.php` |
| 2 | **PayFlow 生产收款** | 2026-09-17 起生产；1.0.7；与 LearnFlow 买课即入学联调 | `nownexts.com/payflow` 在线后台 |
| 3 | **LearnFlow 训练营** | R.B.E 训练营真实开营；课程 → 进度 → 测验 → 证书全链路 | `learnflow` 仓库 + 线上课程 |
| 4 | **ConFlow 真实产物** | 5 个已完成任务目录，文章 + 幻灯片无手工排版 | `nownexts.com/VTH/output/aircAruvnKk/slides.html` |
| 5 | **WebsFlow 在线 Demo** | 仅预览；44 模块 / 13 模板 / 单文件导出 | `nownexts.com/webflow/` |
| 6 | **MFlow 内容产线** | 10 语言真实运转；8,200+ 篇（⚠️ 内部计数） | `mflow` 仓库 `docs/` 与截图 |

### 3.2 首个付费客户 ✅（有边界）
- inFlow v1.0.0 已完成商业交付、首个付费客户签约（`inflow/README.md`）。
- **不可引用**：客户名、合同金额、交付周期明细 —— 均未获授权，也无公开证据。

### 3.3 诚实页：我们现在**没有**的东西（客户材料里主动讲）
- ○ 没有客户 logo 墙（未取得授权，也不 PS 一个）。
- ○ 没有「提升 X% / 增长 Y 倍」的效果数字（没有对照组与授权数据）。
- ○ 没有独立第三方安全审计、等保或 SLA 承诺文件。
- ○ 没有统一的对外价目表（官网只有课程价格在 `data/settings.json`）。
- ○ 没有中大型客户的组织级案例（现有证据全部是自用 + 首个付费）。

> 这一页在 pitch 里的作用不是示弱，而是把「可核验」变成差异点：客户可以拿这份清单去问任何一家 SaaS，多数答不上来。

### 3.4 对外可以说 / 不能说（沿用 GTM）
- 可以说：MIT 开源、可自托管、数据在自己服务器、AI 未配置也能跑、真实连接器清单（飞书/企微/WhatsApp/Notion/GitHub/SMTP/虎皮椒/GSC）、具体数字（197 / 121 / 23 / 243 / 170 / 9）。
- 不能说：Platform Intelligence、唯一 / 首个 / 最强、自动帮你修、每 6 小时自动爬、「400+ 连接器」、钉钉 / Slack / Salesforce 内置连接器、任何未给出来源的效果百分比。

---

## 四、旧口径纠偏（这份文档存在的主要理由）

| # | 旧说法 | 出处 | 现在的事实 | 处置 |
|---|---|---|---|---|
| 1 | 「七个独立产品 / 矩阵七件套」 | `scripts/seed-product-matrix-v3.php`、产品页矩阵块 | 命名总表 **12 件**；本次对外 **9 件** | 新页一律用「三层 · 十二件」，客户材料写「本次九件」 |
| 2 | 「芭乐派」 | 旧 README / 旧页面 | 主品牌 **林下 Understory**（2026-10 全站切换） | 已作废，禁止再出现 |
| 3 | 后台 178 页 / API 88 个 | `docs/EVOLUTION.md` 第一章 | **197 / 121**（metrics 2026-11-22） | 以 metrics 为准；旧文档留着但当历史读 |
| 4 | 18 个 MCP 工具 | `docs/GTM.md` 第一节筹码表 | **23 个**（`lib/McpTools.php` + metrics） | 对外统一 23 |
| 5 | WebsFlow 4.40.0 | `websflow/README.md` 徽章 | `VERSION` **4.42.0** | 以 VERSION 为准；让仓库改徽章 |
| 6 | UserLoop 0.6.1 | `userloop/README.md` 徽章 | `VERSION` **0.6.2** | 同上 |
| 7 | ConFlow 32 主题 | `V2HTML/README.md` 徽章 | 正文写 **30 种** | 对外写「30 种主题」，冲突先进待办 |
| 8 | 「全矩阵开源」 | 口头/近似表述 | OpenFlow 系 **MIT**；Litmus 是 **AGPL-3.0** | 必须分开说，避免 license 误导 |
| 9 | 26 城 39 个 OPC 社区 | `docs/GTM-COPY.md` 标注「硬数据」 | **无外部出处可查** | ○ 不对外引用（客户会问来源） |
| 10 | 「用户不部署也能活，但部署了今天就有团队能力」 | GTM 定位 | 成立，但需补「能力清单」 | 保留说法，配 2.1 的真实数字 |

---

## 五、缺口清单（需要人拍板 / 补证据）

1. **客户素材**：至少 1 个可署名的客户案例（含授权书）；否则 pitch 里只放自用 + 首个付费（匿名）。
2. **效果口径**：任一产品的可对外指标（如 inFlow 验证命中率、MFlow AI 引用率变化），需真实样本与统计方法。
3. **价格与合同**：产品价格表、私有化报价区间、SLA 条款 —— 目前都没有公开版本。
4. **安全合规**：独立渗透报告 / 等保 / 数据处理协议模板。
5. **ConFlow 归属执行**：产品页「引擎已并入 Litmus」的文案是否已全站替换（NAMING 第五节记为待办）。
6. **仓库一致性**：WebsFlow / UserLoop README 徽章、ConFlow 主题数、`docs/EVOLUTION.md` 旧数字需要各仓库同步修。
7. **统一入口**：9 件产品是否要做统一登录 / 统一计费 / 统一导航（架构上支持，产品上未定）。

---

## 六、出处索引

**本仓库（openflow）**
- `VERSION` · `CHANGELOG.md` · `LICENSE`
- `docs/VISION.md`（TIPS、Flow/Loop、产品四把尺）
- `docs/GTM.md`（定位屋、事实边界、转化路径）
- `docs/BRAND-VOICE.md`（声音三原则、立场词、矩阵话术）
- `docs/PRODUCT-MATRIX.md`（三层定位、矩阵总览、升级路径）
- `docs/NAMING.md`（12 件命名唯一来源、ConFlow 归属）
- `docs/EVOLUTION.md`（多租户与规模演进；注意旧数字）
- `docs/PRODUCT-MAP.md`（功能地图、演化路径、死功能审计）
- `docs/handbook/PRODUCT-STRATEGY.md` / `PRODUCT-PLAN.md`（商业模式与功能升级点：属规划）
- `scripts/metrics.php`（197 / 121 / 23 / 243 / 170 / 9 / 80，2026-11-22）
- `deck.php` + `lib/DeckThemes.php` + `gtm-deck.html`（站内幻灯片放映与编辑语言）

**产品仓库（本地 `/Users/seveno/Projects/`）**
- `mflow`（VERSION 1.2.0；README；docs/product.md 8,200 文档口径）
- `websflow`（VERSION 4.42.0；README 44 模块 / 13 模板）
- `userloop`（VERSION 0.6.2；README 209 测试 / 8 阶段 / 5 模板）
- `inflow`（README v1.0.0；12 源 / 8 模型 / 6 适配器 / 33 REST / 10 MCP / 800+ 测试）
- `payflow`（README 1.0.7；2026-09-17 生产；137 自检）
- `learnflow`（README 1.0.0；154 回归 / 42 工具；R.B.E 训练营）
- `litmus`（README；`gh release list` v0.34.1 / 2026-11-20；AGPL-3.0）
- `openflow-migrate/wwwroot/V2HTML`（ConFlow 引擎 README：30 主题 / 4 文体 / 真实 output）

**采集命令**
```bash
php scripts/metrics.php            # 本仓库指标
gh repo list sevenaaaaaaaaa --limit 40 --json name,description,pushedAt
gh release list -R sevenaaaaaaaaa/litmus --limit 3
for d in mflow inflow websflow userloop learnflow payflow litmus; do cat ~/Projects/$d/VERSION; done
```
