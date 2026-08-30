<?php
/**
 * 统一页面列表 — 所有页面（基础页 / 模块化页 / 落地页）一个入口管理
 * 基础页：静态模板（首页/产品等），提供 SEO 与状态管理
 * 模块化页：builder-pages.json，点击进入模块化编辑器
 * 落地页：landing-pages.json，点击进入聚合落地页编辑
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$sitePages = json_read(DATA_DIR . '/site-pages.json');
$builderPages = json_read(DATA_DIR . '/builder-pages.json');
$landingPages = json_read(DATA_DIR . '/landing-pages.json');
$seo = json_read(DATA_DIR . '/seo.json');

// 组装统一列表
$all = [];
foreach ((array)$sitePages as $p) {
    $all[] = [
        'id' => 'base:' . ($p['slug'] ?? ''),
        'title' => $p['title'] ?? '',
        'slug' => $p['slug'] ?? '',
        'type' => '基础页',
        'template' => $p['template'] ?? '',
        'status' => $p['status'] ?? 'published',
        'desc' => $p['desc'] ?? '',
        'edit' => 'page-builder.php?edit=base:' . urlencode($p['slug'] ?? ''),
        'preview' => $p['slug'] ?? '',
    ];
}
foreach ((array)$builderPages as $p) {
    $all[] = [
        'id' => 'builder:' . ($p['id'] ?? ''),
        'title' => $p['title'] ?? '',
        'slug' => '/b/' . ($p['slug'] ?? ($p['id'] ?? '')),
        'type' => '模块化页',
        'template' => 'blocks',
        'status' => $p['status'] ?? 'draft',
        'desc' => '区块数：' . count((array)($p['blocks'] ?? [])),
        'edit' => 'page-builder.php?edit=' . urlencode($p['id'] ?? ''),
        'preview' => '',
    ];
}
foreach ((array)$landingPages as $p) {
    $all[] = [
        'id' => 'landing:' . ($p['id'] ?? ''),
        'title' => $p['title'] ?? '',
        'slug' => '/lp/' . ($p['slug'] ?? ''),
        'type' => '落地页',
        'template' => 'landing',
        'status' => $p['status'] ?? 'published',
        'desc' => '聚合：' . (is_array($p['aggregate_tags'] ?? null) ? implode(', ', $p['aggregate_tags']) : ($p['aggregate_tags'] ?? '')) . ' · 布局 ' . ($p['layout'] ?? ''),
        'edit' => 'landing-pages.php?edit=' . urlencode($p['id'] ?? ''),
        'preview' => '/lp/' . ($p['slug'] ?? ''),
    ];
}

// SEO 快速更新（基础页）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_save'])) {
    csrf_verify();
    $key = $_POST['page_key'] ?? '';
    $seo[$key] = [
        'title' => trim($_POST['seo_title'] ?? ''),
        'desc' => trim($_POST['seo_desc'] ?? ''),
        'keywords' => trim($_POST['seo_keywords'] ?? ''),
    ];
    json_write(DATA_DIR . '/seo.json', $seo);
    flash('success', 'SEO 已更新');
    header('Location: /xmp/content-hub?tab=pages');
    exit;
}

// 搜索/筛选
$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? '';
if ($q) $all = array_values(array_filter($all, fn($p) => strpos($p['title'] . $p['slug'], $q) !== false));
if ($type) $all = array_values(array_filter($all, fn($p) => $p['type'] === $type));
$typeCount = [];
foreach ($all as $p) $typeCount[$p['type']] = ($typeCount[$p['type']] ?? 0) + 1;

if (!defined('OF_EMBED')) admin_header('页面列表');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('pages-list'); ?>
  <div class="main">
<?php endif; ?>
<?php
// B3：浅 CRUD 页归并为本页的子 tab
require_once __DIR__ . '/_subtabs.php';
$SUBTABS = ['self' => ['页面列表', '', 'pages'],
            'cats' => ['分类', 'page-categories.php', 'pages'],
            'tags' => ['标签', 'tags.php', 'pages']];
$__sub = of_subtab_begin($SUBTABS);
if ($__sub === 'self'):
?>
    <div class="v-head">
      <div><h1>页面列表</h1><p class="v-sub">统一管理所有页面：基础页（SEO/状态）· 模块化页（区块编辑）· 落地页（聚合编辑）</p></div>
      <div class="v-actions">
        <a href="page-builder.php" class="btn btn-p btn-sm">+ 新建模块化页</a>
        <a href="landing-pages.php" class="btn btn-s btn-sm">新建落地页</a>
      </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <form method="get" style="flex:1;display:flex;gap:8px;max-width:480px">
        <input type="search" name="q" value="<?=htmlspecialchars($q)?>" placeholder="搜索页面标题 / URL…" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px">
        <button class="btn btn-s btn-sm">搜索</button>
      </form>
      <a href="<?=of_hub_url()?>" class="btn btn-s btn-sm <?=$type===''?'on':''?>" style="<?=$type===''?'border-color:var(--accent);color:var(--accent)':''?>">全部 (<?=count($all)?>)</a>
      <?php foreach ($typeCount as $t => $n): ?>
      <a href="<?=of_hub_url(['type'=>$t])?>" class="btn btn-s btn-sm <?=$type===$t?'on':''?>" style="<?=$type===$t?'border-color:var(--accent);color:var(--accent)':''?>"><?=htmlspecialchars($t)?> (<?=$n?>)</a>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>页面</th><th>类型</th><th>模板</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($all)): ?><tr><td colspan="5" class="empty">没有匹配的页面</td></tr><?php endif; ?>
          <?php foreach ($all as $p): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:18px"><?=str_starts_with($p['type'],'基础')?'🏠':(str_starts_with($p['type'],'模块')?'🧱':'🎯')?></span>
                <div>
                  <strong><?=htmlspecialchars($p['title'])?></strong>
                  <div style="font-size:11px;color:var(--faint);font-family:var(--font-mono)"><?=htmlspecialchars($p['slug'])?></div>
                  <?php if ($p['desc']): ?><div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($p['desc'])?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="badge" style="background:var(--surface-2);padding:2px 8px;border-radius:999px;font-size:11px"><?=htmlspecialchars($p['type'])?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['template'])?></td>
            <td><span style="color:<?=($p['status']??'')==='published'?'var(--ok)':'var(--warn)'?>"><?=($p['status']??'')==='published'?'已发布':'草稿'?></span></td>
            <td style="white-space:nowrap">
              <a href="<?=htmlspecialchars($p['edit'])?>" class="btn btn-p btn-sm">编辑</a>
              <?php if ($p['preview']): ?><a href="<?=htmlspecialchars($p['preview'])?>" target="_blank" class="btn btn-ghost btn-sm">预览</a><?php endif; ?>
              <?php if (str_starts_with($p['id'],'base:')): ?>
              <button class="btn btn-ghost btn-sm" onclick="document.getElementById('seo-<?=md5($p['id'])?>').classList.toggle('open');document.getElementById('seo-<?=md5($p['id'])?>').style.display=document.getElementById('seo-<?=md5($p['id'])?>').style.display==='none'?'':'none'">SEO</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php if (str_starts_with($p['id'],'base:')): $sk = $p['slug']; $sm = $seo[$sk] ?? []; ?>
          <tr id="seo-<?=md5($p['id'])?>" style="display:none">
            <td colspan="5" style="padding:14px 20px">
              <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="seo_save" value="1">
                <input type="hidden" name="page_key" value="<?=htmlspecialchars($sk)?>">
                <input type="text" name="seo_title" value="<?=htmlspecialchars($sm['title']??'')?>" placeholder="SEO 标题" style="flex:1;min-width:180px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px">
                <input type="text" name="seo_desc" value="<?=htmlspecialchars($sm['desc']??'')?>" placeholder="SEO 描述" style="flex:1;min-width:200px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px">
                <input type="text" name="seo_keywords" value="<?=htmlspecialchars($sm['keywords']??'')?>" placeholder="关键词" style="width:140px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px">
                <button class="btn btn-s btn-sm">保存 SEO</button>
              </form>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php else: of_subtab_include($SUBTABS, $__sub); endif; ?>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
