<?php
/**
 * 转化目标管理 — 命名目标（事件+作用域+价值），落地页/A-B/报表统一口径
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ConversionGoal.php';
require_login();
require_perm('conversion');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'save') {
        $r = cg_save([
            'id' => $_POST['id'] ?? '', 'name' => $_POST['name'] ?? '', 'event' => $_POST['event'] ?? '',
            'scope' => $_POST['scope'] ?? '', 'value' => $_POST['value'] ?? 0,
            'revenue_mode' => !empty($_POST['revenue_mode']), 'status' => $_POST['status'] ?? 'active',
        ]);
        $msg = $r['ok'] ? '目标已保存' : ('保存失败：' . ($r['error'] ?? ''));
    } elseif ($act === 'delete') {
        cg_delete((string)($_POST['id'] ?? ''));
        $msg = '目标已删除';
    }
}
$goals = cg_stats_all(30);
$eventOptions = ['page_view' => '页面浏览', 'form_submit' => '表单提交', 'purchase' => '完成购买', 'member_register' => '注册会员', 'download' => '下载资料', 'share' => '分享', 'consultation_done' => '完成咨询', 'event_register' => '活动报名'];
admin_header('转化目标');
?>
<div class="admin-layout">
  <?php admin_sidebar('conversion-goals'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>转化目标</h1><p class="v-sub">把"目标事件 + 作用域 + 价值"定义成可命名目标；落地页与报表都挂在它上面，口径统一。</p></div>
      <div class="v-actions"><a class="btn btn-s btn-sm" href="/xmp/conversion">转化组件</a></div>
    </div>
    <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid var(--ok)"><?=htmlspecialchars($msg)?></div><?php endif; ?>

    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="flex:2;min-width:320px">
        <?php if (!$goals): ?><div class="card"><div class="empty" style="padding:20px">还没有转化目标。用右侧表单建一个。</div></div><?php endif; ?>
        <?php foreach ($goals as $s): $g = $s['goal']; ?>
        <div class="card" style="margin-bottom:10px">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <b><?=htmlspecialchars($g['name'])?></b>
            <span class="badge badge-gray mono"><?=htmlspecialchars($g['event'])?></span>
            <?php if ($g['scope'] !== ''): ?><span class="text-sm text-muted">作用域 <?=htmlspecialchars($g['scope'])?></span><?php endif; ?>
            <span class="badge <?=$g['status'] === 'active' ? 'badge-green' : 'badge-gray'?>"><?=$g['status'] === 'active' ? '启用' : '暂停'?></span>
          </div>
          <div class="kpi-grid" style="margin-top:10px">
            <div class="kpi"><div class="k-label">近30天转化</div><div class="k-val mono"><?=$s['conversions']?></div></div>
            <div class="kpi"><div class="k-label">转化价值</div><div class="k-val mono">¥<?=number_format($s['value'], 0)?></div></div>
            <div class="kpi"><div class="k-label">转化率</div><div class="k-val mono"><?=$s['rate']?>%</div><div class="k-sub">/ <?=$s['baseline']?> 次浏览</div></div>
          </div>
          <div style="margin-top:10px;display:flex;gap:8px">
            <a class="btn btn-ghost btn-sm" href="?edit=<?=urlencode($g['id'])?>">编辑</a>
            <form method="post" data-no-guard data-confirm="删除该转化目标？">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($g['id'])?>">
              <button class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php $edit = isset($_GET['edit']) ? cg_get((string)$_GET['edit']) : null; ?>
      <div style="flex:1;min-width:280px">
        <div class="card">
          <h2 style="font-size:15px"><?=$edit ? '编辑目标' : '新建目标'?></h2>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>"><?php endif; ?>
            <div class="field"><label>名称</label><input class="inp" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required placeholder="如：落地页预约转化"></div>
            <div class="field"><label>目标事件</label>
              <select class="inp" name="event"><?php foreach ($eventOptions as $k => $v): ?><option value="<?=$k?>" <?=($edit['event'] ?? '') === $k ? 'selected' : ''?>><?=htmlspecialchars($v . '（' . $k . '）')?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>作用域 URL 前缀 <span class="hint">· 空=全站</span></label><input class="inp" name="scope" value="<?=htmlspecialchars($edit['scope'] ?? '')?>" placeholder="/landing/xxx 或 /course/"></div>
            <div class="field"><label>每次转化价值 <span class="hint">· 元（非成交类）</span></label><input class="inp" type="number" step="0.01" name="value" value="<?=htmlspecialchars((string)($edit['value'] ?? 0))?>"></div>
            <label style="display:block;font-size:13px;margin:6px 0"><input type="checkbox" name="revenue_mode" value="1" <?=!empty($edit['revenue_mode'])?'checked':''?>> 成交类（价值取订单实付金额）</label>
            <div class="field"><label>状态</label><select class="inp" name="status"><option value="active" <?=($edit['status'] ?? 'active') === 'active' ? 'selected' : ''?>>启用</option><option value="paused" <?=($edit['status'] ?? '') === 'paused' ? 'selected' : ''?>>暂停</option></select></div>
            <button class="btn btn-p btn-sm"><?=$edit ? '更新' : '创建'?></button>
            <?php if ($edit): ?><a class="btn btn-ghost btn-sm" href="/xmp/conversion-goals">取消</a><?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
