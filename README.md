# 芭乐派 · OpenFlow

> 帮一人公司设计 Agent 能跑的增长系统

**芭乐派**是主品牌（增长方法论 + 门派社区），**OpenFlow** 是它的开源底座（增长操作系统）。理论（芭乐派方法论）→ 工具（OpenFlow 平台）→ 落地（Agent 增长引擎），三位一体。

核心理念：**鱼与渔相结合**——不只卖工具（鱼），更提供最前沿的增长策略（渔）。核心能力永久开源。

---

## ✨ 核心能力（TIPS 框架）

触达 Touch · 洞察 Insight · 个性化 Personality · 销售 Sales——四力合一，AI Agent 驱动。

| 模块 | 能力 |
|------|------|
| 内容引擎 CMS | 文章/页面/分类/发布 |
| 营销自动化 MA | 可视化工作流 + AI 步骤 |
| 客户数据 CDP | 画像 · 分群 · 洞察 |
| SEO / GEO | 搜索与 AI 优化 |
| CRM 与线索 | 线索池 · 跟进 · 转化 |
| 数据分析 | 归因 · A/B · 洞察 |

**自生长 AI Engine**：每 6 小时主动跑一轮增长闭环——爬取信号 → AI 洞察 → 生成草稿 → 主动触达转化。

---

## 🚀 快速开始

```bash
# 本地快速体验
php -S 127.0.0.1:8899 -t .

# 浏览器打开 http://127.0.0.1:8899/
```

环境要求：PHP ≥ 8.0，扩展 `pdo_sqlite`、`curl`、`mbstring`、`json`。

生产部署见 `md-docs/deployment.md`（Apache 用 `.htaccess`，Nginx 参考 `nginx.site.conf`）。

---

## 📖 详细文档（知识库）

完整版 README 已分门别类写入公司知识库（`data/knowledge/index.json`），后台 `admin/knowledge.php` 可编辑，AI 助手可检索：

- **general** — 项目总览
- **company** — 品牌故事与创始人
- **product** — 产品定位与 TIPS 框架
- **market** — 增长方法论 + 课程体系
- **ops** — 站点结构 + 技术架构 + 视觉规范 + 更新流程

---

## 🧭 站点页面

- `/` 首页 · `/product` 产品 · `/capability` 能力 · `/courses` 课程
- `/about` 关于 · `/academy` 学院 · `/community` 门派社区 · `/docs` 文档

---

## 🏗 技术架构

```
前端页面 (HTML/JS + role-content.js 角色化 + site-shell.js 外壳)
        │
        ▼
API 层 (api/*.php)
        │
        ▼
核心逻辑 (lib/*.php)  ← 数据驱动
        │
        ▼
数据层 (data/ — JSON + SQLite)
```

- **无框架**：原生 PHP，零依赖
- **数据透明**：JSON + SQLite，可随时备份/迁移
- **可扩展**：PluginSystem 插件 + Skill 生态

---

## 📄 许可证

[MIT](LICENSE) © 芭乐派 · OpenFlow
