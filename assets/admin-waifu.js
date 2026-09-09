/* ─────────────────────────────────────────────────────────────────────────
 * OpenFlow · admin-waifu.js v1 — 后台二次元看板娘（Live2D）
 *
 * 造型：Live2D 官方免费示例模型 Hiyori（Live2D Free Material License），
 * 运行时：Cubism SDK for Web（© Live2D Inc.）+ pixi.js@6 + pixi-live2d-display，
 * 全部自托管于 assets/vendor/live2d/，无外部 CDN 依赖。
 *
 * 交互：
 *   - 视线/头部实时跟随鼠标（全页面范围，不只在角色上）
 *   - 待机随机动作 + 自动眨眼/呼吸（模型物理）
 *   - 点击角色 → 打开/收起 OFOR 聊天窗，并播放 TapBody 动作 + 气泡吐槽
 *   - 气泡提示：首次进入按时段问候；随后按当前页面给出 Copilot 建议
 *   - 聊天联动：发消息=思考动作，收到回复=开心动作，出错=委屈气泡
 *   - 右键角色 → 本次会话隐藏（sessionStorage），旧圆形按钮兜底
 *
 * 降级（任一命中则不加载模型，保留旧圆形 FAB）：
 *   ≤840px 窄屏 / prefers-reduced-motion / 无 WebGL / 会话内被隐藏
 * ─────────────────────────────────────────────────────────────────────── */
(function () {
  if (window.__OF_WAIFU__) return;
  window.__OF_WAIFU__ = true;

  var BASE = '/assets/vendor/live2d';
  var MODEL_URL = BASE + '/model/hiyori/Hiyori.model3.json';
  var W = 210, H = 300; // 画布 CSS 尺寸

  function reducedMotion() { return matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function narrow() { return matchMedia('(max-width:840px)').matches; }
  function hiddenThisSession() { try { return sessionStorage.getItem('of_waifu_off') === '1'; } catch (e) { return false; } }
  function webglOk() {
    try { var c = document.createElement('canvas'); return !!(c.getContext('webgl') || c.getContext('experimental-webgl')); }
    catch (e) { return false; }
  }
  function fallback() { return reducedMotion() || narrow() || hiddenThisSession() || !webglOk(); }

  /* ── 气泡 ── */
  var bubbleEl = null, bubbleTimer = 0;
  function say(text, ms) {
    if (!bubbleEl) return;
    bubbleEl.textContent = text;
    bubbleEl.classList.add('show');
    clearTimeout(bubbleTimer);
    if (ms !== 0) bubbleTimer = setTimeout(function () { bubbleEl.classList.remove('show'); }, ms || 4200);
  }

  /* ── 聊天联动（config.php 的助手在关键节点调用这些钩子） ── */
  var model = null;
  function react(group) {
    if (!model) return;
    try { model.motion(group); } catch (e) {}
  }
  window.OFWaifu = {
    say: say,
    onChatToggle: function (open) {
      if (open) { react('TapBody'); say(pick(['来啦～有什么想让我做的？', '我在呢，尽管问 ✨', '今天也想高效下班！']), 3600); }
    },
    onSend: function () { say('让我想想…', 0); },
    onReceive: function () { react('TapBody'); say(pick(['回复好啦，看看有没有用～', '搞定 ✨ 还有别的吗？']), 3000); },
    onError: function () { say('呜…网络好像出问题了', 3600); }
  };
  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  if (fallback()) return; // 旧 FAB 保持可见，到此为止

  /* ── 懒加载运行库（后台首屏优先，模型延后） ── */
  function loadScript(src) {
    return new Promise(function (res, rej) {
      var s = document.createElement('script');
      s.src = src; s.onload = res; s.onerror = rej;
      document.head.appendChild(s);
    });
  }
  function boot() {
    if (!window.PIXI || !window.PIXI.live2d) return;
    buildStage();
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

  /* ── 舞台 ── */
  function buildStage() {
    var box = document.createElement('div');
    box.className = 'of-waifu';
    box.innerHTML =
      '<div class="of-waifu-bubble" role="status"></div>' +
      '<canvas class="of-waifu-canvas" width="' + W + '" height="' + H + '" aria-label="OFOR 看板娘，点击打开助手"></canvas>';
    document.body.appendChild(box);
    bubbleEl = box.querySelector('.of-waifu-bubble');

    var canvas = box.querySelector('canvas');
    var app = new PIXI.Application({
      view: canvas, transparent: true, autoStart: true,
      width: W, height: H, resolution: window.devicePixelRatio || 1, autoDensity: true
    });

    PIXI.live2d.Live2DModel.from(MODEL_URL, { autoInteract: false }).then(function (m) {
      model = m;
      var ow = m.width, oh = m.height;
      var s = (H * 0.98) / oh;
      m.scale.set(s);
      m.x = (W - ow * s) / 2;
      m.y = H - oh * s;
      app.stage.addChild(m);
      document.body.classList.add('waifu-on'); // 隐藏旧 FAB、让出底条

      // 视线跟随（全页面）：把鼠标位置换算进画布坐标
      var raf = 0;
      document.addEventListener('pointermove', function (e) {
        if (raf) return;
        raf = requestAnimationFrame(function () {
          raf = 0;
          var r = canvas.getBoundingClientRect();
          try { m.focus(e.clientX - r.left, e.clientY - r.top); } catch (err) {}
        });
      }, { passive: true });

      // 待机动作：模型会循环播放 Idle 组，这里定时换一个，避免一直是同一个
      react('Idle');
      setInterval(function () { if (!document.hidden) react('Idle'); }, 32000);

      greet();
    }).catch(function () { box.remove(); });

    // 点击 = 互动动作 + 开关聊天窗
    canvas.addEventListener('pointerdown', function () {
      react('TapBody');
      if (window.fcHelperToggle) window.fcHelperToggle();
      else say(pick(['戳我干嘛啦～', '再戳要害羞了！', '嘿嘿，痒痒的']));
    });
    // 右键 = 本次会话隐藏
    box.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      try { sessionStorage.setItem('of_waifu_off', '1'); } catch (err) {}
      document.body.classList.remove('waifu-on');
      box.remove();
      if (window.fcToast) fcToast('OFOR 看板娘本次会话已隐藏，新开标签页会回来');
    });
  }

  /* ── 问候：时段 + 当前页面建议（复用助手的 Copilot 页面地图） ── */
  function greet() {
    var h = new Date().getHours();
    var hello = h < 6 ? '夜深了，注意休息呀' : h < 12 ? '早上好呀' : h < 14 ? '中午好，吃饭了吗' : h < 19 ? '下午好呀' : '晚上好呀';
    var page = '', hints = null;
    try {
      if (window.fcHelperCurrentPage && window.FC_HELPER) {
        page = fcHelperCurrentPage();
        hints = FC_HELPER.pageHints[page];
      }
    } catch (e) {}
    var tip = hints && hints.length ? '在「' + page + '」可以试试：' + hints[0] : '点我可以聊天，右键我会暂时消失';
    setTimeout(function () { say(hello + '～' + tip, 6500); }, 1200);
  }

  if (document.readyState === 'complete') schedule();
  else window.addEventListener('load', schedule);
})();
