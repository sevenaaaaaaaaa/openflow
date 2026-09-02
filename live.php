<?php
/**
 * 直播前台 — 直播列表 / 直播间（播放 + 聊天 + 售卖课程）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/LiveSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/ShopSystem.php';

$settings = live_settings();
if (empty($settings['enabled'])) { http_response_code(404); die('功能未开启'); }

$rooms = live_rooms();
$courses = json_read(DATA_DIR . '/courses/index.json');
$courseMap = [];
foreach ($courses as $c) $courseMap[$c['id']] = $c;
$member = member_current();

$roomId = $_GET['room'] ?? '';
$room = $roomId ? live_room($roomId) : null;
$shopSettings = shop_settings();
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$room ? htmlspecialchars($room['title']) : htmlspecialchars($settings['page_title'])?> | <?=site_config_get("site_name")?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 直播独有：播放器画布、弹幕盒、直播红点。其余全部来自 modules.css。 */
.player{aspect-ratio:16/9;background:var(--fg);color:var(--on-accent);display:grid;place-items:center;text-align:center}
.player video{width:100%;height:100%;display:block;background:var(--fg)}
.player .ph{display:flex;flex-direction:column;align-items:center;gap:8px;font-weight:700;font-size:18px}
.player .ph small{font-size:13px;font-weight:400;opacity:.65}
.live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--danger);animation:blink 1.2s infinite}
.badge.live{background:var(--danger-soft);color:var(--danger)}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.chat-box{height:420px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:14px}
.chat-msg{font-size:13px;line-height:1.5}
.chat-msg .u{font-weight:700;font-size:12px;color:var(--accent)}
.chat-msg .t{color:var(--muted)}
.chat-in{display:flex;gap:8px;padding:12px;border-top:1px solid var(--border-soft)}
.chat-in .inp{min-height:42px;padding:9px 14px;font-size:14px}
.room-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.room-head h1{font-size:clamp(22px,2.6vw,30px);font-weight:800;letter-spacing:-.02em}
.a-card .cov .tag{position:absolute;top:12px;left:12px}
.a-card .cov .tag.r{left:auto;right:12px;top:auto;bottom:12px}
@media (max-width:860px){.chat-box{height:280px}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('events'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
<?php if ($room): ?>
<?php $st = live_status($room); $sellCourse = !empty($room['sell_course']) ? ($courseMap[$room['sell_course']] ?? null) : null; ?>
  <!-- ═══ 直播间 ═══ -->
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="live-room">
    <div class="actions"><a href="/live" class="act">← 返回直播列表</a></div>
    <div class="g-main-aside">
      <div>
        <div class="sp-win">
          <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">live · <?=htmlspecialchars($room['id'])?></div></div>
          <div class="player">
            <?php if ($st === 'live' && !empty($room['hls_url'])): ?>
            <video id="livePlayer" controls autoplay muted playsinline src="<?=htmlspecialchars($room['hls_url'])?>"></video>
            <?php elseif ($st === 'live' && empty($room['hls_url'])): ?>
            <div class="ph"><span class="live-dot"></span>直播进行中<small>播放地址待配置</small></div>
            <?php elseif ($st === 'replay' && !empty($room['replay_url'])): ?>
            <video controls playsinline src="<?=htmlspecialchars($room['replay_url'])?>"></video>
            <?php elseif ($st === 'scheduled'): ?>
            <div class="ph"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>直播预告<small><?=htmlspecialchars(substr($room['start_at'] ?? '', 0, 16))?> 开播</small></div>
            <?php else: ?>
            <div class="ph">暂未开播</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="room-head">
          <h1><?=htmlspecialchars($room['title'])?></h1>
          <?php if ($st === 'live'): ?><span class="badge live"><span class="live-dot"></span>直播中</span><?php else: ?><span class="pill neutral"><?=live_status_label($st)?></span><?php endif; ?>
        </div>
        <p class="lead" style="font-size:15px;line-height:1.85;color:var(--muted)"><?=nl2br(htmlspecialchars($room['desc'] ?? ''))?></p>
        <?php if (!empty($room['start_at'])): ?><div class="note mono" style="margin-top:0"><?=htmlspecialchars(substr($room['start_at'], 0, 16))?> — <?=htmlspecialchars(substr($room['end_at'] ?? '', 0, 16))?></div><?php endif; ?>

        <?php if ($sellCourse): $price = $shopSettings['course_prices'][$sellCourse['id']] ?? 0; ?>
        <div class="strip">
          <?php if (!empty($sellCourse['cover'])): ?><img src="<?=htmlspecialchars($sellCourse['cover'])?>" alt="" style="width:120px;aspect-ratio:16/10;object-fit:cover;border-radius:10px" onerror="this.style.display='none'"><?php else: ?><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span><?php endif; ?>
          <div class="tx"><span class="kicker" style="font-size:11px">直播间同款课程</span><b><?=htmlspecialchars($sellCourse['title'])?></b><span><?=htmlspecialchars($sellCourse['description'] ?? '')?></span></div>
          <div style="text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end"><b style="font-family:var(--font-display);font-size:24px;color:var(--ok)"><?=$price > 0 ? '¥' . number_format($price, 0) : '限时'?></b><a href="/courses/<?=urlencode($sellCourse['id'])?>" class="btn primary" style="height:40px;padding:0 18px;font-size:14px">查看课程 →</a></div>
        </div>
        <?php endif; ?>
      </div>

      <aside>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="win-bar"><span class="ic" style="width:16px;height:16px;color:var(--accent)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span><b style="font-size:13.5px">直播间弹幕</b></div>
          <div class="chat-box" id="chatBox"></div>
          <div class="chat-in">
            <input id="chatInput" class="inp" placeholder="发条消息…">
            <button id="chatBtn" type="button" class="btn primary" style="height:42px;padding:0 16px;font-size:14px" onclick="sendChat()">发送</button>
          </div>
        </div>
      </aside>
    </div>
  </section>
<script>
  var ROOM_ID = <?=json_encode($room['id'])?>;
  var LAST_COUNT = 0;
  function loadChat() {
    fetch('/api/live?action=chat&room_id=' + encodeURIComponent(ROOM_ID)).then(function(r){ return r.json(); }).then(function(d) {
      if (!d.ok) return;
      var box = document.getElementById('chatBox');
      if (d.messages.length > LAST_COUNT) {
        box.innerHTML = '';
        d.messages.forEach(function(m) {
          var el = document.createElement('div');
          el.className = 'chat-msg';
          el.innerHTML = '<span class="u">' + (m.user||'游客').replace(/[<>&"]/g, function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];}) + '：</span><span class="t">' + (m.text||'').replace(/[<>&"]/g, function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];}) + '</span>';
          box.appendChild(el);
        });
        LAST_COUNT = d.messages.length;
        box.scrollTop = box.scrollHeight;
      }
    });
  }
  function sendChat() {
    var input = document.getElementById('chatInput');
    var text = input.value.trim();
    if (!text) return;
    var body = new FormData();
    body.append('room_id', ROOM_ID);
    body.append('text', text);
    fetch('/api/live?action=send', { method: 'POST', body: body })
      .then(function(r){ return r.json(); }).then(function(d) {
        if (d.ok) { input.value = ''; loadChat(); }
        else alert(d.error || '发送失败');
      });
  }
  document.getElementById('chatInput').addEventListener('keydown', function(e){ if (e.key === 'Enter') sendChat(); });
  loadChat();
  setInterval(loadChat, 3000);
</script>

<?php else: ?>
  <!-- ═══ 直播列表 ═══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="live-hero">
    <div class="hero-center">
      <span class="kicker">LIVE · 直播</span>
      <h1><?=htmlspecialchars($settings['page_title'] ?? '直播')?></h1>
      <?php if (!empty($settings['page_desc'])): ?><p class="lead"><?=htmlspecialchars($settings['page_desc'])?></p><?php endif; ?>
    </div>
  </section>

  <?php
  usort($rooms, function($a, $b) {
    $pa = !empty($a['is_live']) ? 0 : 1; $pb = !empty($b['is_live']) ? 0 : 1;
    return $pa <=> $pb ?: strcmp($b['start_at'] ?? '', $a['start_at'] ?? '');
  });
  ?>
  <section id="rooms" class="sec reveal" data-od-anchor data-od-id="live-list">
    <div class="sec-head row"><div><span class="kicker">ROOMS</span><h2>全部直播</h2></div><span class="sub"><?=count($rooms)?> 场</span></div>
    <?php if (empty($rooms)): ?>
    <div class="empty">暂无直播安排，敬请期待</div>
    <?php else: ?>
    <div class="a-grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
      <?php foreach ($rooms as $r): $st = live_status($r); ?>
      <a href="/live?room=<?=urlencode($r['id'])?>" class="a-card">
        <div class="cov">
          <?php if (!empty($r['cover'])): ?><img src="<?=htmlspecialchars($r['cover'])?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12a15 15 0 0 1 20 0M5 15a10 10 0 0 1 14 0M8.5 18a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1.2" fill="currentColor"/></svg><?php endif; ?>
          <span class="tag <?=$st==='live'?'badge live':'pill hl'?>"><?php if ($st==='live'): ?><span class="live-dot"></span><?php endif; ?><?=live_status_label($st)?></span>
          <?php if (!empty($r['sell_course'])): ?><span class="tag r pill neutral">课程</span><?php endif; ?>
        </div>
        <div class="bd">
          <h3><?=htmlspecialchars($r['title'])?></h3>
          <div class="meta"><?=htmlspecialchars(substr($r['start_at'] ?? '', 0, 16))?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
