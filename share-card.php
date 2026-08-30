<?php
/**
 * Social Media Share Card Generator
 * Renders a vertical 16:9 share card with title, description, QR code
 * Usage: share-card.php?type=article&id=xxx
 */
require_once __DIR__ . '/admin/config.php';

$type = $_GET['type'] ?? 'article';
$id = $_GET['id'] ?? '';

$title = '';
$description = '';
$url = '';
$siteName = 'OpenFlow';

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($type === 'article') {
    $a = get_article($id);
    if ($a) {
        $title = $a['title'] ?? '';
        $description = strip_tags(mb_substr($a['content'] ?? '', 0, 120));
        $url = $protocol . '://' . $host . '/article/' . ($a['slug'] ?? $a['id']);
    }
} elseif ($type === 'event') {
    $events = json_read(DATA_DIR . '/events/index.json');
    foreach ($events as $e) {
        if ($e['id'] === $id) {
            $title = $e['title'] ?? '';
            $description = $e['description'] ?? strip_tags(mb_substr($e['content'] ?? '', 0, 120));
            $url = $protocol . '://' . $host . '/event/' . $e['slug'];
            break;
        }
    }
} elseif ($type === 'page') {
    $pages = ['index'=>'首页','about'=>'关于我们','product'=>'产品','courses'=>'课程','capability'=>'能力','academy'=>'学院','community'=>'门派社区','docs'=>'文档'];
    if (isset($pages[$id])) {
        $title = $pages[$id] . ' - ' . $siteName;
        $description = '芭乐派 · 帮一人公司设计 Agent 能跑的增长系统';
        $url = $protocol . '://' . $host . ($id === 'index' ? '/' : '/' . $id);
    }
}

if (empty($title)) { $title = '芭乐派 · OpenFlow'; $description = '帮一人公司设计 Agent 能跑的增长系统'; $url = $protocol . '://' . $host . '/'; }

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($url);
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>分享卡片 - <?=htmlspecialchars($title)?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,'Inter','PingFang SC','Noto Sans SC',system-ui,sans-serif;background:#1e1e1e;display:flex;flex-direction:column;align-items:center;padding:40px 20px;min-height:100vh}
.card{width:540px;min-height:960px;background:linear-gradient(160deg,#1e1e1e 0%,#2d2d2d 100%);border-radius:24px;overflow:hidden;position:relative;box-shadow:0 24px 80px rgba(0,0,0,.4)}
.card-inner{padding:48px 40px;display:flex;flex-direction:column;min-height:960px}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(221,255,14,.15);border:1px solid rgba(221,255,14,.3);border-radius:999px;font-size:12px;font-weight:600;color:#ddff0e;margin-bottom:32px;align-self:flex-start}
.title{font-size:36px;font-weight:700;line-height:1.2;letter-spacing:-.02em;color:var(--surface);margin-bottom:20px;flex:1}
.desc{font-size:16px;line-height:1.7;color:rgba(255,255,255,.6);margin-bottom:40px}
.divider{height:1px;background:rgba(255,255,255,.1);margin-bottom:32px}
.footer{display:flex;align-items:center;gap:24px}
.qr img{width:140px;height:140px;border-radius:12px;background:var(--surface);padding:8px}
.site-info{flex:1}
.site-info .name{font-size:20px;font-weight:700;color:var(--surface);margin-bottom:4px}
.site-info .tagline{font-size:13px;color:rgba(255,255,255,.4)}
.site-info .url{font-size:12px;color:rgba(221,255,14,.6);margin-top:6px;font-family:monospace}
.gradient-bar{position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#ddff0e,#86efac,#7dd3fc)}
.controls{display:flex;gap:12px;margin-top:24px;max-width:540px}
.controls button{padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s}
.controls .dl{background:var(--accent-soft);color:var(--accent)}
.controls .dl:hover{transform:translateY(-2px)}
.controls .close{background:rgba(255,255,255,.1);color:var(--surface)}
.controls .close:hover{background:rgba(255,255,255,.2)}
@media(max-width:600px){.card{width:100%;border-radius:16px}.card-inner{padding:32px 24px}.title{font-size:28px}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body>

<div class="card" id="shareCard">
  <div class="gradient-bar"></div>
  <div class="card-inner">
    <div class="badge"><span>✦</span> <?=htmlspecialchars($siteName)?></div>
    <div class="title"><?=htmlspecialchars($title)?></div>
    <div class="desc"><?=htmlspecialchars($description ?: '帮一人公司设计 Agent 能跑的增长系统')?></div>
    <div class="divider"></div>
    <div class="footer">
      <div class="qr"><img src="<?=htmlspecialchars($qrUrl)?>" alt="QR"></div>
      <div class="site-info">
        <div class="name"><?=htmlspecialchars($siteName)?></div>
        <div class="tagline">帮一人公司设计 Agent 能跑的增长系统</div>
        <div class="url"><?=htmlspecialchars(parse_url($url, PHP_URL_HOST) ?: $host)?></div>
      </div>
    </div>
  </div>
</div>

<div class="controls">
  <button class="dl" onclick="downloadCard()">📥 下载 PNG</button>
  <button class="close" onclick="window.open('<?=htmlspecialchars($url)?>','_blank')">🔗 打开页面</button>
  <button class="close" onclick="window.print()">🖨 打印</button>
</div>

<script>
function downloadCard() {
  // Create a canvas from the card element using html2canvas if available
  // Fallback: open a print dialog
  var card = document.getElementById('shareCard');
  if (typeof html2canvas !== 'undefined') {
    html2canvas(card, {scale: 2, backgroundColor: '#1e1e1e', useCORS: true}).then(function(canvas) {
      var a = document.createElement('a');
      a.download = 'share-card-<?=htmlspecialchars($id ?? 'openflow')?>.png';
      a.href = canvas.toDataURL('image/png');
      a.click();
    });
  } else {
    // Load html2canvas dynamically
    var s = document.createElement('script');
    s.src = '/assets/vendor/html2canvas.min.js';
    s.onload = function() { downloadCard(); };
    document.head.appendChild(s);
  }
}
</script>
</body>
</html>
