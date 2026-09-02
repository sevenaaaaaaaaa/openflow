<?php
/**
 * 入站数据连接器 — 外部系统向本站推送数据（CRM线索 / CDP事件 / 画像）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/InboundReceiver.php';
require_login();
require_perm('settings');

$conns = inbound_connectors();
$message = '';

// 保存/新增
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
        'type' => $_POST['type'] ?? 'lead',
        'secret' => trim($_POST['secret'] ?? ''),
        'enabled' => isset($_POST['enabled']),
        'mapping' => $mapping,
        'event' => trim($_POST['event'] ?? ''),
        'source' => trim($_POST['source'] ?? ''),
        'tags' => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
        'properties' => array_filter(array_map('trim', explode(',', $_POST['properties'] ?? ''))),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($conn['name'])) { $message = '名称必填'; }
    else {
        $found = false;
        foreach ($conns as &$c) if (($c['id'] ?? '') === $id) { $c = $conn; $found = true; break; }
        unset($c);
        if (!$found) { $conn['created_at'] = date('Y-m-d H:i:s'); $conns[] = $conn; }
        inbound_save_connectors($conns);
        $message = '连接器已保存';
    }
}

// 生成 secret
if (isset($_GET['gen_secret'])) {
    echo json_encode(['secret' => bin2hex(random_bytes(16))]);
    exit;
}

// 删除
if (isset($_GET['delete'])) {
    $conns = array_values(array_filter($conns, fn($c) => ($c['id'] ?? '') !== $_GET['delete']));
    inbound_save_connectors($conns);
    header('Location: /xmp/inbound');
    exit;
}

// 编辑回填
$edit = null;
if (isset($_GET['edit'])) { foreach ($conns as $c) if (($c['id'] ?? '') === $_GET['edit']) { $edit = $c; break; } }

$log = json_read(inbound_log_file());

admin_header('入站连接器');
?>
<div class="admin-layout">
  <?php admin_sidebar('inbound'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>入站数据连接器</h1><p class="v-sub">外部系统向本站推送数据 · lead→CRM 线索 / cdp_event→CDP 事件 / contact→画像属性</p></div>
      <?php if (!$edit): ?><div class="v-actions"><a href="?edit=new" class="btn btn-s btn-sm">+ 新建连接器</a></div><?php endif; ?>
    </div>
    <?php if ($message): ?><?=msg($message === '连接器已保存' ? 'success' : 'error', $message)?><?php endif; ?>

    <?php if ($edit): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'] ?? 'conn_' . substr(bin2hex(random_bytes(6)), 0, 8))?>">
      <h2 style="margin-bottom:16px"><?=($edit['id'] ?? '') ? '编辑连接器' : '新建连接器'?></h2>
      <div class="field-row">
        <div class="field"><label>名称 *</label><input type="text" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" placeholder="如：外部 CRM / 巨量回传 / 伙伴系统"></div>
        <div class="field"><label>类型</label><select name="type">
          <option value="lead" <?=($edit['type'] ?? '')==='lead'?'selected':''?>>lead · CRM 线索</option>
          <option value="cdp_event" <?=($edit['type'] ?? '')==='cdp_event'?'selected':''?>>cdp_event · CDP 行为事件</option>
          <option value="contact" <?=($edit['type'] ?? '')==='contact'?'selected':''?>>contact · 画像属性</option>
        </select></div>
      </div>
      <div class="field-row">
        <div class="field"><label>签名密钥（留空不校验）</label>
          <div style="display:flex;gap:8px"><input type="text" name="secret" id="secInput" value="<?=htmlspecialchars($edit['secret'] ?? '')?>" placeholder="HMAC-SHA256 secret"><button type="button" class="btn btn-ghost btn-sm" onclick="fetch('?gen_secret=1').then(r=>r.json()).then(d=>document.getElementById('secInput').value=d.secret)">生成</button></div>
        </div>
        <div class="field"><label>来源标记 <span class="hint">· 线索 source / 事件属性</span></label><input type="text" name="source" value="<?=htmlspecialchars($edit['source'] ?? '')?>" placeholder="如：external_crm"></div>
      </div>
      <?php if (($edit['type'] ?? 'lead') === 'cdp_event'): ?>
      <div class="field-row">
        <div class="field"><label>事件名 <span class="hint">· 留空用 payload.event</span></label><input type="text" name="event" value="<?=htmlspecialchars($edit['event'] ?? '')?>" placeholder="如：external_order"></div>
        <div class="field"><label>透传属性 <span class="hint">· 逗号分隔，留空透传全部</span></label><input type="text" name="properties" value="<?=htmlspecialchars(implode(',', $edit['properties'] ?? []))?>" placeholder="order_id,amount,plan"></div>
      </div>
      <?php endif; ?>
      <div class="field"><label>自动打标签 <span class="hint">· 逗号分隔，仅 lead 类型</span></label><input type="text" name="tags" value="<?=htmlspecialchars(implode(',', $edit['tags'] ?? []))?>" placeholder="外部线索,重点跟进"></div>
      <div class="field"><label>字段映射 <span class="hint">· 外部字段名 → 内部字段</span></label>
        <div id="mapRows">
          <?php $editMap = $edit['mapping'] ?? []; $mapAll = ['email','name','phone','company','source','event','visitor_id']; if (empty($editMap)): ?>
          <div style="display:flex;gap:8px;margin-bottom:6px"><input type="text" name="map_source[]" placeholder="外部字段名" style="width:200px;padding:8px;border:1.5px solid var(--border);border-radius:8px"><select name="map_target[]" style="padding:8px;border:1.5px solid var(--border);border-radius:8px"><?php foreach ($mapAll as $mt): ?><option value="<?=$mt?>"><?=$mt?></option><?php endforeach; ?></select><button type="button" onclick="this.parentElement.remove()" style="border:none;background:none;color:var(--danger)">✕</button></div>
          <?php else: foreach ($editMap as $target => $source): ?>
          <div style="display:flex;gap:8px;margin-bottom:6px"><input type="text" name="map_source[]" value="<?=htmlspecialchars($source)?>" placeholder="外部字段名" style="width:200px;padding:8px;border:1.5px solid var(--border);border-radius:8px"><select name="map_target[]" style="padding:8px;border:1.5px solid var(--border);border-radius:8px"><?php foreach ($mapAll as $mt): ?><option value="<?=$mt?>" <?=$target===$mt?'selected':''?>><?=$mt?></option><?php endforeach; ?></select><button type="button" onclick="this.parentElement.remove()" style="border:none;background:none;color:var(--danger)">✕</button></div>
          <?php endforeach; endif; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="var d=document.createElement('div');d.style.cssText='display:flex;gap:8px;margin-bottom:6px';d.innerHTML='<input type=text name=map_source[] placeholder=外部字段名 style=&quot;width:200px;padding:8px;border:1.5px solid var(--border);border-radius:8px&quot;><select name=map_target[] style=&quot;padding:8px;border:1.5px solid var(--border);border-radius:8px&quot;><?php foreach ($mapAll as $mt): ?><option value="<?=$mt?>"><?=$mt?></option><?php endforeach; ?></select><button type=button onclick=&quot;this.parentElement.remove()&quot; style=&quot;border:none;background:none;color:var(--danger)&quot;>✕</button>';document.getElementById('mapRows').appendChild(d)">+ 映射字段</button>
      </div>
      <div style="display:flex;gap:12px;align-items:center;margin-top:6px">
        <label style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" name="enabled" value="1" <?=empty($edit['enabled'] ?? 1) ? '' : 'checked'?> style="width:15px;height:15px"> 启用</label>
        <button class="btn btn-s btn-sm">保存连接器</button>
        <a href="inbound.php" class="btn btn-ghost btn-sm">取消</a>
      </div>
    </form>

    <div class="card" style="margin-top:16px">
      <h2 style="margin-bottom:8px">📮 调用方式</h2>
      <pre style="background:var(--bg);padding:14px;border-radius:10px;font-size:12px;overflow:auto;line-height:1.7">POST <?=site_url_base()?>/api/webhook.php
Headers:
  X-Inbound-Id: <b><?=htmlspecialchars($edit['id'] ?? 'conn_xxx')?></b>
  X-Inbound-Signature: hash_hmac('sha256', body, secret)
  Content-Type: application/json

Body: {"email":"a@b.com","name":"外部用户","company":"XX 公司"}  // lead 示例
      {"event":"external_order","order_id":"O-1","amount":99}      // cdp_event 示例</pre>
    </div>
    <?php else: ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>名称</th><th>类型</th><th>来源</th><th>映射</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($conns)): ?><tr><td colspan="6" style="text-align:center;color:var(--faint)">暂无连接器，点击右上角新建</td></tr><?php endif; ?>
          <?php foreach ($conns as $c): ?>
          <tr>
            <td><b><?=htmlspecialchars($c['name'] ?? '')?></b><div style="font-size:11px;color:var(--faint)"><?=htmlspecialchars($c['id'] ?? '')?></div></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($c['type'] ?? 'lead')?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['source'] ?? '—')?></td>
            <td class="text-sm text-muted"><?=count($c['mapping'] ?? [])?> 字段</td>
            <td><span class="badge <?=!empty($c['enabled'])?'badge-green':'badge-gray'?>"><?=!empty($c['enabled'])?'启用':'停用'?></span></td>
            <td style="white-space:nowrap"><a href="?edit=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="?delete=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" data-confirm="删除该连接器?">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-top:16px">
      <h2 style="margin-bottom:8px">📋 最近处理日志（<?=count($log)?>）</h2>
      <?php if (empty($log)): ?><p class="text-sm text-muted">暂无入站记录</p>
      <?php else: ?>
      <div style="border:1px solid var(--border);border-radius:10px;overflow:auto;max-height:360px">
        <table style="font-size:12px">
          <thead><tr><th>时间</th><th>连接器</th><th>类型</th><th>状态</th><th>详情</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($log, 0, 50) as $e): ?>
            <tr>
              <td style="white-space:nowrap"><?=htmlspecialchars(substr($e['at'] ?? '', 5, 11))?></td>
              <td class="text-sm"><?=htmlspecialchars($e['connector_id'] ?? '')?></td>
              <td><?=htmlspecialchars($e['type'] ?? '')?></td>
              <td><span class="badge <?=($e['status']??'')==='ok'?'badge-green':'badge-red'?>"><?=htmlspecialchars($e['status'] ?? '')?></span></td>
              <td class="text-sm" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(json_encode($e['detail'] ?? $e['error'] ?? '', JSON_UNESCAPED_UNICODE))?></td>
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
