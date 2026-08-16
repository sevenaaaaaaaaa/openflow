/* OpenFlow site theme — tokens strictly from brand-spec.md (PDF extraction).
   Light system: 薄紫灰底 + 白面 + 品牌紫 accent + 暖金点睛。No dark hero. */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        // Canvas / surface / text — light, airy (PDF posture rule 3)
        bg:      '#ffffff',  // --bg 浅紫灰底
        cream:   '#ffffff',  // legacy alias -> light canvas (NOT beige)
        surface: '#ffffff',  // --surface
        ink:     '#000000',  // --fg 深紫黑字（仅作文字/深块，不再作整屏底色）
        ink2:    '#fff5e0',  // 浅紫块，替代原深色内层
        // Accent family (品牌紫)
        accent:  '#ffb110',  // --accent 品牌主紫
        jade:    '#ffb110',  // legacy alias -> 品牌紫（原为绿色，纠正）
        flow:    '#e89d01',  // 深紫（hover / 层级）
        mint:    '#ffc95e',  // 浅紫（次要点缀）
        soft:    '#fff5e0',  // 浅紫块
        // Gold — 次强调，仅点睛
        sun:     '#e89d01',  // --gold
        // Neutrals
        muted:   '#666666',  // --muted
        line:    '#f2f9ff',  // --border
        coral:   '#f77463',  // 版权标签（暖，非红）
      },
      maxWidth: { site: '1180px' },
      fontFamily: {
        sans: ['"Inter"', '"PingFang SC"', '"Noto Sans SC"', '"Source Han Sans SC"', 'system-ui', 'sans-serif'],
        num: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        card: '0 8px 28px oklch(0.35 0.06 295 / 0.10)',
        lift: '0 24px 60px oklch(0.35 0.08 295 / 0.14)',
      },
    },
  },
};

(function () {
  if (document.getElementById('fc-site-css')) return;
  var css = document.createElement('style');
  css.id = 'fc-site-css';
  css.textContent = [
    '@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap");',
    'html{scroll-behavior:smooth}',
    'body{background:#ffffff;color:#000000;-webkit-font-smoothing:antialiased}',
    /* NAV — light, sticky, blur on scroll */
    '#nav{background:transparent;backdrop-filter:none}',
    '.nav-link{color:rgba(26,22,37,.62);font-weight:500;transition:color .2s,background .2s;padding:8px 12px;border-radius:999px}',
    '.nav-link:hover,.nav-link[aria-current="page"]{color:#000000;background:color-mix(in oklch,#ffb110 10%,transparent)}',
    '#nav.scrolled{background:color-mix(in oklch,#ffffff 88%,transparent);backdrop-filter:blur(16px) saturate(1.2);box-shadow:0 1px 0 #f2f9ff,0 6px 20px oklch(0.35 0.05 295 / 0.07)}',
    '.brand-text{color:#000000}',
    '.burger{color:#000000}',
    /* Buttons */
    '.btn-ghost{display:inline-flex;align-items:center;gap:8px;min-height:48px;padding:12px 22px;border-radius:999px;border:1px solid #f2f9ff;background:#fff;color:#000000;font-weight:600;font-size:15px;transition:.2s}',
    '.btn-ghost:hover{border-color:#ffb110;color:#ffb110}',
    '.btn-ink{display:inline-flex;align-items:center;gap:8px;min-height:48px;padding:12px 22px;border-radius:999px;background:#ffb110;color:#fff;font-weight:600;font-size:15px;box-shadow:0 10px 28px rgba(255,177,16,.30);transition:.2s}',
    '.btn-ink:hover{background:#e89d01;transform:translateY(-1px)}',
    '.btn-ink:active{transform:translateY(1px) scale(.98);box-shadow:0 4px 12px rgba(0,0,0,.15)}',
    '.btn-ghost:active{transform:scale(.97)}',
    '.btn-ink:focus-visible,.btn-ghost:focus-visible,.btn-line:focus-visible{outline:2px solid #ffb110;outline-offset:2px}',
    '.eyebrow{font-family:"JetBrains Mono",ui-monospace,monospace;font-size:12px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#ffb110}',
    '.grid-bg{background-image:linear-gradient(rgba(0,0,0,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,0,0,.04) 1px,transparent 1px);background-size:48px 48px}',
    '.blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:.42;pointer-events:none;animation:blob 18s ease-in-out infinite alternate}',
    '@keyframes blob{from{transform:translate(0,0) scale(1)}to{transform:translate(24px,-18px) scale(1.08)}}',
    '.reveal{opacity:0;transform:translateY(24px);transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1)}',
    '.reveal.in{opacity:1;transform:none}',
    /* Hero stagger — children reveal in sequence */
    '.hero-stagger > *{opacity:0;transform:translateY(20px);transition:opacity .6s cubic-bezier(.16,1,.3,1),transform .6s cubic-bezier(.16,1,.3,1)}',
    '.hero-stagger.in > *:nth-child(1){opacity:1;transform:none;transition-delay:.05s}',
    '.hero-stagger.in > *:nth-child(2){opacity:1;transform:none;transition-delay:.15s}',
    '.hero-stagger.in > *:nth-child(3){opacity:1;transform:none;transition-delay:.25s}',
    '.hero-stagger.in > *:nth-child(4){opacity:1;transform:none;transition-delay:.35s}',
    '.hero-stagger.in > *:nth-child(5){opacity:1;transform:none;transition-delay:.45s}',
    '.hero-stagger.in > *:nth-child(6){opacity:1;transform:none;transition-delay:.55s}',
    '.font-num{font-family:"JetBrains Mono",ui-monospace,monospace;font-variant-numeric:tabular-nums}',
    /* Form */
    '.lbl{display:block;font-size:13.5px;font-weight:600;color:#000000;margin-bottom:7px}',
    '.inp{width:100%;border:1px solid #f2f9ff;border-radius:14px;background:#fff;padding:12px 14px;font-size:14.5px;outline:none;transition:border-color .2s,box-shadow .2s}',
    '.inp:focus{border-color:#ffb110;box-shadow:0 0 0 3px color-mix(in oklch,#ffb110 18%,transparent)}',
    '.tag{display:inline-flex;align-items:center;border-radius:999px;background:#fff;border:1px solid #f2f9ff;padding:6px 12px;font-size:13px;color:#000000}',
    '.layer{border-radius:12px;background:#fff;border:1px solid #f2f9ff;padding:12px 14px;text-align:center;font-size:13.5px;font-weight:600}',
    /* Footer (single dark footer is intentional, low-slop) */
    '.foot-h{font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:14px}',
    '.foot-l{list-style:none;padding:0;margin:0;display:grid;gap:10px}',
    '.foot-l a{color:rgba(255,255,255,.72);font-size:14px;transition:color .2s}',
    '.foot-l a:hover{color:#fff}',
    '.social{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:36px;padding:0 12px;border-radius:999px;border:1px solid rgba(255,255,255,.16);font-size:12.5px;color:rgba(255,255,255,.72);cursor:pointer;transition:.2s}',
    '.social:hover{border-color:rgba(255,255,255,.35);color:#fff}',
    '.social:focus-visible{outline:2px solid #e89d01;outline-offset:2px}',
    /* Cards */
    '.case-card{overflow:hidden;border-radius:20px;border:1px solid #f2f9ff;background:#fff;box-shadow:0 4px 16px oklch(0.35 0.05 295 / 0.07);transition:transform .35s cubic-bezier(.34,1.56,.64,1),box-shadow .35s cubic-bezier(.16,1,.3,1)}',
    '.case-card:hover{transform:translateY(-4px);box-shadow:0 20px 48px oklch(0.35 0.07 295 / 0.14)}',
    '.case-media{position:relative;aspect-ratio:16/10;overflow:hidden;background:linear-gradient(135deg,#fff5e0 0%,#ffffff 50%,#fff5e0 100%)}',
    '.case-media img{width:100%;height:100%;object-fit:cover;transition:transform .4s;display:block}',
    '.case-card:hover .case-media img{transform:scale(1.04)}',
    '.case-media .case-ind{position:absolute;left:12px;top:12px;background:color-mix(in oklch,#ffffff 88%,transparent);backdrop-filter:blur(6px);color:#e89d01;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;box-shadow:0 2px 8px oklch(0.3 0.04 295 / .15)}',
    '.case-body{padding:18px 20px 20px}',
    '.case-brand{font-size:12.5px;font-weight:600;color:#ffb110}',
    '.case-title{margin-top:6px;font-size:16.5px;font-weight:700;line-height:1.35}',
    '.case-desc{margin-top:8px;font-size:13.5px;line-height:1.7;color:#666666}',
    /* Expert cards with real photo */
    '.expert{border-radius:20px;border:1px solid #f2f9ff;background:#fff;overflow:hidden;box-shadow:0 4px 16px oklch(0.35 0.05 295 / 0.06);transition:transform .35s cubic-bezier(.34,1.56,.64,1),box-shadow .35s cubic-bezier(.16,1,.3,1)}',
    '.expert:hover{transform:translateY(-4px);box-shadow:0 20px 48px oklch(0.35 0.07 295 / 0.14)}',
    '.expert-photo{aspect-ratio:4/5;overflow:hidden;background:linear-gradient(160deg,#ffe6b0,#fff5e0);position:relative}',
    '.expert-photo img{width:100%;height:100%;object-fit:cover;object-position:top center;display:block;border-radius:0;filter:brightness(1.2) contrast(1.05) saturate(1.05)}',
    '.expert-photo::after{content:"";position:absolute;inset:0;background:linear-gradient(to bottom,rgba(226,211,252,.12) 0%,rgba(226,211,252,.35) 100%);mix-blend-mode:screen;pointer-events:none}',
    '.expert-body{padding:18px 20px 20px}',
    '.expert-name{font-size:18px;font-weight:700}',
    '.expert-en{font-size:12.5px;color:#ffb110;font-family:"JetBrains Mono",ui-monospace,monospace;margin-top:1px}',
    '.expert-list{list-style:none;padding:0;margin:14px 0 0;display:grid;gap:7px}',
    '.expert-list li{position:relative;padding-left:16px;font-size:13px;line-height:1.55;color:#666666}',
    '.expert-list li::before{content:"";position:absolute;left:0;top:8px;width:6px;height:6px;border-radius:50%;background:#e89d01}',
    /* Marquee (logo loop / wall) */
    '.marquee{display:flex;width:max-content;animation:marquee 42s linear infinite}',
    '.marquee-rev{animation:marquee-rev 50s linear infinite}',
    '@keyframes marquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}',
    '@keyframes marquee-rev{from{transform:translateX(-50%)}to{transform:translateX(0)}}',
    '.marquee-mask{-webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent)}',
    /* logo cell — white card so any logo reads on light canvas */
    '.lw-cell{flex:0 0 auto;width:160px;height:82px;display:flex;align-items:center;justify-content:center;padding:16px 20px;background:#fff;border:1px solid #f2f9ff;border-radius:14px;box-shadow:0 2px 10px oklch(0.35 0.04 295 / 0.06);transition:transform .25s,box-shadow .25s,border-color .25s}',
    '.lw-cell:hover{transform:translateY(-3px);box-shadow:0 12px 28px oklch(0.35 0.06 295 / .12);border-color:color-mix(in oklch,#ffb110 35%,#f2f9ff)}',
    '.lw-cell img{width:100%;height:100%;max-height:46px;object-fit:contain;object-position:center;filter:grayscale(.12);transition:filter .25s}',
    '.lw-cell:hover img{filter:grayscale(0)}',
    '@media (max-width:768px){.lw-cell{width:128px;height:68px;padding:12px 14px}.lw-cell img{max-height:36px}}',
    '.tab-btn[aria-selected="true"]{border-color:#ffb110!important;background:color-mix(in oklch,#ffb110 8%,white)!important}',
    '.tab-btn:focus-visible{outline:2px solid #ffb110;outline-offset:2px}',
    /* Capability panels (light) */
    '.cap-li{position:relative;padding-left:26px;line-height:1.6;color:#666666}',
    '.cap-li::before{content:"";position:absolute;left:0;top:8px;width:14px;height:14px;border-radius:5px;background:color-mix(in oklch,#ffb110 16%,white);box-shadow:inset 0 0 0 1.5px #ffb110}',
    '.flow-step{display:flex;gap:14px;padding:12px 0;position:relative}',
    '.flow-step:not(.flow-step-last)::after{content:"";position:absolute;left:15px;top:36px;bottom:-4px;width:2px;background:#f2f9ff}',
    '.flow-num{flex:0 0 auto;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:#ffb110;color:#fff;font-family:"JetBrains Mono",monospace;font-size:13px;font-weight:600;z-index:1}',
    '.flow-step p:first-child{font-weight:600;color:#000000;font-size:14.5px}',
    '.flow-desc{font-size:12.5px;color:#666666;margin-top:2px}',
    '.chip{display:inline-flex;align-items:center;border-radius:999px;background:color-mix(in oklch,#ffb110 8%,white);border:1px solid #f2f9ff;padding:5px 11px;font-size:12.5px;color:#e89d01}',
    '.lvl{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 14px;border-radius:12px;background:#fff;border:1px solid #f2f9ff;font-size:14px;font-weight:500;color:#000000}',
    '.lvl-n{font-family:"JetBrains Mono",monospace;font-size:12.5px;color:#ffb110;font-weight:600}',
    '.mod{padding:14px 12px;border-radius:12px;background:#fff;border:1px solid #f2f9ff;text-align:center;font-size:13.5px;font-weight:600;color:#000000}',
    /* Solutions list bits */
    '.benefit{position:relative;padding-left:22px;color:#666666;line-height:1.5}',
    '.benefit::before{content:"";position:absolute;left:0;top:7px;width:9px;height:9px;border-radius:50%;background:#e89d01}',
    '.course{display:flex;gap:12px;align-items:baseline;padding:9px 0;border-bottom:1px dashed #f2f9ff}',
    '.course:last-child{border-bottom:0}',
    '.course-k{flex:0 0 auto;width:60px;font-weight:700;color:#ffb110;font-size:13.5px}',
    '.course-t{font-size:13.5px;color:#666666;line-height:1.5}',
    '.course-t em{font-style:normal;font-size:11.5px;color:#e89d01;font-weight:600;margin-left:6px}',
    '.ind{display:inline-flex;align-items:center;border-radius:999px;background:color-mix(in oklch,#ffb110 7%,white);border:1px solid color-mix(in oklch,#ffb110 14%,#f2f9ff);padding:7px 16px;font-size:13px;font-weight:600;color:#e89d01;pointer-events:none}',
    /* ====== Heading & text utility classes ====== */
    '.h2{font-size:clamp(26px,4vw,40px);font-weight:700;line-height:1.25;letter-spacing:-.01em;color:#000000}',
    '.h3{font-size:clamp(22px,3vw,36px);font-weight:700;line-height:1.28;letter-spacing:-.01em;color:#000000}',
    '.lead{margin-top:20px;font-size:16.5px;line-height:1.9;color:#666666;max-width:680px}',
    '.body-t{font-size:15.5px;line-height:1.9;color:rgba(26,22,37,.65)}',
    '.eyebrow-sun{font-family:"JetBrains Mono",ui-monospace,monospace;font-size:12px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#e89d01}',
    /* ====== Card variants ====== */
    '.card{border-radius:20px;border:1px solid #f2f9ff;background:#fff;padding:28px;box-shadow:0 4px 16px oklch(0.35 0.05 295 / 0.07);transition:transform .2s,box-shadow .2s}',
    '.card:hover{transform:translateY(-2px);box-shadow:0 12px 32px oklch(0.35 0.06 295 / .11)}',
    '.card-cream{border-radius:20px;border:1px solid #f2f9ff;background:#ffffff;padding:28px}',
    /* ====== Check list ====== */
    '.check{position:relative;padding-left:26px;line-height:1.6;color:#666666}',
    '.check::before{content:"";position:absolute;left:0;top:3px;width:18px;height:18px;border-radius:6px;background:color-mix(in oklch,#ffb110 12%,white);background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%237050b6\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'M2.5 6l2.5 2.5 5-5\'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:center;background-size:12px}',
    /* ====== Step flow ====== */
    '.step{display:flex;gap:14px;padding:14px 0;position:relative}',
    '.step:not(.step-last)::after{content:"";position:absolute;left:17px;top:40px;bottom:-4px;width:2px;background:linear-gradient(to bottom,#f2f9ff 60%,transparent)}',
    '.step-num{flex:0 0 auto;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:#ffb110;color:#fff;font-family:"JetBrains Mono",monospace;font-size:13px;font-weight:600;z-index:1}',
    '.step-t{font-weight:600;color:#000000;font-size:15px}',
    '.step-d{font-size:13px;color:#666666;margin-top:3px;line-height:1.55}',
    /* ====== Pill / chip ====== */
    '.pill{display:inline-flex;align-items:center;border-radius:999px;background:color-mix(in oklch,#ffb110 8%,white);border:1px solid #f2f9ff;padding:5px 14px;font-size:13px;font-weight:500;color:#e89d01}',
    '.cr{display:inline-flex;align-items:center;border-radius:999px;background:color-mix(in oklch,#e89d01 14%,white);color:#e89d01;font-size:11.5px;font-weight:600;padding:3px 10px}',
    /* ====== Capability model table ====== */
    '.model-head{display:grid;grid-template-columns:1fr auto 1fr;gap:16px;padding:14px 28px;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}',
    '.model-row{display:grid;grid-template-columns:1fr auto 1fr;gap:16px;align-items:center;padding:16px 28px;transition:background .2s}',
    '.model-row:hover{background:color-mix(in oklch,#ffb110 4%,white)}',
    '.model-left{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px}',
    '.model-dot{width:10px;height:10px;border-radius:50%;flex:0 0 auto}',
    '.model-link{display:flex;align-items:center;justify-content:center;width:40px}',
    '.model-link span{display:block;width:24px;height:2px;background:#f2f9ff;position:relative}',
    '.model-link span::before,.model-link span::after{content:"";position:absolute;right:-1px;width:6px;height:6px;border-radius:50%;background:#f2f9ff;top:-2px}',
    '.model-link span::after{right:auto;left:-1px}',
    '.model-right{font-weight:600;font-size:15px;color:#000000}',
    '.model-note{display:block;font-size:12.5px;font-weight:400;color:#666666;margin-top:3px}',
    /* ====== Catalog / course tables ====== */
    '.cat{border-radius:20px;border:1px solid #f2f9ff;background:#fff;overflow:hidden;box-shadow:0 4px 16px oklch(0.35 0.05 295 / 0.05)}',
    '.cat-h{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:color-mix(in oklch,#ffb110 5%,white);border-bottom:1px solid #f2f9ff}',
    '.cat-t{font-size:15px;font-weight:700;color:#000000}',
    '.cat-n{font-family:"JetBrains Mono",monospace;font-size:12px;font-weight:600;color:#ffb110}',
    '.cat-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 24px;border-bottom:1px solid color-mix(in oklch,#f2f9ff 60%,transparent);transition:background .15s}',
    '.cat-row:last-child{border-bottom:0}',
    '.cat-row:hover{background:color-mix(in oklch,#ffb110 3%,white)}',
    '.cat-name{font-size:14px;font-weight:500;color:#000000}',
    '.cat-dur{font-family:"JetBrains Mono",monospace;font-size:12.5px;font-weight:600;color:#ffb110;white-space:nowrap}',
    /* ====== Button line variant ====== */
    '.btn-line{display:inline-flex;align-items:center;gap:8px;min-height:48px;padding:12px 22px;border-radius:999px;border:1.5px solid #ffb110;background:transparent;color:#ffb110;font-weight:600;font-size:15px;transition:.2s}',
    '.btn-line:hover{background:#ffb110;color:#fff}',
    /* ====== Quote mark ====== */
    '.quote-mark{font-family:Georgia,"Times New Roman",serif;line-height:1}',
    /* ====== Navigation capsule (scroll effect) ====== */
    '#nav.capsule-mode{top:12px;left:50%;transform:translateX(-50%);right:auto;width:min(calc(100% - 24px),980px);border-radius:999px;border:1px solid #f2f9ff;background:color-mix(in oklch,#ffffff 92%,transparent);box-shadow:0 8px 32px oklch(0.35 0.06 295 / .12);backdrop-filter:blur(20px) saturate(1.4)}',
    '#nav.capsule-mode .max-w-site{max-width:100%}',
    '#nav.capsule-mode .flex.h-\\[72px\\]{height:56px}',
    '#nav.capsule-mode .brand-text{font-size:16px}',
    '#nav.capsule-mode .nav-link{font-size:13.5px;padding:6px 10px}',
    '#nav.capsule-mode svg.h-8{height:24px;width:24px}',
    '#nav.capsule-mode .rounded-full.bg-jade{padding:8px 18px;font-size:13px;min-height:38px}',
    /* ====== Solution layout variants ====== */
    /* Pyramid — stacked layers tapering up */
    '.pyramid{display:flex;flex-direction:column;align-items:center;gap:6px;padding:28px 24px}',
    '.pyramid-row{display:flex;align-items:center;justify-content:center;border-radius:10px;padding:11px 18px;font-size:13.5px;font-weight:600;color:#000000;border:1px solid #f2f9ff;background:#fff;transition:transform .2s,box-shadow .2s;position:relative}',
    '.pyramid-row:hover{transform:translateY(-2px);box-shadow:0 6px 18px oklch(0.35 0.05 295 / .08)}',
    '.pyramid-row .pyr-idx{position:absolute;left:-28px;font-family:"JetBrains Mono",monospace;font-size:11px;font-weight:600;color:#e89d01}',
    /* Module grid — 2×3 with icon circles */
    '.mod-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:24px}',
    '.mod-cell{display:flex;flex-direction:column;align-items:center;gap:8px;padding:18px 10px;border-radius:16px;border:1px solid #f2f9ff;background:#fff;text-align:center;transition:transform .2s,border-color .2s}',
    '.mod-cell:hover{transform:translateY(-2px);border-color:#ffb110}',
    '.mod-cell .mod-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:color-mix(in oklch,#ffb110 10%,white);font-size:18px}',
    '.mod-cell .mod-label{font-size:13px;font-weight:600;color:#000000;line-height:1.3}',
    '.mod-cell .mod-sub{font-size:11px;color:#666666;margin-top:2px}',
    /* Hex ring — radial arrangement */
    '.hex-ring{display:flex;flex-direction:column;align-items:center;gap:28px;padding:28px 20px}',
    '.hex-center{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#ffb110,#e89d01);display:grid;place-items:center;color:#fff;font-size:13px;font-weight:700;text-align:center;line-height:1.2;box-shadow:0 8px 24px rgba(255,177,16,.32)}',
    '.hex-petals{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:360px}',
    '.hex-petal{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;border-radius:14px;border:1px solid #f2f9ff;background:#fff;transition:transform .2s,border-color .2s}',
    '.hex-petal:hover{transform:scale(1.04);border-color:#e89d01}',
    '.hex-petal .petal-icon{width:38px;height:38px;border-radius:50%;background:color-mix(in oklch,#e89d01 14%,white);display:grid;place-items:center;font-size:15px}',
    '.hex-petal .petal-name{font-size:12.5px;font-weight:700;color:#000000}',
    '.hex-petal .petal-course{font-size:11px;color:#666666;text-align:center;line-height:1.35}',
    /* Progress track — horizontal step indicator */
    '.prog-track{display:flex;align-items:center;gap:0;padding:28px 20px;overflow-x:auto}',
    '.prog-step{display:flex;flex-direction:column;align-items:center;gap:8px;flex:1;min-width:100px;position:relative}',
    '.prog-step:not(:last-child)::after{content:"";position:absolute;top:20px;left:55%;width:90%;height:2px;background:#f2f9ff}',
    '.prog-dot{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-family:"JetBrains Mono",monospace;font-size:13px;font-weight:700;color:#fff;position:relative;z-index:1}',
    '.prog-step .prog-name{font-size:13px;font-weight:600;color:#000000;text-align:center}',
    '.prog-step .prog-title{font-size:11.5px;color:#666666;text-align:center;line-height:1.35}',
    /* ====== Flow concept — wave dividers ====== */
    '.flow-mesh{position:absolute;inset:0;pointer-events:none;opacity:.18;background:radial-gradient(ellipse 80% 60% at 20% 30%,#ffb110 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 80% 70%,#e89d01 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 50% 50%,#ffc95e 0%,transparent 50%);background-size:200% 200%;animation:flow-mesh 20s ease-in-out infinite alternate}',
    '@keyframes flow-mesh{0%{background-position:0% 0%}25%{background-position:100% 30%}50%{background-position:60% 100%}75%{background-position:20% 60%}100%{background-position:0% 0%}}',
    '.flow-divider{position:relative;height:80px;overflow:hidden}',
    '.flow-divider svg{position:absolute;bottom:0;width:100%;height:100%}',
    '.flow-divider .wave-fill{fill:#ffffff}',
    '.flow-divider.to-white .wave-fill{fill:#ffffff}',
    '.flow-divider.to-cream .wave-fill{fill:#ffffff}',
    /* Animated flowing accent line */
    '.flow-line{position:absolute;width:200%;height:3px;background:linear-gradient(90deg,transparent 0%,#ffb110 25%,#e89d01 50%,#ffb110 75%,transparent 100%);background-size:50% 100%;animation:flow-line 8s linear infinite;opacity:.25;pointer-events:none}',
    '@keyframes flow-line{from{transform:translateX(-50%)}to{transform:translateX(0)}}',
    /* Staggered reveal for cards */
    '.reveal-stagger .reveal:nth-child(1){transition-delay:0s}.reveal-stagger .reveal:nth-child(2){transition-delay:.08s}.reveal-stagger .reveal:nth-child(3){transition-delay:.16s}.reveal-stagger .reveal:nth-child(4){transition-delay:.24s}.reveal-stagger .reveal:nth-child(5){transition-delay:.32s}.reveal-stagger .reveal:nth-child(6){transition-delay:.40s}',
    /* Tab auto-rotate progress bar */
    '.tab-progress{height:3px;background:linear-gradient(90deg,#ffb110,#e89d01);border-radius:2px;transform-origin:left;transform:scaleX(0);transition:transform 3s linear}',
    '.tab-progress.active{transform:scaleX(1)}',
    /* Tab panel liquid glass transition */
    '[role="tabpanel"]{transition:opacity .45s cubic-bezier(.16,1,.3,1),transform .45s cubic-bezier(.16,1,.3,1),backdrop-filter .45s}',
    '[role="tabpanel"].panel-enter{opacity:0;transform:translateY(12px) scale(.98);backdrop-filter:blur(8px)}',
    '[role="tabpanel"].panel-active{opacity:1;transform:none;backdrop-filter:blur(0)}',
    '.tab-btn{transition:border-color .3s,background .3s,box-shadow .3s,transform .2s}',
    '.tab-btn[aria-selected="true"]{box-shadow:0 4px 20px rgba(255,177,16,.15)}',
    '@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}.reveal,.hero-stagger,.hero-stagger>*{opacity:1!important;transform:none!important;transition:none!important}.marquee{animation:none!important}.flow-line,.flow-mesh{display:none!important}.tab-progress{display:none!important}.blob{animation:none!important}}',
  ].join('');
  document.head.appendChild(css);

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    var nav = document.getElementById('nav');
    var burger = document.getElementById('burger');
    var menu = document.getElementById('mobile-menu');

    function onScroll() {
      if (!nav) return;
      var y = window.scrollY;
      nav.classList.toggle('scrolled', y > 12);
      nav.classList.toggle('capsule-mode', y > 260);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (burger && menu) {
      burger.addEventListener('click', function () {
        var willOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden', !willOpen);
        burger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
      menu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          menu.classList.add('hidden');
          burger.setAttribute('aria-expanded', 'false');
        });
      });
    }

    var tabBtns = Array.prototype.slice.call(document.querySelectorAll('.tab-btn'));
    if (tabBtns.length) {
      var tabTimer = null;
      var TAB_INTERVAL = 3000;

      function activateTab(btn) {
        var panelId = btn.getAttribute('aria-controls');
        var targetPanel = document.getElementById(panelId);
        tabBtns.forEach(function (b) { b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
        // Liquid glass transition: exit current, enter new
        document.querySelectorAll('[role="tabpanel"]').forEach(function (p) {
          if (p.id === panelId) {
            p.classList.remove('hidden', 'panel-enter');
            void p.offsetWidth; // force reflow
            p.classList.add('panel-active');
          } else {
            p.classList.remove('panel-active');
            p.classList.add('panel-enter');
            setTimeout(function () { p.classList.add('hidden'); }, 300);
          }
        });
        // Reset progress bar
        var prog = btn.querySelector('.tab-progress');
        document.querySelectorAll('.tab-progress').forEach(function (p) { p.classList.remove('active'); p.style.transform = 'scaleX(0)'; });
        if (prog) { void prog.offsetWidth; prog.classList.add('active'); }
      }

      function nextTab() {
        var cur = tabBtns.findIndex(function (b) { return b.getAttribute('aria-selected') === 'true'; });
        var next = (cur + 1) % tabBtns.length;
        activateTab(tabBtns[next]);
      }

      function startAutoRotate() {
        clearInterval(tabTimer);
        tabTimer = setInterval(nextTab, TAB_INTERVAL);
        // Start progress on current tab
        var cur = tabBtns.find(function (b) { return b.getAttribute('aria-selected') === 'true'; });
        if (cur) { var p = cur.querySelector('.tab-progress'); if (p) { void p.offsetWidth; p.classList.add('active'); } }
      }

      tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          activateTab(btn);
          startAutoRotate(); // Reset timer on manual click
        });
      });

      startAutoRotate();
    }

    var form = document.getElementById('lead-form');
    var msg = document.getElementById('form-msg');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        if (msg) {
          msg.textContent = '已收到预约信息（演示）。正式环境将同步至顾问工作台。';
          msg.classList.remove('hidden');
        }
        form.reset();
      });
    }

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) {
            en.target.classList.add('in');
            io.unobserve(en.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      document.querySelectorAll('.reveal,.hero-stagger').forEach(function (el) { io.observe(el); });
    } else {
      document.querySelectorAll('.reveal,.hero-stagger').forEach(function (el) { el.classList.add('in'); });
    }

    document.querySelectorAll('[data-count]').forEach(function (el) {
      if (el.hasAttribute('data-plain')) return;
      var target = parseFloat(el.getAttribute('data-count'));
      if (isNaN(target)) return;
      var start = 0;
      var dur = 1200;
      var t0 = null;
      function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(start + (target - start) * eased);
        if (p < 1) requestAnimationFrame(step);
      }
      if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) {
          entries.forEach(function (en) {
            if (en.isIntersecting) {
              requestAnimationFrame(step);
              cio.unobserve(el);
            }
          });
        }, { threshold: 0.4 });
        cio.observe(el);
      } else {
        el.textContent = String(target);
      }
    });
  });
})();
