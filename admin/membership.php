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
    csrf_verify();
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
    csrf_verify();
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
<style>
.mb-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
.mb-kpi{--c:var(--faint);padding:14px 18px;border-radius:14px;border:1px solid var(--border);background:var(--surface);box-shadow:inset 3px 0 0 var(--c)}
.mb-kpi .l{font-size:12.5px;color:var(--muted)}
.mb-kpi .n{font-family:var(--font-mono);font-size:24px;font-weight:800;letter-spacing:-.02em;margin-top:2px}
.inline-select{height:32px;padding:0 26px 0 10px;border:1px solid var(--border);border-radius:9px;font-size:12.5px;font-weight:600;background:var(--surface);color:var(--fg);max-width:100%}
.mb-ent td{font-size:13px}
.mb-ent td:last-child{font-family:var(--font-mono);font-size:11.5px;color:var(--faint)}
.ent{font-size:12.5px;color:var(--muted)}.ent.yes{color:var(--ok);font-weight:700}.ent.no{color:var(--faint)}
@media(max-width:840px){.mb-kpis{grid-template-columns:1fr 1fr}}
</style>
<div class="admin-layout">
  <?php admin_sidebar('membership'); ?>
  <div class="main">
    <h1>会员体系</h1>
    <p class="sub">统一会员等级 + 全站权益模型 · 打通文章/资料/课程/邮件/直播/1v1/社区</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 会员分布 -->
    <div class="mb-kpis">
      <div class="mb-kpi" style="--c:var(--faint)"><div class="l">免费用户</div><div class="n"><?=$tierCount['free']?></div></div>
      <div class="mb-kpi" style="--c:var(--warn)"><div class="l">普通会员</div><div class="n"><?=$tierCount['member']?></div></div>
      <div class="mb-kpi" style="--c:var(--accent)"><div class="l">VIP 会员</div><div class="n"><?=$tierCount['vip']?></div></div>
      <div class="mb-kpi" style="--c:var(--border-strong)"><div class="l">全部会员</div><div class="n"><?=count($members)?></div></div>
    </div>

    <div class="tabs" style="margin-bottom:16px">
      <a href="?tab=members" class="<?=($_GET['tab']??'members')==='members'?'active':''?>">会员等级</a>
      <a href="?tab=plans" class="<?=($_GET['tab']??'')==='plans'?'active':''?>">会员计划</a>
      <a href="?tab=entitlements" class="<?=($_GET['tab']??'')==='entitlements'?'active':''?>">权益模型</a>
    </div>

    <?php if (($_GET['tab'] ?? 'members') === 'members'): ?>
    <div class="card lst-card">
      <table class="lst-table">
        <thead><tr><th class="c-title">会员</th><th style="width:120px">当前等级</th><th style="width:80px">积分</th><th style="width:70px">订阅</th><th style="width:90px">已购课程</th><th style="width:90px">咨询次数</th><th style="width:150px">手动授予 <span class="hint" style="font-weight:400;text-transform:none;letter-spacing:0">· 改了即存</span></th></tr></thead>
        <tbody>
          <?php if (empty($members)): ?><tr><td colspan="7"><div class="of-empty" style="border:0;margin:0">还没有会员。用户在前台注册后会出现在这里。</div></td></tr><?php endif; ?>
          <?php foreach ($members as $m): $e = $entitlements[$m['id']] ?? member_entitlements($m); ?>
          <tr>
            <td class="c-title"><div class="lst-title"><?=htmlspecialchars($m['name'] ?? '')?></div><div class="lst-sub"><span class="lst-slug"><?=htmlspecialchars($m['email'] ?? '')?></span></div></td>
            <td><span class="badge <?=$e['tier']==='vip'?'badge-blue':($e['tier']==='member'?'badge-yellow':'badge-gray')?>"><?=htmlspecialchars($e['tier_name'])?></span></td>
            <td class="mono"><?=$e['points']?></td>
            <td><?=$e['subscription'] ? '<span class="badge badge-green">是</span>' : '<span class="text-muted">—</span>'?></td>
            <td class="mono"><?=count($e['owned_courses'])?></td>
            <td class="mono"><?=$e['consultation_used']?></td>
            <td>
              <form method="post" data-no-guard>
                <?= csrf_field() ?>
                <input type="hidden" name="member_id" value="<?=htmlspecialchars($m['id'])?>">
                <input type="hidden" name="grant" value="1">
                <select name="tier" class="inline-select" onchange="this.form.requestSubmit()" aria-label="授予等级">
                  <option value="free" <?=$e['granted_tier']===''?'selected':''?>>自动（按消费）</option>
                  <option value="member" <?=$e['granted_tier']==='member'?'selected':''?>>普通会员</option>
                  <option value="vip" <?=$e['granted_tier']==='vip'?'selected':''?>>VIP</option>
                </select>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif (($_GET['tab'] ?? '') === 'plans'): ?>
    <div class="card">
      <h2>会员计划（权益定义）</h2>
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
      <h2>统一权益模型</h2>
      <p class="text-sm text-muted mb-4">所有付费/免费功能的鉴权统一由 MembershipSystem 提供</p>
      <table data-static class="mb-ent">
        <thead><tr><th>权益</th><th>免费用户</th><th>普通会员</th><th>VIP</th><th>键名</th></tr></thead>
        <tbody>
          <tr><td>公开文章</td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>articles</td></tr>
          <tr><td>会员专享文章</td><td><span class="ent no">—</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>articles_member</td></tr>
          <tr><td>资料下载</td><td><span class="ent no">—</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>downloads</td></tr>
          <tr><td>订阅邮件</td><td><span class="ent no">—</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>newsletter / subscription_email</td></tr>
          <tr><td>课程购买观看</td><td><span class="ent">按需购买</span></td><td><span class="ent">按需购买</span></td><td><span class="ent yes">✓ 免费看</span></td><td>courses</td></tr>
          <tr><td>直播观看</td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>live</td></tr>
          <tr><td>直播回放</td><td><span class="ent no">—</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>live_replay</td></tr>
          <tr><td>1v1 咨询</td><td><span class="ent yes">✓ 可预约</span></td><td><span class="ent yes">✓ 可预约</span></td><td><span class="ent yes">✓ 85 折</span></td><td>consultation / consultation_discount</td></tr>
          <tr><td>社区发帖评论</td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓ + 徽章</span></td><td>community_post / community_vip_badge</td></tr>
          <tr><td>积分等级</td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td><span class="ent yes">✓</span></td><td>level_virtual</td></tr>
          <tr><td>投稿优先审核</td><td><span class="ent no">—</span></td><td><span class="ent no">—</span></td><td><span class="ent yes">✓</span></td><td>priority_review</td></tr>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
