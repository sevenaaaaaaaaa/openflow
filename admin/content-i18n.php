<?php
/**
 * 内容多语言 —— 给文章挂语言版本、AI 一键初译、复核发布（BACKLOG T0-3）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ContentI18n.php';
require_login();
require_perm('articles');

$base = ci18n_base_locale();
$locales = array_values(array_filter(i18n_supported(), fn($l) => $l !== $base));
$msg = ''; $err = '';
$aid = $_GET['a'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $aid = $_POST['a'] ?? $aid;
    $loc = $_POST['locale'] ?? '';
    if ($act === 'ai_translate') {
        $art = get_article($aid);
        $r = $art ? ci18n_ai_translate($art, $loc) : ['ok' => false, 'error' => '文章不存在'];
        if ($r['ok']) { ci18n_set($aid, $loc, $r['fields']); audit("AI 初译 {$aid} → {$loc}", 'i18n'); $msg = 'AI 初译完成，已存为草稿，请复核后发布。'; }
        else $err = $r['error'] ?? 'AI 翻译失败';
    } elseif ($act === 'save_translation') {
        $r = ci18n_set($aid, $loc, [
            'title' => $_POST['title'] ?? '', 'content' => $_POST['content'] ?? '',
            'seo_title' => $_POST['seo_title'] ?? '', 'seo_desc' => $_POST['seo_desc'] ?? '',
            'status' => $_POST['status'] ?? 'draft',
        ]);
        $msg = $r['ok'] ? '译文已保存。' : ($r['error'] ?? '保存失败');
    } elseif ($act === 'delete_translation') {
        ci18n_delete($aid, $loc); $msg = '译文已删除。';
    }
}

$articles = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') !== 'trashed'));
$current = $aid ? get_article($aid) : null;

admin_header('内容多语言');
?>
<div style="max-width:1080px">
  <h1 style="margin:0 0 4px">🌐 内容多语言</h1>
  <p class="v-sub" style="margin:0 0 14px">基准语言 <strong><?=htmlspecialchars(i18n_native($base))?></strong>。给文章挂其它语言版本，可 AI 一键初译，复核后发布；前台按访客语言自动切换并输出 hreflang。</p>
  <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <?php if (!$current): ?>
    <div style="font-weight:700;margin-bottom:8px">选择文章</div>
    <?php if (empty($locales)): ?>
      <div class="card" style="padding:16px;color:var(--faint)">当前只启用了一种语言。到「语言设置」里增加语言后，这里才能建译文。</div>
    <?php endif; ?>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach (array_slice($articles, 0, 100) as $a): $done = array_keys(ci18n_translations($a)); ?>
      <a href="/xmp/content-i18n?a=<?=urlencode($a['id'])?>" class="card" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px">
        <span><strong><?=htmlspecialchars($a['title'] ?? '无标题')?></strong></span>
        <span style="font-size:12px;color:var(--faint)"><?php
          $tags = [];
          foreach ($locales as $l) { $has = in_array($l, $done, true); $tags[] = ($has ? '✅' : '○') . ' ' . htmlspecialchars($l); }
          echo implode(' &nbsp; ', $tags);
        ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <a href="/xmp/content-i18n" style="font-size:13px">← 返回文章列表</a>
    <div style="font-weight:800;font-size:18px;margin:8px 0 4px"><?=htmlspecialchars($current['title'] ?? '')?></div>
    <div class="v-sub" style="margin-bottom:14px">基准（<?=htmlspecialchars(i18n_native($base))?>）内容为源，下面每种语言一份译文。</div>

    <?php foreach ($locales as $loc): $tr = ci18n_translations($current)[$loc] ?? null; ?>
    <div class="card" style="padding:16px;margin-bottom:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <div style="font-weight:700"><?=htmlspecialchars(i18n_native($loc))?> <span style="font-size:12px;color:var(--faint)">(<?=htmlspecialchars($loc)?>)</span>
          <?php if ($tr): ?><span style="font-size:11px;padding:1px 8px;border-radius:999px;background:<?=($tr['status']??'')==='published'?'#dcfce7':'#f1f5f9'?>;color:<?=($tr['status']??'')==='published'?'#166534':'#64748b'?>"><?=($tr['status']??'')==='published'?'已发布':'草稿'?></span><?php endif; ?>
        </div>
        <div style="display:flex;gap:6px">
          <form method="post" style="margin:0" onsubmit="return confirm('用 AI 从基准内容初译到<?=htmlspecialchars(i18n_native($loc))?>？会覆盖当前译文草稿。')">
            <?= csrf_field() ?><input type="hidden" name="action" value="ai_translate"><input type="hidden" name="a" value="<?=htmlspecialchars($current['id'])?>"><input type="hidden" name="locale" value="<?=htmlspecialchars($loc)?>">
            <button class="btn btn-ghost btn-sm">✨ AI 初译</button>
          </form>
          <?php if ($tr): ?>
          <form method="post" style="margin:0" onsubmit="return confirm('删除该语言译文？')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_translation"><input type="hidden" name="a" value="<?=htmlspecialchars($current['id'])?>"><input type="hidden" name="locale" value="<?=htmlspecialchars($loc)?>"><button class="btn btn-ghost btn-sm" style="color:#dc2626">删除</button></form>
          <?php endif; ?>
        </div>
      </div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_translation"><input type="hidden" name="a" value="<?=htmlspecialchars($current['id'])?>"><input type="hidden" name="locale" value="<?=htmlspecialchars($loc)?>">
        <input name="title" placeholder="标题（<?=htmlspecialchars($loc)?>）" value="<?=htmlspecialchars($tr['title'] ?? '')?>" style="width:100%;margin-bottom:8px">
        <textarea name="content" rows="8" placeholder="正文（保留 HTML 结构）" style="width:100%;margin-bottom:8px;font-family:inherit"><?=htmlspecialchars($tr['content'] ?? '')?></textarea>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
          <input name="seo_title" placeholder="SEO 标题" value="<?=htmlspecialchars($tr['seo_title'] ?? '')?>" style="flex:1;min-width:160px">
          <input name="seo_desc" placeholder="SEO 描述" value="<?=htmlspecialchars($tr['seo_desc'] ?? '')?>" style="flex:2;min-width:200px">
          <select name="status" style="width:110px"><option value="draft" <?=($tr['status']??'draft')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($tr['status']??'')==='published'?'selected':''?>>发布</option></select>
        </div>
        <button class="btn btn-primary btn-sm">保存译文</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
