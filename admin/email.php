<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/review-lib.php';
require_once __DIR__ . '/../lib/MailCampaign.php';
require_login();
require_perm('email');

$bmFile = DATA_DIR . '/billionmail.json';
$mauticFile = DATA_DIR . '/mautic.json';
$bmConfig = json_read($bmFile);
$mauticConfig = json_read($mauticFile);
$newsletterFile = DATA_DIR . '/newsletter.json';

$activeTab = $_GET['tab'] ?? 'bm';
$message = '';

// ─── BillionMail ───
if (isset($_POST['save_bm'])) {
    $bmConfig = [
        'api_url' => rtrim(trim($_POST['bm_api_url'] ?? ''), '/'),
        'api_key' => trim($_POST['bm_api_key'] ?? ''),
        'default_sender' => trim($_POST['bm_default_sender'] ?? ''),
        'default_sender_name' => trim($_POST['bm_default_sender_name'] ?? 'OpenFlow'),
        'enabled' => isset($_POST['bm_enabled']),
    ];
    json_write($bmFile, $bmConfig);
    $message = 'BillionMail 配置已保存';
}

if (isset($_GET['bm_test'])) {
    if (empty($bmConfig['api_url']) || empty($bmConfig['api_key'])) {
        $message = '请先填写 BillionMail 配置';
    } else {
        $ch = curl_init($bmConfig['api_url'] . '/api/batch_mail/api/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['recipient' => 'test@test.com']),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bmConfig['api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $message = $http >= 200 && $http < 500
            ? "BillionMail 连接成功 (HTTP $http)"
            : "BillionMail 连接失败 (HTTP $http): " . mb_substr($resp, 0, 100);
    }
}

// ─── Mautic ───
if (isset($_POST['save_mautic'])) {
    $mauticConfig = [
        'base_url' => rtrim(trim($_POST['mautic_base_url'] ?? ''), '/'),
        'version' => $_POST['mautic_version'] ?? 'BasicAuth',
        'username' => trim($_POST['mautic_username'] ?? ''),
        'password' => trim($_POST['mautic_password'] ?? ''),
        'client_key' => trim($_POST['mautic_client_key'] ?? ''),
        'client_secret' => trim($_POST['mautic_client_secret'] ?? ''),
        'default_email_id' => trim($_POST['mautic_default_email_id'] ?? ''),
        'enabled' => isset($_POST['mautic_enabled']),
    ];
    json_write($mauticFile, $mauticConfig);
    $message = 'Mautic 配置已保存';
}

if (isset($_GET['mautic_test'])) {
    if (empty($mauticConfig['base_url'])) {
        $message = '请先填写 Mautic 配置';
    } else {
        $ch = curl_init($mauticConfig['base_url'] . '/api/ping');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $message = $http === 200 ? "Mautic 连接成功" : "Mautic 连接失败 (HTTP $http)";
    }
}

// ─── Newsletter ───
if (isset($_POST['send_newsletter'])) {
    $articles = get_articles();
    $published = array_values(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));
    $selectedId = $_POST['newsletter_article'] ?? '';

    if ($selectedId && $bmConfig['enabled'] && $bmConfig['api_url'] && $bmConfig['api_key']) {
        $article = null;
        foreach ($published as $a) { if ($a['id'] === $selectedId) { $article = $a; break; } }

        // ─── 邮件推送前审核 ───
        if ($article) {
            $emailReview = review_content($article['title'], $article['content'] ?? '', 'email');
            if (review_needed($emailReview)) {
                review_apply('email', $article['id'], $emailReview, ['title' => $article['title']]);
                $issueSummary = implode('；', array_column($emailReview['issues'], 'desc'));
                notify('review', '邮件推送需审核：' . mb_substr($article['title'], 0, 20), $issueSummary, 'admin/reviews.php?type=email', ['admin', 'marketing']);
                $message = '⚠️ 邮件内容命中审核规则，未推送：' . $issueSummary;
                $article = null; // 阻止发送
            }
        }
        if ($article) {
            $content = strip_tags($article['content'] ?? '');
            $content = mb_substr($content, 0, 500);

            // 订阅者列表（本地采集）+ 发送模式
            $subscribers = json_read(DATA_DIR . '/newsletter/subscribers.json');
            $mode = $_POST['newsletter_mode'] ?? 'subscribers'; // subscribers|test
            $recipients = [];
            if ($mode === 'test') {
                $recipients[] = ['email' => $bmConfig['default_sender'], 'name' => '测试'];
            } else {
                foreach ($subscribers as $s) {
                    if (($s['status'] ?? 'subscribed') === 'subscribed' && !empty($s['email'])) {
                        $recipients[] = ['email' => $s['email'], 'name' => $s['name'] ?? ''];
                    }
                }
                if (empty($recipients)) {
                    $recipients[] = ['email' => $bmConfig['default_sender'], 'name' => '测试'];
                    $mode = 'test';
                }
            }

            // 邮件模板（若有选择）
            $mailTemplate = null;
            if (!empty($_POST['mail_template'])) $mailTemplate = mailc_template($_POST['mail_template']);
            $campaign = 'nl_' . $selectedId;

            $sentCount = 0;
            foreach ($recipients as $rcpt) {
                // 渲染内容（模板 + 变量 + 退订链接 + pixel + 链接包装）
                $subject = $article['title'];
                $articleUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/article/' . $article['slug'];
                $content = $mailTemplate
                    ? $mailTemplate['html']
                    : '<p>' . nl2br(htmlspecialchars($content)) . '</p>'
                      . '<p style="text-align:center"><a href="' . $articleUrl . '" style="background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;display:inline-block">阅读全文 →</a></p>';
                $vars = ['title' => $article['title'], 'subject' => $subject, 'content' => strip_tags($content), 'article_url' => $articleUrl];
                $html = mailc_render($content, $vars, $campaign, $rcpt['email']);
                $html .= '<img src="' . mailc_pixel($campaign, $rcpt['email']) . '" width="1" height="1" alt="" style="display:none">';

                // Post to BillionMail
                $ch = curl_init($bmConfig['api_url'] . '/api/batch_mail/api/send');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'recipient' => $rcpt['email'],
                        'addresser' => $bmConfig['default_sender'],
                        'attribs' => [
                            'subject' => '📬 ' . $subject,
                            'content' => $html,
                            'article_url' => $articleUrl,
                            'title' => $article['title'],
                        ],
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $bmConfig['api_key'],
                        'Content-Type: application/json',
                    ],
                    CURLOPT_TIMEOUT => 15,
                ]);
                curl_exec($ch);
                $sentCount++;
            }

            // Log
            $log = json_read($newsletterFile);
            if (empty($log)) $log = [];
            $log[] = ['article_id' => $selectedId, 'title' => $article['title'], 'sent_at' => date('Y-m-d H:i:s'), 'recipients' => $sentCount, 'mode' => $mode, 'campaign' => $campaign];
            json_write($newsletterFile, $log);
            $message = "Newsletter 已推送: {$article['title']}（{$sentCount} 位收件人 · " . ($mode === 'test' ? '测试' : '订阅者') . "）";
        }
    } else {
        $message = '请先配置并启用 BillionMail';
    }
}

// ─── Stats ───
$newsletterLog = json_read($newsletterFile);

admin_header('邮件营销');
?>
<style>
.tab-content{display:none}
.tab-content.active{display:block}
.status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px}
.status-dot.on{background:#22c55e}
.status-dot.off{background:#ef4444}
</style>
<div class="admin-layout">
  <?php admin_sidebar('email'); ?>
  <div class="main">
    <h1>邮件营销</h1>
    <p class="sub">BillionMail + Mautic 集成管理 · 部署后填写配置即可启用</p>

    <?php if ($message): ?><?=msg(str_contains($message, '失败') ? 'error' : 'success', $message)?><?php endif; ?>

    <!-- Status Bar -->
    <div class="stats" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card">
        <div><span class="status-dot <?=$bmConfig['enabled']??false?'on':'off'?>"></span><strong>BillionMail</strong></div>
        <div class="label"><?=($bmConfig['enabled']??false)?'已启用':'未配置'?></div>
      </div>
      <div class="stat-card">
        <div><span class="status-dot <?=$mauticConfig['enabled']??false?'on':'off'?>"></span><strong>Mautic</strong></div>
        <div class="label"><?=($mauticConfig['enabled']??false)?'已启用':'未配置'?></div>
      </div>
      <div class="stat-card">
        <div><strong>📬 Newsletter</strong></div>
        <div class="label">已发送 <?=count($newsletterLog)?> 期</div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tabs">
      <a href="?tab=bm" class="<?=$activeTab==='bm'?'active':''?>" onclick="switchTab('bm')">BillionMail</a>
      <a href="?tab=mautic" class="<?=$activeTab==='mautic'?'active':''?>" onclick="switchTab('mautic')">Mautic</a>
      <a href="?tab=newsletter" class="<?=$activeTab==='newsletter'?'active':''?>" onclick="switchTab('newsletter')">Newsletter</a>
      <a href="?tab=log" class="<?=$activeTab==='log'?'active':''?>" onclick="switchTab('log')">发送记录</a>
    </div>

    <!-- ═══ BillionMail Tab ═══ -->
    <div class="tab-content <?=$activeTab==='bm'?'active':''?>" id="tab-bm">
      <div class="card">
        <h2>BillionMail 配置</h2>
        <p class="text-sm text-muted mb-4">国内邮件发送平台，用于事务性邮件和 Newsletter 群发。</p>
        <form method="post">
          <?= csrf_field() ?>
          <div class="field-row">
            <div class="field"><label>API 地址</label><input type="url" name="bm_api_url" value="<?=htmlspecialchars($bmConfig['api_url'] ?? '')?>" placeholder="https://mail.yourdomain.com"></div>
            <div class="field"><label>API Key</label><input type="password" name="bm_api_key" value="<?=htmlspecialchars($bmConfig['api_key'] ?? '')?>" placeholder="在 BillionMail 设置页获取"></div>
          </div>
          <div class="field-row">
            <div class="field"><label>默认发件人邮箱</label><input type="email" name="bm_default_sender" value="<?=htmlspecialchars($bmConfig['default_sender'] ?? '')?>" placeholder="noreply@nownexts.com"></div>
            <div class="field"><label>发件人名称</label><input type="text" name="bm_default_sender_name" value="<?=htmlspecialchars($bmConfig['default_sender_name'] ?? 'OpenFlow')?>"></div>
          </div>
          <div class="field">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="bm_enabled" value="1" <?=($bmConfig['enabled']??false)?'checked':''?> style="width:18px;height:18px">
              启用 BillionMail
            </label>
          </div>
          <div class="flex gap-2">
            <button type="submit" name="save_bm" class="btn btn-primary">保存配置</button>
            <a href="?tab=bm&bm_test=1" class="btn btn-ghost">测试连接</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>PHP SDK 方法</h2>
        <p class="text-sm text-muted">部署后以下功能可直接使用</p>
        <table>
          <thead><tr><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>BillionMail::send($to, $subject, $html)</code></td><td>发送单封邮件</td></tr>
            <tr><td><code>BillionMail::batchSend([$to1, $to2], $subject, $html)</code></td><td>批量发送相同内容</td></tr>
            <tr><td><code>BillionMail::sendTemplate($to, $templateId, $attribs)</code></td><td>使用模板发送</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ Mautic Tab ═══ -->
    <div class="tab-content <?=$activeTab==='mautic'?'active':''?>" id="tab-mautic">
      <div class="card">
        <h2>Mautic 配置</h2>
        <p class="text-sm text-muted mb-4">开源营销自动化平台，支持联系人管理、行为追踪、自动化流程。</p>
        <form method="post">
          <?= csrf_field() ?>
          <div class="field-row">
            <div class="field"><label>Mautic 地址</label><input type="url" name="mautic_base_url" value="<?=htmlspecialchars($mauticConfig['base_url'] ?? '')?>" placeholder="https://mautic.yourdomain.com"></div>
            <div class="field"><label>认证方式</label><select name="mautic_version"><option value="BasicAuth" <?=($mauticConfig['version']??'')==='BasicAuth'?'selected':''?>>Basic Auth</option><option value="OAuth2" <?=($mauticConfig['version']??'')==='OAuth2'?'selected':''?>>OAuth2</option></select></div>
          </div>
          <div id="mauticBasic" style="display:<?=($mauticConfig['version']??'BasicAuth')==='BasicAuth'?'block':'none'?>">
            <div class="field-row">
              <div class="field"><label>用户名</label><input type="text" name="mautic_username" value="<?=htmlspecialchars($mauticConfig['username'] ?? '')?>" placeholder="Mautic 管理员账号"></div>
              <div class="field"><label>密码</label><input type="password" name="mautic_password" value="<?=htmlspecialchars($mauticConfig['password'] ?? '')?>"></div>
            </div>
          </div>
          <div id="mauticOAuth" style="display:<?=($mauticConfig['version']??'')==='OAuth2'?'block':'none'?>">
            <div class="field-row">
              <div class="field"><label>Client Key</label><input type="text" name="mautic_client_key" value="<?=htmlspecialchars($mauticConfig['client_key'] ?? '')?>"></div>
              <div class="field"><label>Client Secret</label><input type="text" name="mautic_client_secret" value="<?=htmlspecialchars($mauticConfig['client_secret'] ?? '')?>"></div>
            </div>
          </div>
          <div class="field"><label>默认邮件模板 ID</label><input type="text" name="mautic_default_email_id" value="<?=htmlspecialchars($mauticConfig['default_email_id'] ?? '')?>" placeholder="在 Mautic 中创建的邮件 ID"></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="mautic_enabled" value="1" <?=($mauticConfig['enabled']??false)?'checked':''?> style="width:18px;height:18px">启用 Mautic</label></div>
          <div class="flex gap-2">
            <button type="submit" name="save_mautic" class="btn btn-primary">保存配置</button>
            <a href="?tab=mautic&mautic_test=1" class="btn btn-ghost">测试连接</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>PHP SDK 方法</h2>
        <p class="text-sm text-muted">通过 Composer 安装 <code>mautic/api-library</code> 后可用</p>
        <table>
          <thead><tr><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>Mautic::createContact($email, $data)</code></td><td>创建/更新联系人</td></tr>
            <tr><td><code>Mautic::sendEmail($emailId, $contactId)</code></td><td>发送邮件给指定联系人</td></tr>
            <tr><td><code>Mautic::addToCampaign($campaignId, $contactId)</code></td><td>将联系人加入营销活动</td></tr>
            <tr><td><code>Mautic::getContacts($search)</code></td><td>搜索联系人</td></tr>
            <tr><td><code>Mautic::createSegment($name, $filters)</code></td><td>创建用户分群</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ Newsletter Tab ═══ -->
    <div class="tab-content <?=$activeTab==='newsletter'?'active':''?>" id="tab-newsletter">
      <div class="card">
        <h2>发送 Newsletter</h2>
        <p class="text-sm text-muted mb-4">选择一篇文章，通过 BillionMail 发送为邮件简报。</p>
        <?php
        $published = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));
        $subscribers = json_read(DATA_DIR . '/newsletter/subscribers.json');
        $subCount = count(array_filter($subscribers, fn($s) => ($s['status'] ?? 'subscribed') === 'subscribed'));
        if (empty($published)): ?><div class="msg msg-info">暂无已发布的文章，请先在文章管理发布文章。</div>
        <?php else: ?>
        <div class="msg msg-info">当前订阅者：<strong><?=$subCount?></strong> 人（来自前台 Newsletter 订阅）</div>
        <form method="post">
          <?= csrf_field() ?>
          <div class="field"><label>选择文章</label>
            <select name="newsletter_article" required>
              <option value="">— 选择 —</option>
              <?php foreach ($published as $a): ?>
              <option value="<?=htmlspecialchars($a['id'])?>"><?=htmlspecialchars($a['title'])?> (<?=substr($a['created_at']??'',0,10)?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>邮件模板 <span class="hint">· 留空用默认图文版</span></label>
            <select name="mail_template">
              <option value="">— 默认图文版 —</option>
              <?php foreach (mailc_templates() as $t): ?>
              <option value="<?=htmlspecialchars($t['id'])?>"><?=htmlspecialchars($t['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>发送方式</label>
            <select name="newsletter_mode">
              <option value="test">测试：仅发送到配置的发件人邮箱</option>
              <option value="subscribers">批量：发送给全部 <?=$subCount?> 位订阅者</option>
            </select>
          </div>
          <button type="submit" name="send_newsletter" class="btn btn-primary" onclick="return confirm('确认发送?')">📬 发送 Newsletter</button>
        </form>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Newsletter 模板变量</h2>
        <p class="text-sm text-muted">在 BillionMail 模板中可使用以下变量</p>
        <table><thead><tr><th>变量</th><th>说明</th></tr></thead>
        <tbody>
          <tr><td><code>{{title}}</code></td><td>文章标题</td></tr>
          <tr><td><code>{{subject}}</code></td><td>邮件主题</td></tr>
          <tr><td><code>{{content}}</code></td><td>文章内容摘要（前 500 字）</td></tr>
          <tr><td><code>{{article_url}}</code></td><td>文章链接</td></tr>
        </tbody></table>
      </div>

      <?php
      // 邮件模板保存
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
          csrf_verify();
          $tpls = mailc_templates();
          $tpls[] = ['id' => 'tpl_' . date('YmdHis'), 'name' => trim($_POST['tpl_name'] ?? ''), 'html' => $_POST['tpl_html'] ?? '', 'created_at' => date('Y-m-d H:i:s')];
          mailc_save_templates($tpls);
          flash('success', '邮件模板已保存');
          header('Location: /xmp/email');
          exit;
      }
      if (isset($_GET['del_tpl'])) {
          mailc_save_templates(array_values(array_filter(mailc_templates(), fn($t) => ($t['id'] ?? '') !== $_GET['del_tpl'])));
          header('Location: /xmp/email');
          exit;
      }
      $templates = mailc_templates();
      // 最近群发统计
      $nlLog = json_read($newsletterFile);
      $lastCamp = $nlLog ? end($nlLog) : null;
      $lastStats = $lastCamp && !empty($lastCamp['campaign']) ? mailc_campaign_stats($lastCamp['campaign'], $lastCamp['recipients'] ?? 0) : null;
      ?>
      <div class="card">
        <h2>📧 邮件模板 + 效果统计</h2>
        <p class="sub">群发可选模板（支持 {{title}} {{subject}} {{content}} {{article_url}} {{unsubscribe}}）；自动加打开统计与退订链接</p>
        <?php if ($lastStats): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px">
          <div style="padding:12px;border-radius:10px;background:var(--bg)"><div class="text-lg font-bold"><?=$lastStats['sent']?></div><div class="text-xs text-muted">发送（最近一刊）</div></div>
          <div style="padding:12px;border-radius:10px;background:var(--bg)"><div class="text-lg font-bold" style="color:var(--ok)"><?=$lastStats['opens']?> · <?=$lastStats['open_rate']?>%</div><div class="text-xs text-muted">打开数 · 打开率</div></div>
          <div style="padding:12px;border-radius:10px;background:var(--bg)"><div class="text-lg font-bold" style="color:var(--accent)"><?=$lastStats['clicks']?> · <?=$lastStats['click_rate']?>%</div><div class="text-xs text-muted">点击数 · 点击率</div></div>
        </div>
        <?php endif; ?>
        <?php if (empty($templates)): ?><p class="text-sm text-muted">暂无模板，用下方表单创建（不选模板则用默认图文版）</p><?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
          <?php foreach ($templates as $t): ?>
          <span style="padding:4px 12px;border:1px solid var(--border);border-radius:999px;font-size:12px"><?=htmlspecialchars($t['name'])?> <a href="?del_tpl=<?=urlencode($t['id'])?>" style="color:var(--danger)">✕</a></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px;color:var(--muted)">+ 新建邮件模板</summary>
        <form method="post" style="margin-top:10px">
          <?= csrf_field() ?>
          <div class="field"><label>模板名称</label><input type="text" name="tpl_name" placeholder="如：Newsletter 图文版"></div>
          <div class="field"><label>HTML 内容 <span class="hint">· 支持变量与 {{unsubscribe}} 退订链接</span></label><textarea name="tpl_html" rows="6" placeholder="<h2>{{title}}</h2><p>{{content}}</p><p style='text-align:center'><a href='{{article_url}}'>阅读全文</a></p><p style='font-size:11px;color:#999'>不想收到？<a href='{{unsubscribe}}'>一键退订</a></p>"></textarea></div>
          <button class="btn btn-s btn-sm">保存模板</button>
        </form>
        </details>
      </div>

      <div class="card">
        <h2>订阅者列表 (<?=count($subscribers)?>)</h2>
        <?php if (empty($subscribers)): ?>
        <div class="empty">暂无订阅者。前台文章内嵌 Newsletter 卡片或社区页订阅表单会写入此列表。</div>
        <?php else: ?>
        <div style="overflow:auto">
        <table>
          <thead><tr><th>邮箱</th><th>来源</th><th>订阅时间</th><th>状态</th></tr></thead>
          <tbody>
            <?php foreach (array_slice(array_reverse($subscribers), 0, 50) as $s): ?>
            <tr>
              <td><?=htmlspecialchars($s['email'] ?? '')?></td>
              <td class="text-sm text-muted"><?=htmlspecialchars($s['source'] ?? 'article')?></td>
              <td class="text-sm text-muted"><?=htmlspecialchars(substr($s['created_at'] ?? '', 0, 16))?></td>
              <td><?=($s['status'] ?? 'subscribed') === 'subscribed' ? '✅' : '退订'?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══ Log Tab ═══ -->
    <div class="tab-content <?=$activeTab==='log'?'active':''?>" id="tab-log">
      <div class="card" style="padding:0;overflow:auto">
        <?php if (empty($newsletterLog)): ?>
        <div class="empty">暂无发送记录</div>
        <?php else: ?>
        <table>
          <thead><tr><th>时间</th><th>文章</th></tr></thead>
          <tbody>
            <?php foreach (array_reverse($newsletterLog) as $l): ?>
            <tr><td class="text-sm text-muted"><?=htmlspecialchars($l['sent_at'] ?? '')?></td><td><?=htmlspecialchars($l['title'] ?? '')?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tabs a').forEach(function(a) {
    a.classList.toggle('active', a.getAttribute('href') === '?tab=' + tab);
  });
  document.querySelectorAll('.tab-content').forEach(function(tc) { tc.classList.remove('active'); });
  document.getElementById('tab-' + tab).classList.add('active');
  history.replaceState(null, '', '?tab=' + tab);
}

// Mautic auth mode toggle
document.querySelector('select[name="mautic_version"]')?.addEventListener('change', function() {
  document.getElementById('mauticBasic').style.display = this.value === 'BasicAuth' ? 'block' : 'none';
  document.getElementById('mauticOAuth').style.display = this.value === 'OAuth2' ? 'block' : 'none';
});
</script>
<?php admin_footer(); ?>
