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

if (isset($_POST['delete'])) {
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
<div class="admin-layout">
  <?php admin_sidebar('campaigns'); ?>
  <div class="main">
    <h1>Campaign 管理</h1>
    <p class="sub">营销活动 · 每个活动包含多条转化组件 · 独立生效范围+排期</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>Campaign</th><th>组件数</th><th>排期</th><th>页面范围</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($campaigns)): ?><tr><td colspan="6" class="empty">暂无 Campaign</td></tr><?php endif; ?>
          <?php foreach ($campaigns as $c):
            $now = date('Y-m-d H:i:s');
            $isActive = $c['status'] === 'active' && (empty($c['start_date']) || $c['start_date'] <= $now) && (empty($c['end_date']) || $c['end_date'] >= $now);
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($c['name'])?></strong></td>
            <td><?=count($c['components'] ?? [])?></td>
            <td class="text-sm text-muted"><?=substr($c['start_date']??'',0,10)?> → <?=substr($c['end_date']??'',0,10)?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['page_scope'] ?? 'all')?></td>
            <td><span class="badge <?=$isActive?'badge-green':($c['status']==='active'?'badge-yellow':'badge-gray')?>"><?=$isActive?'运行中':$c['status']?></span></td>
            <td><a href="?edit=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline" data-confirm="确认删除?">
                <?= csrf_field() ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:12px 20px;border-top:1px solid var(--border)"><a href="?edit=new" class="btn btn-primary btn-sm">+ 新建 Campaign</a></div>
    </div>

    <?php if (isset($_GET['edit'])): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save" value="1">
      <input type="hidden" name="id" value="<?=htmlspecialchars($editCamp['id'] ?? '')?>">

      <div class="card">
        <h2><?=$editCamp?'编辑 Campaign':'新建 Campaign'?></h2>
        <div class="field-row">
          <div class="field"><label>Campaign 名称</label><input type="text" name="name" value="<?=htmlspecialchars($editCamp['name'] ?? '')?>" required></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editCamp['status']??'')==='draft'?'selected':''?>>草稿</option><option value="active" <?=($editCamp['status']??'')==='active'?'selected':''?>>激活</option><option value="ended" <?=($editCamp['status']??'')==='ended'?'selected':''?>>已结束</option></select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>开始时间</label><input type="datetime-local" name="start_date" value="<?=htmlspecialchars($editCamp['start_date']??'')?>"></div>
          <div class="field"><label>结束时间</label><input type="datetime-local" name="end_date" value="<?=htmlspecialchars($editCamp['end_date']??'')?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>页面范围</label>
            <select name="page_scope" onchange="document.getElementById('campPaths').style.display=this.value==='specific'?'block':'none'">
              <option value="all" <?=($editCamp['page_scope']??'')==='all'?'selected':''?>>全部页面</option>
              <option value="home" <?=($editCamp['page_scope']??'')==='home'?'selected':''?>>仅首页</option>
              <option value="article" <?=($editCamp['page_scope']??'')==='article'?'selected':''?>>仅文章页</option>
              <option value="specific" <?=($editCamp['page_scope']??'')==='specific'?'selected':''?>>指定路径</option>
            </select>
          </div>
          <div class="field" id="campPaths" style="display:<?=($editCamp['page_scope']??'')==='specific'?'block':'none'?>"><label>路径 <span class="hint">一行一个</span></label><textarea name="page_paths" rows="2"><?=htmlspecialchars($editCamp['page_paths'] ?? '')?></textarea></div>
        </div>
      </div>

      <!-- Components -->
      <div class="card">
        <h2>🧩 转化组件</h2>
        <p class="text-sm text-muted mb-4">一个 Campaign 可包含多条组件，每条独立配置位置/触发/频次/页面</p>
        <div id="compList">
          <?php foreach (($editCamp['components'] ?? []) as $ci => $comp): ?>
          <div class="comp-item" style="border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
              <select name="comp_type[]" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
                <option value="top_bar" <?=$comp['type']==='top_bar'?'selected':''?>>📢 顶部通知条</option>
                <option value="bottom_cta" <?=$comp['type']==='bottom_cta'?'selected':''?>>⬇️ 底部 CTA</option>
                <option value="popup" <?=$comp['type']==='popup'?'selected':''?>>💬 弹窗</option>
                <option value="inline_cta" <?=$comp['type']==='inline_cta'?'selected':''?>>📝 文中 CTA</option>
              </select>
              <input type="text" name="comp_title[]" value="<?=htmlspecialchars($comp['title']??'')?>" placeholder="标题" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.comp-item').remove()">✕</button>
            </div>
            <textarea name="comp_content[]" rows="2" placeholder="内容 (HTML)" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars($comp['content']??'')?></textarea>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">
              <input type="text" name="comp_button[]" value="<?=htmlspecialchars($comp['button_text']??'')?>" placeholder="按钮文字">
              <input type="text" name="comp_url[]" value="<?=htmlspecialchars($comp['button_url']??'')?>" placeholder="按钮链接">
              <select name="comp_position[]"><option value="center" <?=($comp['position']??'')==='center'?'selected':''?>>居中</option><option value="bottom-left" <?=($comp['position']??'')==='bottom-left'?'selected':''?>>左下</option><option value="bottom-right" <?=($comp['position']??'')==='bottom-right'?'selected':''?>>右下</option></select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-top:8px">
              <select name="comp_trigger[]"><option value="time" <?=($comp['trigger']??'')==='time'?'selected':''?>>时间</option><option value="scroll" <?=($comp['trigger']??'')==='scroll'?'selected':''?>>滚动</option><option value="exit" <?=($comp['trigger']??'')==='exit'?'selected':''?>>退出</option></select>
              <input type="number" name="comp_delay[]" value="<?=htmlspecialchars($comp['trigger_delay']??5)?>" placeholder="延迟秒" style="width:80px">
              <select name="comp_freq[]"><option value="once_per_session" <?=($comp['frequency']??'')==='once_per_session'?'selected':''?>>每次会话</option><option value="once_per_day" <?=($comp['frequency']??'')==='once_per_day'?'selected':''?>>每天一次</option><option value="always" <?=($comp['frequency']??'')==='always'?'selected':''?>>总是</option></select>
              <input type="text" name="comp_scope[]" value="<?=htmlspecialchars($comp['page_scope']??'')?>" placeholder="页面范围">
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addComponent()">+ 添加组件</button>
      </div>

      <button type="submit" class="btn btn-primary">保存 Campaign</button>
      <a href="campaigns.php" class="btn btn-ghost">取消</a>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
function addComponent() {
  var div = document.createElement('div');
  div.className = 'comp-item';
  div.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)';
  div.innerHTML =
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
      '<select name="comp_type[]" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
        '<option value="top_bar">📢 顶部通知条</option><option value="bottom_cta">⬇️ 底部 CTA</option><option value="popup">💬 弹窗</option><option value="inline_cta">📝 文中 CTA</option>' +
      '</select>' +
      '<input type="text" name="comp_title[]" placeholder="标题" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.comp-item\').remove()">✕</button>' +
    '</div>' +
    '<textarea name="comp_content[]" rows="2" placeholder="内容 (HTML)" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;font-family:var(--mono)"></textarea>' +
    '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">' +
      '<input type="text" name="comp_button[]" placeholder="按钮文字">' +
      '<input type="text" name="comp_url[]" placeholder="按钮链接">' +
      '<select name="comp_position[]"><option value="center">居中</option><option value="bottom-left">左下</option><option value="bottom-right">右下</option></select>' +
    '</div>' +
    '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-top:8px">' +
      '<select name="comp_trigger[]"><option value="time">时间</option><option value="scroll">滚动</option><option value="exit">退出</option></select>' +
      '<input type="number" name="comp_delay[]" value="5" placeholder="延迟秒" style="width:80px">' +
      '<select name="comp_freq[]"><option value="once_per_session">每次会话</option><option value="once_per_day">每天一次</option><option value="always">总是</option></select>' +
      '<input type="text" name="comp_scope[]" placeholder="页面范围">' +
    '</div>';
  document.getElementById('compList').appendChild(div);
}
</script>
<?php admin_footer(); ?>
