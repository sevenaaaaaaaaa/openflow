<?php
/**
 * 资料下载中心 — 免费资料列表 + 门禁表单下载
 *
 * v7（2026-09-01）：迁到共享 archetype（双栏 hero + tab 筛选 + link-grid 资料卡 + 共享 .modal 门禁表单）。逻辑与接口调用原样保留。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('downloads', 1800)) exit;

$all = json_read(DATA_DIR . '/downloads.json');
$downloads = array_values(array_filter($all, fn($d) => ($d['status'] ?? 'draft') === 'published'));
$catFilter = req_str('cat', 'all');

// 分类：优先使用后台配置的 download 分类，退化为内置默认
$catDefs = get_categories('download');
if (empty($catDefs)) {
    $catDefs = array_map(fn($k) => ['key' => $k, 'name' => ['whitepaper' => '白皮书', 'template' => '模板', 'report' => '报告', 'ebook' => '电子书', 'toolkit' => '工具包'][$k] ?? $k], ['whitepaper', 'template', 'report', 'ebook', 'toolkit']);
}
$cats = [];
foreach ($catDefs as $c) $cats[$c['key']] = $c['name'];
if ($catFilter !== 'all') $downloads = array_values(array_filter($downloads, fn($d) => ($d['category'] ?? '') === $catFilter));
usort($downloads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>资料下载 | <?=site_config_get('site_name')?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 资料库独有：资料卡。其余全部来自 modules.css。 */
.dl{display:flex;flex-direction:column;gap:10px}
.dl .hd{display:flex;align-items:center;gap:12px}
.dl .hd .ic{width:40px;height:40px;border-radius:11px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto}
.dl .hd .ic svg{width:18px;height:18px}
.dl h3{font-size:15.5px;font-weight:700;line-height:1.4}
.dl .cat{font-family:var(--font-mono);font-size:11.5px;color:var(--faint);margin-top:2px}
.dl p{font-size:13.5px;color:var(--muted);line-height:1.7}
.dl .btn{margin-top:auto;align-self:flex-start}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('downloads'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="downloads-hero">
    <div class="hero">
      <div class="hero-copy">
        <span class="kicker">RESOURCES · 资料库</span>
        <h1>好资料，<br><i class="si">领走即用</i></h1>
        <p class="lead">白皮书 · 模板 · 报告 · 工具包。填写表单即可免费下载，直接用在你的增长里。</p>
        <div class="trust"><span class="dot"></span><?=count($all)?> 份资料 · 免费领取</div>
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">downloads · 四类</div></div>
        <div class="win-flow">
          <?php $dlc = [['白皮书','深度行业报告','whitepaper'],['模板','可复用工具模板','template'],['报告','数据分析报告','report'],['工具包','增长实用工具包','toolkit']]; foreach ($dlc as $k => $dc): if ($k) echo '<div class="flow-link"></div>'; ?>
          <a class="flow-row" href="?cat=<?=$dc[2]?>"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span><div><div class="ft"><?=$dc[0]?></div><div class="fd"><?=$dc[1]?></div></div></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section id="list" class="sec reveal" data-od-anchor data-od-id="downloads-list">
    <div class="tab-bar" role="navigation" aria-label="分类" style="justify-content:flex-start">
      <a class="tab-p" href="?cat=all" aria-selected="<?=$catFilter==='all'?'true':'false'?>">全部</a>
      <?php foreach ($cats as $ck => $cv): ?><a class="tab-p" href="?cat=<?=$ck?>" aria-selected="<?=$catFilter===$ck?'true':'false'?>"><?=htmlspecialchars($cv)?></a><?php endforeach; ?>
    </div>
    <?php if (empty($downloads)): ?>
    <div class="empty">暂无资料</div>
    <?php else: ?>
    <div class="grid g3" style="gap:16px">
      <?php foreach ($downloads as $d): ?>
      <div class="card dl">
        <div class="hd"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0-4-4m4 4 4-4M4 20h16"/></svg></span><div><h3><?=htmlspecialchars($d['title'])?></h3><div class="cat"><?=htmlspecialchars($cats[$d['category'] ?? ''] ?? $d['category'] ?? '资料')?></div></div></div>
        <p><?=htmlspecialchars($d['description'] ?? '')?></p>
        <?php if (!empty($d['tags'])): ?><div class="tags"><?php foreach (array_slice($d['tags'], 0, 4) as $t): ?><span>#<?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
        <a href="/downloads/<?=urlencode($d['slug'] ?: $d['id'])?>" class="btn ghost">查看详情 →</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- 门禁表单弹层（共享 .modal） -->
  <div id="dlOverlay" class="modal" onclick="if(event.target===this)closeDl()">
    <div class="mbox">
      <div class="mhead"><h3 id="dlTitle">下载资料</h3><button class="mx" type="button" onclick="closeDl()" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>
      <p class="note" style="margin-bottom:14px">填写信息后即可获取下载链接</p>
      <form onsubmit="return submitDl(event)" class="mbody">
        <input type="hidden" name="download_id" id="dlId">
        <input type="text" name="name" required placeholder="你的姓名" class="inp">
        <input type="email" name="email" required placeholder="工作邮箱" class="inp">
        <input type="text" name="company" placeholder="公司 / 组织" class="inp">
        <input type="text" name="title" placeholder="职位（选填）" class="inp">
        <button type="submit" class="btn primary" style="width:100%">获取下载链接</button>
        <div id="dlMsg" class="f-note" style="text-align:center"></div>
      </form>
    </div>
  </div>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function openDlForm(id, title) {
  document.getElementById('dlId').value = id;
  document.getElementById('dlTitle').textContent = '下载：' + title;
  document.getElementById('dlOverlay').classList.add('open');
}
function closeDl() { document.getElementById('dlOverlay').classList.remove('open'); }
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
        msg.innerHTML = '<span style="color:var(--danger)">' + (d.error || '提交失败') + '</span>';
      }
    }).catch(function(){ msg.innerHTML = '<span style="color:var(--danger)">网络异常</span>'; });
}
</script>
</body>
</html>
<?php PageCache::end('downloads', 1800); ?>
