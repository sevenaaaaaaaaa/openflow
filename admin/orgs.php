<?php
/**
 * 企业客户管理 — ToB 商业发行版
 * 从 ToB 线索转成企业实体，跟踪状态机：意向→有效→报价→签约→部署→使用
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/OrgSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_login();
require_perm('crm');

$statuses = org_statuses();
$plans = org_plans();

// ─── POST 处理 ───
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $orgId = $_POST['org_id'] ?? '';
    $org = $orgId ? org_get($orgId) : null;
    if ($org) {
        if ($action === 'update_status') {
            $org['status'] = $_POST['status'] ?? $org['status'];
            $org['updated_at'] = date('Y-m-d H:i:s');
            org_save($org);
            $message = '状态已更新为「' . org_status_label($org['status']) . '」';
        } elseif ($action === 'edit') {
            $org['name'] = trim($_POST['name'] ?? $org['name']);
            $org['industry'] = trim($_POST['industry'] ?? '');
            $org['size'] = trim($_POST['size'] ?? '');
            $org['plan_type'] = trim($_POST['plan_type'] ?? $org['plan_type']);
            $org['budget'] = trim($_POST['budget'] ?? '');
            $org['notes'] = trim($_POST['notes'] ?? '');
            $org['contact_name'] = trim($_POST['contact_name'] ?? '');
            $org['contact_phone'] = trim($_POST['contact_phone'] ?? '');
            $org['updated_at'] = date('Y-m-d H:i:s');
            org_save($org);
            $message = '企业信息已更新';
        } elseif ($action === 'add_member') {
            $memberEmail = mb_strtolower(trim($_POST['member_email'] ?? ''));
            if ($memberEmail) {
                $mem = member_find($memberEmail);
                if ($mem) {
                    org_add_member($orgId, $mem['id']);
                    $message = "成员 {$memberEmail} 已加入企业";
                } else {
                    $message = "未找到用户 {$memberEmail}（需先在官网注册）";
                }
            }
        }
    }
    header('Location: /xmp/orgs' . ($orgId ? '?focus=' . urlencode($orgId) : ''));
    exit;
}

$orgs = org_get_all();
usort($orgs, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) $orgs = array_values(array_filter($orgs, fn($o) => ($o['status'] ?? '') === $statusFilter));

$focusOrg = null;
$focusId = $_GET['focus'] ?? '';
if ($focusId) { foreach (org_get_all() as $o) if ($o['id'] === $focusId) { $focusOrg = $o; break; } }

admin_header('企业客户');
?>
<style>
.org-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:18px 20px;margin-bottom:12px}
.org-name{font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px}
.org-meta{font-size:12px;color:var(--faint);margin-top:4px;font-family:var(--font-mono)}
.org-desc{font-size:12.5px;color:var(--muted);margin-top:8px}
.st-badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;color:#fff}
.kv{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;margin-top:14px}
.kv .kv-item{font-size:12.5px}
.kv .kv-item b{color:var(--fg);font-weight:600}
.kv .kv-item span{color:var(--faint)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('orgs'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>企业客户</h1><p class="v-sub">ToB 商业发行版 · 从申请到部署的客户生命周期。所有企业申请来自官网「商业发行版」表单。</p></div>
      <div class="v-actions"><?php if ($message): ?><span class="st st-ok"><?=htmlspecialchars($message)?></span><?php endif; ?></div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
      <a href="/xmp/orgs" class="btn btn-s btn-sm <?=$statusFilter===''?'on':''?>" style="<?=$statusFilter===''?'border-color:var(--accent);color:var(--accent)':''?>">全部 (<?=count(org_get_all())?>)</a>
      <?php foreach ($statuses as $k => $s): $n = count(array_filter(org_get_all(), fn($o) => ($o['status'] ?? '') === $k)); ?>
      <a href="/xmp/orgs?status=<?=$k?>" class="btn btn-s btn-sm <?=$statusFilter===$k?'on':''?>" style="<?=$statusFilter===$k?'border-color:var(--accent);color:var(--accent)':''?>"><?=$s['label']?> (<?=$n?>)</a>
      <?php endforeach; ?>
    </div>

    <?php if ($focusOrg): ?>
    <div class="card" style="padding:24px;margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <h2 style="font-size:18px;font-weight:800"><?=htmlspecialchars($focusOrg['name'])?></h2>
        <span class="st-badge" style="background:<?=($statuses[$focusOrg['status']]['color'] ?? '#888')?>"><?=org_status_label($focusOrg['status'])?></span>
        <span class="st-badge" style="background:oklch(52% .17 258)"><?=org_plan_label($focusOrg['plan_type'])?></span>
      </div>
      <div class="kv">
        <div class="kv-item"><b>行业：</b><span><?=htmlspecialchars($focusOrg['industry'])?> / <?=htmlspecialchars($focusOrg['size'])?></span></div>
        <div class="kv-item"><b>预算：</b><span><?=htmlspecialchars($focusOrg['budget'])?></span></div>
        <div class="kv-item"><b>联系人：</b><span><?=htmlspecialchars($focusOrg['contact_name'])?></span></div>
        <div class="kv-item"><b>联系方式：</b><span><?=htmlspecialchars($focusOrg['contact_email'])?> / <?=htmlspecialchars($focusOrg['contact_phone'])?></span></div>
        <div class="kv-item"><b>创建时间：</b><span><?=htmlspecialchars($focusOrg['created_at'])?></span></div>
        <div class="kv-item"><b>成员数：</b><span><?=count((array)($focusOrg['members'] ?? []))?></span></div>
      </div>
      <?php if (!empty($focusOrg['notes'])): ?><p style="font-size:13px;color:var(--muted);margin-top:14px;line-height:1.7"><b>需求：</b><?=htmlspecialchars($focusOrg['notes'])?></p><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:22px">
        <div>
          <h3 style="font-size:13px;font-weight:700;margin-bottom:10px">更新状态</h3>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="org_id" value="<?=$focusOrg['id']?>">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <select name="status" class="inp" style="flex:1;min-width:150px;height:38px">
                <?php foreach ($statuses as $k => $s): ?><option value="<?=$k?>" <?=$focusOrg['status']===$k?'selected':''?>><?=$s['label']?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-p btn-sm">更新</button>
            </div>
          </form>
          <h3 style="font-size:13px;font-weight:700;margin:20px 0 10px">添加成员</h3>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_member">
            <input type="hidden" name="org_id" value="<?=$focusOrg['id']?>">
            <div style="display:flex;gap:8px">
              <input type="email" name="member_email" class="inp" placeholder="成员注册邮箱" style="flex:1;height:38px" required>
              <button class="btn btn-s btn-sm">加入</button>
            </div>
          </form>
          <h3 style="font-size:13px;font-weight:700;margin:20px 0 8px">成员 (<?=count((array)($focusOrg['members'] ?? []))?>)</h3>
          <?php foreach ((array)($focusOrg['members'] ?? []) as $mid): $m = member_get($mid); ?>
          <div style="font-size:12.5px;color:var(--muted);padding:5px 0;border-bottom:1px solid var(--border-soft)"><?=htmlspecialchars($m['email'] ?? $mid)?> <?=($focusOrg['admin_member_id']===$mid)?'<span class="st st-ok">管理员</span>':''?></div>
          <?php endforeach; ?>
        </div>
        <div>
          <h3 style="font-size:13px;font-weight:700;margin-bottom:10px">编辑企业信息</h3>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="org_id" value="<?=$focusOrg['id']?>">
            <div class="fld"><label style="font-size:11.5px;color:var(--faint)">企业名称</label><input class="inp" name="name" value="<?=htmlspecialchars($focusOrg['name'])?>" style="height:36px"></div>
            <div class="grid gap-2" style="grid-template-columns:1fr 1fr">
              <div class="fld"><label style="font-size:11.5px;color:var(--faint)">行业</label><input class="inp" name="industry" value="<?=htmlspecialchars($focusOrg['industry'])?>" style="height:36px"></div>
              <div class="fld"><label style="font-size:11.5px;color:var(--faint)">规模</label><input class="inp" name="size" value="<?=htmlspecialchars($focusOrg['size'])?>" style="height:36px"></div>
            </div>
            <div class="fld"><label style="font-size:11.5px;color:var(--faint)">方案类型</label>
              <select name="plan_type" class="inp" style="height:36px"><?php foreach ($plans as $k => $p): ?><option value="<?=$k?>" <?=$focusOrg['plan_type']===$k?'selected':''?>><?=$p['label']?></option><?php endforeach; ?></select>
            </div>
            <div class="fld"><label style="font-size:11.5px;color:var(--faint)">预算</label><input class="inp" name="budget" value="<?=htmlspecialchars($focusOrg['budget'])?>" style="height:36px"></div>
            <div class="fld"><label style="font-size:11.5px;color:var(--faint)">联系人</label><input class="inp" name="contact_name" value="<?=htmlspecialchars($focusOrg['contact_name'])?>" style="height:36px"></div>
            <div class="fld"><label style="font-size:11.5px;color:var(--faint)">联系电话</label><input class="inp" name="contact_phone" value="<?=htmlspecialchars($focusOrg['contact_phone'])?>" style="height:36px"></div>
            <div class="fld"><label style="font-size:11.5px;color:var(--faint)">需求备注</label><textarea class="inp" name="notes" rows="3"><?=htmlspecialchars($focusOrg['notes'])?></textarea></div>
            <button class="btn btn-s btn-sm">保存修改</button>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($orgs)): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--faint)">暂无企业客户<?=$statusFilter?'（当前状态筛选）':''?>。官网「商业发行版」表单提交后自动进入这里。</div>
    <?php endif; ?>

    <?php foreach ($orgs as $o): ?>
    <div class="org-card">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span class="org-name"><?=htmlspecialchars($o['name'])?></span>
        <span class="st-badge" style="background:<?=($statuses[$o['status']]['color'] ?? '#888')?>"><?=org_status_label($o['status'])?></span>
        <span class="st-badge" style="background:oklch(52% .17 258)"><?=org_plan_label($o['plan_type'])?></span>
        <a href="/xmp/orgs?focus=<?=urlencode($o['id'])?>" class="btn btn-s btn-sm" style="margin-left:auto">管理 →</a>
      </div>
      <div class="org-meta"><?=htmlspecialchars($o['industry'])?> / <?=htmlspecialchars($o['size'])?> / 预算:<?=htmlspecialchars($o['budget'])?> / 联系人:<?=htmlspecialchars($o['contact_name'])?> / <?=htmlspecialchars($o['contact_email'])?></div>
      <?php if (!empty($o['notes'])): ?><div class="org-desc"><?=htmlspecialchars(mb_substr($o['notes'], 0, 80))?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php admin_footer(); ?>
