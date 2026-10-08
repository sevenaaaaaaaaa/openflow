/**
 * uploads-r2 — Cloudflare Worker：/uploads/* 直出 R2（键映射 assets/uploads/<X>）
 *
 * 背景：vhost 层有图片灭杀规则（RewriteRule .(jpg|...) /404.html），源站从不吐图片；
 *       图片统一经 CF→R2 出。老内容烙的是 /uploads/<X>，桶里的键是 assets/uploads/<X>
 *       （deploy/uploads-r2-mirror.php 回填口径）。这个 Worker 补上映射。
 *
 * 路由：nownexts.com/uploads/*  →  uploads-r2（桶 nownexts-static）
 * 支持 HTTP Range(206)：uploads 里也有 mp4/webm 视频，浏览器拖进度条需要。
 *
 * 部署：CF_TOKEN=xxx python3 deploy/deploy-uploads-r2.py
 */
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const rest = url.pathname.replace(/^\/uploads\//, '');
    if (!rest || rest.includes('..')) {
      return new Response('Not Found', { status: 404 });
    }
    // 桶里的键带 assets/ 前缀（与站点资源同一映射）
    const key = 'assets/uploads/' + rest;

    const range = request.headers.get('Range');
    let object = null;
    try {
      object = await env.MEDIA.get(key, { range: range ? parseRange(range) : undefined });
    } catch (err) {
      return new Response('Storage error: ' + err.message, { status: 500 });
    }

    if (!object) {
      return new Response('Not Found', { status: 404 });
    }

    const headers = new Headers();
    object.writeHttpMetadata(headers);
    headers.set('etag', object.httpEtag);
    headers.set('accept-ranges', 'bytes');
    headers.set('access-control-allow-origin', '*');
    // 与 sync-r2.py 一致的缓存策略（上传镜像时已带 cache-control，元数据会带过来；
    // 这里再兜底一次，防止对象缺该元数据时每次都回 Worker）
    headers.set('cache-control', 'public, max-age=2592000, immutable');

    return new Response(object.body, { status: range ? 206 : 200, headers });
  },
};

function parseRange(header) {
  const m = /^bytes=(\d+)-(\d*)$/.exec(header.trim());
  if (!m) return undefined;
  return { offset: parseInt(m[1], 10), length: m[2] ? parseInt(m[2], 10) - parseInt(m[1], 10) + 1 : undefined };
}
