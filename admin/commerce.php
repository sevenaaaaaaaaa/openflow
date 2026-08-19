<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_login();
require_perm('settings');

$message = '';
$error = '';
$products = CommerceSystem::products();
$skills = skills_all();

// 发布 skill 为商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_skill'])) {
    csrf_verify();
    $skillId = $_POST['skill_id'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $mode = $_POST['pricing_mode'] ?? 'one_time';
    $period = $_POST['period'] ?? 'month';
    $rate = (float)($_POST['commission_rate'] ?? 0.7);
    $author = $_POST['author'] ?? '';
    if (!$skillId || $price <= 0) { $error = '请选择 Skill 并设置价格'; }
    else {
        $r = CommerceSystem::publishSkill($skillId, ['mode' => $mode, 'price' => $price, 'period' => $period], $author, $rate);
        $message = "Skill「{$r['title']}」已发布为商品";
    }
}

// 保存商品编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    csrf_verify();
    $id = $_POST['product_id'] ?? '';
    CommerceSystem::updateProduct($id, [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'pricing' => ['mode' => $_POST['mode'] ?? 'one_time', 'price' => (float)($_POST['price'] ?? 0), 'period' => $_POST['period'] ?? 'month'],
        'commission_rate' => (float)($_POST['commission_rate'] ?? 0.7),
        'stock' => (int)($_POST['stock'] ?? -1),  // -1 = 不限库存
    ]);
    $message = '商品已更新';
}

// 快速修改库存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_only'])) {
    csrf_verify();
    CommerceSystem::updateProduct($_POST['product_id'] ?? '', ['stock' => (int)($_POST['stock'] ?? -1)]);
    header('Location: /xmp/commerce');
    exit;
}

// 设置限时折扣
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_promo'])) {
    csrf_verify();
    $promo = ['price' => (float)($_POST['promo_price'] ?? 0)];
    if (!empty($_POST['promo_start'])) $promo['start'] = $_POST['promo_start'];
    if (!empty($_POST['promo_end'])) $promo['end'] = $_POST['promo_end'];
    CommerceSystem::updateProduct($_POST['product_id'] ?? '', ['promo' => $promo]);
    header('Location: /xmp/commerce');
    exit;
}
// 清除限时折扣
if (isset($_GET['clear_promo'])) {
    CommerceSystem::updateProduct($_GET['clear_promo'], ['promo' => null]);
    header('Location: /xmp/commerce');
    exit;
}

// 删除商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    csrf_verify();
    CommerceSystem::deleteProduct($_POST['product_id'] ?? '');
    $message = '商品已删除';
}

// 手动创建 API 套餐商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_api_plan'])) {
    csrf_verify();
    $prod = CommerceSystem::createProduct([
        'type' => 'api_plan',
        'asset_id' => trim($_POST['plan_id'] ?? ''),
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'pricing' => ['mode' => $_POST['plan_mode'] ?? 'go', 'price' => (float)($_POST['price'] ?? 0), 'period' => $_POST['plan_period'] ?? 'month'],
        'author' => '', 'commission_rate' => 0,
        'status' => 'published',
    ]);
    $message = "API 套餐「{$prod['title']}」已创建";
}

// 订单（数字商品）
$orders = [];
try { $orders = Database::query("SELECT * FROM orders WHERE goods_type = 'product' ORDER BY created_at DESC LIMIT 50"); } catch (Exception $e) {}

$stats = CommerceSystem::stats();
$typeLabels = ['skill' => 'Skill', 'plugin' => '插件', 'theme' => '主题', 'api_plan' => 'API套餐'];

admin_header('商业中心');
?>
<div class="admin-layout">
  <?php admin_sidebar('commerce'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 商业中心</h1>
      <div class="flex gap-2 ml-auto">
        <a href="marketplace" class="btn btn-ghost btn-sm">生态市场</a>
      </div>
    </div>
    <p class="sub">数字商品 · 购买交付 · 作者分成 · API 套餐 — 生态变现中枢</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px">
      <div class="stat-card"><div class="num"><?=$stats['total']?></div><div class="label">商品总数</div></div>
      <div class="stat-card"><div class="num" style="color:var(--ok)"><?=$stats['published']?></div><div class="label">已上架</div></div>
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=$stats['sales']?></div><div class="label">累计销量</div></div>
      <div class="stat-card"><div class="num"><?=count($orders)?></div><div class="label">数字商品订单</div></div>
    </div>

    <!-- 商品列表 -->
    <div class="card" style="padding:0;overflow:auto;margin-bottom:24px">
      <div style="padding:14px 20px;background:var(--surface-2);display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">📦 数字商品</h2>
      </div>
      <table>
        <thead><tr><th>商品</th><th>类型</th><th>定价</th><th>库存</th><th>作者</th><th>分成</th><th>销量</th><th>状态</th><th>促销</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($products)): ?><tr><td colspan="8" class="empty">暂无商品，用下方表单发布 Skill 或创建 API 套餐</td></tr><?php endif; ?>
          <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <strong><?=htmlspecialchars($p['title'])?></strong>
              <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars(mb_substr($p['description'] ?? '', 0, 50))?></div>
            </td>
            <td><span class="badge badge-gray"><?=$typeLabels[$p['type']] ?? $p['type']?></span></td>
            <td>
              <?php $pr = $p['pricing'] ?? []; ?>
              <?=($pr['mode'] ?? 'one_time')==='one_time'?'买断':'订阅'?> ¥<?=$pr['price'] ?? 0?>
              <?php if (($pr['period'] ?? '') === 'year'): ?><span class="text-sm text-muted">/年</span><?php endif; ?>
            </td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?=htmlspecialchars($p['id'])?>">
                <input type="hidden" name="stock_only" value="1">
                <input type="number" name="stock" value="<?=htmlspecialchars($p['stock'] ?? -1)?>" min="-1" style="width:60px;padding:4px 6px;border:1.5px solid var(--border);border-radius:6px;font-size:12px">
                <button class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:11px">存</button>
              </form>
              <span style="font-size:10px;color:<?=($p['stock'] ?? -1) === 0 ? 'var(--danger)' : 'var(--faint)'?>"><?=($p['stock'] ?? -1) === -1 ? '不限' : (($p['stock'] ?? 0) === 0 ? '售罄' : '余'.$p['stock'])?></span>
            </td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['author_name'] ?? $p['author'] ?? '—')?></td>
            <td><?=round(($p['commission_rate'] ?? 0.7) * 100)?>%</td>
            <td><span class="badge badge-green"><?=$p['sales_count'] ?? 0?></span></td>
            <td><span class="badge <?=($p['status'] ?? '')==='published'?'badge-green':'badge-yellow'?>"><?=$p['status'] ?? 'draft'?></span></td>
            <td style="white-space:nowrap">
              <?php if (!empty($p['promo']['price'])): ?>
              <span style="font-size:11px;color:var(--danger);font-weight:700">促销 ¥<?=$p['promo']['price']?></span>
              <a href="?clear_promo=<?=urlencode($p['id'])?>" style="font-size:10px;color:var(--muted)">清除</a>
              <?php else: ?><span style="font-size:10px;color:var(--faint)">—</span><?php endif; ?>
              <button class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:11px" onclick="document.getElementById('promo-<?=md5($p['id'])?>').style.display='block'">促销</button>
              <form method="post" id="promo-<?=md5($p['id'])?>" style="display:none;margin-top:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="set_promo" value="1">
                <input type="hidden" name="product_id" value="<?=htmlspecialchars($p['id'])?>">
                <input type="number" name="promo_price" placeholder="折扣价 ¥" value="<?=htmlspecialchars($p['promo']['price'] ?? '')?>" style="width:70px;padding:4px 6px;border:1.5px solid var(--border);border-radius:6px;font-size:11px">
                <input type="datetime-local" name="promo_start" value="<?=$p['promo']['start']?str_replace(' ','T',substr($p['promo']['start'],0,16)):''?>" style="padding:4px;border:1.5px solid var(--border);border-radius:6px;font-size:11px">
                <input type="datetime-local" name="promo_end" value="<?=$p['promo']['end']?str_replace(' ','T',substr($p['promo']['end'],0,16)):''?>" style="padding:4px;border:1.5px solid var(--border);border-radius:6px;font-size:11px">
                <button class="btn btn-s btn-sm" style="padding:2px 8px;font-size:11px">设置</button>
              </form>
            </td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('删除商品？')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_product" value="1">
                <input type="hidden" name="product_id" value="<?=htmlspecialchars($p['id'])?>">
                <button class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 发布 Skill 为商品 -->
    <div class="card" style="margin-bottom:24px">
      <h2>🚀 发布 Skill 为商品</h2>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        <?= csrf_field() ?>
        <input type="hidden" name="publish_skill" value="1">
        <div class="field"><label>选择 Skill</label><select name="skill_id"><?php foreach ($skills as $s): ?><option value="<?=htmlspecialchars($s['id'])?>"><?=htmlspecialchars($s['title'])?></option><?php endforeach; ?></select></div>
        <div class="field"><label>价格 ¥</label><input type="number" name="price" value="99" min="1" step="1" style="width:90px"></div>
        <div class="field"><label>模式</label><select name="pricing_mode"><option value="one_time">买断</option><option value="subscription">订阅</option></select></div>
        <div class="field"><label>周期</label><select name="period"><option value="month">月</option><option value="year">年</option></select></div>
        <div class="field"><label>作者</label><input type="text" name="author" placeholder="作者 member id" style="width:140px"></div>
        <div class="field"><label>分成 %</label><input type="number" name="commission_rate" value="0.7" min="0" max="1" step="0.05" style="width:80px"></div>
        <button type="submit" class="btn btn-primary">发布</button>
      </form>
    </div>

    <!-- 创建 API 套餐 -->
    <div class="card" style="margin-bottom:24px">
      <h2>🔌 创建 API 套餐</h2>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        <?= csrf_field() ?>
        <input type="hidden" name="create_api_plan" value="1">
        <div class="field"><label>套餐 ID</label><input type="text" name="plan_id" placeholder="如 go_basic" style="width:120px"></div>
        <div class="field"><label>名称</label><input type="text" name="title" placeholder="Go 基础版" style="width:140px"></div>
        <div class="field"><label>模式</label><select name="plan_mode"><option value="go">Go（订阅+频率上限）</option><option value="zen">Zen（按量计费）</option></select></div>
        <div class="field"><label>价格 ¥</label><input type="number" name="price" value="199" min="1" step="1" style="width:90px"></div>
        <div class="field"><label>周期</label><select name="plan_period"><option value="month">月</option><option value="year">年</option></select></div>
        <button type="submit" class="btn btn-primary">创建</button>
      </form>
      <p class="hint" style="margin-top:8px">Go 模式：订阅制，每5小时/天/周有调用上限；Zen 模式：按调用量从余额扣费。</p>
    </div>

    <!-- 数字商品订单 -->
    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">🧾 数字商品订单</h2></div>
      <table>
        <thead><tr><th>订单号</th><th>商品</th><th>买家</th><th>金额</th><th>作者</th><th>分成</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
          <?php if (empty($orders)): ?><tr><td colspan="8" class="empty">暂无数字商品订单</td></tr><?php endif; ?>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><code style="font-size:11px"><?=htmlspecialchars($o['id'])?></code></td>
            <td class="text-sm"><?=htmlspecialchars($o['course_title'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($o['member_id'] ?? '')?></td>
            <td><b>¥<?=number_format($o['amount'] ?? 0, 2)?></b></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($o['author'] ?? '—')?></td>
            <td class="text-sm"><?=round(($o['commission_rate'] ?? 0.7) * 100)?>%</td>
            <td><span class="badge <?=($o['status'] ?? '')==='paid'?'badge-green':'badge-yellow'?>"><?=$o['status'] ?? ''?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($o['created_at'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
