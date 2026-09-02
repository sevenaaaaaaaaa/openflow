<?php
/**
 * CRM 线索管理 — 阶段 / 打分 / 跟进 / 交接 / 商机转化
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_once __DIR__ . '/../lib/PrivacySystem.php';
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
    } elseif ($action === 'add_task') {
        $tasks = json_read(DATA_DIR . '/tasks.json');
        $tasks[] = [
            'id' => 't' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 4),
            'type' => 'crm_followup',
            'email' => $email,
            'title' => trim($_POST['title'] ?? ''),
            'due' => $_POST['due'] ?? '',
            'owner' => $_SESSION['admin_user'] ?? '',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/tasks.json', $tasks);
        flash('success', '跟进任务已创建');
    } elseif ($action === 'complete_task') {
        $tasks = json_read(DATA_DIR . '/tasks.json');
        foreach ($tasks as &$t) if (($t['id'] ?? '') === ($_POST['task_id'] ?? '')) $t['status'] = 'done';
        json_write(DATA_DIR . '/tasks.json', $tasks);
        flash('success', '任务已完成');
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
    } elseif ($action === 'claim_raw') {
        // 原始提交 → 跟进线索（CSV 表头是中文：时间/来源页面/姓名/电话/公司/邮箱/职位/需求留言/其他字段）
        $rEmail = mb_strtolower(trim($_POST['raw_email'] ?? '')); $rPhone = trim($_POST['raw_phone'] ?? ''); $rName = trim($_POST['raw_name'] ?? '');
        if ($rEmail === '' && $rPhone === '') { flash('error', '这条提交没有邮箱也没有电话，无法建线索'); header('Location: /xmp/crm?tab=raw'); exit; }
        $lead = crm_ensure_lead($rEmail ?: $rPhone, $rName, $rPhone);
        $d = crm_get(); $k = mb_strtolower($rEmail ?: $rPhone);
        if (isset($d['leads'][$k])) {
            if (empty($d['leads'][$k]['company']) && !empty($_POST['raw_company'])) $d['leads'][$k]['company'] = trim($_POST['raw_company']);
            if (empty($d['leads'][$k]['source'])) $d['leads'][$k]['source'] = 'form:' . trim($_POST['raw_page'] ?? '');
            if (!empty($_POST['raw_message'])) $d['leads'][$k]['follow_ups'][] = ['owner' => $_SESSION['admin_user'] ?? 'system', 'time' => date('Y-m-d H:i'), 'content' => '表单留言：' . trim($_POST['raw_message'])];
            crm_save($d);
        }
        flash('success', '已转为跟进线索');
        header('Location: /xmp/crm?tab=pipeline&focus=' . urlencode($rEmail ?: $rPhone)); exit;
    } elseif ($action === 'import' && !empty($_FILES['csv_file']['tmp_name'])) {
        $imported = 0; $skipped = 0; $duplicated = 0;
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
                // 查重：已有同邮箱/手机/公司的线索则跳过（防撞单）
                $conflicts = crm_find_duplicate($email2, $phone2, '');
                if (!empty($conflicts)) { $duplicated++; continue; }
                $lead2 = crm_ensure_lead($email2 ?: $phone2, $name2, $phone2);
                if (!empty($company2)) $lead2['company'] = $company2;
                $lead2['source'] = 'import';
                $lead2['updated_at'] = date('Y-m-d H:i:s');
                crm_update_lead($email2 ?: $phone2, ['company'=>$company2, 'source'=>'import']);
                $imported++;
            }
            fclose($handle);
        }
        flash('success', "导入完成：新增 {$imported} 条，跳过 {$skipped} 条（缺联系），跳过 {$duplicated} 条（重复/撞单）");
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
.crm-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
@media(max-width:840px){.crm-kpis{grid-template-columns:1fr}.crm-f label{flex:1 1 140px}.crm-f input[type=number],.crm-f input[type=date]{width:100%}}
.crm-kpis .pipe-card{text-align:left;padding:14px 18px}
.crm-kpis .lab .hint{font-weight:400;color:var(--faint);font-size:11px}
.crm-stages{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.crm-stage{--c:var(--fg);display:flex;align-items:center;gap:9px;padding:7px 12px 7px 10px;border-radius:12px;border:1px solid var(--border);background:var(--surface);text-decoration:none;color:var(--fg);transition:border-color .15s,background .15s}
.crm-stage:hover{border-color:var(--border-strong);background:var(--hover)}
.crm-stage.on{border-color:var(--c);box-shadow:inset 0 0 0 1px var(--c)}
.crm-stage .n{font-family:var(--font-mono);font-weight:800;font-size:16px;color:var(--c);min-width:18px}
.crm-stage .l{display:flex;flex-direction:column;font-size:12.5px;font-weight:600;line-height:1.2}
.crm-stage .l em{font-style:normal;font-weight:400;font-size:10.5px;color:var(--faint)}
.crm-detail-h{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 8px}
.crm-detail-h h2{margin:0;font-size:16px}
.crm-sec-h{font-size:12px;font-weight:800;letter-spacing:.04em;color:var(--muted);margin:14px 0 8px;text-transform:uppercase}
.crm-f{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px}
.crm-f label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;color:var(--faint);font-weight:600;min-width:0}
.crm-f input,.crm-f select{height:34px;padding:0 10px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--surface);color:var(--fg);min-width:0;width:100%}
.crm-f input[type=number]{width:110px}.crm-f input[type=date]{width:140px}
.crm-f .btn{height:34px}
.crm-fold{border:1px solid var(--border);border-radius:12px;padding:0 12px;margin:4px 0 10px;background:var(--surface-strong)}
.crm-fold summary{list-style:none;cursor:pointer;padding:10px 0;font-size:12.5px;font-weight:700;display:flex;gap:8px;align-items:baseline}
.crm-fold summary::-webkit-details-marker{display:none}
.crm-fold summary em{font-style:normal;font-weight:400;color:var(--faint);font-size:11.5px}
.crm-fold .crm-f{padding-bottom:6px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('crm'); ?>
  <div class="main">
    <?php
    // 待办任务提醒（跟进任务，逾期高亮）
    $crmTasks = array_values(array_filter((array)json_read(DATA_DIR . '/tasks.json'), fn($t) => ($t['type'] ?? '') === 'crm_followup' && ($t['status'] ?? '') !== 'done'));
    $overdue = array_values(array_filter($crmTasks, fn($t) => !empty($t['due']) && $t['due'] < date('Y-m-d')));
    if (!empty($crmTasks)):
    ?>
    <div style="padding:12px 16px;border-radius:12px;background:<?=$overdue?'var(--warn-soft)':'var(--surface)'?>;border:1px solid <?=$overdue?'var(--warn)':'var(--border)'?>;margin-bottom:16px;font-size:13px">
      <b>跟进待办（<?=count($crmTasks)?>）</b><?=count($overdue)>0?' · <span style="color:var(--danger);font-weight:700">'.$overdue[0]['due'].' 起 '.count($overdue).' 个逾期</span>':''?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
        <?php foreach (array_slice($crmTasks, 0, 6) as $t): $m = $data['leads'][$t['email']]['name'] ?? $t['email']; $isOverdue = !empty($t['due']) && $t['due'] < date('Y-m-d'); ?>
        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:<?=$isOverdue?'var(--danger-soft)':'var(--accent-soft)'?>;color:<?=$isOverdue?'var(--danger)':'var(--accent)'?>;font-size:12px">
          <?=htmlspecialchars(mb_substr($t['title'] ?? '', 0, 18))?> · <?=htmlspecialchars($m)?><?=$t['due']?' · '.substr($t['due'],5):''?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="complete_task"><input type="hidden" name="task_id" value="<?=htmlspecialchars($t['id'])?>"><button style="background:none;border:none;color:inherit;cursor:pointer;font-size:11px">✓</button></form>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">CRM 线索</h1>
      <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
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
      <a href="?tab=pipeline" class="<?=$tab==='pipeline'?'active':''?>">销售管线</a>
      <a href="?tab=kanban" class="<?=$tab==='kanban'?'active':''?>">看板</a>
      <a href="?tab=pool" class="<?=$tab==='pool'?'active':''?>">公海 (<?=$poolCount?>)</a>
      <a href="?tab=raw" class="<?=$tab==='raw'?'active':''?>">原始提交 (<?=count($rawLeads)?>)</a>
      <a href="?tab=customers" class="<?=$tab==='customers'?'active':''?>">客户 (<?=count(crm_get_customers())?>)</a>
      <a href="?tab=arr" class="<?=$tab==='arr'?'active':''?>">ARR 报表</a>
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
            <div class="email"><?=htmlspecialchars(privacy_mask_email($l['email']))?></div>
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
    <?php
      // CSV 表头中文 → 字段
      $__col = function (array $r, array $names) { foreach ($names as $n) if (isset($r[$n]) && $r[$n] !== '') return $r[$n]; return ''; };
      $__known = array_map(fn($l) => mb_strtolower($l['email'] ?? ''), $data['leads'] ?? []);
    ?>
    <div class="card lst-card">
      <?php if (empty($rawLeads)): ?>
        <div class="of-empty" style="border:0;margin:0;padding:40px">还没有原始提交。前台任何表单提交都会先落到这里，再由你决定要不要转成跟进线索。</div>
      <?php else: ?>
      <table class="lst-table">
        <thead><tr><th style="width:130px">时间</th><th class="c-title">提交人</th><th style="width:220px">联系方式</th><th style="width:160px">来源页面</th><th style="width:26%">留言</th><th class="c-act" style="width:120px"></th></tr></thead>
        <tbody>
          <?php foreach ($rawLeads as $rl):
            $rlEmail = mb_strtolower(trim($__col($rl, ['邮箱', 'email', 'Email'])));
            $rlPhone = trim($__col($rl, ['电话', 'phone', '手机']));
            $rlName  = $__col($rl, ['姓名', 'name']); $rlCo = $__col($rl, ['公司', 'company']); $rlJob = $__col($rl, ['职位', 'job', 'title']);
            $rlPage  = $__col($rl, ['来源页面', 'page', 'source']); $rlMsg = $__col($rl, ['需求留言', 'message', '留言']); $rlTime = $__col($rl, ['时间', 'time', 'created_at']);
            $claimed = $rlEmail !== '' && in_array($rlEmail, $__known, true);
          ?>
          <tr>
            <td class="lst-when" style="font-size:12px"><?=htmlspecialchars(substr($rlTime, 0, 16))?></td>
            <td class="c-title"><div class="lst-title"><?=htmlspecialchars($rlName ?: '（未留姓名）')?></div><?php if ($rlCo || $rlJob): ?><div class="lst-sub"><span class="text-sm text-muted"><?=htmlspecialchars(trim($rlCo . ' · ' . $rlJob, ' ·'))?></span></div><?php endif; ?></td>
            <td style="font-size:12.5px;overflow:hidden;text-overflow:ellipsis"><?php if ($rlEmail): ?><div><?=htmlspecialchars($rlEmail)?></div><?php endif; if ($rlPhone): ?><div class="text-muted"><?=htmlspecialchars($rlPhone)?></div><?php endif; if (!$rlEmail && !$rlPhone): ?><span class="text-muted">—</span><?php endif; ?></td>
            <td class="lst-slug" style="overflow:hidden;text-overflow:ellipsis" title="<?=htmlspecialchars($rlPage)?>"><?=htmlspecialchars($rlPage ?: '—')?></td>
            <td style="font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($rlMsg)?>"><?=htmlspecialchars($rlMsg ?: '—')?></td>
            <td class="c-act">
              <?php if ($claimed): ?>
                <a href="?tab=pipeline&focus=<?=urlencode($rlEmail)?>" class="btn btn-ghost btn-sm">已在跟进 →</a>
              <?php elseif ($rlEmail || $rlPhone): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="claim_raw">
                  <input type="hidden" name="raw_email" value="<?=htmlspecialchars($rlEmail)?>"><input type="hidden" name="raw_phone" value="<?=htmlspecialchars($rlPhone)?>">
                  <input type="hidden" name="raw_name" value="<?=htmlspecialchars($rlName)?>"><input type="hidden" name="raw_company" value="<?=htmlspecialchars($rlCo)?>">
                  <input type="hidden" name="raw_page" value="<?=htmlspecialchars($rlPage)?>"><input type="hidden" name="raw_message" value="<?=htmlspecialchars($rlMsg)?>">
                  <button type="submit" class="btn btn-primary btn-sm">转跟进</button>
                </form>
              <?php else: ?><span class="text-muted" style="font-size:12px">无联系方式</span><?php endif; ?>
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
              <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars(privacy_mask_email($l['email']))?></div>
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
            <td><strong><a href="customer-detail.php?c=<?=urlencode($c['id'])?>" style="color:var(--accent);text-decoration:none"><?=htmlspecialchars($c['name'] ?: '—')?></a></strong><div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($c['company'] ?: $c['email'])?></div></td>
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
    <?php $pipelineW = crm_pipeline_weighted(); $forecast = crm_forecast(); $rates = crm_stage_win_rates(); ?>
    <div class="crm-kpis">
      <div class="pipe-card"><div class="lab">管线总额</div><div class="num" style="color:var(--ok)">¥<?=number_format($pipelineValue,0)?></div><div class="lab"><?=count($data['leads'] ?? [])?> 条跟进中的线索</div></div>
      <div class="pipe-card"><div class="lab">加权金额 <span class="hint">· 按阶段赢率折算</span></div><div class="num" style="color:var(--accent)">¥<?=number_format($pipelineW['weighted'],0)?></div></div>
      <div class="pipe-card"><div class="lab">销售预测</div><div class="num" style="color:var(--warn)">¥<?=number_format($forecast['weighted'],0)?></div><div class="lab"><?=$forecast['opportunities']?> 个商机</div></div>
    </div>

    <?php if (!empty($forecast['by_month'])): ?>
    <div class="card" style="padding:16px;margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;margin-bottom:10px">销售预测（按预计成交月 · 加权金额）</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php $maxF = max($forecast['by_month']) ?: 1; foreach ($forecast['by_month'] as $m => $amt): ?>
        <div style="flex:1;min-width:100px">
          <div style="font-size:11px;color:var(--faint)"><?=htmlspecialchars($m)?></div>
          <div style="height:36px;border-radius:6px;background:var(--hover);margin:4px 0;overflow:hidden"><div style="height:100%;width:<?=round($amt/$maxF*100)?>%;background:var(--accent);border-radius:6px"></div></div>
          <div style="font-size:12px;font-weight:700;color:var(--accent)">¥<?=number_format($amt,0)?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 阶段筛选 -->
    <div class="crm-stages" role="tablist" aria-label="按阶段筛选">
      <a href="crm.php" class="crm-stage <?=!$stageFilter?'on':''?>"><span class="n"><?=count($data['leads'] ?? [])?></span><span class="l">全部</span></a>
      <?php $__sc=['new'=>'var(--faint)','contacted'=>'var(--accent)','qualified'=>'var(--warn)','opportunity'=>'var(--accent)','won'=>'var(--ok)','lost'=>'var(--danger)']; foreach ($stages as $k => $label): ?>
      <a href="?stage=<?=$k?>" class="crm-stage <?=$stageFilter===$k?'on':''?>" style="--c:<?=$__sc[$k]?>"><span class="n"><?=$stageCounts[$k] ?? 0?></span><span class="l"><?=htmlspecialchars($label)?><em>赢率 <?=round($rates[$k]*100)?>%</em></span></a>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:<?=$focusLead?'1.3fr 1fr':'1fr'?>;gap:20px" class="crm-grid">
      <!-- 线索列表 -->
      <div class="card lst-card">
        <table class="lst-table">
          <thead><tr><th class="c-title">线索</th><th style="width:96px">阶段</th><th style="width:70px">评分</th><th style="width:110px">跟进人</th><th style="width:100px">商机</th><th class="c-act" style="width:80px"></th></tr></thead>
          <tbody>
            <?php if (empty($leads)): ?><tr><td colspan="6"><div class="of-empty" style="border:0;margin:0">暂无线索。网站表单提交会自动进来；也可以右上角「导入 CSV」，或从<a href="segments.php">用户分群</a>导入。</div></td></tr><?php endif; ?>
            <?php foreach ($leads as $l): ?>
            <tr>
              <td>
                <strong><?=htmlspecialchars($l['name'] ?: '—')?></strong>
                <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars(privacy_mask_email($l['email']))?></div>
              </td>
              <td><span class="stage-pill" style="background:<?=['new'=>'#f4f3e9','contacted'=>'#dbeafe','qualified'=>'#fef3c7','opportunity'=>'#ede9fe','won'=>'#dcfce7','lost'=>'#fee2e2'][$l['stage']]?>;color:<?=['new'=>'#6b6580','contacted'=>'#1d4ed8','qualified'=>'#92400e','opportunity'=>'#5b21b6','won'=>'#166534','lost'=>'#991b1b'][$l['stage']]?>"><?=htmlspecialchars($stages[$l['stage']] ?? $l['stage'])?></span></td>
              <td><b style="color:<?=$l['score']>=60?'var(--ok)':($l['score']>=30?'var(--warn)':'var(--danger)')?>"><?=$l['score']?></b></td>
              <td class="text-sm text-muted"><?=htmlspecialchars($adminNames[$l['owner']] ?? $l['owner'] ?: '—')?></td>
              <td><?=$l['value'] ? '¥'.number_format($l['value'],0) : '—'?></td>
              <td class="c-act"><a href="?focus=<?=urlencode($l['email'])?><?=$stageFilter?'&stage='.$stageFilter:''?>" class="btn btn-ghost btn-sm">详情</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 详情 -->
      <?php if ($focusLead): ?>
      <div class="card crm-detail" style="padding:0;overflow:auto">
        <div class="crm-detail-h"><h2>线索详情</h2><a href="crm.php<?=$stageFilter?'?stage='.$stageFilter:''?>" class="ib" title="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></a></div>
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

          <!-- 阶段更新 + 商机 -->
          <div class="crm-sec-h">阶段与商机</div>
          <form method="post" class="crm-f">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="update_stage">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <label><span>阶段</span><select name="stage">
              <?php foreach ($stages as $k => $label): ?>
              <option value="<?=$k?>" <?=$focusLead['stage']===$k?'selected':''?>><?=htmlspecialchars($label)?></option>
              <?php endforeach; ?>
            </select></label>
            <label><span>商机金额 ¥</span><input type="number" name="value" value="<?=htmlspecialchars($focusLead['value'] ?? 0)?>" step="100"></label>
            <label><span>预计成交</span><input type="date" name="expected_close" value="<?=htmlspecialchars($focusLead['expected_close'] ?? '')?>"></label>
            <button type="submit" class="btn btn-primary btn-sm">更新</button>
          </form>

          <!-- 转为客户（商机 → 客户） -->
          <details class="crm-fold" <?=in_array($focusLead['stage'], ['opportunity','won'])?'open':''?>>
            <summary>转为客户 <em>成交后填年化金额，计入 ARR</em></summary>
            <form method="post" class="crm-f">
              <?= csrf_field() ?>
              <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
              <input type="hidden" name="action" value="to_customer">
              <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
              <label><span>年化 ARR ¥</span><input type="number" name="arr" placeholder="如 36000" step="100"></label>
              <label><span>方案</span><select name="plan_type">
                <option value="saas">SaaS 订阅</option><option value="private">私有化部署</option><option value="custom">定制开发</option>
              </select></label>
              <label><span>合同到期</span><input type="date" name="contract_end"></label>
              <button type="submit" class="btn btn-primary btn-sm" data-confirm="把这条线索转为客户？会从管线移出并计入 ARR。">转客户</button>
            </form>
          </details>

          <!-- 交接 -->
          <div class="crm-sec-h">归属与评分</div>
          <form method="post" class="crm-f">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="transfer">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <label style="flex:1"><span>跟进人</span><select name="owner">
              <?php foreach ($adminNames as $uk => $un): ?>
              <option value="<?=htmlspecialchars($uk)?>" <?=$focusLead['owner']===$uk?'selected':''?>><?=htmlspecialchars($un)?></option>
              <?php endforeach; ?>
            </select></label>
            <button type="submit" class="btn btn-ghost btn-sm">交接</button>
          </form>

          <!-- 手动加分 -->
          <form method="post" class="crm-f">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="score">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <label><span>手动加减分</span><input type="number" name="delta" value="10" step="5"></label>
            <button type="submit" class="btn btn-ghost btn-sm">调整</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="aiScoreLead()" style="margin-left:auto">AI 评分建议</button>
          </form>
          <div id="aiScoreBox" style="margin:-4px 0 12px;display:none"></div>

          <!-- 跟进记录 -->
          <div class="crm-sec-h">跟进</div>
          <form method="post" class="crm-f">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="add_followup">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <label style="flex:1"><span>跟进记录</span><input type="text" name="content" placeholder="今天聊了什么、下一步是什么"></label>
            <button type="submit" class="btn btn-primary btn-sm">记一条</button>
          </form>
          <form method="post" class="crm-f">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?=htmlspecialchars($focusLead['email'])?>">
            <input type="hidden" name="action" value="add_task">
            <input type="hidden" name="focus" value="<?=htmlspecialchars($focusLead['email'])?>">
            <label style="flex:1"><span>待办</span><input type="text" name="title" placeholder="如：本周内电话回访"></label>
            <label><span>截止</span><input type="date" name="due"></label>
            <button type="submit" class="btn btn-ghost btn-sm">设任务</button>
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
