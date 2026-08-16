<?php
/**
 * MA 融合同步配置 — Mautic + BillionMail
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ma-sync-lib.php';
require_login();
require_perm('settings');

$cfgFile = DATA_DIR . '/ma-sync.json';
$cfg = json_read($cfgFile);
$message = '';
$error = '';

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $cfg = [
        'enabled' => isset($_POST['enabled']),
        'mautic_enabled' => isset($_POST['mautic_enabled']),
        'bm_enabled' => isset($_POST['bm_enabled']),
        'tags' => [
            'lead' => array_filter(array_map('trim', explode(',', $_POST['tags_lead'] ?? ''))),
            'newsletter' => array_filter(array_map('trim', explode(',', $_POST['tags_newsletter'] ?? ''))),
            'download' => array_filter(array_map('trim', explode(',', $_POST['tags_download'] ?? ''))),
        ],
        'campaigns' => [
            'lead' => (int)($_POST['campaign_lead'] ?? 0),
            'newsletter' => (int)($_POST['campaign_newsletter'] ?? 0),
            'download' => (int)($_POST['campaign_download'] ?? 0),
        ],
        'bm_welcome_subject' => trim($_POST['bm_welcome_subject'] ?? ''),
        'bm_welcome_html' => $_POST['bm_welcome_html'] ?? '',
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    json_write($cfgFile, $cfg);
    $message = '融合同步配置已保存';
}

// 测试同步（用最近一条线索或指定邮箱）
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sync'])) {
    csrf_verify();
    $testEmail = trim($_POST['test_email'] ?? '');
    if (empty($testEmail)) {
        $error = '请输入测试邮箱';
    } else {
        $testData = ['email' => $testEmail, 'name' => '测试用户', 'company' => '测试公司'];
        if ($_POST['test_target'] === 'mautic' && !empty($cfg['mautic_enabled'])) {
            $testResult = ma_sync_to_mautic($testData, 'lead', '测试同步');
        } elseif ($_POST['test_target'] === 'billionmail' && !empty($cfg['bm_enabled'])) {
            $testResult = ma_sync_to_billionmail($testData, $_POST['test_list_id'] ?? '');
        } else {
            $error = '请先启用对应平台同步';
        }
    }
}

// 重试失败的同步
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_log'])) {
    csrf_verify();
    $retryId = trim($_POST['retry_log'] ?? '');
    $retryResult = ma_retry($retryId);
    if ($retryResult['ok']) {
        flash('success', $retryResult['msg']);
    } else {
        flash('error', $retryResult['msg']);
    }
    header('Location: ma-sync.php');
    exit;
}

// 读取日志
$log = json_read(DATA_DIR . '/ma-sync-log.json');
$log = array_slice(array_reverse($log), 0, 50);

// 表单配置（显示 newsletter_list_id）
$forms = json_read(DATA_DIR . '/forms/index.json');
$newsletterForms = array_values(array_filter($forms, fn($f) => $f['type'] === 'newsletter'));

admin_header('MA 融合同步');
?>
<div class="admin-layout">
  <?php admin_sidebar('ma-sync'); ?>
  <div class="main">
    <h1>🔗 MA 融合同步</h1>
    <p class="sub">CMS 表单提交 → 自动同步到 Mautic（联系人/标签/Campaign）与 BillionMail（列表/欢迎信）</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>
    <?php if ($testResult): ?>
      <div class="msg msg-<?=$testResult['ok']?'success':'error'?>">测试结果：<?=htmlspecialchars(json_encode($testResult, JSON_UNESCAPED_UNICODE))?></div>
    <?php endif; ?>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <a href="email.php" class="btn btn-ghost">📧 邮件营销（BillionMail/Mautic 配置）</a>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>⚙️ 同步开关</h2>
        <div style="display:flex;gap:24px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=($cfg['enabled']??false)?'checked':''?> style="width:17px;height:17px"> <strong>启用 MA 融合同步</strong></label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="mautic_enabled" value="1" <?=($cfg['mautic_enabled']??false)?'checked':''?> style="width:17px;height:17px"> 同步到 <strong>Mautic</strong></label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="bm_enabled" value="1" <?=($cfg['bm_enabled']??false)?'checked':''?> style="width:17px;height:17px"> 同步到 <strong>BillionMail</strong></label>
        </div>
        <p class="text-sm text-muted mt-3">需先在「邮件营销」页配置好 BillionMail / Mautic 的连接参数并启用。</p>
      </div>

      <div class="card">
        <h2>🏷 Mautic 标签映射</h2>
        <p class="text-sm text-muted mb-4">表单类型 → 自动打的标签（逗号分隔）</p>
        <div class="field-row">
          <div class="field"><label>线索表单（lead）</label><input type="text" name="tags_lead" value="<?=htmlspecialchars(implode(',', $cfg['tags']['lead'] ?? []))?>" placeholder="如：网站线索,官网预约"></div>
          <div class="field"><label>订阅表单（newsletter）</label><input type="text" name="tags_newsletter" value="<?=htmlspecialchars(implode(',', $cfg['tags']['newsletter'] ?? []))?>" placeholder="如：订阅用户"></div>
        </div>
        <div class="field"><label>资料下载（download）</label><input type="text" name="tags_download" value="<?=htmlspecialchars(implode(',', $cfg['tags']['download'] ?? []))?>" placeholder="如：资料下载"></div>
      </div>

      <div class="card">
        <h2>🎯 Mautic Campaign 映射</h2>
        <p class="text-sm text-muted mb-4">表单类型 → 自动加入的 Campaign ID（0 表示不加）</p>
        <div class="field-row">
          <div class="field"><label>线索 Campaign ID</label><input type="number" name="campaign_lead" value="<?=htmlspecialchars($cfg['campaigns']['lead'] ?? 0)?>" min="0"></div>
          <div class="field"><label>订阅 Campaign ID</label><input type="number" name="campaign_newsletter" value="<?=htmlspecialchars($cfg['campaigns']['newsletter'] ?? 0)?>" min="0"></div>
          <div class="field"><label>下载 Campaign ID</label><input type="number" name="campaign_download" value="<?=htmlspecialchars($cfg['campaigns']['download'] ?? 0)?>" min="0"></div>
        </div>
      </div>

      <div class="card">
        <h2>📬 BillionMail 欢迎信</h2>
        <p class="text-sm text-muted mb-4">订阅后发送的欢迎邮件（需在表单配置里填 newsletter_list_id）</p>
        <div class="field"><label>主题</label><input type="text" name="bm_welcome_subject" value="<?=htmlspecialchars($cfg['bm_welcome_subject'] ?? '')?>" placeholder="欢迎订阅 OpenFlow Newsletter"></div>
        <div class="field"><label>HTML 内容</label><textarea name="bm_welcome_html" rows="5" style="font-family:var(--mono);font-size:13px"><?=htmlspecialchars($cfg['bm_welcome_html'] ?? '')?></textarea></div>
        <?php if ($newsletterForms): ?>
        <p class="text-sm text-muted">订阅表单的 list_id 在「表单管理」中设置：<?php foreach ($newsletterForms as $nf): ?><code style="margin-left:6px"><?=htmlspecialchars($nf['id'])?></code><?php endforeach; ?></p>
        <?php else: ?>
        <p class="text-sm text-muted" style="color:var(--warn)">⚠️ 尚未配置 newsletter 类型表单</p>
        <?php endif; ?>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存配置</button>
    </form>

    <div class="card" style="margin-top:20px">
      <h2>🧪 手动测试同步</h2>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:0"><label>测试邮箱</label><input type="email" name="test_email" placeholder="test@example.com" required style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px"></div>
        <div class="field" style="margin-bottom:0"><label>目标</label><select name="test_target" style="padding:8px;border:1.5px solid var(--border);border-radius:8px"><option value="mautic">Mautic</option><option value="billionmail">BillionMail</option></select></div>
        <div class="field" style="margin-bottom:0"><label>BillionMail list_id</label><input type="text" name="test_list_id" placeholder="列表 ID（选填）" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px"></div>
        <button type="submit" name="test_sync" class="btn btn-ghost">测试</button>
      </form>
    </div>

    <div class="card" style="padding:0;overflow-x:auto;margin-top:20px">
      <h2 style="padding:20px 20px 0">📜 同步日志</h2>
      <?php if (empty($log)): ?>
      <div class="empty" style="padding:24px">暂无同步日志</div>
      <?php else: ?>
      <table>
        <thead><tr><th>时间</th><th>级别</th><th>目标</th><th>详情</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($log as $l): ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars($l['time']??'')?></td>
            <td><span class="badge <?=($l['level']??'')==='success'?'badge-green':(($l['level']??'')==='error'?'badge-red':'badge-gray')?>" style="font-size:11px"><?=htmlspecialchars($l['level']??'')?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['target']??'')?></td>
            <td class="text-sm" style="max-width:400px"><?=htmlspecialchars($l['message']??'')?></td>
            <td>
              <?php if (($l['level']??'') === 'error' && !empty($l['retry'])): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="retry_log" value="<?=htmlspecialchars($l['id']??'')?>">
                <button type="submit" class="btn btn-primary btn-sm">↻ 重试</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
