<?php
/**
 * 资料下载中心 — 免费资料列表 + 门禁表单下载
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>资料下载 | <?=site_config_get('site_name')?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830b" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .dl-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;transition:.15s}
  .dl-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260901a" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1200px">
    <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);align-items:center;margin-bottom:28px">
      <div style="display:flex;flex-direction:column;gap:14px">
        <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">RESOURCES · 资料库</span>
        <h1 style="font-size:clamp(28px,4.5vw,44px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">好资料，<span style="font-family:var(--font-display);font-style:italic">领走即用</span></h1>
        <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:520px">白皮书 · 模板 · 报告 · 工具包。填写表单即可免费下载，直接用在你的增长里。</p>
        <div style="display:flex;gap:18px;margin-top:6px;color:var(--faint);font-size:12.5px;flex-wrap:wrap">
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> <b style="color:var(--fg)"><?=count($downloads)?></b> 份资料</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></span> 免费下载</span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <?php $dlc = [
          ['📄', '白皮书', '深度行业报告', '', 'var(--accent-soft)', 'var(--accent)'],
          ['🧩', '模板', '可复用工具模板', '', 'var(--ok-soft)', 'var(--ok)'],
          ['📊', '报告', '数据分析报告', '', 'oklch(70% .13 305/.14)', 'oklch(60% .18 300)'],
          ['🎁', '工具包', '增长实用工具包', '', 'oklch(70% .13 75/.14)', 'oklch(62% .15 70)'],
        ]; foreach ($dlc as $dci => $dc): ?>
        <a href="?cat=<?=$dc[3]?>" style="display:flex;flex-direction:column;gap:10px;padding:18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%);text-decoration:none;transition:transform .25s var(--ease-spring),box-shadow .25s,border-color .25s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-sm)';this.style.borderColor='var(--border-strong)'" onmouseout="this.style.transform='none';this.style.boxShadow='none';this.style.borderColor='var(--border)'">
          <span style="width:38px;height:38px;border-radius:12px;background:<?=$dc[4]?>;color:<?=$dc[5]?>;display:grid;place-items:center;font-size:18px"><?=$dc[0]?></span>
          <b style="font-size:14.5px;color:var(--fg)"><?=$dc[1]?></b>
          <span style="font-size:12px;color:var(--muted);line-height:1.5"><?=$dc[2]?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 分类 -->
    <div class="flex gap-2 flex-wrap mb-8">
      <a href="?cat=all" class="px-4 py-2 rounded-full text-sm font-semibold <?=$catFilter==='all'?'':'bg-white border'?>" style="<?=$catFilter==='all'?'background:var(--accent);color:var(--on-accent)':''?>">全部</a>
      <?php foreach ($cats as $ck => $cv): ?>
      <a href="?cat=<?=$ck?>" class="px-4 py-2 rounded-full text-sm font-semibold <?=$catFilter===$ck?'':'bg-white border'?>" style="<?=$catFilter===$ck?'background:var(--accent);color:var(--on-accent)':''?>"><?=htmlspecialchars($cv)?></a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($downloads)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">暂无资料</div>
    <?php else: ?>
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
      <?php foreach ($downloads as $d): ?>
      <div class="dl-card">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#7dd3fc,#86efac);display:grid;place-items:center;font-size:20px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span></div>
          <div style="flex:1;min-width:0">
            <div class="font-bold"><?=htmlspecialchars($d['title'])?></div>
            <div class="text-[11px] text-gray-600 mt-0.5"><?=htmlspecialchars($cats[$d['category'] ?? ''] ?? $d['category'] ?? '资料')?></div>
          </div>
        </div>
        <p class="text-sm text-gray-600 mt-3 leading-relaxed"><?=htmlspecialchars($d['description'] ?? '')?></p>
        <?php if (!empty($d['tags'])): ?>
        <div class="flex gap-1.5 flex-wrap mt-3">
          <?php foreach (array_slice($d['tags'], 0, 4) as $t): ?>
          <span class="px-2 py-0.5 rounded-full text-[10.5px] font-semibold" style="background:var(--accent-soft);color:var(--accent)">#<?=htmlspecialchars($t)?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="/downloads/<?=urlencode($d['slug'] ?: $d['id'])?>" class="mt-4 w-full rounded-full py-2.5 font-bold text-sm block text-center" style="background:var(--accent);color:var(--on-accent)">查看详情 →</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- 门禁表单弹层 -->
  <div id="dlOverlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center" onclick="if(event.target===this)closeDl()">
    <div style="background:var(--surface);border-radius:18px;padding:28px;width:90%;max-width:400px">
      <h2 class="font-bold text-lg mb-1" id="dlTitle">下载资料</h2>
      <p class="text-xs text-gray-600 mb-5">填写信息后即可获取下载链接</p>
      <form onsubmit="return submitDl(event)" class="grid gap-3">
        <input type="hidden" name="download_id" id="dlId">
        <input type="text" name="name" required placeholder="你的姓名" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border)">
        <input type="email" name="email" required placeholder="工作邮箱" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border)">
        <input type="text" name="company" placeholder="公司 / 组织" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border)">
        <input type="text" name="title" placeholder="职位（选填）" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border)">
        <button type="submit" class="w-full rounded-full py-3 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">获取下载链接</button>
        <div id="dlMsg" class="text-sm text-center"></div>
      </form>
    </div>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
      <div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>

<script>
function openDlForm(id, title) {
  document.getElementById('dlId').value = id;
  document.getElementById('dlTitle').textContent = '下载：' + title;
  document.getElementById('dlOverlay').style.display = 'flex';
}
function closeDl() { document.getElementById('dlOverlay').style.display = 'none'; }
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
        msg.innerHTML = '<span style="color:#dc2626"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01M15 9h.01"/></svg></span> ' + (d.error || '下载失败') + '</span>';
      }
    }).catch(function(){ msg.innerHTML = '<span style="color:#dc2626">网络异常</span>'; });
}
</script>
</body>
</html>
<?php PageCache::end('downloads', 1800); ?>
