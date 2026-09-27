<div align="center">

<img src="favicon.svg" width="72" alt="OpenFlow">

# OpenFlow

**增长操作系统 · Touch · Insight · Personalize · Sell**

[![CI](https://github.com/sevenaaaaaaaaa/openflow/actions/workflows/ci.yml/badge.svg)](../../actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-2563eb.svg)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-7c5cff.svg)](../../actions)
[![Tests](https://img.shields.io/badge/%E5%A5%91%E7%BA%A6%E6%B5%8B%E8%AF%95-156%20passed-16a34a.svg)](tests/)

**别人的增长工具，默认你有一支团队。OpenFlow 是给没有团队的人的那一套。**

[官网](https://nownexts.com) · [在线体验](https://nownexts.com/register) · [产品能力](https://nownexts.com/capability) · [定价](https://nownexts.com/pricing) · [文档](docs/README.md)

</div>

---

## 这是什么

OpenFlow 把**内容触达、用户洞察、个性化运营、销售变现**装进同一个系统，再用 AI 把它们连成会自己转的增长闭环。部署它之后，你一个人就能拥有原本要雇一支团队才玩得起的能力——数据在自己服务器上，核心能力永久开源。

它不取代你已经会用的工具，它把「一个人干不完增长」这件事的门槛，降到今天就能跨过去。

| 没有它 | 有了它 |
|---|---|
| 热点要自己刷,选题靠感觉 | AI 岗位每天盯热点、出选题、写好草稿等你审 |
| 访客来了就走了,谁也不知道 | CDP 认得出每个访客,看过什么、来过几次、该跟进谁 |
| 触达靠手动群发 | 画布拖出流程:表单→分组→邮件→提醒→成交回流 |
| 收款、课程、会员再拼一个平台 | 内置商城、订阅、优惠券、分销、收据 |

## 看一眼真实的后台

**今日主线** —— 全站信号收成一条行动脊柱:先处理钱与时效,再推进今日目标。小福(增长 COO)每天交班,晨会 3 分钟讲完「结果 → 重点 → 待拍板」。

![今日主线](docs/screenshots/01-today.png)

**AI 岗位** —— 不是跑一次的任务,是有 KPI、有工具边界、每天交班的数字员工。草稿永远待审,发布永远由人拍板;连续不达标自动降级为「仅建议」。

![AI 岗位](docs/screenshots/05-agent-posts.png)

**经营驾驶舱** —— KPI、转化漏斗、AI 兜底解读;数据不只是展示,异常会被说人话地指出来。

![驾驶舱](docs/screenshots/02-dashboard.png)

**CDP 用户画像** —— 匿名访客与会员身份合并,一人一册完整行为时间线。

![CDP](docs/screenshots/03-cdp.png)

**营销自动化画布** —— SVG 真流程图:拖拽、条件分支、A/B 分流、延时续流,执行全程留痕。

![画布](docs/screenshots/04-canvas.png)

<details>
<summary><b>更多界面</b>(内容中心 / Studio 编排)</summary>

![内容中心](docs/screenshots/06-content-hub.png)

![Studio](docs/screenshots/07-studio.png)

</details>

## 两种工作方式

| | **Flow 模式 · AITIPS Copilot** | **Loop 模式 · TIPS Agent** |
|---|---|---|
| 适合 | 已经知道怎么做的流程 | 知道目标但缺人手缺经验 |
| 人做什么 | 设计节点、画布、规则 | 定义目标、预算、边界 |
| AI 做什么 | 辅助搭建、检查、优化 | 在白名单工具里链式执行,中高风险等你批准 |
| 典型例子 | 询价表单→建线索→48h 未跟进自动提醒 | 「这个月拿到 20 个付费咨询」→ Agent 拆解执行 |

两者共享同一套 TIPS 数据模型:成熟的 Flow 是 Agent 的可靠工具,验证过的 Loop 固化为新的 Flow。

## 核心能力

- **触达 Touch**:CMS / 落地页 / 多语言 / SEO-GEO 全栈(四大搜索引擎 + 热点雷达)/ 全平台发布(Telegram·Discord·Mastodon·WordPress…)
- **洞察 Insight**:CDP 身份图谱 / 分群 RFM / 转化漏斗 / 归因与 A/B / 对话式 BI / 自定义报表
- **个性化 Personalize**:自动化画布 / 动态内容 / 频控与审批 / 增长规则引擎
- **销售 Sell**:CRM / 商城 / 订阅计费 / 课程交付 / 虎皮椒·微信·支付宝·Stripe 多渠道支付 / 收据退款
- **AI 员工**:AI 岗位(内容编辑等,带 KPI 考核)/ Agent 运行时(风险审批门)/ 创作台 / 晨会汇报
- **开放**:插件平台 v2 / MCP Server(外部 Agent 直连)/ CLI / PWA / API 权限矩阵

全部可在后台与 GitHub 代码里逐项核验——**我们不把远景写成现状**。

## 芭乐派产品矩阵中的位置

OpenFlow 是[芭乐派产品矩阵](docs/PRODUCT-MATRIX.md)的**入口层**:TIPS 理念的 all-in-one,门槛最低的起点。
用上一段时间后长出更深的单点需求,可进阶到 Flow 家族(MFlow 内容管线 / inFlow 情报 / UserLoop 数据中枢 / PayFlow 变现 / LearnFlow 交付);ThirdC、V2HTML、InputFlow 组成偏本地的 Studio 套件。**账号互通已上线**:OpenFlow 登录,全家免注册。

## 快速开始

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git
cd openflow
composer install --no-dev --optimize-autoloader
```

Web 根目录指向项目目录(Apache 用自带 `.htaccess`,Nginx 参考 `nginx.site.conf`),访问站点按安装向导完成初始化。要求:PHP 8.1+,扩展 `pdo_sqlite / gd / mbstring / fileinfo / curl / openssl`;**无 MySQL / Redis / Node 依赖**。

最后一件事——给系统装上心跳,定时发布、自动化队列、AI 岗位、热点雷达才会转起来:

```cron
* * * * * curl -fsS --max-time 55 "https://你的域名/api/cron.php?secret=你的密钥" >/dev/null 2>&1
```

密钥在 `data/cron_secret.json`。自部署完整清单见[帮助中心 · 运维篇](https://nownexts.com/help)。

AI 是可选能力:未配置模型时,内容、数据、自动化、商城照常运行,相关 AI 功能明确降级、不假成功。

## 文档

[产品北极星](docs/VISION.md) · [功能地图](docs/PRODUCT-MAP.md) · [矩阵定位](docs/PRODUCT-MATRIX.md) · [矩阵账号互通](docs/MATRIX-SSO-INTEGRATION.md) · [插件开发](docs/PLUGIN-DEV.md) · [架构规范](md-docs/ARCHITECTURE.md) · [部署](md-docs/deployment.md) · [变更日志](md-docs/CHANGELOG.md)

## 当前边界(诚实声明)

- 增长大脑以可解释规则建议为主 + 受控 Agent 执行,不是无约束的自主策略 Agent;
- AI 岗位与 Agent 的产出均为草稿/建议,发布、群发、价格、支付永远有人工闸门;
- 「高意向线索→成交」的黄金 Loop 尚在真实数据验证中;
- AI 能力取决于你配置的模型与预算;直播媒体层需外部流媒体服务。

我们区分**已实现 / 已接入 / 已被使用 / 已验证有效**,不把远景写成现状。

## License

[MIT](LICENSE) · 由 [芭乐派](https://nownexts.com) 维护 —— 给一人公司的增长方法论与增长社区。
