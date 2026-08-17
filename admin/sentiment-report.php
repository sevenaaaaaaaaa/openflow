<?php
/**
 * 舆情报告页（被 sentiment.php include）
 * 需在包含前定义：$topic, $rows, $pos, $neg, $neu, $total, $sources, $topWords, $sentimentPct, $risk
 */
if (!isset($topic)) exit;

// CSV 导出
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sentiment-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['时间', '来源', '标题', 'URL', '摘要', '情感']);
    foreach ($rows as $r) fputcsv($fp, [$r['created_at'] ?? '', $r['source'] ?? '', $r['title'] ?? '', $r['url'] ?? '', $r['snippet'] ?? '', $r['sentiment'] ?? '']);
    fclose($fp);
    exit;
}
?>
<div class="admin-layout">
  <?php admin_sidebar('sentiment'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0"> 舆情报告</h1>
      <a href="sentiment.php" class="btn btn-ghost btn-sm ml-auto">← 返回</a>
      <a href="?report=<?=urlencode($topic['id'])?>&export=1" class="btn btn-primary btn-sm">📥 导出 CSV</a>
    </div>
    <p class="sub">主题：<?=htmlspecialchars($topic['name'])?> · 共 <?=$total?> 条数据</p>

    <!-- 总览指标 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px">
      <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">情感指数</div><div style="font-size:28px;font-weight:800;color:<?=$sentimentPct>=50?'var(--ok)':($sentimentPct>=30?'var(--warn)':'var(--danger)')?>"><?=$sentimentPct?>%</div><div style="font-size:11px;color:var(--text-3)">正面占比</div></div>
      <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">正面</div><div style="font-size:24px;font-weight:700;color:var(--ok)"><?=$pos?></div></div>
      <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">负面</div><div style="font-size:24px;font-weight:700;color:var(--danger)"><?=$neg?></div></div>
      <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">中性</div><div style="font-size:24px;font-weight:700;color:var(--warn)"><?=$neu?></div></div>
      <div class="card" style="text-align:center;grid-column:span 2"><div style="font-size:12px;color:var(--text-3)">风险提示</div><div style="font-size:15px;font-weight:600;margin-top:6px"><?=htmlspecialchars($risk)?></div></div>
    </div>

    <!-- AI 舆情分析 -->
    <?php
    $aiSentiment = null;
    try {
        require_once __DIR__ . '/../lib/AIBusiness.php';
        $aiResults = [];
        foreach (array_slice($rows, 0, 20) as $r) $aiResults[] = ['title' => $r['title'] ?? '', 'url' => $r['url'] ?? ''];
        $aiSentiment = AIBusiness::analyzeSentiment($topic['name'] ?? '舆情', $aiResults);
    } catch (Exception $e) {}
    if ($aiSentiment):
    ?>
    <div class="card" style="margin-bottom:20px;padding:20px;background:linear-gradient(135deg,var(--accent-soft),transparent)">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h2 style="margin:0">🤖 AI 舆情分析</h2>
        <?php if ($aiSentiment['ai']): ?><span class="badge badge-green">AI 生成</span><?php else: ?><span class="badge badge-gray">规则分析</span><?php endif; ?>
      </div>
      <div style="font-size:14px;line-height:1.8"><?=htmlspecialchars($aiSentiment['summary'] ?? '')?></div>
      <?php if (!empty($aiSentiment['tone'])): ?>
      <div style="margin-top:8px;font-size:13px;color:var(--text-3)">情感倾向：<strong><?=htmlspecialchars($aiSentiment['tone'])?></strong></div>
      <?php endif; ?>
      <?php if (!empty($aiSentiment['hot_points'])): ?>
      <div style="margin-top:8px;font-size:13px">热点：<?=implode(' · ', array_map('htmlspecialchars', $aiSentiment['hot_points']))?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>


    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="rep-grid">
      <!-- 情感分布 -->
      <div class="card">
        <h2>😊 情感分布</h2>
        <div style="display:flex;height:30px;border-radius:8px;overflow:hidden;margin-bottom:10px">
          <div style="width:<?=$total>0?round($pos/$total*100):0?>%;background:var(--ok)"></div>
          <div style="width:<?=$total>0?round($neu/$total*100):0?>%;background:var(--warn)"></div>
          <div style="width:<?=$total>0?round($neg/$total*100):0?>%;background:var(--danger)"></div>
        </div>
        <div style="display:flex;gap:16px;font-size:13px">
          <span><b style="color:var(--ok)">■</b> 正面 <?=round($pos/max(1,$total)*100)?>%</span>
          <span><b style="color:var(--warn)">■</b> 中性 <?=round($neu/max(1,$total)*100)?>%</span>
          <span><b style="color:var(--danger)">■</b> 负面 <?=round($neg/max(1,$total)*100)?>%</span>
        </div>
      </div>

      <!-- 媒体来源 -->
      <div class="card">
        <h2>📡 媒体来源分布</h2>
        <?php if (empty($sources)): ?><div class="empty" style="padding:16px">暂无数据</div>
        <?php else: $maxS = max($sources) ?: 1; foreach ($sources as $s => $c): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <span style="font-size:12px;width:70px"><?=htmlspecialchars($s)?></span>
          <div style="flex:1;height:16px;background:var(--surface-2);border-radius:4px;overflow:hidden"><div style="height:100%;width:<?=round($c/$maxS*100)?>%;background:#7dd3fc"></div></div>
          <span style="font-size:12px;width:30px;text-align:right"><?=$c?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- 热词 -->
    <div class="card" style="margin-bottom:20px">
      <h2>🔥 高频热词</h2>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($topWords as $w => $c): $fs = 14 + min(8, $c); ?>
        <span style="font-size:<?=$fs?>px;padding:4px 10px;border-radius:999px;background:var(--surface-2);color:var(--text)"><?=htmlspecialchars($w)?> <b style="color:var(--text-3)"><?=$c?></b></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 详细结果 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📄 采集明细（<?=count($rows)?> 条）</h2>
      <table>
        <thead><tr><th>情感</th><th>标题</th><th>来源</th><th>摘要</th><th>链接</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?><tr><td colspan="5" class="empty">暂无数据，先点「采集」</td></tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge <?=['正面'=>'badge-green','负面'=>'badge-red','中性'=>'badge-yellow'][$r['sentiment']]??'badge-gray'?>" style="font-size:11px"><?=htmlspecialchars($r['sentiment'])?></span></td>
            <td style="max-width:220px"><strong><?=htmlspecialchars($r['title'])?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($r['source'])?></td>
            <td class="text-sm text-muted" style="max-width:280px"><?=htmlspecialchars(mb_substr($r['snippet']??'',0,80))?></td>
            <td><a href="<?=htmlspecialchars($r['url'])?>" target="_blank" rel="noopener" class="text-sm" style="color:var(--accent)">查看 →</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.rep-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
