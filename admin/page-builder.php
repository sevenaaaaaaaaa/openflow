<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('pages');

$builderFile = DATA_DIR . '/builder-pages.json';
$pages = json_read($builderFile);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $data = [
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'seo_title' => $_POST['seo_title'] ?? '',
        'seo_desc' => $_POST['seo_desc'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'is_ad_landing' => isset($_POST['is_ad_landing']),
        'blocks' => [],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($data['slug'])) $data['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $data['title']);

    // Build blocks from POST
    $blockTypes = $_POST['block_type'] ?? [];
    foreach ($blockTypes as $bi => $bt) {
        if (empty($bt)) continue;
        $block = ['id' => 'blk_' . $bi . '_' . substr(bin2hex(random_bytes(4)), 0, 6), 'type' => $bt];
        foreach (['title','subtitle','content','image','bg_color','button_text','button_url','video_url','icon','columns','count','items','form_slug','layout'] as $fk) {
            if (isset($_POST['block_' . $fk][$bi])) $block[$fk] = $_POST['block_' . $fk][$bi];
        }
        $data['blocks'][] = $block;
    }

    if (empty($id)) {
        $data['id'] = 'lp_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $data['created_at'] = date('Y-m-d H:i:s');
        $pages[] = $data;
    } else {
        foreach ($pages as &$p) { if ($p['id'] === $id) { $p = array_merge($p, $data); break; } }
    }
    json_write($builderFile, $pages);
    $message = '落地页已保存';
}

if (isset($_POST['delete'])) {
    $pages = array_values(array_filter($pages, fn($p) => $p['id'] !== $_POST['delete']));
    json_write($builderFile, $pages);
    header('Location: page-builder.php');
    exit;
}

$editPage = null;
if (isset($_GET['edit'])) {
    foreach ($pages as $p) { if ($p['id'] === $_GET['edit']) { $editPage = $p; break; } }
}

$blockTypes = [
    'hero' => 'Hero 大标题', 'features' => '功能列表', 'cta' => 'CTA 行动号召',
    'text' => '文本段落', 'image-text' => '图文混排', 'stats' => '数据指标',
    'testimonials' => '客户证言', 'logo-wall' => 'Logo 墙', 'faq' => 'FAQ',
    'gallery' => '图片画廊', 'form' => '表单嵌入', 'newsletter' => '订阅表单',
    'video' => '视频嵌入',
];

admin_header('落地页构建器');
?>
<div class="admin-layout">
  <?php admin_sidebar('page-builder'); ?>
  <div class="main">
    <h1>落地页构建器</h1>
    <p class="sub">模块化搭建营销落地页 · 广告页独立入口 · 支持 Hero/CTA/表单等 13 种区块</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>页面标题</th><th>Slug</th><th>区块数</th><th>广告页</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pages)): ?><tr><td colspan="6" class="empty">暂无落地页</td></tr><?php endif; ?>
          <?php foreach ($pages as $p): ?>
          <tr>
            <td><strong><?=htmlspecialchars($p['title'])?></strong></td>
            <td><code>/lp/<?=htmlspecialchars($p['slug'])?></code></td>
            <td><?=count($p['blocks'] ?? [])?></td>
            <td><?=($p['is_ad_landing'] ?? false) ? '<span class="badge badge-yellow">📢 广告页</span>' : '<span class="text-sm text-muted">—</span>'?></td>
            <td><span class="badge <?=($p['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$p['status']??'draft'?></span></td>
            <td>
              <a href="?edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline" onsubmit="return confirm('确认删除?')">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:12px 20px;border-top:1px solid var(--border)">
        <a href="?edit=new" class="btn btn-primary btn-sm">+ 新建落地页</a>
        <a href="?edit=new&ad=1" class="btn btn-ghost btn-sm">+ 新建广告落地页</a>
      </div>
    </div>

    <?php if (isset($_GET['edit'])): ?>
    <div class="card">
      <h2><?=$editPage?'编辑：'.htmlspecialchars($editPage['title']):'新建落地页'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?=htmlspecialchars($editPage['id']??'')?>">
        <div class="field-row">
          <div class="field"><label>页面标题</label><input type="text" name="title" value="<?=htmlspecialchars($editPage['title']??'')?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($editPage['slug']??'')?>" placeholder="自动生成"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($editPage['seo_title']??'')?>"></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editPage['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editPage['status']??'')==='published'?'selected':''?>>已发布</option></select></div>
        </div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" rows="2"><?=htmlspecialchars($editPage['seo_desc']??'')?></textarea></div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_ad_landing" value="1" <?=($editPage['is_ad_landing']??false)?'checked':''?> style="width:18px;height:18px">📢 标记为广告落地页（独立入口）</label></div>

        <!-- Blocks Editor -->
        <div class="card" style="margin:16px 0;padding:16px">
          <h2>🧱 页面区块</h2>
          <p class="text-sm text-muted mb-4">从上到下排列，拖拽可排序（开发中）</p>
          <div id="blocksList">
            <?php foreach (($editPage['blocks'] ?? []) as $bi => $blk): ?>
            <div class="block-item" style="border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <span style="font-weight:600;font-size:14px">🧱 <?=htmlspecialchars($blockTypes[$blk['type']] ?? $blk['type'])?></span>
                <select name="block_type[]" onchange="renameBlock(this)" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                  <?php foreach ($blockTypes as $btk => $btv): ?>
                  <option value="<?=$btk?>" <?=$blk['type']===$btk?'selected':''?>><?=htmlspecialchars($btv)?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest('.block-item').remove()">✕</button>
              </div>
              <div class="block-fields" style="display:grid;gap:8px">
                <input type="text" name="block_title[]" value="<?=htmlspecialchars($blk['title']??'')?>" placeholder="标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">
                <input type="text" name="block_subtitle[]" value="<?=htmlspecialchars($blk['subtitle']??'')?>" placeholder="副标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">
                <textarea name="block_content[]" rows="2" placeholder="内容 (支持 HTML)" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px;font-family:var(--mono)"><?=htmlspecialchars($blk['content']??'')?></textarea>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                  <input type="text" name="block_image[]" value="<?=htmlspecialchars($blk['image']??'')?>" placeholder="图片路径">
                  <input type="text" name="block_bg_color[]" value="<?=htmlspecialchars($blk['bg_color']??'')?>" placeholder="背景色 (如 #f4f3e9)">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                  <input type="text" name="block_button_text[]" value="<?=htmlspecialchars($blk['button_text']??'')?>" placeholder="按钮文字">
                  <input type="text" name="block_button_url[]" value="<?=htmlspecialchars($blk['button_url']??'')?>" placeholder="按钮链接">
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px">
            <?php foreach ($blockTypes as $btk => $btv): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addBlock('<?=$btk?>','<?=htmlspecialchars($btv)?>')">+ <?=htmlspecialchars($btv)?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">保存落地页</button>
        <a href="page-builder.php" class="btn btn-ghost">取消</a>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
var blockIdx = <?=count($editPage['blocks'] ?? [])?>;

function addBlock(type, label) {
  var div = document.createElement('div');
  div.className = 'block-item';
  div.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)';
  var idx = blockIdx++;
  div.innerHTML =
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">' +
      '<span style="font-weight:600;font-size:14px">🧱 ' + label + '</span>' +
      '<select name="block_type[]" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
        '<?php foreach ($blockTypes as $btk => $btv): ?><option value="<?=$btk?>" ' + (type === '<?=$btk?>' ? 'selected' : '') + '><?=htmlspecialchars($btv)?></option><?php endforeach; ?>' +
      '</select>' +
      '<button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest(\'.block-item\').remove()">✕</button>' +
    '</div>' +
    '<div class="block-fields" style="display:grid;gap:8px">' +
      '<input type="text" name="block_title[]" placeholder="标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' +
      '<input type="text" name="block_subtitle[]" placeholder="副标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' +
      '<textarea name="block_content[]" rows="2" placeholder="内容 (支持 HTML)" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px;font-family:var(--mono)"></textarea>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
        '<input type="text" name="block_image[]" placeholder="图片路径">' +
        '<input type="text" name="block_bg_color[]" placeholder="背景色">' +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
        '<input type="text" name="block_button_text[]" placeholder="按钮文字">' +
        '<input type="text" name="block_button_url[]" placeholder="按钮链接">' +
      '</div>' +
    '</div>';
  document.getElementById('blocksList').appendChild(div);
}

function renameBlock(sel) {
  var label = sel.options[sel.selectedIndex].text;
  var title = sel.parentElement.querySelector('span');
  if (title) title.textContent = '🧱 ' + label;
}
</script>
<?php admin_footer(); ?>
