<?php
/**
 * NPS 填写页 — 公开访问
 * /nps.php?id=nps_xxx
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/admin/nps-lib.php';

$id = req_str('id');
$project = nps_get_project($id);

if (!$project) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>问卷不存在 | 芭乐派 · OpenFlow</title>
    <link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
    <body class="bg-white text-gray-900"><div class="mx-auto max-w-lg px-5 py-[140px] text-center">
    <h1 class="text-2xl font-bold">问卷不存在</h1>
    <p class="mt-3 text-gray-600">请联系发送链接的管理员。</p>
    </div></body></html><?php
    exit;
}

$done = isset($_GET['done']);
$active = ($project['status'] ?? 'active') === 'active';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($project['title'])?> | 芭乐派 · OpenFlow NPS</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<style>
  body{background:var(--bg)}
  .score-btn{width:46px;height:46px;border-radius:50%;border:2px solid var(--border);display:grid;place-items:center;font-weight:700;font-size:16px;cursor:pointer;background:var(--surface);transition:.15s;color:var(--muted)}
  .score-btn:hover{border-color:var(--accent);transform:scale(1.08)}
  .score-btn.on{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
  .emoji{font-size:34px;transition:.2s}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body class="min-h-screen">
  <div class="mx-auto max-w-xl px-5 py-12">
    <div class="rounded-2xl p-8 text-center text-white mb-8" style="background:linear-gradient(160deg,var(--accent-strong),var(--accent))">
      <div style="font-size:40px">📈</div>
      <h1 class="mt-3 text-2xl font-bold"><?=htmlspecialchars($project['title'])?></h1>
      <p class="mt-2 text-[#cbd5e1] text-sm"><?=htmlspecialchars($project['collect_name'] ? '感谢你的反馈，请放心填写' : '匿名问卷 · 感谢你的反馈')?></p>
    </div>

    <?php if ($done): ?>
    <div class="bg-white rounded-2xl border border-[var(--border)] p-10 text-center">
      <div style="font-size:56px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 20 8-8M9 4l.5 3.5L13 8l-3.5.5L9 12l-.5-3.5L5 8l3.5-.5L9 4ZM16 6l.4 2.6L19 9l-2.6.4L16 12l-.4-2.6L13 9l2.6-.4L16 6ZM20 13l.3 2 2 .3-2 .3-.3 2-.3-2-2-.3 2-.3.3-2Z"/></svg></span></div>
      <h2 class="mt-3 text-xl font-bold">感谢你的反馈！</h2>
      <p class="mt-2 text-gray-600">你的意见对我们非常重要。</p>
      <a href="/" class="mt-6 inline-block rounded-full bg-[var(--accent)] px-7 py-3 font-semibold text-white">返回首页</a>
    </div>
    <?php elseif (!$active): ?>
    <div class="bg-white rounded-2xl border border-[var(--border)] p-10 text-center">
      <h2 class="text-xl font-bold">本次调研已结束</h2>
      <p class="mt-2 text-gray-600">感谢你的关注与支持。</p>
    </div>
    <?php else: ?>
    <form method="post" action="/api/nps-submit.php?id=<?=urlencode($project['id'])?>" onsubmit="return npsSubmit()">
      <div class="bg-white rounded-2xl border border-[var(--border)] p-8">
        <p class="text-[17px] font-bold text-center"><?=htmlspecialchars($project['question'] ?: '你有多大可能向朋友或同事推荐我们？')?></p>
        <div class="text-center mt-3 mb-6">
          <span class="emoji" id="npsEmoji">😐</span>
          <div class="flex justify-between text-[12px] text-gray-400 mt-1 px-1"><span>完全不可能</span><span>非常可能</span></div>
        </div>
        <div class="flex justify-center gap-2 flex-wrap" id="scoreRow">
          <?php for ($i = 0; $i <= 10; $i++): ?>
          <button type="button" class="score-btn" data-val="<?=$i?>" onclick="pickScore(<?=$i?>)"><?=$i?></button>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="score" id="scoreInput" required>

        <?php if ($project['collect_name']): ?>
        <div class="grid grid-cols-2 gap-4 mt-7">
          <div><label class="block text-[13px] font-semibold mb-1.5">姓名</label><input type="text" name="name" placeholder="选填" class="w-full rounded-xl border border-[var(--border)] p-3 text-sm outline-none"></div>
          <div><label class="block text-[13px] font-semibold mb-1.5">邮箱</label><input type="email" name="email" placeholder="选填" class="w-full rounded-xl border border-[var(--border)] p-3 text-sm outline-none"></div>
        </div>
        <?php endif; ?>

        <label class="block text-[14px] font-semibold mt-7 mb-1.5"><?=htmlspecialchars($project['followup_question'] ?: '你给出这个分数的主要原因是什么？')?></label>
        <textarea name="comment" rows="3" class="w-full rounded-xl border border-[var(--border)] p-3 text-sm outline-none resize-none" placeholder="告诉我们更多（选填）"></textarea>
      </div>
      <button type="submit" class="w-full mt-5 rounded-full py-4 font-bold text-lg" style="background:var(--accent);color:var(--on-accent);border:none;cursor:pointer">提交评分</button>
    </form>
    <?php endif; ?>
  </div>

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
