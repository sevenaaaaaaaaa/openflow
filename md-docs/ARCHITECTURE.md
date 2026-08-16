# OpenFlow 架构规范

> 本文档定义「每个功能唯一实现位置」，防止同功能多实现累积噪声。
> **新增功能前，先查本文档 + grep 现有 lib/，能复用就不新建。**

---

## 一、前端资产（唯一实现清单）

| 用途 | 唯一文件 | 说明 |
|------|----------|------|
| 设计 token（配色/间距/圆角） | `assets/tokens.css` | Open Design 统一，全站唯一 token 源 |
| 组件样式（卡片/按钮/表单等） | `assets/modules.css` | Open Design 统一组件库 |
| 主页面视觉 | 各 `.php` 内联 `<style>` | index/product/capability/courses/about 各自内联 |
| 次级页面样式 | `assets/tailwind-build.css` | academy/community/docs 等 35 个次级页 |
| 独立页样式 | `assets/standalone.css` | 问卷/感谢页等 7 个独立页 |
| 落地页模板 | `assets/site-arc-betterup.css` + `assets/site-betterup.js` | 仅 landing.php 使用 |
| 次级页面外壳/导航 | `assets/site-shell.js` | 26 个次级页共用 |
| 首页角色化 | `assets/role-content.js` + `assets/role-switch.js` | 仅首页 |
| 埋点注入 | `assets/inject.js` | 全站 |
| SEO 注入 | `assets/seo-inject.js` | 主页面 |
| 埋点 SDK | `assets/cdp-track.js` | 全站 |

**已删除的探索期变体**（勿恢复）：`site-arc.js/css`、`site-notion.js/css`、`site-betterup.css`、`site.js`、`dynamic-content.js`、`lead-form.js`。

---

## 二、Markdown 转换（唯一实现）

**唯一实现：`lib/Markdown.php`（`Markdown::toHtml` / `Markdown::extractFrontMatter`）**

现状存在 4 处重复，需逐步收敛：
- ✅ `lib/Markdown.php` — 完整实现（标题/加粗/代码块/链接/图片/列表/引用/表格），目前仅 `api/ingest.php` 使用
- ❌ `docs.php::md_render` — 简化版，应改为调用 `Markdown::toHtml`
- ❌ `bin/import.php::md_to_html` — 简化版，同上
- ❌ `bin/import-drafts.php::md_to_html` — 简化版，同上

---

## 三、导航系统（现状 3 套 → 目标 1 套）

| 页面 | 现状 | 目标 |
|------|------|------|
| 首页 index.php | SSR 静态导航（Open Design 重构） | 保留 SSR |
| product/capability/courses/about | 内联 `NAV` 数组 + `renderTabs/renderSidebar` | 迁到 site-shell.js |
| 次级页面 26 个 | `site-shell.js` | 唯一实现 |

---

## 四、缓存（唯一实现）

**唯一实现：`lib/Cache.php`（`Cache::remember` / `FileCache` / `RedisCache`）**

- `lib/PageCache.php` 是页面级缓存封装，内部已用 `FileCache`，保留但不再另起炉灶。

---

## 五、数据存储（两套分工明确）

| 存储 | 用途 | 文件 |
|------|------|------|
| JSON 文件 | 内容型数据（文章/课程/社区/配置） | `data/*.json` |
| SQLite | 关系型/高频写（会员/订单/日志） | `data/db/openflow.db` |

规则：**内容读多写少用 JSON，事务/关系用 SQLite**，不混用。

---

## 六、站点配置（唯一入口）

**唯一入口：`lib/SiteConfig.php`（`site_config_get()`）**

- 品牌名/标语/联系方式等一律走 `site_config_get('key')`
- 不要在页面里硬编码品牌名（历史遗留的硬编码已清理）

---

## 七、新增功能规范

1. **先查表**：本文档 + `grep -rn "关键词" lib/`，确认没有现成实现
2. **单一实现**：同一功能只在一处实现，其他地方 require 复用
3. **命名一致**：模块名 = 功能名，不造同义词（如 GrowthDriver vs GrowthEngine 二选一）
4. **不留旧品牌**：文案/字段名/注释禁止出现 flowcoming、幸福、Well Q、wellbeing、wellq、心理学、职场等历史词
5. **不留备份**：工具产生的 `.bak*` 文件不入库（已在 .gitignore 排除）
6. **不留死代码**：删除旧实现后，跑 `qa-check.sh` 确认无 0 引用残留

---

## 八、历史遗留债务（待阶段 2/3 处理）

| 债务 | 位置 | 影响 |
|------|------|------|
| `flow-community` 旧页面名 | config.php、admin/*（15 文件）、data/pages/flow-community.json | 后台 CMS 历史页面 |
| `wellq_*` 字段名 | config.php、admin/config.php、page-preview.php、page-editor-config.php 等 6 处 | 字段名仍是幸福度模型名 |
| GrowthDriver vs GrowthEngine | lib/ | 两个增长引擎概念重叠 |
| CdpSystem / CdpInsight / CdpSync | lib/ | CDP 功能分三处 |
| FlowSystem / CanvasSystem / AutomationSystem | lib/ | 流程编排可能重叠 |
