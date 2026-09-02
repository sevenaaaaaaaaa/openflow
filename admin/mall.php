<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MallSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';

require_login();
if (!has_perm('shop-settings') && !has_perm('settings')) { http_response_code(403); exit('无权限'); }

$message = '';
$error = '';
$tab = $_GET['tab'] ?? 'products';

// ─── 实体商品 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    csrf_verify();
    $p = [
        'id' => trim($_POST['product_id'] ?? '') ?: ('p_' . substr(md5(uniqid('', true)), 0, 8)),
        'title' => trim($_POST['title'] ?? ''),
        'desc' => trim($_POST['desc'] ?? ''),
        'price' => (float)($_POST['price'] ?? 0),
        'stock' => (int)($_POST['stock'] ?? 0),
        'image' => trim($_POST['image'] ?? ''),
        'shipping' => trim($_POST['shipping'] ?? ''),
    ];
    if ($p['title'] && $p['price'] > 0) { mall_product_save($p); $message = '商品已保存'; }
    else $error = '请填写标题和价格';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    csrf_verify();
    mall_product_delete($_POST['delete_product']);
    $message = '商品已删除';
}

// ─── 积分商品 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_points'])) {
    csrf_verify();
    $p = [
        'id' => trim($_POST['pid'] ?? '') ?: ('pp_' . substr(md5(uniqid('', true)), 0, 8)),
        'title' => trim($_POST['points_title'] ?? ''),
        'desc' => trim($_POST['points_desc'] ?? ''),
        'points' => (int)($_POST['points'] ?? 0),
        'stock' => (int)($_POST['points_stock'] ?? 0),
        'image' => trim($_POST['points_image'] ?? ''),
    ];
    if ($p['title'] && $p['points'] > 0) { mall_points_product_save($p); $message = '积分商品已保存'; }
    else $error = '请填写标题和积分';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_points'])) {
    csrf_verify();
    mall_points_product_delete($_POST['delete_points']);
    $message = '积分商品已删除';
}

$products = mall_products();
$pointsProducts = mall_points_products();
$redemptions = mall_redemptions();

// 编辑态：?edit=<id>（实体）/ ?edit_points=<id>（积分）；?new=1 打开空表单
$editP = null; $editPP = null;
if (!empty($_GET['edit'])) foreach ($products as $x) if ($x['id'] === $_GET['edit']) { $editP = $x; break; }
if (!empty($_GET['edit_points'])) foreach ($pointsProducts as $x) if ($x['id'] === $_GET['edit_points']) { $editPP = $x; break; }
$openForm = $editP || $editPP || isset($_GET['new']) || $error;

admin_header('商城管理');
?>
<style>
.ml-head{display:flex;align-items:flex-start;gap:16px;margin-bottom:6px}
.ml-head .sub{margin-bottom:0}
.ml-head .btn{margin-left:auto;margin-top:6px;flex:0 0 auto}
.ml-form{margin-bottom:16px}
.ml-form h2{display:flex;align-items:center;gap:10px}
.ml-form h2 .hint{font-weight:400;font-size:12px;color:var(--faint)}
.ml-form .acts{display:flex;gap:8px;margin-top:4px}
.ml-img{width:44px;height:44px;border-radius:9px;object-fit:cover;background:var(--hover);flex:0 0 auto;display:grid;place-items:center;color:var(--faint)}
.ml-img svg{width:18px;height:18px}
.ml-stock{font-family:var(--font-mono)}
.ml-stock.low{color:var(--warn)}.ml-stock.out{color:var(--danger)}
.ml-price{font-family:var(--font-mono);font-weight:800}
</style>
<div class="admin-layout">
  <?php admin_sidebar('shop-settings'); ?>
  <div class="main">
<div class="ml-head">
  <div><h1>商城</h1><p class="sub">实体商品、积分商城与兑换记录；价格与库存改完立即生效</p></div>
  <?php if ($tab === 'products' && !$openForm): ?><a href="?tab=products&new=1" class="btn btn-primary">新增商品</a>
  <?php elseif ($tab === 'points' && !$openForm): ?><a href="?tab=points&new=1" class="btn btn-primary">新增积分商品</a><?php endif; ?>
</div>

<?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
<?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

<div class="tabs" style="margin-bottom:16px">
  <a href="?tab=products" class="<?=$tab==='products'?'active':''?>">实体商品 <span class="text-muted">(<?=count($products)?>)</span></a>
  <a href="?tab=points" class="<?=$tab==='points'?'active':''?>">积分商城 <span class="text-muted">(<?=count($pointsProducts)?>)</span></a>
  <a href="?tab=redemptions" class="<?=$tab==='redemptions'?'active':''?>">兑换记录 <span class="text-muted">(<?=count($redemptions)?>)</span></a>
</div>

<?php if ($tab === 'products'): ?>
<?php if ($openForm && !$editPP): ?>
<div class="card ml-form">
  <h2><?=$editP ? '编辑商品' : '新增实体商品'?> <?php if ($editP): ?><span class="hint">ID <?=htmlspecialchars($editP['id'])?></span><?php endif; ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?=htmlspecialchars($editP['id'] ?? '')?>">
    <div class="field-row">
      <div class="field"><label>商品标题 <span class="hint">· 必填</span></label><input type="text" name="title" class="inp" required value="<?=htmlspecialchars($editP['title'] ?? '')?>" autofocus></div>
      <div class="field"><label>运费说明</label><input type="text" name="shipping" class="inp" placeholder="包邮 / ¥10" value="<?=htmlspecialchars($editP['shipping'] ?? '')?>"></div>
    </div>
    <div class="field"><label>描述</label><textarea name="desc" class="inp" rows="2"><?=htmlspecialchars($editP['desc'] ?? '')?></textarea></div>
    <div class="field-row">
      <div class="field"><label>价格 (¥) <span class="hint">· 必填</span></label><input type="number" name="price" class="inp" step="0.01" min="0.01" required value="<?=htmlspecialchars($editP['price'] ?? '')?>"></div>
      <div class="field"><label>库存 <span class="hint">· 0 = 售罄</span></label><input type="number" name="stock" class="inp" value="<?=htmlspecialchars($editP['stock'] ?? 0)?>" min="0"></div>
    </div>
    <div class="field"><label>图片 URL</label><input type="text" name="image" class="inp" placeholder="https://…/product.png" value="<?=htmlspecialchars($editP['image'] ?? '')?>"></div>
    <div class="acts"><button type="submit" name="save_product" value="1" class="btn btn-primary">保存商品</button><a href="?tab=products" class="btn btn-ghost">取消</a></div>
  </form>
</div>
<?php endif; ?>

<div class="card lst-card">
  <?php if (empty($products)): ?><div class="of-empty" style="border:0;margin:0;padding:40px">还没有实体商品。<a href="?tab=products&new=1">新增第一个</a>，填标题、价格、库存即可上架。</div>
  <?php else: ?>
  <table class="lst-table">
    <thead><tr><th class="c-title">商品</th><th style="width:120px">价格</th><th style="width:90px">库存</th><th style="width:140px">运费</th><th class="c-act" style="width:150px"></th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($products) as $p): $stk = (int)($p['stock'] ?? 0); ?>
      <tr>
        <td class="c-title"><div class="lst-item">
          <?php if (!empty($p['image'])): ?><img class="ml-img" src="<?=htmlspecialchars($p['image'])?>" alt="" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'ml-img'}))"><?php else: ?><span class="ml-img"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg></span><?php endif; ?>
          <div class="lst-body"><div class="lst-title"><?=htmlspecialchars($p['title'])?></div><?php if (!empty($p['desc'])): ?><div class="lst-sub"><span class="text-sm text-muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($p['desc'])?></span></div><?php endif; ?></div>
        </div></td>
        <td class="ml-price">¥<?=number_format($p['price'], 2)?></td>
        <td><span class="ml-stock <?=$stk===0?'out':($stk<=5?'low':'')?>"><?=$stk===0?'售罄':$stk?></span></td>
        <td class="text-sm text-muted"><?=htmlspecialchars($p['shipping'] ?? '') ?: '—'?></td>
        <td class="c-act">
          <a href="?tab=products&edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
          <form method="post" style="display:inline" data-confirm="下架并删除「<?=htmlspecialchars($p['title'], ENT_QUOTES)?>」？已有订单不受影响。">
            <?= csrf_field() ?>
            <button type="submit" name="delete_product" value="<?=htmlspecialchars($p['id'])?>" class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'points'): ?>
<?php if ($openForm && !$editP): ?>
<div class="card ml-form">
  <h2><?=$editPP ? '编辑积分商品' : '新增积分商品'?> <?php if ($editPP): ?><span class="hint">ID <?=htmlspecialchars($editPP['id'])?></span><?php endif; ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="pid" value="<?=htmlspecialchars($editPP['id'] ?? '')?>">
    <div class="field"><label>商品标题 <span class="hint">· 必填</span></label><input type="text" name="points_title" class="inp" required value="<?=htmlspecialchars($editPP['title'] ?? '')?>" autofocus></div>
    <div class="field"><label>描述</label><textarea name="points_desc" class="inp" rows="2"><?=htmlspecialchars($editPP['desc'] ?? '')?></textarea></div>
    <div class="field-row">
      <div class="field"><label>所需积分 <span class="hint">· 必填</span></label><input type="number" name="points" class="inp" min="1" required value="<?=htmlspecialchars($editPP['points'] ?? '')?>"></div>
      <div class="field"><label>库存 <span class="hint">· 0 = 兑完</span></label><input type="number" name="points_stock" class="inp" value="<?=htmlspecialchars($editPP['stock'] ?? 0)?>" min="0"></div>
    </div>
    <div class="field"><label>图片 URL</label><input type="text" name="points_image" class="inp" value="<?=htmlspecialchars($editPP['image'] ?? '')?>"></div>
    <div class="acts"><button type="submit" name="save_points" value="1" class="btn btn-primary">保存积分商品</button><a href="?tab=points" class="btn btn-ghost">取消</a></div>
  </form>
</div>
<?php endif; ?>

<div class="card lst-card">
  <?php if (empty($pointsProducts)): ?><div class="of-empty" style="border:0;margin:0;padding:40px">还没有积分商品。<a href="?tab=points&new=1">新增第一个</a>，会员可用积分兑换。</div>
  <?php else: ?>
  <table class="lst-table">
    <thead><tr><th class="c-title">商品</th><th style="width:130px">所需积分</th><th style="width:90px">库存</th><th class="c-act" style="width:150px"></th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($pointsProducts) as $p): $stk = (int)($p['stock'] ?? 0); ?>
      <tr>
        <td class="c-title"><div class="lst-item">
          <?php if (!empty($p['image'])): ?><img class="ml-img" src="<?=htmlspecialchars($p['image'])?>" alt=""><?php else: ?><span class="ml-img"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8c-2-3-6-3-6-1s3 1 6 1Zm0 0c2-3 6-3 6-1s-3 1-6 1Z"/></svg></span><?php endif; ?>
          <div class="lst-body"><div class="lst-title"><?=htmlspecialchars($p['title'])?></div><?php if (!empty($p['desc'])): ?><div class="lst-sub"><span class="text-sm text-muted"><?=htmlspecialchars($p['desc'])?></span></div><?php endif; ?></div>
        </div></td>
        <td class="ml-price"><?=$p['points']?> <span class="text-muted" style="font-weight:500;font-size:12px">积分</span></td>
        <td><span class="ml-stock <?=$stk===0?'out':($stk<=5?'low':'')?>"><?=$stk===0?'兑完':$stk?></span></td>
        <td class="c-act">
          <a href="?tab=points&edit_points=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
          <form method="post" style="display:inline" data-confirm="删除积分商品「<?=htmlspecialchars($p['title'], ENT_QUOTES)?>」？">
            <?= csrf_field() ?>
            <button type="submit" name="delete_points" value="<?=htmlspecialchars($p['id'])?>" class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'redemptions'): ?>
<div class="card lst-card">
  <?php if (empty($redemptions)): ?><div class="of-empty" style="border:0;margin:0;padding:40px">还没有兑换记录。</div>
  <?php else: ?>
  <table class="lst-table">
    <thead><tr><th style="width:150px">时间</th><th style="width:200px">会员</th><th class="c-title">商品</th><th style="width:120px">消耗积分</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($redemptions) as $r): ?>
      <tr>
        <td class="lst-when" style="font-size:12px"><?=htmlspecialchars(substr($r['created_at'] ?? '', 0, 16))?></td>
        <td class="text-sm"><?=htmlspecialchars($r['member_id'] ?? '')?></td>
        <td class="c-title"><?=htmlspecialchars($r['product_title'] ?? '')?></td>
        <td class="ml-price"><?=$r['points']?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

  </div>
</div>

<?php admin_footer(); ?>
