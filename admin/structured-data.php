<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('structured');

$message = '';
$type = $_GET['type'] ?? 'page';
$id = $_GET['id'] ?? '';
$allowedTypes = ['page', 'article', 'topic', 'landing'];

if (!in_array($type, $allowedTypes)) $type = 'page';

// Get available items
$items = [];
if ($type === 'page') {
    $pages = ['index' => '首页', 'about' => '关于我们', 'capability' => '产品', 'courses' => '解决方案'];
    foreach ($pages as $k => $v) $items[] = ['id' => $k, 'title' => $v];
} elseif ($type === 'article') {
    foreach (get_articles() as $a) $items[] = ['id' => $a['id'], 'title' => $a['title']];
} elseif ($type === 'topic') {
    foreach (get_topics() as $t) $items[] = ['id' => $t['id'], 'title' => $t['title']];
} elseif ($type === 'landing') {
    foreach (get_landing_pages() as $p) $items[] = ['id' => $p['id'], 'title' => $p['title']];
}

if (empty($id) && !empty($items)) $id = $items[0]['id'];

$sd = get_structured_data($type, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $raw = $_POST['jsonld'] ?? '';
    // Validate JSON
    $parsed = json_decode($raw, true);
    if ($parsed !== null || $raw === '') {
        save_structured_data($type, $id, ['jsonld' => $raw, 'updated_at' => date('Y-m-d H:i:s')]);
        $message = '结构化数据已保存';
        $sd = get_structured_data($type, $id);
    } else {
        $message = 'JSON 格式无效，请检查';
    }
}

admin_header('结构化数据');
?>
<div class="admin-layout">
  <?php admin_sidebar('structured'); ?>
  <div class="main">
    <h1>结构化数据 (JSON-LD)</h1>
    <p class="sub">为每个页面/文章配置自定义 Schema.org 结构化数据，直接嵌入前端代码</p>
    <?php if ($message): ?><?=msg($message==='JSON 格式无效，请检查'?'error':'success', $message)?><?php endif; ?>

    <div class="flex gap-4 mb-4" style="align-items:end">
      <div class="field" style="margin-bottom:0">
        <label>内容类型</label>
        <select onchange="location.href='?type='+this.value">
          <?php foreach ($allowedTypes as $t): ?>
          <option value="<?=$t?>" <?=$type===$t?'selected':''?>><?=htmlspecialchars($t)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;flex:1">
        <label>选择<?=htmlspecialchars($type)?></label>
        <select onchange="location.href='?type=<?=$type?>&id='+encodeURIComponent(this.value)">
          <?php foreach ($items as $item): ?>
          <option value="<?=htmlspecialchars($item['id'])?>" <?=$id===$item['id']?'selected':''?>><?=htmlspecialchars($item['title'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="card">
      <h2>JSON-LD 代码</h2>
      <p class="text-sm text-muted mb-4">此代码将直接输出到页面 <code>&lt;head&gt;</code> 中的 <code>&lt;script type="application/ld+json"&gt;</code></p>
      <form method="post">
        <?= csrf_field() ?>
        <textarea name="jsonld" rows="16" style="font-family:var(--mono);font-size:13px;line-height:1.6;min-height:300px" placeholder='{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "文章标题",
  "description": "文章描述",
  "datePublished": "2026-08-09"
}'><?=htmlspecialchars($sd['jsonld'] ?? '')?></textarea>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">保存</button>
          <button type="button" class="btn btn-ghost" onclick="formatJson()">格式化</button>
          <button type="button" class="btn btn-ghost" onclick="loadTemplate()">加载模板</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>常用 Schema 模板</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px">
        <button class="btn btn-ghost btn-sm" onclick="insertTemplate('Organization')">Organization</button>
        <button class="btn btn-ghost btn-sm" onclick="insertTemplate('Article')">Article</button>
        <button class="btn btn-ghost btn-sm" onclick="insertTemplate('BreadcrumbList')">BreadcrumbList</button>
        <button class="btn btn-ghost btn-sm" onclick="insertTemplate('FAQPage')">FAQPage</button>
        <button class="btn btn-ghost btn-sm" onclick="insertTemplate('Product')">Product</button>
        <button class="btn btn-ghost btn-sm" onclick="insertTemplate('LocalBusiness')">LocalBusiness</button>
      </div>
    </div>
  </div>
</div>
<script>
var templates = {
  Organization: '{\n  "@context": "https://schema.org",\n  "@type": "Organization",\n  "name": "OpenFlow",\n  "url": "https://nownexts.com",\n  "logo": "https://nownexts.com/assets/images/logo.png",\n  "contactPoint": {\n    "@type": "ContactPoint",\n    "telephone": "+86-13166373667",\n    "contactType": "customer service"\n  }\n}',
  Article: '{\n  "@context": "https://schema.org",\n  "@type": "Article",\n  "headline": "文章标题",\n  "description": "文章描述",\n  "datePublished": "' + new Date().toISOString().split('T')[0] + '",\n  "author": {\n    "@type": "Person",\n    "name": "OpenFlow"\n  }\n}',
  BreadcrumbList: '{\n  "@context": "https://schema.org",\n  "@type": "BreadcrumbList",\n  "itemListElement": [{\n    "@type": "ListItem",\n    "position": 1,\n    "name": "首页",\n    "item": "https://nownexts.com"\n  },{\n    "@type": "ListItem",\n    "position": 2,\n    "name": "当前页面"\n  }]\n}',
  FAQPage: '{\n  "@context": "https://schema.org",\n  "@type": "FAQPage",\n  "mainEntity": [{\n    "@type": "Question",\n    "name": "问题1",\n    "acceptedAnswer": {\n      "@type": "Answer",\n      "text": "答案1"\n    }\n  }]\n}',
  Product: '{\n  "@context": "https://schema.org",\n  "@type": "Product",\n  "name": "产品名称",\n  "description": "产品描述"\n}',
  LocalBusiness: '{\n  "@context": "https://schema.org",\n  "@type": "LocalBusiness",\n  "name": "OpenFlow 科技有限公司",\n  "address": {\n    "@type": "PostalAddress",\n    "addressLocality": "上海"\n  }\n}'
};
function insertTemplate(type) {
  var ta = document.querySelector('textarea[name="jsonld"]');
  ta.value = templates[type] || '';
}
function formatJson() {
  var ta = document.querySelector('textarea[name="jsonld"]');
  try { ta.value = JSON.stringify(JSON.parse(ta.value), null, 2); }
  catch(e) { alert('JSON 格式无效'); }
}
function loadTemplate() { insertTemplate('Organization'); }
</script>
<?php admin_footer(); ?>
