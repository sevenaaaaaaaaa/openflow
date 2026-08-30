<?php
/**
 * 1v1 咨询前台 — 咨询师列表 / 详情 / 报名 / 我的预约
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/ConsultationSystem.php';
require_once __DIR__ . '/lib/ShopSystem.php';

$settings = con_settings();
if (empty($settings['enabled'])) { http_response_code(404); die('服务未开启'); }

$mentors = con_mentors();
$courses = json_read(DATA_DIR . '/courses/index.json');
$courseMap = [];
foreach ($courses as $c) $courseMap[$c['id']] = $c;
$member = member_current();
$view = $_GET['view'] ?? 'list';

// 我的预约
$myBookings = [];
if ($member) {
    $myBookings = array_values(array_filter(con_bookings(), fn($b) => $b['member_id'] === $member['id']));
    usort($myBookings, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
}

$mentorId = $_GET['mentor'] ?? '';
$mentor = $mentorId ? con_mentor($mentorId) : null;

$slotOptions = con_slot_options();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($settings['page_title'] ?? '1v1 咨询')?>  | <?=site_config_get("site_name")?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830a" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .mentor-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:22px;transition:.18s;display:flex;flex-direction:column;gap:12px}
  .mentor-card:hover{box-shadow:var(--shadow-sm);transform:translateY(-2px)}
  .slot-pick{display:flex;flex-wrap:wrap;gap:8px}
  .slot-pick label{cursor:pointer;border:1.5px solid var(--border);border-radius:var(--r-sm);padding:8px 14px;font-size:13px;display:flex;align-items:center;gap:6px;background:var(--surface);transition:.12s}
  .slot-pick label:has(input:checked){border-color:var(--ok);background:var(--ok);color:var(--surface)}
  .slot-pick input{display:none}

  /* 设计语言统一：token 语义工具类（终版契约） */
  .text-faint{color:var(--faint)}.text-muted{color:var(--muted)}.text-fg{color:var(--fg)}
  .text-ok{color:var(--ok)}.text-accent{color:var(--accent)}.text-danger{color:var(--danger)}
  .bg-surface{background:var(--surface)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1120px">
    <?php if ($view === 'detail' && $mentor): ?>
    <!-- ═══ 咨询师详情 ═══ -->
    <a href="/consultation" class="text-sm ">← 返回咨询师列表</a>
    <div class="bg-surface rounded-3xl p-8 mt-4 mb-8" style="border:1px solid var(--border)">
      <div class="flex gap-6 flex-wrap items-start">
        <div style="width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:44px;overflow:hidden">
          <?php if (!empty($mentor['avatar'])): ?><img src="<?=htmlspecialchars($mentor['avatar'])?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>👩‍<span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/></svg></span><?php endif; ?>
        </div>
        <div style="flex:1;min-width:240px">
          <h1 class="text-2xl font-bold"><?=htmlspecialchars($mentor['name'])?></h1>
          <div class="text-sm mt-1" style="color:var(--muted)"><?=htmlspecialchars($mentor['title'] ?? '')?></div>
          <div class="flex gap-2 flex-wrap mt-3">
            <?php foreach ($mentor['specialties'] ?? [] as $t): ?><span class="text-xs px-3 py-1 rounded-full" style="background:var(--bg);color:var(--muted)"># <?=htmlspecialchars($t)?></span><?php endforeach; ?>
          </div>
          <p class="text-sm leading-relaxed mt-4" style="color:var(--muted)"><?=htmlspecialchars($mentor['intro'] ?? '')?></p>
        </div>
        <div class="text-center px-6" style="border-left:1px solid var(--border)">
          <div class="text-3xl font-extrabold" style="color:var(--ok)">¥<?=number_format($mentor['price'] ?? 0, 0)?></div>
          <div class="text-xs text-muted mt-1">/ <?=htmlspecialchars($mentor['duration'] ?? '60 分钟')?></div>
          <a href="#book" class="inline-block mt-4 px-8 py-3 rounded-full font-bold" style="background:var(--accent);color:var(--on-accent)">立即报名 →</a>
        </div>
      </div>
    </div>

    <!-- 代表课程 -->
    <?php $repCourses = array_filter($mentor['rep_courses'] ?? [], fn($cid) => isset($courseMap[$cid])); ?>
    <?php if ($repCourses): ?>
    <h2 class="font-bold text-lg mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span> 代表课程</h2>
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
      <?php foreach ($repCourses as $cid): $c = $courseMap[$cid]; ?>
      <a href="/courses/<?=urlencode($cid)?>" class="bg-surface rounded-2xl overflow-hidden" style="border:1px solid var(--border);text-decoration:none;color:inherit">
        <?php if (!empty($c['cover'])): ?><img src="<?=htmlspecialchars($c['cover'])?>" class="w-full h-36 object-cover" onerror="this.style.display='none'"><?php endif; ?>
        <div class="p-4">
          <div class="font-bold text-sm"><?=htmlspecialchars($c['title'])?></div>
          <div class="text-xs text-muted mt-1 line-clamp-2"><?=htmlspecialchars($c['description'] ?? '')?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 报名表单 -->
    <div class="bg-surface rounded-3xl p-8 mt-8" style="border:1px solid var(--border)" id="book">
      <h2 class="font-bold text-xl mb-2"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg></span> 预约报名</h2>
      <p class="text-sm text-muted mb-6">请填写以下资料，工作人员将进行资格审核；通过后完成付款即可锁定时段。</p>
      <?php if (!$member): ?>
      <div class="rounded-2xl p-6 text-center" style="background:var(--bg)">
        <p class="text-sm mb-4">报名前请先登录/注册会员账号</p>
        <a href="/account?view=login&next=<?=urlencode('/consultation?view=detail&mentor=' . $mentor['id'] . '#book')?>" class="inline-block px-8 py-3 rounded-full font-bold" style="background:var(--accent);color:var(--on-accent)">登录后报名</a>
      </div>
      <?php else: ?>
      <form id="bookForm" onsubmit="return submitBooking(event)">
        <input type="hidden" name="mentor_id" value="<?=htmlspecialchars($mentor['id'])?>">
        <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
          <div><label class="text-sm font-semibold">公司/组织</label><input type="text" name="company" class="w-full mt-1 px-4 py-3 rounded-xl" style="border:1.5px solid var(--border);font-size:14px"></div>
          <div><label class="text-sm font-semibold">职位</label><input type="text" name="position" class="w-full mt-1 px-4 py-3 rounded-xl" style="border:1.5px solid var(--border);font-size:14px"></div>
          <div><label class="text-sm font-semibold">手机号 *</label><input type="tel" name="phone" value="<?=htmlspecialchars($member['phone'] ?? '')?>" class="w-full mt-1 px-4 py-3 rounded-xl" style="border:1.5px solid var(--border);font-size:14px"></div>
          <div><label class="text-sm font-semibold">邮箱</label><input type="email" name="email" value="<?=htmlspecialchars($member['email'] ?? '')?>" class="w-full mt-1 px-4 py-3 rounded-xl" style="border:1.5px solid var(--border);font-size:14px"></div>
        </div>
        <div class="mt-4"><label class="text-sm font-semibold">咨询目标 *</label><textarea name="goal" rows="3" class="w-full mt-1 px-4 py-3 rounded-xl" style="border:1.5px solid var(--border);font-size:14px" placeholder="你希望解决的具体问题 / 想获得的帮助…"></textarea></div>
        <div class="mt-4"><label class="text-sm font-semibold">相关经历</label><textarea name="experience" rows="2" class="w-full mt-1 px-4 py-3 rounded-xl" style="border:1.5px solid var(--border);font-size:14px" placeholder="与本咨询相关的背景（可选）"></textarea></div>
        <div class="mt-4">
          <label class="text-sm font-semibold">请选择 3 个期望时段 *</label>
          <div class="slot-pick mt-2">
            <?php foreach ($slotOptions as $i => $sl): ?>
            <label><input type="radio" name="slot_<?=$i+1?>" value="<?=htmlspecialchars($sl)?>" onchange="bindSlot(<?=$i+1?>, this.value)"> <?=htmlspecialchars($sl)?></label>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="slot1" id="slot1"><input type="hidden" name="slot2" id="slot2"><input type="hidden" name="slot3" id="slot3">
          <p class="text-xs text-muted mt-2">每个时段对应不同日期？提交后导师会与你协商最终时间。</p>
        </div>
        <div class="flex items-center gap-4 mt-6 flex-wrap">
          <button type="submit" class="px-10 py-3 rounded-full font-bold" style="background:var(--accent);color:var(--on-accent)">提交报名</button>
          <span class="text-sm text-muted">资格审核通过后将进入付款环节</span>
        </div>
        <div id="bookMsg" class="text-sm mt-3"></div>
      </form>
      <?php endif; ?>
    </div>

    <?php elseif ($view === 'my' && $member): ?>
    <!-- ═══ 我的预约 ═══ -->
    <h1 class="text-2xl font-bold"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9V4ZM9 10h6M9 14h4"/></svg></span> 我的预约</h1>
    <p class="text-sm text-muted mt-1 mb-6">查看你的 1v1 咨询进度与回放</p>
    <?php if (empty($myBookings)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">暂无预约记录</div>
    <?php else: foreach ($myBookings as $b): $statusColor = con_status_color($b['status']); ?>
    <div class="rounded-3xl p-6 mb-4 flex flex-wrap gap-4 items-start" style="background:var(--surface);border:1px solid var(--border)">
      <div style="flex:1;min-width:260px">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="font-bold"><?=htmlspecialchars($b['mentor_name'] ?? '')?></span>
          <span class="text-xs px-3 py-1 rounded-full" style="background:<?=$statusColor?>;color:var(--surface)"><?=con_status_label($b['status'])?></span>
        </div>
        <div class="text-sm mt-2 text-muted"><?=htmlspecialchars($b['goal'] ?? '')?></div>
        <?php if (!empty($b['slots'])): ?><div class="text-xs text-muted mt-2">期望时段：<?=htmlspecialchars(implode(' / ', $b['slots']))?></div><?php endif; ?>
        <?php if (!empty($b['scheduled_at'])): ?><div class="text-sm mt-2" style="color:var(--accent)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></span> 已约时间：<?=htmlspecialchars($b['scheduled_at'])?><?php if (!empty($b['meeting_link'])): ?> · <a href="<?=htmlspecialchars($b['meeting_link'])?>" target="_blank" class="underline">进入线上会议</a><?php endif; ?></div><?php endif; ?>
        <?php if (!empty($b['replay_url'])): ?><div class="text-sm mt-2" style="color:var(--ok)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span> 回放：<a href="<?=htmlspecialchars($b['replay_url'])?>" target="_blank" class="underline">观看咨询回放</a></div><?php endif; ?>
        <?php if (!empty($b['review_note'])): ?><div class="text-xs text-muted mt-2"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg></span> <?=htmlspecialchars($b['review_note'])?></div><?php endif; ?>
      </div>
      <div class="text-right" style="min-width:120px">
        <div class="text-lg font-extrabold" style="color:var(--ok)">¥<?=number_format($b['amount'] ?? 0, 0)?></div>
        <div class="text-xs text-muted"><?=htmlspecialchars(substr($b['created_at'] ?? '', 0, 10))?></div>
        <?php if ($b['status'] === 'approved'): ?>
        <button class="mt-2 px-6 py-2 rounded-full text-sm font-bold" style="background:var(--ok);color:var(--surface)" onclick="payBooking('<?=htmlspecialchars($b['id'])?>', this)">去付款</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php else: ?>
    <!-- ═══ 咨询师列表 ═══ -->
    <div class="text-center py-6 mb-8">
      <h1 class="text-3xl font-extrabold" style="display:flex;align-items:center"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-4px;margin-right:8px"><path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-5.5V5a1.5 1.5 0 0 1 3 0v4m0-4.5A1.5 1.5 0 0 1 16 5v4m0-3.5a1.5 1.5 0 0 1 3 0V14a6 6 0 0 1-6 6h-1.5a6 6 0 0 1-4.7-2.3L4 13.5a1.6 1.6 0 0 1 2.4-2.1L8 13V8a1.5 1.5 0 0 1 3 0"/></svg><?=htmlspecialchars($settings['page_title'] ?? '1v1 专家咨询')?></h1>
      <p class="text-muted mt-3 max-w-xl mx-auto"><?=htmlspecialchars($settings['page_desc'] ?? '')?></p>
    </div>

    <?php if (empty(array_filter($mentors, fn($m) => !empty($m['available'])))): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">咨询师正在准备中，敬请期待</div>
    <?php else: ?>
    <div class="grid gap-5" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
      <?php foreach ($mentors as $m): if (empty($m['available'])) continue; ?>
      <a href="/consultation?view=detail&mentor=<?=urlencode($m['id'])?>" class="mentor-card" style="text-decoration:none;color:inherit">
        <div style="display:flex;align-items:center;gap:14px">
          <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--blob-b),var(--blob-c));display:grid;place-items:center;font-size:24px;overflow:hidden">
            <?php if (!empty($m['avatar'])): ?><img src="<?=htmlspecialchars($m['avatar'])?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>👩‍<span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/></svg></span><?php endif; ?>
          </div>
          <div>
            <div class="font-bold text-lg"><?=htmlspecialchars($m['name'])?></div>
            <div class="text-xs text-muted mt-0.5"><?=htmlspecialchars($m['title'] ?? '')?></div>
          </div>
        </div>
        <div class="text-sm text-muted leading-relaxed line-clamp-3"><?=htmlspecialchars($m['intro'] ?? '')?></div>
        <div class="flex gap-2 flex-wrap">
          <?php foreach (array_slice($m['specialties'] ?? [], 0, 3) as $t): ?><span class="text-xs px-3 py-1 rounded-full" style="background:var(--bg);color:var(--muted)"># <?=htmlspecialchars($t)?></span><?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:10px;border-top:1px solid var(--bg-soft)">
          <span class="text-xl font-extrabold" style="color:var(--ok)">¥<?=number_format($m['price'] ?? 0, 0)?><span class="text-xs font-normal text-muted">/ <?=htmlspecialchars($m['duration'] ?? '60 分钟')?></span></span>
          <span class="text-xs px-4 py-2 rounded-full" style="background:var(--accent);color:var(--on-accent)">查看详情 →</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($member): ?>
    <div class="text-center mt-8"><a href="/consultation?view=my" class="text-sm  underline">查看我的预约 →</a></div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <footer class="pt-10 pb-8" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1120px">
      <div class="mb-2"><?=site_config_get("site_name")?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>

<script>
function bindSlot(idx, val) { document.getElementById('slot' + idx).value = val; }
function submitBooking(e) {
  e.preventDefault();
  var f = document.getElementById('bookForm');
  var msg = document.getElementById('bookMsg');
  var btn = f.querySelector('button[type=submit]');
  if (!f.slot1.value || !f.slot2.value || !f.slot3.value) {
    msg.innerHTML = '<span style="color:var(--danger)">请选择 3 个期望时段</span>'; return false;
  }
  if (btn) { btn.disabled = true; btn.textContent = '提交中…'; }
  msg.innerHTML = '<span style="color:var(--muted)">正在提交…</span>';
  var body = new FormData(f);
  fetch('/api/consultation?action=book', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        msg.innerHTML = '<span style="color:var(--ok)">✅ 报名成功！请前往「我的预约」等待资格审核。</span>';
        setTimeout(function(){ location.href = '/consultation?view=my'; }, 1200);
      } else {
        if (btn) { btn.disabled = false; btn.textContent = '提交报名'; }
        msg.innerHTML = '<span style="color:var(--danger)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01M15 9h.01"/></svg></span> ' + (d.error || '提交失败') + '</span>';
      }
    }).catch(function(){ if (btn) { btn.disabled = false; btn.textContent = '提交报名'; } msg.innerHTML = '<span style="color:var(--danger)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01M15 9h.01"/></svg></span> 网络异常</span>'; });
  return false;
}
function payBooking(id, btn) {
  btn.disabled = true;
  btn.textContent = '跳转支付…';
  var body = new FormData(); body.append('booking_id', id);
  fetch('/api/consultation?action=pay', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok && d.payment && d.payment.ok) {
        var form = document.createElement('form');
        form.method = 'POST'; form.action = d.payment.gateway; form.target = '_blank';
        Object.keys(d.payment.params).forEach(function(k){
          var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = d.payment.params[k];
          form.appendChild(i);
        });
        document.body.appendChild(form); form.submit();
      } else {
        alert(d.error || '支付失败'); btn.disabled = false; btn.textContent = '去付款';
      }
    }).catch(function(){ alert('网络异常'); btn.disabled = false; btn.textContent = '去付款'; });
}
</script>
</body>
</html>
