<?php
/**
 * 调研系统 — 统计查看（角色化范围）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/survey-lib.php';
require_login();
require_perm('settings');

$surveys = survey_get_surveys();
$me = survey_current_user();
$scope = survey_scope($me);

// 当前查看的问卷
$currentId = $_GET['survey'] ?? ($surveys[0]['id'] ?? '');
$survey = survey_get_survey($currentId);

// 角色范围过滤
$visibleResponses = $survey ? survey_filter_responses($survey['id'], $me) : [];
$visibleSurveys = array_filter($surveys, function ($s) use ($me, $scope) {
    // 按问卷投放范围 + 角色过滤
    if (($s['company_scope'] ?? 'all') === 'all') return true;
    if ($scope['type'] === 'all') return true;
    $targets = $s['company_ids'] ?? [];
    if ($scope['type'] === 'company') return in_array($scope['company'], $targets);
    if ($scope['type'] === 'department') return in_array($scope['company'], $targets);
    return true;
});

// 统计
$stats = ($survey && !empty($visibleResponses)) ? survey_compute_stats($survey, $visibleResponses) : [];

$roleLabel = ['company_admin' => '公司管理员', 'department_admin' => '部门管理员', 'hr' => 'HR', 'employee' => '员工'][$me['role'] ?? 'employee'];
$scopeDesc = ['all' => '全部范围', 'company' => '本公司', 'department' => '本部门', 'self' => '仅自己'][$scope['type']];

admin_header('调研统计');
?>
<style>
.stat-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;background:#f4f3e9;color:#5b5b52;border:1px solid #e2dfd2}
.bar-wrap{background:#f4f3e9;border-radius:99px;height:14px;overflow:hidden}
.bar-fill{height:100%;background:linear-gradient(90deg,#86efac,#ddff0e);border-radius:99px}
.avg-big{font-size:32px;font-weight:800;color:#1a1625}
</style>
<div class="admin-layout">
  <?php admin_sidebar('survey-stats'); ?>
  <div class="main">
    <h1>📊 调研统计</h1>
    <p class="sub">按角色查看统计范围 · 你的角色：<strong><?=$roleLabel?></strong>（<?=$scopeDesc?>）</p>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <a href="survey" class="btn btn-ghost">📋 问卷管理</a>
      <a href="survey-stats.php" class="btn btn-primary">📊 统计查看</a>
      <a href="survey-org.php" class="btn btn-ghost">🏢 组织架构</a>
      <a href="survey-agent.php" class="btn btn-ghost" style="margin-left:auto">🤖 官方 Agent 咨询</a>
    </div>

    <?php if (empty($visibleSurveys)): ?>
    <div class="card"><div class="empty">你的权限范围内暂无问卷</div></div>
    <?php else: ?>
    <!-- 问卷切换 -->
    <div class="flex gap-2 mb-4" style="flex-wrap:wrap">
      <?php foreach ($visibleSurveys as $s): ?>
      <a href="survey-stats.php?survey=<?=urlencode($s['id'])?>" class="btn btn-sm <?=$s['id']===$currentId?'btn-primary':'btn-ghost'?>"><?=htmlspecialchars($s['title'])?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$survey): ?>
    <div class="card"><div class="empty">问卷不存在</div></div>
    <?php else: ?>
    <div class="flex gap-3 mb-4" style="flex-wrap:wrap;align-items:center">
      <span class="stat-chip">📝 <?=htmlspecialchars($survey['title'])?></span>
      <span class="stat-chip">📥 回收 <?=count($visibleResponses)?> 份</span>
      <span class="stat-chip">🔒 范围：<?=$scopeDesc?></span>
      <a href="../survey.php?id=<?=urlencode($survey['id'])?>" class="btn btn-ghost btn-sm" target="_blank" style="margin-left:auto">✍️ 打开填写页</a>
    </div>

    <?php if (empty($visibleResponses)): ?>
    <div class="card"><div class="empty">范围内暂无回收数据</div></div>
    <?php else: ?>

    <!-- 每题统计 -->
    <?php foreach ($stats as $st): ?>
    <div class="card">
      <h2><?=htmlspecialchars($st['question'])?>
        <span class="badge badge-gray" style="font-size:11px;margin-left:6px"><?=['single'=>'单选','multi'=>'多选','rating'=>'评分','text'=>'文本'][$st['type']]?></span>
      </h2>
      <?php if ($st['type'] === 'rating' && $st['avg'] !== null): ?>
        <div style="display:flex;gap:24px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
          <div class="avg-big"><?=$st['avg']?><span style="font-size:14px;color:#9a94ac"> / <?=$st['max']?></span></div>
          <div>
            <div class="stat-chip">共 <?=$st['count']?> 人评分</div>
            <div class="stat-chip">最低 <?=$st['min']?></div>
            <div class="stat-chip">最高 <?=$st['max']?></div>
          </div>
        </div>
      <?php elseif ($st['type'] === 'single' || $st['type'] === 'multi' || $st['type'] === 'dropdown'): ?>
        <?php if (!empty($st['distribution'])):
          $maxCnt = max($st['distribution']);
        ?>
        <?php foreach ($st['distribution'] as $opt => $cnt): $pct = $maxCnt > 0 ? round($cnt / $st['total'] * 100) : 0; ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
            <span><?=htmlspecialchars($opt)?></span>
            <span style="color:#6b6580"><?=$cnt?> 人 · <?=$pct?>%</span>
          </div>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?=$pct?>%"></div></div>
        </div>
        <?php endforeach; ?>
        <?php else: ?><p class="text-sm text-muted">暂无选项数据</p><?php endif; ?>
      <?php else: ?>
        <p class="text-sm text-muted">文本题：请在下方「回收明细」中查看具体回答</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- 回收明细 -->
    <div class="card" style="padding:0;overflow-x:auto">
      <h2 style="padding:20px 20px 0">📥 回收明细</h2>
      <table>
        <thead><tr><th>时间</th><th>公司</th><th>部门</th><th>姓名</th><?php foreach ($survey['questions'] as $q): ?><th><?=htmlspecialchars(mb_substr($q['title'],0,12))?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach (array_slice(array_reverse($visibleResponses), 0, 50) as $r): ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($r['created_at'],0,16))?></td>
            <td><?=htmlspecialchars($r['company'] ?: '—')?></td>
            <td><?=htmlspecialchars($r['department'] ?: '—')?></td>
            <td><?=htmlspecialchars($r['name'] ?: ($r['email'] ?: '匿名'))?></td>
            <?php foreach ($survey['questions'] as $q):
              $v = $r['answers'][$q['id']] ?? '';
              $display = is_array($v) ? implode('、', $v) : (string)$v;
            ?>
            <td class="text-sm" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($display)?>"><?=htmlspecialchars(mb_substr($display,0,30))?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($visibleResponses) > 50): ?><p class="text-sm text-muted" style="padding:12px 20px">仅显示最近 50 条，共 <?=count($visibleResponses)?> 条</p><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
