# 定位方向 → 可执行清单（2026-09-20）

用户给的方向：**更易用 · 更敏捷 · 更自我进化 · 更 AI agent · 更 AI native · 更开放 ·
更可定制化 · 更兼容 · 更面向生态伙伴 / 开发者 / AI 爱好者 / OPC**。

这份文档把每条方向拆成：**现有可核验证据 → 缺口 → 下一步动作**。

> 硬约束：`docs/GTM.md` §2 的「可以说 / 不能说」优先于本文所有表述。
> 比较级（"更 X"）只有在**访客能自己验证**时才允许出现在对外页面——
> 所以每条方向都必须先有"证据栏"，没有证据的先补证据，而不是先改文案。

---

## 1. 更易用

**证据（今天就有）**
- 226 个后台页，统一外壳与设计令牌（`assets/tokens.css` + `admin-ui.css`）
- 首次进入有角色选择（新手 / 运营 / 开发者 / 企业），选择结果会改首页与 CTA 文案
- 全局命令面板 ⌘K；`setup-wizard.php` 首次配置引导
- `Teams+` 把团队协作收成一个入口（顶栏右侧图标区）
- 统一对话框层 `OFDialog`（ESC / 遮罩 / 焦点 / 滚动锁），后台弹窗行为一致
- 一键本地预览：`scripts/preview-admin.sh`（绕开登录验证码看真实渲染）

**缺口**
- 没有「5 分钟上手」路径：新用户看到 226 个入口不知道该点哪
- 列表页空态不统一：部分页面空的时候只有一句"暂无"
- 后台移动端未审计（`scripts/audit-devices.py` 覆盖 15 个前台页 × 7 宽度，不含后台）

**下一步**
1. 首登 3 步向导：选角色 → 接一个数据源（或跳过）→ 发第一篇内容
2. 后台空态统一：每个列表页给"下一步做什么"+ 一个直达按钮
3. 后台移动端断点审计，纳入 CI（复用 audit-devices 的框架）

---

## 2. 更敏捷

**证据**
- CI 14 道门禁：typecheck / TS 单测 / PHPStan（基线内 0）/ ruff / strict_types 棘轮 /
  148 个 PHP 测试 / 故事线审计 / 设计护栏 / 内容契约 / 栅格契约 / design_contract / 设备审计
- `scripts/deploy.py`：逐文件 md5 断言、备份与回滚、`--from-git`、部署后 data 归属体检
- 契约测试能拦住"改了这里坏了那里"（内容契约 153 项、栅格契约 29 项、设计护栏 0 风险）

**缺口**
- 回滚只有 CLI，没有后台入口
- 缺少"改动影响面"提示：改 `assets/` 必须 bump 版本号仍靠人记
- 没有部署后冒烟（关键路由 200 + 关键元素存在）

**下一步**
1. 部署后自动冒烟：8 条关键路由 + 关键元素断言，失败即报警
2. 改 `assets/**` 自动 bump `OF_SHELL_VER` / `OF_ADMIN_UI_VER`
3. 后台「部署与回滚」页（列出最近 manifest、一键回滚、显示上次冒烟结果）

---

## 3. 更自我进化

**证据**
- 6 个相关模块：`SelfEvolve` / `GrowthEngine` / `GrowthLearning` / `DecisionTrace` /
  `EvidenceProjection` / `LoopRuntime`
- `AutonomyGuard`：受控自治（写操作要过闸门）
- cron 里定期跑：系统体检、风控扫描、自我进化扫描、增长驱动
- 决策轨迹回流：今日主线完成/稍后 → `dtrace_record` → 影响后续排序

**缺口**
- **对外不可核验**：用户看不到"它为自己改了什么、结果如何"
- 自我改进缺少前后对比度量（改了但说不清效果）

**下一步**
1. 后台「自我进化日志」：每次巡检 → 发现 → 动作 → 结果，可回溯、可关闭
2. 关键指标回归：动作前后对比（转化率 / 逾期数 / 内容产出），无提升标记为"无效"
3. 对外只讲机制与可验证结果，不讲"自动变强"（护栏）

---

## 4. 更 AI agent / 5. 更 AI native

**证据**
- **23 个 MCP 工具**：`articles_list` / `articles_create` / `articles_publish` / `members_list` /
  `leads_count` / `orders_revenue` / `search` …（`mcp-server.php`）
- Agent 运行层：`AgentRuntime` / `SiteAgent` / `AgentPost` / `AgentPost` 定时产出
- `AiCenter`：多模型接入（DeepSeek 已验证可用），会话与预算控制（`AiBudget`）
- 受控 Loop：`LoopRuntime` + ADR-004（只读运行时）+ ADR-003（动作网关）
- 人审闭环：`ActionApprovalView` / `admin/action-approvals.php`
- AI 造插件：适配流水线（`AdapterIntake` → `Forge` → `Verify` → 人审）已跑通首个上架插件
- 看板娘：live2d 多模型（自托管，走 R2）

**缺口**
- MCP 工具**没有面向外部的清单与示例**，开发者不知道能调什么、怎么接
- Agent 的动作边界与审批流程只在内部 ADR 里，对外没有说明
- 没有"给 AI 用的一句话接入"（如 Claude/Cursor 的 MCP 配置片段）

**下一步**
1. 公开「MCP 工具清单 + 3 个真实调用示例 + 配置片段」（新页面 `/mcp`）
2. Agent 能力边界页：哪些自动做、哪些必须人审、哪些只读（把 ADR-003/004 翻译成人话）
3. 开发者一键生成 API Key + 首个请求示例（打通 `admin/api-keys.php` → 文档）

---

## 6. 更开放

**证据**
- **MIT 许可**（`LICENSE`），核心能力永久开源是既有承诺
- **116 个 API** 端点，`api/v1/docs.php` + `docs.json.php` 提供机器可读文档
- 插件体系：`PluginSDK` / `PackageRegistry` / `PluginSystem`；`plugins/` 下已有 9 个插件示例
- 生态市场：`marketplace.php`（社区投稿 + 官方适配可区分，含"官方适配·免费"标识）
- 适配即服务：`docs/ADAPTER-SPEC.md` 契约 v2 + 筛选脚本 + AI 合成 + 验证闸门 + 人审页
- 数据主权：可自托管，数据在自己的服务器

**缺口**
- 开源与托管服务的**边界没说清**（哪些开源、哪些是服务）
- 没有对外版「适配者指南」（现在是内部 SPEC）
- 没有 `CONTRIBUTING.md`

**下一步**
1. 「开放清单」页：开源范围 / API / MCP / 插件 / 适配规范 / 自托管，逐项给可点击证据
2. `CONTRIBUTING.md`（含本地起服务、跑 CI、提 PR 的最小路径）
3. 适配者指南：从 0 到上架一个适配器（复用 ADAPTER-SPEC，改写成教程语气）

---

## 7. 更可定制化

**证据**
- 自定义内容类型：7 种字段 + 关联 / 汇总 / 查值 + 层级（自关联即层级）+ 五种视图
- 页面构建器：47 个展示区块 + 页面级 SEO 配置（`page-builder.php` / `BlockRegistry`）
- 主题系统、导航编辑器、页脚链接、角色权限（`roles.json` + `require_perm`）
- 自动化与工作流（`AutomationSystem` / `WorkflowLibrary`）
- **13 种语言**（`lib/I18n.php`）+ 内容多语言（`ContentI18n`）

**缺口**
- 能力可发现性差：功能散在 226 个页面里，新人找不到
- 没有模板：每做一个新场景都要从空开始

**下一步**
1. 「定制化能力地图」：能改什么 → 在哪改 → 一个真实例子（供后台首页与对外页共用）
2. 模板库：内容类型 / 看板 / 工作流 / 视图配置，一键应用（先做 5 个常用模板）
3. 自定义字段落地到团队协作（现在只有内容类型有 relation/rollup）

---

## 8. 更兼容

**证据**
- 连接器模板：飞书 / Slack / 企微 / 通用 REST Bearer（`lib/connection-templates/`）
- 数据进出：`DataSync` / `IngestAdapters` / `DataConnector` / CSV 与 REST 拉取
- 鉴权：OAuth2（`OAuth2Client`）+ API Key（`ApiKeyAuth`）+ TOTP
- 通知渠道：企微 / 飞书 / Slack / WhatsApp（+ 邮件）
- 多语言 13 种；SQLite 兜底（生产 3.7.17 无 FTS5 也能跑，降级路径已被测试覆盖）

**缺口**
- 没有**兼容性矩阵**（支持哪些 PHP / MySQL / SQLite 版本、哪些浏览器、哪些平台）
- 从别的工具迁移没有向导（Notion / 飞书多维表格 / WordPress）

**下一步**
1. 兼容性矩阵页：只写真实测过的组合（含"测过的降级路径"）
2. 迁移向导 v1：CSV / Notion 导入 → 自动建内容类型与字段
3. 连接器模板扩到 webhook 双向（现在多数是单向推送）

---

## 9. 更面向生态伙伴 / 开发者 / AI 爱好者 / OPC

**证据**
- 生态伙伴：生态市场（上架 / 投稿）、适配流水线、官方适配标识
- 开发者：116 API + 机器可读文档 + 插件 SDK + 6 个示例插件
- AI 爱好者：23 MCP 工具 + 看板娘 + 提示词/工作流模板（`WorkflowLibrary`）
- OPC：既有主线（一人公司 / 个体户）——TIPS 四力 + 受控 Loop + 七件可组合产品

**缺口**
- 四类人**各自的最短路径不明显**：首页是 OPC 叙事，开发者要自己摸到 `/docs`、`/marketplace`
- 没有伙伴计划说明（能拿到什么、怎么分成、如何被推荐）

**下一步**
1. 首页与产品页加"按身份进入"四入口（一人公司 / 开发者 / 生态伙伴 / AI 爱好者），各自直达
2. `/developers` 落地页：API + MCP + 插件 + 适配 + 三个真实示例（与 §4/§6 合并做）
3. 伙伴页：上架流程、审核标准、展示位置、免费/付费规则

---

## 建议的第一刀（覆盖 5 条方向，且全部可核验）

**做 `/developers` 一个页面 + 三个支撑件**——因为它一次覆盖
**更开放 · 更面向开发者 · 更 AI agent · 更兼容 · 更可定制化**，而且所需的内部能力**今天已经存在**
（116 API / 23 MCP / 插件 SDK / 生态市场 / 适配规范），不需要先补后端：

1. `/developers` 落地页：开放清单（MIT / API / MCP / 插件 / 适配 / 自托管）+ 每条给可点击证据
2. `/mcp` 或页面内区块：23 个 MCP 工具清单 + 3 个真实调用示例 + 客户端配置片段
3. 适配者指南（对外版）+ `CONTRIBUTING.md`
4. 一键生成 API Key → 首个请求的成功路径（打通现有 `admin/api-keys.php`）

## 进度（2026-09-20）

**已完成**：`/developers` 落地页——开放清单（MIT / API / MCP / 插件 / 适配 / 自托管）+ MCP 23 个工具清单
（来自 `lib/McpTools.php`，与 `mcp-server.php` 同源）+ 可直接跑的 curl 与 JSON-RPC 示例 + 适配上架流程。

**同一刀里顺带修的**
- MCP 工具注册表抽成单一来源 `lib/McpTools.php`：此前文档与 server 各写一份，必然漂移
- 两个 MCP 契约测试改为读单一来源，并新增"server 必须使用 `mcp_tools()`"断言，防止再次分叉
- 纠正一处会骗到人的数字：MCP 工具是 **23** 个（此前按文本 grep 误算成 31）

**这一刀还差**
- 适配者指南（对外版，现在只有内部 `ADAPTER-SPEC.md`）
- `CONTRIBUTING.md`
- API Key → 首个请求的引导路径（打通 `admin/api-keys.php` 到文档）
- 站点主导航加入口（现在只在页脚与 sitemap）

第二批（产品侧）：自我进化日志 + 模板库 + 迁移向导。
