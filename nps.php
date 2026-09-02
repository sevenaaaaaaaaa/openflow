<?php
/**
 * NPS 填写页 — 公开访问（独立页，不接站点外壳：是发给外部受访者的问卷）
 *
 * v7（2026-09-01）：从 tailwind + standalone.css 迁到 tokens + modules（form-card / btn / inp）。提交逻辑原样保留。
 * /nps.php?id=nps_xxx
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/admin/nps-lib.php';

$id = req_str('id');
$project = nps_get_project($id);

if (!$project) {
    http_response_code(404);
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>问卷不存在</title><?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?></head>
    <body style="display:grid;place-items:center;min-height:100vh;text-align:center;padding:20px"><div>
    <h1 style="font-size:24px;font-weight:700">问卷不存在</h1>
    <p style="margin-top:12px;color:var(--muted)">请联系发送链接的管理员。</p>
    </div></body></html><?php
    exit;
}

$done = isset($_GET['done']);
$active = ($project['status'] ?? 'active') === 'active';
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($project['title'])?> | 芭乐派 · OpenFlow NPS</title>
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* NPS 独立页：无外壳，居中一张卡。评分按钮为本页独有。 */
body{display:flex;flex-direction:column;align-items:center;padding:clamp(24px,6vw,72px) 20px}
.nps{width:min(620px,100%);display:flex;flex-direction:column;gap:22px}
.nps .brand{justify-content:center}
.q{font-size:18px;font-weight:800;text-align:center;line-height:1.5;letter-spacing:-.01em}
.emoji{font-size:38px;text-align:center;display:block;margin:14px 0 6px;transition:transform .2s var(--ease-spring)}
.scale{display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:11.5px;color:var(--faint);padding:0 4px}
.scores{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:14px}
.score-btn{width:44px;height:44px;border-radius:50%;border:2px solid var(--border);display:grid;place-items:center;font-weight:700;font-size:15px;background:var(--surface);color:var(--muted);transition:transform .15s var(--ease-spring),border-color .15s,background .15s,color .15s}
.score-btn:hover{border-color:var(--accent);transform:scale(1.08)}
.score-btn.on{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media (max-width:520px){.g2{grid-template-columns:1fr}.score-btn{width:38px;height:38px;font-size:13.5px}}
</style>
</head>
<body>
<main class="nps">
  <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
  <div class="hero-center" style="padding:6px 0 0;gap:12px">
    <span class="kicker">NPS</span>
    <h1 style="font-size:clamp(24px,4vw,34px)"><?=htmlspecialchars($project['title'])?></h1>
    <p class="lead" style="font-size:14.5px"><?=htmlspecialchars($project['collect_name'] ? '感谢你的反馈，请放心填写' : '匿名问卷 · 感谢你的反馈')?></p>
  </div>
  <?php if ($done): ?>
  <div class="form-card gate-box"><span class="kicker">已提交</span><h2>感谢你的反馈！</h2><p>你的意见对我们非常重要。</p><a href="/" class="btn primary">返回首页</a></div>
  <?php elseif (!$active): ?>
  <div class="form-card gate-box"><h2>本次调研已结束</h2><p>感谢你的关注与支持。</p></div>
  <?php else: ?>
  <form method="post" action="/api/nps-submit.php?id=<?=urlencode($project['id'])?>" onsubmit="return npsSubmit()" class="form-card form-grid">
    <p class="q"><?=htmlspecialchars($project['question'] ?: '你有多大可能向朋友或同事推荐我们？')?></p>
    <span class="emoji" id="npsEmoji">😐</span>
    <div class="scale"><span>完全不可能</span><span>非常可能</span></div>
    <div class="scores" id="scoreRow"><?php for ($i = 0; $i <= 10; $i++): ?><button type="button" class="score-btn" data-val="<?=$i?>" onclick="pickScore(<?=$i?>)"><?=$i?></button><?php endfor; ?></div>
    <input type="hidden" name="score" id="scoreInput" required>
    <?php if ($project['collect_name']): ?>
    <div class="g2" style="margin-top:10px">
      <div class="field"><label for="nps-name">姓名</label><input class="inp" id="nps-name" type="text" name="name" placeholder="选填"></div>
      <div class="field"><label for="nps-email">邮箱</label><input class="inp" id="nps-email" type="email" name="email" placeholder="选填"></div>
    </div>
    <?php endif; ?>
    <div class="field" style="margin-top:10px"><label for="nps-comment"><?=htmlspecialchars($project['followup_question'] ?: '你给出这个分数的主要原因是什么？')?></label><textarea class="inp" id="nps-comment" name="comment" rows="3" placeholder="告诉我们更多（选填）"></textarea></div>
    <button type="submit" class="btn primary" style="width:100%;margin-top:6px">提交评分</button>
  </form>
  <?php endif; ?>
</main>
<script>
var EMOJIS = { 0:'😫',1:'😫',2:'😞',3:'😞',4:'😕',5:'😐',6:'😐',7:'🙂',8:'😊',9:'😄',10:'🤩' };
function pickScore(v) {
  document.querySelectorAll('.score-btn').forEach(function(b) { b.classList.remove('on'); });
  document.querySelectorAll('.score-btn').forEach(function(b) {
    if (parseInt(b.dataset.val) <= v) b.classList.add('on');
  });
  document.getElementById('scoreInput').value = v;
  document.getElementById('npsEmoji').textContent = EMOJIS[v] || '😐';
}
function npsSubmit() {
  var s = document.getElementById('scoreInput').value;
  if (!s) { alert('请选择一个分数'); return false; }
  return true;
}
</script>
</body>
</html>
