<?php
/**
 * 资料详情页 — 单个资料的完整展示 + 门禁表单下载
 *
 * v7（2026-09-01）：迁到共享 archetype（reader + art-head + contact-wrap 门禁表单）。下载接口原样保留。
 * /downloads/{slug}
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$slug = req_str('slug', '');
$all = json_read(DATA_DIR . '/downloads.json');
$dl = null;
foreach ($all as $d) {
    if (($d['status'] ?? 'draft') === 'published' && (($d['slug'] ?? '') === $slug || ($d['id'] ?? '') === $slug)) { $dl = $d; break; }
}

if (!$dl) {
    header('Location: /downloads');
    exit;
}

$catDefs = get_categories('download');
$catNames = [];
foreach ($catDefs as $c) $catNames[$c['key']] = $c['name'];
$catName = $catNames[$dl['category'] ?? ''] ?? $dl['category'] ?? '资料';
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($dl['title'])?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars(mb_substr($dl['description'] ?? '', 0, 120))?>">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 资料详情页零私有 CSS */

</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('downloads'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section class="reveal in" data-od-id="download">
    <nav class="art-meta" aria-label="面包屑" style="margin-bottom:18px"><a href="/downloads" style="color:var(--faint)">← 全部资料</a></nav>
    <div class="contact-wrap">
      <div class="ct-pitch">
        <span class="kicker">资料 · <?=htmlspecialchars($catName)?></span>
        <h2><?=htmlspecialchars($dl['title'])?></h2>
        <p class="lead"><?=nl2br(htmlspecialchars($dl['description'] ?? ''))?></p>
        <?php if (!empty($dl['tags'])): ?><div class="tags"><?php foreach ($dl['tags'] as $t): ?><span>#<?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
        <div class="art-meta" style="margin-top:8px"><span>已下载 <?=(int)($dl['download_count'] ?? 0)?> 次</span><span class="sep"></span><span>更新于 <?=htmlspecialchars(substr($dl['updated_at'] ?? $dl['created_at'] ?? '', 0, 10))?></span></div>
      </div>
      <div class="form-card">
        <div class="sec-head" style="gap:6px;margin-bottom:18px"><h3 class="h3" style="font-size:20px">获取下载链接</h3><p class="note">填写信息后即可获取下载</p></div>
        <form onsubmit="return submitDl(event)" class="form-grid">
          <input type="hidden" name="download_id" value="<?=htmlspecialchars($dl['id'])?>">
          <div class="field"><label for="dl-name">姓名 *</label><input class="inp" id="dl-name" type="text" name="name" required placeholder="你的姓名"></div>
          <div class="field"><label for="dl-email">工作邮箱 *</label><input class="inp" id="dl-email" type="email" name="email" required placeholder="工作邮箱"></div>
          <div class="field"><label for="dl-company">公司 / 组织</label><input class="inp" id="dl-company" type="text" name="company" placeholder="公司 / 组织"></div>
          <div class="field"><label for="dl-title">职位</label><input class="inp" id="dl-title" type="text" name="title" placeholder="选填"></div>
          <button type="submit" class="btn primary" style="width:100%">获取下载链接</button>
          <div id="dlMsg" class="f-note" style="text-align:center"></div>
        </form>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function submitDl(e) {
  e.preventDefault();
  var msg = document.getElementById('dlMsg');
  var body = new FormData(e.target);
  fetch('/api/download', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        msg.innerHTML = '<span style="color:var(--ok)">✅ 下载开始…</span>';
        setTimeout(function(){ location.href = d.url; }, 800);
      } else {
        msg.innerHTML = '<span style="color:var(--danger)">' + (d.error || '下载失败') + '</span>';
      }
    }).catch(function(){ msg.innerHTML = '<span style="color:var(--danger)">网络异常</span>'; });
}
</script>
</body>
</html>
