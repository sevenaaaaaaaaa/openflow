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

    <!-- 待审核内容 -->
    <?php if (!empty($pending)): ?>
    <h2 style="font-size:16px;font-weight:800;margin:16px 0 12px">🕐 待审核内容</h2>
    <?php foreach (array_slice(array_reverse($pending), 0, 10) as $p): ?>
    <div class="driver-card">
      <div style="display:flex;align-items:center;gap:8px">
        <b><?=htmlspecialchars($p['title'] ?? '')?></b>
        <span class="text-xs text-muted" style="margin-left:auto"><?=date('Y-m-d H:i', $p['ts'] ?? 0)?></span>
      </div>
      <a href="/admin/article-edit.php?id=<?=htmlspecialchars($p['id'] ?? '')?>" class="btn btn-ghost btn-sm" style="margin-top:8px">审核草稿 →</a>
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
