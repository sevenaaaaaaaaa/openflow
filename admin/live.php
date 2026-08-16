<?php
/**
 * 直播管理 — 创建直播房间 / OBS 推流密钥 / 售卖课程 / 回放
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/LiveSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_login();
require_perm('live');

$rooms = live_rooms();
$settings = live_settings();
$courses = json_read(DATA_DIR . '/courses/index.json');
$courseMap = [];
foreach ($courses as $c) $courseMap[$c['id']] = $c['title'];
$message = '';

// 保存房间
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_room'])) {
    csrf_verify();
    $id = trim($_POST['room_id'] ?? '') ?: 'live_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $isNew = !live_room($id);
    $room = [
        'id' => $id,
        'title' => trim($_POST['title'] ?? ''),
        'desc' => trim($_POST['desc'] ?? ''),
        'cover' => trim($_POST['cover'] ?? ''),
        'start_at' => trim($_POST['start_at'] ?? ''),
        'end_at' => trim($_POST['end_at'] ?? ''),
        'hls_url' => trim($_POST['hls_url'] ?? ''),
        'replay_url' => trim($_POST['replay_url'] ?? ''),
        'sell_course' => trim($_POST['sell_course'] ?? ''),   // 售卖课程
        'stream_key' => $isNew ? live_gen_key() : (live_room($id)['stream_key'] ?? live_gen_key()),
        'is_live' => isset($_POST['is_live']),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    live_room_save($room);
    $message = '直播房间已保存' . ($isNew ? '，推流密钥已生成' : '');
    $rooms = live_rooms();
}
// 删除房间
if (isset($_GET['del_room'])) {
    live_rooms_save(array_values(array_filter($rooms, fn($r) => $r['id'] !== $_GET['del_room'])));
    flash('success', '房间已删除');
    header('Location: live.php');
    exit;
}
// 切换直播状态
if (isset($_GET['toggle_live'])) {
    $r = live_room($_GET['toggle_live']);
    if ($r) {
        $wasLive = !empty($r['is_live']);
        $r['is_live'] = !$wasLive;
        live_room_save($r);
        if (!$wasLive && $r['is_live']) {
            inbox_notify_event('live_started', ['title' => $r['title'] ?? '', 'room_id' => $r['id'] ?? '']);
        }
    }
    header('Location: live.php');
    exit;
}
// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    $s = live_settings();
    $s['enabled'] = isset($_POST['enabled']);
    $s['rtmp_url'] = trim($_POST['rtmp_url'] ?? '');
    $s['page_title'] = trim($_POST['page_title'] ?? '');
    $s['page_desc'] = trim($_POST['page_desc'] ?? '');
    json_write(live_settings_file(), $s);
    $message = '设置已保存';
    $settings = live_settings();
}

$liveRooms = array_values(array_filter($rooms, fn($r) => !empty($r['is_live'])));
$scheduledRooms = array_values(array_filter($rooms, fn($r) => empty($r['is_live'])));

admin_header('直播管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('live'); ?>
  <div class="main">
    <h1>📡 直播管理</h1>
    <p class="sub">创建直播间 · OBS 推流 · 售卖课程 · 回放</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
      <div class="card" style="border-left:4px solid var(--danger)"><h3 style="font-size:13px" class="text-muted">🔴 直播中</h3><div style="font-size:26px;font-weight:800"><?=count($liveRooms)?></div></div>
      <div class="card" style="border-left:4px solid #2563eb"><h3 style="font-size:13px" class="text-muted">🕐 预告/未开播</h3><div style="font-size:26px;font-weight:800"><?=count($scheduledRooms)?></div></div>
      <div class="card"><h3 style="font-size:13px" class="text-muted">📡 推流地址</h3><div style="font-size:13px;font-family:var(--mono);word-break:break-all"><?=htmlspecialchars($settings['rtmp_url'])?></div></div>
    </div>

    <!-- OBS 推流说明 -->
    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.08));margin-bottom:20px">
      <h2 style="font-size:15px">🎬 如何用 OBS 推流？</h2>
      <ol style="font-size:13px;line-height:2;padding-left:20px;margin-top:8px">
        <li>下载安装 OBS Studio → 设置 → 推流，选择「自定义」</li>
        <li>服务器：<code style="background:var(--surface-2);padding:2px 8px;border-radius:6px"><?=htmlspecialchars($settings['rtmp_url'])?></code></li>
        <li>推流密钥：使用下方每个房间生成的 <code style="background:var(--surface-2);padding:2px 8px;border-radius:6px">Stream Key</code>（含房间 ID，如 <code>live_xxx</code>）</li>
        <li>服务器需启用 nginx-rtmp / SRS 收流，并把 HLS 播放地址填入房间「播放地址」</li>
      </ol>
    </div>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?tab=rooms" class="btn <?=($_GET['tab']??'rooms')==='rooms'?'btn-primary':'btn-ghost'?> btn-sm">📡 直播间 <?=count($rooms)?></a>
      <a href="?tab=new" class="btn <?=($_GET['tab']??'')==='new'?'btn-primary':'btn-ghost'?> btn-sm">➕ 新建直播</a>
      <a href="?tab=settings" class="btn <?=($_GET['tab']??'')==='settings'?'btn-primary':'btn-ghost'?> btn-sm">⚙️ 设置</a>
    </div>

    <?php if (($_GET['tab'] ?? 'rooms') === 'rooms'): ?>
    <?php if (empty($rooms)): ?>
    <div class="card empty" style="padding:40px">暂无直播间，点击「新建直播」创建第一个</div>
    <?php else: foreach ($rooms as $r): $st = live_status($r); ?>
    <div class="card" style="margin-bottom:14px;display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
      <?php if (!empty($r['cover'])): ?><img src="<?=htmlspecialchars($r['cover'])?>" style="width:120px;height:70px;object-fit:cover;border-radius:10px" onerror="this.style.display='none'"><?php endif; ?>
      <div style="flex:1;min-width:240px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <strong style="font-size:15px"><?=htmlspecialchars($r['title'])?></strong>
          <span class="badge" style="background:<?=live_status_color($st)?>;color:#fff;padding:3px 10px;border-radius:999px;font-size:11px"><?=live_status_label($st)?></span>
          <?php if (!empty($r['sell_course'])): ?><span class="badge" style="background:var(--ok);color:#fff;padding:3px 10px;border-radius:999px;font-size:11px">🎓 售卖：<?=htmlspecialchars(mb_substr($courseMap[$r['sell_course']] ?? $r['sell_course'], 0, 16))?></span><?php endif; ?>
        </div>
        <div class="text-sm text-muted" style="margin-top:6px"><?=htmlspecialchars(substr($r['start_at'] ?? '', 0, 16))?> → <?=htmlspecialchars(substr($r['end_at'] ?? '', 0, 16))?></div>
        <div class="text-sm" style="margin-top:6px;word-break:break-all">
          <b>播放地址：</b><code style="background:var(--surface-2);padding:2px 6px;border-radius:6px;font-size:12px"><?=htmlspecialchars($r['hls_url'] ?: '(未填)')?></code>
        </div>
        <div class="text-sm" style="margin-top:4px;word-break:break-all">
          <b>Stream Key：</b><code style="background:var(--surface-2);padding:2px 6px;border-radius:6px;font-size:12px"><?=htmlspecialchars($r['stream_key'] ?? '')?></code>
          <button class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('<?=htmlspecialchars($r['stream_key'] ?? '')?>').then(()=>fcToast('密钥已复制'))">复制</button>
        </div>
        <?php if (!empty($r['replay_url'])): ?><div class="text-sm" style="margin-top:4px;color:var(--ok)">🎬 回放：<a href="<?=htmlspecialchars($r['replay_url'])?>" target="_blank">观看</a></div><?php endif; ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;min-width:130px">
        <a href="?toggle_live=<?=urlencode($r['id'])?>" class="btn btn-sm <?=!empty($r['is_live']) ? 'btn-danger' : 'btn-success'?>" style="<?=!empty($r['is_live']) ? '' : 'background:var(--ok);color:#fff'?>"><?=!empty($r['is_live']) ? '🔴 结束直播' : '▶️ 标记开播'?></a>
        <a href="?tab=new&edit=<?=urlencode($r['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
        <a href="?del_room=<?=urlencode($r['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除？')">删除</a>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php elseif (($_GET['tab'] ?? '') === 'new'): ?>
    <?php
      $editId = $_GET['edit'] ?? '';
      $r = $editId ? live_room($editId) : null;
    ?>
    <div class="card" style="max-width:720px">
      <h2><?=$r ? '编辑直播间' : '➕ 新建直播间'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_room" value="1">
        <input type="hidden" name="room_id" value="<?=htmlspecialchars($r['id'] ?? '')?>">
        <div class="field"><label>直播标题</label><input type="text" name="title" required value="<?=htmlspecialchars($r['title'] ?? '')?>" placeholder="如：增长圆桌 · 第 3 期"></div>
        <div class="field"><label>直播介绍</label><textarea name="desc" rows="2" placeholder="本期主题、嘉宾、看点…"><?=htmlspecialchars($r['desc'] ?? '')?></textarea></div>
        <div class="field-row">
          <div class="field"><label>封面 URL</label><input type="text" name="cover" value="<?=htmlspecialchars($r['cover'] ?? '')?>" placeholder="https://..."></div>
          <div class="field"><label>售卖课程</label>
            <select name="sell_course">
              <option value="">— 不售卖 —</option>
              <?php foreach ($courses as $c): ?>
              <option value="<?=htmlspecialchars($c['id'])?>" <?=($r['sell_course']??'')===$c['id']?'selected':''?>><?=htmlspecialchars($c['title'])?> · <?=$courseMap[$c['id']]?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>开播时间</label><input type="datetime-local" name="start_at" value="<?=htmlspecialchars(str_replace(' ', 'T', $r['start_at'] ?? ''))?>"></div>
          <div class="field"><label>结束时间</label><input type="datetime-local" name="end_at" value="<?=htmlspecialchars(str_replace(' ', 'T', $r['end_at'] ?? ''))?>"></div>
        </div>
        <div class="field"><label>播放地址 <span class="hint">· HLS m3u8 或第三方直播链接</span></label><input type="text" name="hls_url" value="<?=htmlspecialchars($r['hls_url'] ?? '')?>" placeholder="https://your-server/live/xxx.m3u8"></div>
        <div class="field"><label>回放地址 <span class="hint">· 结束后填写</span></label><input type="text" name="replay_url" value="<?=htmlspecialchars($r['replay_url'] ?? '')?>" placeholder="https://..."></div>
        <?php if ($r): ?>
        <div class="field-row">
          <div class="field"><label>推流密钥</label><input type="text" value="<?=htmlspecialchars($r['stream_key'] ?? '')?>" readonly style="background:var(--surface-2);font-family:var(--mono)"></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;margin-top:26px"><input type="checkbox" name="is_live" <?=!empty($r['is_live'])?'checked':''?> style="width:16px;height:16px"> 正在直播</label></div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">保存直播间</button>
      </form>
    </div>

    <?php else: ?>
    <div class="card" style="max-width:640px">
      <h2>⚙️ 直播设置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_settings" value="1">
        <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="enabled" <?=$settings['enabled']?'checked':''?> style="width:16px;height:16px"> 启用直播功能</label></div>
        <div class="field"><label>OBS 推流地址 (RTMP)</label><input type="text" name="rtmp_url" value="<?=htmlspecialchars($settings['rtmp_url'])?>" placeholder="rtmp://your-server.com/live"></div>
        <div class="field-row">
          <div class="field"><label>页面标题</label><input type="text" name="page_title" value="<?=htmlspecialchars($settings['page_title'])?>"></div>
          <div class="field"><label>页面描述</label><input type="text" name="page_desc" value="<?=htmlspecialchars($settings['page_desc'])?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
