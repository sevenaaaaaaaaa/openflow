/**
 * OpenFlow · admin-ui.js — 后台框架层（v1 · 2026-09-03）
 *
 * 由 admin_footer() 引入，192 个后台页同时生效。每一段都只依赖 DOM 约定，不改任何业务代码：
 *   1. 侧栏：区切换 / 当前项滚到可见 / 最近打开
 *   2. 对话框：[data-confirm] 链接 / 按钮 / 表单 → 统一确认框（替代 98 处原生 confirm()）；ofAlert → toast
 *   3. 表单：POST 表单脏检测 + 离开提示；一屏放不下的表单自动加粘性保存条；≥4 分节的长表单右侧自动生成分节目录
 *   4. 表格：≥ 12 行的表自动获得筛选 / 计数 / 排序 / 分页
 *   5. emoji 图标 → 线框 svg
 */
(function () {
  if (window.OF_ADMIN_UI) return; window.OF_ADMIN_UI = true;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var SVG = function (p) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
  var DANGER_RE = /删除|移除|清空|清除|注销|停用|禁用|回收|重置|撤销|驳回|拒绝|退款|卸载|delete|remove|purge|revoke|reset|drop|destroy/i;

  /* ── 1. 侧栏 ── */
  (function nav() {
    var sb = $('#sidebar'); if (!sb) return;
    // 首屏不做侧栏过渡（data-sb 从 localStorage 恢复时不该「飞」一下）
    requestAnimationFrame(function () { requestAnimationFrame(function () { document.body.classList.add('sb-anim'); }); });
    // 窄屏抽屉的遮罩：点遮罩关闭
    var bd = document.createElement('div'); bd.className = 'sb-backdrop'; document.body.appendChild(bd);
    bd.addEventListener('click', function () { sb.classList.remove('open'); document.body.classList.remove('sb-open'); });
    var areas = $$('.sb-area', sb), panels = $$('.sb-panel', sb);
    function show(id) {
      areas.forEach(function (a) { var on = a.dataset.area === id; a.classList.toggle('on', on); a.setAttribute('aria-selected', on ? 'true' : 'false'); });
      panels.forEach(function (p) { p.classList.toggle('on', p.dataset.area === id); });
      sb.dataset.area = id;
    }
    areas.forEach(function (a) {
      a.addEventListener('click', function () {
        if (document.body.getAttribute('data-sb') === 'rail') { document.body.setAttribute('data-sb', 'full'); try { localStorage.setItem('of_sb', 'full'); } catch (e) {} }
        show(a.dataset.area);
      });
    });
    // 当前项滚到可见
    var act = $('.sb-link.active', sb);
    if (act) { var pn = $('.sb-panels', sb); if (pn) pn.scrollTop = Math.max(0, act.offsetTop - pn.clientHeight / 2); }
    // 最近打开（localStorage，最多 6 条，不含当前页）
    var rec = $('#sbRecent'); if (!rec) return;
    var KEY = 'of_admin_recent', list = [];
    try { list = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { list = []; }
    var cur = location.pathname + location.search, curLabel = rec.dataset.currentLabel || ($('h1') ? $('h1').textContent.trim().slice(0, 18) : '');
    var seen = {}, out = list.filter(function (x) { if (!x || !x.h || x.h === cur || x.l === curLabel || seen[x.l]) return false; seen[x.l] = 1; return true; }).slice(0, 6);
    rec.innerHTML = out.map(function (x) { return '<a href="' + esc(x.h) + '"><i></i><span>' + esc(x.l) + '</span></a>'; }).join('');
    if (curLabel && !/login|logout/.test(cur)) {
      list = [{ h: cur, l: curLabel }].concat(list.filter(function (x) { return x && x.h !== cur; })).slice(0, 8);
      try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
    }
  })();

  // <details class="ae-more"> 之类的下拉菜单：点外面关闭
  document.addEventListener('click', function (e) { $$('details[open].ae-more, details[open].of-menu').forEach(function (d) { if (!d.contains(e.target)) d.open = false; }); });

  /* ── 2. 对话框 + toast ── */
  var dlg = null, dlgResolve = null;
  function ensureDialog() {
    if (dlg) return dlg;
    dlg = document.createElement('div');
    dlg.className = 'of-dialog'; dlg.setAttribute('role', 'alertdialog'); dlg.setAttribute('aria-modal', 'true');
    dlg.innerHTML = '<div class="dlg"><h3><span class="ic"></span><span class="dlg-title"></span></h3><p class="dlg-msg"></p><div class="dlg-actions"><button type="button" class="dlg-btn" data-r="0">取消</button><button type="button" class="dlg-btn primary" data-r="1">确认</button></div></div>';
    document.body.appendChild(dlg);
    dlg.addEventListener('click', function (e) { var b = e.target.closest('[data-r]'); if (b) done(b.dataset.r === '1'); else if (e.target === dlg) done(false); });
    dlg.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); done(false); } });
    return dlg;
  }
  var lastFocus = null;
  function done(v) { if (!dlg) return; dlg.classList.remove('open'); var r = dlgResolve; dlgResolve = null; if (lastFocus && lastFocus.focus) lastFocus.focus(); if (r) r(v); }
  window.ofConfirm = function (opts) {
    opts = typeof opts === 'string' ? { message: opts } : (opts || {});
    var d = ensureDialog();
    var danger = opts.danger != null ? !!opts.danger : DANGER_RE.test((opts.title || '') + (opts.message || '') + (opts.okText || ''));
    d.classList.toggle('danger', danger);
    $('.ic', d).innerHTML = SVG(danger ? '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-14"/>' : '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 12v4"/>');
    $('.dlg-title', d).textContent = opts.title || (danger ? '确认这个操作？' : '请确认');
    $('.dlg-msg', d).textContent = opts.message || '';
    $('.dlg-btn.primary', d).textContent = opts.okText || (danger ? '确认执行' : '确认');
    $('.dlg-btn[data-r="0"]', d).textContent = opts.cancelText || '取消';
    lastFocus = document.activeElement;
    return new Promise(function (res) {
      dlgResolve = res; d.classList.add('open');
      setTimeout(function () { $('.dlg-btn[data-r="0"]', d).focus(); }, 20);
    });
  };
  // 从 confirm 文案里抠出动作名做标题：「确认删除该文章？」→ 标题「删除该文章」
  function titleFrom(msg, el) {
    var m = (msg || '').replace(/[?？!！。]+$/, '').replace(/^(确认|确定|是否|真的|要)+/, '').trim();
    if (m.length > 0 && m.length <= 16) return m;
    var t = el && (el.getAttribute('title') || el.getAttribute('aria-label') || (el.textContent || '').trim());
    return t && t.length <= 12 ? t : '';
  }
  // [data-confirm] 链接 / 按钮
  document.addEventListener('click', function (e) {
    var el = e.target.closest('a[data-confirm],button[data-confirm],input[type=submit][data-confirm]');
    if (!el || el.dataset.ofOk === '1') return;
    // 元素自带的 onclick（前置校验 / stopPropagation）先跑；返回 false 就不弹框
    if (typeof el.onclick === 'function') { var pre = el.onclick.call(el, e); if (pre === false) { e.preventDefault(); e.stopImmediatePropagation(); return; } }
    e.preventDefault(); e.stopImmediatePropagation();
    var msg = el.dataset.confirm;
    var isDanger = DANGER_RE.test(msg + ' ' + (el.getAttribute('href') || '') + ' ' + (el.name || '') + ' ' + el.className);
    ofConfirm({ title: titleFrom(msg, el), message: msg, danger: isDanger, okText: isDanger ? '确认执行' : '确认' }).then(function (ok) {
      if (!ok) return;
      el.dataset.ofOk = '1';
      if (el.tagName === 'A') { if (el.target === '_blank') window.open(el.href); else location.href = el.href; }
      else { var f = el.form; if (f) { if (el.name && el.value !== undefined) { var h = document.createElement('input'); h.type = 'hidden'; h.name = el.name; h.value = el.value; f.appendChild(h); } (f.requestSubmit ? f.requestSubmit() : f.submit()); } else el.dispatchEvent(new CustomEvent('of:confirmed', { bubbles: true })); }
      setTimeout(function () { delete el.dataset.ofOk; }, 1500);
    });
  }, true);
  // form[data-confirm]
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.matches || !f.matches('form[data-confirm]') || f.dataset.ofOk === '1') return;
    e.preventDefault(); e.stopImmediatePropagation();
    var msg = f.dataset.confirm, isDanger = DANGER_RE.test(msg + ' ' + (f.getAttribute('action') || ''));
    ofConfirm({ title: titleFrom(msg, null), message: msg, danger: isDanger, okText: isDanger ? '确认执行' : '确认' }).then(function (ok) {
      if (!ok) return; f.dataset.ofOk = '1'; (f.requestSubmit ? f.requestSubmit() : f.submit()); setTimeout(function () { delete f.dataset.ofOk; }, 1500);
    });
  }, true);
  // alert 替代：语义猜类型（失败 / 错误 → error；成功 / 已 → success）
  window.ofAlert = function (msg, type) {
    msg = String(msg == null ? '' : msg).replace(/^[\s✅❌⚠️🎉✓✗]+/, '');
    if (!type) type = /失败|错误|异常|无法|不能|不存在|无效|超时|拒绝|请(先|输入|选择|填写)/.test(msg) ? 'error' : (/成功|已|完成|✓/.test(msg) ? 'success' : 'info');
    if (window.fcToast) window.fcToast(msg, type); else window.alert(msg);
  };

  /* ── 3. 表单：脏检测 + 离开提示 + 粘性保存条 ── */
  (function forms() {
    var forms = $$('form').filter(function (f) {
      var m = (f.getAttribute('method') || 'get').toLowerCase();
      if (m !== 'post' || f.hasAttribute('data-no-guard')) return false;
      // 包着表格的批量操作 / 列表表单不算「编辑表单」：勾选不应触发离开提示
      if (f.querySelector('table')) return false;
      var fields = $$('input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=search]),select,textarea', f);
      var editable = fields.filter(function (x) { return !(x.type === 'checkbox' || x.type === 'radio'); });
      return fields.length >= 2 && editable.length >= 1;
    });
    if (!forms.length) return;
    var dirty = new Set(), submitting = false;
    forms.forEach(function (f) {
      f.addEventListener('input', function () { dirty.add(f); mark(); });
      f.addEventListener('change', function () { dirty.add(f); mark(); });
      f.addEventListener('submit', function () { submitting = true; dirty.delete(f); setTimeout(function () { submitting = false; }, 4000); });
    });
    window.addEventListener('beforeunload', function (e) { if (dirty.size && !submitting) { e.preventDefault(); e.returnValue = ''; } });
    // 粘性保存条：挑最长且有提交按钮、底部在首屏以下的那张表单
    var target = null, submitBtn = null;
    forms.forEach(function (f) {
      if (f.hasAttribute('data-no-savebar')) return;   // 页面自己有常驻保存按钮
      var btn = $('button[type=submit],input[type=submit],button:not([type])', f); if (!btn) return;
      var r = f.getBoundingClientRect(); if (r.height < window.innerHeight * 0.9) return;
      if (!target || r.height > target.getBoundingClientRect().height) { target = f; submitBtn = btn; }
    });
    var bar = null;
    if (target) {
      bar = document.createElement('div'); bar.className = 'of-savebar';
      var label = (submitBtn.value || submitBtn.textContent || '保存').trim().slice(0, 12);
      bar.innerHTML = '<div class="sv-msg"><i></i><span class="sv-txt">' + esc(label) + '</span></div><div class="sv-actions"><button type="button" class="sv-btn" data-act="top">回到顶部</button><button type="button" class="sv-btn primary" data-act="save">' + esc(label) + '</button></div>';
      document.body.appendChild(bar); document.body.classList.add('has-savebar');
      bar.addEventListener('click', function (e) {
        var a = e.target.closest('[data-act]'); if (!a) return;
        if (a.dataset.act === 'top') window.scrollTo({ top: 0, behavior: 'smooth' });
        else submitBtn.click();
      });
      // 原按钮在视口里时藏掉条，避免两个保存按钮同时可见
      var io = new IntersectionObserver(function (en) { bar.classList.toggle('show', !en[0].isIntersecting); }, { threshold: 0.1 });
      io.observe(submitBtn);
    }
    function mark() {
      if (!bar) return;
      var d = dirty.has(target);
      $('.sv-txt', bar).textContent = d ? '有未保存的修改' : (submitBtn.value || submitBtn.textContent || '保存').trim().slice(0, 12);
      $('.sv-msg i', bar).style.visibility = d ? 'visible' : 'hidden';
    }
    if (bar) mark();
  })();

  /* ── 3b. 长表单分节导航：≥4 个 .card>h2 的分节且高度 > 1.6 屏，右侧粘性目录 + 滚动高亮 ── */
  (function secnav() {
    var main = $('.main'); if (!main || document.body.classList.contains('zen-mode')) return;
    var forms = $$('form[method=post]', main).filter(function (f) { return !f.hasAttribute('data-no-secnav'); });
    var target = null, secs = [];
    forms.forEach(function (f) {
      var cards = $$('.card', f).filter(function (c) { var h = c.querySelector(':scope > h2, :scope > h3'); return h && h.textContent.trim(); });
      if (cards.length >= 4 && f.getBoundingClientRect().height > window.innerHeight * 1.6 && (!target || cards.length > secs.length)) { target = f; secs = cards; }
    });
    if (!target) return;
    var wrap = document.createElement('div'); wrap.className = 'of-secnav-body';
    while (main.firstChild) wrap.appendChild(main.firstChild);
    main.appendChild(wrap);
    var nav = document.createElement('nav'); nav.className = 'of-secnav'; nav.setAttribute('aria-label', '本页分节');
    var h = '<div class="sn-h">本页</div>';
    secs.forEach(function (c, i) {
      if (!c.id) c.id = 'sec-' + (i + 1);
      var hd = c.querySelector(':scope > h2, :scope > h3'), t = '';
      hd.childNodes.forEach(function (n) { if (n.nodeType === 3) t += n.nodeValue; else if (n.nodeType === 1 && !n.classList.contains('hint') && n.tagName !== 'SMALL') t += n.textContent; });
      t = t.replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, '').trim();
      h += '<a href="#' + c.id + '" data-i="' + i + '">' + esc(t) + '</a>';
    });
    nav.innerHTML = h; main.appendChild(nav); document.body.classList.add('has-secnav');
    var links = $$('a', nav);
    nav.addEventListener('click', function (e) { var a = e.target.closest('a'); if (!a) return; e.preventDefault(); var el = document.getElementById(a.getAttribute('href').slice(1)); window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 92, behavior: 'smooth' }); history.replaceState(null, '', a.getAttribute('href')); });
    var cur = -1;
    function spy() {
      var y = 120, best = 0;
      secs.forEach(function (c, i) { if (c.getBoundingClientRect().top <= y) best = i; });
      if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) best = secs.length - 1;
      if (best !== cur) { cur = best; links.forEach(function (a, i) { a.classList.toggle('on', i === best); }); }
    }
    window.addEventListener('scroll', spy, { passive: true }); spy();
  })();

  /* ── 3c. 页内 tab：<div class="of-tabbed"> 的直接子元素带 data-tab="标题" 即成一页一 tab；hash / sessionStorage 记忆 ── */
  (function tabbed() {
    $$('.of-tabbed').forEach(function (box, bi) {
      var panes = $$(':scope > [data-tab]', box); if (panes.length < 2) return;
      var key = 'of_tab:' + location.pathname + '#' + bi, bar = document.createElement('div'); bar.className = 'tabs of-tabbar'; bar.setAttribute('role', 'tablist');
      var want = (location.hash || '').replace('#tab-', ''); try { if (!want) want = sessionStorage.getItem(key) || ''; } catch (e) {}
      panes.forEach(function (p, i) {
        var id = p.dataset.tabId || ('t' + (bi + 1) + '-' + (i + 1)); p.dataset.tabId = id;
        var a = document.createElement('a'); a.href = '#tab-' + id; a.textContent = p.dataset.tab; a.setAttribute('role', 'tab'); a.dataset.for = id; bar.appendChild(a);
      });
      box.parentNode.insertBefore(bar, box);
      function show(id) {
        if (!panes.some(function (p) { return p.dataset.tabId === id; })) id = panes[0].dataset.tabId;
        panes.forEach(function (p) { p.hidden = p.dataset.tabId !== id; });
        $$('a', bar).forEach(function (a) { a.classList.toggle('active', a.dataset.for === id); a.setAttribute('aria-selected', a.dataset.for === id ? 'true' : 'false'); });
        try { sessionStorage.setItem(key, id); } catch (e) {}
      }
      bar.addEventListener('click', function (e) { var a = e.target.closest('a'); if (!a) return; e.preventDefault(); show(a.dataset.for); history.replaceState(null, '', '#tab-' + a.dataset.for); });
      // 提交过表单的那个 pane（有 msg / 错误提示）优先；否则记忆 / 首个
      var flagged = panes.find(function (p) { return p.querySelector('.msg, .of-empty.err, [data-tab-active]'); });
      show(flagged ? flagged.dataset.tabId : want);
    });
  })();

  /* ── 4. 表格：筛选 / 计数 / 排序 / 分页（≥ 12 行才启用；data-static 跳过）── */
  (function tables() {
    var ICON_SEARCH = 'url("data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23888" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg>') + '")';
    $$('table').forEach(function (t) {
      if (t.hasAttribute('data-static') || t.closest('.no-of-table') || t.closest('.of-dialog')) return;
      var body = t.tBodies[0]; if (!body) return;
      var rows = $$('tr', body).filter(function (r) { return r.parentNode === body; });
      if (rows.length < 12) return;
      if (t.parentElement && $('.of-pager,.pager,.pagination', t.parentElement)) return;
      t.setAttribute('data-of-table', '1');
      var size = 20, page = 1, q = '', sortCol = -1, sortDir = '';
      var tb = document.createElement('div'); tb.className = 'of-tbl';
      tb.innerHTML = '<input class="tb-q" type="search" placeholder="筛选本表…" aria-label="筛选表格"><span class="tb-n"></span>';
      $('.tb-q', tb).style.backgroundImage = ICON_SEARCH;
      t.parentNode.insertBefore(tb, t);
      var pg = document.createElement('div'); pg.className = 'of-pager'; t.parentNode.insertBefore(pg, t.nextSibling);
      var ths = $$('thead th', t);
      ths.forEach(function (th, i) {
        var txt = th.textContent.trim(); if (!txt || /操作|action/i.test(txt) || th.querySelector('input')) return;
        th.setAttribute('data-sort', ''); th.addEventListener('click', function () { if (sortCol === i) sortDir = sortDir === 'asc' ? 'desc' : 'asc'; else { sortCol = i; sortDir = 'asc'; } ths.forEach(function (x) { if (x.hasAttribute('data-sort')) x.setAttribute('data-sort', ''); }); th.setAttribute('data-sort', sortDir); render(); });
      });
      function cellVal(r, i) { var c = r.cells[i]; return c ? c.textContent.trim() : ''; }
      function render() {
        var list = rows.filter(function (r) { return !q || r.textContent.toLowerCase().indexOf(q) > -1; });
        if (sortCol >= 0) list.sort(function (a, b) { var x = cellVal(a, sortCol), y = cellVal(b, sortCol), nx = parseFloat(x.replace(/[^\d.-]/g, '')), ny = parseFloat(y.replace(/[^\d.-]/g, '')); var c = (!isNaN(nx) && !isNaN(ny) && /\d/.test(x) && /\d/.test(y)) ? nx - ny : x.localeCompare(y, 'zh'); return sortDir === 'asc' ? c : -c; });
        var pages = Math.max(1, Math.ceil(list.length / size)); if (page > pages) page = pages;
        rows.forEach(function (r) { r.classList.add('of-hide'); });
        var slice = list.slice((page - 1) * size, page * size);
        slice.forEach(function (r) { r.classList.remove('of-hide'); body.appendChild(r); });
        $('.tb-n', tb).textContent = q ? ('匹配 ' + list.length + ' / ' + rows.length) : ('共 ' + rows.length + ' 行');
        var h = '';
        if (pages > 1) {
          h += '<button type="button" data-p="' + (page - 1) + '"' + (page === 1 ? ' disabled' : '') + '>‹</button>';
          for (var i = 1; i <= pages; i++) { if (pages > 9 && Math.abs(i - page) > 2 && i !== 1 && i !== pages) { if (h.slice(-6) !== '<span>…</span>'.slice(-6)) h += '<span>…</span>'; continue; } h += '<button type="button" data-p="' + i + '" class="' + (i === page ? 'on' : '') + '">' + i + '</button>'; }
          h += '<button type="button" data-p="' + (page + 1) + '"' + (page === pages ? ' disabled' : '') + '>›</button>';
        }
        h += '<select aria-label="每页行数"><option' + (size === 20 ? ' selected' : '') + '>20</option><option' + (size === 50 ? ' selected' : '') + '>50</option><option' + (size === 100 ? ' selected' : '') + '>100</option></select><span>行 / 页</span>';
        pg.innerHTML = h;
        var emp = t.nextSibling && t.nextSibling.classList && t.nextSibling.classList.contains('of-empty') ? t.nextSibling : null;
        if (!list.length && !emp) { emp = document.createElement('div'); emp.className = 'of-empty'; emp.textContent = '没有匹配「' + q + '」的行'; t.parentNode.insertBefore(emp, pg); }
        else if (list.length && emp) emp.remove();
      }
      $('.tb-q', tb).addEventListener('input', function () { q = this.value.trim().toLowerCase(); page = 1; render(); });
      pg.addEventListener('click', function (e) { var b = e.target.closest('button[data-p]'); if (!b || b.disabled) return; page = +b.dataset.p; render(); });
      pg.addEventListener('change', function (e) { if (e.target.tagName === 'SELECT') { size = +e.target.value; page = 1; render(); } });
      render();
    });
  })();

  /* ── 5. emoji → 线框 svg（只替换按钮 / 链接 / 标题 / 标签 / 侧栏里的已知图标）── */
  (function icons() {
    var P = {
      '🗑': '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-14"/>',
      '✏️': '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>', '✏': '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>',
      '👁': '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>', '👁️': '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
      '📤': '<path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>', '📥': '<path d="M12 4v12m0 0-4-4m4 4 4-4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
      '📋': '<rect x="8" y="3" width="8" height="4" rx="1"/><path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2M9 12h6M9 16h6"/>',
      '📌': '<path d="M12 17v5M8 7h8l-1 6h3l-1 4H7l-1-4h3L8 7Z"/><path d="M9 3h6v4H9z"/>', '📦': '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
      '📣': '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6M18 6a7 7 0 0 1 0 12"/>', '💬': '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/>',
      '🌐': '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>', '🧩': '<path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/>',
      '🔍': '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/>', '⚡': '<path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/>', '📊': '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>',
      '🎨': '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/>',
      '🔥': '<path d="M12 22c4 0 7-3 7-7 0-3-2-5-3-7-1 2-2 3-3 3 0-3-1-6-3-8-1 3-5 6-5 12 0 4 3 7 7 7Z"/>', '🚨': '<path d="M4 20h16M6 20v-6a6 6 0 0 1 12 0v6M12 3v2M4.5 8 6 9.5M19.5 8 18 9.5"/>',
      '🛡': '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>', '🛡️': '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>', '🎬': '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/>',
      '📮': '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>', '🎯': '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/>',
      '💰': '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5c0-1 1-1.5 2.5-1.5s2.5.6 2.5 1.5-1 1.5-2.5 1.5-2.5.6-2.5 1.5 1 1.5 2.5 1.5 2.5-.5 2.5-1.5"/>', '🏢': '<path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10"/>',
      '🧠': '<path d="M9 3a3 3 0 0 0-3 3 3 3 0 0 0-2 3c0 1 .5 2 1 2.5A3 3 0 0 0 6 16a3 3 0 0 0 3 3h1V3H9ZM15 3a3 3 0 0 1 3 3 3 3 0 0 1 2 3c0 1-.5 2-1 2.5A3 3 0 0 1 18 16a3 3 0 0 1-3 3h-1V3h1Z"/>', '🛤': '<path d="M6 3v18M18 3v18M6 7h12M6 12h12M6 17h12"/>',
      '📚': '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/>', '🧑‍💻': '<path d="m8 9-3 3 3 3M13 15h4"/><path d="M7 4h13a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/>',
      '🎟': '<path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v3ZM13 5v14"/>', '↩️': '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-2"/>', '↩': '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-2"/>',
      '⬅': '<path d="M19 12H5m6-6-6 6 6 6"/>', '➡': '<path d="M5 12h14m-6-6 6 6-6 6"/>', '🧭': '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
      '✅': '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>', '❌': '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>', '⚠️': '<path d="M12 3 2 21h20L12 3Z"/><path d="M12 10v5M12 18h.01"/>', '⚠': '<path d="M12 3 2 21h20L12 3Z"/><path d="M12 10v5M12 18h.01"/>',
      '☀️': '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>', '🌙': '<path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.4 5.4 0 0 1-7.54-7.54C12.92 3.04 12.46 3 12 3Z"/>',
      '🔔': '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>', '🔑': '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.2-8.2M15 7l3 3M18 4l2 2"/>', '🎉': '<path d="m4 20 8-8M9 4l.5 3.5L13 8l-3.5.5L9 12l-.5-3.5L5 8l3.5-.5L9 4ZM16 6l.4 2.6L19 9l-2.6.4L16 12l-.4-2.6L13 9l2.6-.4L16 6Z"/>',
      '✨': '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>', '🚀': '<path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2-.7-3 0Z"/><path d="M12 15l-3-3c2-5.5 5-9 9-9s3 6-1 11l-5 1Z"/>',
      '🤖': '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01M9 17h6"/>', '📝': '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>', '📄': '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/>',
      '⚙️': '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>', '⚙': '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
      '🔗': '<path d="M10 14a3.5 3.5 0 0 0 5 0l3-3a3.5 3.5 0 0 0-5-5l-1 1"/><path d="M14 10a3.5 3.5 0 0 0-5 0l-3 3a3.5 3.5 0 0 0 5 5l1-1"/>', '📈': '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>', '👤': '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>', '👥': '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>',
      '🎓': '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/>', '💡': '<path d="M12 3a6 6 0 0 0-4 10.5V16h8v-2.5A6 6 0 0 0 12 3ZM10 20h4"/>', '🕐': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>', '📅': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>'
    };
    var keys = Object.keys(P).sort(function (a, b) { return b.length - a.length; });
    var re = new RegExp('(' + keys.map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'g');
    var reTest = new RegExp(re.source);
    var scope = $$('.main, #chrome, .sidebar, .of-savebar');
    var sel = 'button, a, th, h1, h2, h3, h4, label, .tag, .badge, .pill, .st, .kpi .k-label, .v-sub, summary, .tab, .subtab';
    scope.forEach(function (root) {
      $$(sel, root).forEach(function (el) {
        if (el.closest('pre,code,textarea,script,style') || el.dataset.ofIcons) return;
        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null), nodes = [], n;
        while ((n = walker.nextNode())) if (reTest.test(n.nodeValue)) nodes.push(n);
        nodes.forEach(function (tn) {
          var frag = document.createDocumentFragment(), parts = tn.nodeValue.split(re);
          parts.forEach(function (pt) {
            if (P[pt]) { var s = document.createElement('span'); s.className = 'of-ic'; s.setAttribute('aria-hidden', 'true'); s.innerHTML = SVG(P[pt]); frag.appendChild(s); }
            else if (pt) frag.appendChild(document.createTextNode(pt));
          });
          tn.parentNode.replaceChild(frag, tn);
        });
        el.dataset.ofIcons = '1';
      });
    });
  })();
})();
