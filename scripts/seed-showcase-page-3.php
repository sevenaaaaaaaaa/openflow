<?php
/**
 * 给示例页追加第三批 10 种区块（checklist/countdown/banner/team/code/kbd/spotlight/ticker/quote-wall/changelog）
 * 用法: php scripts/seed-showcase-page-3.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';
$pages = builder_pages_all();
$page = null;
foreach ($pages as $p) if (($p['slug'] ?? '') === 'growth-os-tour') { $page = $p; break; }
if (!$page) { echo "❌ 找不到示例页\n"; exit(1); }
foreach (($page['blocks'] ?? []) as $b) {
    if (($b['_type'] ?? '') === 'checklist') { echo "已追加过，跳过\n"; exit; }
}

$checklist = ['_type' => 'checklist', '_key' => $k(), 'title' => '上线前，这套系统替你做完的事', 'subtitle' => '开箱清单', 'content' => <<<HTML
<li>内容引擎：热点采集 → AI 选题 → 草稿 → SEO 检查 → 定时发布</li>
<li>数据底座：访客行为自动入 CDP，画像与分群实时更新</li>
<li>触达矩阵：邮件 / 公众号 / 短信 / 站内通知，一个画布编排</li>
<li>转化闭环：购物车、订阅计费、推荐码、佣金结算全链路</li>
<li>SEO 全家桶：sitemap、JSON-LD、IndexNow、GEO 全网采集</li>
<li>系统体检：自动扫描前后端异常，给出修复建议</li>
HTML];

$countdown = ['_type' => 'countdown', '_key' => $k(), 'title' => '', 'content' => '第 4 期 R.B.E 训练营报名通道，将于截止时间关闭。', 'end' => '2026-12-31 23:59:59'];

$team = ['_type' => 'team', '_key' => $k(), 'title' => '同一批人在维护这套系统', 'subtitle' => '团队', 'content' => <<<HTML
<div><b>内容引擎</b><span>爬热点 · 写初稿 · 发全网</span></div>
<div><b>数据底座</b><span>埋点 · 画像 · 分群</span></div>
<div><b>触达中枢</b><span>邮件 · 短信 · 站内信</span></div>
<div><b>AI 基础</b><span>四力 Agent · 模型编排</span></div>
HTML];

$code = ['_type' => 'code', '_key' => $k(), 'title' => '对接无头 CMS 只需三行', 'subtitle' => 'javascript', 'content' => <<<HTML
const r = await fetch('https://nownexts.com/api/public-content?type=articles&limit=12');
const { items } = await r.json();
render(items);  // 你的组件，你的样式
HTML];

$kbd = ['_type' => 'kbd', '_key' => $k(), 'title' => '每天用得最多的六个快捷键', 'subtitle' => '效率', 'content' => <<<HTML
<div><b>⌘ K</b><span>全局搜索：模块、内容、订单、线索</span></div>
<div><b>⌘ S</b><span>保存当前编辑</span></div>
<div><b>⌘ P</b><span>快速跳页面</span></div>
<div><b>⇧ ⌘ A</b><span>呼出 AI 助手</span></div>
<div><b>⌘ /</b><span>快捷键速查</span></div>
<div><b>Esc</b><span>关闭弹窗 / 退出预览</span></div>
HTML];

$spotlight = ['_type' => 'spotlight', '_key' => $k(), 'title' => '让 Agent 跑流程，<em>你只做判断</em>', 'content' => '已知方法交给 Flow 稳定执行，未知路径由受控 Loop 辅助判断。'];

$ticker = ['_type' => 'ticker', '_key' => $k(), 'title' => '这套系统上线后的运行数字', 'subtitle' => '运行数据', 'content' => <<<HTML
<div data-n="502"><b>0</b><span>已发布文章</span></div>
<div data-n="25"><b>0</b><span>区块类型</span></div>
<div data-n="127"><b>0</b><span>契约测试</span></div>
<div data-n="99"><b>0</b><span>页面可用性</span></div>
HTML];

$qwall = ['_type' => 'quote-wall', '_key' => $k(), 'title' => '用过的人怎么说', 'subtitle' => '用户声音', 'content' => <<<HTML
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」— 陈默，内容工作室</div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」— 独立开发者</div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」— 增长负责人</div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」— 前端工程师</div>
<div>「体检建议不是空话，每条都连着真实数据。」— 运营</div>
<div>「课程播放器终于能播视频了，拖进度条很流畅。」— 学员</div>
HTML];

$changelog = ['_type' => 'changelog', '_key' => $k(), 'title' => '系统在持续生长', 'subtitle' => '更新日志', 'content' => <<<HTML
<div data-v="v2.6"><b>09-16</b><h4><span class="vtag">v2.6</span>展示区块扩展 + 组件工厂</h4><p>25 种区块类型；组件工厂支持描述 / HTML / 网址 / 截图四种方式生成新模块。</p></div>
<div data-v="v2.5"><b>09-14</b><h4><span class="vtag">v2.5</span>增长工具箱</h4><p>AI 岗位制 · 每日晨会 · 利润公式推演器 · MCP 技能包。</p></div>
<div data-v="v2.1"><b>09-08</b><h4><span class="vtag">v2.1</span>商业化闭环</h4><p>订阅计费、购物车、推荐码、市场上架、佣金结算、转化漏斗。</p></div>
HTML];

// 插入位置：banner 放页面最顶部（公告条），其余合理分布
$banner = ['_type' => 'banner', '_key' => $k(), 'title' => 'v2.6 已发布：25 种展示区块 + 组件工厂', 'content' => '后台建站面板可直接使用', 'button_text' => '查看全部能力', 'button_url' => '/capability'];

$out = [$banner]; // banner 置顶
$done = ['cl'=>false, 'cd'=>false, 'tm'=>false, 'code'=>false, 'kbd'=>false, 'sp'=>false, 'tk'=>false, 'qw'=>false, 'clg'=>false];
foreach ($page['blocks'] as $b) {
    $t = $b['_type'] ?? '';
    if (!$done['cl'] && $t === 'proof') { $out[] = $checklist; $done['cl'] = true; }
    if (!$done['cd'] && $t === 'prompt') { $out[] = $countdown; $done['cd'] = true; }
    if (!$done['tm'] && $t === 'team-placeholder-never') { $out[] = $team; }
    $out[] = $b;
    if (!$done['kbd'] && $t === 'faq') { $out[] = $kbd; $done['kbd'] = true; }
    if (!$done['sp'] && $t === 'cta') { $out[] = $spotlight; $done['sp'] = true; }
}
// team / code / ticker / quote-wall / changelog 依次追加在 CTA 前
$ctaIdx = 0;
$final = [];
foreach ($out as $b) {
    $t = $b['_type'] ?? '';
    if ($t === 'cta') {
        $final[] = $team; $final[] = $code; $final[] = $ticker; $final[] = $qwall; $final[] = $changelog;
        $ctaIdx++;
    }
    $final[] = $b;
}
if ($ctaIdx === 0) { foreach ([$team,$code,$ticker,$qwall,$changelog] as $n) $final[] = $n; }

save_builder_page($page['id'], ['blocks' => $final]);
echo "✅ 第三批已追加，现共 " . count($final) . " 个区块\n";
