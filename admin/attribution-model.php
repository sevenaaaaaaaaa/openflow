<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('analytics');
admin_header('多触点归因');
$models = AttributionModel::models();
$selectedModel = $_GET['model'] ?? 'linear';
$dateRange = $_GET['range'] ?? '30';
$startDate = date('Y-m-d', strtotime("-{$dateRange} days"));
$endDate = date('Y-m-d');
$stats = AttributionModel::batchStats($selectedModel, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
$totalAttrib = array_sum($stats);
$touchpointsFile = DATA_DIR . '/attribution_touchpoints.json';
$allTouchpoints = json_read($touchpointsFile);
$recentTouchpoints = array_slice(array_reverse($allTouchpoints), 0, 50);
?>
<div class="admin-layout">
  <?php admin_sidebar('attribution-model'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 多触点归因</h1>
      <div class="flex gap-2 ml-auto">
        <span class="badge" style="background:var(--accent);color:var(--on-accent);padding:4px 12px;border-radius:999px;font-size:13px"><?=count($allTouchpoints)?> 个触点</span>
      </div>
    </div>
    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px;margin-bottom:24px">
      <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <div>
          <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">归因模型</label>
          <select name="model" style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
            <?php foreach ($models as $key => $label): ?>
              <option value="<?=$key?>" <?=selected($key, $selectedModel)?>><?=$label?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">时间范围</label>
          <select name="range" style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
            <option value="7" <?=selected('7', $dateRange)?>>近 7 天</option>
            <option value="30" <?=selected('30', $dateRange)?>>近 30 天</option>
            <option value="90" <?=selected('90', $dateRange)?>>近 90 天</option>
            <option value="365" <?=selected('365', $dateRange)?>>近 1 年</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">分析</button>
      </form>
    </div>

    <?php if (!empty($stats)): ?>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:24px">
        <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px">
          <h3 style="margin:0 0 16px;font-size:16px;color:var(--text)">渠道归因占比</h3>
          <?php foreach ($stats as $src => $pct): ?>
            <?php $pctVal = $totalAttrib > 0 ? round($pct / $totalAttrib * 100, 1) : 0; ?>
            <div style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:14px;font-weight:500;color:var(--text)"><?=h($src)?></span>
                <span style="font-size:13px;color:var(--muted)"><?=$pctVal?>%</span>
              </div>
              <div style="height:8px;background:var(--surface-2);border-radius:4px;overflow:hidden">
                <div style="height:100%;width:<?=$pctVal?>%;background:var(--accent);border-radius:4px;transition:.3s"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px">
          <h3 style="margin:0 0 16px;font-size:16px;color:var(--text)">归因说明</h3>
          <div style="font-size:14px;color:var(--text);line-height:1.7">
            <div style="padding:12px;background:var(--surface-2);border-radius:8px;margin-bottom:8px">
              <strong><?=h($models[$selectedModel] ?? $selectedModel)?></strong>
            </div>
            <?php if ($selectedModel === 'first_touch'): ?>
              将转化价值 100% 归因给用户的第一个触点来源。适合评估获客渠道效果。
            <?php elseif ($selectedModel === 'last_touch'): ?>
              将转化价值 100% 归因给转化前的最后一个触点。适合评估转化临门一脚的渠道。
            <?php elseif ($selectedModel === 'linear'): ?>
              所有触点平均分配转化价值。适合衡量每个渠道的均衡贡献。
            <?php elseif ($selectedModel === 'time_decay'): ?>
              越接近转化时刻的触点获得越高的权重。半衰期 7 天，适合长周期决策场景。
            <?php elseif ($selectedModel === 'u_shaped'): ?>
              首次触点 40%，末次触点 40%，中间触点平分 20%。兼顾获客与转化。
            <?php elseif ($selectedModel === 'w_shaped'): ?>
              首次 30%，关键中间触点 30%，末次 30%，其余平分 10%。三阶段归因模型。
            <?php endif; ?>
          </div>
          <div style="margin-top:16px;padding:12px;background:var(--surface-2);border-radius:8px;font-size:13px;color:var(--muted)">
            分析范围：<?=$startDate?> ~ <?=$endDate?> (<?=$dateRange?> 天)
          </div>
        </div>
      </div>

      <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden;margin-bottom:24px">
        <div style="padding:16px 20px;background:var(--surface-2);border-bottom:1px solid var(--border)">
          <h3 style="margin:0;font-size:16px">渠道排名</h3>
        </div>
        <table style="width:100%;border-collapse:collapse">
          <thead><tr>
            <th style="padding:12px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">排名</th>
            <th style="padding:12px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">渠道来源</th>
            <th style="padding:12px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">归因分数</th>
            <th style="padding:12px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">占比</th>
          </tr></thead>
          <tbody>
          <?php $rank = 1; foreach ($stats as $src => $score): ?>
            <?php $pctVal = $totalAttrib > 0 ? round($score / $totalAttrib * 100, 1) : 0; ?>
            <tr style="border-top:1px solid var(--border)">
              <td style="padding:12px 20px;font-size:14px;font-weight:700;color:<?=$rank<=3?'var(--accent)':'var(--muted)'?>">#<?=$rank++?></td>
              <td style="padding:12px 20px;font-size:14px"><?=h($src)?></td>
              <td style="padding:12px 20px;font-size:14px;font-weight:600"><?=round($score, 1)?></td>
              <td style="padding:12px 20px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="flex:1;height:6px;background:var(--surface-2);border-radius:3px;overflow:hidden;max-width:200px">
                    <div style="height:100%;width:<?=$pctVal?>%;background:var(--accent);border-radius:3px"></div>
                  </div>
                  <span style="font-size:13px;color:var(--muted)"><?=$pctVal?>%</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden">
      <div style="padding:16px 20px;background:var(--surface-2);border-bottom:1px solid var(--border)">
        <h3 style="margin:0;font-size:16px">最近触点记录</h3>
      </div>
      <table style="width:100%;border-collapse:collapse">
        <thead><tr>
          <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">用户</th>
          <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">来源</th>
          <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">媒介</th>
          <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">活动</th>
          <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">事件</th>
          <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">时间</th>
        </tr></thead>
        <tbody>
        <?php if (empty($recentTouchpoints)): ?>
          <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">暂无触点数据，请先通过 CDP 追踪脚本采集数据</td></tr>
        <?php else: foreach ($recentTouchpoints as $tp): ?>
          <tr style="border-top:1px solid var(--border)">
            <td style="padding:10px 20px;font-size:13px"><?=h($tp['user_id'] ?? '')?></td>
            <td style="padding:10px 20px"><span style="padding:2px 8px;border-radius:6px;background:var(--accent);color:var(--on-accent);font-size:12px"><?=h($tp['source'] ?? '')?></span></td>
            <td style="padding:10px 20px;font-size:13px"><?=h($tp['medium'] ?? '-')?></td>
            <td style="padding:10px 20px;font-size:13px"><?=h($tp['campaign'] ?? '-')?></td>
            <td style="padding:10px 20px;font-size:13px"><?=h($tp['event'] ?? '')?></td>
            <td style="padding:10px 20px;font-size:12px;color:var(--muted)"><?=h($tp['timestamp'] ?? '')?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
