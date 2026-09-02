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
// 预约状态 → 共享 badge 修饰（颜色只从 token 来；lib 里的 hex 表是后台用的）
$statusBadge = ['pending_review'=>'badge warn','approved'=>'pill hl','paid'=>'pill hl','confirmed'=>'badge ok','completed'=>'badge ok','rejected'=>'badge danger','cancelled'=>'pill neutral'];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($settings['page_title'] ?? '1v1 咨询')?> | <?=site_config_get("site_name")?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 咨询独有：导师头像、时段选择器、预约记录行。其余全部来自 modules.css。 */
.mav{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--accent-soft),var(--ok-soft));color:var(--accent);display:grid;place-items:center;overflow:hidden;flex:0 0 auto}
.mav img{width:100%;height:100%;object-fit:cover}
.mav svg{width:24px;height:24px}
.mav.lg{width:110px;height:110px}.mav.lg svg{width:44px;height:44px}
.mentor{display:flex;flex-direction:column;gap:14px;color:inherit;text-decoration:none}
.mentor .hd{display:flex;align-items:center;gap:14px}
.mentor .hd b{font-size:18px;font-weight:800;letter-spacing:-.01em;display:block}
.mentor .hd span{font-size:12.5px;color:var(--faint)}
.mentor p{font-size:14px;color:var(--muted);line-height:1.75;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.mentor .ft{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:14px;border-top:1px solid var(--border-soft)}
.price{font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--ok);letter-spacing:-.01em}
.price small{font-family:var(--font-body);font-size:12px;font-weight:400;color:var(--faint);margin-left:4px}
.m-prof{display:flex;gap:clamp(20px,3vw,32px);flex-wrap:wrap;align-items:flex-start}
.m-prof .bio{flex:1;min-width:240px;display:flex;flex-direction:column;gap:10px}
.m-prof .bio h1{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em}
.m-prof .bio p{font-size:14.5px;color:var(--muted);line-height:1.8}
.m-prof .buy{text-align:center;padding-left:clamp(20px,3vw,32px);border-left:1px solid var(--border-soft);display:flex;flex-direction:column;gap:8px;align-items:center}
.slot-pick{display:flex;flex-wrap:wrap;gap:8px}
.slot-pick label{cursor:pointer;border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;font-size:13px;font-weight:600;background:var(--surface);transition:border-color .15s,background .15s,color .15s}
.slot-pick label:hover{border-color:var(--border-strong)}
.slot-pick label:has(input:checked){border-color:var(--accent);background:var(--accent);color:var(--on-accent)}
.slot-pick input{position:absolute;opacity:0;pointer-events:none}
.bk{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start}
.bk .main{flex:1;min-width:260px;display:flex;flex-direction:column;gap:8px}
.bk .main .who{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-weight:800;font-size:16px}
.bk .main p{font-size:14px;color:var(--muted);line-height:1.7}
.bk .main .ln{font-size:13px;display:flex;align-items:center;gap:6px}
.bk .main .ln .ic{width:15px;height:15px;flex:0 0 auto}.bk .main .ln .ic svg{width:15px;height:15px}
.bk .side{text-align:right;min-width:120px;display:flex;flex-direction:column;gap:6px;align-items:flex-end}
@media (max-width:860px){.m-prof .buy{border-left:0;padding-left:0;width:100%;align-items:flex-start;text-align:left}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('enterprise'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
<?php if ($view === 'detail' && $mentor): ?>
  <!-- ═══ 咨询师详情 ═══ -->
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="con-detail">
    <div class="actions"><a href="/consultation" class="act">← 返回咨询师列表</a></div>
    <div class="card m-prof">
      <div class="mav lg"><?php if (!empty($mentor['avatar'])): ?><img src="<?=htmlspecialchars($mentor['avatar'])?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/></svg><?php endif; ?></div>
      <div class="bio">
        <div><h1><?=htmlspecialchars($mentor['name'])?></h1><div class="note" style="margin-top:4px;font-size:13.5px"><?=htmlspecialchars($mentor['title'] ?? '')?></div></div>
        <?php if (!empty($mentor['specialties'])): ?><div class="tags"><?php foreach ($mentor['specialties'] as $t): ?><span># <?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
        <p><?=htmlspecialchars($mentor['intro'] ?? '')?></p>
      </div>
      <div class="buy">
        <span class="price" style="font-size:32px">¥<?=number_format($mentor['price'] ?? 0, 0)?></span>
        <span class="note" style="margin-top:0">/ <?=htmlspecialchars($mentor['duration'] ?? '60 分钟')?></span>
        <a href="#book" class="btn primary" style="margin-top:8px">立即报名 →</a>
      </div>
    </div>
  </section>

  <?php $repCourses = array_filter($mentor['rep_courses'] ?? [], fn($cid) => isset($courseMap[$cid])); ?>
  <?php if ($repCourses): ?>
  <section id="rep" class="sec reveal" data-od-anchor data-od-id="con-courses">
    <div class="sec-head row"><div><span class="kicker">COURSES</span><h2>代表课程</h2></div></div>
    <div class="a-grid">
      <?php foreach ($repCourses as $cid): $c = $courseMap[$cid]; ?>
      <a href="/courses/<?=urlencode($cid)?>" class="a-card">
        <div class="cov"><?php if (!empty($c['cover'])): ?><img src="<?=htmlspecialchars($c['cover'])?>" alt="" onerror="this.style.display='none'"><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg><?php endif; ?></div>
        <div class="bd"><span class="cat">课程</span><h3><?=htmlspecialchars($c['title'])?></h3><div class="meta" style="font-family:var(--font-body)"><?=htmlspecialchars(mb_strimwidth($c['description'] ?? '', 0, 80, '…'))?></div></div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section id="book" class="sec reveal" data-od-anchor data-od-id="con-book">
    <div class="contact-wrap">
      <div class="ct-pitch">
        <span class="kicker">BOOKING</span>
        <h2>预约报名</h2>
        <p class="lead">请填写以下资料，工作人员将进行资格审核；通过后完成付款即可锁定时段。</p>
        <ul class="ct-list">
          <li>提交后 1 个工作日内完成资格审核</li>
          <li>审核通过 → 付款 → 导师与你协商最终时间</li>
          <li>咨询结束后可回看完整回放</li>
        </ul>
      </div>
      <div class="form-card">
        <?php if (!$member): ?>
        <div class="gate-box" style="text-align:center"><p class="lead" style="font-size:15px">报名前请先登录 / 注册会员账号</p><a href="/account?view=login&next=<?=urlencode('/consultation?view=detail&mentor=' . $mentor['id'] . '#book')?>" class="btn primary">登录后报名</a></div>
        <?php else: ?>
        <form id="bookForm" class="form-grid" onsubmit="return submitBooking(event)">
          <input type="hidden" name="mentor_id" value="<?=htmlspecialchars($mentor['id'])?>">
          <div class="grid g2" style="gap:14px">
            <div class="field"><label for="f-company">公司 / 组织</label><input id="f-company" type="text" name="company" class="inp"></div>
            <div class="field"><label for="f-position">职位</label><input id="f-position" type="text" name="position" class="inp"></div>
            <div class="field"><label for="f-phone">手机号 *</label><input id="f-phone" type="tel" name="phone" value="<?=htmlspecialchars($member['phone'] ?? '')?>" class="inp"></div>
            <div class="field"><label for="f-email">邮箱</label><input id="f-email" type="email" name="email" value="<?=htmlspecialchars($member['email'] ?? '')?>" class="inp"></div>
          </div>
          <div class="field"><label for="f-goal">咨询目标 *</label><textarea id="f-goal" name="goal" rows="3" class="inp" placeholder="你希望解决的具体问题 / 想获得的帮助…"></textarea></div>
          <div class="field"><label for="f-exp">相关经历</label><textarea id="f-exp" name="experience" rows="2" class="inp" placeholder="与本咨询相关的背景（可选）"></textarea></div>
          <div class="field">
            <label>请选择 3 个期望时段 *</label>
            <div class="slot-pick">
              <?php foreach ($slotOptions as $i => $sl): ?>
              <label><input type="radio" name="slot_<?=$i+1?>" value="<?=htmlspecialchars($sl)?>" onchange="bindSlot(<?=$i+1?>, this.value)"><?=htmlspecialchars($sl)?></label>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="slot1" id="slot1"><input type="hidden" name="slot2" id="slot2"><input type="hidden" name="slot3" id="slot3">
            <p class="note">每个时段对应不同日期？提交后导师会与你协商最终时间。</p>
          </div>
          <div class="cta-row" style="align-items:center"><button type="submit" class="btn primary">提交报名</button><span class="note" style="margin:0">资格审核通过后将进入付款环节</span></div>
          <div id="bookMsg" class="note" style="font-size:13.5px"></div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php elseif ($view === 'my' && $member): ?>
  <!-- ═══ 我的预约 ═══ -->
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="con-my">
    <div class="actions"><a href="/consultation" class="act">← 返回咨询师列表</a></div>
    <div class="sec-head"><span class="kicker">MY BOOKINGS</span><h2>我的预约</h2><p class="lead">查看你的 1v1 咨询进度与回放</p></div>
    <?php if (empty($myBookings)): ?>
    <div class="empty">暂无预约记录</div>
    <?php else: foreach ($myBookings as $b): ?>
    <div class="card bk">
      <div class="main">
        <div class="who"><?=htmlspecialchars($b['mentor_name'] ?? '')?><span class="<?=$statusBadge[$b['status']] ?? 'pill neutral'?>"><?=con_status_label($b['status'])?></span></div>
        <p><?=htmlspecialchars($b['goal'] ?? '')?></p>
        <?php if (!empty($b['slots'])): ?><div class="note" style="margin:0">期望时段：<?=htmlspecialchars(implode(' / ', $b['slots']))?></div><?php endif; ?>
        <?php if (!empty($b['scheduled_at'])): ?><div class="ln" style="color:var(--accent)"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></span>已约时间：<?=htmlspecialchars($b['scheduled_at'])?><?php if (!empty($b['meeting_link'])): ?> · <a href="<?=htmlspecialchars($b['meeting_link'])?>" target="_blank" rel="noopener" style="text-decoration:underline">进入线上会议</a><?php endif; ?></div><?php endif; ?>
        <?php if (!empty($b['replay_url'])): ?><div class="ln" style="color:var(--ok)"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span>回放：<a href="<?=htmlspecialchars($b['replay_url'])?>" target="_blank" rel="noopener" style="text-decoration:underline">观看咨询回放</a></div><?php endif; ?>
        <?php if (!empty($b['review_note'])): ?><div class="ln note" style="margin:0"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg></span><?=htmlspecialchars($b['review_note'])?></div><?php endif; ?>
      </div>
      <div class="side">
        <span class="price">¥<?=number_format($b['amount'] ?? 0, 0)?></span>
        <span class="note mono" style="margin:0"><?=htmlspecialchars(substr($b['created_at'] ?? '', 0, 10))?></span>
        <?php if ($b['status'] === 'approved'): ?><button type="button" class="btn primary" style="height:40px;padding:0 18px;font-size:14px" onclick="payBooking('<?=htmlspecialchars($b['id'])?>', this)">去付款</button><?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </section>

<?php else: ?>
  <!-- ═══ 咨询师列表 ═══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="con-hero">
    <div class="hero-center">
      <span class="kicker">1V1 · 专家咨询</span>
      <h1><?=htmlspecialchars($settings['page_title'] ?? '1v1 专家咨询')?></h1>
      <?php if (!empty($settings['page_desc'])): ?><p class="lead"><?=htmlspecialchars($settings['page_desc'])?></p><?php endif; ?>
      <?php if ($member): ?><div class="cta-row"><a href="/consultation?view=my" class="btn ghost">查看我的预约 →</a></div><?php endif; ?>
    </div>
  </section>

  <section id="mentors" class="sec reveal" data-od-anchor data-od-id="con-list">
    <?php $avail = array_filter($mentors, fn($m) => !empty($m['available'])); ?>
    <div class="sec-head row"><div><span class="kicker">MENTORS</span><h2>咨询师</h2></div><span class="sub"><?=count($avail)?> 位可约</span></div>
    <?php if (empty($avail)): ?>
    <div class="empty">咨询师正在准备中，敬请期待</div>
    <?php else: ?>
    <div class="grid g3" style="gap:18px">
      <?php foreach ($avail as $m): ?>
      <a href="/consultation?view=detail&mentor=<?=urlencode($m['id'])?>" class="card hov mentor" style="padding:24px">
        <div class="hd">
          <div class="mav"><?php if (!empty($m['avatar'])): ?><img src="<?=htmlspecialchars($m['avatar'])?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/></svg><?php endif; ?></div>
          <div><b><?=htmlspecialchars($m['name'])?></b><span><?=htmlspecialchars($m['title'] ?? '')?></span></div>
        </div>
        <p><?=htmlspecialchars($m['intro'] ?? '')?></p>
        <?php if (!empty($m['specialties'])): ?><div class="tags"><?php foreach (array_slice($m['specialties'], 0, 3) as $t): ?><span># <?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
        <div class="ft"><span class="price">¥<?=number_format($m['price'] ?? 0, 0)?><small>/ <?=htmlspecialchars($m['duration'] ?? '60 分钟')?></small></span><span class="btn subtle">查看详情 →</span></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="reveal" data-od-id="con-cta">
    <div class="cta-band">
      <span class="kicker">TEAM PLAN</span>
      <h2>团队想整套落地？</h2>
      <p class="lead">企业版提供驻场诊断、私有化部署与陪跑，把 1v1 的判断变成组织的能力。</p>
      <div class="cta-row"><a href="/enterprise" class="btn primary">了解企业版</a><a href="/courses" class="btn ghost">先看看课程</a></div>
    </div>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
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
