<?php
/**
 * NPS 调研系统 — 项目管理与统计
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/nps-lib.php';
require_login();
require_perm('settings');

$projects = nps_get_projects();
$message = '';
$error = '';

// 创建项目
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    csrf_verify();
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        $error = '项目名称不能为空';
    } else {
        $projects[] = [
            'id' => 'nps_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'title' => $title,
            'question' => trim($_POST['question'] ?? '') ?: '你有多大可能向朋友或同事推荐我们？',
            'followup_question' => trim($_POST['followup_question'] ?? '') ?: '你给出这个分数的主要原因是什么？',
            'collect_name' => isset($_POST['collect_name']),
            'status' => $_POST['status'] ?? 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        nps_save_projects($projects);
        flash('success', 'NPS 项目已创建');
        header('Location: /xmp/nps?project=' . end($projects)['id']);
        exit;
    }
}

// 删除
if (isset($_GET['delete'])) {
    csrf_verify();
    // basename 收口，防止 ?delete=../../x 目录穿越删掉任意 .json
    $delId = basename((string)$_GET['delete']);
    $projects = array_values(array_filter($projects, fn($p) => $p['id'] !== $delId));
    nps_save_projects($projects);
    @unlink(nps_responses_dir() . '/' . $delId . '.json');
    flash('success', 'NPS 项目已删除');
    header('Location: /xmp/nps');
    exit;
}

// 状态切换
if (isset($_GET['toggle'])) {
    foreach ($projects as &$p) if ($p['id'] === $_GET['toggle']) $p['status'] = ($p['status'] ?? '') === 'active' ? 'closed' : 'active';
    unset($p);
    nps_save_projects($projects);
    header('Location: /xmp/nps');
    exit;
}

$currentId = $_GET['project'] ?? ($projects[0]['id'] ?? '');
$current = nps_get_project($currentId);
$responses = $current ? nps_get_responses($current['id']) : [];
$stats = $current ? nps_compute($responses) : null;

// 时间序列（按天）
$trendByDay = [];
foreach ($responses as $r) {
    $day = substr($r['created_at'] ?? '', 0, 10);
    $trendByDay[$day][] = $r['score'] ?? 0;
}
$trendData = [];
foreach ($trendByDay as $day => $scores) {
    $trendData[] = ['day' => $day, 'nps' => nps_compute(array_map(fn($s) => ['score' => $s], $scores))['nps'], 'count' => count($scores)];
}
// 时间升序
usort($trendData, fn($a, $b) => strcmp($a['day'], $b['day']));
$trendData = array_slice($trendData, -14);

admin_header('NPS 调研系统');
?>
<style>
.nps-score{font-size:56px;font-weight:800;line-height:1}
.nps-ring{width:120px;height:120px;border-radius:50%;display:grid;place-items:center;flex-shrink:0}
.nps-ring .inner{width:96px;height:96px;border-radius:50%;background:var(--surface);display:grid;place-items:center}
.nps-bar{height:10px;border-radius:99px;overflow:hidden;display:flex;background:#f4f3e9}
.nps-bar i{height:100%}
.nps-seg{display:inline-block;width:calc(100% / 11);height:100%}
</style>
<div class="admin-layout">
  <?php admin_sidebar('nps'); ?>
  <div class="main">
    <h1>NPS 调研系统</h1>
    <p class="sub">净推荐值（Net Promoter Score）· 衡量客户与员工忠诚度 · 0-10 分 · 9-10 推荐者 / 7-8 被动者 / 0-6 贬损者</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <a href="nps.php" class="btn btn-ghost" onclick="showCreateBox()">➕ 新建 NPS 项目</a>
      <?php if ($current): ?>
      <a href="../nps.php?id=<?=urlencode($current['id'])?>" class="btn btn-ghost" target="_blank" style="margin-left:auto">✍️ 打开填写页</a>
      <a href="nps.php?toggle=<?=urlencode($current['id'])?>" class="btn btn-ghost"><?=($current['status'] ?? '') === 'active' ? '⏸ 关闭' : '▶ 开启'?></a>
      <?php endif; ?>
    </div>

    <!-- 创建框 -->
    <div class="card" id="createBox" style="display:none">
      <h2>➕ 新建 NPS 项目</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field"><label>项目名称 <span class="hint">· 必填</span></label><input type="text" name="title" required placeholder="如：官网用户 NPS / 员工 NPS"></div>
          <div class="field"><label>状态</label><select name="status"><option value="active">发布中</option><option value="closed">已结束</option></select></div>
        </div>
        <div class="field"><label>核心问题</label><input type="text" name="question" placeholder="你有多大可能向朋友或同事推荐我们？"></div>
        <div class="field"><label>跟进问题 <span class="hint">· 开放式评论</span></label><input type="text" name="followup_question" placeholder="你给出这个分数的主要原因是什么？"></div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="collect_name" value="1" style="width:16px;height:16px">收集受访者姓名/邮箱（可选）</label></div>
        <button type="submit" name="create" class="btn btn-primary">创建项目</button>
      </form>
    </div>

    <!-- 项目切换 + 统计 -->
    <div class="flex gap-2 mb-4" style="flex-wrap:wrap">
      <?php foreach ($projects as $p):
        $pStats = nps_compute(nps_get_responses($p['id']));
      ?>
      <a href="nps.php?project=<?=urlencode($p['id'])?>" class="btn btn-sm <?=$p['id']===$currentId?'btn-primary':'btn-ghost'?>">
        <?=htmlspecialchars($p['title'])?>
        <?php if ($pStats['nps'] !== null): ?><span style="opacity:.8"> · <?=$pStats['nps']?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($current && $stats): ?>
    <?php $grade = nps_grade($stats['nps'] ?? 0); ?>
    <!-- 评分总览 -->
    <div class="card">
      <div style="display:flex;gap:32px;align-items:center;flex-wrap:wrap">
        <div class="nps-ring" style="background:conic-gradient(<?=$grade[1]?> <?=(max(0,$stats['nps']??0)/100)*360?>deg, var(--surface-2) 0deg)">
          <div class="inner">
            <div class="nps-score" style="color:<?=$grade[1]?>"><?=$stats['nps'] ?? '—'?></div>
          </div>
        </div>
        <div style="flex:1;min-width:260px">
          <div style="font-size:20px;font-weight:700"><?=$grade[0]?></div>
          <div class="text-sm text-muted" style="margin-top:6px">共 <?=$stats['total']?> 份回收 · 平均 <?=$stats['avg']?> 分</div>
          <div class="nps-bar" style="margin-top:16px;display:flex;overflow:hidden">
            <i style="width:<?=$stats['detractor_pct']?>%;background:var(--danger)"></i>
            <i style="width:<?=$stats['passive_pct']?>%;background:var(--warn)"></i>
            <i style="width:<?=$stats['promoter_pct']?>%;background:var(--ok)"></i>
          </div>
          <div style="display:flex;gap:18px;margin-top:10px;font-size:13px;flex-wrap:wrap">
            <span><b style="color:var(--ok)">😍 推荐者</b> <?=$stats['promoters']?> (<?=$stats['promoter_pct']?>%)</span>
            <span><b style="color:var(--warn)">😐 被动者</b> <?=$stats['passives']?> (<?=$stats['passive_pct']?>%)</span>
            <span><b style="color:var(--danger)">😞 贬损者</b> <?=$stats['detractors']?> (<?=$stats['detractor_pct']?>%)</span>
          </div>
        </div>
      </div>

      <!-- 分数分布 -->
      <div style="margin-top:24px">
        <p style="font-size:13px;font-weight:600;margin-bottom:8px">分数分布（0-10）</p>
        <div style="display:flex;gap:4px;align-items:flex-end;height:120px">
          <?php $maxD = max($stats['distribution']) ?: 1; ?>
          <?php foreach ($stats['distribution'] as $score => $cnt):
            $h = $cnt > 0 ? max(6, round($cnt / $maxD * 110)) : 2;
            $c = $score >= 9 ? 'var(--ok)' : ($score >= 7 ? 'var(--warn)' : 'var(--danger)');
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
            <span style="font-size:11px;color:var(--text-3)"><?=$cnt?:''?></span>
            <div style="width:100%;height:<?=$h?>px;background:<?=$c?>;border-radius:4px 4px 0 0"></div>
            <span style="font-size:10px;color:var(--text-3)"><?=$score?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 趋势 -->
      <?php if (count($trendData) >= 2): ?>
      <div style="margin-top:24px">
        <p style="font-size:13px;font-weight:600;margin-bottom:8px">NPS 趋势</p>
        <div style="display:flex;align-items:flex-end;gap:6px;height:90px">
          <?php $maxN = max(array_map(fn($t) => $t['nps'] ?? 0, $trendData)) ?: 1; ?>
          <?php $minN = min(array_map(fn($t) => $t['nps'] ?? 0, $trendData)) ?: 0; ?>
          <?php $range = max(1, $maxN - $minN); ?>
          <?php foreach ($trendData as $t): ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
            <span style="font-size:10px;color:var(--text-3)"><?=$t['nps']?></span>
            <div style="width:100%;height:<?=$t['nps']>=0?max(4,($t['nps']-$minN)/$range*80):4?>px;background:<?=$t['nps']>=0?'#2e6b4f':'var(--danger)'?>;border-radius:3px"></div>
            <span style="font-size:9px;color:var(--text-3);white-space:nowrap"><?=substr($t['day'],5)?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 评论列表 -->
    <div class="card" style="padding:0;overflow-x:auto">
      <h2 style="padding:20px 20px 0">💬 受访者评论</h2>
      <?php if (empty($responses)): ?>
      <div class="empty" style="padding:32px">暂无回收数据，分享填写链接开始收集</div>
      <?php else: ?>
      <table>
        <thead><tr><th>时间</th><th>分数</th><th><?=htmlspecialchars($current['collect_name'] ? '受访者' : '类型')?></th><th>评论</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($responses) as $r): ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($r['created_at']??'',0,16))?></td>
            <td><span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:700;color:#fff;background:<?=($r['score']??0)>=9?'var(--ok)':(($r['score']??0)>=7?'var(--warn)':'var(--danger)')?>"><?=htmlspecialchars($r['score'])?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($r['name'] ?: ($r['source'] ?: '匿名'))?></td>
            <td class="text-sm" style="max-width:400px"><?=htmlspecialchars($r['comment'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php elseif (empty($projects)): ?>
    <div class="card"><div class="empty">还没有 NPS 项目，点击「➕ 新建 NPS 项目」创建</div></div>
    <?php endif; ?>
  </div>
</div>

<script>
function showCreateBox() {
  var b = document.getElementById('createBox');
  b.style.display = b.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php admin_footer(); ?>
