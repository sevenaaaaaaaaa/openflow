<?php
/**
 * 投放管理 — 计划 / 素材 / 指标 / ROI
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AdCampaign.php';
require_login();
require_perm('settings');

$campaigns = adc_all();
$message = '';

// 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = trim($_POST['id'] ?? '');
    $camp = [
        'id' => $id,
        'name' => trim($_POST['name'] ?? ''),
        'platform' => trim($_POST['platform'] ?? ''),
        'status' => in_array($_POST['status'] ?? '', ['running','paused','ended'], true) ? $_POST['status'] : 'running',
        'budget' => (float)($_POST['budget'] ?? 0),
        'start_date' => trim($_POST['start_date'] ?? ''),
        'end_date' => trim($_POST['end_date'] ?? ''),
        'landing_url' => trim($_POST['landing_url'] ?? ''),
        'creative' => trim($_POST['creative'] ?? ''),
        'aov' => (float)($_POST['aov'] ?? 0),
        'metrics' => [
            'cost' => (float)($_POST['cost'] ?? 0),
            'impressions' => (int)($_POST['impressions'] ?? 0),
            'clicks' => (int)($_POST['clicks'] ?? 0),
            'conversions' => (int)($_POST['conversions'] ?? 0),
        ],
        'note' => trim($_POST['note'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($camp['name'])) { $message = '计划名称必填'; }
    else {
        $found = false;
        foreach ($campaigns as &$c) if (($c['id'] ?? '') === $id) { $c = $camp; $found = true; break; }
        unset($c);
        if (!$found) { $camp['created_at'] = date('Y-m-d H:i:s'); $campaigns[] = $camp; }
        adc_save($campaigns);
        $message = '投放计划已保存';
    }
}
if (isset($_GET['delete'])) {
    $campaigns = array_values(array_filter($campaigns, fn($c) => ($c['id'] ?? '') !== $_GET['delete']));
    adc_save($campaigns);
    header('Location: /xmp/ad-campaigns');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) { foreach ($campaigns as $c) if (($c['id'] ?? '') === $_GET['edit']) { $edit = $c; break; } }

$dam = json_read(DATA_DIR . '/dam.json');
$damAssets = [];
foreach ($dam as $type => $items) foreach ($items as $a) $damAssets[] = ['type' => $type, 'file' => $a['file'] ?? ''];

admin_header('投放管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('ad-campaigns'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>投放管理</h1><p class="v-sub">投放计划 · 素材创意 · 平台指标 · ROI 归因（转化对账 CAPI）</p></div>
      <?php if (!$edit): ?><div class="v-actions"><a href="?edit=new" class="btn btn-s btn-sm">+ 新建计划</a></div><?php endif; ?>
    </div>
    <?php if ($message): ?><?=msg($message === '投放计划已保存' ? 'success' : 'error', $message)?><?php endif; ?>

    <?php if ($edit): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'] ?? 'c_' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 6))?>">
      <h2 style="margin-bottom:16px"><?=($edit['id'] ?? '') ? '编辑投放计划' : '新建投放计划'?></h2>
      <div class="field-row">
        <div class="field" style="flex:2"><label>计划名称 *</label><input type="text" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" placeholder="如：新品上市巨量引流"></div>
        <div class="field"><label>平台</label><select name="platform"><?php foreach (adc_platforms() as $p): ?><option <?=($edit['platform'] ?? '')===$p?'selected':''?>><?=$p?></option><?php endforeach; ?></select></div>
        <div class="field"><label>状态</label><select name="status"><option value="running" <?=($edit['status'] ?? '')==='running'?'selected':''?>>投放中</option><option value="paused" <?=($edit['status'] ?? '')==='paused'?'selected':''?>>暂停</option><option value="ended" <?=($edit['status'] ?? '')==='ended'?'selected':''?>>已结束</option></select></div>
      </div>
      <div class="field-row">
        <div class="field"><label>预算（元）</label><input type="number" name="budget" value="<?=htmlspecialchars($edit['budget'] ?? 0)?>" min="0" step="0.01"></div>
        <div class="field"><label>开始日期</label><input type="date" name="start_date" value="<?=htmlspecialchars(substr($edit['start_date'] ?? '', 0, 10))?>"></div>
        <div class="field"><label>结束日期</label><input type="date" name="end_date" value="<?=htmlspecialchars(substr($edit['end_date'] ?? '', 0, 10))?>"></div>
      </div>
      <div class="field-row">
        <div class="field" style="flex:2"><label>落地页 URL</label><input type="text" name="landing_url" value="<?=htmlspecialchars($edit['landing_url'] ?? '')?>" placeholder="https://…/lp/xxx?utm_source=…"></div>
        <div class="field"><label>客单价（元）<span class="hint">· 用于 ROI 估算</span></label><input type="number" name="aov" value="<?=htmlspecialchars($edit['aov'] ?? 0)?>" min="0" step="0.01"></div>
      </div>
      <div class="field"><label>创意素材 <span class="hint">· 从 DAM 选择</span></label>
        <select name="creative" style="width:100%"><option value="">— 无 / 外部素材 —</option><?php foreach ($damAssets as $da): ?><option value="<?=htmlspecialchars($da['file'])?>" <?=($edit['creative'] ?? '')===$da['file']?'selected':''?>><?=htmlspecialchars($da['type'] . ' / ' . basename($da['file']))?></option><?php endforeach; ?></select>
      </div>
      <h2 style="margin:18px 0 10px">📊 平台指标（可从平台报表录入）</h2>
      <div class="field-row">
        <div class="field"><label>花费（元）</label><input type="number" name="cost" value="<?=htmlspecialchars($edit['metrics']['cost'] ?? 0)?>" min="0" step="0.01"></div>
        <div class="field"><label>曝光</label><input type="number" name="impressions" value="<?=htmlspecialchars($edit['metrics']['impressions'] ?? 0)?>" min="0"></div>
        <div class="field"><label>点击</label><input type="number" name="clicks" value="<?=htmlspecialchars($edit['metrics']['clicks'] ?? 0)?>" min="0"></div>
        <div class="field"><label>转化数</label><input type="number" name="conversions" value="<?=htmlspecialchars($edit['metrics']['conversions'] ?? 0)?>" min="0"></div>
      </div>
      <div class="field"><label>备注</label><textarea name="note" rows="2" placeholder="投放策略 / 人群定向 / 备注…"><?=htmlspecialchars($edit['note'] ?? '')?></textarea></div>
      <div style="display:flex;gap:12px;align-items:center"><button class="btn btn-s btn-sm">保存计划</button><a href="ad-campaigns.php" class="btn btn-ghost btn-sm">取消</a></div>
    </form>
    <?php else: ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>计划</th><th>平台</th><th>状态</th><th>花费</th><th>转化</th><th>CPC</th><th>CPA</th><th>ROI</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($campaigns)): ?><tr><td colspan="9" style="text-align:center;color:var(--faint)">暂无投放计划，点击右上角新建</td></tr><?php endif; ?>
          <?php foreach ($campaigns as $c): $roi = adc_compute_roi($c); ?>
          <tr>
            <td><b><?=htmlspecialchars($c['name'])?></b><div style="font-size:11px;color:var(--faint)"><?=htmlspecialchars($c['platform'] ?? '')?> · <?=htmlspecialchars(substr($c['start_date'] ?? '', 0, 10))?></div></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($c['platform'] ?? '')?></span></td>
            <td><span class="badge <?=['running'=>'badge-green','paused'=>'badge-yellow','ended'=>'badge-gray'][$c['status'] ?? ''] ?? 'badge-gray'?>"><?=['running'=>'投放中','paused'=>'暂停','ended'=>'已结束'][$c['status'] ?? ''] ?? $c['status']?></span></td>
            <td class="mono">¥<?=number_format($roi['cost'], 0)?></td>
            <td class="mono"><?=$roi['conversions']?></td>
            <td class="mono text-sm"><?=$roi['cpc'] !== null ? '¥' . $roi['cpc'] : '—'?></td>
            <td class="mono text-sm"><?=$roi['cpa'] !== null ? '¥' . $roi['cpa'] : '—'?></td>
            <td style="font-weight:700;color:<?=$roi['roi'] === null ? 'var(--muted)' : ($roi['roi'] >= 0 ? 'var(--ok)' : 'var(--danger)')?>"><?=$roi['roi'] === null ? '—' : ($roi['roi'] >= 0 ? '+' : '') . $roi['roi'] . '%'?></td>
            <td style="white-space:nowrap"><a href="?edit=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="?delete=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" data-confirm="删除?">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 投放汇总 -->
    <?php if (!empty($campaigns)): $tCost = 0; $tConv = 0; $tRev = 0; foreach ($campaigns as $c) { $r = adc_compute_roi($c); $tCost += $r['cost']; $tConv += $r['conversions']; $tRev += $r['revenue']; } ?>
    <div class="card" style="margin-top:16px">
      <h2 style="margin-bottom:12px">📈 投放汇总</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
        <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-2xl font-bold mono">¥<?=number_format($tCost, 0)?></div><div class="text-sm text-muted">总花费</div></div>
        <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-2xl font-bold mono"><?=$tConv?></div><div class="text-sm text-muted">总转化</div></div>
        <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-2xl font-bold mono" style="color:var(--ok)">¥<?=number_format($tRev, 0)?></div><div class="text-sm text-muted">估算收入（转化×客单价）</div></div>
        <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-2xl font-bold mono" style="color:<?=($tCost > 0 && $tRev >= $tCost) ? 'var(--ok)' : 'var(--warn)'?>"><?=$tCost > 0 ? round(($tRev - $tCost) / $tCost * 100, 0) . '%' : '—'?></div><div class="text-sm text-muted">整体 ROI</div></div>
      </div>
      <p class="text-sm text-muted" style="margin-top:12px">💡 数据闭环：转化数录入后，叠加 CAPI 回传（购买/线索）到广告平台自动优化；素材在「数字资产管理 DAM」维护，落地页用 UTM 归因。</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
