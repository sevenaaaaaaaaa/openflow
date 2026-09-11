<?php
/**
 * 导航站点对比页 — /navigation/compare?ids=a,b,c（最多 3 个）
 *
 * 从导航详情页 / 相似推荐的「⚖ 对比」进入。
 * 行 = 对比维度，列 = 站点；GitHub 数据走 NavGithub 缓存；每列可直达详情或外站。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/nav-icons.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/NavGithub.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$sites = $nav['sites'] ?? [];
$catNames = [];
foreach (($nav['categories'] ?? []) as $c) $catNames[$c['id']] = $c['name'];

$ids = array_values(array_unique(array_filter(array_map('trim', explode(',', $_GET['ids'] ?? '')))));
$ids = array_slice($ids, 0, 3);
$cols = [];
foreach ($ids as $id) {
    foreach ($sites as $s) if (($s['id'] ?? '') === $id) { $cols[] = $s; break; }
}

// 每列的派生数据
$derived = [];
foreach ($cols as $s) {
    $repo = NavGithub::repoFromUrl($s['url'] ?? '');
    $derived[$s['id']] = [
        'rating' => comment_rating_summary('site', $s['id']),
        'gh'     => $repo ? NavGithub::repoMeta($repo) : null,
        'repo'   => $repo,
    ];
}

/** 找一行里数值最大的站点 id（用于高亮「更优」） */
function row_best(array $cols, callable $getter): ?string {
    $best = null; $bestV = null;
    foreach ($cols as $s) {
        $v = $getter($s);
        if ($v === null) continue;
        if ($bestV === null || $v > $bestV) { $bestV = $v; $best = $s['id']; }
    }
    return $bestV ? $best : null;
}
$bestStars = row_best($cols, fn($s) => $derived[$s['id']]['gh']['stars'] ?? null);
$bestRating = row_best($cols, fn($s) => $derived[$s['id']]['rating']['count'] ? $derived[$s['id']]['rating']['avg'] : null);
$bestHits = row_best($cols, fn($s) => (int)($s['hits'] ?? 0) ?: null);
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>产品对比 | <?=site_config_get("site_name")?> 增长导航</title>
<meta name="description" content="并排对比增长工具的功能、评分与适用场景，用数据帮你做选型决策。">
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
.cmp{width:100%;border-collapse:separate;border-spacing:0;background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.cmp th,.cmp td{padding:14px 16px;text-align:left;vertical-align:top;border-bottom:1px solid var(--border);font-size:13.5px;line-height:1.7}
.cmp tr:last-child th,.cmp tr:last-child td{border-bottom:none}
.cmp thead th{background:var(--surface-2);min-width:180px}
.cmp tbody th{width:110px;color:var(--faint);font-weight:500;font-size:12.5px;white-space:nowrap;background:var(--surface-2)}
.cmp .best{color:var(--ok);font-weight:700}
.cmp .best::after{content:" · 更优";font-size:10.5px;font-weight:500}
.cmp .nm{font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.cmp .nm img{width:22px;height:22px;border-radius:6px;object-fit:contain;background:var(--surface)}
.cmp .muted{color:var(--muted);font-size:12.5px}
.cmp .tag{display:inline-block;font-size:11px;padding:2px 8px;border-radius:999px;background:var(--accent-soft);color:var(--accent-strong);margin:1px 3px 1px 0}
.cmp-wrap{overflow-x:auto}
@media(max-width:720px){.cmp thead th{min-width:150px}.cmp tbody th{width:84px}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('navigation'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section class="reader reveal in" data-od-id="compare">
    <nav class="art-meta" aria-label="面包屑" style="margin-bottom:18px"><a href="/navigation" style="color:var(--faint)">← 增长导航</a></nav>

    <?php if (count($cols) < 2): ?>
    <div class="card" style="text-align:center;padding:60px 24px">
      <h1 class="h3">选择至少两个站点来对比</h1>
      <p class="lead" style="color:var(--muted)">在任意站点详情页点「⚖ 对比同类产品」即可生成对比。</p>
      <a href="/navigation" class="btn primary">去逛逛导航站</a>
    </div>
    <?php else: ?>
    <span class="kicker">产品对比</span>
    <h1 style="font-size:clamp(22px,3vw,30px);font-weight:800;letter-spacing:-.02em;margin:6px 0 18px"><?=htmlspecialchars(implode(' vs ', array_map(fn($s) => $s['name'], $cols)))?></h1>

    <div class="cmp-wrap">
    <table class="cmp">
      <thead>
        <tr><th></th>
        <?php foreach ($cols as $s): ?>
          <th>
            <div class="nm">
              <img src="<?=htmlspecialchars(nav_site_icon_url($s) ?? '')?>" alt="" onerror="this.remove()" loading="lazy">
              <a href="/navigation/<?=urlencode($s['id'])?>" style="color:inherit"><?=htmlspecialchars($s['name'])?></a>
              <?php if (!empty($s['featured'])): ?><span class="badge warn" style="font-size:10px">编辑推荐</span><?php endif; ?>
            </div>
            <div class="muted"><?=htmlspecialchars($catNames[$s['category'] ?? $s['cat'] ?? ''] ?? '未分类')?><?=!empty($s['sub'])?' · ' . htmlspecialchars($s['sub']):''?> · <?=($s['region']??'')==='cn'?'国内':'海外'?></div>
          </th>
        <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr><th>简介</th><?php foreach ($cols as $s): ?><td><?=htmlspecialchars($s['description'] ?? $s['desc'] ?? '—')?></td><?php endforeach; ?></tr>
        <tr><th>标签</th><?php foreach ($cols as $s): ?><td><?php $ts = (array)($s['tags'] ?? []); if ($ts): foreach ($ts as $t): ?><span class="tag"><?=htmlspecialchars($t)?></span><?php endforeach; else: ?>—<?php endif; ?></td><?php endforeach; ?></tr>
        <tr><th>用户评分</th><?php foreach ($cols as $s): $r = $derived[$s['id']]['rating']; ?><td class="<?=$bestRating===$s['id']?'best':''?>"><?=$r['count'] ? '★ ' . number_format($r['avg'], 1) . '（' . $r['count'] . ' 条）' : '暂无点评'?></td><?php endforeach; ?></tr>
        <tr><th>访问量</th><?php foreach ($cols as $s): ?><td class="<?=$bestHits===$s['id']?'best':''?>"><?=(int)($s['hits'] ?? 0) ? number_format((int)$s['hits']) . ' 次' : '—'?></td><?php endforeach; ?></tr>
        <tr><th>收录时间</th><?php foreach ($cols as $s): ?><td><?=htmlspecialchars(substr($s['created_at'] ?? '', 0, 10) ?: '—')?></td><?php endforeach; ?></tr>
        <?php if (array_filter($cols, fn($s) => $derived[$s['id']]['gh'])): ?>
        <tr><th>GitHub</th><?php foreach ($cols as $s): $g = $derived[$s['id']]['gh']; ?><td class="<?=$bestStars===$s['id']?'best':''?>"><?=$g ? '★ ' . NavGithub::compactNum($g['stars']) . ' · Fork ' . NavGithub::compactNum($g['forks']) : '—'?></td><?php endforeach; ?></tr>
        <tr><th>技术栈</th><?php foreach ($cols as $s): $g = $derived[$s['id']]['gh']; ?><td><?=$g && $g['language'] ? htmlspecialchars($g['language']) . ($g['license'] && $g['license'] !== 'NOASSERTION' ? ' · ' . htmlspecialchars($g['license']) : '') : '—'?></td><?php endforeach; ?></tr>
        <tr><th>最近更新</th><?php foreach ($cols as $s): $g = $derived[$s['id']]['gh']; ?><td><?=$g && $g['pushed_at'] ? NavGithub::relativeTime($g['pushed_at']) : '—'?></td><?php endforeach; ?></tr>
        <?php endif; ?>
        <?php if (array_filter($cols, fn($s) => !empty($s['reason']))): ?>
        <tr><th>编辑点评</th><?php foreach ($cols as $s): ?><td><?=htmlspecialchars($s['reason'] ?? '—')?></td><?php endforeach; ?></tr>
        <?php endif; ?>
        <tr><th>操作</th><?php foreach ($cols as $s): ?>
          <td>
            <a class="btn primary" style="font-size:12px;padding:6px 14px" href="/api/nav-click?site=<?=urlencode($s['id'])?>" target="_blank" rel="noopener">访问 →</a>
            <a class="btn ghost" style="font-size:12px;padding:6px 14px" href="/navigation/<?=urlencode($s['id'])?>">详情</a>
          </td>
        <?php endforeach; ?></tr>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
</body>
</html>
