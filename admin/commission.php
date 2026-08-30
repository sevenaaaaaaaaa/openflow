<?php
/**
 * 分成与结算政策 —— 平台经济学的唯一设置处（BACKLOG T0-5）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CommissionPolicy.php';
require_login();
require_perm('commerce');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    commission_policy_save([
        'platform_fee_rate' => ((float)($_POST['platform_fee_pct'] ?? 10)) / 100,
        'distribution_rate' => (float)($_POST['distribution_rate'] ?? 20),
        'min_withdraw'      => (float)($_POST['min_withdraw'] ?? 100),
    ]);
    audit('更新分成政策', 'commerce');
    $msg = '已保存。各交易系统（商城/数字商品/收款）自即刻起按此结算。';
}
$p = commission_policy();
$feePct = round($p['platform_fee_rate'] * 100, 2);
admin_header('分成与结算政策');
?>
<div style="max-width:720px">
  <h1 style="margin:0 0 4px">💰 分成与结算政策</h1>
  <p class="v-sub" style="margin:0 0 16px">平台怎么抽成、分销拿多少、多少能提现——一处设定，商城 / 数字商品 / 收款单统一引用。不设则用默认。</p>
  <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:14px;border-left:3px solid #16a34a"><?=htmlspecialchars($msg)?></div><?php endif; ?>

  <form method="post" class="card" style="padding:20px">
    <?= csrf_field() ?>
    <div style="margin-bottom:16px">
      <label style="display:block;font-weight:600;margin-bottom:4px">平台抽成率（%）</label>
      <div class="v-sub" style="margin-bottom:6px">每笔成交平台留存的比例（覆盖支付手续费等）。作者/创作者拿扣除平台费与分销佣金后的余额。</div>
      <input name="platform_fee_pct" type="number" min="0" max="90" step="0.5" value="<?=htmlspecialchars((string)$feePct)?>" style="width:140px"> %
    </div>
    <div style="margin-bottom:16px">
      <label style="display:block;font-weight:600;margin-bottom:4px">默认分销/联盟佣金率（%）</label>
      <div class="v-sub" style="margin-bottom:6px">有推广人成交时，推广人默认拿的比例。单个商品可另设覆盖此默认。</div>
      <input name="distribution_rate" type="number" min="0" max="100" step="0.5" value="<?=htmlspecialchars((string)$p['distribution_rate'])?>" style="width:140px"> %
    </div>
    <div style="margin-bottom:20px">
      <label style="display:block;font-weight:600;margin-bottom:4px">最低提现额（元）</label>
      <input name="min_withdraw" type="number" min="0" step="1" value="<?=htmlspecialchars((string)$p['min_withdraw'])?>" style="width:140px">
    </div>
    <button class="btn btn-primary">保存政策</button>
  </form>

  <div class="card" style="padding:16px;margin-top:16px">
    <div style="font-weight:700;margin-bottom:8px">举例：一笔 ¥1000 的成交（有推广人）</div>
    <?php $ex = commission_split(1000, true); ?>
    <div style="font-size:14px;line-height:1.9">
      平台费（<?=$feePct?>%）：<strong>¥<?=number_format($ex['platform_fee'],2)?></strong><br>
      分销佣金（<?=$p['distribution_rate']?>%）：<strong>¥<?=number_format($ex['commission'],2)?></strong><br>
      作者/创作者到手：<strong>¥<?=number_format($ex['author_amount'],2)?></strong>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
