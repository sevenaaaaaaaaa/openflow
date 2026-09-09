<?php
/**
 * 导航站管理 — 国内外优秀增长/SEO/AI 运营工具与资源网站
 *
 * v2（2026-09-10）：保存逻辑从「按表单字段重建记录」改为「按 id 合并旧记录」，
 * 修复保存即丢弃 hits / tags / logo / reason / created_at 等字段的 bug；
 * 表单补齐 description / name_en / sub / tags / reason / logo 编辑能力。
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$navFile = DATA_DIR . '/navigation.json';
$nav = json_read($navFile);
$message = '';

// 保存分类与站点（合并式：表单字段覆盖，其余字段保留）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $oldCats = [];
    foreach ($nav['categories'] ?? [] as $oc) $oldCats[$oc['id'] ?? ''] = $oc;
    $oldSites = [];
    foreach ($nav['sites'] ?? [] as $os) $oldSites[$os['id'] ?? ''] = $os;

    $categories = [];
    foreach (($_POST['cat_name'] ?? []) as $i => $cn) {
        if (empty(trim($cn))) continue;
        $cid = ($_POST['cat_id'][$i] ?? '') ?: 'cat_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $cat = $oldCats[$cid] ?? [];
        $cat['id'] = $cid;
        $cat['name'] = trim($cn);
        $cat['icon'] = $_POST['cat_icon'][$i] ?? '🌐';
        $cat['sort'] = (int)($_POST['cat_sort'][$i] ?? 0);
        $categories[] = $cat;
    }
    $sites = [];
    foreach (($_POST['site_name'] ?? []) as $i => $sn) {
        if (empty(trim($sn))) continue;
        $sid = ($_POST['site_id'][$i] ?? '') ?: 'site_' . substr(bin2hex(random_bytes(4)), 0, 6);
        // 以旧记录为底（保留 hits/created_at/weight/status/submitter 等），表单字段覆盖
        $site = $oldSites[$sid] ?? ['created_at' => date('Y-m-d H:i:s')];
        $site['id'] = $sid;
        $site['name'] = trim($sn);
        $site['name_en'] = trim($_POST['site_name_en'][$i] ?? '');
        $site['url'] = trim($_POST['site_url'][$i] ?? '');
        $site['description'] = trim($_POST['site_desc'][$i] ?? '');
        $site['reason'] = trim($_POST['site_reason'][$i] ?? '');
        $site['category'] = $_POST['site_cat'][$i] ?? '';
        $site['sub'] = trim($_POST['site_sub'][$i] ?? '');
        $site['tags'] = array_values(array_filter(array_map('trim', preg_split('/[,，]/', $_POST['site_tags'][$i] ?? ''))));
        $site['logo'] = trim($_POST['site_logo'][$i] ?? '');
        $site['featured'] = isset($_POST['site_featured'][$i]);
        $site['region'] = $_POST['site_region'][$i] ?? 'cn'; // cn / intl
        unset($site['desc'], $site['cat']); // 旧兼容字段统一并入 description/category
        $sites[] = $site;
    }
    $nav = [
        'categories' => $categories,
        'sites' => $sites,
        'hot_searches' => array_filter(array_map('trim', explode("\n", $_POST['hot_searches'] ?? ''))),
        'banner' => [
            'title' => trim($_POST['banner_title'] ?? ''),
            'subtitle' => trim($_POST['banner_subtitle'] ?? ''),
            'site_id' => trim($_POST['banner_site'] ?? ''), // Banner 推荐站点
        ],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    json_write($navFile, $nav);
    $message = '导航站已保存（未在表单中的字段已保留）';
}

// 同步 GitHub 仓库元数据（stars/语言/topics…，写入 data/nav-github.json 缓存）
if (isset($_GET['sync_github'])) {
    require_once __DIR__ . '/../lib/NavGithub.php';
    $r = NavGithub::syncAll();
    flash('success', "GitHub 数据同步完成：共 {$r['total']} 个仓库 · 更新 {$r['fetched']} · 缓存有效跳过 {$r['skipped']} · 失败 {$r['failed']}");
    header('Location: /xmp/navigation');
    exit;
}

// 删除
if (isset($_GET['delete_site'])) {
    $nav['sites'] = array_values(array_filter($nav['sites'] ?? [], fn($s) => $s['id'] !== $_GET['delete_site']));
    json_write($navFile, $nav);
    flash('success', '站点已删除');
    header('Location: /xmp/navigation');
    exit;
}
if (isset($_GET['delete_cat'])) {
    $nav['categories'] = array_values(array_filter($nav['categories'] ?? [], fn($c) => $c['id'] !== $_GET['delete_cat']));
    json_write($navFile, $nav);
    flash('success', '分类已删除');
    header('Location: /xmp/navigation');
    exit;
}

admin_header('导航站管理');
$inp = 'padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px;background:var(--surface);color:var(--text)';
?>
<div class="admin-layout">
  <?php admin_sidebar('navigation'); ?>
  <div class="main">
    <h1> 导航站</h1>
    <p class="sub">收录国内外优秀增长、SEO、AI 运营工具与资源网站 · 前台展示于 /navigation.php</p>
    <div style="margin:-8px 0 16px"><a class="btn btn-ghost btn-sm" href="/xmp/navigation?sync_github=1" onclick="this.textContent='同步中…'">🐙 同步 GitHub 数据</a></div>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.08))">
      <h2 style="font-size:15px">🔗 快速添加精选站点</h2>
      <p class="text-sm text-muted mb-4">点击按钮预填知名增长/SEO/AI 工具网站</p>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="presetSite('Google Search Console','https://search.google.com/search-console','Google 官方搜索与收录工具','intl')">+ Search Console</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="presetSite('Ahrefs','https://ahrefs.com','全球领先的 SEO 与反向链接分析','intl')">+ Ahrefs</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="presetSite('Semrush','https://www.semrush.com','关键词研究与竞品分析平台','intl')">+ Semrush</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="presetSite('百度搜索资源平台','https://ziyuan.baidu.com','百度官方站长工具','cn')">+ 百度资源平台</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="presetSite('5118','https://www.5118.com','中文关键词与内容数据分析','cn')">+ 5118</button>
      </div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🏠 导航首页设置</h2>
        <p class="text-sm text-muted mb-4">热搜词 + Banner 推荐 · 首屏展示</p>
        <div class="field-row">
          <div class="field"><label>热搜词 <span class="hint">· 每行一个</span></label><textarea name="hot_searches" rows="4" placeholder="GEO 优化&#10;SEO 工具&#10;AI 运营"><?=htmlspecialchars(implode("\n", $nav['hot_searches'] ?? []))?></textarea></div>
          <div class="field">
            <label>Banner 标题</label><input type="text" name="banner_title" value="<?=htmlspecialchars($nav['banner']['title'] ?? '')?>" placeholder="发现优秀增长资源">
            <label>Banner 副标题</label><input type="text" name="banner_subtitle" value="<?=htmlspecialchars($nav['banner']['subtitle'] ?? '')?>" placeholder="精选国内外增长/SEO/AI 运营工具">
            <label>Banner 推荐站点</label>
            <select name="banner_site">
              <option value="">— 不设置 —</option>
              <?php foreach ($nav['sites'] ?? [] as $s): ?>
              <option value="<?=htmlspecialchars($s['id'])?>" <?=($nav['banner']['site_id'] ?? '')===$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>🗂️ 分类管理</h2>
        <div id="catList">
          <?php foreach ($nav['categories'] ?? [] as $ci => $c): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <input type="hidden" name="cat_id[]" value="<?=htmlspecialchars($c['id'])?>">
            <input type="text" name="cat_icon[]" value="<?=htmlspecialchars($c['icon'] ?? '🌐')?>" style="width:50px;<?=$inp?>;text-align:center">
            <input type="text" name="cat_name[]" value="<?=htmlspecialchars($c['name'])?>" placeholder="分类名称" style="flex:1;<?=$inp?>">
            <input type="number" name="cat_sort[]" value="<?=htmlspecialchars($c['sort'] ?? 0)?>" style="width:70px;<?=$inp?>" title="排序">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addCat()">+ 添加分类</button>
      </div>

      <div class="card">
        <h2>🌐 站点列表</h2>
        <p class="text-sm text-muted mb-4">两行一组：第一行是基础信息，第二行是详情页内容（简介 / 推荐理由 / 标签 / Logo）。保存不会丢失未展示的字段（点击数、收录时间等）。</p>
        <div id="siteList">
          <?php foreach ($nav['sites'] ?? [] as $si => $s): ?>
          <div class="site-row" style="margin-bottom:10px;padding:10px;background:var(--surface-2);border-radius:10px">
            <input type="hidden" name="site_id[]" value="<?=htmlspecialchars($s['id'])?>">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <input type="text" name="site_name[]" value="<?=htmlspecialchars($s['name'])?>" placeholder="名称 *" title="名称" style="width:120px;<?=$inp?>">
              <input type="text" name="site_name_en[]" value="<?=htmlspecialchars($s['name_en'] ?? '')?>" placeholder="英文名" title="英文名" style="width:110px;<?=$inp?>">
              <input type="text" name="site_url[]" value="<?=htmlspecialchars($s['url'])?>" placeholder="https://..." title="网址（GitHub 仓库会自动同步 stars）" style="flex:1;min-width:180px;<?=$inp?>">
              <select name="site_cat[]" title="分类" style="width:110px;<?=$inp?>">
                <option value="">— 分类 —</option>
                <?php foreach ($nav['categories'] ?? [] as $c): ?>
                <option value="<?=htmlspecialchars($c['id'])?>" <?=($s['category'] ?? $s['cat'] ?? '')===$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="site_sub[]" value="<?=htmlspecialchars($s['sub'] ?? '')?>" placeholder="子分类" title="子分类（如：关键词研究 / 建站工具）" style="width:100px;<?=$inp?>">
              <select name="site_region[]" title="地区" style="width:74px;<?=$inp?>"><option value="cn" <?=($s['region']??'')==='cn'?'selected':''?>>国内</option><option value="intl" <?=($s['region']??'')==='intl'?'selected':''?>>海外</option></select>
              <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="site_featured[]" value="1" <?=!empty($s['featured'])?'checked':''?> style="width:15px;height:15px">推荐</label>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.site-row').remove()">✕</button>
            </div>
            <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
              <input type="text" name="site_desc[]" value="<?=htmlspecialchars($s['description'] ?? $s['desc'] ?? '')?>" placeholder="一句话简介（列表页 + 详情页展示）" title="一句话简介" style="flex:2;min-width:200px;<?=$inp?>">
              <input type="text" name="site_reason[]" value="<?=htmlspecialchars($s['reason'] ?? '')?>" placeholder="推荐理由（编辑点评，详情页展示）" title="推荐理由" style="flex:2;min-width:180px;<?=$inp?>">
              <input type="text" name="site_tags[]" value="<?=htmlspecialchars(implode(',', $s['tags'] ?? []))?>" placeholder="标签,逗号分隔" title="标签（用于相似推荐与对比）" style="flex:1;min-width:120px;<?=$inp?>">
              <input type="text" name="site_logo[]" value="<?=htmlspecialchars($s['logo'] ?? '')?>" placeholder="Logo URL（留空用 favicon）" title="Logo URL" style="flex:1;min-width:140px;<?=$inp?>">
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addSite()">+ 添加站点</button>
        <div style="margin-top:12px"><button type="submit" name="save" class="btn btn-primary">保存导航站</button></div>
      </div>
    </form>
  </div>
</div>

<script>
var CATS = <?=json_encode(array_map(fn($c) => ['id'=>$c['id'],'name'=>$c['name']], $nav['categories'] ?? []), JSON_UNESCAPED_UNICODE)?>;
var INP = 'padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px;background:var(--surface);color:var(--text)';
function catOpts() {
  return CATS.map(function(c){ return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');
}
function addCat() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  d.innerHTML = '<input type="hidden" name="cat_id[]" value="cat_' + Date.now() + '"><input type="text" name="cat_icon[]" value="🌐" style="width:50px;' + INP + ';text-align:center"><input type="text" name="cat_name[]" placeholder="分类名称" style="flex:1;' + INP + '"><input type="number" name="cat_sort[]" value="0" style="width:70px;' + INP + '"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('catList').appendChild(d);
}
function siteRowHTML(id) {
  return '<input type="hidden" name="site_id[]" value="' + id + '">'
    + '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
    + '<input type="text" name="site_name[]" placeholder="名称 *" title="名称" style="width:120px;' + INP + '">'
    + '<input type="text" name="site_name_en[]" placeholder="英文名" title="英文名" style="width:110px;' + INP + '">'
    + '<input type="text" name="site_url[]" placeholder="https://..." title="网址" style="flex:1;min-width:180px;' + INP + '">'
    + '<select name="site_cat[]" title="分类" style="width:110px;' + INP + '"><option value="">— 分类 —</option>' + catOpts() + '</select>'
    + '<input type="text" name="site_sub[]" placeholder="子分类" title="子分类" style="width:100px;' + INP + '">'
    + '<select name="site_region[]" title="地区" style="width:74px;' + INP + '"><option value="cn">国内</option><option value="intl">海外</option></select>'
    + '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="site_featured[]" value="1" style="width:15px;height:15px">推荐</label>'
    + '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.site-row\').remove()">✕</button>'
    + '</div>'
    + '<div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">'
    + '<input type="text" name="site_desc[]" placeholder="一句话简介" title="一句话简介" style="flex:2;min-width:200px;' + INP + '">'
    + '<input type="text" name="site_reason[]" placeholder="推荐理由" title="推荐理由" style="flex:2;min-width:180px;' + INP + '">'
    + '<input type="text" name="site_tags[]" placeholder="标签,逗号分隔" title="标签" style="flex:1;min-width:120px;' + INP + '">'
    + '<input type="text" name="site_logo[]" placeholder="Logo URL（留空用 favicon）" title="Logo URL" style="flex:1;min-width:140px;' + INP + '">'
    + '</div>';
}
function addSite() {
  var d = document.createElement('div');
  d.className = 'site-row';
  d.style.cssText = 'margin-bottom:10px;padding:10px;background:var(--surface-2);border-radius:10px';
  d.innerHTML = siteRowHTML('site_' + Date.now());
  document.getElementById('siteList').appendChild(d);
  return d;
}
function presetSite(name, url, desc, region) {
  var row = addSite();
  row.querySelector('input[name="site_name[]"]').value = name;
  row.querySelector('input[name="site_url[]"]').value = url;
  row.querySelector('input[name="site_desc[]"]').value = desc;
  row.querySelector('select[name="site_region[]"]').value = region;
}
</script>
<?php admin_footer(); ?>
