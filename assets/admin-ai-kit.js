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
})();
