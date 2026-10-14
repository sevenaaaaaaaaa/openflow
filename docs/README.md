# OpenFlow 文档导读

这个目录有两层文档：**战略层**回答「我们为什么存在、往哪走」，
**工程层**回答「现在是什么状态、怎么建、怎么发」。当前共同主线是：

> **OpenFlow 是一套基于 TIPS、同时支持 Flow 与 Loop 的开源增长操作系统。**
> Flow 由人设计、Copilot 辅助并确定性执行；Loop 由人定义目标与边界，TIPS Agent
> 调用 Skills、Harness 和成熟 Flow，在受控循环中执行、评估和调整。两者长期并存，
> 共享同一套业务数据、权限、审计与执行能力。

先读 `VISION.md`（北极星，5 分钟），它给全部规划一个共同坐标系；再按需要
往下走。

---

## 战略层 · 产品北极星与架构决策

`VISION.md` 立坐标系，ADR 记录在这个坐标系下做出的关键架构决策。

| 文档 | 回答什么问题 | 一句话结论 |
|---|---|---|
| `VISION.md` | 长期不变的产品边界 | TIPS 是共同业务模型；Flow/Copilot 与 Loop/Agent 是两种并存范式；业务、稳定与兼容优先 |
| `FLOW-BASELINE.md` | 现有 AITIPS Flow 如何测量 | 8 个运行维度形成可重复 JSON 基线，完整契约套件阻断回归 |
| `ADR-001-SHARED-DOMAIN-CONTRACTS.md` | Flow 与 Loop 是否共用业务对象 | 共用身份、权限、审计和执行入口；Loop 不复制现有业务实现 |
| `ADR-002-EVIDENCE-WRITE-PATH.md` | 何时接入共享证据写路径 | 先只读投影量化缺口，再以可选字段影子双写；当前尚未实施 |

### 建议阅读顺序

- **只看产品叙事**：根目录 `README.md` → `VISION`。
- **看架构决策**：`ADR-001` → `ADR-005` 顺序读。

---

## 平台层 · 技术选型与运维手册

面向"要接手开发的人/Agent"的五份实操文档（2026-09-17 新增）：

| 文档 | 回答什么问题 |
|---|---|
| `TECH-STRATEGY.md` | **技术选型与服务边界**：什么活归什么语言（PHP/Python/TypeScript/Go）、API 契约模板、渐进迁移路线、决策清单 |
| `API-INVENTORY.md` | **API 端点 + MCP 能力**的完整清单（按域分组 + 鉴权档位 + 对外集成推荐入口） |
| `AGENT-DEPLOY.md` | **Agent 怎么部署**：拓扑、标准命令、验证清单、绝对规则、常见故障、回滚 |
| `MCP-SETUP.md` | **把 OpenFlow 接到 AI Agent**：HTTP SSE / stdio 两种接入、<!--m:mcp_tools-->23<!--/m--> 个工具速览、鉴权与安全、排障 |
| `SERVICE-INTEGRATION.md` | **服务之间怎么对接**：矩阵服务地图、已打通通道、新增对接规范、数据归属 |
| `INFRASTRUCTURE.md` | **服务器与 Cloudflare 配置**：站点/别名、systemd 服务、DNS、Workers、R2、常用运维命令 |
| `DEPLOY-CONVENTIONS.md` | 多会话协作约定 + 前端资产版本号纪律（多人/多 Agent 并行时必读） |

---

## 工程层 · 现状与建设

| 文档 | 内容 |
|---|---|
| `GAP-ANALYSIS.md` | 功能缺口分析（用户体验 + 竞品对照） |
| `GTM.md` | 市场定位屋 + **可以说 / 不能说的事实边界**（信息架构一节已作废）|
| `GTM-COPY.md` | **逐槽位文案替换表**——不动任何现有模块，只换词 |
| `SECURITY-API.md` | API 权限矩阵：四档划分、默认取值与调整方式（后台 `/xmp/api-permissions`）|
| `HOOKS.md` | 插件系统钩子清单（已实现的插入点；「尚无插入点」以下为规划） |
| `PLUGIN-DEV.md` | **插件开发指南（v2）**——API 路由 / 后台菜单与设置页 / 前台插槽 / 定时任务，第三方开发者从这里开始 |
| `PRODUCT-MAP.md` | **功能地图与演化路径**——九大能力域现状、版本脉络、死功能审计结论、新功能四件套纪律 |
| `WAIFU-WARDROBE.md` | **看板娘衣橱**——五形象换装、性格化语气、自定义模型（女武神等）接入指南 |
| `../CHANGELOG.md` | **版本变更日志**——每个正式版本的亮点与改进（唯一来源） |
| `PERFORMANCE.md` | 性能基线与优化记录（含 events 索引） |
| `EVOLUTION.md` | 三档规模演进路线——已按实测修订：轴定为「细胞式」、第六章 DataStore 暂缓、第八章触发器重标 |
| `DEPLOY.md` | 部署/发布流程参考 |
