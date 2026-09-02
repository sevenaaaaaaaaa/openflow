<?php
/**
 * 课程详情/播放页 — 购买 + 已购观看 + 学习进度（打勾/续播/完成度）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/ShopSystem.php';
require_once __DIR__ . '/lib/ProgressSystem.php';
require_once __DIR__ . '/lib/MembershipSystem.php';
require_once __DIR__ . '/lib/CommentSystem.php';

$courseId = req_str('id', '', false);
$courseKey = $courseId ?: req_str('course') ?: req_str('slug');
$course = null;
foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
    if (($c['id'] ?? '') === $courseKey || ($c['slug'] ?? '') === $courseKey) { $course = $c; break; }
}
if (!$course) { http_response_code(404); die('课程不存在'); }
$courseId = $course['id'] ?? $courseKey;

$member = member_current();
$settings = shop_settings();
$price = $settings['course_prices'][$courseId] ?? 0;
// 课程限时折扣展示
$cpromo = $settings['course_promos'][$courseId] ?? [];
$_now = time();
$coursePromoOn = !empty($cpromo['price']) && $cpromo['price'] > 0
    && (!$cpromo['start'] || strtotime($cpromo['start']) <= $_now)
    && (!$cpromo['end'] || strtotime($cpromo['end']) >= $_now);
$originalPrice = $price;
if ($coursePromoOn) $price = (float)$cpromo['price'];

// 收藏状态
$favFile = DATA_DIR . '/course-favorites.json';
$favs = json_read($favFile);
$isFav = $member && !empty($favs[$member['id']][$courseId]);

// 权益解锁：已购 / 激活码激活 / VIP 全通 / 订阅
$hasAccess = false;
if ($member) {
    if (in_array($courseId, shop_course_ids_for_member($member['id']))) $hasAccess = true;
    // 激活码激活的课程
    if (!$hasAccess) {
        foreach (($member['activated_products'] ?? []) as $ap) {
            if (($ap['goods_type'] ?? '') === 'course' && ($ap['goods_id'] ?? '') === $courseId) { $hasAccess = true; break; }
        }
    }
    if (!$hasAccess) $hasAccess = member_can($member, 'courses', ['course_id' => $courseId]);
}

// 学习进度
$progress = $member ? progress_get($member['id'], $courseId) : [];
$summary = $member && $hasAccess ? progress_summary($member['id'], $courseId, $course) : ['total' => 0, 'done' => 0, 'in_progress' => 0, 'percent' => 0];
$resume = $member && $hasAccess ? progress_resume($member['id'], $courseId, $course) : null;

// 扁平化章节便于 JS
$lessonsFlat = [];
foreach ($course['chapters'] ?? [] as $ch) {
    foreach ($ch['lessons'] ?? [] as $l) {
        $lessonsFlat[$l['id']] = $l;
    }
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($course['title'])?> | OpenFlow 课程</title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 课程播放页独有：目录行、进度条、播放器画布、测验。其余全部来自 modules.css。 */
.cp-title h1{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;line-height:1.25}
.cp-title p{font-size:14.5px;color:var(--muted);line-height:1.8;margin-top:8px}
.prog{display:flex;flex-direction:column;gap:8px}
.prog .row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--muted);font-family:var(--font-mono)}
.prog .bar{height:8px;background:var(--border);border-radius:99px;overflow:hidden}
.prog .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--ok),var(--accent));border-radius:99px}
.player{aspect-ratio:16/9;background:var(--fg);color:var(--on-accent);display:grid;place-items:center;text-align:center;border-radius:var(--r-md);overflow:hidden}
.player .ph{display:flex;flex-direction:column;align-items:center;gap:8px}
.player .ph svg{width:40px;height:40px}
.player .ph b{font-size:17px;font-weight:700;margin-top:6px}
.player .ph small{font-size:13px;opacity:.65}
.player .ph .btn{margin-top:14px}
.toc{display:flex;flex-direction:column;gap:2px}
.toc .ch{font-size:13px;font-weight:700;color:var(--faint);letter-spacing:.04em;text-transform:uppercase;font-family:var(--font-mono);margin:16px 0 6px}
.lesson{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;cursor:pointer;transition:background .12s;font-size:14px}
.lesson:hover{background:var(--hover)}
.lesson.locked{opacity:.6;cursor:not-allowed}
.lesson.active{background:var(--accent);color:var(--on-accent)}
.lesson .t{flex:1;min-width:0}
.lesson .d{font-size:12px;color:var(--faint);font-family:var(--font-mono)}
.lesson.active .d{color:inherit;opacity:.75}
.lesson .chk{width:20px;height:20px;border-radius:50%;border:2px solid var(--border-strong);display:grid;place-items:center;font-size:11px;flex:0 0 auto;color:var(--on-accent)}
.lesson .chk.done{background:var(--ok);border-color:var(--ok)}
.lesson .chk.playing{background:var(--warn);border-color:var(--warn)}
.lesson .lock{width:14px;height:14px;color:var(--warn)}
.buy-card{position:sticky;top:20px;display:flex;flex-direction:column;gap:12px}
.buy-card .price{font-family:var(--font-display);font-size:32px;font-weight:700;letter-spacing:-.01em;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.buy-card .price s{font-size:16px;font-weight:400;color:var(--faint)}
.buy-card .btn{width:100%}
.fav-on{color:var(--warn)!important}
#quizArea{padding:20px;border-radius:var(--r-md);background:var(--bg-soft);border:1px solid var(--border-soft)}
.qz{margin-bottom:18px}
.qz-q{font-weight:600;font-size:14px;margin-bottom:8px}
.qz-q small{color:var(--faint);font-size:11px;font-weight:400}
.qz label{display:block;font-size:13.5px;margin:4px 0}
.qz label input{margin-right:6px;accent-color:var(--accent)}
.qz-res{margin-top:14px;padding:14px;border-radius:10px;font-weight:700;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.qz-res.ok{background:var(--ok-soft);color:var(--ok)}.qz-res.warn{background:var(--warn-soft);color:var(--warn)}
.stars button{font-size:22px;color:var(--warn);background:none;border:none;cursor:pointer;padding:0 2px}
.rv{padding:12px 0;border-bottom:1px solid var(--border-soft);font-size:14px}
.rv .hd{display:flex;justify-content:space-between;gap:10px}.rv .hd span{color:var(--warn);font-size:12px}
.rv p{color:var(--muted);margin-top:4px;line-height:1.7}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('courses'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="cp-main">
    <div class="actions"><a href="/courses" class="act">← 全部课程</a></div>
    <div class="g-main-aside">
      <div>
        <div class="card" style="display:flex;flex-direction:column;gap:22px">
          <div class="cp-title"><h1><?=htmlspecialchars($course['title'])?></h1><p><?=htmlspecialchars($course['description'] ?? '')?></p></div>

          <?php if ($hasAccess): ?>
          <div class="prog">
            <div class="row"><span>学习进度</span><span><?=$summary['done']?>/<?=$summary['total']?> 节 · <?=$summary['percent']?>%</span></div>
            <div class="bar"><i style="width:<?=$summary['percent']?>%"></i></div>
            <?php if ($resume): ?><div><button type="button" onclick="openLesson('<?=htmlspecialchars($resume['lesson_id'])?>')" class="btn primary" style="height:40px;padding:0 18px;font-size:14px">▶ 继续上次学习：<?=htmlspecialchars($lessonsFlat[$resume['lesson_id']]['title'] ?? '')?> →</button></div><?php endif; ?>
          </div>

          <div id="playerPanel" style="display:<?=empty($resume)?'none':'block'?>">
            <div class="player">
              <div class="ph" id="playerEmpty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg>
                <b id="playerLessonTitle"><?=htmlspecialchars($resume ? ($lessonsFlat[$resume['lesson_id']]['title'] ?? '') : '')?></b>
                <small id="playerStatus">已就绪 · 点击开始学习</small>
                <button type="button" onclick="togglePlay()" class="btn ghost" id="playBtn">▶ 开始播放</button>
              </div>
            </div>
            <div id="quizArea" style="display:none;margin-top:14px">
              <h3 style="font-size:17px;font-weight:800;margin-bottom:14px">课时测验</h3>
              <div id="quizBody"></div>
              <button type="button" onclick="submitQuiz()" class="btn primary" style="height:40px;padding:0 18px;font-size:14px">提交答案</button>
              <div id="quizResult" class="qz-res" style="display:none"></div>
            </div>
            <div class="cta-row" style="margin-top:12px;align-items:center;gap:12px"><button type="button" onclick="markCurrentDone()" class="btn ghost" style="height:40px;padding:0 18px;font-size:14px;color:var(--ok)">✓ 标记本节完成</button><span class="note mono" style="margin:0" id="resumeHint"><?=$resume ? '已记住上次进度 ' . gmdate('i:s', (int)$resume['position']) : ''?></span></div>
          </div>
          <?php endif; ?>

          <div class="toc">
            <?php foreach ($course['chapters'] ?? [] as $ch): ?>
            <div class="ch"><?=htmlspecialchars($ch['title'] ?? '')?></div>
            <?php foreach ($ch['lessons'] ?? [] as $lesson):
              $st = $progress[$lesson['id']] ?? null;
              $isDone = $st && !empty($st['done']);
              $isPlaying = $st && !empty($st['position']) && empty($st['done']);
            ?>
            <div class="lesson <?=$hasAccess?'':'locked'?>" data-id="<?=htmlspecialchars($lesson['id'])?>" onclick="<?=$hasAccess?"openLesson('".htmlspecialchars($lesson['id'], ENT_QUOTES)."')":''?>">
              <span class="chk <?=$isDone?'done':($isPlaying?'playing':'')?>"><?=$isDone?'✓':($isPlaying?'▶':'')?></span>
              <span class="t"><?=htmlspecialchars($lesson['title'] ?? '')?></span>
              <span class="d"><?=htmlspecialchars($lesson['duration'] ?? '')?></span>
              <?php if (!$hasAccess): ?><svg class="lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <aside>
        <div class="card buy-card">
          <?php if ($hasAccess): ?>
          <div class="gate-box" style="padding:8px 0">
            <span class="ic" style="width:44px;height:44px;color:var(--accent)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span>
            <h2 style="font-size:19px">课程已解锁</h2>
            <?php if ($summary['percent'] >= 100): ?><p style="color:var(--ok)">已全部学完，太棒了！</p><?php else: ?><p>已学 <?=$summary['done']?>/<?=$summary['total']?> 节</p><?php endif; ?>
            <a href="/account?view=courses" class="btn primary">我的课程</a>
          </div>
          <?php else: ?>
          <div class="price">¥<?=number_format($price, 2)?><?php if ($coursePromoOn): ?><s>¥<?=number_format($originalPrice, 2)?></s><span class="badge danger">限时</span><?php endif; ?></div>
          <p class="note" style="margin:0">一次购买，永久观看</p>
          <?php if ($member && member_can($member, 'courses', ['course_id' => $courseId])): ?>
          <span class="badge ok"><span class="dot"></span>VIP 会员免费观看</span>
          <?php endif; ?>
          <button type="button" onclick="buyCourse('wechat')" class="btn primary">微信支付</button>
          <button type="button" onclick="buyCourse('alipay')" class="btn ghost">支付宝</button>
          <button type="button" onclick="buyCourse('unionpay')" class="btn ghost">云闪付</button>
          <p class="note" style="text-align:center;margin:0">支付由虎皮椒聚合支付提供</p>
          <?php if ($member): ?>
          <a href="/account?view=membership" class="btn subtle">开通会员，更多课程免费看 →</a>
          <?php endif; ?>
          <?php endif; ?>
          <?php if ($member): ?>
          <button type="button" onclick="toggleFav(<?=htmlspecialchars(json_encode($courseId), ENT_QUOTES)?>, this)" class="btn ghost<?=$isFav?' fav-on':''?>" style="height:40px;font-size:14px"><?=$isFav?'★ 已收藏':'☆ 收藏课程'?></button>
          <?php endif; ?>
        </div>

        <?php
$relProducts = [];
try {
    $allP = array_values(array_filter(CommerceSystem::allPublished(), fn($p) => (float)($p['pricing']['price'] ?? 0) > 0));
    $kws = array_filter(preg_split('/[\s,，、\/]+/', ($course['title'] ?? '') . ' ' . implode(' ', $course['tags'] ?? [])), fn($w) => mb_strlen($w) >= 2);
    foreach ($allP as $pp) {
        $hay = ($pp['title'] ?? '') . ' ' . implode(' ', $pp['tags'] ?? []) . ' ' . ($pp['description'] ?? '');
        foreach ($kws as $kw) { if (mb_strpos($hay, $kw) !== false) { $relProducts[] = $pp; break; } }
        if (count($relProducts) >= 3) break;
    }
    if (empty($relProducts)) $relProducts = array_slice($allP, 0, 3);
} catch (Exception $e) {}

        if (!empty($relProducts)): ?>
        <div class="aside-box">
          <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span>配套生态工具</h3>
          <div class="link-grid" style="grid-template-columns:1fr;gap:2px">
            <?php foreach ($relProducts as $rp): $rpUrl = in_array($rp['type'], ['skill','plugin','theme']) ? '/' . $rp['type'] . '/' . urlencode($rp['id']) : '/marketplace'; ?>
            <a href="<?=$rpUrl?>" class="link-it" style="padding:10px 8px"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span><span class="lt"><b><?=htmlspecialchars($rp['title'])?></b><span>¥<?=number_format((float)($rp['pricing']['price'] ?? 0),0)?> · 即买即用</span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </aside>
    </div>
  </section>

  <section id="reviewArea" class="sec reveal" data-od-anchor data-od-id="cp-reviews">
    <div class="grid g2" style="gap:20px;align-items:start">
      <div class="card" style="padding:28px">
        <div class="sec-head" style="gap:8px;margin-bottom:16px"><span class="kicker">REVIEWS</span><h2 style="font-size:20px">课程评价</h2></div>
        <?php $rateSum = comment_rating_summary('course', $courseId); if ($rateSum['count']): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <span style="font-family:var(--font-display);font-size:32px;font-weight:700;color:var(--warn)"><?=$rateSum['avg']?></span>
          <div><div style="color:var(--warn);letter-spacing:.1em"><?=str_repeat('★', (int)round($rateSum['avg']))?></div><div class="note" style="margin:0"><?=$rateSum['count']?> 人评价</div></div>
        </div>
        <?php endif; ?>
        <?php if ($member): ?>
        <form id="rateForm" class="form-grid" style="margin-bottom:16px">
          <div class="stars" id="rateStars"><?php for ($i=1; $i<=5; $i++): ?><button type="button" data-r="<?=$i?>" onclick="setRate(<?=$i?>)">☆</button><?php endfor; ?></div>
          <input type="hidden" id="rateVal" value="5">
          <textarea id="rateContent" rows="2" placeholder="说说这门课怎么样…" class="inp" style="min-height:0;font-size:14px"></textarea>
          <div class="cta-row" style="align-items:center"><button type="button" onclick="submitRate()" class="btn primary" style="height:40px;padding:0 18px;font-size:14px">提交评价</button><span id="rateMsg" class="note" style="margin:0"></span></div>
        </form>
        <?php endif; ?>
        <div id="rateList">
          <?php $reviews = comments_for('course', $courseId); foreach (array_slice($reviews, 0, 3) as $c): ?>
          <div class="rv"><div class="hd"><b><?=htmlspecialchars($c['author'])?></b><span><?=str_repeat('★', (int)$c['rating'])?></span></div><p><?=htmlspecialchars($c['text'])?></p></div>
          <?php endforeach; if (empty($reviews)): ?><div class="empty">暂无评价，来抢沙发</div><?php endif; ?>
        </div>
      </div>

      <div class="card" style="padding:28px">
        <div class="sec-head" style="gap:8px;margin-bottom:16px"><span class="kicker">NOTES</span><h2 style="font-size:20px">课时笔记</h2></div>
        <?php if (!$member): ?><div class="empty">登录后可记录课时笔记</div>
        <?php else: $myNotes = (json_read(DATA_DIR . '/course-notes.json')[$member['id']][$courseId] ?? []); ?>
        <div class="form-grid">
          <p class="note" style="margin:0">选择任一课时，记录你的学习笔记（自动关联课时）</p>
          <select id="noteLesson" class="inp" style="font-size:14px">
            <?php foreach ($lessonsFlat as $lid => $l): ?><option value="<?=htmlspecialchars($lid)?>"><?=htmlspecialchars($l['title'])?></option><?php endforeach; ?>
          </select>
          <textarea id="noteContent" rows="3" placeholder="记录这节的重点 / 疑问 / 待复习…" class="inp" style="min-height:0;font-size:14px"><?=htmlspecialchars($myNotes[array_key_first($myNotes)]['note'] ?? '')?></textarea>
          <div class="cta-row" style="align-items:center"><button type="button" onclick="saveNote()" class="btn primary" style="height:40px;padding:0 18px;font-size:14px">保存笔记</button><span id="noteMsg" class="note" style="margin:0"></span></div>
          <?php if (!empty($myNotes)): ?><p class="note" style="margin:0">已存 <?=count($myNotes)?> 节笔记</p><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
var COURSE_ID = <?=json_encode($courseId)?>;
/* 课程行为埋点 → CDP + 行为触发 */
if (window.fcTrack) {
  try { fcTrack('course_start', { course_id: COURSE_ID, title: document.title }); } catch (e) {}
  if (<?=$hasAccess?'true':'false'?>) { try { fcTrack('course_enroll', { course_id: COURSE_ID }); } catch (e) {} }
}
/* 收藏 */
function toggleFav(cid, btn) {
  var fd = new FormData(); fd.append('action','toggle_fav'); fd.append('course_id', cid);
  fetch('/api/course', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) { btn.textContent = d.fav ? '★ 已收藏' : '☆ 收藏课程'; btn.classList.toggle('fav-on', !!d.fav); } });
}
/* 评价 */
var curRate = 5;
function setRate(r) {
  curRate = r;
  var stars = document.querySelectorAll('#rateStars button');
  stars.forEach(function(s, i){ s.textContent = i < r ? '★' : '☆'; });
  document.getElementById('rateVal').value = r;
}
function submitRate() {
  var content = document.getElementById('rateContent').value.trim();
  if (!content) { document.getElementById('rateMsg').textContent = '请填写评价内容'; return; }
  var fd = new FormData(); fd.append('action','rate_course'); fd.append('course_id', COURSE_ID); fd.append('rating', curRate); fd.append('content', content);
  fetch('/api/course', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ document.getElementById('rateMsg').textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 900); });
}
/* 笔记 */
function saveNote() {
  var lesson = document.getElementById('noteLesson').value;
  var note = document.getElementById('noteContent').value.trim();
  var fd = new FormData(); fd.append('action','save_note'); fd.append('course_id', COURSE_ID); fd.append('lesson_id', lesson); fd.append('note', note);
  fetch('/api/course', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ document.getElementById('noteMsg').textContent = d.message || d.error; if (d.ok) document.getElementById('noteMsg').style.color='var(--ok)'; });
}
var HAS_ACCESS = <?=$hasAccess?'true':'false'?>;
var MEMBER_ID = <?=json_encode($member ? $member['id'] : null)?>;
var LESSONS = <?=json_encode($lessonsFlat)?>;
var currentLesson = null;
var playStart = Date.now();

function openLesson(id) {
  if (!HAS_ACCESS) return;
  currentLesson = id;
  document.getElementById('playerPanel').style.display = 'block';
  document.getElementById('playerEmpty').style.display = 'flex';
  document.getElementById('playerLessonTitle').textContent = LESSONS[id].title || '';
  document.getElementById('playerStatus').textContent = '学习「' + (LESSONS[id].title||'') + '」…';
  document.getElementById('playBtn').textContent = '▶ 开始播放';
  // quiz 课时：渲染测验
  var isQuiz = LESSONS[id] && LESSONS[id].type === 'quiz' && LESSONS[id].questions && LESSONS[id].questions.length;
  var quizArea = document.getElementById('quizArea');
  var playerVid = quizArea ? quizArea.previousElementSibling : null;
  if (isQuiz) {
    if (quizArea) { quizArea.style.display = 'block'; renderQuiz(LESSONS[id].questions); }
    if (playerVid) playerVid.style.display = 'none';
  } else {
    if (quizArea) quizArea.style.display = 'none';
    if (playerVid) playerVid.style.display = 'grid';
  }
  // 高亮
  document.querySelectorAll('.lesson').forEach(function(el){
    el.classList.toggle('active', el.dataset.id === id);
  });
  // 记录进入（续播）
  saveProgress(id, { position: 5, done: false });
  playStart = Date.now();
}
function togglePlay() {
  if (!currentLesson) return;
  var btn = document.getElementById('playBtn');
  var playing = btn.textContent.indexOf('暂停') >= 0;
  btn.textContent = playing ? '▶ 继续播放' : '⏸ 暂停';
  document.getElementById('playerStatus').textContent = playing ? '已暂停' : '正在学习：' + (LESSONS[currentLesson].title||'');
}
function markCurrentDone() {
  if (!currentLesson) { alert('请先选择一节课'); return; }
  saveProgress(currentLesson, { done: true });
  /* 课时完成 → 行为触发 */
  if (window.fcTrack) { try { fcTrack('lesson_complete', { course_id: COURSE_ID, lesson_id: currentLesson }); } catch (e) {} }
  var el = document.querySelector('.lesson[data-id="'+currentLesson+'"] .chk');
  if (el) { el.className = 'chk done'; el.textContent = '✓'; }
  var btn = document.querySelector('.lesson[data-id="'+currentLesson+'"]');
  if (btn) btn.classList.remove('active');
  document.getElementById('playerStatus').textContent = '✅ 本节已完成';
  alert('本节已标记完成');
}
/* 测验 */
function renderQuiz(qs) {
  var body = document.getElementById('quizBody');
  var html = '';
  qs.forEach(function(q, qi) {
    html += '<div class="qz">';
    html += '<div class="qz-q">' + (qi+1) + '. ' + (q.q||'') + ' <small>' + ({single:'单选',multi:'多选',judge:'判断'}[q.type]||'') + '</small></div>';
    if (q.type === 'judge') {
      html += '<label><input type="radio" name="quiz'+qi+'" value="对"> 对</label>';
      html += '<label><input type="radio" name="quiz'+qi+'" value="错"> 错</label>';
    } else if (q.type === 'multi') {
      (q.options||[]).forEach(function(op, oi) {
        html += '<label><input type="checkbox" name="quiz'+qi+'" value="' + String.fromCharCode(65+oi) + '"> ' + op + '</label>';
      });
    } else {
      (q.options||[]).forEach(function(op, oi) {
        html += '<label><input type="radio" name="quiz'+qi+'" value="' + String.fromCharCode(65+oi) + '"> ' + op + '</label>';
      });
    }
    html += '</div>';
  });
  body.innerHTML = html;
  document.getElementById('quizResult').style.display = 'none';
}
function submitQuiz() {
  var qs = LESSONS[currentLesson].questions || [];
  var correct = 0;
  qs.forEach(function(q, qi) {
    var answer = (q.answer||'').toUpperCase().replace(/\s/g,'');
    if (q.type === 'judge') {
      var sel = document.querySelector('input[name="quiz'+qi+'"]:checked');
      if (sel && sel.value === q.answer) correct++;
    } else if (q.type === 'multi') {
      var sel = document.querySelectorAll('input[name="quiz'+qi+'"]:checked');
      var val = Array.prototype.map.call(sel, function(s){ return s.value; }).sort().join(',');
      var exp = answer.split(',').sort().join(',');
      if (val === exp) correct++;
    } else {
      var sel = document.querySelector('input[name="quiz'+qi+'"]:checked');
      if (sel && sel.value === answer) correct++;
    }
  });
  var total = qs.length;
  var score = Math.round(correct/total*100);
  var passed = score >= 80;
  var res = document.getElementById('quizResult');
  res.style.display = 'block';
  res.className = 'qz-res ' + (passed ? 'ok' : 'warn');
  res.innerHTML = passed
    ? '🎉 恭喜通过！得分 ' + score + '%（' + correct + '/' + total + '） <button type="button" onclick="reTakeQuiz()" class="btn subtle" style="height:30px;font-size:12.5px">重考</button>'
    : '😅 得分 ' + score + '%（' + correct + '/' + total + '），未达 80% 通过线 <button type="button" onclick="reTakeQuiz()" class="btn subtle" style="height:30px;font-size:12.5px">重新作答</button>';
  if (passed) { saveProgress(currentLesson, { done: true, quiz_score: score }); markQuizDone(); }
  else { saveProgress(currentLesson, { quiz_score: score }); }
  res.scrollIntoView({ block:'center' });
}
function markQuizDone() {
  var el = document.querySelector('.lesson[data-id="'+currentLesson+'"] .chk');
  if (el) { el.className = 'chk done'; el.textContent = '✓'; }
  var btn = document.querySelector('.lesson[data-id="'+currentLesson+'"]');
  if (btn) btn.classList.remove('active');
}
function reTakeQuiz() {
  var qs = LESSONS[currentLesson].questions || [];
  renderQuiz(qs);
}
function saveProgress(lessonId, extra) {
  if (!MEMBER_ID) return;
  var fd = new FormData();
  fd.append('action','progress');
  fd.append('course_id', COURSE_ID);
  fd.append('lesson_id', lessonId);
  Object.keys(extra||{}).forEach(function(k){ fd.append(k, extra[k]); });
  fetch('/api/course-progress', { method:'POST', body: fd });
}
// 初始化：若已有续播，自动打开
var resume = <?=json_encode($resume ? $resume['lesson_id'] : null)?>;
if (resume) { openLesson(resume); }
function buyCourse(payType) {
  var member = <?=json_encode($member ? ['id'=>$member['id']] : null)?>;
  if (!member) { location.href = '/account?view=login&next=/courses/' + <?=json_encode($courseId)?>; return; }
  var fd = new FormData();
  fd.append('action','create_order');
  fd.append('course_id', <?=json_encode($courseId)?>);
  var ref = new URLSearchParams(location.search).get('ref') || '';
  if (ref) fd.append('ref', ref);
  fetch('/api/shop?pay_type=' + payType + '&action=create_order', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { alert(d.error); return; }
      var form = document.createElement('form');
      form.method = 'POST'; form.action = d.payment.gateway;
      Object.keys(d.payment.params).forEach(function(k){
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = k; input.value = d.payment.params[k];
        form.appendChild(input);
      });
      document.body.appendChild(form); form.submit();
    });
}
</script>
</body>
</html>
