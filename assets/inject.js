/**
 * OpenFlow 全局脚本注入器
 * 用法：在页面 <head> 引入 <script src="/assets/inject.js" data-site-inject></script>
 * 功能：1. 从后端拉取启用的埋点脚本并注入 2. 应用 AB 测试分流
 */
(function () {
  var SCRIPT_URL = '/api/scripts.php?path=' + encodeURIComponent(location.pathname);
  var SCRIPT_ID = 'fc-site-injected';

  function injectScript(s) {
    if (s.type === 'url') {
      // 外部 URL 脚本
      if (document.querySelector('script[src="' + s.content + '"]')) return;
      var el = document.createElement('script');
      el.src = s.content;
      el.async = true;
      (s.position === 'body' ? document.body : document.head).appendChild(el);
    } else {
      // 内联脚本
      var wrapper = document.createElement('div');
      wrapper.innerHTML = s.content;
      var nodes = Array.prototype.slice.call(wrapper.childNodes);
      var frag = document.createDocumentFragment();
      nodes.forEach(function (n) { frag.appendChild(n); });
      if (s.position === 'body') document.body.appendChild(frag);
      else document.head.appendChild(frag);
    }
  }

  function applyAB(t) {
    // CSS 注入
    if (t.css) {
      var style = document.createElement('style');
      style.textContent = t.css;
      style.setAttribute('data-ab', t.id);
      document.head.appendChild(style);
    }
    // JS 执行（B 变体时）
    if (t.js && t.variant === 'B') {
      try { (new Function(t.js))(); } catch (e) { console.error('AB JS error:', e); }
    }
    // 重定向（B 变体指定 URL 时）
    if (t.redirect && t.variant === 'B' && t.redirect !== location.href && t.redirect !== location.pathname) {
      location.href = t.redirect;
    }
    // 标记 body 变体，方便 CSS 选择器写 [data-variant="B"]
    if (document.body) document.body.setAttribute('data-variant', t.variant);

    // 自动上报曝光（延迟确保页面加载完成）
    var abId = t.id, variant = t.variant;
    setTimeout(function () {
      fcTrackAB(abId, variant, 'impression');
    }, 1500);
  }

  // ═══ A/B 事件上报全局接口 ═══
  // 页面在转化处调用：fcTrackAB(abId, variant, 'conversion', 'submit')
  // 或直接用 body 上的 data-variant + 当前测试列表
  function fcTrackAB(abId, variant, event, label) {
    try {
      var body = 'ab_id=' + encodeURIComponent(abId) + '&variant=' + encodeURIComponent(variant) + '&event=' + encodeURIComponent(event || 'impression') + '&label=' + encodeURIComponent(label || '');
      if (navigator.sendBeacon) {
        navigator.sendBeacon('/api/ab-event.php', body);
      } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/ab-event.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(body);
      }
    } catch (e) {}
  }

  // 暴露全局接口 + 当前用户的 A/B 分配（供页面 JS 判断用了哪个变体）
  window.fcTrackAB = fcTrackAB;
  window.fcABVariant = function (abId) {
    try {
      var s = JSON.parse(sessionStorage.getItem('fc_ab_' + abId) || 'null');
      return s ? s.variant : null;
    } catch (e) { return null; }
  };

  // ═══ 统一行为追踪 ═══
  // 用法：fcTrack('click', { element: 'btn', product: 'x' })
  // 可选参数 fcTrack(event, props, webhookOverride)
  function fcTrack(event, props, webhook) {
    try {
      var body = JSON.stringify({
        event: event || 'custom',
        props: props || {},
        label: (props && props.label) || '',
        page: location.pathname
      });
      if (webhook) body = JSON.stringify({ event: event, props: props || {}, label: (props && props.label) || '', page: location.pathname, webhook: webhook });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('/api/track.php', body);
      } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/track.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(body);
      }
    } catch (e) {}
  }
  window.fcTrack = fcTrack;

  // ═══ 转化组件渲染（top_bar / bottom_cta / popup）═══
  function esc(s){ var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }
  function buildPopupForm(form) {
    var f = document.createElement('form');
    f.style.cssText = 'display:grid;gap:10px;margin-top:6px';
    (form.fields || []).forEach(function(fld) {
      var label = document.createElement('label');
      label.style.cssText = 'font-size:12px;font-weight:600;display:grid;gap:4px;text-align:left';
      label.textContent = fld.label + (fld.required ? ' *' : '');
      var input;
      if (fld.type === 'textarea') { input = document.createElement('textarea'); input.rows = 2; }
      else if (fld.type === 'select' && fld.options) {
        input = document.createElement('select');
        (fld.options || '').split(',').forEach(function(op) {
          var o = document.createElement('option'); o.value = op.trim(); o.textContent = op.trim(); input.appendChild(o);
        });
      } else {
        input = document.createElement('input');
        input.type = (fld.type === 'email') ? 'email' : 'text';
      }
      input.name = fld.key;
      input.placeholder = fld.placeholder || '';
      if (fld.required) input.required = true;
      label.appendChild(input);
      f.appendChild(label);
    });
    var btn = document.createElement('button');
    btn.type = 'submit'; btn.textContent = '提交';
    btn.style.cssText = 'background:#1e1e1e;color:#ddff0e;border:none;border-radius:999px;padding:11px;font-weight:700;cursor:pointer';
    f.appendChild(btn);
    f.addEventListener('submit', function(e) {
      e.preventDefault();
      var fd = new FormData(f);
      fd.append('form_slug', form.slug);
      fetch('/api/form-submit.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d) {
          fcTrack('form_submit', { form: form.slug });
          btn.textContent = d.ok ? '✅ 已提交' : '⚠️ ' + (d.error || '失败');
          if (d.ok) setTimeout(function() { var p = document.getElementById('fc-popup'); if (p) p.remove(); }, 1200);
        }).catch(function(){ btn.textContent = '网络异常'; });
    });
    return f;
  }
  function renderConversion(cfg) {
    if (!cfg || !cfg.ok) return;
    var conv = cfg.conversion || {};
    // 1) 顶部通知条
    var tb = conv.top_bar || {};
    if (tb.enabled && tb.text) {
      var bar = document.createElement('div');
      bar.id = 'fc-top-bar';
      bar.setAttribute('data-component', 'top_bar');
      bar.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99998;padding:9px 42px 9px 16px;text-align:center;font-size:13.5px;font-weight:600;background:' + (tb.bg_color || '#1e1e1e') + ';color:' + (tb.text_color || '#fff') + ';';
      var link = document.createElement('a');
      link.href = tb.link || '#';
      link.style.cssText = 'color:inherit;text-decoration:none;display:inline-block';
      link.textContent = tb.text;
      bar.appendChild(link);
      if (tb.dismissible) {
        var x = document.createElement('span');
        x.textContent = '✕';
        x.style.cssText = 'position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;opacity:.7';
        x.addEventListener('click', function(e){ e.preventDefault(); bar.remove(); });
        bar.appendChild(x);
      }
      document.body.appendChild(bar);
      fcTrack('component_view', { label: 'top_bar' });
    }
    // 2) 底部 CTA
    var bc = conv.bottom_cta || {};
    if (bc.enabled && bc.title) {
      var cta = document.createElement('div');
      cta.id = 'fc-bottom-cta';
      cta.setAttribute('data-component', 'bottom_cta');
      cta.style.cssText = 'background:' + (bc.bg_color || '#1e1e1e') + ';color:' + (bc.text_color || '#fff') + ';padding:40px 20px;text-align:center;margin-top:40px';
      cta.innerHTML = '<div style="max-width:720px;margin:0 auto"><h2 style="font-size:24px;font-weight:800;margin-bottom:8px">' + esc(bc.title) + '</h2><p style="opacity:.75;margin-bottom:18px">' + esc(bc.description || '') + '</p><a href="' + esc(bc.button_url || '#') + '" style="display:inline-block;background:#ddff0e;color:#1e1e1e;font-weight:700;padding:12px 32px;border-radius:999px;text-decoration:none">' + esc(bc.button_text || '了解更多') + '</a></div>';
      document.body.appendChild(cta);
      fcTrack('component_view', { label: 'bottom_cta' });
    }
    // 3) 弹窗
    var pp = conv.popup || {};
    if (pp.enabled) {
      var path = location.pathname;
      var inScope = true;
      if (pp.page_scope === 'home') inScope = (path === '/' || path === '/index.html');
      else if (pp.page_scope === 'article') inScope = path.indexOf('/article/') === 0;
      else if (pp.page_scope === 'specific' && pp.page_paths) {
        var paths = (pp.page_paths || '').split('\n').map(function(s){return s.trim()}).filter(Boolean);
        inScope = paths.some(function(p){ return path === p || path.indexOf(p) === 0; });
      }
      if (inScope) {
        var trigger = pp.trigger || 'time';
        var delay = Math.max(0, (parseInt(pp.trigger_delay || 10, 10) || 10) * 1000);
        var freq = pp.frequency || 'once_per_session';
        var fired = false;
        function showPopup() {
          if (fired) return; fired = true;
          if (freq === 'once' && sessionStorage.getItem('fc_popup_shown')) return;
          if (freq === 'once_per_session') { try { sessionStorage.setItem('fc_popup_shown', '1'); } catch (e) {} }
          if (freq === 'once_per_day') {
            var today = new Date().toDateString();
            try { if (localStorage.getItem('fc_popup_day') === today) return; localStorage.setItem('fc_popup_day', today); } catch (e) {}
          }
          var ov = document.createElement('div');
          ov.id = 'fc-popup';
          ov.setAttribute('data-component', 'popup');
          ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center';
          var box = document.createElement('div');
          box.style.cssText = 'background:' + (pp.bg_color || '#fff') + ';border-radius:18px;padding:28px;width:90%;max-width:' + (pp.width || '500px') + ';position:relative;max-height:88vh;overflow-y:auto;color:#1e1e1e';
          var close = document.createElement('button');
          close.textContent = '✕';
          close.style.cssText = 'position:absolute;right:12px;top:10px;border:none;background:none;font-size:18px;cursor:pointer;opacity:.6;color:#1e1e1e';
          close.addEventListener('click', function(){ ov.remove(); });
          box.appendChild(close);
          if (pp.image) box.insertAdjacentHTML('beforeend', '<img src="' + esc(pp.image) + '" style="width:100%;border-radius:12px;margin-bottom:14px">');
          if (pp.title) box.insertAdjacentHTML('beforeend', '<h3 style="font-size:20px;font-weight:800;margin-bottom:8px">' + esc(pp.title) + '</h3>');
          if (pp.content) box.insertAdjacentHTML('beforeend', '<div style="margin-bottom:14px;font-size:14px;line-height:1.7">' + pp.content + '</div>');
          if (pp.form_slug) {
            var forms = cfg.forms || [];
            for (var i = 0; i < forms.length; i++) if (forms[i].slug === pp.form_slug) { box.appendChild(buildPopupForm(forms[i])); break; }
          }
          ov.appendChild(box);
          ov.addEventListener('click', function(e){ if (e.target === ov) ov.remove(); });
          document.body.appendChild(ov);
          fcTrack('component_view', { label: 'popup', form: pp.form_slug || '' });
        }
        if (trigger === 'time') { setTimeout(showPopup, delay); }
        else if (trigger === 'scroll') {
          window.addEventListener('scroll', function() { if (fired) return; if (window.innerHeight + window.scrollY > document.body.scrollHeight * 0.6) showPopup(); }, { passive: true });
        } else if (trigger === 'exit') {
          document.addEventListener('mouseleave', function(e) { if (e.clientY < 10) showPopup(); });
        }
      }
    }
  }
  function loadConversion() {
    try {
      fetch('/api/conversion.php', { cache: 'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d && d.ok) renderConversion(d); })
        .catch(function(){});
    } catch (e) {}
  }

  // ═══ 页面 SEO 注入（完整：关键词/Canonical/OG/结构化数据/favicon）═══
  function loadPageSeo() {
    var pageMap = {
      '/': 'index', '/index.html': 'index',
      '/about': 'about', '/about.html': 'about',
      '/capability': 'capability', '/capability.html': 'capability',
      '/courses': 'courses', '/courses.html': 'courses',
      '/product': 'product', '/product.html': 'product',
      '/tools': 'tools', '/tools.php': 'tools',
      '/docs': 'docs', '/docs.php': 'docs',
      '/marketplace': 'marketplace', '/marketplace.php': 'marketplace',
      '/community': 'community', '/community.php': 'community',
      '/academy': 'academy', '/academy.php': 'academy'
    };
    var page = pageMap[location.pathname];
    if (!page) return;
    try {
      fetch('/api/seo.php?page=' + page, { cache: 'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(d) {
          if (!d || !d.ok || !d.head) return;
          var head = document.head;
          // 移除旧的由本脚本注入的 SEO 标签
          head.querySelectorAll('[data-of-seo]').forEach(function(el){ el.remove(); });
          // 注入新的（保留原 title/description，仅补全缺失项）
          var wrap = document.createElement('div');
          wrap.innerHTML = d.head;
          wrap.querySelectorAll('*').forEach(function(el) {
            if (el.getAttribute('data-of-seo') === null) el.setAttribute('data-of-seo', '1');
            if (el.tagName === 'META' && el.getAttribute('name') === 'description') {
              // 已存在 description 则替换
              var existing = head.querySelector('meta[name="description"]');
              if (existing) { existing.setAttribute('content', el.getAttribute('content')); return; }
            }
            if (el.tagName === 'LINK' && el.getAttribute('rel') === 'canonical') {
              var existing = head.querySelector('link[rel="canonical"]');
              if (existing) { existing.setAttribute('href', el.getAttribute('href')); return; }
            }
            head.appendChild(el);
          });
        }).catch(function(){});
    } catch (e) {}
  }

  // ═══ 站点结构注入（site-builder 配置 → [data-site-nav] / [data-site-footer] 容器）═══
  function loadSiteStructure() {
    var navBox = document.querySelector('[data-site-nav]');
    var footBox = document.querySelector('[data-site-footer]');
    if (!navBox && !footBox) return;
    try {
      fetch('/api/site-structure.php', { cache: 'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(d) {
          if (!d || !d.ok) return;
          if (navBox && d.nav && d.nav.length) {
            navBox.innerHTML = '';
            d.nav.forEach(function(item) {
              var a = document.createElement('a');
              a.href = item.url || '#';
              a.textContent = item.label || '';
              a.style.cssText = 'color:inherit;text-decoration:none';
              navBox.appendChild(a);
            });
          }
          if (footBox && d.footer && d.footer.columns && d.footer.columns.length) {
            footBox.innerHTML = '';
            d.footer.columns.forEach(function(col) {
              var div = document.createElement('div');
              var h = document.createElement('p');
              h.textContent = col.name || '';
              h.style.cssText = 'font-weight:700;margin-bottom:10px';
              div.appendChild(h);
              (col.links || []).forEach(function(l) {
                var a = document.createElement('a');
                a.href = l.url || '#';
                a.textContent = l.label || '';
                a.style.cssText = 'display:block;color:inherit;text-decoration:none;opacity:.7;margin-bottom:6px';
                div.appendChild(a);
              });
              footBox.appendChild(div);
            });
          }
        }).catch(function(){});
    } catch (e) {}
  }

  // 自动采集：页面浏览 + 全量点击捕获（按钮/链接/导航/页脚/转化组件，含动态元素）───
  try {
    fcTrack('page_view', { url: location.pathname, title: document.title });
  } catch (e) {}

  // 从元素提取标签
  function elLabel(el, maxLen) {
    maxLen = maxLen || 60;
    var s = el.getAttribute('aria-label') || el.title || (el.textContent || '').trim() || el.getAttribute('href') || el.id || el.tagName;
    return s.substring(0, maxLen);
  }

  // 全量点击分类上报（capture 阶段，事件委托覆盖动态生成的元素）
  function autoTrackClick(e) {
    var t = e.target;
    if (!t || t === document || t === window) return;
    // 显式 data-track 优先，走原有逻辑
    var tracked = t.closest('[data-track]');
    if (tracked) {
      var tr = tracked.getAttribute('data-track') || 'click';
      fcTrack(tr, { label: tracked.getAttribute('data-track-label') || elLabel(tracked), element: tracked.tagName.toLowerCase() });
      return;
    }
    var el = t.closest('button, a, input[type="submit"], input[type="button"], [role="button"], .btn, [data-component], [data-cta], [data-convert]');
    if (!el) return;
    // 转化组件区域内点击
    var comp = el.closest('[data-convert], [data-cta], [data-component]');
    if (comp) {
      fcTrack('component_click', { label: comp.getAttribute('data-cta') || comp.getAttribute('data-component') || elLabel(comp), element: comp.tagName.toLowerCase() });
      return;
    }
    var tag = el.tagName.toLowerCase();
    var inFooter = el.closest('footer');
    var inNav = el.closest('nav, header');
    var label = elLabel(el, 80);
    var eventName = 'click';
    if (tag === 'button' || tag === 'input' || el.getAttribute('role') === 'button' || el.classList.contains('btn')) {
      eventName = 'button_click';
    } else if (tag === 'a') {
      if (inFooter) eventName = 'footer_click';
      else if (inNav) eventName = 'nav_click';
      else eventName = 'link_click';
    }
    fcTrack(eventName, { label: label, element: tag, href: (el.getAttribute('href') || '').substring(0, 120) });
  }
  document.addEventListener('click', autoTrackClick, true);

  // 转化组件曝光（IntersectionObserver + MutationObserver 覆盖动态/模块化生成）
  function bindComponentViews() {
    var els = document.querySelectorAll('[data-convert], [data-cta], [data-component]');
    Array.prototype.forEach.call(els, function(el) {
      if (el.__fc_view_bound) return;
      el.__fc_view_bound = true;
      if (typeof IntersectionObserver === 'function') {
        var io = new IntersectionObserver(function(entries) {
          entries.forEach(function(en) {
            if (en.isIntersecting) {
              fcTrack('component_view', { label: en.target.getAttribute('data-cta') || en.target.getAttribute('data-component') || elLabel(en.target, 50) });
              io.unobserve(en.target);
            }
          });
        }, { threshold: 0.3 });
        io.observe(el);
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindComponentViews);
  else bindComponentViews();
  if (typeof MutationObserver === 'function') {
    var mo = new MutationObserver(bindComponentViews);
    try { mo.observe(document.body, { childList: true, subtree: true }); } catch (e) {}
  }

  function run() {
    if (document.getElementById(SCRIPT_ID)) return;
    var marker = document.createElement('span');
    marker.id = SCRIPT_ID;
    marker.style.display = 'none';
    document.head.appendChild(marker);

    try {
      fetch(SCRIPT_URL, { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            (d.scripts || []).forEach(function (s) { try { injectScript(s); } catch (e) { console.error('script inject error:', e); } });
            (d.abtests || []).forEach(function (t) {
              try {
                // 记录本次会话的 A/B 分配
                try { sessionStorage.setItem('fc_ab_' + t.id, JSON.stringify({ variant: t.variant, at: Date.now() })); } catch (e) {}
                applyAB(t);
              } catch (e) { console.error('ab apply error:', e); }
            });
          }
        })
        .catch(function () {});
    } catch (e) {}
    loadConversion();
    loadPageSeo();
    loadSiteStructure();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
