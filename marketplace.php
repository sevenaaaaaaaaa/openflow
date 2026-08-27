<?php
/**
 * 统一市场 — 插件 / 技能 / 主题 浏览与安装
 * 前台的「生态商店」：开发者发布资产，用户浏览/安装/打分
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>生态市场 | <?=site_config_get("site_name")?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .mkt-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px;transition:.15s;display:flex;flex-direction:column;gap:10px}
  .mkt-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm);border-color:var(--accent)}
  .mkt-badge{font-size:10.5px;padding:2px 8px;border-radius:999px;font-weight:600}
  .mkt-btn{display:inline-block;padding:8px 18px;border-radius:999px;font-size:13px;font-weight:600;transition:.15s}

  /* 设计语言统一：token 语义工具类（终版契约） */
  .text-faint{color:var(--faint)}.text-muted{color:var(--muted)}.text-fg{color:var(--fg)}
  .text-ok{color:var(--ok)}.text-accent{color:var(--accent)}.text-danger{color:var(--danger)}
  .bg-surface{background:var(--surface)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="marketplace"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1120px">
    <?php if ($view === 'skill' && $skill): ?>
    <!-- ═══ Skill 详情 ═══ -->
    <a href="/marketplace" class="text-sm text-accent">← 返回市场</a>
    <div class="bg-surface rounded-3xl p-8 mt-4" style="border:1px solid var(--border)">
      <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
        <div style="width:72px;height:72px;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:34px"><?=htmlspecialchars($skill['icon'] ?? '⚡')?></div>
        <div style="flex:1;min-width:240px">
          <h1 class="text-2xl font-bold"><?=htmlspecialchars($skill['title'] ?? '')?></h1>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:6px">
            <span class="mkt-badge" style="background:var(--bg);color:var(--muted)"><?=$skillTypes['prompt']['name'] ?? ''?><?=htmlspecialchars($skill['type'] ?? '')?></span>
            <span class="mkt-badge" style="background:var(--ok-soft);color:var(--ok)"><?=htmlspecialchars($skill['author'] ?? 'OpenFlow')?></span>
            <span class="text-xs text-faint">v<?=htmlspecialchars($skill['version'] ?? '1.0.0')?></span>
            <span class="text-xs text-faint"><?=($skill['installs'] ?? 0)?> 次安装</span>
          </div>
          <div class="text-[14px] leading-relaxed mt-3 text-muted"><?=htmlspecialchars($skill['description'] ?? '')?></div>
          <div class="flex gap-2 flex-wrap mt-3">
            <?php foreach ($skill['tags'] ?? [] as $t): ?><span class="text-xs px-3 py-1 rounded-full" style="background:var(--bg);color:var(--muted)"># <?=htmlspecialchars($t)?></span><?php endforeach; ?>
          </div>
        </div>
        <div class="text-center" style="min-width:120px">
          <?php if (($skill['rating_count'] ?? 0) > 0): ?>
          <div class="text-2xl font-extrabold" style="color:var(--warn-strong,var(--warn))"><?=number_format((float)$skill['rating'], 1)?></div>
          <div style="color:var(--warn);font-size:14px;letter-spacing:1px"><?=str_repeat('★', max(0, min(5, (int)round((float)$skill['rating']))))?><?=str_repeat('☆', max(0, 5 - (int)round((float)$skill['rating'])))?></div>
          <div class="text-xs text-faint"><?=$skill['rating_count']?> 人评分</div>
          <?php endif; ?>
          <?php $skillPrice = (float)($skill['price'] ?? 0); $skillOwned = !empty($member) && in_array($skill['id'] ?? '', $member['purchased_skills'] ?? []); ?>
          <?php if ($skillPrice > 0 && !$skillOwned): ?>
          <div class="text-xl font-extrabold mt-2" style="color:var(--warn-strong,var(--warn))">¥<?=$skillPrice?></div>
          <button onclick="purchaseSkill('<?=htmlspecialchars($skill['id'])?>')" class="mkt-btn mt-3" style="background:var(--accent);color:var(--on-accent)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M3 3h2l2.4 12.4a1.5 1.5 0 0 0 1.5 1.2h7.7a1.5 1.5 0 0 0 1.5-1.2L20 7H6"/></svg></span> 购买此技能</button>
          <?php else: ?>
          <button onclick="installSkill('<?=htmlspecialchars($skill['id'])?>')" class="mkt-btn mt-3" style="background:var(--accent);color:var(--on-accent)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span> 安装此技能</button>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($skill['type'] === 'prompt' && !empty($skill['content'])): ?>
      <div class="mt-6">
        <div class="text-sm font-bold mb-2" style="color:var(--ok)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4m-3 1 3 3 3-3"/><circle cx="9" cy="13.5" r="1.2" fill="currentColor"/><circle cx="15" cy="13.5" r="1.2" fill="currentColor"/><path d="M9.5 17h5"/></svg></span> 提示词内容</div>
        <pre style="background:var(--accent);color:var(--on-accent);padding:18px;border-radius:12px;font-size:13px;white-space:pre-wrap;line-height:1.7"><?=htmlspecialchars($skill['content'])?></pre>
        <button onclick="copySkillPrompt()" class="mkt-btn mt-3" style="background:var(--bg);color:var(--fg)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9V4ZM9 10h6M9 14h4"/></svg></span> 复制提示词</button>
      </div>
      <?php endif; ?>
      <?php if ($skill['type'] === 'workflow' && !empty($skill['steps'])): ?>
      <div class="mt-6">
        <div class="text-sm font-bold mb-3" style="color:var(--ok);display:flex;align-items:center"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-3px;margin-right:6px"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>工作流步骤</div>
        <?php foreach ($skill['steps'] as $i => $st): ?>
        <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--bg)">
          <span style="width:26px;height:26px;border-radius:50%;background:var(--accent);color:var(--on-accent);display:grid;place-items:center;font-size:12px;font-weight:700;flex-shrink:0"><?=$i+1?></span>
          <div><div class="font-semibold text-sm"><?=htmlspecialchars($st['title'] ?? '')?></div><div class="text-xs text-muted mt-0.5"><?=htmlspecialchars($st['desc'] ?? '')?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 评论 -->
    <div style="margin-top:30px"><?php fc_comment_widget('skill', $skill['id'], ['title' => '评价', 'rating' => true]); ?></div>

    <?php elseif ($view === 'plugin' && $plugin): ?>
    <!-- ═══ 插件详情 ═══ -->
    <a href="/marketplace" class="text-sm text-accent">← 返回市场</a>
    <div class="bg-surface rounded-3xl p-8 mt-4" style="border:1px solid var(--border)">
      <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
        <div style="width:72px;height:72px;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:34px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span></div>
        <div style="flex:1;min-width:240px">
          <h1 class="text-2xl font-bold"><?=htmlspecialchars($plugin['title'] ?? '')?></h1>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:6px">
            <span class="mkt-badge" style="background:var(--bg);color:var(--muted)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> 插件</span>
            <span class="mkt-badge" style="background:var(--ok-soft);color:var(--ok)"><?=htmlspecialchars($plugin['author'] ?? 'OpenFlow')?></span>
            <span class="text-xs text-faint">v<?=htmlspecialchars($plugin['version'] ?? '1.0.0')?></span>
          </div>
          <p class="text-[14px] leading-relaxed mt-3 text-muted"><?=htmlspecialchars($plugin['description'] ?? '')?></p>
          <?php $pr = comment_rating_summary('plugin', $plugin['id']); if ($pr['count'] > 0): ?>
          <div class="mt-3" style="color:var(--warn);font-size:14px"><?=str_repeat('★', max(0, min(5, (int)round($pr['avg']))))?><?=str_repeat('☆', max(0, 5 - (int)round($pr['avg'])))?> <b style="color:var(--warn-strong,var(--warn))"><?=number_format($pr['avg'], 1)?></b> <span class="text-xs" style="color:var(--faint)"><?=$pr['count']?> 人评分</span></div>
          <?php endif; ?>
        </div>
        <div class="text-center" style="min-width:120px">
          <div class="mkt-badge" style="background:var(--ok-soft);color:var(--ok);display:inline-flex;align-items:center"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="m4 12.5 5 5L20 6.5"/></svg>已安装</div>
          <a href="/xmp/plugins" class="mkt-btn mt-3" style="background:var(--accent);color:var(--on-accent)">去后台管理</a>
        </div>
      </div>
    </div>
    <div style="margin-top:30px"><?php fc_comment_widget('plugin', $plugin['id'], ['title' => '评价', 'rating' => true]); ?></div>

    <?php else: ?>
    <!-- ═══ 市场首页 ═══ -->
    <div style="padding:clamp(16px,3vw,32px) 0 clamp(20px,3vw,36px)">
      <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);align-items:center">
        <div style="display:flex;flex-direction:column;gap:14px">
          <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">OPEN ECO · 生态市场</span>
          <h1 style="font-size:clamp(30px,4.5vw,46px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">把增长能力<span style="font-family:var(--font-display);font-style:italic">即装即用</span></h1>
          <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:540px">Skill · 插件 · 主题，一站式扩展你的 <?=site_config_get("site_name")?>。开发者可发布，用户可安装，构建共创生态。</p>
          <form method="get" style="display:flex;gap:8px;max-width:460px;margin-top:4px">
            <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="搜索插件、技能、主题…" style="flex:1;padding:12px 18px;border-radius:999px;font-size:14px;outline:none;border:1.5px solid var(--border);background:var(--surface)">
            <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
            <input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>">
            <button style="padding:12px 22px;border-radius:999px;font-weight:700;font-size:14px;background:var(--accent);color:var(--on-accent);border:0;cursor:pointer">搜索</button>
          </form>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <?php $heroMk = [
            ['⚡', 'Skill 技能', '开箱即用的增长能力', '/category/marketplace/skills', 'var(--accent-soft)', 'var(--accent)'],
            ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg>', '插件', '扩展系统功能', '/category/marketplace/plugins', 'var(--ok-soft)', 'var(--ok)'],
            ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg>', '主题', '视觉与布局', '/category/marketplace/themes', 'oklch(70% .13 305/.14)', 'oklch(60% .18 300)'],
            ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg>', '论坛', '社区问答与讨论', '/community', 'oklch(70% .13 75/.14)', 'oklch(62% .15 70)'],
          ]; foreach ($heroMk as $hc): ?>
          <a href="<?=$hc[3]?>" style="display:flex;flex-direction:column;gap:10px;padding:18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%);text-decoration:none;transition:transform .25s var(--ease-spring),box-shadow .25s,border-color .25s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-sm)';this.style.borderColor='var(--border-strong)'" onmouseout="this.style.transform='none';this.style.boxShadow='none';this.style.borderColor='var(--border)'">
            <span style="width:38px;height:38px;border-radius:12px;background:<?=$hc[4]?>;color:<?=$hc[5]?>;display:grid;place-items:center;font-size:18px"><?=$hc[0]?></span>
            <b style="font-size:14.5px;color:var(--fg)"><?=$hc[1]?></b>
            <span style="font-size:12px;color:var(--muted);line-height:1.5"><?=$hc[2]?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 统计 -->
    <div class="grid gap-4 mb-8" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
      <div class="rounded-2xl p-5 text-center" style="background:var(--surface);border:1px solid var(--border)"><div class="text-2xl font-extrabold"><?=$stats['total']?></div><div class="text-xs text-muted mt-1">全部资产</div></div>
      <div class="rounded-2xl p-5 text-center" style="background:var(--surface);border:1px solid var(--border)"><div class="text-2xl font-extrabold"><?=$stats['plugins']?></div><div class="text-xs text-muted mt-1"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> 插件</div></div>
      <div class="rounded-2xl p-5 text-center" style="background:var(--surface);border:1px solid var(--border)"><div class="text-2xl font-extrabold"><?=$stats['skills']?></div><div class="text-xs text-muted mt-1"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span> 技能</div></div>
      <div class="rounded-2xl p-5 text-center" style="background:var(--surface);border:1px solid var(--border)"><div class="text-2xl font-extrabold"><?=$stats['themes']?></div><div class="text-xs text-muted mt-1"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg></span> 主题</div></div>
    </div>

    <!-- 搜索 + 筛选 -->
    <form method="get" class="flex gap-2 mb-4">
      <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="搜索插件、技能、主题…" class="flex-1 px-4 py-3 rounded-full" style="border:1.5px solid var(--border);background:var(--surface);font-size:14px;outline:none">
      <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
      <input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>">
      <button class="px-6 rounded-full font-bold" style="background:var(--accent);color:var(--on-accent)">搜索</button>
    </form>
    <div class="flex flex-wrap gap-2 mb-8">
      <a href="?type=all&sort=<?=$sort?>" class="mkt-btn <?=$type==='all'?'':'bg-surface border'?>" style="<?=$type==='all'?'background:var(--accent);color:var(--on-accent)':''?>">全部</a>
      <?php foreach ($categories as $tk => $tc): ?>
      <a href="?type=<?=$tk?>&sort=<?=$sort?>" class="mkt-btn <?=$type===$tk?'':'bg-surface border'?>" style="<?=$type===$tk?'background:var(--accent);color:var(--on-accent)':''?>"><?=$tc['icon']?> <?=$tc['name']?></a>
      <?php endforeach; ?>
      <span style="margin-left:auto;display:flex;gap:8px">
        <a href="?type=<?=$type?>&sort=popular" class="mkt-btn bg-surface border" style="<?=$sort==='popular'?'background:var(--ok);color:var(--surface);border-color:var(--ok)':''?>">热门</a>
        <a href="?type=<?=$type?>&sort=rating" class="mkt-btn bg-surface border" style="<?=$sort==='rating'?'background:var(--ok);color:var(--surface);border-color:var(--ok)':''?>">评分</a>
        <a href="?type=<?=$type?>&sort=new" class="mkt-btn bg-surface border" style="<?=$sort==='new'?'background:var(--ok);color:var(--surface);border-color:var(--ok)':''?>">最新</a>
      </span>
    </div>

    <?php if (empty($assets)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">暂无匹配资产</div>
    <?php else: ?>
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
      <?php foreach ($assets as $a): ?>
      <div class="mkt-card" style="display:flex;flex-direction:column">
        <a href="<?=htmlspecialchars($a['url'])?>" style="text-decoration:none;color:inherit">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:20px;flex-shrink:0"><?=htmlspecialchars($a['icon'])?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <span class="font-bold"><?=htmlspecialchars($a['title'])?></span>
              <span class="mkt-badge" style="background:var(--bg);color:var(--muted)"><?=$typeNames[$a['type']]['name'] ?? $a['type']?></span>
            </div>
            <div class="text-xs text-muted mt-0.5"><?=htmlspecialchars($a['author'] ?? '')?><?=($a['author_type'] ?? '') === 'user' ? ' · 用户发布' : ''?><?=($a['source'] ?? '') === 'remote' ? ' · 🌍 远程' : ''?></div>
          </div>
        </div>
        <p class="text-sm text-muted line-clamp-2" style="font-size:13px"><?=htmlspecialchars($a['description'] ?? '')?></p>
        <?php if (($a['type'] ?? '') === 'bundle'): ?>
        <div style="font-size:12px;color:var(--accent);margin-top:4px">包含 <?=$a['items_count'] ?? 0?> 个产品</div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:12px;color:var(--faint);margin-top:auto">
          <?php if (($a['rating_count'] ?? 0) > 0): ?><span style="color:var(--warn)">★ <?=number_format((float)$a['rating'], 1)?></span><?php endif; ?>
          <span>⬇ <?=$a['installs'] ?? 0?> 次安装</span>
          <?php if (($a['type'] ?? '') === 'skill' && (float)($a['price'] ?? 0) <= 0): ?><span class="mkt-badge" style="background:var(--ok-soft);color:var(--ok)">免费安装</span><?php endif; ?>
          <?php if (!empty($a['installed'])): ?><span class="mkt-badge" style="background:var(--ok-soft);color:var(--ok);margin-left:auto">已安装</span><?php endif; ?>
        </div>
        </a>
        <?php if (in_array($a['type'] ?? '', ['bundle','membership']) && ($a['price'] ?? 0) > 0): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
          <span style="font-weight:800;color:var(--ok);font-size:16px">¥<?=number_format($a['price'],0)?></span>
          <button onclick="purchaseSkill('<?=htmlspecialchars($a['id'])?>')" class="mkt-btn" style="background:var(--accent);color:var(--on-accent);font-size:13px"><?=$a['type']==='membership'?'开通会员':'购买组合包'?> →</button>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 发布入口 -->
    <div class="rounded-3xl p-6 mt-10 text-center" style="background:var(--surface);border:1px solid var(--border)">
      <div class="font-bold text-lg">想发布你的插件 / 技能？</div>
      <p class="text-sm text-muted mt-2 mb-4">用 AI 生成插件骨架，或直接发布一个可复用的 Skill，分享给整个社区。</p>
      <a href="/account?view=login" class="mkt-btn" style="background:var(--accent);color:var(--on-accent)">开始创作 →</a>
    </div>
    <?php endif; ?>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1120px">
      <div class="mb-2"><?=site_config_get("site_name")?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>

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
