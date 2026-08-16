<?php
/**
 * 自我进化中心 — 平台自动体检 + 迭代建议
 * 定期扫描前后端数据，发现改进点，供管理员采纳后优化平台
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$message = '';
$error = '';

// ─── 操作 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['scan'])) {
    csrf_verify();
    if (isset($_POST['resolve']) && isset($_POST['id'])) {
        SelfEvolve::resolve(trim($_POST['id']), trim($_POST['note'] ?? ''));
        $message = '已标记为已解决，进入迭代历史。';
        header('Location: evolution.php');
        exit;
    }
    if (isset($_POST['ignore']) && isset($_POST['id'])) {
        // 忽略建议：通知生长引擎降权
        $sugs = SelfEvolve::state()['suggestions'] ?? [];
        foreach ($sugs as $sg) {
            if ($sg['id'] === $_POST['id']) { GrowthEngine::suggestionIgnored($sg['id'], $sg['category'] ?? 'other'); break; }
        }
        $message = '已忽略该建议，同类建议会降低优先级。';
        header('Location: evolution.php');
        exit;
    }
    if (isset($_POST['to_task']) && isset($_POST['id'])) {
        // 建议转待办
        $res = SelfEvolve::convertToTask(trim($_POST['id']), trim($_POST['assignee'] ?? ''));
        if ($res['ok']) {
            $message = '已转为任务，可在任务管理查看。';
        } else {
            $error = '转任务失败：' . ($res['error'] ?? '未知');
        }
        header('Location: evolution.php');
        exit;
    }
    if (isset($_POST['publish_template'])) {
        // 脱敏打包上架生态商城
        $tpl = GrowthEngine::exportAnonymizedTemplate();
        $base = ThemeSystem::get($tpl['suggested_base_theme'] ?? 'default');
        $theme = $base ?: ThemeSystem::get('default');
        $theme['name'] = $tpl['name'];
        $theme['desc'] = $tpl['desc'];
        $theme['author'] = '社区';
        $theme['version'] = '1.0.0';
        $theme['installs'] = 0;
        $theme['tags'] = ['生长模板', '社区'];
        $theme['fingerprint'] = $tpl['fingerprint'];
        ThemeSystem::saveCustom($tpl['theme_id'], $theme);
        try { GrowthEngine::timeline('publish', '脱敏打包上架：' . $tpl['name']); } catch (\Throwable $e) {}
        $message = '✅ 已脱敏打包并上架生态商城主题区（未含任何用户/内容数据）。';
        header('Location: evolution.php');
        exit;
    }
    if (isset($_GET['scan'])) {
        $result = SelfEvolve::runScan();
        $message = "扫描完成：新增 {$result['new']} 条建议，共 {$result['total']} 条待处理。";
        header('Location: evolution.php?done=1');
        exit;
    }
    if (isset($_POST['archive'])) {
        $removed = SelfEvolve::archiveResolved();
        $message = "已归档 {$removed} 条已解决建议。";
        header('Location: evolution.php');
        exit;
    }
}

$state = SelfEvolve::state();
$suggestions = $state['suggestions'] ?? [];
$history = array_reverse($state['history'] ?? []);
$lastScan = $state['last_scan'] ?? 0;
$scanCount = $state['scan_count'] ?? 0;

// 统计数据
$open = array_filter($suggestions, fn($s) => ($s['status'] ?? 'open') === 'open');
$critical = array_filter($open, fn($s) => ($s['severity'] ?? '') === 'critical');
$high = array_filter($open, fn($s) => ($s['severity'] ?? '') === 'high');
$bugs = array_filter($open, fn($s) => ($s['category'] ?? '') === 'bug');
$content = array_filter($open, fn($s) => ($s['category'] ?? '') === 'content');
$perf = array_filter($open, fn($s) => ($s['category'] ?? '') === 'perf');
$routing = array_filter($open, fn($s) => ($s['category'] ?? '') === 'routing');

// 排序：严重度 + 个性权重（生长引擎）综合排序
$suggestions = SelfEvolve::personalizedOrder($suggestions);
// 形态画像
$shape = GrowthEngine::shape();
$daysAlive = GrowthEngine::daysAlive();
// 新增：时间线 / 周报 / 形态对比 / 修复验证
$timeline = GrowthEngine::timelineGet(20);
$report = GrowthEngine::report(7);
$compare = GrowthEngine::shapeCompare();
$verifications = SelfEvolve::verifyRecentResolutions();
$users = get_users();

$sevLabel = ['critical' => '🔴 严重', 'high' => '🟠 高', 'medium' => '🟡 中', 'info' => '🔵 信息'];
$sevColor = ['critical' => 'var(--danger)', 'high' => '#ea580c', 'medium' => '#ca8a04', 'info' => '#2563eb'];
$catLabel = ['bug' => '🐞 Bug', 'content' => '📄 内容', 'perf' => '⚡ 性能', 'routing' => '🧭 路由', 'interaction' => '🎨 交互'];

admin_header('自我进化中心');
?>
<style>
  .evo-hero{background:linear-gradient(135deg,var(--accent-soft),oklch(60% .18 300/.12));border:1px solid var(--border);border-radius:var(--r-lg);padding:28px}
  .evo-stat{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;text-align:center}
  .evo-stat .num{font-size:28px;font-weight:800}
  .evo-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;margin-bottom:12px;transition:.15s}
  .evo-card:hover{box-shadow:var(--shadow-sm);border-color:var(--border-strong)}
  .evo-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700}
  .evo-tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:600;background:var(--hover)}
  .evo-history{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px;margin-bottom:8px;font-size:13px}
</style>

<div class="admin-layout">
  <?php admin_sidebar('evolution'); ?>
  <div class="main">
    <div class="evo-hero mb-4">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="font-size:34px">🧬</div>
        <div>
          <h1 style="font-size:20px;font-weight:800">自我进化中心</h1>
          <p class="text-sm text-muted" style="margin-top:2px">平台定期体检前后端数据，自动发现改进点。你的采纳就是下一次迭代。</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <form method="get" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary" name="scan" value="1">🔍 立即扫描</button></form>
          <form method="post" style="display:inline"><?= csrf_field() ?><button class="btn btn-ghost" name="archive" value="1">🗄 归档已解决</button></form>
        </div>
      </div>
      <div class="text-sm text-muted" style="margin-top:10px">
        <?php if ($lastScan): ?>上次扫描：<?=date('Y-m-d H:i:s', $lastScan)?> · 累计扫描 <?=$scanCount?> 次<?php else: ?>尚未扫描，点击"立即扫描"开始体检<?php endif; ?>
        <?php if (!empty($report['highlights'])): ?>
        <div style="margin-top:10px;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:12px">
          <div style="font-size:12px;font-weight:700;color:var(--accent);margin-bottom:4px">📋 近 <?=htmlspecialchars($report['period'])?> 概览</div>
          <div style="font-size:12.5px;color:var(--muted);line-height:1.7"><?php foreach ($report['highlights'] as $hl): ?>· <?=htmlspecialchars($hl)?><br><?php endforeach; ?></div>
          <?php if (!empty($report['active_hours'])): ?><div class="text-xs" style="color:var(--faint);margin-top:4px">活跃时段：<?=implode(', ', array_map(fn($h) => $h . '时', $report['active_hours']))?></div><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 统计卡 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px">
      <div class="evo-stat"><div class="num" style="color:var(--fg)"><?=count($open)?></div><div class="text-xs text-muted">待处理建议</div></div>
      <div class="evo-stat"><div class="num" style="color:var(--danger)"><?=count($critical)?></div><div class="text-xs text-muted">严重问题</div></div>
      <div class="evo-stat"><div class="num" style="color:#ea580c"><?=count($high)?></div><div class="text-xs text-muted">高优先级</div></div>
      <div class="evo-stat"><div class="num" style="color:#2563eb"><?=count($bugs)?></div><div class="text-xs text-muted">🐞 Bug</div></div>
      <div class="evo-stat"><div class="num" style="color:var(--ok)"><?=count($content)?></div><div class="text-xs text-muted">📄 内容缺失</div></div>
      <div class="evo-stat"><div class="num" style="color:#ca8a04"><?=count($perf)+count($routing)?></div><div class="text-xs text-muted">⚡ 性能/路由</div></div>
      <div class="evo-stat"><div class="num" style="color:var(--faint)"><?=count($history)?></div><div class="text-xs text-muted">已迭代</div></div>
    </div>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:16px;align-items:start">
      <!-- 建议清单 -->
      <div>
        <h2 style="font-size:16px;font-weight:800;margin-bottom:12px">💡 迭代建议清单</h2>
        <?php if (empty($suggestions)): ?>
        <div class="evo-card text-center text-muted" style="padding:40px">🎉 暂无建议，一切健康。点击"立即扫描"让平台自我体检。</div>
        <?php else: ?>
        <?php foreach ($suggestions as $s): $isOpen = ($s['status'] ?? 'open') === 'open'; $pw = $s['personal_weight'] ?? 0; ?>
        <div class="evo-card" style="<?=!$isOpen?'opacity:.5':''?>">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <span class="evo-badge" style="background:<?=$sevColor[$s['severity'] ?? 'medium']?>;color:#fff"><?=$sevLabel[$s['severity'] ?? 'medium']?></span>
            <span class="evo-tag"><?=$catLabel[$s['category'] ?? ''] ?? $s['category']?></span>
            <?php if (($s['seen_count'] ?? 1) > 1): ?><span class="evo-tag">已见 <?=$s['seen_count']?> 次</span><?php endif; ?>
            <?php if ($pw > 0.5): ?><span class="evo-tag" style="color:var(--ok)">📈 你常用此模块</span>
            <?php elseif ($pw < -0.3): ?><span class="evo-tag" style="color:var(--faint)">📉 已降权</span><?php endif; ?>
            <span class="text-xs text-muted" style="margin-left:auto"><?=htmlspecialchars($s['first_seen'] ?? '')?></span>
          </div>
          <div style="font-weight:700;font-size:14.5px;margin-bottom:6px"><?=htmlspecialchars($s['title'])?></div>
          <div class="text-sm text-muted" style="line-height:1.7;margin-bottom:6px"><?=htmlspecialchars($s['detail'] ?? '')?></div>
          <?php if (!empty($s['hint'])): ?><div class="text-xs" style="color:var(--faint);margin-bottom:6px">💭 <?=htmlspecialchars($s['hint'])?></div><?php endif; ?>
          <?php if (!empty($s['action'])): ?><div class="text-xs" style="color:var(--accent);margin-bottom:8px">→ <?=htmlspecialchars($s['action'])?></div><?php endif; ?>
          <?php if ($isOpen): ?>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <form method="post" style="display:flex;gap:8px;align-items:center;flex:1;min-width:280px">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?=htmlspecialchars($s['id'])?>">
              <input type="text" name="note" placeholder="修复备注（可选）" style="flex:1;min-width:160px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:12.5px">
              <button class="btn btn-primary btn-sm" name="resolve" value="1">✅ 采纳</button>
            </form>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?=htmlspecialchars($s['id'])?>"><button class="btn btn-ghost btn-sm" name="ignore" value="1" title="忽略，同类建议降权">🙈 忽略</button></form>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?=htmlspecialchars($s['id'])?>"><button class="btn btn-ghost btn-sm" name="to_task" value="1" title="转为待办任务">📋 转任务</button></form>
          </div>
          <?php else: ?>
          <div class="text-xs text-muted">✅ 已解决 · <?=htmlspecialchars($s['resolved_at'] ?? '')?><?=!empty($s['resolve_note'])?' · '.htmlspecialchars($s['resolve_note']):''?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- 右侧：数据洞察 + 历史 -->
      <div>
        <h2 style="font-size:16px;font-weight:800;margin-bottom:12px">🌱 你的平台形态</h2>
        <div class="evo-card" style="background:linear-gradient(135deg,var(--accent-soft),oklch(60% .18 300/.12))">
          <div style="display:flex;align-items:center;gap:14px">
            <div style="font-size:42px"><?=htmlspecialchars($shape['label'] ?? '🌱 新生')[0] === '🌱' ? '🌱' : '🧬'?></div>
            <div>
              <div style="font-weight:800;font-size:17px"><?=htmlspecialchars($shape['label'] ?? '🌱 新生')?></div>
              <div class="text-xs text-muted mt-1">已生长 <?=$daysAlive?> 天 · <?=htmlspecialchars($shape['born_at'] ? date('Y-m-d', $shape['born_at']) : '')?> 出生</div>
              <?php if (!empty($shape['strengths'])): ?>
              <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap"><?php foreach ($shape['strengths'] as $st): ?><span class="evo-tag"><?=htmlspecialchars($st)?></span><?php endforeach; ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!empty($shape['advice'])): ?><div class="text-xs mt-3" style="color:var(--muted)">💭 <?=htmlspecialchars($shape['advice'])?></div><?php endif; ?>
          <form method="post" style="margin-top:12px">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" name="publish_template" value="1" title="把当前形态脱敏打包（不含任何用户/内容数据），上架到生态商城主题区">📦 脱敏打包上架生态商城</button>
          </form>
        </div>

        <h2 style="font-size:16px;font-weight:800;margin-bottom:12px">📊 平台健康度</h2>
        <div class="evo-card">
          <?php
          $totalSignals = count(SelfEvolve::readPhpErrors()) + count(SelfEvolve::readJsErrors()) + count(SelfEvolve::read404s());
          $healthy = count($critical) === 0 && count($high) === 0;
          ?>
          <div style="display:flex;align-items:center;gap:12px">
            <div style="font-size:40px"><?=$healthy?'🟢':'🟠'?></div>
            <div>
              <div style="font-weight:700"><?=$healthy?'整体健康':'有改进空间'?></div>
              <div class="text-xs text-muted">已采集 <?=$totalSignals?> 个信号 · <?=count($open)?> 个待办</div>
            </div>
          </div>
        </div>

        <div class="evo-card" style="font-size:13px">
          <div class="font-bold mb-2">采集的数据源</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="text-sm">🐞 PHP 错误</div><div class="text-sm text-right"><?=count(SelfEvolve::readPhpErrors())?></div>
            <div class="text-sm">🌐 JS 错误</div><div class="text-sm text-right"><?=count(SelfEvolve::readJsErrors())?></div>
            <div class="text-sm">🧭 404 路由</div><div class="text-sm text-right"><?=count(SelfEvolve::read404s())?></div>
            <div class="text-sm">📄 空模块</div><div class="text-sm text-right"><?=count(SelfEvolve::findEmptyModules())?></div>
          </div>
        </div>

        <h2 style="font-size:16px;font-weight:800;margin:20px 0 12px">📈 形态对比（出厂 vs 现在）</h2>
        <div class="evo-card" style="font-size:13px">
          <div class="text-sm mb-2" style="color:var(--muted)">当前形态：<b style="color:var(--fg)"><?=htmlspecialchars($compare['shape'] ?? '未知')?></b></div>
          <?php foreach ($compare['distribution'] ?? [] as $d): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <span style="width:48px;font-size:12px;color:var(--muted)"><?=htmlspecialchars($d['label'])?></span>
            <div style="flex:1;height:8px;background:var(--hover);border-radius:99px;overflow:hidden"><div style="height:100%;width:<?=(int)$d['pct']?>%;background:var(--accent);border-radius:99px;transition:width .5s"></div></div>
            <span style="width:36px;font-size:12px;text-align:right;color:var(--faint)"><?=(int)$d['pct']?>%</span>
          </div>
          <?php endforeach; ?>
        </div>

        <h2 style="font-size:16px;font-weight:800;margin:20px 0 12px">✅ 修复验证（最近采纳）</h2>
        <?php if (empty($verifications)): ?>
        <div class="evo-card text-muted text-sm" style="padding:16px">采纳建议后，系统会复扫验证修复是否生效。</div>
        <?php else: ?>
        <?php foreach (array_slice($verifications, 0, 5) as $v): ?>
        <div class="evo-history">
          <div class="font-bold text-sm"><?=htmlspecialchars($v['title'] ?? '')?></div>
          <div class="text-xs mt-1" style="color:<?=($v['improved']===true?'var(--ok)':($v['improved']===false?'var(--danger)':'var(--faint)'))?>">
            <?php if ($v['improved'] === true): ?>✅ 已改善（<?=(int)$v['delta']?> 个信号减少）
            <?php elseif ($v['improved'] === false): ?>⚠️ 未见改善，问题可能仍在
            <?php else: ?>… 待复扫验证<?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <h2 style="font-size:16px;font-weight:800;margin:20px 0 12px">🧭 进化时间线</h2>
        <?php if (empty($timeline)): ?>
        <div class="evo-card text-muted text-sm" style="padding:16px">开始使用后，这里会记录你的每次生长。</div>
        <?php else: ?>
        <?php foreach ($timeline as $m): $tIcon = ['scan'=>'🔍','resolve'=>'✅','expire'=>'🗄'][$m['type'] ?? ''] ?? '•'; ?>
        <div class="evo-history" style="display:flex;gap:10px;align-items:flex-start">
          <span style="font-size:15px"><?=$tIcon?></span>
          <div style="min-width:0">
            <div class="font-bold text-sm"><?=htmlspecialchars($m['key'] ?? '')?></div>
            <?php if (!empty($m['detail'])): ?><div class="text-xs text-muted mt-0.5"><?=htmlspecialchars($m['detail'])?></div><?php endif; ?>
            <div class="text-xs" style="color:var(--faint)"><?=date('m-d H:i', $m['ts'] ?? 0)?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <h2 style="font-size:16px;font-weight:800;margin:20px 0 12px">🕘 迭代历史</h2>
        <?php if (empty($history)): ?>
        <div class="evo-card text-muted text-sm" style="padding:20px">暂无已采纳的迭代记录。</div>
        <?php else: ?>
        <?php foreach (array_slice($history, 0, 10) as $h): ?>
        <div class="evo-history">
          <div class="font-bold text-sm">✅ <?=htmlspecialchars($h['title'] ?? '')?></div>
          <div class="text-xs text-muted mt-1"><?=htmlspecialchars($h['resolved_at'] ?? '')?><?=!empty($h['note'])?' · '.htmlspecialchars($h['note']):''?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php admin_footer();
