# OpenFlow XMP — 产品路线图

> **一句话定位**：OpenFlow 是唯一一个内置"自我进化能力"的 AI 原生增长操作系统——它不只帮你做增长，它能告诉你增长哪里出了问题、建议你怎么改、甚至帮你自动修。

> **规模演进**：一人公司 → 中型 → 大型的三档演进路线（存储抽象/量化触发器/DataStore 设计）见 [docs/EVOLUTION.md](../docs/EVOLUTION.md)

---

## 核心架构（3 层）

```
┌─────────────────────────────────────────────────────┐
│  Layer 3：开发者生态（扩展层）                          │
│  插件系统(30+ hooks) · Skills Marketplace              │
│  MCP Server · SDK · 贡献指南                          │
├─────────────────────────────────────────────────────┤
│  Layer 2：TIPS 增长框架（业务层）                       │
│  Touch(内容) → Insight(数据)                          │
│  → Personalize(触达) → Sell(交易)                     │
│  + 跨层桥梁：CDP · GrowthDriver · Courses             │
├─────────────────────────────────────────────────────┤
│  Layer 1：Platform Intelligence（智能层）              │
│  自我进化 · 协同修复 · 健康检测 · AI 配置               │
│  ← 市面独一无二的差异化护城河                          │
└─────────────────────────────────────────────────────┘
```

---

## 重点功能（深做，形成护城河）

| 功能 | 层级 | 深度 | 为什么深做 |
|---|---|---|---|
| 自我进化引擎 | PI | ★★★ | **市面独一无二**——AI 自动体检+诊断+修复+生长数据 |
| CDP + RFM + 留存 | Insight | ★★★ | 单体 PHP 全功能 CDP，对标 Mixpanel/Amplitude |
| 营销自动化画布 | Personalize | ★★★ | 可视化+条件分支+多渠道，对标 HubSpot MA |
| CRM 管道 + 预测 | Sell | ★★★ | 已有深度（漏斗/预测/ARR），对标 HubSpot CRM |
| AI Copilot | PI | ★★★ | 自然语言建流程+查数据+管内容，**增长副驾** |
| 多语言 i18n | Touch | ★★☆ | 11 语言 RTL，对标 WordPress WPML |
| 内容引擎 + SEO | Touch | ★★☆ | AI 选题/成文/发布 + GEO/IndexNow，已有深度 |
| 课程 + 分销 | Sell | ★★☆ | 付费内容闭环+三层分成+虎皮椒支付 |

---

## 合并清单（减少后台噪音）

> **2026-09-20 状态**：下表多数已落地，`tests/hub_merge_test.php` 守住不回退
> （tags→pages-list、payment-settings→shop-settings、mail-settings→email、
> footer-links→site-builder、storage→health-check、activity→audit-log）。
> 继续合并之前先看**真实使用数据**（`审计日志 → 使用分析`）——按猜测重排导航，
> 正是 `docs/USABILITY-DIAGNOSIS.md` §7 明确说不要做的事。


| 合并项 | 合并到 | 减少页面 |
|---|---|---|
| 页面标签 + 文章标签 | 父级列表筛选栏 | -2 |
| 页面分类 + 文章分类 | 父级列表筛选栏 | -2 |
| 支付设置 | 商城中心（内嵌 tab） | -1 |
| 邮件设置 | 邮件营销（内嵌 tab） | -1 |
| 尾部链接 + 导航管理 | 站内构造（内嵌 tab） | -2 |
| 报告订阅 + 存储 | 仪表盘 / 健康检测 | -2 |
| 活动日志 | 审计日志（合并） | -1 |
| 分享传播重复入口 | 只保留 Insight 入口 | -1 |
| 内容日历重复入口 | 只保留顶层快捷 | -1 |

---

## 生态定位

> **OpenFlow 的生态不是"卖插件"，而是"赋能增长方法论"。**

- **Skills**：封装增长方法论（"SEO 标题生成""邮件模板""转化漏斗巡检"）
- **插件**：扩展渠道能力（微信小程序、抖音发布、企业微信深度集成）
- **MCP**：让外部 AI（Claude/GPT）直接操作 OpenFlow
- **SDK**：简化插件开发的工具库（数据访问/UI 注入/配置管理）

开发者基于 TIPS 框架构建扩展——**方法论即接口**。

---

## 当前优先级

> **校正于 2026-09-20。** 上一版（2026-08-31）的 P0 与实际在做的事已经脱节：那一版列的
> 「后台合并」「插件生态基础」大部分已经落地，而 9 月中下旬真正在做的是**把主张变成可核验的证据**
> （定位证据化 / 开发者与生态 / 可用性诊断与整改 / 自我进化台账），路线图没跟上。
> 本节按「有证据 / 缺口 / 下一步」重写，规则同 `docs/POSITIONING.md`：**没有证据的条目不写成进行中**。

### 本月在做（有明确证据）

| 方向 | 已落地的证据 | 还差什么 |
|---|---|---|
| **可用性（算出来再改）** | `docs/USABILITY-DIAGNOSIS.md` + `scripts/usability-audit.php`；S1 ⌘K 覆盖 18%→100%；S2 页面级空态缺出路 47→0；S4 地址口径统一；`tests/usability_ratchet_test.php` 棘轮门禁 | **S3 后台窄屏**：88 页无窄屏线索，先做 `admin-ui.css` 全局兜底再逐页 |
| **埋点（把假设变证据）** | `lib/PageUsage.php` 按天聚合页面访问；后台 `审计日志 → 使用分析`；`tests/page_usage_test.php` | 数据要攒 **2–4 周**；之后才谈 H2「按真实使用裁剪导航」 |
| **开放与开发者** | `/developers` 落地页；MCP 注册表单源化（`lib/McpTools.php`）；插件 hooks 35 个；`lib/PluginSDK.php`；8 个示例插件 | `CONTRIBUTING.md`；适配者指南对外版；API Key → 首个请求的引导路径；主导航入口 |
| **数据安全** | `lib/OffsiteBackup.php`（S3/R2 异地备份，SigV4 已与 AWS SDK 交叉验证）+ 后台配置与状态 + `scripts/offsite-backup.php` | **线上仍需配置凭据并跑一次 `--test`**；未配置前备份仍与站点同盘 |
| **口径一致性** | `scripts/metrics.php` 单一来源 + `lib/AdminInventory.php` + `tests/metrics_contract_test.php`；见 [项目口径指标](../docs/METRICS.md) | 把更多对外页面的规模数字纳管 |
| **自我进化可核验** | 自我进化台账（动作 → 度量 → 结算） | 动作前后的**指标回归对比**；无提升要能标记为"无效" |

### 上一版 P0 的实际状态（核对过，不是凭印象）

| 上一版条目 | 现状 |
|---|---|
| 统一内容中心 / 统一 SEO 中心 | ✅ `admin/content-hub.php` · `admin/seo-center.php` |
| 合并标签/分类/设置等浅 CRUD 到父级 | ✅ 6 组已归并，`tests/hub_merge_test.php` 守住（tags→pages-list、payment-settings→shop-settings、mail-settings→email、footer-links→site-builder、storage→health-check、activity→audit-log） |
| PluginSystem hooks 扩展到 30+ | ✅ 35 个 |
| 插件 SDK（`lib/PluginSDK.php`） | ✅ 已有 |
| 官方示例插件（3 个） | ✅ 8 个（`plugins/` 下带 `plugin.json` 的目录） |
| 开发者文档 | 🟡 `/developers` 已上线，`CONTRIBUTING.md` 与适配者指南对外版仍缺 |
| Skills marketplace API | ⬜ 未核实到证据 |
| CRM 事件接入 FlowSystem | 🟡 `FlowSystem` 与 `crm_stage` 均有代码痕迹，但**端到端链路未验证** |
| MA 流程可读 CRM 字段 | 🟡 同上 |
| CDP 分群 → CRM 批量操作 | ⬜ 未找到证据 |
| 营销活动 ROI 归因 | ⬜ 未找到证据 |

> 标 ⬜/🟡 的不代表没做，代表**当前没有可指向的证据**。要么补证据（一条能跑的路径 + 一个测试），
> 要么就别把它算进"已具备"。

### 下季度（P1）

- **系统联动收口**：把上表 ⬜/🟡 的四条各补一条端到端路径与契约测试，而不是继续列在路线图里
- CDP 性能优化（事件分层缓存 + 画像预计算 + cron 后台任务）
- 营销自动化画布升级（A/B 分流 + 多路径测试 + 条件嵌套）
- 内容引擎深化（版本历史 + 自动保存 + 互动数据回写 + 关键词库）
- 后台前端组件化（`admin-ui.css` + PHP 组件库）与后台移动端适配（与 S3 合并做）
- **测试基础设施**：修掉测试间的状态污染——多个测试直接写仓库 `data/`，导致"跑第二遍才红"
  （现象：全套跑完后 `design_grid_contract_test` 会失败）

### P2 · 年度（生态成熟 + 智能化）

**生态成熟**：

- [ ] 插件付费市场（作者 80% / 平台 20%）
- [ ] 付费 Skill（订阅制）
- [ ] 插件依赖管理 + 沙箱
- [ ] 官方认证体系（认证开发者/顾问/Skill）

**智能化深化**：

- [ ] 多 Agent 分工（内容助手/客服助手/数据分析师）
- [ ] AI 文案生成节点嵌入营销画布
- [ ] 预测式转化（AI 预测线索成交概率）
- [ ] 全链路归因模型（首触/末触/线性/时间衰减）

### P3 · 愿景

- [ ] 多租户 SaaS 架构
- [ ] Headless API 层（支持 Next.js/Astro）
- [ ] 可视化低代码平台（拖拽搭建业务流）
- [ ] 全自动增长引擎（AI 自主选题→成文→发布→收录→分析→优化）

## 已完成里程碑

### v1.5（2026-08-23）
- ✅ 11 语言国际化 + 翻译管理后台
- ✅ Cloudflare 全栈加速（Cache Rules + R2 + Workers + WebP）
- ✅ Notion 全内容双向同步（6 类数据）
- ✅ 虎皮椒聚合支付（微信+支付宝双通道）
- ✅ 全站前端统一（site-shell.js 全局导航+侧栏）
- ✅ 站点健康检测 · Cloudflare 管理 · Notion 同步管理

### v1.4（2026-08-20）
- ✅ AI Agent 原生（Copilot · 漏斗巡检 · AI 落地页）
- ✅ CDP 全域智能（11 tab · RFM · 留存 · 路径 · 营收）
- ✅ 营销自动化画布 · 邮件闭环 · 频控
- ✅ 会话回放 · 弹窗 A/B · 隐私中心

### v1.3（2026-08-19）
- ✅ 订单三源统一 · 课程三层分成 · 分销
- ✅ 入站/出站数据接收 · WebhookSystem
- ✅ 活动系统 · 导航站 · A/B 实验 · 多语言 i18n

### v1.0-v1.2
- ✅ 完整 CMS + SEO + AI 多供应商
- ✅ CRM 管道 + 自动评分 + 营销自动化画布
- ✅ CDP 用户画像 + 行为埋点
- ✅ 课程/订阅/咨询/直播交易闭环
- ✅ Skill 系统 + 插件引擎 + MCP Server + CLI
