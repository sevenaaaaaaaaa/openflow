<?php
/**
 * 画布流程编辑器 — 可视化流程编排
 * 轻量实现：横向节点流 + 分支，拖拽排序，无需 SVG 连线
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CanvasSystem.php';
require_login();
require_perm('settings');

$flows = canvas_get();
$forms = json_read(DATA_DIR . '/forms/index.json');
$message = '';

// 保存流程
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $nodes = [];
    foreach (($_POST['node_type'] ?? []) as $i => $nt) {
        if (empty($nt)) continue;
        $node = ['id' => 'n' . $i, 'type' => $nt];
        switch ($nt) {
            case 'trigger':
                $node['trigger'] = $_POST['node_trigger'][$i] ?? 'form_submit';
                $node['form_slug'] = $_POST['node_form'][$i] ?? '';
                $node['threshold'] = (int)($_POST['node_threshold'][$i] ?? 7);
                break;
            case 'send_email':
                $node['subject'] = $_POST['node_subject'][$i] ?? '';
                $node['content'] = $_POST['node_content'][$i] ?? '';
                break;
            case 'condition':
                $node['field'] = $_POST['node_field'][$i] ?? 'email';
                $node['op'] = $_POST['node_op'][$i] ?? 'eq';
                $node['value'] = $_POST['node_value'][$i] ?? '';
                $node['true_next'] = ($_POST['node_true_next'][$i] ?? '') !== '' ? (int)$_POST['node_true_next'][$i] : null;
                $node['false_next'] = ($_POST['node_false_next'][$i] ?? '') !== '' ? (int)$_POST['node_false_next'][$i] : null;
                break;
            case 'delay':
                $node['delay_minutes'] = (int)($_POST['node_delay'][$i] ?? 60);
                break;
            case 'notify':
                $node['title'] = $_POST['node_title'][$i] ?? '';
                break;
        }
        $nodes[] = $node;
    }
    // 自动生成边（顺序 + 条件分支）
    $edges = [];
    for ($i = 0; $i < count($nodes) - 1; $i++) {
        $node = $nodes[$i];
        // 条件节点：走真/假两条分支边，不生成顺序边
        if (($node['type'] ?? '') === 'condition') {
            $trueNext = ($node['true_next'] ?? null);
            $falseNext = ($node['false_next'] ?? null);
            $hasBranch = ($trueNext !== null || $falseNext !== null);
            if (!$hasBranch) { $edges[] = ['from' => $node['id'], 'to' => $nodes[$i+1]['id']]; continue; }
            if ($trueNext !== null && isset($nodes[$trueNext])) $edges[] = ['from' => $node['id'], 'to' => $nodes[$trueNext]['id'], 'condition' => 'true'];
            elseif ($trueNext === null) $edges[] = ['from' => $node['id'], 'to' => $nodes[$i+1]['id'], 'condition' => 'true'];
            if ($falseNext !== null && isset($nodes[$falseNext])) $edges[] = ['from' => $node['id'], 'to' => $nodes[$falseNext]['id'], 'condition' => 'false'];
            continue;
        }
        $edges[] = ['from' => $node['id'], 'to' => $nodes[$i+1]['id']];
    }
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'nodes' => $nodes,
        'edges' => $edges,
        'enabled' => isset($_POST['enabled']),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($id)) {
        $data['id'] = 'canvas_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $data['created_at'] = date('Y-m-d H:i:s');
        $flows[] = $data;
    } else {
        foreach ($flows as &$f) if ($f['id'] === $id) { $f = array_merge($f, $data); break; }
        unset($f);
    }
    canvas_save($flows);
    flash('success', '画布流程已保存');
    header('Location: /xmp/canvas');
    exit;
}

if (isset($_GET['delete'])) {
    $flows = array_values(array_filter($flows, fn($f) => $f['id'] !== $_GET['delete']));
    canvas_save($flows);
    flash('success', '流程已删除');
    header('Location: /xmp/canvas');
    exit;
}
if (isset($_GET['toggle'])) {
    foreach ($flows as &$f) if ($f['id'] === $_GET['toggle']) $f['enabled'] = !($f['enabled'] ?? false);
    unset($f);
    canvas_save($flows);
    header('Location: /xmp/canvas');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $edit = ['id' => '', 'name' => '', 'nodes' => [], 'enabled' => false];
    } else {
        foreach ($flows as $f) if ($f['id'] === $_GET['edit']) { $edit = $f; break; }
    }
}

admin_header('画布编辑器');
?>
<style>
.canvas-flow{display:flex;gap:12px;overflow-x:auto;padding:24px 8px;align-items:flex-start}
.canvas-node{min-width:220px;max-width:260px;background:var(--surface);border:2px solid var(--border);border-radius:14px;padding:14px;position:relative;flex-shrink:0}
.canvas-node.trigger{border-color:var(--ok)}
.canvas-node.send_email{border-color:var(--accent)}
.canvas-node.condition{border-color:var(--warn)}
.canvas-node.delay{border-color:var(--accent)}
.canvas-node.notify{border-color:var(--faint)}
.canvas-node .node-head{font-size:12px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.canvas-node .node-body input,.canvas-node .node-body select{width:100%;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;margin-bottom:6px;box-sizing:border-box}
.canvas-node .node-body textarea{width:100%;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;box-sizing:border-box}
.canvas-arrow{display:flex;align-items:center;color:var(--text-3);font-size:20px;padding-top:30px;flex-shrink:0}
.canvas-node .del{position:absolute;top:6px;right:8px;background:none;border:none;color:var(--text-3);cursor:pointer;font-size:14px}
.canvas-node.dragging{opacity:.5;border-style:dashed}
</style>
<div class="admin-layout">
  <?php admin_sidebar('canvas'); ?>
  <div class="main">
    <h1> 画布编辑器</h1>
    <p class="sub">可视化流程编排 · 拖拽节点构建营销自动化流程</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="flex items-center gap-4 mb-4">
      <h2 style="margin-bottom:0">画布流程</h2>
      <a href="canvas.php?edit=new" class="btn btn-primary btn-sm ml-auto">➕ 新建画布</a>
    </div>

    <?php if ($edit): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'] ?? '')?>">
      <div class="card">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <input type="text" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" placeholder="流程名称" required style="flex:1;min-width:200px;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px"><input type="checkbox" name="enabled" value="1" <?=($edit['enabled']??false)?'checked':''?> style="width:16px;height:16px"> 启用</label>
        </div>
      </div>

      <div class="card">
        <h2>🔄 流程画布</h2>
        <p class="text-sm text-muted mb-4">点击"添加节点"构建流程 · 第一个节点必须是触发器 · 拖拽节点调整顺序</p>
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
          <button type="button" class="btn btn-ghost btn-sm" onclick="addNode('trigger')">🔔 触发器</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addNode('send_email')">📧 发送邮件</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addNode('condition')">🔀 条件分支</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addNode('delay')">⏱ 延迟</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addNode('notify')">🔔 通知</button>
        </div>
        <div class="canvas-flow" id="canvasFlow">
          <?php $editNodes = $edit['nodes'] ?? []; foreach ($editNodes as $ni => $n): ?>
          <?php canvas_render_node($n, $ni, $forms); ?>
          <?php if ($ni < count($editNodes) - 1): ?><div class="canvas-arrow">→</div><?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:16px"><button type="submit" name="save" class="btn btn-primary">保存画布</button>
        <a href="canvas.php" class="btn btn-ghost">取消</a></div>
      </div>
    </form>

    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>流程</th><th>节点数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($flows)): ?><tr><td colspan="4" class="empty">暂无画布流程</td></tr><?php endif; ?>
          <?php foreach ($flows as $f): ?>
          <tr>
            <td><strong><?=htmlspecialchars($f['name'])?></strong></td>
            <td><?=count($f['nodes'] ?? [])?></td>
            <td><span class="badge <?=($f['enabled']??false)?'badge-green':'badge-gray'?>"><?=($f['enabled']??false)?'🟢 运行中':'⏸ 已停'?></span></td>
            <td style="white-space:nowrap">
              <a href="?toggle=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm"><?=($f['enabled']??false)?'停用':'启用'?></a>
              <a href="canvas.php?edit=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="?delete=<?=urlencode($f['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
// 节点渲染函数
function canvas_render_node(array $n, int $i, array $forms): void {
    $type = $n['type'] ?? 'trigger';
    $icons = ['trigger'=>'🔔','send_email'=>'📧','condition'=>'🔀','delay'=>'⏱','notify'=>'📢'];
    $labels = ['trigger'=>'触发器','send_email'=>'发送邮件','condition'=>'条件分支','delay'=>'延迟','notify'=>'通知'];
    echo '<div class="canvas-node ' . $type . '" draggable="true" ondragstart="nodeDragStart(event)" ondragover="event.preventDefault()" ondrop="nodeDrop(event)">';
    echo '<button type="button" class="del" onclick="this.closest(\'.canvas-node\').remove()">✕</button>';
    echo '<div class="node-head">' . $icons[$type] . ' ' . $labels[$type] . '</div>';
    echo '<div class="node-body">';
    echo '<input type="hidden" name="node_type[]" value="' . $type . '">';
    switch ($type) {
        case 'trigger':
            echo '<select name="node_trigger[]"><option value="form_submit" ' . ($n['trigger']=='form_submit'?'selected':'') . '>表单提交</option><option value="member_register" ' . ($n['trigger']=='member_register'?'selected':'') . '>用户注册</option><option value="nps_submit" ' . ($n['trigger']=='nps_submit'?'selected':'') . '>NPS评分</option></select>';
            echo '<select name="node_form[]"><option value="">全部表单</option>';
            foreach ($forms as $f) echo '<option value="' . htmlspecialchars($f['slug']) . '" ' . (($n['form_slug']??'')===$f['slug']?'selected':'') . '>' . htmlspecialchars($f['title']) . '</option>';
            echo '</select>';
            echo '<label style="font-size:11px;color:var(--text-3)">NPS阈值</label><input type="number" name="node_threshold[]" value="' . htmlspecialchars($n['threshold']??7) . '">';
            break;
        case 'send_email':
            echo '<input type="text" name="node_subject[]" value="' . htmlspecialchars($n['subject']??'') . '" placeholder="邮件主题">';
            echo '<textarea name="node_content[]" rows="3" placeholder="内容 {name} {email}">' . htmlspecialchars($n['content']??'') . '</textarea>';
            break;
        case 'condition':
            // 字段下拉：分组渲染，数据源为 canvas_condition_fields()（加字段只改那一处）
            $curField = $n['field'] ?? '';
            echo '<select name="node_field[]">';
            foreach (canvas_condition_fields() as $group => $fields) {
                echo '<optgroup label="' . htmlspecialchars($group) . '">';
                foreach ($fields as $fk => $flabel) {
                    echo '<option value="' . htmlspecialchars($fk) . '"' . ($curField === $fk ? ' selected' : '') . '>'
                       . htmlspecialchars($flabel) . '</option>';
                }
                echo '</optgroup>';
            }
            echo '</select>';
            $curOp = $n['op'] ?? '';
            $ops = ['eq'=>'等于','neq'=>'不等于','gt'=>'大于','gte'=>'大于等于','lt'=>'小于','lte'=>'小于等于',
                    'contains'=>'包含','in'=>'属于（逗号分隔）','empty'=>'为空','not_empty'=>'不为空'];
            echo '<select name="node_op[]">';
            foreach ($ops as $ok => $olabel) {
                echo '<option value="' . $ok . '"' . ($curOp === $ok ? ' selected' : '') . '>' . $olabel . '</option>';
            }
            echo '</select>';
            echo '<input type="text" name="node_value[]" value="' . htmlspecialchars($n['value']??'') . '" placeholder="条件值">';
            echo '<div style="display:flex;gap:6px;align-items:center;margin-top:6px;font-size:11px;color:var(--text-3)">条件为真 → 跳第 <input type="number" name="node_true_next[]" value="' . htmlspecialchars(($n['true_next'] ?? '') !== null ? $n['true_next'] : '') . '" placeholder="空=下一步" style="width:52px;padding:4px;border:1px solid var(--border);border-radius:6px"> 步 / 为假 → 跳第 <input type="number" name="node_false_next[]" value="' . htmlspecialchars(($n['false_next'] ?? '') !== null ? $n['false_next'] : '') . '" placeholder="空=不执行" style="width:52px;padding:4px;border:1px solid var(--border);border-radius:6px"> 步（节点从 0 数）</div>';
            break;
        case 'delay':
            echo '<label style="font-size:11px;color:var(--text-3)">延迟分钟</label><input type="number" name="node_delay[]" value="' . htmlspecialchars($n['delay_minutes']??60) . '">';
            break;
        case 'notify':
            echo '<input type="text" name="node_title[]" value="' . htmlspecialchars($n['title']??'') . '" placeholder="通知标题">';
            break;
    }
    echo '</div></div>';
}
?>
<script>
var FORMS = <?=json_encode(array_map(fn($f)=>(['slug'=>$f['slug'],'title'=>$f['title']]), $forms), JSON_UNESCAPED_UNICODE)?>;
var dragNode = null;
function addNode(type) {
  var flow = document.getElementById('canvasFlow');
  var idx = flow.querySelectorAll('.canvas-node').length;
  var d = document.createElement('div');
  d.className = 'canvas-node ' + type;
  d.setAttribute('draggable', 'true');
  d.ondragstart = nodeDragStart; d.ondragover = function(e){e.preventDefault();}; d.ondrop = nodeDrop;
  var icons = {trigger:'🔔',send_email:'📧',condition:'🔀',delay:'⏱',notify:'📢'};
  var labels = {trigger:'触发器',send_email:'发送邮件',condition:'条件分支',delay:'延迟',notify:'通知'};
  var body = '<input type="hidden" name="node_type[]" value="' + type + '">';
  if (type === 'trigger') {
    body += '<select name="node_trigger[]"><option value="form_submit">表单提交</option><option value="member_register">用户注册</option><option value="nps_submit">NPS评分</option></select>';
    body += '<select name="node_form[]"><option value="">全部表单</option>' + FORMS.map(function(f){return '<option value="' + f.slug + '">' + f.title + '</option>';}).join('') + '</select>';
    body += '<label style="font-size:11px;color:var(--text-3)">NPS阈值</label><input type="number" name="node_threshold[]" value="7">';
  } else if (type === 'send_email') {
    body += '<input type="text" name="node_subject[]" placeholder="邮件主题"><textarea name="node_content[]" rows="3" placeholder="内容 {name} {email}"></textarea>';
  } else if (type === 'condition') {
    body += '<select name="node_field[]"><option value="email">email</option><option value="form_type">form_type</option><option value="score">score</option></select><select name="node_op[]"><option value="eq">等于</option><option value="neq">不等于</option><option value="gt">大于</option><option value="lt">小于</option><option value="contains">包含</option><option value="empty">为空</option></select><input type="text" name="node_value[]" placeholder="条件值">';
    body += '<div style="display:flex;gap:6px;align-items:center;margin-top:6px;font-size:11px;color:var(--text-3)">为真→跳第<input type="number" name="node_true_next[]" placeholder="空=下一步" style="width:52px;padding:4px;border:1px solid var(--border);border-radius:6px">步 / 为假→跳第<input type="number" name="node_false_next[]" placeholder="空=不执行" style="width:52px;padding:4px;border:1px solid var(--border);border-radius:6px">步（节点从0数）</div>';
  } else if (type === 'delay') {
    body += '<label style="font-size:11px;color:var(--text-3)">延迟分钟</label><input type="number" name="node_delay[]" value="60">';
  } else if (type === 'notify') {
    body += '<input type="text" name="node_title[]" placeholder="通知标题">';
  }
  d.innerHTML = '<button type="button" class="del" onclick="this.closest(\'.canvas-node\').remove()">✕</button><div class="node-head">' + icons[type] + ' ' + labels[type] + '</div><div class="node-body">' + body + '</div>';
  // 加箭头
  var arrow = document.createElement('div');
  arrow.className = 'canvas-arrow';
  arrow.textContent = '→';
  flow.appendChild(arrow);
  flow.appendChild(d);
}
function nodeDragStart(e) { dragNode = e.target.closest('.canvas-node'); if (dragNode) dragNode.classList.add('dragging'); }
function nodeDrop(e) {
  if (!dragNode) return;
  var target = e.target.closest('.canvas-node');
  var flow = document.getElementById('canvasFlow');
  if (target && target !== dragNode) {
    flow.insertBefore(dragNode, target.nextSibling);
  }
  dragNode.classList.remove('dragging');
  dragNode = null;
}
</script>
<?php admin_footer(); ?>
