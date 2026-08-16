<?php
/**
 * CRM 线索管理 — 阶段 / 打分 / 跟进 / 交接 / 商机转化
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('leads');

$data = crm_get();
$leads = $data['leads'] ?? [];
$stages = crm_stages();
$users = get_users();
$adminNames = [];
foreach ($users as $uKey => $u) {
    $adminNames[$uKey] = is_array($u) ? ($u['name'] ?? $u['username'] ?? $uKey) : $uKey;
}
$tab = $_GET['tab'] ?? 'pipeline';
$rawLeads = get_leads();

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = $_POST['email'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'update_stage') {
        crm_convert($email, $_POST['stage'] ?? 'new', (float)($_POST['value'] ?? 0), $_POST['expected_close'] ?? '');
        flash('success', '阶段已更新');
    } elseif ($action === 'add_followup') {
        crm_add_followup($email, trim($_POST['content'] ?? ''));
        flash('success', '跟进记录已添加');
    } elseif ($action === 'score') {
        crm_score($email, (int)($_POST['delta'] ?? 0));
        flash('success', '评分已更新');
    } elseif ($action === 'transfer') {
        crm_transfer($email, $_POST['owner'] ?? '');
        flash('success', '线索已交接');
    }
    header('Location: crm.php' . (isset($_POST['focus']) ? '?focus=' . urlencode($email) : ''));
    exit;
}

// 排序（按更新时间倒序）
usort($leads, fn($a,$b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

// 阶段筛选
$stageFilter = $_GET['stage'] ?? '';
if ($stageFilter) $leads = array_values(array_filter($leads, fn($l) => ($l['stage'] ?? '') === $stageFilter));
$focus = $_GET['focus'] ?? '';
$focusLead = null;
if ($focus) foreach ($leads as $l) if (mb_strtolower($l['email']) === mb_strtolower($focus)) { $focusLead = $l; break; }

// 统计
$stageCounts = [];
foreach ($data['leads'] ?? [] as $l) $stageCounts[$l['stage']] = ($stageCounts[$l['stage']] ?? 0) + 1;
$pipelineValue = 0;
foreach ($data['leads'] ?? [] as $l) if (in_array($l['stage'], ['opportunity','qualified'])) $pipelineValue += (float)($l['value'] ?? 0);

// 公海：没有跟进人的线索（暂无人跟进）
$poolLeads = array_values(array_filter($data['leads'] ?? [], fn($l) => empty($l['owner'])));
$poolCount = count($poolLeads);

admin_header('CRM 线索管理');
?>
<style>
.pipe-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center}
.pipe-card .num{font-size:24px;font-weight:800}
.pipe-card .lab{font-size:12px;color:var(--text-3)}
.stage-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600}
.followup{background:var(--surface-2);border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:13px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('crm'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">💼 CRM 线索</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=count($data['leads'] ?? [])?> 跟进线索</span>
        <span class="badge badge-gray"><?=count($rawLeads)?> 原始提交</span>
        <a href="export.php?format=csv" class="btn btn-ghost btn-sm">导出 CSV</a>
        <a href="export.php?format=json" class="btn btn-ghost btn-sm">导出 JSON</a>
      </div>
    </div>
    <p class="sub">线索阶段 · 打分 · 跟进 · 交接 · 商机转化 · 原始提交</p>

    <div class="tabs" style="margin-bottom:16px">
      <a href="?tab=pipeline" class="<?=$tab==='pipeline'?'active':''?>">🔀 销售管线</a>
      <a href="?tab=kanban" class="<?=$tab==='kanban'?'active':''?>">📋 看板视图</a>
      <a href="?tab=pool" class="<?=$tab==='pool'?'active':''?>">🌊 公海 (<?=$poolCount?>)</a>
      <a href="?tab=raw" class="<?=$tab==='raw'?'active':''?>">📥 原始提交 (<?=count($rawLeads)?>)</a>
    </div>

    <?php if ($tab === 'kanban'): ?>
    <!-- ═══ 看板视图 ═══ -->
    <style>
    .kanban{display:flex;gap:12px;overflow-x:auto;padding-bottom:12px;min-height:400px}
    .kanban-col{min-width:240px;max-width:280px;flex:1;background:var(--surface-2);border-radius:12px;padding:12px;display:flex;flex-direction:column}
    .kanban-col-header{font-weight:700;font-size:13px;margin-bottom:10px;display:flex;align-items:center;gap:6px}
    .kanban-col-header .count{background:var(--border);border-radius:99px;padding:1px 8px;font-size:11px;font-weight:600}
    .kanban-cards{flex:1;display:flex;flex-direction:column;gap:8px;min-height:60px}
    .kanban-cards.drag-over{background:var(--accent);opacity:.15;border-radius:8px}
    .kanban-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px;cursor:grab;transition:box-shadow .15s}
    .kanban-card:active{cursor:grabbing;box-shadow:0 4px 16px rgba(0,0,0,.12)}
    .kanban-card.dragging{opacity:.4}
    .kanban-card .name{font-weight:600;font-size:13px;margin-bottom:2px}
    .kanban-card .email{font-size:11px;color:var(--muted);word-break:break-all}
    .kanban-card .meta{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;align-items:center}
    .kanban-card .score{font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px}
    .kanban-card .value{font-size:11px;color:var(--accent);font-weight:600}
    .kanban-card .owner{font-size:10px;color:var(--muted);margin-left:auto}
    </style>
    <div class="kanban" id="kanbanBoard">
      <?php foreach ($stages as $stageKey => $stageLabel):
        $stageLeads = array_values(array_filter($leads, fn($l) => ($l['stage'] ?? '') === $stageKey));
      ?>
      <div class="kanban-col" data-stage="<?=$stageKey?>">
        <div class="kanban-col-header">
          <span style="color:<?=['new'=>'#9ca3af','contacted'=>'var(--accent)','qualified'=>'var(--warn)','opportunity'=>'#7c3aed','won'=>'var(--ok)','lost'=>'var(--danger)'][$stageKey]?>">●</span>
          <?=htmlspecialchars($stageLabel)?><span class="count"><?=count($stageLeads)?></span>
        </div>
        <div class="kanban-cards" data-stage="<?=$stageKey?>">
          <?php foreach ($stageLeads as $l): ?>
          <div class="kanban-card" draggable="true" data-email="<?=htmlspecialchars($l['email'])?>" data-id="<?=htmlspecialchars($l['id'] ?? $l['email'])?>">
            <div class="name"><?=htmlspecialchars($l['name'] ?: '—')?></div>
            <div class="email"><?=htmlspecialchars($l['email'])?></div>
            <div class="meta">
              <span class="score" style="background:<?=($l['score']>=60)?'#dcfce7':(($l['score']>=30)?'#fef3c7':'#fee2e2')?>;color:<?=($l['score']>=60)?'#166534':(($l['score']>=30)?'#92400e':'#991b1b')?>"><?=$l['score']?></span>
              <?php if (!empty($l['value'])): ?><span class="value">¥<?=number_format($l['value'],0)?></span><?php endif; ?>
              <?php if (!empty($l['owner'])): ?><span class="owner"><?=htmlspecialchars($adminNames[$l['owner']] ?? $l['owner'])?></span><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Hidden form for AJAX stage update -->
    <form id="kanbanForm" method="post" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="email" id="kanbanEmail">
      <input type="hidden" name="action" value="update_stage">
      <input type="hidden" name="stage" id="kanbanStage">
      <input type="hidden" name="focus" id="kanbanFocus">
    </form>

    <script>
    (function(){
      var dragCard = null;
      var board = document.getElementById('kanbanBoard');
      // Card drag events
      board.addEventListener('dragstart', function(e) {
        var card = e.target.closest('.kanban-card');
        if (!card) return;
        dragCard = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.email);
      });
      board.addEventListener('dragend', function(e) {
        var card = e.target.closest('.kanban-card');
        if (card) card.classList.remove('dragging');
        dragCard = null;
        document.querySelectorAll('.kanban-cards.drag-over').forEach(function(el){ el.classList.remove('drag-over'); });
      });
      // Column drop zones
      board.querySelectorAll('.kanban-cards').forEach(function(col) {
        col.addEventListener('dragover', function(e) {
          if (!dragCard) return;
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          col.classList.add('drag-over');
        });
        col.addEventListener('dragleave', function(e) {
          col.classList.remove('drag-over');
        });
        col.addEventListener('drop', function(e) {
          e.preventDefault();
          col.classList.remove('drag-over');
          if (!dragCard) return;
          var newStage = col.dataset.stage;
          var email = dragCard.dataset.email;
          // Move card visually
          col.appendChild(dragCard);
          // Update counts
          board.querySelectorAll('.kanban-col').forEach(function(c) {
            var cnt = c.querySelector('.kanban-cards').querySelectorAll('.kanban-card').length;
            c.querySelector('.count').textContent = cnt;
          });
          // AJAX update
          document.getElementById('kanbanEmail').value = email;
          document.getElementById('kanbanStage').value = newStage;
          document.getElementById('kanbanFocus').value = email;
          var fd = new FormData(document.getElementById('kanbanForm'));
          fetch('crm.php', { method: 'POST', body: fd });
        });
      });
    })();
    </script>

    <script>
    function aiScoreLead() {
      var box = document.getElementById('aiScoreBox');
      var lead = {
        email: <?=json_encode($focusLead['email'] ?? '')?>,
        name: <?=json_encode($focusLead['name'] ?? '')?>,
        company: <?=json_encode($focusLead['company'] ?? '')?>,
        source: <?=json_encode($focusLead['source'] ?? '')?>,
        score: <?=(int)($focusLead['score'] ?? 0)?>,
        stage: <?=json_encode($focusLead['stage'] ?? '')?>
      };
      box.style.display = 'block';
      box.innerHTML = '<div class="text-sm text-muted">🤖 AI 评分中…</div>';
      fetch('../api/ai-business.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'score_lead', lead: lead})
      }).then(function(r){return r.json();}).then(function(d){
        if (!d.ok) { box.innerHTML = '<div class="text-sm text-muted">评分失败</div>'; return; }
        var color = d.score >= 70 ? 'var(--ok)' : (d.score >= 40 ? 'var(--warn)' : 'var(--danger)');
        var h = '<div style="padding:12px;background:var(--surface-2);border-radius:10px">' +
          '<div style="display:flex;align-items:center;gap:8px;font-size:14px">' +
          '<span>AI 建议评分</span><strong style="color:' + color + ';font-size:22px">' + d.score + '</strong>' +
          '<span class="badge ' + (d.priority==='high'?'badge-green':(d.priority==='medium'?'badge-yellow':'badge-gray')) + '">' + d.priority + ' 优先</span>' +
          (d.ai ? '<span class="badge badge-green">AI</span>' : '<span class="badge badge-gray">规则</span>') + '</div>' +
          (d.advice ? '<div style="margin-top:8px;font-size:13px;line-height:1.6">💡 ' + d.advice + '</div>' : '') +
          '</div>';
        box.innerHTML = h;
      });
    }
    </script>

    <?php elseif ($tab === 'raw'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <?php if (empty($rawLeads)): ?>
        <div class="empty">暂无原始提交（网站表单提交后自动写入 CSV）</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <?php foreach (array_keys($rawLeads[0]) as $header): ?>
            <th><?=htmlspecialchars($header)?></th>
            <?php endforeach; ?>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rawLeads as $rl):
            $rlEmail = $rl['email'] ?? '';
          ?>
          <tr>
            <?php foreach ($rl as $v): ?>
            <td><?=htmlspecialchars($v)?></td>
            <?php endforeach; ?>
            <td>
              <?php if ($rlEmail): ?><a href="?tab=pipeline&focus=<?=urlencode($rlEmail)?>" class="btn btn-ghost btn-sm">→ 转跟进</a><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php elseif ($tab === 'pool'): ?>
    <!-- ═══ 公海：暂无人跟进的线索 ═══ -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
      <div class="pipe-card"><div class="lab">公海线索</div><div class="num" style="color:var(--warn)"><?=$poolCount?></div></div>
      <div class="pipe-card"><div class="lab">高评分（≥60）</div><div class="num" style="color:var(--ok)"><?=count(array_filter($poolLeads, fn($l) => ($l['score'] ?? 0) >= 60))?></div></div>
      <div class="pipe-card"><div class="lab">有商机金额</div><div class="num" style="color:#7c3aed"><?=count(array_filter($poolLeads, fn($l) => ($l['value'] ?? 0) > 0))?></div></div>
      <div class="pipe-card"><div class="lab">原始提交未认领</div><div class="num"><?=count(array_filter($rawLeads, function($rl) use ($data) {
          $emails = array_column($data['leads'] ?? [], 'email');
          return !in_array($rl['email'] ?? '', array_map('mb_strtolower', $emails));
      }))?></div></div>
    </div>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>线索</th><th>阶段</th><th>评分</th><th>商机</th><th>最近跟进</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($poolLeads)): ?><tr><td colspan="6" class="empty">🎉 公海为空，所有线索都已有人跟进</td></tr><?php endif; ?>
          <?php foreach ($poolLeads as $l): ?>
          <tr>
            <td>
              <strong><?=htmlspecialchars($l['name'] ?: '—')?></strong>
              <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($l['email'])?></div>
            </td>
            <td><span class="stage-pill" style="background:#f4f3e9;color:#6b6580"><?=htmlspecialchars($stages[$l['stage']] ?? $l['stage'])?></span></td>
            <td><b style="color:<?=($l['score']??0)>=60?'var(--ok)':(($l['score']??0)>=30?'var(--warn)':'var(--danger)')?>"><?=$l['score'] ?? 0?></b></td>
            <td><?=($l['value'] ?? 0) ? '¥'.number_format($l['value'],0) : '—'?></td>
            <td class="text-sm text-muted"><?=!empty($l['follow_ups']) ? htmlspecialchars($l['follow_ups'][count($l['follow_ups'])-1]['time'] ?? '') : '—'?></td>
            <td>
              <a href="?tab=pipeline&focus=<?=urlencode($l['email'])?>" class="btn btn-ghost btn-sm">👁 认领并跟进</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php else: /* pipeline tab */ ?>

    <!-- 管线概览 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:20px">
      <div class="pipe-card"><div class="lab">管线总额</div><div class="num" style="color:var(--ok)">¥<?=number_format($pipelineValue,0)?></div></div>
      <?php foreach ($stages as $k => $label): ?>
      <div class="pipe-card"><div class="lab"><?=htmlspecialchars($label)?></div><div class="num" style="color:<?=['new'=>'#9ca3af','contacted'=>'var(--accent)','qualified'=>'var(--warn)','opportunity'=>'#7c3aed','won'=>'var(--ok)','lost'=>'var(--danger)'][$k]?>"><?=$stageCounts[$k] ?? 0?></div></div>
      <?php endforeach; ?>
    </div>

    <!-- 阶段筛选 -->
    <div class="flex gap-2 mb-4" style="flex-wrap:wrap">
      <a href="crm.php" class="btn btn-sm <?=!$stageFilter?'btn-primary':'btn-ghost'?>">全部 (<?=count($data['leads'] ?? [])?>)</a>
      <?php foreach ($stages as $k => $label): ?>
      <a href="?stage=<?=$k?>" class="btn btn-sm <?=$stageFilter===$k?'btn-primary':'btn-ghost'?>"><?=htmlspecialchars($label)?> (<?=$stageCounts[$k] ?? 0?>)</a>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:<?=$focusLead?'1.3fr 1fr':'1fr'?>;gap:20px" class="crm-grid">
      <!-- 线索列表 -->
      <div class="card" style="padding:0;overflow:auto">
        <table>
          <thead><tr><th>线索</th><th>阶段</th><th>评分</th><th>跟进人</th><th>商机</th><th>操作</th></tr></thead>
          <tbody>
            <?php if (empty($leads)): ?><tr><td colspan="6" class="empty">暂无线索（表单提交自动生成）</td></tr><?php endif; ?>
            <?php foreach ($leads as $l): ?>
            <tr>
              <td>
                <strong><?=htmlspecialchars($l['name'] ?: '—')?></strong>
                <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($l['email'])?></div>
              </td>
              <td><span class="stage-pill" style="background:<?=['new'=>'#f4f3e9','contacted'=>'#dbeafe','qualified'=>'#fef3c7','opportunity'=>'#ede9fe','won'=>'#dcfce7','lost'=>'#fee2e2'][$l['stage']]?>;color:<?=['new'=>'#6b6580','contacted'=>'#1d4ed8','qualified'=>'#92400e','opportunity'=>'#5b21b6','won'=>'#166534','lost'=>'#991b1b'][$l['stage']]?>"><?=htmlspecialchars($stages[$l['stage']] ?? $l['stage'])?></span></td>
              <td><b style="color:<?=$l['score']>=60?'var(--ok)':($l['score']>=30?'var(--warn)':'var(--danger)')?>"><?=$l['score']?></b></td>
              <td class="text-sm text-muted"><?=htmlspecialchars($adminNames[$l['owner']] ?? $l['owner'] ?: '—')?></td>
              <td><?=$l['value'] ? '¥'.number_format($l['value'],0) : '—'?></td>
              <td><a href="?focus=<?=urlencode($l['email'])?><?=$stageFilter?'&stage='.$stageFilter:''?>" class="btn btn-ghost btn-sm">👁 详情</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 详情 -->
      <?php if ($focusLead): ?>
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:20px 20px 0">👁 线索详情</h2>
        <div style="padding:0 20px 20px">
          <!-- 基础信息 -->
          <div style="padding:14px;background:var(--surface-2);border-radius:12px;margin-bottom:14px">
            <div class="font-bold text-lg"><?=htmlspecialchars($focusLead['name'] ?: '—')?></div>
            <div class="text-sm text-muted"><?=htmlspecialchars($focusLead['email'])?> · <?=htmlspecialchars($focusLead['phone'])?></div>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
              <span class="stage-pill" style="background:#1e1e1e;color:#38bdf8"><?=htmlspecialchars($stages[$focusLead['stage']] ?? '')?></span>
              <span class="stage-pill" style="background:#f4f3e9">评分 <?=$focusLead['score']?></span>
              <span class="stage-pill" style="background:#f4f3e9">跟进人 <?=htmlspecialchars($adminNames[$focusLead['owner']] ?? $focusLead['owner'] ?: '未分配')?></span>
            </div>
          </div>

          <!-- 阶段更新 + 商机 -->
          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="update_stage">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <select name="stage" style="padding:7px;border:1.5px solid var(--border);border-radius:8px">
              <?php foreach ($stages as $k => $label): ?>
              <option value="<?=$k?>" <?=$focusLead['stage']===$k?'selected':''?>><?=htmlspecialchars($label)?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="value" value="<?=htmlspecialchars($focusLead['value'] ?? 0)?>" placeholder="商机金额" step="100" style="width:110px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <input type="date" name="expected_close" value="<?=htmlspecialchars($focusLead['expected_close'] ?? '')?>" style="padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <button type="submit" class="btn btn-primary btn-sm">更新阶段</button>
          </form>

          <!-- 交接 -->
          <form method="post" style="display:flex;gap:8px;margin-bottom:12px">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="transfer">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <select name="owner" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px">
              <?php foreach ($adminNames as $uk => $un): ?>
              <option value="<?=htmlspecialchars($uk)?>" <?=$focusLead['owner']===$uk?'selected':''?>><?=htmlspecialchars($un)?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">交接线索</button>
          </form>

          <!-- 手动加分 -->
          <form method="post" style="display:flex;gap:8px;margin-bottom:16px">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="score">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <span class="text-sm text-muted" style="align-self:center">手动加分</span>
            <input type="number" name="delta" value="10" step="5" style="width:70px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <button type="submit" class="btn btn-ghost btn-sm">调整</button>
          </form>

          <!-- AI 评分建议 -->
          <div style="margin-bottom:16px">
            <button type="button" class="btn btn-primary btn-sm" onclick="aiScoreLead()">🤖 AI 评分建议</button>
            <div id="aiScoreBox" style="margin-top:8px;display:none"></div>
          </div>

          <!-- 跟进记录 -->
          <div class="text-sm font-semibold mb-2">📝 跟进记录</div>
          <form method="post" style="display:flex;gap:8px;margin-bottom:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="add_followup">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="text" name="content" placeholder="记录跟进内容…" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px">
            <button type="submit" class="btn btn-primary btn-sm">添加</button>
          </form>
          <?php if (empty($focusLead['follow_ups'])): ?>
          <p class="text-sm text-muted">暂无跟进记录</p>
          <?php else: foreach (array_reverse($focusLead['follow_ups']) as $f): ?>
          <div class="followup">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-3);margin-bottom:4px"><b><?=htmlspecialchars($f['owner'])?></b><span><?=htmlspecialchars($f['time'])?></span></div>
            <div><?=htmlspecialchars($f['content'])?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<style>@media(max-width:900px){.crm-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
