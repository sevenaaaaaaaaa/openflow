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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?=$room ? htmlspecialchars($room['title']) : htmlspecialchars($settings['page_title'])?> | <?=site_config_get("site_name")?></title>
<meta name="description" content="<?=htmlspecialchars(mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($room['description'] ?? $settings['page_desc'] ?? '')))), 0, 120) ?: '芭乐派直播：增长实战分享与课程答疑')?>">
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
<link rel="stylesheet" href="/assets/live.css?v=20260910c">
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main<?=$room && ($room['stream_mode'] ?? '') === 'immersive' ? ' class="live-immersive"' : ''?>>
<?php of_shell('events'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
<?php if ($room): ?>
<?php
$st = live_status($room);
$sellCourse = !empty($room['sell_course']) ? ($courseMap[$room['sell_course']] ?? null) : null;
$streamMode = $room['stream_mode'] ?? 'landscape';

// 播放器内容抽成可复用块（沉浸/常规两种形态共用同一套源逻辑）
$ytEmbed = '';
if (!empty($room['youtube_url']) && preg_match('~(?:youtube\.com/(?:watch\?v=|live/)|youtu\.be/)([\w-]{6,})~', $room['youtube_url'], $m)) {
    $ytEmbed = 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&rel=0';
}
ob_start();
if ($st === 'live' && $ytEmbed): ?>
<iframe src="<?=htmlspecialchars($ytEmbed)?>" style="width:100%;height:100%;border:0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
<?php elseif ($st === 'live' && !empty($room['hls_url'])): ?>
<video id="livePlayer" controls autoplay muted playsinline></video>
<?php elseif ($st === 'live' && empty($room['hls_url'])): ?>
<div class="ph"><span class="live-dot"></span>直播进行中<small>播放地址待配置</small></div>
<?php elseif ($st === 'replay' && $ytEmbed): ?>
<iframe src="<?=htmlspecialchars(str_replace('autoplay=1', 'autoplay=0', $ytEmbed))?>" style="width:100%;height:100%;border:0" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>
<?php elseif ($st === 'replay' && !empty($room['replay_url'])): ?>
<video id="livePlayer" controls playsinline></video>
<?php elseif ($st === 'scheduled'): ?>
<div class="ph"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>直播预告<small><?=htmlspecialchars(substr($room['start_at'] ?? '', 0, 16))?> 开播</small></div>
<?php else: ?>
<div class="ph">暂未开播</div>
<?php endif;
$playerHtml = ob_get_clean();

// 多商品卡数据（两种形态共用）
$liveProducts = [];
foreach ((array)($room['products'] ?? []) as $line) {
    $parts = array_map('trim', explode('|', $line));
    if ($parts[0] ?? '') $liveProducts[] = ['title' => $parts[0], 'link' => $parts[1] ?? '#', 'price' => $parts[2] ?? ''];
}
?>

<?php if ($streamMode === 'immersive'): ?>
  <!-- ═══ 沉浸式直播间（抖音式全屏 9:16）：视频铺满，弹幕/操作浮于画面 ═══ -->
  <div class="imm-frame">
    <div class="player player-v live-enter"><?=$playerHtml?></div>
    <div class="imm-top live-enter live-enter-1">
      <a href="/live" class="imm-back" aria-label="返回直播列表">←</a>
      <b><?=htmlspecialchars($room['title'])?></b>
      <?php if ($st === 'live'): ?><span class="badge live"><span class="live-dot"></span>直播中</span><?php else: ?><span class="pill neutral"><?=live_status_label($st)?></span><?php endif; ?>
    </div>
    <?php if ($st === 'scheduled'): ?>
    <div class="imm-sub card" style="padding:16px">
      <b>开播提醒我</b>
      <div class="text-sm" style="margin:6px 0 10px">已有 <b id="subCount"><?=live_sub_count($room['id'])?></b> 人预约</div>
      <div style="display:flex;gap:8px">
        <?php if (!$member): ?><input id="subEmail" class="inp" type="email" placeholder="你的邮箱" style="height:40px"><?php endif; ?>
        <button class="btn primary" id="subBtn" style="height:40px;padding:0 18px;font-size:14px">预约</button>
      </div>
    </div>
    <?php endif; ?>
    <div class="imm-chat live-enter live-enter-2"><div class="chat-box" id="chatBox"></div></div>
    <div class="imm-rail live-enter live-enter-3">
      <button class="act-btn" id="likeBtn" aria-label="点赞">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        <span class="like-n" id="likeN"><?=live_likes($room['id'])?></span>
      </button>
      <?php if ($liveProducts || $sellCourse): ?>
      <button class="act-btn" id="shopBtn" aria-label="商品">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
      </button>
      <?php endif; ?>
    </div>
    <div class="live-actions imm-bar">
      <input class="inp" id="chatInputM" placeholder="说点什么…" maxlength="100">
    </div>
    <!-- 兼容 JS 的隐藏元素（桌面端输入回退 / 推品 / 商品浮层） -->
    <div style="display:none"><input id="chatInput"><a class="prod-link" id="immProdAnchor" href="<?=htmlspecialchars($liveProducts[0]['link'] ?? ($sellCourse ? '/courses/' . $sellCourse['id'] : ''))?>" data-title="<?=htmlspecialchars($liveProducts[0]['title'] ?? $sellCourse['title'] ?? '')?>"></a></div>
    <div class="push-pop" id="pushPop">
      <button class="x" id="pushX" aria-label="关闭">✕</button>
      <div class="k">🔴 主播推荐</div>
      <b id="pushTitle"></b>
      <div class="row"><em id="pushPrice"></em><button class="btn primary" id="pushGo" style="height:38px;padding:0 16px;font-size:13px">去看看</button></div>
    </div>
    <div class="shop-modal" id="shopModal">
      <div class="sheet">
        <div class="sheet-h"><b id="sheetTitle">商品详情</b><button id="sheetClose">← 返回直播</button></div>
        <iframe id="sheetFrame" src="about:blank"></iframe>
      </div>
    </div>
  </div>
  <style>body{padding-bottom:0!important}</style>
<?php else: ?>
  <!-- ═══ 直播间（常规形态） ═══ -->
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="live-room">
    <div class="actions"><a href="/live" class="act">← 返回直播列表</a></div>
    <div class="g-main-aside">
      <div>
        <div class="sp-win">
          <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">live · <?=htmlspecialchars($room['id'])?></div></div>
          <div class="player live-enter<?=$streamMode === 'vertical' ? ' player-v' : ''?>"><?=$playerHtml?></div>
        </div>

        <div class="room-head">
          <h1><?=htmlspecialchars($room['title'])?></h1>
          <?php if ($st === 'live'): ?><span class="badge live"><span class="live-dot"></span>直播中</span><?php else: ?><span class="pill neutral"><?=live_status_label($st)?></span><?php endif; ?>
          <span class="pill neutral" title="当前在线（5分钟内活跃）" style="margin-left:8px">👁 <span id="viewOnline">…</span></span>
        </div>
        <p class="lead" style="font-size:15px;line-height:1.85;color:var(--muted)"><?=nl2br(htmlspecialchars($room['desc'] ?? ''))?></p>
        <?php if (!empty($room['start_at'])): ?><div class="note mono" style="margin-top:0"><?=htmlspecialchars(substr($room['start_at'], 0, 16))?> — <?=htmlspecialchars(substr($room['end_at'] ?? '', 0, 16))?></div><?php endif; ?>

        <?php if ($st === 'replay'):
            $chapters = !empty($room['chapters']) && is_array($room['chapters']) ? $room['chapters'] : live_auto_chapters($room);
            if (count($chapters) > 1 && function_exists('live_save_chapters')) { try { live_save_chapters($room['id'], $chapters); } catch (\Throwable $e) {} }
        ?>
        <!-- 回放章节（自动从弹幕/推品历史提取时间轴，可点击跳转） -->
        <div class="card" style="padding:14px 18px;margin-top:14px">
          <div style="font-size:12px;font-weight:700;color:var(--text-3);margin-bottom:8px">🎬 回放章节</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach ($chapters as $ci => $ch):
                // 时间转秒（章节 t 格式 "MM:SS" 或 "HH:MM"）
                $chParts = array_map('intval', explode(':', $ch['t'] ?? '0:0'));
                $chSec = count($chParts) === 2 ? $chParts[0] * 60 + $chParts[1] : 0;
            ?>
            <button onclick="var v=document.getElementById('livePlayer');if(v&&typeof v.currentTime==='number'){v.currentTime=<?=$chSec?>;v.play();}" style="padding:5px 12px;border-radius:999px;border:1px solid var(--border);background:var(--surface-2);cursor:pointer;font-size:12px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
              <b style="color:var(--accent)"><?=htmlspecialchars($ch['t'])?></b> <?=htmlspecialchars($ch['title'])?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($st === 'scheduled'): ?>
        <!-- 预约提醒（E3）：会员一键预约，游客留邮箱；开播自动通知 -->
        <div class="strip" id="subStrip">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span>
          <div class="tx"><b>开播提醒我</b><span>已有 <b id="subCount"><?=live_sub_count($room['id'])?></b> 人预约 · 开播时通过<?=$member ? '站内信' : '邮件'?>通知</span></div>
          <div style="display:flex;gap:8px;align-items:center">
            <?php if (!$member): ?><input id="subEmail" class="inp" type="email" placeholder="你的邮箱" style="height:40px;min-width:200px"><?php endif; ?>
            <button class="btn primary" id="subBtn" style="height:40px;padding:0 18px;font-size:14px">预约</button>
          </div>
        </div>
        <script>
        document.getElementById('subBtn').onclick = function() {
          var body = new FormData();
          body.append('room_id', <?=json_encode($room['id'])?>);
          var em = document.getElementById('subEmail');
          if (em) body.append('email', em.value.trim());
          fetch('/api/live?action=subscribe', {method:'POST', body:body}).then(function(r){return r.json()}).then(function(d){
            if (d.ok) {
              document.getElementById('subCount').textContent = d.count;
              document.getElementById('subStrip').querySelector('.tx b').textContent = d.dup ? '你已预约过' : '✅ 预约成功';
              document.getElementById('subBtn').disabled = true;
            } else (window.OFShell?OFShell.toast:alert)(d.error || '预约失败');
          });
        };
        </script>
        <?php endif; ?>

        <?php
        // 多商品卡（E3）：课程/插件/咨询/定制服务，每行 "标题|链接|价格文案"（$liveProducts 已在上方统一解析）
        ?>
        <?php if ($liveProducts): ?>
        <div style="display:grid;gap:12px;margin-top:16px">
          <?php foreach ($liveProducts as $lp): ?>
          <div class="strip">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg></span>
            <div class="tx"><span class="kicker" style="font-size:11px">直播间专属</span><b><?=htmlspecialchars($lp['title'])?></b></div>
            <div style="display:flex;align-items:center;gap:14px">
              <?php if ($lp['price'] !== ''): ?><b style="font-family:var(--font-display);font-size:20px;color:var(--ok)"><?=htmlspecialchars($lp['price'])?></b><?php endif; ?>
              <a href="<?=htmlspecialchars($lp['link'])?>" class="btn primary prod-link" data-title="<?=htmlspecialchars($lp['title'])?>" style="height:40px;padding:0 18px;font-size:14px">去看看 →</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($sellCourse): $price = $shopSettings['course_prices'][$sellCourse['id']] ?? 0; ?>
        <div class="strip">
          <?php if (!empty($sellCourse['cover'])): ?><img src="<?=htmlspecialchars($sellCourse['cover'])?>" alt="" style="width:120px;aspect-ratio:16/10;object-fit:cover;border-radius:10px" onerror="this.style.display='none'"><?php else: ?><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span><?php endif; ?>
          <div class="tx"><span class="kicker" style="font-size:11px">直播间同款课程</span><b><?=htmlspecialchars($sellCourse['title'])?></b><span><?=htmlspecialchars($sellCourse['description'] ?? '')?></span></div>
          <div style="text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end"><b style="font-family:var(--font-display);font-size:24px;color:var(--ok)"><?=$price > 0 ? '¥' . number_format($price, 0) : '限时'?></b><a href="/courses/<?=urlencode($sellCourse['id'])?>" class="btn primary prod-link" data-title="<?=htmlspecialchars($sellCourse['title'])?>" style="height:40px;padding:0 18px;font-size:14px">查看课程 →</a></div>
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

    <!-- F2 移动端底部操作条：评论 + 点赞 + 商品 -->
    <div class="live-actions">
      <input class="inp" id="chatInputM" placeholder="说点什么…" maxlength="100">
      <button class="act-btn" id="likeBtn" aria-label="点赞" title="点赞">
        <svg viewBox="0 0 24 24" fill="currentColor" style="color:var(--danger)"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        <span class="like-n" id="likeN"><?=live_likes($room['id'])?></span>
      </button>
      <?php if ($liveProducts || $sellCourse): ?>
      <button class="act-btn" id="shopBtn" aria-label="商品" title="直播间商品">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
      </button>
      <?php endif; ?>
    </div>

    <!-- F2 主播推品弹窗 -->
    <div class="push-pop" id="pushPop">
      <button class="x" id="pushX" aria-label="关闭">✕</button>
      <div class="k">🔴 主播推荐</div>
      <b id="pushTitle"></b>
      <div class="row"><em id="pushPrice"></em><button class="btn primary" id="pushGo" style="height:38px;padding:0 16px;font-size:13px">去看看</button></div>
    </div>

    <!-- F2 商品浮层：购买不离开直播，播放器缩成小窗 -->
    <div class="shop-modal" id="shopModal">
      <div class="sheet">
        <div class="sheet-h"><b id="sheetTitle">商品详情</b><button id="sheetClose">← 返回直播</button></div>
        <iframe id="sheetFrame" src="about:blank"></iframe>
      </div>
    </div>
  </section>
<?php endif; ?>
<script src="/assets/vendor/hls.light.min.js?v=20260907a"></script>
<script>
  var ROOM_ID = <?=json_encode($room['id'])?>;
  var LAST_COUNT = 0, LAST_MSG_ID = '';
  var LAST_PUSH_TS = +(localStorage.getItem('of_push_' + ROOM_ID) || 0);
  var ESC_MAP = {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'};
  function escH(s){ return (s||'').replace(/[<>&"]/g, function(c){ return ESC_MAP[c]; }); }

  /* ── HLS 播放：Safari 原生，其余浏览器 hls.js 兜底（修 Chrome/安卓黑屏）── */
  (function(){
    var v = document.getElementById('livePlayer');
    if (!v) return;
    var src = <?=json_encode((string)($room['hls_url'] ?? $room['replay_url'] ?? ''))?>;
    if (!src) return;
    var canNative = v.canPlayType('application/vnd.apple.mpegurl');
    if (canNative) { v.src = src; return; }              // Safari/iOS 原生
    if (window.Hls && Hls.isSupported()) {                // Chrome/安卓/微信 hls.js
      var hls = new Hls({ lowLatencyMode: true, backBufferLength: 30 });
      hls.loadSource(src);
      hls.attachMedia(v);
      hls.on(Hls.Events.ERROR, function(_, data){
        if (data.fatal) { switch(data.type){
          case Hls.ErrorTypes.NETWORK_ERROR: hls.startLoad(); break;
          case Hls.ErrorTypes.MEDIA_ERROR:   hls.recoverMediaError(); break;
          default: hls.destroy();
        }}
      });
    } else {
      v.src = src;                                        // 最后兜底直连
    }
  })();

  /* ── 性能分级：低端机自动降级（关毛玻璃/飘心限量/轮询降频）── */
  var LOW_POWER = (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4)
    || (navigator.deviceMemory && navigator.deviceMemory <= 4)
    || (navigator.connection && (navigator.connection.saveData || /2g/.test(navigator.connection.effectiveType || '')))
    || matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (LOW_POWER) document.body.classList.add('low-power');
  var POLL_MS = LOW_POWER ? 5000 : 3000;
  var pollTimer = null;

  function loadChat() {
    fetch('/api/live?action=chat&room_id=' + encodeURIComponent(ROOM_ID)).then(function(r){ return r.json(); }).then(function(d) {
      if (!d.ok) return;
      var box = document.getElementById('chatBox');
      // 增量渲染：只追加新消息（按 id 定位），避免每 3s 全量重建 DOM
      var msgs = d.messages, startIdx = 0;
      if (LAST_MSG_ID) {
        for (var k = msgs.length - 1; k >= 0; k--) { if (msgs[k].id === LAST_MSG_ID) { startIdx = k + 1; break; } }
      }
      if (msgs.length - startIdx > 60 || (msgs.length && msgs.length < LAST_COUNT)) {
        box.innerHTML = ''; startIdx = 0;   // 缺口过大或被清理 → 全量重建一次
      }
      if (msgs.length > startIdx) {
        for (var i = startIdx; i < msgs.length; i++) {
          var el = document.createElement('div');
          el.className = 'chat-msg';
          el.innerHTML = '<span class="u">' + escH(msgs[i].user||'游客') + '：</span><span class="t">' + escH(msgs[i].text) + '</span>';
          box.appendChild(el);
        }
        LAST_MSG_ID = msgs[msgs.length-1].id;
        box.scrollTop = box.scrollHeight;
      }
      LAST_COUNT = msgs.length;
      // 点赞数同步
      var likeN = document.getElementById('likeN');
      if (likeN && typeof d.likes === 'number') likeN.textContent = d.likes > 999 ? (d.likes/1000).toFixed(1)+'k' : d.likes;
      // 主播推品：新推品自动弹出（12s 自动收起）
      if (d.push && d.push.ts > LAST_PUSH_TS) {
        LAST_PUSH_TS = d.push.ts;
        localStorage.setItem('of_push_' + ROOM_ID, d.push.ts);
        document.getElementById('pushTitle').textContent = d.push.title;
        document.getElementById('pushPrice').textContent = d.push.price || '';
        document.getElementById('pushGo').onclick = function(){ document.getElementById('pushPop').classList.remove('on'); openShop(d.push.link, d.push.title); };
        document.getElementById('pushPop').classList.add('on');
        setTimeout(function(){ document.getElementById('pushPop').classList.remove('on'); }, 12000);
      }
    });
  }
  document.getElementById('pushX').onclick = function(){ document.getElementById('pushPop').classList.remove('on'); };

  function sendChat() {
    var m = document.getElementById('chatInputM');
    var input = (m && m.offsetParent !== null) ? m : document.getElementById('chatInput');
    var text = input.value.trim();
    if (!text) return;
    var body = new FormData();
    body.append('room_id', ROOM_ID);
    body.append('text', text);
    fetch('/api/live?action=send', { method: 'POST', body: body })
      .then(function(r){ return r.json(); }).then(function(d) {
        if (d.ok) { input.value = ''; loadChat(); }
        else (window.OFShell?OFShell.toast:alert)(d.error || '发送失败');
      });
  }
  document.getElementById('chatInput').addEventListener('keydown', function(e){ if (e.key === 'Enter') sendChat(); });
  var chatInputM = document.getElementById('chatInputM');
  if (chatInputM) chatInputM.addEventListener('keydown', function(e){ if (e.key === 'Enter') sendChat(); });
  loadChat();
  // 页面切后台暂停轮询，回前台立即拉一次（省电省流量）
  function startPoll(){ if (!pollTimer) pollTimer = setInterval(loadChat, POLL_MS); }
  function stopPoll(){ clearInterval(pollTimer); pollTimer = null; }
  document.addEventListener('visibilitychange', function(){ document.hidden ? stopPoll() : (loadChat(), startPoll()); });
  startPoll();

  /* 观看心跳：30s/次（低端机 60s），页面可见时才发；顺带回显在线人数 */
  var VIEW_MS = LOW_POWER ? 60000 : 30000, viewTimer = null;
  function viewPing(){
    if (document.hidden) return;
    var body = 'room_id=' + encodeURIComponent(ROOM_ID);
    fetch('/api/live?action=view', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) return;
        var n = document.getElementById('viewOnline');
        if (n && typeof d.online === 'number') n.textContent = d.online > 999 ? (d.online/1000).toFixed(1)+'k' : d.online;
      }).catch(function(){});
  }
  function startView(){ if (!viewTimer) { viewPing(); viewTimer = setInterval(viewPing, VIEW_MS); } }
  function stopView(){ clearInterval(viewTimer); viewTimer = null; }
  document.addEventListener('visibilitychange', function(){ document.hidden ? stopView() : startView(); });
  startView();

  /* 点赞：飘心动画 + 连击徽标 + 计数（每会话 60 次/分钟限速；低端机限量并发） */
  var HEARTS = ['❤️','🧡','💛','💜','💙','💖'];
  var heartsAlive = 0, HEART_CAP = LOW_POWER ? 4 : 12;
  var likeBtn = document.getElementById('likeBtn');
  var likeCombo = 0, likeComboTimer = 0, comboEl = null;
  function spawnHeart(x, y) {
    if (heartsAlive >= HEART_CAP || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    heartsAlive++;
    var h = document.createElement('div');
    h.className = 'fly-heart';
    h.textContent = HEARTS[Math.floor(Math.random()*HEARTS.length)];
    h.style.left = x + 'px';
    h.style.top = y + 'px';
    h.style.setProperty('--hx', (Math.random()*80-40) + 'px');
    document.body.appendChild(h);
    setTimeout(function(){ h.remove(); heartsAlive--; }, 1500);
  }
  function bumpCombo() {
    likeCombo++;
    clearTimeout(likeComboTimer);
    likeComboTimer = setTimeout(function(){ likeCombo = 0; if (comboEl) { comboEl.remove(); comboEl = null; } }, 1400);
    if (likeCombo >= 3 && likeBtn) {
      if (!comboEl) { comboEl = document.createElement('span'); comboEl.className = 'like-combo'; likeBtn.appendChild(comboEl); }
      comboEl.textContent = '🔥 连击 ×' + likeCombo;
      comboEl.style.animation = 'none'; comboEl.offsetHeight; comboEl.style.animation = '';
    }
  }
  if (likeBtn) likeBtn.addEventListener('click', function() {
    var rect = likeBtn.getBoundingClientRect();
    var burst = likeCombo >= 5 && !LOW_POWER ? 3 : 1; // 连击 ≥5 一次飘 3 颗
    for (var bi = 0; bi < burst; bi++) {
      spawnHeart(rect.left + rect.width/2 - 11 + (Math.random()*24-12), rect.top - 8 - bi*6);
    }
    likeBtn.classList.add('like-tap');
    setTimeout(function(){ likeBtn.classList.remove('like-tap'); }, 130);
    bumpCombo();
    var body = new FormData(); body.append('room_id', ROOM_ID);
    fetch('/api/live?action=like', {method:'POST', body:body}).then(function(r){return r.json()}).then(function(d){
      if (d.count !== undefined) { var n = document.getElementById('likeN'); n.textContent = d.count > 999 ? (d.count/1000).toFixed(1)+'k' : d.count; }
    }).catch(function(){});
  });

  /* 商品浮层：购买不离开直播，播放器缩成小窗 */
  var shopModal = document.getElementById('shopModal');
  var sheetFrame = document.getElementById('sheetFrame');
  window.openShop = function(link, title){
    if (!link || link === '#') return;
    document.cookie = 'of_live_room=' + encodeURIComponent(ROOM_ID) + ';path=/;max-age=86400';
    var sep = link.indexOf('?') > -1 ? '&' : '?';
    sheetFrame.src = link + sep + 'from=live&room=' + encodeURIComponent(ROOM_ID);
    document.getElementById('sheetTitle').textContent = title || '商品详情';
    shopModal.classList.add('on');
    document.body.classList.add('shop-open');
  };
  function closeShop(){ shopModal.classList.remove('on'); document.body.classList.remove('shop-open'); sheetFrame.src = 'about:blank'; }
  document.getElementById('sheetClose').onclick = closeShop;
  shopModal.addEventListener('click', function(e){ if (e.target === shopModal) closeShop(); });
  // 商品卡点击 → 浮层打开（不跳走）
  document.querySelectorAll('.prod-link').forEach(function(a){
    a.addEventListener('click', function(e){ e.preventDefault(); openShop(a.getAttribute('href'), a.getAttribute('data-title') || ''); });
  });
  var shopBtn = document.getElementById('shopBtn');
  if (shopBtn) shopBtn.addEventListener('click', function(){
    var first = document.querySelector('.prod-link');
    if (first) openShop(first.getAttribute('href'), first.getAttribute('data-title') || '');
  });
  // 浮层内购买成功 → 返回直播（order-success 页 postMessage）
  window.addEventListener('message', function(e){ if (e.data === 'of-back-to-live') closeShop(); });
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
