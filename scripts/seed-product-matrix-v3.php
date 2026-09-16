<?php
/**
 * 产品矩阵 · PayFlow / LearnFlow 产品页（区块系统搭建）
 * 用法: php scripts/seed-product-matrix-v3.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';

function upsert_page(string $slug, array $page): void {
    $pages = builder_pages_all();
    foreach ($pages as $p) {
        if (($p['slug'] ?? '') === $slug) {
            save_builder_page($p['id'], ['blocks' => $page['blocks'], 'title' => $page['title'], 'seo_title' => $page['seo_title'], 'seo_desc' => $page['seo_desc']]);
            echo "♻ $slug 重写\n";
            return;
        }
    }
    save_builder_page('', $page);
    echo "✅ $slug 创建\n";
}

/** 七件套矩阵块 */
function matrix_block(string $self, callable $k): array {
    $items = [
        ['openflow',  'OpenFlow',  '全站增长操作系统', '四力合一 · all-in-one 人群', '/product'],
        ['mflow',     'MFlow',     '轻量内容生产与分发', '不强依赖 CMS/CDP · 兼容现成生态', '/mflow'],
        ['webs-flow', 'Webs Flow', '落地页专精', '投放承接页以小时计 · 无需 CMS', '/webs-flow'],
        ['userloop',  'UserLoop',  '全域营销数据中枢', '独立运行 · 对接任何 MA', '/userloop'],
        ['inflow',    'inFlow',    '情报增长站', '外部数据驱动 · 趋势/舆情/竞品', '/inflow'],
        ['payflow',   'PayFlow',   '商业变现引擎', '一行嵌入收款 · 订阅/裂变/佣金', '/payflow'],
        ['learnflow', 'LearnFlow', '课程与训练营交付', '上课→进度→测验→证书→复购', '/learnflow'],
    ];
    $html = '';
    foreach ($items as $it) {
        if ($it[0] === $self) continue;
        $html .= "<div><span class=\"tg-tag\">独立产品</span><h3>{$it[1]}</h3><p>{$it[2]} · {$it[3]}</p><em>API 互通</em></div>";
    }
    return ['_type' => 'tool-grid', '_key' => $k(), 'title' => '七个独立产品，API 互相增益', 'subtitle' => '产品矩阵', 'content' => $html];
}

/* ═════════ PayFlow · 商业变现引擎 ═════════ */
upsert_page('payflow', [
    'slug' => 'payflow', 'title' => 'PayFlow · 商业变现引擎', 'status' => 'published',
    'seo_title' => 'PayFlow — 一行嵌入的商业变现引擎 | 芭乐派',
    'seo_desc' => '收款 + 订阅 + 推荐裂变 + 佣金结算。买按钮嵌进任何落地页、内容页或第三方网站即可收款，零 CMS/CDP 依赖。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '一行嵌入，开始收钱', 'subtitle' => 'PAY FLOW', 'content' => 'PayFlow 只做一件事并做到极致：让你的数字产品、课程、订阅能收钱。买按钮嵌进任何页面——落地页、内容站、现成的商城——三分钟上线。', 'button_text' => '看嵌入示例', 'button_url' => '/payflow#embed'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>1 行</b><span>嵌入代码</span></div>
<div><b>0</b><span>CMS/CDP 依赖</span></div>
<div><b>订阅</b><span>自动续费闭环</span></div>
<div><b>可审计</b><span>佣金与提现</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '想收钱的创作者，都被这些卡过', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>接支付要建站</h4><p>想卖一门课，却要先把整套商城/建站系统架起来。</p></div>
<div><h4>订阅做不完整</h4><p>扣款失败没人管、到期没人提醒、流失静悄悄发生。</p></div>
<div><h4>分销算不清</h4><p>推荐码谁点的、订单算谁的、佣金怎么提——全靠手工记账。</p></div>
<div><h4>平台绑死</h4><p>用平台收款的代价：提现规则、数据主权、迁移成本全在别人手里。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '一笔收款的全流程', 'subtitle' => 'PAY FLOW', 'content' => <<<HTML
<div><h3>上架商品</h3><p>一次性定价或订阅定价，30 秒建好商品与价格。</p></div>
<div><h3>一行嵌入</h3><p>买按钮/弹窗/托管收银台，嵌任何页面（我们自己的落地页也用它）。</p></div>
<div><h3>订单状态机</h3><p>创建→支付→交付→退款，每一步留痕可查。</p></div>
<div><h3>自动续费</h3><p>扣款失败自动重试、降级提醒、到期提醒——订阅闭环完整。</p></div>
<div><h3>裂变结算</h3><p>推荐码归因、佣金记账、提现审核，分钱分得清清楚楚。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '与其他产品的边界', 'subtitle' => '独立但不孤立', 'content' => <<<HTML
<div><b>对 OpenFlow</b><span>全家桶用户直接用；PayFlow 也独立售卖，零母体依赖。</span></div>
<div><b>对 Webs Flow</b><span>落地页做承接，PayFlow 做收款——表单即支付是互通点。</span></div>
<div><b>对 MFlow</b><span>分发的内容挂购买链接；订单事件回传全域档案。</span></div>
HTML],
        ['_type' => 'code', '_key' => $k(), 'title' => '嵌入示例：一行接入', 'subtitle' => 'embed', 'content' => <<<HTML
&lt;script src="https://payflow.nownexts.com/checkout.js"&gt;&lt;/script&gt;
&lt;button data-payflow="你的商品ID"&gt;立即购买&lt;/button&gt;
<!-- 点击即唤起托管收银台，支付完成回调你的页面 -->
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => 'PayFlow 开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>商品与订阅定价管理</li>
<li>嵌入式结账（按钮/弹窗/托管页三种形态）</li>
<li>订阅自动续费 + 失败重试 + 降级提醒</li>
<li>优惠券：满减 / 折扣 / 限时</li>
<li>推荐码：点击→归因→订单归属</li>
<li>佣金余额 + 提现审核</li>
<li>数字商品交付：license / 文件</li>
<li>收款邮件通知</li>
HTML],
        ['_type' => 'tabs', '_key' => $k(), 'title' => '谁在用 PayFlow', 'subtitle' => 'WHO', 'content' => <<<HTML
<div data-tab="知识付费创作者"><h3>专栏/咨询/资料</h3><p>现成网站加一个买按钮就开始卖，不用迁移内容。</p></div>
<div data-tab="独立开发者"><h3>SaaS/工具订阅</h3><p>订阅计费 + 失败重试 + Webhook 出站接自己的系统。</p></div>
<div data-tab="训练营主理人"><h3>课程售卖</h3><p>配 LearnFlow 做交付：收钱归 PayFlow，上课归 LearnFlow。</p></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>需要用 OpenFlow 吗？</summary><p>不需要。PayFlow 是独立产品，一行嵌入即可在任何网站收款。当然，OpenFlow 全家桶用户也内置了它。</p></details>
<details><summary>支持哪些支付方式？</summary><p>通道适配层设计，首批支付宝/微信，逐步扩展更多通道与地区。</p></details>
<details><summary>订阅扣款失败会怎样？</summary><p>自动重试→降级提醒→到期提醒，订阅流失有完整兜底，不是静默流失。</p></details>
<details><summary>佣金怎么提现？</summary><p>推荐人佣金实时入余额，管理员审核后打款，全链路可审计。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '今天就把买按钮挂上去', 'subtitle' => 'PAYFLOW', 'content' => '一行嵌入，三分钟收第一笔钱。', 'button_text' => '开始收款', 'button_url' => '/payflow#embed'],
    ],
]);

/* ═════════ LearnFlow · 课程与训练营交付 ═════════ */
upsert_page('learnflow', [
    'slug' => 'learnflow', 'title' => 'LearnFlow · 课程与训练营交付', 'status' => 'published',
    'seo_title' => 'LearnFlow — 课程与训练营交付引擎 | 芭乐派',
    'seo_desc' => '上课→进度→测验→证书→复购。讲师、教练、训练营主理人的交付闭环工具，收款接 PayFlow，不强依赖任何 CMS/CDP。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '从开营到结业，一个后台管完', 'subtitle' => 'LEARN FLOW', 'content' => 'LearnFlow 给讲师的交付闭环：课程结构、学员名单、学习进度、章节测验、结业证书。不给你增长系统——只把课讲完、教好、发证这件事做好。', 'button_text' => '看交付流程', 'button_url' => '#flow'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>4 步</b><span>开营到结业</span></div>
<div><b>流畅</b><span>分段播放</span></div>
<div><b>可分享</b><span>结业证书</span></div>
<div><b>轻</b><span>轻依赖</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '讲师们的交付之痛', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>交付散在各处</h4><p>课件在一个盘、录播在一个平台、作业在一个群——学员和老师都晕。</p></div>
<div><h4>进度看不见</h4><p>谁学到哪了、谁掉队了、谁快结业了，全靠点名。</p></div>
<div><h4>测验靠人工</h4><p>章节测验发群里收表格，批改靠手工，数据不留痕。</p></div>
<div><h4>证书是图片</h4><p>结业证书发一张图，没法校验、没法分享、没法背书。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'id' => 'flow', 'title' => '一个训练营的完整生命周期', 'subtitle' => 'CAMP FLOW', 'content' => <<<HTML
<div><h3>开营</h3><p>课程结构（章节/课时/测验/资料）+ 开营节奏一键配置；学员经 PayFlow 购买或邀请码入学。</p></div>
<div><h3>学习</h3><p>视频分段播放、续播记忆、每日任务与打卡提醒。</p></div>
<div><h3>检验</h3><p>章节测验即时判分，结业测验守住出口标准。</p></div>
<div><h3>结业</h3><p>自动生成可分享的结业证书（可校验防伪），学员晒、雇主查。</p></div>
<div><h3>复盘</h3><p>完课率、学习曲线、掉队名单一页看完，下一期改哪里有依据。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '与其他产品的边界', 'subtitle' => '独立但不孤立', 'content' => <<<HTML
<div><b>对 PayFlow</b><span>收款归 PayFlow（API 互通），LearnFlow 专注交付，不做支付。</span></div>
<div><b>对 OpenFlow</b><span>全站 CMS/社区在 OpenFlow；LearnFlow 只做课程交付域。</span></div>
<div><b>对 UserLoop</b><span>学习行为（完课/进度）可进全域档案——增益不是依赖。</span></div>
HTML],
        ['_type' => 'tool-grid', '_key' => $k(), 'title' => '交付工具箱', 'subtitle' => 'TOOLKIT', 'content' => <<<HTML
<div><span class="tg-tag">课程</span><h3>结构化课程</h3><p>章节/课时/测验/资料，图文视频混排。</p><em>随时改</em></div>
<div><span class="tg-tag">播放</span><h3>托管播放</h3><p>R2 托管 mp4/HLS，Range 分段流畅拖动。</p><em>不卡</em></div>
<div><span class="tg-tag">进度</span><h3>进度与曲线</h3><p>续播记忆、完成标记、学习曲线看板。</p><em>自动</em></div>
<div><span class="tg-tag">测验</span><h3>章节测验</h3><p>单选/多选/判断，即时判分，数据留痕。</p><em>自动判</em></div>
<div><span class="tg-tag">证书</span><h3>结业证书</h3><p>可分享可校验，学员背书 employer 可查。</p><em>防伪</em></div>
<div><span class="tg-tag">运营</span><h3>训练营节奏</h3><p>开营/任务/打卡/作业，轻圈子问答。</p><em>一期一个</em></div>
HTML],
        ['_type' => 'stats', '_key' => $k(), 'title' => '交付闭环跑起来的样子', 'subtitle' => 'IMPACT', 'content' => <<<HTML
<div><b>1 页</b><span>后台管全程</span></div>
<div><b>自动</b><span>测验与证书</span></div>
<div><b>看得见</b><span>完课率与掉队</span></div>
<div><b>可迁移</b><span>数据主权</span></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>需要用 OpenFlow 吗？</summary><p>不需要。LearnFlow 独立交付课程，收款走 PayFlow（互通不绑定）。</p></details>
<details><summary>视频存在哪？会不会卡？</summary><p>对象存储 + 边缘分发，HTTP Range 分段播放，拖进度条流畅。</p></details>
<details><summary>证书怎么防伪？</summary><p>每张证书有唯一校验链接，雇主扫码即可核验真伪与结业状态。</p></details>
<details><summary>和知识星球类产品什么区别？</summary><p>它们是社区为主；LearnFlow 是课程交付为主（结构/进度/测验/证书），社区只是轻量配套。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '下一期训练营，交给 LearnFlow', 'subtitle' => 'LEARNFLOW', 'content' => '开营到结业四步闭环：上课、进度、测验、证书。', 'button_text' => '开始交付', 'button_url' => '/learnflow'],
    ],
]);

// 补矩阵块（七件套）
$pages = builder_pages_all();
foreach ($pages as $i => $p) {
    $slug = $p['slug'] ?? '';
    if (!in_array($slug, ['payflow', 'learnflow'])) continue;
    $blocks = $p['blocks'] ?? [];
    $m = matrix_block($slug, $k);
    $replaced = false;
    foreach ($blocks as &$b) {
        if (($b['_type'] ?? '') === 'tool-grid' && ($b['subtitle'] ?? '') === '产品矩阵') { $b = $m; $replaced = true; break; }
    }
    unset($b);
    if (!$replaced) $blocks[] = $m;
    save_builder_page($p['id'], ['blocks' => $blocks]);
}
// 五件套老页的矩阵块文案统一升级为七件套
foreach ($pages as $p) {
    $slug = $p['slug'] ?? '';
    if (!in_array($slug, ['mflow', 'webs-flow', 'userloop', 'inflow'])) continue;
    $blocks = $p['blocks'] ?? [];
    foreach ($blocks as &$b) {
        if (($b['_type'] ?? '') === 'tool-grid' && ($b['subtitle'] ?? '') === '产品矩阵') { $b = matrix_block($slug, $k); break; }
    }
    unset($b);
    save_builder_page($p['id'], ['blocks' => $blocks]);
}
echo "✅ 全部完成（payflow/learnflow 新页 + 五件套矩阵块升级七件套）\n";
