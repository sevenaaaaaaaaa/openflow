<?php
/**
 * 外部数据连接器 — REST API / CSV 主动拉取外部数据到 CDP / CRM
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DataSync.php';
require_login();
require_perm('settings');

$conns = datasync_connectors();
$message = '';
if (!is_dir(datasync_dir())) mkdir(datasync_dir(), 0755, true);

// 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = trim($_POST['id'] ?? '');
    $mapping = [];
    foreach (($_POST['map_target'] ?? []) as $i => $target) {
        $source = trim($_POST['map_source'][$i] ?? '');
        $target = trim($target);
        if ($source === '' || $target === '') continue;
        $mapping[$target] = $source;
    }
    $conn = [
        'id' => $id,
        'name' => trim($_POST['name'] ?? ''),
        'kind' => $_POST['kind'] ?? 'rest_api',
        'target' => $_POST['target'] ?? 'cdp_event',
        'event' => trim($_POST['event'] ?? ''),
        'source' => trim($_POST['source'] ?? ''),
        'enabled' => isset($_POST['enabled']),
        'mapping' => $mapping,
        'config' => ['url' => trim($_POST['cfg_url'] ?? ''), 'token' => trim($_POST['cfg_token'] ?? ''), 'headers' => trim($_POST['cfg_headers'] ?? ''), 'data_key' => trim($_POST['cfg_data_key'] ?? '')],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    // CSV 文件上传
    if ($conn['kind'] === 'csv' && !empty($_FILES['csv_file']['tmp_name'])) {
        $dest = datasync_dir() . '/' . $id . '.csv';
        move_uploaded_file($_FILES['csv_file']['tmp_name'], $dest);
    }
    if (empty($conn['name'])) { $message = '名称必填'; }
    else {
        $found = false;
        foreach ($conns as &$c) if (($c['id'] ?? '') === $id) { $c = $conn; $found = true; break; }
        unset($c);
        if (!$found) { $conn['created_at'] = date('Y-m-d H:i:s'); $conns[] = $conn; }
        datasync_save($conns);
        $message = '连接器已保存';
    }
}

// 手动同步
if (isset($_GET['sync'])) {
    $r = datasync_run_connector($_GET['sync']);
    $message = $r['ok'] ? ("同步完成：写入 {$r['rows']} 条，失败 {$r['failed']} 条") : ('同步失败：' . ($r['error'] ?? ''));
}

// 删除
if (isset($_GET['delete'])) {
    @unlink(datasync_dir() . '/' . $_GET['delete'] . '.csv');
    $conns = array_values(array_filter($conns, fn($c) => ($c['id'] ?? '') !== $_GET['delete']));
    datasync_save($conns);
    header('Location: /xmp/data-sync');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) { foreach ($conns as $c) if (($c['id'] ?? '') === $_GET['edit']) { $edit = $c; break; } }

$log = json_read(datasync_log_file());

admin_header('外部连接器');
?>
<div class="admin-layout">
  <?php admin_sidebar('data-sync'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>外部数据连接器</h1><p class="v-sub">REST API / CSV 主动拉取外部数据 → CDP 事件 / CRM 线索 / 画像属性</p></div>
      <?php if (!$edit): ?><div class="v-actions"><a href="?edit=new" class="btn btn-s btn-sm">+ 新建连接器</a></div><?php endif; ?>
    </div>
    <?php if ($message): ?><?=msg(strpos($message, '失败') !== false || strpos($message, '必填') !== false ? 'error' : 'success', $message)?><?php endif; ?>

    <?php if ($edit): ?>
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'] ?? 'sync_' . substr(bin2hex(random_bytes(6)), 0, 8))?>">
      <h2 style="margin-bottom:16px"><?=($edit['id'] ?? '') ? '编辑连接器' : '新建连接器'?></h2>
      <div class="field-row">
        <div class="field"><label>名称 *</label><input type="text" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" placeholder="如：巨量报表 / 伙伴 API"></div>
        <div class="field"><label>类型</label><select name="kind" onchange="document.getElementById('restCfg').style.display=this.value==='rest_api'?'':'none';document.getElementById('csvCfg').style.display=this.value==='csv'?'':'none'">
          <option value="rest_api" <?=($edit['kind'] ?? '')==='rest_api'?'selected':''?>>REST API 拉取</option>
          <option value="csv" <?=($edit['kind'] ?? '')==='csv'?'selected':''?>>CSV 导入</option>
        </select></div>
      </div>
      <div class="field-row">
        <div class="field"><label>写入目标</label><select name="target">
          <option value="cdp_event" <?=($edit['target'] ?? '')==='cdp_event'?'selected':''?>>CDP 事件</option>
          <option value="lead" <?=($edit['target'] ?? '')==='lead'?'selected':''?>>CRM 线索</option>
          <option value="contact" <?=($edit['target'] ?? '')==='contact'?'selected':''?>>画像属性</option>
        </select></div>
        <div class="field"><label>来源标记 <span class="hint">· 事件 source / 线索来源</span></label><input type="text" name="source" value="<?=htmlspecialchars($edit['source'] ?? '')?>" placeholder="如：ocean_engine"></div>
      </div>
      <div class="field"><label>事件名 <span class="hint">· 目标为 CDP 事件时使用，留空用行内 event</span></label><input type="text" name="event" value="<?=htmlspecialchars($edit['event'] ?? '')?>" placeholder="如：ad_report / external_order"></div>

      <div id="restCfg" style="display:<?=($edit['kind'] ?? 'rest_api')==='rest_api'?'':'none'?>">
        <div class="field-row">
          <div class="field" style="flex:2"><label>API URL</label><input type="text" name="cfg_url" value="<?=htmlspecialchars($edit['config']['url'] ?? '')?>" placeholder="https://api.example.com/v1/report?date=<?=date('Y-m-d')?>"></div>
          <div class="field"><label>结果数组 Key <span class="hint">· 留空自动识别</span></label><input type="text" name="cfg_data_key" value="<?=htmlspecialchars($edit['config']['data_key'] ?? '')?>" placeholder="data / list / rows"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Bearer Token</label><input type="password" name="cfg_token" value="<?=htmlspecialchars($edit['config']['token'] ?? '')?>"></div>
          <div class="field"><label>额外 Headers <span class="hint">· JSON 对象</span></label><input type="text" name="cfg_headers" value="<?=htmlspecialchars($edit['config']['headers'] ?? '')?>" placeholder='{"Content-Type":"application/json"}'></div>
        </div>
      </div>
      <div id="csvCfg" style="display:<?=($edit['kind'] ?? 'rest_api')==='csv'?'':'none'?>">
        <div class="field"><label>上传 CSV <span class="hint">· 首行为表头，同步时按映射取列</span></label><input type="file" name="csv_file" accept=".csv"></div>
      </div>

      <div class="field"><label>字段映射 <span class="hint">· 外部列名 → 内部字段</span></label>
        <div id="mapRows">
          <?php $editMap = $edit['mapping'] ?? []; $mapAll = ['email','name','phone','company','source','event','visitor_id','amount','order_id','date','channel']; if (empty($editMap)): ?>
          <div style="display:flex;gap:8px;margin-bottom:6px"><input type="text" name="map_source[]" placeholder="外部列名" style="width:200px;padding:8px;border:1.5px solid var(--border);border-radius:8px"><select name="map_target[]" style="padding:8px;border:1.5px solid var(--border);border-radius:8px"><?php foreach ($mapAll as $mt): ?><option value="<?=$mt?>"><?=$mt?></option><?php endforeach; ?></select><button type="button" onclick="this.parentElement.remove()" style="border:none;background:none;color:var(--danger)">✕</button></div>
          <?php else: foreach ($editMap as $target => $source): ?>
          <div style="display:flex;gap:8px;margin-bottom:6px"><input type="text" name="map_source[]" value="<?=htmlspecialchars($source)?>" placeholder="外部列名" style="width:200px;padding:8px;border:1.5px solid var(--border);border-radius:8px"><select name="map_target[]" style="padding:8px;border:1.5px solid var(--border);border-radius:8px"><?php foreach ($mapAll as $mt): ?><option value="<?=$mt?>" <?=$target===$mt?'selected':''?>><?=$mt?></option><?php endforeach; ?></select><button type="button" onclick="this.parentElement.remove()" style="border:none;background:none;color:var(--danger)">✕</button></div>
          <?php endforeach; endif; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="var d=document.createElement('div');d.style.cssText='display:flex;gap:8px;margin-bottom:6px';d.innerHTML='<input type=text name=map_source[] placeholder=外部列名 style=&quot;width:200px;padding:8px;border:1.5px solid var(--border);border-radius:8px&quot;><select name=map_target[] style=&quot;padding:8px;border:1.5px solid var(--border);border-radius:8px&quot;><?php foreach ($mapAll as $mt): ?><option value="<?=$mt?>"><?=$mt?></option><?php endforeach; ?></select><button type=button onclick=&quot;this.parentElement.remove()&quot; style=&quot;border:none;background:none;color:var(--danger)&quot;>✕</button>';document.getElementById('mapRows').appendChild(d)">+ 映射字段</button>
      </div>
      <div style="display:flex;gap:12px;align-items:center;margin-top:6px">
        <label style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" name="enabled" value="1" <?=empty($edit['enabled'] ?? 1) ? '' : 'checked'?> style="width:15px;height:15px"> 启用</label>
        <button class="btn btn-s btn-sm">保存</button>
        <a href="data-sync.php" class="btn btn-ghost btn-sm">取消</a>
      </div>
    </form>
    <?php else: ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>名称</th><th>类型</th><th>目标</th><th>来源</th><th>上次同步</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($conns)): ?><tr><td colspan="7" style="text-align:center;color:var(--faint)">暂无连接器，点击右上角新建</td></tr><?php endif; ?>
          <?php foreach ($conns as $c): ?>
          <tr>
            <td><b><?=htmlspecialchars($c['name'] ?? '')?></b><div style="font-size:11px;color:var(--faint)"><?=htmlspecialchars($c['id'] ?? '')?></div></td>
            <td><span class="badge badge-gray"><?=($c['kind'] ?? '')==='csv'?'CSV':'REST API'?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['target'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['source'] ?? '—')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($c['last_sync'] ?? '', 5, 11)) ?: '—'?></td>
            <td><span class="badge <?=!empty($c['enabled'])?'badge-green':'badge-gray'?>"><?=!empty($c['enabled'])?'启用':'停用'?></span><?php if (!empty($c['last_count'])): ?><span style="font-size:11px;color:var(--faint);margin-left:4px"><?=$c['last_count']?>行</span><?php endif; ?></td>
            <td style="white-space:nowrap"><a href="?sync=<?=urlencode($c['id'])?>" class="btn btn-s btn-sm" onclick="return confirm('立即同步该连接器?')">▶ 同步</a><a href="?edit=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="?delete=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('删除?')">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-top:16px">
      <h2 style="margin-bottom:8px">📋 同步日志（<?=count($log)?>）</h2>
      <?php if (empty($log)): ?><p class="text-sm text-muted">暂无同步记录</p>
      <?php else: ?>
      <div style="border:1px solid var(--border);border-radius:10px;overflow:auto;max-height:320px">
        <table style="font-size:12px">
          <thead><tr><th>时间</th><th>连接器</th><th>状态</th><th>详情</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($log, 0, 50) as $e): ?>
            <tr>
              <td style="white-space:nowrap"><?=htmlspecialchars(substr($e['at'] ?? '', 5, 11))?></td>
              <td class="text-sm"><?=htmlspecialchars($e['connector_id'] ?? '')?></td>
              <td><span class="badge <?=($e['status']??'')==='ok'?'badge-green':'badge-red'?>"><?=htmlspecialchars($e['status'] ?? '')?></span></td>
              <td class="text-sm" style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(json_encode($e['rows'] ?? $e['error'] ?? '', JSON_UNESCAPED_UNICODE))?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
