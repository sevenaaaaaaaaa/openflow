<?php
/**
 * 存储与性能体检 — 数据文件大小 / SQLite 统计 / 风险识别 / 一键清理
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/StorageSystem.php';
require_login();
require_perm('settings');

$message = '';
// 执行清理
if (isset($_POST['maintain'])) {
    $cleaned = storage_maintain();
    $message = '维护完成：' . implode('；', $cleaned);
}
$scan = storage_scan();
$risks = storage_risks($scan);
$totalJson = array_sum(array_map(fn($j) => $j['size'], $scan['json']));
$totalUp = $scan['uploads']['total'] ?? 0;

if (!defined('OF_EMBED')) admin_header('存储与性能');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('storage'); ?>
  <div class="main">
<?php endif; ?>
    <h1> 存储与性能</h1>
    <p class="sub">数据文件大小 · SQLite 统计 · 风险识别 · 一键维护</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 概览 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
      <div class="card" style="border-left:4px solid #7dd3fc"><div class="text-sm text-muted">📄 JSON 数据</div><div style="font-size:24px;font-weight:800"><?=storage_fmt($totalJson)?></div></div>
      <div class="card" style="border-left:4px solid #7dd3fc"><div class="text-sm text-muted">🖼️ 素材上传</div><div style="font-size:24px;font-weight:800"><?=storage_fmt($totalUp)?></div><div class="text-sm text-muted"><?=$scan['uploads']['files']?> 个文件</div></div>
      <div class="card" style="border-left:4px solid #f59e0b"><div class="text-sm text-muted">⚠️ 风险项</div><div style="font-size:24px;font-weight:800;color:<?=$risks?'var(--danger)':'var(--ok)'?>"><?=count($risks)?></div></div>
      <div class="card"><div class="text-sm text-muted">🗄️ SQLite 表</div><div style="font-size:24px;font-weight:800"><?=count($scan['sqlite'])?></div></div>
    </div>

    <!-- 风险 + 维护 -->
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <h2 style="font-size:15px">🛠️ 维护</h2>
        <form method="post">
          <?= csrf_field() ?>
      </div>
      <?php if ($risks): ?>
      <div style="margin-top:12px">
        <?php foreach ($risks as $r): ?>
        <div style="padding:8px 12px;border-radius:8px;margin-bottom:6px;font-size:13px;background:<?=$r['level']==='warn'?'rgba(220,38,38,.08)':'var(--surface-2)'?>;color:<?=$r['level']==='warn'?'var(--danger)':'var(--text-2)'?>"><?=$r['level']==='warn'?'⚠️':'ℹ️'?> <?=htmlspecialchars($r['msg'])?></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-sm" style="color:var(--ok);margin-top:10px">✅ 未发现明显风险</p>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="st-grid">
      <!-- 大 JSON 文件 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:20px 20px 0">📄 JSON 数据文件 Top <?=count($scan['json'])?></h2>
        <table>
          <thead><tr><th>文件</th><th>大小</th></tr></thead>
          <tbody>
            <?php if (empty($scan['json'])): ?><tr><td colspan="2" class="empty">无数据</td></tr><?php endif; ?>
            <?php foreach (array_slice($scan['json'], 0, 15) as $j): ?>
            <tr>
              <td style="font-size:12px;font-family:var(--mono)"><?=htmlspecialchars($j['path'])?></td>
              <td><?=storage_fmt($j['size'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- SQLite 表 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:20px 20px 0">🗄️ SQLite 表行数</h2>
        <table>
          <thead><tr><th>表</th><th>行数</th></tr></thead>
          <tbody>
            <?php if (empty($scan['sqlite'])): ?><tr><td colspan="2" class="empty">无表</td></tr><?php endif; ?>
            <?php foreach ($scan['sqlite'] as $t): ?>
            <tr><td style="font-family:var(--mono);font-size:12px"><?=htmlspecialchars($t['table'])?></td><td><?=number_format($t['count'])?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.st-grid{grid-template-columns:1fr!important}}</style>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
