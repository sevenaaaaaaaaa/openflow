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

admin_header('商城管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('shop-settings'); ?>
  <div class="main">
<h1>商城管理</h1>
<p class="sub">管理实体商品、积分商城与兑换记录</p>

<?php if ($message): ?><div class="msg msg-success"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg msg-error"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="tabs">
  <a href="?tab=products" class="<?=$tab==='products'?'active':''?>">实体商品</a>
  <a href="?tab=points" class="<?=$tab==='points'?'active':''?>">积分商城</a>
  <a href="?tab=redemptions" class="<?=$tab==='redemptions'?'active':''?>">兑换记录</a>
</div>

<?php if ($tab === 'products'): ?>
<div class="card" style="max-width:560px">
  <h2>➕ 添加 / 编辑实体商品</h2>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label>商品 ID（留空自动生成）</label><input type="text" name="product_id" class="inp" placeholder="p_xxx"></div>
    <div class="field"><label>商品标题 *</label><input type="text" name="title" class="inp" required></div>
    <div class="field"><label>描述</label><textarea name="desc" class="inp" rows="2"></textarea></div>
    <div class="field-row">
      <div class="field"><label>价格 (¥) *</label><input type="number" name="price" class="inp" step="0.01" min="0.01" required></div>
      <div class="field"><label>库存</label><input type="number" name="stock" class="inp" value="0" min="0"></div>
    </div>
    <div class="field"><label>图片 URL</label><input type="text" name="image" class="inp" placeholder="https://…/product.png"></div>
    <div class="field"><label>运费说明</label><input type="text" name="shipping" class="inp" placeholder="包邮 / ¥10"></div>
    <button type="submit" name="save_product" class="btn primary">保存商品</button>
  </form>
</div>

<div class="card">
  <h2>📦 商品列表 (<?=count($products)?>)</h2>
  <?php if (empty($products)): ?><div class="empty">暂无商品</div>
  <?php else: ?>
  <div style="overflow:auto"><table>
    <thead><tr><th>商品</th><th>价格</th><th>库存</th><th>运费</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($products) as $p): ?>
      <tr>
        <td>
          <?php if (!empty($p['image'])): ?><img src="<?=htmlspecialchars($p['image'])?>" style="width:40px;height:40px;object-fit:cover;border-radius:8px;vertical-align:middle;margin-right:8px" onerror="this.style.display='none'"><?php endif; ?>
          <strong><?=htmlspecialchars($p['title'])?></strong><br><span class="text-sm text-muted"><?=htmlspecialchars($p['desc'] ?? '')?></span>
        </td>
        <td><strong>¥<?=number_format($p['price'], 2)?></strong></td>
        <td><?=$p['stock']?></td>
        <td class="text-sm text-muted"><?=htmlspecialchars($p['shipping'] ?? '')?></td>
        <td>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'points'): ?>
<div class="card" style="max-width:560px">
  <h2>➕ 添加积分商品</h2>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label>商品标题 *</label><input type="text" name="points_title" class="inp" required></div>
    <div class="field"><label>描述</label><textarea name="points_desc" class="inp" rows="2"></textarea></div>
    <div class="field-row">
      <div class="field"><label>所需积分 *</label><input type="number" name="points" class="inp" min="1" required></div>
      <div class="field"><label>库存</label><input type="number" name="points_stock" class="inp" value="0" min="0"></div>
    </div>
    <div class="field"><label>图片 URL</label><input type="text" name="points_image" class="inp"></div>
    <button type="submit" name="save_points" class="btn primary">保存积分商品</button>
  </form>
</div>

<div class="card">
  <h2>🎁 积分商品列表 (<?=count($pointsProducts)?>)</h2>
  <?php if (empty($pointsProducts)): ?><div class="empty">暂无积分商品</div>
  <?php else: ?>
  <div style="overflow:auto"><table>
    <thead><tr><th>商品</th><th>积分</th><th>库存</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($pointsProducts) as $p): ?>
      <tr>
        <td>
          <?php if (!empty($p['image'])): ?><img src="<?=htmlspecialchars($p['image'])?>" style="width:40px;height:40px;object-fit:cover;border-radius:8px;vertical-align:middle;margin-right:8px" onerror="this.style.display='none'"><?php endif; ?>
          <strong><?=htmlspecialchars($p['title'])?></strong>
        </td>
        <td><span class="pill ok"><span class="dot"></span><?=$p['points']?> 积分</span></td>
        <td><?=$p['stock']?></td>
        <td><form method="post" style="display:inline">
          <?= csrf_field() ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'redemptions'): ?>
<div class="card">
  <h2>🏷️ 积分兑换记录</h2>
  <?php if (empty($redemptions)): ?><div class="empty">暂无兑换记录</div>
  <?php else: ?>
  <div style="overflow:auto"><table>
    <thead><tr><th>时间</th><th>会员</th><th>商品</th><th>消耗积分</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($redemptions) as $r): ?>
      <tr>
        <td class="text-sm text-muted"><?=htmlspecialchars(substr($r['created_at'] ?? '', 0, 16))?></td>
        <td><?=htmlspecialchars($r['member_id'] ?? '')?></td>
        <td><?=htmlspecialchars($r['product_title'] ?? '')?></td>
        <td><?=$r['points']?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

  </div>
</div>

<?php admin_footer(); ?>
