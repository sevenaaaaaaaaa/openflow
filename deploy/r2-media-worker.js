/**
 * r2-media — Cloudflare Worker：从 R2 桶分发视频/大文件，支持 HTTP Range(206) 分段播放。
 *
 * 路由：nownexts.com/media/*  →  r2-media（R2 桶 nownexts-static，键前缀 media/）
 *
 * 关键：R2 binding 的 object.get({ range }) 原生支持分段读取，Worker 返回 206 + Content-Range，
 *      浏览器的 <video> 才能拖动进度条。纯对象存储默认不支持 Range，必须靠这层 Worker。
 *
 * 部署：CF_TOKEN=xxx python3 deploy/deploy-r2-media.py
 *      （桶名/binding 在部署脚本里指定，此处不含任何密钥）
 */
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const path = url.pathname;

    // 丢弃查询串，取完整的 /media/xxx 路径（R2 里也存 media/ 前缀）
    const key = path.replace(/^\/+\//, '').replace(/^\//, '');

    if (!key) {
      return new Response('Not Found: media/ from r2-media worker', { status: 404 });
    }

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
    // 大文件长缓存：视频/音频一次性可缓存 24h
    headers.set('cache-control', 'public, max-age=86400');

    // 带 Range → 206 分段
    if (range && object.range) {
      headers.set('Content-Length', String(object.range.length));
      headers.set('Content-Range', `bytes ${object.range.offset}-${object.range.offset + object.range.length - 1}/${object.size}`);
      return new Response(object.body, { status: 206, headers });
    }

    // HEAD 请求：只回元信息
    if (request.method === 'HEAD') {
      headers.set('Content-Length', String(object.size));
      return new Response(null, { status: 200, headers });
    }

    // 普通 GET → 200 全量
    headers.set('Content-Length', String(object.size));
    return new Response(object.body, { status: 200, headers });
  },
};

/**
 * 解析 Range 头，转成 R2 的 range 参数 { offset, length }。
 * 支持 bytes=start- 与 bytes=start-end。暂只处理单区间。
 */
function parseRange(rangeHeader) {
  const m = /^bytes=(\d*)-(\d*)$/.exec(rangeHeader.trim());
  if (!m) return undefined;
  let start = m[1] ? parseInt(m[1], 10) : null;
  const end = m[2] ? parseInt(m[2], 10) : null;
  if (start === null) return undefined; // 后缀区间暂不支持，回退全量
  // R2 range 用 offset + length
  return { offset: start, length: end !== null ? end - start + 1 : undefined };
}
