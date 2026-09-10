/* OpenFlow · admin-ai-kit.js — AI 原生交互套件（JS 侧）
 * AIThink: 思考过程面板（步骤推进 + 真实计时）
 * aiTypewriter: 关键生成文本打字机
 * 依赖 admin-ai-kit.css；无其他依赖。
 */
(function () {
  'use strict';

  /* AIThink.start(container, steps) → { done(summaryHtml?), fail(msg) }
   * container: 选择器或元素；steps: 步骤文案数组。 */
  window.AIThink = {
    start: function (container, steps) {
      var box = typeof container === 'string' ? document.querySelector(container) : container;
      if (!box) return { done: function () {}, fail: function () {} };
      box.innerHTML =
        '<div class="ai-think">' +
          '<div class="ai-orb"></div>' +
          '<div class="ai-steps">' +
            steps.map(function (s) { return '<div class="ai-step">' + s + '</div>'; }).join('') +
          '</div>' +
          '<div class="ai-elapsed">0.0s</div>' +
        '</div>';
      var stepEls = box.querySelectorAll('.ai-step');
      var elapsedEl = box.querySelector('.ai-elapsed');
      var t0 = Date.now(), idx = 0, stopped = false;
      stepEls[0] && stepEls[0].classList.add('active');

      var stepTimer = setInterval(function () {
        if (idx < stepEls.length - 1) {
          stepEls[idx].classList.remove('active');
          stepEls[idx].classList.add('done');
          idx++;
          stepEls[idx].classList.add('active');
        }
      }, 1600);
      var clock = setInterval(function () {
        if (elapsedEl) elapsedEl.textContent = ((Date.now() - t0) / 1000).toFixed(1) + 's';
      }, 100);

      function finish(ok, payload) {
        if (stopped) return;
        stopped = true;
        clearInterval(stepTimer); clearInterval(clock);
        stepEls.forEach(function (s) { s.classList.remove('active'); s.classList.add('done'); });
        var total = ((Date.now() - t0) / 1000).toFixed(1);
        var panel = box.querySelector('.ai-think');
        if (!ok) {
          if (panel) {
            panel.style.borderColor = 'var(--danger, #dc2626)';
            panel.innerHTML = '<div style="font-size:13.5px;color:var(--danger,#dc2626)">⚠️ ' + (payload || '生成失败，请重试') + '</div>';
          }
          return;
        }
        if (panel) {
          panel.style.borderColor = 'var(--ok, #22c55e)';
          var last = panel.querySelector('.ai-steps');
          if (last) last.insertAdjacentHTML('beforeend', '<div class="ai-step done" style="font-weight:600">✅ 完成，用时 ' + total + 's' + (payload ? ' · ' + payload : '') + '</div>');
        }
        setTimeout(function () { if (panel) { panel.style.transition = 'opacity .5s'; panel.style.opacity = '0'; setTimeout(function () { panel.remove(); }, 500); } }, 2600);
      }
      return { done: function (summary) { finish(true, summary); }, fail: function (msg) { finish(false, msg); } };
    }
  };

  /* aiTypewriter(el, text, speed?) — 打完移除光标 */
  window.aiTypewriter = function (el, text, speed) {
    if (!el) return;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = text; return; }
    el.textContent = '';
    el.classList.add('ai-typing');
    var i = 0, sp = speed || 18;
    (function tick() {
      if (i <= text.length) {
        el.textContent = text.slice(0, i++);
        setTimeout(tick, sp);
      } else {
        el.classList.remove('ai-typing');
      }
    })();
  };

  /* ── KPI 数字滚动：.k-val 内含数字的元素自动 count-up ──
   * 支持 ¥1,234 / 12.5% / — 等格式；保留前后缀与千分位。 */
  function animateCounters() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('.k-val').forEach(function (el) {
      var raw = el.textContent.trim();
      var m = raw.match(/^([^\d\-]*)(-?[\d,]+(?:\.\d+)?)([^\d]*)$/);
      if (!m) return; // 纯文本（如「运行中」「—」）不动
      var target = parseFloat(m[2].replace(/,/g, ''));
      if (!isFinite(target) || target === 0) return;
      var prefix = m[1], suffix = m[3], decimals = (m[2].split('.')[1] || '').length;
      var t0 = null, dur = 900;
      function fmt(v) {
        var s = decimals ? v.toFixed(decimals) : String(Math.round(v));
        if (m[2].indexOf(',') >= 0) s = Number(s).toLocaleString('en-US', decimals ? { minimumFractionDigits: decimals } : {});
        return prefix + s + suffix;
      }
      function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var ease = 1 - Math.pow(1 - p, 3); // easeOutCubic
        el.textContent = fmt(target * ease);
        if (p < 1) requestAnimationFrame(step);
      }
      el.textContent = fmt(0);
      requestAnimationFrame(step);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', animateCounters);
  else animateCounters();

  /* ── SVG 趋势 sparkline：.ai-sparkline[data-points="1,2,3"] ──
   * 面积渐变 + 描边 draw-in 动画 + 端点圆点。 */
  window.aiSparkline = function (el) {
    var pts = (el.dataset.points || '').split(',').map(Number).filter(function (v) { return isFinite(v); });
    if (pts.length < 2) return;
    var W = 600, H = 130, pad = 8;
    var max = Math.max.apply(null, pts) || 1, min = Math.min.apply(null, pts);
    var span = (max - min) || 1;
    var stepX = (W - pad * 2) / (pts.length - 1);
    var xy = pts.map(function (v, i) {
      return [pad + i * stepX, H - pad - ((v - min) / span) * (H - pad * 2)];
    });
    var line = xy.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var area = line + ' L' + xy[xy.length - 1][0].toFixed(1) + ',' + H + ' L' + xy[0][0].toFixed(1) + ',' + H + ' Z';
    var gid = 'sg' + Math.random().toString(36).slice(2, 7);
    el.innerHTML =
      '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" style="width:100%;height:130px;display:block">' +
        '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">' +
          '<stop offset="0" stop-color="var(--accent)" stop-opacity=".28"/>' +
          '<stop offset="1" stop-color="var(--accent)" stop-opacity="0"/>' +
        '</linearGradient></defs>' +
        '<path d="' + area + '" fill="url(#' + gid + ')"/>' +
        '<path class="ai-spark-line" d="' + line + '" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
        '<circle cx="' + xy[xy.length - 1][0].toFixed(1) + '" cy="' + xy[xy.length - 1][1].toFixed(1) + '" r="4" fill="var(--accent)"/>' +
      '</svg>';
    var path = el.querySelector('.ai-spark-line');
    if (path && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
      var len = path.getTotalLength();
      path.style.strokeDasharray = len;
      path.style.strokeDashoffset = len;
      path.getBoundingClientRect();
      path.style.transition = 'stroke-dashoffset 1.1s cubic-bezier(.4,0,.2,1)';
      path.style.strokeDashoffset = '0';
    }
  };
  function initSparklines() { document.querySelectorAll('.ai-sparkline').forEach(aiSparkline); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSparklines);
  else initSparklines();

  /* ── 全局 AI 调用指示器 ──
   * 拦截 fetch / XHR 中对 /api/ai-*、/api/survey-ai 的调用，
   * 右下角浮出「AI 处理中 · 计时」，结束自动消失。
   * 创作台 /api/create.php 与画布已有专属思考面板，不重复指示。 */
  (function globalAiIndicator() {
    var RE = /\/api\/(ai-[a-z]+|survey-ai)\.php/;
    var ind = null, clock = null, t0 = 0, pending = 0;
    function ensure() {
      if (ind) return ind;
      ind = document.createElement('div');
      ind.className = 'ai-global-ind hide';
      ind.innerHTML = '<div class="ai-orb"></div><span class="ai-ind-label">AI 处理中</span><span class="ai-ind-t">0.0s</span>';
      document.body.appendChild(ind);
      return ind;
    }
    function waifuSay(text, ms) { try { if (window.OFWaifu && window.OFWaifu.say) window.OFWaifu.say(text, ms); } catch (e) {} }
    function show() {
      pending++;
      if (pending > 1) return;
      var el = ensure();
      t0 = Date.now();
      el.classList.remove('hide');
      el.querySelector('.ai-ind-label').textContent = 'AI 处理中';
      waifuSay('🤔 让我想想…', 0);
      clock = setInterval(function () {
        var t = el.querySelector('.ai-ind-t');
        if (t) t.textContent = ((Date.now() - t0) / 1000).toFixed(1) + 's';
      }, 100);
    }
    function hide() {
      pending = Math.max(0, pending - 1);
      if (pending > 0 || !ind) return;
      clearInterval(clock);
      var el = ind, total = ((Date.now() - t0) / 1000).toFixed(1);
      el.querySelector('.ai-ind-label').textContent = '✅ 完成 · ' + total + 's';
      waifuSay('搞定啦，用了 ' + total + 's ✨', 2600);
      setTimeout(function () { el.classList.add('hide'); }, 900);
    }
    var ofFetch = window.fetch;
    if (ofFetch) {
      window.fetch = function (url) {
        var u = String((url && url.url) || url);
        if (!RE.test(u)) return ofFetch.apply(this, arguments);
        show();
        var p = ofFetch.apply(this, arguments);
        p.then(hide, hide);
        return p;
      };
    }
    var oOpen = XMLHttpRequest.prototype.open, oSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (m, u) {
      this._ofAiInd = RE.test(String(u));
      return oOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
      if (this._ofAiInd) { show(); this.addEventListener('loadend', hide); }
      return oSend.apply(this, arguments);
    };
  })();
})();
