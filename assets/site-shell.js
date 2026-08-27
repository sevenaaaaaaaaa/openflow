/**
 * OpenFlow · site-shell.js v6 — 全站共享外壳注入器（终版契约）
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
    lf.id = 'of-fonts-css'; lf.rel = 'stylesheet'; lf.href = '/assets/fonts/fonts.css?v=20260826b';
    document.head.appendChild(lf);
  }
  if (!document.getElementById('of-tokens-css')) {
    var l1 = document.createElement('link');
    l1.id = 'of-tokens-css'; l1.rel = 'stylesheet'; l1.href = '/assets/tokens.css?v=20260826b';
    document.head.appendChild(l1);
  }
  if (!document.getElementById('of-modules-css')) {
    var l2 = document.createElement('link');
    l2.id = 'of-modules-css'; l2.rel = 'stylesheet'; l2.href = '/assets/modules.css?v=20260826b';
    document.head.appendChild(l2);
  }
  /* ── mega 菜单 CSS（site-shell 专属，不进共享层） ── */
  if (!document.getElementById('of-mega-css')) {
    var mc = document.createElement('style');
    mc.id = 'of-mega-css';
    mc.textContent = '.tab{position:relative}.mega{position:fixed;top:72px;left:50%;transform:translateX(-50%);width:min(720px,calc(100vw - 32px));background:var(--surface-strong);-webkit-backdrop-filter:blur(40px) saturate(200%);backdrop-filter:blur(40px) saturate(200%);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:20px;opacity:0;pointer-events:none;transition:opacity .2s,transform .25s var(--ease-spring);z-index:80}.tab:hover .mega,.tab.mega-open .mega{opacity:1;pointer-events:auto;transform:translateX(-50%) translateY(0)}.mega-top{display:flex;align-items:baseline;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--border);margin-bottom:14px}.mega-top h4{font-size:15px;font-weight:800}.mega-top p{font-size:12px;color:var(--faint)}.mega-cols{display:grid;grid-template-columns:1fr 1fr;gap:18px}.mega-col-head{font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:8px}.mega-item{display:flex;flex-direction:column;gap:2px;padding:8px 10px;border-radius:10px;text-decoration:none;color:var(--fg);transition:background .14s}.mega-item:hover{background:var(--hover)}.mega-item b{font-size:13px;font-weight:600}.mega-item span{font-size:11.5px;color:var(--faint)}.mega-foot{display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}.mega-foot a{padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;background:var(--hover);color:var(--fg);transition:.15s}.mega-foot a:hover{background:var(--accent);color:var(--on-accent)}@media(max-width:860px){.mega{display:none}}';
    document.head.appendChild(mc);
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
        title: '产品与平台', blurb: '芭乐派增长操作系统 · 帮一人公司设计 Agent 能跑的增长系统',
        cols: [
          { head: '核心产品', items: [
            { t: '内容引擎 CMS', d: '文章 · 页面 · 发布', href: '/category/products/cms' },
            { t: '营销自动化 MA', d: '可视化工作流引擎', href: '/category/products/ma' },
            { t: '客户数据 CDP', d: '画像 · 分群 · 洞察', href: '/category/products/cdp' },
            { t: 'SEO / GEO 引擎', d: '搜索与 AI 优化', href: '/category/products/seo' }
          ]},
          { head: '增长与商业', items: [
            { t: 'CRM 与线索', d: '线索池与转化', href: '/category/products/crm' },
            { t: '商业与订阅', d: '商城 · 会员 · 付费', href: '/category/products/commerce' },
            { t: '社区与内容', d: '论坛 · 评论 · 积分', href: '/category/products/community' },
            { t: '数据分析', d: '归因 · A/B · 洞察', href: '/category/products/data' }
          ]}
        ],
        foot: [{ t: '产品总览', href: '/product' }, { t: '能力矩阵', href: '/category/capabilities/content' }, { t: 'API 文档', href: '/xmp/api-docs' }]
      }
    },
    {
      id: 'capability', label: '能力', href: '/capability', icon: 'bolt',
      mega: {
        title: '六大核心能力', blurb: 'TIPS 框架 · 触达/洞察/个性化/销售四力合一',
        cols: [
          { head: '内容与增长', items: [
            { t: '内容引擎', d: 'CMS · 课程 · 资料 · 播客', href: '/category/capabilities/content' },
            { t: '增长与获客', d: '落地页 · 表单 · SEO · 工具', href: '/category/capabilities/growth' },
            { t: '转化与留存', d: 'MA 自动化 · 会员 · 订阅', href: '/category/capabilities/conversion' },
            { t: '数据与洞察', d: 'CDP · 分析 · 归因 · A/B', href: '/category/capabilities/data' }
          ]},
          { head: '商业与运营', items: [
            { t: '商业闭环', d: '商城 · 生态 · 分销', href: '/category/capabilities/commerce' },
            { t: '社区运营', d: '论坛 · 积分 · 直播 · 咨询', href: '/category/capabilities/community' },
            { t: '内容学院', d: '文章 · 案例 · 方法论', href: '/category/academy/articles' },
            { t: '生态市场', d: 'Skill · 插件 · 主题', href: '/category/marketplace/skills' }
          ]}
        ],
        foot: [{ t: '全部能力', href: '/capability' }, { t: '进入学院', href: '/category/academy/articles' }, { t: '社区讨论', href: '/community' }]
      }
    },
    {
      id: 'courses', label: '课程', href: '/courses', icon: 'book',
      mega: {
        title: '芭乐派 · 学习路径', blurb: 'New-1~4 课程 + R.B.E 训练营 · 以 OpenFlow 为工具',
        cols: [
          { head: '课程类型', items: [
            { t: '大课程', d: '体系化完整课程', href: '/category/courses/big' },
            { t: '系列课', d: '主题系列课程', href: '/category/courses/series' },
            { t: '专栏', d: '深度专题专栏', href: '/category/courses/column' },
            { t: '直播课', d: '实时互动课程', href: '/category/courses/live' }
          ]},
          { head: '相关资源', items: [
            { t: '免费资源', d: '入门免费内容', href: '/category/courses/free' },
            { t: '资料下载', d: '白皮书 · 模板', href: '/downloads' },
            { t: '播客视频', d: '干货音视频', href: '/podcasts' },
            { t: '内容学院', d: '增长实践文章', href: '/category/academy/articles' }
          ]}
        ],
        foot: [{ t: '浏览全部课程', href: '/courses' }]
      }
    },
    {
      id: 'articles', label: '学院', href: '/academy', icon: 'doc',
      mega: {
        title: '内容学院', blurb: '增长系统 · Agent · 一人公司方法论',
        cols: [
          { head: '内容专区', items: [
            { t: '文章', d: '增长实践文章', href: '/category/academy/articles' },
            { t: '资料', d: '白皮书 · 模板 · 报告', href: '/category/academy/downloads' },
            { t: '播客视频', d: '干货音视频', href: '/category/academy/podcasts' },
            { t: '专题合集', d: '主题系列文章', href: '/category/academy/topics' }
          ]},
          { head: '文档与工具', items: [
            { t: '文档中心', d: '产品文档 · 使用指南', href: '/category/academy/docs' },
            { t: '工具箱', d: 'SEO 检查 · Meta · LTV', href: '/category/academy/tools' },
            { t: '社区问答', d: '提问与讨论', href: '/community' }
          ]}
        ],
        foot: [{ t: '进入学院', href: '/academy' }, { t: '浏览工具', href: '/category/academy/tools' }]
      }
    },
    {
      id: 'marketplace', label: '生态', href: '/marketplace', icon: 'box',
      mega: {
        title: '生态市场', blurb: 'Skill · 插件 · 主题 · 一人公司增长门派',
        cols: [
          { head: '生态分类', items: [
            { t: 'Skill 技能', d: '开箱即用的增长能力', href: '/category/marketplace/skills' },
            { t: '插件', d: '扩展系统功能', href: '/category/marketplace/plugins' },
            { t: '主题', d: '视觉与布局', href: '/category/marketplace/themes' },
            { t: '论坛', d: '社区问答与讨论', href: '/category/marketplace/forum' }
          ]},
          { head: '开发者', items: [
            { t: 'API 文档', d: 'REST API 参考', href: '/xmp/api-docs' },
            { t: '开发者文档', d: '架构与扩展点', href: '/md-docs/DEVELOPER.md' },
            { t: 'GitHub', d: '开源仓库', href: 'https://github.com/balepai/openflow' }
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

  /* ── 导航数据源解析：window.OF_NAV（PHP 注入 data/nav.json）优先，内置兜底 ── */
  var NAV = (function () {
    var raw = window.OF_NAV;
    if (raw && !Array.isArray(raw) && Array.isArray(raw.items)) raw = raw.items;
    if (!Array.isArray(raw) || !raw.length) return NAV_FALLBACK;
    var ok = [];
    for (var i = 0; i < raw.length; i++) {
      var n = raw[i];
      if (!n || !n.id || !n.label || typeof n.href !== 'string') continue;
      ok.push(n);
    }
    return ok.length ? ok : NAV_FALLBACK;
  })();

  /* 侧栏空间名用导航中文标签，而不是 data-page 的英文 id */
  var PAGE_LABEL = (function () {
    for (var i = 0; i < NAV.length; i++) if (NAV[i].id === PAGE) return NAV[i].label;
    return PAGE === 'home' ? '首页' : PAGE;
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
      if (n.mega) {
        a.classList.add('tab');
        var mm = document.createElement('div');
        mm.className = 'mega';
        var html = '<div class="mega-top"><h4>' + n.mega.title + '</h4><p>' + (n.mega.blurb || '') + '</p></div><div class="mega-cols">';
        (n.mega.cols || []).forEach(function (col) {
          html += '<div><div class="mega-col-head">' + col.head + '</div>';
          (col.items || []).forEach(function (it) {
            html += '<a class="mega-item" href="' + it.href + '"><b>' + it.t + '</b><span>' + it.d + '</span></a>';
          });
          html += '</div>';
        });
        html += '</div><div class="mega-foot">';
        (n.mega.foot || []).forEach(function (f) { html += '<a href="' + f.href + '">' + f.t + '</a>'; });
        html += '</div>';
        mm.innerHTML = html;
        a.appendChild(mm);
      }
      tabs.appendChild(a);
    });

    /* 侧栏（Arc 三态：full → rail → closed → drawer） */
    var sb = document.createElement('aside');
    sb.id = 'sidebar';
    var sbHtml =
      '<div class="ws" id="ws" role="button" tabindex="0" aria-label="收起侧栏" title="收起侧栏">' +
        '<span class="ic">' + BRAND_SVG + '</span><b>Open Flow · ' + PAGE_LABEL + '</b>' +
        '<span class="chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></span>' +
      '</div>' +
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
      var a = document.createElement('a');
      a.className = 's-item' + (n.id === PAGE ? ' on' : '');
      a.href = n.href;
      a.innerHTML = '<span class="ic">' + (I[n.icon] || '') + '</span><b>' + n.label + '</b>';
      sbNav.appendChild(a);
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
          'body.of-shell-body{margin-left:calc(var(--sb-w,248px) + 34px);' +
          'padding:calc(var(--chrome-h,56px) + 34px) clamp(16px,4vw,40px) 64px;' +
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
    var menuBtn = g('btn-menu'), scrim = g('scrim'), sbToggle = g('sb-toggle'), wsBtn = g('ws');
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
    wsBtn.addEventListener('click', function () { sbSet('closed'); });
    wsBtn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sbSet('closed'); } });
    menuBtn.addEventListener('click', function () { sbOpen(document.body.dataset.sb !== 'drawer'); });
    scrim.addEventListener('click', function () { sbOpen(false); });
    sbNav.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { sbOpen(false); }); });

    /* 滚动胶囊（y>24 收胶囊，y>260 缩档） */
    var chrome = g('chrome');
    function onScroll() {
      var y = window.scrollY || document.documentElement.scrollTop;
      chrome.classList.toggle('scrolled', y > 24);
      chrome.classList.toggle('capsule-mode', y > 260);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

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
    avBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (curUser()) drop.classList.toggle('open');
      else openAuth('login');
    });
    g('dropProfile').addEventListener('click', function () { drop.classList.remove('open'); openProfile(); });
    g('dropLogout').addEventListener('click', function () { drop.classList.remove('open'); setUser(null); toast('已退出登录'); });
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
      if (reg) { fd.append('name', nick); fd.append('email', mail); }
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
            err.textContent = d.error || '操作失败，请稍后再试'; err.classList.add('show');
          }
        })
        .catch(function () { btn.disabled = false; btn.textContent = orig; err.textContent = '网络异常，请稍后再试'; err.classList.add('show'); });
    });

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
    g('pfLogout').addEventListener('click', function () { closePf(); setUser(null); toast('已退出登录'); });
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

  if (document.body) mount();
  else document.addEventListener('DOMContentLoaded', mount);
})();
