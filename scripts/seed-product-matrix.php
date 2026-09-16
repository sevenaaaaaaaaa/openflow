<?php
/**
 * 芭乐派产品矩阵 —— 三个新产品页
 *   MFlow（Marketing Flow）营销流程引擎
 *   Webs Flow（Website & Page Flow）建站与页面流
 *   UserLoop 用户行为循环
 * 用法: php scripts/seed-product-matrix.php
 * 定位依据：三个产品 = OpenFlow 能力域的产品化拆分，矩阵互相衔接。
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';
$have = [];
foreach (builder_pages_all() as $p) $have[$p['slug'] ?? ''] = true;

/* ─────────────── 矩阵导航区块（每页复用，指另外三兄弟） ─────────────── */
function matrix_block(string $self, callable $k): array {
    $items = [
        ['openflow', 'OpenFlow', '增长操作系统', '内容、数据、触达、销售四力合一的底座', '/product', '主力'],
        ['mflow', 'MFlow', '营销流程引擎', '多渠道触达 · 自动化工作流 · 增长实验', '/mflow', '营销'],
        ['webs-flow', 'Webs Flow', '建站与页面流', '35 种区块 · 组件工厂 · 无头 CMS', '/webs-flow', '建站'],
        ['userloop', 'UserLoop', '用户行为循环', '埋点 · 画像 · 分群 · 旅程 · 复购', '/userloop', '数据'],
    ];
    $html = '';
    foreach ($items as $it) {
        if ($it[0] === $self) continue;
        $html .= "<div><span class=\"tc-n\">{$it[5]}</span><h3>{$it[1]}</h3><p>{$it[2]} · {$it[3]}</p><a href=\"{$it[4]}\">了解 {$it[1]} →</a></div>";
    }
    return ['_type' => 'tool-grid', '_key' => $k(), 'title' => '芭乐派产品矩阵：它们这样衔接', 'subtitle' => 'MATRIX', 'content' => $html];
}

/* ─────────────── MFlow · 营销流程引擎 ─────────────── */
if (!isset($have['mflow'])) {
    $mflow = [
        'slug' => 'mflow', 'title' => 'MFlow · 营销流程引擎', 'status' => 'published',
        'seo_title' => 'MFlow — 营销自动化流程引擎 | 芭乐派',
        'seo_desc' => 'Marketing Flow：把邮件、短信、公众号、站内通知接进同一画布，工作流自动跑，转化不再靠手动催。',
        'blocks' => [
            ['_type' => 'hero', '_key' => $k(), 'title' => '营销不再是一张张工单，<br>而是一条自动跑的<i class="si">流程</i>', 'subtitle' => 'MARKETING FLOW', 'content' => 'MFlow 把渠道、内容、优惠、测试接进同一条营销流：画布上编排，工作流执行，效果回流到数据。你定策略，它管执行。', 'button_text' => '进入 MFlow 后台', 'button_url' => '/xmp/automation'],
            ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>6+</b><span>触达渠道</span></div>
<div><b>6</b><span>预制增长工作流</span></div>
<div><b>∞</b><span>画布编排</span></div>
<div><b>1</b><span>条营销主线</span></div>
HTML],
            ['_type' => 'journey', '_key' => $k(), 'title' => '一次营销活动的完整流程', 'subtitle' => 'CAMPAIGN FLOW', 'content' => <<<HTML
<div><h3>圈定人群</h3><p>从 UserLoop 分群直接选人：新客、沉睡、高意向、复购期，一句话描述也能圈。</p></div>
<div><h3>编排触达</h3><p>邮件、短信、公众号、站内通知画布上连线，A/B 变体一键展开。</p></div>
<div><h3>自动执行</h3><p>触发条件（注册/浏览/弃购/生日）自动驱动，工作流 7×24 在跑。</p></div>
<div><h3>效果归因</h3><p>打开、点击、下单逐环回收，哪个触点贡献了成交一目了然。</p></div>
<div><h3>沉淀复用</h3><p>跑通的流程存成模板，下次活动复制即用。</p></div>
HTML],
            ['_type' => 'features', '_key' => $k(), 'title' => '营销最重的活，都编进了流程', 'subtitle' => '核心能力', 'content' => <<<HTML
<div><h3>可视化工作流</h3><p>触发器 → 条件分支 → 动作 → 等待，节点拖拽编排，复杂旅程一条线讲清。</p></div>
<div><h3>营销画布</h3><p>多人协作的活动画布：从创意到上线到复盘，全部节点留痕。</p></div>
<div><h3>多渠道触达</h3><p>邮件 SMTP/SES、公众号模板消息、短信、站内信、Webhook，渠道即插即用。</p></div>
<div><h3>优惠与促销</h3><p>优惠券、折扣码、限时活动与倒计时，一条流程串起来发。</p></div>
HTML],
            ['_type' => 'tabs', '_key' => $k(), 'title' => '按角色看 MFlow', 'subtitle' => 'ROLE VIEW', 'content' => <<<HTML
<div data-tab="增长负责人"><h3>主线收口</h3><p>所有营销动作收进一条主线：晨会 3 分钟看进展，推演器算什么动作值得做，AI 岗位盯执行。</p></div>
<div data-tab="营销运营"><h3>搭流程不写代码</h3><p>预制工作流（欢迎序列/弃购挽回/复购提醒）开箱即用，改一改文案和人群就上线。</p></div>
<div data-tab="投放优化"><h3>变体自然生长</h3><p>A/B 测试自动分流与统计，赢的变体自动扩量，输的自动停。</p></div>
HTML],
            ['_type' => 'checklist', '_key' => $k(), 'title' => 'MFlow 开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>新用户欢迎序列（注册后 7 天触达节奏）</li>
<li>弃购挽回流（购物车 30 分钟未付款自动提醒）</li>
<li>复购召回（按购买周期自动圈人触达）</li>
<li>沉睡唤醒（30/60/90 天未活跃梯度唤醒）</li>
<li>内容订阅推送（新文章自动推全渠道）</li>
<li>活动倒计时（配合 Webs Flow 的倒计时区块）</li>
HTML],
            ['_type' => 'stats', '_key' => $k(), 'title' => '把重复的营销动作交给流程', 'subtitle' => 'IMPACT', 'content' => <<<HTML
<div><b>-80%</b><span>手动群发时间</span></div>
<div><b>7×24</b><span>工作流在线</span></div>
<div><b>3 分钟</b><span>晨会过完主线</span></div>
<div><b>1 套</b><span>客户数据打通</span></div>
HTML],
            ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>MFlow 和 OpenFlow 是什么关系？</summary><p>MFlow 是 OpenFlow 平台里的营销能力域产品化：营销自动化、画布、触达渠道、A/B 测试打包成一个聚焦「营销流程」的产品，与 OpenFlow 共用同一套数据和后台。</p></details>
<details><summary>必须先装 OpenFlow 吗？</summary><p>是的。MFlow 是芭乐派矩阵的一员，依托 OpenFlow 的数据底座（UserLoop）与内容底座（Webs Flow）运行。</p></details>
<details><summary>支持哪些触达渠道？</summary><p>邮件（SMTP/SES/Resend）、微信公众号模板消息与群发、短信、站内信、Webhook。微信生态与企业微信在路线图上。</p></details>
HTML],
            ['_type' => 'cta', '_key' => $k(), 'title' => '把营销交给流程，把判断留给自己', 'subtitle' => 'MFlow', 'content' => '后台打开自动化模块，选一条预制工作流，今天就能跑起来。', 'button_text' => '开始编排', 'button_url' => '/xmp/automation'],
        ],
    ];
    $mflow['blocks'][] = matrix_block('mflow', $k);
    save_builder_page('', $mflow);
    echo "✅ MFlow 页已创建\n";
}

/* ─────────────── Webs Flow · 建站与页面流 ─────────────── */
if (!isset($have['webs-flow'])) {
    $webs = [
        'slug' => 'webs-flow', 'title' => 'Webs Flow · 建站与页面流', 'status' => 'published',
        'seo_title' => 'Webs Flow — 建站与页面流引擎 | 芭乐派',
        'seo_desc' => 'Website & Page Flow：35 种展示区块 + AI 组件工厂 + 无头 CMS。搭页面像搭积木，数据在后台，展示随你换。',
        'blocks' => [
            ['_type' => 'hero', '_key' => $k(), 'title' => '搭页面像搭积木，<br>数据与展示<i class="si">彻底分开</i>', 'subtitle' => 'WEBSITE & PAGE FLOW', 'content' => 'Webs Flow 把建站变成流程：35 种展示区块拼页面，AI 组件工厂随时造新模块，内容后台统一管理——前端想换成 React/Vue？API 直接给数据。', 'button_text' => '试试区块系统', 'button_url' => '/b/growth-os-tour'],
            ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>35</b><span>展示区块类型</span></div>
<div><b>4</b><span>AI 造组件方式</span></div>
<div><b>100+</b><span>API 端点</span></div>
<div><b>0</b><span>私有样式耦合</span></div>
HTML],
            ['_type' => 'journey', '_key' => $k(), 'title' => '从空白到一个能打的页面', 'subtitle' => 'PAGE FLOW', 'content' => <<<HTML
<div><h3>选区块</h3><p>35 种展示区块分好类：布局/内容/转化/媒体/社交证明，搜索即达。</p></div>
<div><h3>填内容</h3><p>每个区块统一 8 个核心字段（标题/内容/图/按钮），所见即所得实时预览。</p></div>
<div><h3>AI 造新模块</h3><p>不够用？组件工厂：描述、贴 HTML、给网址、传截图，四种方式生成新模块。</p></div>
<div><h3>定人群</h3><p>任何区块可按登录态、访客类型、CDP 分群、UTM 定向展示。</p></div>
<div><h3>发上线</h3><p>一键发布，静态资源自动上 CDN，页面缓存与边缘加速全自动。</p></div>
HTML],
            ['_type' => 'feature-detail', '_key' => $k(), 'title' => '不只是建站工具，是一套展示系统', 'subtitle' => '为什么不同', 'content' => <<<HTML
<div><b>区块即组件</b><span>全部区块走共享设计系统（设计令牌 + archetype），改一处全站更新。</span></div>
<div><b>Portable Text 契约</b><span>区块形状兼容公开规范，将来接任何编辑器生态不掉队。</span></div>
<div><b>无头模式</b><span>100+ API 端点吐 JSON，你的前端你的样式，后台只管内容和用户。</span></div>
HTML],
            ['_type' => 'tabs', '_key' => $k(), 'title' => '三种用法，一个后台', 'subtitle' => 'USAGE', 'content' => <<<HTML
<div data-tab="可视化搭页"><h3>不写代码</h3><p>后台页面构建器拖区块、填内容、实时预览、人群定向——营销页、活动页、落地页当天上线。</p></div>
<div data-tab="代码扩展"><h3>贴 HTML 造模块</h3><p>把现有代码片段或别的页面结构贴进组件工厂，参数化成可复用模块，团队共享。</p></div>
<div data-tab="无头 CMS"><h3>前端随便换</h3><p>Next.js / Nuxt / 任何框架走 /api/public-content 与 /api/landing 取数据，后台内容管理能力一点不少。</p></div>
HTML],
            ['_type' => 'bento', '_key' => $k(), 'title' => '展示层的全套零件', 'subtitle' => 'COMPONENTS', 'content' => <<<HTML
<div data-w="3"><h3>布局类</h3><p>Hero · 旅程步骤 · 手风琴 · 标签页 · 编号展示 · 聚光大字</p></div>
<div data-w="3"><h3>内容类</h3><p>功能列表 · Bento 拼贴 · 工具网格 · 博客卡 · 密集卡片 · 代码展示</p></div>
<div data-w="2"><h3>转化类</h3><p>CTA · 表单 · 订阅 · 清单 · 倒计时 · 公告条</p></div>
<div data-w="2"><h3>媒体类</h3><p>Bento · 画布墙 · 滚动横幅 · 前后对比 · 竖版卡</p></div>
<div data-w="2"><h3>社交证明</h3><p>证言 · 便签墙 · 信任条 · 团队</p></div>
HTML],
            ['_type' => 'tool-grid', '_key' => $k(), 'title' => '配套工具', 'subtitle' => 'TOOLKIT', 'content' => <<<HTML
<div><span class="tg-tag">建站</span><h3>页面构建器</h3><p>拖拽区块、实时预览、批量模板、版本记录。</p><em>随 OpenFlow</em></div>
<div><span class="tg-tag">AI</span><h3>组件工厂</h3><p>描述 / HTML / 网址 / 截图 → 可复用模块。</p><em>4 种方式</em></div>
<div><span class="tg-tag">性能</span><h3>边缘加速</h3><p>静态资源 R2 + Workers，图片 WebP 自动转换。</p><em>全球 CDN</em></div>
<div><span class="tg-tag">SEO</span><h3>技术 SEO</h3><p>sitemap · JSON-LD · sitemap · 结构化数据 · 收录推送。</p><em>全家桶</em></div>
HTML],
            ['_type' => 'code', '_key' => $k(), 'title' => '无头模式：三行接上你的前端', 'subtitle' => 'HEADLESS', 'content' => <<<HTML
const r = await fetch('https://nownexts.com/api/public-content?type=articles&limit=12');
const { items } = await r.json();
render(items);  // 你的组件，你的样式
HTML],
            ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>必须用你们的前端吗？</summary><p>不。后台是完整 CMS，前台既可以由系统直出（PHP SSR），也可以走无头 API 由你自己的前端渲染。</p></details>
<details><summary>区块不够用怎么办？</summary><p>两条路：组件工厂让 AI 造（描述/HTML/网址/截图四种方式），或者插件系统注册自定义区块（代码级）。</p></details>
<details><summary>页面性能如何？</summary><p>静态资源全上 CDN、图片自动 WebP、页面级缓存、区块渲染零数据库查询（JSON 直读）。</p></details>
HTML],
            ['_type' => 'cta', '_key' => $k(), 'title' => '内容在后台，展示随你换', 'subtitle' => 'WEBS FLOW', 'content' => '看示例页感受区块系统的上限，或直接进后台搭你自己的页面。', 'button_text' => '看完整示例', 'button_url' => '/b/growth-os-tour'],
        ],
    ];
    $webs['blocks'][] = matrix_block('webs-flow', $k);
    save_builder_page('', $webs);
    echo "✅ Webs Flow 页已创建\n";
}

/* ─────────────── UserLoop · 用户行为循环 ─────────────── */
if (!isset($have['userloop'])) {
    $ul = [
        'slug' => 'userloop', 'title' => 'UserLoop · 用户行为循环', 'status' => 'published',
        'seo_title' => 'UserLoop — 用户行为循环引擎 | 芭乐派',
        'seo_desc' => '埋点、身份合并、画像、分群、旅程、复购——把散落的用户行为接成一个可运营的循环。',
        'blocks' => [
            ['_type' => 'hero', '_key' => $k(), 'title' => '用户的每一步，<br>都该变成<i class="si">下一步的理由</i>', 'subtitle' => 'USER LOOP', 'content' => 'UserLoop 把行为埋点、身份合并、画像分群、旅程分析与复购循环装进一个引擎：数据自己会说话，行为自己会变钱。', 'button_text' => '进入 CDP 后台', 'button_url' => '/xmp/cdp'],
            ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>1</b><span>行 JS 全站埋点</span></div>
<div><b>自动</b><span>身份合并建档</span></div>
<div><b>实时</b><span>分群进出</span></div>
<div><b>闭环</b><span>行为→触达→回流</span></div>
HTML],
            ['_type' => 'journey', '_key' => $k(), 'title' => '一个访客的数据旅程', 'subtitle' => 'DATA LOOP', 'content' => <<<HTML
<div><h3>匿名埋点</h3><p>一行脚本自动采集 page_view / 点击 / 提交，匿名 uid 建档。</p></div>
<div><h3>身份合并</h3><p>注册/登录时匿名档案合并进真实账号，历史行为一分不丢。</p></div>
<div><h3>画像成形</h3><p>浏览偏好、来源渠道、内容兴趣自动打进画像，规则标签同步运行。</p></div>
<div><h3>分群流转</h3><p>进群/出群事件实时触发：高意向、沉睡、复购-ready，分群自己会动。</p></div>
<div><h3>闭环回流</h3><p>分群直接喂给 MFlow 触达、Webs Flow 页面定向——行为变成增长动作。</p></div>
HTML],
            ['_type' => 'features', '_key' => $k(), 'title' => '行为数据的一站式底座', 'subtitle' => '核心能力', 'content' => <<<HTML
<div><h3>事件总线</h3><p>全站埋点批量上报、插件级接入（UserLoop Tracker）、API 直推，事件先落库再分发。</p></div>
<div><h3>身份图谱</h3><p>匿名 uid ↔ 邮箱 ↔ 手机号自动合并，一个用户一张完整档案。</p></div>
<div><h3>画像与标签</h3><p>维度画像 + 自动打标规则 + 手动标签，分群支持嵌套条件。</p></div>
<div><h3>旅程与漏斗</h3><p>页面路径、转化漏斗、留存曲线；行为数据反哺洞察报表。</p></div>
HTML],
            ['_type' => 'code', '_key' => $k(), 'title' => '接入只要一行', 'subtitle' => 'install', 'content' => <<<HTML
<!-- 全站页脚自动注入，也可手动 -->
<script src="https://nownexts.com/assets/cdp-track.js" async></script>
<!-- 之后：任何页面/点击/提交自动进事件总线 -->
HTML],
            ['_type' => 'cluster', '_key' => $k(), 'title' => '没有行为底座，增长全是猜', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>触达全靠群发</h4><p>不知道谁在看什么，只能一刀切全量推送，打扰与流失并存。</p></div>
<div><h4>画像靠脑补</h4><p>用户兴趣、意向度全凭感觉，分群是拍脑袋的。</p></div>
<div><h4>漏单找不到原因</h4><p>访客在哪一步走掉、为什么走掉，没有数据只能开会猜。</p></div>
<div><h4>个性化做不了</h4><p>同一页面给所有人看同一内容，转化率天花板锁死。</p></div>
HTML],
            ['_type' => 'tool-grid', '_key' => $k(), 'title' => '与矩阵的衔接', 'subtitle' => 'INTEGRATION', 'content' => <<<HTML
<div><span class="tg-tag">→ MFlow</span><h3>分群即人群</h3><p>UserLoop 分群直接作为 MFlow 工作流的触发与目标人群。</p><em>自动</em></div>
<div><span class="tg-tag">→ Webs Flow</span><h3>分群即定向</h3><p>任何页面区块按分群显示不同内容。</p><em>自动</em></div>
<div><span class="tg-tag">→ OpenFlow</span><h3>行为即洞察</h3><p>转化漏斗、推荐个性化、体检建议全部吃 UserLoop 数据。</p><em>原生</em></div>
<div><span class="tg-tag">→ Insight Flow</span><h3>事件即情报</h3><p>行为事件 HMAC 出站给增长情报系统做深度分析。</p><em>插件</em></div>
HTML],
            ['_type' => 'stats', '_key' => $k(), 'title' => '行为数据跑起来的样子', 'subtitle' => 'IMPACT', 'content' => <<<HTML
<div><b>1 行</b><span>接入代码</span></div>
<div><b>0 丢失</b><span>登录合并档案</span></div>
<div><b>实时</b><span>分群进出事件</span></div>
<div><b>4 个</b><span>下游消费方</span></div>
HTML],
            ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>UserLoop 是独立产品还是功能？</summary><p>是矩阵中的独立产品：行为采集与循环引擎。它自带插件化接入（UserLoop Tracker），数据既是自己的底座，也是矩阵其他产品的燃料。</p></details>
<details><summary>匿名用户能追踪吗？</summary><p>能。匿名 uid 先建档，注册/登录时自动合并进真实账号，跨设备行为不丢失。</p></details>
<details><summary>数据怎么用才合规？</summary><p>内置同意管理（consent）与脱敏导出，画像仅服务你自己的站点运营，可随时导出或删除。</p></details>
HTML],
            ['_type' => 'cta', '_key' => $k(), 'title' => '让用户行为，自己转出增长', 'subtitle' => 'USERLOOP', 'content' => '装上埋点、看清旅程、圈对人群——然后把剩下的交给 MFlow 和 Webs Flow。', 'button_text' => '开始建档', 'button_url' => '/xmp/cdp'],
        ],
    ];
    $ul['blocks'][] = matrix_block('userloop', $k);
    save_builder_page('', $ul);
    echo "✅ UserLoop 页已创建\n";
}

echo "全部完成\n";
