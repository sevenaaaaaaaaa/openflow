<?php
/**
 * 统一市场 — 插件 / 技能 / 主题 浏览与安装
 * 前台的「生态商店」：开发者发布资产，用户浏览/安装/打分
 *
 * v7（2026-09-01）：从 tailwind + 行内样式迁到共享 archetype。三视图（市场 / Skill 详情 / 插件详情）
 * 的数据、安装 / 购买 / 复制脚本与评论部件调用原样保留，只换渲染层。文案逐字相同。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('marketplace', 1800)) exit;
require_once __DIR__ . '/lib/MarketplaceSystem.php';
require_once __DIR__ . '/lib/SkillSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/comment-widget.php';

$member = member_current();
$view = req_str('view', 'market');
$categories = mkt_categories();

// Skill 详情页
$skill = null;
if ($view === 'skill') { $gid = req_str('id'); if ($gid !== '') $skill = skill_get($gid); }

// 插件详情页（从 PluginSystem 读取）
$plugin = null;
if ($view === 'plugin') { $gid = req_str('id'); if ($gid !== '') {
    $plugin = null;
    foreach (mkt_assets() as $a) {
        if ($a['type'] === 'plugin' && $a['id'] === $gid) { $plugin = $a; break; }
    }
}
}

// 搜索/筛选
$q = req_str('q');
$type = req_str('type', 'all');
$sort = req_str('sort', 'popular');
$assets = mkt_search($q, $type, $sort);
$stats = mkt_stats();
$typeNames = mkt_categories();
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>生态市场 | <?=site_config_get("site_name")?></title>
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902b">
<style>
/* 生态市场独有：资产卡、详情头、提示词块。其余全部来自 modules.css。 */
.search{display:flex;gap:10px;max-width:520px}
.search .inp{border-radius:999px;padding-left:20px}
.search .btn{border-radius:999px;flex:0 0 auto}
.mk-grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.mk .cov{display:block}
.mk .tag-r{position:absolute;right:12px;bottom:12px;z-index:1}
.mk .bd p{font-size:13.5px;color:var(--muted);line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.mk .meta{align-items:center}
.scn-f .gcov{aspect-ratio:2.4/1}
.filters{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.filters .tab-bar{border-bottom:none;padding-bottom:0;justify-content:flex-start;flex:1}
.filters .sort{display:flex;gap:6px;margin-left:auto}
/* 详情 */
.dt-head{display:grid;grid-template-columns:76px minmax(0,1fr) auto;gap:22px;align-items:start}
.dt-head .em{width:76px;height:76px;border-radius:var(--r-md);background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:34px}
.dt-head .em svg{width:32px;height:32px}
.dt-head h1{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;line-height:1.25}
.dt-head .row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}
.dt-head .desc{font-size:15px;color:var(--muted);line-height:1.8;margin-top:12px}
.dt-side{display:flex;flex-direction:column;align-items:center;gap:8px;min-width:150px;text-align:center}
.dt-side .big{font-family:var(--font-display);font-size:30px;font-weight:700;color:var(--warn)}
.dt-side .stars{color:var(--warn);letter-spacing:.12em}
.dt-side .price{font-family:var(--font-display);font-size:24px;font-weight:700;color:var(--ok)}
.prompt{background:var(--fg);color:var(--bg);padding:20px 22px;border-radius:var(--r-md);font-family:var(--font-mono);font-size:13px;line-height:1.75;white-space:pre-wrap;word-break:break-word}
.back{display:inline-flex;align-items:center;gap:6px;font-size:13.5px;font-weight:600;color:var(--accent)}
@media (max-width:860px){.dt-head{grid-template-columns:1fr}.dt-side{align-items:flex-start;text-align:left}.filters .sort{margin-left:0}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('marketplace'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

<?php if ($view === 'skill' && $skill): ?>
  <!-- ═══ Skill 详情 ═══ -->
  <section class="reveal in" data-od-id="mkt-skill">
    <a class="back" href="/marketplace">← 返回市场</a>
    <div class="card" style="margin-top:16px">
      <div class="dt-head">
        <div class="em"><?=mkt_type_icon('skill')?></div>
        <div>
          <h1><?=htmlspecialchars($skill['title'] ?? '')?></h1>
          <div class="row">
            <span class="pill neutral"><?=$skillTypes['prompt']['name'] ?? ''?><?=htmlspecialchars($skill['type'] ?? '')?></span>
            <span class="badge ok"><?=htmlspecialchars($skill['author'] ?? 'OpenFlow')?></span>
            <span class="note">v<?=htmlspecialchars($skill['version'] ?? '1.0.0')?></span>
            <span class="note"><?=($skill['installs'] ?? 0)?> 次安装</span>
          </div>
          <p class="desc"><?=htmlspecialchars($skill['description'] ?? '')?></p>
          <div class="tags" style="margin-top:12px"><?php foreach ($skill['tags'] ?? [] as $t): ?><span># <?=htmlspecialchars($t)?></span><?php endforeach; ?></div>
        </div>
        <div class="dt-side">
          <?php if (($skill['rating_count'] ?? 0) > 0): ?>
          <div class="big"><?=number_format((float)$skill['rating'], 1)?></div>
          <div class="stars"><?=str_repeat('★', max(0, min(5, (int)round((float)$skill['rating']))))?><?=str_repeat('☆', max(0, 5 - (int)round((float)$skill['rating'])))?></div>
          <div class="note"><?=$skill['rating_count']?> 人评分</div>
          <?php endif; ?>
          <?php $skillPrice = (float)($skill['price'] ?? 0); $skillOwned = !empty($member) && in_array($skill['id'] ?? '', $member['purchased_skills'] ?? []); ?>
          <?php if ($skillPrice > 0 && !$skillOwned): ?>
          <div class="price">¥<?=$skillPrice?></div>
          <button onclick="purchaseSkill('<?=htmlspecialchars($skill['id'])?>')" class="btn primary">购买此技能</button>
          <?php else: ?>
          <button onclick="installSkill('<?=htmlspecialchars($skill['id'])?>')" class="btn primary"><span class="ic" style="width:16px;height:16px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>安装此技能</button>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($skill['type'] === 'prompt' && !empty($skill['content'])): ?>
      <div style="margin-top:28px">
        <div class="kicker" style="margin-bottom:12px">提示词内容</div>
        <pre class="prompt"><?=htmlspecialchars($skill['content'])?></pre>
        <div class="cta-row" style="margin-top:14px"><button onclick="copySkillPrompt()" class="btn ghost">复制提示词</button></div>
      </div>
      <?php endif; ?>
      <?php if ($skill['type'] === 'workflow' && !empty($skill['steps'])): ?>
      <div style="margin-top:28px">
        <div class="kicker" style="margin-bottom:6px">工作流步骤</div>
        <div class="tp-steps" style="border-left:none;padding-left:0">
          <?php foreach ($skill['steps'] as $i => $st): ?>
          <div class="tp-step"><span class="tp-n">0<?=$i+1?></span><div><b><?=htmlspecialchars($st['title'] ?? '')?></b><span><?=htmlspecialchars($st['desc'] ?? '')?></span></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <section class="reveal in" data-od-id="mkt-skill-comments"><?php fc_comment_widget('skill', $skill['id'], ['title' => '评价', 'rating' => true]); ?></section>

<?php elseif ($view === 'plugin' && $plugin): ?>
  <!-- ═══ 插件详情 ═══ -->
  <section class="reveal in" data-od-id="mkt-plugin">
    <a class="back" href="/marketplace">← 返回市场</a>
    <div class="card" style="margin-top:16px">
      <div class="dt-head">
        <div class="em"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></div>
        <div>
          <h1><?=htmlspecialchars($plugin['title'] ?? '')?></h1>
          <div class="row">
            <span class="pill neutral">插件</span>
            <span class="badge ok"><?=htmlspecialchars($plugin['author'] ?? 'OpenFlow')?></span>
            <span class="note">v<?=htmlspecialchars($plugin['version'] ?? '1.0.0')?></span>
          </div>
          <p class="desc"><?=htmlspecialchars($plugin['description'] ?? '')?></p>
          <?php $pr = comment_rating_summary('plugin', $plugin['id']); if ($pr['count'] > 0): ?>
          <div class="row"><span class="dt-side stars" style="min-width:0"><?=str_repeat('★', max(0, min(5, (int)round($pr['avg']))))?><?=str_repeat('☆', max(0, 5 - (int)round($pr['avg'])))?></span><b style="color:var(--warn)"><?=number_format($pr['avg'], 1)?></b><span class="note"><?=$pr['count']?> 人评分</span></div>
          <?php endif; ?>
        </div>
        <div class="dt-side">
          <span class="badge ok"><span class="dot"></span>已安装</span>
          <a href="/xmp/plugins" class="btn primary">去后台管理</a>
        </div>
      </div>
    </div>
  </section>
  <section class="reveal in" data-od-id="mkt-plugin-comments"><?php fc_comment_widget('plugin', $plugin['id'], ['title' => '评价', 'rating' => true]); ?></section>

<?php else: ?>
  <!-- ═══ 市场首页 ═══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="mkt-hero">
    <div class="hero">
      <div class="hero-copy">
        <span class="kicker">OPEN ECO · 生态市场</span>
        <h1>别人做好的增长能力，<br><i class="si">装上就能用</i></h1>
        <p class="lead">Skill · 插件 · 主题，一站式扩展你的 <?=site_config_get("site_name")?>。开发者可发布，用户可安装，构建共创生态。</p>
        <form method="get" class="search" role="search">
          <input class="inp" type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="搜索插件、技能、主题…" aria-label="搜索">
          <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
          <input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>">
          <button class="btn primary" type="submit">搜索</button>
        </form>
        <div class="trust"><span class="dot"></span><?=$stats['total']?> 个资产 · <?=$stats['plugins']?> 个插件 · <?=$stats['themes']?> 个主题 · 核心能力永久开源</div>
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">marketplace · 按类型进入</div></div>
        <div class="win-flow">
          <?php $entries = [['skill','Skill 技能','开箱即用的增长能力：AI 指令 / 工具 / 工作流'],['plugin','插件','扩展后台功能的 PHP 模块，装完即生效'],['theme','主题','前台视觉与布局，一键切换'],['bundle','组合包','多个产品打包，一次装齐']]; foreach ($entries as $k => $e): if ($k) echo '<div class="flow-link"></div>'; ?>
          <a class="flow-row" href="?type=<?=$e[0]?>"><span class="fi"><?=mkt_type_icon($e[0])?></span><div><div class="ft"><?=$e[1]?></div><div class="fd"><?=$e[2]?></div></div></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <?php
  // 编辑精选：安装量最高的 4 个（全量，不受筛选影响）；只在默认视图（无搜索 / 无类型筛选）展示
  $picks = [];
  if ($q === '' && $type === 'all') { $picks = mkt_search('', 'all', 'popular'); $picks = array_slice($picks, 0, 4); }
  if (count($picks) >= 2): $lead = $picks[0]; $rest = array_slice($picks, 1, 3); ?>
  <section id="picks" class="sec reveal" data-od-anchor data-od-id="mkt-picks">
    <div class="sec-head row"><div><span class="kicker">EDITOR'S PICKS</span><h2>装得最多的</h2></div><span class="sub">按安装量</span></div>
    <div class="scn">
      <a class="scn-f" href="<?=htmlspecialchars($lead['url'])?>" style="padding:0;overflow:hidden;display:flex;flex-direction:column">
        <?=mkt_asset_cover($lead, $typeNames)?>
        <div style="padding:22px 24px;display:flex;flex-direction:column;gap:8px;flex:1">
          <span class="f-tag"><?=$typeNames[$lead['type']]['name'] ?? $lead['type']?> · <?=$lead['installs'] ?? 0?> 次安装</span>
          <h3 style="font-size:20px;font-weight:800;letter-spacing:-.01em"><?=htmlspecialchars($lead['title'])?></h3>
          <p style="font-size:14.5px;color:var(--muted);line-height:1.8"><?=htmlspecialchars($lead['description'] ?? '')?></p>
          <span class="note mono" style="margin-top:auto"><?=htmlspecialchars($lead['author'] ?? '')?></span>
        </div>
      </a>
      <div class="scn-s">
        <?php foreach ($rest as $r): ?>
        <a class="scn-row" href="<?=htmlspecialchars($r['url'])?>">
          <span class="ic" style="color:var(--<?=mkt_type_meta($r['type'])['hue']==='neutral'?'muted':mkt_type_meta($r['type'])['hue']?>)"><?=mkt_type_icon($r['type'])?></span>
          <div><b><?=htmlspecialchars($r['title'])?></b><span><?=htmlspecialchars(mb_strimwidth($r['description'] ?? '', 0, 60, '…'))?></span></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section id="browse" class="sec reveal" data-od-anchor data-od-id="mkt-browse">
    <div class="sec-head row"><div><span class="kicker">BROWSE</span><h2><?=$q !== '' ? '搜索「' . htmlspecialchars($q) . '」' : ($type === 'all' ? '全部资产' : ($typeNames[$type]['name'] ?? $type))?></h2></div><span class="sub"><?=count($assets)?> 个<?php if ($q !== '' || $type !== 'all'): ?> · <a href="/marketplace" style="color:var(--accent)">清除筛选</a><?php endif; ?></span></div>
    <div class="filters">
      <div class="tab-bar dense" role="navigation" aria-label="类型">
        <a class="tab-p" href="?type=all&sort=<?=$sort?>" aria-selected="<?=$type==='all'?'true':'false'?>">全部</a>
        <?php foreach ($categories as $tk => $tc): ?>
        <a class="tab-p" href="?type=<?=$tk?>&sort=<?=$sort?>" aria-selected="<?=$type===$tk?'true':'false'?>"><span class="ic"><?=mkt_type_icon($tk)?></span><?=$tc['name']?></a>
        <?php endforeach; ?>
      </div>
      <div class="sort">
        <?php foreach (['popular'=>'热门','rating'=>'评分','new'=>'最新'] as $sk=>$sv): ?>
        <a class="pill <?=$sort===$sk?'hl':'neutral'?>" href="?type=<?=$type?>&sort=<?=$sk?><?=$q!==''?'&q='.urlencode($q):''?>"><?=$sv?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($assets)): ?>
    <div class="empty">暂无匹配资产</div>
    <?php else: ?>
    <div class="a-grid mk-grid">
      <?php foreach ($assets as $a): $tm = mkt_type_meta($a['type'] ?? ''); $price = (float)($a['price'] ?? 0); ?>
      <article class="a-card mk" data-od-id="mkt-asset-<?=htmlspecialchars($a['id'] ?? '')?>">
        <a href="<?=htmlspecialchars($a['url'])?>" class="cov"><?=mkt_asset_cover($a, $typeNames)?><?php if (!empty($a['installed'])): ?><span class="badge ok tag-r">已安装</span><?php elseif ($price <= 0): ?><span class="pill neutral tag-r">免费</span><?php else: ?><span class="pill hl tag-r">¥<?=number_format($price,0)?></span><?php endif; ?></a>
        <div class="bd">
          <span class="cat" style="color:var(--<?=$tm['hue']==='neutral'?'muted':$tm['hue']?>)"><?=htmlspecialchars($a['author'] ?? 'OpenFlow')?><?=($a['author_type'] ?? '') === 'user' ? ' · 用户发布' : ''?><?=($a['source'] ?? '') === 'remote' ? ' · 远程' : ''?></span>
          <h3><a href="<?=htmlspecialchars($a['url'])?>" style="color:inherit"><?=htmlspecialchars($a['title'])?></a></h3>
          <p><?=htmlspecialchars($a['description'] ?? '')?></p>
          <?php if (($a['type'] ?? '') === 'bundle'): ?><span class="note" style="color:var(--accent);margin:0">包含 <?=$a['items_count'] ?? 0?> 个产品</span><?php endif; ?>
          <div class="meta">
            <?php if (($a['rating_count'] ?? 0) > 0): ?><span style="color:var(--warn)">★ <?=number_format((float)$a['rating'], 1)?></span><span>·</span><?php endif; ?>
            <span><?=$a['installs'] ?? 0?> 次安装</span>
            <?php if (in_array($a['type'] ?? '', ['bundle','membership']) && $price > 0): ?><button type="button" onclick="purchaseSkill('<?=htmlspecialchars($a['id'])?>')" class="btn primary" style="margin-left:auto;height:34px;padding:0 14px;font-size:12.5px"><?=$a['type']==='membership'?'开通会员':'购买'?> →</button>
            <?php else: ?><a href="<?=htmlspecialchars($a['url'])?>" class="go" style="margin-left:auto;color:var(--accent);font-weight:600;font-family:var(--font-body)">查看 →</a><?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section id="how" class="sec reveal" data-od-anchor data-od-id="mkt-how">
    <div class="sec-head center"><span class="kicker">HOW IT WORKS</span><h2>三步，把别人的能力变成你的</h2></div>
    <div class="wf">
      <div class="wf-step"><span class="wf-n">1</span><h3>找到</h3><p>按类型或关键词找到你要的 Skill / 插件 / 主题，看安装量与评分。</p></div>
      <div class="wf-step"><span class="wf-n">2</span><h3>一键安装</h3><p>登录后点「安装」，免费的直接进后台；付费的走虎皮椒支付，付完自动解锁。</p></div>
      <div class="wf-step"><span class="wf-n">3</span><h3>在后台启用</h3><p>插件在「插件」里开关，Skill 在 Agent 工作流里调用，主题在「外观」里切换。装完就能改，改坏了一键停用。</p></div>
    </div>
  </section>

  <section id="publish" class="reveal" data-od-anchor data-od-id="mkt-publish">
    <div class="cta-band">
      <span class="kicker">OIA · One is All</span>
      <h2>想发布你的插件 / 技能？</h2>
      <p class="lead">你一旦参与，就同时是开发者、创作者、作者。不用先申请资质、不用先会写代码——用 AI 生成插件骨架，或直接发布一个可复用的 Skill，分享给整个社区。</p>
      <div class="cta-row"><a href="/account?view=developer" class="btn primary">开始创作 →</a><a href="/docs" class="btn ghost">阅读开发文档</a></div>
    </div>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
var MEMBER = <?=json_encode($member ? ['id' => $member['id']] : null)?>;
function installSkill(id) {
  if (!MEMBER) { location.href = '/account?view=login&next=/marketplace?view=skill&id=' + encodeURIComponent(id); return; }
  var body = new FormData(); body.append('skill_id', id);
  fetch('/api/marketplace?action=install', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) { alert('✅ 技能已安装'); location.reload(); }
      else if (d.need_purchase) { if (confirm('该技能需 ¥' + d.price + ' 购买，是否前往购买？')) purchaseSkill(id); }
      else alert(d.error || '安装失败');
    });
}
function purchaseSkill(id) {
  if (!MEMBER) { location.href = '/account?view=login&next=/marketplace?view=skill&id=' + encodeURIComponent(id); return; }
  var body = new FormData(); body.append('skill_id', id);
  fetch('/api/marketplace?action=purchase', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok && d.payment && d.payment.ok) {
        var f = document.createElement('form'); f.method = 'post'; f.action = d.payment.gateway;
        Object.keys(d.payment.params).forEach(function(k){ var i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=d.payment.params[k]; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
      } else if (d.ok && d.already_purchased) { alert('✅ 你已购买，可直接安装'); location.reload(); }
      else alert(d.error || '购买失败');
    })
    .catch(function(){ alert('网络异常'); });
}
function copySkillPrompt() {
  var pre = document.querySelector('pre');
  navigator.clipboard.writeText(pre.textContent).then(function(){ alert('已复制提示词'); });
}
</script>
</body>
</html>
<?php PageCache::end('marketplace', 1800); ?>
