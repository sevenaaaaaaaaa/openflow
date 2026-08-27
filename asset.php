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
        $asset['icon'] = $asset['icon'] ?? '⚡';
        $asset['version'] = $asset['version'] ?? '1.0.0';
    }
} elseif ($type === 'plugin') {
    foreach (mkt_assets() as $a) if ($a['type'] === 'plugin' && $a['id'] === $id) $asset = $a;
} elseif ($type === 'theme') {
    $themes = json_read(DATA_DIR . '/themes.json');
    foreach (($themes['themes'] ?? []) as $t) if ($t['id'] === $id) {
        $asset = $t;
        $asset['type'] = 'theme';
        $asset['icon'] = '🎨';
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
$icon = $asset['icon'] ?? '⚡';
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<style>
body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
.dcard{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px}
.buy-btn{background:var(--accent);color:var(--on-accent);font-weight:700;padding:12px 24px;border-radius:12px;border:none;cursor:pointer;font-size:15px}
.ghost-btn{background:var(--surface);border:1px solid var(--border);color:var(--muted);font-weight:600;padding:12px 24px;border-radius:12px;cursor:pointer}
.step-item{border-left:3px solid var(--accent);padding:8px 14px;background:var(--bg-soft);border-radius:0 8px 8px 0;margin-bottom:8px}
.rel-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;transition:.15s;display:block;text-decoration:none;color:inherit}
.rel-card:hover{box-shadow:0 8px 20px rgba(30,30,30,.08)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="home"></script>

<div class="mx-auto px-5 py-8" style="max-width:1100px">
  <!-- 面包屑 -->
  <div class="text-sm text-gray-600 mb-4">
    <a href="/marketplace" class="hover:underline">生态市场</a> / <span><?=$typeLabel?></span>
  </div>

  <!-- 头部 -->
  <div class="dcard mb-6">
    <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
      <div style="width:88px;height:88px;border-radius:20px;background:linear-gradient(135deg,#7dd3fc,#86efac);display:grid;place-items:center;font-size:40px"><?=htmlspecialchars($icon)?></div>
      <div style="flex:1;min-width:260px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <h1 class="text-2xl font-extrabold"><?=htmlspecialchars($asset['title'] ?? $asset['name'] ?? $id)?></h1>
          <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" style="background:var(--ok-soft);color:var(--ok)"><?=$typeLabel?></span>
          <?php if ($price > 0): ?><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" style="background:var(--accent);color:var(--on-accent)">¥<?=$price?></span>
          <?php else: ?><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" style="background:var(--bg);color:var(--ok)">免费</span><?php endif; ?>
        </div>
        <div class="text-sm text-gray-600 mt-2">👤 <?=htmlspecialchars($author)?> · 📦 v<?=htmlspecialchars($asset['version'] ?? '1.0.0')?> · ⬇ <?=(int)($asset['installs'] ?? $asset['sales_count'] ?? 0)?> 安装 · ⭐ <?=$asset['rating'] ?? 0?></div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($product && $price > 0): ?>
          <?php if ($owned): ?>
            <button class="ghost-btn" onclick="installAsset()">✅ 已拥有 · 安装</button>
          <?php else: ?>
            <button class="buy-btn" onclick="purchase()">🛒 购买 ¥<?=$price?></button>
            <button class="ghost-btn" onclick="installAsset()">安装</button>
          <?php endif; ?>
        <?php else: ?>
          <button class="buy-btn" onclick="installAsset()">⚡ 免费安装</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="display:grid;gap:20px" class="lg:grid-cols-3">
    <!-- 主内容 -->
    <div class="lg:col-span-2">
      <div class="dcard mb-6">
        <h2 class="text-lg font-extrabold mb-3">📝 介绍</h2>
        <div class="text-[14px] leading-relaxed text-gray-600" style="line-height:1.9"><?=nl2br(htmlspecialchars($asset['description'] ?? '暂无描述'))?></div>
      </div>

      <?php if ($type === 'skill' && !empty($asset['steps'])): ?>
      <div class="dcard mb-6">
        <h2 class="text-lg font-extrabold mb-3">🛠 功能步骤（<?=$stepCount?>）</h2>
        <?php foreach ($asset['steps'] as $si => $st): ?>
        <div class="step-item">
          <b>Step <?=$si + 1?>：<?=htmlspecialchars($st['title'] ?? '')?></b>
          <?php if (!empty($st['desc'])): ?><div class="text-sm text-gray-600 mt-1"><?=htmlspecialchars($st['desc'])?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($type === 'theme'): ?>
      <div class="dcard mb-6">
        <h2 class="text-lg font-extrabold mb-3">🎨 主题预览</h2>
        <div style="height:200px;border-radius:12px;background:linear-gradient(135deg,<?=htmlspecialchars($asset['primary_color'] ?? 'var(--accent)')?>,<?=htmlspecialchars($asset['accent_color'] ?? 'var(--on-accent)')?>)"></div>
        <div class="text-sm text-gray-600 mt-3">主题色预览 · 实际效果以安装后为准</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 侧栏 -->
    <div>
      <div class="dcard mb-6">
        <h3 class="font-extrabold mb-3">📌 资产信息</h3>
        <div class="text-sm text-gray-600 leading-8">
          <div>类型：<?=$typeLabel?></div>
          <div>作者：<?=htmlspecialchars($author)?></div>
          <div>版本：v<?=htmlspecialchars($asset['version'] ?? '1.0.0')?></div>
          <div>安装量：<?=(int)($asset['installs'] ?? $asset['sales_count'] ?? 0)?></div>
          <?php if (!empty($asset['tags'])): ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px"><?php foreach (array_slice($asset['tags'], 0, 5) as $t): ?><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs" style="background:var(--bg);color:var(--muted)">#<?=htmlspecialchars($t)?></span><?php endforeach; ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="dcard mb-6">
        <h3 class="font-extrabold mb-3">🕹 相关推荐</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
          <?php foreach ($related as $ra): ?>
          <a class="rel-card" href="/<?=urlencode($ra['type'])?>/<?=urlencode($ra['id'])?>">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="font-size:24px"><?=htmlspecialchars($ra['icon'] ?? '📦')?></span>
              <div>
                <div class="font-bold text-[14px]"><?=htmlspecialchars($ra['title'] ?? $ra['name'] ?? '')?></div>
                <div class="text-xs text-gray-600"><?=$ra['type'] === 'skill' ? 'Skill' : ($ra['type'] === 'plugin' ? '插件' : '主题')?></div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!empty($relatedCourses)): ?>
      <div class="dcard mb-6">
        <h3 class="font-extrabold mb-3">🎓 配套课程</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
          <?php foreach ($relatedCourses as $rc): $rcPrice = (float)($shopCfg['course_prices'][$rc['id']] ?? 0); ?>
          <a class="rel-card" href="/courses/<?=urlencode($rc['id'])?>?id=<?=urlencode($rc['id'])?>">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="font-size:24px">🎓</span>
              <div style="flex:1;min-width:0">
                <div class="font-bold text-[14px]"><?=htmlspecialchars($rc['title'] ?? '')?></div>
                <div class="text-xs text-gray-600"><?=htmlspecialchars($rc['type'] ?? '课程')?> · <?=count($rc['chapters'] ?? [])?> 章 · ¥<?=number_format($rcPrice,0)?></div>
              </div>
              <span style="font-size:12px;color:var(--accent)">→</span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
    <div class="mb-2"><?=htmlspecialchars($siteName)?> · 生态市场</div>
    <div class="flex gap-6 justify-center mb-3 text-xs">
      <a href="/marketplace" class="text-white/50 hover:text-white transition">生态市场</a>
      <a href="/tools" class="text-white/50 hover:text-white transition">工具箱</a>
      <a href="/docs" class="text-white/50 hover:text-white transition">文档</a>
    </div>
  </div>
</footer>

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
