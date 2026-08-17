<?php
/**
 * WeChat 公众号 & 小程序 配置管理
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$configFile = DATA_DIR . '/wechat.json';
$cfg = json_read($configFile);
$tab = $_GET['tab'] ?? 'mp';

// Defaults
$cfg += ['mp' => ['appid' => '', 'secret' => '', 'token' => '', 'encoding_aes_key' => '', 'menu_json' => '', 'auto_reply_welcome' => '', 'auto_reply_default' => ''], 'miniprogram' => ['appid' => '', 'secret' => '', 'legal' => ''], 'wecom' => ['corp_id' => '', 'secret' => '', 'agent_id' => '', 'token' => '', 'encoding_aes_key' => '']];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $type = $_POST['type'] ?? 'mp';
    if ($type === 'mp') {
        $cfg['mp'] = [
            'appid' => trim($_POST['appid'] ?? ''),
            'secret' => trim($_POST['appsecret'] ?? ''),
            'token' => trim($_POST['token'] ?? ''),
            'encoding_aes_key' => trim($_POST['encoding_aes_key'] ?? ''),
            'menu_json' => $_POST['menu_json'] ?? '',
            'auto_reply_welcome' => $_POST['auto_reply_welcome'] ?? '',
            'auto_reply_default' => $_POST['auto_reply_default'] ?? '',
        ];
    } elseif ($type === 'miniprogram') {
        $cfg['miniprogram'] = [
            'appid' => trim($_POST['mp_appid'] ?? ''),
            'secret' => trim($_POST['mp_secret'] ?? ''),
            'legal' => trim($_POST['mp_legal'] ?? ''),
        ];
    } elseif ($type === 'wecom') {
        $cfg['wecom'] = [
            'corp_id' => trim($_POST['wc_corp_id'] ?? ''),
            'secret' => trim($_POST['wc_secret'] ?? ''),
            'agent_id' => trim($_POST['wc_agent_id'] ?? ''),
            'token' => trim($_POST['wc_token'] ?? ''),
            'encoding_aes_key' => trim($_POST['wc_aes_key'] ?? ''),
        ];
    }
    json_write($configFile, $cfg);
    $message = $type === 'mp' ? '公众号配置已保存' : ($type === 'miniprogram' ? '小程序配置已保存' : '企业微信配置已保存');
    log_activity('update', 'setting', 'wechat', ($type === 'mp' ? '公众号' : ($type === 'miniprogram' ? '小程序' : '企业微信')) . ' 配置已更新');
}

$mpCfg = $cfg['mp'];
$mpCfg2 = $cfg['miniprogram'];
$wecomCfg = $cfg['wecom'];
$serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/wechat.php';

admin_header('WeChat 微信管理');
?>
<style>
.mp-status{display:flex;align-items:center;gap:8px;padding:10px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
.mp-status.ready{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.mp-status.pending{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.mp-status.off{background:var(--surface-2);color:var(--text-3)}
code.mp{background:var(--surface-2);padding:2px 8px;border-radius:4px;font-size:12px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('wechat-mp'); ?>
  <div class="main">
    <h1> WeChat 微信管理</h1>
    <p class="sub">公众号 & 小程序配置 · 授权完成后即可使用</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="tabs">
      <a class="<?=$tab==='mp'?'active':''?>" href="?tab=mp">公众号</a>
      <a class="<?=$tab==='miniprogram'?'active':''?>" href="?tab=miniprogram">小程序</a>
      <a class="<?=$tab==='wecom'?'active':''?>" href="?tab=wecom">企业微信</a>
    </div>

<?php if ($tab === 'mp'): ?>
    <!-- ────── 公众号 ────── -->
    <?php
    $mpReady = !empty($mpCfg['appid']) && !empty($mpCfg['secret']);
    $mpTokenOk = !empty($mpCfg['token']);
    ?>
    <?php if ($mpReady): ?>
    <div class="mp-status ready">✅ 公众号已配置 · AppID: <?=htmlspecialchars(substr($mpCfg['appid'], 0, 6))?>… · 可进行服务器验证与消息接收</div>
    <?php elseif (!empty($mpCfg['appid']) || !empty($mpCfg['secret'])): ?>
    <div class="mp-status pending">⚠️ 部分配置已完成，请补充 AppID 与 AppSecret</div>
    <?php else: ?>
    <div class="mp-status off">🔒 公众号尚未配置 · 请前往微信公众平台获取 AppID 与 AppSecret</div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="mp">
      <div class="card">
        <h2>基础配置</h2>
        <div class="field-row">
          <div class="field"><label>AppID</label><input type="text" name="appid" value="<?=htmlspecialchars($mpCfg['appid'])?>" placeholder="wx..."></div>
          <div class="field"><label>AppSecret</label><input type="password" name="appsecret" value="<?=htmlspecialchars($mpCfg['secret'])?>" placeholder="公众号密钥"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Token <span class="hint">· 服务器验证</span></label><input type="text" name="token" value="<?=htmlspecialchars($mpCfg['token'])?>" placeholder="自定义 Token 字符串"></div>
          <div class="field"><label>EncodingAESKey <span class="hint">· 可选</span></label><input type="text" name="encoding_aes_key" value="<?=htmlspecialchars($mpCfg['encoding_aes_key'])?>" placeholder="43 位随机字符"></div>
        </div>
        <p class="text-sm text-muted">服务器地址（URL）：<code class="mp"><?=$serverUrl?></code> · 在微信公众平台「开发 → 基本配置」中填入</p>
      </div>

      <div class="card">
        <h2>自定义菜单 <span class="hint" style="font-weight:400">· JSON 格式</span></h2>
        <p class="text-sm text-muted mb-4">配置完成后点击「推送菜单」即可将菜单同步到微信服务器。</p>
        <div class="field"><textarea name="menu_json" rows="12" style="font-family:var(--mono);font-size:13px" placeholder='{
  "button": [
    { "name": "关于我们", "sub_button": [
      {"type":"view","name":"OpenFlow","url":"https://nownexts.com/"},
      {"type":"click","name":"联系方式","key":"CONTACT"}
    ]},
    { "name": "最新文章", "type": "view", "url": "https://nownexts.com/community" }
  ]
}'><?=htmlspecialchars($mpCfg['menu_json'])?></textarea></div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="pushMenu()" <?=$mpReady ? '' : 'disabled'?>>📡 推送菜单到微信服务器</button>
        <span id="menuResult" class="text-sm text-muted" style="margin-left:8px"></span>
      </div>

      <div class="card">
        <h2>自动回复</h2>
        <div class="field"><label>首次关注/欢迎语</label><textarea name="auto_reply_welcome" rows="3"><?=htmlspecialchars($mpCfg['auto_reply_welcome'])?></textarea></div>
        <div class="field"><label>默认回复 <span class="hint">· 无法识别的消息</span></label><textarea name="auto_reply_default" rows="3"><?=htmlspecialchars($mpCfg['auto_reply_default'])?></textarea></div>
      </div>

      <button type="submit" class="btn btn-primary">💾 保存公众号配置</button>
    </form>

<?php elseif ($tab === 'miniprogram'): ?>
    <!-- ────── 小程序 ────── -->
    <?php $mp2Ready = !empty($mpCfg2['appid']) && !empty($mpCfg2['secret']); ?>
    <?php if ($mp2Ready): ?>
    <div class="mp-status ready">✅ 小程序已配置 · AppID: <?=htmlspecialchars(substr($mpCfg2['appid'], 0, 6))?>…</div>
    <?php else: ?>
    <div class="mp-status off">🔒 小程序尚未配置 · 请前往微信公众平台获取 AppID 与 AppSecret</div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="miniprogram">
      <div class="card">
        <h2>小程序基础配置</h2>
        <div class="field-row">
          <div class="field"><label>AppID</label><input type="text" name="mp_appid" value="<?=htmlspecialchars($mpCfg2['appid'])?>" placeholder="wx..."></div>
          <div class="field"><label>AppSecret</label><input type="password" name="mp_secret" value="<?=htmlspecialchars($mpCfg2['secret'])?>" placeholder="小程序密钥"></div>
        </div>
        <div class="field"><label>隐私声明 / 备案号</label><input type="text" name="mp_legal" value="<?=htmlspecialchars($mpCfg2['legal'])?>" placeholder="如：粤ICP备XXXX号"></div>
      </div>

      <div class="card">
        <h2>小程序 API 对接说明</h2>
        <p class="text-sm text-muted">配置完成后，小程序端可直接调用以下接口获取 CMS 数据：</p>
        <table>
          <tr><td><code class="mp">POST /api/mp-login.php</code></td><td>小程序登录（wx.login → code2session）</td></tr>
          <tr><td><code class="mp">GET /api/articles.php?type=list</code></td><td>获取文章列表（支持 category/tag/search 参数）</td></tr>
          <tr><td><code class="mp">GET /api/articles.php?type=get&slug=xxx</code></td><td>获取单篇文章详情</td></tr>
          <tr><td><code class="mp">GET /api/articles.php?type=categories</code></td><td>获取文章分类</td></tr>
          <tr><td><code class="mp">GET /api/leads.php</code></td><td>获取公开活动/线索（限制字段）</td></tr>
        </table>
      </div>

      <button type="submit" class="btn btn-primary">💾 保存小程序配置</button>
    </form>

<?php elseif ($tab === 'wecom'): ?>
    <!-- ────── 企业微信 ────── -->
    <?php $wecomReady = !empty($wecomCfg['corp_id']) && !empty($wecomCfg['secret']) && !empty($wecomCfg['agent_id']); ?>
    <?php if ($wecomReady): ?>
    <div class="mp-status ready">✅ 企业微信已配置 · CorpID: <?=htmlspecialchars(substr($wecomCfg['corp_id'], 0, 8))?>… · AgentID: <?=htmlspecialchars($wecomCfg['agent_id'])?></div>
    <?php else: ?>
    <div class="mp-status off">🔒 企业微信尚未配置 · 前往企业微信管理后台获取 CorpID / Secret / AgentID</div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="wecom">
      <div class="card">
        <h2>企业微信基础配置</h2>
        <div class="field-row">
          <div class="field"><label>企业 CorpID</label><input type="text" name="wc_corp_id" value="<?=htmlspecialchars($wecomCfg['corp_id'])?>" placeholder="企业微信 CorpID"></div>
          <div class="field"><label>应用 AgentID</label><input type="text" name="wc_agent_id" value="<?=htmlspecialchars($wecomCfg['agent_id'])?>" placeholder="自建应用 AgentID"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>应用 Secret</label><input type="password" name="wc_secret" value="<?=htmlspecialchars($wecomCfg['secret'])?>" placeholder="应用密钥"></div>
          <div class="field"><label>Token <span class="hint">· 可选</span></label><input type="text" name="wc_token" value="<?=htmlspecialchars($wecomCfg['token'])?>" placeholder="回调 Token"></div>
        </div>
        <div class="field"><label>EncodingAESKey <span class="hint">· 可选</span></label><input type="text" name="wc_aes_key" value="<?=htmlspecialchars($wecomCfg['encoding_aes_key'])?>" placeholder="43 位随机字符"></div>
      </div>

      <div class="card">
        <h2>企业微信能力说明</h2>
        <p class="text-sm text-muted">配置完成后可用于：</p>
        <table>
          <tr><td><strong>客户标签 / 分组</strong></td><td>给客户打标签、建分组，按标签定向触达</td></tr>
          <tr><td><strong>定向私信</strong></td><td>给单个客户/客户群发送应用消息（文本/图片/图文）</td></tr>
          <tr><td><strong>群发助手</strong></td><td>向多个客户/客户群批量群发消息</td></tr>
          <tr><td><strong>CDP 打通</strong></td><td>企微客户身份与 CDP 画像关联，标签双向同步</td></tr>
        </table>
        <p class="text-sm text-muted" style="margin-top:10px">回调 URL（用于接收企微消息）：<code class="mp"><?=(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http')?>://<?=htmlspecialchars($_SERVER['HTTP_HOST'] ?? '')?>/api/wecom.php</code></p>
        <p class="text-sm text-muted">对应管理界面见「侧边栏 → Sales → 企业微信」下的群发&私信、标签管理。</p>
      </div>

      <button type="submit" class="btn btn-primary">💾 保存企业微信配置</button>
    </form>
<?php endif; ?>
  </div>
</div>

<script>
function pushMenu() {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/wechat.php?action=push_menu', true);
  xhr.onload = function() {
    var d = JSON.parse(xhr.responseText);
    document.getElementById('menuResult').textContent = d.ok ? '✅ 菜单已推送成功' : '❌ ' + d.error;
  };
  xhr.send();
}
</script>
<?php admin_footer(); ?>
