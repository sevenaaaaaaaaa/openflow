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

前提：仓库原本**没有** `package.json` / `composer.json`。任何 TS 化都先要有构建与依赖管理。

> **进展（2026-09-18）**：步骤 1 已完成 —— `package.json` + `tsconfig.json` + esbuild 构建链就位，
> 首个 TS 样板已上线：`src/lib/dialog.ts`（弹窗状态机）、`src/lib/palette.ts`（命令面板过滤），
> 由 `src/shell/index.js` 消费并打包回 **原路径** `assets/site-shell.js`（IIFE，HTML 零改动）。
> 单测用 Node 24 原生 `node --test`（无需 vitest）。顺带修掉一个真 bug：
> **命令面板输入框此前只有 placeholder、没有任何过滤逻辑**，现已支持过滤 + ↑↓ 高亮 + Enter 打开。
> 命令：`npm run typecheck` / `npm test` / `npm run build`；`scripts/ci.sh` 已把三者纳入门禁
> （含「构建产物是否最新」校验，防止忘记构建就提交）。
>
> **第 2 步（角色系统）已完成**：`role-content.js`（纯数据）→ `src/roles/content.ts`（类型化），
> `role-switch.js` → `src/roles/switch.js`，纯逻辑抽到 `src/lib/roles.ts`
> （角色校验/落地页/CTA 绑定/顺序循环/首次浮层判定/UTM 渠道推断），**累计 32 个 TS 单测**。
> 构建改为多入口（一表加一行即接入新迁移），banner 改为**确定性**（不含日期/commit，
> 否则每次提交都会让「产物是否最新」校验失效）。两个首页的角色脚本版本号也从硬编码
> `?v=3/?v=4` 统一为 `?v=OF_SHELL_VER`。

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

**已完成的第 1 步（样板）**：`package.json`（esbuild + typescript + @types/node）+ `tsconfig.json`（strict）
+ `src/lib/{dialog,palette}.ts`（17 个单测）+ `src/shell/index.js`（原 site-shell.js 迁入，消费上述模块）
+ `scripts/build.mjs`（打包回 `assets/site-shell.js`，带构建 banner/rev）。
第 2 步建议：把 `role-switch.js` / `role-content.js` 同样迁入 `src/`，并把 `inject.js` 的短逻辑并入；
每次迁移都按「先抽纯函数 + 单测 → 再改 DOM 适配」的节奏，产物仍走 `OF_SHELL_VER + sync-r2 + purge`。

---

## 六、工程质量（本期已做 + 下一步）

### 已做
- `scripts/ci.sh`：一条命令跑 **128 测试 + 故事线审计 + 设计护栏**，任一失败即非零退出（可挂 pre-commit/CI）。
- 修复 `admin/ingest.php` 缺 curl 超时（`CONNECTTIMEOUT 5 / TIMEOUT 20`）。
- 弹窗行为层（§三）+ 首次浮层层级缺陷。
- `scripts/audit-devices.py`：多设备 + 弹窗的可回归审计（可入 CI）。

### 部署器（2026-09-18 完成）

`scripts/deploy.py` 取代 shell + rsync：

| 能力 | 说明 |
|------|------|
| **md5 双向断言** | 上传前后各比对一次远端 md5；不一致即失败（根治 rsync 静默失败） |
| 逐文件 scp | 不用 rsync；文件级重试与失败清单 |
| 安全清单 | 目录白名单 + 根文件白名单；拒绝 `src/ tests/ node_modules/ *.bak* *.old-*`；`data/` 需 `--with-data` 显式允许 |
| 备份 / 回滚 | 替换前抓回远端旧版到 `.deploy/backups/<stamp>/`；`--rollback <stamp>` 一键还原并核验 |
| manifest | 每次部署写 `.deploy/manifests/<stamp>.json`（路径 + md5） |
| dry-run | 只打印计划（含"跳过/上传"判定），零副作用 |
| 联动 | assets 变更 → 提醒 bump `OF_SHELL_VER` → 自动 `sync-r2.py` → 按文件推导 URL 做 CF purge → 清 `data/cache` |
| 单测 | `python3 -m unittest discover -s scripts/tests`（10 项，已入 CI） |

实测：dry-run 对真实服务器正确判定"跳过"；robots.txt 走完 上传→核验→备份→回滚 全链路。

### 下一步（按性价比排序）
| 优先级 | 事项 | 说明 |
|---|---|---|
| P0 | `package.json` + esbuild/vitest/tsc | TS 化的前置；同时给前端加 lint |
| ~~P0~~ | ~~`deploy.py` 取代 shell 部署~~ | ✅ **已完成**（见 §七）：逐文件 scp + md5 双向断言 + 备份/回滚 + dry-run + R2/purge 联动 |
| P1 | 拆 `admin/config.php`（2,386 行） | 按职责拆 `lib/{auth,articles,forms,cache}.php`，保持函数签名不变 |
| P1 | z-index 令牌 + `of-dialog` 共享化 | 见 §三/§四 |
| P1 | 断点收敛（5 档）+ 组件清单 CI | 见 §四 |
| P2 | 清理 `TODO/XXX`、`print_r`、`console.log` | 低风险，顺手做 |
| P2 | 数据体检脚本（slug 碰撞等） | 已知 1 处 `uniclipboard` 待人工决策 |

---

## 七、语言选型与静态分析（2026-09-18）

### 7.1 JS vs TS：判据（写死，避免后续会话各行其是）

| 判据 | 结论 |
|------|------|
| **抽成 TS** | 纯逻辑 / 有数据契约 / 被多处消费 / 是状态机 / 将被重构 / 单测能证明价值 |
| **留在 JS** | 命令式 DOM 胶水、一次性页面行为、vendor、churn≈0 的代码 |
| **不迁移** | PHP 页面内联 `<script>`（271 处）；vendor（live2d / sw.js）；`inject.js` 整体（776 行、49 个页面硬编码引用，胶水为主——只抽其中纯函数） |
| **边界风险** | TS 类型是"带注释的信任"：`window.OF_NAV / OF_ROLES` 由 PHP 注入，**运行时形状未被校验**，需要运行时校验或类型生成 |

现状：TS 只覆盖前端最值得的一层（`site-shell` 弹窗状态机、`palette` 过滤、`roles` 角色/UTM）＝ **32 个 TS 单测**。
而本仓 83% 代码是 PHP（835 文件 / 2% 开 strict_types），**最大的静态分析杠杆在 PHP 侧**。

### 7.2 PHPStan 分级摸底（level 0 / 2 / 5）

| 级别 | 首测 | 处理 | 现状 |
|------|------|------|------|
| 0 | 15 | 修掉 3 个真 bug（见下）+ 清掉误入仓库的 composer-setup.php | **10** |
| 2 | 1135 | 加 `phpstan-bootstrap.php` 声明运行时常量（`DATA_DIR` 等） | — |
| 5 | 1394 | 同上：**1040/1394（75%）只是 `constant.notFound` 噪音** | **354 → 已生成基线** |

- 配置：`phpstan.neon`（level 5、`phpVersion: 80300` 对齐生产、排除 vendor/data/tests/src）
- 基线：`phpstan-baseline.neon`（354 条），基线内 **0 错误** → CI 只拦**新增**问题
- 接入：`scripts/ci.sh` 第 0.6 步

### 7.3 PHPStan 抓到的真 bug（level 0 就能抓到）

| 位置 | 问题 | 影响 | 处理 |
|------|------|------|------|
| `api/growth-signal.php:33` | 调用不存在的 `GrowthEngine::recordActivity()` | 被 try/catch 吞掉 → **活跃时段记录一直静默失败**（数据从未落盘） | 实现 `recordActivity()` + `activityHours()`（24 桶直方图）+ 6 项契约测试 |
| `admin/config.php:648` | 调用不存在的 `smtp_send()` | 配了 SMTP 时**密码重置直接 Fatal** | 缺实现时优雅降级（返回明确错误 + `error_log`）；SMTP 实现列为待补 |
| `admin/inbound.php:124` | 调用不存在的 `site_url_base()` | 入站连接器页面 **Fatal** | 改用既有 `site_config_get('site_url')` |

### 7.4 Ruff（Python，21 个脚本）

| 项 | 首测 | 现状 |
|----|------|------|
| 全部 | 257 | — |
| 真实缺陷 | `F401/F841/F541/B005/E722/F601` | 全部修掉 |
| 风格噪音（`W293/E701/E702`） | 106+76 | 配置忽略（运维脚本刻意保持紧凑） |
| 范围 | 全仓 | 收敛为维护中脚本（`bin/`、`tests/visual/` 为历史一次性脚本，排除） |
| 现状 | — | **All checks passed**（接入 CI 第 0.7 步） |

**顺带修掉一个性能 + 潜在缺陷**：`sync-r2.py` 里 `src_unchanged` 变量定义了却没用
（正是我上次改 WebP 逻辑时的漏改）→ 每次同步都**重建 440 张 WebP**（耗时约 5 分钟）。
修复后源图未变更即复用：实测 **6 秒**完成，输出 `WebP 0（复用 440）`。

### 7.5 strict_types 与 PHPStan 收敛（2026-09-18 续）

| 项 | 之前 | 现在 |
|----|------|------|
| `lib/` strict_types | 1/219 | **219/219（100%）** |
| 全仓 strict_types | 18/835（2%） | **236/836（28%）** |
| PHPStan level 5 | 1394 | **354 → 303**（已入基线，基线内 0 错误） |
| CI 门禁 | 6 道 | **9 道**（+PHPStan / ruff / strict_types 棘轮） |

- 全量加 `declare(strict_types=1)` 后：**129 测试 + 60 页渲染烟测全绿**，PHPStan 无新增类型错误
- CI 加**覆盖率棘轮**（`0.8/5`：strict_types 覆盖只许升不许降）

**本轮 PHPStan 又抓到的真 bug（8 类）**
| 位置 | 问题 | 影响 |
|------|------|------|
| AgentRuntime / AutomationSystem / GeoCitable / SeoAudit | `save_article()` 签名是 `(string $id, array $data)`，却只传数组 | 生产必然 TypeError（被 1 参测试桩掩盖，桩已统一） |
| AutomationSystem `send_wecom` / `send_wechat` | 步骤引用了**不存在的函数** | 跑到该步 Fatal → 已补两个实现 |
| ModerationSystem | 对 PDO 调 `execute()` | 屏蔽评论时致命 → 改 `prepare+execute` |
| FlowSystem | `$conv_track([...])` 把变量当函数 | 转化回传失效 → 改 `conv_track()` |
| GrowthEngine | 活跃分布 `str_contains($key, $k)` 用了 int 索引 | 分布统计错误 → 改 `$label` |
| userloop-bridge | `Wecom::sendAppMessage()` 参数缺失 | ArgumentCountError → 按真实签名传参 |
| PluginSystem / PaymentChannel / CdpSystem / CptSystem | 死代码 / 重复数组键 / 数组与 0 比较 / 变量未初始化 | 各 1 处 |

### 7.6 deploy.py 实战与修正

用新部署器上线本轮 225 个文件时发现**自身性能缺陷**：逐文件一次 ssh 校验 → 200+ 文件 = 400+ 次远程调用（首次执行 15 分钟超时）。
已改为**并行上传（6 并发）+ 一次批量 md5 校验**，并加上进度输出。另发现 `url_for()` 只知道路径映射、不认识 `.htaccess` 重写路由（如 `home-v2.php → /demo/home-v2`），部署后需按实际路由补 purge。
