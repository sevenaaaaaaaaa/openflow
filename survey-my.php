<?php
/**
 * 员工调研结果查询页 — 独立访问（无需后台登录；不接站点外壳）
 *
 * v7（2026-09-01）：从 tailwind 迁到 tokens + modules。
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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>我的调研结果 | 芭乐派 · OpenFlow</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 调研结果独立页：无外壳，居中单栏。 */
body{display:flex;flex-direction:column;align-items:center;padding:clamp(24px,6vw,72px) 20px}
.wrap{width:min(720px,100%);display:flex;flex-direction:column;gap:18px}
.brand{justify-content:center}
.resp{border-top:1px dashed var(--border);padding-top:14px;margin-top:14px}
.resp:first-child{border-top:none;padding-top:0;margin-top:0}
.qa{margin-bottom:12px}
.qa b{display:block;font-size:13.5px;margin-bottom:4px}
.qa .v{font-size:14px;color:var(--muted)}
</style>
</head>
<body>
<main class="wrap">
  <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
  <div class="hero-center" style="padding:6px 0 0;gap:12px">
    <span class="kicker">调研</span>
    <h1 style="font-size:clamp(24px,4vw,34px)">我的调研结果</h1>
    <?php if ($profile): ?><p class="lead" style="font-size:14.5px"><?=htmlspecialchars($profile['name'] ?? '员工')?></p><?php endif; ?>
  </div>
  <?php if ($notFound): ?>
  <div class="card gate-box"><h2>链接无效或已过期</h2><p>请联系管理员获取正确的查询链接。</p></div>
  <?php elseif (empty($mySurveys)): ?>
  <div class="card gate-box"><span class="kicker">暂未参与</span><h2>暂未参与调研</h2><p>你还没有填写过任何调研问卷。</p></div>
  <?php else: ?>
  <?php foreach ($mySurveys as $ms): $s = $ms['survey']; ?>
  <div class="card">
    <div class="sec-head row" style="margin-bottom:14px"><div><h2 style="font-size:19px"><?=htmlspecialchars($s['title'])?></h2></div><span class="sub"><?=count($ms['responses'])?> 次提交</span></div>
    <?php foreach (array_reverse($ms['responses']) as $idx => $r): ?>
    <div class="resp">
      <div class="note" style="margin-bottom:10px">提交于 <?=htmlspecialchars(substr($r['created_at']??'',0,16))?></div>
      <?php foreach ($s['questions'] as $q): $v = $r['answers'][$q['id']] ?? ''; ?>
      <div class="qa"><b><?=htmlspecialchars($q['title'])?></b>
        <?php if ($q['type'] === 'rating'): ?><span class="badge ok"><?=htmlspecialchars((string)$v)?> 分</span>
        <?php elseif ($q['type'] === 'multi' && is_array($v)): ?><span class="tags"><?php foreach ($v as $opt): if ($opt !== '') echo '<span>' . htmlspecialchars($opt) . '</span>'; endforeach; ?></span>
        <?php else: ?><div class="v"><?=htmlspecialchars((string)$v ?: '未作答')?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <div class="cta-row" style="justify-content:center"><a href="/community" class="btn primary">返回社区</a></div>
</main>
</body>
</html>
