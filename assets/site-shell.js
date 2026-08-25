/**
 * OpenFlow · site-shell.js v6 — 全站共享外壳注入器（终版契约）
 * 升级记录（2026-08-16）：
 *   - CSS 不再内联，改为引用 /assets/tokens.css + /assets/modules.css（与 index.php 同源契约）
 *   - chrome 注入 brand（O+F logo）+ 浏览器标签页导航（tab-pill 左图标右文字）
 *   - 侧栏升级为 Arc 三态状态机（full → rail → closed → drawer）
 *   - 滚动胶囊：#chrome.scrolled（999px）+ capsule-mode（y>260 缩档）
 *   - 认证换真实 API（/api/member.php，会话 of_session_v3），弃用 prompt() 演示
 *   - 使用本地优先字体栈，不依赖外部字体请求
 */
(function () {
  if (window.OF_SHELL_LOADED) return;
  window.OF_SHELL_LOADED = true;

  /* ── 性能上报（保留） ── */
  (function () {
    var REPORT = '/api/evolution-report.php';
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
  var PAGE_ALIAS = { docs: 'articles', tools: 'articles', podcasts: 'articles', downloads: 'articles', community: 'articles', category: 'articles', topics: 'articles', search: 'articles', author: 'articles' };
  if (PAGE_ALIAS[PAGE]) PAGE = PAGE_ALIAS[PAGE];

  /* ── 共享资产：tokens.css + modules.css（终版契约，与 index.php 同源） ── */
  if (!document.getElementById('of-tokens-css')) {
    var l1 = document.createElement('link');
    l1.id = 'of-tokens-css'; l1.rel = 'stylesheet'; l1.href = '/assets/tokens.css?v=20260816';
    document.head.appendChild(l1);
  }
  if (!document.getElementById('of-modules-css')) {
    var l2 = document.createElement('link');
    l2.id = 'of-modules-css'; l2.rel = 'stylesheet'; l2.href = '/assets/modules.css?v=20260816';
    document.head.appendChild(l2);
  }
  /* ── mega 菜单 CSS（site-shell 专属，不进共享层） ── */
  if (!document.getElementById('of-mega-css')) {
    var mc = document.createElement('style');
    mc.id = 'of-mega-css';
    mc.textContent = '.tab{flex:1 1 0;min-width:104px;max-width:168px;height:44px;display:flex;align-items:center;gap:8px;padding:0 12px;border-radius:14px;font-size:13px;font-weight:600;color:var(--muted);white-space:nowrap;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .22s,color .22s,border-color .22s;position:relative}.tab:hover{background:var(--hover);color:var(--fg)}.tab.active{background:var(--surface-strong);color:var(--fg);border-color:var(--border);box-shadow:var(--shadow-sm)}.tab .ic{color:var(--faint)}.tab.active .ic{color:var(--accent)}.mega{position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);width:min(720px,calc(100vw - 32px));background:var(--surface-strong);-webkit-backdrop-filter:blur(40px) saturate(200%);backdrop-filter:blur(40px) saturate(200%);border:1px solid var(--border);border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.18);padding:28px;opacity:0;pointer-events:none;transition:opacity .2s,transform .25s var(--ease-spring);z-index:80}.tab:hover .mega,.tab.mega-open .mega{opacity:1;pointer-events:auto;transform:translateX(-50%) translateY(0)}.mega-top{display:flex;flex-direction:column;gap:6px;padding-bottom:16px;border-bottom:1px solid var(--border);margin-bottom:14px}.mega-top h4{font-size:18px;font-weight:800;letter-spacing:-.01em}.mega-top p{font-size:13px;color:var(--muted);line-height:1.5}.mega-cols{display:grid;grid-template-columns:1fr 1fr;gap:24px}.mega-col-head{font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.14em;color:var(--accent);padding-bottom:6px;border-bottom:1px solid var(--border-soft);margin-bottom:10px;text-transform:uppercase;margin-bottom:8px}.mega-item{display:flex;flex-direction:column;gap:3px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--fg);transition:background .14s}.mega-item:hover{background:var(--hover)}.mega-item b{font-size:14px;font-weight:700;letter-spacing:-.01em}.mega-item span{font-size:12.5px;color:var(--muted);line-height:1.4}.mega-foot{display:flex;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid var(--border)}.mega-foot a{padding:7px 16px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;background:var(--hover);color:var(--fg);transition:.15s}.mega-foot a:hover{background:var(--accent);color:var(--on-accent)}@media(max-width:860px){.mega{display:none}}#chrome{position:fixed;top:0;left:0;right:0;z-index:60;height:var(--chrome-h,56px);padding:0 14px;transition:top .45s var(--ease-spring),left .45s var(--ease-spring),right .45s var(--ease-spring),border-radius .45s var(--ease-spring),box-shadow .3s,background .3s;border-bottom:1px solid transparent}#chrome.scrolled{top:10px;left:calc(var(--content-x, 262px) + 12px);right:14px;height:56px;border-radius:999px;border:1px solid var(--glass-border);background:var(--glass-bright);box-shadow:var(--shadow-sm);padding:0 14px}#chrome.scrolled .bar{background:transparent;border:none;box-shadow:none}#chrome.capsule-mode{height:48px}#chrome.capsule-mode .bar{height:48px}#chrome.capsule-mode .tabs a{height:30px;padding:0 10px;font-size:12.5px}#chrome.capsule-mode .tabs a .ic{width:13px;height:13px}#chrome.capsule-mode .cbtn{width:30px;height:30px}#chrome.capsule-mode .cbtn svg{width:15px;height:15px}#chrome.capsule-mode .kbd-chip{height:30px;font-size:12px}#chrome.capsule-mode .brand{font-size:12.5px}';
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
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4M16 2v4M3 8h18"/><rect x="3" y="5" width="18" height="17" rx="2"/><path d="M8 13h3M14 13h2M8 17h3"/></svg>',
    compass: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-2.2 5-4.8 1.2L10.4 10 15 9Z"/></svg>'
  };
  function ic(n) { return '<span class="ic">' + (I[n] || '') + '</span>'; }

  /* ── 前端多语言：URL 前缀 / cookie → 语言包 ── */
  var __dict = {}, __locale = 'zh-CN';
  (function () {
    var m = location.pathname.match(/^\/(zh-CN|zh-TW|en|ja|ko|ru|es|pt|ar|fr|de)(\/|$)/i);
    if (m) __locale = m[1];
    else { var c = (document.cookie.match(/(?:^|;\s*)of_lang=([^;]+)/) || [])[1]; if (c) __locale = c; }
  })();
  function t(n) { return (n.key && __dict[n.key]) ? __dict[n.key] : n.label; }
  function loadLang(cb) {
    fetch('/api/lang.php?locale=' + __locale)
      .then(function (r) { return r.json(); })
      .then(function (d) { __dict = d || {}; document.documentElement.lang = __locale; document.documentElement.dir = (__locale === 'ar') ? 'rtl' : 'ltr'; if (cb) cb(); })
      .catch(function () {});
  }

  /* ── 站点导航（含 mega） ── */
  var NAV = [
    { id: 'home', label: '首页', key: 'nav.home', href: '/', icon: 'home' },
    {
      id: 'product', label: '产品', key: 'nav.product', href: '/product', icon: 'box',
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
        foot: [{ t: '产品总览', href: '/product' }, { t: '能力矩阵', href: '/category/capabilities/content' }, { t: 'API 文档', href: '/api-docs.php' }]
      }
    },
    {
      id: 'capability', label: '能力', key: 'nav.capability', href: '/capability', icon: 'bolt',
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
      id: 'courses', label: '课程', key: 'nav.courses', href: '/courses', icon: 'book',
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
      id: 'articles', label: '学院', key: 'nav.academy', href: '/academy', icon: 'doc',
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
    { id: 'about', label: '关于', key: 'nav.about', href: '/about', icon: 'info' }
  ];

  /* ── O+F brand logo（终版） ── */
  var BRAND_SVG = '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><defs><linearGradient id="ofg-b" x1="2" y1="16" x2="30" y2="16" gradientUnits="userSpaceOnUse"><stop stop-color="var(--accent)"/><stop offset="1" stop-color="oklch(58% .16 285)"/></linearGradient></defs><path d="M16 6.5a9.5 9.5 0 1 1-9.5 9.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/><path d="M11.5 10v13M11.5 13.5h8.2M11.5 18.5h8.2" stroke="url(#ofg-b)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M19.7 18.5c2.3 0 4.4-.7 6.1-2M25 14.3l1.6 2.2-2.9 1" stroke="url(#ofg-b)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>';

  function g(id) { return document.getElementById(id); }

  /* ── 挂载 ── */
  function mount() {
    /* 创建 DOM 元素（仅当 SSR chrome 不存在时） */
    if (!g('chrome')) {
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

      if (!document.getElementById('of-blobs')) {
        var blobs = document.createElement('div');
        blobs.className = 'of-blobs'; blobs.id = 'of-blobs';
        blobs.innerHTML = '<div class="of-blob of-blob-a"></div><div class="of-blob of-blob-b"></div><div class="of-blob of-blob-c"></div>';
        document.body.insertBefore(blobs, document.body.firstChild);
      }

      var ui = document.createElement('div');
      ui.innerHTML =
        '<div class="scrim" id="scrim"></div>' +
        '<div class="overlay" id="palOverlay"></div>' +
        '<div class="palette" id="palette" role="dialog" aria-label="命令面板"><input id="palInput" placeholder="搜索页面或命令…" autocomplete="off"><div class="p-list" id="palList"></div></div>' +
        '<div class="modal" id="authModal"><div class="mbox"><div class="mhead"><h3 id="authTitle">登录</h3><button class="mx" id="authClose">×</button></div>' +
          '<div class="auth-tabs" id="authTabs"><button class="auth-tab active" data-mode="login">登录</button><button class="auth-tab" data-mode="register">注册</button></div>' +
          '<div class="mbody" id="authBody"></div>' +
          '<div id="authMsg" class="err"></div>' +
          '<div class="auth-foot">登录即同意 <a href="/terms">服务条款</a> 和 <a href="/privacy">隐私政策</a></div>' +
        '</div></div>' +
        '<div class="toast" id="toast"></div>';
      document.body.appendChild(ui);

      /* 渲染标签页导航 */
      function renderTabs() {
        var tabs = g('tabs');
        if (!tabs) return;
        tabs.innerHTML = '';
        NAV.forEach(function (n) {
          var a = document.createElement('a');
          a.className = 'tab' + (n.id === PAGE ? ' active' : '');
          a.href = n.href;
          a.setAttribute('data-nav', n.id);
          a.innerHTML = '<span class="ic">' + (I[n.icon] || '') + '</span><span>' + t(n) + '</span>';
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
      }
      renderTabs();
      loadLang(function () { renderTabs(); });

      /* 侧栏 */
      var sb = document.createElement('aside');
      sb.id = 'sidebar';
      sb.innerHTML = '<div class="ws" id="ws" role="button" tabindex="0" aria-label="收起侧栏" title="收起侧栏"><span class="ic">' + BRAND_SVG + '</span><b>Open Flow · ' + (PAGE === 'home' ? '首页' : PAGE) + '</b><span class="chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></span></div><div class="sec-title"><span>站点</span></div><div id="sbNav"></div><div class="sec-title"><span>账户</span></div><button class="drop-item" id="drawer-auth"><span class="ic">' + I.users + '</span><b id="drawer-auth-label">登录 / 注册</b></button><div class="sb-foot"><button id="sb-toggle" aria-label="折叠侧栏" title="折叠侧栏"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M9 4v16"/></svg></button></div>';
      document.body.appendChild(sb);
      var sbNav = g('sbNav');
      NAV.forEach(function (n) {
        var a = document.createElement('a');
        a.className = 's-item' + (n.id === PAGE ? ' on' : '');
        a.href = n.href;
        a.innerHTML = '<span class="ic">' + (I[n.icon] || '') + '</span><b>' + t(n) + '</b>';
        sbNav.appendChild(a);
      });

      /* 主内容避让 */
      var mains = document.querySelectorAll('main');
      (mains.length ? mains : [document.body]).forEach(function (m) {
        var cur = parseInt(getComputedStyle(m).marginLeft, 10) || 0;
        if (cur < 262) m.style.marginLeft = '282px';
        if (m === document.body) m.style.paddingTop = '96px';
        var ml = parseInt(getComputedStyle(m).marginLeft, 10) || 0;
        document.documentElement.style.setProperty('--content-x', ml + 'px');
      });
    }

    /* ── 事件绑定（无论 chrome 是 SSR 还是 JS 创建都执行）─── */
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
    if (themeBtn) themeBtn.addEventListener('click', function () {
      document.documentElement.classList.add('theme-switching');
      S.theme = S.theme === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem(LS, JSON.stringify(S)); } catch (e) {}
      applyTheme();
      setTimeout(function () { document.documentElement.classList.remove('theme-switching'); }, 380);
    });

    /* 侧栏状态机 */
    var menuBtn = g('btn-menu'), scrim = g('scrim'), sbToggle = g('sb-toggle'), wsBtn = g('ws');
    var sbNav = g('sbNav');
    var sbOrder = ['full', 'rail', 'closed'];
    function sbSet(v) { document.body.dataset.sb = v; S.sb = v; try { localStorage.setItem(LS, JSON.stringify(S)); } catch (e) {} }
    function sbOpen(v) { document.body.dataset.sb = v ? 'drawer' : (S.sb || 'full'); if (menuBtn) menuBtn.setAttribute('aria-expanded', v ? 'true' : 'false'); }
    if (S.sb === 'rail' || S.sb === 'closed') sbSet(S.sb);
    if (sbToggle) sbToggle.addEventListener('click', function () {
      if (matchMedia('(max-width:860px)').matches) { sbOpen(false); return; }
      var cur = document.body.dataset.sb === 'drawer' ? (S.sb || 'full') : document.body.dataset.sb;
      var i = sbOrder.indexOf(cur); if (i < 0) i = 0;
      sbSet(sbOrder[(i + 1) % 3]);
    });
    if (wsBtn) wsBtn.addEventListener('click', function () { sbSet('closed'); });
    if (menuBtn) menuBtn.addEventListener('click', function () { sbOpen(document.body.dataset.sb !== 'drawer'); });
    if (scrim) scrim.addEventListener('click', function () { sbOpen(false); });
    if (sbNav) sbNav.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { sbOpen(false); }); });

    /* 滚动胶囊 */
    var chrome = g('chrome');
    function onScroll() {
      var y = window.scrollY || document.documentElement.scrollTop;
      if (chrome) {
        chrome.classList.toggle('scrolled', y > 24);
        chrome.classList.toggle('capsule-mode', y > 260);
      }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  if (document.body) mount();
  else document.addEventListener('DOMContentLoaded', mount);
})();
