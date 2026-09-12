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
$courseLessonsMap = [];
foreach ($courses as $c) {
    $ls = [];
    foreach (($c['chapters'] ?? []) as $ch) foreach (($ch['lessons'] ?? []) as $l) if (!empty($l['id'])) $ls[] = ['id' => $l['id'], 'title' => (($ch['title'] ?? '') !== '' ? $ch['title'] . ' / ' : '') . ($l['title'] ?? '')];
    foreach (($c['lessons'] ?? []) as $l) if (!empty($l['id'])) $ls[] = ['id' => $l['id'], 'title' => $l['title'] ?? ''];
    $courseLessonsMap[$c['id']] = $ls;
}
$message = '';

// 弹幕管理：删除 / 禁言 / 取消禁言
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_action'])) {
    csrf_verify();
    $chatAction = $_POST['chat_action'] ?? '';
    if ($chatAction === 'delete_msg') { live_chat_delete((string)($_POST['msg_id'] ?? '')); flash('success', '弹幕已删除'); }
    elseif ($chatAction === 'mute_user') { live_mute_user(trim((string)($_POST['mute_user'] ?? '')), '后台手动禁言'); flash('success', '已禁言'); }
    elseif ($chatAction === 'unmute_user') { live_unmute_user(trim((string)($_POST['unmute_user'] ?? ''))); flash('success', '已取消禁言'); }
    header('Location: /xmp/live?tab=chat' . (!empty($_POST['room_id']) ? '&room=' . urlencode((string)$_POST['room_id']) : ''));
    exit;
}

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
        'youtube_url' => trim($_POST['youtube_url'] ?? ''),   // YouTube 直播/回放链接（优先于 HLS 播放）
        'replay_url' => trim($_POST['replay_url'] ?? ''),
        'sell_course' => trim($_POST['sell_course'] ?? ''),   // 售卖课程（兼容旧字段）
        'bind_course_id' => trim($_POST['bind_course_id'] ?? ''),   // 绑定课程（作为课程内直播课时）
        'bind_lesson_id' => trim($_POST['bind_lesson_id'] ?? ''),   // 绑定的课时 id
        'products' => array_values(array_filter(array_map('trim', explode("\n", (string)($_POST['products'] ?? ''))))),  // 多商品卡：每行 "标题|链接|价格文案"
        'slow_mode' => max(0, min(60, (int)($_POST['slow_mode'] ?? 3))),   // 慢速模式：同一观众最少间隔秒数
        'stream_mode' => in_array($_POST['stream_mode'] ?? '', ['landscape','vertical','immersive'], true) ? $_POST['stream_mode'] : 'landscape',  // 画面形态
        'push' => $isNew ? null : (live_room($id)['push'] ?? null),        // 推品状态不随编辑丢失
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
    header('Location: /xmp/live');
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
            $notified = live_notify_subs($r['id'], $r['title'] ?? '');   // 通知预约者（E3）
            if ($notified) flash('success', "已开播，并通知了 {$notified} 位预约观众");
        }
    }
    header('Location: /xmp/live');
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
    $s['banned_words'] = trim($_POST['banned_words'] ?? '');   // 弹幕敏感词（每行一个）
    $s['muted'] = trim($_POST['muted'] ?? '');                 // 禁言名单（会员ID或IP哈希，每行一个）
    json_write(live_settings_file(), $s);
    $message = '设置已保存';
    $settings = live_settings();
}

// 主播推品：把商品卡实时推到所有观众屏幕（F2d）
if (isset($_GET['push'], $_GET['room'])) {
    csrf_verify();
    $r = live_room($_GET['room']);
    if ($r) {
        $idx = (int)$_GET['push'];
        if ($idx === -1) {
            $r['push'] = null;   // 取消推品
            flash('success', '已收起商品弹窗');
        } else {
            $products = array_values(array_filter((array)($r['products'] ?? [])));
            $line = $products[$idx] ?? '';
            $parts = array_map('trim', explode('|', $line));
            if ($parts[0] ?? '') {
                $r['push'] = ['title' => $parts[0], 'link' => $parts[1] ?? '', 'price' => $parts[2] ?? '', 'ts' => time()];
                flash('success', '已推送商品卡：' . $parts[0] . '（观众端 3 秒内弹出）');
            }
        }
        live_room_save($r);
    }
    header('Location: /xmp/live');
    exit;
}

$liveRooms = array_values(array_filter($rooms, fn($r) => !empty($r['is_live'])));
$scheduledRooms = array_values(array_filter($rooms, fn($r) => empty($r['is_live'])));

admin_header('直播管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('live'); ?>
  <div class="main">
    <h1>直播管理</h1>
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
      <h2 style="font-size:15px">🎬 开播两条路</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:8px;font-size:13px;line-height:2">
        <div>
          <b>A · 私域自播（自建收流）</b>
          <ol style="padding-left:18px;margin:4px 0 0">
            <li>OBS → 设置 → 推流 → 自定义，服务器填 <code style="background:var(--surface-2);padding:2px 8px;border-radius:6px"><?=htmlspecialchars($settings['rtmp_url'])?></code></li>
            <li>推流密钥用房间的 Stream Key；服务器需 nginx-rtmp / SRS 收流，HLS 地址填进房间「播放地址」</li>
          </ol>
        </div>
        <div>
          <b>B · 推 YouTube，私域同步看（推荐 · 免自建收流）</b>
          <ol style="padding-left:18px;margin:4px 0 0">
            <li>OBS → 推流到 YouTube Studio 给的 RTMP + 密钥（也可用 restream.io 同时分发多平台）</li>
            <li>把 YouTube 直播链接填进房间「YouTube 直播链接」→ 本站观看页自动内嵌播放，弹幕与商品卡照常用</li>
          </ol>
        </div>
      </div>
      <p class="hint" style="margin-top:10px">💡 像 OBS 一样定制画面：把房间的「OBS 叠加层」地址加为浏览器源（宽 1280 高 720，透明背景），直播标题 / 商品卡 / 实时弹幕就会叠在画面上，后台改内容 OBS 里自动更新。</p>
    </div>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?tab=rooms" class="btn <?=($_GET['tab']??'rooms')==='rooms'?'btn-primary':'btn-ghost'?> btn-sm">📡 直播间 <?=count($rooms)?></a>
      <a href="?tab=new" class="btn <?=($_GET['tab']??'')==='new'?'btn-primary':'btn-ghost'?> btn-sm">➕ 新建直播</a>
      <a href="?tab=chat" class="btn <?=($_GET['tab']??'')==='chat'?'btn-primary':'btn-ghost'?> btn-sm">💬 弹幕管理</a>
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
          <b>播放地址：</b><code style="background:var(--surface-2);padding:2px 6px;border-radius:6px;font-size:12px"><?=htmlspecialchars(($r['hls_url'] ?? '') ?: '(未填)')?></code>
        </div>
        <div class="text-sm" style="margin-top:4px;word-break:break-all">
          <b>Stream Key：</b><code style="background:var(--surface-2);padding:2px 6px;border-radius:6px;font-size:12px"><?=htmlspecialchars($r['stream_key'] ?? '')?></code>
          <button class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('<?=htmlspecialchars($r['stream_key'] ?? '')?>').then(()=>fcToast('密钥已复制'))">复制</button>
        </div>
        <?php if (!empty($r['replay_url'])): ?><div class="text-sm" style="margin-top:4px;color:var(--ok)">🎬 回放：<a href="<?=htmlspecialchars($r['replay_url'])?>" target="_blank">观看</a></div><?php endif; ?>
        <div class="text-sm" style="margin-top:4px;word-break:break-all">
          <b>OBS 叠加层：</b><code style="background:var(--surface-2);padding:2px 6px;border-radius:6px;font-size:12px"><?=htmlspecialchars(SITE_URL)?>/live-overlay?room=<?=urlencode($r['id'])?></code>
          <button class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('<?=htmlspecialchars(SITE_URL)?>/live-overlay?room=<?=urlencode($r['id'])?>').then(()=>fcToast('叠加层地址已复制'))">复制</button>
          <span class="hint">· 透明背景，OBS 浏览器源直接贴（宽 1280 高 720）</span>
        </div>
        <div class="text-sm" style="margin-top:4px"><b>预约：</b><?=live_sub_count($r['id'])?> 人 · <b>点赞：</b><?=live_likes($r['id'])?><?php $vs = function_exists('live_view_stats') ? live_view_stats($r['id']) : []; if ($vs): ?> · <b>观看：</b><?=$vs['viewers']?> 人（在线 <?=$vs['online']?>）· <b>时长：</b><?=$vs['minutes']?> 分钟<?php endif; ?> · <a href="/live?room=<?=urlencode($r['id'])?>" target="_blank">前台直播间 →</a></div>
        <?php $rps = array_values(array_filter((array)($r['products'] ?? []))); if ($rps): ?>
        <div class="text-sm" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <b>推品：</b>
          <?php foreach ($rps as $pi => $line): $pt = trim(explode('|', $line)[0]); ?>
          <a href="?room=<?=urlencode($r['id'])?>&push=<?=$pi?>&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm">📣 <?=htmlspecialchars(mb_substr($pt, 0, 12))?></a>
          <?php endforeach; ?>
          <?php if (!empty($r['push'])): ?><a href="?room=<?=urlencode($r['id'])?>&push=-1&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm" style="color:var(--danger)">收起弹窗</a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;min-width:130px">
        <a href="?toggle_live=<?=urlencode($r['id'])?>" class="btn btn-sm <?=!empty($r['is_live']) ? 'btn-danger' : 'btn-success'?>" style="<?=!empty($r['is_live']) ? '' : 'background:var(--ok);color:#fff'?>"><?=!empty($r['is_live']) ? '🔴 结束直播' : '▶️ 标记开播'?></a>
        <a href="?tab=new&edit=<?=urlencode($r['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
        <a href="?del_room=<?=urlencode($r['id'])?>" class="btn btn-danger btn-sm" data-confirm="确认删除？">删除</a>
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
          <div class="field"><label>绑定课程（作为课程内「直播课」课时）</label>
            <select name="bind_course_id" id="bindCourse" onchange="bindCourseLessons()">
              <option value="">— 不绑定 —</option>
              <?php foreach ($courses as $c): ?>
              <option value="<?=htmlspecialchars($c['id'])?>" <?=($r['bind_course_id']??'')===$c['id']?'selected':''?>><?=htmlspecialchars($c['title'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>对应课时</label>
            <select name="bind_lesson_id" id="bindLesson"><option value="">— 先选课程 —</option></select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>开播时间</label><input type="datetime-local" name="start_at" value="<?=htmlspecialchars(str_replace(' ', 'T', $r['start_at'] ?? ''))?>"></div>
          <div class="field"><label>结束时间</label><input type="datetime-local" name="end_at" value="<?=htmlspecialchars(str_replace(' ', 'T', $r['end_at'] ?? ''))?>"></div>
        </div>
        <div class="field"><label>播放地址 <span class="hint">· HLS m3u8 或第三方直播链接</span></label><input type="text" name="hls_url" value="<?=htmlspecialchars($r['hls_url'] ?? '')?>" placeholder="https://your-server/live/xxx.m3u8"></div>
        <div class="field"><label>YouTube 直播链接 <span class="hint">· 海外平台优先，观看页自动转 embed 播放</span></label><input type="text" name="youtube_url" value="<?=htmlspecialchars($r['youtube_url'] ?? '')?>" placeholder="https://www.youtube.com/watch?v=... 或 /live/..."></div>
        <div class="field"><label>回放地址 <span class="hint">· 结束后填写</span></label><input type="text" name="replay_url" value="<?=htmlspecialchars($r['replay_url'] ?? '')?>" placeholder="https://..."></div>
        <div class="field"><label>直播间商品卡 <span class="hint">· 每行一条：标题|链接|价格文案（如 限时 ¥299）。课程/插件/咨询/定制服务都可以</span></label>
          <textarea name="products" rows="3" placeholder="OpenFlow 增长实战课|/courses/xxx|限时 ¥299"><?=htmlspecialchars(implode("\n", (array)($r['products'] ?? [])))?></textarea>
        </div>
        <div class="field-row">
          <div class="field"><label>慢速模式 <span class="hint">· 发言最少间隔秒数（0=不限）</span></label>
            <input type="number" name="slow_mode" value="<?=htmlspecialchars((string)($r['slow_mode'] ?? 3))?>" min="0" max="60">
          </div>
          <div class="field"><label>画面形态 <span class="hint">· 沉浸=抖音式全屏</span></label>
            <select name="stream_mode">
              <option value="landscape" <?=($r['stream_mode'] ?? 'landscape')==='landscape'?'selected':''?>>横屏 16:9（默认）</option>
              <option value="vertical" <?=($r['stream_mode'] ?? '')==='vertical'?'selected':''?>>竖屏 9:16（贴顶布局）</option>
              <option value="immersive" <?=($r['stream_mode'] ?? '')==='immersive'?'selected':''?>>全屏沉浸 9:16（抖音式）</option>
            </select>
          </div>
        </div>
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
        <h3 style="margin:18px 0 4px;font-size:14px">🛡️ 弹幕风控</h3>
        <div class="field"><label>敏感词 <span class="hint">· 每行一个，命中即拒发</span></label>
          <textarea name="banned_words" rows="3" placeholder="广告&#10;加微信"><?=htmlspecialchars($settings['banned_words'] ?? '')?></textarea>
        </div>
        <div class="field"><label>禁言名单 <span class="hint">· 每行一个：会员 ID 或 IP 哈希（游客）。被禁言者发言直接拒绝</span></label>
          <textarea name="muted" rows="2" placeholder="member_id_xxx"><?=htmlspecialchars($settings['muted'] ?? '')?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if (($_GET['tab'] ?? '') === 'chat'): ?>
    <?php $chatRoom = trim((string)($_GET['room'] ?? ($rooms[0]['id'] ?? ''))); $chatMsgs = $chatRoom ? live_chat_admin($chatRoom, 50) : []; $mutedNow = live_muted_list(); ?>
    <div class="card" style="padding:16px;margin-bottom:16px">
      <form method="get" style="display:flex;gap:10px;align-items:center">
        <input type="hidden" name="tab" value="chat">
        <select name="room" onchange="this.form.submit()" style="min-width:200px">
          <?php foreach ($rooms as $r): ?><option value="<?=htmlspecialchars($r['id'])?>" <?=$chatRoom===$r['id']?'selected':''?>><?=htmlspecialchars($r['title'])?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-primary btn-sm">查看</button></noscript>
      </form>
    </div>
    <?php if (!$chatRoom || !$chatMsgs): ?><div class="card empty" style="padding:40px">该房间暂无弹幕</div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>时间</th><th>用户</th><th>内容</th><th style="width:160px">操作</th></tr></thead>
        <tbody>
          <?php foreach ($chatMsgs as $msg): $msgUser = (string)($msg['user'] ?? ''); $isMuted = in_array($msgUser, $mutedNow, true); ?>
          <tr>
            <td class="text-sm text-muted"><?=htmlspecialchars($msg['time'] ?? '')?></td>
            <td><span class="badge <?=$isMuted?'badge-red':'badge-gray'?>" style="font-size:11px"><?=htmlspecialchars($msgUser ?: '匿名')?></span><?php if ($isMuted): ?> <span class="st st-danger" style="font-size:10px">已禁言</span><?php endif; ?></td>
            <td class="text-sm"><?=htmlspecialchars(mb_substr($msg['text'] ?? '', 0, 60))?></td>
            <td>
              <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="chat_action" value="delete_msg"><input type="hidden" name="msg_id" value="<?=htmlspecialchars($msg['id'] ?? '')?>"><input type="hidden" name="room_id" value="<?=htmlspecialchars($chatRoom)?>"><button class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button></form>
              <?php if (!$isMuted && $msgUser): ?>
              <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="chat_action" value="mute_user"><input type="hidden" name="mute_user" value="<?=htmlspecialchars($msgUser)?>"><input type="hidden" name="room_id" value="<?=htmlspecialchars($chatRoom)?>"><button class="btn btn-ghost btn-sm" style="color:var(--warn)">禁言</button></form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($mutedNow): ?>
    <div class="card" style="padding:16px;margin-top:16px">
      <h3 style="font-size:14px;margin-bottom:10px">当前禁言名单（<?=count($mutedNow)?> 人）</h3>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($mutedNow as $mu): ?>
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="chat_action" value="unmute_user"><input type="hidden" name="unmute_user" value="<?=htmlspecialchars($mu)?>"><button class="btn btn-ghost btn-sm" title="点击取消禁言">        <button type="button" class="btn btn-ghost btn-sm" title="点击取消禁言"><?=htmlspecialchars($mu)?> ✕</button></form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
<script>
var COURSE_LESSONS = <?=json_encode($courseLessonsMap, JSON_UNESCAPED_UNICODE)?>;
var CUR_BIND_LESSON = <?=json_encode((string)($r['bind_lesson_id'] ?? ''))?>;
function bindCourseLessons() {
  var c = document.getElementById('bindCourse');
  var sel = document.getElementById('bindLesson');
  if (!c || !sel) return;
  var list = COURSE_LESSONS[c.value] || [];
  sel.innerHTML = '';
  if (!list.length) { sel.innerHTML = '<option value="">— 先选课程 —</option>'; return; }
  list.forEach(function (l) {
    var o = document.createElement('option');
    o.value = l.id; o.textContent = l.title || l.id;
    if (l.id === CUR_BIND_LESSON) o.selected = true;
    sel.appendChild(o);
  });
}
document.addEventListener('DOMContentLoaded', bindCourseLessons);
</script>
