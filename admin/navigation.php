<?php
/**
 * 导航站管理 — 国内外优秀增长/SEO/AI 运营工具与资源网站
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$navFile = DATA_DIR . '/navigation.json';
$nav = json_read($navFile);
$message = '';

// 保存分类与站点
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $categories = [];
    foreach (($_POST['cat_name'] ?? []) as $i => $cn) {
        if (empty(trim($cn))) continue;
        $categories[] = [
            'id' => ($_POST['cat_id'][$i] ?? '') ?: 'cat_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'name' => trim($cn),
            'icon' => $_POST['cat_icon'][$i] ?? '🌐',
            'sort' => (int)($_POST['cat_sort'][$i] ?? 0),
        ];
    }
    $sites = [];
    foreach (($_POST['site_name'] ?? []) as $i => $sn) {
        if (empty(trim($sn))) continue;
        $sites[] = [
            'id' => ($_POST['site_id'][$i] ?? '') ?: 'site_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'name' => trim($sn),
            'url' => trim($_POST['site_url'][$i] ?? ''),
            'description' => trim($_POST['site_desc'][$i] ?? ''),
            'category' => $_POST['site_cat'][$i] ?? '',
            'featured' => isset($_POST['site_featured'][$i]),
            'region' => $_POST['site_region'][$i] ?? 'cn', // cn / intl
        ];
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
    $message = '导航站已保存';
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
?>
<div class="admin-layout">
  <?php admin_sidebar('navigation'); ?>
  <div class="main">
    <h1> 导航站</h1>
    <p class="sub">收录国内外优秀增长、SEO、AI 运营工具与资源网站 · 前台展示于 /navigation.php</p>
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
            <input type="text" name="cat_icon[]" value="<?=htmlspecialchars($c['icon'] ?? '🌐')?>" style="width:50px;padding:7px;border:1.5px solid var(--border);border-radius:8px;text-align:center">
            <input type="text" name="cat_name[]" value="<?=htmlspecialchars($c['name'])?>" placeholder="分类名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="number" name="cat_sort[]" value="<?=htmlspecialchars($c['sort'] ?? 0)?>" style="width:70px;padding:7px;border:1.5px solid var(--border);border-radius:8px" title="排序">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addCat()">+ 添加分类</button>
      </div>

      <div class="card">
        <h2>🌐 站点列表</h2>
        <div id="siteList">
          <?php foreach ($nav['sites'] ?? [] as $si => $s): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;padding:8px;background:var(--surface-2);border-radius:10px">
            <input type="hidden" name="site_id[]" value="<?=htmlspecialchars($s['id'])?>">
            <input type="text" name="site_name[]" value="<?=htmlspecialchars($s['name'])?>" placeholder="名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="text" name="site_url[]" value="<?=htmlspecialchars($s['url'])?>" placeholder="https://..." style="flex:1;min-width:180px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <select name="site_cat[]" style="width:120px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <option value="">— 分类 —</option>
              <?php foreach ($nav['categories'] ?? [] as $c): ?>
              <option value="<?=htmlspecialchars($c['id'])?>" <?=($s['category']??'')===$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
            <select name="site_region[]" style="width:80px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="cn" <?=($s['region']??'')==='cn'?'selected':''?>>国内</option><option value="intl" <?=($s['region']??'')==='intl'?'selected':''?>>海外</option></select>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="site_featured[]" value="1" <?=!empty($s['featured'])?'checked':''?> style="width:15px;height:15px">推荐</label>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
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
function catOpts() {
  return CATS.map(function(c){ return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');
}
function addCat() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  d.innerHTML = '<input type="hidden" name="cat_id[]" value="cat_' + Date.now() + '"><input type="text" name="cat_icon[]" value="🌐" style="width:50px;padding:7px;border:1.5px solid var(--border);border-radius:8px;text-align:center"><input type="text" name="cat_name[]" placeholder="分类名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="number" name="cat_sort[]" value="0" style="width:70px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('catList').appendChild(d);
}
function addSite() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;padding:8px;background:var(--surface-2);border-radius:10px';
  d.innerHTML = '<input type="hidden" name="site_id[]" value="site_' + Date.now() + '"><input type="text" name="site_name[]" placeholder="名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="text" name="site_url[]" placeholder="https://..." style="flex:1;min-width:180px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><select name="site_cat[]" style="width:120px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="">— 分类 —</option>' + catOpts() + '</select><select name="site_region[]" style="width:80px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="cn">国内</option><option value="intl">海外</option></select><label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="site_featured[]" value="1" style="width:15px;height:15px">推荐</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('siteList').appendChild(d);
}
function presetSite(name, url, desc, region) {
  addSite();
  var rows = document.querySelectorAll('#siteList > div');
  var row = rows[rows.length - 1];
  row.querySelector('input[name="site_name[]"]').value = name;
  row.querySelector('input[name="site_url[]"]').value = url;
  var reg = row.querySelector('select[name="site_region[]"]');
  reg.value = region;
}
</script>
<?php admin_footer(); ?>
