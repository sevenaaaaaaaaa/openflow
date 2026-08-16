<?php
/**
 * 内容审核规则配置 — 违禁词 / 竞品词 / 低质词 / 产品定位关键词
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/review-lib.php';
require_login();
require_perm('settings');

$rulesFile = DATA_DIR . '/review-rules.json';
$rules = review_rules();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $rules = [
        'banned_words' => array_filter(array_map('trim', explode("\n", $_POST['banned_words'] ?? ''))),
        'competitor_words' => array_filter(array_map('trim', explode("\n", $_POST['competitor_words'] ?? ''))),
        'low_quality' => array_filter(array_map('trim', explode("\n", $_POST['low_quality'] ?? ''))),
        'positioning_keywords' => array_filter(array_map('trim', explode("\n", $_POST['positioning_keywords'] ?? ''))),
    ];
    json_write($rulesFile, $rules);
    $message = '审核规则已保存';
}

admin_header('审核规则');
?>
<div class="admin-layout">
  <?php admin_sidebar('review-settings'); ?>
  <div class="main">
    <h1>审核规则</h1>
    <p class="sub">配置内容审核词库 · 命中任一规则即进入待审核状态</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🚫 违禁违规词 <span class="hint" style="font-weight:400">· 每行一个</span></h2>
        <p class="text-sm text-muted mb-4">命中即判定为违规内容，必须审核</p>
        <textarea name="banned_words" rows="8" style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars(implode("\n", $rules['banned_words']))?></textarea>
      </div>

      <div class="card">
        <h2>🏢 竞品词 <span class="hint" style="font-weight:400">· 每行一个</span></h2>
        <p class="text-sm text-muted mb-4">出现竞品品牌/产品名即进入审核，避免为竞品导流</p>
        <textarea name="competitor_words" rows="8" style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars(implode("\n", $rules['competitor_words']))?></textarea>
      </div>

      <div class="card">
        <h2>📉 低质量信号词 <span class="hint" style="font-weight:400">· 每行一个</span></h2>
        <p class="text-sm text-muted mb-4">出现这些词判定内容可能低质（另含自动规则：正文 <100 字、连续标点）</p>
        <textarea name="low_quality" rows="6" style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars(implode("\n", $rules['low_quality']))?></textarea>
      </div>

      <div class="card">
        <h2>🎯 产品定位关键词 <span class="hint" style="font-weight:400">· 每行一个</span></h2>
        <p class="text-sm text-muted mb-4">内容与这些关键词关联度过低（<15%）判定偏离产品定位</p>
        <textarea name="positioning_keywords" rows="8" style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars(implode("\n", $rules['positioning_keywords']))?></textarea>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存规则</button>
      <a href="reviews" class="btn btn-ghost">← 返回审核列表</a>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
