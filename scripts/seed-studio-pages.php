<?php
/**
 * 林下 Understory · 工作台层（Studio 套件）产品页
 * 第一批（2026-10-09）：林可兔 LinkTo / 鹿蕊 Litmus
 * 第二批（2026-10-09）：松萝 Liana / ConFlow / 零真 ZeroZen
 *
 * 素材源：各产品 GitHub 仓库 README（2026-10-09 版），全部表述可核验——GTM §2「可以说/不能说」约束。
 * 新口径：产品矩阵块改用「林下三层」（入口层 / 进阶层 / 工作台层），任务 A 铺开时其余产品页统一换成此块。
 * 用法: php scripts/seed-studio-pages.php（幂等，upsert 可反复重跑）
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
            echo "♻  $slug 重写\n";
            return;
        }
    }
    save_builder_page('', $page);
    echo "✅ $slug 创建\n";
}

/** 林下家族矩阵块（新口径 · 三层结构）——各 Studio 页共用，任务 A 铺开时成为全站标准块 */
function understory_matrix_block(string $self, callable $k): array {
    $cards = [
        ['入口层', 'OpenFlow', '全站增长操作系统 · 四力合一（内容 · 数据 · 触达 · 销售），不部署也能活，部署了今天就有大团队的能力', 'API 互通'],
        ['进阶层 · Flow 家族', '从用到好的升级路径', 'MFlow 内容营销 · WebsFlow 落地页工场 · UserLoop 全域数据 · inFlow 外部情报 · PayFlow 收款变现 · LearnFlow 课程交付', '独立可用'],
        ['工作台层 · Studio', '长在桌面上的工作台', '鹿蕊 Litmus 知识 OS · 松萝 Liana 输入法 · ConFlow 视频⇄HTML · 林可兔 LinkTo 邮件工作台 · 零真 ZeroZen 浏览净化', '本地工具'],
        ['品牌', '林下 Understory', '品牌与产品矩阵之名——产品间 API 互通、零强制绑定、按需组合', '家族图谱'],
    ];
    $html = '';
    foreach ($cards as $c) {
        [$tag, $name, $desc, $em] = $c;
        $html .= "<div><span class=\"tg-tag\">{$tag}</span><h3>" . htmlspecialchars($name) . "</h3><p>{$desc}</p><em>{$em}</em><a href=\"/product\" style=\"color:var(--accent);font-size:13px;font-weight:700\">看家族图谱 →</a></div>";
    }
    return ['_type' => 'tool-grid', '_key' => $k(), 'title' => '林下 Understory · 三层定位', 'subtitle' => '产品矩阵', 'content' => $html];
}

/* 全站标准证言墙（与既有产品页一致） */
function standard_quote_wall(callable $k): array {
    return ['_type' => 'quote-wall', '_key' => $k(), 'title' => '用过的人怎么说', 'subtitle' => '真实反馈', 'content' => <<<'HTML'
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」<br><span style="color:var(--muted);font-size:12.5px">— 陈默 · 内容工作室</span></div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」<br><span style="color:var(--muted);font-size:12.5px">— 独立开发者</span></div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」<br><span style="color:var(--muted);font-size:12.5px">— 增长负责人</span></div>
<div>「不用换我的商城，接上就收款。」<br><span style="color:var(--muted);font-size:12.5px">— DTC 卖家</span></div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」<br><span style="color:var(--muted);font-size:12.5px">— 前端工程师</span></div>
<div>「课程播放器拖进度条很流畅，学员完课率肉眼可见在涨。」<br><span style="color:var(--muted);font-size:12.5px">— 训练营主理人</span></div>
HTML];
}

/* ═══════════════════ 林可兔 LinkTo · AI 原生邮件工作台 ═══════════════════ */
upsert_page('linkto', [
    'slug' => 'linkto', 'title' => '林可兔 LinkTo · AI 原生邮件工作台', 'status' => 'published',
    'seo_title' => '林可兔 LinkTo — AI 原生邮件工作台 | 芭乐派',
    'seo_desc' => '多账户统一收件箱 + AI 分类降噪 / 摘要 / 起草 + Agent 记忆成长系统。国内外主流邮箱一键接入，数据全本地（SQLite + 系统钥匙串加密），备份口令加密跨设备迁移。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '邮件自己会分类，回复自己会起草', 'subtitle' => '林可兔 LINKTO · AI 原生邮件工作台', 'content' => '基础体验对齐 Canary Mail（多账户、统一收件箱、通知、签名、模板、规则），在此之上叠加 AI 原生：智能分类降噪、会话摘要、起草回复，还有随你成长的 Agent 记忆——全部数据留在你自己的电脑上。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/linkto'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>多账户</b><span>统一收件箱 · 一键授权</span></div>
<div><b>四类</b><span>AI 分类降噪</span></div>
<div><b>五维</b><span>Agent 记忆持续成长</span></div>
<div><b>全本地</b><span>SQLite · 零上传</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '收件箱是噪声重灾区', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>四像混流</h4><p>个人信件、系统通知、订阅更新、营销邮件挤在同一个列表里，找一封正事要翻三屏。</p></div>
<div><h4>重要信息转瞬即逝</h4><p>验证码埋在正文里、账单还款日要自己记、会议邀请散落在各处。</p></div>
<div><h4>回信永远写不完</h4><p>该回的信越攒越多，每一封都要重新交代一遍上下文。</p></div>
<div><h4>换设备从头再来</h4><p>新电脑上账户要重配、密码要重填，历史邮件还要重新同步一遍。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '一封邮件进来之后', 'subtitle' => 'INBOX FLOW', 'content' => <<<HTML
<div><h3>一键授权接入</h3><p>Gmail 浏览器弹窗授权 / Outlook 系设备码授权，token 临期自动刷新；未配置凭据时自动降级为应用密码引导。</p></div>
<div><h3>AI 四类降噪</h3><p>新邮件自动分入 个人 / 通知 / Newsletter / 噪声，正事不再被淹没。</p></div>
<div><h3>就地提炼</h3><p>验证码气泡一键复制、账单与会议自动转待办、订阅文章自动进阅读清单。</p></div>
<div><h3>每日商情</h3><p>通读近 36 小时邮件，输出主题 digest + 当日重点 + 备忘录 + 清理建议。</p></div>
<div><h3>记忆沉淀</h3><p>事实 / 偏好 / 人物关系 / 承诺 / 惯例自动入库；起草与问答都会参考「你是谁、你答应过什么」。</p></div>
HTML],
        ['_type' => 'features', '_key' => $k(), 'title' => '为什么选林可兔', 'subtitle' => 'FEATURES', 'content' => <<<HTML
<div><h3>AI 原生</h3><p>智能分类降噪 · 会话摘要 · AI 起草回复 · 提取待办；模型自己配（智谱 GLM / OpenAI / DeepSeek / Ollama 均可，流式输出）。</p></div>
<div><h3>Agent 记忆成长</h3><p>从邮件沉淀长期记忆并注入所有 AI 能力；发件人画像（关系总结 · 高频主题 · 回复率）随往来更新；本地存储可查看 / 编辑 / 归档。</p></div>
<div><h3>一键授权</h3><p>Gmail / Outlook · Hotmail · Microsoft 365 点卡片即授权自动完成，token 临期自动刷新。</p></div>
<div><h3>预设齐全</h3><p>Gmail / Outlook / iCloud / QQ / 163 / 126 / 139 / 新浪 / 腾讯企业邮 / 阿里云邮 / Yahoo / Zoho 官方推荐参数全部预填。</p></div>
<div><h3>备份即迁移</h3><p>口令加密（AES-256-GCM + scrypt）备份文件投递 iCloud Drive / Dropbox / OneDrive / 坚果云同步目录，新设备导入即恢复。</p></div>
<div><h3>订阅治理</h3><p>Newsletter 自动识别、订阅发件人管理、List-Unsubscribe 一键退订。</p></div>
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => '林可兔开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>多账户统一收件箱 · 通知 · 签名 · 模板 · 规则 · 集成</li>
<li>智能洞察四 Tab：今日商情 / 待办 / 阅读清单 / 公司洞察</li>
<li>发送增强：延迟发送（可撤销）与定时发送（逐条撤销 · 失败自动重试）</li>
<li>⌘K 命令面板：导航 / 动作 / 邮件直达</li>
<li>三层换肤：明暗 · 9 款主题 · 24 款阅读信纸</li>
<li>Liquid Glass 界面：环境光斑 · 玻璃拟态 · 指针跟随高光</li>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '与林下家族的边界', 'subtitle' => '独立但不孤立', 'content' => <<<HTML
<div><b>对 OpenFlow</b><span>林下 Understory 家族成员；OpenFlow 连接器架构（Manifest 声明式框架）已按统一 Intake 协议接入。</span></div>
<div><b>对鹿蕊 Litmus</b><span>邮件一键收藏为知识条目——通信归林可兔，知识归鹿蕊，各归其位。</span></div>
<div><b>对 Studio 同层</b><span>与鹿蕊（知识）/ 松萝（输入）/ ConFlow（内容）/ 零真（净化）同为工作台层本地工具，独立可用、按需组合。</span></div>
HTML],
        ['_type' => 'cmp', '_key' => $k(), 'title' => '通用客户端 vs 云端 AI 邮箱 vs 林可兔', 'subtitle' => '对比', 'content' => <<<HTML
<table class="cmp"><thead><tr><th scope="col">维度</th><th scope="col">通用邮件客户端</th><th scope="col">云端 AI 邮箱</th><th scope="col" class="ol">林可兔</th></tr></thead><tbody>
<tr><th scope="row">AI 能力</th><td data-l="通用邮件客户端">基础过滤与规则</td><td data-l="云端 AI 邮箱">AI 在云端处理，正文离开本机</td><td class="ol y" data-l="林可兔">本地调用你自配的模型，正文不落云</td></tr>
<tr><th scope="row">记忆</th><td data-l="通用邮件客户端">无记忆，每次从零开始</td><td data-l="云端 AI 邮箱">会话级上下文</td><td class="ol y" data-l="林可兔">五维长期记忆，重复出现自动提升置信度</td></tr>
<tr><th scope="row">账户接入</th><td data-l="通用邮件客户端">手动填服务器参数</td><td data-l="云端 AI 邮箱">各家支持不一</td><td class="ol y" data-l="林可兔">一键授权 + 国内外主流邮箱参数全预填</td></tr>
<tr><th scope="row">数据归属</th><td data-l="通用邮件客户端">邮件托管在服务器</td><td data-l="云端 AI 邮箱">正文与索引都在云端</td><td class="ol y" data-l="林可兔">本地 SQLite（FTS5）+ 系统钥匙串加密</td></tr>
<tr><th scope="row">跨设备</th><td data-l="通用邮件客户端">重新配置账户</td><td data-l="云端 AI 邮箱">重新登录</td><td class="ol y" data-l="林可兔">口令加密备份投递网盘，新设备导入即恢复</td></tr>
</tbody></table><p class="cmp-note">对比口径：「通用邮件客户端」指传统桌面/移动邮件 App；「云端 AI 邮箱」指 AI 处理发生在服务端的邮箱产品，能力以其官方说明为准。林可兔的 AI 走你自己配置的 OpenAI 兼容接口，数据与记忆全部本地。</p>
HTML],
        standard_quote_wall($k),
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>和 OpenFlow 是什么关系？</summary><p>独立产品，林下 Understory 家族成员。OpenFlow 家族产品已按统一 Intake 协议接入连接器架构；不装 OpenFlow 也能单独用林可兔。</p></details>
<details><summary>支持哪些邮箱？</summary><p>国内外主流全部预填：Gmail / Outlook / iCloud / QQ / 163 / 126 / 139 / 新浪 / 腾讯企业邮 / 阿里云邮 / Yahoo / Zoho。Gmail 与 Outlook 系支持 OAuth 一键授权，未配置凭据时自动降级为应用专用密码引导。</p></details>
<details><summary>AI 用谁的模型？</summary><p>你自己配：OpenAI 兼容接口全接（智谱 GLM / OpenAI / DeepSeek / Ollama），流式输出，成本你控制。</p></details>
<details><summary>我的数据放在哪？</summary><p>全部本地：邮件存 SQLite（FTS5 中文全文检索），密码经系统钥匙串（safeStorage）加密，Agent 记忆本地存储、可查看 / 编辑 / 归档，不上传任何服务器。</p></details>
HTML],
        understory_matrix_block('linkto', $k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '今天开始，让收件箱自己整理自己', 'subtitle' => '林可兔 LINKTO · 林下 Understory 出品', 'content' => '本地优先，AI 原生，记忆随你成长——Link 音译「林可」，to 音译「兔」，动若脱兔。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/linkto'],
    ],
]);

/* ═══════════════════ 鹿蕊 Litmus · AI 时代的知识操作系统 ═══════════════════ */
upsert_page('litmus', [
    'slug' => 'litmus', 'title' => '鹿蕊 Litmus · AI 时代的知识操作系统', 'status' => 'published',
    'seo_title' => '鹿蕊 Litmus — AI 时代的知识操作系统 | 芭乐派',
    'seo_desc' => '不是又一个笔记软件：知识库是磁盘上一堆普通 Markdown，人用画布和阅读器看它，agent 用同一套本地 API 读写它。五模式一体、MCP 双向、核心永久开源（AGPL-3.0）、无锁定。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '人随手记，AI 帮你建，<br>一切是磁盘上的普通文件', 'subtitle' => '鹿蕊 LITMUS · 林下 Understory 出品', 'content' => '笔记软件要你先学方法论、再花几百小时搭体系；Agent 框架把知识锁进向量库。鹿蕊反过来：知识库就是一堆普通 Markdown——人用画布与阅读器看它，agent 用同一套本地 API 读写它，双方共享一个真相，谁也不锁定谁。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/thirdc'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>0 门槛</b><span>打开就能用 · 一句话建库</span></div>
<div><b>本地</b><span>磁盘上的普通 Markdown</span></div>
<div><b>MCP</b><span>双向 · 人机同一套 ABI</span></div>
<div><b>AGPL</b><span>核心永久开源 · 无锁定</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '知识库的两个失灵旧答案', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>笔记软件门槛高</h4><p>功能强大，但你得先学会它的方法论，再花几百个小时手工搭建。</p></div>
<div><h4>Agent 框架锁知识</h4><p>各类 RAG 方案把知识锁进向量库和私有格式，人反而看不见、改不了自己的知识。</p></div>
<div><h4>平台锁定</h4><p>换工具等于重来：导出的文件夹失去结构与链接，附件散落各处。</p></div>
<div><h4>笔记上不了台面</h4><p>从笔记到 PPT、到发布，要再手工做一遍——积累与表达是两套活。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '知识的一生', 'subtitle' => 'KNOWLEDGE FLOW', 'content' => <<<HTML
<div><h3>零门槛入手</h3><p>打开就能用；输入一个主题，自动从 Wikipedia / Hacker News / arXiv 采集成库。</p></div>
<div><h3>随手积累</h3><p>Memo 随手记（语音转文字）· 浏览器插件整页 / 选区采集 · PDF / DOCX 解析 · Obsidian 库直接导入。</p></div>
<div><h3>自然组织</h3><p>文件夹即结构、画布即视图——Flow 画布节点拖排、景深、连线、时间线、看板。</p></div>
<div><h3>AI 帮你长</h3><p>圈选批量整理 · 语义聚类搬移 · 划选即指令（总结 / 精简 / 改写 / 扩写 / 翻译）。</p></div>
<div><h3>随时上台</h3><p>Feynman 展示文档即幻灯片（50 款设计史风格）· 整库 PPT · AI-HTML 静态站 · llms.txt。</p></div>
HTML],
        ['_type' => 'tabs', '_key' => $k(), 'title' => '五模式，同一套数据', 'subtitle' => '模式是同一内核的不同界面配置，切换零成本', 'content' => <<<HTML
<div data-tab="Flow 画布"><h3>自由画布</h3><p>节点拖排 · 景深 · 连线 · 多画布 · 时间线 · 看板——纯手动、无 AI 也完整。</p></div>
<div data-tab="Feynman"><h3>学习-展示</h3><p>沉浸研读 + 文档即幻灯片，学习成果直接上台；50 款设计史经典风格实时换肤。</p></div>
<div data-tab="Memo"><h3>随手记一笔</h3><p>写作热力图 · 标签快查 · 语音速记——碎片记录的第一入口。</p></div>
<div data-tab="Agent-L"><h3>面向 AI 的 SAG 知识库</h3><p>对话优先 · 工具调用全程可见 · Agent 记忆——你和你的 agents 共享一个真相。</p></div>
<div data-tab="Studio"><h3>工作室</h3><p>发布 · 分享 · 分发，面向内外部生态与商业场景。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '双向 AI', 'subtitle' => '输入零门槛，输出随时上台', 'content' => <<<HTML
<div><b>输入侧 · 万物入袋</b><span>一键建库 / 零星积累 / 网页本地化 / MCP 连接器拉取外部系统。</span></div>
<div><b>输出侧 · 随时成稿</b><span>一键整理（圈选批量 / 语义聚类）/ 划选即指令 / 整库 PPT / AI-HTML 静态站。</span></div>
<div><b>无 Key 也能用</b><span>内置命令模式（离线规则引擎，工具调用照常可见）；配置任意 OpenAI 兼容模型后解锁 AI 动词与 token 计量。</span></div>
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => '鹿蕊开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>Flow 画布 · 时间线 · 看板 · Memo 热力图</li>
<li>Feynman 阅读 / 展示 · 50 款设计史风格实时换肤</li>
<li>MCP 双向：thirdc mcp 可作为 server 被其他 agent 调用</li>
<li>Open Knowledge Format：Markdown 语义 + YAML 元数据 + 块锚点 + JSON-LD</li>
<li>浏览器扩展源码在库（Chrome / Firefox / Safari）</li>
<li>多平台客户端：macOS dmg · Windows msi/nsis · Linux deb/AppImage · 移动端可源码构建</li>
HTML],
        ['_type' => 'cmp', '_key' => $k(), 'title' => '笔记软件 vs RAG 框架 vs 鹿蕊', 'subtitle' => '对比', 'content' => <<<HTML
<table class="cmp"><thead><tr><th scope="col">维度</th><th scope="col">笔记软件</th><th scope="col">RAG 框架</th><th scope="col" class="ol">鹿蕊</th></tr></thead><tbody>
<tr><th scope="row">知识存储</th><td data-l="笔记软件">私有格式 + 方法论门槛</td><td data-l="RAG 框架">向量库黑盒，人看不见改不了</td><td class="ol y" data-l="鹿蕊">磁盘普通 Markdown，人机共享一个真相</td></tr>
<tr><th scope="row">Agent 接入</th><td data-l="笔记软件">插件市场间接接入</td><td data-l="RAG 框架">需自建管道</td><td class="ol y" data-l="鹿蕊">MCP 双向，人机同一套本地 API</td></tr>
<tr><th scope="row">上手门槛</th><td data-l="笔记软件">先学方法论再搭体系</td><td data-l="RAG 框架">需要开发能力</td><td class="ol y" data-l="鹿蕊">打开就能用，一句话建库</td></tr>
<tr><th scope="row">成稿输出</th><td data-l="笔记软件">模板手动排</td><td data-l="RAG 框架">PPT 与发布要另做</td><td class="ol y" data-l="鹿蕊">Feynman 50 款风格 + 整库 PPT + AI-HTML 静态站</td></tr>
<tr><th scope="row">锁定风险</th><td data-l="笔记软件">导出即失序</td><td data-l="RAG 框架">管道要自己维护</td><td class="ol y" data-l="鹿蕊">任何编辑器都能打开，任何 agent 都能读写</td></tr>
</tbody></table><p class="cmp-note">对比口径：「笔记软件」指以方法论与双链为卖点的知识管理工具；「RAG 框架」指把文档灌入向量库供 LLM 检索的通用方案，能力以其官方说明为准。鹿蕊的立场：知识不锁进任何私有格式。</p>
HTML],
        standard_quote_wall($k),
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>和 OpenFlow 是什么关系？</summary><p>独立产品，林下 Understory 工作台层（Studio）成员。矩阵产品（OpenFlow / ConFlow / WebsFlow / PayFlow / inFlow）经 MCP 连接器接入，互相独立、按需组合。</p></details>
<details><summary>我的数据在哪？</summary><p>默认库在 ~/Documents/ThirdC（应用内可切换）：磁盘上的普通 Markdown / HTML / 附件，任何编辑器都能打开，任何 agent 都能读写——这就是「文件即真相」。</p></details>
<details><summary>必须配 AI Key 吗？</summary><p>不必。内置命令模式离线可用（规则引擎，工具调用照常可见）；在 thirdc.toml 配置任意 OpenAI 兼容模型后，解锁改写 / 扩写 / 翻译等 AI 动词。</p></details>
<details><summary>怎么装？</summary><p>Releases 下载桌面客户端（macOS dmg / Windows msi / Linux deb · AppImage）；也可 CLI + Web：thirdc serve 守护进程 + HTTP API，五模式 UI 是它的桌面。</p></details>
HTML],
        understory_matrix_block('litmus', $k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '让知识自己长成样子', 'subtitle' => '鹿蕊 LITMUS · 林下 Understory 出品', 'content' => '鹿蕊是共生地衣——人类最早的「真相指示剂」（石蕊试纸）提取自它。人与 AI 共生，显色见真。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/thirdc'],
    ],
]);

/* ═══════════════════ 松萝 Liana · 隐私优先的跨平台输入法 ═══════════════════ */
upsert_page('liana', [
    'slug' => 'liana', 'title' => '松萝 Liana · 隐私优先的跨平台输入法', 'status' => 'published',
    'seo_title' => '松萝 Liana — 隐私优先的跨平台输入法 | 芭乐派',
    'seo_desc' => '引擎、词典、学习全部本机运行，云端一个字节都拿不到。全拼 20 万词条 + Viterbi 整句、双拼×3、中英混输，纠错即教学、喂食式学习，局域网 P2P 端到端加密同步。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '你的每一个按键，<br>都只留在你的设备上', 'subtitle' => '松萝 LIANA · 林下 Understory 出品', 'content' => '市面上绝大多数输入法把按键、词频、习惯送上云端换「智能」；松萝把方向反过来——所有智能都在本地产生，云端一个字节都拿不到。Rust 内核 13 个纯逻辑 crate，214 项单元测试全绿，安装包断网可用。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/InputFlow'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>零遥测</b><span>没有「匿名上报」· 联网纠错</span></div>
<div><b>20 万</b><span>词条 + Viterbi 整句转换</span></div>
<div><b>214 项</b><span>内核单元测试全绿</span></div>
<div><b>&lt;1MB</b><span>学习模型 · 只存本机</span></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '三个承诺', 'subtitle' => '松萝的立场', 'content' => <<<HTML
<div><b>🔒 按键不离开设备</b><span>引擎、词典、学习全部本机运行，安装包可断网使用。</span></div>
<div><b>🙈 零遥测、零云 API</b><span>没有「匿名上报」，没有「联网纠错」；本地 AI 模型由你显式下载、可随时删除。</span></div>
<div><b>🔍 一切可审计</b><span>AGPL-3.0 开源，Rust 内核零第三方依赖，插件是数据不是代码。</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '输入法的「智能」，代价是什么', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>智能换数据</h4><p>你打的每一个字都经过输入法——多数输入法把按键、词频、习惯送上云端换「智能」。</p></div>
<div><h4>群体词频不对你</h4><p>在这个应用、这个话题、这个时段，xian 是先、县还是西安——答案不在人群里，在你和此刻的上下文里。</p></div>
<div><h4>教了记不住</h4><p>专有名词教了又忘；你的删除被云输入法当噪声丢掉，而不是当教材。</p></div>
<div><h4>装输入法像装监控</h4><p>遥测埋在「联网纠错」与「用户体验计划」里，关都关不干净。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '一个打字的人，怎么被它懂得', 'subtitle' => 'TYPE FLOW', 'content' => <<<HTML
<div><h3>专业引擎起步</h3><p>全拼（20 万词条 + Viterbi 整句 + 前缀补全）· 双拼×3（小鹤 / 微软 / 自然码）· 英文 · 日语罗马字；中英混输，大写即英文意图。</p></div>
<div><h3>学你的切换</h3><p>按应用记忆中/英：终端自动英文、微信自动中文——本地统计模型，Shift 一按即学。</p></div>
<div><h3>纠错即教学</h3><p>选词 +1 · 删除后重选 +1.5 · 选了又删 −2（最强负反馈）——云输入法把删除当噪声，松萝当教材。</p></div>
<div><h3>喂它一段</h3><p>截图（本机 OCR，零网络）/ 文档 / 粘贴文本 → 专有名词进「营养库」：你的甲方名字教一次，处处可打。</p></div>
<div><h3>傍晚小结</h3><p>对比「学习开 vs 关」的反事实首选命中率——学习有没有用，数字说话，不掀桌：重排只微调排序，词频与整句概率仍是基本盘。</p></div>
HTML],
        ['_type' => 'features', '_key' => $k(), 'title' => '为什么选松萝', 'subtitle' => 'FEATURES', 'content' => <<<HTML
<div><h3>本地 AI 增强（可选）</h3><p>L0 知你自进化（默认开启）· L1 端上语音边说边落字 · L2 小模型一键下载（sha256 校验，本机运行）· 同声传译（仅 127.0.0.1 回环）——不支持端上就禁用，绝不回退云端。</p></div>
<div><h3>有生命的桌宠</h3><p>VRM 3D 渲染器（跟随鼠标注视 / 眨眼 / 弹簧骨物理），内置形象离线零下载；点击即中英切换，跨屏跟随。</p></div>
<div><h3>插件 = 数据包</h3><p>皮肤 / 桌宠 / 词典声明式零代码执行，权限白名单制——无在线市场、无自动更新、无可执行代码。</p></div>
<div><h3>权限中心</h3><p>全部敏感能力集中展示：碰什么数据、存在哪、怎么删——关闭即清空。</p></div>
<div><h3>零内容统计</h3><p>速度、纠错、节省击键——只计次与秒，绝不记录任何按键内容。</p></div>
<div><h3>局域网 P2P 同步</h3><p>设备间扫码配对（Ed25519 + 一次性配对码 + 6 位校验码防中间人），端到端加密，没有服务器没有账号，默认关闭。</p></div>
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => '松萝开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>全拼（20 万词条 + Viterbi + 前缀补全）· 双拼×3 · 英文（2 万词 + 词频）· 日语罗马字</li>
<li>中英混输 · 表情模式（连按 aa）· 符号输入（u 前缀）· 简繁转换（OpenCC 词表）</li>
<li>知你自进化：纠错即教学 + 营养库 + 反事实评估（L0，默认开启）</li>
<li>本地语音听写与同声传译（可选，显式下载 · 本机运行）</li>
<li>桌宠 VRM 渲染器（内置形象零下载，可导入 VRoid Studio 自捏角色）</li>
<li>用户词与剪贴板历史 ChaCha20-Poly1305 加密落盘，密钥在系统钥匙串</li>
HTML],
        ['_type' => 'cmp', '_key' => $k(), 'title' => '云输入法 vs 无学习本地输入法 vs 松萝', 'subtitle' => '对比', 'content' => <<<HTML
<table class="cmp"><thead><tr><th scope="col">维度</th><th scope="col">云输入法</th><th scope="col">无学习本地输入法</th><th scope="col" class="ol">松萝</th></tr></thead><tbody>
<tr><th scope="row">数据归属</th><td data-l="云输入法">按键、词频、习惯上云</td><td data-l="无学习本地输入法">本机运行，但基本不学习</td><td class="ol y" data-l="松萝">全本机 + 加密落盘，密钥在系统钥匙串</td></tr>
<tr><th scope="row">学习</th><td data-l="云输入法">云端群体模型，优化「平均人」</td><td data-l="无学习本地输入法">无自进化</td><td class="ol y" data-l="松萝">纠错即教学 + 营养库，反事实命中率可验证</td></tr>
<tr><th scope="row">AI 能力</th><td data-l="云输入法">云端 AI 推荐，离不开网</td><td data-l="无学习本地输入法">无</td><td class="ol y" data-l="松萝">端上模型显式下载、本机运行、可随时删除</td></tr>
<tr><th scope="row">隐私透明</th><td data-l="云输入法">隐私政策要读三遍</td><td data-l="无学习本地输入法">权限模糊</td><td class="ol y" data-l="松萝">AGPL 开源 + 权限中心 + 零内容统计</td></tr>
<tr><th scope="row">跨设备</th><td data-l="云输入法">账号云同步</td><td data-l="无学习本地输入法">逐台手工配置</td><td class="ol y" data-l="松萝">局域网 P2P 端到端加密，无服务器无账号</td></tr>
</tbody></table><p class="cmp-note">对比口径：「云输入法」指词频与纠错依赖云端服务的输入法产品；「无学习本地输入法」指系统自带类输入法，能力以其官方说明为准。松萝的立场：所有智能都在本地产生。</p>
HTML],
        standard_quote_wall($k),
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>和 OpenFlow 是什么关系？</summary><p>独立产品，林下 Understory 工作台层（Studio）成员。它不是 Flow，也不依赖矩阵任何产品——单独安装即可用。</p></details>
<details><summary>我的数据在哪？</summary><p>全部本机：学习模型不到 1MB、加密落盘（ChaCha20-Poly1305，密钥在系统钥匙串），用户词随备份搬走、一键清空；安装包断网可用。</p></details>
<details><summary>AI 功能要联网吗？</summary><p>不要。本地模型由你显式下载、本机运行、可随时删除；设备不支持端上模型就禁用该能力，绝不回退云端。</p></details>
<details><summary>怎么装？</summary><p>macOS 13+：双击松萝安装器 → 一键安装到 ~/Library/Input Methods → 键盘设置添加「松萝」即可；开发者可从源码构建（cargo test 214 项全绿是合并底线）。</p></details>
HTML],
        understory_matrix_block('liana', $k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '打字这件事，不需要交给云', 'subtitle' => '松萝 LIANA · 林下 Understory 出品', 'content' => '松萝，附松而生、随枝而长的地衣——不打扰你，但一直在随你进化。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/InputFlow'],
    ],
]);

/* ═══════════════════ ConFlow · 视频⇄HTML 双向内容引擎 ═══════════════════ */
upsert_page('conflow', [
    'slug' => 'conflow', 'title' => 'ConFlow · 视频⇄HTML 双向内容引擎', 'status' => 'published',
    'seo_title' => 'ConFlow — 看完一条视频，得到一堆可上线的内容 | 芭乐派',
    'seo_desc' => '贴一条 YouTube 链接，拿回一篇按论证结构重写的文章 + 一套重新设计的幻灯片。脚本管确定性，LLM 按写作法管语义，防幻觉是规则不是愿望。可直推 OpenFlow 草稿箱一键发布。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '看完一条视频，<br>得到一堆可上线的内容', 'subtitle' => 'ConFlow · 视频⇄HTML 双向内容引擎', 'content' => '你不用再「回头整理一下视频」。贴一条链接，拿回一篇能直接发布的文章，和一套能当众放映的幻灯片：脚本管确定性，LLM 按写作法管语义——数字逐字核对、补全显式标注、观点归属分离。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/V2HTML'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>4 种</b><span>文体写作法（教程/科普/评论/评测）</span></div>
<div><b>30 种</b><span>幻灯片主题 · 放映中实时换肤</span></div>
<div><b>10 种</b><span>产出语言 · 防幻觉规则不降级</span></div>
<div><b>一条</b><span>流水线：视频→文章→幻灯片→短视频</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '看过的视频，最后什么都没留下', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>视频没法用</h4><p>没法搜索、没法引用、没法贴进文档——分享给同事只能甩个链接加一句「这段讲得挺好」。</p></div>
<div><h4>手工整理三小时</h4><p>把一小时视频整理成文章和 PPT，往往要三个小时——然后你就放弃了。</p></div>
<div><h4>字幕流水账</h4><p>转写稿按时间线性堆砌，论证结构全丢——那不是内容，是原料。</p></div>
<div><h4>幻灯片是截图拼贴</h4><p>截图拼贴是时间的碎片；能放映的幻灯片是论证的空间化——标题写论断句，一页一个论点。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '贴一条链接之后', 'subtitle' => 'CONTENT FLOW', 'content' => <<<HTML
<div><h3>贴链接</h3><p>前台表单贴视频链接选文体，或 CLI 一条命令，或在 ZCode 里说「用 v2html 把这条视频转成教程」。</p></div>
<div><h3>脚本抓素材</h3><p>视频、字幕、关键帧——确定性工作交给脚本，可复现；每步执行日志可查。</p></div>
<div><h3>按文体写作法成文</h3><p>系统先判定文体再执行对应方法论：教程带逐字核对过的命令和避坑清单；科普直觉→机制→边界分层递进；评论把论点与论据可信度摆开。</p></div>
<div><h3>重新设计幻灯片</h3><p>论断句标题、只嵌入含独有信息的原视频帧、每页标注时间戳——30 种主题放映中按 T 实时切换。</p></div>
<div><h3>直接上线</h3><p>文章自动推进 OpenFlow 内容库草稿箱，审完一键发布；幻灯片浏览器直接放映，按 P 导出 PDF。</p></div>
HTML],
        ['_type' => 'features', '_key' => $k(), 'title' => '特色能力', 'subtitle' => 'FEATURES', 'content' => <<<HTML
<div><h3>四种文体四种写作法</h3><p>教程、科普、口播评论、评测访谈各有一套沉淀在 prompts/ 里的方法论——不是「总结视频」。</p></div>
<div><h3>短视频脚本版</h3><p>同任务可追加产出 45–90 秒口播脚本：节拍表逐拍给秒数、口播词、画面提示——视频→文章→幻灯片→短视频物料，一条流水线。</p></div>
<div><h3>十种产出语言</h3><p>中 / 英 / 日 / 韩 / 西 / 法 / 德 / 葡 / 俄 / 阿版本文章与幻灯片；语言是硬性要求，防幻觉规则不因语言改变。</p></div>
<div><h3>反方向也成立</h3><p>v2video 把旧文章逆向成分镜提示词包（Sora / Veo / 可灵直接可用），或输出 HTML 动画演示——视频变内容，内容再变回视频。</p></div>
<div><h3>三形态运行</h3><p>CLI 直转（一条命令 + 一个 OpenAI 兼容 key）/ 客户端 ZCode 技能 / 服务端 FastAPI + 管理后台——同一份 prompts 与 engine，形态只是壳。</p></div>
<div><h3>产物原样可查</h3><p>仓库 output/ 目录里的文章与幻灯片无任何手工排版——看到了就是真实产出水平。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '防幻觉是规则，不是愿望', 'subtitle' => '方法论写死的防线', 'content' => <<<HTML
<div><b>数字逐字核对</b><span>数字与命令必须对照转写稿和视频帧逐字核对，不是「大概是这样」。</span></div>
<div><b>补全显式标注</b><span>视频没讲清但流程必需的内容，用「💡 补全」显式标注——补的署名补全作者，不冒充视频作者。</span></div>
<div><b>观点归属分离</b><span>视频作者的归作者，补全的署名写作者；每页幻灯片带原视频时间戳，讲错了随时回跳核对。</span></div>
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => '一个任务的产物清单', 'subtitle' => 'OUTPUT', 'content' => <<<HTML
<li>doc.md — 按论证结构重写的文章（教程/科普/评论/评测四写作法）</li>
<li>slides — 30 主题幻灯片，浏览器直接放映、按 P 导出 PDF</li>
<li>script.md — 可选：45–90 秒口播短视频脚本（节拍表逐拍秒数）</li>
<li>storyboard.json — 分镜提示词包（Sora / Veo / 可灵直接可用）</li>
<li>十种产出语言任选（防幻觉规则不降级）</li>
<li>文章直推 OpenFlow 内容库草稿箱（带来源视频与幻灯片链接）</li>
HTML],
        ['_type' => 'cmp', '_key' => $k(), 'title' => '字幕转写 vs AI 摘要 vs ConFlow', 'subtitle' => '对比', 'content' => <<<HTML
<table class="cmp"><thead><tr><th scope="col">维度</th><th scope="col">字幕转写工具</th><th scope="col">AI 一段摘要</th><th scope="col" class="ol">ConFlow</th></tr></thead><tbody>
<tr><th scope="row">产出</th><td data-l="字幕转写工具">转写稿 + 时间轴</td><td data-l="AI 一段摘要">一段要点摘要</td><td class="ol y" data-l="ConFlow">文章 + 幻灯片 + 短视频脚本，一条流水线</td></tr>
<tr><th scope="row">结构</th><td data-l="字幕转写工具">按时间线性堆砌</td><td data-l="AI 一段摘要">要点列表</td><td class="ol y" data-l="ConFlow">按论证结构重写：论断句标题、一页一论点</td></tr>
<tr><th scope="row">可信度</th><td data-l="字幕转写工具">原话但无结构</td><td data-l="AI 一段摘要">数字可能编</td><td class="ol y" data-l="ConFlow">数字逐字核对 + 每页时间戳回跳核对 + 补全显式标注</td></tr>
<tr><th scope="row">幻灯片</th><td data-l="字幕转写工具">无</td><td data-l="AI 一段摘要">无</td><td class="ol y" data-l="ConFlow">30 主题重构，可放映可导出可分享</td></tr>
<tr><th scope="row">分发</th><td data-l="字幕转写工具">转写稿还要再加工</td><td data-l="AI 一段摘要">停在对话框</td><td class="ol y" data-l="ConFlow">直推 OpenFlow 草稿箱，审完一键发布</td></tr>
</tbody></table><p class="cmp-note">对比口径：「字幕转写工具」指语音转文字类产品；「AI 一段摘要」指通用大模型直接总结，能力以其官方说明为准。ConFlow 的差异不在转写，在写作法与防幻觉规则。</p>
HTML],
        standard_quote_wall($k),
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>和 OpenFlow 是什么关系？</summary><p>独立产品，林下 Understory 工作台层（Studio）成员。文章可自动推进 OpenFlow 内容库草稿箱（带来源视频与幻灯片链接，默认草稿态）——不装 OpenFlow 也能单独用。</p></details>
<details><summary>LLM 会不会一本正经胡说八道？</summary><p>防幻觉写死在方法论里：数字与命令逐字核对、视频没讲清的内容用「💡 补全」显式标注、观点归属严格分离、每页幻灯片带原视频时间戳可回溯核对。</p></details>
<details><summary>怎么跑？</summary><p>三选一：CLI 一条命令（只需一个 OpenAI 兼容 key，适合脚本化批量）；客户端 ZCode 技能（一句话驱动，质量上限最高）；服务端 FastAPI + 管理后台（浏览器提交、任务队列全自动）。</p></details>
<details><summary>能产出哪些语言？</summary><p>中 / English / 日本語 / 한국어 / Español / Français / Deutsch / Português / Русский / العربية 十种——标题、正文、要点、图表标注、页脚全部切换，代码与专名保留原文。</p></details>
HTML],
        understory_matrix_block('conflow', $k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '别再「回头整理一下视频」了', 'subtitle' => 'ConFlow · 林下 Understory 出品', 'content' => '贴一条链接，20 分钟后拿回能发布的文章和能放映的幻灯片。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/V2HTML'],
    ],
]);

/* ═══════════════════ 零真 ZeroZen · 跨浏览器广告与弹窗净化扩展 ═══════════════════ */
upsert_page('zerozen', [
    'slug' => 'zerozen', 'title' => '零真 ZeroZen · 广告与弹窗净化扩展', 'status' => 'published',
    'seo_title' => '零真 ZeroZen — 跨浏览器广告与弹窗净化扩展 | 芭乐派',
    'seo_desc' => '规则引擎 + AI 识别 + 下载工具箱。41 规则包 7283 条规则本地拦截，覆盖不到的交给 AI 识别，顺手把视频断点续传存下来。纯本地运行，无后端不收集浏览数据，Chrome/Firefox/Safari。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '装上即净，顺手把视频也存下来', 'subtitle' => '零真 ZEROZEN · 林下 Understory 出品', 'content' => '规则引擎负责把广告和弹窗清干净，AI 识别补规则覆盖不到的新花样，下载工具箱负责把视频完整存下来。纯本地运行：没有服务器、没有账号，拦截由浏览器 declarativeNetRequest 引擎完成，扩展本身看不到任何请求内容。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/zerozen'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>41 包</b><span>7283 条规则 · 本地拦截</span></div>
<div><b>四层</b><span>协同净化 · 不留空洞</span></div>
<div><b>三浏览器</b><span>Chrome · Firefox · Safari</span></div>
<div><b>0 上传</b><span>无后端 · 不收集浏览数据</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '打开一个网页，你先看到什么', 'subtitle' => 'WHY', 'content' => <<<HTML
<div><h4>弹窗、浮层、信息流</h4><p>打开一个资讯站，先看到的是弹窗、浮层、信息流里的「内容」——正文被挤到第三屏。</p></div>
<div><h4>假下载按钮</h4><p>想下载个视频，要在层层诱导按钮里找出真链接。</p></div>
<div><h4>规则覆盖不到</h4><p>EasyList 系订阅挡不住原生广告的新花样——按条更新永远慢一拍。</p></div>
<div><h4>拦截器自己不透明</h4><p>装了拦截器，你不知道它拦了什么、上报了什么。</p></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '四层协同，页面回到内容', 'subtitle' => 'CLEAN FLOW', 'content' => <<<HTML
<div><h3>网络层拦截</h3><p>declarativeNetRequest 按规则拦截请求——浏览器引擎执行，扩展本身看不到任何请求内容。</p></div>
<div><h3>外观层清理</h3><p>CSS 隐藏 / 移除广告位与浮层；选择器做词边界处理，不误伤正常类名。</p></div>
<div><h3>弹窗守护</h3><p>劫持无手势 window.open；信息流广告清掉后空位自动回填，不留空洞。</p></div>
<div><h3>AI 补位</h3><p>规则没见过的原生广告与假下载按钮，Alt+A 让你自配的模型识别——只发元素的结构化描述，不传正文、不截图、不传 Cookie，默认关闭。</p></div>
<div><h3>点选即规则</h3><p>Alt+Z 选中元素直接生成隐藏 / 移除 / 放行规则；多站点复现自动通用化，学习成果跟着你走。</p></div>
HTML],
        ['_type' => 'features', '_key' => $k(), 'title' => '为什么选零真', 'subtitle' => 'FEATURES', 'content' => <<<HTML
<div><h3>41 规则包 · 7283 条规则</h3><p>按分组独立开关，覆盖中 / 日 / 韩 / 俄 / 欧 / 东南亚 / 印度 / 拉美 / 港澳台站点；冲突时自定义规则优先。</p></div>
<div><h3>规则订阅</h3><p>EasyList / EasyPrivacy / anti-AD / AdGuard / uBlock / CJX 等 10 个预设源自动更新、备用地址自动切换；Adblock / hosts / 纯域名 / JSON 四种格式通吃。</p></div>
<div><h3>AI 自主识别</h3><p>你填的任意 OpenAI 兼容接口（OpenAI / DeepSeek / 智谱 / 通义 / 硅基流动 / 本地 Ollama）；带缓存与限额，结果先进「待审」再生效。</p></div>
<div><h3>下载工具箱</h3><p>视频嗅探（不点播放也能发现）→ m3u8 解密合并（AES-128）→ 分片级断点续传（IndexedDB 持久化）→ 按类型自动归档；后台执行，关掉弹窗不中断。</p></div>
<div><h3>阅读模式</h3><p>一键提取正文，收束动画进入无干扰阅读；一键隐藏导航 / 侧栏 / 评论。</p></div>
<div><h3>内网友好</h3><p>私网 / NAS / 路由器后台 / 在线文档（飞书、腾讯文档、Notion……）默认不启用——管理后台与文档编辑零干扰。</p></div>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '独立引流品的边界', 'subtitle' => '诚实，是产品观', 'content' => <<<HTML
<div><b>不依赖矩阵</b><span>不依赖任何林下产品、不要求注册、不引导转化——给所有人立即可用的浏览器刚需工具。</span></div>
<div><b>功能免费</b><span>觉得好用可以在控制台配置的支持链接付费（诚实付费），没有弹窗催付。</span></div>
<div><b>权限最小化</b><span>bookmarks / history / downloads / webRequest 安装时不申请，第一次用到才询问、随时收回；AI 识别只发元素描述与页面地址标题。</span></div>
HTML],
        ['_type' => 'checklist', '_key' => $k(), 'title' => '零真开箱就带', 'subtitle' => 'READY', 'content' => <<<HTML
<li>41 规则包 · 7283 条规则，按分组独立开关（通用/搜索社交/视频直播/资讯电商/区域站点/垂直站点）</li>
<li>Alt+Z 点选即规则 · Alt+A AI 识别本页</li>
<li>10 个订阅源：自动更新 + 备用地址切换</li>
<li>下载工具箱：嗅探 → 解密合并 → 断点续传 → 自动归档 ZeroZen/</li>
<li>阅读模式：一键提取正文，无干扰阅读</li>
<li>双语界面（中 / English），纯本地运行</li>
HTML],
        ['_type' => 'cmp', '_key' => $k(), 'title' => '传统拦截扩展 vs 纯订阅包 vs 零真', 'subtitle' => '对比', 'content' => <<<HTML
<table class="cmp"><thead><tr><th scope="col">维度</th><th scope="col">传统拦截扩展</th><th scope="col">纯订阅规则包</th><th scope="col" class="ol">零真</th></tr></thead><tbody>
<tr><th scope="row">规则覆盖</th><td data-l="传统拦截扩展">内置规则 + 订阅</td><td data-l="纯订阅规则包">只靠订阅更新</td><td class="ol y" data-l="零真">41 内置包 + 10 订阅源 + 点选即规则 + AI 补位</td></tr>
<tr><th scope="row">新花样广告</th><td data-l="传统拦截扩展">等官方更新</td><td data-l="纯订阅规则包">等维护者更新</td><td class="ol y" data-l="零真">AI 识别补位（自配接口，结果先待审）</td></tr>
<tr><th scope="row">弹窗与信息流</th><td data-l="传统拦截扩展">部分处理</td><td data-l="纯订阅规则包">只管网络层</td><td class="ol y" data-l="零真">四层协同：网络 + 外观 + 弹窗守护 + 空位回填</td></tr>
<tr><th scope="row">下载需求</th><td data-l="传统拦截扩展">要另装下载工具</td><td data-l="纯订阅规则包">无</td><td class="ol y" data-l="零真">内置嗅探 / 解密合并 / 分片断点续传</td></tr>
<tr><th scope="row">隐私透明</th><td data-l="传统拦截扩展">隐私政策要读</td><td data-l="纯订阅规则包">规则来源要审</td><td class="ol y" data-l="零真">declarativeNetRequest 引擎执行，扩展看不到请求内容；权限用到才申请</td></tr>
</tbody></table><p class="cmp-note">对比口径：「传统拦截扩展」指主流广告拦截类扩展；「纯订阅规则包」指只提供规则订阅的方案，能力以其官方说明为准。零真额外把「下载」这一刚需一并收进工具箱。</p>
HTML],
        standard_quote_wall($k),
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>和 OpenFlow 是什么关系？</summary><p>独立引流品，林下 Understory 工作台层（Studio）成员。不依赖矩阵任何产品、不要求注册、不引导转化——代表矩阵「本地优先、诚实边界」的产品观。</p></details>
<details><summary>我的浏览数据安全吗？</summary><p>无后端，不收集不上传任何浏览数据：网络拦截由浏览器 declarativeNetRequest 引擎完成，扩展本身看不到请求内容；AI 识别只发元素的结构化描述与页面地址标题，不传正文、不截图、不传 Cookie，且默认关闭。</p></details>
<details><summary>内网站点会被误拦吗？</summary><p>不会。私网 / NAS / 路由器后台 / 在线文档（飞书、腾讯文档、Notion 等）默认不启用——管理后台与文档编辑零干扰。</p></details>
<details><summary>怎么装？</summary><p>Chrome / Edge / Arc：chrome://extensions 开发者模式加载 dist/chrome；Firefox 128+ 安装 xpi；Safari（macOS 13+ / iOS 16.4+）Xcode 构建后在设置勾选。装完先记住 Alt+Z（点选屏蔽）与 Alt+A（AI 识别本页）。</p></details>
HTML],
        understory_matrix_block('zerozen', $k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '装上即净，浏览还给内容', 'subtitle' => '零真 ZEROZEN · 林下 Understory 出品', 'content' => '功能免费，觉得好用再付费（诚实付费）——规则开源可审计，权限用到才申请。', 'button_text' => '在 GitHub 上开始', 'button_url' => 'https://github.com/sevenaaaaaaaaa/zerozen'],
    ],
]);

echo "完成：林下 Studio 全量 5 页（linkto / litmus / liana / conflow / zerozen）\n";