<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('seo');

$strategyFile = DATA_DIR . '/seo-strategy.json';
$strategy = json_read($strategyFile);
$message = '';
$previewResults = [];

$contentTypes = [
    'article' => ['label' => '文章', 'items' => 'get_articles()'],
    'landing' => ['label' => '聚合页', 'items' => 'get_landing_pages()'],
    'course' => ['label' => '课程', 'items' => 'json_read(DATA_DIR . "/courses/index.json")'],
    'download' => ['label' => '资料', 'items' => 'json_read(DATA_DIR . "/downloads.json")'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save'])) {
        $strategy['templates'] = $_POST['templates'] ?? [];
        $strategy['structured'] = $_POST['structured'] ?? [];
        json_write($strategyFile, $strategy);
        $message = 'SEO 策略已保存';
    }
    if (isset($_POST['apply_all'])) {
    $applied = 0;
    $cats = get_categories('article');
    $catMap = [];
    foreach ($cats as $c) $catMap[$c['key']] = $c['name'];

    foreach ($contentTypes as $ct => $cv) {
        $t = $templates[$ct] ?? [];
        $titleTpl = $t['title'] ?? '';
        $descTpl = $t['description'] ?? '';
        $kwTpl = $t['keywords'] ?? '';
        $structTpl = $strategy['structured'][$ct] ?? '';

        $items = [];
        if ($ct === 'article') $items = get_articles();
        elseif ($ct === 'landing') { save_landing_pages(array_map(function($item) use ($titleTpl, $descTpl, $kwTpl) {
            $r = ['{title}'=>$item['title']??'','{site_name}'=>'OpenFlow','{date}'=>date('Y-m-d')];
            $item['seo_title'] = str_replace(array_keys($r),array_values($r),$titleTpl);
            $item['seo_desc'] = str_replace(array_keys($r),array_values($r),$descTpl);
            return $item;
        }, get_landing_pages())); $applied += count(get_landing_pages()); continue; }

        foreach ($items as &$item) {
            $catName = $catMap[$item['category'] ?? ''] ?? '';
            $tagsStr = implode(', ', array_slice($item['tags'] ?? [], 0, 3));
            $replacements = ['{title}'=>$item['title']??'', '{category}'=>$catName, '{tags}'=>$tagsStr, '{site_name}'=>'OpenFlow', '{date}'=>date('Y-m-d')];
            $item['seo_title'] = str_replace(array_keys($replacements), array_values($replacements), $titleTpl);
            $item['seo_desc'] = str_replace(array_keys($replacements), array_values($replacements), $descTpl);
            $item['seo_keywords'] = str_replace(array_keys($replacements), array_values($replacements), $kwTpl);

            if ($ct === 'article') save_article($item['id'], $item);
            elseif ($ct === 'course') { /* course update logic */ }
            elseif ($ct === 'download') { /* download update logic */ }
            $applied++;
        }
    }
    $message = "已应用到 {$applied} 项内容";
}
if (isset($_POST['preview'])) {
        $type = $_POST['preview_type'] ?? '';
        $templateTitle = $_POST['templates'][$type]['title'] ?? '';
        $templateDesc = $_POST['templates'][$type]['description'] ?? '';
        $templateKeywords = $_POST['templates'][$type]['keywords'] ?? '';
        $structuredTemplate = $_POST['structured'][$type] ?? '';

        $cats = get_categories($type === 'article' ? 'article' : ($type === 'course' ? 'course' : ($type === 'download' ? 'download' : 'article')));
        $catMap = [];
        foreach ($cats as $c) $catMap[$c['key']] = $c['name'];

        $items = [];
        if ($type === 'article') $items = get_articles();
        elseif ($type === 'landing') $items = get_landing_pages();
        elseif ($type === 'course') $items = json_read(DATA_DIR . '/courses/index.json');
        elseif ($type === 'download') $items = json_read(DATA_DIR . '/downloads.json');

        foreach (array_slice($items, 0, 10) as $item) {
            $title = $item['title'] ?? '';
            $catKey = $item['category'] ?? '';
            $catName = $catMap[$catKey] ?? '';
            $tags = implode(', ', array_slice($item['tags'] ?? [], 0, 3));

            $replacements = [
                '{title}' => $title,
                '{category}' => $catName,
                '{tags}' => $tags,
                '{site_name}' => 'OpenFlow',
                '{date}' => date('Y-m-d'),
            ];

            $seoTitle = str_replace(array_keys($replacements), array_values($replacements), $templateTitle);
            $seoDesc = str_replace(array_keys($replacements), array_values($replacements), $templateDesc);
            $seoKeywords = str_replace(array_keys($replacements), array_values($replacements), $templateKeywords);

            $previewResults[] = [
                'title' => $title,
                'seo_title' => $seoTitle,
                'seo_desc' => $seoDesc,
                'seo_keywords' => $seoKeywords,
            ];
        }
        $message = '预览生成了 ' . count($previewResults) . ' 条 TDK';
    }
}

$templates = $strategy['templates'] ?? [];

if (!defined('OF_EMBED')) admin_header('批量 SEO 策略');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('seo-batch'); ?>
  <div class="main">
<?php endif; ?>
    <h1>批量 SEO 策略</h1>
    <p class="sub">为文章/聚合页/课程/资料设置 TDK 生成规则，支持变量替换，一键应用</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($contentTypes as $ct => $cv):
        $t = $templates[$ct] ?? ['title' => '{title} - {site_name}', 'description' => '{title} - {site_name}', 'keywords' => '{category}, {tags}'];
      ?>
      <div class="card">
        <h2><?=htmlspecialchars($cv['label'])?></h2>
        <div class="field"><label>标题模板 <span class="hint">支持 {title} {category} {tags} {site_name} {date}</span></label>
          <input type="text" name="templates[<?=$ct?>][title]" value="<?=htmlspecialchars($t['title'] ?? '')?>" placeholder="{title} - {site_name}">
        </div>
        <div class="field"><label>描述模板</label>
          <textarea name="templates[<?=$ct?>][description]" rows="2"><?=htmlspecialchars($t['description'] ?? '')?></textarea>
        </div>
        <div class="field"><label>关键词模板</label>
          <input type="text" name="templates[<?=$ct?>][keywords]" value="<?=htmlspecialchars($t['keywords'] ?? '')?>" placeholder="{category}, {tags}">
        </div>
        <div class="field"><label>结构化数据模板 (JSON-LD) <span class="hint">可选，留空不生成</span></label>
          <textarea name="structured[<?=$ct?>]" rows="3" style="font-family:var(--mono);font-size:13px"><?=htmlspecialchars($strategy['structured'][$ct] ?? '')?></textarea>
        </div>
        <div class="flex gap-2">
          <button type="submit" name="preview" value="1" class="btn btn-ghost btn-sm" onclick="this.form.elements['preview_type'].value='<?=$ct?>'">预览 10 条</button>
          <input type="hidden" name="preview_type" value="">
        </div>
      </div>
      <?php endforeach; ?>

      <button type="submit" name="save" class="btn btn-primary">保存策略</button>
      <button type="submit" name="apply_all" class="btn btn-ghost" data-confirm="确认将策略应用到所有内容？将覆盖现有 SEO 设置。">应用到全部内容</button>
    </form>

    <!-- Preview Results -->
    <?php if (!empty($previewResults)): ?>
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📋 预览 (前 10 条)</h2>
      <table>
        <thead><tr><th>原标题</th><th>SEO 标题</th><th>SEO 描述</th></tr></thead>
        <tbody>
          <?php foreach ($previewResults as $pr): ?>
          <tr>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($pr['title'])?></td>
            <td style="max-width:200px"><?=htmlspecialchars($pr['seo_title'])?></td>
            <td style="max-width:250px;font-size:12px;color:var(--text-2)"><?=htmlspecialchars(mb_substr($pr['seo_desc'],0,100))?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>可用变量说明</h2>
      <table><thead><tr><th>变量</th><th>说明</th><th>示例</th></tr></thead>
      <tbody>
        <tr><td><code>{title}</code></td><td>原标题</td><td>GEO 优化落地指南</td></tr>
        <tr><td><code>{category}</code></td><td>分类名称</td><td>增长洞察</td></tr>
        <tr><td><code>{tags}</code></td><td>标签（前3个）</td><td>GEO, 内容引擎, AI 收录</td></tr>
        <tr><td><code>{site_name}</code></td><td>站点名称</td><td>OpenFlow</td></tr>
        <tr><td><code>{date}</code></td><td>当前日期</td><td>2026-08-09</td></tr>
      </tbody></table>
    </div>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
