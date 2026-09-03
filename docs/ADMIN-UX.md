# 后台交互框架层（ADMIN-UX）

> 2026-09-03 · v1。后台 190+ 页共用一套外壳（`admin/config.php` 的 `admin_header / admin_sidebar / admin_footer`），
> 交互层的问题（导航迷路、原生弹窗、长表单找不到保存、长表格没有分页、emoji 当图标）都在这一层一次性修，页面本身不用改。
> 本文写的是**约定**：新页面照着写就自动获得这些能力；破坏约定 `php tests/admin_contract_test.php` 会红。

## 一、组成

| 文件 | 作用 |
|---|---|
| `includes/admin-nav.php` | 导航树（数据）：7 个区 → 分组 → 条目；权限过滤；`ADMIN_NAV_ALIAS` 把子页（article-edit、customer-detail…）归到父条目；`admin_nav_locate()` 按**脚本名**定位当前页 |
| `assets/admin-ui.css` | 侧栏两级结构 + 三态、统一对话框、粘性保存条、表格工具条、图标尺寸；颜色全部来自 admin_header 的 token |
| `assets/admin-ui.js` | 五段独立 IIFE：侧栏 / 对话框 & toast / 表单 / 表格 / 图标。只依赖 DOM 约定 |
| `admin/config.php` | `admin_sidebar()` 变成 5 行：调 `admin_nav_render()`；`admin_header` 挂 css、`admin_footer` 挂 js；版本号 `OF_ADMIN_UI_VER` |

## 二、导航

* **置顶（4）**：工作台 · 经营驾驶舱 · 内容日历 · 自我进化。跨区常用，永远钉在侧栏最上面，不属于任何区；数据见 `admin_nav_pinned()`。
* **三级**：左侧 60px 区 rail = **产品框架本身**（触达 Touch / 洞察 Insight / 个性化 Personalize / 销售 Sales / AI 引擎 / 系统），
  中间是当前区的**聚合入口**（52 个），进页面后顶部再出现**簇内子 tab 条**在兄弟功能之间切换。对外讲 TIPS+AI、对内找功能是同一套词。
* **聚合入口 = 一个条目带一簇兄弟页**：条目写 `subs`，第一条是默认落点，`id` 自动取它。侧栏只显示条目（带条数角标 + 一行 hint），
  兄弟页由 `admin_nav_cluster_bar()` 渲成子 tab 条，**点击是真跳转**——每个子页仍可单独收藏、单独直达、单独设权限。
  子 tab 条由 `admin_wrap_body()` 在框架层注入 `.main` 开头，所以 90 多个页面一行都没改。
* **当前页定位不靠页面传的 id**：`admin_sidebar('settings')` 这种历史遗留参数只做兜底，实际用调用方文件名（`audit-log.php → audit-log`）
  加 `?tab=` 拼子条目（`crm.php?tab=raw → crm-raw`）。所以新页面**文件名 = 导航 id** 就自动高亮；子页在 `ADMIN_NAV_ALIAS` 加一行。
* **三态**：顶栏第一个按钮循环 full → rail → closed，存 `localStorage.of_sb`；rail 态点任一区图标自动回 full。窄屏（≤840px）是抽屉 + 遮罩。
* **最近打开**：`localStorage.of_admin_recent`，最多 6 条，按名称去重，放在当前区面板底部。
* 首屏不做侧栏过渡动画（`body.sb-anim` 两帧后才加），避免恢复 rail 态时「飞」一下。

## 三、确认与提示（不再有原生弹窗）

```html
<!-- 链接：确认后跳 href -->
<a href="?delete=<?=$id?>&csrf_token=…" class="btn btn-danger btn-sm" data-confirm="确认删除该文章？">删除</a>

<!-- 表单里的按钮：确认后提交所在表单（保留按钮的 name/value） -->
<button name="withdraw_action" value="approve" data-confirm="确认打款 ¥<?=$w['amount']?>？">通过</button>

<!-- 整张表单 -->
<form method="post" data-confirm="确认从此备份恢复？当前数据将被备份后覆盖">…</form>
```

* 文案含「删除 / 清空 / 退款 / 重置 / 停用 …」自动走红色危险样式，标题从文案里抠出动作名（「确认删除该文章？」→ 标题「删除该文章」）。
* 元素自带的 `onclick` 会**先**执行，返回 `false` 就不弹框——用于前置校验（authors.php 的「合并」）或 `event.stopPropagation()`。
  `data-confirm` 的元素上**不要**把真正的动作写进 `onclick`；不在表单里、也没有 href 的按钮，确认后派发 `of:confirmed` 事件。
* JS 里：`if (!await ofConfirm('…')) return;`（函数改 `async`），或 `ofConfirm({title, message, okText, danger}).then(ok => …)`。
* 提示：`ofAlert(msg, type?)`，走右下角 toast；不传 type 按文案猜（失败 / 错误 → error，成功 / 已 → success）。

## 四、表单

* `method="post"` 且有 ≥2 个字段的表单自动：脏检测 + 离开页面提示（`beforeunload`）。不想要就加 `data-no-guard`。
  包着 `<table>` 的批量操作表单、只有勾选框的表单不算编辑表单。
* 高度超过 0.9 视口且有提交按钮的最长那张表单，自动得到底部**粘性保存条**（「回到顶部」+ 主按钮，文案取自原按钮）；
  原按钮在视口内时保存条自动隐藏，避免两个保存按钮同时可见；有未保存修改时文案变「有未保存的修改」并亮点。

## 五、表格

* `<tbody>` ≥ 12 行的表格自动获得：筛选框（全文匹配）、行数、点表头排序（数字列按数值）、分页 20 / 50 / 100、空态。
* 不想要：`<table data-static>`，或外层容器加 `.no-of-table`；已经有服务端分页（旁边存在 `.pager / .pagination`）的表格会自动跳过。
* 表头文案含「操作」的列不排序。

## 六、图标

* 按钮 / 链接 / 表头 / 标题 / 标签 / 侧栏里的 emoji（🗑 ✏️ 📤 📥 ➕ 🔄 …共 60 余个）在运行时替换为 1.8px 线框 svg，尺寸 15px、随文字颜色。
* 新页面直接写 svg（`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">`）；写 emoji 也行，会被替换，但别在 `<pre>/<code>` 里指望替换。

## 七、约定清单（新页面 checklist）

1. 文件名 = 导航 id；子页在 `ADMIN_NAV_ALIAS` 归父。
2. 破坏性动作：`data-confirm`，永远不要 `onclick="return confirm()"`。
3. 反馈：`ofAlert()` / `$_SESSION['_flash']`，不要 `alert()`。
4. 列表页优先服务端分页；没有也没关系，≥12 行自动前端分页。
5. 长表单不用自己写「保存」浮层。
6. `php tests/admin_contract_test.php` 必须全绿。

## 八、第二阶段：逐页重排（v12 · 2026-09-03）

框架层解决「每页都有」的问题；第二阶段按用户定的优先级逐页改信息架构。共用的列表页骨架（`lst-*`、`.of-selbar`、`.of-menu`、`.ib`）已提到 `admin-ui.css`，新列表页直接套：

```html
<div class="lst-filter">  <form class="lst-search" role="search">…</form> <select class="lst-sel">…</select> <span class="lst-count">N 条</span> </div>
<div class="card lst-card">
  <table class="lst-table">  <!-- table-layout:fixed；给非标题列写 width，标题列 class="c-title" -->
    <thead><tr><th class="c-check">…</th><th class="c-title">…</th><th style="width:120px">…</th><th class="c-act"></th></tr></thead>
    …
```

| 页面 | 原来 | 现在 |
|---|---|---|
| 写文章 `article-edit` | 单列 2671px；正文编辑器在 916px 以下；保存在 2535px | 顶部常驻操作条（返回 / 状态 / 导入 / AI / 更多 / 保存）；左正文（大标题 + slug 行 + 编辑器 62vh）右元数据（发布 / 归类 / 封面 / SEO 折叠 / JSON-LD 折叠）；Zen 模式保留 |
| 内容中心 `content-hub` + `articles` | 10 列表格、表头与单元格错位、标题被压到 80px；4 条工具栏叠着 | 6 列 fixed 布局（标题列吃剩余宽度，slug / 标签放标题下）；子页操作按钮提到 tab 行；筛选一行；勾选后才出现的底部批量条（`.of-selbar`）；行内「···」菜单收纳导出 / 复制 / SEO / 回收站 |
| 内容日历 `content-calendar` | 标题 + 工具栏 + 图例 + 提示 四段 | 一行工具栏（月份 / 今天 / 月周切换 / 图例 / ? 提示）；文章与资料颜色区分；导航归入「内容」区 |
| 营销自动化 `automation` | 每个步骤 12 个字段全部平铺，不管动作类型 | 步骤按动作类型只显示相关字段（邮件 3 / 延迟 1 / 通知 3 / 标签 1 / 积分 1 / 优惠券 4），头部有编号、摘要、上下移；列表显示「邮件 → 延迟 → 标签」链 |
| 活动 `campaigns` | 删除表单 HTML 断裂（无按钮无闭合）、删除无 CSRF；组件字段平铺 | 修好删除（带 CSRF + 确认）；列表显示组件链、排期、范围、真实状态（运行中 / 待开始 / 已过期）；组件编辑按类型显示位置 / 触发字段 |
| 用户分群 `segments` | 新建要手写「规则 JSON」 | 条件构建器（字段 / 运算 / 值，按字段类型给运算符）；卡片显示规则链、AND/OR；从分群直达「建自动化」 |
| CDP `cdp` | 「用户属性分布」「用户列表」写在 `admin_footer()` 之后（在 `</html>` 外面）；50 张用户卡片 | 归位到「用户画像」tab；用户列表改表格（前 200，自动筛选 / 排序 / 分页）；所有 inline grid 加响应式 |
| 订单 `orders` | 只有查询框 | 四个可点的状态卡（已支付金额 / 已退款 / 待支付 / 全部）即筛选；一行搜索；表格 fixed 布局 + 自动分页 |
| CRM `crm` | 9 个 KPI 卡（第 9 个掉到第二行）与阶段筛选按钮重复；详情面板 6 张表单平铺；面板里多了一个 `</div>` | 3 个金额卡 + 阶段漏斗 chips（数量 + 赢率，可点筛选）；详情分「阶段与商机 / 转客户（折叠，商机以上自动展开）/ 归属与评分 / 跟进」；转客户加确认 |
| 商城 `mall` | 表单在列表上面占满首屏；删除表单 HTML 断裂；无编辑 | 列表优先，右上「新增」；`?edit=` 进入编辑（表单带回填）；删除修好（带 CSRF + 确认）；库存低 / 售罄着色 |
| 会员 `membership` | 每行 select + 保存按钮；hex 颜色；emoji 权益表 | select 改了即存；token 色 KPI；权益表 ✓ / — 文本化 |

顺带修的真 bug：**22 处被截断的 `<form>`**（`campaigns / mall / analytics / approvals / categories / forms / landing-pages / moderation / page-builder / plugins / shop-settings / storage / topics`——`csrf_field()` 之后的按钮与 `</form>` 全丢了，讲师审核、投稿审核、提现打款、删除分类 / 表单 / 专题 / 页面、内容扫描、存储维护这些按钮从基线起就不存在；已按各页的 POST 处理逻辑逐一补回并加确认，契约测试新增 form/select 标签配对检查）、`campaigns` 删除无 CSRF、`cdp.php` 60 行正文在 `</html>` 之后、`crm.php` 详情面板多一个闭合标签、`config.php` 缺 `.badge-red`（15 个页面在用）。

## 九、第三轮：系统区 + 框架层两项新能力（v13 · 2026-09-03）

框架层新增两个「零改页面」能力，现在和以后所有页面都能用：

* **长表单分节目录**（`admin-ui.js` 3b）：POST 表单里有 ≥4 个带 `h2/h3` 的 `.card`、且高度超过 1.6 屏，右侧自动生成粘性目录并随滚动高亮；`.main` 变两栏（正文 + 188px 目录），≤1180px 隐藏。不想要就给 form 加 `data-no-secnav`。当前命中：系统设置（9 节）、广告位（5）、社区配置（4）、SEO 中心（4）。
* **页内 tab**（3c）：`<div class="of-tabbed">` 的直接子元素带 `data-tab="标题"`（可选 `data-tab-id`），自动生成 tab 条，一次只显示一个；hash / sessionStorage 记忆，提交后带 `.msg` 的那个 pane 自动置前。内容导入页 6 张表单由此从 2659px 变一屏。

| 页面 | 原来 | 现在 |
|---|---|---|
| 系统设置 `settings` | 3516px 一张表单 9 节，全靠滚 | 右侧分节目录 + 粘性保存条；emoji 去掉 |
| 角色与权限 `roles` | 87 个权限只显示英文 key，无分组全选 | 每项显示中文名 + key，每组可整组勾选（半选态）、显示 n/m，顶部显示已授权计数 |
| 后台用户 `users` | 顶部一张写死的「角色权限说明」表（与真实权限脱节） | 删掉假表，链到「角色与权限」；列表套 lst 骨架，标出「我」 |
| 健康检测 `health-check` | **整页被隐藏**：self tab 要求权限 `health-check`，但注册表里没有这个键，连超管都看不到，进来只看到「存储」 | 权限改 `settings`，健康评分页回来了 |
| 内容导入 `ingest` | 6 张表单纵向堆 2659px | `of-tabbed`：Obsidian / 平台 API / GitHub / Cloudflare / API 接入 / RSS |
| CRM 原始提交 `crm?tab=raw` | 直接把 CSV 的 9 列吐出来；「转跟进」按钮读 `$rl['email']`，而 CSV 表头是中文「邮箱」→ **按钮从来没出现过** | 固定 6 列（时间 / 提交人 / 联系方式 / 来源 / 留言 / 操作）；新增 `claim_raw` 动作，真正把提交转成跟进线索（带公司、来源、留言进跟进记录），已在跟进的显示「已在跟进 →」 |

顺带修的 PHP 警告（fresh install 或空数据时整页顶部一排 Warning）：`ai-config`（providers / global_prompts 未定义）、`publish`（variant）、`conversion`（四个组件配置为空时 30 处未定义键）、`live`（hls_url）、`seo-console`（GSC 缓存行缺字段）。走查脚本现在把 `<b>Warning</b>` 也算错误。

### 还没做

主题、API 分佣、事件字典、用户画像、导航站这些页量过没有硬伤，只有 inline style 多；不值得单独重排。emoji 只替换按钮 / 标题 / 标签里的，卡片正文里的装饰 emoji（增长驱动、自我进化）保留——那是内容而不是控件。

## 十、第四轮：置顶 + TIPS 骨架 + 聚合入口（v14 · 2026-09-03）

v1 的七个区（概览/内容/社区/增长/数据/商业/系统）把 148 条链接收进了区，但两件事做坏了，这轮修掉：

**① 17 个页面根本没有布局**。`consent` / `ai-usage` / `api-permissions` / `catalog` / `inbox` / `brain` / `cpt` / `dev-docs` 等
调了 `admin_header()` 却从没写过 `<div class="admin-layout"><div class="main">`——既没有侧栏，正文也没有任何边距，直接怼在顶栏底下的左上角。
与其逐页补 17 遍模板，改在框架层兜底：`admin_header()` 末尾 `ob_start()`，`admin_footer()` 里的 `admin_wrap_body()`
发现这一趟没人调过 `admin_sidebar()`，就把正文包进 `.admin-layout > .main` 再输出。新页面漏写模板也不会再破相。

**② 区里仍旧是平铺**：数据区一口气 27 条、系统区 33 条，比重排前更难扫，而且产品框架 TIPS+AI 在后台完全消失。
现在顶层 rail 直接是 TIPS 四象限 + AI 引擎 + 系统，区里是 52 个聚合入口，相邻功能收进同一个入口、进去再按需切换：

| 区 | 条目 | 覆盖页 | 典型聚合入口 |
|---|---|---|---|
| 触达 Touch | 9 | 38 | 素材库（数字资产·多媒体·图库）、学院与活动（课程·活动·直播·咨询·导航） |
| 洞察 Insight | 10 | 27 | 运营分析（实时·路径·传播·订阅）、埋点与接入（埋点·脚本·连接器·入站·外部） |
| 个性化 Personalize | 9 | 21 | 微信生态（公众号·群发·标签·客服·企微）、分群与画像 |
| 销售 Sales | 9 | 24 | 商城（商品·优惠券·设置）、分销与结算（分销·分成·API 分佣） |
| AI 引擎 | 6 | 10 | 增长大脑（决策·轨道）、自我进化（进化·协同修复）、AI Agent（模型·用量） |
| 系统 | 9 | 25 | 运维与部署（运维·健康·备份·CDN·SDK）、用户与权限（账号·角色·2FA·API 矩阵） |

合计 **52 个侧栏条目**（其中 41 个是聚合入口）+ 4 个置顶，覆盖原来的 143 个页面入口。

验证：`admin_contract_test` 1080 项（新增：簇内每个兄弟页都要真实存在、框架层两个注入点都要在）、177 个后台页 0 PHP 警告 0 JS 错误、
185 个页面量布局（175 个有 `.main`，其余是 lib / 需参数的子页）、41 个聚合入口 × 130 个兄弟页逐个验证子 tab 条在位、高亮不错位。

顺带：窄屏 `input[type=file]` 撑出横向滚动（数字资产页）已修。
