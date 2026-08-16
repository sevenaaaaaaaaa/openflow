<?php
/**
 * 1v1 咨询管理 — 咨询师库 + 预约单全流程（审核/确认时间/交付回放）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ConsultationSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_once __DIR__ . '/../lib/NotifyChannels.php';
require_login();
require_perm('consultation');

$mentors = con_mentors();
$bookings = con_bookings();
$settings = con_settings();
$message = '';
$courses = json_read(DATA_DIR . '/courses/index.json');
$members = json_read(DATA_DIR . '/members/index.json');

// ─── 保存咨询师 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mentor'])) {
    csrf_verify();
    $id = trim($_POST['mentor_id'] ?? '') ?: 'mentor_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $repCourses = array_filter($_POST['rep_courses'] ?? []);
    con_mentor_save([
        'id' => $id,
        'name' => trim($_POST['name'] ?? ''),
        'title' => trim($_POST['title'] ?? ''),
        'avatar' => trim($_POST['avatar'] ?? ''),
        'intro' => trim($_POST['intro'] ?? ''),
        'specialties' => array_filter(array_map('trim', explode(',', $_POST['specialties'] ?? ''))),
        'price' => (float)($_POST['price'] ?? 0),
        'duration' => trim($_POST['duration'] ?? '60 分钟'),
        'rep_courses' => array_values($repCourses),
        'available' => isset($_POST['available']),
        'sort' => (int)($_POST['sort'] ?? 0),
        'stats' => ['sessions' => 0, 'rating' => 0, 'rating_count' => 0],
    ]);
    $message = '咨询师已保存';
    $mentors = con_mentors();
}
// 删除咨询师
if (isset($_GET['del_mentor'])) {
    con_mentor_delete($_GET['del_mentor']);
    flash('success', '咨询师已删除');
    header('Location: consultation.php');
    exit;
}

// ─── 预约单操作 ───
if (isset($_POST['booking_action'])) {
    $id = $_POST['booking_id'] ?? '';
    $act = $_POST['booking_action'];
    $note = trim($_POST['note'] ?? '');
    $b = con_booking($id) ?? ['member_id' => '', 'mentor_name' => '', 'amount' => 0];
    $memberName = '';
    foreach (member_get_all() as $mm) if ($mm['id'] === $b['member_id']) { $memberName = $mm['name'] ?? ''; break; }
    if ($act === 'approve') { con_booking_update($id, ['status' => 'approved', 'review_note' => $note]); inbox_notify_event('consultation_approved', ['member_id' => $b['member_id'] ?? '']); notify_channels_send('1v1 咨询审核通过', '预约人 ' . $memberName . ' 已通过审核，待付款 ¥' . ($b['amount'] ?? 0), 'admin/consultation.php'); }
    elseif ($act === 'reject') { con_booking_update($id, ['status' => 'rejected', 'review_note' => $note]); notify_channels_send('1v1 咨询被拒绝', $memberName . ' 的咨询报名被拒绝：' . $note, 'admin/consultation.php'); }
    elseif ($act === 'confirm') {
        con_booking_update($id, ['status' => 'confirmed', 'scheduled_at' => trim($_POST['scheduled_at'] ?? ''), 'meeting_link' => trim($_POST['meeting_link'] ?? '')]);
        inbox_notify_event('consultation_confirmed', ['member_id' => $b['member_id'] ?? '', 'scheduled_at' => trim($_POST['scheduled_at'] ?? '')]);
        notify_channels_send('1v1 咨询已约时间', $memberName . ' 咨询时间已确认：' . trim($_POST['scheduled_at'] ?? ''), 'admin/consultation.php');
    } elseif ($act === 'complete') {
        con_booking_update($id, ['status' => 'completed', 'replay_url' => trim($_POST['replay_url'] ?? ''), 'delivery_note' => $note]);
        inbox_notify_event('consultation_completed', ['member_id' => $b['member_id'] ?? '']);
        notify_channels_send('1v1 咨询已交付', $memberName . ' 咨询已完成，回放已提供', 'admin/consultation.php');
    } elseif ($act === 'cancel') { con_booking_update($id, ['status' => 'cancelled', 'review_note' => $note]); notify_channels_send('1v1 咨询已取消', $memberName . ' 的咨询被取消', 'admin/consultation.php'); }
    $message = '预约单已更新';
    $bookings = con_bookings();
}

// ─── 保存设置 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    $s = con_settings();
    $s['enabled'] = isset($_POST['enabled']);
    $s['need_review'] = isset($_POST['need_review']);
    $s['page_title'] = trim($_POST['page_title'] ?? '');
    $s['page_desc'] = trim($_POST['page_desc'] ?? '');
    $s['default_price'] = (float)($_POST['default_price'] ?? 0);
    $s['xfpay_appid'] = trim($_POST['xfpay_appid'] ?? '');
    $s['xfpay_secret'] = trim($_POST['xfpay_secret'] ?? '');
    json_write(DATA_DIR . '/consultation/settings.json', $s);
    $message = '设置已保存';
    $settings = con_settings();
}

$memberMap = [];
foreach ($members as $m) $memberMap[$m['id']] = $m;
$courseMap = [];
foreach ($courses as $c) $courseMap[$c['id']] = $c['title'];

$tabs = $_GET['tab'] ?? 'bookings';
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) $bookings = array_values(array_filter($bookings, fn($b) => $b['status'] === $statusFilter));
// 未处理优先
usort($bookings, function ($a, $b) {
    $pri = ['pending_review' => 0, 'paid' => 1, 'approved' => 2, 'confirmed' => 3, 'completed' => 4, 'cancelled' => 5, 'rejected' => 6];
    $pa = $pri[$a['status']] ?? 9; $pb = $pri[$b['status']] ?? 9;
    return $pa <=> $pb ?: strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

admin_header('1v1 咨询');
?>
<div class="admin-layout">
  <?php admin_sidebar('consultation'); ?>
  <div class="main">
    <h1>🤝 1v1 咨询</h1>
    <p class="sub">咨询师库 + 报名审核 + 付款 + 确认时间 + 线上交付 + 回放</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?tab=bookings" class="btn <?=$tabs==='bookings'?'btn-primary':'btn-ghost'?> btn-sm">📋 预约单 <?=count($bookings)?></a>
      <a href="?tab=mentors" class="btn <?=$tabs==='mentors'?'btn-primary':'btn-ghost'?> btn-sm">👩‍🏫 咨询师 <?=count($mentors)?></a>
      <a href="?tab=settings" class="btn <?=$tabs==='settings'?'btn-primary':'btn-ghost'?> btn-sm">⚙️ 设置</a>
    </div>

    <?php if ($tabs === 'bookings'): ?>
    <!-- 状态筛选 -->
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <a href="?" class="btn btn-ghost btn-sm <?=!$statusFilter?'btn-primary':''?>">全部</a>
      <?php foreach (['pending_review','approved','paid','confirmed','completed','cancelled','rejected'] as $s): ?>
      <a href="?status=<?=$s?>" class="btn btn-ghost btn-sm <?=$statusFilter===$s?'btn-primary':''?>" style="color:<?=con_status_color($s)?>"><?=con_status_label($s)?></a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($bookings)): ?>
    <div class="card empty" style="padding:40px">暂无预约单。前台用户提交报名后会出现在这里。</div>
    <?php else: foreach ($bookings as $b): $mem = $memberMap[$b['member_id']] ?? null; ?>
    <div class="card" style="margin-bottom:14px">
      <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
        <div style="flex:1;min-width:280px">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <strong style="font-size:15px"><?=htmlspecialchars($b['member_name'] ?? '')?></strong>
            <span class="badge" style="background:<?=con_status_color($b['status'])?>;color:#fff;padding:3px 10px;border-radius:999px;font-size:11px"><?=con_status_label($b['status'])?></span>
            <span class="text-sm text-muted">→ <?=htmlspecialchars($b['mentor_name'] ?? '')?></span>
            <span class="text-sm text-muted" style="margin-left:auto"><?=htmlspecialchars(substr($b['created_at'] ?? '', 0, 16))?></span>
          </div>
          <div class="text-sm" style="margin-top:10px;line-height:1.8">
            <div><b>报名资料：</b><?=htmlspecialchars($b['company'] ?? '')?> · <?=htmlspecialchars($b['position'] ?? '')?> · <?=htmlspecialchars($b['phone'] ?? '')?></div>
            <div><b>咨询目标：</b><?=htmlspecialchars($b['goal'] ?? '')?></div>
            <?php if (!empty($b['experience'])): ?><div><b>相关经历：</b><?=htmlspecialchars($b['experience'])?></div><?php endif; ?>
            <?php if (!empty($b['slots'])): ?>
            <div><b>期望时段：</b><?php foreach ($b['slots'] as $i => $sl): ?><span style="margin-right:8px">①<?=$i+1?> <?=htmlspecialchars($sl)?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if (!empty($b['review_note'])): ?><div class="text-muted">📝 <?=htmlspecialchars($b['review_note'])?></div><?php endif; ?>
            <?php if (!empty($b['scheduled_at'])): ?><div style="color:#0891b2"><b>已约时间：</b><?=htmlspecialchars($b['scheduled_at'])?><?php if (!empty($b['meeting_link'])): ?> · <a href="<?=htmlspecialchars($b['meeting_link'])?>" target="_blank">进入会议</a><?php endif; ?></div><?php endif; ?>
            <?php if (!empty($b['replay_url'])): ?><div style="color:var(--ok)"><b>回放：</b><a href="<?=htmlspecialchars($b['replay_url'])?>" target="_blank">观看回放</a><?php if (!empty($b['delivery_note'])): ?> · <?=htmlspecialchars($b['delivery_note'])?><?php endif; ?></div><?php endif; ?>
          </div>
        </div>
        <!-- 操作区 -->
        <div style="width:270px;background:var(--surface-2);border-radius:12px;padding:14px">
          <?php if ($b['status'] === 'pending_review'): ?>
          <form method="post" style="display:flex;flex-direction:column;gap:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?=htmlspecialchars($b['id'])?>">
            <input type="hidden" name="booking_action" value="approve">
            <input type="text" name="note" placeholder="审核备注（可选）" style="padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <button class="btn btn-primary btn-sm">✅ 审核通过 → 待付款</button>
          </form>
          <form method="post" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?=htmlspecialchars($b['id'])?>">
            <input type="hidden" name="booking_action" value="reject">
            <input type="text" name="note" placeholder="拒绝原因（必填）" style="padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <button class="btn btn-danger btn-sm">✕ 拒绝报名</button>
          </form>
          <?php elseif ($b['status'] === 'paid'): ?>
          <form method="post" style="display:flex;flex-direction:column;gap:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?=htmlspecialchars($b['id'])?>">
            <input type="hidden" name="booking_action" value="confirm">
            <input type="text" name="scheduled_at" value="<?=date('Y-m-d H:i')?>" placeholder="确认时间 YYYY-MM-DD HH:MM" style="padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="text" name="meeting_link" placeholder="会议链接（腾讯会议/Zoom）" style="padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <button class="btn btn-primary btn-sm">📅 确认时间并生成链接</button>
          </form>
          <?php elseif ($b['status'] === 'confirmed'): ?>
          <form method="post" style="display:flex;flex-direction:column;gap:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?=htmlspecialchars($b['id'])?>">
            <input type="hidden" name="booking_action" value="complete">
            <input type="text" name="replay_url" placeholder="回放链接（交付后填写）" style="padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="text" name="note" placeholder="交付备注（可选）" style="padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <button class="btn btn-success btn-sm" style="background:var(--ok);color:#fff">✅ 完成交付 + 回放</button>
          </form>
          <?php endif; ?>
          <?php if (in_array($b['status'], ['pending_review','approved','paid','confirmed'])): ?>
          <form method="post" style="margin-top:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?=htmlspecialchars($b['id'])?>">
            <input type="hidden" name="booking_action" value="cancel">
            <input type="hidden" name="note" value="管理员取消">
            <button class="btn btn-ghost btn-sm" onclick="return confirm('确认取消该预约？')" style="width:100%">↺ 取消预约</button>
          </form>
          <?php endif; ?>
          <?php if (in_array($b['status'], ['approved','paid'])): ?>
          <div class="text-sm text-muted" style="margin-top:8px">💰 金额 ¥<?=number_format($b['amount'], 0)?><?=($b['status']==='paid')?' · 已付款':' · 待付款'?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php elseif ($tabs === 'mentors'): ?>
    <!-- 咨询师列表 -->
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>咨询师</th><th>头衔</th><th>专长</th><th>价格</th><th>代表课程</th><th>可用</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($mentors)): ?><tr><td colspan="7" class="empty">暂无咨询师，先添加</td></tr><?php endif; ?>
          <?php foreach ($mentors as $m): ?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:8px"><img src="<?=htmlspecialchars($m['avatar'] ?? '')?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;background:var(--surface-2)" onerror="this.style.display='none'"><strong><?=htmlspecialchars($m['name'])?></strong></div></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($m['title'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(implode('、', array_slice($m['specialties'] ?? [], 0, 3)))?></td>
            <td><strong>¥<?=number_format($m['price'] ?? 0, 0)?></strong></td>
            <td class="text-sm text-muted"><?php foreach (array_slice($m['rep_courses'] ?? [], 0, 2) as $cid): ?><?=htmlspecialchars($courseMap[$cid] ?? $cid)?><br><?php endforeach; ?></td>
            <td><?=!empty($m['available']) ? '<span class="text-sm" style="color:var(--ok)">● 可预约</span>' : '<span class="text-sm" style="color:#9ca3af">○ 停用</span>'?></td>
            <td><a href="#edit-<?=htmlspecialchars($m['id'])?>" class="btn btn-ghost btn-sm">编辑</a> <a href="?del_mentor=<?=urlencode($m['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除？')">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 添加/编辑咨询师 -->
    <div class="card" style="margin-top:18px">
      <h2 id="mentor-form">➕ 添加咨询师</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_mentor" value="1">
        <input type="hidden" name="mentor_id" id="mentor_id" value="">
        <div class="field-row">
          <div class="field"><label>姓名</label><input type="text" name="name" id="m_name" required placeholder="如：王征"></div>
          <div class="field"><label>头衔</label><input type="text" name="title" id="m_title" placeholder="如：增长顾问 · 10 年营销经验"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>头像 URL</label><input type="text" name="avatar" id="m_avatar" placeholder="https://... 或留空用默认"></div>
          <div class="field"><label>单次价格 (¥)</label><input type="number" name="price" id="m_price" value="<?=$settings['default_price']?>" min="0"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>专长 <span class="hint">· 逗号分隔</span></label><input type="text" name="specialties" id="m_specialties" placeholder="SEO/GEO, 内容策略, AI 运营"></div>
          <div class="field"><label>单次时长</label><input type="text" name="duration" id="m_duration" value="60 分钟"></div>
        </div>
        <div class="field"><label>个人介绍</label><textarea name="intro" id="m_intro" rows="3" placeholder="背景、资历、擅长解决的困惑…"></textarea></div>
        <div class="field"><label>代表课程（可多选）</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($courses as $c): ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;background:var(--surface-2);padding:6px 12px;border-radius:8px"><input type="checkbox" name="rep_courses[]" class="m_rep" value="<?=htmlspecialchars($c['id'])?>" style="width:15px;height:15px"> <?=htmlspecialchars(mb_substr($c['title'], 0, 18))?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>排序</label><input type="number" name="sort" id="m_sort" value="0"></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;margin-top:26px"><input type="checkbox" name="available" id="m_available" checked style="width:16px;height:16px"> 可预约</label></div>
        </div>
        <button type="submit" class="btn btn-primary">保存咨询师</button>
      </form>
    </div>
    <script>
    // 点击编辑行 → 填充表单
    document.querySelectorAll('a[href^="#edit-"]').forEach(function(a) {
      a.addEventListener('click', function() {
        // 从行数据填充（简化：提示去数据库管理，实际用行内数据）
      });
    });
    </script>

    <?php else: ?>
    <!-- 设置 -->
    <div class="card" style="max-width:640px">
      <h2>⚙️ 咨询设置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_settings" value="1">
        <div class="field-row">
          <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="enabled" <?=$settings['enabled']?'checked':''?> style="width:16px;height:16px"> 启用 1v1 咨询</label></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="need_review" <?=$settings['need_review']?'checked':''?> style="width:16px;height:16px"> 报名需资格审核</label></div>
        </div>
        <div class="field-row">
          <div class="field"><label>页面标题</label><input type="text" name="page_title" value="<?=htmlspecialchars($settings['page_title'])?>"></div>
          <div class="field"><label>默认单次价格</label><input type="number" name="default_price" value="<?=$settings['default_price']?>" min="0"></div>
        </div>
        <div class="field"><label>页面描述</label><textarea name="page_desc" rows="2"><?=htmlspecialchars($settings['page_desc'])?></textarea></div>
        <h3 style="font-size:14px;margin:14px 0 8px">💳 虎皮椒支付</h3>
        <div class="field-row">
          <div class="field"><label>APPID</label><input type="text" name="xfpay_appid" value="<?=htmlspecialchars($settings['xfpay_appid'])?>"></div>
          <div class="field"><label>通讯密钥</label><input type="text" name="xfpay_secret" value="<?=htmlspecialchars($settings['xfpay_secret'])?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
