<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/EventDictionary.php';
require_login();
require_perm('cdp');

$message = '';
$dict = EventDictionary::config();
$stats = EventDictionary::stats();

// 导出字典 JSON（POST + CSRF）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_dict'])) {
    csrf_verify();
    $exportData = [
        'name' => 'OpenFlow 事件字典',
        'version' => '1.0',
        'exported_at' => date('Y-m-d H:i:s'),
        'events' => $dict,
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="event-dictionary-' . date('Ymd') . '.json"');
    echo json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 导入字典
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_dict'])) {
    csrf_verify();
    if (isset($_FILES['dict_file']) && ($_FILES['dict_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $json = file_get_contents($_FILES['dict_file']['tmp_name']);
        $data = json_decode($json, true);
        if (!$data || !isset($data['events'])) {
            $error = '无效的字典文件';
        } else {
            $switches = [];
            foreach ($data['events'] as $ev) {
                if (isset($ev['name'])) $switches[$ev['name']] = !empty($ev['enabled']);
            }
            EventDictionary::saveSwitches($switches);
            $message = '字典导入成功（' . count($switches) . ' 个事件配置）';
            $dict = EventDictionary::config();
        }
    } else {
        $error = '请选择字典 JSON 文件';
    }
}

// 保存事件开关
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_switches'])) {
    csrf_verify();
    $switches = [];
    foreach ($_POST['event_enabled'] ?? [] as $name => $val) {
        $switches[$name] = true;
    }
    EventDictionary::saveSwitches($switches);
    $message = '事件开关已保存';
    $dict = EventDictionary::config();
}

// 分类统计
$catTotals = $stats['by_category'];
$catLabels = ['自动采集','内容','课程','转化','社区','用户','工具','系统'];
$evStats = $stats['by_event'];

admin_header('事件字典');
?>
<div class="admin-layout">
  <?php admin_sidebar('event-dictionary'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">📋 事件字典</h1>
      <div class="flex gap-2 ml-auto">
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="export_dict" value="1">
          <button type="submit" class="btn btn-ghost btn-sm">📤 导出 JSON</button>
        </form>
        <label class="btn btn-ghost btn-sm" style="cursor:pointer">📥 导入<input type="file" id="dictImport" accept=".json" style="display:none" onchange="importDict(this)"></label>
        <span class="badge badge-gray"><?=count($dict)?> 个事件</span>
        <span class="badge badge-green"><?=$stats['total']?> 条采集</span>
      </div>
    </div>
    <p class="sub">管理全站埋点事件 · 查看采集统计 · 可停用不追踪的事件</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 分类统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:20px">
      <?php foreach ($catLabels as $cat): ?>
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=$catTotals[$cat] ?? 0?></div><div class="label"><?=$cat?></div></div>
      <?php endforeach; ?>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save_switches" value="1">
      <div class="card" style="padding:0;overflow:auto;margin-bottom:20px">
        <table>
          <thead><tr><th>启用</th><th>事件名</th><th>分类</th><th>说明</th><th>关键属性</th><th>采集数</th><th>转化</th></tr></thead>
          <tbody>
            <?php foreach ($dict as $ev): ?>
            <tr>
              <td><input type="checkbox" name="event_enabled[<?=htmlspecialchars($ev['name'])?>]" value="1" <?=$ev['enabled']?'checked':''?> style="width:16px;height:16px"></td>
              <td><code style="font-size:12px"><?=htmlspecialchars($ev['name'])?></code></td>
              <td><span class="badge badge-gray" style="font-size:11px"><?=htmlspecialchars($ev['category'])?></span></td>
              <td class="text-sm"><?=htmlspecialchars($ev['desc'])?></td>
              <td class="text-sm text-muted" style="font-size:11px;max-width:220px"><?=htmlspecialchars($ev['props'] ?? '')?></td>
              <td><span class="badge <?=($evStats[$ev['name']] ?? 0) > 0 ? 'badge-green' : 'badge-gray'?>"><?=$evStats[$ev['name']] ?? 0?></span></td>
              <td><?=!empty($ev['conversion']) ? '<span class="badge" style="background:#1e1e1e;color:var(--accent)">转化</span>' : ''?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="submit" class="btn btn-primary">保存事件开关</button>
    </form>

    <!-- 说明 -->
    <div class="card" style="padding:16px">
      <h2 style="margin-bottom:10px">💡 说明</h2>
      <p class="text-sm text-muted">事件字典与 <code>docs/TRACKING-PLAN.md</code> 对齐。停用的事件将不再被采集（前端 cdp-track.js 仍会上报，后端过滤丢弃）。</p>
      <p class="text-sm text-muted">新增业务事件：页面中直接 <code>CDP.track('event_name', {属性})</code> 即可，事件会自动出现在统计中。</p>
      <p class="text-sm text-muted">导出/导入：可将事件开关配置备份为 JSON，或从其他站点/团队导入分享。</p>
    </div>
  </div>
</div>

<form id="dictImportForm" method="post" enctype="multipart/form-data" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="import_dict" value="1">
  <input type="file" name="dict_file" id="dictFileInput" onchange="this.form.submit()">
</form>
<script>
function importDict(input) {
  if (input.files && input.files.length) {
    var file = input.files[0];
    // 预校验 JSON
    var reader = new FileReader();
    reader.onload = function(e) {
      try { var d = JSON.parse(e.target.result); if (!d.events) throw new Error('no events'); }
      catch(err) { alert('无效的字典文件'); input.value = ''; return; }
      if (confirm('导入字典将覆盖当前事件开关配置，继续？')) {
        // 复用隐藏表单
        var dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('dictFileInput').files = dt.files;
        document.getElementById('dictImportForm').submit();
      } else { input.value = ''; }
    };
    reader.readAsText(file);
  }
}
</script>
<?php admin_footer(); ?>
