<?php
/**
 * 桌宠审核 — 松萝纸偶形象（.lianapet）投稿审核 / 下架
 *
 * 审核要点：图片原创或已获授权（不含他人肖像、未授权角色、商标）；内容合规；定价合理。
 * 服务端已在投稿时校验过结构（只含图片与 JSON、rights=shareable、有平时图），这里只做人工判断。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_once __DIR__ . '/../lib/PetMarket.php';
require_login();
require_perm('marketplace');

// 预览图 / 下载原包检查（只读，管理员可看待审核的投稿）
if (isset($_GET['preview']) || isset($_GET['file'])) {
    $it = pet_sub_find((string) ($_GET['preview'] ?? $_GET['file']));
    $path = $it ? (isset($_GET['preview']) ? pet_preview_path($it) : pet_file_path($it)) : '';
    if ($path === '' || !is_file($path)) { http_response_code(404); exit; }
    header('X-Content-Type-Options: nosniff');
    if (isset($_GET['preview'])) {
        header('Content-Type: image/png');
    } else {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $it['pack_id'] . '-r' . (int) ($it['revision'] ?? 1) . '.lianapet"');
    }
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $r = pet_review((string) ($_POST['sub_id'] ?? ''), (string) ($_POST['decision'] ?? ''), (string) ($_POST['note'] ?? ''));
    $labels = ['approve' => '已通过并上架', 'reject' => '已驳回', 'unlist' => '已下架'];
    flash($r['ok'] ? 'success' : 'error', $r['ok'] ? ($labels[$_POST['decision'] ?? ''] ?? '已处理') : ($r['error'] ?? '操作失败'));
    header('Location: /xmp/pets?tab=' . urlencode((string) ($_GET['tab'] ?? 'pending')));
    exit;
}

$tab = (string) ($_GET['tab'] ?? 'pending');
$all = pet_all();
usort($all, fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
$groups = [
    'pending' => ['label' => '待审核', 'match' => ['pending']],
    'published' => ['label' => '已上架', 'match' => ['published']],
    'closed' => ['label' => '驳回 / 下架 / 旧版本', 'match' => ['rejected', 'unlisted', 'superseded']],
];
if (!isset($groups[$tab])) $tab = 'pending';
$rows = array_values(array_filter($all, fn($it) => in_array($it['status'] ?? '', $groups[$tab]['match'], true)));
$statusText = ['pending' => '待审核', 'published' => '已上架', 'rejected' => '已驳回', 'unlisted' => '已下架', 'superseded' => '旧版本'];

admin_header('桌宠审核');
?>
<div class="admin-layout">
  <?php admin_sidebar('pets'); ?>
  <div class="main">
    <h1>桌宠审核</h1>
    <p class="sub">松萝纸偶形象投稿 · 只收原创或已获授权的形象 · 前台 <a href="/marketplace?type=pet" target="_blank">/marketplace?type=pet</a> · 松萝官方形象源 <a href="/api/pets" target="_blank">/api/pets</a></p>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <?php foreach ($groups as $k => $g): $n = count(array_filter($all, fn($it) => in_array($it['status'] ?? '', $g['match'], true))); ?>
      <a href="?tab=<?=$k?>" class="btn <?=$tab === $k ? 'btn-primary' : 'btn-ghost'?> btn-sm"><?=$g['label']?> <?=$n?></a>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>形象</th><th>作者</th><th>许可证 / 价格</th><th>版本</th><th>下载</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?><tr><td colspan="7" class="empty"><?=empty_state('这里还没有投稿', '认证开发者在会员中心「开发者中心」发布桌宠后会出现在这里；先确认有通过认证的开发者。', '开发者审核', '/xmp/developers')?></td></tr><?php endif; ?>
          <?php foreach ($rows as $it): $sid = htmlspecialchars((string) $it['sub_id']); ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <img src="?preview=<?=urlencode((string) $it['sub_id'])?>" alt="" style="height:64px;max-width:64px;object-fit:contain;background:var(--surface-2);border-radius:8px">
                <div>
                  <strong><?=htmlspecialchars((string) $it['name'])?></strong>
                  <div class="text-sm text-muted mono"><?=htmlspecialchars((string) $it['pack_id'])?></div>
                  <div class="text-sm text-muted"><?=htmlspecialchars(mb_substr((string) ($it['description'] ?? ''), 0, 60))?></div>
                </div>
              </div>
            </td>
            <td class="text-sm"><?=htmlspecialchars((string) ($it['author_name'] ?? ''))?></td>
            <td class="text-sm"><?=htmlspecialchars((string) ($it['license'] ?? ''))?><br><?=(float) ($it['price'] ?? 0) > 0 ? '¥' . number_format((float) $it['price'], 2) : '免费'?></td>
            <td class="mono">r<?=(int) ($it['revision'] ?? 1)?></td>
            <td><?=(int) ($it['downloads'] ?? 0)?></td>
            <td class="text-sm"><?=$statusText[$it['status']] ?? htmlspecialchars((string) $it['status'])?><?php if (!empty($it['review_note'])): ?><div class="text-muted"><?=htmlspecialchars((string) $it['review_note'])?></div><?php endif; ?></td>
            <td style="white-space:nowrap">
              <a href="?file=<?=urlencode((string) $it['sub_id'])?>" class="btn btn-ghost btn-sm">下载检查</a>
              <?php if (($it['status'] ?? '') === 'pending'): ?>
              <form method="post" style="display:inline" data-confirm="通过并上架「<?=htmlspecialchars((string) $it['name'])?>」？">
                <?=csrf_field()?><input type="hidden" name="sub_id" value="<?=$sid?>"><input type="hidden" name="decision" value="approve">
                <button type="submit" class="btn btn-primary btn-sm">✓ 通过</button>
              </form>
              <form method="post" style="display:inline-flex;gap:4px" data-confirm="驳回这个投稿？">
                <?=csrf_field()?><input type="hidden" name="sub_id" value="<?=$sid?>"><input type="hidden" name="decision" value="reject">
                <input type="text" name="note" placeholder="驳回理由（作者可见）" style="width:150px;padding:4px 8px;font-size:12px">
                <button type="submit" class="btn btn-danger btn-sm">✕ 驳回</button>
              </form>
              <?php elseif (($it['status'] ?? '') === 'published'): ?>
              <a href="/pet/<?=urlencode((string) $it['pack_id'])?>" target="_blank" class="btn btn-ghost btn-sm">前台</a>
              <form method="post" style="display:inline-flex;gap:4px" data-confirm="下架「<?=htmlspecialchars((string) $it['name'])?>」？已购买的用户不受影响。">
                <?=csrf_field()?><input type="hidden" name="sub_id" value="<?=$sid?>"><input type="hidden" name="decision" value="unlist">
                <input type="text" name="note" placeholder="下架原因" style="width:120px;padding:4px 8px;font-size:12px">
                <button type="submit" class="btn btn-danger btn-sm">下架</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
