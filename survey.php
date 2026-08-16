<?php
/**
 * 调研填写页 — 公开访问
 * /survey.php?id=survey_xxx
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/admin/survey-lib.php';

$id = req_str('id');
$survey = survey_get_survey($id);

if (!$survey || ($survey['status'] ?? 'draft') !== 'active') {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>问卷不存在 | <?=site_config_get("site_name")?></title>
    <link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
    <body style="background:var(--bg);color:var(--fg)"><div class="mx-auto px-5 py-[140px] text-center" style="max-width:512px">
    <p style="font-size:60px;font-weight:700;color:var(--accent)">404</p>
    <h1 style="margin-top:16px;font-size:24px;font-weight:700">问卷不存在或已结束</h1>
    <p style="margin-top:12px;color:var(--muted)">请联系发送问卷的管理员。</p>
    </div></body></html><?php
    exit;
}

$submitted = isset($_GET['done']);

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Survey',
    'name' => $survey['title'],
    'description' => $survey['description'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($survey['title'])?>  | <?=site_config_get("site_name")?> 调研</title>
<meta name="robots" content="noindex">
<script type="application/ld+json"><?=json_encode($jsonLd, JSON_UNESCAPED_UNICODE)?></script>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<style>
  body{background:var(--bg)}
  .q-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:16px;box-shadow:0 4px 16px rgba(30,30,30,.05)}
  .q-title{font-size:16px;font-weight:700;color:var(--fg);margin-bottom:12px}
  .q-title .req{color:var(--danger)}
  .opt-label{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;margin-bottom:8px;cursor:pointer;transition:.15s;font-size:14px}
  .opt-label:hover{border-color:var(--accent);background:var(--bg-soft)}
  .opt-label input{margin:0}
  .rating-row{display:flex;gap:8px;flex-wrap:wrap}
  .rating-btn{width:44px;height:44px;border-radius:10px;border:1.5px solid var(--border);display:grid;place-items:center;font-weight:700;cursor:pointer;transition:.15s;background:var(--surface)}
  .rating-btn:hover{border-color:var(--accent)}
  .rating-btn.on{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
  input[type=text],input[type=email]{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;outline:none}
  input:focus{border-color:var(--accent)}

  /* ── 卡片式 / 游戏化 / 沉浸式 模板 ── */
  .survey-step{display:none;animation:stepIn .35s}
  .survey-step.on{display:block}
  @keyframes stepIn{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:none}}
  .survey-progress{position:sticky;top:0;z-index:10;background:var(--bg-soft);backdrop-filter:blur(6px);padding:12px 0;margin-bottom:16px}
  .survey-progress .bar{height:8px;background:var(--border);border-radius:99px;overflow:hidden}
  .survey-progress .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--ok),var(--accent));transition:width .3s}
  .survey-progress .pct{font-size:12px;color:var(--muted);margin-top:6px}
  .survey-nav{display:flex;gap:10px;margin-top:18px}
  .survey-nav button{flex:1;padding:14px;border-radius:999px;font-weight:700;font-size:15px;border:none;cursor:pointer}
  .survey-nav .next{background:var(--accent);color:var(--on-accent)}
  .survey-nav .prev{background:var(--border);color:var(--fg)}
  /* 游戏化：选项点击动效 */
  body[data-template="gamified"] .opt-label:active{transform:scale(.97)}
  body[data-template="gamified"] .opt-label:has(input:checked){background:var(--accent);border-color:var(--accent);font-weight:600}
  body[data-template="gamified"] .q-title{font-size:20px;text-align:center;padding:20px 0 8px}
  /* 沉浸式：全屏渐变 */
  body[data-template="immersive"]{background:linear-gradient(160deg,var(--accent-strong),var(--accent))}
  body[data-template="immersive"] .survey-head{display:none}
  body[data-template="immersive"] .q-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:var(--surface);box-shadow:none;padding:32px}
  body[data-template="immersive"] .q-title{color:var(--surface);font-size:22px}
  body[data-template="immersive"] .opt-label{border-color:rgba(255,255,255,.2);color:var(--border)}
  body[data-template="immersive"] .opt-label:hover{border-color:var(--accent);background:rgba(221,255,14,.1)}
  body[data-template="immersive"] .survey-progress{background:rgba(30,30,30,.5)}
  body[data-template="immersive"] .pct{color:var(--muted)}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body class="min-h-screen" data-template="<?=htmlspecialchars($survey['template'] ?? 'classic')?>">
  <div class="mx-auto max-w-2xl px-5 py-10">
    <!-- 头部 -->
    <div class="survey-head rounded-2xl bg-white border border-[var(--border)] p-7 mb-6 text-center" style="background:linear-gradient(160deg,var(--accent-strong),var(--accent));border:none">
      <h1 class="text-2xl font-bold text-white"><?=htmlspecialchars($survey['title'])?></h1>
      <?php if (!empty($survey['description'])): ?><p class="mt-3 text-[var(--muted)] leading-relaxed"><?=htmlspecialchars($survey['description'])?></p><?php endif; ?>
      <p class="mt-4 text-[13px] text-[var(--faint)]"><?=($survey['type']==='named'?'实名问卷':'匿名问卷')?> · <?=count($survey['questions'])?> 题 · 约需 3 分钟</p>
    </div>

    <?php if ($submitted): ?>
    <div class="q-card text-center py-12">
      <div style="font-size:56px">✅</div>
      <h2 class="mt-4 text-xl font-bold">提交成功</h2>
      <p class="mt-3 text-gray-600">感谢你的参与！你的反馈对我们非常重要。</p>
      <a href="/community" class="mt-6 inline-block rounded-full bg-[var(--accent)] px-7 py-3 font-semibold text-white">返回社区</a>
    </div>
    <?php else: ?>
    <form method="post" action="/api/survey-submit.php?id=<?=urlencode($survey['id'])?>" onsubmit="return surveySubmit(this)">
      <!-- 身份信息（仅实名问卷） -->
      <?php if ($survey['type'] === 'named'): ?>
      <div class="q-card">
        <div class="q-title">基本信息</div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-[13px] font-semibold mb-1.5">姓名</label><input type="text" name="name" required placeholder="你的姓名"></div>
          <div><label class="block text-[13px] font-semibold mb-1.5">邮箱</label><input type="email" name="email" required placeholder="用于查询结果"></div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div><label class="block text-[13px] font-semibold mb-1.5">公司</label><input type="text" name="company" placeholder="公司名称"></div>
          <div><label class="block text-[13px] font-semibold mb-1.5">部门</label><input type="text" name="department" placeholder="部门名称"></div>
        </div>
      </div>
      <?php else: ?>
      <div class="q-card">
        <div class="q-title">基本信息 <span style="font-weight:400;font-size:12px;color:var(--muted)">（用于统计范围，可匿名填写）</span></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-[13px] font-semibold mb-1.5">公司</label><input type="text" name="company" placeholder="公司名称（可选）"></div>
          <div><label class="block text-[13px] font-semibold mb-1.5">部门</label><input type="text" name="department" placeholder="部门名称（可选）"></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 题目 -->
      <?php $tpl = $survey['template'] ?? 'classic'; $isStep = in_array($tpl, ['cards','gamified','immersive']); ?>
      <?php if ($isStep): ?>
      <div class="survey-progress">
        <div class="bar"><i id="sProgBar" style="width:0%"></i></div>
        <div class="pct" id="sProgPct">0 / <?=count($survey['questions'])?></div>
      </div>
      <?php endif; ?>
      <?php foreach ($survey['questions'] as $qi => $q): ?>
      <?php $stepNo = $qi + 1; ?>
      <div class="q-card<?=$isStep?' survey-step':''?>" data-qid="<?=$q['id']?>" data-step="<?=$stepNo?>" <?=$isStep && $stepNo>1?'style="display:none"':''?>>
        <?php if ($isStep): ?><div style="font-size:12px;color:var(--faint);margin-bottom:8px">第 <?=$stepNo?> 题 / 共 <?=count($survey['questions'])?> 题</div><?php endif; ?>
        <div class="q-title"><?=htmlspecialchars($q['title'])?><?php if ($q['required']): ?> <span class="req">*</span><?php endif; ?></div>
        <?php if ($q['type'] === 'single' || $q['type'] === 'dropdown'): ?>
          <?php foreach ($q['options'] as $oi => $opt): ?>
          <label class="opt-label"><input type="radio" name="ans[<?=$q['id']?>]" value="<?=htmlspecialchars($opt)?>" <?=$q['required']?'required':''?> onclick="<?=$isStep?'stepNext()':''?>"> <?=htmlspecialchars($opt)?></label>
          <?php endforeach; ?>
        <?php elseif ($q['type'] === 'multi'): ?>
          <?php foreach ($q['options'] as $oi => $opt): ?>
          <label class="opt-label"><input type="checkbox" name="ans[<?=$q['id']?>][]" value="<?=htmlspecialchars($opt)?>"> <?=htmlspecialchars($opt)?></label>
          <?php endforeach; ?>
        <?php elseif ($q['type'] === 'rating'): $scale = $q['scale'] ?? 5; ?>
          <div class="rating-row">
            <?php for ($r = 1; $r <= $scale; $r++): ?>
            <button type="button" class="rating-btn" onclick="pickRating(this, '<?=$q['id']?>')" data-qid="<?=$q['id']?>" data-val="<?=$r?>"><?=$r?></button>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="ans[<?=$q['id']?>]" data-rid="<?=$q['id']?>">
          <?php if ($isStep): ?><button type="button" class="btn-next-step" style="display:block;margin-top:14px;padding:12px 32px;border-radius:999px;border:none;background:var(--accent);color:var(--on-accent);font-weight:700;cursor:pointer" onclick="stepNext()">下一题 →</button><?php endif; ?>
        <?php else: ?>
          <textarea name="ans[<?=$q['id']?>]" rows="3" placeholder="请输入..." style="width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px" <?=$q['required']?'required':''?>><?php if ($isStep): ?><?php endif; ?></textarea>
          <?php if ($isStep): ?><button type="button" class="btn-next-step" style="display:block;margin-top:14px;padding:12px 32px;border-radius:999px;border:none;background:var(--accent);color:var(--on-accent);font-weight:700;cursor:pointer" onclick="stepNext()">下一题 →</button><?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php if ($isStep): ?>
      <div class="survey-step survey-submit" data-step="<?=count($survey['questions'])+1?>" style="display:none">
        <div class="q-card" style="text-align:center;padding:40px">
          <div style="font-size:40px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 20 8-8M9 4l.5 3.5L13 8l-3.5.5L9 12l-.5-3.5L5 8l3.5-.5L9 4ZM16 6l.4 2.6L19 9l-2.6.4L16 12l-.4-2.6L13 9l2.6-.4L16 6ZM20 13l.3 2 2 .3-2 .3-.3 2-.3-2-2-.3 2-.3.3-2Z"/></svg></span></div>
          <h3 style="margin:12px 0 8px">全部答完！</h3>
          <p style="color:var(--muted);font-size:14px">确认无误后提交。</p>
        </div>
      </div>
      <button type="submit" class="btn-submit-final w-full rounded-full py-4 font-bold text-lg" style="display:none;background:var(--accent);color:var(--on-accent);border:none;cursor:pointer">提交问卷 <span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2-.7-3 0Z"/><path d="M12 15l-3-3c2-5.5 5-9 9-9s3 6-1 11l-5 1Z"/><path d="M9 12c-2.5 1-4 3-4.5 5M15 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg></span></button>
      <?php else: ?>
      <button type="submit" class="w-full rounded-full py-4 font-bold text-lg" style="background:var(--accent);color:var(--on-accent);border:none;cursor:pointer">提交问卷</button>
      <?php endif; ?>
      <p class="text-center text-[12px] text-gray-600 mt-4">你的回答仅用于组织健康分析，信息严格保密</p>
    </form>
    <?php endif; ?>
  </div>

<script>
function pickRating(btn, qid) {
  var box = btn.closest('.q-card');
  box.querySelectorAll('.rating-btn').forEach(function(b) { b.classList.remove('on'); });
  var scale = box.querySelectorAll('.rating-btn').length;
  // 选中当前及之前所有按钮
  var val = parseInt(btn.dataset.val);
  box.querySelectorAll('.rating-btn').forEach(function(b) {
    if (parseInt(b.dataset.val) <= val) b.classList.add('on');
  });
  box.querySelector('input[data-rid="' + qid + '"]').value = val;
}
// ── 分步模板：下一题 / 上一题 / 进度条 ──
var STEP_TOTAL = <?=count($survey['questions'])?>;
var STEP_CUR = 1;
function updateStepUI() {
  var steps = document.querySelectorAll('.survey-step');
  steps.forEach(function(s) { s.classList.remove('on'); });
  var cur = document.querySelector('.survey-step[data-step="' + STEP_CUR + '"]');
  if (cur) cur.classList.add('on');
  // 进度
  var pct = Math.round((STEP_CUR - 1) / STEP_TOTAL * 100);
  var bar = document.getElementById('sProgBar');
  if (bar) bar.style.width = pct + '%';
  var pctEl = document.getElementById('sProgPct');
  if (pctEl) pctEl.textContent = (STEP_CUR - 1) + ' / ' + STEP_TOTAL;
  // 最终提交按钮
  var finalBtn = document.querySelector('.btn-submit-final');
  if (finalBtn) finalBtn.style.display = STEP_CUR > STEP_TOTAL ? 'block' : 'none';
  // 隐藏原生提交按钮（分步时）
  document.querySelectorAll('.btn-next-step').forEach(function(b) {
    var s = b.closest('.survey-step');
    b.style.display = (s && s.dataset.step == STEP_CUR) ? 'block' : 'none';
  });
  if (cur) window.scrollTo({ top: 0, behavior: 'smooth' });
}
function stepNext() {
  var cur = document.querySelector('.survey-step[data-step="' + STEP_CUR + '"]');
  if (!cur) return;
  // 校验必答
  var qid = cur.dataset.qid;
  var hasRating = cur.querySelector('input[data-rid]');
  if (hasRating && cur.querySelector('.q-title .req') && !hasRating.value) {
    alert('请完成本题'); return;
  }
  var radio = cur.querySelector('input[type=radio]:checked');
  if (cur.querySelector('.q-title .req') && cur.querySelector('input[type=radio]') && !radio) {
    alert('请完成本题'); return;
  }
  STEP_CUR++;
  if (STEP_CUR > STEP_TOTAL + 1) STEP_CUR = STEP_TOTAL + 1;
  updateStepUI();
}
function stepPrev() {
  STEP_CUR = Math.max(1, STEP_CUR - 1);
  updateStepUI();
}
// 游戏化/沉浸式：初始化分步（隐藏原生提交，显示导航）
document.addEventListener('DOMContentLoaded', function() {
  var tpl = document.body.getAttribute('data-template');
  if (tpl === 'cards' || tpl === 'gamified' || tpl === 'immersive') {
    updateStepUI();
    // 每个 step 底部加"上一题"（除了第一题）
    document.querySelectorAll('.survey-step').forEach(function(s) {
      var n = parseInt(s.dataset.step);
      if (n > 1) {
        var prev = document.createElement('button');
        prev.type = 'button';
        prev.textContent = '← 上一题';
        prev.style.cssText = 'margin-top:14px;padding:12px 32px;border-radius:999px;border:none;background:var(--border);color:var(--fg);font-weight:700;cursor:pointer;margin-right:10px';
        prev.onclick = stepPrev;
        var nxt = s.querySelector('.btn-next-step');
        if (nxt) nxt.parentNode.insertBefore(prev, nxt);
        else s.appendChild(prev);
      }
    });
  }
});
function surveySubmit(form) {
  // 分步模板：去掉隐藏步骤的 required，避免原生校验拦截
  var tpl = document.body.getAttribute('data-template');
  if (tpl === 'cards' || tpl === 'gamified' || tpl === 'immersive') {
    form.querySelectorAll('.survey-step').forEach(function(s) {
      if (!s.classList.contains('on')) {
        s.querySelectorAll('[required]').forEach(function(f) { f.removeAttribute('required'); });
      }
    });
  }
  // 校验评分题
  var missing = false;
  form.querySelectorAll('.q-card').forEach(function(card) {
    if (tpl === 'cards' || tpl === 'gamified' || tpl === 'immersive') {
      if (!card.closest('.survey-step').classList.contains('on')) return;
    }
    var qid = card.dataset.qid;
    var hasRating = card.querySelector('input[data-rid]');
    if (hasRating && !hasRating.value) {
      var title = card.querySelector('.q-title');
      if (title && title.querySelector('.req')) { missing = true; title.style.color = 'var(--danger)'; }
    }
  });
  if (missing) { alert('请完成所有必答题'); return false; }
  return true;
}
</script>
</body>
</html>
