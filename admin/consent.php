<?php
/**
 * 同意与数据保留 —— 合规设置（BACKLOG T1-5）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ConsentSystem.php';
require_login();
require_perm('settings');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'purge_now') {
        $r = consent_purge_expired();
        audit('手动执行数据保留清理', 'settings');
        $msg = $r['skipped'] ? '未设置保留期，跳过。' : "清理完成：事件 {$r['events']} 条、画像 {$r['profiles']} 条。";
    } else {
        consent_save($_POST);
        audit('更新同意与保留策略', 'settings');
        $msg = '已保存。';
    }
}
$c = consent_settings();
$modes = ['off' => '不启用（默认，行为不变）', 'implied' => '默认同意，可拒绝（opt-out）', 'explicit' => '必须明确同意（opt-in / GDPR 式）'];
admin_header('同意与数据保留');
?>
<div style="max-width:720px">
  <h1 style="margin:0 0 4px">🛡 同意与数据保留</h1>
  <p class="v-sub" style="margin:0 0 16px">出海与个保法刚需：没拿到同意就别建画像，采集了也不该永久留。这里一处设定，采集链路与清理任务统一遵守。</p>
  <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:14px;border-left:3px solid #16a34a"><?=htmlspecialchars($msg)?></div><?php endif; ?>

  <form method="post" class="card" style="padding:20px">
    <?= csrf_field() ?>
    <div style="margin-bottom:16px">
      <label style="display:block;font-weight:600;margin-bottom:4px">同意模式</label>
      <div class="v-sub" style="margin-bottom:6px">explicit 模式下，访客未点「同意」前不采集任何行为、不建画像。</div>
      <?php foreach ($modes as $k => $label): ?>
      <label style="display:block;font-size:14px;margin:4px 0"><input type="radio" name="mode" value="<?=$k?>" <?=$c['mode']===$k?'checked':''?>> <?=htmlspecialchars($label)?></label>
      <?php endforeach; ?>
    </div>
    <div style="margin-bottom:16px">
      <label style="display:block;font-weight:600;margin-bottom:4px">横幅文案</label>
      <input name="banner_text" value="<?=htmlspecialchars($c['banner_text'])?>" style="width:100%">
    </div>
    <div style="margin-bottom:20px">
      <label style="display:block;font-weight:600;margin-bottom:4px">数据保留期（天）</label>
      <div class="v-sub" style="margin-bottom:6px">超期的行为事件与画像会被自动清理。0 = 不启用（仍有 90 天兜底清理）。</div>
      <input name="retention_days" type="number" min="0" step="1" value="<?=(int)$c['retention_days']?>" style="width:140px"> 天
    </div>
    <button class="btn btn-primary">保存</button>
  </form>

  <form method="post" class="card" style="padding:16px;margin-top:14px">
    <?= csrf_field() ?><input type="hidden" name="action" value="purge_now">
    <div style="font-weight:700;margin-bottom:6px">立即执行清理</div>
    <div class="v-sub" style="margin-bottom:10px">按当前保留期立刻清理一次（平时由 cron 每 24 小时自动跑）。</div>
    <button class="btn btn-ghost btn-sm" data-confirm="将永久删除超期数据，确认？">立即清理</button>
  </form>
</div>
<?php admin_footer(); ?>
