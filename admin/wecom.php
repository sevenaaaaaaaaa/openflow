<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('wechat-mp');

$configured = Wecom::configured();
$message = '';
$error = '';

// 群发/私信记录
$logFile = DATA_DIR . '/wecom-log.json';
$log = json_read($logFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';

    if ($do === 'send_msg') {
        $toType = $_POST['to_type'] ?? 'user';
        $userIds = array_values(array_filter(array_map('trim', explode("\n", $_POST['user_ids'] ?? ''))));
        $partyIds = array_values(array_filter(array_map('trim', explode("\n", $_POST['party_ids'] ?? ''))));
        $tagIds = array_values(array_filter(array_map('trim', explode("\n", $_POST['tag_ids'] ?? ''))));
        $content = trim($_POST['content'] ?? '');
        $cardTitle = trim($_POST['card_title'] ?? '');
        $cardDesc = trim($_POST['card_desc'] ?? '');
        $cardUrl = trim($_POST['card_url'] ?? '');
        $msgType = $_POST['msg_type'] ?? 'text';

        if (empty($content) && $msgType === 'text') { $error = '请输入私信内容'; }
        else {
            $to = [];
            if (!empty($userIds)) $to['touser'] = implode('|', $userIds);
            if (!empty($partyIds)) $to['toparty'] = implode('|', $partyIds);
            if (!empty($tagIds)) $to['totag'] = implode('|', $tagIds);
            if (empty($to)) { $error = '请至少填写一类接收人'; }
            else {
                if ($msgType === 'textcard' && $cardTitle) {
                    $r = Wecom::sendTextCard($to, $cardTitle, $cardDesc, $cardUrl);
                } else {
                    $r = Wecom::sendText($to, $content);
                }
                if (($r['errcode'] ?? 1) === 0) {
                    $message = '私信已发送';
                    $log[] = [
                        'time' => date('Y-m-d H:i:s'), 'do' => '私信',
                        'type' => $msgType, 'to' => json_encode($to, JSON_UNESCAPED_UNICODE),
                        'preview' => mb_substr($content ?: $cardTitle, 0, 60), 'status' => 'sent',
                    ];
                    json_write($logFile, array_slice($log, -100));
                } else {
                    $error = '发送失败: ' . ($r['errmsg'] ?? '未知') . ' (errcode ' . ($r['errcode'] ?? -1) . ')';
                }
            }
        }
    }

    if ($do === 'send_group') {
        // 群发助手（外部客户）
        $externalIds = array_values(array_filter(array_map('trim', explode("\n", $_POST['external_ids'] ?? ''))));
        $groupText = trim($_POST['group_text'] ?? '');
        $sender = trim($_POST['sender'] ?? '');
        if (empty($externalIds) || empty($groupText)) { $error = '客户 ID 和内容必填'; }
        else {
            $r = Wecom::addMsgTemplate($externalIds, $groupText, [], $sender);
            if (($r['errcode'] ?? 1) === 0) {
                $message = '群发任务已创建（需成员在企业微信确认发送）';
                $log[] = [
                    'time' => date('Y-m-d H:i:s'), 'do' => '群发助手',
                    'type' => 'group', 'preview' => mb_substr($groupText, 0, 60), 'status' => 'queued',
                ];
                json_write($logFile, array_slice($log, -100));
            } else {
                $error = '群发失败: ' . ($r['errmsg'] ?? '未知');
            }
        }
    }

    if ($do === 'add_tag') {
        $groupName = trim($_POST['tag_group'] ?? '');
        $tagNames = array_values(array_filter(array_map('trim', explode(',', $_POST['tag_names'] ?? ''))));
        if (empty($groupName) || empty($tagNames)) { $error = '标签组名和标签名必填'; }
        else {
            $r = Wecom::addCustomerTag($groupName, $tagNames);
            if (($r['errcode'] ?? 1) === 0) $message = '客户标签已添加';
            else $error = '添加标签失败: ' . ($r['errmsg'] ?? '未知');
        }
    }
}

// 拉取标签 / 部门（配置后可调用）
$tags = [];
$departments = [];
if ($configured) {
    try { $r = Wecom::customerTags(); $tags = $r['tag_group'] ?? []; } catch (Exception $e) {}
    try { $r = Wecom::departmentList(); $departments = $r['department'] ?? []; } catch (Exception $e) {}
}

admin_header('企业微信管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('wecom'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 企业微信</h1>
      <div class="flex gap-2 ml-auto">
        <a href="wechat-mp.php?tab=wecom" class="btn btn-ghost btn-sm">配置</a>
      </div>
    </div>
    <p class="sub">客户标签/分组 · 定向私信 · 群发助手 · 客户群</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>
    <?php if (!$configured): ?>
      <div class="card"><?=msg('error', '尚未配置企业微信，请先到「企业微信」设置页填写 CorpID / Secret / AgentID')?></div>
    <?php endif; ?>

    <!-- 定向私信 -->
    <div class="card" style="margin-bottom:24px">
      <h2>📨 定向私信（应用消息）</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="send_msg">
        <div class="field-row">
          <div class="field">
            <label>消息类型</label>
            <select name="msg_type" onchange="document.getElementById('cardFields').style.display=this.value==='textcard'?'block':'none';document.getElementById('textField').style.display=this.value==='textcard'?'none':'block'">
              <option value="text">📝 文本</option>
              <option value="textcard">🃏 文本卡片（带跳转）</option>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>成员 UserID <span class="hint">· 每行一个</span></label><textarea name="user_ids" rows="2" placeholder="如 ZhangSan&#10;LiSi"></textarea></div>
          <div class="field"><label>部门 PartyID <span class="hint">· 每行一个</span></label><textarea name="party_ids" rows="2" placeholder="可选"></textarea></div>
          <div class="field"><label>标签 TagID <span class="hint">· 每行一个</span></label><textarea name="tag_ids" rows="2" placeholder="可选"></textarea></div>
        </div>
        <div class="field" id="textField"><label>内容</label><textarea name="content" rows="3" placeholder="输入私信内容…"></textarea></div>
        <div id="cardFields" style="display:none">
          <div class="field"><label>卡片标题</label><input type="text" name="card_title" placeholder="卡片标题"></div>
          <div class="field"><label>卡片描述</label><textarea name="card_desc" rows="2" placeholder="卡片描述"></textarea></div>
          <div class="field"><label>跳转链接</label><input type="text" name="card_url" placeholder="https://..."></div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">发送私信</button>
      </form>
    </div>

    <!-- 群发助手 -->
    <div class="card" style="margin-bottom:24px">
      <h2>📢 群发助手（外部客户）</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="send_group">
        <div class="field-row">
          <div class="field"><label>外部客户 ExternalUserID <span class="hint">· 每行一个</span></label><textarea name="external_ids" rows="3" placeholder="客户的 external_userid"></textarea></div>
          <div class="field"><label>发送成员 UserID</label><input type="text" name="sender" placeholder="成员账号"></div>
        </div>
        <div class="field"><label>群发内容</label><textarea name="group_text" rows="3" placeholder="群发文本…"></textarea></div>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">创建群发任务</button>
        <p class="hint" style="margin-top:8px">说明：群发任务创建后需由对应成员在企业微信客户端确认发送。</p>
      </form>
    </div>

    <!-- 客户标签 -->
    <div class="card" style="margin-bottom:24px">
      <h2>🏷 客户标签</h2>
      <form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="add_tag">
        <div class="field"><label>标签组名</label><input type="text" name="tag_group" placeholder="如：客户分层"></div>
        <div class="field"><label>标签名 <span class="hint">· 逗号分隔</span></label><input type="text" name="tag_names" placeholder="高意向, 已购, VIP"></div>
        <button type="submit" class="btn btn-primary">添加</button>
      </form>
      <?php if ($tags): ?>
      <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($tags as $g): ?>
        <span class="badge badge-gray"><?=htmlspecialchars($g['group_name'] ?? '')?>: <?=implode(', ', array_column($g['tag'] ?? [], 'name'))?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 操作记录 -->
    <?php if ($log): ?>
    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">🕘 操作记录</h2></div>
      <table>
        <thead><tr><th>时间</th><th>类型</th><th>接收对象</th><th>内容</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($log) as $l): ?>
          <tr>
            <td class="text-sm"><?=htmlspecialchars($l['time'])?></td>
            <td><span class="badge badge-gray"><?=$l['do']?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['to'] ?? $l['type'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['preview'] ?? '')?></td>
            <td><span class="badge badge-green"><?=$l['status']?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
