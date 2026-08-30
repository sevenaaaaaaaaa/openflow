<?php
/**
 * 收款链接 / 报价单 —— 开单、拿链接、看状态
 *
 * 一人公司的成交闭环：给客户开一张收款单 → 复制链接发过去 → 客户付款
 * → 对应 CRM 线索自动推进为「已成交」。付款/退款都复用商城那套。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/QuoteSystem.php';
require_login();
require_perm('quotes');

$message = ''; $error = ''; $newLink = '';

if (($_POST['action'] ?? '') === 'create') {
    $items = [];
    $names  = (array)($_POST['item_name'] ?? []);
    $qtys   = (array)($_POST['item_qty'] ?? []);
    $prices = (array)($_POST['item_price'] ?? []);
    foreach ($names as $i => $n) {
        if (trim((string)$n) === '') continue;
        $items[] = ['name' => $n, 'qty' => $qtys[$i] ?? 1, 'price' => $prices[$i] ?? 0];
    }
    $r = quote_create([
        'title'    => $_POST['title'] ?? '',
        'amount'   => $_POST['amount'] ?? 0,
        'email'    => $_POST['email'] ?? '',
        'customer' => $_POST['customer'] ?? '',
        'note'     => $_POST['note'] ?? '',
        'expires_at' => $_POST['expires_at'] ?? '',
        'items'    => $items,
    ]);
    if ($r['ok']) {
        $newLink = $r['pay_url'];
        $message = '收款单已创建，把下面的链接发给客户即可。';
        audit('创建收款单 ' . $r['order']['id'] . ' ¥' . $r['order']['amount'], 'shop', ['email' => $r['order']['crm_email']]);
    } else {
        $error = $r['error'];
    }
}

$quotes = quote_all();
$statusLabel = ['pending'=>'待支付','paid'=>'已支付','refunded'=>'已退款','cancelled'=>'已取消'];
$sumPaid = array_sum(array_map(fn($q) => ($q['status'] ?? '') === 'paid' ? (float)($q['amount'] ?? 0) : 0, $quotes));
$countPending = count(array_filter($quotes, fn($q) => ($q['status'] ?? '') === 'pending'));

admin_header('收款链接');
?>
<div class="admin-layout">
  <?php admin_sidebar('quotes'); ?>
  <div class="main">
    <h1>收款链接 / 报价单</h1>
    <p class="sub">给客户开一张收款单，把链接发过去就能收款。付款成功会自动把对应 CRM 线索推进为「已成交」。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <?php if ($newLink): ?>
      <div class="card" style="border:1px solid var(--accent)">
        <div style="font-size:13px;color:var(--muted);margin-bottom:6px">🔗 收款链接（点复制发给客户）</div>
        <div style="display:flex;gap:8px">
          <input id="newlink" type="text" readonly value="<?=htmlspecialchars($newLink)?>" style="flex:1;font-family:monospace;font-size:13px" onclick="this.select()">
          <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('newlink').value).then(()=>fcToast&&fcToast('已复制','success'))">复制</button>
        </div>
      </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0">
      <div class="card" style="margin:0"><div class="sub">收款单总数</div><div style="font-size:26px;font-weight:700"><?=count($quotes)?></div></div>
      <div class="card" style="margin:0"><div class="sub">待支付</div><div style="font-size:26px;font-weight:700;color:var(--warn)"><?=$countPending?></div></div>
      <div class="card" style="margin:0"><div class="sub">已收款合计</div><div style="font-size:26px;font-weight:700;color:var(--ok)">¥<?=number_format($sumPaid, 2)?></div></div>
    </div>

    <div class="card">
      <h2 style="margin-top:0;font-size:15px">开一张收款单</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="flex:2;min-width:200px"><label>收款事由</label><input type="text" name="title" placeholder="如：品牌官网设计 · 首款" required></div>
          <div class="field" style="flex:1;min-width:120px"><label>客户称呼 <span class="hint">选填</span></label><input type="text" name="customer" placeholder="张先生 / 某某公司"></div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="flex:1;min-width:160px"><label>客户邮箱 <span class="hint">填了会自动关联 CRM 线索</span></label><input type="email" name="email" placeholder="client@example.com"></div>
          <div class="field" style="flex:1;min-width:120px"><label>链接有效期 <span class="hint">选填</span></label><input type="date" name="expires_at"></div>
        </div>

        <div style="border:1px dashed var(--border);border-radius:10px;padding:12px;margin:6px 0">
          <div class="sub" style="margin-bottom:8px">明细（可选，填了会自动求和；也可只填下面的总额）</div>
          <div id="items"></div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addItem()">+ 加一行明细</button>
        </div>

        <div class="field" style="max-width:220px"><label>收款总额（元）</label><input type="number" name="amount" id="amount" step="0.01" min="0" placeholder="留空则按明细求和"></div>
        <div class="field"><label>备注 <span class="hint">显示在收款页上</span></label><textarea name="note" rows="2" placeholder="如：本款为项目首付 50%，尾款验收后支付"></textarea></div>
        <button class="btn btn-primary">生成收款链接</button>
      </form>
    </div>

    <div class="card" style="padding:0;overflow-x:auto">
      <table class="table">
        <thead><tr><th>事由</th><th>客户</th><th>金额</th><th>状态</th><th>创建</th><th style="width:1%">操作</th></tr></thead>
        <tbody>
          <?php if (!$quotes): ?><tr><td colspan="6" class="empty">还没有收款单</td></tr><?php endif; ?>
          <?php foreach ($quotes as $q): $st = $q['status'] ?? 'pending'; ?>
            <tr>
              <td><?=htmlspecialchars($q['goods_title'] ?? '收款')?></td>
              <td style="font-size:12px"><?=htmlspecialchars($q['customer'] ?: ($q['crm_email'] ?? '—'))?></td>
              <td class="mono">¥<?=number_format((float)($q['amount'] ?? 0), 2)?></td>
              <td><span class="badge <?= $st==='paid'?'ok':($st==='refunded'?'danger':'') ?>"><?=htmlspecialchars($statusLabel[$st] ?? $st)?></span></td>
              <td style="font-size:12px;color:var(--text-3)"><?=htmlspecialchars(substr($q['created_at'] ?? '', 0, 16))?></td>
              <td>
                <?php if ($st === 'pending'): ?>
                  <button class="btn btn-ghost btn-sm" onclick="copyLink('<?=htmlspecialchars(quote_pay_url($q), ENT_QUOTES)?>')">复制链接</button>
                <?php else: ?>
                  <span class="sub" style="font-size:12px">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function addItem() {
  var wrap = document.getElementById('items');
  var row = document.createElement('div');
  row.style.cssText = 'display:flex;gap:8px;margin-bottom:6px';
  row.innerHTML = '<input name="item_name[]" placeholder="项目名" style="flex:2">'
    + '<input name="item_qty[]" type="number" min="1" value="1" placeholder="数量" style="width:80px" oninput="recalc()">'
    + '<input name="item_price[]" type="number" min="0" step="0.01" placeholder="单价" style="width:110px" oninput="recalc()">'
    + '<button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove();recalc()">×</button>';
  wrap.appendChild(row);
}
function recalc() {
  var qs = document.getElementsByName('item_qty[]'), ps = document.getElementsByName('item_price[]'), sum = 0;
  for (var i = 0; i < qs.length; i++) sum += (parseInt(qs[i].value)||0) * (parseFloat(ps[i].value)||0);
  if (sum > 0) document.getElementById('amount').value = sum.toFixed(2);
}
function copyLink(u) { navigator.clipboard.writeText(u).then(function(){ if(window.fcToast) fcToast('链接已复制','success'); }); }
</script>
<?php admin_footer(); ?>
