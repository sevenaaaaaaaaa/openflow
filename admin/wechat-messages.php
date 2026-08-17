<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/WechatMp.php';
require_login();
require_perm('wechat-mp');

$cfg = WechatMp::config();
$appid = $cfg['appid'] ?? '';
$message = '';
$error = '';

$logFile = DATA_DIR . '/wechat-msg-log.json';
$log = json_read($logFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';

    // 客服消息（48h 窗口私信）
    if ($do === 'kf_send') {
        $openid = trim($_POST['openid'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (empty($openid) || empty($content)) { $error = 'OpenID 和内容必填'; }
        else {
            $r = WechatMp::sendKfText($openid, $content);
            if (($r['errcode'] ?? 1) === 0) {
                $message = '客服消息已发送';
                $log[] = ['time' => date('Y-m-d H:i:s'), 'do' => '客服消息', 'openid' => $openid, 'preview' => mb_substr($content, 0, 50), 'status' => 'sent'];
                json_write($logFile, array_slice($log, -100));
            } else $error = '发送失败: ' . ($r['errmsg'] ?? '未知');
        }
    }

    // 模板消息
    if ($do === 'tpl_send') {
        $openid = trim($_POST['openid'] ?? '');
        $templateId = trim($_POST['template_id'] ?? '');
        $fieldNames = $_POST['tpl_field'] ?? [];
        $fieldValues = $_POST['tpl_value'] ?? [];
        $url = trim($_POST['url'] ?? '');
        if (empty($openid) || empty($templateId)) { $error = 'OpenID 和模板 ID 必填'; }
        else {
            $data = [];
            foreach ($fieldNames as $i => $fname) {
                $fname = trim($fname); if (!$fname) continue;
                $data[$fname] = ['value' => $fieldValues[$i] ?? ''];
            }
            if (empty($data)) { $error = '请填写至少一个模板字段'; }
            else {
                $r = WechatMp::sendTemplate($openid, $templateId, $data, $url);
                if (($r['errcode'] ?? 1) === 0) {
                    $message = '模板消息已发送';
                    $log[] = ['time' => date('Y-m-d H:i:s'), 'do' => '模板消息', 'openid' => $openid, 'preview' => $templateId, 'status' => 'sent'];
                    json_write($logFile, array_slice($log, -100));
                } else $error = '发送失败: ' . ($r['errmsg'] ?? '未知') . ' (' . ($r['errcode'] ?? -1) . ')';
            }
        }
    }
}

admin_header('客服消息');
?>
<div class="admin-layout">
  <?php admin_sidebar('wechat-messages'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 客服消息 & 模板消息</h1>
      <div class="flex gap-2 ml-auto">
        <a href="wechat-send.php" class="btn btn-ghost btn-sm">群发</a>
      </div>
    </div>
    <p class="sub">48 小时客服消息窗口私信 · 模板消息（订单/通知/提醒）</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>
    <?php if (!$appid): ?><?=msg('error', '请先在「公众号设置」配置 AppID/AppSecret')?><?php endif; ?>

    <!-- 客服消息 -->
    <div class="card" style="margin-bottom:24px">
      <h2>💬 客服消息</h2>
      <p class="text-sm text-muted mb-4">向单个用户发送私信（48 小时内有交互的用户可收到）</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="kf_send">
        <div class="field"><label>用户 OpenID</label><input type="text" name="openid" placeholder="用户的 openid"></div>
        <div class="field"><label>消息内容</label><textarea name="content" rows="3" placeholder="输入私信内容…"></textarea></div>
        <button type="submit" class="btn btn-primary">发送客服消息</button>
      </form>
    </div>

    <!-- 模板消息 -->
    <div class="card" style="margin-bottom:24px">
      <h2>📋 模板消息</h2>
      <p class="text-sm text-muted mb-4">模板消息不受 48h 限制，用于订单通知/进度提醒等（需在公众平台开通模板消息并获取模板 ID）</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="tpl_send">
        <div class="field-row">
          <div class="field"><label>用户 OpenID</label><input type="text" name="openid" placeholder="用户的 openid"></div>
          <div class="field"><label>模板 ID</label><input type="text" name="template_id" placeholder="模板消息 ID"></div>
        </div>
        <div class="field"><label>跳转链接（可选）</label><input type="text" name="url" placeholder="https://..."></div>
        <div class="field"><label>模板字段</label><div id="tplFields">
          <div class="tpl-row" style="display:flex;gap:8px;margin-bottom:6px">
            <input type="text" name="tpl_field[]" placeholder="字段名 (如 first/thing1/remark)" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <input type="text" name="tpl_value[]" placeholder="字段值" style="flex:1.5;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.tpl-row').remove()">✕</button>
          </div>
        </div></div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addTplRow()">+ 添加字段</button>
        <div style="margin-top:12px"><button type="submit" class="btn btn-primary">发送模板消息</button></div>
      </form>
    </div>

    <!-- 操作记录 -->
    <?php if ($log): ?>
    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">🕘 发送记录</h2></div>
      <table>
        <thead><tr><th>时间</th><th>类型</th><th>OpenID</th><th>内容</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($log) as $l): ?>
          <tr>
            <td class="text-sm"><?=htmlspecialchars($l['time'])?></td>
            <td><span class="badge badge-gray"><?=$l['do']?></span></td>
            <td><code style="font-size:11px"><?=htmlspecialchars($l['openid'])?></code></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['preview'])?></td>
            <td><span class="badge badge-green"><?=$l['status']?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function addTplRow() {
  var div = document.createElement('div');
  div.className = 'tpl-row';
  div.style.cssText = 'display:flex;gap:8px;margin-bottom:6px';
  div.innerHTML =
    '<input type="text" name="tpl_field[]" placeholder="字段名" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px">' +
    '<input type="text" name="tpl_value[]" placeholder="字段值" style="flex:1.5;padding:7px;border:1.5px solid var(--border);border-radius:8px">' +
    '<button type="button" class="btn btn-ghost btn-sm" onclick="this.closest(\'.tpl-row\').remove()">✕</button>';
  document.getElementById('tplFields').appendChild(div);
}
</script>
<?php admin_footer(); ?>
