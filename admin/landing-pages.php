<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('landing');

$pages = get_landing_pages();
$allTags = get_tags();
$publishedArticles = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save'])) {
        $id = $_POST['id'] ?? '';
        $data = [
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'description' => $_POST['description'] ?? '',
            'seo_title' => $_POST['seo_title'] ?? '',
            'seo_desc' => $_POST['seo_desc'] ?? '',
            'status' => $_POST['status'] ?? 'draft',
            'aggregate_mode' => $_POST['aggregate_mode'] ?? 'tag',
            'aggregate_tags' => array_filter(explode(',', $_POST['aggregate_tags'] ?? '')),
            'aggregate_category' => trim($_POST['aggregate_category'] ?? ''),
            'aggregate_author' => trim($_POST['aggregate_author'] ?? ''),
            'max_articles' => max(1, (int)($_POST['max_articles'] ?? 20)),
            'layout' => $_POST['layout'] ?? 'grid',
            'show_description' => isset($_POST['show_description']),
            'sort_by' => $_POST['sort_by'] ?? 'newest',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($data['slug'])) $data['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $data['title']);

        if (empty($id)) {
            $data['id'] = 'landing_' . substr(bin2hex(random_bytes(6)), 0, 12);
            $data['created_at'] = date('Y-m-d H:i:s');
            $pages[] = $data;
        } else {
            foreach ($pages as &$p) { if ($p['id'] === $id) { $p = array_merge($p, $data); break; } }
        }
        save_landing_pages($pages);
        $message = '聚合页已保存';
    }
    if (isset($_POST['delete'])) {
        $pages = array_values(array_filter($pages, fn($p) => $p['id'] !== $_POST['delete']));
        save_landing_pages($pages);
        $message = '聚合页已删除';
        header('Location: /xmp/landing-pages');
        exit;
    }
    $pages = get_landing_pages();
}

$editPage = null;
if (isset($_GET['edit'])) {
    foreach ($pages as $p) { if ($p['id'] === $_GET['edit']) { $editPage = $p; break; } }
}

// Preview aggregated articles
$previewArticles = [];
$aggregateCounts = []; // 供列表统计
if ($editPage) {
    $previewArticles = aggregate_match($publishedArticles, $editPage);
}
foreach ($pages as $p) {
    $aggregateCounts[$p['id']] = count(aggregate_match($publishedArticles, $p));
}

function aggregate_match($articles, $p) {
    $mode = $p['aggregate_mode'] ?? 'tag';
    $out = [];
    foreach ($articles as $a) {
        $hit = false;
        if ($mode === 'all') $hit = true;
        elseif ($mode === 'tag') {
            foreach (($p['aggregate_tags'] ?? []) as $t) {
                if (in_array($t, $a['tags'] ?? [])) { $hit = true; break; }
            }
        } elseif ($mode === 'category') {
            $cat = trim(strtolower($p['aggregate_category'] ?? ''));
            if ($cat && strtolower($a['category'] ?? '') === $cat) $hit = true;
            elseif ($cat && in_array($cat, array_map('strtolower', $a['tags'] ?? []))) $hit = true;
        } elseif ($mode === 'author') {
            $author = trim(strtolower($p['aggregate_author'] ?? ''));
            if ($author && strtolower($a['author'] ?? '') === $author) $hit = true;
            elseif ($author && stripos($a['author_name'] ?? '', $author) !== false) $hit = true;
        }
        if ($hit) $out[] = $a;
    }
    $sortBy = $p['sort_by'] ?? 'newest';
    if ($sortBy === 'popular') {
        usort($out, fn($x, $y) => (($y['views'] ?? 0) <=> ($x['views'] ?? 0)));
    }
    return array_slice($out, 0, $p['max_articles'] ?? 20);
}

$typeLabels = ['lead' => '预约', 'download' => '下载', 'newsletter' => '订阅'];
$modeLabels = ['tag' => '🏷️ 按标签', 'category' => '📂 按分类', 'author' => '✍️ 按作者', 'all' => '📰 全站文章'];

admin_header('聚合页管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('landing'); ?>
  <div class="main">
    <h1>聚合页管理</h1>
    <p class="sub">按标签自动聚合文章的落地页 · 选好标签，文章自动归集</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>标题</th><th>URL</th><th>聚合方式</th><th>匹配文章</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pages)): ?><tr><td colspan="6" class="empty">暂无聚合页</td></tr><?php endif; ?>
          <?php foreach ($pages as $p):
            $matchCount = $aggregateCounts[$p['id']] ?? 0;
            $pmode = $p['aggregate_mode'] ?? 'tag';
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($p['title'])?></strong></td>
            <td><code>/<?=htmlspecialchars($p['slug'])?></code></td>
            <td>
              <?php if ($pmode === 'all'): ?><span class="badge badge-gray">📰 全站</span>
              <?php elseif ($pmode === 'category'): ?><span class="badge badge-gray">📂 <?=htmlspecialchars($p['aggregate_category'] ?? '')?></span>
              <?php elseif ($pmode === 'author'): ?><span class="badge badge-gray">✍️ <?=htmlspecialchars($p['aggregate_author'] ?? '')?></span>
              <?php else: ?>
                <?php foreach (($p['aggregate_tags'] ?? []) as $t): ?><span class="badge badge-gray"><?=htmlspecialchars($t)?></span> <?php endforeach; if (empty($p['aggregate_tags'])): ?><span class="text-sm text-muted">未设置</span><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><strong><?=$matchCount?></strong> 篇</td>
            <td><span class="badge <?=($p['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$p['status']??'draft'?></span></td>
            <td><a href="?edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="../content-preview.php?type=landing&id=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a>
              <form method="post" style="display:inline" data-confirm="确认删除?">
                <?= csrf_field() ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2><?=$editPage?'编辑聚合页':'新建聚合页'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?=htmlspecialchars($editPage['id']??'')?>">
        <div class="field-row">
          <div class="field"><label>页面标题</label><input type="text" name="title" value="<?=htmlspecialchars($editPage['title']??'')?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($editPage['slug']??'')?>" placeholder="auto-generated"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($editPage['seo_title']??'')?>"></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editPage['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editPage['status']??'')==='published'?'selected':''?>>已发布</option></select></div>
        </div>
        <div class="field"><label>描述</label><textarea name="description" rows="2"><?=htmlspecialchars($editPage['description']??'')?></textarea></div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" rows="2"><?=htmlspecialchars($editPage['seo_desc']??'')?></textarea></div>

        <!-- Aggregation -->
        <div class="card" style="margin:16px 0;padding:16px;background:var(--surface-2)">
          <h2>🔀 聚合方式</h2>
          <p class="text-sm text-muted mb-4">选择聚合依据，拥有对应属性的文章会自动出现在此聚合页</p>
          <div class="field">
            <label>聚合模式</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <?php foreach ($modeLabels as $mk => $ml): ?>
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;<?=($editPage['aggregate_mode']??'tag')===$mk?'background:var(--accent);border-color:var(--accent)':''?>">
                <input type="radio" name="aggregate_mode" value="<?=$mk?>" <?=($editPage['aggregate_mode']??'tag')===$mk?'checked':''?> style="width:16px;height:16px" onchange="this.closest('label').style.background='var(--accent)';document.querySelectorAll('label:has(input[name=aggregate_mode])').forEach(function(l){if(l!==this.closest('label'))l.style.background=''},this)"> <?=$ml?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div id="aggTagFields" style="<?=($editPage['aggregate_mode']??'tag')==='tag'?'':'display:none'?>">
            <div class="field" style="margin-top:12px"><label>聚合标签 <span class="hint">· 逗号分隔，或点击下方标签添加</span></label>
              <input type="text" name="aggregate_tags" id="aggTags" value="<?=htmlspecialchars(implode(',', $editPage['aggregate_tags'] ?? []))?>" placeholder="标签1, 标签2">
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
              <?php foreach ($allTags as $t): ?>
              <span class="tag-item" style="cursor:pointer;background:<?=in_array($t, $editPage['aggregate_tags']??[]) ? 'var(--accent)' : 'var(--surface)'?>"
                    onclick="toggleTag('<?=htmlspecialchars($t, ENT_QUOTES)?>', this)"><?=htmlspecialchars($t)?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <div id="aggCategoryFields" class="field-row" style="margin-top:12px;<?=($editPage['aggregate_mode']??'tag')==='category'?'':'display:none'?>">
            <div class="field"><label>分类 Key <span class="hint">· 如 growth / seo</span></label><input type="text" name="aggregate_category" value="<?=htmlspecialchars($editPage['aggregate_category']??'')?>" placeholder="growth"></div>
          </div>

          <div id="aggAuthorFields" class="field-row" style="margin-top:12px;<?=($editPage['aggregate_mode']??'tag')==='author'?'':'display:none'?>">
            <div class="field"><label>作者 <span class="hint">· 作者名或邮箱</span></label><input type="text" name="aggregate_author" value="<?=htmlspecialchars($editPage['aggregate_author']??'')?>" placeholder="张三"></div>
          </div>

          <div class="field-row" style="margin-top:12px">
            <div class="field"><label>最大显示文章数</label><input type="number" name="max_articles" value="<?=htmlspecialchars($editPage['max_articles'] ?? 20)?>" min="1" max="100"></div>
            <div class="field"><label>布局</label><select name="layout"><option value="grid" <?=($editPage['layout']??'grid')==='grid'?'selected':''?>>网格</option><option value="list" <?=($editPage['layout']??'')==='list'?'selected':''?>>列表</option></select></div>
          </div>
          <div class="field-row" style="margin-top:12px">
            <div class="field"><label>排序</label><select name="sort_by"><option value="newest" <?=($editPage['sort_by']??'newest')==='newest'?'selected':''?>>最新发布</option><option value="popular" <?=($editPage['sort_by']??'')==='popular'?'selected':''?>>最受欢迎（按浏览）</option></select></div>
            <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="show_description" value="1" <?=($editPage['show_description']??true)?'checked':''?> style="width:18px;height:18px">显示文章摘要</label></div>
          </div>
        </div>

        <!-- Preview matching articles -->
        <?php if ($editPage): ?>
        <div class="card" style="padding:16px;background:var(--surface)">
          <h2>📋 匹配预览 (<?=count($previewArticles)?> 篇)</h2>
          <?php if (empty($previewArticles)): ?>
          <p class="text-sm text-muted">所选标签暂无匹配的已发布文章</p>
          <?php else: ?>
          <div style="display:grid;gap:6px">
            <?php foreach ($previewArticles as $a): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--surface-2);border-radius:8px;font-size:13px">
              <span>📄</span>
              <span style="flex:1"><strong><?=htmlspecialchars($a['title'])?></strong></span>
              <span class="text-sm text-muted"><?=implode(', ', array_slice($a['tags']??[], 0, 3))?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-4">
          <button type="submit" class="btn btn-primary">保存聚合页</button>
          <a href="landing-pages.php" class="btn btn-ghost">取消</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleTag(tag, el) {
  var input = document.getElementById('aggTags');
  var tags = input.value.split(',').map(function(s){return s.trim()}).filter(Boolean);
  var idx = tags.indexOf(tag);
  if (idx >= 0) { tags.splice(idx, 1); el.style.background = ''; }
  else { tags.push(tag); el.style.background = '#38bdf8'; }
  input.value = tags.join(', ');
}
document.querySelectorAll('input[name=aggregate_mode]').forEach(function(r) {
  r.addEventListener('change', function() {
    var m = this.value;
    document.getElementById('aggTagFields').style.display = m === 'tag' ? '' : 'none';
    var cf = document.getElementById('aggCategoryFields');
    cf.style.display = m === 'category' ? '' : 'none';
    var af = document.getElementById('aggAuthorFields');
    af.style.display = m === 'author' ? '' : 'none';
  });
});
</script>
<?php admin_footer(); ?>
