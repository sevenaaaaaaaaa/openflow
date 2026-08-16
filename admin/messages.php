<?php
/**
 * 站内信管理 — 全体广播 + 个人发送 + 消息列表
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_login();
require_perm('settings');

$message = '';
$all = inbox_all();
$members = member_get_all();

// 发送
if (isset($_POST['send'])) {
    $to = $_POST['to'] ?? 'all';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $icon = trim($_POST['icon'] ?? '🔔');
    $type = $_POST['type'] ?? 'system';
    if (empty($title) || empty($content)) {
        $message = '标题和内容不能为空';
    } else {
        if ($to === 'all') {
            inbox_send('all', $title, $content, ['type' => $type, 'link' => $link, 'icon' => $icon, 'by' => $_SESSION['admin_user'] ?? '']);
        } else {
            inbox_send($to, $title, $content, ['type' => $type, 'link' => $link, 'icon' => $icon, 'by' => $_SESSION['admin_user'] ?? '']);
        }
        $message = '消息已发送';
        $all = inbox_all();
    }
}
// 删除
if (isset($_GET['del'])) {
    inbox_delete($_GET['del']);
    flash('success', '消息已删除');
    header('Location: /xmp/messages');
    exit;
}

admin_header('站内信');
?>
<div class="admin-layout">
  <?php admin_sidebar('messages'); ?>
  <div class="main">
    <h1>站内信</h1>
    <p class="sub">全体广播 + 个人发送 · 会员在站内信 / 导航栏可见</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:20px" class="msg-grid">
      <!-- 发送 -->
      <div>
        <div class="card">
          <h2>📤 发送消息</h2>
          <form method="post">
            <?= csrf_field() ?>
            <div class="field"><label>收件人</label>
              <select name="to">
                <option value="all">📢 全体会员广播</option>
                <?php foreach ($members as $m): ?>
                <option value="<?=htmlspecialchars($m['id'])?>"><?=htmlspecialchars($m['name'] ?? $m['email'])?> (<?=htmlspecialchars($m['email'] ?? '')?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field-row">
              <div class="field"><label>类型</label>
                <select name="type"><option value="system">系统</option><option value="order">订单</option><option value="consultation">咨询</option><option value="live">直播</option><option value="membership">会员</option><option value="marketing">营销</option></select>
              </div>
              <div class="field"><label>图标</label><input type="text" name="icon" value="🔔" style="width:80px"></div>
            </div>
            <div class="field"><label>标题</label><input type="text" name="title" required placeholder="消息标题"></div>
            <div class="field"><label>内容</label><textarea name="content" rows="4" required placeholder="消息内容"></textarea></div>
            <div class="field"><label>跳转链接</label><input type="text" name="link" placeholder="/member.php?view=orders"></div>
            <button type="submit" name="send" class="btn btn-primary">发送</button>
          </form>
        </div>

        <!-- 业务事件发信说明 -->
        <div class="card" style="margin-top:16px">
          <h2>⚡ 自动发信事件</h2>
          <p class="text-sm text-muted mb-3">以下业务事件会自动给会员发站内信：</p>
          <ul style="font-size:13px;line-height:2;padding-left:18px;color:var(--text-2)">
            <li>🤝 1v1 咨询：审核通过 / 时间确认 / 交付回放</li>
            <li>🛒 订单支付成功</li>
            <li>📡 直播开始广播</li>
            <li>💎 会员等级升级</li>
            <li>🏆 获得积分</li>
            <li>📝 投稿审核结果</li>
          </ul>
        </div>
      </div>

      <!-- 已发消息 -->
      <div class="card" style="padding:0;align-self:start">
        <h2 style="padding:20px 20px 0">📨 已发送（<?=count($all)?>）</h2>
        <div style="padding:0 20px 20px;max-height:70vh;overflow-y:auto">
          <?php if (empty($all)): ?><div class="empty" style="padding:30px">暂无消息</div><?php endif; ?>
          <?php foreach (array_reverse($all) as $m): ?>
          <div style="padding:12px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span><?=htmlspecialchars($m['icon'] ?? '🔔')?></span>
              <strong style="font-size:13.5px"><?=htmlspecialchars($m['title'] ?? '')?></strong>
              <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:var(--surface-2)"><?=$m['to'] === 'all' ? '全体' : '个人'?></span>
              <span class="text-sm text-muted" style="margin-left:auto"><?=htmlspecialchars($m['created_at'] ?? '')?></span>
            </div>
            <div class="text-sm text-muted" style="margin-top:4px;white-space:pre-line"><?=htmlspecialchars(mb_substr($m['content'] ?? '', 0, 120))?></div>
            <a href="?del=<?=urlencode($m['id'])?>" class="btn btn-danger btn-sm" style="margin-top:6px" onclick="return confirm('确认删除？')">删除</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.msg-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
