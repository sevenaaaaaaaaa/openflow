/**
 * OpenFlow · site-shell.js v8 — 全站共享外壳注入器
 * v8（2026-09-02）单坐标系外壳：
 *   - #chrome 改 sticky 胶囊，几何全由 modules.css 的 --content-l / --gx 决定，与 #main 同源；滚动只切 .scrolled（表面）
 *   - 滚动状态用 IntersectionObserver 哨兵，不再 scroll 事件阈值；删掉 capsule-mode 缩档
 *   - mega 菜单改为 body 级单例 #mega，JS 锚到当前 tab 中心，hover 意图延迟 + 键盘可达；不再嵌在 <a> 里
 *   - 侧栏去 backdrop-filter（它下面没有内容滚过）；顶栏按自身宽度用容器查询收缩
 * 升级记录（2026-08-16）：
 *   - CSS 不再内联，改为引用 /assets/tokens.css + /assets/modules.css（与 index.php 同源契约）
 *   - chrome 注入 brand（O+F logo）+ 浏览器标签页导航（tab-pill 左图标右文字）
 *   - 侧栏升级为 Arc 三态状态机（full → rail → closed → drawer）
 *   - 滚动胶囊：#chrome.scrolled（999px）+ capsule-mode（y>260 缩档）
 *   - 认证换真实 API（/api/member，会话 of_session_v3），弃用 prompt() 演示
 *   - 使用本地优先字体栈，不依赖外部字体请求
 */
(function () {
  if (window.OF_SHELL_LOADED) return;
  window.OF_SHELL_LOADED = true;

  /* ── 性能上报（保留） ── */
  (function () {
    var REPORT = '/api/evolution-report';
    function send(payload) {
      if (navigator.sendBeacon) { var fd = new FormData(); fd.append('json', JSON.stringify(payload)); navigator.sendBeacon(REPORT, fd); return; }
      var x = new XMLHttpRequest(); x.open('POST', REPORT, true); x.setRequestHeader('Content-Type', 'application/json'); x.send(JSON.stringify(payload));
    }
    var lastErr = {};
    window.addEventListener('error', function (e) {
      var msg = (e.message || '').slice(0, 200);
      if (!msg || msg.indexOf('evolution-report') > -1) return;
      var now = Date.now();
      if (lastErr[msg] && now - lastErr[msg] < 10000) return;
      lastErr[msg] = now;
      send({ type: 'error', page: location.pathname, msg: msg });
    });
    window.addEventListener('load', function () {
      try { var lt = Math.round(performance.timing.loadEventEnd - performance.timing.navigationStart); if (lt > 500) send({ type: 'perf', page: location.pathname, load_ms: lt }); } catch (e) {}
    });
  })();

  var tag = document.currentScript;
  var PAGE = (tag && tag.getAttribute('data-page')) || 'home';
  if (!tag || !tag.getAttribute('data-page')) {
    /* fallback：currentScript 为 null（异步加载/延迟执行）时从 DOM 读 */
    var tag2 = document.querySelector('script[data-page]');
    if (tag2) PAGE = tag2.getAttribute('data-page') || PAGE;
  }
  /* data-page → NAV 高亮归一化（无独立顶级项的内容页归入学院） */
  var PAGE_ALIAS = { docs: 'articles', tools: 'articles', podcasts: 'articles', downloads: 'articles', category: 'articles', topics: 'articles', search: 'articles', author: 'articles' };
  if (PAGE_ALIAS[PAGE]) PAGE = PAGE_ALIAS[PAGE];

  /* ── 共享资产：tokens.css + modules.css（终版契约，与 index.php 同源） ── */
  if (!document.getElementById('of-fonts-css')) {
    var lf = document.createElement('link');
    lf.id = 'of-fonts-css'; lf.rel = 'stylesheet'; lf.href = '/assets/fonts/fonts.css?v=20260903a';
    document.head.appendChild(lf);
  }
  if (!document.getElementById('of-tokens-css')) {
    var l1 = document.createElement('link');
    l1.id = 'of-tokens-css'; l1.rel = 'stylesheet'; l1.href = '/assets/tokens.css?v=20260903a';
    document.head.appendChild(l1);
  }
  if (!document.getElementById('of-modules-css')) {
    var l2 = document.createElement('link');
    l2.id = 'of-modules-css'; l2.rel = 'stylesheet'; l2.href = '/assets/modules.css?v=20260903a';
    document.head.appendChild(l2);
  }
  /* ── 图标库 ── */
  var I = {
    home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>',
    box: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/></svg>',
    bolt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg>',
    book: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg>',
    doc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 12v4"/></svg>',
    users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.2 2.7-5 6-5s6 1.8 6 5"/><path d="M16 4.5a3.2 3.2 0 0 1 0 6.5M18 15.5c2 .8 3 2.3 3 4.5"/></svg>',
    search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg>',
    sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
    moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.4 5.4 0 0 1-7.54-7.54C12.92 3.04 12.46 3 12 3Z"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>'
  };
  function ic(n) { return '<span class="ic">' + (I[n] || '') + '</span>'; }

  /* ── 站点导航（含 mega）── 内置兜底副本 ──
     唯一数据源是 data/nav.json（由 includes/site-nav.php 注入为 window.OF_NAV）。
     下面这份只在注入缺失/损坏时兜底，改导航请改 data/nav.json。 */
  var NAV_FALLBACK = [
    { id: 'home', label: '首页', href: '/', icon: 'home' },
    {
      id: 'product', label: '产品', href: '/product', icon: 'box',
      mega: {
        title: '产品', blurb: '芭乐派增长操作系统 · 帮一人公司设计 Agent 能跑的增长系统',
        cols: [
          { head: '它是怎么工作的', items: [
            { t: '可视化编排画布', d: '拖拽触发器、条件、动作，连线即流程', href: '/product#feat-canvas' },
            { t: 'AI 步骤', d: '给流程装上判断力：分类、打分、生成', href: '/product#feat-ai' },
            { t: '开放连接器', d: '飞书 · 企微 · Notion · GitHub · 支付', href: '/product#feat-connectors' },
            { t: '自生长 AI Engine', d: '按周期自动跑：信号 → 洞察 → 草稿 → 触达', href: '/product#feat-engine' }
          ]},
          { head: '看看再说', items: [
            { t: '在线演示', d: '点一下，看增长引擎跑起来', href: '/product#demo' },
            { t: '它值多少', d: '增长引擎产生的实际价值', href: '/product#value' },
            { t: '常见问题', d: '部署 · 数据 · 开源 · 价格', href: '/product#faq' },
            { t: '企业与私有化', d: '托管 / 自建 / 混合', href: '/enterprise' }
          ]}
        ],
        foot: [{ t: '产品总览', href: '/product' }, { t: '四种能力', href: '/capability' }, { t: '文档中心', href: '/docs' }]
      }
    },
    {
      id: 'capability', label: '能力', href: '/capability', icon: 'bolt',
      mega: {
        title: '能力 · TIPS 框架', blurb: '触达 / 洞察 / 个性化 / 销售 四力合一，加上自生长与开源两个底座',
        cols: [
          { head: '四力', items: [
            { t: '触达 Touch', d: '内容引擎 · 分发渠道 · 触达体系', href: '/capability#cap-touch' },
            { t: '洞察 Insight', d: '数据 · CDP · 舆情 · 分析', href: '/capability#cap-insight' },
            { t: '个性化 Personality', d: '画像 · 分群 · 自动化', href: '/capability#cap-personality' },
            { t: '销售 Sales', d: 'CRM · 转化 · 商城 · 订阅', href: '/capability#cap-sales' }
          ]},
          { head: '底座', items: [
            { t: '自生长 AI Engine', d: '按周期自动推一轮增长', href: '/capability#cap-engine' },
            { t: '永久开源', d: '核心能力 MIT 开源，Tools 与 Strategy 双向迭代', href: '/capability#cap-open' },
            { t: '接你现有的工具', d: '不用推翻现在在用的东西', href: '/capability#connectors' },
            { t: '托管还是自建', d: '云端 SaaS / 私有化 / 混合', href: '/capability#deploy' }
          ]}
        ],
        foot: [{ t: '全部能力', href: '/capability' }, { t: '看它跑出什么', href: '/capability#scenes' }, { t: '社区讨论', href: '/community' }]
      }
    },
    {
      id: 'courses', label: '课程', href: '/courses', icon: 'book',
      mega: {
        title: '课程 · 学习路径', blurb: 'New-1~4 基石课 + R.B.E 训练营 · 以 OpenFlow 为工具',
        cols: [
          { head: '课程', items: [
            { t: '全部课程', d: '按顺序学，边学边用', href: '/courses#catalog' },
            { t: '基石课', d: 'New-1~4：利润公式 · 四引擎 · 系统设计', href: '/courses?f=基石#catalog' },
            { t: '训练营', d: 'R.B.E：带着做，交作业，晒数据', href: '/courses?f=训练营#catalog' },
            { t: '直播与活动', d: '公开课 · 线下局', href: '/events' }
          ]},
          { head: '配套', items: [
            { t: '资料下载', d: '白皮书 · 模板 · 清单', href: '/downloads' },
            { t: '播客视频', d: '对谈与拆解', href: '/podcasts' },
            { t: '文章', d: '增长实践与方法论', href: '/articles' },
            { t: '1v1 咨询', d: '带着你的问题来', href: '/consultation' }
          ]}
        ],
        foot: [{ t: '浏览全部课程', href: '/courses' }, { t: '进学院', href: '/academy' }]
      }
    },
    {
      id: 'articles', label: '学院', href: '/academy', icon: 'doc',
      mega: {
        title: '学院', blurb: '增长系统 · Agent · 一人公司方法论，读、下、听、练',
        cols: [
          { head: '内容', items: [
            { t: '文章', d: '增长实践与行业洞察', href: '/articles' },
            { t: '资料', d: '白皮书 · 模板 · 报告', href: '/downloads' },
            { t: '播客视频', d: '干货音视频', href: '/podcasts' },
            { t: '专题合集', d: '按主题串起来的系列', href: '/topics' }
          ]},
          { head: '文档与工具', items: [
            { t: '文档中心', d: '产品文档 · 使用指南 · API', href: '/docs' },
            { t: '工具箱', d: 'SEO 检查 · Meta · LTV 计算', href: '/tools' },
            { t: '增长导航', d: '值得用的增长 / AI 站点', href: '/navigation' },
            { t: '社区问答', d: '提问与讨论', href: '/community' }
          ]}
        ],
        foot: [{ t: '进入学院', href: '/academy' }, { t: '搜索全站', href: '/search' }]
      }
    },
    {
      id: 'marketplace', label: '生态', href: '/marketplace', icon: 'box',
      mega: {
        title: '生态市场', blurb: 'Skill · 插件 · 主题 · 一人公司增长门派',
        cols: [
          { head: '资产', items: [
            { t: 'Skill 技能', d: '开箱即用的增长能力', href: '/marketplace?type=skill' },
            { t: '插件', d: '扩展系统功能', href: '/marketplace?type=plugin' },
            { t: '主题', d: '视觉与布局', href: '/marketplace?type=theme' },
            { t: '全部资产', d: '搜索、按热度浏览', href: '/marketplace' }
          ]},
          { head: '开发者', items: [
            { t: '开放 API', d: 'REST 端点参考', href: '/docs#api' },
            { t: '开发者文档', d: '架构与扩展点', href: '/docs?doc=DEVELOPER' },
            { t: 'GitHub', d: 'MIT 开源仓库', href: 'https://github.com/sevenaaaaaaaaa/openflow' },
            { t: '论坛', d: '提问、交作业、晒增长数据', href: '/community' }
          ]}
        ],
        foot: [{ t: '进入生态市场', href: '/marketplace' }]
      }
    },
    { id: 'community', label: '社区', href: '/community', icon: 'users' },
    { id: 'events', label: '活动', href: '/events', icon: 'bolt' },
    { id: 'navigation', label: '导航', href: '/navigation', icon: 'search' },
    { id: 'about', label: '关于', href: '/about', icon: 'info' }
  ];

  /* 旧的 /category/{section}/{key} 中转页链接 → 真实目的地（data/nav.json 里若还留着旧链接也会被改写；服务端 category.php 同步 301） */
  var LEGACY = {
    '/category/products/cms': '/product#feat-canvas', '/category/products/ma': '/product#feat-canvas', '/category/products/cdp': '/capability#cap-insight', '/category/products/seo': '/capability#cap-touch',
    '/category/products/crm': '/capability#cap-sales', '/category/products/commerce': '/capability#cap-sales', '/category/products/community': '/community', '/category/products/data': '/capability#cap-insight',
    '/category/capabilities/content': '/capability#cap-touch', '/category/capabilities/growth': '/capability#cap-touch', '/category/capabilities/conversion': '/capability#cap-personality', '/category/capabilities/data': '/capability#cap-insight',
    '/category/capabilities/commerce': '/capability#cap-sales', '/category/capabilities/community': '/community',
    '/category/courses/big': '/courses?f=基石#catalog', '/category/courses/series': '/courses#catalog', '/category/courses/column': '/articles', '/category/courses/live': '/events', '/category/courses/free': '/downloads',
    '/category/academy/articles': '/articles', '/category/academy/downloads': '/downloads', '/category/academy/podcasts': '/podcasts', '/category/academy/topics': '/topics', '/category/academy/docs': '/docs', '/category/academy/tools': '/tools',
    '/category/marketplace/skills': '/marketplace?type=skill', '/category/marketplace/plugins': '/marketplace?type=plugin', '/category/marketplace/themes': '/marketplace?type=theme', '/category/marketplace/forum': '/community',
    '/xmp/api-docs': '/docs#api', '/md-docs/DEVELOPER.md': '/docs?doc=DEVELOPER', 'https://github.com/balepai/openflow': 'https://github.com/sevenaaaaaaaaa/openflow'
  };
  function fixHref(h) { if (typeof h !== 'string') return h; var k = h.replace(/\/$/, ''); return LEGACY[k] || h; }
  function fixNav(list) {
    list.forEach(function (n) {
      n.href = fixHref(n.href);
      if (n.mega) {
        (n.mega.cols || []).forEach(function (c) { (c.items || []).forEach(function (it) { it.href = fixHref(it.href); }); });
        (n.mega.foot || []).forEach(function (f) { f.href = fixHref(f.href); });
      }
    });
    return list;
  }

  /* ── 导航数据源解析：window.OF_NAV（PHP 注入 data/nav.json）优先，内置兜底 ── */
  var NAV = (function () {
    var raw = window.OF_NAV;
    if (raw && !Array.isArray(raw) && Array.isArray(raw.items)) raw = raw.items;
    if (!Array.isArray(raw) || !raw.length) return fixNav(NAV_FALLBACK);
    var ok = [];
    for (var i = 0; i < raw.length; i++) {
      var n = raw[i];
      if (!n || !n.id || !n.label || typeof n.href !== 'string') continue;
      ok.push(n);
    }
    return fixNav(ok.length ? ok : NAV_FALLBACK);
  })();

  /* 侧栏空间名用导航中文标签，而不是 data-page 的英文 id */
  var PAGE_LABEL = (function () {
    for (var i = 0; i < NAV.length; i++) if (NAV[i].id === PAGE) return NAV[i].label;
    return PAGE === 'home' ? '首页' : (PAGE === 'account' ? '个人中心' : PAGE);
  })();

  /* ── O+F brand logo（终版） ── */
  var BRAND_SVG = '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><defs><linearGradient id="ofg-b" x1="2" y1="16" x2="30" y2="16" gradientUnits="userSpaceOnUse"><stop stop-color="var(--accent)"/><stop offset="1" stop-color="oklch(58% .16 285)"/></linearGradient></defs><path d="M16 6.5a9.5 9.5 0 1 1-9.5 9.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/><path d="M11.5 10v13M11.5 13.5h8.2M11.5 18.5h8.2" stroke="url(#ofg-b)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M19.7 18.5c2.3 0 4.4-.7 6.1-2M25 14.3l1.6 2.2-2.9 1" stroke="url(#ofg-b)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>';

  function g(id) { return document.getElementById(id); }

  /* ── 挂载 ── */
  function mount() {
    if (g('chrome')) return;

    var header = document.createElement('header');
    header.id = 'chrome';
    header.innerHTML =
      '<div class="bar">' +
        '<div class="bar-start"><div class="lights" aria-hidden="true"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span></div>' +
        '<a class="brand" href="/" aria-label="OpenFlow 首页"><span class="ic">' + BRAND_SVG + '</span><span>OpenFlow<span class="bn-sub">GROWTH OS</span></span></a></div>' +
        '<nav class="tabs" id="tabs" role="navigation" aria-label="站点导航"></nav>' +
        '<div class="controls">' +
          '<button class="cbtn" id="btn-menu" aria-label="打开导航" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>' +
          '<button class="kbd-chip" id="btn-cmd" aria-label="打开命令面板"><span class="ic">' + I.search + '</span><span>搜索与命令</span><span class="kbd">⌘ K</span></button>' +
          '<button class="cbtn" id="btn-theme" aria-label="切换主题"></button>' +
          '<button class="avatar anon" id="btn-av" aria-label="登录 / 注册"></button>' +
        '</div>' +
        '<div class="drop" id="drop">' +
          '<div class="drop-head"><div class="drop-av" id="dropAv">?</div><div style="min-width:0"><div class="drop-name" id="dropName"></div><div class="drop-mail" id="dropMail"></div></div></div>' +
          '<button class="drop-item" id="dropProfile">' + ic('users') + '<b>个人中心</b></button>' +
          '<button class="drop-item danger" id="dropLogout">' + ic('info') + '<b>退出登录</b></button>' +
        '</div>' +
      '</div>';
    document.body.insertBefore(header, document.body.firstChild);

    /* 背景光斑 */
    if (!document.getElementById('of-blobs')) {
      var blobs = document.createElement('div');
      blobs.className = 'of-blobs'; blobs.id = 'of-blobs';
      blobs.innerHTML = '<div class="of-blob of-blob-a"></div><div class="of-blob of-blob-b"></div><div class="of-blob of-blob-c"></div>';
      document.body.insertBefore(blobs, document.body.firstChild);
    }

    /* 遮罩 + 命令面板 + toast + 弹窗 */
    var ui = document.createElement('div');
    ui.innerHTML =
      '<div class="scrim" id="scrim"></div>' +
      '<div class="overlay" id="palOverlay"></div>' +
      '<div class="palette" id="palette" role="dialog" aria-label="命令面板"><input id="palInput" placeholder="搜索页面或命令…" autocomplete="off"><div class="p-list" id="palList"></div></div>' +
      '<div id="toasts" aria-live="polite"></div>' +
      '<div class="modal" id="authModal" role="dialog" aria-modal="true" aria-label="登录"><div class="mbox">' +
        '<div class="mhead"><h3 id="authTitle">登录 OpenFlow</h3><button class="mx" data-close="authModal" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>' +
        '<div class="mbody">' +
          '<div class="auth-tabs"><button type="button" class="auth-tab on" id="tabLogin">登录</button><button type="button" class="auth-tab" id="tabReg">注册</button></div>' +
          '<div id="regFields" style="display:none"><div class="field"><label for="fNick">昵称</label><input class="inp" id="fNick" placeholder="2-20 个字符" autocomplete="nickname"></div></div>' +
          '<div class="field"><label for="fMail">邮箱</label><input class="inp" id="fMail" placeholder="you@example.com" type="email" autocomplete="email"></div>' +
          '<div class="field"><label for="fPwd">密码</label><input class="inp" id="fPwd" placeholder="至少 6 位" type="password" autocomplete="current-password"></div>' +
          '<div class="field" id="capRow" style="display:none"><label for="fCap">邮箱验证码</label><div style="display:flex;gap:8px"><input class="inp" id="fCap" placeholder="6 位验证码" inputmode="numeric" autocomplete="one-time-code" style="flex:1"><button type="button" class="btn ghost" id="capSend" style="flex:0 0 auto;white-space:nowrap">发送验证码</button></div></div>' +
          '<div class="err" id="authErr" role="alert"></div>' +
          '<button type="button" class="btn primary" id="authSubmit" style="width:100%">登录</button>' +
          '<p class="auth-foot">登录即开通 OpenFlow 社区账号，课程与社区内容跨站同步。</p>' +
        '</div>' +
      '</div></div>' +
      '<div class="modal" id="profileModal" role="dialog" aria-modal="true" aria-label="个人中心"><div class="mbox">' +
        '<div class="mhead"><h3>个人中心</h3><button class="mx" data-close="profileModal" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>' +
        '<div class="mbody">' +
          '<div style="display:flex;align-items:center;gap:14px;margin-bottom:4px"><div class="drop-av" id="pfAv">?</div><div style="min-width:0"><div class="drop-name" id="pfName"></div><div class="drop-mail" id="pfMail"></div></div></div>' +
           '<div class="p-stat"><a class="ps" href="/account?view=courses"><div class="pv" id="pfC1">0</div><div class="pl">我的课程</div></a><a class="ps" href="/account?view=orders"><div class="pv" id="pfC2">0</div><div class="pl">我的订单</div></a><a class="ps" href="/consultation?view=my"><div class="pv" id="pfC3">0</div><div class="pl">我的咨询</div></a></div>' +
           '<a class="drop-item" href="/messages" style="border-top:1px solid var(--border-soft);margin-top:2px"><span class="ic">' + I.doc + '</span><span>站内信</span><span id="pfUnread" style="margin-left:auto;background:var(--danger-soft);color:var(--danger);font-size:11px;padding:1px 7px;border-radius:999px;display:none">0</span></a>' +
           '<a class="drop-item" href="/account" style="border-bottom:1px solid var(--border-soft);margin-bottom:2px"><span class="ic">' + I.users + '</span><span>完整个人中心</span><span style="margin-left:auto;color:var(--faint);font-size:11px">→</span></a>' +
           '<a class="drop-item" id="pfOrg" href="/enterprise" style="border-bottom:1px solid var(--border-soft);margin-bottom:2px"><span class="ic">' + I.box + '</span><span>申请商业版</span><span style="margin-left:auto;color:var(--faint);font-size:11px">→</span></a>' +
          '<div class="set-row"><div><div class="st2">深色主题</div><div class="sd">跟随你的偏好</div></div><div class="switch" id="setTheme" role="switch" aria-checked="false" tabindex="0"></div></div>' +
          '<div class="set-row"><div><div class="st2">减少动效</div><div class="sd">关闭动画与过渡</div></div><div class="switch" id="setRM" role="switch" aria-checked="false" tabindex="0"></div></div>' +
          '<button type="button" class="btn ghost" id="pfLogout" style="width:100%;margin-top:14px;color:var(--danger);border-color:var(--danger)">退出登录</button>' +
        '</div>' +
      '</div></div>';
    document.body.appendChild(ui);

    /* 渲染标签页导航（浏览器标签形态：左图标右文字） */
    var tabs = g('tabs');
    NAV.forEach(function (n) {
      var a = document.createElement('a');
      a.className = 'tab-pill' + (n.id === PAGE ? ' on' : '');
      a.href = n.href;
      a.setAttribute('data-nav', n.id);
      a.innerHTML = '<span class="ic">' + (I[n.icon] || '') + '</span><span>' + n.label + '</span>';
      if (n.mega) { a.classList.add('tab'); a.setAttribute('aria-haspopup', 'true'); a.setAttribute('aria-expanded', 'false'); a.setAttribute('aria-controls', 'mega'); }
      tabs.appendChild(a);
    });

    /* ── mega 菜单：body 级单例，锚到当前 tab ── */
    var mega = document.createElement('div');
    mega.id = 'mega'; mega.setAttribute('role', 'group'); mega.setAttribute('aria-label', '子导航');
    mega.innerHTML = '<div class="mg-panel"></div>';
    document.body.appendChild(mega);
    var megaPanel = mega.firstChild, megaTab = null, megaTimer = null;
    var GO = '<span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>';
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function megaHtml(m) {
      var html = '<div class="mg-intro"><span class="kicker">' + esc(m.kicker || 'OpenFlow') + '</span><h4>' + esc(m.title) + '</h4>' + (m.blurb ? '<p>' + esc(m.blurb) + '</p>' : '') + '<div class="mg-foot">';
      (m.foot || []).forEach(function (f) { html += '<a href="' + esc(f.href) + '">' + esc(f.t) + '</a>'; });
      html += '</div></div><div class="mg-cols">';
      (m.cols || []).forEach(function (col) {
        html += '<div class="mg-col"><div class="mg-head">' + esc(col.head) + '</div>';
        (col.items || []).forEach(function (it) { html += '<a class="link-it" href="' + esc(it.href) + '"><span class="lt"><b>' + esc(it.t) + '</b><span>' + esc(it.d) + '</span></span>' + GO + '</a>'; });
        html += '</div>';
      });
      return html + '</div>';
    }
    function megaPlace() {
      if (!megaTab) return;
      var chrome = g('chrome'), cr = chrome.getBoundingClientRect(), tr = megaTab.getBoundingClientRect();
      var w = Math.min(720, cr.width - 24);
      megaPanel.style.width = w + 'px';
      var left = Math.round(tr.left + tr.width / 2 - w / 2);
      left = Math.max(cr.left + 12, Math.min(left, cr.right - 12 - w));
      mega.style.left = left + 'px';
      mega.style.top = Math.round(cr.bottom - 6) + 'px';
    }
    function megaOpen(tab) {
      clearTimeout(megaTimer);
      if (megaTab === tab && mega.classList.contains('open')) return;
      var id = tab.getAttribute('data-nav'), item = null;
      for (var i = 0; i < NAV.length; i++) if (NAV[i].id === id) item = NAV[i];
      if (!item || !item.mega) return;
      if (megaTab) { megaTab.classList.remove('mega-open'); megaTab.setAttribute('aria-expanded', 'false'); }
      megaTab = tab; tab.classList.add('mega-open'); tab.setAttribute('aria-expanded', 'true');
      megaPanel.innerHTML = megaHtml(item.mega);
      megaPlace();
      mega.classList.add('open');
    }
    function megaClose() {
      clearTimeout(megaTimer);
      mega.classList.remove('open');
      if (megaTab) { megaTab.classList.remove('mega-open'); megaTab.setAttribute('aria-expanded', 'false'); }
      megaTab = null;
    }
    function megaCloseSoon() { clearTimeout(megaTimer); megaTimer = setTimeout(megaClose, 180); }
    var HOVER_OK = !matchMedia('(hover: none)').matches;
    tabs.querySelectorAll('.tab').forEach(function (t) {
      if (HOVER_OK) {
        t.addEventListener('mouseenter', function () { clearTimeout(megaTimer); megaTimer = setTimeout(function () { megaOpen(t); }, 80); });
        t.addEventListener('mouseleave', megaCloseSoon);
      }
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || (e.key === 'Enter' && e.altKey)) { e.preventDefault(); megaOpen(t); var f = mega.querySelector('.link-it') || mega.querySelector('a'); if (f) f.focus(); }
        if (e.key === 'Escape') megaClose();
      });
    });
    mega.addEventListener('mouseenter', function () { clearTimeout(megaTimer); });
    mega.addEventListener('mouseleave', megaCloseSoon);
    mega.addEventListener('keydown', function (e) {
      var links = Array.prototype.slice.call(mega.querySelectorAll('a')), i = links.indexOf(document.activeElement);
      if (e.key === 'Escape') { e.preventDefault(); var t = megaTab; megaClose(); if (t) t.focus(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); if (links[i + 1]) links[i + 1].focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (i <= 0) { var t2 = megaTab; megaClose(); if (t2) t2.focus(); } else links[i - 1].focus(); }
    });
    mega.addEventListener('focusout', function (e) { if (!mega.contains(e.relatedTarget) && !(megaTab && megaTab === e.relatedTarget)) megaCloseSoon(); });
    window.addEventListener('scroll', function () { if (mega.classList.contains('open')) megaClose(); }, { passive: true });
    window.addEventListener('resize', function () { if (mega.classList.contains('open')) megaPlace(); });
    document.addEventListener('click', function (e) { if (mega.classList.contains('open') && !mega.contains(e.target) && !(megaTab && megaTab.contains(e.target))) megaClose(); });

    /* 侧栏（Arc 三态：full → rail → closed → drawer） */
    var sb = document.createElement('aside');
    sb.id = 'sidebar';
    var sbHtml =
      '<div class="ws" id="ws"><span class="ic">' + BRAND_SVG + '</span><b>Open Flow · ' + PAGE_LABEL + '</b></div>' +
      '<div id="sbExtra"></div>' +
      '<div class="sec-title"><span>站点</span></div>' +
      '<div id="sbNav"></div>' +
      '<div class="sec-title"><span>账户</span></div>' +
      '<button class="drop-item" id="drawer-auth"><span class="ic">' + I.users + '</span><b id="drawer-auth-label">登录 / 注册</b></button>' +
      '<div class="sb-foot"><button id="sb-toggle" aria-label="折叠侧栏" title="折叠侧栏"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M9 4v16"/></svg></button></div>';
    sb.innerHTML = sbHtml;
    document.body.appendChild(sb);
    var sbNav = g('sbNav');
    NAV.forEach(function (n) {
      var row = document.createElement('div');
      row.className = 's-row';
      var a = document.createElement('a');
      a.className = 's-item' + (n.id === PAGE ? ' on' : '');
      a.href = n.href;
      a.innerHTML = '<span class="ic">' + (I[n.icon] || '') + '</span><b>' + n.label + '</b>';
      row.appendChild(a);
      if (n.mega && n.mega.cols) {
        /* mega 的子项在侧栏 / 抽屉里以手风琴呈现（窄屏没有 hover，mega 面板不可用） */
        var btn = document.createElement('button');
        btn.className = 's-more'; btn.type = 'button'; btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-label', '展开 ' + n.label + ' 子导航');
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        var sub = document.createElement('div');
        sub.className = 's-sub';
        var subHtml = '';
        n.mega.cols.forEach(function (col) { (col.items || []).forEach(function (it) { subHtml += '<a href="' + it.href + '"><b>' + it.t + '</b><span>' + it.d + '</span></a>'; }); });
        sub.innerHTML = subHtml;
        btn.addEventListener('click', function (e) { e.preventDefault(); var open = row.classList.toggle('open'); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        row.appendChild(btn); row.appendChild(sub);
      }
      sbNav.appendChild(row);
    });

    /* 页面锚点插槽：页面可放 <template id="of-sidebar-extra">…</template>，
       其内容会插到侧栏「站点」之前（首页的「本页」锚点区就走这里）。
       template 在 body 里，挂载时还没解析到，所以延到 DOMContentLoaded。 */
    (function mountSidebarExtra() {
      function fill() {
        var tpl = document.getElementById('of-sidebar-extra'), slot = g('sbExtra');
        if (!tpl || !slot) return;
        slot.innerHTML = tpl.innerHTML;
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fill);
      else fill();
    })();

    /* 主内容避让（侧栏 + 胶囊 chrome）
       - <body data-of-main>：页面自带 #main，避让由 modules.css 的 #main 负责，
         这里只同步 --content-x（胶囊 chrome 的左边界）。
       - 无 main（academy 型页面）：由 JS 给 body 补避让，保持既有行为。 */
    /* 主内容避让：全部交给 CSS（modules.css 的 #main / body.of-shell-body），
       不再写内联 style —— 内联 style 优先级高于媒体查询，会让移动端无法收敛。
       页面自带 #main 的（<body data-of-main>）由 #main 负责；其余给 body 加类。 */
    if (!document.body.hasAttribute('data-of-main')) {
      document.body.classList.add('of-shell-body');
      /* 兜底：若 modules.css 是旧版（浏览器/边缘缓存错位，没有 .of-shell-body 规则），
         主内容会失去左避让被侧栏压住。检测到就补一份等价规则，避免整页塌掉。
         用注入 <style> 而不是内联 style，媒体查询才能正常收敛移动端。 */
      var guard = function () {
        if (document.getElementById('of-shell-body-fallback')) return;
        /* 直接查规则是否存在，而不是量 marginLeft —— 后者受样式表加载时序影响，
           会把正常页面误判成旧版。同源样式表可读 cssRules；读不到就跳过。 */
        var found = false, sheets = document.styleSheets;
        for (var i = 0; i < sheets.length && !found; i++) {
          var rules;
          try { rules = sheets[i].cssRules; } catch (e) { continue; }
          if (!rules) continue;
          for (var j = 0; j < rules.length; j++) {
            if (rules[j].selectorText && rules[j].selectorText.indexOf('.of-shell-body') > -1) { found = true; break; }
          }
        }
        if (found) return;
        var st = document.createElement('style');
        st.id = 'of-shell-body-fallback';
        st.textContent =
          'body.of-shell-body{margin-left:var(--content-l,calc(var(--sb-w,248px) + 34px));' +
          'padding:30px var(--gx,clamp(16px,4vw,40px)) 64px;' +
          'transition:margin-left .45s var(--ease-spring)}' +
          '@media(max-width:960px){body.of-shell-body{margin-left:0;padding-left:14px;padding-right:14px}}';
        document.head.appendChild(st);
      };
      var mlink = document.getElementById('of-modules-css');
      if (mlink && !mlink.sheet) mlink.addEventListener('load', guard);
      else guard();
      window.addEventListener('load', guard);
    }

    /* ── 状态：主题 / 侧栏 / 胶囊 ── */
    var LS = 'openflow-site-v3', SK = 'of_session_v3';
    var S;
    try { S = JSON.parse(localStorage.getItem(LS) || '{}'); } catch (e) { S = {}; }
    if (!S.theme) S.theme = 'light';
    var RM = false;
    try { RM = matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
    if (RM) document.documentElement.classList.add('rm');

    function applyTheme() {
      document.documentElement.dataset.theme = S.theme;
      var bt = g('btn-theme');
      if (bt) bt.innerHTML = S.theme === 'dark' ? I.sun : I.moon;
    }
    applyTheme();
    var themeBtn = g('btn-theme');
    themeBtn.addEventListener('click', function () {
      document.documentElement.classList.add('theme-switching');
      S.theme = S.theme === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem(LS, JSON.stringify(S)); } catch (e) {}
      applyTheme();
      setTimeout(function () { document.documentElement.classList.remove('theme-switching'); }, 380);
    });

    /* 侧栏状态机 */
    var menuBtn = g('btn-menu'), scrim = g('scrim'), sbToggle = g('sb-toggle');
    var sbOrder = ['full', 'rail', 'closed'];
    function sbSet(v) { document.body.dataset.sb = v; S.sb = v; try { localStorage.setItem(LS, JSON.stringify(S)); } catch (e) {} }
    function sbOpen(v) { document.body.dataset.sb = v ? 'drawer' : (S.sb || 'full'); menuBtn.setAttribute('aria-expanded', v ? 'true' : 'false'); }
    if (S.sb === 'rail' || S.sb === 'closed') sbSet(S.sb);
    sbToggle.addEventListener('click', function () {
      if (matchMedia('(max-width:860px)').matches) { sbOpen(false); return; }
      var cur = document.body.dataset.sb === 'drawer' ? (S.sb || 'full') : document.body.dataset.sb;
      var i = sbOrder.indexOf(cur); if (i < 0) i = 0;
      sbSet(sbOrder[(i + 1) % 3]);
    });
    menuBtn.addEventListener('click', function () {
      /* 桌面且侧栏已关：直接恢复为常驻侧栏；窄屏 / 其它情况：抽屉 */
      if (!matchMedia('(max-width:960px)').matches && document.body.dataset.sb === 'closed') { sbSet('full'); return; }
      sbOpen(document.body.dataset.sb !== 'drawer');
    });
    scrim.addEventListener('click', function () { sbOpen(false); });
    sbNav.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { sbOpen(false); }); });

    /* 滚动状态：只切表面（.scrolled），不改几何。用 1px 哨兵 + IntersectionObserver，没有阈值抖动 */
    var chrome = g('chrome');
    (function mountScrollState() {
      var sentinel = document.createElement('div');
      sentinel.id = 'of-top-sentinel'; sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'position:absolute;top:8px;left:0;width:1px;height:1px;pointer-events:none';
      document.body.insertBefore(sentinel, document.body.firstChild);
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (en) { chrome.classList.toggle('scrolled', !en[0].isIntersecting); }).observe(sentinel);
      } else {
        var onScroll = function () { chrome.classList.toggle('scrolled', (window.scrollY || 0) > 8); };
        window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
      }
    })();

    /* ── 账户（真实 API /api/member） ── */
    function curUser() { return S.user || null; }
    function setUser(u) {
      S.user = u || null;
      try { if (u) localStorage.setItem(SK, u.email); else localStorage.removeItem(SK); } catch (e) {}
      refreshAuth();
    }
    try { var sess = localStorage.getItem(SK); if (sess && !S.user) { S.user = { email: sess, nick: sess.split('@')[0] }; } } catch (e) {}
    function toast(txt) {
      var t = document.createElement('div');
      t.className = 'toast'; t.textContent = txt;
      t.setAttribute('role', 'status'); t.setAttribute('aria-live', 'polite');
      document.body.appendChild(t);
      setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 2600);
    }
    var avBtn = g('btn-av'), drop = g('drop');
    function refreshAuth() {
      var u = curUser(), lab = g('drawer-auth-label');
      if (u) {
        avBtn.textContent = (u.nick || u.email || '?').charAt(0).toUpperCase();
        avBtn.classList.add('logged');
        avBtn.setAttribute('aria-label', '账户：' + (u.nick || u.email));
        if (lab) lab.textContent = '个人中心';
        g('dropName').textContent = u.nick || u.email;
        g('dropMail').textContent = u.email;
      } else {
        avBtn.textContent = '';
        avBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.6"/><path d="M4.5 20c1.4-3.5 4.2-5.2 7.5-5.2s6.1 1.7 7.5 5.2"/></svg>';
        avBtn.classList.remove('logged');
        avBtn.setAttribute('aria-label', '登录 / 注册');
        if (lab) lab.textContent = '登录 / 注册';
      }
    }
    refreshAuth();
    /* 与服务端会话对齐：以前只看 localStorage —— 在 /account 页登录后头像仍显示未登录，
       服务端会话过期后头像却一直显示已登录，点「我的课程」又被弹回登录页。 */
    fetch('/api/member?action=profile_summary', { method: 'POST', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (d) { return { http: r.status, d: d }; }).catch(function () { return { http: r.status, d: {} }; }); })
      .then(function (res) {
        var d = res.d || {};
        if (res.http === 200 && d.ok) { var cu = curUser(); if (!cu || cu.email !== d.email || cu.nick !== d.name) setUser({ email: d.email || (cu && cu.email) || '', nick: d.name || '' }); }
        else if (res.http === 401 && curUser()) setUser(null);
      }).catch(function () {});
    avBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (curUser()) drop.classList.toggle('open');
      else openAuth('login');
    });
    g('dropProfile').addEventListener('click', function () { drop.classList.remove('open'); openProfile(); });
    function logout() {
      /* 以前只清本地状态，服务端会话仍然有效：头像变回未登录，但 /account 还能直接进 */
      var fd = new FormData(); fd.append('action', 'logout');
      return fetch('/api/member', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {}).then(function () { setUser(null); toast('已退出登录'); });
    }
    g('dropLogout').addEventListener('click', function () { drop.classList.remove('open'); logout(); });
    g('drawer-auth').addEventListener('click', function () { if (curUser()) openProfile(); else openAuth('login'); });
    document.addEventListener('click', function (e) {
      if (drop.classList.contains('open') && !drop.contains(e.target) && !avBtn.contains(e.target)) drop.classList.remove('open');
    });

    /* 焦点陷阱 + 滚动锁 */
    var lastFocus = null;
    function lockScroll(on) { document.body.style.overflow = on ? 'hidden' : ''; }
    function trapFocus(cont, e) {
      var f = cont.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      var first = f[0], lastF = f[f.length - 1];
      if (e.key === 'Tab') {
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastF.focus(); }
        else if (!e.shiftKey && document.activeElement === lastF) { e.preventDefault(); first.focus(); }
      }
    }

    /* 登录 / 注册 */
    var authModal = g('authModal');
    function openAuth(mode) {
      lastFocus = document.activeElement; lockScroll(true);
      authModal.classList.add('open'); setAuthMode(mode || 'login');
      setTimeout(function () { var f = authModal.querySelector('#fMail'); if (f) f.focus(); }, 30);
    }
    function closeAuth() {
      authModal.classList.remove('open'); g('authErr').classList.remove('show');
      lockScroll(false); if (lastFocus) lastFocus.focus();
    }
    function setAuthMode(m) {
      var login = m === 'login';
      g('tabLogin').classList.toggle('on', login); g('tabReg').classList.toggle('on', !login);
      g('regFields').style.display = login ? 'none' : 'block';
      if (login) g('capRow').style.display = 'none';
      g('authTitle').textContent = login ? '登录 OpenFlow' : '注册 OpenFlow';
      authModal.setAttribute('aria-label', login ? '登录' : '注册');
      g('authSubmit').textContent = login ? '登录' : '注册并进入个人中心';
    }
    g('tabLogin').addEventListener('click', function () { setAuthMode('login'); });
    g('tabReg').addEventListener('click', function () { setAuthMode('register'); });
    authModal.addEventListener('keydown', function (e) { if (authModal.classList.contains('open')) trapFocus(authModal, e); });
    g('authSubmit').addEventListener('click', function () {
      var mail = g('fMail').value.trim(), pwd = g('fPwd').value, nick = g('fNick').value.trim(), err = g('authErr');
      var reg = !g('tabLogin').classList.contains('on');
      err.classList.remove('show');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) { err.textContent = '请输入有效的邮箱地址'; err.classList.add('show'); return; }
      if (pwd.length < 6) { err.textContent = '密码至少 6 位'; err.classList.add('show'); return; }
      if (reg && (nick.length < 2 || nick.length > 20)) { err.textContent = '昵称需 2-20 个字符'; err.classList.add('show'); return; }
      var btn = g('authSubmit'), orig = btn.textContent;
      btn.disabled = true; btn.textContent = '处理中…';
      var fd = new FormData();
      fd.append('account', mail); fd.append('password', pwd);
      if (reg) { fd.append('name', nick); fd.append('email', mail); fd.append('captcha', g('fCap').value.trim()); }
      fetch('/api/member?action=' + (reg ? 'register' : 'login'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (d) { return { http: r.status, d: d }; }).catch(function () { return { http: 0, d: {} }; }); })
        .then(function (res) {
          btn.disabled = false; btn.textContent = orig;
          var d = res.d || {};
          if (res.http === 200 && d.ok) {
            setUser({ email: mail, nick: reg ? nick : mail.split('@')[0] });
            closeAuth(); toast(reg ? '注册成功，欢迎加入芭乐派' : '已登录，欢迎回来');
            openProfile();
          } else {
            /* 注册需要邮箱验证码：显示验证码栏（第一次提交时自动把码发出去） */
            if (d.need_captcha && g('capRow').style.display === 'none') { g('capRow').style.display = 'block'; sendCaptcha(mail); setTimeout(function () { g('fCap').focus(); }, 30); }
            err.textContent = d.error || '操作失败，请稍后再试'; err.classList.add('show');
          }
        })
        .catch(function () { btn.disabled = false; btn.textContent = orig; err.textContent = '网络异常，请稍后再试'; err.classList.add('show'); });
    });

    function sendCaptcha(mail) {
      var sb = g('capSend'), err = g('authErr');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) { err.textContent = '请先填写有效的邮箱地址'; err.classList.add('show'); return; }
      sb.disabled = true; sb.textContent = '发送中…';
      var fd = new FormData(); fd.append('target', mail);
      fetch('/api/member?action=send_captcha', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (d) {
          if (d.ok) { toast(d.message || '验证码已发送'); var n = 60; sb.textContent = n + 's'; var t = setInterval(function () { n--; if (n <= 0) { clearInterval(t); sb.disabled = false; sb.textContent = '重新发送'; } else sb.textContent = n + 's'; }, 1000); }
          else { sb.disabled = false; sb.textContent = '发送验证码'; err.textContent = d.error || '发送失败'; err.classList.add('show'); }
        })
        .catch(function () { sb.disabled = false; sb.textContent = '发送验证码'; });
    }
    g('capSend').addEventListener('click', function () { sendCaptcha(g('fMail').value.trim()); });

    /* 个人中心 */
    var pfModal = g('profileModal');
    function openProfile() {
      var u = curUser(); if (!u) { openAuth('login'); return; }
      g('pfAv').textContent = (u.nick || u.email).charAt(0).toUpperCase();
      g('pfName').textContent = u.nick || u.email;
      g('pfMail').textContent = u.email;
      g('pfC1').textContent = '…'; g('pfC2').textContent = '…'; g('pfC3').textContent = '…';
      var un = g('pfUnread');
      fetch('/api/member?action=profile_summary', { method: 'POST', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (d) {
          if (d && d.ok && d.stats) {
            g('pfC1').textContent = d.stats.courses || 0;
            g('pfC2').textContent = d.stats.orders || 0;
            g('pfC3').textContent = d.stats.consultations || 0;
            if (un && (d.stats.unread || 0) > 0) { un.style.display = 'inline'; un.textContent = d.stats.unread; }
            var orgLink = g('pfOrg');
            if (orgLink && d.org) {
              orgLink.href = '/account?view=org';
              orgLink.innerHTML = '<span class="ic">' + I.box + '</span><span>' + (d.org.name || '企业') + ' · 企业控制台</span><span style="margin-left:auto;color:var(--faint);font-size:11px">→</span>';
            }
          } else {
            g('pfC1').textContent = 0; g('pfC2').textContent = 0; g('pfC3').textContent = 0;
          }
        })
        .catch(function () { g('pfC1').textContent = 0; g('pfC2').textContent = 0; g('pfC3').textContent = 0; });
      g('setTheme').setAttribute('aria-checked', document.documentElement.dataset.theme === 'dark' ? 'true' : 'false');
      g('setRM').setAttribute('aria-checked', document.documentElement.classList.contains('rm') ? 'true' : 'false');
      lastFocus = document.activeElement; lockScroll(true);
      pfModal.classList.add('open');
      setTimeout(function () { var f = pfModal.querySelector('.mbox button,.mbox .switch'); if (f) f.focus(); }, 30);
    }
    function closePf() { pfModal.classList.remove('open'); lockScroll(false); if (lastFocus) lastFocus.focus(); }
    g('setTheme').addEventListener('click', function () {
      themeBtn.click();
      this.setAttribute('aria-checked', document.documentElement.dataset.theme === 'dark' ? 'true' : 'false');
    });
    g('setRM').addEventListener('click', function () {
      var on = this.getAttribute('aria-checked') === 'true';
      this.setAttribute('aria-checked', on ? 'false' : 'true');
      document.documentElement.classList.toggle('rm', !on);
      window.__timers = window.__timers || [];
      if (!on) window.__timers.forEach(function (t) { clearInterval(t); });
    });
    g('pfLogout').addEventListener('click', function () { closePf(); logout(); });
    pfModal.addEventListener('keydown', function (e) { if (pfModal.classList.contains('open')) trapFocus(pfModal, e); });
    document.querySelectorAll('[data-close]').forEach(function (b) {
      b.addEventListener('click', function () {
        var t = b.getAttribute('data-close');
        if (t === 'authModal') closeAuth();
        else if (t === 'profileModal') closePf();
      });
    });

    /* ── 命令面板 ── */
    var pal = g('palette'), palOv = g('palOverlay'), palInput = g('palInput'), palList = g('palList');
    function openPalette() {
      palList.innerHTML = '';
      NAV.forEach(function (n) {
        var a = document.createElement('a');
        a.className = 'p-item'; a.href = n.href;
        a.innerHTML = ic(n.icon) + '<span>' + n.label + '</span><span class="p-hint">打开页面</span>';
        palList.appendChild(a);
      });
      pal.classList.add('open'); palOv.classList.add('open');
      palInput.value = ''; palInput.focus();
    }
    function closePalette() { pal.classList.remove('open'); palOv.classList.remove('open'); }
    g('btn-cmd').addEventListener('click', openPalette);
    palOv.addEventListener('click', closePalette);
    palInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePalette();
      if (e.key === 'Enter') { var a = palList.querySelector('a'); if (a) location.href = a.href; }
    });
    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        pal.classList.contains('open') ? closePalette() : openPalette();
      }
      if (e.key === 'Escape') { closePalette(); drop.classList.remove('open'); }
    });

    /* ── 对外最小 API：页面级脚本复用外壳能力，不再自绘 ── */
    window.OFShell = {
      NAV: NAV,
      page: PAGE,
      toast: toast,
      curUser: curUser,
      openAuth: openAuth,
      openProfile: function () { if (curUser()) openProfile(); else openAuth('login'); },
      openPalette: openPalette,
      toggleTheme: function () { var b = g('btn-theme'); if (b) b.click(); }
    };

    /* Footer 唯一语言入口：所有共享外壳页面统一为一个下拉按钮。 */
    (function mountFooterLanguage() {
      if (document.querySelector('.of-lang')) return;
      var footer = document.querySelector('footer');
      if (!footer) return;
      var locales = [{ id: 'zh-CN', label: '中文' }, { id: 'zh-TW', label: '繁體中文' }, { id: 'en', label: 'EN' }, { id: 'ja', label: '日本語' }, { id: 'ko', label: '한국어' }, { id: 'ru', label: 'Русский' }, { id: 'es', label: 'Español' }, { id: 'pt', label: 'Português' }, { id: 'ar', label: 'العربية' }, { id: 'fr', label: 'Français' }, { id: 'de', label: 'Deutsch' }];
      var localePattern = /^\/(zh-CN|zh-TW|en|ja|ko|ru|es|pt|ar|fr|de)(?:\/|$)/;
      var match = location.pathname.match(localePattern);
      var current = match ? match[1] : (document.documentElement.lang || 'zh-CN');
      if (!locales.some(function (l) { return l.id === current; })) current = 'zh-CN';
      function urlFor(locale) {
        var clean = location.pathname.replace(localePattern, '/') || '/';
        return (locale === 'zh-CN' ? clean : '/' + locale + (clean === '/' ? '/' : clean)) + location.search + location.hash;
      }
      var details = document.createElement('details');
      details.className = 'of-lang';
      details.style.cssText = 'position:relative;display:inline-block;margin-top:12px;font-size:12px';
      var active = locales.filter(function (l) { return l.id === current; })[0];
      details.innerHTML = '<summary aria-label="切换语言" style="list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border:1px solid var(--border);border-radius:999px;background:var(--surface);color:var(--muted)">🌐 <span>' + active.label + '</span>⌄</summary><span class="of-lang-menu" style="position:absolute;right:0;bottom:calc(100% + 8px);display:grid;min-width:112px;padding:6px;border:1px solid var(--border);border-radius:12px;background:var(--surface-strong);box-shadow:var(--shadow-sm);z-index:30"></span>';
      var menu = details.querySelector('.of-lang-menu');
      locales.forEach(function (locale) {
        var a = document.createElement('a'); a.href = urlFor(locale.id); a.lang = locale.id; a.textContent = locale.label;
        a.style.cssText = 'padding:7px 9px;border-radius:8px;text-decoration:none;color:' + (locale.id === current ? 'var(--on-accent)' : 'var(--muted)') + ';background:' + (locale.id === current ? 'var(--accent)' : 'transparent');
        menu.appendChild(a);
      });
      var bottom = footer.querySelector('.f-bottom') || footer.querySelector('.text-center') || footer;
      bottom.appendChild(details);
    })();
  }

  /* ── 滚动显现（.reveal → .in）+ 回到顶部：原本只在 index.php 内联，收进外壳让所有页可用 ── */
  function mountMotion() {
    var all = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
    if ('IntersectionObserver' in window) {
      var rvIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); rvIO.unobserve(en.target); } });
      }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
      all('.reveal').forEach(function (el) { rvIO.observe(el); });
      setTimeout(function () { all('.reveal:not(.in)').forEach(function (el) { el.classList.add('in'); }); }, 2500);
    } else {
      all('.reveal').forEach(function (el) { el.classList.add('in'); });
    }
    /* 通用 tab（opt-in：tablist 带 data-tabs）。首页自己的 tab 脚本不受影响。 */
    all('[role="tablist"][data-tabs]').forEach(function (bar) {
      var tabs = Array.prototype.slice.call(bar.querySelectorAll('[role="tab"]'));
      if (!tabs.length) return;
      function panelOf(t) { return document.getElementById(t.getAttribute('aria-controls')); }
      function sel(t) {
        tabs.forEach(function (x) { var on = x === t; x.setAttribute('aria-selected', on ? 'true' : 'false'); x.tabIndex = on ? 0 : -1; var p = panelOf(x); if (p) p.classList.toggle('on', on); });
      }
      // 深链：#<tab id> 或 #<panel id> 或 tab 的 data-hash 命中时直接选中该 tab 并滚到 tablist
      function byHash() {
        var h = (location.hash || '').slice(1); if (!h) return;
        var hit = tabs.filter(function (t) { return t.id === h || t.getAttribute('aria-controls') === h || t.dataset.hash === h; })[0];
        if (hit) { sel(hit); var go = function () { var y = bar.getBoundingClientRect().top + window.scrollY - 88; window.scrollTo({ top: Math.max(0, y), behavior: 'auto' }); }; go(); setTimeout(go, 200); setTimeout(go, 700); }
      }
      byHash(); window.addEventListener('hashchange', byHash);
      tabs.forEach(function (t, i) {
        t.tabIndex = t.getAttribute('aria-selected') === 'true' ? 0 : -1;
        t.addEventListener('click', function () { sel(t); });
        t.addEventListener('keydown', function (e) {
          var n; if (e.key === 'ArrowRight') n = (i + 1) % tabs.length; else if (e.key === 'ArrowLeft') n = (i - 1 + tabs.length) % tabs.length; else return;
          e.preventDefault(); sel(tabs[n]); tabs[n].focus();
        });
      });
    });
    var bt = document.getElementById('backtop');
    if (bt) {
      var RM = false; try { RM = matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
      var upd = function () { bt.classList.toggle('show', window.scrollY > 480); };
      window.addEventListener('scroll', upd, { passive: true }); upd();
      bt.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: RM ? 'auto' : 'smooth' }); });
    }
  }

  if (document.body) mount();
  else document.addEventListener('DOMContentLoaded', mount);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountMotion);
  else mountMotion();
})();
