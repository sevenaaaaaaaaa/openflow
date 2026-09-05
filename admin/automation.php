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
            'tag' => $_POST['step_tag'][$i] ?? '',
            'points' => (int)($_POST['step_points'][$i] ?? 0),
            'coupon_name' => $_POST['step_coupon_name'][$i] ?? '',
            'coupon_type' => $_POST['step_coupon_type'][$i] ?? 'fixed',
            'coupon_value' => (float)($_POST['step_coupon_value'][$i] ?? 0),
            'coupon_min' => (float)($_POST['step_coupon_min'][$i] ?? 0),
            'action_id' => preg_replace('/[^a-z0-9_]/', '', (string)($_POST['step_action_id'][$i] ?? '')),
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
    header('Location: /xmp/automation');
    exit;
}

// 删除/切换
if (isset($_GET['delete'])) {
    $flows = array_values(array_filter($flows, fn($f) => $f['id'] !== $_GET['delete']));
    automation_save($flows);
    flash('success', '流程已删除');
    header('Location: /xmp/automation');
    exit;
}
if (isset($_GET['toggle'])) {
    foreach ($flows as &$f) if ($f['id'] === $_GET['toggle']) $f['enabled'] = !($f['enabled'] ?? false);
    unset($f);
    automation_save($flows);
    header('Location: /xmp/automation');
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
$triggerLabels = ['form_submit'=>'表单提交','member_register'=>'用户注册','nps_submit'=>'NPS 评分','page_view'=>'页面访问','article_view'=>'文章浏览','element_click'=>'元素点击','download'=>'资料下载','purchase'=>'购买成功','course_complete'=>'课程学完','course_enroll'=>'课程报名','lesson_complete'=>'完成课时','role_selected'=>'选择角色','tool_use'=>'使用工具','segment_enter'=>'进入分群','segment_exit'=>'退出分群'];
$behavList = ['page_view','article_view','element_click','download','purchase','course_complete','course_enroll','lesson_complete','role_selected','tool_use','segment_enter','segment_exit'];

admin_header('营销自动化');
?>
<style>
.au-h3{font-size:14px;font-weight:800;margin:18px 0 10px;display:flex;align-items:center;gap:8px}
.au-h3 .hint{font-weight:400;color:var(--faint);font-size:12px}
.au-steps{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}
.au-step{border:1px solid var(--border);border-radius:14px;background:var(--surface-strong);overflow:hidden}
.au-step-head{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--hover)}
.au-n{width:22px;height:22px;border-radius:50%;background:var(--fg);color:var(--bg);font-family:var(--font-mono);font-size:11px;font-weight:700;display:grid;place-items:center;flex:0 0 auto}
.au-act{height:34px;padding:0 28px 0 10px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-weight:700;background:var(--surface);color:var(--fg);width:auto}
.au-step-sum{flex:1;min-width:0;font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.au-step-tools{display:flex;gap:2px}
.au-step-tools .ib.danger:hover{color:var(--danger);background:var(--danger-soft,var(--hover))}
.au-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px;padding:12px 14px 14px}
.au-f{display:none;flex-direction:column;gap:5px;font-size:12.5px;font-weight:600;min-width:0}
.au-f > span:first-child{display:flex;gap:6px;align-items:baseline}
.au-f em{font-style:normal;font-weight:400;color:var(--faint);font-size:11.5px}
.au-f input,.au-f select,.au-f textarea{width:100%;height:36px;padding:0 10px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--surface);color:var(--fg)}
.au-f textarea{height:auto;padding:8px 10px;line-height:1.6;resize:vertical}
.au-f.wide{grid-column:1/-1}
.au-inline{display:flex;align-items:center;gap:8px}
.au-inline input{width:120px}
.au-step[data-action="send_email"] [data-f="subject"],.au-step[data-action="send_email"] [data-f="content"],.au-step[data-action="send_email"] [data-f="mail_id"],
.au-step[data-action="delay"] [data-f="delay"],
.au-step[data-action="notify"] [data-f="title"],.au-step[data-action="notify"] [data-f="content"],.au-step[data-action="notify"] [data-f="link"],
.au-step[data-action="inbox"] [data-f="title"],.au-step[data-action="inbox"] [data-f="content"],.au-step[data-action="inbox"] [data-f="link"],
.au-step[data-action="add_tag"] [data-f="tag"],
.au-step[data-action="award_points"] [data-f="points"],
.au-step[data-action="send_coupon"] [data-f^="coupon_"]{display:flex}
.au-step[data-action="send_wecom"] [data-f="content"],[data-action="send_wecom"] [data-f="mode"],[data-action="send_wecom"] [data-f="title"],[data-action="send_wecom"] [data-f="url"]{display:flex}
.au-step[data-action="send_wechat"] [data-f="content"],[data-action="send_wechat"] [data-f="mode"],[data-action="send_wechat"] [data-f="template_id"],[data-action="send_wechat"] [data-f="tag_id"],[data-action="send_wechat"] [data-f="title"],[data-action="send_wechat"] [data-f="url"]{display:flex}
@media(max-width:840px){.au-fields{grid-template-columns:1fr}}
</style>
<div class="admin-layout">
  <?php admin_sidebar('automation'); ?>
  <div class="main">
    <h1>营销自动化</h1>
    <p class="sub">自动化邮件流程 · 触发条件 → 动作（发邮件/通知/延迟/企业微信/公众号）</p>
    <?php if (function_exists('automation_flows_stats')): $allStats = automation_flows_stats(); ?>
    <div class="card" style="padding:16px;margin-bottom:16px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:10px">📊 流程漏斗洞察</h3>
      <table class="lst-table">
        <thead><tr><th>流程</th><th>触发</th><th>状态</th><th>进入</th><th>触达</th><th>各渠道</th><th>失败</th><th>转化</th></tr></thead>
        <tbody>
        <?php if (!$allStats): ?><tr><td colspan="8" class="empty">暂无流程。运行后这里显示每流程的进入/触达/转化漏斗。</td></tr>
        <?php else: foreach ($allStats as $s): ?>
          <tr>
            <td class="c-title"><div class="lst-item"><div class="lst-body"><div class="lst-title"><?=htmlspecialchars($s['name'])?></div><div class="lst-sub mono"><?=htmlspecialchars($s['id'])?></div></div></div></td>
            <td class="text-sm"><?=htmlspecialchars($s['trigger'] ?: '—')?></td>
            <td><span class="st <?=$s['status']==='enabled'?'st-ok':'st-faint'?>"><?=$s['status']==='enabled'?'启用':'停用'?></span></td>
            <td class="text-sm"><b><?=$s['entered']?></b></td>
            <td class="text-sm"><?=$s['sent']?></td>
            <td class="text-xs text-muted">邮<?=$s['channels']['email']?> 企微<?=$s['channels']['wecom']?> 公<?=$s['channels']['wechat']?> 券<?=$s['channels']['coupon']?></td>
            <td class="text-sm"><?=$s['failed']?></td>
            <td class="text-sm" style="color:var(--accent)"><?=$s['conversion']?>%</td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <p class="text-xs text-muted" style="margin-top:8px">基于自动化日志聚合；使用 {flow 变量} 或接入企微/公众号后各渠道触达会显示。</p>
    </div>
    <?php endif; ?>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="flex items-center gap-4 mb-4">
      <h2 style="margin-bottom:0">自动化流程</h2>
      <a href="automation.php?edit=new" class="btn btn-primary btn-sm ml-auto">新建流程</a>
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

        <h3 class="au-h3">触发器 <span class="hint">· 什么事发生时启动这条流程</span></h3>
        <div class="field-row">
          <div class="field"><label>触发类型</label><select name="trigger" onchange="triggerChange(this)">
            <option value="form_submit" <?=($edit['trigger']??'')==='form_submit'?'selected':''?>>表单提交</option>
            <option value="member_register" <?=($edit['trigger']??'')==='member_register'?'selected':''?>>用户注册</option>
            <option value="nps_submit" <?=($edit['trigger']??'')==='nps_submit'?'selected':''?>>NPS 评分</option>
            <option value="page_view" <?=($edit['trigger']??'')==='page_view'?'selected':''?>>页面访问</option>
            <option value="article_view" <?=($edit['trigger']??'')==='article_view'?'selected':''?>>文章浏览</option>
            <option value="element_click" <?=($edit['trigger']??'')==='element_click'?'selected':''?>>元素点击</option>
            <option value="download" <?=($edit['trigger']??'')==='download'?'selected':''?>>资料下载</option>
            <option value="purchase" <?=($edit['trigger']??'')==='purchase'?'selected':''?>>购买成功</option>
            <option value="course_complete" <?=($edit['trigger']??'')==='course_complete'?'selected':''?>>课程学完</option>
            <option value="course_enroll" <?=($edit['trigger']??'')==='course_enroll'?'selected':''?>>课程报名</option>
            <option value="lesson_complete" <?=($edit['trigger']??'')==='lesson_complete'?'selected':''?>>完成课时</option>
            <option value="role_selected" <?=($edit['trigger']??'')==='role_selected'?'selected':''?>>选择角色</option>
            <option value="tool_use" <?=($edit['trigger']??'')==='tool_use'?'selected':''?>>使用工具</option>
            <option value="segment_enter" <?=($edit['trigger']??'')==='segment_enter'?'selected':''?>>进入分群</option>
            <option value="segment_exit" <?=($edit['trigger']??'')==='segment_exit'?'selected':''?>>退出分群</option>
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

        <h3 class="au-h3">动作步骤 <span class="hint">· 按顺序执行，每一步只填该动作需要的字段</span></h3>
        <div id="stepList" class="au-steps"></div>
        <script>var AU_STEPS = <?=json_encode(array_values($edit['steps'] ?? []), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?>;</script>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addStep()">+ 添加步骤</button>
        <div style="margin-top:12px"><button type="submit" name="save" class="btn btn-primary">保存流程</button>
        <a href="automation.php" class="btn btn-ghost">取消</a></div>
      </div>
    </form>

    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>流程</th><th>触发器</th><th>步骤</th><th>状态</th><th class="actions">操作</th></tr></thead>
        <tbody>
          <?php if (empty($flows)): ?><tr><td colspan="5"><div class="of-empty" style="border:0;margin:0">还没有自动化流程。<a href="automation.php?edit=new">新建第一条</a>：比如「表单提交 → 发欢迎邮件 → 3 天后再发一封」</div></td></tr><?php endif; ?>
          <?php foreach ($flows as $f): ?>
          <tr>
            <td><strong><?=htmlspecialchars($f['name'])?></strong></td>
            <td class="text-sm text-muted"><?=$triggerLabels[$f['trigger']] ?? $f['trigger']?></td>
            <td class="text-sm text-muted"><?php $__names=['send_email'=>'邮件','delay'=>'延迟','notify'=>'通知','inbox'=>'站内信','add_tag'=>'标签','award_points'=>'积分','send_coupon'=>'优惠券','send_wecom'=>'企业微信','send_wechat'=>'公众号','connection_action'=>'连接动作']; echo htmlspecialchars(implode(' → ', array_map(fn($st)=>$__names[$st['action']??'']??($st['action']??'?'), $f['steps'] ?? []))) ?: '—'; ?></td>
            <td><span class="badge <?=($f['enabled']??false)?'badge-green':'badge-gray'?>"><?=($f['enabled']??false)?'运行中':'已停用'?></span></td>
            <td style="white-space:nowrap">
              <a href="?toggle=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm"><?=($f['enabled']??false)?'停用':'启用'?></a>
              <a href="automation.php?edit=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="?delete=<?=urlencode($f['id'])?>" class="btn btn-danger btn-sm" data-confirm="确认删除?">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 运行日志 -->
    <div class="card" style="padding:0;overflow:auto;margin-top:16px">
      <h2 style="padding:20px 20px 0">运行日志</h2>
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
var AU_ACTIONS = {
  send_email:   {label:'发送邮件', fields:['subject','content','mail_id']},
  delay:        {label:'延迟',     fields:['delay']},
  notify:       {label:'通知',     fields:['title','content','link']},
  inbox:        {label:'站内信',   fields:['title','content','link']},
  add_tag:      {label:'打标签',   fields:['tag']},
  award_points: {label:'加积分',   fields:['points']},
  send_coupon:  {label:'发优惠券', fields:['coupon_name','coupon_type','coupon_value','coupon_min']},
  send_wecom:   {label:'企业微信', fields:['content','title','url','mode']},
  send_wechat:  {label:'公众号/服务号', fields:['content','title','url','template_id','tag_id','mode']},
  connection_action: {label:'连接动作（外部服务）', fields:['action_id']}
};
// 开放能力：可用的连接动作（连接名 · 动作名）
var AU_CONN_ACTIONS = <?php require_once __DIR__ . '/../lib/ConnectionActions.php'; echo json_encode(action_options(), JSON_UNESCAPED_UNICODE); ?>;
function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function stepHTML(st, i) {
  st = st || {}; var act = st.action || 'send_email';
  var opts = Object.keys(AU_ACTIONS).map(function(k){return '<option value="'+k+'"'+(k===act?' selected':'')+'>'+AU_ACTIONS[k].label+'</option>'}).join('');
  return '<div class="au-step" data-action="'+act+'">' +
    '<div class="au-step-head"><span class="au-n">'+(i+1)+'</span>' +
      '<select name="step_action[]" class="au-act" onchange="stepAction(this)">'+opts+'</select>' +
      '<span class="au-step-sum"></span>' +
      '<span class="au-step-tools"><button type="button" class="ib" title="上移" onclick="stepMove(this,-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg></button><button type="button" class="ib" title="下移" onclick="stepMove(this,1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button><button type="button" class="ib danger" title="删除此步" onclick="this.closest(\'.au-step\').remove();stepRenumber()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></span>' +
    '</div>' +
    '<div class="au-fields">' +
      '<label class="au-f" data-f="subject"><span>邮件主题</span><input type="text" name="step_subject[]" value="'+esc(st.subject)+'" placeholder="如：欢迎加入 OpenFlow"></label>' +
      '<label class="au-f" data-f="mail_id"><span>Mautic 邮件 ID <em>可选，填了就发 Mautic 模板</em></span><input type="text" name="step_mail_id[]" value="'+esc(st.mautic_email_id)+'" placeholder="如 12"></label>' +
      '<label class="au-f" data-f="delay"><span>等待</span><span class="au-inline"><input type="number" name="step_delay[]" value="'+esc(st.delay_minutes==null?60:st.delay_minutes)+'" min="0"><em>分钟后执行下一步</em></span></label>' +
      '<label class="au-f" data-f="title"><span>标题</span><input type="text" name="step_title[]" value="'+esc(st.title)+'" placeholder="通知 / 站内信标题"></label>' +
      '<label class="au-f" data-f="link"><span>链接 <em>可选</em></span><input type="text" name="step_link[]" value="'+esc(st.link)+'" placeholder="https:// 或 /path"></label>' +
      '<label class="au-f wide" data-f="content"><span>内容 <em>支持 {name} {email} {company}；{recommend} 自动插入个性化推荐</em></span><textarea name="step_content[]" rows="3">'+esc(st.content)+'</textarea></label>' +
      '<label class="au-f" data-f="tag"><span>标签名</span><input type="text" name="step_tag[]" value="'+esc(st.tag)+'" placeholder="如 vip-candidate"></label>' +
      '<label class="au-f" data-f="points"><span>积分</span><span class="au-inline"><input type="number" name="step_points[]" value="'+esc(st.points==null?0:st.points)+'"><em>分</em></span></label>' +
      '<label class="au-f" data-f="coupon_name"><span>券名</span><input type="text" name="step_coupon_name[]" value="'+esc(st.coupon_name)+'" placeholder="如 新人立减 20"></label>' +
      '<label class="au-f" data-f="coupon_type"><span>类型</span><select name="step_coupon_type[]"><option value="fixed"'+((st.coupon_type||'fixed')==='fixed'?' selected':'')+'>满减 ¥</option><option value="percent"'+(st.coupon_type==='percent'?' selected':'')+'>折扣 %</option></select></label>' +
      '<label class="au-f" data-f="coupon_value"><span>面值</span><input type="number" name="step_coupon_value[]" value="'+esc(st.coupon_value==null?0:st.coupon_value)+'"></label>' +
      '<label class="au-f" data-f="coupon_min"><span>满额门槛</span><input type="number" name="step_coupon_min[]" value="'+esc(st.coupon_min==null?0:st.coupon_min)+'"></label>' +
      // 线A：企业微信 / 公众号 触达动作字段（复用已通 API）
      '<label class="au-f" data-f="mode"><span>触达方式</span><select name="step_mode[]">' +
        '<option value="text"'+((st.mode||'text')==='text'?' selected':'')+'>企业微信-文本</option>' +
        '<option value="news"'+(st.mode==='news'?' selected':'')+'>企业微信-图文</option>' +
        '<option value="textcard"'+(st.mode==='textcard'?' selected':'')+'>企业微信-文本卡片</option>' +
        '<option value="template"'+(st.mode==='template'?' selected':'')+'>公众号-模板消息</option>' +
        '<option value="kf"'+(st.mode==='kf'?' selected':'')+'>公众号-客服消息</option>' +
        '<option value="mass_tag"'+(st.mode==='mass_tag'?' selected':'')+'>公众号-按标签群发</option>' +
      '</select></label>' +
      '<label class="au-f" data-f="title"><span>标题<em>图文/卡片</em></span><input type="text" name="step_title[]" value="'+esc(st.title)+'"></label>' +
      '<label class="au-f" data-f="url"><span>链接 <em>可选</em></span><input type="text" name="step_url[]" value="'+esc(st.url)+'" placeholder="https:// 或 /path"></label>' +
      '<label class="au-f" data-f="template_id"><span>模板ID<em>公众号模板消息</em></span><input type="text" name="step_template_id[]" value="'+esc(st.template_id)+'" placeholder="TM/模板ID"></label>' +
      '<label class="au-f" data-f="tag_id"><span>标签ID<em>群发</em></span><input type="number" name="step_tag_id[]" value="'+esc(st.tag_id==null?0:st.tag_id)+'"></label>' +
      '<label class="au-f wide" data-f="content"><span>内容 <em>支持 {name} {email} 等变量</em></span><textarea name="step_content[]" rows="3">'+esc(st.content)+'</textarea></label>' +
      '<label class="au-f wide" data-f="action_id"><span>连接动作 <em>在「连接」里定义；事件字段会代入动作模板</em></span><select name="step_action_id[]">' +
        '<option value="">请选择…</option>' + Object.keys(AU_CONN_ACTIONS).map(function(k){ return '<option value="'+esc(k)+'"'+((st.action_id||'')===k?' selected':'')+'>'+esc(AU_CONN_ACTIONS[k])+'</option>'; }).join('') +
        (Object.keys(AU_CONN_ACTIONS).length ? '' : '<option value="" disabled>还没有启用的连接动作，先到「连接」里建一个</option>') +
      '</select></label>' +
    '</div></div>';
}
function stepAction(sel) { var row = sel.closest('.au-step'); row.dataset.action = sel.value; stepSummary(row); }
function stepSummary(row) {
  var a = row.dataset.action, s = '';
  var v = function(n){ var el = row.querySelector('[name="'+n+'[]"]'); return el ? el.value : ''; };
  if (a === 'send_email') s = v('step_subject') || '（未填主题）';
  else if (a === 'delay') s = '等 ' + v('step_delay') + ' 分钟';
  else if (a === 'notify' || a === 'inbox') s = v('step_title') || '（未填标题）';
  else if (a === 'add_tag') s = v('step_tag') ? '#' + v('step_tag') : '（未填标签）';
  else if (a === 'award_points') s = '+' + v('step_points') + ' 分';
  else if (a === 'send_coupon') s = (v('step_coupon_name') || '券') + ' · ' + (v('step_coupon_type') === 'percent' ? v('step_coupon_value') + '%' : '¥' + v('step_coupon_value'));
  else if (a === 'connection_action') s = AU_CONN_ACTIONS[v('step_action_id')] || '未选择动作';
  row.querySelector('.au-step-sum').textContent = s;
}
function stepRenumber() { document.querySelectorAll('#stepList .au-step').forEach(function (r, i) { r.querySelector('.au-n').textContent = i + 1; }); }
function stepMove(btn, dir) { var r = btn.closest('.au-step'), p = r.parentNode; var t = dir < 0 ? r.previousElementSibling : r.nextElementSibling; if (!t) return; dir < 0 ? p.insertBefore(r, t) : p.insertBefore(t, r); stepRenumber(); }
function addStep(st) {
  var list = document.getElementById('stepList'), d = document.createElement('div');
  d.innerHTML = stepHTML(st, list.children.length); var row = d.firstChild; list.appendChild(row); stepSummary(row);
  if (!st) { row.querySelector('.au-act').focus(); row.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
}
if (document.getElementById('stepList')) {
  (window.AU_STEPS || []).forEach(function (st) { addStep(st); });
  document.getElementById('stepList').addEventListener('input', function (e) { var r = e.target.closest('.au-step'); if (r) stepSummary(r); });
}
</script>
<?php admin_footer(); ?>
