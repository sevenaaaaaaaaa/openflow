<?php
/**
 * OBS 浏览器源叠加层 — /live-overlay?room={id}（E3）
 *
 * 透明背景，1280×720 设计：标题条（左上）+ 商品卡轮换（左下）+ 实时弹幕（右下）。
 * OBS → 来源 → 浏览器 → 填本页 URL，宽 1280 高 720，勾选「关闭时刷新」。
 * 后台改房间内容，OBS 里 10 秒内自动跟上（本页每 10s 自刷新配置）。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/LiveSystem.php';

$roomId = (string)($_GET['room'] ?? '');
$room = $roomId ? live_room($roomId) : null;
if (!$room) { http_response_code(404); die('room not found'); }

$products = [];
foreach ((array)($room['products'] ?? []) as $line) {
    $parts = array_map('trim', explode('|', $line));
    if ($parts[0] ?? '') $products[] = ['title' => $parts[0], 'link' => $parts[1] ?? '', 'price' => $parts[2] ?? ''];
}
// 兼容旧字段：单卖课
if (!$products && !empty($room['sell_course'])) {
    foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
        if ($c['id'] === $room['sell_course']) { $products[] = ['title' => $c['title'], 'link' => '/courses/' . $c['id'], 'price' => '直播间同款']; break; }
    }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>overlay · <?=htmlspecialchars($room['title'])?></title>
<style>
*{margin:0;box-sizing:border-box}
html,body{background:transparent;width:1280px;height:720px;overflow:hidden;font-family:"PingFang SC","Microsoft YaHei",system-ui,sans-serif}
/* ── 标题条（左上）── */
.titlebar{position:absolute;top:28px;left:32px;display:flex;align-items:center;gap:12px;
  background:rgba(10,12,24,.72);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.14);
  border-radius:14px;padding:12px 20px;color:#fff;max-width:640px}
.titlebar .live-dot{width:10px;height:10px;border-radius:50%;background:#ff3b4e;animation:blink 1.2s infinite;flex:none}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.titlebar b{font-size:20px;font-weight:800;letter-spacing:.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.titlebar small{font-size:12px;opacity:.6;white-space:nowrap}
/* ── 商品卡（左下，轮换）── */
.shopcard{position:absolute;left:32px;bottom:36px;width:400px;background:rgba(10,12,24,.78);backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,.16);border-radius:16px;padding:16px 20px;color:#fff;
  opacity:0;transform:translateY(14px);transition:opacity .5s,transform .5s}
.shopcard.on{opacity:1;transform:none}
.shopcard .k{font-size:11px;letter-spacing:.18em;color:#ffd666;font-weight:700;margin-bottom:6px}
.shopcard b{display:block;font-size:18px;font-weight:800;line-height:1.35}
.shopcard .p{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
.shopcard .p em{font-style:normal;font-size:16px;font-weight:800;color:#7dffa8}
.shopcard .p span{font-size:12px;opacity:.62}
/* ── 弹幕（右下，最新 4 条）── */
.danmu{position:absolute;right:32px;bottom:36px;width:380px;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.dm{background:rgba(10,12,24,.66);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.12);
  border-radius:999px;padding:8px 16px;color:#fff;font-size:14px;line-height:1.4;max-width:100%;
  animation:dmin .35s ease;word-break:break-all}
.dm b{color:#8ab4ff;margin-right:6px;font-size:13px}
@keyframes dmin{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
</style>
</head>
<body>
<div class="titlebar">
  <span class="live-dot"></span>
  <b><?=htmlspecialchars($room['title'])?></b>
  <small><?=htmlspecialchars(site_config_get('site_name', 'OpenFlow'))?> 直播</small>
</div>

<?php if ($products): ?>
<div class="shopcard" id="shopcard">
  <div class="k">直播间专属</div>
  <b id="sc-title"></b>
  <div class="p"><em id="sc-price"></em><span id="sc-link"></span></div>
</div>
<?php endif; ?>

<div class="danmu" id="danmu"></div>

<script>
var ROOM = <?=json_encode($roomId)?>;
var PRODUCTS = <?=json_encode($products, JSON_UNESCAPED_UNICODE)?>;

/* 商品卡轮换：8s 一张 */
(function(){
  if (!PRODUCTS.length) return;
  var i = 0, card = document.getElementById('shopcard');
  var t = document.getElementById('sc-title'), p = document.getElementById('sc-price'), l = document.getElementById('sc-link');
  function show() {
    card.classList.remove('on');
    setTimeout(function(){
      var it = PRODUCTS[i % PRODUCTS.length]; i++;
      t.textContent = it.title; p.textContent = it.price || ''; l.textContent = (it.link || '').replace(/^https?:\/\//, '');
      card.classList.add('on');
    }, 500);
  }
  show(); setInterval(show, 8000);
})();

/* 弹幕轮询：3s，只保留最新 4 条 */
(function(){
  var box = document.getElementById('danmu'), last = 0;
  function esc(s){return String(s||'').replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function load(){
    fetch('/api/live?action=chat&room_id=' + encodeURIComponent(ROOM)).then(function(r){return r.json()}).then(function(d){
      if (!d.ok || d.messages.length === last) return;
      last = d.messages.length;
      box.innerHTML = d.messages.slice(-4).map(function(m){
        return '<div class="dm"><b>' + esc(m.user || '游客') + '</b>' + esc(m.text) + '</div>';
      }).join('');
    }).catch(function(){});
  }
  load(); setInterval(load, 3000);
})();

/* 配置热更新：10s 自刷新（后台改了商品/标题 OBS 自动跟上） */
setTimeout(function(){ location.reload(); }, 600000);  /* 兜底 10 分钟硬刷新 */
</script>
</body>
</html>
