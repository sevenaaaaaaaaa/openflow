<?php
/**
 * 员工调研结果查询页 — 独立访问（无需后台登录）
 * /survey-my.php?token=xxx
 * token 由管理员在组织架构中生成，绑定员工邮箱/姓名
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/admin/survey-lib.php';

$token = trim($_GET['token'] ?? '');
$tokens = json_read(DATA_DIR . '/survey/employee-tokens.json');
$profile = null;
if ($token) {
    foreach ($tokens as $t) if (($t['token'] ?? '') === $token) { $profile = $t; break; }
}

$notFound = !$profile;
$surveys = survey_get_surveys();
$mySurveys = [];

if ($profile) {
    // 找出该员工参与过的调研（按 email 或 name 匹配答卷）
    foreach ($surveys as $s) {
        $responses = survey_get_responses($s['id']);
        $mine = array_values(array_filter($responses, function ($r) use ($profile) {
            $emailMatch = !empty($profile['email']) && ($r['email'] ?? '') === $profile['email'];
            $nameMatch = !empty($profile['name']) && ($r['name'] ?? '') === $profile['name'];
            return $emailMatch || $nameMatch;
        }));
        if (!empty($mine)) {
            $mySurveys[] = ['survey' => $s, 'responses' => $mine];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>我的调研结果 | 芭乐派 · OpenFlow</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" defer></script>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
<style>
  /* ── 设计语言统一：token 语义工具类 ── */
  .text-fg{color:var(--fg)}.text-muted{color:var(--muted)}.text-faint{color:var(--faint)}
  .text-accent{color:var(--accent)}.text-ok{color:var(--ok)}.text-danger{color:var(--danger)}
  body{background:var(--bg);font-family:var(--font-body)}
  .q-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px;margin-bottom:14px;box-shadow:0 4px 16px rgba(30,30,30,.05)}
  .ans-tag{display:inline-block;padding:4px 12px;border-radius:999px;background:var(--ok-soft);color:var(--ok);font-size:13px;font-weight:600;margin:3px}
</style>
</head>
<body class="min-h-screen">
  <div class="mx-auto max-w-2xl px-5 py-10">
    <div class="rounded-2xl p-8 text-center text-white mb-6" style="background:linear-gradient(160deg,var(--accent-strong),var(--accent))">
      <div style="font-size:38px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9V4ZM9 10h6M9 14h4"/></svg></span></div>
      <h1 class="mt-3 text-2xl font-bold">我的调研结果</h1>
      <?php if ($profile): ?>
      <p class="mt-2 text-[var(--muted)] text-sm"><?=htmlspecialchars($profile['name'] ?? '员工')?></p>
      <?php endif; ?>
    </div>

    <?php if ($notFound): ?>
    <div class="q-card text-center py-12">
      <div style="font-size:52px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span></div>
      <h2 class="mt-4 text-xl font-bold">链接无效或已过期</h2>
      <p class="mt-3 text-muted">请联系管理员获取正确的查询链接。</p>
    </div>
    <?php elseif (empty($mySurveys)): ?>
    <div class="q-card text-center py-12">
      <div style="font-size:52px">🗂️</div>
      <h2 class="mt-4 text-xl font-bold">暂未参与调研</h2>
      <p class="mt-3 text-muted">你还没有填写过任何调研问卷。</p>
    </div>
    <?php else: ?>
    <?php foreach ($mySurveys as $ms): $s = $ms['survey']; ?>
    <div class="q-card">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <h2 class="text-lg font-bold"><?=htmlspecialchars($s['title'])?></h2>
        <span class="text-[12px] text-muted"><?=count($ms['responses'])?> 次提交</span>
      </div>
      <?php foreach (array_reverse($ms['responses']) as $idx => $r): ?>
      <div style="border-top:1px dashed var(--border);<?=$idx===0?'border-top:0;':''?>padding-top:<?=$idx===0?'0':'14px'?>;margin-top:<?=$idx===0?'0':'14px'?>">
        <div class="text-[12px] text-faint mb-3">提交于 <?=htmlspecialchars(substr($r['created_at']??'',0,16))?></div>
        <?php foreach ($s['questions'] as $q): $v = $r['answers'][$q['id']] ?? ''; ?>
        <div style="margin-bottom:12px">
          <div style="font-size:13.5px;font-weight:600;margin-bottom:4px"><?=htmlspecialchars($q['title'])?></div>
          <?php if ($q['type'] === 'rating'): ?>
            <span class="ans-tag">⭐ <?=htmlspecialchars((string)$v)?> 分</span>
          <?php elseif ($q['type'] === 'multi' && is_array($v)): ?>
            <?php foreach ($v as $opt): if ($opt !== '') echo '<span class="ans-tag">' . htmlspecialchars($opt) . '</span>'; endforeach; ?>
          <?php else: ?>
            <div class="text-[14px] text-muted"><?=htmlspecialchars((string)$v ?: '未作答')?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center mt-8">
      <a href="/community" class="inline-block rounded-full bg-[var(--accent)] px-7 py-3 font-semibold text-white">返回社区</a>
    </div>
  </div>
</body>
</html>
