/**
 * OpenFlow Studio PWA Service Worker
 * 策略：网络优先（后台是动态数据，离线只兜底壳资源 + Studio 页面骨架）
 * Shell 资源离线缓存；API/XHR 走网络；失败回退离线壳。
 */
const CACHE = 'openflow-studio-v1';
const SHELL = [
  '/assets/pwa/manifest.webmanifest',
  '/assets/tokens.css',
  '/assets/modules.css',
  '/assets/fonts/fonts.css',
  '/assets/site-shell.js',
  '/assets/inject.js',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // 只处理同源 GET（后台 API/XHR 走网络，不做离线缓存避免数据脏）
  if (e.request.method !== 'GET' || url.origin !== location.origin) return;
  // 静态资源：缓存优先 + 后台更新
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(e.request, copy));
      return res;
    }).catch(() => caches.match('/assets/tokens.css'))));
    return;
  }
  // 页面：网络优先，失败回退壳
  e.respondWith(fetch(e.request).then((res) => {
    const copy = res.clone();
    if (res.ok && url.pathname.startsWith('/xmp/')) caches.open(CACHE).then((c) => c.put(e.request, copy));
    return res;
  }).catch(() => caches.match(e.request).then((hit) => hit || caches.match('/xmp/studio'))));
});

// Web Push 通知骨架
self.addEventListener('push', (e) => {
  let data = { title: 'OpenFlow', body: '有新的增长任务' };
  try { data = e.data ? e.data.json() : data; } catch (_) {}
  e.waitUntil(self.registration.showNotification(data.title || 'OpenFlow', {
    body: data.body || '', icon: '/assets/pwa/icon-192.png', badge: '/assets/pwa/icon-192.png',
    data: data.url || '/xmp/studio',
  }));
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) { if (c.url.includes('/xmp/')) return c.focus(); }
    return clients.openWindow(e.notification.data || '/xmp/studio');
  }));
});
