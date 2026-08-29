# OpenFlow XMP — 产品路线图

> **核心方向**：从"一人系统"进化为"开发者生态"——让其他开发者能基于 TIPS 模型构建插件、Skills 和扩展。
>
> 优先级：P0 本月 / P1 下季度 / P2 年度 / P3 愿景

---

## 当前核心问题（按优先级排序）

### 🔴 P0-A：系统联动断裂
CRM / MA / CDP / Sales 各自独立，数据流断裂：
- CDP 检测高价值用户 → 无法自动触发 CRM 跟进
- CRM 线索阶段变化 → 无法触发 MA 流程
- MA 自动化结果 → 无法回写 CDP 标签

**根因**：FlowSystem（事件总线）只连接 行为→CDP→MA，**缺少 CRM↔MA 双向桥接**。

### 🔴 P0-B：后台功能碎片化
- SEO：8 个独立页面（seo/seo-tools/seo-batch/seo-console/structured-data/redirects/image-seo/geo），无统一入口
- 内容管理：15+ 页面散落，无统一"内容中心"
- Analytics：CDP 11 tab + analytics.php + path-analysis + attribution + heatmap + session-replay 重叠
- 交互无产品逻辑，新人上手困难，不利于对外宣传

### 🔴 P0-C：开发者生态基础设施缺失
- PluginSystem 只有 9 个 hooks，只有 1 个示例插件
- SkillSystem 只有 5 个 Skills（3 个官方），workflow 是 stub
- 无插件 SDK、无 API 文档、无开发者门户、无贡献指南
- hooks 覆盖面太窄（核心操作如 CDP 事件/自动化触发/表单提交未暴露）

### 🟡 P1-A：CDP 性能
CDP 全量加载 events/profiles（JSON）→ 服务器内存/CPU 要求高。需：
- 事件/画像分层缓存（热数据 Redis/CF KV，冷数据 JSON）
- 聚合运算（留存/RFM/路径）抽成 cron 后台任务
- 请求只读预计算结果

### 🟡 P1-B：后台前端统一
admin/config.php 的 admin_sidebar() 硬编码 2,464 行，168 个后台页面各自 inline CSS。需：
- 抽 admin PHP 组件库（admin_table/form/card/grid/modal）
- 统一 CSS 变量（和前台 tokens.css 一致）
- 后台页面逐步迁移到组件化

---

## P0 · 近期（本月）

### 一、系统联动（打通 MA ↔ CRM ↔ CDP）

- [ ] **CRM 事件接入 FlowSystem**：线索阶段变化（新建/跟进/赢单/输单）→ 触发 MA 流程 + 写入 CDP 标签
- [ ] **MA 流程可读 CRM 字段**：自动化条件节点支持"线索阶段""客户等级""最近跟进时间"
- [ ] **CDP 分群 → CRM 批量操作**：分群结果一键"转线索""批量发邮件""分配销售"
- [ ] **CRM 管道看板联动 CDP**：管道视图展示每条线索的 CDP 标签（渠道/活跃度/健康分）
- [ ] **营销活动 ROI 归因**：MA 触达 → 订单/转化 → 自动计算 ROI 并写入活动报表

### 二、后台统一（合并碎片页面）

- [ ] **统一内容中心**：合并 articles/pages/downloads/podcasts 为统一入口，tab 切换
- [ ] **统一 SEO 中心**：合并 8 个 SEO 页面为单一页面（概览/工具/批量/控制台/结构化/重定向 tabs）
- [ ] **统一数据洞察**：合并 analytics/path/attribution/heatmap/session-replay 为"数据洞察"单一入口
- [ ] **统一设置中心**：合并 settings/ai-config/payment-settings/mail-settings/shop-settings 为"系统设置"
- [ ] **admin 组件库**：`lib/admin-ui.php` 提供 `admin_table()`、`admin_form()`、`admin_card()`、`admin_modal()` 统一组件

### 三、开发者生态基础

- [ ] **PluginSystem hooks 扩展**：至少 30 个 hooks（CDP 事件/自动化触发/表单提交/CRM 操作/支付回调/内容发布/SEO 更新/社区事件）
- [ ] **插件 SDK**（`lib/PluginSDK.php`）：简化插件开发的工具库（数据访问/UI 注入/配置管理/日志）
- [ ] **官方示例插件**：3 个完整示例（SEO 增强插件/邮件模板插件/数据分析插件）
- [ ] **Skills marketplace API**：公开 API 供第三方提交/搜索/安装 Skills
- [ ] **开发者文档**：插件开发指南 + Skill 开发指南 + API 参考（端点/参数/返回值/示例）

---

## P1 · 中期（下季度）

### 四、CDP 性能优化

- [ ] **CDP 事件分层**：热数据（7 天）→ CF KV / Redis，冷数据（>7 天）→ JSON/SQLite
- [ ] **画像预计算**：留存/RFM/路径/分群匹配 → cron 后台任务，请求只读结果
- [ ] **事件采样**：高频事件（page_view）支持采样率配置
- [ ] **独立 CDP Worker**：可选把 CDP 运算部署到 Cloudflare Workers（边缘计算）

### 五、后台前端组件化

- [ ] **admin-ui.css**：统一后台 CSS 变量（和前台 tokens.css 同源）
- [ ] **PHP 组件库**：`admin_table($columns, $rows, $actions)`、`admin_form($fields)`、`admin_card($title, $body)`、`admin_grid($items)`、`admin_modal($content)`
- [ ] **迁移首批 10 个页面**：articles、settings、crm、cdp、automation 优先迁到组件化
- [ ] **后台响应式**：移动端适配（当前后台在手机端基本不可用）

### 六、内容与增长

- [ ] **版本历史 + 自动保存**：草稿每 30s 自动备份，支持一键回滚
- [ ] **互动数据回写**：文章页展示阅读量/评论数/点赞数
- [ ] **关键词库**：收录目标关键词、跟踪排名、竞品对比
- [ ] **站内链接自动建议** → 批量一键插入

### 七、交易

- [ ] **优惠券/拼团/限时折扣**营销插件
- [ ] **课程评分体系**复用 CommentSystem
- [ ] **虎皮椒支付回调对接完善**：回调验签 + 订单状态更新 + 自动发货

---

## P2 · 远期（年度）

### 八、生态成熟

- [ ] **插件付费市场**：完整结算体系（作者 80% / 平台 20%）
- [ ] **付费 Skill**：购买后安装/执行，支持订阅制
- [ ] **插件依赖管理**：plugin.json 声明依赖，自动安装
- [ ] **插件沙箱**：独立进程/容器运行，隔离故障
- [ ] **官方认证体系**：认证开发者 / 认证顾问 / 认证 Skill

### 九、智能化深化

- [ ] **预设 Prompt 模板库**（SEO 标题、文案、问卷分析）
- [ ] **多 Agent 分工**（内容助手 / 客服助手 / 数据分析师）
- [ ] **AI 文案生成节点**嵌入营销画布
- [ ] **预测式转化**：AI 预测线索成交概率，自动分配销售
- [ ] **金句/FAQ 抽取**，反哺文章与营销内容

### 十、数据与问卷

- [ ] **A/B 测试自动选出显著胜者**并回写
- [ ] **舆情周报自动生成**（AI 汇总趋势 + 应对建议）
- [ ] **竞品监测源扩展**
- [ ] **问卷分卷逻辑**（条件跳题）
- [ ] **NPS 关键人跟进**：低分用户自动进入 CRM 挽回流程

---

## P3 · 愿景（未来）

### 战略级

- [ ] **多租户 SaaS 架构**：一套代码服务多个独立站点
- [ ] **Headless API 层**：支持前端框架（Next.js/Astro）通过 API 消费内容
- [ ] **PWA 离线支持**：后台 PWA 化，移动端也可管理
- [ ] **可视化低代码平台**：拖拽搭建完整业务流

### 智能化

- [ ] **全自动增长引擎**：AI 自主选题 → 成文 → 发布 → 收录 → 分析 → 优化，完整闭环
- [ ] **跨平台智能分发**：内容一键适配多平台格式
- [ ] **动态定价引擎**：基于需求/库存 AI 定价

---

## 已完成的里程碑

### v1.5（2026-08-23）
- ✅ 11 语言国际化（i18n）· URL 前缀路由 · 翻译管理后台
- ✅ Cloudflare 全栈加速（Cache Rules + R2 + Workers + WebP 图片优化）
- ✅ Notion 全内容双向同步（导航站/文章/课程/落地页/技能）
- ✅ 虎皮椒聚合支付（微信 + 支付宝双通道）
- ✅ 全站前端统一（site-shell.js 全局导航 + 侧栏 + 主题切换）
- ✅ 站点健康检测 · 翻译管理 · Cloudflare 管理 · Notion 同步管理后台
- ✅ 导航站 417 站 · 505+ 文章批量管理 · CSS 渐变封面

### v1.4（2026-08-20）
- ✅ AI Agent 原生（小福 Copilot · 漏斗巡检 · AI 生成落地页）
- ✅ CDP 全域智能（11 tab · RFM · 留存 · 路径 · 营收 · 分群 · 热力图）
- ✅ 营销自动化画布 · 邮件营销闭环 · 频控
- ✅ 会话回放 · 弹窗 A/B · 隐私中心 · 视频号/抖音发布

### v1.3（2026-08-19）
- ✅ 订单三源统一 · 作者分成修复 · 课程三层分成
- ✅ 入站数据接收层 · 外部数据连接器 · WebhookSystem
- ✅ 活动系统升级 · 导航站 · A/B 实验 · 多语言 i18n

### v1.0-v1.2
- ✅ 完整 CMS + SEO 全家桶 + AI 多供应商
- ✅ 线索管理 + CRM 管道 + 自动评分
- ✅ 营销自动化 + 可视化画布
- ✅ 行为埋点 + CDP + 用户画像
- ✅ 课程/订阅/咨询/直播完整交易闭环
- ✅ Skill 系统 + 插件引擎 + 生态市场
- ✅ MCP Server + CLI 命令行
