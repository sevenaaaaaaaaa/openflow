<?php
/**
 * 林下 Understory · 工作台层（Studio 套件）产品页
 * 第一批（2026-10-09）：林可兔 LinkTo / 鹿蕊 Litmus
 * 后续批次：松萝 Liana / ConFlow / 零真 ZeroZen（追加到本脚本重跑即可，upsert 幂等）
 *
 * 素材源：各产品 GitHub 仓库 README（2026-10-09 版），全部表述可核验——GTM §2「可以说/不能说」约束。
 * 新口径：产品矩阵块改用「林下三层」（入口层 / 进阶层 / 工作台层），任务 A 铺开时其余产品页统一换成此块。
 * 用法: php scripts/seed-studio-pages.php
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

echo "完成：林下 Studio 第一批（linkto / litmus）\n";