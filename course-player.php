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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($course['title'])?> | OpenFlow 课程</title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830b" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .lesson{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;cursor:pointer;transition:.12s;font-size:14px}
  .lesson:hover{background:var(--bg)}
  .lesson.locked{opacity:.55;cursor:not-allowed}
  .lesson.active{background:var(--accent);color:var(--on-accent)}
  .lesson .chk{width:20px;height:20px;border-radius:50%;border:2px solid #d1d5db;display:grid;place-items:center;font-size:11px;flex-shrink:0;color:var(--surface)}
  .lesson .chk.done{background:var(--ok);border-color:var(--ok)}
  .lesson .chk.playing{background:var(--warn);border-color:var(--warn)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260901a" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-8" style="max-width:1100px">
    <div class="grid gap-6" style="grid-template-columns:1fr 340px">
      <!-- 播放/目录 -->
      <div>
        <div class="card p-6">
          <h1 class="text-2xl font-bold mb-1"><?=htmlspecialchars($course['title'])?></h1>
          <p class="text-sm text-gray-600 mb-4"><?=htmlspecialchars($course['description'] ?? '')?></p>

          <?php if ($hasAccess): ?>
          <!-- 学习进度条 -->
          <div class="mb-6">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px">
              <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> 学习进度</span>
              <span><?=$summary['done']?>/<?=$summary['total']?> 节 · <?=$summary['percent']?>%</span>
            </div>
            <div style="height:8px;background:var(--border);border-radius:99px;overflow:hidden">
              <div style="height:100%;width:<?=$summary['percent']?>%;background:linear-gradient(90deg,var(--ok),var(--accent));border-radius:99px;transition:.3s"></div>
            </div>
            <?php if ($resume): ?>
            <button onclick="openLesson('<?=htmlspecialchars($resume['lesson_id'])?>')" class="mt-3 text-sm font-bold px-5 py-2 rounded-full" style="background:var(--accent);color:var(--on-accent)">▶ 继续上次学习：<?=htmlspecialchars($lessonsFlat[$resume['lesson_id']]['title'] ?? '')?> →</button>
            <?php endif; ?>
          </div>

          <!-- 当前播放节 -->
          <div id="playerPanel" class="mb-6" <?=empty($resume)?'style="display:none"':''?>>
            <div style="background:#000;border-radius:14px;overflow:hidden;aspect-ratio:16/9;display:grid;place-items:center">
              <div style="color:var(--surface);text-align:center" id="playerEmpty">
                <div style="font-size:40px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span></div>
                <div id="playerLessonTitle" class="mt-3 font-bold"><?=htmlspecialchars($resume ? ($lessonsFlat[$resume['lesson_id']]['title'] ?? '') : '')?></div>
                <div class="text-white/50 text-sm mt-1" id="playerStatus">已就绪 · 点击开始学习</div>
                <button onclick="togglePlay()" class="mt-5 px-8 py-3 rounded-full font-bold" style="background:var(--accent-soft);color:var(--accent)" id="playBtn">▶ 开始播放</button>
              </div>
            </div>
            <div id="quizArea" style="display:none;padding:20px;border-radius:14px;background:var(--bg);margin-top:14px">
              <h3 class="font-bold text-lg mb-4" style="color:var(--fg)">📝 课时测验</h3>
              <div id="quizBody"></div>
              <button onclick="submitQuiz()" class="mt-4 rounded-full px-8 py-3 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">提交答案</button>
              <div id="quizResult" class="mt-4" style="display:none;padding:14px;border-radius:10px;font-weight:700"></div>
            </div>
            <div class="flex items-center gap-3 mt-3">              <button onclick="markCurrentDone()" class="text-sm font-bold px-5 py-2 rounded-full" style="background:var(--ok);color:var(--surface)">✅ 标记本节完成</button>
              <span class="text-xs text-gray-400" id="resumeHint"><?=$resume ? '已记住上次进度 ' . gmdate('i:s', (int)$resume['position']) : ''?></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- 目录 -->
          <?php foreach ($course['chapters'] ?? [] as $ch): ?>
          <div class="font-semibold text-sm mb-2 mt-4" style="color:var(--fg)"><?=htmlspecialchars($ch['title'] ?? '')?></div>
          <?php foreach ($ch['lessons'] ?? [] as $lesson):
            $st = $progress[$lesson['id']] ?? null;
            $isDone = $st && !empty($st['done']);
            $isPlaying = $st && !empty($st['position']) && empty($st['done']);
          ?>
          <div class="lesson <?=$hasAccess?'':'locked'?>" data-id="<?=htmlspecialchars($lesson['id'])?>" onclick="<?=$hasAccess?"openLesson('".htmlspecialchars($lesson['id'], ENT_QUOTES)."')":''?>">
            <span class="chk <?=$isDone?'done':($isPlaying?'playing':'')?>"><?=$isDone?'✓':($isPlaying?'▶':'')?></span>
            <span class="flex-1"><?=htmlspecialchars($lesson['title'] ?? '')?></span>
            <span class="text-xs text-gray-400"><?=htmlspecialchars($lesson['duration'] ?? '')?></span>
            <?php if (!$hasAccess): ?><span class="text-xs" style="color:#d97706"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span></span><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 购买/信息卡 -->
      <div class="card p-6 h-fit" style="position:sticky;top:20px">
        <?php if ($hasAccess): ?>
        <div class="text-center py-4">
          <div style="font-size:44px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span></div>
          <p class="font-bold text-lg mt-3">课程已解锁</p>
          <?php if ($summary['percent'] >= 100): ?><p class="text-sm mt-1" style="color:var(--ok)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M10 14h4v3h-4zM12 17v3M8 21h8"/></svg></span> 已全部学完，太棒了！</p><?php else: ?><p class="text-sm mt-1 text-gray-600">已学 <?=$summary['done']?>/<?=$summary['total']?> 节</p><?php endif; ?>
          <a href="/account?view=courses" class="mt-5 inline-block rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">我的课程</a>
        </div>
        <?php else: ?>
        <div class="text-3xl font-bold mb-1">¥<?=number_format($price, 2)?><?php if ($coursePromoOn): ?> <s class="text-base font-normal" style="color:var(--faint)">¥<?=number_format($originalPrice, 2)?></s> <span class="text-xs font-bold px-2 py-0.5 rounded-full" style="background:var(--danger-soft,#fde8e8);color:var(--danger)">限时</span><?php endif; ?></div>
        <p class="text-sm text-gray-600 mb-4">一次购买，永久观看</p>
        <?php if ($member && member_can($member, 'courses', ['course_id' => $courseId])): ?>
        <div class="text-sm font-semibold mb-4" style="color:var(--ok)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9l4-6Z"/><path d="M2 9h20M9 3 7 9l5 12M15 3l2 6-5 12"/></svg></span> VIP 会员免费观看</div>
        <?php endif; ?>
        <div class="grid gap-3 mb-4">
          <button onclick="buyCourse('wechat')" class="rounded-full py-3 font-bold" style="background:var(--ok);color:var(--surface)">微信支付</button>
          <button onclick="buyCourse('alipay')" class="rounded-full py-3 font-bold" style="background:#0284c7;color:var(--surface)">支付宝</button>
          <button onclick="buyCourse('unionpay')" class="rounded-full py-3 font-bold" style="background:#7c3aed;color:var(--surface)">云闪付</button>
        </div>
        <p class="text-xs text-gray-400 text-center mb-4">支付由虎皮椒聚合支付提供</p>
        <?php if ($member): ?>
        <a href="/account?view=membership" class="block text-center text-sm font-semibold" style="color:#2b5f7e">开通会员，更多课程免费看 →</a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($member): ?>
        <button onclick="toggleFav(<?=json_encode($courseId)?>, this)" class="mt-3 w-full rounded-full py-2 text-sm font-semibold" style="border:1.5px solid var(--border);color:<?=$isFav?'var(--warn)':'var(--muted)'?>"><?=$isFav?'★ 已收藏':'☆ 收藏课程'?></button>
        <?php endif; ?>
      </div>

      <!-- 配套生态工具（课程 ↔ 生态交叉销售） -->
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
      <div class="mt-4">
        <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:var(--muted)">🎁 配套生态工具</div>
        <?php foreach ($relProducts as $rp): $rpUrl = in_array($rp['type'], ['skill','plugin','theme']) ? '/' . $rp['type'] . '/' . urlencode($rp['id']) : '/marketplace'; ?>
        <a href="<?=$rpUrl?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid var(--border);border-radius:12px;margin-bottom:8px;color:var(--fg)">
          <span style="font-size:18px"><?=['skill'=>'⚡','plugin'=>'🔌','theme'=>'🎨','membership'=>'👑','bundle'=>'📦'][$rp['type']] ?? '📦'?></span>
          <div style="flex:1;min-width:0"><div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($rp['title'])?></div><div style="font-size:11px;color:var(--faint)">¥<?=number_format((float)($rp['pricing']['price'] ?? 0),0)?> · 即买即用</div></div>
          <span style="font-size:12px;color:var(--accent)">去看看 →</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 评价 + 课时笔记 -->
  <div class="mx-auto px-5 py-10" style="max-width:1000px">
    <div class="grid gap-6" style="grid-template-columns:1fr 1fr" id="reviewArea">
      <!-- 课程评价 -->
      <div class="card p-6">
        <h3 class="font-bold text-lg mb-3">课程评价</h3>
        <?php $rateSum = comment_rating_summary('course', $courseId); if ($rateSum['count']): ?>
        <div class="mb-4 flex items-center gap-3">
          <div class="text-3xl font-extrabold" style="color:var(--warn)"><?=$rateSum['avg']?></div>
          <div>
            <div style="color:var(--warn)"><?=str_repeat('★', (int)round($rateSum['avg']))?></div>
            <div class="text-xs text-gray-400"><?=$rateSum['count']?> 人评价</div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($member): ?>
        <form id="rateForm" class="mb-4">
          <div class="flex items-center gap-2 mb-2" id="rateStars">
            <?php for ($i=1; $i<=5; $i++): ?><button type="button" data-r="<?=$i?>" onclick="setRate(<?=$i?>)" style="font-size:22px;color:var(--warn);background:none;border:none;cursor:pointer">☆</button><?php endfor; ?>
          </div>
          <input type="hidden" id="rateVal" value="5">
          <textarea id="rateContent" rows="2" placeholder="说说这门课怎么样…" class="w-full p-3 rounded-xl" style="border:1.5px solid var(--border);font-size:13px"></textarea>
          <button type="button" onclick="submitRate()" class="mt-2 rounded-full px-5 py-2 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">提交评价</button>
          <span id="rateMsg" class="text-xs ml-2"></span>
        </form>
        <?php endif; ?>
        <div id="rateList">
          <?php $reviews = comments_for('course', $courseId); foreach (array_slice($reviews, 0, 3) as $c): ?>
          <div class="py-3 border-b" style="border-color:var(--border)">
            <div class="flex justify-between"><b class="text-sm"><?=htmlspecialchars($c['author'])?></b><span class="text-xs" style="color:var(--warn)"><?=str_repeat('★', (int)$c['rating'])?></span></div>
            <div class="text-sm text-gray-600 mt-1"><?=htmlspecialchars($c['text'])?></div>
          </div>
          <?php endforeach; if (empty($reviews)): ?><p class="text-sm text-gray-400">暂无评价，来抢沙发</p><?php endif; ?>
        </div>
      </div>

      <!-- 课时笔记 -->
      <div class="card p-6">
        <h3 class="font-bold text-lg mb-3">课时笔记</h3>
        <?php if (!$member): ?><p class="text-sm text-gray-400">登录后可记录课时笔记</p>
        <?php else: $myNotes = (json_read(DATA_DIR . '/course-notes.json')[$member['id']][$courseId] ?? []); ?>
        <div class="mb-3 text-xs text-gray-400">选择任一课时，记录你的学习笔记（自动关联课时）</div>
        <select id="noteLesson" class="w-full p-3 rounded-xl mb-2" style="border:1.5px solid var(--border);font-size:13px">
          <?php foreach ($lessonsFlat as $lid => $l): ?><option value="<?=htmlspecialchars($lid)?>"><?=htmlspecialchars($l['title'])?></option><?php endforeach; ?>
        </select>
        <textarea id="noteContent" rows="3" placeholder="记录这节的重点 / 疑问 / 待复习…" class="w-full p-3 rounded-xl mb-2" style="border:1.5px solid var(--border);font-size:13px"><?=htmlspecialchars($myNotes[array_key_first($myNotes)]['note'] ?? '')?></textarea>
        <button onclick="saveNote()" class="rounded-full px-5 py-2 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">保存笔记</button>
        <span id="noteMsg" class="text-xs ml-2"></span>
        <?php if (!empty($myNotes)): ?>
        <div class="mt-4 text-xs text-gray-400">已存 <?=count($myNotes)?> 节笔记</div>
        <?php endif; endif; ?>
      </div>
    </div>
  </div>

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
    .then(function(d){ if (d.ok) { btn.textContent = d.fav ? '★ 已收藏' : '☆ 收藏课程'; btn.style.color = d.fav ? 'var(--warn)' : 'var(--muted)'; } });
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
  document.getElementById('playerEmpty').style.display = 'grid';
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
  alert('本节已标记完成 <span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 20 8-8M9 4l.5 3.5L13 8l-3.5.5L9 12l-.5-3.5L5 8l3.5-.5L9 4ZM16 6l.4 2.6L19 9l-2.6.4L16 12l-.4-2.6L13 9l2.6-.4L16 6ZM20 13l.3 2 2 .3-2 .3-.3 2-.3-2-2-.3 2-.3.3-2Z"/></svg></span>');
}
/* 测验 */
function renderQuiz(qs) {
  var body = document.getElementById('quizBody');
  var html = '';
  qs.forEach(function(q, qi) {
    html += '<div class="mb-5" style="color:var(--fg)">';
    html += '<div class="font-semibold mb-2 text-sm">' + (qi+1) + '. ' + (q.q||'') + ' <span style="color:var(--faint);font-size:11px;font-weight:400">' + ({single:'单选',multi:'多选',judge:'判断'}[q.type]||'') + '</span></div>';
    if (q.type === 'judge') {
      html += '<label style="margin-right:16px;font-size:13px"><input type="radio" name="quiz'+qi+'" value="对"> 对</label>';
      html += '<label style="font-size:13px"><input type="radio" name="quiz'+qi+'" value="错"> 错</label>';
    } else if (q.type === 'multi') {
      (q.options||[]).forEach(function(op, oi) {
        html += '<label class="block mb-1" style="font-size:13px"><input type="checkbox" name="quiz'+qi+'" value="' + String.fromCharCode(65+oi) + '"> ' + op + '</label>';
      });
    } else {
      (q.options||[]).forEach(function(op, oi) {
        html += '<label class="block mb-1" style="font-size:13px"><input type="radio" name="quiz'+qi+'" value="' + String.fromCharCode(65+oi) + '"> ' + op + '</label>';
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
  res.style.background = passed ? 'var(--ok-soft,#e7f6ec)' : 'var(--warn-soft,#fdf0e3)';
  res.style.color = passed ? 'var(--ok)' : 'var(--warn)';
  res.innerHTML = passed
    ? '🎉 恭喜通过！得分 ' + score + '%（' + correct + '/' + total + '） <button onclick="reTakeQuiz()" class="ml-3 px-4 py-1 rounded-full text-xs" style="background:var(--accent-soft);color:var(--accent)">重考</button>'
    : '😅 得分 ' + score + '%（' + correct + '/' + total + '），未达 80% 通过线 <button onclick="reTakeQuiz()" class="ml-3 px-4 py-1 rounded-full text-xs" style="background:var(--accent-soft);color:var(--accent)">重新作答</button>';
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
<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)"><div class="mx-auto px-5 text-center text-sm" style="max-width:1100px"><div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div><div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div></div></footer>
</body>
</html>
