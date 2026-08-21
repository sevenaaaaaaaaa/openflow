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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$room ? htmlspecialchars($room['title']) : htmlspecialchars($settings['page_title'])?> | <?=site_config_get("site_name")?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .chat-box{height:420px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:14px;background:#faf9f4;border-radius:14px;border:1px solid var(--border)}
  .chat-msg{font-size:13px;line-height:1.5}
  .chat-msg .u{font-weight:700;font-size:12px;color:#2b5f7e}
  .chat-msg .t{color:var(--muted)}
  .live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#dc2626;animation:blink 1.2s infinite}
  @keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260821" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-8" style="max-width:1100px">
    <?php if ($room): ?>
    <?php $st = live_status($room); $sellCourse = !empty($room['sell_course']) ? ($courseMap[$room['sell_course']] ?? null) : null; ?>
    <!-- ═══ 直播间 ═══ -->
    <a href="/live.php" class="text-sm text-[#2b5f7e]">← 返回直播列表</a>
    <div class="mt-4 grid gap-6" style="grid-template-columns:1fr 320px">
      <div>
        <!-- 播放器 -->
        <div style="background:#000;border-radius:16px;overflow:hidden;aspect-ratio:16/9;position:relative">
          <?php if ($st === 'live' && !empty($room['hls_url'])): ?>
          <video id="livePlayer" class="w-full h-full" controls autoplay muted playsinline src="<?=htmlspecialchars($room['hls_url'])?>"></video>
          <?php elseif ($st === 'live' && empty($room['hls_url'])): ?>
          <div class="w-full h-full flex flex-col items-center justify-center text-white" style="background:var(--fg)">
            <div class="live-dot mb-3"></div><div class="text-lg font-bold">直播进行中</div><div class="text-sm text-white/60 mt-1">播放地址待配置</div>
          </div>
          <?php elseif ($st === 'replay' && !empty($room['replay_url'])): ?>
          <video class="w-full h-full" controls playsinline src="<?=htmlspecialchars($room['replay_url'])?>"></video>
          <?php else: ?>
          <div class="w-full h-full flex flex-col items-center justify-center text-white" style="background:var(--fg)">
            <?php if ($st === 'scheduled'): ?>
            <div class="text-lg font-bold"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></span> 直播预告</div>
            <div class="text-sm text-white/60 mt-2"><?=htmlspecialchars(substr($room['start_at'] ?? '', 0, 16))?> 开播</div>
            <?php else: ?>
            <div class="text-lg font-bold">😴 暂未开播</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <!-- 标题信息 -->
        <div class="mt-5">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold"><?=htmlspecialchars($room['title'])?></h1>
            <?php if ($st === 'live'): ?><span class="text-xs px-3 py-1 rounded-full flex items-center gap-2" style="background:#fee2e2;color:#dc2626;font-weight:600"><span class="live-dot"></span> 直播中</span><?php else: ?><span class="text-xs px-3 py-1 rounded-full" style="background:var(--bg);color:var(--muted)"><?=live_status_label($st)?></span><?php endif; ?>
          </div>
          <p class="text-sm text-gray-600 mt-2"><?=nl2br(htmlspecialchars($room['desc'] ?? ''))?></p>
          <?php if (!empty($room['start_at'])): ?><div class="text-xs text-gray-400 mt-2">🕐 <?=htmlspecialchars(substr($room['start_at'], 0, 16))?> — <?=htmlspecialchars(substr($room['end_at'] ?? '', 0, 16))?></div><?php endif; ?>
        </div>

        <!-- 售卖课程 -->
        <?php if ($sellCourse): $price = $shopSettings['course_prices'][$sellCourse['id']] ?? 0; ?>
        <div class="mt-5 bg-white rounded-2xl p-5 flex gap-4 flex-wrap" style="border:1px solid var(--border)">
          <?php if (!empty($sellCourse['cover'])): ?><img src="<?=htmlspecialchars($sellCourse['cover'])?>" class="w-32 h-20 object-cover rounded-xl" onerror="this.style.display='none'"><?php endif; ?>
          <div style="flex:1;min-width:200px">
            <div class="text-xs font-bold" style="color:var(--ok)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span> 直播间同款课程</div>
            <div class="font-bold mt-1"><?=htmlspecialchars($sellCourse['title'])?></div>
            <div class="text-sm text-gray-600 mt-1 line-clamp-2"><?=htmlspecialchars($sellCourse['description'] ?? '')?></div>
          </div>
          <div class="text-right">
            <div class="text-2xl font-extrabold" style="color:var(--ok)"><?=$price > 0 ? '¥' . number_format($price, 0) : '限时'?></div>
            <a href="/course/<?=urlencode($sellCourse['id'])?>" class="inline-block mt-2 px-6 py-2 rounded-full font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">查看课程 →</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- 聊天 -->
      <div>
        <div class="bg-white rounded-2xl" style="border:1px solid var(--border)">
          <div class="px-5 py-3 border-b border-[var(--border)] font-bold text-sm"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span> 直播间弹幕</div>
          <div class="chat-box" id="chatBox"></div>
          <div class="p-3 flex gap-2" style="border-top:1px solid var(--border)">
            <input id="chatInput" class="flex-1 px-3 py-2 rounded-xl text-sm" style="border:1.5px solid var(--border)" placeholder="发条消息…">
            <button id="chatBtn" class="px-4 rounded-xl font-bold text-sm" style="background:var(--accent);color:var(--on-accent)" onclick="sendChat()">发送</button>
          </div>
        </div>
      </div>
    </div>
    <script>
      var ROOM_ID = <?=json_encode($room['id'])?>;
      var LAST_COUNT = 0;
      function loadChat() {
        fetch('/api/live.php?action=chat&room_id=' + encodeURIComponent(ROOM_ID)).then(function(r){ return r.json(); }).then(function(d) {
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
        fetch('/api/live.php?action=send', { method: 'POST', body: body })
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
    <div class="text-center py-6 mb-8">
      <h1 class="text-3xl font-extrabold"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12a15 15 0 0 1 20 0M5 15a10 10 0 0 1 14 0M8.5 18a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1.2" fill="currentColor"/></svg></span> <?=htmlspecialchars($settings['page_title'] ?? '直播')?></h1>
      <p class="text-gray-600 mt-3"><?=htmlspecialchars($settings['page_desc'] ?? '')?></p>
    </div>

    <?php
    usort($rooms, function($a, $b) {
      $pa = !empty($a['is_live']) ? 0 : 1; $pb = !empty($b['is_live']) ? 0 : 1;
      return $pa <=> $pb ?: strcmp($b['start_at'] ?? '', $a['start_at'] ?? '');
    });
    ?>
    <?php if (empty($rooms)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">暂无直播安排，敬请期待</div>
    <?php else: ?>
    <div class="grid gap-5" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
      <?php foreach ($rooms as $r): $st = live_status($r); ?>
      <a href="/live.php?room=<?=urlencode($r['id'])?>" class="bg-white rounded-2xl overflow-hidden" style="border:1px solid var(--border);text-decoration:none;color:inherit;transition:.15s">
        <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--fg),#2b5f7e);display:grid;place-items:center;position:relative">
          <?php if (!empty($r['cover'])): ?><img src="<?=htmlspecialchars($r['cover'])?>" class="w-full h-full object-cover"><?php else: ?><span style="font-size:38px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12a15 15 0 0 1 20 0M5 15a10 10 0 0 1 14 0M8.5 18a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1.2" fill="currentColor"/></svg></span></span><?php endif; ?>
          <span class="absolute top-3 left-3 text-xs px-3 py-1 rounded-full flex items-center gap-1.5" style="background:<?=$st==='live'?'#dc2626':'var(--accent)'?>;color:var(--surface);font-weight:600"><?=$st==='live'?'<span class="live-dot"></span>':'▶️'?> <?=live_status_label($st)?></span>
          <?php if (!empty($r['sell_course'])): ?><span class="absolute bottom-3 right-3 text-xs px-3 py-1 rounded-full" style="background:var(--accent-soft);color:var(--accent);font-weight:600"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span> 课程</span><?php endif; ?>
        </div>
        <div class="p-4">
          <div class="font-bold"><?=htmlspecialchars($r['title'])?></div>
          <div class="text-xs text-gray-600 mt-1"><?=htmlspecialchars(substr($r['start_at'] ?? '', 0, 16))?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
      <div class="mb-2"><?=site_config_get("site_name")?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>
</body>
</html>
