<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ActivationSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';

require_login();
if (!has_perm('shop-settings') && !has_perm('settings')) { http_response_code(403); exit('无权限'); }

$message = '';
$error = '';

// 创建批次
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_batch'])) {
    csrf_verify();
    $title = trim($_POST['batch_title'] ?? '');
    $goodsType = trim($_POST['goods_type'] ?? 'course');
    $goodsId = trim($_POST['goods_id'] ?? '');
    $total = (int)($_POST['total'] ?? 0);
    if ($title && $total > 0 && $total <= 1000) {
        $r = act_create_batch($title, $goodsType, $goodsId, $total);
        $message = "已生成批次 {$r['batch_id']}，共 {$r['total']} 个激活码";
    } else {
        $error = '请填写标题，数量 1-1000';
    }
}

// 标记已售
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_sold'])) {
    csrf_verify();
    $r = act_mark_sold($_POST['mark_code'] ?? '', trim($_POST['buyer'] ?? ''));
    if ($r['ok']) $message = '已标记激活码为已售';
    else $error = $r['error'] ?? '操作失败';
}

$batches = act_batch_stats();
$courses = json_read(DATA_DIR . '/courses/index.json');
$batchView = $_GET['batch'] ?? '';

admin_header('激活码管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('shop-settings'); ?>
  <div class="main">
<h1>激活码体系</h1>
<p class="sub">渠道批量采购激活码，用户凭码自助激活课程或服务</p>

<?php if ($message): ?><div class="msg msg-success"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg msg-error"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="card" style="max-width:520px">
  <h2>➕ 生成激活码批次</h2>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label>批次标题</label><input type="text" name="batch_title" class="inp" placeholder="如：2026 Q1 渠道采购 - 增长引擎课" required></div>
    <div class="field-row">
      <div class="field"><label>商品类型</label>
        <select name="goods_type" class="inp">
          <option value="course">课程</option>
          <option value="subscription">付费订阅</option>
          <option value="consult">1v1 咨询</option>
        </select>
      </div>
      <div class="field"><label>关联商品 ID</label>
        <select name="goods_id" class="inp" id="goodsSelect">
          <option value="">— 选择课程 —</option>
          <?php foreach ($courses as $c): ?><option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars($c['title'])?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field"><label>生成数量 (1-1000)</label><input type="number" name="total" class="inp" value="50" min="1" max="1000" required></div>
    <button type="submit" name="create_batch" class="btn primary">生成激活码批次</button>
  </form>
</div>

<div class="card">
  <h2>📦 激活码批次列表</h2>
  <?php if (empty($batches)): ?>
  <div class="empty">暂无批次。生成批次后，渠道可批量采购激活码。</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>批次</th><th>商品</th><th>总量</th><th>未售</th><th>已售</th><th>已激活</th><th>生成时间</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($batches) as $b): ?>
      <tr>
        <td><strong><?=htmlspecialchars($b['batch']['title'])?></strong><br><code><?=htmlspecialchars($b['batch']['id'])?></code></td>
        <td><?=htmlspecialchars($b['batch']['goods_type'])?></td>
        <td><?=$b['total']?></td>
        <td><?=$b['remaining']?></td>
        <td><?=$b['sold']?></td>
        <td><?=$b['activated']?></td>
        <td class="text-sm text-muted"><?=htmlspecialchars(substr($b['batch']['created_at'] ?? '', 0, 16))?></td>
        <td><a href="?batch=<?=urlencode($b['batch']['id'])?>" class="btn btn-sm btn-ghost">查看/导出</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($batchView): $codes = act_batch_codes($batchView); ?>
<div class="card">
  <h2>🔑 批次激活码 (<?=count($codes)?> 个)</h2>
  <?php if (empty($codes)): ?>
  <div class="empty">该批次无激活码</div>
  <?php else: ?>
  <div style="overflow:auto;max-height:400px">
  <table>
    <thead><tr><th>激活码</th><th>状态</th><th>购货方</th><th>激活用户</th><th>激活时间</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($codes) as $c): ?>
      <tr>
        <td><code><?=htmlspecialchars($c['code'])?></code></td>
        <td>
          <?php if ($c['status'] === 'unsold'): ?><span class="pill gray">未售</span>
          <?php elseif ($c['status'] === 'sold'): ?><span class="pill warn"><span class="dot"></span>已售</span>
          <?php else: ?><span class="pill ok"><span class="dot"></span>已激活</span><?php endif; ?>
        </td>
        <td class="text-sm text-muted"><?=htmlspecialchars($c['sold_to'] ?? '')?></td>
        <td class="text-sm text-muted"><?=htmlspecialchars($c['activated_by'] ?? '')?></td>
        <td class="text-sm text-muted"><?=htmlspecialchars(substr($c['activated_at'] ?? '', 0, 16))?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <button class="btn btn-ghost mt-4" onclick="exportBatch()">⬇ 导出未售激活码</button>
  <script>
  function exportBatch() {
    var codes = <?=json_encode(array_map(fn($c) => $c['code'], array_filter($codes, fn($c) => $c['status'] === 'unsold')))?>;
    if (!codes.length) { alert('无未售激活码'); return; }
    var text = codes.join('\n');
    var a = document.createElement('a');
    a.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(text);
    a.download = 'batch-<?=htmlspecialchars($batchView)?>-codes.txt';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  }
  </script>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>🔓 激活码登记（渠道线下售出后标记）</h2>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= csrf_field() ?>
    <div class="field" style="margin-bottom:0"><label>激活码</label><input type="text" name="mark_code" class="inp" placeholder="XXXX-XXXX-XXXX" required></div>
    <div class="field" style="margin-bottom:0"><label>购货方/渠道备注</label><input type="text" name="buyer" class="inp" placeholder="如：渠道A / 经销商B"></div>
    <button type="submit" name="mark_sold" class="btn btn-ghost">标记为已售</button>
  </form>
</div>

<div class="card">
  <h2>ℹ️ 前台激活入口</h2>
  <p class="text-sm text-muted">用户持激活码，登录后访问会员中心或指定激活页面输入激活码，即可解锁对应课程/服务。</p>
  <p class="text-sm" style="margin-top:8px">激活接口：<code>POST /api/activation.php</code>（body: action=activate, code=XXXX-XXXX-XXXX）</p>
</div>
  </div>
</div>

<?php admin_footer(); ?>
