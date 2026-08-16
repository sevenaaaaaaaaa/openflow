/* OpenFlow SEO 注入器（独立版，用于静态页面）
 * 调用 /api/seo.php 获取完整 SEO head（关键词/Canonical/OG/JSON-LD/favicon）并注入
 * 用法：<script src="/assets/seo-inject.js" data-page="index"></script>
 * data-page 缺省时从 URL 推断
 */
(function () {
  if (window.OF_SEO_LOADED) return;
  window.OF_SEO_LOADED = true;

  var tag = document.currentScript;
  var page = (tag && tag.getAttribute('data-page')) || (function () {
    var map = {
      '/': 'index', '/index.html': 'index',
      '/about.html': 'about',
      '/capability.html': 'capability',
      '/courses.html': 'courses',
      '/product.html': 'product',
      '/tools.php': 'tools',
      '/docs.php': 'docs',
      '/marketplace.php': 'marketplace'
    };
    return map[location.pathname] || '';
  })();

  if (!page) return;

  try {
    fetch('/api/seo.php?page=' + page, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok || !d.head) return;
        var head = document.head;
        var wrap = document.createElement('div');
        wrap.innerHTML = d.head;
        Array.prototype.slice.call(wrap.children).forEach(function (el) {
          // 避免重复
          if (el.tagName === 'LINK' && el.getAttribute('rel') === 'canonical') {
            var ex = head.querySelector('link[rel="canonical"]');
            if (ex) { ex.setAttribute('href', el.getAttribute('href')); return; }
          }
          if (el.tagName === 'META' && el.getAttribute('name') === 'description') {
            var ex = head.querySelector('meta[name="description"]');
            if (ex) { ex.setAttribute('content', el.getAttribute('content')); return; }
          }
          if (el.tagName === 'META' && el.getAttribute('name') === 'keywords') {
            var ex = head.querySelector('meta[name="keywords"]');
            if (ex) { ex.setAttribute('content', el.getAttribute('content')); return; }
          }
          head.appendChild(el);
        });
      }).catch(function () {});
  } catch (e) {}
})();
