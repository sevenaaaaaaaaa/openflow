<?php
/**
 * Newsletter 付费订阅管理 — 设置 / 计划 / 订阅用户 / 订单（Tab 化）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/SubscriptionSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';
require_login();
require_perm('settings');

$plans = sub_get_plans();
$settings = sub_settings();
$state = sub_get_state();
$message = '';
$error = '';
$tab = $_GET['tab'] ?? 'overview';

// 保存计划
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plans'])) {
    csrf_verify();
    $plans = [];
    foreach (($_POST['plan_name'] ?? []) as $i => $pn) {
        if (empty(trim($pn))) continue;
        $plans[] = [
            'id' => ($_POST['plan_id'][$i] ?? '') ?: 'plan_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'name' => trim($pn),
            'price' => (float)($_POST['plan_price'][$i] ?? 0),
            'period' => $_POST['plan_period'][$i] ?? 'month',
            'description' => trim($_POST['plan_desc'][$i] ?? ''),
            'permissions' => array_filter(array_map('trim', explode(',', $_POST['plan_perm'][$i] ?? ''))),
            'enabled' => isset($_POST['plan_enabled'][$i]),
        ];
    }
    sub_save_plans($plans);
    $message = '订阅计划已保存';
}

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    $settings['enabled'] = isset($_POST['enabled']);
    $settings['ghost_enabled'] = isset($_POST['ghost_enabled']);
    $settings['ghost_api_url'] = trim($_POST['ghost_api_url'] ?? '');
    $settings['ghost_content_key'] = trim($_POST['ghost_content_key'] ?? '');
    $settings['ghost_admin_key'] = trim($_POST['ghost_admin_key'] ?? '');
    sub_save_settings($settings);
    $message = '订阅设置已保存';
}

// 手动续期/调整
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_extend'])) {
    csrf_verify();
    $mid = $_POST['member_id'] ?? '';
    $months = (int)($_POST['months'] ?? 1);
    $s = sub_get_member($mid);
    $base = ($s && ($s['status'] ?? '') === 'active' && !empty($s['expires_at'])) ? $s['expires_at'] : date('Y-m-d');
    sub_set_member($mid, [
        'member_id' => $mid,
        'member_name' => $_POST['member_name'] ?? '',
        'plan_id' => $_POST['plan_id'] ?? '',
        'status' => 'active',
        'expires_at' => date('Y-m-d', strtotime($base . ' +' . $months . ' month')),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $message = '订阅已续期 ' . $months . ' 个月';
}

// 到期判断（7 天内）
$soon = [];
foreach ($state as $mid => $s) {
    if (($s['status'] ?? '') === 'active' && !empty($s['expires_at'])) {
        $days = (strtotime($s['expires_at']) - time()) / 86400;
        if ($days <= 7) $soon[$mid] = (int)ceil($days);
    }
}
$expiredCount = count(array_filter($state, fn($s) => ($s['status'] ?? '') === 'expired'));

// 订阅订单
$orders = json_read(shop_orders_file());
$subOrders = array_values(array_filter($orders, fn($o) => !empty($o['plan_id']) || ($o['goods_type'] ?? '') === 'subscription'));

// 计划名映射
$planNames = [];
foreach ($plans as $pl) $planNames[$pl['id']] = $pl['name'];

admin_header('付费订阅');
?>
<div class="admin-layout">
  <?php admin_sidebar('subscription'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 付费订阅</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <?php if ($settings['enabled']): ?><span class="badge badge-green">🟢 订阅已启用</span><?php else: ?><span class="badge badge-gray">⏸ 订阅未启用</span><?php endif; ?>
        <span class="badge <?=$expiredCount>0?'badge-yellow':'badge-gray'?>"><?=count($state)?> 订阅用户</span>
      </div>
    </div>
    <p class="sub">Newsletter 付费订阅 · 国内版自研（虎皮椒支付）+ 海外版 Ghost CMS</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="tabs">
      <a href="?tab=overview" class="<?=$tab==='overview'?'active':''?>">概览</a>
      <a href="?tab=plans" class="<?=$tab==='plans'?'active':''?>">计划管理</a>
      <a href="?tab=users" class="<?=$tab==='users'?'active':''?>">订阅用户 <?=$soon?'(' . count($soon) . ' 将到期)':''?></a>
      <a href="?tab=orders" class="<?=$tab==='orders'?'active':''?>">订阅订单</a>
      <a href="?tab=settings" class="<?=$tab==='settings'?'active':''?>">设置</a>
    </div>

    <?php if ($tab === 'overview'): ?>
    <div class="stats">
      <div class="stat-card"><div class="num"><?=count($plans)?></div><div class="label">订阅计划</div></div>
      <div class="stat-card"><div class="num"><?=count($state)?></div><div class="label">订阅用户</div></div>
      <div class="stat-card"><div class="num"><?=$expiredCount?></div><div class="label">已过期</div></div>
      <div class="stat-card"><div class="num" style="color:var(--warn)"><?=count($soon)?></div><div class="label">7天内到期</div></div>
    </div>

    <div class="card">
      <h2>📋 计划概览</h2>
      <?php if (empty($plans)): ?><div class="empty">暂无订阅计划，到「计划管理」创建</div>
      <?php else: ?>
      <div style="overflow:auto"><table>
        <thead><tr><th>计划</th><th>价格</th><th>周期</th><th>权限</th><th>状态</th></tr></thead>
        <tbody>
          <?php foreach ($plans as $pl): ?>
          <tr>
            <td><strong><?=htmlspecialchars($pl['name'])?></strong></td>
            <td><strong>¥<?=number_format($pl['price'],2)?></strong></td>
            <td class="text-sm text-muted"><?=$pl['period']==='year'?'年付':'月付'?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(implode(', ', $pl['permissions'] ?? []))?:'—'?></td>
            <td><span class="badge <?=!empty($pl['enabled'])?'badge-green':'badge-gray'?>"><?=!empty($pl['enabled'])?'启用':'停用'?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>⏰ 即将到期（7 天内）</h2>
      <?php if (empty($soon)): ?><div class="empty">暂无即将到期的订阅 ✅</div>
      <?php else: ?>
      <div style="overflow:auto"><table>
        <thead><tr><th>用户</th><th>计划</th><th>到期</th><th>剩余</th></tr></thead>
        <tbody>
          <?php foreach ($soon as $mid => $days): $s = $state[$mid]; ?>
          <tr>
            <td><strong><?=htmlspecialchars($s['member_name'] ?? $mid)?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($planNames[$s['plan_id']] ?? $s['plan_id'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($s['expires_at'] ?? '')?></td>
            <td><span class="badge badge-yellow"><?=$days?> 天</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'plans'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>💳 订阅计划</h2>
        <div id="planList">
          <?php foreach ($plans as $pi => $p): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;padding:10px;background:var(--surface-2);border-radius:10px">
            <input type="hidden" name="plan_id[]" value="<?=htmlspecialchars($p['id'])?>">
            <input type="text" name="plan_name[]" value="<?=htmlspecialchars($p['name'])?>" placeholder="计划名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="number" name="plan_price[]" value="<?=htmlspecialchars($p['price'])?>" placeholder="价格" style="width:90px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <select name="plan_period[]" style="width:80px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="month" <?=($p['period']??'')==='month'?'selected':''?>>月付</option><option value="year" <?=($p['period']??'')==='year'?'selected':''?>>年付</option></select>
            <input type="text" name="plan_desc[]" value="<?=htmlspecialchars($p['description'] ?? '')?>" placeholder="描述" style="flex:1;min-width:140px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="text" name="plan_perm[]" value="<?=htmlspecialchars(implode(',', $p['permissions'] ?? []))?>" placeholder="权限(逗号分隔)" style="width:180px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="plan_enabled[]" value="1" <?=!empty($p['enabled'])?'checked':''?> style="width:15px;height:15px">启用</label>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPlan()">+ 添加计划</button>
        <div style="margin-top:12px"><button type="submit" name="save_plans" class="btn btn-primary">保存计划</button></div>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($tab === 'users'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">👥 订阅用户</h2>
      <table>
        <thead><tr><th>用户</th><th>计划</th><th>状态</th><th>到期</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($state)): ?><tr><td colspan="5" class="empty">暂无订阅用户</td></tr><?php endif; ?>
          <?php foreach ($state as $mid => $s):
            $days = null;
            if (($s['status'] ?? '') === 'active' && !empty($s['expires_at'])) $days = (int)ceil((strtotime($s['expires_at']) - time()) / 86400);
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($s['member_name'] ?? $mid)?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($planNames[$s['plan_id']] ?? $s['plan_id'] ?? '')?></td>
            <td>
              <?php if (($s['status']??'') === 'active'): ?>
                <span class="badge badge-green">活跃</span>
                <?php if ($days !== null && $days <= 7): ?><span class="badge badge-yellow"><?=$days?>天</span><?php endif; ?>
              <?php elseif (($s['status']??'') === 'expired'): ?><span class="badge badge-gray">已过期</span>
              <?php else: ?><span class="badge badge-yellow"><?=$s['status']??'?'?></span><?php endif; ?>
            </td>
            <td class="text-sm text-muted"><?=htmlspecialchars($s['expires_at'] ?? '—')?></td>
            <td style="white-space:nowrap">
              <form method="post" style="display:inline-flex;gap:6px">
                <?= csrf_field() ?>
                <input type="hidden" name="member_id" value="<?=htmlspecialchars($mid)?>">
                <input type="hidden" name="member_name" value="<?=htmlspecialchars($s['member_name'] ?? '')?>">
                <input type="hidden" name="plan_id" value="<?=htmlspecialchars($s['plan_id'] ?? '')?>">
                <input type="number" name="months" value="1" min="1" max="24" style="width:60px;padding:4px;border:1.5px solid var(--border);border-radius:6px">
                <button type="submit" name="manual_extend" class="btn btn-ghost btn-sm">续期(月)</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'orders'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">🧾 订阅订单</h2>
      <?php if (empty($subOrders)): ?>
      <div class="empty">暂无订阅订单。用户购买订阅后，订单会显示在这里。</div>
      <?php else: ?>
      <table>
        <thead><tr><th>订单</th><th>用户</th><th>商品</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($subOrders) as $o): ?>
          <tr>
            <td><code><?=htmlspecialchars($o['id'])?></code></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($o['member_id'])?></td>
            <td><?=htmlspecialchars($o['goods_title'] ?? $planNames[$o['plan_id']] ?? '订阅')?></td>
            <td><strong>¥<?=number_format($o['amount']??0,2)?></strong></td>
            <td><span class="badge <?=($o['status']??'')==='paid'?'badge-green':'badge-gray'?>"><?=$o['status']??'?'?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($o['created_at']??'',0,16))?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'settings'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>⚙️ 订阅设置</h2>
        <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:12px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=$settings['enabled']?'checked':''?> style="width:17px;height:17px"> <strong>启用付费订阅</strong>（国内自研版）</label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="ghost_enabled" value="1" <?=$settings['ghost_enabled']?'checked':''?> style="width:17px;height:17px"> <strong>启用海外版（Ghost CMS）</strong></label>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:10px;margin-bottom:12px">
          <p class="text-sm text-muted mb-4" style="font-size:13px">🌍 海外版通过 Ghost CMS 提供会员订阅。填好 Ghost 站点信息后，海外用户走 Ghost 的订阅支付，国内用户走自研虎皮椒支付。</p>
          <div class="field-row">
            <div class="field"><label>Ghost API URL</label><input type="text" name="ghost_api_url" value="<?=htmlspecialchars($settings['ghost_api_url'])?>" placeholder="https://your-ghost.example.com"></div>
            <div class="field"><label>Ghost Content Key</label><input type="text" name="ghost_content_key" value="<?=htmlspecialchars($settings['ghost_content_key'])?>" placeholder="Content API Key"></div>
          </div>
          <div class="field"><label>Ghost Admin Key <span class="hint">· 可选</span></label><input type="text" name="ghost_admin_key" value="<?=htmlspecialchars($settings['ghost_admin_key'])?>" placeholder="Admin API Key（用于创建订阅）"></div>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary">保存设置</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<script>
function addPlan() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;padding:10px;background:var(--surface-2);border-radius:10px';
  d.innerHTML = '<input type="hidden" name="plan_id[]" value="plan_' + Date.now() + '"><input type="text" name="plan_name[]" placeholder="计划名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="number" name="plan_price[]" value="0" placeholder="价格" style="width:90px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><select name="plan_period[]" style="width:80px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="month">月付</option><option value="year">年付</option></select><input type="text" name="plan_desc[]" placeholder="描述" style="flex:1;min-width:140px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="text" name="plan_perm[]" placeholder="权限(逗号分隔)" style="width:180px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px"><label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="plan_enabled[]" value="1" checked style="width:15px;height:15px">启用</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('planList').appendChild(d);
}
</script>
<?php admin_footer(); ?>
