<?php
/**
 * 营销自动化 — 流程编排（触发 → 动作）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_login();
require_perm('settings');

$flows = automation_get();
$forms = json_read(DATA_DIR . '/forms/index.json');
$message = '';

// 保存流程
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $steps = [];
    foreach (($_POST['step_action'] ?? []) as $i => $sa) {
        if (empty($sa)) continue;
        $steps[] = [
            'action' => $sa,
            'subject' => $_POST['step_subject'][$i] ?? '',
            'content' => $_POST['step_content'][$i] ?? '',
            'mautic_email_id' => $_POST['step_mail_id'][$i] ?? '',
            'delay_minutes' => (int)($_POST['step_delay'][$i] ?? 60),
            'title' => $_POST['step_title'][$i] ?? '',
            'link' => $_POST['step_link'][$i] ?? '',
        ];
    }
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'trigger' => $_POST['trigger'] ?? 'form_submit',
        'trigger_type' => $_POST['trigger_type'] ?? 'all',
        'form_slug' => $_POST['form_slug'] ?? '',
        'nps_threshold' => (int)($_POST['nps_threshold'] ?? 7),
        'match_field' => trim($_POST['match_field'] ?? ''),
        'match_value' => trim($_POST['match_value'] ?? ''),
        'match_props' => trim($_POST['match_props'] ?? ''),
        'steps' => $steps,
        'enabled' => isset($_POST['enabled']),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($id)) {
        $data['id'] = 'flow_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $data['created_at'] = date('Y-m-d H:i:s');
        $flows[] = $data;
    } else {
        foreach ($flows as &$f) if ($f['id'] === $id) { $f = array_merge($f, $data); break; }
        unset($f);
    }
    automation_save($flows);
    flash('success', '自动化流程已保存');
    header('Location: automation.php');
    exit;
}

// 删除/切换
if (isset($_GET['delete'])) {
    $flows = array_values(array_filter($flows, fn($f) => $f['id'] !== $_GET['delete']));
    automation_save($flows);
    flash('success', '流程已删除');
    header('Location: automation.php');
    exit;
}
if (isset($_GET['toggle'])) {
    foreach ($flows as &$f) if ($f['id'] === $_GET['toggle']) $f['enabled'] = !($f['enabled'] ?? false);
    unset($f);
    automation_save($flows);
    header('Location: automation.php');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $edit = ['id' => '', 'name' => '', 'trigger' => 'form_submit', 'form_slug' => '', 'nps_threshold' => 0, 'steps' => [], 'enabled' => false];
    } else {
        foreach ($flows as $f) if ($f['id'] === $_GET['edit']) { $edit = $f; break; }
    }
}

$log = array_reverse(json_read(automation_log_file()));
$triggerLabels = ['form_submit'=>'表单提交','member_register'=>'用户注册','nps_submit'=>'NPS 评分','page_view'=>'页面访问','article_view'=>'文章浏览','element_click'=>'元素点击','download'=>'资料下载','purchase'=>'购买成功','course_complete'=>'课程学完','course_enroll'=>'课程报名','lesson_complete'=>'完成课时','role_selected'=>'选择角色','tool_use'=>'使用工具'];
$behavList = ['page_view','article_view','element_click','download','purchase','course_complete','course_enroll','lesson_complete','role_selected','tool_use'];

admin_header('营销自动化');
?>
<div class="admin-layout">
  <?php admin_sidebar('automation'); ?>
  <div class="main">
    <h1>⚡ 营销自动化</h1>
    <p class="sub">自动化邮件流程 · 触发条件 → 动作（发邮件/通知/延迟）</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="flex items-center gap-4 mb-4">
      <h2 style="margin-bottom:0">自动化流程</h2>
      <a href="automation.php?edit=new" class="btn btn-primary btn-sm ml-auto">➕ 新建流程</a>
    </div>

    <?php if ($edit): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'] ?? '')?>">
      <div class="card">
        <h2><?=empty($edit['id'])?'新建自动化流程':'编辑：'.htmlspecialchars($edit['name'])?></h2>
        <div class="field-row">
          <div class="field"><label>流程名称 <span class="hint">· 必填</span></label><input type="text" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required placeholder="如：新线索欢迎邮件"></div>
          <div class="field"><label>启用</label><label style="display:flex;align-items:center;gap:6px;margin-top:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=($edit['enabled']??false)?'checked':''?> style="width:16px;height:16px"> 启用此流程</label></div>
        </div>

        <h3 style="font-size:15px;margin:16px 0 10px">🔔 触发器</h3>
        <div class="field-row">
          <div class="field"><label>触发类型</label><select name="trigger" onchange="triggerChange(this)">
            <option value="form_submit" <?=($edit['trigger']??'')==='form_submit'?'selected':''?>>表单提交</option>
            <option value="member_register" <?=($edit['trigger']??'')==='member_register'?'selected':''?>>用户注册</option>
            <option value="nps_submit" <?=($edit['trigger']??'')==='nps_submit'?'selected':''?>>NPS 评分</option>
            <option value="page_view" <?=($edit['trigger']??'')==='page_view'?'selected':''?>>👀 页面访问</option>
            <option value="article_view" <?=($edit['trigger']??'')==='article_view'?'selected':''?>>📄 文章浏览</option>
            <option value="element_click" <?=($edit['trigger']??'')==='element_click'?'selected':''?>>🖱 元素点击</option>
            <option value="download" <?=($edit['trigger']??'')==='download'?'selected':''?>>📥 资料下载</option>
            <option value="purchase" <?=($edit['trigger']??'')==='purchase'?'selected':''?>>🛒 购买成功</option>
            <option value="course_complete" <?=($edit['trigger']??'')==='course_complete'?'selected':''?>>🎓 课程学完</option>
            <option value="course_enroll" <?=($edit['trigger']??'')==='course_enroll'?'selected':''?>>📚 课程报名</option>
            <option value="lesson_complete" <?=($edit['trigger']??'')==='lesson_complete'?'selected':''?>>✅ 完成课时</option>
            <option value="role_selected" <?=($edit['trigger']??'')==='role_selected'?'selected':''?>>👤 选择角色</option>
            <option value="tool_use" <?=($edit['trigger']??'')==='tool_use'?'selected':''?>>🧰 使用工具</option>
          </select></div>
          <div class="field" id="formSelBox" style="display:<?=($edit['trigger']??'')==='form_submit'?'block':'none'?>">
            <label>指定表单 <span class="hint">· 留空=全部</span></label>
            <select name="form_slug"><option value="">全部表单</option><?php foreach ($forms as $f): ?><option value="<?=htmlspecialchars($f['slug'])?>" <?=($edit['form_slug']??'')===$f['slug']?'selected':''?>><?=htmlspecialchars($f['title'])?></option><?php endforeach; ?></select>
          </div>
          <div class="field" id="npsBox" style="display:<?=($edit['trigger']??'')==='nps_submit'?'block':'none'?>">
            <label>评分阈值 <span class="hint">· 高于此分触发</span></label>
            <input type="number" name="nps_threshold" value="<?=htmlspecialchars($edit['nps_threshold'] ?? 7)?>" min="0" max="10">
          </div>
          <div class="field" id="behavMatchBox" style="display:<?=in_array($edit['trigger']??'', $behavList)?'block':'none'?>">
            <label>匹配字段 <span class="hint">· page/label/course_id/amount</span></label>
            <input type="text" name="match_field" value="<?=htmlspecialchars($edit['match_field'] ?? '')?>" placeholder="如 page / label" style="width:140px">
          </div>
          <div class="field" id="behavValBox" style="display:<?=in_array($edit['trigger']??'', $behavList)?'block':'none'?>">
            <label>匹配值 <span class="hint">· 留空=全部</span></label>
            <input type="text" name="match_value" value="<?=htmlspecialchars($edit['match_value'] ?? '')?>" placeholder="包含此值即触发" style="width:160px">
          </div>
          <div class="field" id="behavPropsBox" style="display:<?=in_array($edit['trigger']??'', $behavList)?'block':'none'?>">
            <label>属性条件 <span class="hint">· key=value,逗号分隔</span></label>
            <input type="text" name="match_props" value="<?=htmlspecialchars($edit['match_props'] ?? '')?>" placeholder="如 amount>100 或 plan=pro" style="width:180px">
          </div>
        </div>

        <h3 style="font-size:15px;margin:16px 0 10px">⚙️ 动作步骤</h3>
        <div id="stepList">
          <?php foreach ($edit['steps'] ?? [] as $si => $st): ?>
          <div class="step-row" style="border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px;background:var(--surface-2)">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
              <select name="step_action[]" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
                <option value="send_email" <?=($st['action']??'')==='send_email'?'selected':''?>>📧 发送邮件</option>
                <option value="delay" <?=($st['action']??'')==='delay'?'selected':''?>>⏱ 延迟</option>
                <option value="notify" <?=($st['action']??'')==='notify'?'selected':''?>>🔔 通知</option>
              </select>
              <input type="text" name="step_subject[]" value="<?=htmlspecialchars($st['subject'] ?? '')?>" placeholder="邮件主题" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <input type="number" name="step_delay[]" value="<?=htmlspecialchars($st['delay_minutes'] ?? 60)?>" placeholder="延迟(分钟)" style="width:110px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.step-row').remove()">✕</button>
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
              <input type="text" name="step_mail_id[]" value="<?=htmlspecialchars($st['mautic_email_id'] ?? '')?>" placeholder="Mautic 邮件ID(可选)" style="width:160px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
              <input type="text" name="step_title[]" value="<?=htmlspecialchars($st['title'] ?? '')?>" placeholder="通知标题" style="width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
              <input type="text" name="step_link[]" value="<?=htmlspecialchars($st['link'] ?? '')?>" placeholder="通知链接" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
            </div>
            <textarea name="step_content[]" rows="2" placeholder="邮件内容（支持 {name} {email} {company} 变量 · {recommend} 自动插入个性化推荐）" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><?=htmlspecialchars($st['content'] ?? '')?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addStep()">+ 添加步骤</button>
        <div style="margin-top:12px"><button type="submit" name="save" class="btn btn-primary">保存流程</button>
        <a href="automation.php" class="btn btn-ghost">取消</a></div>
      </div>
    </form>

    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>流程</th><th>触发器</th><th>步骤数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($flows)): ?><tr><td colspan="5" class="empty">暂无自动化流程，点击右上角创建</td></tr><?php endif; ?>
          <?php foreach ($flows as $f): ?>
          <tr>
            <td><strong><?=htmlspecialchars($f['name'])?></strong></td>
            <td class="text-sm text-muted"><?=$triggerLabels[$f['trigger']] ?? $f['trigger']?></td>
            <td><?=count($f['steps'])?></td>
            <td><span class="badge <?=($f['enabled']??false)?'badge-green':'badge-gray'?>"><?=($f['enabled']??false)?'🟢 运行中':'⏸ 已停'?></span></td>
            <td style="white-space:nowrap">
              <a href="?toggle=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm"><?=($f['enabled']??false)?'停用':'启用'?></a>
              <a href="automation.php?edit=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="?delete=<?=urlencode($f['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 运行日志 -->
    <div class="card" style="padding:0;overflow:auto;margin-top:16px">
      <h2 style="padding:20px 20px 0">📜 运行日志</h2>
      <table>
        <thead><tr><th>时间</th><th>流程</th><th>级别</th><th>详情</th></tr></thead>
        <tbody>
          <?php if (empty($log)): ?><tr><td colspan="4" class="empty">暂无日志</td></tr><?php endif; ?>
          <?php foreach (array_slice($log,0,20) as $l): ?>
          <tr>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['time']??'')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['flow']??'')?></td>
            <td><span class="badge <?=(($l['level']??'')==='error') ? 'badge-red' : (($l['level']??'')==='info' ? 'badge-gray' : 'badge-green')?>"><?=htmlspecialchars($l['level']??'')?></span></td>
            <td class="text-sm"><?=htmlspecialchars($l['message']??'')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function triggerChange(sel) {
  var v = sel.value;
  document.getElementById('formSelBox').style.display = v === 'form_submit' ? 'block' : 'none';
  document.getElementById('npsBox').style.display = v === 'nps_submit' ? 'block' : 'none';
  var isBehav = $behavList.indexOf(v) >= 0;
  var disp = isBehav ? 'block' : 'none';
  document.getElementById('behavMatchBox').style.display = disp;
  document.getElementById('behavValBox').style.display = disp;
  document.getElementById('behavPropsBox').style.display = disp;
}
function addStep() {
  var d = document.createElement('div');
  d.className = 'step-row';
  d.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px;background:var(--surface-2)';
  d.innerHTML =
    '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">' +
      '<select name="step_action[]" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="send_email">📧 发送邮件</option><option value="delay">⏱ 延迟</option><option value="notify">🔔 通知</option></select>' +
      '<input type="text" name="step_subject[]" placeholder="邮件主题" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">' +
      '<input type="number" name="step_delay[]" value="60" placeholder="延迟(分钟)" style="width:110px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.step-row\').remove()">✕</button>' +
    '</div>' +
    '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">' +
      '<input type="text" name="step_mail_id[]" placeholder="Mautic 邮件ID(可选)" style="width:160px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">' +
      '<input type="text" name="step_title[]" placeholder="通知标题" style="width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">' +
      '<input type="text" name="step_link[]" placeholder="通知链接" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">' +
    '</div>' +
    '<textarea name="step_content[]" rows="2" placeholder="邮件内容（支持 {name} {email} 变量）" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"></textarea>';
  document.getElementById('stepList').appendChild(d);
}
</script>
<?php admin_footer(); ?>
