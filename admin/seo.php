<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('seo');

$seoFile = DATA_DIR . '/seo.json';
$seo = json_read($seoFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $seo = $_POST['seo'] ?? [];
    json_write($seoFile, $seo);
    $message = 'SEO 设置已保存';
}

$pages = [
    'index' => '首页',
    'about' => '关于我们',
    'capability' => '产品',
    'courses' => '解决方案',
    'flow-community' => 'Flow社区',
];

admin_header('SEO 管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('seo'); ?>
  <div class="main">
    <h1>SEO 管理</h1>
    <p class="sub">设置每个页面的 SEO 标题、描述和关键词</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($pages as $key => $label): 
        $pageSeo = $seo[$key] ?? ['title' => '', 'description' => '', 'keywords' => ''];
      ?>
      <div class="card">
        <h2><?=htmlspecialchars($label)?></h2>
        <div class="field">
          <label>Meta 标题 <span class="hint">· 建议 10–60 字</span></label>
          <input type="text" name="seo[<?=$key?>][title]" value="<?=htmlspecialchars($pageSeo['title'])?>" placeholder="OpenFlow · AI 时代的网站增长操作系统">
        </div>
        <div class="field">
          <label>Meta 描述 <span class="hint">· 建议 50–160 字</span></label>
          <textarea name="seo[<?=$key?>][description]" rows="2" placeholder="OpenFlow 以「管理 + 心理」双螺旋驱动..."><?=htmlspecialchars($pageSeo['description'])?></textarea>
        </div>
        <div class="field">
          <label>关键词 <span class="hint">· 逗号分隔</span></label>
          <input type="text" name="seo[<?=$key?>][keywords]" value="<?=htmlspecialchars($pageSeo['keywords'] ?? '')?>" placeholder="网站增长, SEO, GEO, AI 运营">
        </div>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary">保存所有 SEO 设置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
