<?php
/**
 * 开发者入驻审核 — 审核开发者申请，管理开发者
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_login();
require_perm('settings');

$message = '';

// 审核处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $memberId = $_POST['member_id'] ?? '';
    $decision = $_POST['decision'] ?? '';
    $members = member_get_all();
    foreach ($members as &$m) {
        if ($m['id'] === $memberId) {
            if ($decision === 'approve') { $m['developer_status'] = 'approved'; $m['developer_reviewed_at'] = date('Y-m-d H:i:s'); }
            elseif ($decision === 'reject') { $m['developer_status'] = 'rejected'; $m['developer_reviewed_at'] = date('Y-m-d H:i:s'); }
            member_save($m);
            break;
        }
    }
    $message = '开发者状态已更新';
    header('Location: /xmp/developers');
    exit;
}

$members = member_get_all();
$pending = array_values(array_filter($members, fn($m) => ($m['developer_status'] ?? '') === 'pending'));
$approved = array_values(array_filter($members, fn($m) => ($m['developer_status'] ?? '') === 'approved'));

admin_header('开发者审核');
?>
<div class="admin-layout">
  <?php admin_sidebar('developers'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>开发者审核</h1><p class="v-sub">开发者入驻申请与认证管理。开发者提交的 Skill/主题在「生态市场」的待审核状态中处理。</p></div>
      <div class="v-actions"><?php if ($message): ?><span class="st st-ok"><?=htmlspecialchars($message)?></span><?php endif; ?></div>
    </div>

    <h3 style="font-size:15px;font-weight:700;margin:20px 0 12px">待审核申请（<?=count($pending)?>）</h3>
    <?php if (empty($pending)): ?><div class="card" style="padding:30px;text-align:center;color:var(--faint)">暂无待审核的开发者申请</div>
    <?php else: foreach ($pending as $m): ?>
    <div class="card" style="padding:18px 20px;margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-weight:700"><?=strtoupper(mb_substr($m['name'] ?? '?', 0, 1))?></div>
        <div style="flex:1;min-width:200px">
          <div style="font-weight:700"><?=htmlspecialchars($m['name'] ?? '')?> <span style="font-size:12px;color:var(--muted);font-weight:400"><?=htmlspecialchars($m['email'] ?? '')?></span></div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:4px"><?=htmlspecialchars($m['developer_bio'] ?? '')?></div>
          <?php if (!empty($m['developer_skills'])): ?><div style="font-size:11.5px;color:var(--accent);margin-top:4px">擅长：<?=htmlspecialchars($m['developer_skills'])?></div><?php endif; ?>
          <?php if (!empty($m['developer_website'])): ?><div style="font-size:11.5px;color:var(--faint)"><?=htmlspecialchars($m['developer_website'])?></div><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="member_id" value="<?=htmlspecialchars($m['id'])?>">
            <input type="hidden" name="decision" value="approve">
            <button class="btn btn-primary btn-sm">✓ 通过</button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="member_id" value="<?=htmlspecialchars($m['id'])?>">
            <input type="hidden" name="decision" value="reject">
            <button class="btn btn-danger btn-sm">✕ 拒绝</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <h3 style="font-size:15px;font-weight:700;margin:24px 0 12px">已认证开发者（<?=count($approved)?>）</h3>
    <?php if (empty($approved)): ?><div class="card" style="padding:24px;text-align:center;color:var(--faint)">暂无认证开发者</div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>开发者</th><th>邮箱</th><th>简介</th><th>认证时间</th></tr></thead>
        <tbody>
          <?php foreach ($approved as $m): ?>
          <tr>
            <td><strong><?=htmlspecialchars($m['name'] ?? '')?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($m['email'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(mb_substr($m['developer_bio'] ?? '', 0, 50))?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($m['developer_reviewed_at'] ?? '—')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
