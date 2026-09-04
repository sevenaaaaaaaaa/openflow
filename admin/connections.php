<?php
/**
 * 连接 —— 开放能力的后台（主线 A）
 *
 * 连接 = 对某个外部服务的凭据 + 基址；动作 = 绑定连接的请求模板；
 * 模板 = 剥掉秘钥的连接+动作定义，可导入导出分享。
 * 秘钥只在保存时进来一次，之后界面上只看得到末 4 位。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Connections.php';
require_once __DIR__ . '/../lib/ConnectionActions.php';
require_once __DIR__ . '/../lib/OAuth2Client.php';
require_login();
require_perm('settings');

$message = ''; $error = ''; $testResult = null;
if (!empty($_GET['msg'])) { ($_GET['kind'] ?? '') === 'error' ? $error = (string)$_GET['msg'] : $message = (string)$_GET['msg']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_conn') {
        $auth = ['type' => $_POST['auth_type'] ?? 'none'];
        foreach (['in', 'name', 'value', 'token', 'user', 'pass', 'auth_url', 'token_url', 'client_id', 'client_secret', 'scopes'] as $f) {
            if (isset($_POST['auth_' . $f])) $auth[$f] = (string)$_POST['auth_' . $f];
        }
        $headers = [];
        foreach (array_filter(array_map('trim', explode("\n", (string)($_POST['headers'] ?? '')))) as $ln) {
            if (str_contains($ln, ':')) { [$k, $v] = explode(':', $ln, 2); $headers[trim($k)] = trim($v); }
        }
        $r = conn_save([
            'id' => $_POST['id'] ?? '', 'name' => $_POST['name'] ?? '', 'note' => $_POST['note'] ?? '',
            'base_url' => $_POST['base_url'] ?? '', 'auth' => $auth, 'headers' => $headers,
            'allow_private' => isset($_POST['allow_private']), 'enabled' => isset($_POST['enabled']),
        ]);
        if ($r['ok']) { header('Location: /xmp/connections?edit=' . urlencode($r['conn']['id']) . '&msg=' . urlencode('连接已保存')); exit; }
        $error = $r['error'];
    } elseif ($act === 'delete_conn') {
        foreach (action_for_conn((string)$_POST['id']) as $a) action_delete($a['id']);
        conn_delete((string)$_POST['id']) ? $message = '连接及其动作已删除' : $error = '连接不存在';
    } elseif ($act === 'test_conn') {
        // 测试请求：GET 基址下的一个路径，看鉴权与网络通不通
        $testResult = conn_request((string)$_POST['id'], (string)($_POST['test_method'] ?? 'GET'), (string)($_POST['test_path'] ?? '/'));
    } elseif ($act === 'oauth_begin') {
        $c = conn_get((string)$_POST['id']);
        $r = $c ? oauth2_begin($c) : ['ok' => false, 'error' => '连接不存在'];
        if ($r['ok']) { header('Location: ' . $r['url']); exit; }
        $error = $r['error'];
    } elseif ($act === 'save_action') {
        $q = [];
        foreach (array_filter(array_map('trim', explode("\n", (string)($_POST['query'] ?? '')))) as $ln) {
            if (str_contains($ln, '=')) { [$k, $v] = explode('=', $ln, 2); $q[trim($k)] = trim($v); }
        }
        $r = action_save([
            'id' => $_POST['aid'] ?? '', 'conn_id' => $_POST['conn_id'] ?? '', 'name' => $_POST['aname'] ?? '',
            'method' => $_POST['method'] ?? 'POST', 'path' => $_POST['path'] ?? '/', 'query' => $q,
            'body' => $_POST['body'] ?? '', 'body_mode' => $_POST['body_mode'] ?? 'json',
            'enabled' => isset($_POST['aenabled']), 'note' => $_POST['anote'] ?? '',
        ]);
        $r['ok'] ? $message = '动作已保存' : $error = $r['error'];
        if ($r['ok']) { header('Location: /xmp/connections?edit=' . urlencode((string)$_POST['conn_id']) . '&msg=' . urlencode('动作已保存')); exit; }
    } elseif ($act === 'delete_action') {
        action_delete((string)$_POST['aid']) ? $message = '动作已删除' : $error = '动作不存在';
    } elseif ($act === 'run_action') {
        // 手动试跑：用一份示例上下文
        $ctx = json_decode((string)($_POST['ctx'] ?? '{}'), true);
        if (!is_array($ctx)) { $error = '示例上下文不是合法 JSON'; }
        else $testResult = action_run((string)$_POST['aid'], $ctx, true);
    } elseif ($act === 'import_template') {
        $tpl = null;
        if (!empty($_POST['bundled'])) {
            foreach (conn_bundled_templates() as $t) if (($t['_file'] ?? '') === $_POST['bundled']) { $tpl = $t; break; }
        } elseif (!empty($_POST['template_json'])) {
            $tpl = json_decode((string)$_POST['template_json'], true);
        }
        if (!is_array($tpl)) $error = '没有可导入的模板';
        else {
            $r = conn_template_import($tpl);
            if ($r['ok']) { header('Location: /xmp/connections?edit=' . urlencode($r['conn']['id']) . '&msg=' . urlencode('已导入为停用状态，请填好秘钥、核对地址后再启用')); exit; }
            $error = $r['error'];
        }
    } elseif ($act === 'export_template') {
        $tpl = conn_template_export((string)$_POST['id'], action_for_conn((string)$_POST['id']));
        if ($tpl) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="openflow-connection-' . preg_replace('/[^a-z0-9-]+/i', '-', $tpl['name']) . '.json"');
            echo json_encode($tpl, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit;
        }
        $error = '连接不存在';
    }
}

$conns = conn_all();
$editId = (string)($_GET['edit'] ?? '');
$edit = $editId !== '' ? conn_get($editId) : null;
$editDisp = $edit ? conn_for_display($edit) : null;
$editActions = $edit ? action_for_conn($edit['id']) : [];
$recent = conn_recent_calls(30, $editId);

admin_header('连接');
?>
<style>
.cx-grid{display:grid;grid-template-columns:minmax(0,300px) minmax(0,1fr);gap:22px;align-items:start}
@media(max-width:960px){.cx-grid{grid-template-columns:1fr}}
.cx-item{display:block;padding:10px 12px;border-radius:9px;text-decoration:none;color:inherit;border:1px solid transparent}
.cx-item:hover{background:var(--hover)}.cx-item.on{border-color:var(--accent);background:var(--accent-soft)}
.cx-item small{display:block;color:var(--muted);font-size:12px;margin-top:2px}
.cx-auth{display:none}.cx-auth.on{display:contents}
.cx-secret{font-family:var(--font-mono);font-size:12px;color:var(--muted)}
.cx-log{font-family:var(--font-mono);font-size:12px}
.cx-log td{padding:4px 8px;border-bottom:1px solid var(--border)}
.cx-res{background:var(--hover);border-radius:8px;padding:12px;font-family:var(--font-mono);font-size:12.5px;white-space:pre-wrap;word-break:break-all;max-height:300px;overflow:auto}
.cx-act{border:1px solid var(--border);border-radius:9px;padding:12px 14px;margin-bottom:10px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <h1>连接</h1>
    <p class="sub">把任何外部服务接进来：一份凭据 + 一个基址就是一个连接；给它定义几个动作，自动化和画布里就能直接用。
      接法可以导出成模板分享给别人——秘钥会被剥掉。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="cx-grid">
      <div>
        <div class="card" style="padding:10px">
          <div class="sb-panel-h" style="padding:6px 10px 8px;display:flex;justify-content:space-between;align-items:center">
            <span>连接</span><a class="btn btn-primary btn-sm" href="/xmp/connections?edit=new">+ 新建</a>
          </div>
          <?php foreach ($conns as $c): $st = oauth2_status($c); ?>
          <a class="cx-item <?=$editId === $c['id'] ? 'on' : ''?>" href="/xmp/connections?edit=<?=urlencode($c['id'])?>">
            <b><?=htmlspecialchars($c['name'])?></b>
            <?php if (empty($c['enabled'])): ?><span class="badge badge-gray">停用</span><?php endif; ?>
            <small><?=htmlspecialchars(conn_auth_types()[$c['auth']['type'] ?? 'none'] ?? '')?>
              · <?=htmlspecialchars(parse_url((string)$c['base_url'], PHP_URL_HOST) ?: '（无基址）')?>
              <?php if ($st['state'] !== 'n/a'): ?> · <?=htmlspecialchars($st['label'])?><?php endif; ?></small>
          </a>
          <?php endforeach; ?>
          <?php if (!$conns): ?><p class="hint" style="padding:10px">还没有连接。从右边的模板开始最快。</p><?php endif; ?>
        </div>

        <div class="card">
          <h3 style="margin-top:0">从模板开始</h3>
          <p class="hint">这几个是随仓库带的示例，说明机制用的——改掉基址和路径就能接任何一家。导入后是停用状态，填好秘钥、核对过地址再启用。</p>
          <?php foreach (conn_bundled_templates() as $t): ?>
          <form method="post" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 0;border-top:1px solid var(--border)">
            <?=csrf_field()?><input type="hidden" name="action" value="import_template">
            <input type="hidden" name="bundled" value="<?=htmlspecialchars($t['_file'])?>">
            <span style="font-size:13.5px"><?=htmlspecialchars($t['name'])?> <span class="hint">· <?=count($t['actions'] ?? [])?> 个动作</span></span>
            <button class="btn btn-ghost btn-sm">导入</button>
          </form>
          <?php endforeach; ?>
          <details style="margin-top:10px"><summary class="hint" style="cursor:pointer">粘贴别人分享的模板 JSON</summary>
            <form method="post" style="margin-top:8px">
              <?=csrf_field()?><input type="hidden" name="action" value="import_template">
              <textarea class="inp" name="template_json" rows="5" placeholder='{"openflow_connection_template":1, ...}'></textarea>
              <button class="btn btn-sm" style="margin-top:6px">导入</button>
            </form>
          </details>
        </div>
      </div>

      <div>
        <?php if ($editId === 'new' || $edit): $e = $editDisp ?: ['auth' => ['type' => 'none'], 'enabled' => true]; $at = $e['auth']['type'] ?? 'none'; ?>
        <form method="post" class="card" id="connForm">
          <?=csrf_field()?><input type="hidden" name="action" value="save_conn">
          <input type="hidden" name="id" value="<?=htmlspecialchars($e['id'] ?? '')?>">
          <h3 style="margin-top:0"><?=$edit ? '编辑连接' : '新建连接'?></h3>
          <div class="form-grid">
            <label>名称<input class="inp" name="name" required value="<?=htmlspecialchars($e['name'] ?? '')?>" placeholder="例如：企业微信群机器人"></label>
            <label>基址<input class="inp" name="base_url" value="<?=htmlspecialchars($e['base_url'] ?? '')?>" placeholder="https://api.example.com"></label>
            <label>鉴权方式
              <select class="inp" name="auth_type" id="authType">
                <?php foreach (conn_auth_types() as $k => $v): ?><option value="<?=$k?>" <?=$at === $k ? 'selected' : ''?>><?=htmlspecialchars($v)?></option><?php endforeach; ?>
              </select>
            </label>
            <label>状态<span style="display:flex;gap:14px;align-items:center;height:38px;font-size:13.5px">
              <label><input type="checkbox" name="enabled" <?=!empty($e['enabled']) ? 'checked' : ''?>> 启用</label>
              <label title="自托管的内网服务才需要。模板导入永远不带这个开关。"><input type="checkbox" name="allow_private" <?=!empty($e['allow_private']) ? 'checked' : ''?>> 允许私网地址</label>
            </span></label>

            <?php $sec = fn($k) => !empty($e['auth'][$k . '_masked']) && $e['auth'][$k . '_masked'] !== '（未设置）' ? '已设置：' . $e['auth'][$k . '_masked'] . '（留空则不改）' : ''; ?>
            <div class="cx-auth" data-auth="api_key">
              <label>放在<select class="inp" name="auth_in"><option value="header" <?=($e['auth']['in'] ?? '') !== 'query' ? 'selected' : ''?>>请求头</option><option value="query" <?=($e['auth']['in'] ?? '') === 'query' ? 'selected' : ''?>>查询参数</option></select></label>
              <label>参数名<input class="inp" name="auth_name" value="<?=htmlspecialchars($e['auth']['name'] ?? '')?>" placeholder="X-API-Key 或 key"></label>
              <label style="grid-column:1/-1">API Key <span class="cx-secret"><?=htmlspecialchars($sec('value'))?></span><input class="inp" name="auth_value" type="password" autocomplete="new-password" placeholder="<?=$sec('value') ? '留空不改' : '粘贴 key'?>"></label>
            </div>
            <div class="cx-auth" data-auth="bearer">
              <label style="grid-column:1/-1">Token <span class="cx-secret"><?=htmlspecialchars($sec('token'))?></span><input class="inp" name="auth_token" type="password" autocomplete="new-password" placeholder="<?=$sec('token') ? '留空不改' : '粘贴 token'?>"></label>
            </div>
            <div class="cx-auth" data-auth="basic">
              <label>用户名<input class="inp" name="auth_user" value="<?=htmlspecialchars($e['auth']['user'] ?? '')?>"></label>
              <label>密码 <span class="cx-secret"><?=htmlspecialchars($sec('pass'))?></span><input class="inp" name="auth_pass" type="password" autocomplete="new-password"></label>
            </div>
            <div class="cx-auth" data-auth="oauth2">
              <label>授权地址 (auth_url)<input class="inp" name="auth_auth_url" value="<?=htmlspecialchars($e['auth']['auth_url'] ?? '')?>" placeholder="https://.../oauth/authorize"></label>
              <label>令牌地址 (token_url)<input class="inp" name="auth_token_url" value="<?=htmlspecialchars($e['auth']['token_url'] ?? '')?>" placeholder="https://.../oauth/token"></label>
              <label>Client ID<input class="inp" name="auth_client_id" value="<?=htmlspecialchars($e['auth']['client_id'] ?? '')?>"></label>
              <label>Client Secret <span class="cx-secret"><?=htmlspecialchars($sec('client_secret'))?></span><input class="inp" name="auth_client_secret" type="password" autocomplete="new-password" placeholder="公共客户端可留空（有 PKCE）"></label>
              <label style="grid-column:1/-1">Scopes<input class="inp" name="auth_scopes" value="<?=htmlspecialchars($e['auth']['scopes'] ?? '')?>" placeholder="空格分隔"></label>
              <div style="grid-column:1/-1" class="hint">回调地址填到对方的应用设置里：<code><?=htmlspecialchars(oauth2_callback_url())?></code>
                <?php if ($edit): $st = oauth2_status($edit); ?><br>当前：<?=htmlspecialchars($st['label'])?><?php endif; ?></div>
            </div>

            <label style="grid-column:1/-1">额外请求头 <span class="hint">每行一个 <code>Name: value</code></span>
              <textarea class="inp" name="headers" rows="2"><?php foreach ((array)($e['headers'] ?? []) as $k => $v) echo htmlspecialchars("$k: $v") . "\n"; ?></textarea></label>
            <label style="grid-column:1/-1">备注<input class="inp" name="note" value="<?=htmlspecialchars($e['note'] ?? '')?>"></label>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            <button class="btn btn-primary">保存连接</button>
            <?php if ($edit): ?>
              <?php if ($at === 'oauth2'): ?><button class="btn" name="action" value="oauth_begin">🔑 去授权 / 重新授权</button><?php endif; ?>
              <button class="btn btn-ghost" name="action" value="export_template">导出为模板（不含秘钥）</button>
              <button class="btn btn-danger" name="action" value="delete_conn" data-confirm="删除这个连接及其全部动作？引用它的自动化步骤会失效。">删除</button>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($edit): ?>
        <div class="card">
          <h3 style="margin-top:0">测试请求</h3>
          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?=csrf_field()?><input type="hidden" name="action" value="test_conn"><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>">
            <select class="inp" name="test_method" style="width:auto"><option>GET</option><option>POST</option></select>
            <input class="inp" name="test_path" value="/" style="flex:1;min-width:200px" placeholder="/v1/me">
            <button class="btn">发送</button>
          </form>
          <?php if ($testResult !== null && !isset($testResult['action'])): ?>
          <div class="cx-res" style="margin-top:10px"><?=htmlspecialchars(($testResult['ok'] ? '✓ ' : '✗ ') . 'HTTP ' . $testResult['status'] . ' · ' . $testResult['ms'] . 'ms' . ($testResult['error'] ? "\n" . $testResult['error'] : '') . "\n" . mb_substr((string)$testResult['body'], 0, 2000))?></div>
          <?php endif; ?>
        </div>

        <div class="card">
          <h3 style="margin-top:0">动作 <span class="hint">· 事件发生时按模板发请求，模板里用 <code>{{email}}</code> <code>{{amount}}</code> <code>{{member.id}}</code> 代入事件字段</span></h3>
          <?php foreach ($editActions as $a): ?>
          <div class="cx-act">
            <details>
              <summary style="cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px">
                <span><b><?=htmlspecialchars($a['name'])?></b> <span class="hint"><?=htmlspecialchars($a['method'] . ' ' . $a['path'])?></span>
                  <?php if (empty($a['enabled'])): ?><span class="badge badge-gray">停用</span><?php endif; ?>
                  <?php if ($a['last_ok'] !== null): ?><span class="badge badge-<?=$a['last_ok'] ? 'green' : 'orange'?>">上次 <?=$a['last_ok'] ? '成功' : '失败'?></span><?php endif; ?></span>
              </summary>
              <?=render_action_form($edit['id'], $a)?>
              <form method="post" style="margin-top:10px;display:flex;gap:8px;align-items:flex-start">
                <?=csrf_field()?><input type="hidden" name="action" value="run_action"><input type="hidden" name="aid" value="<?=htmlspecialchars($a['id'])?>">
                <textarea class="inp" name="ctx" rows="2" style="flex:1;font-family:var(--font-mono);font-size:12px" placeholder='示例上下文 JSON'>{"email":"test@example.com","name":"测试","amount":99,"order_id":"o_test","message":"来自 OpenFlow 的测试消息"}</textarea>
                <button class="btn">试跑</button>
              </form>
            </details>
          </div>
          <?php endforeach; ?>
          <?php if ($testResult !== null && isset($testResult['action'])): ?>
          <div class="cx-res"><?=htmlspecialchars('动作「' . $testResult['action'] . '」' . ($testResult['ok'] ? '✓ ' : '✗ ') . 'HTTP ' . $testResult['status'] . ' · ' . $testResult['ms'] . 'ms' . ($testResult['error'] ? "\n" . $testResult['error'] : '') . "\n" . mb_substr((string)($testResult['body'] ?? ''), 0, 2000))?></div>
          <?php endif; ?>
          <details style="margin-top:8px"><summary class="hint" style="cursor:pointer">+ 新建动作</summary><?=render_action_form($edit['id'], null)?></details>
        </div>

        <div class="card">
          <h3 style="margin-top:0">最近调用 <span class="hint">· 不记查询参数与响应体，错误信息已脱敏</span></h3>
          <?php if (!$recent): ?><p class="hint">还没有调用记录。</p><?php else: ?>
          <table class="cx-log" style="width:100%">
            <?php foreach ($recent as $l): ?>
            <tr><td><?=htmlspecialchars(substr($l['at'], 5, 11))?></td><td><?=htmlspecialchars($l['method'])?></td>
              <td style="word-break:break-all"><?=htmlspecialchars($l['url'])?></td>
              <td><span class="badge badge-<?=$l['ok'] ? 'green' : 'orange'?>"><?=(int)$l['status'] ?: '—'?></span></td>
              <td><?=(int)$l['ms']?>ms</td><td class="hint"><?=htmlspecialchars($l['error'])?></td></tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="of-empty">选一个连接，或新建一个。</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var sel = document.getElementById('authType'); if (!sel) return;
  function sync(){ document.querySelectorAll('.cx-auth').forEach(function(d){ d.classList.toggle('on', d.dataset.auth === sel.value); }); }
  sel.addEventListener('change', sync); sync();
})();
</script>
<?php
function render_action_form(string $connId, ?array $a): string {
    ob_start(); $a = $a ?: ['method' => 'POST', 'path' => '/', 'body_mode' => 'json', 'enabled' => true]; ?>
    <form method="post" class="form-grid" style="margin-top:10px">
      <?=csrf_field()?><input type="hidden" name="action" value="save_action">
      <input type="hidden" name="conn_id" value="<?=htmlspecialchars($connId)?>"><input type="hidden" name="aid" value="<?=htmlspecialchars($a['id'] ?? '')?>">
      <label>动作名<input class="inp" name="aname" required value="<?=htmlspecialchars($a['name'] ?? '')?>" placeholder="例如：新订单提醒"></label>
      <label>方法 & 路径<span style="display:flex;gap:6px">
        <select class="inp" name="method" style="width:auto"><?php foreach (['GET','POST','PUT','PATCH','DELETE'] as $m): ?><option <?=($a['method'] ?? '') === $m ? 'selected' : ''?>><?=$m?></option><?php endforeach; ?></select>
        <input class="inp" name="path" value="<?=htmlspecialchars($a['path'] ?? '/')?>" placeholder="/v1/contacts/{{member.id}}"></span></label>
      <label style="grid-column:1/-1">请求体 <span class="hint">JSON；叶子整段是 <code>{{x}}</code> 时保留原始类型</span>
        <textarea class="inp" name="body" rows="4" style="font-family:var(--font-mono);font-size:12.5px"><?=htmlspecialchars($a['body'] !== null && isset($a['body']) ? json_encode($a['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '')?></textarea></label>
      <label>请求体格式<select class="inp" name="body_mode"><option value="json" <?=($a['body_mode'] ?? 'json') === 'json' ? 'selected' : ''?>>JSON</option><option value="form" <?=($a['body_mode'] ?? '') === 'form' ? 'selected' : ''?>>表单</option></select></label>
      <label>查询参数 <span class="hint">每行 <code>k=v</code></span><textarea class="inp" name="query" rows="2"><?php foreach ((array)($a['query'] ?? []) as $k => $v) echo htmlspecialchars("$k=$v") . "\n"; ?></textarea></label>
      <label style="grid-column:1/-1">备注<input class="inp" name="anote" value="<?=htmlspecialchars($a['note'] ?? '')?>"></label>
      <div style="grid-column:1/-1;display:flex;gap:10px;align-items:center">
        <label><input type="checkbox" name="aenabled" <?=!empty($a['enabled']) ? 'checked' : ''?>> 启用</label>
        <button class="btn btn-primary btn-sm">保存动作</button>
        <?php if (!empty($a['id'])): ?><button class="btn btn-danger btn-sm" name="action" value="delete_action" data-confirm="删除这个动作？">删除</button><?php endif; ?>
      </div>
    </form>
    <?php return ob_get_clean();
}
admin_footer(); ?>
