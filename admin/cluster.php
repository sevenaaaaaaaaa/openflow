<?php
/**
 * Cluster 聚合管理 — 聚合页的强化管理界面
 * 支持三种聚合方式：
 * 1. 手动聚合：手动挑选文章进聚合
 * 2. 规则聚合：在模块内配置聚合规则（标签/分类/作者/关键词/时间范围组合）
 * 3. 自动聚合：按规则自动归集，规则变更即重新匹配
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('landing');

$pages = get_landing_pages();
$publishedArticles = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));
$allTags = get_tags();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save'])) {
        $id = $_POST['id'] ?? '';
        $data = [
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'description' => $_POST['description'] ?? '',
            'status' => $_POST['status'] ?? 'draft',
            // 聚合方式：manual（手动）/ rule（规则）/ auto（自动按规则）
            'aggregate_mode' => $_POST['aggregate_mode'] ?? 'rule',
            // 手动聚合：选中的文章 id 列表
            'manual_articles' => array_filter(explode(',', $_POST['manual_articles'] ?? '')),
            // 规则聚合：条件组合（AND/OR）
            'rule_logic' => $_POST['rule_logic'] ?? 'or',
            'rule_tags' => array_filter(explode(',', $_POST['rule_tags'] ?? '')),
            'rule_category' => trim($_POST['rule_category'] ?? ''),
            'rule_author' => trim($_POST['rule_author'] ?? ''),
            'rule_keyword' => trim($_POST['rule_keyword'] ?? ''),
            'rule_date_from' => trim($_POST['rule_date_from'] ?? ''),
            'rule_date_to' => trim($_POST['rule_date_to'] ?? ''),
            'max_articles' => max(1, (int)($_POST['max_articles'] ?? 20)),
            'sort_by' => $_POST['sort_by'] ?? 'newest',
            'show_description' => isset($_POST['show_description']),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($data['slug'])) $data['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $data['title']);
        if (empty($id)) {
            $data['id'] = 'cluster_' . substr(bin2hex(random_bytes(6)), 0, 12);
            $data['created_at'] = date('Y-m-d H:i:s');
            $pages[] = $data;
        } else {
            foreach ($pages as &$p) { if ($p['id'] === $id) { $p = array_merge($p, $data); break; } }
        }
        save_landing_pages($pages);
        $message = '聚合已保存';
    }
    if (isset($_POST['delete'])) {
        $pages = array_values(array_filter($pages, fn($p) => $p['id'] !== ($_POST['delete'] ?? '')));
        save_landing_pages($pages);
        header('Location: cluster.php');
        exit;
    }
    $pages = get_landing_pages();
}

$editPage = null;
if (isset($_GET['edit'])) {
    foreach ($pages as $p) { if ($p['id'] === $_GET['edit']) { $editPage = $p; break; } }
}

// 匹配逻辑
function cluster_match(array $articles, array $p): array {
    $mode = $p['aggregate_mode'] ?? 'rule';
    if ($mode === 'manual') {
        $ids = array_flip($p['manual_articles'] ?? []);
        return array_values(array_filter($articles, fn($a) => isset($ids[$a['id']])));
    }
    // rule / auto 共用规则匹配
    $logic = $p['rule_logic'] ?? 'or';
    $out = [];
    foreach ($articles as $a) {
        $hits = [];
        if (!empty($p['rule_tags'])) {
            $hits['tag'] = count(array_intersect($p['rule_tags'], $a['tags'] ?? [])) > 0;
        }
        if (!empty($p['rule_category'])) {
            $hits['cat'] = strtolower($a['category'] ?? '') === strtolower($p['rule_category'])
                || in_array(strtolower($p['rule_category']), array_map('strtolower', $a['tags'] ?? []));
        }
        if (!empty($p['rule_author'])) {
            $hits['author'] = strtolower($a['author'] ?? '') === strtolower($p['rule_author'])
                || stripos($a['author_name'] ?? '', $p['rule_author']) !== false;
        }
        if (!empty($p['rule_keyword'])) {
            $hits['kw'] = mb_strpos($a['title'] ?? '', $p['rule_keyword']) !== false
                || mb_strpos($a['excerpt'] ?? $a['summary'] ?? '', $p['rule_keyword']) !== false;
        }
        if (!empty($p['rule_date_from'])) {
            $hits['from'] = ($a['created_at'] ?? '') >= $p['rule_date_from'] . ' 00:00:00';
        }
        if (!empty($p['rule_date_to'])) {
            $hits['to'] = ($a['created_at'] ?? '') <= $p['rule_date_to'] . ' 23:59:59';
        }
        $matched = $logic === 'and' ? (count($hits) > 0 && !in_array(false, $hits, true)) : (count($hits) > 0 && in_array(true, $hits, true));
        if ($matched) $out[] = $a;
    }
    $sortBy = $p['sort_by'] ?? 'newest';
    if ($sortBy === 'popular') usort($out, fn($x, $y) => (($y['views'] ?? 0) <=> ($x['views'] ?? 0)));
    return array_slice($out, 0, $p['max_articles'] ?? 20);
}

$previewArticles = [];
$clusterCounts = [];
if ($editPage) $previewArticles = cluster_match($publishedArticles, $editPage);
foreach ($pages as $p) $clusterCounts[$p['id']] = count(cluster_match($publishedArticles, $p));

$modeLabels = ['manual' => '🖐 手动聚合', 'rule' => '📐 规则聚合', 'auto' => '⚡ 自动聚合'];

admin_header('Cluster 聚合管理');
?>
<style>
.cluster-mode-card{display:flex;gap:12px;flex-wrap:wrap}
.cluster-mode-card label{display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;transition:.12s}
.cluster-mode-card label.on{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
.article-pick{max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;padding:8px}
.article-pick-item{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;cursor:pointer;font-size:13px}
.article-pick-item:hover{background:var(--surface-2)}
.article-pick-item.sel{background:var(--accent-soft)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('cluster'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">🚀 Cluster 聚合管理</h1>
      <div class="flex gap-2 ml-auto">
        <a href="landing-pages.php" class="btn btn-ghost btn-sm">简化聚合页</a>
      </div>
    </div>
    <p class="sub">三种聚合方式：手动挑选 / 模块内规则配置 / 按规则自动归集 · 规则变更即自动重新匹配</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
      <div class="stat-card"><div class="num"><?=count($pages)?></div><div class="label">聚合总数</div></div>
      <div class="stat-card"><div class="num" style="color:var(--ok)"><?=count(array_filter($pages, fn($p)=>($p['aggregate_mode']??'')==='auto'))?></div><div class="label">自动聚合</div></div>
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=count($publishedArticles)?></div><div class="label">可聚合文章</div></div>
    </div>

    <!-- 聚合列表 -->
    <div class="card" style="padding:0;overflow:auto;margin-bottom:24px">
      <table>
        <thead><tr><th>标题</th><th>URL</th><th>聚合方式</th><th>匹配文章</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pages)): ?><tr><td colspan="6" class="empty">暂无聚合</td></tr><?php endif; ?>
          <?php foreach ($pages as $p):
            $mode = $p['aggregate_mode'] ?? 'rule';
            $ml = $modeLabels[$mode] ?? $mode;
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($p['title'])?></strong></td>
            <td><code>/<?=htmlspecialchars($p['slug'])?></code></td>
            <td>
              <span class="badge <?=$mode==='auto'?'badge-green':($mode==='manual'?'badge-yellow':'badge-gray')?>"><?=$ml?></span>
              <?php if ($mode === 'rule' || $mode === 'auto'): ?>
                <?php foreach (($p['rule_tags'] ?? []) as $t): ?><span class="badge badge-gray"><?=htmlspecialchars($t)?></span> <?php endforeach; ?>
              <?php elseif ($mode === 'manual'): ?>
                <span class="text-sm text-muted">已选 <?=count($p['manual_articles'] ?? [])?> 篇</span>
              <?php endif; ?>
            </td>
            <td><strong><?=$clusterCounts[$p['id']] ?? 0?></strong> 篇</td>
            <td><span class="badge <?=($p['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$p['status']??'draft'?></span></td>
            <td>
              <a href="?edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="../content-preview.php?type=landing&id=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a>
              <form method="post" style="display:inline" onsubmit="return confirm('确认删除?')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete" value="<?=htmlspecialchars($p['id'])?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 新建/编辑表单 -->
    <div class="card">
      <h2><?=$editPage?'编辑聚合':'新建聚合'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?=htmlspecialchars($editPage['id']??'')?>">
        <div class="field-row">
          <div class="field"><label>聚合标题</label><input type="text" name="title" value="<?=htmlspecialchars($editPage['title']??'')?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($editPage['slug']??'')?>" placeholder="auto"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editPage['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editPage['status']??'')==='published'?'selected':''?>>已发布</option></select></div>
          <div class="field"><label>最大文章数</label><input type="number" name="max_articles" value="<?=htmlspecialchars($editPage['max_articles']??20)?>" min="1" max="100"></div>
        </div>
        <div class="field"><label>描述</label><textarea name="description" rows="2"><?=htmlspecialchars($editPage['description']??'')?></textarea></div>

        <!-- 聚合方式 -->
        <div class="card" style="margin:16px 0;padding:16px;background:var(--surface-2)">
          <h2>🔀 聚合方式</h2>
          <div class="cluster-mode-card">
            <?php foreach ($modeLabels as $mk => $ml): ?>
            <label class="<?=($editPage['aggregate_mode']??'rule')===$mk?'on':''?>">
              <input type="radio" name="aggregate_mode" value="<?=$mk?>" <?=($editPage['aggregate_mode']??'rule')===$mk?'checked':''?> onchange="switchMode('<?=$mk?>')"> <?=$ml?>
            </label>
            <?php endforeach; ?>
          </div>

          <!-- 手动聚合 -->
          <div id="mode-manual" style="margin-top:16px;<?=($editPage['aggregate_mode']??'rule')==='manual'?'':'display:none'?>">
            <label>🖐 手动挑选文章</label>
            <input type="hidden" name="manual_articles" id="manualArticles" value="<?=htmlspecialchars(implode(',', $editPage['manual_articles'] ?? []))?>">
            <div class="article-pick" style="margin-top:8px">
              <?php foreach ($publishedArticles as $a):
                $sel = in_array($a['id'], $editPage['manual_articles'] ?? []);
              ?>
              <div class="article-pick-item <?=$sel?'sel':''?>" data-id="<?=htmlspecialchars($a['id'])?>" onclick="togglePick(this)">
                <input type="checkbox" <?=$sel?'checked':''?> style="pointer-events:none">
                <span style="flex:1"><?=htmlspecialchars($a['title'])?></span>
                <span class="text-sm text-muted"><?=htmlspecialchars(implode(',', array_slice($a['tags']??[],0,3)))?></span>
              </div>
              <?php endforeach; ?>
              <?php if (empty($publishedArticles)): ?><div class="text-sm text-muted" style="padding:20px;text-align:center">暂无已发布文章</div><?php endif; ?>
            </div>
          </div>

          <!-- 规则聚合 / 自动聚合 -->
          <div id="mode-rule" style="margin-top:16px;<?=($editPage['aggregate_mode']??'rule')==='manual'?'display:none':''?>">
            <div class="field-row">
              <div class="field">
                <label>匹配逻辑</label>
                <select name="rule_logic">
                  <option value="or" <?=($editPage['rule_logic']??'or')==='or'?'selected':''?>>任一条件命中 (OR)</option>
                  <option value="and" <?=($editPage['rule_logic']??'')==='and'?'selected':''?>>全部条件满足 (AND)</option>
                </select>
              </div>
              <div class="field"><label>聚合标签 <span class="hint">· 逗号分隔</span></label><input type="text" name="rule_tags" value="<?=htmlspecialchars(implode(',', $editPage['rule_tags'] ?? []))?>" placeholder="标签1, 标签2"></div>
            </div>
            <div class="field-row">
              <div class="field"><label>分类</label><input type="text" name="rule_category" value="<?=htmlspecialchars($editPage['rule_category']??'')?>" placeholder="growth"></div>
              <div class="field"><label>作者</label><input type="text" name="rule_author" value="<?=htmlspecialchars($editPage['rule_author']??'')?>" placeholder="作者名"></div>
            </div>
            <div class="field-row">
              <div class="field"><label>标题/摘要关键词</label><input type="text" name="rule_keyword" value="<?=htmlspecialchars($editPage['rule_keyword']??'')?>" placeholder="如：AI"></div>
              <div class="field"><label>排序</label><select name="sort_by"><option value="newest" <?=($editPage['sort_by']??'newest')==='newest'?'selected':''?>>最新</option><option value="popular" <?=($editPage['sort_by']??'')==='popular'?'selected':''?>>最热</option><option value="personalized" <?=($editPage['sort_by']??'')==='personalized'?'selected':''?>>🎯 个性化（按访客画像）</option></select></div>
            </div>
            <div class="field-row">
              <div class="field"><label>发布时间从</label><input type="date" name="rule_date_from" value="<?=htmlspecialchars($editPage['rule_date_from']??'')?>"></div>
              <div class="field"><label>发布时间到</label><input type="date" name="rule_date_to" value="<?=htmlspecialchars($editPage['rule_date_to']??'')?>"></div>
            </div>
            <?php if ($editPage): ?>
            <div class="field" style="margin-top:8px"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="show_description" value="1" <?=($editPage['show_description']??true)?'checked':''?> style="width:18px;height:18px">显示文章摘要</label></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($editPage): ?>
        <div class="card" style="padding:16px;background:var(--surface)">
          <h2>📋 匹配预览 (<?=count($previewArticles)?> 篇)</h2>
          <?php if (empty($previewArticles)): ?>
          <p class="text-sm text-muted">当前规则暂无匹配文章</p>
          <?php else: ?>
          <div style="display:grid;gap:6px">
            <?php foreach ($previewArticles as $a): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--surface-2);border-radius:8px;font-size:13px">
              <span>📄</span><span style="flex:1"><strong><?=htmlspecialchars($a['title'])?></strong></span>
              <span class="text-sm text-muted"><?=htmlspecialchars(implode(', ', array_slice($a['tags']??[],0,3)))?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-4" style="margin-top:16px">
          <button type="submit" class="btn btn-primary">保存聚合</button>
          <a href="cluster.php" class="btn btn-ghost">取消</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function switchMode(m) {
  document.querySelectorAll('.cluster-mode-card label').forEach(function(l) { l.classList.remove('on'); });
  var radios = document.querySelectorAll('.cluster-mode-card input[name=aggregate_mode]');
  radios.forEach(function(r) { if (r.value === m) r.closest('label').classList.add('on'); });
  document.getElementById('mode-manual').style.display = m === 'manual' ? '' : 'none';
  document.getElementById('mode-rule').style.display = m === 'manual' ? 'none' : '';
}
function togglePick(el) {
  el.classList.toggle('sel');
  var cb = el.querySelector('input[type=checkbox]');
  cb.checked = !cb.checked;
  var ids = [];
  document.querySelectorAll('.article-pick-item.sel').forEach(function(i) { ids.push(i.dataset.id); });
  document.getElementById('manualArticles').value = ids.join(',');
}
</script>
<?php admin_footer(); ?>
