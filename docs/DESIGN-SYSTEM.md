# OpenFlow 前台设计系统

> `assets/tokens.css` 的头注释引用了「设计系统文档 #shell 章节」，但那份文档在仓库里不存在——
> 契约只剩代码没剩说明，于是每个新页面都靠猜。这份文档把契约写回来。
>
> 一句话：**首页是标准答案，其它页面用首页的零件拼，不许自己造零件。**

---

## 一、三层结构

```
assets/tokens.css    设计契约 —— 颜色（全 oklch）、字体、圆角、阴影、缓动、暗色模式。49 个变量。
assets/modules.css   外壳（顶栏 / 侧栏 / 命令面板 / 登录弹窗）+ 全部 archetype（页面拼装零件）。
页面 <style>          只放这一页独有、别处不会再用的部件。上限见第四节。
```

加载顺序固定：`fonts.css → tokens.css → modules.css → 页面 <style>`。
页面样式在共享层之后，所以**页面可以覆盖共享层，共享层永远不知道页面的存在**。

历史包袱（2026-09-01 前）：首页把 464 行 archetype 与「密度放宽」锁在自己的 `<style>` 里，
其它页只能拿到 modules.css 里"小气"的默认值，于是各自复制粘贴出 139～191 行私有 CSS，
越改越散。v7 把这些全部收进 modules.css（374 → 731 行），首页私有样式降到 40 行，
只剩信号流画布 `.arena`。2026-09-02 起前台 **全部 41 个 PHP 页面** 都在这三层上，
`tailwind-build.css` 与 `standalone.css` 在前台不再被任何页面引用。

两个共享 include 把 `<head>` 与页脚也收成一处：

```
includes/site-head.php    of_head_assets()  主题早绑定脚本 + fonts / tokens / modules 三条 <link>，版本号取 OF_SHELL_VER
includes/site-footer.php  of_footer()       全站同一个 .foot（4 列 + 版权行）；改页脚文案只改这里
```

---

## 二、#shell · 浏览器外壳契约（tokens.css 引用的那一章）

外壳由 `includes/site-nav.php` 的 `of_shell('<page>')` 在 `<body>` 第一行注入，
渲染全部在 `assets/site-shell.js`。四条硬约束，任何迭代不得移除或简化：

1. **红绿灯永远在导航最左侧**（`.lights` 三个 `.light-r/.light-y/.light-g`）。
2. **导航真居中且保持浏览器标签页形态**：`#chrome .bar` 是 `1fr auto 1fr` 三栏网格，
   中栏 `.topnav .tab-pill`。曾有人改成 `auto auto 1fr auto` 把导航挤到左边，已回退，别再犯。
3. **侧栏三态** `body[data-sb=full|rail|closed]`，窄屏变 `drawer`。宽度走 `--sb-w`。
4. **所有动效走 `--ease-spring`**（Arc 原版弹簧 `cubic-bezier(.32,.72,0,1)`）。

`data-page` 决定导航高亮，合法值：`home product capability courses articles marketplace community events navigation about`。
学院及其子页（docs / tools / podcasts / downloads / category / topics / search / author）都归 `articles`；
`account`（用户中心）不高亮任何顶级项，侧栏空间名显示「个人中心」。功能页按归属借用：shop / asset → `marketplace`，
live → `events`，consultation → `enterprise`，course-player → `courses`，landing → `articles`。

外壳还统一提供：`.reveal → .in` 的滚动显现观察者、`#backtop` 回到顶部、
以及 opt-in 的通用 tab（`[role=tablist][data-tabs]` + `aria-controls`）。页面不要再各写一份。

---

## 三、archetype 目录（页面拼装零件）

每个零件都来自首页，名字就是首页里的名字。**先在这张表里找，找不到再考虑新增。**

| 用途 | 类 | 在哪些页看效果 |
|---|---|---|
| 首屏 · 居中 | `.hero-center` › `.kicker` `h1` `.lead` `.cta-row` `.trust` | 首页、about、product、capability、courses、enterprise、events、community、navigation |
| 首屏 · 双栏（文字 + 窗） | `.hero` › `.hero-copy` + `.hero-win` | academy |
| 节标题 · 居中 | `.sec-head.center` › `.kicker` `h2` `.lead` | 所有营销页 |
| 节标题 · 行式（带「全部 →」） | `.sec-head.row` › `div{.kicker h2}` `.more` | academy、navigation 楼层 |
| 三栏对照（中栏高亮） | `.worlds` › `.w-col` `.w-gap` `.w-tag` `.w-q` | 首页「两个世界」、about「派的三层」 |
| hairline 列（任意内容） | `.cols` / `.cols.n4` › `.ic` `.ltr` `h3` `p` | product 痛点 / TIPS、capability 部署 / 生态、courses 免费资源 |
| 大数字条 | `.stats` › `.st` `.st-n` `.st-en` `.st-t` | about、product 价值 |
| 工作流 / 学习路径 | `.wf` / `.wf.n4` › `.wf-step` `.wf-n` `h3` `p` `.tags` | 首页三步、about 主张、courses 课程体系 |
| 时间轴 / 编号三栏 | `.tl` / `.tl.n4` › `.tl-step` `.tl-n` `.tl-y` | about 历程、capability & courses「适合谁」 |
| 一大三小 | `.scn` › `.scn-f`(`.f-tag`) + `.scn-s`(`.scn-row`) | 首页场景、about 思想源流、capability 场景、courses 收获 |
| 双栏交替（文 + 窗） | `.split` / `.split.rev` › `.sp-txt`(`.sp-list`) + `.sp-vis`(`.sp-win` / `.hero-win`) | product 四段能力、about 创始人 |
| 玻璃窗 | `.sp-win` / `.hero-win` › `.win-bar`(`.light×3` `.url`) + 内容 | 承载任何演示件：`.win-flow`+`.flow-row`、`.conn-chips`、`.mock-canvas`… |
| tab 聚合 | `.tab-bar`(`.dense`) › `.tab-p`；`.tab-panel.on` › `.tp-txt` + `.tp-steps`(`.tp-step`) | 首页场景、capability 六项能力；也可只用 `.tab-bar` 做筛选 |
| 覆盖式 Deck | `.deck` › `.deck-stage` `.deck-p.on` `.deck-cta` + `.auto` `.prog` | 首页 TIPS 四力 |
| 对比表 | `.cmp-wrap` › `.cmp` `td.ol` `.y` `.na` `.cmp-note` | 首页 |
| 引语栏 | `.qr` › `.q-i` `.stars` `blockquote` `.who`(`.av`) | 首页、product、courses 评价 |
| 入口网格 | `.link-grid` › `.link-it`(`.top` `.dashed`) `.ic` `.lt` `.go` | about 加入门派、academy 资料 / 播客 / 空楼层 |
| FAQ 手风琴 | `.faq` › `.fq` `.fq-q`(`.fx`) `.fq-a` | product |
| 预约 / 申请（文 + 表单卡） | `.contact-wrap` › `.ct-pitch`(`.ct-list`) + `.form-card`(`.form-grid` `.field` `.inp`) | 首页预约诊断、enterprise 申请 |
| 收尾 CTA | `.cta-band` › `.kicker` `h2` `.lead` `.cta-row` | 所有营销页的最后一节。**取代旧页的紫色渐变横幅**（调色板外，已全部下线） |
| 提示条 | `.strip` › `.ic` `.tx` `.btn` | academy 学习路径联动、navigation 编辑首推 |
| 内容卡 | `.a-grid` › `.a-card`(`.cov` `.bd` `.cat` `h3` `.meta`) | academy 文章 / 视频、shop 商品、live 直播、landing 专题 |
| 主栏 + 侧栏 | `.g-main-aside`(`.aside-left`) › `div` + `aside`(`.aside-box` `.rank`) | academy、navigation、community、asset、course-player、live 直播间、member（左导航） |
| 正文阅读 | `.reader` › `.art-head` `.art-meta` `.art-cover` `.prose` `.actions`(`.act`) `.gate`(`.gate-box`) | article、community-post、event、asset 介绍 |
| 弹层 | `.modal`(`.open`) › `.mbox` `.mhead`(`.mx`) `.mbody` | downloads 门禁表单、marketplace 安装 |
| 生成式封面 | `.gcov`(`.h-accent/.h-ok/.h-warn/.h-danger/.h-neutral` `.lg`) › `.gc-code` `.gc-k` `.gc-t` | 无图的文章卡（`lib/CoverRenderer.php`）、生态资产卡（`mkt_asset_cover()`） |
| 空状态 | `.empty` | 所有列表页 |
| 页脚 | `.foot` › `.fb`(`.brand` `.f-about` `.note` `h4` `a`) `.f-bottom` | 所有页 |

原子件：`.btn`(`.primary` `.ghost` `.subtle`) `.card`(`.hov`) `.badge`(`.ok` `.warn` `.danger`) `.pill`(`.hl` `.neutral`)
`.kicker` `.note` `.mono` `.si`(渐变强调) `.tags` `.grid .g2/.g3/.g4` `.form-grid` `.field` `.inp`。

功能页（表单 / 列表 / 控制台）的通用做法：面板 = `.card`，表单 = `.form-grid` › `.field` › `.inp`，
状态词 = `.badge.ok/.warn/.danger` 或 `.pill.neutral/.hl`（颜色只从 token 来，lib 里遗留的 hex 状态色表只给后台用），
结果消息 = 页面私有 `.msg.ok/.err`。member.php 是这套做法最完整的样本（16 个面板，私有 CSS 44 行）。

### 图标

全站图标只有一种：24×24 线框 svg（`stroke-width:1.8`，圆角端点），颜色跟 `currentColor`。
**不直出 emoji**——后台数据里配的 emoji（导航分类、市场类型、消息类型、话题）到前台一律映射：
`includes/nav-icons.php`（导航分类 / 地区 / 站点 favicon + 首字母兜底）、`mkt_type_icon()`（市场类型）、
`CoverRenderer::PALETTE`（文章分类）。评分星 ★ 与 NPS 表情是刻意保留的两处例外。

### 新增零件的规则

- 第二个页面要用同一个东西时，才进 modules.css；只有一页用的留在页面 `<style>`。
  （产品页与能力页的 `.conn-chips` 现在是两份私有拷贝，第三处出现就收编。）
- 必须用 token：**零 hex、零 rgb、零 tailwind 类**。颜色派生用 `color-mix(in oklab, …)`。
- 必须给 ≤1080 / ≤860 / ≤640 三档降档，并同时在亮暗两色下检查。
- 命名沿用首页的短名风格（`.tl-n` 不是 `.timeline-node-number`）。

---

## 四、页面接入契约

一页从头写，骨架只有这些：

```php
<?php require_once __DIR__ . '/admin/config.php'; /* 数据准备 */ ?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>…</title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>/* 只放本页独有部件 */</style>
</head>
<body data-of-main>
<?php of_shell('product'); ?>
<a class="skip" href="#main">跳到主要内容</a>
<main id="main">
  <section id="top" class="reveal in"> <div class="hero-center">…</div> </section>
  <section id="…" class="sec reveal" data-od-anchor> <div class="sec-head center">…</div> <!-- archetype --> </section>
  …
  <section class="reveal"> <div class="cta-band">…</div> </section>
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" aria-label="回到顶部">…</button>
</body></html>
```

对外独立页（`survey.php` `survey-my.php` `nps.php`：由外链打开、不该带站点导航）不接 `of_shell()` 也没有页脚，
其余契约照常；它们列在契约测试的 `$STANDALONE` 名单里。

硬规则（`tests/design_contract_test.php` 会检查前四条；根目录任何新 .php 只要引了 tailwind 也会被兜底检查抓到）：

| 规则 | 为什么 |
|---|---|
| 页面私有 `<style>` ≤ 60 行 | 超过就说明在重新发明零件。首页 40 行，其余页 0～44 行 |
| 不引 `tailwind-build.css` | 两套体系并存就是"视觉不统一"的根源 |
| 页面 CSS 里零 hex 色（`#xxxxxx`）、零 `rgb(`、零 `linear-gradient(…, #fff …)` | 调色板外的颜色一律来自它 |
| 页面自己不定义 `.btn .card .sec-head .kicker .foot .stats .hero` 等共享类 | 页面覆盖共享类只能改值，不能重定义 |
| 内容服务端直出，不用 JS 拼 HTML | 爬虫可见；about / product / capability / courses 四页原本各有 3～6 段 JS 渲染的正文 |
| `section+section` 的间距交给共享层，不要自己加 margin | 节奏统一 |

---

## 五、改动前后怎么证明「没弄坏」

```bash
php -S 127.0.0.1:8890 -t . &
python3 tests/visual/snap.py /tmp/before index.php about.php …    # 改之前
# …改…
python3 tests/visual/snap.py /tmp/after  index.php about.php …
python3 tests/visual/snap.py --diff /tmp/before /tmp/after
```

`snap.py` 用真 Chromium 在 **亮 / 暗 × 桌面 1280 / 手机 390** 四种状态下拍整页，逐像素比。
它会关掉动效、预置角色浮层已选、固定主题，所以两次结果可复现（不改任何东西连拍两次 = 8/8 一致）。

约定：**改共享层时首页必须逐像素不变**（除非就是在修首页 bug，那要在提交说明里写清哪一节变了）。
已知噪声：手机端「提交预约」按钮阴影边缘偶有几十到一千个像素的合成抖动，肉眼不可辨，
出现在 (190~360, 页尾) 区域即可忽略。

方案比选：`tests/visual/mock/*.php` 是用真 tokens 渲染的备选方案（如 events-b / community-b），
只用于截图对比，不是站点页面。

会员页要带登录态拍：先 `curl -c jar -d 'action=login&account=…&password=…' /api/member.php`，
再 `OF_COOKIE="PHPSESSID=…" python3 tests/visual/snap.py OUT "member.php?view=dashboard"`。
带 query 的页面直接写 `page.php?a=b`，截图文件名保留 query。

---

## 六、迁移状态（2026-09-02）

前台 41 个页面全部在三层结构上，契约测试 `$PENDING` 为空、393 项全过。分三批完成：

| 批次 | 页面 |
|---|---|
| v7 · 营销 10 页 | index about product capability courses academy enterprise navigation events community |
| 内容页族 | marketplace article articles category docs downloads podcasts author search topics |
| 功能页族 | event community-post reviews messages activate nps download navigation-site survey-my front-builder · shop live tools consultation · survey asset course-player · member · landing thank-you seo-board |

新页面照第四节骨架写，写完把文件名加进 `tests/design_contract_test.php` 的 `$MIGRATED`（对外独立页另加 `$STANDALONE`）。
不加也会被兜底检查抓到，但名单本身是进度表，请维护。

`admin/` 与 `api/` 不在本设计系统范围内（后台仍是 tailwind）。
