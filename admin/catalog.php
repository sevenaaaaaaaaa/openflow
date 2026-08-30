<?php
/**
 * 统一商品目录 —— 一处看全平台在卖什么（BACKLOG T1-13）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/UnifiedCatalog.php';
require_login();
require_perm('commerce');

$all = catalog_all();
$sum = catalog_summary($all);
$q = trim((string)($_GET['q'] ?? ''));
$kind = (string)($_GET['kind'] ?? '');
$status = (string)($_GET['status'] ?? '');
$items = catalog_search($all, $q, $kind, $status);

admin_header('统一商品目录');
?>
<div style="max-width:1060px">
  <h1 style="margin:0 0 4px">📦 统一商品目录</h1>
  <p class="v-sub" style="margin:0 0 16px">数字商品、实物、积分、课程分散在三套系统里——这里一处看全。编辑仍回各自后台，先把「平台在卖什么」看清楚。</p>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px">
    <div class="card" style="padding:14px"><div style="font-size:22px;font-weight:800"><?=$sum['total']?></div><div style="font-size:12px;color:var(--faint)">商品总数</div></div>
    <div class="card" style="padding:14px"><div style="font-size:22px;font-weight:800;color:#16a34a"><?=$sum['active']?></div><div style="font-size:12px;color:var(--faint)">在售</div></div>
    <div class="card" style="padding:14px"><div style="font-size:22px;font-weight:800;color:<?=$sum['out_of_stock']?'#dc2626':'inherit'?>"><?=$sum['out_of_stock']?></div><div style="font-size:12px;color:var(--faint)">缺货</div></div>
    <div class="card" style="padding:14px"><div style="font-size:22px;font-weight:800"><?=$sum['creators']?></div><div style="font-size:12px;color:var(--faint)">创作者</div></div>
  </div>

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="搜标题 / 作者 / ID" style="flex:1;min-width:180px">
    <select name="kind"><option value="">全部类型</option><?php foreach (catalog_kinds() as $k=>$v): ?><option value="<?=$k?>" <?=$kind===$k?'selected':''?>><?=$v?>（<?=$sum['by_kind'][$k] ?? 0?>）</option><?php endforeach; ?></select>
    <select name="status"><option value="">全部状态</option><option value="active" <?=$status==='active'?'selected':''?>>在售</option><option value="draft" <?=$status==='draft'?'selected':''?>>草稿</option><option value="archived" <?=$status==='archived'?'selected':''?>>已下架</option></select>
    <button class="btn btn-primary btn-sm">筛选</button>
  </form>

  <?php if (!$items): ?>
    <div class="card" style="padding:30px;text-align:center;color:var(--faint)">没有匹配的商品。</div>
  <?php else: ?>
  <div class="card" style="padding:0;overflow:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="text-align:left;border-bottom:1px solid var(--border)">
        <th style="padding:10px 12px">商品</th><th style="padding:10px">类型</th><th style="padding:10px">价格</th>
        <th style="padding:10px">创作者</th><th style="padding:10px">库存</th><th style="padding:10px">状态</th><th style="padding:10px"></th>
      </tr></thead>
      <tbody>
      <?php foreach (array_slice($items, 0, 200) as $i): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:10px 12px"><strong><?=htmlspecialchars($i['title'] ?: $i['id'])?></strong></td>
          <td style="padding:10px;color:var(--faint)"><?=htmlspecialchars(catalog_kinds()[$i['kind']] ?? $i['kind'])?></td>
          <td style="padding:10px"><?=$i['kind']==='points' ? (int)$i['points'].' 积分' : '¥'.number_format($i['price'],2)?></td>
          <td style="padding:10px;color:var(--faint)"><?=htmlspecialchars($i['author'] ?: '—')?></td>
          <td style="padding:10px;color:<?=($i['stock']!==null&&$i['stock']<=0)?'#dc2626':'inherit'?>"><?=$i['stock']===null?'—':(int)$i['stock']?></td>
          <td style="padding:10px"><span style="font-size:11px;padding:1px 8px;border-radius:999px;background:<?=$i['status']==='active'?'#dcfce7':'#f1f5f9'?>;color:<?=$i['status']==='active'?'#166534':'#64748b'?>"><?=htmlspecialchars($i['status'])?></span></td>
          <td style="padding:10px"><?php if ($i['edit_url']): ?><a href="<?=htmlspecialchars($i['edit_url'])?>" class="btn btn-ghost btn-sm">管理</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
