<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/WechatMp.php';
require_login();
require_perm('wechat-mp');

$cfg = WechatMp::config();
$appid = $cfg['appid'] ?? '';
$message = '';
$error = '';

// 定时群发任务存储
$schedFile = DATA_DIR . '/wechat-mass.json';
$scheduled = json_read($schedFile);

// 群发记录
$logFile = DATA_DIR . '/wechat-mass-log.json';
$massLog = json_read($logFile);

$action = $_GET['action'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

// 标签列表
$tags = [];
if ($appid) {
    try {
        $r = WechatMp::listTags();
        $tags = $r['tags'] ?? [];
    } catch (Exception $e) {}
}

if ($post) {
    csrf_verify();
    $do = $_POST['do'] ?? '';

    // ── 立即群发 ──
    if ($do === 'send_now') {
        $msgType = $_POST['msg_type'] ?? 'text';
        $target = $_POST['target'] ?? 'all'; // all / tag / openids
        $tagId = (int)($_POST['tag_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $openidsText = trim($_POST['openids'] ?? '');

        if ($msgType === 'text' && empty($content)) {
            $error = '请输入群发内容';
        } else {
            $contentBody = $msgType === 'text' ? ['content' => $content] : ['media_id' => trim($_POST['media_id'] ?? '')];
            if ($target === 'all') {
                $r = WechatMp::massSendByTag($contentBody, $msgType, 0);
            } elseif ($target === 'tag') {
                $r = WechatMp::massSendByTag($contentBody, $msgType, $tagId);
            } else {
                $openids = array_values(array_filter(array_map('trim', explode("\n", $openidsText))));
                $r = WechatMp::massSendByOpenids($openids, $contentBody, $msgType);
            }
            if (($r['errcode'] ?? 1) === 0) {
                $message = '群发已发送成功' . ($r['msg_id'] ?? '');
                $massLog[] = [
                    'time' => date('Y-m-d H:i:s'), 'type' => $msgType, 'target' => $target,
                    'tag_id' => $tagId, 'preview' => mb_substr($content, 0, 60), 'msg_id' => $r['msg_id'] ?? '', 'status' => 'sent',
                ];
                json_write($logFile, array_slice($massLog, -100));
            } else {
                $error = '群发失败: ' . ($r['errmsg'] ?? '未知') . ' (errcode ' . ($r['errcode'] ?? -1) . ')';
            }
        }
    }

    // ── 预览群发 ──
    if ($do === 'preview') {
        $msgType = $_POST['msg_type'] ?? 'text';
        $content = trim($_POST['content'] ?? '');
        $previewOpenid = trim($_POST['preview_openid'] ?? '');
        if (!$previewOpenid) { $error = '请输入测试 openid'; }
        else {
            $contentBody = $msgType === 'text' ? ['content' => $content] : ['media_id' => trim($_POST['media_id'] ?? '')];
            $r = WechatMp::massPreview($previewOpenid, $contentBody, $msgType);
            if (($r['errcode'] ?? 1) === 0) $message = '预览已发送';
            else $error = '预览失败: ' . ($r['errmsg'] ?? '未知');
        }
    }

    // ── 定时群发 ──
    if ($do === 'schedule') {
        $msgType = $_POST['msg_type'] ?? 'text';
        $target = $_POST['target'] ?? 'all';
        $tagId = (int)($_POST['tag_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $sendAt = trim($_POST['send_at'] ?? '');
        if (empty($content) || empty($sendAt)) { $error = '内容和定时时间必填'; }
        else {
            $scheduled[] = [
                'id' => 'ws_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 6),
                'msg_type' => $msgType, 'target' => $target, 'tag_id' => $tagId,
                'content' => $content, 'send_at' => $sendAt,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ];
            json_write($schedFile, $scheduled);
            $message = '定时群发已排入队列';
        }
    }

    // ── 删除定时任务 ──
    if ($do === 'cancel_schedule') {
        $id = $_POST['id'] ?? '';
        $scheduled = array_values(array_filter($scheduled, fn($s) => $s['id'] !== $id));
        json_write($schedFile, $scheduled);
        $message = '定时任务已删除';
    }
}

admin_header('微信群发');
?>
<div class="admin-layout">
  <?php admin_sidebar('wechat-send'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 微信群发</h1>
      <div class="flex gap-2 ml-auto">
        <a href="wechat-mp.php" class="btn btn-ghost btn-sm">公众号设置</a>
      </div>
    </div>
    <p class="sub">服务号群发 · 定向标签群发 · 定时群发 · 客服私信 · 模板消息</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>
    <?php if (!$appid): ?>
      <div class="card"><?=msg('error', '请先在「公众号设置」配置 AppID 和 AppSecret')?></div>
    <?php endif; ?>

    <!-- 立即群发 -->
    <div class="card" style="margin-bottom:24px">
      <h2>🚀 立即群发</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="send_now">
        <div class="field-row">
          <div class="field">
            <label>消息类型</label>
            <select name="msg_type" id="msgType">
              <option value="text">📝 文本</option>
              <option value="mpnews">📰 图文素材</option>
            </select>
          </div>
          <div class="field">
            <label>群发对象</label>
            <select name="target" id="massTarget" onchange="document.getElementById('tagSelBox').style.display=this.value==='tag'?'block':'none';document.getElementById('openidBox').style.display=this.value==='openids'?'block':'none'">
              <option value="all">全部粉丝</option>
              <option value="tag">指定标签</option>
              <option value="openids">指定 openid 列表</option>
            </select>
          </div>
          <div class="field" id="tagSelBox" style="display:none">
            <label>选择标签</label>
            <select name="tag_id">
              <option value="0">— 请选择 —</option>
              <?php foreach ($tags as $t): ?>
              <option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?> (<?=$t['count']??0?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field" id="openidBox" style="display:none;margin-top:10px">
          <label>OpenID 列表 <span class="hint">· 每行一个</span></label>
          <textarea name="openids" rows="3" placeholder="oxxxxxxxxxxxxxxxxxxx"></textarea>
        </div>
        <div class="field" id="textBox" style="margin-top:10px">
          <label>文本内容</label>
          <textarea name="content" rows="4" placeholder="输入要群发的文字…"></textarea>
        </div>
        <div class="field" id="mediaBox" style="display:none;margin-top:10px">
          <label>图文素材 media_id <span class="hint">· 在「素材管理」中获取</span></label>
          <input type="text" name="media_id" placeholder="media_id 或通过素材库选择">
        </div>
        <div class="flex gap-3" style="margin-top:12px">
          <button type="submit" class="btn btn-primary" onclick="return confirm('确认立即群发？')">立即群发</button>
        </div>
      </form>
    </div>

    <!-- 定时群发 -->
    <div class="card" style="margin-bottom:24px">
      <h2>⏰ 定时群发</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="schedule">
        <div class="field-row">
          <div class="field"><label>消息类型</label><select name="msg_type"><option value="text">文本</option><option value="mpnews">图文</option></select></div>
          <div class="field"><label>对象</label><select name="target"><option value="all">全部</option><option value="tag">按标签</option></select></div>
          <div class="field"><label>标签</label><select name="tag_id"><option value="0">全部</option><?php foreach ($tags as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select></div>
          <div class="field"><label>定时时间</label><input type="datetime-local" name="send_at" required></div>
        </div>
        <div class="field" style="margin-top:10px"><label>内容</label><textarea name="content" rows="3" placeholder="群发内容…"></textarea></div>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">排入定时队列</button>
      </form>

      <?php if ($scheduled): ?>
      <h3 style="margin:16px 0 8px">📅 定时队列</h3>
      <table>
        <thead><tr><th>时间</th><th>对象</th><th>内容</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($scheduled as $s): ?>
          <tr>
            <td><?=htmlspecialchars($s['send_at'])?></td>
            <td><?=($s['target']==='tag'?'标签#'.$s['tag_id']:'全部')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(mb_substr($s['content'],0,40))?></td>
            <td><span class="badge <?=$s['status']==='pending'?'badge-yellow':'badge-green'?>"><?=$s['status']?></span></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="cancel_schedule">
                <input type="hidden" name="id" value="<?=htmlspecialchars($s['id'])?>">
                <button class="btn btn-ghost btn-sm" style="color:var(--danger)">取消</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- 客服消息 / 模板消息 -->
    <div class="card" style="margin-bottom:24px">
      <h2>💬 定向私信</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="send_now">
        <input type="hidden" name="target" value="openids">
        <input type="hidden" name="msg_type" value="text">
        <div class="field"><label>OpenID（单发一个用户）</label><input type="text" name="openids" placeholder="用户 openid"></div>
        <div class="field"><label>私信内容</label><textarea name="content" rows="3" placeholder="定向私信内容…"></textarea></div>
        <button type="submit" class="btn btn-primary">发送私信</button>
        <p class="hint" style="margin-top:8px">说明：使用客服消息接口（48 小时内有交互的用户可收到）；服务号需提前接入客服接口。</p>
      </form>
    </div>

    <!-- 群发记录 -->
    <?php if ($massLog): ?>
    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">🕘 群发记录</h2></div>
      <table>
        <thead><tr><th>时间</th><th>类型</th><th>对象</th><th>内容</th><th>MsgID</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($massLog) as $l): ?>
          <tr>
            <td class="text-sm"><?=htmlspecialchars($l['time'])?></td>
            <td><span class="badge badge-gray"><?=$l['type']?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['target'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['preview'])?></td>
            <td><code style="font-size:11px"><?=htmlspecialchars($l['msg_id'])?></code></td>
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
document.getElementById('msgType').addEventListener('change', function(){
  var t = this.value;
  document.getElementById('textBox').style.display = t === 'text' ? '' : 'none';
  document.getElementById('mediaBox').style.display = t === 'mpnews' ? '' : 'none';
});
</script>
<?php admin_footer(); ?>
