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
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260901a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260901a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260901a">
<style>
/* 生态市场独有：资产卡、详情头、提示词块。其余全部来自 modules.css。 */
.search{display:flex;gap:10px;max-width:520px}
.search .inp{border-radius:999px;padding-left:20px}
.search .btn{border-radius:999px;flex:0 0 auto}
.mk-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}
.mk{display:flex;flex-direction:column;gap:10px;padding:22px 24px}
.mk .hd{display:flex;align-items:center;gap:12px}
.mk .em{width:44px;height:44px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:20px;flex:0 0 auto}
.mk .em svg{width:20px;height:20px}
.mk .ttl{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:15.5px;font-weight:700}
.mk .by{font-size:12px;color:var(--faint);font-family:var(--font-mono);margin-top:2px}
.mk p{font-size:13.5px;color:var(--muted);line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.mk .meta{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:auto;font-family:var(--font-mono);font-size:12px;color:var(--faint)}
.mk .meta .star{color:var(--warn)}
.mk .buy{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:12px;border-top:1px solid var(--border-soft)}
.mk .buy b{font-family:var(--font-display);font-size:18px;color:var(--ok)}
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
        <div class="em"><?=htmlspecialchars($skill['icon'] ?? '⚡')?></div>
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
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">marketplace · 四个入口</div></div>
        <div class="win-flow">
          <a class="flow-row" href="/category/marketplace/skills"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span><div><div class="ft">Skill 技能</div><div class="fd">开箱即用的增长能力</div></div></a>
          <div class="flow-link"></div>
          <a class="flow-row" href="/category/marketplace/plugins"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span><div><div class="ft">插件</div><div class="fd">扩展系统功能</div></div></a>
          <div class="flow-link"></div>
          <a class="flow-row" href="/category/marketplace/themes"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg></span><div><div class="ft">主题</div><div class="fd">视觉与布局</div></div></a>
          <div class="flow-link"></div>
          <a class="flow-row" href="/community"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span><div><div class="ft">论坛</div><div class="fd">社区问答与讨论</div></div></a>
        </div>
      </div>
    </div>
  </section>

  <section id="stats" class="sec reveal" data-od-anchor data-od-id="mkt-stats">
    <div class="stats">
      <div class="st"><div class="st-n"><?=$stats['total']?></div><span class="st-en">Assets</span><span class="st-t">全部资产</span></div>
      <div class="st"><div class="st-n"><?=$stats['plugins']?></div><span class="st-en">Plugins</span><span class="st-t">插件</span></div>
      <div class="st"><div class="st-n"><?=$stats['skills']?></div><span class="st-en">Skills</span><span class="st-t">技能</span></div>
      <div class="st"><div class="st-n"><?=$stats['themes']?></div><span class="st-en">Themes</span><span class="st-t">主题</span></div>
    </div>
  </section>

  <section id="browse" class="sec reveal" data-od-anchor data-od-id="mkt-browse">
    <div class="filters">
      <div class="tab-bar" role="navigation" aria-label="类型">
        <a class="tab-p" href="?type=all&sort=<?=$sort?>" aria-selected="<?=$type==='all'?'true':'false'?>">全部</a>
        <?php foreach ($categories as $tk => $tc): ?>
        <a class="tab-p" href="?type=<?=$tk?>&sort=<?=$sort?>" aria-selected="<?=$type===$tk?'true':'false'?>"><?=$tc['icon']?> <?=$tc['name']?></a>
        <?php endforeach; ?>
      </div>
      <div class="sort">
        <?php foreach (['popular'=>'热门','rating'=>'评分','new'=>'最新'] as $sk=>$sv): ?>
        <a class="pill <?=$sort===$sk?'hl':'neutral'?>" href="?type=<?=$type?>&sort=<?=$sk?>"><?=$sv?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($assets)): ?>
    <div class="empty">暂无匹配资产</div>
    <?php else: ?>
    <div class="mk-grid">
      <?php foreach ($assets as $a): ?>
      <div class="card mk" data-od-id="mkt-asset-<?=htmlspecialchars($a['id'] ?? '')?>">
        <a href="<?=htmlspecialchars($a['url'])?>" style="display:contents">
          <div class="hd">
            <span class="em"><?=htmlspecialchars($a['icon'])?></span>
            <div style="min-width:0">
              <div class="ttl"><span><?=htmlspecialchars($a['title'])?></span><span class="pill neutral" style="height:24px"><?=$typeNames[$a['type']]['name'] ?? $a['type']?></span></div>
              <div class="by"><?=htmlspecialchars($a['author'] ?? '')?><?=($a['author_type'] ?? '') === 'user' ? ' · 用户发布' : ''?><?=($a['source'] ?? '') === 'remote' ? ' · 远程' : ''?></div>
            </div>
          </div>
          <p><?=htmlspecialchars($a['description'] ?? '')?></p>
          <?php if (($a['type'] ?? '') === 'bundle'): ?><div class="note" style="color:var(--accent)">包含 <?=$a['items_count'] ?? 0?> 个产品</div><?php endif; ?>
          <div class="meta">
            <?php if (($a['rating_count'] ?? 0) > 0): ?><span class="star">★ <?=number_format((float)$a['rating'], 1)?></span><?php endif; ?>
            <span><?=$a['installs'] ?? 0?> 次安装</span>
            <?php if (($a['type'] ?? '') === 'skill' && (float)($a['price'] ?? 0) <= 0): ?><span class="badge ok">免费安装</span><?php endif; ?>
            <?php if (!empty($a['installed'])): ?><span class="badge ok" style="margin-left:auto">已安装</span><?php endif; ?>
          </div>
        </a>
        <?php if (in_array($a['type'] ?? '', ['bundle','membership']) && ($a['price'] ?? 0) > 0): ?>
        <div class="buy"><b>¥<?=number_format($a['price'],0)?></b><button onclick="purchaseSkill('<?=htmlspecialchars($a['id'])?>')" class="btn primary" style="height:40px;padding:0 18px;font-size:13.5px"><?=$a['type']==='membership'?'开通会员':'购买组合包'?> →</button></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section id="publish" class="reveal" data-od-anchor data-od-id="mkt-publish">
    <div class="cta-band">
      <span class="kicker">OIA · One is All</span>
      <h2>想发布你的插件 / 技能？</h2>
      <p class="lead">你一旦参与，就同时是开发者、创作者、作者。不用先申请资质、不用先会写代码——用 AI 生成插件骨架，或直接发布一个可复用的 Skill，分享给整个社区。</p>
      <div class="cta-row"><a href="/account?view=login" class="btn primary">开始创作 →</a></div>
    </div>
  </section>
<?php endif; ?>

  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note"><?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></p>
    </div>
    <div class="fb"><h4>站点导航</h4><a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">论坛</a><a href="/about">关于我们</a></div>
    <div class="fb"><h4>生态</h4><a href="/category/marketplace/skills">Skill 技能</a><a href="/category/marketplace/plugins">插件</a><a href="/category/marketplace/themes">主题</a><a href="/docs#api">开放 API</a></div>
    <div class="fb"><h4>联系</h4><a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/community">门派社区</a></div>
    <div class="f-bottom"><span><?=site_copyright()?></span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span><?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></span></div>
  </footer>
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
