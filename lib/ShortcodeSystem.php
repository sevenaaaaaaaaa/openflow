<?php
/**
 * OpenFlow 短代码系统 — 文章正文内嵌卡片
 *
 * 用法（在文章正文 HTML 中插入）：
 *   [card type="podcast" id="xxx"]          播客卡片
 *   [card type="subscribe"]                  订阅 Newsletter 卡片
 *   [card type="subscription"]               付费订阅卡片
 *   [card type="course" id="xxx"]            课程卡片
 *   [card type="consult" id="xxx"]           1v1 咨询卡片
 *   [card type="live" id="xxx"]              直播预告卡片
 *   [card type="live-replay" id="xxx"]       直播回放卡片
 *   [card type="download" id="xxx"]          资料下载卡片
 *   [card type="cta" title="..." url="..."]  通用 CTA 卡片
 *
 * 渲染前需调用 shortcode_init()（自动被 article.php 引入）
 */

if (!function_exists('shortcode_init')) {

function shortcode_init(): void {
    // 一次性注册
    static $done = false;
    if ($done) return;
    $done = true;
}

function shortcode_render(string $content): string {
    return preg_replace_callback(
        '/\[card\s+([^\]]+)\]/i',
        function ($m) { return shortcode_build($m[1]); },
        $content
    );
}

function shortcode_build(string $attrs): string {
    // Parse attrs into key-value
    $pairs = [];
    if (preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $attrs, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $x) $pairs[$x[1]] = $x[2];
    }
    $type = $pairs['type'] ?? 'cta';
    $id = $pairs['id'] ?? '';

    switch ($type) {
        case 'course':    return shortcode_course($id);
        case 'podcast':   return shortcode_podcast($id);
        case 'subscribe': return shortcode_newsletter();
        case 'subscription': return shortcode_subscription();
        case 'consult':   return shortcode_consult($id);
        case 'live':      return shortcode_live($id, false);
        case 'live-replay': return shortcode_live($id, true);
        case 'download':  return shortcode_download($id);
        case 'cta':       return shortcode_cta($pairs);
        case 'embed':     return shortcode_embed($pairs['url'] ?? '');
        default:          return '';
    }
}

// ─── Video/Content Embed（YouTube / Bilibili 等）───
function shortcode_embed(string $url): string {
    $url = trim($url);
    if (!$url) return '';
    $iframe = '';

    // YouTube
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([\w-]{6,})#', $url, $m)) {
        $iframe = '<iframe width="100%" height="420" src="https://www.youtube.com/embed/' . $m[1] . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" style="border-radius:14px"></iframe>';
    }
    // Bilibili
    elseif (preg_match('#bilibili\.com/video/(BV[\w]+)#', $url, $m)) {
        $iframe = '<iframe width="100%" height="420" src="//player.bilibili.com/player.html?bvid=' . $m[1] . '&page=1" scrolling="no" border="0" frameborder="no" framespacing="0" allowfullscreen="true" loading="lazy" style="border-radius:14px"></iframe>';
    }
    // Vimeo
    elseif (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
        $iframe = '<iframe width="100%" height="420" src="https://player.vimeo.com/video/' . $m[1] . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" style="border-radius:14px"></iframe>';
    }
    // 通用 iframe（显式传入 embed 域名）
    elseif (preg_match('#^https?://#', $url)) {
        $iframe = '<iframe width="100%" height="420" src="' . htmlspecialchars($url) . '" frameborder="0" allowfullscreen loading="lazy" style="border-radius:14px"></iframe>';
    }

    return $iframe ? '<div style="margin:24px 0">' . $iframe . '</div>' : '';
}

// ─── Card styles (shared) ───
function shortcode_style(): string {
    return '<style>'
        . '.of-card{display:flex;align-items:center;gap:14px;padding:16px 18px;margin:24px 0;border:1px solid var(--border,#e5e5e5);border-radius:16px;background:var(--surface,#fff);box-shadow:0 8px 24px rgba(0,0,0,.05);transition:transform .2s,box-shadow .2s}'
        . '.of-card:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.1)}'
        . '.of-card .of-ic{flex:0 0 auto;width:52px;height:52px;border-radius:14px;display:grid;place-items:center;font-size:24px;background:linear-gradient(135deg,#ddf0d6,#d9eaf6);color:#1e1e1e}'
        . '.of-card .of-info{flex:1;min-width:0}'
        . '.of-card .of-k{font-size:11px;font-weight:700;letter-spacing:.08em;color:#8a8a8a;text-transform:uppercase;margin-bottom:3px}'
        . '.of-card .of-t{font-size:15px;font-weight:700;color:#1a1625;line-height:1.4}'
        . '.of-card .of-d{font-size:12.5px;color:#6b6580;margin-top:3px;line-height:1.5}'
        . '.of-card .of-btn{flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:999px;background:#1e1e1e;color:#ddff0e;font-size:13px;font-weight:700;text-decoration:none;transition:background .2s}'
        . '.of-card .of-btn:hover{background:#333}'
        . '@media(max-width:520px){.of-card{flex-wrap:wrap}.of-card .of-btn{width:100%;justify-content:center}}'
        . '</style>';
}

function shortcode_card_html(string $icon, string $kicker, string $title, string $desc, string $url, string $btn = '查看详情'): string {
    return '<div class="of-card"><div class="of-ic">' . $icon . '</div>'
        . '<div class="of-info"><div class="of-k">' . htmlspecialchars($kicker) . '</div>'
        . '<div class="of-t">' . htmlspecialchars($title) . '</div>'
        . '<div class="of-d">' . htmlspecialchars($desc) . '</div></div>'
        . '<a class="of-btn" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($btn) . ' →</a></div>';
}

// ─── Course ───
function shortcode_course(string $id): string {
    foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
        if (($c['id'] ?? '') === $id) {
            $price = isset($c['price']) ? '¥' . $c['price'] : '免费';
            return shortcode_card_html('🎓', '课程', $c['title'] ?? '', ($c['desc'] ?? '') . ' · ' . $price, '/course-player.php?id=' . urlencode($id), '开始学习');
        }
    }
    return '';
}

// ─── Podcast ───
function shortcode_podcast(string $id): string {
    foreach (json_read(DATA_DIR . '/podcasts.json') as $p) {
        if (($p['id'] ?? '') === $id) {
            return shortcode_card_html('🎙️', '播客', $p['title'] ?? '', $p['desc'] ?? '', '/podcasts.php#ep-' . urlencode($id), '收听');
        }
    }
    return '';
}

// ─── Newsletter ───
function shortcode_newsletter(): string {
    return '<div class="of-card"><div class="of-ic">✉️</div><div class="of-info"><div class="of-k">Newsletter</div>'
        . '<div class="of-t">订阅内容更新</div><div class="of-d">每周获取网站增长与 AI 运营最新洞察，绝无打扰。</div></div>'
        . '<form onsubmit="return ofNewsletterSubmit(this,event)" style="display:flex;gap:8px;flex:0 0 auto">'
        . '<input type="email" name="email" placeholder="你的邮箱" required style="height:38px;padding:0 12px;border:1px solid #e5e5e5;border-radius:999px;font-size:13px;outline:none">'
        . '<button type="submit" class="of-btn">订阅</button></form></div>'
        . '<script>window.ofNewsletterSubmit=function(f,e){e.preventDefault();var em=f.querySelector("input").value;fetch("/api/newsletter.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({email:em})}).then(r=>r.json()).then(d=>{f.innerHTML=d.ok?"✅ 订阅成功":"⚠️ "+d.error});return false;};</script>';
}

// ─── Paid subscription ───
function shortcode_subscription(): string {
    return shortcode_card_html('💎', '付费订阅', '开通会员', '解锁全部内容、课程与专属权益', '/member.php?view=subscribe', '查看计划');
}

// ─── 1v1 Consult ───
function shortcode_consult(string $id): string {
    foreach (json_read(DATA_DIR . '/consultation/mentors.json') as $m) {
        if (($m['id'] ?? '') === $id) {
            $price = isset($m['price']) ? '¥' . $m['price'] . '/次' : '';
            return shortcode_card_html('🤝', '1v1 咨询', $m['name'] ?? '', ($m['title'] ?? '') . ' ' . $price, '/consultation.php', '预约');
        }
    }
    return '';
}

// ─── Live ───
function shortcode_live(string $id, bool $replay): string {
    foreach (json_read(DATA_DIR . '/live/index.json') as $l) {
        if (($l['id'] ?? '') === $id) {
            $icon = $replay ? '📺' : '🔴';
            $kicker = $replay ? '直播回放' : '直播预告';
            $btn = $replay ? '观看回放' : '预约直播';
            return shortcode_card_html($icon, $kicker, $l['title'] ?? '', $l['desc'] ?? '', '/live.php?id=' . urlencode($id), $btn);
        }
    }
    return '';
}

// ─── Download ───
function shortcode_download(string $id): string {
    foreach (json_read(DATA_DIR . '/downloads.json') as $d) {
        if (($d['id'] ?? '') === $id) {
            return shortcode_card_html('📥', '资料下载', $d['title'] ?? '', $d['desc'] ?? '', '/downloads.php', '获取资料');
        }
    }
    return '';
}

// ─── Generic CTA ───
function shortcode_cta(array $p): string {
    return shortcode_card_html('🚀', '行动', $p['title'] ?? '立即开始', $p['desc'] ?? '', $p['url'] ?? '/index.html#contact', $p['btn'] ?? '了解更多');
}

// 供前端文章页调用
function article_render(string $content): string {
    shortcode_init();
    return shortcode_style() . shortcode_render($content);
}

} // end if function_exists
