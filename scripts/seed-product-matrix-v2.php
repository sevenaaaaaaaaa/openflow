<?php
/**
 * 产品矩阵 v2 —— 差异化定位版
 * inFlow 新建 + MFlow/WebsFlow/UserLoop 按独立产品定位重写
 * 用法: php scripts/seed-product-matrix-v2.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';

/** 覆盖式写入（不存在则建，存在则整页替换 blocks） */
function upsert_page(string $slug, array $page): void {
    $pages = builder_pages_all();
    foreach ($pages as $p) {
        if (($p['slug'] ?? '') === $slug) {
            $data = ['blocks' => $page['blocks'], 'title' => $page['title'], 'seo_title' => $page['seo_title'], 'seo_desc' => $page['seo_desc']];
            save_builder_page($p['id'], $data);
            echo "♻ $slug 已按新定位重写\n";
            return;
        }
    }
    save_builder_page('', $page);
    echo "✅ $slug 已创建\n";
}

/** 矩阵导航块（v2：五件套，强调独立+互通） */
function matrix_block(string $self, callable $k): array {
    $items = [
        ['openflow',  'OpenFlow',  '全站增长操作系统', '四力合一 · all-in-one 人群', '/product'],
        ['mflow',     'MFlow',     '轻量内容生产与分发', '不强依赖 CMS/CDP · 兼容现成生态', '/mflow'],
        ['webs-flow', 'Webs Flow', '落地页专精', '投放承接页以小时计 · 无需 CMS', '/webs-flow'],
        ['userloop',  'UserLoop',  '全域营销数据中枢', '独立运行 · 对接任何 MA', '/userloop'],
        ['inflow',    'inFlow',    '情报增长站', '外部数据驱动 · 趋势/舆情/竞品', '/inflow'],
    ];
    $html = '';
    foreach ($items as $it) {
        if ($it[0] === $self) continue;
        $html .= "<div><span class=\"tg-tag\">独立产品</span><h3>{$it[1]}</h3><p>{$it[2]} · {$it[3]}</p><em>API 互通</em></div>";
    }
    return ['_type' => 'tool-grid', '_key' => $k(), 'title' => '五个独立产品，API 互相增益', 'subtitle' => '产品矩阵', 'content' => $html];
}

$interopNote = '五个产品独立运行，互通走公开 API，拔掉任何一个其余照常。你从哪个入口进来都能跑通闭环。';

/* ═══════════════ inFlow · 情报增长站（新建） ═════════ */
upsert_page('inflow', [
    'slug' => 'inflow', 'title' => 'inFlow · 情报增长站', 'status' => 'published',
    'seo_title' => 'inFlow — 外部数据驱动的增长情报站 | 芭乐派',
    'seo_desc' => 'Insight Flow：不需要 CMS/CDP，输入关键词与竞品，替你盯外部世界——趋势、舆情、竞品动态、选题情报。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '替你盯着外部世界，<br>情报变成每天的动作', 'subtitle' => 'INSIGHT FLOW', 'content' => 'inFlow 不需要你部署 CMS 或 CDP：输入关键词、竞品和行业，它替你采集媒体、社媒、搜索趋势与舆情，变成晨报、周报和可执行的选题。', 'button_text' => '进入情报后台', 'button_url' => '/xmp/geo'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>0</b><span>CMS/CDP 依赖</span></div>
<div><b>5+</b><span>数据源类型</span></div>
<div><b>每天</b><span>情报晨报</span></div>
<div><b>1 条</b><span>主线建议出口</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '情报工作里的四个高频痛点', 'subtitle' => '为什么需要', 'content' => <<<HTML
<div><h4>趋势靠刷</h4><p>每天刷几十个平台找热点，看不过来也记不住，机会转瞬即逝。</p></div>
<div><h4>竞品靠翻</h4><p>竞品上了什么内容、投了什么渠道，手工逐个翻，永远慢一拍。</p></div>
<div><h4>舆情靠撞</h4><p>差评与危机先在社媒发酵，你从客户投诉才知道——晚了。</p></div>
<div><h4>选题靠憋</h4><p>没有外部信号，选题全凭感觉，写出来总是慢半拍。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '情报如何变成每天的动作', 'subtitle' => 'INTEL FLOW', 'content' => <<<HTML
<div><h3>订阅情报源</h3><p>搜索 API、RSS、社媒、行业媒体——你的关键词与竞品清单决定看什么。</p></div>
<div><h3>全网采集</h3><p>多源定时抓取，去重合并，热度与新鲜度自动打分。</p></div>
<div><h3>舆情与竞品</h3><p>主题情感分析给出预警；竞品内容节奏与渠道动作持续追踪。</p></div>
<div><h3>情报输出</h3><p>晨报三分钟看完：趋势、预警、值得写的三个选题。</p></div>
<div><h3>API 回填</h3><p>选题经 API 回填到 OpenFlow/MFlow 选题库，或推送到你的工作台。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '与 OpenFlow 的洞察：交集，但不是同一个东西', 'subtitle' => '边界清晰', 'content' => <<<HTML
<div><b>inFlow 看外部</b><span>趋势、舆情、竞品、行业动态——外部世界的机会与风险。</span></div>
<div><b>OpenFlow 洞察看自己</b><span>自己站点的内容表现、用户行为、转化数据。</span></div>
<div><b>API 互通增益</b><span>inFlow 情报回填选题库；站点内容表现回传做效果校准。双向 HMAC 签名，已落地插件通道。</span></div>
HTML],
        ['_type' => 'tabs', '_key' => $k(), 'title' => '按场景用 inFlow', 'subtitle' => 'USE CASES', 'content' => <<<HTML
<div data-tab="品牌方"><h3>舆情雷达</h3><p>订阅品牌词与产品词，情感分析实时预警；差评发酵前先介入。</p></div>
<div data-tab="内容策划"><h3>选题雷达</h3><p>每天三分钟晨报：趋势、热点、竞品动态，直接给出今天值得写的三个选题。</p></div>
<div data-tab="市场研究"><h3>竞品情报</h3><p>竞品的内容频率、渠道布局、产品更新，一条时间线看全。</p></div>
<div data-tab="投研增长"><h3>趋势验证</h3><p>搜索指数、社媒声量、媒体热度交叉验证一个方向值不值得投。</p></div>
HTML],
        ['_type' => 'tool-grid', '_key' => $k(), 'title' => '情报能力清单', 'subtitle' => 'CAPABILITY', 'content' => <<<HTML
<div><span class="tg-tag">采集</span><h3>全网信号采集</h3><p>搜索 API + RSS + 社媒，多源定时抓取。</p><em>5+ 源</em></div>
<div><span class="tg-tag">监测</span><h3>舆情监测</h3><p>主题订阅、情感分析、异常预警。</p><em>实时</em></div>
<div><span class="tg-tag">情报</span><h3>竞品追踪</h3><p>内容节奏、渠道动作、产品更新。</p><em>时间线</em></div>
<div><span class="tg-tag">交付</span><h3>晨报/周报</h3><p>趋势+预警+选题，一条主线出口。</p><em>自动生成</em></div>
<div><span class="tg-tag">互通</span><h3>API 回填</h3><p>情报直填 OpenFlow/MFlow 选题库。</p><em>HMAC</em></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>必须先装 OpenFlow 吗？</summary><p>不用。inFlow 是独立产品：输入关键词和竞品即可运行，不需要 CMS、CDP 或任何部署依赖。</p></details>
<details><summary>和 OpenFlow 的洞察功能有什么区别？</summary><p>OpenFlow 洞察看你自己的站点数据；inFlow 看外部世界的情报。两者 API 互通、互为增益，互不依赖。</p></details>
<details><summary>数据源覆盖哪些？</summary><p>搜索引擎 API（Google/Bing/百度）、RSS 订阅、社媒平台、行业媒体。供应商可配置扩展。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '外部世界在发生什么，每天早上三分钟知道', 'subtitle' => 'INFLOW', 'content' => '输入你的关键词和竞品，明天的晨报就开始了。', 'button_text' => '开始订阅情报', 'button_url' => '/xmp/geo'],
    ],
]);

/* ═════════ MFlow v2 · 轻量内容生产与分发机 ═════════ */
upsert_page('mflow', [
    'slug' => 'mflow', 'title' => 'MFlow · 内容生产与分发引擎', 'status' => 'published',
    'seo_title' => 'MFlow — 轻量内容生产、分发与触达引擎 | 芭乐派',
    'seo_desc' => '轻量版增长引擎：专门解决内容生产、多平台分发与轻触达。不强依赖 CDP/CMS，兼容现成的网站、公众号与商城。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '内容写完，分发出去，触达回来', 'subtitle' => 'MARKETING FLOW · 轻量版', 'content' => 'MFlow 专门解决内容生产、分发与触达——不强依赖 CDP，不要求换 CMS。你现有的网站、公众号、商城照常用，它补上生成与分发这块最重的活。', 'button_text' => '进入 MFlow 后台', 'button_url' => '/xmp/automation'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>低</b><span>CMS/CDP 依赖</span></div>
<div><b>多平台</b><span>分发适配器</span></div>
<div><b>多家</b><span>生成模型兼容</span></div>
<div><b>轻</b><span>触达起步</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '内容人群的真实日常', 'subtitle' => '痛点', 'content' => <<<HTML
<div><h4>写不完</h4><p>选题、初稿、改稿、配图——每天三小时进产出，真正该做的判断没时间。</p></div>
<div><h4>发不动</h4><p>公众号、知乎、小红书、邮件——一个平台一个后台，发一次要切五次。</p></div>
<div><h4>触达靠群发</h4><p>没有分群引擎，只能一刀切推送；又不想为触达上一个重型 CDP。</p></div>
<div><h4>系统太重</h4><p>看过一体化的增长系统，但对 CMS/CDP 的深度依赖让人却步。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '一篇文章的轻量旅程', 'subtitle' => 'CONTENT FLOW', 'content' => <<<HTML
<div><h3>找选题</h3><p>热点采集与选题库（可接 inFlow 情报，也可自己喂关键词）。</p></div>
<div><h3>生成初稿</h3><p>接多家模型产文/产图/产脚本，不锁供应商，AI 初稿你把关。</p></div>
<div><h3>多平台分发</h3><p>公众号、知乎、邮件列表等渠道适配器——一次编辑多平台发布。</p></div>
<div><h3>轻触达</h3><p>邮件/社群通知起步，不需要先上一个分群引擎。</p></div>
<div><h3>效果回收</h3><p>打开、阅读、转化数据回流，反哺下一轮选题。</p></div>
HTML],
        ['_type' => 'features', '_key' => $k(), 'title' => '兼容度极好，是它的护城河', 'subtitle' => '为什么选 MFlow', 'content' => <<<HTML
<div><h3>不换 CMS</h3><p>WordPress、Shopify、自建站都行——API 桥接现成的内容库。</p></div>
<div><h3>不锁模型</h3><p>OpenAI 兼容协议全接：DeepSeek、GLM、Kimi、Claude……谁的生成好用用谁。</p></div>
<div><h3>不建 CDP</h3><p>触达人群可以用简单规则（订阅者/买家/标签），要深度分群时再接 UserLoop。</p></div>
<div><h3>轻触达起步</h3><p>邮件 + 社群通知就够启动；需要复杂旅程时再升级工作流。</p></div>
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => 'MFlow 开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>AI 写作助手：选题→初稿→改写，多模型可切换</li>
<li>内容日历与定时发布</li>
<li>多平台分发适配器（公众号/知乎/邮件起步）</li>
<li>轻触达：邮件与社群通知模板</li>
<li>效果简报：打开/阅读/转化一页看完</li>
<li>与 inFlow 的选题情报接口（可选）</li>
HTML],
        ['_type' => 'tabs', '_key' => $k(), 'title' => '谁在用 MFlow', 'subtitle' => 'WHO', 'content' => <<<HTML
<div data-tab="内容创作者"><h3>一个人当编辑部</h3><p>选题、初稿、配图、多平台发布——一个人跑通一条内容流水线。</p></div>
<div data-tab="电商卖家"><h3>内容带来复购</h3><p>商品内容（笔记/测评/清单）批量生成，邮件与社群触达带来回访。</p></div>
<div data-tab="公众号矩阵"><h3>多号统一管理</h3><p>内容池 → 各号分发 → 数据汇总，矩阵号不再一个一个切后台。</p></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>MFlow 和 OpenFlow 是什么关系？</summary><p>两个独立产品。MFlow 是轻量的内容生产与分发引擎，适合不想上全家桶的用户；OpenFlow 是完整的增长操作系统。MFlow 用得顺想升级全家桶时，数据可平滑迁移。</p></details>
<details><summary>必须用 OpenFlow 的 CMS 吗？</summary><p>不用。MFlow 通过 API 桥接你现成的 CMS/商城/公众号后台，对内容库零侵入。</p></details>
<details><summary>生成用谁的模型？</summary><p>你的模型你选：OpenAI 兼容协议全接（DeepSeek/GLM/Kimi/Claude 等），在设置里随时切换。</p></details>
<details><summary>触达要做复杂工作流怎么办？</summary><p>MFlow 的轻触达覆盖邮件与通知。需要复杂自动化旅程时，接 UserLoop 分群与 MFlow 工作流（API 互通，不强制）。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '写作交给模型，分发交给适配器，判断留给你', 'subtitle' => 'MFLOW', 'content' => '不换 CMS、不建 CDP、不锁模型——今天就开始你的轻量内容流。', 'button_text' => '开始生产内容', 'button_url' => '/xmp/automation'],
    ],
]);

/* ═════════ Webs Flow v2 · 落地页专精 ═════════ */
upsert_page('webs-flow', [
    'slug' => 'webs-flow', 'title' => 'Webs Flow · 落地页引擎', 'status' => 'published',
    'seo_title' => 'Webs Flow — 落地页专精工具 | 芭乐派',
    'seo_desc' => '专精投放承接页、活动页、广告落地页：35 种展示区块 + AI 组件工厂 + 投放原生能力，从素材到上线以小时计。无需 CMS。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '为投放而生：落地页从素材到上线，以小时计', 'subtitle' => 'WEBSITE & PAGE FLOW · 落地页专精', 'content' => 'Webs Flow 不做全站 CMS——它只做一件事：让你的广告点进来的人，看到一张对得上承诺的承接页。35 种区块、AI 造模块、投放原生能力。', 'button_text' => '看示例页', 'button_url' => '/b/growth-os-tour'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>小时</b><span>素材到上线</span></div>
<div><b>35</b><span>展示区块</span></div>
<div><b>0</b><span>CMS 依赖</span></div>
<div><b>4</b><span>AI 造模块方式</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '投放团队的落地页之痛', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>承接页慢</h4><p>广告本周要跑，落地页还在排设计排期——预算在烧，页面没影子。</p></div>
<div><h4>素材与页面脱节</h4><p>广告承诺一个角度，落地页讲另一个故事，转化率被承诺落差吃掉。</p></div>
<div><h4>A/B 排不上</h4><p>每换一个钩子/报价/背景都是一张设计工单，测试永远排不上队。</p></div>
<div><h4>工程师被卡</h4><p>团队有自己的前端栈，却必须用建站工具的前端。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '一个投放活动的落地页流程', 'subtitle' => 'LP FLOW', 'content' => <<<HTML
<div><h3>选模板起步</h3><p>按行业与活动类型选起点：促销承接 / 内容下载 / 报名表单 / 对比页。</p></div>
<div><h3>区块拼装</h3><p>35 种区块（Hero/证据/对比/FAQ/倒计时/清单）拖出来就是一张专业落地页。</p></div>
<div><h3>AI 补模块</h3><p>缺特殊形态？组件工厂四种方式（描述/HTML/网址/截图）现场造。</p></div>
<div><h3>投放原生</h3><p>UTM 归因、广告像素、A/B 分流、表单直连 MA——投放要的全有。</p></div>
<div><h3>变体作战</h3><p>人群定向区块 + 页面级缓存 + 边缘加速，多活动并行不打架。</p></div>
HTML],
        ['_type' => 'tabs', '_key' => $k(), 'title' => '两种用法，都为速度', 'subtitle' => 'USAGE', 'content' => <<<HTML
<div data-tab="可视化搭页"><h3>投手自己上手</h3><p>拖区块、填内容、实时预览——不排设计排期，本周活动本周上线。</p></div>
<div data-tab="无头接管"><h3>工程师接管渲染</h3><p>区块数据走 API 吐 JSON，你们自己的前端框架渲染，后台只管内容与转化。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '为什么不是「再一个建站工具」', 'subtitle' => '区别', 'content' => <<<HTML
<div><b>单点极致</b><span>OpenFlow 面向全站；Webs Flow 只把「落地页」这一件事做到极致——区块、归因、像素、分流全部投放原生。</span></div>
<div><b>无 CMS 依赖</b><span>不需要内容系统配合，一页一活的作战方式。</span></div>
<div><b>全域数据可接</b><span>表单与转化事件直接喂给 UserLoop 或你现有的 MA（API 互通，不强制）。</span></div>
HTML],
        ['_type' => 'tool-grid', '_key' => $k(), 'title' => '落地页作战工具箱', 'subtitle' => 'TOOLKIT', 'content' => <<<HTML
<div><span class="tg-tag">速度</span><h3>组合模板</h3><p>常用落地页结构一键铺底，改内容就上线。</p><em>分钟级</em></div>
<div><span class="tg-tag">AI</span><h3>AI 落地页生成</h3><p>一句话需求 → 区块数组 → 成页。</p><em>api/ai-landing</em></div>
<div><span class="tg-tag">归因</span><h3>UTM + 像素</h3><p>渠道归因与广告平台事件回传。</p><em>原生</em></div>
<div><span class="tg-tag">实验</span><h3>A/B 分流</h3><p>变体分流与统计，赢的方向继续扩。</p><em>内置</em></div>
<div><span class="tg-tag">性能</span><h3>边缘加速</h3><p>静态资源 CDN + 图片 WebP + 页面缓存。</p><em>全球</em></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>Webs Flow 和 OpenFlow 的建站有什么区别？</summary><p>本质区别：OpenFlow 面向全站（内容/社区/商城一体化），Webs Flow 专精落地页——投放承接页、活动页、报名页。它不需要 CMS，是独立产品。</p></details>
<details><summary>必须用你们的前端吗？</summary><p>不必。无头 API 直接吐区块 JSON，工程师可以完全接管渲染，后台只管内容与转化。</p></details>
<details><summary>表单提交的数据去哪？</summary><p>进 UserLoop 或你现有的任何 MA（Webhook/API 互通），不锁平台。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '下一个活动，页面今天上线', 'subtitle' => 'WEBS FLOW', 'content' => '从素材到上线以小时计——让承接页追上你的投放节奏。', 'button_text' => '搭第一张落地页', 'button_url' => '/b/growth-os-tour'],
    ],
]);

/* ═════════ UserLoop v2 · 独立全域营销数据中枢 ═════════ */
upsert_page('userloop', [
    'slug' => 'userloop', 'title' => 'UserLoop · 全域营销数据中枢', 'status' => 'published',
    'seo_title' => 'UserLoop — 独立的全域营销数据中枢 | 芭乐派',
    'seo_desc' => '独立运行的全域营销工具：一行 JS 埋点 + 外部数据打通（广告/电商/社媒）+ 对接你现有的任何 MA。不依赖任何特定 CMS/CDP。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '全域数据一处清，<br>MA 随便接', 'subtitle' => 'USER LOOP · 独立全域', 'content' => 'UserLoop 是独立的全域营销数据中枢，不依赖任何特定 CMS/CDP：打通广告、电商、社媒等外部数据，对接你现有的任何 MA 平台。', 'button_text' => '开始接入', 'button_url' => '/xmp/cdp'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>独立</b><span>不依赖特定 CMS/CDP</span></div>
<div><b>多源</b><span>广告/电商/社媒数据</span></div>
<div><b>任何</b><span>MA 平台可对接</span></div>
<div><b>1 行</b><span>接入代码</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '多平台运营团队的数据困局', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>数据在五个平台</h4><p>广告在投放平台、订单在电商、行为在自己站、粉丝在社媒——对不上一个人。</p></div>
<div><h4>MA 被绑死</h4><p>数据进了某个 MA 就拿不出来，换平台等于重建用户档案。</p></div>
<div><h4>分群全靠导表</h4><p>跨平台圈一个人群，要导三张表对半天，圈完已经过时。</p></div>
<div><h4>档案跟人走不了</h4><p>换 CMS/换商城，用户历史行为全部作废重来。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '全域数据的接入与流转', 'subtitle' => 'DATA HUB', 'content' => <<<HTML
<div><h3>全站埋点</h3><p>一行 JS 自动采集，匿名 uid 建档；插件与服务端 API 可深度接入。</p></div>
<div><h3>外部数据打通</h3><p>广告平台、电商平台、社媒平台的数据经 API/导入接进来。</p></div>
<div><h3>身份合并</h3><p>匿名 uid ↔ 邮箱 ↔ 手机号自动合并，跨平台跨设备一张档案。</p></div>
<div><h3>MA 对接器</h3><p>对接你现有的任何 MA：Webhook/API 适配器，不绑死任何一家。</p></div>
<div><h3>人群即服务</h3><p>分群结果作为人群推送：MFlow、OpenFlow、第三方 MA 都能吃。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '独立性是它的硬本事', 'subtitle' => '为什么独立', 'content' => <<<HTML
<div><b>不绑 CMS</b><span>不用换你现有的网站/商城，埋点接入即用。</span></div>
<div><b>不绑 MA</b><span>Webhook/API 对接现有营销自动化——数据主权在你手里。</span></div>
<div><b>档案可迁移</b><span>换平台不重建：完整用户档案随时导出导入。</span></div>
HTML],
        ['_type' => 'code', '_key' => $k(), 'title' => '接入只要一行', 'subtitle' => 'install', 'content' => <<<HTML
<!-- 全站页脚自动注入，也可手动 -->
<script src="https://nownexts.com/assets/cdp-track.js" async></script>
<!-- 外部数据：API 直推 -->
POST /api/ingest  { "event": "purchase", "user_id": "..." }
HTML],
        ['_type' => 'tool-grid', '_key' => $k(), 'title' => '与谁互通', 'subtitle' => 'INTEGRATION', 'content' => <<<HTML
<div><span class="tg-tag">MA</span><h3>任何 MA</h3><p>Webhook/API 适配器：HubSpot、客户现有 MA、MFlow 工作流。</p><em>适配器</em></div>
<div><span class="tg-tag">页面</span><h3>Webs Flow</h3><p>落地页表单与转化事件直接进档案。</p><em>原生</em></div>
<div><span class="tg-tag">情报</span><h3>inFlow</h3><p>行为事件 HMAC 出站给情报系统做深度分析。</p><em>插件</em></div>
<div><span class="tg-tag">底座</span><h3>OpenFlow</h3><p>想升级全家桶时，数据带得走：档案导出导入。</p><em>可迁移</em></div>
HTML],
        ['_type' => 'stats', '_key' => $k(), 'title' => '独立数据中枢的样子', 'subtitle' => 'IMPACT', 'content' => <<<HTML
<div><b>1 处</b><span>全域数据</span></div>
<div><b>0 绑定</b><span>MA 自由对接</span></div>
<div><b>可迁</b><span>档案带走</span></div>
<div><b>实时</b><span>分群进出</span></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>UserLoop 需要 OpenFlow 吗？</summary><p>不需要。UserLoop 是独立产品，对接你现有的网站、商城、MA——它自己就是完整的数据中枢。</p></details>
<details><summary>我现有的 MA 平台能对接吗？</summary><p>能。Webhook 与 API 适配器对接你现有的任何营销自动化平台，数据双向同步，不被绑死。</p></details>
<details><summary>外部平台数据怎么接？</summary><p>广告平台（转化回传）、电商平台（订单流）、社媒平台（粉丝/互动）通过 API 与导入通道接入，统一进身份图谱。</p></details>
<details><summary>想换平台怎么办？</summary><p>档案随时导出：身份、行为、标签、分群全部带走，数据主权在你。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '数据主权在你手里', 'subtitle' => 'USERLOOP', 'content' => '一行代码接入，档案永远可迁移，MA 随便换。', 'button_text' => '开始接入全域数据', 'button_url' => '/xmp/cdp'],
    ],
]);

/* ═════════ MFlow 页尾 + Webs 页尾 + UserLoop 页尾统一补矩阵块（上面已有各自尾部）═════════ */

// 给三个重写页各补矩阵块（追加到 blocks 尾部）
$pages = builder_pages_all();
foreach ($pages as $i => $p) {
    $slug = $p['slug'] ?? '';
    if (!in_array($slug, ['mflow', 'webs-flow', 'userloop', 'inflow'])) continue;
    $blocks = $p['blocks'] ?? [];
    // 幂等：已有 matrix 块（含「产品矩阵」副标题）则替换，否则追加
    $m = matrix_block($slug, $k);
    $replaced = false;
    foreach ($blocks as &$b) {
        if (($b['_type'] ?? '') === 'tool-grid' && ($b['subtitle'] ?? '') === '产品矩阵') { $b = $m; $replaced = true; break; }
    }
    unset($b);
    if (!$replaced) $blocks[] = $m;
    save_builder_page($p['id'], ['blocks' => $blocks]);
}
echo "✅ 矩阵块已更新（五件套）\n";
