<?php
/**
 * 会员体系管理 — 会员计划 / 权益总览 / 用户等级授予
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MembershipSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_login();
require_perm('settings');

$members = member_get_all();
$plans = mem_plans();
$state = mem_state();
$message = '';

// 授予/变更会员等级
if (isset($_POST['grant'])) {
    $mid = $_POST['member_id'] ?? '';
    $tier = $_POST['tier'] ?? '';
    if (in_array($tier, ['free', 'member', 'vip'])) {
        if ($tier === 'free') unset($state[$mid]);
        else $state[$mid] = ['tier' => $tier, 'updated_at' => date('Y-m-d H:i:s'), 'by' => $_SESSION['admin_user'] ?? ''];
        mem_save_state($state);
        $message = '会员等级已更新';
    }
}

// 保存计划
if (isset($_POST['save_plans'])) {
    $plans = [];
    foreach (($_POST['plan_id'] ?? []) as $i => $pid) {
        if (empty($pid)) continue;
        $plans[] = [
            'id' => $pid,
            'name' => trim($_POST['plan_name'][$i] ?? ''),
            'icon' => trim($_POST['plan_icon'][$i] ?? '👤'),
            'price' => (float)($_POST['plan_price'][$i] ?? 0),
            'period' => trim($_POST['plan_period'][$i] ?? 'year'),
            'benefits' => array_filter(array_map('trim', explode("\n", $_POST['plan_benefits'][$i] ?? ''))),
        ];
    }
    mem_save_state($state);
    if (!is_dir(dirname(mem_plans_file()))) mkdir(dirname(mem_plans_file()), 0755, true);
    json_write(mem_plans_file(), $plans);
    $message = '会员计划已保存';
    $plans = mem_plans();
}

// 会员权益统计
$entitlements = [];
foreach ($members as $m) $entitlements[$m['id']] = member_entitlements($m);
$tierCount = ['free' => 0, 'member' => 0, 'vip' => 0];
foreach ($entitlements as $e) $tierCount[$e['tier']] = ($tierCount[$e['tier']] ?? 0) + 1;

admin_header('会员体系');
?>
<div class="admin-layout">
  <?php admin_sidebar('membership'); ?>
  <div class="main">
    <h1> 会员体系</h1>
    <p class="sub">统一会员等级 + 全站权益模型 · 打通文章/资料/课程/邮件/直播/1v1/社区</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 会员分布 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
      <div class="card" style="border-left:4px solid var(--faint)"><div class="text-sm text-muted">👤 免费用户</div><div style="font-size:26px;font-weight:800"><?=$tierCount['free']?></div></div>
      <div class="card" style="border-left:4px solid #f59e0b"><div class="text-sm text-muted">⭐ 普通会员</div><div style="font-size:26px;font-weight:800"><?=$tierCount['member']?></div></div>
      <div class="card" style="border-left:4px solid #b45309"><div class="text-sm text-muted">👑 VIP 会员</div><div style="font-size:26px;font-weight:800"><?=$tierCount['vip']?></div></div>
      <div class="card"><div class="text-sm text-muted">👥 全部会员</div><div style="font-size:26px;font-weight:800"><?=count($members)?></div></div>
    </div>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?tab=members" class="btn <?=($_GET['tab']??'members')==='members'?'btn-primary':'btn-ghost'?> btn-sm">👥 会员等级管理</a>
      <a href="?tab=plans" class="btn <?=($_GET['tab']??'')==='plans'?'btn-primary':'btn-ghost'?> btn-sm">📦 会员计划</a>
      <a href="?tab=entitlements" class="btn <?=($_GET['tab']??'')==='entitlements'?'btn-primary':'btn-ghost'?> btn-sm">🔑 权益模型</a>
    </div>

    <?php if (($_GET['tab'] ?? 'members') === 'members'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>会员</th><th>当前等级</th><th>积分</th><th>订阅</th><th>已购课程</th><th>咨询次数</th><th>授予等级</th></tr></thead>
        <tbody>
          <?php if (empty($members)): ?><tr><td colspan="7" class="empty">暂无会员</td></tr><?php endif; ?>
          <?php foreach ($members as $m): $e = $entitlements[$m['id']] ?? member_entitlements($m); ?>
          <tr>
            <td><strong><?=htmlspecialchars($m['name'] ?? '')?></strong><div class="text-sm text-muted"><?=htmlspecialchars($m['email'] ?? '')?></div></td>
            <td><span class="badge" style="background:<?=$e['tier']==='vip'?'#b45309':($e['tier']==='member'?'#f59e0b':'var(--faint)')?>;color:#fff;padding:3px 10px;border-radius:999px;font-size:11px"><?=$e['icon']?> <?=htmlspecialchars($e['tier_name'])?></span></td>
            <td><?=$e['points']?></td>
            <td><?=$e['subscription'] ? '⭐ 是' : '—'?></td>
            <td><?=count($e['owned_courses'])?></td>
            <td><?=$e['consultation_used']?></td>
            <td>
              <form method="post" style="display:flex;gap:4px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="member_id" value="<?=htmlspecialchars($m['id'])?>">
                <select name="tier" style="padding:6px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
                  <option value="free" <?=$e['granted_tier']===''?'selected':''?>>自动</option>
                  <option value="member" <?=$e['granted_tier']==='member'?'selected':''?>>普通会员</option>
                  <option value="vip" <?=$e['granted_tier']==='vip'?'selected':''?>>VIP</option>
                </select>
                <button type="submit" name="grant" class="btn btn-ghost btn-sm">保存</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif (($_GET['tab'] ?? '') === 'plans'): ?>
    <div class="card">
      <h2>📦 会员计划（权益定义）</h2>
      <p class="text-sm text-muted mb-4">每个计划定义权益清单，会员中心自动展示</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_plans" value="1">
        <?php foreach ($plans as $i => $p): ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px">
          <input type="hidden" name="plan_id[]" value="<?=htmlspecialchars($p['id'])?>">
          <div class="field-row">
            <div class="field"><label>名称</label><input type="text" name="plan_name[]" value="<?=htmlspecialchars($p['name'] ?? '')?>"></div>
            <div class="field"><label>图标</label><input type="text" name="plan_icon[]" value="<?=htmlspecialchars($p['icon'] ?? '👤')?>" style="width:80px"></div>
            <div class="field"><label>价格</label><input type="number" name="plan_price[]" value="<?=$p['price'] ?? 0?>" style="width:110px" min="0"></div>
            <div class="field"><label>周期</label><select name="plan_period[]" style="width:100px"><option value="year" <?=($p['period']??'year')==='year'?'selected':''?>>年付</option><option value="month" <?=($p['period']??'')==='month'?'selected':''?>>月付</option></select></div>
          </div>
          <div class="field"><label>权益清单 <span class="hint">· 每行一项</span></label>
            <textarea name="plan_benefits[]" rows="4"><?=htmlspecialchars(implode("\n", $p['benefits'] ?? []))?></textarea>
          </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">保存会员计划</button>
      </form>
    </div>

    <?php else: ?>
    <div class="card">
      <h2>🔑 统一权益模型</h2>
      <p class="text-sm text-muted mb-4">所有付费/免费功能的鉴权统一由 MembershipSystem 提供</p>
      <table>
        <thead><tr><th>权益</th><th>免费用户</th><th>普通会员</th><th>VIP</th><th>说明</th></tr></thead>
        <tbody>
          <tr><td>公开文章</td><td>✅</td><td>✅</td><td>✅</td><td>articles</td></tr>
          <tr><td>会员专享文章</td><td>🔒</td><td>✅</td><td>✅</td><td>articles_member</td></tr>
          <tr><td>资料下载</td><td>🔒</td><td>✅</td><td>✅</td><td>downloads</td></tr>
          <tr><td>订阅邮件</td><td>🔒</td><td>✅</td><td>✅</td><td>newsletter / subscription_email</td></tr>
          <tr><td>课程购买观看</td><td>🛒 按需购买</td><td>🛒 按需购买</td><td>✅ 免费看</td><td>courses</td></tr>
          <tr><td>直播观看</td><td>✅</td><td>✅</td><td>✅</td><td>live</td></tr>
          <tr><td>直播回放</td><td>🔒</td><td>✅</td><td>✅</td><td>live_replay</td></tr>
          <tr><td>1v1 咨询</td><td>✅ 可预约</td><td>✅ 可预约</td><td>✅ 85 折</td><td>consultation / consultation_discount</td></tr>
          <tr><td>社区发帖评论</td><td>✅</td><td>✅</td><td>✅ + 徽章</td><td>community_post / community_vip_badge</td></tr>
          <tr><td>积分等级</td><td>✅</td><td>✅</td><td>✅</td><td>level_virtual</td></tr>
          <tr><td>投稿优先审核</td><td>—</td><td>—</td><td>✅</td><td>priority_review</td></tr>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
