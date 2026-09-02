<?php
/**
 * 优惠券管理 — 满减/折扣/无门槛券，限时限量
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CouponSystem.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save'])) {
        $r = coupon_save($_POST);
        $message = $r['ok'] ? '优惠券已保存' : ('保存失败：' . ($r['error'] ?? ''));
    } elseif (isset($_POST['delete'])) {
        coupon_delete(trim($_POST['id'] ?? ''));
        $message = '优惠券已删除';
    }
    header('Location: /xmp/coupons');
    exit;
}

$coupons = coupon_all();
$editId = trim($_GET['edit'] ?? '');
$edit = $editId !== '' ? coupon_get($editId) : null;

admin_header('优惠券');
?>
<div class="admin-layout">
  <?php admin_sidebar('coupons'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>优惠券</h1><p class="v-sub">满减 / 折扣 / 无门槛券，可用于课程与商城商品，限时限量。</p></div>
      <div class="v-actions"><?php if ($message): ?><span class="st st-ok"><?=htmlspecialchars($message)?></span><?php endif; ?></div>
    </div>

    <?php if ($edit): ?>
    <form method="post" class="card" style="padding:24px;margin-bottom:20px;max-width:720px">
      <input type="hidden" name="save" value="1">
      <input type="hidden" name="id" value="<?=$edit['id']?>">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:14px">编辑优惠券</h3>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="fld"><label style="font-size:12px;color:var(--faint)">优惠码 *</label><input class="inp" name="code" value="<?=htmlspecialchars($edit['code']??'')?>" required style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">名称 *</label><input class="inp" name="name" value="<?=htmlspecialchars($edit['name']??'')?>" required style="height:38px"></div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr">
        <div class="fld"><label style="font-size:12px;color:var(--faint)">类型</label>
          <select class="inp" name="type" style="height:38px">
            <option value="fixed" <?=($edit['type']??'')==='fixed'?'selected':''?>>满减（减固定金额）</option>
            <option value="percent" <?=($edit['type']??'')==='percent'?'selected':''?>>折扣（减百分比）</option>
            <option value="free" <?=($edit['type']??'')==='free'?'selected':''?>>无门槛（全免）</option>
          </select></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">面值 ¥ 或 %</label><input class="inp" type="number" name="value" value="<?=htmlspecialchars($edit['value']??'0')?>" step="0.01" style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">最低消费 ¥</label><input class="inp" type="number" name="min_amount" value="<?=htmlspecialchars($edit['min_amount']??'0')?>" step="0.01" style="height:38px"></div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr">
        <div class="fld"><label style="font-size:12px;color:var(--faint)">总用量上限（0=不限）</label><input class="inp" type="number" name="max_uses" value="<?=htmlspecialchars($edit['max_uses']??'0')?>" style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">开始时间</label><input class="inp" type="datetime-local" name="start_time" value="<?=$edit['start_time']?str_replace(' ','T',substr($edit['start_time'],0,16)):''?>" style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">结束时间</label><input class="inp" type="datetime-local" name="end_time" value="<?=$edit['end_time']?str_replace(' ','T',substr($edit['end_time'],0,16)):''?>" style="height:38px"></div>
      </div>
      <div class="fld"><label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--faint)"><input type="checkbox" name="status" value="active" <?=($edit['status']??'')==='active'?'checked':''?> style="width:16px;height:16px"> 启用</label></div>
      <button class="btn btn-p btn-sm">保存</button>
      <a href="/xmp/coupons" class="btn btn-s btn-sm">取消</a>
    </form>
    <?php endif; ?>

    <form method="post" class="card" style="padding:24px;margin-bottom:20px;max-width:720px">
      <input type="hidden" name="save" value="1">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:14px">新建优惠券</h3>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="fld"><label style="font-size:12px;color:var(--faint)">优惠码 *</label><input class="inp" name="code" placeholder="如 SUMMER20" required style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">名称 *</label><input class="inp" name="name" placeholder="如 暑期 8 折券" required style="height:38px"></div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr">
        <div class="fld"><label style="font-size:12px;color:var(--faint)">类型</label>
          <select class="inp" name="type" style="height:38px"><option value="fixed">满减</option><option value="percent">折扣</option><option value="free">无门槛</option></select></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">面值 ¥ 或 %</label><input class="inp" type="number" name="value" value="10" step="0.01" style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">最低消费 ¥</label><input class="inp" type="number" name="min_amount" value="0" step="0.01" style="height:38px"></div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr">
        <div class="fld"><label style="font-size:12px;color:var(--faint)">总用量上限（0=不限）</label><input class="inp" type="number" name="max_uses" value="0" style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">开始时间</label><input class="inp" type="datetime-local" name="start_time" style="height:38px"></div>
        <div class="fld"><label style="font-size:12px;color:var(--faint)">结束时间</label><input class="inp" type="datetime-local" name="end_time" style="height:38px"></div>
      </div>
      <input type="hidden" name="status" value="active">
      <button class="btn btn-p btn-sm">创建优惠券</button>
    </form>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>优惠码</th><th>名称</th><th>类型</th><th>面值</th><th>门槛</th><th>用量</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($coupons)): ?><tr><td colspan="9" class="empty">暂无优惠券，先创建一张</td></tr><?php endif; ?>
          <?php foreach ($coupons as $c): $typeLabel = ['fixed'=>'满减','percent'=>'折扣','free'=>'无门槛'][$c['type']]??$c['type']; ?>
          <tr>
            <td><strong><?=htmlspecialchars($c['code'])?></strong></td>
            <td><?=htmlspecialchars($c['name'])?></td>
            <td><span class="badge" style="background:var(--surface-2);padding:2px 8px;border-radius:999px;font-size:11px"><?=$typeLabel?></span></td>
            <td style="color:var(--ok);font-weight:600"><?=$c['type']==='percent'?$c['value'].'%':'¥'.number_format($c['value'],0)?></td>
            <td class="text-sm text-muted"><?=$c['min_amount']>0?'满¥'.number_format($c['min_amount'],0):'无门槛'?></td>
            <td><?=$c['used_count']?><?=$c['max_uses']>0?'/'.$c['max_uses']:''?></td>
            <td class="text-sm text-muted"><?=substr($c['start_time']??'',0,10)?> ~ <?=substr($c['end_time']??'',0,10)?></td>
            <td><?=($c['status']??'')==='active'?'<span style="color:var(--ok)">启用</span>':'<span style="color:var(--faint)">停用</span>'?></td>
            <td><a href="?edit=<?=$c['id']?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="delete" value="1"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-danger btn-sm" data-confirm="确认删除？">删除</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
