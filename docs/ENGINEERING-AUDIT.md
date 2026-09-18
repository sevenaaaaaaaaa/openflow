# 工程质量审计（2026-09-18）

> 目标：把「提高工程质量 / 非核心功能用 Python·TS 改写 / 前端统一性 / 多设备兼容 / 弹窗兼容」
> 五项从口号变成**有数据、有优先级、可回归**的清单。
>
> 复现命令：
> ```bash
> bash scripts/ci.sh                 # 测试 + 故事线审计 + 设计护栏
> python3 scripts/audit-devices.py   # 多设备溢出 + 弹窗行为（Playwright 真实测量）
> php scripts/storyline-audit.php
> php scripts/design-guard.php --all
> ```

---

## 一、基调数据（本次体检）

| 维度 | 现状 |
|------|------|
| PHP | 638 文件 / 117,065 行（含 vendor / admin / api / lib / plugins） |
| 前端 JS | 12 文件 / 4,046 行 |
| Python | 4 文件 / 272 行 |
| CSS | 7 文件 / 1,800 行 |
| 测试 | 128 个（全绿，`php tests/*_test.php`） |
| 依赖管理 | **无** `composer.json` / `package.json`（无构建、无类型检查、无锁文件） |
| 遗留调试 | `print_r` 4 / `console.log` 2 / `TODO` 14 / `XXX` 19 |
| 真·shell 执行 | 0（此前 86 处为 PDO `->exec` 误报） |
| 远程请求超时 | 94 处 `curl_init`，此前 1 处无超时（`admin/ingest.php`）→ **已修** |
| 最大自有文件 | `admin/config.php` 2,386 行（配置/权限/文章/表单/缓存混在一起） |

---

## 二、多设备兼容（已完成体检 ✅）

Playwright 实测 15 个前台页 × 7 档宽度（360/390/414/768/1024/1280/1440）：

**横向溢出：全部 0**（`document.scrollWidth - innerWidth`，阈值 2px）。

| 页面 | 360 | 390 | 414 | 768 | 1024 | 1280 | 1440 |
|---|---|---|---|---|---|---|---|
| 首页 / 产品 / 能力 / 首页v2 / 聚合页 / 能力全景 / 文章 / 专题 / 定价 / 导航 / 市场 / 下载 / 课程 / 学院 / 直播 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

结论：**响应式底盘是好的**，不需要大改；后续新段落只要走 §六·五 的护栏流程即可维持。

---

## 三、弹窗兼容（已完成修复 ✅）

### 修复前（真实测量，390px）

| 弹窗 | role | aria-modal | 滚动锁 | 焦点进入 | ESC 关 | 遮罩/外部关 |
|---|---|---|---|---|---|---|
| 登录 | dialog | ✓ | ✗ | ✗ | ✗ | ✗ |
| 个人中心 | dialog | ✓ | ✗ | ✗ | ✗ | ✗ |
| 命令面板 | dialog | ✗ | ✗ | ✗ | ✓ | ✓（自带遮罩） |
| 首次角色浮层 | 无 | 无 | ✗ | ✗ | ✗ | ✗ |

### 修复后

| 弹窗 | role | aria-modal | 滚动锁 | 焦点进入 | ESC 关 | 遮罩/外部关 |
|---|---|---|---|---|---|---|
| 登录 | dialog | ✓ | ✓ | ✓ | ✓ | ✓ |
| 个人中心 | dialog | ✓ | ✓ | ✓ | ✓ | ✓（未登录时回落到登录，属预期） |
| 命令面板 | dialog | ✓ | ✓ | ✓ | ✓ | ✓ |
| 首次角色浮层 | dialog | ✓ | ✓ | ✓ | ✓ | ✓（并让位给弹窗） |

### 做了什么（只补行为，**零视觉改动**）

1. `assets/site-shell.js` 新增通用弹窗行为层 `OFDialog`：
   `[data-of-dialog]` 标记 + `register(el, name, {open, close})`，统一处理
   **ESC / 遮罩或外部点击 / 滚动锁（含滚动条补偿 `--of-sbw`）/ 焦点进入**，并广播 `of:dialog-open`。
2. 三个既有弹窗（`#authModal` / `#profileModal` / `#palette`）注册进该层；开关仍走原函数。
3. `assets/role-switch.js`：首次角色浮层 z-index **9999 → 91**（低于弹窗 92），
   任何弹窗出现即让位（事件 + 700ms 轮询兜底），并接入统一行为层（ESC/遮罩 = 暂不选择）。
4. `live.php` 的 `#shopModal` 补 ESC 关闭（原本只能点关闭/遮罩）。

### 仍然存在的层级隐患（下一步）

z-index 目前**散落硬编码**：`.modal` 92 / `.palette` 91 / `.overlay` 90 / `.skip` 130 /
`#of-role-overlay` 91 / admin `.of-dialog` 99998 / toast 10000。
→ 计划新增 z-index 令牌刻度（`--z-nav/--z-scrim/--z-dialog/--z-toast/--z-onboard`），新代码只准用令牌；
存量不动，避免视觉回归。

---

## 四、前端组件与视觉统一性（待办，按优先级）

| # | 问题 | 证据 | 建议 |
|---|------|------|------|
| V1 | **弹窗有两套世界**：后台 `.of-dialog`（`assets/admin-ui.css/.js`，实现完备）vs 前台自绘（`.modal`/`.palette`/`.shop-modal`） | `of-dialog` 仅 admin 使用（15 条 CSS 规则） | 把 `of-dialog` 的样式与结构**提升为共享**（modules.css + site-shell 的 `OFDialog`），前台逐步迁移；本期已先把**行为**统一 |
| V2 | 断点漂移：18 种断点（900×19、860×17、640×11、1080×9、840/560/960/760/720/920/620/600/374/1199/1180/1000/520/1100） | `@media` 统计 | 收敛为 5 档：`560 / 640 / 900 / 1080 / 1200`；新代码只用这 5 档，存量按页面渐进 |
| V3 | 页面级 `<style>` 与内联 style 偏多 | `member.php` 89 行 style + 218 处内联、`course-player.php` 62/69、`hub-products.php` 86、`product.php` 75/38 | 把重复出现的「窗口 mock / 演示部件」抽到 `modules.css` archetype（与既有 `.sp-win` 同族）；动态值（`--tilt`）保留内联 |
| V4 | 组件无清单，靠记忆找 | 47 个区块 + 若干页面级 archetype | 生成 `docs/COMPONENT-INVENTORY.md`（class → 用途 → 出现页面），纳入 CI 校验 |

> 本期已提供的护栏：`scripts/design-guard.php` —— 新增且 CSS 未定义的 class 直接判失败。

---

## 五、非核心功能改用 Python / TypeScript（评估结论）

前提：仓库**没有** `package.json` / `composer.json`。任何 TS 化都先要有构建与依赖管理，因此分两步走。

### 5.1 Python 优先（低风险、立即可做）

| 候选 | 现状 | 改写理由 | 收益 | 风险 |
|------|------|----------|------|------|
| **部署编排** `deploy.sh` → `deploy.py` | shell + rsync | 本期踩过 rsync **静默失败**；Python 可做 md5 核对、白名单、失败即停 | 部署不再"以为成功" | 低 |
| **媒体处理/R2** `sync-r2.py` 扩展 | 已有 Python | 源图变更检测、多尺寸派生、孤儿文件清理、WebP 一致性 | 资产管线可测 | 低 |
| **内容/数据体检** `data_doctor.py` | 无（本期手工发现 slug 碰撞） | 文章 slug 去重、断链、孤立页、专题 id 有效性 | 数据问题前置发现 | 低 |
| **SEO/内链审计** | 部分在 PHP 测试里 | 全站爬取（Playwright/requests）+ 报告 | 与故事线/站点地图联动 | 低 |
| **定时任务**（巡检、备份、GSC 拉取） | PHP + cron | 与站点解耦，失败可重试可告警 | 降 PHP 进程耦合 | 低 |

### 5.2 TypeScript（需先引入构建，收益中期）

| 候选 | 做法 | 收益 | 风险 |
|------|------|------|------|
| `assets/site-shell.js`（外壳/弹窗/命令面板） | 拆 `src/shell/*.ts` + esbuild 打包成单文件（保持 `/assets/site-shell.js` 路径与 `?v=` 机制） | 类型 + 可测试（vitest）+ 体积控制 | 中（构建链） |
| `assets/role-switch.js` / `role-content.js` | 同上 | 角色逻辑可单测 | 中 |
| 表单校验 / 过滤 / 搜索索引（纯逻辑） | 抽 `src/lib/*.ts` | 单测覆盖，跨页复用 | 低 |
| **不建议迁移** | `live2d` vendor、PWA `sw.js`、`inject.js`（极短） | — | — |

**建议节奏**：先补 `package.json`（esbuild + vitest + tsc --noEmit），**第一个样板**选 `site-shell.js` 里
的纯逻辑片段（弹窗状态机/命令面板过滤）迁 TS 并加测试；验证流程后再批量。产出仍走现有
`OF_SHELL_VER + sync-r2.py + purge` 资产纪律。

---

## 六、工程质量（本期已做 + 下一步）

### 已做
- `scripts/ci.sh`：一条命令跑 **128 测试 + 故事线审计 + 设计护栏**，任一失败即非零退出（可挂 pre-commit/CI）。
- 修复 `admin/ingest.php` 缺 curl 超时（`CONNECTTIMEOUT 5 / TIMEOUT 20`）。
- 弹窗行为层（§三）+ 首次浮层层级缺陷。
- `scripts/audit-devices.py`：多设备 + 弹窗的可回归审计（可入 CI）。

### 下一步（按性价比排序）
| 优先级 | 事项 | 说明 |
|---|---|---|
| P0 | `package.json` + esbuild/vitest/tsc | TS 化的前置；同时给前端加 lint |
| P0 | `deploy.py` 取代 shell 部署 | 消除 rsync 静默失败；带 md5 断言与回滚点 |
| P1 | 拆 `admin/config.php`（2,386 行） | 按职责拆 `lib/{auth,articles,forms,cache}.php`，保持函数签名不变 |
| P1 | z-index 令牌 + `of-dialog` 共享化 | 见 §三/§四 |
| P1 | 断点收敛（5 档）+ 组件清单 CI | 见 §四 |
| P2 | 清理 `TODO/XXX`、`print_r`、`console.log` | 低风险，顺手做 |
| P2 | 数据体检脚本（slug 碰撞等） | 已知 1 处 `uniclipboard` 待人工决策 |
