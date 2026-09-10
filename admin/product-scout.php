<?php
/**
 * 产品发现 Loop — 每天自动发现新产品 → AI 撰写 → 草稿待审（E1）
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

$message = '';
$error = '';

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    csrf_verify();
    $kws = array_values(array_filter(array_map('trim', explode("\n", (string)($_POST['keywords'] ?? '')))));
    ProductScout::saveConfig([
        'enabled'      => !empty($_POST['enabled']),
        'per_day'      => max(1, min(10, (int)($_POST['per_day'] ?? 3))),
        'min_stars'    => max(0, (int)($_POST['min_stars'] ?? 50)),
        'max_age_days' => max(1, min(90, (int)($_POST['max_age_days'] ?? 30))),
        'keywords'     => $kws ?: ['ai agent'],
    ]);
    $message = '配置已保存';
}

// 手动立即运行
if (isset($_GET['run'])) {
    csrf_verify();
    $r = ProductScout::dailyRun(true);
    if (($r['status'] ?? '') === 'ok') {
        $message = '本次发现 ' . ($r['found'] ?? 0) . ' 个候选，生成 ' . count($r['made'] ?? []) . ' 篇草稿，去重跳过 ' . ($r['skipped_dup'] ?? 0) . ' 个';
    } else {
        $error = '运行未成功：' . ($r['detail'] ?? $r['status'] ?? '未知');
    }
}

$cfg = ProductScout::config();
$state = ProductScout::state();
$runs = array_reverse((array)($state['runs'] ?? []));

// 待审的 Loop 草稿
$drafts = array_values(array_filter(get_articles(), fn($a) =>
    ($a['status'] ?? '') === 'draft' && ($a['source'] ?? '') === 'product_scout'));
usort($drafts, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

admin_header('产品发现');
?>
<div class="admin-layout">
  <?php admin_sidebar('product-scout'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">产品发现 Loop</h1>
      <div class="flex gap-2 ml-auto">
        <a href="?run=1&csrf_token=<?=csrf_token()?>" class="btn btn-primary btn-sm" data-confirm="立即运行一次发现 Loop？（AI 撰写会消耗额度）">▶ 立即运行</a>
      </div>
    </div>
    <p class="sub">每天自动发现新产品（GitHub 趋势）→ AI 撰写速览 → 存为草稿待你把关。Loop 只产草稿，绝不自动发布。</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-card"><div class="num"><?=$cfg['enabled']?'运行中':'已停用'?></div><div class="label">Loop 状态</div></div>
      <div class="stat-card"><div class="num"><?=count($drafts)?></div><div class="label">待审草稿</div></div>
      <div class="stat-card"><div class="num"><?=count((array)($state['covered'] ?? []))?></div><div class="label">累计覆盖产品</div></div>
      <div class="stat-card"><div class="num"><?=htmlspecialchars($state['last_run_date'] ?? '—')?></div><div class="label">上次运行</div></div>
    </div>

    <!-- 待审草稿 -->
    <div class="card" style="margin-bottom:24px">
      <h2>待审草稿（<?=count($drafts)?>）</h2>
      <?php if (!$drafts): ?>
      <p class="hint">暂无。点右上角「立即运行」或等每日 cron 自动跑。</p>
      <?php else: ?>
      <table class="tbl">
        <thead><tr><th>标题</th><th>生成时间</th><th>摘要</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($drafts, 0, 20) as $d): ?>
          <tr>
            <td><strong><?=htmlspecialchars($d['title'])?></strong><br><code class="hint"><?=htmlspecialchars($d['slug'])?></code></td>
            <td class="nowrap"><?=htmlspecialchars($d['created_at'] ?? '')?></td>
            <td class="hint"><?=htmlspecialchars(mb_substr($d['excerpt'] ?? '', 0, 80))?>…</td>
            <td class="nowrap">
              <a href="/xmp/article-edit?id=<?=urlencode($d['id'])?>" class="btn btn-sm btn-primary">编辑 / 发布</a>
              <a href="/xmp/articles?delete=<?=urlencode($d['id'])?>" class="btn btn-sm btn-ghost" data-confirm="丢弃这篇草稿？">丢弃</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- 配置 -->
    <div class="card" style="margin-bottom:24px">
      <h2>Loop 配置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_config" value="1">
        <div class="field-row">
          <div class="field"><label>状态</label>
            <label class="flex items-center gap-2" style="font-weight:400"><input type="checkbox" name="enabled" <?=$cfg['enabled']?'checked':''?>> 启用每日自动发现</label>
          </div>
          <div class="field"><label>每天最多产出</label><input type="number" name="per_day" value="<?=$cfg['per_day']?>" min="1" max="10"></div>
          <div class="field"><label>最低星数</label><input type="number" name="min_stars" value="<?=$cfg['min_stars']?>" min="0"></div>
          <div class="field"><label>只发现最近几天内创建</label><input type="number" name="max_age_days" value="<?=$cfg['max_age_days']?>" min="1" max="90"></div>
        </div>
        <div class="field"><label>发现关键词（每行一个，GitHub 搜索用）</label>
          <textarea name="keywords" rows="5"><?=htmlspecialchars(implode("\n", (array)$cfg['keywords']))?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">保存配置</button>
        <span class="hint" style="margin-left:10px">AI 未配置时自动降级为模板草稿（仓库元数据直排），Loop 不会断。</span>
      </form>
    </div>

    <!-- 运行日志 -->
    <div class="card">
      <h2>运行日志</h2>
      <?php if (!$runs): ?><p class="hint">还没有运行记录。</p><?php else: ?>
      <table class="tbl">
        <thead><tr><th>时间</th><th>发现候选</th><th>生成草稿</th><th>去重跳过</th><th>明细</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($runs, 0, 30) as $r): ?>
          <tr>
            <td class="nowrap"><?=date('Y-m-d H:i', $r['ts'] ?? 0)?></td>
            <td><?=$r['found'] ?? 0?></td>
            <td><?=$r['made'] ?? 0?></td>
            <td><?=$r['skipped_dup'] ?? 0?></td>
            <td class="hint"><?=htmlspecialchars(implode('、', array_column((array)($r['items'] ?? []), 'name')))?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
