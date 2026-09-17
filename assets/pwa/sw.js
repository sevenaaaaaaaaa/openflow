/**
 * OpenFlow PWA Service Worker
 *
 * 【作用域说明｜重要】
 * 本 SW 注册于 `/assets/pwa/sw.js`，默认作用域 = `/assets/pwa/`。
 * 也就是说它**只能拦截自己目录下的请求**——原来缓存全站资产（tokens/modules/site-shell/inject）
 * 既拦不到（作用域外）又制造"改了看不到"的隐患，这里已收紧。
 *
 * 若要让后台（/xmp/*）具备离线壳能力，需要两步（涉及服务器配置，改前需对齐）：
 *   1. Apache 为 /assets/pwa/sw.js 加响应头 `Service-Worker-Allowed: /`
 *   2. 注册时指定 `{ scope: '/' }`，并把下面的 SHELL 换成 /xmp/ 壳资源
 * 在那之前，本 SW 只做两件事：PWA 安装所需的静态壳缓存 + Web Push 通知。
 */
const CACHE = 'openflow-pwa-v2';          // 版本化：升版后 activate 会清掉旧缓存
const SHELL = [
  '/assets/pwa/manifest.webmanifest',
  '/assets/pwa/icon-192.png',
  '/assets/pwa/icon-512.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE)
      .then((c) => Promise.allSettled(SHELL.map((u) => c.add(u))))  // 单个 404 不拖垮安装
      .catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // 只处理同源 GET
  if (e.request.method !== 'GET' || url.origin !== location.origin) return;
  // 只缓存自己作用域内的静态壳；其它请求（页面/API/全站资产）一律交给浏览器与 CDN 正常处理
  if (!url.pathname.startsWith('/assets/pwa/')) return;
  e.respondWith(
    caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
      if (res && res.ok) {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy));
      }
      return res;
    }))
  );
});

// ── Web Push 通知 ──
self.addEventListener('push', (e) => {
  let data = { title: 'OpenFlow', body: '有新的增长任务' };
  try { data = e.data ? e.data.json() : data; } catch (_) {}
  e.waitUntil(self.registration.showNotification(data.title || 'OpenFlow', {
    body: data.body || '',
    icon: '/assets/pwa/icon-192.png',
    badge: '/assets/pwa/icon-192.png',
    data: data.url || '/xmp/studio',
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) { if (c.url.includes('/xmp/')) return c.focus(); }
      return clients.openWindow(e.notification.data || '/xmp/studio');
    })
  );
});
