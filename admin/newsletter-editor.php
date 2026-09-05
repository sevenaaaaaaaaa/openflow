<?php
/**
 * Newsletter 编辑器（Revue 式）—— 从文章一键生成美观 newsletter + 可视化编辑 + 实时预览 + 发送
 *
 * 目标：追平 Revue 的核心体验——「选一篇文章 → 自动排版成好看 newsletter（标题/封面/摘要/CTA）
 * → 可视化编辑 → 实时预览 → 发给订阅者/测试」。复用 mailc_render（模板变量+退订+pixel+统计）与
 * BillionMail::send 发送渠道。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MailCampaign.php';
require_once __DIR__ . '/../lib/BillionMail.php';
require_login();
require_perm('email');

$bmFile = DATA_DIR . '/billionmail.json';
$bmConfig = json_read($bmFile);
if (empty($bmConfig['enabled'])) {
    $bmConfig = ['api_url'=>'', 'api_key'=>'', 'default_sender'=>'hello@nownexts.com', 'enabled'=>false];
}

$articles = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));
$message = ''; $error = '';
$editId = trim((string)($_GET['edit'] ?? $_POST['nl_article'] ?? ''));
$editArticle = null;
foreach ($articles as $a) if (($a['id'] ?? '') === $editId) { $editArticle = $a; break; }

// 保存/发送
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $editId = trim((string)($_POST['nl_article'] ?? ''));
    foreach ($articles as $a) if (($a['id'] ?? '') === $editId) { $editArticle = $a; break; }
    if (!$editArticle) { $error = '请选择文章'; goto render; }
    // 定时发送：保存任务，cron 到点自动发
    if ($act === 'schedule') {
        $subject = trim((string)($_POST['nl_subject'] ?? $editArticle['title']));
        $html = (string)($_POST['nl_html'] ?? '');
        $sendAt = trim((string)($_POST['nl_send_at'] ?? ''));
        if ($sendAt === '') { $error = '请设置定时发送时间'; goto render; }
        $r = nl_schedule_save(['subject'=>$subject, 'html'=>$html, 'article_id'=>$editArticle['id'], 'mode'=>$_POST['nl_mode'] ?? 'subscribers', 'send_at'=>str_replace('T', ' ', $sendAt) . ':00']);
        if ($r['ok']) { $message = "已排期定时发送：{$subject}（{$sendAt}）"; } else { $error = $r['error'] ?? '排期失败'; }
        // 保留编辑态
        $editArticle['_scheduled'] = true;
        goto render;
    }
    $subject = trim((string)($_POST['nl_subject'] ?? $editArticle['title']));
    $html = (string)($_POST['nl_html'] ?? '');
    if ($html === '') { $error = '内容不能为空'; goto render; }

    // 发送前审核
    $review = review_content($subject, strip_tags($html), 'email');
    if (review_needed($review)) {
        review_apply('email', $editArticle['id'], $review, ['title' => $subject]);
        $error = '⚠️ 邮件内容命中审核规则：' . implode('；', array_column($review['issues'], 'desc'));
        goto render;
    }

    $subscribers = json_read(DATA_DIR . '/newsletter/subscribers.json');
    $mode = $_POST['nl_mode'] ?? 'subscribers';
    $campaign = 'nl_' . $editArticle['id'];
    $bm = BillionMail::fromConfig();
    if (!$bm && empty($bmConfig['enabled'])) { $error = '请先配置 BillionMail（mail-settings）'; goto render; }

    $recipients = [];
    if ($mode === 'test') $recipients[] = ['email' => $bmConfig['default_sender'] ?? 'hello@nownexts.com', 'name' => '测试'];
    else foreach ((array)$subscribers as $s) if (($s['status'] ?? 'subscribed') === 'subscribed' && !empty($s['email'])) $recipients[] = $s;
    if (!$recipients) { $recipients[] = ['email' => $bmConfig['default_sender'] ?? 'hello@nownexts.com', 'name' => '测试']; $mode = 'test'; }

    $sent = 0;
    foreach ($recipients as $r) {
        $vars = ['title' => $editArticle['title'], 'subject' => $subject, 'content' => strip_tags($html), 'article_url' => site_config_get('site_url') . '/article/' . ($editArticle['slug'] ?? '')];
        $rendered = mailc_render($html, $vars, $campaign, $r['email']);
        $rendered .= '<img src="' . mailc_pixel($campaign, $r['email']) . '" width="1" height="1" alt="" style="display:none">';
        if ($bm) $bm->send($r['email'], '📬 ' . $subject, $rendered, ['title' => $subject]);
        $sent++;
    }
    $log = json_read(DATA_DIR . '/newsletter.json');
    if (!is_array($log)) $log = [];
    $log[] = ['article_id' => $editArticle['id'], 'title' => $subject, 'sent_at' => date('Y-m-d H:i:s'), 'recipients' => $sent, 'mode' => $mode, 'campaign' => $campaign];
    json_write(DATA_DIR . '/newsletter.json', $log);
    $message = "Newsletter 已发送：{$subject}（{$sent} 位收件人 · " . ($mode === 'test' ? '测试' : '订阅者') . "）";
    $editArticle['_sent'] = true;
}
render:

// 生成默认排版模板（从文章自动排版 —— Revue 式）
function nl_build_template(array $a): string {
    $title = htmlspecialchars($a['title'] ?? '');
    $desc = htmlspecialchars(mb_substr(strip_tags($a['excerpt'] ?? $a['content'] ?? ''), 0, 160));
    $cover = ($a['cover'] ?? '') ? '<img src="' . htmlspecialchars($a['cover']) . '" style="width:100%;border-radius:12px;margin:18px 0" alt="">' : '';
    $body = mb_substr(strip_tags($a['content'] ?? ''), 0, 400);
    $url = site_config_get('site_url') . '/article/' . ($a['slug'] ?? '');
    return '<div style="font-family:system-ui,sans-serif;max-width:600px;margin:0 auto">
      <h1 style="font-size:26px;margin:0 0 8px">' . $title . '</h1>
      <p style="color:#666;font-size:15px;line-height:1.7;margin:0 0 14px">' . $desc . '</p>
      ' . $cover . '
      <div style="font-size:15px;line-height:1.8;color:#333">' . nl2br($body) . '</div>
      <div style="text-align:center;margin:26px 0">
        <a href="' . $url . '" style="background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;display:inline-block;font-weight:600">阅读全文 →</a>
      </div>
      <p style="color:#999;font-size:12px;text-align:center;margin-top:24px">感谢订阅 OpenFlow · {{unsubscribe}}</p>
    </div>';
}
$tplHtml = $editArticle ? nl_build_template($editArticle) : '';

admin_header('Newsletter 编辑器');
?>
<div class="admin-layout"><?php admin_sidebar('email'); ?><div class="main">
  <div class="v-head"><div><h1>📮 Newsletter 编辑器 <span class="st st-ok">Revue 式</span></h1>
    <p class="v-sub">选一篇文章 → 自动排版 → 可视化编辑 → 实时预览 → 发给订阅者/测试。含模板变量名、退订链接、打开/点击统计。</p></div>
    <div class="v-actions"><a href="/xmp/email" class="btn btn-s btn-sm">返回邮件中心</a></div></div>
  <?php if ($message): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a"><?=htmlspecialchars($message)?></div><?php endif; ?>
  <?php if ($error): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($error)?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="nl-grid">
    <!-- 左：编辑 -->
    <div class="card" style="padding:16px">
      <h2 style="font-size:14px;margin-bottom:12px">① 选择文章（自动排版）</h2>
      <form method="post">
        <?= csrf_field() ?>
        <select name="nl_article" onchange="this.form.submit()" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px">
          <option value="">— 选择已发布文章 —</option>
          <?php foreach ($articles as $a): ?><option value="<?=htmlspecialchars($a['id'])?>" <?=($editArticle && $a['id']===$editArticle['id'])?'selected':''?>><?=htmlspecialchars($a['title'])?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-primary btn-sm">加载</button></noscript>
      </form>
      <?php if ($editArticle): ?>
      <h2 style="font-size:14px;margin:16px 0 12px">② 编辑内容（可视化）</h2>
      <form method="post" id="nlForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="nl_article" value="<?=htmlspecialchars($editArticle['id'])?>">
        <div style="display:flex;gap:8px;margin-bottom:10px">
          <input type="text" name="nl_subject" value="<?=htmlspecialchars($editArticle['title'])?>" placeholder="邮件主题" style="flex:1;padding:8px;border:1px solid var(--border);border-radius:8px">
          <select name="nl_mode" style="padding:8px;border:1px solid var(--border);border-radius:8px">
            <option value="subscribers">发给订阅者</option><option value="test">发给测试(自己)</option>
          </select>
        </div>
        <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden">
          <div class="nl-toolbar" style="padding:6px 10px;background:var(--bg);border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap">
            <button type="button" class="btn btn-s btn-sm" onclick="nlCmd('bold')">B</button>
            <button type="button" class="btn btn-s btn-sm" onclick="nlCmd('italic')">I</button>
            <button type="button" class="btn btn-s btn-sm" onclick="nlCmd('insertUnorderedList')">• 列表</button>
            <button type="button" class="btn btn-s btn-sm" onclick="nlLink()">🔗</button>
            <button type="button" class="btn btn-s btn-sm" onclick="nlImg()">🖼</button>
            <span class="text-xs text-muted" style="margin-left:8px;align-self:center">模板变量：<code>{{title}}</code> <code>{{subject}}</code> <code>{{unsubscribe}}</code></span>
          </div>
          <div id="nlEditor" contenteditable="true" class="nl-content" style="min-height:320px;padding:16px;outline:none;font-size:15px;line-height:1.8"><?php echo $tplHtml; ?></div>
          <textarea name="nl_html" id="nlHtml" style="display:none"></textarea>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary" onclick="document.getElementById('nlHtml').value=document.getElementById('nlEditor').innerHTML; return true">📤 发送 Newsletter</button>
          <input type="datetime-local" name="nl_send_at" class="inp" style="height:38px;font-size:13px" placeholder="定时发送时间(留空=立即)">
          <button type="submit" class="btn btn-s" name="action" value="schedule" onclick="document.getElementById('nlHtml').value=document.getElementById('nlEditor').innerHTML; return true">⏱ 定时发送</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- 右：实时预览 -->
    <div class="card" style="padding:16px">
      <h2 style="font-size:14px;margin-bottom:12px">③ 实时预览 <span class="st st-faint">所见即所得</span></h2>
      <div id="nlPreview" style="border:1px solid var(--border);border-radius:10px;padding:20px;background:#fff;overflow:auto;max-height:600px">
        <?php if ($editArticle): ?><?php echo $tplHtml; ?><?php else: ?><div class="of-empty">选择文章后预览。</div><?php endif; ?>
      </div>
    </div>
  </div>
</div></div>
<style>
#nlEditor:focus{border-color:var(--accent)}
.nl-content p{margin:0 0 10px}
.nl-content h1{font-size:24px;margin:0 0 8px}
.nl-content a{color:#2563eb}
</style>
<script>
function nlSync(){ document.getElementById('nlPreview').innerHTML = document.getElementById('nlEditor').innerHTML; }
var nlEd = document.getElementById('nlEditor');
if (nlEd) { nlEd.addEventListener('input', nlSync); }
function nlCmd(cmd){ if(nlEd){ document.execCommand(cmd); nlSync(); } }
function nlLink(){ if(nlEd){ var u=prompt('链接地址(留空=移除)',''); if(u) document.execCommand('createLink',false,u); nlSync(); } }
function nlImg(){ if(nlEd){ var s=prompt('图片地址(如 /uploads/x.jpg)'); if(s) document.execCommand('insertImage',false,s); nlSync(); } }
</script>
<?php admin_footer(); ?>
