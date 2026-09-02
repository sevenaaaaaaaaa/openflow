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

* **两级**：左侧 60px 区 rail（概览 / 内容 / 社区 / 增长 / 数据 / 商业 / 系统），右侧只展示**当前区**的分组条目。原来 148 个链接一屏平铺的问题由此消失。
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

## 八、还没做（第二阶段：逐页重排）

按用户定的优先级：内容（写文章 / 内容中心 / 内容日历）→ 增长（自动化 / 活动 / 分群 / CDP）→ 商业（订单 / CRM / 线索 / 商城 / 会员）。
框架层解决的是「每页都有」的问题；单页的信息架构（写文章页正文区 916px、内容中心 10 列表格挤压、设置页 3380px 一张表单）在第二阶段处理。
