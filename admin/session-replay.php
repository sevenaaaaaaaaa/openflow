<?php
/**
 * 会话录屏回放（轻量）— 按会话重放用户的点击/滚动/事件轨迹
 * 数据来自 events（session_id + 坐标），前端 canvas 动画回放
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('analytics');

$days = max(1, (int)($_GET['days'] ?? 7));
$cutoff = date('Y-m-d', strtotime("-{$days} days"));
$session = trim($_GET['session'] ?? '');

// 拉取有坐标的点击事件，按 session 分组
$clickRows = [];
try { $clickRows = Database::query("SELECT props, page, session_id, created_at FROM events WHERE event='element_click' AND created_at >= ? ORDER BY id ASC", [$cutoff]); } catch (Exception $e) {}

$bySession = [];
foreach ($clickRows as $r) {
    $props = json_decode($r['props'] ?? '[]', true);
    if (!is_array($props) || !isset($props['x'])) continue;
    $sid = $r['session_id'] ?? 'unknown';
    $bySession[$sid][] = ['page' => $r['page'] ?? '/', 'props' => $props, 'at' => $r['created_at'] ?? ''];
}
// 会话信息（按点击数排序）
$sessions = [];
foreach ($bySession as $sid => $evs) {
    $sessions[$sid] = ['id' => $sid, 'clicks' => count($evs), 'page' => ($evs[0]['page'] ?? '/'), 'first' => $evs[0]['at'] ?? '', 'last' => end($evs)['at'] ?? ''];
}
usort($sessions, fn($a, $b) => $b['clicks'] <=> $a['clicks']);

$current = $session !== '' ? ($bySession[$session] ?? []) : ($sessions[0]['id'] ?? '' !== '' ? ($bySession[$sessions[0]['id']] ?? []) : []);
$currentSession = $session !== '' ? $session : ($sessions[0]['id'] ?? '');

admin_header('会话回放');
?>
<div class="admin-layout">
  <?php admin_sidebar('session-replay'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>🎬 会话回放</h1><p class="v-sub">近<?=$days?>天点击行为轨迹 · 轻量回放（坐标动画）</p></div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <span style="font-size:13px;color:var(--muted)">选择会话：</span>
        <?php foreach (array_slice($sessions, 0, 20) as $s): ?>
        <a href="session-replay.php?session=<?=urlencode($s['id'])?>" style="padding:5px 12px;border-radius:999px;font-size:11px;border:1.5px solid <?=$currentSession===$s['id']?'var(--accent)':'var(--border)'?>;<?=$currentSession===$s['id']?'background:var(--accent);color:var(--on-accent)':''?>"><?=htmlspecialchars(substr($s['id'], 0, 10))?> · <?=$s['clicks']?>次</a>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?><span class="text-sm text-muted">暂无带坐标点击的会话数据。</span><?php endif; ?>
      </div>
    </div>

    <?php if (!empty($current)): ?>
    <div class="card">
      <h2 style="margin-bottom:4px">会话 <?=htmlspecialchars(substr($currentSession, 0, 20))?></h2>
      <p class="sub"><?=count($current)?> 次点击 · 页面 <?=htmlspecialchars($current[0]['page'])?> · ▶ 播放轨迹</p>
      <div style="position:relative;border:1px solid var(--border);border-radius:12px;overflow:hidden;height:60vh;background:var(--bg)">
        <iframe id="replayFrame" src="<?=htmlspecialchars('/' . ltrim($current[0]['page'], '/'))?>" style="width:100%;height:100%;border:none;pointer-events:none"></iframe>
        <div style="position:absolute;inset:0;pointer-events:none" id="replayLayer"></div>
        <div id="replayInfo" style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.7);color:#fff;padding:6px 16px;border-radius:999px;font-size:12px">点击 ▶ 播放</div>
      </div>
      <div style="display:flex;gap:10px;margin-top:12px;align-items:center">
        <button onclick="playReplay()" class="btn btn-s btn-sm">▶ 播放</button>
        <button onclick="stopReplay()" class="btn btn-ghost btn-sm">⏸ 暂停</button>
        <select id="replaySpeed" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px"><option value="800">1x</option><option value="400">2x</option><option value="200">4x</option></select>
      </div>
    </div>
    <script>
    var EVENTS = <?=json_encode(array_map(fn($e) => array_merge($e['props'], ['page' => $e['page'], 'at' => $e['at']]), $current))?>;
    var layer = document.getElementById('replayLayer');
    var info = document.getElementById('replayInfo');
    var timer = null, idx = 0;
    function playReplay() {
      stopReplay();
      idx = 0;
      step();
    }
    function step() {
      if (idx >= EVENTS.length) { info.textContent = '回放完成'; return; }
      var e = EVENTS[idx++];
      layer.innerHTML = '';
      var dot = document.createElement('div');
      dot.style.cssText = 'position:absolute;left:' + e.x + 'px;top:' + e.y + 'px;width:16px;height:16px;border-radius:50%;background:rgba(255,80,80,.6);border:2px solid #ff0000;transform:translate(-50%,-50%);animation:ripple .8s ease-out';
      var style = document.createElement('style'); style.textContent = '@keyframes ripple{from{transform:translate(-50%,-50%) scale(1);opacity:1}to{transform:translate(-50%,-50%) scale(3);opacity:0}}';
      document.head.appendChild(style);
      layer.appendChild(dot);
      info.textContent = '#' + idx + ' ' + (e.selector || e.tag || '点击') + ' @ ' + (e.x||0) + ',' + (e.y||0) + ' · ' + (e.at||'').substr(11,8);
      timer = setTimeout(step, parseInt(document.getElementById('replaySpeed').value, 10));
    }
    function stopReplay() { clearTimeout(timer); }
    </script>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
