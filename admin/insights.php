<?php
/**
 * 营销洞察看板 — 表单/调研/NPS 数据汇总与趋势
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/nps-lib.php';
require_login();
require_perm('settings');

$submissions = json_read(DATA_DIR . '/submissions/index.json');
$forms = json_read(DATA_DIR . '/forms/index.json');
$surveys = json_read(DATA_DIR . '/survey/surveys.json');
$npsProjects = json_read(DATA_DIR . '/nps/projects.json');

// 表单类型映射
$formMap = [];
foreach ($forms as $f) $formMap[$f['id']] = $f['title'];

// 按天统计提交趋势（近14天）
$byDay = [];
foreach ($submissions as $s) {
    $day = substr($s['created_at'] ?? '', 0, 10);
    $byDay[$day] = ($byDay[$day] ?? 0) + 1;
}
krsort($byDay);
$trendDays = array_slice($byDay, 0, 14);

// 各表单提交分布
$byForm = [];
foreach ($submissions as $s) {
    $fid = $s['form_id'] ?? '';
    $byForm[$fid] = ($byForm[$fid] ?? 0) + 1;
}

// 表单类型分布（lead/download/newsletter）
$byType = ['lead'=>0,'download'=>0,'newsletter'=>0,'other'=>0];
foreach ($submissions as $s) {
    $t = $s['type'] ?? '';
    $byType[isset($byType[$t]) ? $t : 'other']++;
}

// NPS 汇总
$npsSummary = [];
foreach ($npsProjects as $p) {
    $resp = nps_get_responses($p['id']);
    $st = nps_compute($resp);
    $npsSummary[] = ['title'=>$p['title'], 'stats'=>$st, 'status'=>$p['status'] ?? 'active'];
}

// 调研回收汇总
$surveySummary = [];
foreach ($surveys as $s) {
    $resp = json_read(DATA_DIR . '/survey/responses/' . $s['id'] . '.json');
    $surveySummary[] = ['title'=>$s['title'], 'count'=>count($resp), 'status'=>$s['status'] ?? 'draft'];
}

$totalSubs = count($submissions);
$todaySubs = count(array_filter($submissions, fn($s) => substr($s['created_at'] ?? '',0,10) === date('Y-m-d')));

admin_header('营销洞察');
?>
<style>
.metric-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px}
.metric-card .val{font-size:28px;font-weight:800;margin-top:4px}
.metric-card .lab{font-size:12px;color:var(--text-3)}
.bar-row{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.bar-row .lab{width:90px;font-size:13px;color:var(--text-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bar-row .bar{flex:1;height:20px;background:var(--surface-2);border-radius:6px;overflow:hidden}
.bar-row .bar i{display:block;height:100%;background:linear-gradient(90deg,#7dd3fc,#38bdf8);border-radius:6px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('insights'); ?>
  <div class="main">
    <h1>📊 营销洞察</h1>
    <p class="sub">汇总表单、调研、NPS 数据 · 洞察线索获取与用户反馈</p>

    <!-- 核心指标 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px">
      <div class="metric-card"><div class="lab">总提交</div><div class="val"><?=$totalSubs?></div></div>
      <div class="metric-card"><div class="lab">今日提交</div><div class="val" style="color:var(--ok)"><?=$todaySubs?></div></div>
      <div class="metric-card"><div class="lab">表单数</div><div class="val"><?=count($forms)?></div></div>
      <div class="metric-card"><div class="lab">调研项目</div><div class="val"><?=count($surveys)?></div></div>
      <div class="metric-card"><div class="lab">NPS 项目</div><div class="val"><?=count($npsProjects)?></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="insight-grid">
      <!-- 提交趋势 -->
      <div class="card">
        <h2>📈 提交趋势（近 14 天）</h2>
        <?php if (empty($trendDays)): ?>
        <div class="empty" style="padding:32px">暂无数据</div>
        <?php else: $max = max($trendDays) ?: 1; ?>
        <div style="display:flex;align-items:flex-end;gap:6px;height:120px">
          <?php foreach ($trendDays as $day => $cnt): ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
            <span style="font-size:10px;color:var(--text-3)"><?=$cnt?></span>
            <div style="width:100%;height:<?=max(3, round($cnt/$max*100))?>px;background:<?=$cnt>0?'#2e6b4f':'#e5e3da'?>;border-radius:3px"></div>
            <span style="font-size:9px;color:var(--text-3)"><?=substr($day,5)?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 表单类型分布 -->
      <div class="card">
        <h2>🧩 提交类型分布</h2>
        <?php $typeTotal = array_sum($byType); if ($typeTotal === 0): ?>
        <div class="empty" style="padding:32px">暂无数据</div>
        <?php else: ?>
        <?php foreach ($byType as $t => $cnt): if ($cnt === 0) continue; $pct = round($cnt/$typeTotal*100); ?>
        <div class="bar-row">
          <div class="lab"><?=['lead'=>'预约线索','download'=>'资料下载','newsletter'=>'订阅','other'=>'其他'][$t]?></div>
          <div class="bar"><i style="width:<?=$pct?>%"></i></div>
          <span style="font-size:12px;color:var(--text-2);width:60px"><?=$cnt?> (<?=$pct?>%)</span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- 各表单提交 -->
    <div class="card">
      <h2>📋 各表单提交量</h2>
      <?php if (empty($byForm)): ?><div class="empty" style="padding:24px">暂无表单提交</div>
      <?php else: $maxForm = max($byForm) ?: 1; ?>
      <?php foreach ($byForm as $fid => $cnt): ?>
      <div class="bar-row">
        <div class="lab"><?=htmlspecialchars($formMap[$fid] ?? $fid)?></div>
        <div class="bar"><i style="width:<?=round($cnt/$maxForm*100)?>%"></i></div>
        <span style="font-size:12px;color:var(--text-2);width:50px"><?=$cnt?></span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- NPS 总览 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📈 NPS 项目总览</h2>
      <table>
        <thead><tr><th>项目</th><th>状态</th><th>回收</th><th>NPS</th><th>推荐者</th><th>被动者</th><th>贬损者</th></tr></thead>
        <tbody>
          <?php if (empty($npsSummary)): ?><tr><td colspan="7" class="empty">暂无 NPS 项目</td></tr><?php endif; ?>
          <?php foreach ($npsSummary as $n): ?>
          <tr>
            <td><strong><?=htmlspecialchars($n['title'])?></strong></td>
            <td><span class="badge <?=($n['status']??'')==='active'?'badge-green':'badge-gray'?>"><?=$n['status']??'active'?></span></td>
            <td><?=$n['stats']['total']?></td>
            <td><strong style="color:<?=$n['stats']['nps']!==null && $n['stats']['nps']>=0?'var(--ok)':'var(--danger)'?>"><?=$n['stats']['nps'] ?? '—'?></strong></td>
            <td><?=$n['stats']['promoters']?></td>
            <td><?=$n['stats']['passives']?></td>
            <td><?=$n['stats']['detractors']?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 调研回收 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">🗂️ 调研回收总览</h2>
      <table>
        <thead><tr><th>调研</th><th>状态</th><th>回收数</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($surveySummary)): ?><tr><td colspan="4" class="empty">暂无调研项目</td></tr><?php endif; ?>
          <?php foreach ($surveySummary as $s): ?>
          <tr>
            <td><strong><?=htmlspecialchars($s['title'])?></strong></td>
            <td><span class="badge <?=(($s['status']??'')==='active') ? 'badge-green' : (($s['status']??'')==='closed' ? 'badge-yellow' : 'badge-gray')?>"><?=$s['status']??'draft'?></span></td>
            <td><?=$s['count']?></td>
            <td><a href="survey-stats.php?survey=<?=urlencode($s['title']===$s['title']?'':'')?>" class="btn btn-ghost btn-sm" onclick="return false">—</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.insight-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
