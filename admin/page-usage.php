<?php
declare(strict_types=1);
/**
 * 后台使用分析（哪些页在被用，哪些从没被打开）
 *
 * 这一页存在的唯一目的：把「裁剪导航」从猜测变成有依据的动作。
 * 数据来自 lib/PageUsage.php 的按天聚合计数，页面清单来自 lib/AdminInventory.php（同一份口径）。
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');
require_once __DIR__ . '/../lib/PageUsage.php';

$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save_usage_cfg') {
    page_usage_config_set(['enabled' => isset($_POST['enabled']), 'retain_days' => (int) ($_POST['retain_days'] ?? 90)]);
    $message = '设置已保存';
}

$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;

$cfg     = page_usage_config();
$sum     = page_usage_summary($days);
$never   = page_usage_never_opened($days);
$allPages = count(admin_pages());
$maxDaily = max(1, max($sum['daily'] ?: [1]));

if (!defined('OF_EMBED')) admin_header('后台使用分析');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
<?php endif; ?>

<h1 style="margin-bottom:6px">后台使用分析</h1>
<p class="sub">按天聚合的页面打开次数（只记页面与用户名，不记 IP、不记查询串）。用来回答「哪些功能没人用」，而不是追踪个人。</p>
<?php if ($message !== ''): ?><?= msg('success', $message) ?><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:16px 0">
  <div class="cdp-stat"><div class="num" style="color:var(--accent)"><?= (int) $sum['total_pv'] ?></div><div class="lab">最近 <?= $days ?> 天打开次数</div></div>
  <div class="cdp-stat"><div class="num" style="color:var(--ok)"><?= count($sum['pages']) ?> / <?= $allPages ?></div><div class="lab">被打开过的页面</div></div>
  <div class="cdp-stat"><div class="num" style="color:var(--warn,var(--accent))"><?= count($never) ?></div><div class="lab">从未被打开</div></div>
  <div class="cdp-stat"><div class="num"><?= (int) $sum['days_with_data'] ?></div><div class="lab">有数据的天数</div></div>
</div>

<div class="card">
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:0">
    <span class="lab">统计区间</span>
    <?php foreach ([7 => '近 7 天', 30 => '近 30 天', 90 => '近 90 天'] as $d => $label): ?>
      <a class="btn btn-sm <?= $days === $d ? 'btn-primary' : '' ?>" href="/xmp/audit-log?sub=usage&amp;days=<?= $d ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </form>
</div>

<?php if ($sum['total_pv'] === 0): ?>
  <div class="card">
    <div class="empty">
      <?= empty_state(
            '还没有访问数据',
            $cfg['enabled']
              ? '埋点已开启，但这段时间还没有记录到后台页面打开。正常使用几天后，这里会显示真实的页面使用分布。'
              : '埋点当前是关闭状态，所以没有数据。在下方打开它，之后的访问才会被记录。',
            '先去看审计日志（写操作留痕）',
            '/xmp/audit-log') ?>
    </div>
  </div>
<?php else: ?>

  <div class="card">
    <h3 style="margin-top:0">每日打开次数</h3>
    <div style="overflow-x:auto">
      <div style="display:flex;align-items:flex-end;gap:3px;height:110px;min-width:320px">
        <?php foreach ($sum['daily'] as $day => $pv): ?>
          <div title="<?= htmlspecialchars($day) ?>：<?= (int) $pv ?> 次"
               style="flex:1;min-width:4px;height:<?= max(2, (int) round($pv / $maxDaily * 100)) ?>%;background:var(--accent);opacity:<?= $pv > 0 ? '.85' : '.18' ?>;border-radius:2px 2px 0 0"></div>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="sub" style="margin:8px 0 0"><?= htmlspecialchars($sum['from']) ?> → <?= htmlspecialchars($sum['to']) ?>，峰值 <?= (int) $maxDaily ?> 次/天</p>
  </div>

  <div class="card">
    <h3 style="margin-top:0">使用排行（前 30）</h3>
    <div style="overflow-x:auto;max-height:520px;overflow-y:auto">
      <table>
        <thead><tr><th>页面</th><th>地址</th><th style="text-align:right">打开次数</th><th style="text-align:right">用户数</th><th>最近一次</th></tr></thead>
        <tbody>
        <?php $i = 0; foreach ($sum['pages'] as $slug => $row): if (++$i > 30) break; ?>
          <tr>
            <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($slug) ?></td>
            <td style="font-size:12px;color:var(--muted)"><a href="<?= htmlspecialchars(admin_pages()[$slug] ?? '#') ?>"><?= htmlspecialchars(admin_pages()[$slug] ?? '—') ?></a></td>
            <td style="text-align:right;font-weight:600"><?= (int) $row['pv'] ?></td>
            <td style="text-align:right"><?= (int) $row['uv'] ?></td>
            <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= htmlspecialchars($row['last']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3 style="margin-top:0">从未被打开（<?= count($never) ?> 页）</h3>
    <p class="sub">这是<strong>裁剪导航的依据，不是删除清单</strong>。统计区间越长越可信；区间内没人打开，通常说明入口该收进二级，而不是该删。</p>
    <div style="overflow-x:auto;max-height:420px;overflow-y:auto">
      <table>
        <thead><tr><th>页面</th><th>地址</th></tr></thead>
        <tbody>
        <?php if ($never === []): ?>
          <tr><td colspan="2" class="empty"><?= empty_state('每个页面都被打开过', '在这个区间里没有完全没人用的后台页；可以换更短的区间再看。', '换成近 7 天再看', '/xmp/audit-log?sub=usage&days=7') ?></td></tr>
        <?php else: foreach (array_slice($never, 0, 80) as $row): ?>
          <tr>
            <td style="font-size:13px"><?= htmlspecialchars($row['page']) ?></td>
            <td style="font-size:12px;color:var(--muted)"><a href="<?= htmlspecialchars($row['path']) ?>"><?= htmlspecialchars($row['path']) ?></a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0">埋点设置</h3>
  <form method="post" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_usage_cfg">
    <label style="display:flex;gap:8px;align-items:center">
      <input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
      <span>记录后台页面访问</span>
    </label>
    <div class="field" style="margin-bottom:0;min-width:160px">
      <label>保留天数</label>
      <input class="inp" type="number" name="retain_days" min="7" max="400" value="<?= (int) $cfg['retain_days'] ?>" style="height:36px">
    </div>
    <button class="btn btn-primary btn-sm" type="submit" style="height:36px">保存</button>
  </form>
  <p class="sub" style="margin:10px 0 0">过期数据由 <code>/api/cron</code> 每天清理一次。代码里 <code>define('OF_NO_PAGE_USAGE', 1)</code> 可全局关闭。</p>
</div>

<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
