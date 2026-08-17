<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('version-diff');
admin_header('版本对比');

$articlesFile = DATA_DIR . '/articles.json';
$articles = json_read($articlesFile);
$selectedA = $_GET['a'] ?? '';
$selectedB = $_GET['b'] ?? '';
$diff = null;
$stats = null;

if ($selectedA && $selectedB) {
    $a = $articles[$selectedA] ?? null;
    $b = $articles[$selectedB] ?? null;
    if ($a && $b) {
        $contentA = $a['content'] ?? $a['body'] ?? '';
        $contentB = $b['content'] ?? $b['body'] ?? '';
        $diff = VersionDiff::diff($contentA, $contentB);
        $stats = VersionDiff::stats($diff);
    }
}
?>
<div class="admin-layout">
  <?php admin_sidebar('version-diff'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 版本对比</h1>
    </div>
    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px;margin-bottom:24px">
      <form method="GET" style="display:grid;grid-template-columns:1fr auto 1fr auto;gap:16px;align-items:end">
        <div>
          <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">版本 A (旧)</label>
          <select name="a" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
            <option value="">选择文章...</option>
            <?php foreach ($articles as $id => $a): ?>
              <option value="<?=$id?>" <?=selected($id, $selectedA)?>><?=h($a['title'] ?? $id)?> (<?=date('m/d H:i', strtotime($a['updated_at'] ?? $a['created_at'] ?? 'now'))?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="font-size:20px;color:var(--muted);padding-bottom:8px">⟷</div>
        <div>
          <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">版本 B (新)</label>
          <select name="b" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
            <option value="">选择文章...</option>
            <?php foreach ($articles as $id => $a): ?>
              <option value="<?=$id?>" <?=selected($id, $selectedB)?>><?=h($a['title'] ?? $id)?> (<?=date('m/d H:i', strtotime($a['updated_at'] ?? $a['created_at'] ?? 'now'))?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">对比</button>
      </form>
    </div>
    <?php if ($diff !== null): ?>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:16px">
        <div style="padding:16px;background:var(--surface);border-radius:10px;border:1px solid var(--border)">
          <div style="font-size:13px;color:var(--muted);margin-bottom:4px">版本 A</div>
          <div style="font-size:14px;font-weight:600"><?=h($articles[$selectedA]['title'] ?? $selectedA)?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px"><?=h($articles[$selectedA]['updated_at'] ?? $articles[$selectedA]['created_at'] ?? '')?></div>
        </div>
        <div style="padding:16px;background:var(--surface);border-radius:10px;border:1px solid var(--border)">
          <div style="font-size:13px;color:var(--muted);margin-bottom:4px">版本 B</div>
          <div style="font-size:14px;font-weight:600"><?=h($articles[$selectedB]['title'] ?? $selectedB)?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px"><?=h($articles[$selectedB]['updated_at'] ?? $articles[$selectedB]['created_at'] ?? '')?></div>
        </div>
      </div>
      <div style="display:flex;gap:16px;margin-bottom:16px">
        <span style="padding:6px 14px;border-radius:20px;background:#d1fae5;color:#065f46;font-size:13px">+ <?=$stats['inserts']?> 新增</span>
        <span style="padding:6px 14px;border-radius:20px;background:#fee2e2;color:#991b1b;font-size:13px">- <?=$stats['deletes']?> 删除</span>
        <span style="padding:6px 14px;border-radius:20px;background:var(--surface-2);color:var(--muted);font-size:13px">共 <?=count($diff)?> 行</span>
      </div>
      <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden">
        <div style="padding:12px 20px;background:var(--surface-2);border-bottom:1px solid var(--border);font-weight:600;font-size:14px">对比结果</div>
        <div style="font-family:var(--font-mono);font-size:13px;line-height:1.6;overflow-x:auto">
          <?php foreach ($diff as $d): ?>
            <?php
              $bg = $d['type'] === 'insert' ? '#d1fae5' : ($d['type'] === 'delete' ? '#fee2e2' : 'transparent');
              $color = $d['type'] === 'insert' ? '#065f46' : ($d['type'] === 'delete' ? '#991b1b' : 'var(--text)');
              $prefix = $d['type'] === 'insert' ? '+' : ($d['type'] === 'delete' ? '-' : ' ');
            ?>
            <div style="padding:2px 20px;background:<?=$bg?>;color:<?=$color?>;white-space:pre-wrap;border-bottom:1px solid rgba(0,0,0,.03)"><?=$prefix?> <?=h($d['line'])?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php elseif ($selectedA && $selectedB): ?>
      <div style="padding:40px;text-align:center;color:var(--muted)">未找到指定版本</div>
    <?php else: ?>
      <div style="padding:60px;text-align:center;color:var(--muted)">
        <div style="font-size:48px;margin-bottom:16px">⟷</div>
        <div style="font-size:16px;margin-bottom:8px">选择两个版本进行对比</div>
        <div style="font-size:13px">支持同一篇文章的不同编辑版本，或不同文章的内容对比</div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
