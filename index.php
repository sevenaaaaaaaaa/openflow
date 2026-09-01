<?php
/**
 * 首页（动态版）— 与 index.html 视觉一致，提供 SSR SEO + 缓存控制
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0'); // 首页始终新鲜，避免旧版缓存

// 首页文章区：动态读取已发布文章（最新 3 篇）
$homeArticles = [];
try {
    $all = get_articles();
    $pub = array_values(array_filter($all, fn($a) => ($a['status'] ?? '') === 'published'));
    foreach (array_slice($pub, 0, 3) as $a) {
        $homeArticles[] = [
            'cat' => $a['category'] ?? '洞察',
            't' => $a['title'] ?? '',
            'meta' => max(1, (int)round(mb_strlen(strip_tags($a['content'] ?? '')) / 400)) . ' 分钟',
            'date' => substr($a['created_at'] ?? '', 0, 10),
            'd' => mb_substr(strip_tags($a['excerpt'] ?? ''), 0, 90),
            'link' => '/articles/' . ($a['slug'] ?? $a['id'] ?? ''),
        ];
    }
} catch (Throwable $e) {}
$homeArticlesJson = json_encode($homeArticles, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>芭乐派 · OpenFlow 增长操作系统</title>
<meta name="description" content="芭乐派给一人公司的增长系统。OpenFlow 是其开源底座：它自己爬信号、自己出草稿、自己盯该跟进谁——你只做判断，不做事。">
<?php if (function_exists('seo_head')): seo_head(['title' => '芭乐派 · OpenFlow 增长操作系统', 'canonical' => site_config_get('site_url') . '/']); endif; ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='2' y1='16' x2='30' y2='16' gradientUnits='userSpaceOnUse'%3E%3Cstop stop-color='oklch(52%25 .17 258)'/%3E%3Cstop offset='1' stop-color='oklch(58%25 .16 285)'/%3E%3C/linearGradient%3E%3C/defs%3E%3Ccircle cx='16' cy='16' r='16' fill='oklch(16%25 0 0)'/%3E%3Cpath d='M16 6.5a9.5 9.5 0 1 1-9.5 9.5' stroke='url(%23g)' stroke-width='2.4' stroke-linecap='round' fill='none'/%3E%3Cpath d='M11.5 10.5v12M11.5 14h7.6M11.5 18.5h7.6' stroke='oklch(96%25 0 0)' stroke-width='2.2' stroke-linecap='round' fill='none'/%3E%3C/svg%3E">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。
     id 与 site-shell.js 的注入判重一致，故 site-shell 不会重复插入。 -->
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260826b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260826b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260826b">
<style>
/* ══ token 契约（与 openflow-tools-pilot.html 同源，零新增色值） ══ */
:root{
  --bg: oklch(96.5% .016 85);
  --bg-soft: oklch(94% .02 85);
  --surface: oklch(100% 0 0/.62);
  --surface-strong: oklch(100% 0 0/.88);
  --fg: oklch(22% .02 70);
  --muted: oklch(46% .016 70);
  --faint: oklch(51% .014 75);
  --border: oklch(86% .014 80);
  --border-strong: oklch(76% .02 80);
  --border-soft: oklch(86% .014 80/.55);
  --hover: oklch(22% .02 70/.055);
  --hover-strong: oklch(22% .02 70/.11);
  --accent: oklch(52% .17 258);
  --accent-strong: oklch(46% .17 258);
  --accent-soft: oklch(52% .17 258/.12);
  --on-accent: oklch(100% 0 0);
  --glass: oklch(100% 0 0/.5);
  --glass-bright: oklch(100% 0 0/.66);
  --glass-border: oklch(100% 0 0/.68);
  --ok: oklch(58% .17 152);        --ok-soft: oklch(58% .17 152/.12);
  --warn: oklch(66% .15 75);       --warn-soft: oklch(66% .15 75/.14);
  --danger: oklch(55% .2 25);      --danger-soft: oklch(55% .2 25/.12);
  --blob-a: oklch(72% .12 262/.30);
  --blob-b: oklch(70% .13 305/.24);
  --blob-c: oklch(74% .11 200/.22);
  --shadow: 0 24px 60px -24px oklch(30% .04 80/.28);
  --shadow-sm: 0 10px 28px -14px oklch(30% .04 80/.22);
  --r-lg: 26px; --r-md: 18px; --r-sm: 12px;
  --chrome-h: 56px; --sb-w: 248px; --container: 1120px;
  --font-display: "Space Grotesk","PingFang SC","HarmonyOS Sans SC","MiSans","Segoe UI",system-ui,sans-serif;
  --font-body: "Space Grotesk",-apple-system,BlinkMacSystemFont,"PingFang SC","HarmonyOS Sans SC","MiSans","Segoe UI",system-ui,sans-serif;
  --font-mono: ui-monospace,"SF Mono","JetBrains Mono",Menlo,monospace;
  --ease-spring: cubic-bezier(.32,.72,0,1);
  --ease-out: cubic-bezier(.22,1,.36,1);
  color-scheme: light;
}
html[data-theme="dark"]{
  --bg: oklch(19% .014 70); --bg-soft: oklch(22.5% .014 72);
  --surface: oklch(27% .016 75/.55); --surface-strong: oklch(30% .016 75/.82);
  --fg: oklch(93% .008 85); --muted: oklch(70% .014 80); --faint: oklch(64% .014 80);
  --border: oklch(100% 0 0/.1); --border-strong: oklch(100% 0 0/.2);
  --border-soft: oklch(100% 0 0/.06);
  --hover: oklch(93% .008 85/.07); --hover-strong: oklch(93% .008 85/.13);
  --accent: oklch(74% .13 258); --accent-strong: oklch(80% .12 258);
  --accent-soft: oklch(74% .13 258/.15); --on-accent: oklch(16% .03 260);
  --ok: oklch(74% .15 152); --ok-soft: oklch(74% .15 152/.15);
  --warn: oklch(76% .13 75); --warn-soft: oklch(76% .13 75/.16);
  --danger: oklch(72% .16 25); --danger-soft: oklch(72% .16 25/.14);
  --glass: oklch(30% .014 75/.5); --glass-bright: oklch(34% .014 75/.62); --glass-border: oklch(100% 0 0/.15);
  --blob-a: oklch(62% .13 262/.18); --blob-b: oklch(58% .14 305/.15); --blob-c: oklch(60% .12 200/.13);
  --shadow: 0 24px 60px -24px oklch(0% 0 0/.55); --shadow-sm: 0 10px 28px -14px oklch(0% 0 0/.5);
  color-scheme: dark;
}

/* ── Archetype: timeline（无框 · 单轨 + 节点编号） ── */
.tl{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;position:relative}
.tl::before{content:"";position:absolute;top:14px;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--border-strong),var(--border-soft))}
.tl-step{position:relative;padding-top:40px;display:flex;flex-direction:column;gap:10px}
.tl-n{position:absolute;top:-14px;left:0;width:28px;height:28px;border-radius:50%;background:var(--bg);border:2px solid var(--accent);color:var(--accent);display:grid;place-items:center;font-family:var(--font-mono);font-size:12px;font-weight:700}
.tl-step h3{font-size:17px;font-weight:700;letter-spacing:-.01em}
.tl-step p{font-size:14px;color:var(--muted);line-height:1.7}

/* ── 模块：表单（原 60+ 行 inline style → .field/.inp） ── */
.inp{width:100%;min-height:46px;padding:11px 14px;border-radius:12px;border:1.5px solid var(--border);background:var(--surface);font-size:14.5px;transition:border-color .2s,box-shadow .2s;outline:none;color:var(--fg)}
.inp::placeholder{color:var(--faint)}
.inp:focus{border-color:var(--accent);box-shadow:0 0 0 4px oklch(52% .17 258/.12)}
textarea.inp{min-height:110px;resize:vertical;line-height:1.7}
.hp{display:none}

/* ── Archetype: split-form（预约诊断 · pitch 列 + 表单卡） ── */
.contact-wrap{display:grid;grid-template-columns:.85fr 1.15fr;gap:clamp(20px,3vw,40px);align-items:stretch}
.ct-pitch{display:flex;flex-direction:column;gap:14px;padding:clamp(4px,1vw,10px) 0}
.ct-pitch .kicker{margin-bottom:4px}
.ct-pitch h2{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;line-height:1.28}
.ct-pitch .lead{color:var(--muted);font-size:15px;line-height:1.8}
.ct-list{display:flex;flex-direction:column;gap:12px;margin-top:8px;list-style:none}
.ct-list li{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--muted);line-height:1.65}
.ct-list .ck{width:20px;height:20px;border-radius:50%;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;flex:0 0 auto;margin-top:1px}
.ct-list .ck svg{width:11px;height:11px}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:clamp(24px,4vw,40px);box-shadow:var(--shadow-sm);backdrop-filter:blur(16px) saturate(150%)}
.form-grid{display:grid;gap:14px;text-align:left}
.f-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.f-note{font-size:12px;color:var(--faint)}
#form-msg{font-size:14px;font-weight:600;display:none}

/* ── 首页：hero（原 .hero 手写 + inline → 模块化） ── */
.hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:clamp(28px,5vw,64px);align-items:center;padding:18px 0 8px;position:relative}
.hero::before{content:"";position:absolute;inset:-12% -6% -6% -6%;background:radial-gradient(46% 58% at 78% 8%,color-mix(in oklab,var(--accent),transparent 88%),transparent 72%);pointer-events:none}
.hero>*{position:relative}
.hero-copy{display:flex;flex-direction:column;gap:16px}
.hero-copy h1{font-family:var(--font-display);font-size:clamp(40px,5.4vw,66px);font-weight:700;letter-spacing:-.02em;line-height:1.12}
.hero-copy .lead{max-width:560px;color:var(--muted);font-size:16px;line-height:1.8}
.cta-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.trust{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:12.5px;color:var(--faint)}
.trust .dot{width:6px;height:6px;border-radius:50%;background:var(--ok)}
.hero-win{border-radius:var(--r-lg);border:1px solid var(--border);background:var(--surface);backdrop-filter:blur(20px) saturate(160%);box-shadow:var(--shadow);overflow:hidden}
.win-bar{display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid var(--border-soft);background:var(--glass)}
.win-bar .url{flex:1;font-family:var(--font-mono);font-size:12px;color:var(--faint);text-align:center}
.win-flow{display:flex;flex-direction:column;padding:18px}
.flow-row{display:flex;align-items:center;gap:12px;padding:12px 10px;border-radius:12px;background:var(--glass);border:1px solid var(--border-soft)}
.flow-row .fi{width:38px;height:38px;border-radius:11px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto}
.flow-row .fi svg{width:18px;height:18px}
.flow-row .ft{font-size:13.5px;font-weight:700}
.flow-row .fd{font-size:11.5px;font-family:var(--font-mono);color:var(--faint)}
.flow-row .st{margin-left:auto;width:10px;height:10px;border-radius:50%;background:var(--ok);flex:0 0 auto}
.flow-link{height:22px;width:2px;background:linear-gradient(180deg,var(--border-strong),var(--border-soft));margin-left:28px}
.win-chip{display:inline-flex;align-items:center;gap:6px;margin:0 18px 18px;padding:6px 12px;border-radius:999px;background:var(--ok-soft);color:var(--ok);font-size:12px;font-weight:700}

/* ── Archetype: editorial-split（痛点双栏 · hairline 行 + serif 引语） ── */

/* ── Archetype: stat-strip（TIPS 四力 · serif 大数字 + hairline 分隔） ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr)}
.st{padding:8px 28px 8px 0}
.st+.st{border-left:1px solid var(--border-soft);padding-left:28px}
.st .st-n{font-family:var(--font-display);font-size:clamp(40px,4.6vw,56px);font-weight:700;letter-spacing:-.02em;line-height:1}
.st .st-en{display:block;font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--accent);margin-top:14px;text-transform:uppercase;letter-spacing:.08em}
.st .st-t{display:block;font-size:15px;font-weight:700;margin-top:6px}
.st .st-d{display:block;font-size:12.5px;color:var(--muted);line-height:1.65;margin-top:6px}

/* ── Archetype: link-grid（无框文字入口网 · hover 显箭头） ── */
.link-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 28px}
.link-it{display:flex;align-items:center;gap:12px;padding:14px 12px;border-radius:12px;transition:background .2s}
.link-it .ic{width:36px;height:36px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto}
.link-it .ic svg{width:16px;height:16px}
.link-it .lt{min-width:0;flex:1}
.link-it .lt b{display:block;font-size:14.5px;font-weight:700}
.link-it .lt span{display:block;font-size:12px;color:var(--faint);margin-top:2px}
.link-it .go{margin-left:auto;color:var(--accent);opacity:0;transform:translateX(-4px);transition:opacity .2s,transform .2s;flex:0 0 auto}
.link-it .go svg{width:15px;height:15px}
.link-it:hover{background:var(--hover)}
.link-it:hover .go,.link-it:focus-visible .go{opacity:1;transform:none}

/* ── Archetype: featured + rail（场景 · 1 大卡 + 3 行轨道，非对称） ── */
.scn{display:grid;grid-template-columns:1.12fr .88fr;gap:14px;align-items:stretch}
.scn-f{padding:clamp(24px,3vw,34px);border-radius:var(--r-lg);background:linear-gradient(165deg,var(--accent-soft),transparent 65%),var(--surface);border:1px solid var(--border);display:flex;flex-direction:column;gap:14px;box-shadow:0 1px 3px oklch(30% .04 80/.05)}
.scn-f .f-tag{display:inline-flex;align-items:center;align-self:flex-start;font-size:11.5px;font-weight:700;color:var(--accent-strong);background:var(--accent-soft);padding:4px 12px;border-radius:999px}
.scn-f h3{font-family:var(--font-display);font-size:clamp(22px,2.6vw,28px);font-weight:700;letter-spacing:-.01em;line-height:1.35}
.scn-f p{font-size:14px;color:var(--muted);line-height:1.8}
.scn-f .cta-row{margin-top:auto;padding-top:8px}
.scn-s{display:flex;flex-direction:column;justify-content:center}
.scn-row{display:flex;gap:14px;align-items:flex-start;padding:16px 8px;border-bottom:1px solid var(--border-soft)}
.scn-row:first-child{padding-top:4px}
.scn-row:last-child{border-bottom:none}
.scn-row .ic{width:36px;height:36px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto;margin-top:2px}
.scn-row .ic svg{width:16px;height:16px}
.scn-row h3{font-size:14.5px;font-weight:700;margin-bottom:3px}
.scn-row p{font-size:12.5px;color:var(--muted);line-height:1.6}
.tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:2px}
.tags span{font-size:11.5px;font-weight:600;color:var(--accent);background:var(--accent-soft);padding:3px 10px;border-radius:999px}

/* ── Archetype: quote-rail（评价 · serif 引语列 + hairline 分隔） ── */
.qr{display:grid;grid-template-columns:repeat(3,1fr)}
.q-i{padding:6px 28px;border-left:1px solid var(--border-soft);display:flex;flex-direction:column;gap:12px}
.q-i:first-child{border-left:none;padding-left:0}
.q-i:last-child{padding-right:0}
.q-i .stars{color:oklch(76% .16 78);font-size:13px;letter-spacing:.14em}
.q-i blockquote{font-family:var(--font-display);font-size:16px;font-weight:600;letter-spacing:-.01em;line-height:1.8;color:var(--fg)}
.q-i .who{display:flex;align-items:center;gap:10px;margin-top:auto;padding-top:6px}
.q-i .av{width:32px;height:32px;border-radius:50%;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-size:12.5px;font-weight:700;flex:0 0 auto}
.q-i .who b{font-size:13px;display:block}
.q-i .who span{font-size:11.5px;color:var(--faint)}

/* ── Archetype: magazine-rows（文章 · 元数据 + 标题行） ── */
.a-row{display:grid;grid-template-columns:minmax(188px,236px) 1fr auto;gap:20px;align-items:center;padding:20px 4px;border-bottom:1px solid var(--border-soft)}
.a-row:first-child{padding-top:4px}
.a-row .a-meta{display:flex;align-items:center;gap:10px;font-size:11.5px;color:var(--faint);font-family:var(--font-mono);white-space:nowrap}
.a-row .a-body h3{font-size:16px;font-weight:700;letter-spacing:-.01em;line-height:1.5;transition:color .2s}
.a-row .a-body p{font-size:13px;color:var(--muted);line-height:1.7;margin-top:5px}
.a-row .a-go{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;font-weight:600;color:var(--accent);white-space:nowrap}
.a-row .a-go svg{width:13px;height:13px}
a.a-row:hover .a-body h3{color:var(--accent)}

/* ── footer ── */
.foot{margin-top:72px;border-top:1px solid var(--border);padding-top:44px;display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:32px}
.foot .brand{display:flex;align-items:center;gap:8px;font-weight:700;font-size:15px}
.foot .brand .ic{width:20px;height:20px;color:var(--accent)}
.foot .brand .ic svg{width:20px;height:20px}
.f-about{font-size:13px;color:var(--muted);line-height:1.75;margin-top:10px;max-width:300px}
.f-social{display:flex;align-items:center;gap:8px;margin-top:14px;flex-wrap:wrap}
.f-social .soc{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:var(--surface);color:var(--muted);display:grid;place-items:center;transition:color .2s,background .2s,border-color .2s,transform .2s var(--ease-spring)}
.f-social .soc:hover{color:var(--accent);border-color:var(--accent);background:var(--accent-soft);transform:translateY(-2px)}
.f-social .soc svg{width:16px;height:16px}
.f-social .soc-group{display:flex;align-items:center;gap:8px}
.f-social .soc-div{width:1px;height:18px;background:var(--border-soft);margin:0 4px}
.note{font-size:12px;color:var(--faint);margin-top:8px}
.foot h4{font-size:13px;font-weight:700;margin-bottom:10px}
.foot .fb{display:flex;flex-direction:column;gap:8px}
.foot .fb a{font-size:13px;color:var(--muted);transition:color .2s}
.foot .fb a:hover{color:var(--accent)}
.f-bottom{grid-column:1/-1;border-top:1px solid var(--border-soft);padding-top:18px;margin-top:8px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:12px;color:var(--faint)}

/* ── 浏览节奏模块：窄条 strip · 交替 split · tab 聚合 · 对比表 ── */

/* 交替 split —— 左文右图 / 左图右文 */
.sp-txt{display:flex;flex-direction:column;gap:16px}
.sp-txt h2{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;line-height:1.22}
.sp-txt .lead{color:var(--muted);font-size:15.5px;line-height:1.8}
.sp-list{display:flex;flex-direction:column;gap:11px}
.sp-list li{display:flex;gap:11px;align-items:flex-start;font-size:14px;line-height:1.62;color:var(--fg)}
.sp-list li b{font-weight:700}
.sp-list .ck{width:20px;height:20px;border-radius:6px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto;margin-top:2px}
.sp-list .ck svg{width:11px;height:11px}
.sp-vis{position:relative}
.sp-vis::before{content:"";position:absolute;inset:-12% -10% -10% -6%;background:radial-gradient(55% 60% at 70% 18%,color-mix(in oklab,var(--accent),transparent 90%),transparent 70%);pointer-events:none}
.sp-win{position:relative;border-radius:var(--r-lg);border:1px solid var(--border);background:var(--surface);backdrop-filter:blur(20px) saturate(160%);box-shadow:var(--shadow);overflow:hidden}
.sp-body{display:flex;flex-direction:column;gap:16px;padding:16px}
.sp-sec{display:flex;flex-direction:column;gap:9px}
.sp-sec-t{font-size:11px;font-weight:700;letter-spacing:.08em;color:var(--faint);text-transform:uppercase}
.sp-insight{border-radius:var(--r-md);background:var(--accent-soft);border:1px solid color-mix(in oklab,var(--accent),transparent 70%);padding:12px 14px;font-size:13px;line-height:1.7;color:var(--fg)}
.sp-card{border-radius:var(--r-md);background:var(--glass);border:1px solid var(--border-soft);padding:12px 14px;display:flex;flex-direction:column;gap:8px}
.sp-card-t{font-size:14px;font-weight:700;line-height:1.45}
.sp-card-m{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px}
.sp-card-m svg{width:13px;height:13px;color:var(--ok)}
.sp-card-m .ckd{color:var(--ok);font-weight:700}

/* tab 聚合 —— 疲劳点上的再刺激 */
.tab-bar{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;border-bottom:1px solid var(--border-soft);padding-bottom:18px}
.tab-p{appearance:none;border:1px solid transparent;background:none;font-family:var(--font-body);font-size:14px;font-weight:600;color:var(--faint);padding:9px 18px;border-radius:999px;cursor:pointer;transition:background .2s,color .2s,border-color .2s;display:inline-flex;align-items:center;gap:8px}
.tab-p .ic{width:15px;height:15px;color:var(--accent);flex:0 0 auto}
.tab-p .ic svg{width:15px;height:15px}
.tab-p:hover{background:var(--hover);color:var(--fg)}
.tab-p:focus-visible{outline:none;box-shadow:0 0 0 3px color-mix(in oklab,var(--accent),transparent 80%)}
.tab-p[aria-selected="true"]{background:var(--surface);border-color:var(--border);color:var(--fg);box-shadow:var(--shadow-sm)}
.tab-panel{display:none}
.tab-panel.on{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:clamp(20px,3vw,44px);align-items:stretch}
.tp-txt{display:flex;flex-direction:column;gap:13px;padding:6px 2px}
.tp-txt h3{font-family:var(--font-display);font-size:clamp(21px,2.6vw,28px);font-weight:700;letter-spacing:-.01em;line-height:1.3}
.tp-txt p{color:var(--muted);font-size:14.5px;line-height:1.8}
.tp-txt .tags{margin-top:2px}
.tp-steps{display:flex;flex-direction:column;justify-content:center;border-left:1px solid var(--border-soft);padding:6px 0 6px 28px}
.tp-step{display:flex;gap:13px;padding:13px 0;border-bottom:1px solid var(--border-soft);align-items:flex-start}
.tp-step:last-child{border-bottom:none}
.tp-n{font-family:var(--font-mono);font-size:12.5px;font-weight:600;color:var(--accent);flex:0 0 auto;padding-top:1px}
.tp-step b{font-size:14px;font-weight:700;display:block}
.tp-step span{font-size:12.5px;color:var(--faint);line-height:1.6}

/* 对比表 —— 垃圾时间里的理性收束 */
.cmp-wrap{display:flex;flex-direction:column;gap:12px}
.cmp{width:100%;border-collapse:collapse;font-size:13.5px}
.cmp th,.cmp td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border-soft)}
.cmp thead th{font-size:12px;font-weight:700;letter-spacing:.03em;color:var(--faint);white-space:nowrap}
.cmp tbody th{font-size:14px;font-weight:700;color:var(--fg);width:24%}
.cmp td{color:var(--muted)}
.cmp .y{color:var(--ok);font-weight:700}
.cmp .na{color:var(--faint)}
.cmp td.ol{background:color-mix(in oklab,var(--accent),transparent 94%)}
.cmp thead th.ol{color:var(--accent-strong)}
.cmp-note{font-size:12px;color:var(--faint);line-height:1.6}

/* ═════════════════════════════════════════════════════════════
   v4 浏览节奏迭代：集中导航 · 认证 · 首屏竞技场 · 模型 Loop · workflow 变体
   全部 token/color-mix 派生，零新增色值
   ═════════════════════════════════════════════════════════════ */

/* ── 顶栏集中导航（双导航 → 单一顶栏） ── */
/* #chrome 高度不再单独加高：全站统一走 tokens.css 的 --chrome-h(56px)，
   与 about/product/capability/courses/academy 对齐。padding 由 modules.css 给 18px。 */

/* ── 侧栏：桌面常驻（Arc sidebar-first）+ 移动端抽屉 ── */

/* ── Hero v4：居中横幅 · 交互标题 · 场景竞技场 ── */
.hero-center{display:flex;flex-direction:column;align-items:center;text-align:center;gap:22px;padding:clamp(30px,5vw,64px) clamp(8px,2vw,20px) clamp(20px,3vw,36px);position:relative}
.hero-center::before{content:"";position:absolute;inset:-16% -10% -8% -10%;background:radial-gradient(52% 60% at 50% 0%,color-mix(in oklab,var(--accent),transparent 86%),transparent 72%);pointer-events:none}
.hero-center>*{position:relative}
.hero-center h1{font-family:var(--font-display);font-size:clamp(38px,5.4vw,70px);font-weight:700;letter-spacing:-.02em;line-height:1.12;max-width:880px}
.hr-word{position:relative;display:inline-block;color:var(--accent-strong);cursor:pointer;border-bottom:4px solid var(--accent-soft);padding-bottom:2px;transition:border-color .2s,transform .2s var(--ease-spring);white-space:nowrap}
.hr-word:hover{border-color:var(--accent)}
.hr-word:active{transform:scale(.98)}
.hero-center .lead{max-width:620px;color:var(--muted);font-size:16.5px;line-height:1.9}
.hero-center .trust{justify-content:center}
.hero-center .cta-row{justify-content:center;gap:18px}
.arena{width:100%;max-width:1080px;margin-top:10px;border-radius:var(--r-lg);border:1px solid var(--border);background:var(--surface);backdrop-filter:blur(22px) saturate(160%);box-shadow:var(--shadow);overflow:hidden}
.arena-bar{display:flex;align-items:center;gap:8px;padding:14px 18px;border-bottom:1px solid var(--border-soft);background:var(--glass)}
.arena-bar .url{flex:1;font-family:var(--font-mono);font-size:12px;color:var(--faint);text-align:center}
.arena-canvas{position:relative;width:100%;height:clamp(330px,37vw,470px);margin:2px 0 6px}
.arc{position:absolute;inset:0;width:100%;height:100%;overflow:visible}
.ln{fill:none;stroke:var(--border-strong);stroke-width:1.6;stroke-linecap:round;stroke-dasharray:4 10;animation:ln-flow 1.5s linear infinite}
.arena .ghost{position:absolute;font-family:var(--font-mono);font-size:10.5px;letter-spacing:.04em;color:var(--faint);background:var(--surface-strong);border:1px dashed var(--border-strong);border-radius:999px;padding:3px 10px;pointer-events:none;white-space:nowrap}
.arena .ghost.gl{left:8px;bottom:8px;top:auto}
.arena .ghost.gr{right:4px;top:4px}
@keyframes ln-flow{to{stroke-dashoffset:-28}}
.nd{position:absolute;transform:translate(-50%,-50%);display:flex;align-items:center;gap:6px;max-width:178px;background:var(--surface-strong);border:1.5px solid var(--border-strong);border-radius:12px;padding:6px 9px 6px 7px;box-shadow:var(--shadow-sm);transition:border-color .3s,box-shadow .3s;text-align:left;white-space:nowrap}
.nd-ic{position:relative;width:22px;height:22px;border-radius:8px;background:var(--glass);display:grid;place-items:center;color:var(--faint);flex:0 0 auto;transition:color .3s,background .3s}
.nd.on{border-color:var(--accent);box-shadow:0 0 0 5px var(--accent-soft)}
.nd.on .nd-ic{color:var(--accent-strong);background:var(--accent-soft)}
.nd-ic svg{width:13px;height:13px}
.nd b{font-size:12.5px;font-weight:700;line-height:1.15;color:var(--fg)}
.nd span{font-size:10.5px;font-family:var(--font-mono);color:var(--faint);white-space:nowrap}
.arena-driver{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:10px;border-top:1px solid var(--border-soft);background:var(--glass);padding:13px 18px;font-size:12.5px;color:var(--muted)}
.arena-bubbles{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;padding:0 clamp(14px,2.5vw,30px) clamp(20px,2.8vw,26px)}
.bub{position:relative;width:44px;height:44px;border-radius:50%;border:1px solid var(--border);background:var(--surface);color:var(--muted);display:grid;place-items:center;cursor:pointer;transition:transform .2s var(--ease-spring),border-color .2s,background .2s,color .2s,box-shadow .2s}
.bub .mi{width:20px;height:20px}
.bub .mi svg{width:20px;height:20px}
.bub:hover{border-color:var(--border-strong);color:var(--fg);transform:translateY(-2px)}
.bub.on{background:var(--accent);border-color:transparent;color:var(--on-accent);box-shadow:0 6px 18px oklch(52% .17 258/.32)}
.bub::after{content:attr(data-m);position:absolute;bottom:calc(100% + 9px);left:50%;transform:translateX(-50%) translateY(4px);background:var(--fg);color:var(--bg);font-size:11px;font-weight:600;padding:5px 10px;border-radius:8px;opacity:0;pointer-events:none;transition:opacity .18s,transform .18s;white-space:nowrap;z-index:6}
.bub:hover::after,.bub:focus-visible::after{opacity:1;transform:translateX(-50%) translateY(0)}


/* ── workflow 变体（时间轨 + 驱动 chips + 连接线） ── */
.wf{display:grid;grid-template-columns:repeat(3,1fr);position:relative}
.wf::before{content:"";position:absolute;top:21px;left:6%;right:6%;height:2px;background:repeating-linear-gradient(90deg,var(--border-strong) 0 10px,transparent 10px 18px)}
.wf-step{position:relative;display:flex;flex-direction:column;align-items:center;gap:12px;padding:0 28px;text-align:center}
.wf-step:first-child{padding-left:0}
.wf-step:last-child{padding-right:0}
.wf-n{width:44px;height:44px;border-radius:14px;background:var(--surface-strong);border:2px solid var(--accent);color:var(--accent-strong);display:grid;place-items:center;font-family:var(--font-mono);font-size:13px;font-weight:700;box-shadow:0 0 0 6px var(--accent-soft);position:relative;z-index:1}
.wf-step h3{font-size:17px;font-weight:700;letter-spacing:-.01em}
.wf-step p{font-size:13.5px;color:var(--muted);line-height:1.75;max-width:300px}
.wf-driver{margin-top:4px}

/* ── 密度放宽（内距小气修复） ── */
.sec{gap:28px}
.sec-head{gap:16px}
.sec-head .lead{font-size:16.5px;line-height:1.95}
.btn{height:48px;padding:0 26px;font-size:15px}
.btn.subtle{height:40px}
.card{padding:36px}
.hero-copy{gap:22px}
.hero-copy .lead{font-size:16.5px;line-height:1.9}
.cta-row{gap:14px}
.trust{font-size:13px;gap:10px}
.win-flow{padding:24px}
.flow-row{padding:15px 14px;gap:14px}
.win-chip{margin:0 24px 24px;padding:8px 14px}
.stats .st{padding:10px 30px 10px 0}
.stats .st+.st{padding-left:30px}
.st .st-d{font-size:13px;line-height:1.8}
.link-it{padding:16px 14px}
.link-it .lt span{font-size:12.5px}
.scn-f{gap:18px}
.scn-row{padding:20px 10px;gap:16px}
.tl-step{gap:14px}
.tl-step p{font-size:14.5px;line-height:1.85}
.q-i{gap:16px;padding:12px 32px}
.q-i blockquote{font-size:17px;line-height:1.95}
.a-row{padding:24px 6px;gap:24px}
.a-row .a-body p{font-size:14px;line-height:1.85}
.sp-txt{gap:20px}
.sp-txt .lead{font-size:15.5px;line-height:1.95;color:var(--muted)}
.sp-list{gap:16px}
.sp-list li{font-size:14.5px;line-height:1.8}
.sp-body{padding:22px}
.sp-sec{gap:12px}
.sp-insight{padding:14px 16px;font-size:13.5px}
.sp-card{padding:15px 16px;gap:10px}
.tab-bar{gap:10px;padding-bottom:18px}
.tab-p{padding:11px 22px}
.tp-txt{gap:18px}
.tp-txt p{font-size:15.5px;line-height:1.95}
.tp-steps{padding:8px 0 8px 34px}
.tp-step{padding:18px 0;gap:15px}
.tp-step b{font-size:14.5px}
.tp-step span{font-size:13px;line-height:1.7}
.cmp{font-size:14px}
.cmp th,.cmp td{padding:15px 18px}
.cmp-note{font-size:12.5px;line-height:1.75}
.form-card{padding:clamp(28px,4vw,44px)}
.form-grid{gap:16px}
.f-note{font-size:12.5px}
.inp{min-height:50px;padding:13px 16px;font-size:15px}
section+section{margin-top:clamp(64px,8vw,110px)}

/* ── v5 终版：集中标题 · 覆盖式滑动 Deck · 自动切换 Tab ── */
.sec-head.center{align-items:center;text-align:center}
.sec-head.center .lead{margin:0 auto}
.sp-txt h3{font-size:clamp(23px,2.8vw,30px);font-weight:800;letter-spacing:-.02em;line-height:1.24}

/* 覆盖式滑动 Deck（TIPS 四力 · 下一块直接覆盖上一块） */
.deck{display:flex;flex-direction:column;gap:14px}
.deck-stage{display:grid;overflow:hidden;max-width:100%}
.deck-p{grid-area:1/1;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.92fr);gap:clamp(24px,4vw,56px);align-items:center;opacity:0;transform:translateX(48px);visibility:hidden;pointer-events:none;transition:opacity .45s var(--ease-out),transform .45s var(--ease-out)}
.deck-p.on{opacity:1;transform:none;visibility:visible;pointer-events:auto;z-index:2}
.deck-cta{display:flex;justify-content:center;padding-top:6px}

/* 两个世界对照（静态三栏 · 鸿沟居中，替代自动切换 Tab） */
.worlds{display:grid;grid-template-columns:1fr 1.08fr 1fr;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.w-col{display:flex;flex-direction:column;gap:15px;padding:clamp(26px,3vw,42px) clamp(22px,2.6vw,38px);position:relative}
.w-col+.w-col{border-left:1px solid var(--border)}
.w-gap{background:linear-gradient(180deg,var(--accent-soft),transparent 80%)}
.w-gap::before{content:"";position:absolute;top:-1px;left:50%;transform:translateX(-50%);width:72px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--accent),oklch(58% .16 285))}
.w-tag{font-family:var(--font-mono);font-size:12px;font-weight:700;letter-spacing:.14em;color:var(--faint);text-transform:uppercase}
.w-gap .w-tag{color:var(--accent)}
.w-col h3{font-size:15px;font-weight:800;letter-spacing:-.01em;line-height:1.5}
.w-col .w-q{font-family:var(--font-display);font-size:clamp(16px,1.7vw,19px);font-weight:600;letter-spacing:-.01em;line-height:1.78;color:var(--fg)}
.w-gap h3{color:var(--accent-strong)}
.w-gap .w-q{background:linear-gradient(120deg,var(--accent),oklch(58% .16 285));-webkit-background-clip:text;background-clip:text;color:transparent}

/* 自动切换进度条 */
.auto{--prog:4.5s}
.prog{position:relative;height:2px;border-radius:2px;background:var(--border-soft);overflow:hidden}
.prog::after{content:"";position:absolute;inset:0;background:var(--accent);transform-origin:left;transform:scaleX(0)}
.auto[data-auto="on"] .prog::after{animation:prog-run var(--prog) linear forwards}
.auto[data-paused="true"] .prog::after{animation-play-state:paused}
@keyframes prog-run{to{transform:scaleX(1)}}
html.rm .auto[data-auto="on"] .prog::after{animation:none}

/* ── v4 响应式降档 ── */
@media (max-width:960px){
  body[data-sb="full"] #sidebar,body[data-sb="rail"] #sidebar,body[data-sb="closed"] #sidebar{transform:translateX(-110%)}
  body[data-sb="drawer"] #sidebar{transform:translateX(0);left:0;width:auto;top:auto;bottom:0;margin:0 10px;border-radius:26px 26px 0 0;border:1px solid var(--border);border-bottom:0;padding-bottom:calc(14px + env(safe-area-inset-bottom))}
  .arc{display:none}
  .arena .ghost{display:none}
  .arena-canvas{height:auto;display:grid;grid-template-columns:repeat(2,1fr);gap:18px;align-items:center;padding:8px 0}
  .nd{position:static;transform:none;width:auto;gap:6px}
  .arena-canvas .nd>span:not(.nd-ic){display:inline}
  .bub{width:40px;height:40px}
  .worlds{grid-template-columns:1fr}
  .w-col+.w-col{border-left:none;border-top:1px solid var(--border)}
  .w-gap::before{left:24px;transform:none}
  .hero-center h1{font-size:clamp(34px,8.2vw,44px)}
  .wf{grid-template-columns:1fr}
  .wf::before{left:21px;top:0;bottom:0;right:auto;width:2px;height:auto;background:repeating-linear-gradient(180deg,var(--border-strong) 0 10px,transparent 10px 18px)}
  .wf-step{flex-direction:row;text-align:left;align-items:center;gap:18px;padding:16px 0 16px 0}
  .wf-step p{max-width:none}
}
@media (max-width:1199px){.nd>span:not(.nd-ic){display:none}}
@media (max-width:640px){
  .arena-driver{font-size:12px}
}

/* ── 响应式（archetype 降档） ── */
@media (max-width:1080px){
  .stats{grid-template-columns:repeat(2,1fr)}
  .stats .st:nth-child(odd){border-left:none;padding-left:0}
  .link-grid{grid-template-columns:repeat(2,1fr)}
  .tl{grid-template-columns:1fr;gap:0}
  .tl::before{display:none}
  .tl-step{padding:0 0 0 44px;gap:6px}
  .tl-step .tl-n{top:6px;left:0}
  .tl-step::before{content:"";position:absolute;left:13px;top:34px;bottom:-26px;width:2px;background:var(--border-soft)}
  .tl-step:last-child::before{display:none}
  .foot{grid-template-columns:1fr 1fr}
  .tab-panel.on{grid-template-columns:1fr}
  .tp-steps{border-left:none;padding-left:0}
}
@media (max-width:860px){
  .hero{grid-template-columns:1fr;gap:28px}
  .deck-p{grid-template-columns:1fr;gap:24px}
  .stats{grid-template-columns:1fr}
  .stats .st+.st{border-left:none;padding-left:0;border-top:1px solid var(--border-soft);padding-top:18px;margin-top:6px}
  .link-grid{grid-template-columns:1fr}
  .scn{grid-template-columns:1fr}
  .qr{grid-template-columns:1fr}
  .q-i{border-left:none;padding:20px 4px;border-bottom:1px solid var(--border-soft)}
  .q-i:first-child{padding-top:6px}
  .q-i:last-child{border-bottom:none;padding-bottom:6px}
  .a-row{grid-template-columns:1fr;gap:8px}
  .a-row .a-go{justify-self:start}
  .g2,.g3,.g4{grid-template-columns:1fr}
  .cmp thead{display:none}
  .cmp,.cmp tbody,.cmp tr{display:block;width:100%}
  .cmp tr{border:1px solid var(--border-soft);border-radius:var(--r-md);padding:4px 14px 8px;margin-bottom:10px}
  .cmp th,.cmp td{display:flex;justify-content:space-between;align-items:center;gap:12px;border:none;padding:9px 0;text-align:left}
  .cmp tbody th{font-size:14px;border-bottom:1px solid var(--border-soft)}
  .cmp td::before{content:attr(data-l);color:var(--faint);font-size:12px;flex:0 0 42%}
  .desktop-only{display:none!important}
}
@media (max-width:640px){
  .foot{grid-template-columns:1fr}
}
</style>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('home'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<!-- 侧栏「本页」锚点区：由 site-shell.js 读取并插入共享侧栏（#sbExtra）。
     首页专属，其它页面没有这个 template 就不渲染。 -->
<template id="of-sidebar-extra">
  <div class="sec-title"><span>本页</span></div>
  <a class="s-item" href="#top" data-od-id="s-home"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg></span><b>增长系统首页</b></a>
  <a class="s-item" href="#pain" data-od-id="s-pain"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg></span><b>两个世界</b></a>
  <a class="s-item" href="#touch" data-od-id="s-quick"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg></span><b>TIPS 增长力</b></a>
  <a class="s-item" href="#loop" data-od-id="s-loop"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M3 12h18"/></svg></span><b>三步增长闭环</b></a>
  <a class="s-item" href="#scenes" data-od-id="s-scenes"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><b>应用场景</b></a>
  <a class="s-item" href="#reviews" data-od-id="s-reviews"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 10h8M8 14h5M9 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></svg></span><b>真实反馈</b></a>
  <a class="s-item" href="#contact" data-od-id="s-contact"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span><b>预约增长诊断</b></a>
</template>


<main id="main" data-od-id="main">

  <!-- ══ Hero ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="hero">
    <div class="hero-center">
      <span class="kicker">芭乐派 · 给一人公司的增长系统</span>
      <h1>你不缺<i class="si">怎么做</i>，<br>你缺 <span class="hr-word" id="hr-word" role="button" tabindex="0" aria-label="点击切换关键词">该做什么</span></h1>
      <p class="lead">市面上的增长工具都默认你有一支团队。芭乐派做的是另一套：它自己爬信号、自己出草稿、自己盯该跟进谁——你只做判断，不做事。</p>
      <div class="cta-row">
        <a class="btn primary" href="/courses" data-od-id="home-cta-start">免费开始（开源）</a>
        <a class="btn ghost" href="/product" data-od-id="home-cta-demo">先看它一天干什么</a>
      </div>
      <div class="trust"><span class="dot"></span>Seven · 十年增长操盘 · 核心能力永久开源 · 数据在你自己的服务器</div>
      <div class="arena">
        <div class="arena-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">openflow.pspi.run/growth-loop</div></div>
        <div class="arena-canvas" id="arena-stage">
          <svg class="arc" viewBox="0 0 1100 400" preserveAspectRatio="none" aria-hidden="true">
            <!-- 入口（左缘开放，虚线流入画布） -->
            <path class="ln" d="M 0 48 C 30 48 45 48 60 48"/>
            <path class="ln" d="M 0 184 C 30 184 45 184 60 184"/>
            <path class="ln" d="M 0 320 C 30 320 45 320 60 320"/>
<path class="ln" d="M 0 48 C 25 48 35 48 60 48"/>
<path class="ln" d="M 0 184 C 25 184 35 184 60 184"/>
<path class="ln" d="M 0 320 C 25 320 35 320 60 320"/>
<path class="ln" d="M 215 48 C 235 32 243 84 263 68"/>
<path class="ln" d="M 215 116 C 235 100 243 164 263 148"/>
<path class="ln" d="M 215 184 C 235 168 243 244 263 228"/>
<path class="ln" d="M 215 252 C 235 236 243 324 263 308"/>
<path class="ln" d="M 215 320 C 235 332 243 296 263 308"/>
<path class="ln" d="M 419 68 C 441 54 450 102 472 88"/>
<path class="ln" d="M 419 148 C 441 134 450 206 472 192"/>
<path class="ln" d="M 419 228 C 441 214 450 310 472 296"/>
<path class="ln" d="M 419 308 C 441 318 450 286 472 296"/>
<path class="ln" d="M 628 88 C 650 76 659 80 681 68"/>
<path class="ln" d="M 628 192 C 650 180 659 160 681 148"/>
<path class="ln" d="M 628 296 C 650 284 659 240 681 228"/>
<path class="ln" d="M 628 296 C 650 308 659 296 681 308"/>
<path class="ln" d="M 837 68 C 859 56 868 100 890 88"/>
<path class="ln" d="M 837 148 C 859 136 868 204 890 192"/>
<path class="ln" d="M 837 228 C 859 216 868 308 890 296"/>
<path class="ln" d="M 837 308 C 859 318 868 286 890 296"/>
<path class="ln" d="M 1046 88 C 1069 88 1077 88 1100 88"/>
<path class="ln" d="M 1046 192 C 1069 192 1077 192 1100 192"/>
<path class="ln" d="M 1046 296 C 1069 296 1077 296 1100 296"/>
          </svg>
          <span class="ghost gl">＋ 更多数据源可接入</span>
          <span class="ghost gr">可接任意下游 →</span>
          <!-- 信号采集层（5 源，错落） -->
          <div class="nd on" data-k="s1" style="left:12.3%;top:12%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 3.5v17M3.5 12h17"/></svg></span><b>舆情爬虫</b><span>爬取 · 24h</span></div>
          <div class="nd" data-k="s2" style="left:12.7%;top:29%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 20a9 9 0 0 1 9 9M5 12a17 17 0 0 1 17 17M5 20h.01"/></svg></span><b>RSS 站点</b><span>行业源</span></div>
          <div class="nd" data-k="s3" style="left:12.5%;top:46%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17 9 11l4 4 8-9"/><path d="M15 6h6v6"/></svg></span><b>搜索热点</b><span>热度上升</span></div>
          <div class="nd" data-k="s4" style="left:12.3%;top:63%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M3 9h18"/><path d="M8 13h3"/></svg></span><b>CRM 事件</b><span>客户行为</span></div>
          <div class="nd" data-k="s5" style="left:12.7%;top:80%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9m0 0a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3m0-6a3 3 0 0 0-3 3v0a3 3 0 0 0 3 3"/><path d="M12 3a3 3 0 0 0-3 3m3-3a3 3 0 0 1 3 3"/></svg></span><b>Webhook</b><span>外部触发</span></div>
          <!-- 洞察分析层（4，错落） -->
          <div class="nd" data-k="i1" style="left:30.8%;top:17%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="3.5"/></svg></span><b>情感分析</b><span>AI 判读</span></div>
          <div class="nd" data-k="i2" style="left:31.2%;top:37%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.2 2.7-5 6-5s6 1.8 6 5"/><path d="M16 4.5a3.2 3.2 0 0 1 0 6.5M18 15.5c2 .8 3 2.3 3 4.5"/></svg></span><b>受众分群</b><span>CDP 画像</span></div>
          <div class="nd" data-k="i3" style="left:30.8%;top:57%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l3.5-4 3 2.5L18 8"/></svg></span><b>归因模型</b><span>来源归因</span></div>
          <div class="nd" data-k="i4" style="left:31.2%;top:77%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span><b>事件字典</b><span>行为语义</span></div>
          <!-- 内容生产层（3，错落） -->
          <div class="nd" data-k="c1" style="left:49.6%;top:22%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span><b>AI 生成</b><span>草稿待审</span></div>
          <div class="nd" data-k="c2" style="left:50.4%;top:48%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg></span><b>SEO·GEO</b><span>排名驱动</span></div>
          <div class="nd" data-k="c3" style="left:49.6%;top:74%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><b>动态内容</b><span>千人千面</span></div>
          <!-- 分发触达层（4，错落） -->
          <div class="nd" data-k="t1" style="left:68.6%;top:17%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v4H4zM4 14h16v4H4zM8 10v4M16 10v4"/></svg></span><b>旅程自动化</b><span>条件分支</span></div>
          <div class="nd" data-k="t2" style="left:69.4%;top:37%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span><b>多渠道分发</b><span>邮件 · SMS</span></div>
          <div class="nd" data-k="t3" style="left:68.6%;top:57%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 4.5A3.5 3.5 0 0 0 4 8v3.5a3.5 3.5 0 0 0 3.5 3.5H9l2.5 2.5v-2.5h1A3.5 3.5 0 0 0 16 11.5V8a3.5 3.5 0 0 0-3.5-3.5h-5Z"/></svg></span><b>企微公众号</b><span>私域触达</span></div>
          <div class="nd" data-k="t4" style="left:69.4%;top:77%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9.5C4 6.5 7 4 12 4s8 2.5 8 5.5-3 5.5-8 5.5-8-2.5-8-5.5Z"/><path d="M12 15v5m-4-2h8"/></svg></span><b>A/B 实验</b><span>版本优选</span></div>
          <!-- 转化增长层（3，错落） -->
          <div class="nd" data-k="v1" style="left:87.6%;top:22%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><b>CRM 跟进</b><span>销售协同</span></div>
          <div class="nd" data-k="v2" style="left:88.4%;top:48%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l3.5-4 3 2.5L18 8"/><path d="M14 8h4v4"/></svg></span><b>转化追踪</b><span>漏斗归因</span></div>
          <div class="nd" data-k="v3" style="left:87.6%;top:74%"><span class="nd-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16l-1.5 12h-13L4 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg></span><b>订阅电商</b><span>复购增长</span></div>
        </div>
        <div class="arena-driver">当前环节由 <span class="pill hl" id="arena-driver-name">DeepSeek</span> 驱动 · 点击气泡自由切换接入模型</div>
        <div class="arena-bubbles" id="arena-bubbles">
          <button type="button" class="bub on" data-m="DeepSeek" title="DeepSeek" aria-label="切换驱动模型：DeepSeek" data-od-id="bub-deepseek"><span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.8 14.5c.9-5.8 4.4-8.4 8.2-8.4 4.5 0 7.9 3 7 8.4-.7 4-3.5 5.6-7 5.6s-6.5-1.6-8.2-5.6Z"/><path d="M19 11.6c1.7-1.4 3.1-1.6 3.8-.5.5.9-.3 2.2-2.5 2.6"/></svg></span></button>
          <button type="button" class="bub" data-m="OpenAI" title="OpenAI" aria-label="切换驱动模型：OpenAI" data-od-id="bub-openai"><span class="mi"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><g><ellipse cx="12" cy="4.7" rx="2.3" ry="3.9"/><ellipse cx="12" cy="4.7" rx="2.3" ry="3.9" transform="rotate(60 12 12)"/><ellipse cx="12" cy="4.7" rx="2.3" ry="3.9" transform="rotate(120 12 12)"/><ellipse cx="12" cy="4.7" rx="2.3" ry="3.9" transform="rotate(180 12 12)"/><ellipse cx="12" cy="4.7" rx="2.3" ry="3.9" transform="rotate(240 12 12)"/><ellipse cx="12" cy="4.7" rx="2.3" ry="3.9" transform="rotate(300 12 12)"/></g></svg></span></button>
          <button type="button" class="bub" data-m="Claude" title="Claude" aria-label="切换驱动模型：Claude" data-od-id="bub-claude"><span class="mi"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.2 4.7 5.2.7-3.8 3.6.9 5.2-4.5-2.5-4.5 2.5.9-5.2-3.8-3.6 5.2-.7L12 3Z"/></svg></span></button>
          <button type="button" class="bub" data-m="通义千问" title="通义千问" aria-label="切换驱动模型：通义千问" data-od-id="bub-qwen"><span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5a8.5 8.5 0 1 0 8.5 8.5"/><path d="M12 12l5.6-5.6"/></svg></span></button>
          <button type="button" class="bub" data-m="智谱 GLM" title="智谱 GLM" aria-label="切换驱动模型：智谱 GLM" data-od-id="bub-glm"><span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3.5 20.5 12 12 20.5 3.5 12 12 3.5Z"/><path d="M12 8.2 15.8 12 12 15.8 8.2 12 12 8.2Z"/></svg></span></button>
          <button type="button" class="bub" data-m="Webhook" title="Webhook" aria-label="切换驱动模型：Webhook" data-od-id="bub-webhook"><span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9m0 0a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3m0-6a3 3 0 0 0-3 3v0a3 3 0 0 0 3 3"/><path d="M12 3a3 3 0 0 0-3 3m3-3a3 3 0 0 1 3 3"/></svg></span></button>
          <button type="button" class="bub" data-m="飞书企微" title="飞书 / 企微" aria-label="切换驱动模型：飞书企微" data-od-id="bub-feishu"><span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 4.5A3.5 3.5 0 0 0 4 8v3.5a3.5 3.5 0 0 0 3.5 3.5H9l2.5 2.5v-2.5h1A3.5 3.5 0 0 0 16 11.5V8a3.5 3.5 0 0 0-3.5-3.5h-5Z"/></svg></span></button>
          <button type="button" class="bub" data-m="自建系统" title="自建系统" aria-label="切换驱动模型：自建系统" data-od-id="bub-selfhost"><span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><ellipse cx="12" cy="5.5" rx="7.5" ry="2.8"/><path d="M4.5 5.5v6c0 1.5 3.4 2.8 7.5 2.8s7.5-1.3 7.5-2.8v-6"/><path d="M4.5 11.5v6c0 1.5 3.4 2.8 7.5 2.8s7.5-1.3 7.5-2.8v-6"/></svg></span></button>
        </div>
      </div>
    </div>
  </section>

  <!-- ══ 痛点共鸣 · 两个世界（静态三栏对照 · 鸿沟居中，去自动 Tab） ══ -->
  <section id="pain" class="sec reveal" data-od-anchor data-od-id="pain">
    <div class="sec-head center">
      <span class="kicker">你可能正在经历</span>
      <h2>同一年里，有人在裁员，有人在一个人开公司</h2>
    </div>
    <div class="worlds">
      <div class="w-col">
        <span class="w-tag">一边</span>
        <h3>一边在收缩</h3>
        <p class="w-q">「做企业的跟我说，账上现金撑不过六个月的，比例比疫情那两年还高。」投一百份简历，面试三个，一个 offer 都没有。</p>
      </div>
      <div class="w-col w-gap">
        <span class="w-tag">中间的鸿沟</span>
        <h3>而你卡在中间</h3>
        <p class="w-q">年营收 100–1000 万的一人公司，卡在同一个地方：知道 AI 重要，但不知道自己业务里哪一段该交出去。试了几个工具，最后都变成"还得我自己来"。</p>
      </div>
      <div class="w-col">
        <span class="w-tag">另一边</span>
        <h3>另一边在长出来</h3>
        <p class="w-q">2026 年开年，全国 26 个城市冒出 39 个 OPC 社区。一人公司、超级个体正在把「一个人就是一支队伍」变成现实。</p>
      </div>
    </div>
  </section>

  <!-- ══ TIPS 增长力 · 四力覆盖式滑动 Deck（下一块覆盖上一块） ══ -->
  <section id="touch" class="sec reveal" data-od-anchor data-od-id="touch">
    <div class="sec-head center">
      <span class="kicker">TIPS 框架 · 增长能力</span>
      <h2>这四件事，从今天起不用你做了</h2>
      <p class="lead">触达把变化送进来，洞察把判断递到你面前，个性化让内容贴着你的声线，销售把线索跑成成交。</p>
    </div>
    <div class="deck auto" id="tips-auto" data-auto="on" data-interval="4500">
      <div class="tab-bar" id="tips-tabs" role="tablist" aria-label="TIPS 四力">
        <button type="button" class="tab-p on" role="tab" id="tips-t1" aria-selected="true" aria-controls="tips-p1" data-t="p1"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9m0 0a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3m0-6a3 3 0 0 0-3 3v0a3 3 0 0 0 3 3"/><path d="M12 3a3 3 0 0 0-3 3m3-3a3 3 0 0 1 3 3"/></svg></span>触达 · Touch</button>
        <button type="button" class="tab-p" role="tab" id="tips-t2" aria-selected="false" aria-controls="tips-p2" data-t="p2"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10l2 2h6.5A1.5 1.5 0 0 1 20 7.5v11a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Z"/></svg></span>洞察 · Insight</button>
        <button type="button" class="tab-p" role="tab" id="tips-t3" aria-selected="false" aria-controls="tips-p3" data-t="p3"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>个性化 · Personality</button>
        <button type="button" class="tab-p" role="tab" id="tips-t4" aria-selected="false" aria-controls="tips-p4" data-t="p4"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12h8m-8 4h5M9 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></svg></span>销售 · Sales</button>
      </div>
      <div class="prog" aria-hidden="true"></div>
      <div class="deck-stage">
        <div class="deck-p on" id="tips-p1" role="tabpanel" aria-labelledby="tips-t1">
          <div class="sp-txt">
            <span class="kicker">触达 Touch · 信号自动进来</span>
            <h3>你不用再每天刷十几个后台</h3>
            <p class="lead">舆情、搜索热点、RSS 自动爬取。它不会替你决定，但会保证该看到的都送到你面前。</p>
            <ul class="sp-list">
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>舆情、搜索热点、RSS 自动爬取</div></li>
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>新信号实时汇入，按热度排序</div></li>
            </ul>
          </div>
          <div class="sp-vis">
            <div class="sp-win">
              <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">signals · 触达</div></div>
              <div class="sp-body">
                <div class="sp-sec"><span class="sp-sec-t">信号流 · 自动爬取</span>
                  <div class="flow-row"><div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9m0 0a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3m0-6a3 3 0 0 0-3 3v0a3 3 0 0 0 3 3"/><path d="M12 3a3 3 0 0 0-3 3m3-3a3 3 0 0 1 3 3"/></svg></div><div><div class="ft">RSS · 行业新闻</div><div class="fd">新信号 · 已总结</div></div><span class="pill neutral">中性</span></div>
                  <div class="flow-row"><div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V9M10 20V4M16 20v-8M20 20h1"/></svg></div><div><div class="ft">搜索热点 · 一人公司</div><div class="fd">热度上升 · 已总结</div></div><span class="pill neutral">正面</span></div>
                  <div class="flow-row"><div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></div><div><div class="ft">社区讨论 · Agent 化</div><div class="fd">新话题 · 待分析</div></div><span class="pill neutral">待分析</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="deck-p" id="tips-p2" role="tabpanel" aria-labelledby="tips-t2">
          <div class="sp-txt">
            <span class="kicker">洞察 Insight · AI 提炼该做什么</span>
            <h3>只把「值得你判断的」递到面前</h3>
            <p class="lead">热点总结、选题建议自动生成。不是给你 37 条线索，是告诉你今天该联系哪 3 个。</p>
            <ul class="sp-list">
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>热点总结与选题建议</div></li>
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>情绪与热度标注，决策项待你确认</div></li>
            </ul>
          </div>
          <div class="sp-vis">
            <div class="sp-win">
              <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">insight · 洞察提炼</div></div>
              <div class="sp-body">
                <div class="sp-sec"><span class="sp-sec-t">AI 洞察</span>
                  <div class="sp-insight">本周高频词：「一人公司」「Agent 化」「销转率」。AI 已生成选题方向，<span class="mono">待你确认</span>。</div>
                </div>
                <div class="sp-sec"><span class="sp-sec-t">决策项</span>
                  <div class="sp-card"><div class="sp-card-t">选题方向 · 内容获客</div><div class="sp-card-m"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg>热点总结 <span class="ckd">已生成</span></div></div>
                  <div class="sp-card"><div class="sp-card-t">内容排期 · 本周</div><div class="sp-card-m"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg>发布计划 <span class="ckd">待确认</span></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="deck-p" id="tips-p3" role="tabpanel" aria-labelledby="tips-t3">
          <div class="sp-txt">
            <span class="kicker">个性化 Personality · 内容贴合声线</span>
            <h3>草稿不是模板，是贴着你的品牌声线生成的</h3>
            <p class="lead">文章草稿、语气模板、定稿发布——你改的是稿子，不是从空白页开始。</p>
            <ul class="sp-list">
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>文章草稿 · 语气模板 · 品牌声线</div></li>
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>定稿发布 · 排期自动</div></li>
            </ul>
          </div>
          <div class="sp-vis">
            <div class="sp-win">
              <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">persona · 个性化</div></div>
              <div class="sp-body">
                <div class="sp-sec"><span class="sp-sec-t">草稿 · 已匹配声线</span>
                  <div class="sp-card">
                    <div class="sp-card-t">为什么一人公司该有自己的增长系统</div>
                    <div class="sp-card-m"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg>语气模板 · 品牌声线 <span class="ckd">已匹配</span></div>
                    <div class="tags"><span>待审</span><span>发布排期 · 自动</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="deck-p" id="tips-p4" role="tabpanel" aria-labelledby="tips-t4">
          <div class="sp-txt">
            <span class="kicker">销售 Sales · 主动触达与转化</span>
            <h3>内容带来的人，别停在"看过"</h3>
            <p class="lead">MA 流程、线索跟进、sales loop——触达不是群发，是按节奏推进的每一次跟进。</p>
            <ul class="sp-list">
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>MA 流程 · 线索培育与跟进</div></li>
              <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span><div>转化数据回传，反哺每一环</div></li>
            </ul>
          </div>
          <div class="sp-vis">
            <div class="sp-win">
              <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">sales · 主动触达</div></div>
              <div class="sp-body">
                <div class="sp-sec"><span class="sp-sec-t">Sales Loop · 主动触达</span>
                  <div class="flow-row"><div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></div><div><div class="ft">MA 流程 · 线索培育</div><div class="fd">触发条件已配置</div></div><span class="st"></span></div>
                  <div class="flow-row"><div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></div><div><div class="ft">触达 · 私信 / 邮件</div><div class="fd">按节奏推进</div></div><span class="st"></span></div>
                  <div class="flow-row"><div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg></div><div><div class="ft">转化 · sales loop</div><div class="fd">回传数据</div></div><span class="st"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="deck-cta"><a class="btn subtle" href="#loop" data-od-id="tips-cta">看它怎么跑成闭环 →</a></div>
    </div>
  </section>

  <!-- ══ 三步闭环 ══ -->
  <section id="loop" class="sec reveal" data-od-anchor data-od-id="loop">
    <div class="sec-head center">
      <span class="kicker">从 flow 到 loop</span>
      <h2>三步，把它接进你现在的活法</h2>
    </div>
    <div class="wf">
      <div class="wf-step"><span class="wf-n">01</span><h3>告诉它盯什么</h3><p>接入舆情、搜索热点、你自己的客户行为。配一次，之后它自己盯。</p><span class="wf-driver"><span class="pill hl">Webhook · RSS 接入</span></span></div>
      <div class="wf-step"><span class="wf-n">02</span><h3>告诉它什么该自动、什么等你点头</h3><p>选题、撰写、触达、转化，每一段你都能决定是它直接做，还是出草稿等你确认。</p><span class="wf-driver"><span class="pill hl">Task Graph 编排</span></span></div>
      <div class="wf-step"><span class="wf-n">03</span><h3>然后你就可以去干别的了</h3><p>按你设的周期自己跑一轮：洞察 → 优化 → 转化 → 反馈。你回来看结果，不用盯过程。</p><span class="wf-driver"><span class="pill hl">AI Engine · 周期你定</span></span></div>
    </div>
  </section>



  <!-- ══ 应用场景 · tab 聚合（疲劳点上的再刺激） ══ -->
  <section id="scenes" class="sec reveal" data-od-anchor data-od-id="scenes">
    <div class="sec-head center">
      <span class="kicker">谁在用它</span>
      <h2>这些场景里，Agent 正在替人跑增长</h2>
      <p class="lead">不是给大厂用的复杂系统，是给一人公司和超级个体的增长引擎。</p>
    </div>
    <div>
      <div class="tab-bar" id="scene-tabs" role="tablist" aria-label="应用场景">
        <button type="button" class="tab-p" role="tab" id="scene-t1" aria-selected="true" aria-controls="scene-p1" data-t="p1"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>一人公司冷启动</button>
        <button type="button" class="tab-p" role="tab" id="scene-t2" aria-selected="false" aria-controls="scene-p2" data-t="p2"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><circle cx="12" cy="12" r="3.2"/><path d="m6 6 3.2 3.2M17.8 14.8 21 18"/></svg></span>超级个体精算增长</button>
        <button type="button" class="tab-p" role="tab" id="scene-t3" aria-selected="false" aria-controls="scene-p3" data-t="p3"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>小团队自动化运营</button>
        <button type="button" class="tab-p" role="tab" id="scene-t4" aria-selected="false" aria-controls="scene-p4" data-t="p4"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 7-5 5 5 5m8-10 5 5-5 5M13 4l-2 16"/></svg></span>开发者搭建中台</button>
      </div>
      <div class="tab-panels">
        <div class="tab-panel on" id="scene-p1" role="tabpanel" aria-labelledby="scene-t1">
          <div class="tp-txt">
            <span class="kicker">最典型场景</span>
            <h3>一人公司冷启动</h3>
            <p>刚开始做，最怕的是不知道写什么。它替你盯住行业在聊什么，把选题和草稿备好——你从"改"开始，不从"想"开始。</p>
            <div class="tags"><span>内容获客</span><span>线索转化</span></div>
            <div class="cta-row"><a class="btn subtle" href="#loop" data-od-id="scene-featured-cta">看完整场景 →</a></div>
          </div>
          <div class="tp-steps">
            <div class="tp-step"><span class="tp-n">01</span><div><b>接入行业信号</b><span>舆情 / RSS / 搜索热点自动爬取</span></div></div>
            <div class="tp-step"><span class="tp-n">02</span><div><b>生成内容草稿</b><span>贴合声线的文章与选题，待你确认</span></div></div>
            <div class="tp-step"><span class="tp-n">03</span><div><b>主动触达转化</b><span>MA 流程推进线索，从内容到成交</span></div></div>
          </div>
        </div>
        <div class="tab-panel" id="scene-p2" role="tabpanel" aria-labelledby="scene-t2">
          <div class="tp-txt">
            <span class="kicker">利润公式 · 数据驱动</span>
            <h3>超级个体精算增长</h3>
            <p>你已经有流量了，卡在转化。把获客、培育、转化每一环的数拆开摆出来，哪一环漏得最多、哪一环最值得交给 Agent，一眼看得见。</p>
            <div class="tags"><span>数据洞察</span><span>四引擎</span></div>
            <div class="cta-row"><a class="btn subtle" href="#loop" data-od-id="scene-cta-profit">看利润公式怎么拆 →</a></div>
          </div>
          <div class="tp-steps">
            <div class="tp-step"><span class="tp-n">01</span><div><b>拆解利润公式</b><span>把增长拆成可计算的环节</span></div></div>
            <div class="tp-step"><span class="tp-n">02</span><div><b>标出 Agent 环节</b><span>收益最高的是哪一环</span></div></div>
            <div class="tp-step"><span class="tp-n">03</span><div><b>逐个环节跑通</b><span>四引擎协同，从获客到转化</span></div></div>
          </div>
        </div>
        <div class="tab-panel" id="scene-p3" role="tabpanel" aria-labelledby="scene-t3">
          <div class="tp-txt">
            <span class="kicker">工作流 · 流程自动化</span>
            <h3>小团队自动化运营</h3>
            <p>三五个人，一半精力耗在重复运营上。周报、监控、跨群通知这些活交出去，把人留给真正需要判断的事。</p>
            <div class="tags"><span>自动化</span><span>MA 流程</span></div>
            <div class="cta-row"><a class="btn subtle" href="#loop" data-od-id="scene-cta-workflow">看工作流怎么配 →</a></div>
          </div>
          <div class="tp-steps">
            <div class="tp-step"><span class="tp-n">01</span><div><b>接入周报与监控</b><span>数据源自动汇集</span></div></div>
            <div class="tp-step"><span class="tp-n">02</span><div><b>配置工作流规则</b><span>谁触发、跑什么、通知谁</span></div></div>
            <div class="tp-step"><span class="tp-n">03</span><div><b>自动执行与通知</b><span>人只处理异常与决策</span></div></div>
          </div>
        </div>
        <div class="tab-panel" id="scene-p4" role="tabpanel" aria-labelledby="scene-t4">
          <div class="tp-txt">
            <span class="kicker">开源底座 · 私有化</span>
            <h3>开发者搭建增长中台</h3>
            <p>你不想被别人的 SaaS 绑住。MIT 开源、32 个插件钩子、18 个 MCP 工具——拿去当底座，改成你自己的。</p>
            <div class="tags"><span>私有化</span><span>API</span></div>
            <div class="cta-row"><a class="btn subtle" href="#compare" data-od-id="scene-cta-dev">看底座怎么扩展 →</a></div>
          </div>
          <div class="tp-steps">
            <div class="tp-step"><span class="tp-n">01</span><div><b>私有化部署</b><span>数据与代码都在自己手里</span></div></div>
            <div class="tp-step"><span class="tp-n">02</span><div><b>API 集成现有系统</b><span>连接器 + Webhook 开箱即用</span></div></div>
            <div class="tp-step"><span class="tp-n">03</span><div><b>基于底座二次开发</b><span>Skill 生态，按需扩展</span></div></div>
          </div>
        </div>
      </div>
    </div>
  </section>



  <!-- ══ 对比：OpenFlow 底座 vs 单点工具（垃圾时间收束） ══ -->
  <section id="compare" class="sec reveal" data-od-anchor data-od-id="compare">
    <div class="sec-head center">
      <span class="kicker">为什么不是一个「五合一」按钮</span>
      <h2>OpenFlow 底座 vs 单点工具</h2>
      <p class="lead">分开买 = 五个账号、五套数据、五次打通，还有五份等着人来喂的活。芭乐派把这五块放在同一套数据上——不是集成，是本来就是一个。</p>
    </div>
    <div class="cmp-wrap">
      <table class="cmp">
        <thead>
          <tr><th scope="col">能力维度</th><th scope="col">内容引擎 CMS</th><th scope="col">营销自动化 MA</th><th scope="col">客户数据 CDP</th><th scope="col">线索 CRM</th><th scope="col">SEO · GEO 引擎</th><th scope="col" class="ol">OpenFlow 底座</th></tr>
        </thead>
        <tbody>
          <tr><th scope="row">内容发布与管理</th><td class="y" data-l="内容引擎 CMS">原生</td><td class="na" data-l="营销自动化 MA">—</td><td class="na" data-l="客户数据 CDP">—</td><td class="na" data-l="线索 CRM">—</td><td class="na" data-l="SEO · GEO 引擎">—</td><td class="ol y" data-l="OpenFlow 底座">写完即被其余四块共用</td></tr>
          <tr><th scope="row">营销自动化</th><td class="na" data-l="内容引擎 CMS">—</td><td class="y" data-l="营销自动化 MA">原生</td><td class="na" data-l="客户数据 CDP">—</td><td class="na" data-l="线索 CRM">—</td><td class="na" data-l="SEO · GEO 引擎">—</td><td class="ol y" data-l="OpenFlow 底座">拖出流程，直接读得到画像</td></tr>
          <tr><th scope="row">客户数据与分群</th><td class="na" data-l="内容引擎 CMS">—</td><td class="na" data-l="营销自动化 MA">—</td><td class="y" data-l="客户数据 CDP">原生</td><td class="na" data-l="线索 CRM">—</td><td class="na" data-l="SEO · GEO 引擎">—</td><td class="ol y" data-l="OpenFlow 底座">匿名到成交是同一个人</td></tr>
          <tr><th scope="row">搜索与 AI 优化</th><td class="na" data-l="内容引擎 CMS">—</td><td class="na" data-l="营销自动化 MA">—</td><td class="na" data-l="客户数据 CDP">—</td><td class="na" data-l="线索 CRM">—</td><td class="y" data-l="SEO · GEO 引擎">原生</td><td class="ol y" data-l="OpenFlow 底座">发布时就已经优化好</td></tr>
          <tr><th scope="row">线索与转化</th><td class="na" data-l="内容引擎 CMS">—</td><td class="na" data-l="营销自动化 MA">—</td><td class="na" data-l="客户数据 CDP">—</td><td class="y" data-l="线索 CRM">原生</td><td class="na" data-l="SEO · GEO 引擎">—</td><td class="ol y" data-l="OpenFlow 底座">成交结果反哺前面四块</td></tr>
          <tr><th scope="row">跨模块数据打通</th><td class="na" data-l="内容引擎 CMS">需集成</td><td class="na" data-l="营销自动化 MA">需集成</td><td class="na" data-l="客户数据 CDP">需集成</td><td class="na" data-l="线索 CRM">需集成</td><td class="na" data-l="SEO · GEO 引擎">需集成</td><td class="ol y" data-l="OpenFlow 底座">一套数据 · 原生打通</td></tr>
        </tbody>
      </table>
      <p class="cmp-note">对比口径：单点工具以各自官方能力描述为准，「—」表示该工具不覆盖此维度，「需集成」表示依赖第三方打通。OpenFlow 定位为组合式开源底座，各模块共用一套数据层。</p>
    </div>
  </section>

  <!-- ══ 为谁而做 ══ -->
  <section id="reviews" class="sec reveal" data-od-anchor data-od-id="reviews">
    <div class="sec-head center">
      <span class="kicker">你属于哪一类</span>
      <h2>为这些一人公司而设计</h2>
    </div>
    <div class="qr">
      <div class="q-i"><div class="av">内</div><blockquote>「每天花 3 小时找选题、改文章，真正该做的判断反而没时间做。」</blockquote><div class="who"><div><b>内容型一人公司</b><span>靠内容获客，卡在产出</span></div></div></div>
      <div class="q-i"><div class="av">知</div><blockquote>「知道增长该做什么，但哪些交给 Agent、哪些必须自己来，一直没想清楚。」</blockquote><div class="who"><div><b>知识付费操盘手</b><span>想跑通销转，缺一套系统</span></div></div></div>
      <div class="q-i"><div class="av">团</div><blockquote>「4 人小团队，周报、监控、跨群通知全靠人肉，运维就吃掉一半精力。」</blockquote><div class="who"><div><b>SaaS 服务团队</b><span>被重复运营拖住</span></div></div></div>
    </div>
    <p class="cmp-note" style="text-align:center;margin-top:16px">不适合谁也说清楚：已经有成熟市场团队和数据团队的公司，用我们是重复建设。</p>
  </section>

  <!-- ══ 增长洞察（原 JS 注入 → SSR） ══ -->
  <section id="insights" class="sec reveal" data-od-anchor data-od-id="insights">
    <div class="sec-head center">
      <span class="kicker">增长洞察</span>
      <h2>关于增长系统与 Agent 的思考</h2>
    </div>
    <div class="art-list">
      <div id="homeArts"></div>
    </div>
  </section>

  <!-- ══ 预约诊断（原 60+ 行 inline style → .field/.inp 模块） ══ -->
  <section id="contact" class="reveal" data-od-anchor data-od-id="contact">
    <div class="contact-wrap">
      <div class="ct-pitch">
        <span class="kicker">O.L.B 增长诊断</span>
        <h2>30 分钟，告诉你哪一段最该交给 Agent</h2>
        <p class="lead">用 O.L.B 评分卡把你的增长链拆开，指出漏得最多的那一环，和最该先交出去的那一段。</p>
        <ul class="ct-list">
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span>O.L.B 评分卡：三分钟自查增长健康度</li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span>标出 Agent 化收益最高的环节</li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span>带走一份可执行的改造顺序</li>
        </ul>
      </div>
      <div class="form-card">
        <form id="lead-form" data-lead-form novalidate class="form-grid">
          <div class="grid g2">
            <div class="field"><label for="ld-name">姓名 *</label><input class="inp" id="ld-name" name="name" required placeholder="怎么称呼您" autocomplete="name"></div>
            <div class="field"><label for="ld-company">企业名称 *</label><input class="inp" id="ld-company" name="company" required placeholder="公司 / 组织" autocomplete="organization"></div>
          </div>
          <div class="grid g2">
            <div class="field"><label for="ld-title">职位</label><input class="inp" id="ld-title" name="title" placeholder="如 CMO / 增长负责人"></div>
            <div class="field"><label for="ld-contact">手机或邮箱 *</label><input class="inp" id="ld-contact" name="contact" required placeholder="手机或邮箱" autocomplete="email"></div>
          </div>
          <div class="field"><label for="ld-note">想解决的问题</label><textarea class="inp" id="ld-note" name="note" rows="3" placeholder="简单描述当前网站增长遇到的情况"></textarea></div>
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div id="form-msg" role="status" aria-live="polite"></div>
          <div class="f-row">
            <button type="submit" class="btn primary" data-od-id="lead-submit">提交预约 →</button>
            <span class="f-note">提交后进入顾问队列，1 个工作日内联系</span>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- ══ footer ══ -->
  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><defs><linearGradient id="ofg-f" x1="2" y1="16" x2="30" y2="16" gradientUnits="userSpaceOnUse"><stop stop-color="var(--accent)"/><stop offset="1" stop-color="oklch(58% .16 285)"/></linearGradient></defs><path d="M16 6.5a9.5 9.5 0 1 1-9.5 9.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/><path d="M11.5 10v13M11.5 13.5h8.2M11.5 18.5h8.2" stroke="url(#ofg-f)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M19.7 18.5c2.3 0 4.4-.7 6.1-2M25 14.3l1.6 2.2-2.9 1" stroke="url(#ofg-f)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派给一人公司的增长系统。它自己爬信号、自己出草稿、自己盯该跟进谁——你只做判断，不做事。核心能力永久开源。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
      <div class="f-social" aria-label="社交媒体">
        <div class="soc-group">
          <a class="soc" href="https://github.com/balepai/openflow" target="_blank" rel="noopener" data-od-id="soc-github" aria-label="GitHub 开源仓库"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.9-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.9 1.52 2.34 1.08 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.94 0-1.1.39-1.99 1.03-2.69-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02a9.58 9.58 0 0 1 5 0c1.91-1.3 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.6 1.03 2.69 0 3.84-2.34 4.68-4.57 4.93.36.31.68.92.68 1.85V21c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"/></svg></a>
          <a class="soc" href="#" data-od-id="soc-x" aria-label="X 官方账号"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L2 3h6.4l4.4 5.9L17.8 3Zm-1.1 16.1h1.7L7.6 4.8H5.8l10.9 14.3Z"/></svg></a>
          <a class="soc" href="#" data-od-id="soc-youtube" aria-label="YouTube 频道"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg></a>
        </div>
        <span class="soc-div" aria-hidden="true"></span>
        <div class="soc-group">
          <a class="soc" href="#" data-od-id="soc-wechat" aria-label="微信公众号"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.6 4.5C5.9 4.5 3 6.9 3 9.9c0 1.7 1 3.2 2.5 4.2l-.6 2.2 2.4-1.2c.7.2 1.5.3 2.3.3h.5a5.8 5.8 0 0 1-.4-2c0-3 2.9-5.4 6.5-5.4h.4C15.9 6.2 13 4.5 9.6 4.5Zm-2.4 3.4a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.8 0a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Z"/><path d="M21 14.5c0-2.5-2.4-4.5-5.4-4.5s-5.4 2-5.4 4.5 2.4 4.5 5.4 4.5c.5 0 1-.1 1.4-.2l1.9 1-.5-1.7c1.5-.8 2.6-2.2 2.6-3.6Z"/></svg></a>
          <a class="soc" href="#" data-od-id="soc-bilibili" aria-label="B 站账号"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3.2" y="7" width="17.6" height="12.5" rx="2.8"/><path d="M9.2 4 7.8 6.4M14.8 4l1.4 2.4M7.8 11v3.4M12 11v3.4"/></svg></a>
          <a class="soc" href="#" data-od-id="soc-zhihu" aria-label="知乎机构号"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.5 4.5h13l-7.5 12.5h4.5v2.5H5.8l.8-2.4h2.6L12.6 9H5.5v-4.5Z"/></svg></a>
        </div>
      </div>
    </div>
    <div class="fb">
      <h4>站点导航</h4>
      <a href="#touch" data-od-id="f-product">产品</a><a href="#compare" data-od-id="f-capability">能力</a><a href="#insights" data-od-id="f-courses">课程</a><a href="#insights" data-od-id="f-academy">学院</a><a href="#reviews" data-od-id="f-community">论坛</a><a href="#contact" data-od-id="f-about">关于我们</a>
    </div>
    <div class="fb">
      <h4>资源</h4>
      <a href="#insights" data-od-id="f-r-courses">芭乐派课程</a><a href="#top" data-od-id="f-r-docs">文档中心</a><a href="#top" data-od-id="f-r-tpl">模板库</a><a href="#top" data-od-id="f-r-api">开放 API</a>
    </div>
    <div class="fb">
      <h4>联系</h4>
      <a href="mailto:hello@openflow.dev" data-od-id="f-mail">hello@openflow.dev</a><a href="#contact" data-od-id="f-biz">商务合作</a><a href="#contact" data-od-id="f-team">加入团队</a><a href="#reviews" data-od-id="f-community-2">门派社区</a>
    </div>
    <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>给一人公司的增长系统</span></div>
  </footer>
</main>



<script>
/* ────────────────────────────────────────────────────────────────
 * index.php · 页面级脚本
 * 主题 / 侧栏状态机 / 命令面板 / 登录注册 / 个人中心 / 滚动胶囊
 * 已全部移交 assets/site-shell.js，本文件只保留首页自己的交互。
 * 导航数据来自 data/nav.json，页面不再硬编码任何导航项。
 * ──────────────────────────────────────────────────────────────── */
(function(){
  var $=function(s,c){return (c||document).querySelector(s)};
  var $$=function(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s))};
  var RM=false; try{RM=matchMedia('(prefers-reduced-motion: reduce)').matches;}catch(e){}
  /* 与共享外壳「减少动效」开关共享的定时器池：site-shell 打开该开关时会 clearInterval 全部 */
  var __timers = window.__timers = window.__timers || [];
  if(RM)document.documentElement.classList.add('rm');

  /* ── 预约表单（真实提交 /api/form-submit） ── */
  var form=$('#lead-form'),msg=$('#form-msg');
  form.addEventListener('submit',function(e){
    e.preventDefault();
    if(!form.checkValidity()){form.reportValidity();return;}
    var btn=form.querySelector('button[type="submit"]');
    var fd=new FormData(form);
    fd.append('page',location.pathname||'index.html');
    fd.append('form_slug','appointment');
    btn.disabled=true;btn.textContent='提交中…';
    msg.style.display='block';msg.style.color='var(--muted)';msg.textContent='正在提交…';
    fetch('/api/form-submit',{method:'POST',body:fd,headers:{'Accept':'application/json'}})
      .then(function(r){return r.json().catch(function(){return {};});})
      .then(function(d){
        if(d&&d.ok){
          btn.disabled=true;btn.textContent='✅ 已提交';
          msg.style.color='var(--ok)';msg.textContent='✅ 已提交，顾问将在 1 个工作日内与您联系。';
          form.reset();
          if(window.fcTrack)fcTrack('form_submit',{form:'lead',page:location.pathname});
        }else{
          btn.disabled=false;btn.textContent='提交预约 →';
          msg.style.color='var(--danger)';msg.textContent=(d&&d.error)||'提交失败，请稍后再试';
        }
      })
      .catch(function(){btn.disabled=false;btn.textContent='提交预约 →';msg.style.color='var(--danger)';msg.textContent='网络异常，请稍后再试';});
  });

  /* ── 场景 tab 聚合（roving tabindex + 方向键） ── */
  var sceneBar=$('#scene-tabs');
  if(sceneBar){
    var stabs=$$('.tab-p',sceneBar),spanels={};
    stabs.forEach(function(t){spanels[t.dataset.t]=document.getElementById('scene-'+t.dataset.t);t.tabIndex=-1;});
    stabs[0].tabIndex=0;
    function sceneSel(t){
      stabs.forEach(function(x){
        var on=x===t;
        x.setAttribute('aria-selected',on?'true':'false');
        x.tabIndex=on?0:-1;
      });
      Object.keys(spanels).forEach(function(k){spanels[k].classList.toggle('on',k===t.dataset.t);});
    }
    stabs.forEach(function(t,i){
      t.addEventListener('click',function(){sceneSel(t);});
      t.addEventListener('keydown',function(e){
        var n;
        if(e.key==='ArrowRight'){n=(i+1)%stabs.length;}
        else if(e.key==='ArrowLeft'){n=(i-1+stabs.length)%stabs.length;}
        else{return;}
        e.preventDefault();
        sceneSel(stabs[n]);stabs[n].focus();
      });
    });
  }

  /* ── 自动切换 Tab（pain 三 Tab）+ 覆盖式滑动 Deck（TIPS 四力） ── */
  function autoTabs(boxId){
    var box=document.getElementById(boxId);if(!box)return;
    var pre=boxId.replace('-auto','');
    var bar=document.getElementById(pre+'-tabs');if(!bar)return;
    var tabs=$$('.tab-p',bar),panels={},idx=0,timer=null,paused=false;
    tabs.forEach(function(t){panels[t.dataset.t]=document.getElementById(pre+'-'+t.dataset.t);t.tabIndex=-1;});
    if(tabs[0])tabs[0].tabIndex=0;
    var AUTO=box.dataset.auto==='on',IV=parseInt(box.dataset.interval||4500,10);
    function sel(t){
      tabs.forEach(function(x){var on=x===t;x.setAttribute('aria-selected',on?'true':'false');x.tabIndex=on?0:-1;});
      Object.keys(panels).forEach(function(k){panels[k].classList.toggle('on',k===t.dataset.t);});
      if(AUTO){box.dataset.auto='off';void box.offsetWidth;box.dataset.auto='on';}
    }
    function start(){if(!AUTO||RM||paused)return;stop();timer=setInterval(function(){idx=(idx+1)%tabs.length;sel(tabs[idx]);},IV);__timers.push(timer);}
    function stop(){if(timer){clearInterval(timer);timer=null;}}
    function pause(){paused=true;stop();box.dataset.paused='true';}
    function resume(){paused=false;box.dataset.paused='';start();}
    tabs.forEach(function(t,i){
      t.addEventListener('click',function(){idx=i;sel(t);resume();});
      t.addEventListener('keydown',function(e){
        var n;
        if(e.key==='ArrowRight'){n=(i+1)%tabs.length;}
        else if(e.key==='ArrowLeft'){n=(i-1+tabs.length)%tabs.length;}
        else{return;}
        e.preventDefault();idx=n;sel(tabs[n]);tabs[n].focus();resume();
      });
      t.addEventListener('focus',pause);
      t.addEventListener('blur',resume);
    });
    box.addEventListener('mouseenter',pause);
    box.addEventListener('mouseleave',resume);
    start();
  }
  autoTabs('tips-auto');

  /* ── 首屏交互标题（旋转关键词 · 自动轮换 + 点击切换） ── */
  var hrWord=$('#hr-word');
  if(hrWord){
    var WORDS=['该做什么','盯什么信号','改哪篇稿子','联系哪个人','先做哪一步'],wi=0;
    function setWord(i){wi=(i+WORDS.length)%WORDS.length;hrWord.textContent=WORDS[wi];}
    hrWord.addEventListener('click',function(){setWord(wi+1);});
    hrWord.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();setWord(wi+1);}});
    if(!RM){__timers.push(setInterval(function(){setWord(wi+1);},2400));}
  }

  /* ── 场景竞技场：多分支画布节点脉冲 + 模型 logo 徽章切换驱动 ── */
  var aStages=$$('#arena-stage .nd'),aBubs=$$('#arena-bubbles .bub');
  if(aStages.length&&aBubs.length){
    var ai=0,driver=$('#arena-driver-name');
    function stageTick(){aStages[ai].classList.remove('on');ai=(ai+1)%aStages.length;aStages[ai].classList.add('on');}
    function setDriver(b){aBubs.forEach(function(x){var on=x===b;x.classList.toggle('on',on);x.setAttribute('aria-pressed',on?'true':'false');});driver.textContent=b.dataset.m;}
    aBubs.forEach(function(b){b.addEventListener('click',function(){setDriver(b);});});
    setDriver(aBubs[0]);
    if(!RM){__timers.push(setInterval(stageTick,Math.max(850,Math.round(22000/aStages.length))));}
  }


  /* ── 滚动显现动画（.reveal 进入视口 → 加 .in） ── */
  if ('IntersectionObserver' in window) {
    var rvIO = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if (en.isIntersecting) { en.target.classList.add('in'); rvIO.unobserve(en.target); }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    $$('.reveal').forEach(function(el){ rvIO.observe(el); });
    // 兜底：2.5 秒后仍未显现的模块直接显示（防止 IntersectionObserver 异常导致白屏）
    setTimeout(function(){
      $$('.reveal:not(.in)').forEach(function(el){ el.classList.add('in'); });
    }, 2500);
  } else {
    $$('.reveal').forEach(function(el){ el.classList.add('in'); });
  }

})();
</script>
<!-- 角色化内容 + 角色切换 -->
<script src="/assets/role-content.js?v=3"></script>
<script src="/assets/role-switch.js?v=4"></script>
</body>
</html>
