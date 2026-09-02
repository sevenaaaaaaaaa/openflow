<?php
/**
 * 生态资产详情页 — Skill / 插件 / 主题 独立 SEO 页面
 * 访问：/asset.php?type=skill&id=xxx  或伪静态 /skill/{id}/
 * 包含：详情 / 演示预览 / 安装购买（CommerceSystem）/ 评分 / 相关推荐 / JSON-LD
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MarketplaceSystem.php';
require_once __DIR__ . '/lib/CommerceSystem.php';
require_once __DIR__ . '/lib/Personalizer.php';

$siteName = site_config_get('site_name', 'OpenFlow');
$type = $_GET['type'] ?? 'skill';
$id = $_GET['id'] ?? '';

// 解析：支持 asset.php?type=skill&id=xxx
if ($type === 'all') $type = 'skill';

// 从资产源获取
$asset = null;
$member = member_current();

if ($type === 'skill') {
    $asset = skill_get($id);
    if ($asset) {
        $asset['type'] = 'skill';
        $asset['icon'] = $asset['icon'] ?? '';
        $asset['version'] = $asset['version'] ?? '1.0.0';
    }
} elseif ($type === 'plugin') {
    foreach (mkt_assets() as $a) if ($a['type'] === 'plugin' && $a['id'] === $id) $asset = $a;
} elseif ($type === 'theme') {
    $themes = json_read(DATA_DIR . '/themes.json');
    foreach (($themes['themes'] ?? []) as $t) if ($t['id'] === $id) {
        $asset = $t;
        $asset['type'] = 'theme';
        $asset['icon'] = '';
        $asset['version'] = $t['version'] ?? '1.0.0';
    }
}

if (!$asset) { header('HTTP/1.0 404 Not Found'); die('资产不存在'); }

// 关联商品（若有售价）
$product = null;
foreach (CommerceSystem::products() as $p) {
    if ($p['type'] === $type && $p['asset_id'] === $id && $p['status'] === 'published') { $product = $p; break; }
}
$price = $product ? (float)($product['pricing']['price'] ?? 0) : 0;
$owned = $member && $product ? CommerceSystem::owns($member['id'], $product['id']) : false;

// SEO
$title = ($asset['title'] ?? $asset['name'] ?? $id) . ' - ' . $siteName;
$desc = mb_substr(strip_tags($asset['description'] ?? ''), 0, 160);
$icon = $asset['icon'] ?? '';
$author = $asset['author'] ?? 'OpenFlow';

// 相关推荐（按画像）
$pref = Personalizer::buildProfile($_COOKIE['fc_uid'] ?? '', $_COOKIE['member_id'] ?? '');
$relatedAssets = array_values(array_filter(mkt_assets(), fn($a) => $a['id'] !== $id));
usort($relatedAssets, function ($a, $b) use ($pref) {
    $sa = 0; $sb = 0;
    foreach (($a['tags'] ?? []) as $t) if (isset($pref['tags'][$t])) $sa += $pref['tags'][$t];
    foreach (($b['tags'] ?? []) as $t) if (isset($pref['tags'][$t])) $sb += $pref['tags'][$t];
    return $sb <=> $sa;
});
$related = array_slice($relatedAssets, 0, 4);

// 配套课程（生态 ↔ 课程交叉销售）
$relatedCourses = [];
try {
    $shopCfg = shop_settings();
    $allCourses = array_values(array_filter(json_read(DATA_DIR . '/courses/index.json'), fn($c) => ($c['status'] ?? '') === 'published' && (float)($shopCfg['course_prices'][$c['id']] ?? 0) > 0));
    $kws = array_filter(preg_split('/[\s,，、\/]+/', ($asset['title'] ?? '') . ' ' . implode(' ', $asset['tags'] ?? [])), fn($w) => mb_strlen($w) >= 2);
    foreach ($allCourses as $ac) {
        $hay = ($ac['title'] ?? '') . ' ' . implode(' ', $ac['tags'] ?? []) . ' ' . ($ac['description'] ?? '');
        foreach ($kws as $kw) { if (mb_strpos($hay, $kw) !== false) { $relatedCourses[] = $ac; break; } }
        if (count($relatedCourses) >= 3) break;
    }
    if (empty($relatedCourses)) $relatedCourses = array_slice($allCourses, 0, 3);
} catch (Exception $e) {}

$typeLabel = ['skill' => 'Skill', 'plugin' => '插件', 'theme' => '主题'][$type] ?? $type;
$stepCount = count($asset['steps'] ?? []);
$typeIcon = ['skill' => '<path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/>', 'plugin' => '<path d="M9 3v3M15 3v3M8 6h8a2 2 0 0 1 2 2v4a6 6 0 0 1-12 0V8a2 2 0 0 1 2-2ZM12 18v3"/>', 'theme' => '<circle cx="12" cy="12" r="9"/><circle cx="8.5" cy="10" r="1.2" fill="currentColor"/><circle cx="12" cy="7.5" r="1.2" fill="currentColor"/><circle cx="15.5" cy="10" r="1.2" fill="currentColor"/><path d="M12 12.5a2.5 2.5 0 0 0 0 5h1.5a1.5 1.5 0 0 1 0 3"/>'][$type] ?? '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>';
$svgOf = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
$emojiOrSvg = fn(string $e, string $fallback) => (preg_match('/^[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]+$/u', $e) || $e === '') ? $svgOf($fallback) : htmlspecialchars($e);
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<meta name="description" content="<?=htmlspecialchars($desc)?>">
<meta name="robots" content="index,follow">
<meta property="og:type" content="product">
<meta property="og:title" content="<?=htmlspecialchars($asset['title'] ?? '')?>">
<meta property="og:description" content="<?=htmlspecialchars($desc)?>">
<script type="application/ld+json">
<?=json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $asset['title'] ?? '',
    'description' => $desc,
    'brand' => ['@type' => 'Brand', 'name' => $author],
    'category' => $typeLabel,
    'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => $asset['rating'] ?? 0, 'ratingCount' => $asset['rating_count'] ?? 0],
    'offers' => ['@type' => 'Offer', 'price' => $price, 'priceCurrency' => 'CNY', 'availability' => $price > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OnlineOnly'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)?>
</script>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 资产详情独有：详情头、步骤条、主题色预览。与 marketplace.php 的详情视图同一套命名。 */
.dt-head{display:grid;grid-template-columns:76px minmax(0,1fr) auto;gap:22px;align-items:start}
.dt-head .em{width:76px;height:76px;border-radius:var(--r-md);background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:34px}
.dt-head .em svg{width:32px;height:32px}
.dt-head h1{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;line-height:1.25}
.dt-head .row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}
.dt-head .by{font-family:var(--font-mono);font-size:12.5px;color:var(--faint);margin-top:10px;display:flex;gap:12px;flex-wrap:wrap}
.dt-side{display:flex;flex-direction:column;gap:8px;min-width:150px;align-items:stretch}
.dt-side .price{font-family:var(--font-display);font-size:26px;font-weight:700;color:var(--ok);text-align:center}
.step-item{border-left:3px solid var(--accent);padding:10px 16px;background:var(--bg-soft);border-radius:0 10px 10px 0}
.step-item b{font-size:14.5px}
.step-item p{font-size:13.5px;color:var(--muted);margin-top:4px;line-height:1.7}
.steps{display:flex;flex-direction:column;gap:8px}
.kv{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;font-size:13.5px}
.kv span{color:var(--faint)}
.theme-prev{height:180px;border-radius:var(--r-md);border:1px solid var(--border)}
.aside-box .link-grid{grid-template-columns:1fr;gap:2px}
.aside-box .link-it{padding:10px 8px}
.aside-box .link-it .ic{font-size:18px}
@media (max-width:860px){.dt-head{grid-template-columns:1fr}.dt-side{min-width:0}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('marketplace'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="asset-head">
    <div class="actions"><a href="/marketplace" class="act">← 生态市场</a><span class="act" style="pointer-events:none;color:var(--faint)"><?=$typeLabel?></span></div>
    <div class="card dt-head">
      <div class="em"><?=$emojiOrSvg($icon, $typeIcon)?></div>
      <div>
        <h1><?=htmlspecialchars($asset['title'] ?? $asset['name'] ?? $id)?></h1>
        <div class="row">
          <span class="badge ok"><?=$typeLabel?></span>
          <?php if ($price > 0): ?><span class="pill hl">¥<?=$price?></span><?php else: ?><span class="pill neutral">免费</span><?php endif; ?>
          <?php if ($owned): ?><span class="badge ok"><span class="dot"></span>已拥有</span><?php endif; ?>
        </div>
        <div class="by"><span><?=htmlspecialchars($author)?></span><span>v<?=htmlspecialchars($asset['version'] ?? '1.0.0')?></span><span><?=(int)($asset['installs'] ?? $asset['sales_count'] ?? 0)?> 安装</span><span>★ <?=$asset['rating'] ?? 0?></span></div>
      </div>
      <div class="dt-side">
        <?php if ($product && $price > 0): ?>
          <span class="price">¥<?=$price?></span>
          <?php if ($owned): ?>
            <button type="button" class="btn ghost" onclick="installAsset()">已拥有 · 安装</button>
          <?php else: ?>
            <button type="button" class="btn primary" onclick="purchase()">购买 ¥<?=$price?></button>
            <button type="button" class="btn ghost" onclick="installAsset()">安装</button>
          <?php endif; ?>
        <?php else: ?>
          <button type="button" class="btn primary" onclick="installAsset()">免费安装</button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section id="detail" class="sec reveal" data-od-anchor data-od-id="asset-body">
    <div class="g-main-aside">
      <div>
        <div class="card">
          <div class="sec-head" style="gap:10px;margin-bottom:14px"><span class="kicker">ABOUT</span><h2 style="font-size:20px">介绍</h2></div>
          <div class="prose" style="font-size:14.5px"><?=nl2br(htmlspecialchars($asset['description'] ?? '暂无描述'))?></div>
        </div>

        <?php if ($type === 'skill' && !empty($asset['steps'])): ?>
        <div class="card">
          <div class="sec-head" style="gap:10px;margin-bottom:14px"><span class="kicker">STEPS</span><h2 style="font-size:20px">功能步骤（<?=$stepCount?>）</h2></div>
          <div class="steps">
            <?php foreach ($asset['steps'] as $si => $st): ?>
            <div class="step-item"><b>Step <?=$si + 1?>：<?=htmlspecialchars($st['title'] ?? '')?></b><?php if (!empty($st['desc'])): ?><p><?=htmlspecialchars($st['desc'])?></p><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($type === 'theme'): ?>
        <div class="card">
          <div class="sec-head" style="gap:10px;margin-bottom:14px"><span class="kicker">PREVIEW</span><h2 style="font-size:20px">主题预览</h2></div>
          <div class="theme-prev" style="background:linear-gradient(135deg,<?=htmlspecialchars($asset['primary_color'] ?? 'var(--accent)')?>,<?=htmlspecialchars($asset['accent_color'] ?? 'var(--accent-soft)')?>)"></div>
          <p class="note">主题色预览 · 实际效果以安装后为准</p>
        </div>
        <?php endif; ?>
      </div>

      <aside>
        <div class="aside-box">
          <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v6M8.5 8.5 12 12l3.5-3.5M4 14h16v6H4z"/></svg></span>资产信息</h3>
          <div class="kv">
            <span>类型</span><div><?=$typeLabel?></div>
            <span>作者</span><div><?=htmlspecialchars($author)?></div>
            <span>版本</span><div class="mono">v<?=htmlspecialchars($asset['version'] ?? '1.0.0')?></div>
            <span>安装量</span><div class="mono"><?=(int)($asset['installs'] ?? $asset['sales_count'] ?? 0)?></div>
          </div>
          <?php if (!empty($asset['tags'])): ?><div class="tags"><?php foreach (array_slice($asset['tags'], 0, 5) as $t): ?><span>#<?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
        </div>

        <?php if ($related): ?>
        <div class="aside-box">
          <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span>相关推荐</h3>
          <div class="link-grid">
            <?php foreach ($related as $ra): $rt = $ra['type'] === 'skill' ? 'Skill' : ($ra['type'] === 'plugin' ? '插件' : '主题'); ?>
            <a class="link-it" href="/<?=urlencode($ra['type'])?>/<?=urlencode($ra['id'])?>"><span class="ic"><?=$emojiOrSvg((string)($ra['icon'] ?? ''), $ra['type'] === 'skill' ? '<path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/>' : '<path d="M9 3v3M15 3v3M8 6h8a2 2 0 0 1 2 2v4a6 6 0 0 1-12 0V8a2 2 0 0 1 2-2ZM12 18v3"/>')?></span><span class="lt"><b><?=htmlspecialchars($ra['title'] ?? $ra['name'] ?? '')?></b><span><?=$rt?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($relatedCourses)): ?>
        <div class="aside-box">
          <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span>配套课程</h3>
          <div class="link-grid">
            <?php foreach ($relatedCourses as $rc): $rcPrice = (float)($shopCfg['course_prices'][$rc['id']] ?? 0); ?>
            <a class="link-it" href="/courses/<?=urlencode($rc['id'])?>?id=<?=urlencode($rc['id'])?>"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span><span class="lt"><b><?=htmlspecialchars($rc['title'] ?? '')?></b><span><?=htmlspecialchars($rc['type'] ?? '课程')?> · <?=count($rc['chapters'] ?? [])?> 章 · ¥<?=number_format($rcPrice,0)?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </aside>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
var ASSET_TYPE = <?=json_encode($type)?>;
var ASSET_ID = <?=json_encode($id)?>;
function purchase() {
  <?php if (!$member): ?>location.href = '/account?view=login&next=/' + ASSET_TYPE + '/' + encodeURIComponent(ASSET_ID); return;<?php endif; ?>
  var body = new FormData(); body.append('skill_id', ASSET_ID);
  fetch('/api/marketplace?action=purchase', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok && d.payment && d.payment.ok) {
        var f = document.createElement('form'); f.method = 'post'; f.action = d.payment.gateway;
        Object.keys(d.payment.params).forEach(function(k){ var i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=d.payment.params[k]; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
      } else if (d.ok && d.already_purchased) { alert('✅ 你已拥有，可直接安装'); location.reload(); }
      else alert(d.error || '购买失败');
    }).catch(function(){ alert('网络异常'); });
}
function installAsset() {
  var body = new FormData(); body.append('skill_id', ASSET_ID);
  fetch('/api/marketplace?action=install', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) { alert('✅ 已安装'); location.reload(); }
      else if (d.need_purchase) { if (confirm('该资产需付费，是否前往购买？')) purchase(); }
      else alert(d.error || '安装失败');
    });
}
</script>
</body>
</html>
