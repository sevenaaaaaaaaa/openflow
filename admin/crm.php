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
$customers = crm_get_customers();
$arrData = crm_arr();

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
    } elseif ($action === 'to_customer') {
        $customer = crm_to_customer($email, [
            'arr' => (float)($_POST['arr'] ?? 0),
            'plan_type' => $_POST['plan_type'] ?? 'saas',
            'contract_end' => $_POST['contract_end'] ?? '',
            'notes' => $_POST['notes'] ?? '',
        ]);
        flash('success', $customer ? '已转为客户，可在「客户」视图管理' : '转客户失败');
    } elseif ($action === 'update_customer') {
        $customers = crm_get_customers();
        foreach ($customers as &$c) if (($c['id'] ?? '') === ($_POST['customer_id'] ?? '')) {
            if (isset($_POST['arr'])) $c['arr'] = (float)$_POST['arr'];
            if (isset($_POST['health'])) $c['health'] = $_POST['health'];
            if (isset($_POST['status'])) $c['status'] = $_POST['status'];
            if (isset($_POST['contract_end'])) $c['contract_end'] = $_POST['contract_end'];
            if (isset($_POST['plan_type'])) $c['plan_type'] = $_POST['plan_type'];
            $c['updated_at'] = date('Y-m-d H:i:s');
        }
        crm_save_customers($customers);
        flash('success', '客户信息已更新');
    } elseif ($action === 'import' && !empty($_FILES['csv_file']['tmp_name'])) {
        $imported = 0; $skipped = 0;
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle) {
            $header = fgetcsv($handle); // 表头
            while (($row = fgetcsv($handle)) !== false) {
                $line = array_combine($header, $row);
                $email2 = mb_strtolower(trim($line['email'] ?? ''));
                $name2 = trim($line['name'] ?? '');
                $phone2 = trim($line['phone'] ?? '');
                $company2 = trim($line['company'] ?? '');
                if ($email2 === '' && $phone2 === '') { $skipped++; continue; }
                $lead2 = crm_ensure_lead($email2 ?: $phone2, $name2, $phone2);
                if (!empty($company2)) $lead2['company'] = $company2;
                $lead2['source'] = 'import';
                $lead2['updated_at'] = date('Y-m-d H:i:s');
                crm_update_lead($email2 ?: $phone2, ['company'=>$company2, 'source'=>'import']);
                $imported++;
            }
            fclose($handle);
        }
        flash('success', "导入完成：新增/更新 {$imported} 条线索，跳过 {$skipped} 条");
    }
    header('Location: /xmp/crm' . (isset($_POST['focus']) ? '?focus=' . urlencode($email) : ''));
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
      <h1 style="margin-bottom:0"> CRM 线索</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=count($data['leads'] ?? [])?> 跟进线索</span>
        <span class="badge badge-gray"><?=count($rawLeads)?> 原始提交</span>
        <a href="export.php?format=csv" class="btn btn-ghost btn-sm">导出 CSV</a>
        <a href="export.php?format=json" class="btn btn-ghost btn-sm">导出 JSON</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('csvFile').click()">导入 CSV</button>
        <form method="post" enctype="multipart/form-data" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="import">
          <input type="file" name="csv_file" id="csvFile" accept=".csv,text/csv" style="display:none" onchange="this.form.submit()">
        </form>
      </div>
    </div>
    <p class="sub">线索阶段 · 打分 · 跟进 · 交接 · 商机转化 · 原始提交 · <a href="#importTip" style="color:var(--accent);cursor:pointer" onclick="document.getElementById('importTip').style.display=document.getElementById('importTip').style.display==='none'?'':'none'">CSV 导入格式</a></p>
    <div id="importTip" style="display:none;font-size:12px;color:var(--muted);background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:12px">CSV 需含表头：<code>name,email,phone,company</code>，每行一条线索（email 或 phone 至少一项）。</div>

    <div class="tabs" style="margin-bottom:16px">
      <a href="?tab=pipeline" class="<?=$tab==='pipeline'?'active':''?>">🔀 销售管线</a>
      <a href="?tab=kanban" class="<?=$tab==='kanban'?'active':''?>">📋 看板视图</a>
      <a href="?tab=pool" class="<?=$tab==='pool'?'active':''?>">🌊 公海 (<?=$poolCount?>)</a>
      <a href="?tab=raw" class="<?=$tab==='raw'?'active':''?>">📥 原始提交 (<?=count($rawLeads)?>)</a>
      <a href="?tab=customers" class="<?=$tab==='customers'?'active':''?>">🏢 客户 (<?=count(crm_get_customers())?>)</a>
      <a href="?tab=arr" class="<?=$tab==='arr'?'active':''?>">💰 ARR 报表</a>
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
          <span style="color:<?=['new'=>'var(--faint)','contacted'=>'var(--accent)','qualified'=>'var(--warn)','opportunity'=>'var(--accent)','won'=>'var(--ok)','lost'=>'var(--danger)'][$stageKey]?>">●</span>
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
      <div class="pipe-card"><div class="lab">有商机金额</div><div class="num" style="color:var(--accent)"><?=count(array_filter($poolLeads, fn($l) => ($l['value'] ?? 0) > 0))?></div></div>
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

    <?php elseif ($tab === 'customers'): ?>
    <!-- ═══ 客户管理：won 转客户，合同/续费/健康度 ═══ -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px">
      <div class="pipe-card"><div class="lab">活跃客户</div><div class="num" style="color:var(--ok)"><?=$arrData['active_customers']?></div></div>
      <div class="pipe-card"><div class="lab">当前 ARR</div><div class="num" style="color:var(--accent)">¥<?=number_format($arrData['arr'],0)?></div></div>
      <div class="pipe-card"><div class="lab">流失</div><div class="num" style="color:var(--danger)"><?=$arrData['churned_count']?></div></div>
      <div class="pipe-card"><div class="lab">流失率</div><div class="num"><?=$arrData['churn_rate']?>%</div></div>
    </div>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>客户</th><th>方案</th><th>ARR/年</th><th>合同周期</th><th>健康度</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($customers)): ?><tr><td colspan="7" class="empty">暂无客户。将「商机」阶段的线索转为客户后，在这里管理合同与续费。</td></tr><?php endif; ?>
          <?php foreach ($customers as $c): ?>
          <tr>
            <td><strong><?=htmlspecialchars($c['name'] ?: '—')?></strong><div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($c['company'] ?: $c['email'])?></div></td>
            <td><span class="stage-pill" style="background:var(--accent-soft);color:var(--accent)"><?=htmlspecialchars($c['plan_type'])?></span></td>
            <td style="color:var(--ok);font-weight:600">¥<?=number_format($c['arr'] ?? 0,0)?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['contract_start'] ?? '')?> ~ <?=htmlspecialchars($c['contract_end'] ?? '')?></td>
            <td><span class="stage-pill" style="background:<?=['healthy'=>'var(--ok-soft)','at_risk'=>'var(--warn-soft)','churned'=>'var(--danger-soft)'][$c['health']]??'var(--hover)';?>;color:<?=['healthy'=>'var(--ok)','at_risk'=>'var(--warn)','churned'=>'var(--danger)'][$c['health']]??'var(--muted)';?>"><?=['healthy'=>'健康','at_risk'=>'有风险','churned'=>'流失'][$c['health']]??$c['health']?></span></td>
            <td><span class="stage-pill" style="background:<?=$c['status']==='active'?'var(--ok-soft)':'var(--danger-soft)'?>;color:<?=$c['status']==='active'?'var(--ok)':'var(--danger)'?>"><?=$c['status']==='active'?'使用中':'已流失'?></span></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_customer">
                <input type="hidden" name="customer_id" value="<?=htmlspecialchars($c['id'])?>">
                <select name="health" onchange="this.form.submit()" style="padding:4px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
                  <option value="healthy" <?=$c['health']==='healthy'?'selected':''?>>健康</option>
                  <option value="at_risk" <?=$c['health']==='at_risk'?'selected':''?>>有风险</option>
                  <option value="churned" <?=$c['health']==='churned'?'selected':''?>>流失</option>
                </select>
                <select name="arr" onchange="this.form.submit()" style="padding:4px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
                  <?php foreach ([0,5000,10000,20000,50000,100000,200000] as $v): ?><option value="<?=$v?>" <?=(int)($c['arr']??0)===$v?'selected':''?>>¥<?=number_format($v,0)?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'arr'): ?>
    <!-- ═══ ARR 报表 ═══ -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
      <div class="pipe-card"><div class="lab">年度经常性收入 ARR</div><div class="num" style="color:var(--ok)">¥<?=number_format($arrData['arr'],0)?></div></div>
      <div class="pipe-card"><div class="lab">客户平均 ARR</div><div class="num" style="color:var(--accent)">¥<?=number_format($arrData['avg_arr'],0)?></div></div>
      <div class="pipe-card"><div class="lab">商机管道</div><div class="num" style="color:var(--warn)">¥<?=number_format($arrData['pipeline_value'],0)?></div><div class="lab"><?=$arrData['open_deals']?> 个开放商机</div></div>
      <div class="pipe-card"><div class="lab">已成交商机</div><div class="num"><?=$arrData['won_deals']?></div></div>
      <div class="pipe-card"><div class="lab">客户流失率</div><div class="num" style="color:<?=$arrData['churn_rate']>10?'var(--danger)':'var(--ok)'?>"><?=$arrData['churn_rate']?>%</div></div>
    </div>
    <div class="card" style="padding:24px">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:14px">ARR 构成</h3>
      <?php $maxArr = max(1, $arrData['arr']); $pipelinePct = round($arrData['pipeline_value'] / max(1, $arrData['arr'] + $arrData['pipeline_value']) * 100); ?>
      <div style="font-size:12px;color:var(--faint);margin-bottom:8px">已实现 ARR（客户合同 × 12）与商机管道（机会金额）占比</div>
      <div style="display:flex;height:14px;border-radius:99px;overflow:hidden;background:var(--hover);margin-bottom:14px">
        <div style="width:<?=100-$pipelinePct?>%;background:var(--ok)"></div>
        <div style="width:<?=$pipelinePct?>%;background:var(--warn)"></div>
      </div>
      <div style="display:flex;gap:20px;font-size:12px;color:var(--muted)">
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--ok);margin-right:5px"></span>已实现 ARR ¥<?=number_format($arrData['arr'],0)?></span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--warn);margin-right:5px"></span>商机管道 ¥<?=number_format($arrData['pipeline_value'],0)?></span>
      </div>
      <p style="font-size:12px;color:var(--faint);margin-top:18px;line-height:1.7">说明：ARR 由「客户管理」中 active 客户合同的年化金额汇总；商机管道为 opportunity 阶段线索的 value 汇总。将商机转为客户后，ARR 自动计入。</p>
    </div>

    <?php else: /* pipeline tab */ ?>

    <!-- 管线概览 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:20px">
      <div class="pipe-card"><div class="lab">管线总额</div><div class="num" style="color:var(--ok)">¥<?=number_format($pipelineValue,0)?></div></div>
      <?php foreach ($stages as $k => $label): ?>
      <div class="pipe-card"><div class="lab"><?=htmlspecialchars($label)?></div><div class="num" style="color:<?=['new'=>'var(--faint)','contacted'=>'var(--accent)','qualified'=>'var(--warn)','opportunity'=>'var(--accent)','won'=>'var(--ok)','lost'=>'var(--danger)'][$k]?>"><?=$stageCounts[$k] ?? 0?></div></div>
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
              <span class="stage-pill" style="background:#1e1e1e;color:var(--accent)"><?=htmlspecialchars($stages[$focusLead['stage']] ?? '')?></span>
              <span class="stage-pill" style="background:#f4f3e9">评分 <?=$focusLead['score']?></span>
              <span class="stage-pill" style="background:#f4f3e9">跟进人 <?=htmlspecialchars($adminNames[$focusLead['owner']] ?? $focusLead['owner'] ?: '未分配')?></span>
            </div>
            <?php
            // 线索 ↔ CDP 画像打通：按 email 找画像，展示设备/渠道/偏好
            $profile = null;
            try {
                require_once __DIR__ . '/../lib/CdpSystem.php';
                $profiles = CdpSystem::allProfiles();
                foreach ((array)$profiles as $p) {
                    if (mb_strtolower($p['properties']['email'] ?? '') === mb_strtolower($focusLead['email'] ?? '') || ($p['member_email'] ?? '') === ($focusLead['email'] ?? '')) { $profile = $p; break; }
                }
            } catch (Throwable $e) {}
            if ($profile):
                $prop = $profile['properties'] ?? [];
                $ch = $prop['utm_source'] ?? ($prop['channel'] ?? '直接访问');
            ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:12px;font-size:12px">
              <div style="padding:8px 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px"><div style="color:var(--faint)">来源渠道</div><b><?=htmlspecialchars($ch)?></b></div>
              <div style="padding:8px 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px"><div style="color:var(--faint)">设备</div><b><?=htmlspecialchars($prop['os'] ?? '—')?> / <?=htmlspecialchars($prop['browser'] ?? '—')?></b></div>
              <div style="padding:8px 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px"><div style="color:var(--faint)">城市</div><b><?=htmlspecialchars($prop['city'] ?? '—')?></b></div>
              <div style="padding:8px 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px"><div style="color:var(--faint)">事件数</div><b><?=htmlspecialchars($profile['events_count'] ?? 0)?></b></div>
              <?php if (!empty($prop['utm_campaign'])): ?><div style="padding:8px 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px"><div style="color:var(--faint)">活动</div><b><?=htmlspecialchars($prop['utm_campaign'])?></b></div><?php endif; ?>
              <?php if (!empty($profile['tags'])): ?><div style="padding:8px 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px"><div style="color:var(--faint)">标签</div><b style="font-size:11px"><?=htmlspecialchars(implode(' · ', array_slice($profile['tags'],0,4)))?></b></div><?php endif; ?>
            </div>
            <?php endif; ?>
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

          <!-- 转为客户（商机 → 客户） -->
          <div style="padding:12px;background:var(--surface-2);border-radius:12px;margin-bottom:12px">
            <div style="font-size:12px;font-weight:700;margin-bottom:8px">转为客户（won 后计入 ARR）</div>
            <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <?= csrf_field() ?>
              <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
              <input type="hidden" name="action" value="to_customer">
              <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
              <input type="number" name="arr" placeholder="年化 ARR ¥" step="100" style="width:110px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
              <select name="plan_type" style="padding:7px;border:1.5px solid var(--border);border-radius:8px">
                <option value="saas">SaaS 订阅</option><option value="private">私有化部署</option><option value="custom">定制开发</option>
              </select>
              <input type="date" name="contract_end" style="padding:7px;border:1.5px solid var(--border);border-radius:8px">
              <button type="submit" class="btn btn-primary btn-sm">转客户</button>
            </form>
          </div>

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
