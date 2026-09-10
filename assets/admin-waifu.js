/* ─────────────────────────────────────────────────────────────────────────
 * OpenFlow · admin-waifu.js v2 — 后台看板娘「衣橱系统」（Live2D）
 *
 * 六位可换形象（均为 Live2D 官方免费示例模型，Free Material License）：
 *   rice    璃丝 —— 成熟御姐 · 黑裙（默认。语气：从容撩人、干练自信）
 *   mao     玛奥 —— 猫娘少女（语气：慵懒俏皮）
 *   ren     莲   —— 中性青年（语气：冷静简洁）
 *   natori  名取 —— 西装男性（语气：商务专业）
 *   mark    马克 —— 休闲男性（语气：随和直爽）
 *   hiyori  日和 —— 经典少女（语气：活泼可爱）
 *
 * 换装：右键角色 → 衣橱菜单（换人 / 本次隐藏）；选择记 localStorage，
 *       后台「设置 → waifu_model」可改全站默认（config.php 注入 OF_WAIFU_CONFIG）。
 *
 * 想换成真正的「黑丝女武神」等定制造型：把任何 Cubism 4.x 模型目录放进
 * assets/vendor/live2d/model/ 并在下方 WARDROBE 注册一行即可（Booth/nizima 有售）。
 *
 * 交互：视线跟随鼠标 / 待机动作 / 点击开聊天 / 气泡按性格出文案 / 聊天联动。
 * 降级：窄屏 / 减少动态 / 无 WebGL / 会话内隐藏 → 保留旧圆形 FAB。
 * ─────────────────────────────────────────────────────────────────────── */
(function () {
  if (window.__OF_WAIFU__) return;
  window.__OF_WAIFU__ = true;

  var BASE = '/assets/vendor/live2d';
  var W = 230, H = 330;

  /* ── 衣橱注册表 ── */
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

  /* 个人换装（localStorage）只在「全站默认版本」未变时生效；
   * 后台设置改了默认形象 → ver 变化 → 所有浏览器回到新默认，之后可再右键个人换装 */
  function cfgDefault() { var d = (window.OF_WAIFU_CONFIG && window.OF_WAIFU_CONFIG.default) || 'rice'; return WARDROBE[d] ? d : 'rice'; }
  function cfgVer() { return String((window.OF_WAIFU_CONFIG && window.OF_WAIFU_CONFIG.ver) || '0'); }
  function readChoice() {
    try {
      var raw = localStorage.getItem('of_waifu_model');
      if (!raw) return null;
      var c = JSON.parse(raw); // 新格式 {id, ver}
      if (c && WARDROBE[c.id] && String(c.ver) === cfgVer()) return c.id;
      localStorage.removeItem('of_waifu_model'); // 旧格式或版本已过期的个人选择，清除
    } catch (e) { try { localStorage.removeItem('of_waifu_model'); } catch (e2) {} }
    return null;
  }
  function writeChoice(id) {
    try { localStorage.setItem('of_waifu_model', JSON.stringify({ id: id, ver: cfgVer() })); } catch (e) {}
  }
  function clearChoice() { try { localStorage.removeItem('of_waifu_model'); } catch (e) {} }
  function currentId() { return readChoice() || cfgDefault(); }
  var persona = WARDROBE[currentId()];

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

  /* ── 聊天联动钩子 ── */
  var model = null;
  function react(group) {
    if (!model) return;
    try { model.motion(group); } catch (e) {}
  }
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

  /* ── 懒加载运行库 ── */
  function loadScript(src) {
    return new Promise(function (res, rej) {
      var s = document.createElement('script');
      s.src = src; s.onload = res; s.onerror = rej;
      document.head.appendChild(s);
    });
  }
  function schedule() {
    setTimeout(function () {
      loadScript(BASE + '/live2dcubismcore.min.js')
        .then(function () { return loadScript(BASE + '/pixi.min.js'); })
        .then(function () { return loadScript(BASE + '/pixi-live2d-display.min.js'); })
        .then(boot)
        .catch(function () { /* 加载失败 → 静默退回 FAB */ });
    }, 900);
  }
  function boot() {
    if (!window.PIXI || !window.PIXI.live2d) return;
    buildStage();
  }

  /* ── 衣橱菜单（右键）── */
  var menuEl = null;
  function closeMenu() { if (menuEl) { menuEl.remove(); menuEl = null; } }
  document.addEventListener('pointerdown', function (e) {
    if (menuEl && !menuEl.contains(e.target)) closeMenu();
  }, true);
  function openMenu(x, y, box) {
    closeMenu();
    menuEl = document.createElement('div');
    menuEl.className = 'of-waifu-menu';
    var html = '<div class="m-head">换装 · 衣橱</div>';
    ORDER.forEach(function (id) {
      var w = WARDROBE[id];
      html += '<button class="m-item' + (id === currentId() ? ' on' : '') + '" data-id="' + id + '">'
            + '<b>' + w.name + '</b><small>' + w.tag + '</small></button>';
    });
    html += '<button class="m-item" data-act="default">跟随全站默认（' + WARDROBE[cfgDefault()].name + '）</button>';
    html += '<button class="m-item m-hide" data-act="hide">本次会话隐藏</button>';
    menuEl.innerHTML = html;
    document.body.appendChild(menuEl);
    // 定位：优先出现在角色上方，防出屏
    var mw = 180, mh = menuEl.offsetHeight || 260;
    menuEl.style.left = Math.max(8, Math.min(x - mw / 2, innerWidth - mw - 8)) + 'px';
    menuEl.style.top = Math.max(8, y - mh - 12) + 'px';
    menuEl.addEventListener('click', function (e) {
      var btn = e.target.closest('.m-item');
      if (!btn) return;
      if (btn.dataset.act === 'hide') {
        try { sessionStorage.setItem('of_waifu_off', '1'); } catch (err) {}
        document.body.classList.remove('waifu-on');
        closeMenu(); box.remove();
        if (window.fcToast) fcToast(persona.hidden + '（新开标签页会回来）');
        return;
      }
      if (btn.dataset.act === 'default') {
        var cur = currentId();
        clearChoice();
        closeMenu();
        if (cur !== cfgDefault()) swapModel(cfgDefault(), box);
        else say('已跟随全站默认：' + WARDROBE[cfgDefault()].name, 2600);
        return;
      }
      var id = btn.dataset.id;
      if (id && WARDROBE[id] && id !== currentId()) {
        writeChoice(id);
        closeMenu();
        swapModel(id, box);
      } else closeMenu();
    });
  }

  /* ── 换装：销毁旧模型，加载新模型（不刷新页面）── */
  var appRef = null, canvasRef = null, boxRef = null;
  function swapModel(id, box) {
    persona = WARDROBE[id];
    if (model) { try { appRef.stage.removeChild(model); model.destroy(); } catch (e) {} model = null; }
    say('换装中…', 0);
    PIXI.live2d.Live2DModel.from(BASE + '/model/' + persona.file, { autoInteract: false }).then(function (m) {
      model = m;
      var ow = m.width, oh = m.height;
      var s = (H * 0.98) / oh;
      m.scale.set(s);
      m.x = (W - ow * s) / 2;
      m.y = H - oh * s;
      appRef.stage.addChild(m);
      react('Idle');
      say(persona.name + '：' + persona.hello, 3600);
    }).catch(function () { say('这个形象加载失败了…', 3000); });
  }

  /* ── 舞台 ── */
  function buildStage() {
    var box = document.createElement('div');
    box.className = 'of-waifu';
    box.innerHTML =
      '<div class="of-waifu-bubble" role="status"></div>' +
      '<canvas class="of-waifu-canvas" width="' + W + '" height="' + H + '" aria-label="OFOR 助手，点击聊天，右键换装"></canvas>';
    document.body.appendChild(box);
    bubbleEl = box.querySelector('.of-waifu-bubble');

    var canvas = box.querySelector('canvas');
    var app = new PIXI.Application({
      view: canvas, transparent: true, autoStart: true,
      width: W, height: H, resolution: window.devicePixelRatio || 1, autoDensity: true
    });
    appRef = app; canvasRef = canvas; boxRef = box;

    PIXI.live2d.Live2DModel.from(BASE + '/model/' + persona.file, { autoInteract: false }).then(function (m) {
      model = m;
      var ow = m.width, oh = m.height;
      var s = (H * 0.98) / oh;
      m.scale.set(s);
      m.x = (W - ow * s) / 2;
      m.y = H - oh * s;
      app.stage.addChild(m);
      document.body.classList.add('waifu-on');

      // 视线跟随（全页面）
      var raf = 0;
      document.addEventListener('pointermove', function (e) {
        if (raf) return;
        raf = requestAnimationFrame(function () {
          raf = 0;
          var r = canvas.getBoundingClientRect();
          try { m.focus(e.clientX - r.left, e.clientY - r.top); } catch (err) {}
        });
      }, { passive: true });

      react('Idle');
      setInterval(function () { if (!document.hidden) react('Idle'); }, 32000);

      greet();
    }).catch(function () { box.remove(); });

    // 点击 = 互动动作 + 开关聊天窗
    canvas.addEventListener('pointerdown', function () {
      react('TapBody');
      if (window.fcHelperToggle) window.fcHelperToggle();
      else say(persona.tapOnly);
    });
    // 右键 = 衣橱菜单
    box.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      var r = canvas.getBoundingClientRect();
      openMenu(r.left + r.width / 2, r.top, box);
    });
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
