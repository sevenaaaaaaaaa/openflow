<?php
/**
 * 矩阵互通 — 兄弟产品账号互通管理(secret 签发 / 回跳地址 / 启用状态)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MatrixTicket.php';
require_login();
require_perm('settings');

$msg = (string)($_GET['msg'] ?? '');
$kind = (string)($_GET['kind'] ?? '');
$issued = $_SESSION['mtx_issued'] ?? null;   // 刚签发的 secret,只显示一次
unset($_SESSION['mtx_issued']);

admin_header('矩阵互通');
?>
<div class="admin-layout">
  <?php admin_sidebar('matrix'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>矩阵互通</h1><p class="v-sub">OpenFlow 是芭乐派产品矩阵的账号真源:兄弟产品(MFlow / inFlow / UserLoop / PayFlow / LearnFlow…)用「一次性短票 + client_secret」兑换用户身份,实现免二次登录。票据 60 秒有效、用后即焚,泄露窗口最小。</p></div>
    </div>

    <?php if ($msg): ?><div class="card" style="border-color:<?=$kind === 'error' ? 'var(--danger)' : 'var(--ok)'?>"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <?php if ($issued): ?>
    <div class="card" style="border-color:var(--warn)">
      <h2 style="font-size:15px">⚠️ 新 secret 已生成(仅此一次可见,关页即失)</h2>
      <p class="mono" style="word-break:break-all;user-select:all"><?=htmlspecialchars($issued['secret'])?></p>
      <p class="note">把它配到 <b><?=htmlspecialchars($issued['client_id'])?></b> 的环境变量或后台设置(如 <code>MATRIX_CLIENT_SECRET</code>),并记下回跳地址。</p>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2 style="font-size:15px">互通工作方式</h2>
      <p style="font-size:13.5px;line-height:1.9;color:var(--muted)">
        ① 用户在 OpenFlow 登录(账号真源)→ ② 点某产品的「进入」链接 <code>/api/matrix.php?action=enter&app=mflow</code>
        → ③ OpenFlow 签发 60 秒一次性短票,302 到该产品的 <code>/auth/matrix?ticket=…</code>
        → ④ 产品后端拿票 + 自己的 <code>client_secret</code> 调 <code>POST /api/matrix.php?action=redeem</code>
        → ⑤ 拿到用户身份(id/email/name/level),建立本产品会话。兑换必须走后端(secret 不出网),票用后即焚。
      </p>
    </div>

    <div class="card">
      <h2 style="font-size:15px">已登记应用</h2>
      <table class="tbl">
        <thead><tr><th>应用</th><th>client_id</th><th>回跳地址</th><th>状态</th><th style="width:280px">操作</th></tr></thead>
        <tbody>
        <?php foreach (matrix_clients() as $c): $enabled = !empty($c['secret_hash']) && !empty($c['redirect_base']); ?>
        <tr>
          <td><?=htmlspecialchars($c['name'])?></td>
          <td class="mono"><?=htmlspecialchars($c['client_id'])?></td>
          <td class="mono" style="max-width:260px;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars((string)($c['redirect_base'] ?? '')) ?: '<span class="note">未配置</span>'?></td>
          <td><?=$enabled ? '<span class="badge ok">已启用</span>' : '<span class="badge">未启用</span>'?></td>
          <td>
            <form method="post" action="/api/matrix-admin.php" style="display:flex;gap:6px;flex-wrap:wrap" data-confirm="为 <?=$c['client_id']?> 轮换 secret?旧 secret 立即失效,需同步更新到对方项目。">
              <input type="hidden" name="action" value="issue">
              <input type="hidden" name="client_id" value="<?=htmlspecialchars($c['client_id'])?>">
              <input class="inp sm" name="redirect_base" placeholder="https://mflow.example.com" value="<?=htmlspecialchars((string)($c['redirect_base'] ?? ''))?>" style="width:170px" <?=$enabled ? 'disabled' : ''?>>
              <button class="btn btn-s btn-sm"><?=$enabled ? '轮换 secret' : '签发 secret 并启用'?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
