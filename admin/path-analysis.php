<?php
/**
 * 路径分析 — 用户访问路径 / 入口出口 / 跳出率 / 热门流转
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/AnalyticsSystem.php';
require_login();
require_perm('settings');

$days = min(90, max(1, (int)($_GET['days'] ?? 30)));
$pageFilter = trim($_GET['page'] ?? '');
$data = analytics_paths($days, 10, $pageFilter);

function pfmt(string $p): string {
    return htmlspecialchars($p);
}
admin_header('路径分析');
?>
<div class="admin-layout">
  <?php admin_sidebar('path-analysis'); ?>
  <div class="main">
    <h1>🧭 路径分析</h1>
    <p class="sub">基于埋点事件的用户访问路径 · 会话切分（间隔 >30 分钟）· 入口/出口 · 跳出率</p>

    <form method="get" class="card" style="margin-bottom:16px;padding:16px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <select name="days" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <?php foreach ([7,14,30,60,90] as $d): ?>
          <option value="<?=$d?>" <?=$days===$d?'selected':''?>><?=$d?> 天</option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="page" value="<?=htmlspecialchars($pageFilter)?>" placeholder="过滤页面路径（可选，如 /article）" style="flex:1;min-width:200px;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
        <button type="submit" class="btn btn-primary">分析</button>
        <?php if ($pageFilter): ?><a href="path-analysis.php?days=<?=$days?>" class="btn btn-ghost">清除过滤</a><?php endif; ?>
      </div>
    </form>

    <div class="stats">
      <div class="stat-card"><div class="num"><?=$data['sessions']?></div><div class="label">访问会话</div></div>
      <div class="stat-card"><div class="num"><?=$data['avg_pages']?></div><div class="label">平均浏览页数</div></div>
      <div class="stat-card"><div class="num" style="color:<?=$data['bounce_rate']>=60?'var(--danger)':($data['bounce_rate']>=40?'var(--warn)':'#16a34a')?>"><?=$data['bounce_rate']?>%</div><div class="label">跳出率</div></div>
      <div class="stat-card"><div class="num"><?=$data['single_page']?></div><div class="label">单页会话</div></div>
    </div>

    <?php if ($data['sessions'] === 0): ?>
    <div class="card"><div class="empty" style="padding:40px">暂无路径数据。前端埋点产生访问事件后，这里会自动统计。请确认前端已引入 /assets/inject.js。</div></div>
    <?php else: ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="path-grid">
      <!-- 热门 2 步流转 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:16px 20px 0">↔️ 热门页面流转（2 步）</h2>
        <table>
          <thead><tr><th>路径</th><th>次数</th></tr></thead>
          <tbody>
            <?php if (empty($data['pairs'])): ?><tr><td colspan="2" class="empty">暂无</td></tr><?php endif; ?>
            <?php $max = !empty($data['pairs']) ? max(array_values($data['pairs'])) : 1; foreach ($data['pairs'] as $k => $c): list($a, $b) = explode('|', $k); ?>
            <tr>
              <td><code><?=pfmt($a)?></code> <span style="color:var(--text-3)">→</span> <code><?=pfmt($b)?></code></td>
              <td><div style="display:flex;align-items:center;gap:8px"><div style="height:14px;border-radius:4px;background:linear-gradient(90deg,#86efac,#ddff0e);width:<?=round($c/$max*100)?>%"></div><strong><?=$c?></strong></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 热门完整路径 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:16px 20px 0">🔗 热门完整路径</h2>
        <table>
          <thead><tr><th>路径</th><th>会话数</th></tr></thead>
          <tbody>
            <?php if (empty($data['paths'])): ?><tr><td colspan="2" class="empty">暂无</td></tr><?php endif; ?>
            <?php foreach ($data['paths'] as $k => $c): $parts = explode('|', $k); ?>
            <tr>
              <td style="line-height:1.8"><?php foreach ($parts as $i => $p): ?><?php if ($i>0): ?><span style="color:var(--text-3)"> → </span><?php endif; ?><code style="font-size:11px"><?=pfmt($p)?></code><?php endforeach; ?></td>
              <td><strong><?=$c?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 入口页 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:16px 20px 0">🚪 热门入口页</h2>
        <table>
          <thead><tr><th>页面</th><th>进入</th></tr></thead>
          <tbody>
            <?php if (empty($data['entries'])): ?><tr><td colspan="2" class="empty">暂无</td></tr><?php endif; ?>
            <?php $maxE = !empty($data['entries']) ? max(array_values($data['entries'])) : 1; foreach ($data['entries'] as $p => $c): ?>
            <tr>
              <td><code><?=pfmt($p)?></code></td>
              <td><div style="display:flex;align-items:center;gap:8px"><div style="height:12px;border-radius:4px;background:#7dd3fc;width:<?=round($c/$maxE*100)?>%"></div><strong><?=$c?></strong></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 出口页 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:16px 20px 0">🚪 热门出口页</h2>
        <table>
          <thead><tr><th>页面</th><th>离开</th></tr></thead>
          <tbody>
            <?php if (empty($data['exits'])): ?><tr><td colspan="2" class="empty">暂无</td></tr><?php endif; ?>
            <?php $maxX = !empty($data['exits']) ? max(array_values($data['exits'])) : 1; foreach ($data['exits'] as $p => $c): ?>
            <tr>
              <td><code><?=pfmt($p)?></code></td>
              <td><div style="display:flex;align-items:center;gap:8px"><div style="height:12px;border-radius:4px;background:#f4a261;width:<?=round($c/$maxX*100)?>%"></div><strong><?=$c?></strong></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<style>@media(max-width:1000px){.path-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
