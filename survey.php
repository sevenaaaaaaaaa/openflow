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
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>问卷不存在 | <?=site_config_get("site_name")?></title><?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?></head>
    <body style="display:grid;place-items:center;min-height:100vh;text-align:center;padding:20px"><div>
    <p class="kicker" style="font-size:40px;letter-spacing:0">404</p>
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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($survey['title'])?> | <?=site_config_get("site_name")?> 调研</title>
<meta name="robots" content="noindex">
<script type="application/ld+json"><?=json_encode($jsonLd, JSON_UNESCAPED_UNICODE)?></script>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 问卷独立页：无外壳，居中一列题卡。选项 / 评分 / 分步进度为本页独有；沉浸式模板改为深色 token 底。 */
body{display:flex;flex-direction:column;align-items:center;padding:clamp(24px,6vw,72px) 20px}
.sv{width:min(680px,100%);display:flex;flex-direction:column;gap:16px}
.sv-head{text-align:center;display:flex;flex-direction:column;align-items:center;gap:12px;padding:clamp(20px,4vw,36px) 0 8px}
.sv-head h1{font-size:clamp(26px,4vw,38px);font-weight:800;letter-spacing:-.02em;line-height:1.2}
.sv-head p{color:var(--muted);line-height:1.8;max-width:540px}
.q{padding:24px 26px;display:flex;flex-direction:column;gap:12px}
.q-title{font-size:16px;font-weight:700;line-height:1.5}
.q-title .req{color:var(--danger)}
.q-title small{font-weight:400;font-size:12px;color:var(--muted)}
.opt-label{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:14px;background:var(--surface);transition:border-color .15s,background .15s}
.opt-label:hover{border-color:var(--accent);background:var(--bg-soft)}
.opt-label:has(input:checked){border-color:var(--accent);background:var(--accent-soft)}
.opt-label input{margin:0;accent-color:var(--accent)}
.opts{display:flex;flex-direction:column;gap:8px}
.rating-row{display:flex;gap:8px;flex-wrap:wrap}
.rating-btn{width:44px;height:44px;border-radius:10px;border:1.5px solid var(--border);display:grid;place-items:center;font-weight:700;background:var(--surface);color:var(--muted);transition:border-color .15s,background .15s,color .15s}
.rating-btn:hover{border-color:var(--accent)}
.rating-btn.on{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
.survey-step{display:none;animation:stepIn .35s var(--ease-spring)}
.survey-step.on{display:flex}
@keyframes stepIn{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:none}}
.survey-progress{position:sticky;top:0;z-index:10;background:color-mix(in oklab,var(--bg),transparent 15%);backdrop-filter:blur(8px);padding:12px 0}
.survey-progress .bar{height:8px;background:var(--border);border-radius:99px;overflow:hidden}
.survey-progress .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--ok),var(--accent));transition:width .3s}
.survey-progress .pct{font-size:12px;color:var(--muted);margin-top:6px;font-family:var(--font-mono)}
.step-nav{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
body[data-template="gamified"] .opt-label:active{transform:scale(.97)}
body[data-template="gamified"] .opt-label:has(input:checked){background:var(--accent);border-color:var(--accent);color:var(--on-accent);font-weight:600}
body[data-template="gamified"] .q-title{font-size:20px;text-align:center;padding:12px 0 4px}
body[data-template="immersive"]{background:linear-gradient(160deg,var(--accent-strong),var(--accent)) fixed}
body[data-template="immersive"] .sv-head h1,body[data-template="immersive"] .sv-head p{color:var(--on-accent)}
body[data-template="immersive"] .q-title{font-size:22px}
body[data-template="immersive"] .survey-progress{background:color-mix(in oklab,var(--accent-strong),transparent 30%)}
body[data-template="immersive"] .survey-progress .pct{color:var(--on-accent)}
</style>
</head>
<body data-template="<?=htmlspecialchars($survey['template'] ?? 'classic')?>">
<main class="sv" id="main">
  <div class="sv-head survey-head">
    <span class="kicker">SURVEY · 调研</span>
    <h1><?=htmlspecialchars($survey['title'])?></h1>
    <?php if (!empty($survey['description'])): ?><p><?=htmlspecialchars($survey['description'])?></p><?php endif; ?>
    <span class="pill neutral"><?=($survey['type']==='named'?'实名问卷':'匿名问卷')?> · <?=count($survey['questions'])?> 题 · 约需 3 分钟</span>
  </div>

  <?php if ($submitted): ?>
  <div class="form-card gate-box"><span class="kicker">已提交</span><h2>提交成功</h2><p>感谢你的参与！你的反馈对我们非常重要。</p><a href="/community" class="btn primary">返回社区</a></div>
  <?php else: ?>
  <form method="post" action="/api/survey-submit.php?id=<?=urlencode($survey['id'])?>" onsubmit="return surveySubmit(this)" style="display:contents">
    <!-- 身份信息（仅实名问卷） -->
    <?php if ($survey['type'] === 'named'): ?>
    <div class="card q">
      <div class="q-title">基本信息</div>
      <div class="grid g2" style="gap:14px">
        <div class="field"><label>姓名</label><input class="inp" type="text" name="name" required placeholder="你的姓名"></div>
        <div class="field"><label>邮箱</label><input class="inp" type="email" name="email" required placeholder="用于查询结果"></div>
        <div class="field"><label>公司</label><input class="inp" type="text" name="company" placeholder="公司名称"></div>
        <div class="field"><label>部门</label><input class="inp" type="text" name="department" placeholder="部门名称"></div>
      </div>
    </div>
    <?php else: ?>
    <div class="card q">
      <div class="q-title">基本信息 <small>（用于统计范围，可匿名填写）</small></div>
      <div class="grid g2" style="gap:14px">
        <div class="field"><label>公司</label><input class="inp" type="text" name="company" placeholder="公司名称（可选）"></div>
        <div class="field"><label>部门</label><input class="inp" type="text" name="department" placeholder="部门名称（可选）"></div>
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
    <div class="card q<?=$isStep?' survey-step':''?>" data-qid="<?=$q['id']?>" data-step="<?=$stepNo?>">
      <?php if ($isStep): ?><span class="kicker" style="font-size:11px">第 <?=$stepNo?> 题 / 共 <?=count($survey['questions'])?> 题</span><?php endif; ?>
      <div class="q-title"><?=htmlspecialchars($q['title'])?><?php if ($q['required']): ?> <span class="req">*</span><?php endif; ?></div>
      <?php if ($q['type'] === 'single' || $q['type'] === 'dropdown'): ?>
        <div class="opts">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <label class="opt-label"><input type="radio" name="ans[<?=$q['id']?>]" value="<?=htmlspecialchars($opt)?>" <?=$q['required']?'required':''?> onclick="<?=$isStep?'stepNext()':''?>"> <?=htmlspecialchars($opt)?></label>
        <?php endforeach; ?>
        </div>
      <?php elseif ($q['type'] === 'multi'): ?>
        <div class="opts">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <label class="opt-label"><input type="checkbox" name="ans[<?=$q['id']?>][]" value="<?=htmlspecialchars($opt)?>"> <?=htmlspecialchars($opt)?></label>
        <?php endforeach; ?>
        </div>
        <?php if ($isStep): ?><div class="step-nav"><button type="button" class="btn primary btn-next-step" onclick="stepNext()">下一题 →</button></div><?php endif; ?>
      <?php elseif ($q['type'] === 'rating'): $scale = $q['scale'] ?? 5; ?>
        <div class="rating-row">
          <?php for ($r = 1; $r <= $scale; $r++): ?>
          <button type="button" class="rating-btn" onclick="pickRating(this, '<?=$q['id']?>')" data-qid="<?=$q['id']?>" data-val="<?=$r?>"><?=$r?></button>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="ans[<?=$q['id']?>]" data-rid="<?=$q['id']?>">
        <?php if ($isStep): ?><div class="step-nav"><button type="button" class="btn primary btn-next-step" onclick="stepNext()">下一题 →</button></div><?php endif; ?>
      <?php else: ?>
        <textarea class="inp" name="ans[<?=$q['id']?>]" rows="3" placeholder="请输入..." <?=$q['required']?'required':''?>></textarea>
        <?php if ($isStep): ?><div class="step-nav"><button type="button" class="btn primary btn-next-step" onclick="stepNext()">下一题 →</button></div><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($isStep): ?>
    <div class="survey-step survey-submit" data-step="<?=count($survey['questions'])+1?>">
      <div class="form-card gate-box" style="width:100%">
        <span class="ic" style="width:44px;height:44px;color:var(--accent)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 20 8-8M9 4l.5 3.5L13 8l-3.5.5L9 12l-.5-3.5L5 8l3.5-.5L9 4ZM16 6l.4 2.6L19 9l-2.6.4L16 12l-.4-2.6L13 9l2.6-.4L16 6ZM20 13l.3 2 2 .3-2 .3-.3 2-.3-2-2-.3 2-.3.3-2Z"/></svg></span>
        <h2>全部答完！</h2>
        <p>确认无误后提交。</p>
      </div>
    </div>
    <button type="submit" class="btn primary btn-submit-final" style="display:none;width:100%;height:54px;font-size:16px">提交问卷</button>
    <?php else: ?>
    <button type="submit" class="btn primary" style="width:100%;height:54px;font-size:16px">提交问卷</button>
    <?php endif; ?>
    <p class="note" style="text-align:center;margin:0">你的回答仅用于组织健康分析，信息严格保密</p>
  </form>
  <?php endif; ?>
</main>

<script>
function pickRating(btn, qid) {
  var box = btn.closest('.q');
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
  if (finalBtn) finalBtn.style.display = STEP_CUR > STEP_TOTAL ? 'inline-flex' : 'none';
  // 隐藏原生提交按钮（分步时）
  document.querySelectorAll('.btn-next-step').forEach(function(b) {
    var s = b.closest('.survey-step');
    b.style.display = (s && s.dataset.step == STEP_CUR) ? 'inline-flex' : 'none';
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
        prev.className = 'btn ghost step-prev';
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
  form.querySelectorAll('.q').forEach(function(card) {
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
