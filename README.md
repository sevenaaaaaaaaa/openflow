# OpenFlow XMP

> AI 时代的网站增长操作系统 —— 帮一人公司设计 Agent 能跑的增长系统

**芭乐派**是主品牌（增长方法论 + 门派社区），**OpenFlow** 是它的开源底座（增长操作系统）。理论（芭乐派方法论）→ 工具（OpenFlow 平台）→ 落地（Agent 增长引擎），三位一体。

核心理念：**鱼与渔相结合** —— 不只卖工具（鱼），更提供最前沿的增长策略（渔）。核心能力永久开源。

**TIPS 框架四力合一**：触达（Touch）· 洞察（Insight）· 个性化（Personalize）· 销售（Sell）

OpenFlow XMP 不是建站工具，是一套把内容生产、AI 获取、线索培育、用户洞察和数据分析串成闭环的**增长操作系统**，让网站从"被动的展示页"升级为"自动获客的增长引擎"。

[功能清单](md-docs/FEATURES.md) · [架构规范](md-docs/ARCHITECTURE.md) · [部署文档](md-docs/deployment.md) · [使用指南](md-docs/USAGE.md)

---

## 为什么是 OpenFlow

| 维度 | 说明 |
|------|------|
| **一体化** | CMS + SEO/GEO + CDP + MA + CRM + 电商 + 课程 + 社区 + 活动 + 分销 一个系统内数据天然打通 |
| **AI Agent 原生** | 小福增长 Copilot（自然语言→建自动化）、MCP Server、AI 一键生成落地页/文章、AI 洞察巡检 |
| **数据闭环** | 采集→Schema 校验→画像→分群→触达（频控）→转化→CAPI 回传→投放归因，全链路无断点 |
| **一方/三方数据** | 入站 Webhook 接收 + 外部连接器拉取 + CRM/订单/微信数据回填 CDP |
| **开箱即用** | PHP 单体，无 Composer 生产依赖，Apache/Nginx/宝塔/Docker 均可部署 |

---

## 核心能力

### 内容引擎 + SEO/GEO
- AI 选题/成文（多供应商：OpenAI/Claude/DeepSeek/MiniMax 等）
- 批量发布 · 定时发布 · 内容日历排期 · 多平台分发（公众号/知乎/小红书/B站/视频号/抖音）
- SEO 全家桶：301 重定向 · Sitemap · 结构化数据 · 页面级 SEO · hreflang 多语言
- GEO：面向 AI 搜索引擎做实体与引用优化 · IndexNow 即时收录
- 知识库 RAG（飞书/Notion/印象笔记/Obsidian 双向同步）

### 数据底座（CDP）
- 行为采集（页面/点击/滚动/表单/站外/错误）· Tracking Plan Schema 校验 + 数据质量监控
- 用户画像 360°（身份图谱合并：匿名→登录→微信 openid）· 行为时间线
- 标签体系 · 健康分/RFM 评分 · 规则分群（实时进出群触发）· 留存/漏斗/路径/营收分析
- 点击热力图 · 会话回放 · A/B 测试（Z 检验显著性）

### 触达体系（MA/CRM）
- 营销自动化：15+ 触发器 × 邮件/延迟/通知/打标签/积分/发券/站内信动作 · 条件分支并行
- 可视化画布编排 · 邮件营销闭环（模板/退订/打开点击统计/序列）· 跨渠道频控/疲劳度
- CRM：线索/商机/客户管道 · 查重防撞单 · 赢率与预测 · 跟进任务 · 线索孵化
- 私域：公众号（群发/模板消息/标签）· 企业微信（私信/群发助手）

### 商业化
- 电商：SKU 库存 · 限时促销 · 优惠券 · 组合包 · 会员额度 · 三层分成（平台/作者/分销）
- 课程：多课时 · 测验（自动批改/通过线）· 笔记/评分/收藏 · 讲师/开发者发布 + 审核
- 活动：线上/线下 · 原生报名（名额/审核）· 开始前提醒 · 直播/回放
- 生态市场：Skill/插件/主题 · 开发者入驻 · 分销推广 + 排行榜

### AI Agent 原生
- 小福增长 Copilot：自然语言 → 创建自动化流程 / 查询数据（线索/收入/活跃/转化率）
- 转化漏斗级 AI 巡检：落地页/渠道转化率环比骤降自动告警 + 根因建议
- AI 一键生成落地页（需求描述 → 结构化区块）· AI 写文章（标题/slug/SEO 一次产出）
- MCP Server（HTTP/stdio）：外部 AI 客户端操作 CMS（API Key 鉴权）

### 生态与融合
- 公开内容 API（SSR/外部前端拉取）· SSO 统一登录 · `/growth/` 子路径挂载
- 数据仓库出向导出（events/orders 等 JSON/CSV）· CAPI 转化回传（Meta fbp/fbc）
- 隐私中心：数据导出/账号注销/脱敏 · 个保法/GDPR 合规

---

## 快速开始

### 环境要求
- PHP **8.0+**（推荐 8.2+）
- 扩展：`gd` `pdo_sqlite` `mbstring` `fileinfo` `curl` `openssl` `json`
- Web 服务器：Apache（`.htaccess` 已内置）/ Nginx / 宝塔

### 安装

```bash
git clone <your-repo-url> openflow
cd openflow

# 确保 data/ 与 uploads/ 可写
chmod -R 775 data uploads

# 访问站点，完成初始化
# 后台入口：/xmp
```

### Web 服务器配置

| 服务器 | 配置 |
|--------|------|
| **Apache** | 项目自带 `.htaccess`，开启 `mod_rewrite` 即可 |
| **Nginx** | 参照根目录 `nginx.site.conf`（含前台美化 URL 与 `/xmp/` 后台路由） |
| **宝塔** | 站点 → 伪静态 → 粘贴 `deploy/baota-rewrites.conf` |

> ⚠️ 部署后请到后台 **设置 → 站点配置** 把 `site_url` 改为你的域名（默认占位 `example.com`）。

### 定时任务（cron）

```cron
* * * * *  curl -s https://your-domain.com/api/cron.php
```

cron 负责：定时发布 · 自动化延迟队列 · 外部连接器同步 · 活动开始前提醒 · 漏斗巡检 · 报表订阅推送 · 流失预警等。

### 环境变量（可选）

见 `.env.example`：`OF_DATA_DIR` / `OF_UPLOAD_DIR`（容器化/多站点场景使用）。

---

## 技术架构

- **语言/运行时**：PHP 8.0+，无框架，模块化函数库（`lib/`）
- **存储**：JSON 文件（`data/`）+ SQLite（`data/db/openflow.db`），零外部依赖
- **AI**：统一调用层 `lib/AiCenter.php`，兼容 OpenAI / Claude / MiniMax 等供应商
- **数据流**：`api/track.php` → `lib/FlowSystem`（统一事件总线）→ CDP / 自动化 / Webhook / CAPI
- **Composer**：仅 dev 依赖 PHPUnit（生产可跳过 `composer install`）

```
admin/          后台控制台（/xmp/*）
lib/            核心库（AiCenter/CdpSystem/AutomationSystem/CommerceSystem/…）
api/            前端/外部 API 端点
assets/         前端脚本与样式（inject.js 埋点、cdp-track.js、site-shell.js）
data/           运行时数据（不入库，首次运行自动生成）
bin/            运维脚本（数据迁移/种子/CLI）
md-docs/        架构/功能/部署等文档
deploy/         宝塔伪静态规则等部署资产
```

---

## 数据与隐私

- **所有数据本地存储**：`data/`（JSON + SQLite），不依赖任何外部服务
- **密钥/令牌均不入库**：`.gitignore` 已排除 `data/`、`.env`、`deploy.sh` 等
- **隐私合规**：用户可自助导出数据 / 注销账号；后台支持手机号/邮箱脱敏
- **埋点尊重 Do Not Track**，支持 `data-privacy="none"` 关闭采集

---

## 文档

- [功能清单](md-docs/FEATURES.md) · [架构规范](md-docs/ARCHITECTURE.md) · [部署](md-docs/deployment.md) · [使用](md-docs/USAGE.md)
- [开发者](md-docs/DEVELOPER.md) · [变更日志](md-docs/CHANGELOG.md) · [路线图](md-docs/ROADMAP.md)

---

## License

[MIT](LICENSE)

Copyright (c) 2026 芭乐派（OpenFlow）
