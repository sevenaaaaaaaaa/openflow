<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$campFile = DATA_DIR . '/campaigns.json';
$campaigns = json_read($campFile);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    $components = [];
    $compTypes = $_POST['comp_type'] ?? [];
    foreach ($compTypes as $ci => $ct) {
        if (empty($ct)) continue;
        $components[] = [
            'type' => $ct,
            'title' => $_POST['comp_title'][$ci] ?? '',
            'content' => $_POST['comp_content'][$ci] ?? '',
            'button_text' => $_POST['comp_button'][$ci] ?? '',
            'button_url' => $_POST['comp_url'][$ci] ?? '',
            'position' => $_POST['comp_position'][$ci] ?? '',
            'trigger' => $_POST['comp_trigger'][$ci] ?? '',
            'trigger_delay' => (int)($_POST['comp_delay'][$ci] ?? 5),
            'frequency' => $_POST['comp_freq'][$ci] ?? 'once_per_session',
            'page_scope' => $_POST['comp_scope'][$ci] ?? 'all',
            'page_paths' => $_POST['comp_paths'][$ci] ?? '',
            'bg_color' => $_POST['comp_bg'][$ci] ?? '',
            'text_color' => $_POST['comp_text_color'][$ci] ?? '',
        ];
    }

    $data = [
        'name' => $_POST['name'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'page_scope' => $_POST['page_scope'] ?? 'all',
        'page_paths' => $_POST['page_paths'] ?? '',
        'components' => $components,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if (empty($id)) {
        $data['id'] = 'cmp_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $data['created_at'] = date('Y-m-d H:i:s');
        $campaigns[] = $data;
    } else {
        foreach ($campaigns as &$c) { if ($c['id'] === $id) { $c = array_merge($c, $data); break; } }
    }
    json_write($campFile, $campaigns);
    $message = '活动已保存';
    $campaigns = json_read($campFile);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    csrf_verify();
    $campaigns = array_values(array_filter($campaigns, fn($c) => $c['id'] !== $_POST['delete']));
    json_write($campFile, $campaigns);
    flash('success', '活动已删除');
    header('Location: /xmp/campaigns');
    exit;
}

$editCamp = null;
if (isset($_GET['edit'])) {
    foreach ($campaigns as $c) { if ($c['id'] === $_GET['edit']) { $editCamp = $c; break; } }
}

admin_header('Campaign 管理');
?>
<style>
.cp-head{display:flex;align-items:flex-start;gap:16px;margin-bottom:18px}
.cp-head .sub{margin-bottom:0}
.cp-head .btn{margin-left:auto;flex:0 0 auto;margin-top:6px}
.cp-range{font-family:var(--font-mono);font-size:12px;color:var(--muted);white-space:nowrap}
.cp-comps{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}
.cp-comp{border:1px solid var(--border);border-radius:14px;background:var(--surface-strong);overflow:hidden}
.cp-comp-head{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--hover)}
.cp-n{width:22px;height:22px;border-radius:50%;background:var(--fg);color:var(--bg);font-family:var(--font-mono);font-size:11px;font-weight:700;display:grid;place-items:center;flex:0 0 auto}
.cp-type{height:34px;padding:0 28px 0 10px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-weight:700;background:var(--surface);color:var(--fg);width:auto}
.cp-comp-head input[name="comp_title[]"]{flex:1;height:34px;padding:0 10px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--surface);min-width:0}
.cp-fields{display:grid;grid-template-columns:repeat(4,1fr);gap:10px 14px;padding:12px 14px 14px}
.cp-f{display:flex;flex-direction:column;gap:5px;font-size:12.5px;font-weight:600;min-width:0}
.cp-f em{font-style:normal;font-weight:400;color:var(--faint);font-size:11.5px;margin-left:4px}
.cp-f input,.cp-f select,.cp-f textarea{width:100%;height:36px;padding:0 10px;border:1px solid var(--border);border-radius:9px;font-size:13px;background:var(--surface);color:var(--fg)}
.cp-f textarea{height:auto;padding:8px 10px;line-height:1.6;resize:vertical;font-family:var(--font-mono);font-size:12.5px}
.cp-f.w2{grid-column:span 2}.cp-f.w4{grid-column:1/-1}
.cp-comp:not([data-type="popup"]) .cp-f.only-popup{display:none}
.cp-comp[data-type="top_bar"] .cp-f.no-bar{display:none}
.cp-save{display:flex;gap:8px;align-items:center;margin-top:4px}
@media(max-width:1100px){.cp-fields{grid-template-columns:1fr 1fr}.cp-f.w2{grid-column:span 2}}
</style>
<div class="admin-layout">
  <?php admin_sidebar('campaigns'); ?>
  <div class="main">
    <div class="cp-head">
      <div><h1>活动 / Campaign</h1><p class="sub">一个活动 = 一组带排期、页面范围的转化组件（通知条 / 底部 CTA / 弹窗 / 文中 CTA）</p></div>
      <?php if (!isset($_GET['edit'])): ?><a href="?edit=new" class="btn btn-primary">新建活动</a><?php endif; ?>
    </div>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <?php if (!isset($_GET['edit'])): ?>
    <div class="card lst-card">
      <?php if (empty($campaigns)): ?>
        <div class="of-empty" style="border:0;margin:0;padding:40px">还没有活动。<a href="?edit=new">新建第一个</a>：比如「双十一顶部通知条 + 退出弹窗」，设好起止时间就会自动上下线。</div>
      <?php else: ?>
      <table class="lst-table" data-static>
        <thead><tr><th class="c-title">活动</th><th>组件</th><th>排期</th><th>页面范围</th><th>状态</th><th class="c-act">操作</th></tr></thead>
        <tbody>
          <?php $__tn=['top_bar'=>'通知条','bottom_cta'=>'底部 CTA','popup'=>'弹窗','inline_cta'=>'文中 CTA']; $__sc=['all'=>'全部页面','home'=>'仅首页','article'=>'仅文章页','specific'=>'指定路径'];
          foreach ($campaigns as $c):
            $now = date('Y-m-d H:i:s');
            $isActive = $c['status'] === 'active' && (empty($c['start_date']) || $c['start_date'] <= $now) && (empty($c['end_date']) || $c['end_date'] >= $now);
            $stLabel = $isActive ? '运行中' : ($c['status']==='active' ? (!empty($c['start_date']) && $c['start_date'] > $now ? '待开始' : '已过期') : ($c['status']==='ended' ? '已结束' : '草稿'));
          ?>
          <tr>
            <td class="c-title"><div class="lst-title"><a href="?edit=<?=urlencode($c['id'])?>" style="color:inherit;text-decoration:none"><?=htmlspecialchars($c['name'])?></a></div></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(implode(' · ', array_map(fn($x)=>$__tn[$x['type']??'']??($x['type']??'?'), $c['components'] ?? []))) ?: '—'?></td>
            <td class="cp-range"><?=$c['start_date']?substr($c['start_date'],0,10):'即刻'?> → <?=$c['end_date']?substr($c['end_date'],0,10):'不限'?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($__sc[$c['page_scope'] ?? 'all'] ?? $c['page_scope'])?></td>
            <td><span class="badge <?=$isActive?'badge-green':($c['status']==='active'?'badge-yellow':'badge-gray')?>"><?=$stLabel?></span></td>
            <td class="c-act">
              <a href="?edit=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline" data-confirm="删除活动「<?=htmlspecialchars($c['name'], ENT_QUOTES)?>」？其中的组件会一起下线。">
                <?= csrf_field() ?>
                <button type="submit" name="delete" value="<?=htmlspecialchars($c['id'])?>" class="btn btn-danger btn-sm">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save" value="1">
      <input type="hidden" name="id" value="<?=htmlspecialchars($editCamp['id'] ?? '')?>">

      <div class="card">
        <h2><?=$editCamp?'编辑活动':'新建活动'?></h2>
        <div class="field-row">
          <div class="field"><label>活动名称 <span class="hint">· 必填</span></label><input type="text" name="name" value="<?=htmlspecialchars($editCamp['name'] ?? '')?>" required placeholder="如：秋季课程上新"></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editCamp['status']??'draft')==='draft'?'selected':''?>>草稿（不投放）</option><option value="active" <?=($editCamp['status']??'')==='active'?'selected':''?>>激活（按排期投放）</option><option value="ended" <?=($editCamp['status']??'')==='ended'?'selected':''?>>已结束</option></select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>开始时间 <span class="hint">· 留空 = 即刻</span></label><input type="datetime-local" name="start_date" value="<?=htmlspecialchars($editCamp['start_date']??'')?>"></div>
          <div class="field"><label>结束时间 <span class="hint">· 留空 = 不限</span></label><input type="datetime-local" name="end_date" value="<?=htmlspecialchars($editCamp['end_date']??'')?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>页面范围</label>
            <select name="page_scope" onchange="document.getElementById('campPaths').style.display=this.value==='specific'?'block':'none'">
              <option value="all" <?=($editCamp['page_scope']??'all')==='all'?'selected':''?>>全部页面</option>
              <option value="home" <?=($editCamp['page_scope']??'')==='home'?'selected':''?>>仅首页</option>
              <option value="article" <?=($editCamp['page_scope']??'')==='article'?'selected':''?>>仅文章页</option>
              <option value="specific" <?=($editCamp['page_scope']??'')==='specific'?'selected':''?>>指定路径</option>
            </select>
          </div>
          <div class="field" id="campPaths" style="display:<?=($editCamp['page_scope']??'')==='specific'?'block':'none'?>"><label>路径 <span class="hint">· 一行一个，支持 * 通配</span></label><textarea name="page_paths" rows="2"><?=htmlspecialchars($editCamp['page_paths'] ?? '')?></textarea></div>
        </div>
      </div>

      <div class="card">
        <h2>转化组件 <span class="hint" style="font-weight:400;font-size:12px;color:var(--faint)">· 每条独立配置位置 / 触发 / 频次；活动的排期与范围对全部组件生效</span></h2>
        <div id="compList" class="cp-comps"></div>
        <script>var CP_COMPS = <?=json_encode(array_values($editCamp['components'] ?? []), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?>;</script>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addComponent()">+ 添加组件</button>
      </div>

      <div class="cp-save">
        <button type="submit" class="btn btn-primary">保存活动</button>
        <a href="campaigns.php" class="btn btn-ghost">取消</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
var CP_TYPES = {top_bar:'顶部通知条', bottom_cta:'底部 CTA', popup:'弹窗', inline_cta:'文中 CTA'};
function cpEsc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function cpSel(name, opts, cur, cls){return '<select name="'+name+'[]"'+(cls?' class="'+cls+'"':'')+(name==='comp_type'?' onchange="this.closest(\'.cp-comp\').dataset.type=this.value"':'')+'>'+Object.keys(opts).map(function(k){return '<option value="'+k+'"'+(k===cur?' selected':'')+'>'+opts[k]+'</option>'}).join('')+'</select>'}
function compHTML(c, i) {
  c = c || {}; var t = c.type || 'top_bar';
  return '<div class="cp-comp" data-type="'+t+'">' +
    '<div class="cp-comp-head"><span class="cp-n">'+(i+1)+'</span>' + cpSel('comp_type', CP_TYPES, t, 'cp-type') +
      '<input type="text" name="comp_title[]" value="'+cpEsc(c.title)+'" placeholder="组件标题（展示给访客的第一句）">' +
      '<button type="button" class="ib" title="删除此组件" onclick="this.closest(\'.cp-comp\').remove();cpRenumber()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>' +
    '<div class="cp-fields">' +
      '<label class="cp-f w4"><span>内容 <em>HTML，可留空</em></span><textarea name="comp_content[]" rows="2">'+cpEsc(c.content)+'</textarea></label>' +
      '<label class="cp-f"><span>按钮文字</span><input type="text" name="comp_button[]" value="'+cpEsc(c.button_text)+'" placeholder="如：立即领取"></label>' +
      '<label class="cp-f w2"><span>按钮链接</span><input type="text" name="comp_url[]" value="'+cpEsc(c.button_url)+'" placeholder="https:// 或 /path"></label>' +
      '<label class="cp-f only-popup"><span>弹窗位置</span>' + cpSel('comp_position', {center:'居中','bottom-left':'左下','bottom-right':'右下'}, c.position||'center') + '</label>' +
      '<label class="cp-f no-bar"><span>触发</span>' + cpSel('comp_trigger', {time:'停留一段时间', scroll:'滚动到一半', exit:'准备离开'}, c.trigger||'time') + '</label>' +
      '<label class="cp-f no-bar"><span>延迟 <em>秒</em></span><input type="number" name="comp_delay[]" value="'+cpEsc(c.trigger_delay==null?5:c.trigger_delay)+'" min="0"></label>' +
      '<label class="cp-f"><span>频次</span>' + cpSel('comp_freq', {once_per_session:'每次会话一次', once_per_day:'每天一次', always:'总是'}, c.frequency||'once_per_session') + '</label>' +
      '<label class="cp-f"><span>页面范围 <em>留空 = 跟随活动</em></span><input type="text" name="comp_scope[]" value="'+cpEsc(c.page_scope&&c.page_scope!=='all'?c.page_scope:'')+'" placeholder="all / home / article / /path"></label>' +
    '</div></div>';
}
function cpRenumber(){document.querySelectorAll('#compList .cp-comp').forEach(function(r,i){r.querySelector('.cp-n').textContent=i+1})}
function addComponent(c) { var list = document.getElementById('compList'), d = document.createElement('div'); d.innerHTML = compHTML(c, list.children.length); list.appendChild(d.firstChild); if (!c) list.lastChild.querySelector('input[name="comp_title[]"]').focus(); }
if (document.getElementById('compList')) (window.CP_COMPS || []).forEach(function (c) { addComponent(c); });
</script>
<?php admin_footer(); ?>
