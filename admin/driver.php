<?php
/**
 * 增长驱动引擎 — OpenFlow 的"主动推进"主引擎
 * 把舆情/AI/内容/CDP/MA 串成一条自动推动网站前进的飞轮
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('flow');

$message = '';
// 手动触发一轮
if (isset($_GET['run'])) {
    csrf_verify();
    $result = GrowthFlywheel::runCycle();
    $message = '已运行一轮增长驱动飞轮。';
    header('Location: /xmp/driver?done=1');
    exit;
}

$state = GrowthFlywheel::state();
$steps = GrowthFlywheel::steps();
$cycleCount = $state['cycle_count'] ?? 0;
$lastCycle = $state['last_cycle'] ?? 0;
$aiCfg = AiCenter::isConfigured();

// 待审核内容
$pending = $state['pending_review'] ?? [];

admin_header('增长驱动引擎');
?>
<style>
  .driver-hero{background:linear-gradient(135deg,var(--accent-soft),oklch(60% .18 300/.12));border:1px solid var(--border);border-radius:var(--r-lg);padding:24px}
  .driver-ring{display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;margin:16px 0}
  .driver-node{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 16px;border-radius:14px;background:var(--surface);border:1px solid var(--border);min-width:90px;transition:.15s}
  .driver-node.ok{border-color:var(--ok);background:var(--ok-soft)}
  .driver-node.degraded{border-color:var(--warn);background:var(--warn-soft)}
  .driver-node .di{font-size:22px}
  .driver-node .dn{font-size:12px;font-weight:700;text-align:center}
  .driver-node .ds{font-size:10px;color:var(--faint);text-align:center}
  .driver-arrow{color:var(--faint);font-size:16px}
  .driver-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:10px}
  .st-ok{color:var(--ok)}.st-degraded{color:var(--warn)}.st-error{color:var(--danger)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('driver'); ?>
  <div class="main">
    <div class="driver-hero">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="font-size:36px">🔄</div>
        <div>
          <h1 style="font-size:20px;font-weight:800">增长驱动引擎</h1>
          <p class="text-sm text-muted" style="margin-top:2px">舆情 → 总结 → 撰写 → 更新 → 优化 → 转化 → 激活 → 洞察 → 增长。让网站被主动推着前进。</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <form method="get" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary" name="run" value="1">▶️ 运行一轮</button></form>
          <span class="evo-tag"><?=$aiCfg?'🤖 AI 已启用':'⚠️ AI 待配置'?></span>
        </div>
      </div>
      <div class="text-sm text-muted" style="margin-top:10px">
        已运行 <?=$cycleCount?> 轮<?=$lastCycle?' · 最近：'.date('Y-m-d H:i:s', $lastCycle):' · 尚未运行'?>
      </div>
    </div>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 飞轮全景 -->
    <div class="driver-ring">
      <?php foreach ($steps as $id => $st): $r = $state['steps'][$id] ?? null; $st2 = $r['status'] ?? 'pending'; ?>
      <div class="driver-node <?=$st2?>">
        <div class="di"><?=$st['icon']?></div>
        <div class="dn"><?=$st['name']?></div>
        <div class="ds"><?=$st['needs_ai'] && !$aiCfg ? '需AI' : (($r['detail'] ?? '') ? mb_substr($r['detail'], 0, 14) : '待运行')?></div>
      </div>
      <?php if ($id !== 'report'): ?><div class="driver-arrow">→</div><?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- 各环节详情 -->
    <h2 style="font-size:16px;font-weight:800;margin:16px 0 12px">📋 环节明细</h2>
    <?php foreach ($steps as $id => $st): $r = $state['steps'][$id] ?? null; ?>
    <div class="driver-card">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:18px"><?=$st['icon']?></span>
        <b style="font-size:14px"><?=$st['name']?></b>
        <?php if ($r): $st2 = $r['status']; ?>
        <span class="st-<?=$st2==='ok'?'ok':($st2==='degraded'||$st2==='skipped'?'degraded':'error')?>" style="font-size:12px;font-weight:700">
          <?=['ok'=>'✓ 正常','degraded'=>'○ 降级','skipped'=>'○ 跳过','error'=>'✗ 异常','pending'=>'○ 待运行'][$st2] ?? $st2?>
        </span>
        <?php endif; ?>
        <span style="margin-left:auto;color:var(--faint);font-size:11px"><?=$r ? date('H:i', $r['ts'] ?? 0) : ''?></span>
      </div>
      <div class="text-sm text-muted" style="margin-top:6px"><?=htmlspecialchars($st['desc'])?></div>
      <?php if ($r && !empty($r['detail'])): ?><div class="text-xs" style="margin-top:4px;color:var(--accent)">→ <?=htmlspecialchars($r['detail'])?></div><?php endif; ?>
      <?php if ($st['needs_ai'] && !$aiCfg && $r && in_array($r['status'] ?? '', ['degraded','skipped'])): ?>
      <div class="text-xs" style="margin-top:4px;color:var(--warn)">💡 <?=htmlspecialchars($r['suggestion'] ?? '配置 AI 后自动启用')?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- 热点结论（爬取 + AI 洞察） -->
    <?php
    $analyzeR = $state['steps']['analyze'] ?? null;
    $collectR = $state['steps']['collect'] ?? null;
    $hotInsights = [];
    if ($analyzeR && !empty($analyzeR['data'])) {
        $hotInsights = is_array($analyzeR['data']) ? $analyzeR['data'] : [['summary' => $analyzeR['data']]];
    }
    $collectTopics = $collectR['topics'] ?? [];
    if (!empty($hotInsights) || !empty($collectTopics)): ?>
    <div style="padding:16px;border:1px solid var(--border);border-radius:14px;background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.05));margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <span style="font-size:18px">🔥</span><b style="font-size:15px">热点结论</b>
        <span class="text-xs text-muted"><?=$analyzeR ? 'AI 洞察 · ' . date('m-d H:i', $analyzeR['ts'] ?? 0) : '尚未总结（点击右上角运行）'?></span>
      </div>
      <?php if (!empty($collectTopics)): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
        <span class="text-xs text-muted" style="padding-top:3px">爬取主题：</span>
        <?php foreach ($collectTopics as $t): ?><span class="badge badge-gray" style="font-size:12px"><?=htmlspecialchars(is_string($t) ? $t : ($t['name'] ?? ''))?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($hotInsights)): foreach (array_slice($hotInsights, 0, 5) as $hi): ?>
      <div style="padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface);margin-bottom:8px">
        <div style="font-weight:700;font-size:13px">📌 <?=htmlspecialchars($hi['主题'] ?? $hi['topic'] ?? $hi['title'] ?? '热点主题')?></div>
        <?php if (!empty($hi['核心观点'])): ?><div style="font-size:12.5px;color:var(--muted);margin-top:4px">💡 <?=htmlspecialchars($hi['核心观点'])?></div><?php endif; ?>
        <?php if (!empty($hi['机会点'])): ?><div style="font-size:12.5px;color:var(--ok);margin-top:3px">🎯 机会点：<?=htmlspecialchars($hi['机会点'])?></div><?php endif; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>

    <!-- 待审核内容 -->
    <?php if (!empty($pending)): ?>
    <h2 style="font-size:16px;font-weight:800;margin:16px 0 12px">🕐 待审核内容（AI 草稿）</h2>
    <?php foreach (array_slice(array_reverse($pending), 0, 10) as $p): $pArticle = get_article($p['id'] ?? ''); ?>
    <div class="driver-card">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <b><?=htmlspecialchars($p['title'] ?? '')?></b>
        <span class="text-xs text-muted" style="margin-left:auto"><?=date('Y-m-d H:i', $p['ts'] ?? 0)?></span>
      </div>
      <?php if ($pArticle): ?>
      <div style="font-size:12.5px;color:var(--muted);margin-top:6px;line-height:1.7"><?=htmlspecialchars(mb_substr(strip_tags($pArticle['content'] ?? ''), 0, 160))?><?=mb_strlen(strip_tags($pArticle['content'] ?? '')) > 160 ? '…' : ''?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
        <?php if (!empty($pArticle['seo_title'])): ?><span class="badge badge-gray" style="font-size:11px">SEO: <?=htmlspecialchars(mb_substr($pArticle['seo_title'], 0, 24))?></span><?php endif; ?>
        <?php if (!empty($pArticle['tags'])): ?><span class="badge badge-gray" style="font-size:11px"><?=htmlspecialchars(implode('、', array_slice($pArticle['tags'], 0, 3)))?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <div style="margin-top:8px;display:flex;gap:8px">
        <a href="/admin/article-edit.php?id=<?=htmlspecialchars($p['id'] ?? '')?>" class="btn btn-s btn-sm">✍️ 编辑发布</a>
        <a href="../content-preview.php?type=article&id=<?=htmlspecialchars($p['id'] ?? '')?>" class="btn btn-ghost btn-sm" target="_blank">👁 预览</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- 历史 -->
    <?php if (!empty($state['history'])): ?>
    <h2 style="font-size:16px;font-weight:800;margin:16px 0 12px">🕘 驱动历史</h2>
    <?php foreach (array_slice(array_reverse($state['history']), 0, 8) as $h): ?>
    <div class="evo-history">
      <div class="font-bold text-sm">第 <?=htmlspecialchars($h['ts'] ? date('m-d H:i', $h['ts']) : '')?> 轮</div>
      <div class="text-xs text-muted mt-1"><?=htmlspecialchars($h['summary'] ?? '')?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();
