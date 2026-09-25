/* ─────────────────────────────────────────────────────────────────────────
 * OpenFlow · admin-waifu.js v3 — 后台看板娘「衣橱系统」（2D Live2D + 3D VRM）
 *
 * 两种形态，右键角色 → 衣橱菜单随时切换（选择记 localStorage，随全站 ver 失效）：
 *   2D  Live2D 六位形象（官方免费示例，Free Material License）
 *       rice 璃丝(默认) / mao 玛奥 / ren 莲 / natori 名取 / mark 马克 / hiyori 日和
 *   3D  VRM 立体助手（three.js + @pixiv/three-vrm，懒加载）
 *       seed 希德 —— Seed-san © VirtualCast, Inc. · VRM Public License 1.0
 *
 * 全站默认（形态 + 2D 形象）在后台「设置」里改；改默认会 bump ver，
 * 所有浏览器的个人选择随之回到新默认。3D 形象同样支持个人换装。
 *
 * 主壳负责：配置/选择存储、DOM 舞台、气泡、衣橱菜单、聊天联动、降级。
 * 渲染器（2D/3D）统一接口：react(group) / focus(x,y) / setModel(file) / destroy()。
 * 降级：窄屏 / 减少动态 / 无 WebGL / 会话内隐藏 → 保留旧圆形 FAB。
 * ─────────────────────────────────────────────────────────────────────── */
(function () {
  if (window.__OF_WAIFU__) return;
  window.__OF_WAIFU__ = true;

  var BASE = '/assets/vendor/live2d';
  var VRMJS = '/assets/waifu-vrm.js';
  var W = 230, H = 330;

  /* ── 2D 衣橱（Live2D）── */
  var WARDROBE = {
    rice: {
      name: '璃丝', file: 'rice/Rice.model3.json', tag: '御姐 · 黑裙',
      hello: '回来了？我刚好泡了咖啡，说吧，今天想做成什么。', tapChat: '我在，慢慢说。', tapOnly: '嗯？光看不说话，可不像你。',
      thinking: '让我看看…别急。', done: '做好了。还不错吧？', fail: '出了点小状况，我陪你看看。',
      hidden: '想我了，右键叫我回来。'
    },
    mao: {
      name: '玛奥', file: 'mao/Mao.model3.json', tag: '猫娘 · 少女',
      hello: '喵～来干活啦。', tapChat: '说吧说吧，什么事。', tapOnly: '喵？别光戳呀。',
      thinking: '我瞅瞅…', done: '好啦，过目～', fail: '呜，出了点状况。',
      hidden: '需要我时，右键菜单随时叫回来喵。'
    },
    ren: {
      name: '莲', file: 'ren/Ren.model3.json', tag: '中性 · 青年',
      hello: '你好，我在。', tapChat: '请讲。', tapOnly: '嗯？',
      thinking: '处理中…', done: '完成了。', fail: '出错了，重试一下。',
      hidden: '好的，稍后见。'
    },
    natori: {
      name: '名取', file: 'natori/Natori.model3.json', tag: '男性 · 西装',
      hello: '欢迎回来，今天也按计划推进吧。', tapChat: '请指示。', tapOnly: '我在听。',
      thinking: '稍等，我确认一下…', done: '已完成，请查收。', fail: '似乎出了点问题。',
      hidden: '明白，我先退下。'
    },
    mark: {
      name: '马克', file: 'mark/Mark.model3.json', tag: '男性 · 休闲',
      hello: '哟，来啦！', tapChat: '说吧兄弟！', tapOnly: '哈哈，别闹。',
      thinking: '让我想想哈…', done: '搞定！', fail: '哎呀，翻车了…',
      hidden: '行，我先溜了！'
    },
    hiyori: {
      name: '日和', file: 'hiyori/Hiyori.model3.json', tag: '经典 · 少女',
      hello: '今天也想高效下班！', tapChat: '来啦～有什么想让我做的？', tapOnly: '戳我干嘛啦～',
      thinking: '让我想想…', done: '搞定 ✨ 还有别的吗？', fail: '呜…网络好像出问题了',
      hidden: '那我先躲起来啦～'
    }
  };
  var ORDER = ['rice', 'mao', 'ren', 'natori', 'mark', 'hiyori'];

  /* ── 3D 衣橱（VRM）── */
  var VRM_WARDROBE = {
    seed: {
      name: '希德', file: 'seed/Seed-san.vrm', tag: '科技 · 少年',
      credit: 'Seed-san © VirtualCast, Inc. · VRM PL 1.0',
      hello: '系统就绪。今天从哪开始？', tapChat: '说，我在听。', tapOnly: '嗯？',
      thinking: '处理中…', done: '完成，同步给你。', fail: '出了点故障，重试一次。',
      hidden: '需要时，右键唤回。'
    }
  };
  var VORDER = ['seed'];

  /* ── 全站默认（admin/config.php 注入 OF_WAIFU_CONFIG）── */
  function cfgDefault() { var d = (window.OF_WAIFU_CONFIG && window.OF_WAIFU_CONFIG.default) || 'rice'; return WARDROBE[d] ? d : 'rice'; }
  function cfgMode() { return (window.OF_WAIFU_CONFIG && window.OF_WAIFU_CONFIG.mode) === '3d' ? '3d' : '2d'; }
  function cfgVer() { return String((window.OF_WAIFU_CONFIG && window.OF_WAIFU_CONFIG.ver) || '0'); }

  /* 个人选择（localStorage，格式 {id, ver}；ver 不匹配 → 视为过期清除） */
  function readStore(key, valid) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return null;
      var c = JSON.parse(raw);
      if (c && valid(c.id) && String(c.ver) === cfgVer()) return c.id;
      localStorage.removeItem(key);
    } catch (e) { try { localStorage.removeItem(key); } catch (e2) {} }
    return null;
  }
  function writeStore(key, id) {
    try { localStorage.setItem(key, JSON.stringify({ id: id, ver: cfgVer() })); } catch (e) {}
  }
  function clearStore(key) { try { localStorage.removeItem(key); } catch (e) {} }

  function is2dModel(id) { return !!WARDROBE[id]; }
  function is3dModel(id) { return !!VRM_WARDROBE[id]; }
  function isMode(m) { return m === '2d' || m === '3d'; }

  var mode = (function () {
    var m = readStore('of_waifu_mode', isMode);
    return m || cfgMode();
  })();
  var currentId = function () { return readStore('of_waifu_model', is2dModel) || cfgDefault(); };       // 2D 形象
  var currentVrm = function () { return readStore('of_waifu_vrm', is3dModel) || 'seed'; };              // 3D 形象
  var persona = mode === '3d' ? VRM_WARDROBE[currentVrm()] : WARDROBE[currentId()];

  function reducedMotion() { return matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function narrow() { return matchMedia('(max-width:840px)').matches; }
  function hiddenThisSession() { try { return sessionStorage.getItem('of_waifu_off') === '1'; } catch (e) { return false; } }
  function webglOk() {
    try { var c = document.createElement('canvas'); return !!(c.getContext('webgl') || c.getContext('experimental-webgl')); }
    catch (e) { return false; }
  }
  function fallback() { return reducedMotion() || narrow() || hiddenThisSession() || !webglOk(); }
  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  /* ── 气泡 ── */
  var bubbleEl = null, bubbleTimer = 0;
  function say(text, ms) {
    if (!bubbleEl) return;
    bubbleEl.textContent = text;
    bubbleEl.classList.add('show');
    clearTimeout(bubbleTimer);
    if (ms !== 0) bubbleTimer = setTimeout(function () { bubbleEl.classList.remove('show'); }, ms || 4200);
  }

  /* ── 聊天联动钩子（两种形态共用）── */
  var ctrl = null;   // 当前渲染器控制器
  function react(group) { if (ctrl) { try { ctrl.react(group); } catch (e) {} } }
  window.OFWaifu = {
    say: say,
    onChatToggle: function (open) {
      if (open) { react('TapBody'); say(persona.tapChat, 3600); }
    },
    onSend: function () { say(persona.thinking, 0); },
    onReceive: function () { react('TapBody'); say(persona.done, 3000); },
    onError: function () { say(persona.fail, 3600); }
  };

  if (fallback()) return; // 旧 FAB 保持可见，到此为止

  /* ── 懒加载脚本 ── */
  function loadScript(src) {
    return new Promise(function (res, rej) {
      var s = document.createElement('script');
      s.src = src; s.onload = res; s.onerror = rej;
      document.head.appendChild(s);
    });
  }
  function schedule() { setTimeout(boot, 900); }

  /* ── 舞台生命周期 ── */
  var box = null, canvas = null, idleTimer = 0;
  function teardown() {
    if (ctrl) { try { ctrl.destroy(); } catch (e) {} ctrl = null; }
    clearInterval(idleTimer); idleTimer = 0;
    if (box) { box.remove(); box = null; }
    bubbleEl = null;
  }
  function buildBox() {
    box = document.createElement('div');
    box.className = 'of-waifu';
    box.innerHTML =
      '<div class="of-waifu-bubble" role="status"></div>' +
      '<canvas class="of-waifu-canvas" width="' + W + '" height="' + H + '" aria-label="OFOR 助手，点击聊天，右键换装"></canvas>';
    document.body.appendChild(box);
    bubbleEl = box.querySelector('.of-waifu-bubble');
    canvas = box.querySelector('canvas');

    canvas.addEventListener('pointerdown', function () {
      react('TapBody');
      if (window.fcHelperToggle) window.fcHelperToggle();
      else say(persona.tapOnly);
    });
    box.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      var r = canvas.getBoundingClientRect();
      openMenu(r.left + r.width / 2, r.top);
    });
  }

  function boot() {
    teardown();
    buildBox();
    (mode === '3d' ? boot3D : boot2D)().then(function (c) {
      ctrl = c;
      document.body.classList.add('waifu-on');
      idleTimer = setInterval(function () { if (!document.hidden) react('Idle'); }, 32000);
      react('Idle');
      greet();
    }).catch(function () { teardown(); });
  }

  /* ── 2D 渲染器（Live2D，行为与 v2 相同）── */
  function boot2D() {
    return loadScript(BASE + '/live2dcubismcore.min.js')
      .then(function () { return loadScript(BASE + '/pixi.min.js'); })
      .then(function () { return loadScript(BASE + '/pixi-live2d-display.min.js'); })
      .then(function () {
        if (!window.PIXI || !window.PIXI.live2d) throw new Error('live2d lib missing');
        var app = new PIXI.Application({
          view: canvas, transparent: true, autoStart: true,
          width: W, height: H, resolution: window.devicePixelRatio || 1, autoDensity: true
        });
        var model = null;
        function place(m) {
          var ow = m.width, oh = m.height;
          var s = (H * 0.98) / oh;
          m.scale.set(s);
          m.x = (W - ow * s) / 2;
          m.y = H - oh * s;
        }
        function load(file) {
          return PIXI.live2d.Live2DModel.from(BASE + '/model/' + file, { autoInteract: false }).then(function (m) {
            if (model) { try { app.stage.removeChild(model); model.destroy(); } catch (e) {} }
            model = m;
            place(m);
            app.stage.addChild(m);
            try { m.motion('Idle'); } catch (e) {}
          });
        }
        return load(WARDROBE[currentId()].file).then(function () {
          return {
            react: function (g) { if (model) { try { model.motion(g); } catch (e) {} } },
            focus: function (cx, cy) {
              if (!model) return;
              var r = canvas.getBoundingClientRect();
              try { model.focus(cx - r.left, cy - r.top); } catch (e) {}
            },
            setModel: function (file) { return load(file); },
            destroy: function () { try { model && model.destroy(); app.destroy(true); } catch (e) {} }
          };
        });
      });
  }

  /* ── 3D 渲染器（VRM）── */
  function boot3D() {
    return loadScript(VRMJS).then(function () {
      if (!window.OFWaifuVRM) throw new Error('vrm renderer missing');
      say('启动 3D 助手…', 0);
      return OFWaifuVRM.mount(canvas, {
        width: W, height: H,
        file: VRM_WARDROBE[currentVrm()].file,
        onProgress: function (p) { say('加载 3D 助手…' + p + '%', 0); }
      });
    });
  }

  /* ── 视线跟随（全页面，两种形态）── */
  var rafF = 0;
  document.addEventListener('pointermove', function (e) {
    if (rafF) return;
    rafF = requestAnimationFrame(function () {
      rafF = 0;
      if (ctrl) ctrl.focus(e.clientX, e.clientY);
    });
  }, { passive: true });

  /* ── 衣橱菜单（右键）── */
  var menuEl = null;
  function closeMenu() { if (menuEl) { menuEl.remove(); menuEl = null; } }
  document.addEventListener('pointerdown', function (e) {
    if (menuEl && !menuEl.contains(e.target)) closeMenu();
  }, true);
  function openMenu(x, y) {
    closeMenu();
    menuEl = document.createElement('div');
    menuEl.className = 'of-waifu-menu';
    var html = '<div class="m-head">形态</div>';
    html += '<button class="m-item' + (mode === '2d' ? ' on' : '') + '" data-mode="2d"><b>2D 看板娘</b><small>Live2D · 六形象 · 秒开</small></button>';
    html += '<button class="m-item' + (mode === '3d' ? ' on' : '') + '" data-mode="3d"><b>3D 助手</b><small>VRM · 立体 · 首载约 12MB</small></button>';
    if (mode === '3d') {
      html += '<div class="m-head">3D 形象</div>';
      VORDER.forEach(function (id) {
        var w = VRM_WARDROBE[id];
        html += '<button class="m-item' + (id === currentVrm() ? ' on' : '') + '" data-vrm="' + id + '">'
              + '<b>' + w.name + '</b><small>' + w.tag + '</small></button>';
      });
    } else {
      html += '<div class="m-head">2D 形象</div>';
      ORDER.forEach(function (id) {
        var w = WARDROBE[id];
        html += '<button class="m-item' + (id === currentId() ? ' on' : '') + '" data-id="' + id + '">'
              + '<b>' + w.name + '</b><small>' + w.tag + '</small></button>';
      });
    }
    var defLabel = cfgMode() === '3d'
      ? '跟随全站默认（3D · ' + VRM_WARDROBE['seed'].name + '）'
      : '跟随全站默认（' + WARDROBE[cfgDefault()].name + '）';
    html += '<button class="m-item" data-act="default">' + defLabel + '</button>';
    html += '<button class="m-item m-hide" data-act="hide">本次会话隐藏</button>';
    if (mode === '3d') html += '<div class="m-credit">' + VRM_WARDROBE[currentVrm()].credit + '</div>';
    menuEl.innerHTML = html;
    document.body.appendChild(menuEl);
    var mw = 180, mh = menuEl.offsetHeight || 300;
    menuEl.style.left = Math.max(8, Math.min(x - mw / 2, innerWidth - mw - 8)) + 'px';
    menuEl.style.top = Math.max(8, y - mh - 12) + 'px';
    menuEl.addEventListener('click', function (e) {
      var btn = e.target.closest('.m-item');
      if (!btn) return;
      if (btn.dataset.act === 'hide') {
        try { sessionStorage.setItem('of_waifu_off', '1'); } catch (err) {}
        document.body.classList.remove('waifu-on');
        closeMenu(); teardown();
        if (window.fcToast) fcToast(persona.hidden + '（新开标签页会回来）');
        return;
      }
      if (btn.dataset.act === 'default') {
        var prevMode = mode, prev2d = currentId(), prev3d = currentVrm();
        clearStore('of_waifu_mode'); clearStore('of_waifu_model'); clearStore('of_waifu_vrm');
        closeMenu();
        if (cfgMode() !== prevMode) { setMode(cfgMode()); return; }
        if (prevMode === '2d') {
          if (prev2d !== cfgDefault()) swap2D(cfgDefault());
          else say('已跟随全站默认：' + WARDROBE[cfgDefault()].name, 2600);
        } else {
          if (prev3d !== 'seed') swap3D('seed');
          else say('已跟随全站默认：' + VRM_WARDROBE['seed'].name, 2600);
        }
        return;
      }
      if (btn.dataset.mode && btn.dataset.mode !== mode) { setMode(btn.dataset.mode); return; }
      if (btn.dataset.vrm) {
        var vid = btn.dataset.vrm;
        if (vid !== currentVrm()) { writeStore('of_waifu_vrm', vid); swap3D(vid); }
        closeMenu();
        return;
      }
      if (btn.dataset.id) {
        var id = btn.dataset.id;
        if (id !== currentId()) { writeStore('of_waifu_model', id); swap2D(id); }
        closeMenu();
        return;
      }
      closeMenu();
    });
  }

  /* ── 切换形态 / 换形象 ── */
  function setMode(m) {
    closeMenu();
    writeStore('of_waifu_mode', m);
    mode = m;
    persona = mode === '3d' ? VRM_WARDROBE[currentVrm()] : WARDROBE[currentId()];
    boot();
  }
  function swap2D(id) {
    persona = WARDROBE[id];
    if (ctrl) {
      say('换装中…', 0);
      ctrl.setModel(WARDROBE[id].file).then(function () {
        react('Idle');
        say(persona.name + '：' + persona.hello, 3600);
      }).catch(function () { say('这个形象加载失败了…', 3000); });
    }
  }
  function swap3D(vid) {
    persona = VRM_WARDROBE[vid];
    if (ctrl) {
      say('加载 3D 形象…', 0);
      ctrl.setModel(VRM_WARDROBE[vid].file).then(function () {
        react('TapBody');
        say(persona.name + '：' + persona.hello, 3600);
      }).catch(function () { say('这个形象加载失败了…', 3000); });
    }
  }

  /* ── 问候：时段 + 当前页面建议 ── */
  function greet() {
    var h = new Date().getHours();
    var hello = h < 6 ? '夜深了' : h < 12 ? '早上好' : h < 14 ? '中午好' : h < 19 ? '下午好' : '晚上好';
    var page = '', hints = null;
    try {
      if (window.fcHelperCurrentPage && window.FC_HELPER) {
        page = fcHelperCurrentPage();
        hints = FC_HELPER.pageHints[page];
      }
    } catch (e) {}
    var tip = hints && hints.length ? '在「' + page + '」可以试试：' + hints[0] : '点我聊天，右键换装';
    setTimeout(function () { say(persona.hello + ' ' + hello + '。' + tip, 6500); }, 1200);
  }

  if (document.readyState === 'complete') schedule();
  else window.addEventListener('load', schedule);
})();
