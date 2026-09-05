<?php
/**
 * 脚本 & 埋点管理 — 全局 head/body 脚本注入
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$scriptsFile = DATA_DIR . '/scripts.json';
$scripts = json_read($scriptsFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $scripts = [];
    foreach (($_POST['name'] ?? []) as $i => $name) {
        if (empty(trim($name))) continue;
        $scripts[] = [
            'id' => 'script_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'name' => trim($name),
            'position' => $_POST['position'][$i] ?? 'head',
            'type' => $_POST['type'][$i] ?? 'inline',
            'content' => $_POST['content'][$i] ?? '',
            'page_scope' => $_POST['page_scope'][$i] ?? 'all',
            'page_paths' => $_POST['page_paths'][$i] ?? '',
            'enabled' => isset($_POST['enabled'][$i]),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
    json_write($scriptsFile, $scripts);
    $message = '脚本配置已保存';
}

admin_header('脚本与埋点');
?>
<div class="admin-layout">
  <?php admin_sidebar('scripts'); ?>
  <div class="main">
    <h1> 脚本 & 埋点管理</h1>
    <p class="sub">统一管理统计 SDK、JS 脚本、埋点代码 · 保存后自动注入全站，前端无需维护</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.08))">
      <h2 style="font-size:15px">📡 通用埋点快捷入口</h2>
      <p class="text-sm text-muted mb-4">选择平台自动填入脚本模板，只需补充你的统计 ID</p>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('google')">+ Google Analytics</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('baidu')">+ 百度统计</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('gtm')">+ Google Tag Manager</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('clarity')">+ Microsoft Clarity</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('hotjar')">+ Hotjar</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('tencent')">+ 腾讯分析</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('openflow_cdp')">+ OpenFlow CDP</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPreset('custom')">+ 自定义</button>
      </div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>📋 已启用的脚本</h2>
        <p class="text-sm text-muted mb-4">每条脚本按顺序注入 · 支持指定页面范围</p>
        <div id="scriptList">
          <?php foreach ($scripts as $i => $s): ?>
          <div class="script-row" style="border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;background:var(--surface)">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
              <input type="text" name="name[]" value="<?=htmlspecialchars($s['name'])?>" placeholder="脚本名称" style="flex:1;padding:7px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
              <select name="position[]" style="padding:7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px"><option value="head" <?=$s['position']==='head'?'selected':''?>>head</option><option value="body" <?=$s['position']==='body'?'selected':''?>>body</option></select>
              <select name="type[]" style="padding:7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px"><option value="inline" <?=$s['type']==='inline'?'selected':''?>>内联代码</option><option value="url" <?=$s['type']==='url'?'selected':''?>>外部 URL</option></select>
              <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="enabled[]" value="1" <?=$s['enabled']?'checked':''?> style="width:15px;height:15px">启用</label>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.script-row').remove()">✕</button>
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
              <select name="page_scope[]" style="padding:7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px"><option value="all" <?=($s['page_scope']??'all')==='all'?'selected':''?>>全部页面</option><option value="specific" <?=($s['page_scope']??'')==='specific'?'selected':''?>>指定页面</option></select>
              <input type="text" name="page_paths[]" value="<?=htmlspecialchars($s['page_paths'])?>" placeholder="指定路径，逗号分隔，如 /article, /community" style="flex:1;padding:7px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
            </div>
            <textarea name="content[]" rows="3" placeholder="脚本内容（type=URL 时填 https://... 地址）" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-family:var(--mono)"><?=htmlspecialchars($s['content'])?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addScript()">+ 添加脚本</button>
        <div style="margin-top:12px"><button type="submit" name="save" class="btn btn-primary">保存全部脚本</button></div>
      </div>
    </form>

    <div class="card">
      <h2>🔌 如何接入</h2>
      <p class="text-sm text-muted mb-4">所有前端页面只需在 <code>&lt;head&gt;</code> 引入一行（已加到各页面）：</p>
      <pre style="background:#1e1e1e;color:#fff;padding:12px;border-radius:8px;font-size:13px">&lt;script src="/assets/inject.js?v=20260830b" data-site-inject&gt;&lt;/script&gt;</pre>
      <p class="text-sm text-muted mt-4">之后在此页面添加的脚本会自动注入到启用它的页面，无需再改前端代码。</p>
    </div>
  </div>
</div>

<script>
function addScript() {
  var list = document.getElementById('scriptList');
  var d = document.createElement('div');
  d.className = 'script-row';
  d.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;background:var(--surface)';
  d.innerHTML =
    '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">' +
      '<input type="text" name="name[]" placeholder="脚本名称" style="flex:1;padding:7px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
      '<select name="position[]" style="padding:7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px"><option value="head">head</option><option value="body">body</option></select>' +
      '<select name="type[]" style="padding:7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px"><option value="inline">内联代码</option><option value="url">外部 URL</option></select>' +
      '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="enabled[]" value="1" checked style="width:15px;height:15px">启用</label>' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.script-row\').remove()">✕</button>' +
    '</div>' +
    '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">' +
      '<select name="page_scope[]" style="padding:7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px"><option value="all">全部页面</option><option value="specific">指定页面</option></select>' +
      '<input type="text" name="page_paths[]" placeholder="指定路径，逗号分隔" style="flex:1;padding:7px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
    '</div>' +
    '<textarea name="content[]" rows="3" placeholder="脚本内容（type=URL 时填 https://... 地址）" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-family:var(--mono)"></textarea>';
  list.appendChild(d);
}
function addPreset(kind) {
  addScript();
  var rows = document.querySelectorAll('.script-row');
  var row = rows[rows.length - 1];
  var presets = {
    google: { name: 'Google Analytics', content: '<!-- Google tag (gtag.js) -->\n<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"><\/script>\n<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag("js", new Date());\ngtag("config", "G-XXXXXXXXXX");\n<\/script>' },
    baidu: { name: '百度统计', content: '<script>\nvar _hmt = _hmt || [];\n(function() {\nvar hm = document.createElement("script");\nhm.src = "https://hm.baidu.com/hm.js?HMACCOUNT";\nvar s = document.getElementsByTagName("script")[0];\ns.parentNode.insertBefore(hm, s);\n})();\n<\/script>' },
    gtm: { name: 'Google Tag Manager', content: '<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","GTM-XXXXXXX");<\/script>' },
    clarity: { name: 'Microsoft Clarity', content: '<script>\n(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","YOUR_CLARITY_ID");\n<\/script>' },
    hotjar: { name: 'Hotjar', content: '<script>\n(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:YOUR_ID,hjsv:6};a=o.getElementsByTagName("head")[0];r=o.createElement("script");r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,"https://static.hotjar.com/c/hotjar-","%2F","|");\n<\/script>' },
    tencent: { name: '腾讯分析', content: '<script>\nvar _mtac = {};\n(function() {\nvar mta = document.createElement("script");\nmta.src = "//pingjs.qq.com/h5/stats.js?v2.0.4";\nmta.setAttribute("name", "MTAH5");\nmta.setAttribute("sid", "YOUR_SID");\nvar s = document.getElementsByTagName("script")[0];\ns.parentNode.insertBefore(mta, s);\n})();\n<\/script>' },
    openflow_cdp: { name: 'OpenFlow CDP 埋点', content: '/assets/cdp-track.js' },
    openflow_ga_bridge: { name: 'GA4/Segment→CDP 数据桥（P1-2）', content: '<script>(function(){var dl=window.dataLayer=window.dataLayer||[],op=dl.push||function(){};dl.push=function(){var a=Array.prototype.slice.call(arguments);try{var e=a[0];if(e&&e.event&&!String(e.event).slice(0,5)!=="gtm."){var t=new Date().getTime();fetch("/api/track.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({event:e.event,props:Object.assign({},e,{event_time:t}),message_id:("ga_"+t+"_"+Math.random().toString(36).slice(2,8))})});}}catch(_){}}return op.apply(dl,a);};})();<\/script>' },
    custom: { name: '自定义脚本', content: '' }
  };
  var p = presets[kind];
  row.querySelector('input[name="name[]"]').value = p.name;
  row.querySelector('textarea[name="content[]"]').value = p.content;
  if (kind === 'openflow_cdp') {
    var typeSel = row.querySelector('select[name="type[]"]');
    if (typeSel) typeSel.value = 'url';
    row.querySelector('select[name="position[]"]').value = 'head';
  }
}
</script>
<?php admin_footer(); ?>
