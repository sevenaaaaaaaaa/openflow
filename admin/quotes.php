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

// 交付阶段
if (($_POST['action'] ?? '') === 'set_stage') {
    if (quote_set_stage(trim($_POST['id'] ?? ''), trim($_POST['stage'] ?? ''))) $message = '交付阶段已更新。';
}
// 待办：增 / 勾 / 删
if (($_POST['action'] ?? '') === 'add_todo') {
    if (quote_add_todo(trim($_POST['id'] ?? ''), $_POST['text'] ?? '', $_POST['due'] ?? '')) $message = '待办已添加。';
    else $error = '待办内容不能为空。';
}
if (($_POST['action'] ?? '') === 'toggle_todo') {
    quote_toggle_todo(trim($_POST['id'] ?? ''), (int)($_POST['idx'] ?? -1));
}
if (($_POST['action'] ?? '') === 'del_todo') {
    quote_remove_todo(trim($_POST['id'] ?? ''), (int)($_POST['idx'] ?? -1));
}

// 详情视图（?id=）用于管待办
$detailId = trim($_GET['id'] ?? '');
$detail = $detailId !== '' ? (quote_locate($detailId)[1] ?? null) : null;

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

    <?php
      $att = quote_attention();
      $stages = quote_stages();
      // 需要盯的：只在有内容时显示
      $buckets = [
        ['已付钱·活没完', $att['paid_undelivered'], 'var(--danger)', '收了钱，赶紧交付'],
        ['已交付·钱没清', $att['delivered_unpaid'], 'var(--warn)',  '活干完了，该收钱 / 催尾款'],
        ['待办到期',       $att['todo_due'],         'var(--accent)','有到期没做的事'],
      ];
      $hasAtt = $att['paid_undelivered'] || $att['delivered_unpaid'] || $att['todo_due'];
    ?>
    <?php if ($hasAtt): ?>
      <h2 style="margin:18px 0 10px">需要盯的</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:8px">
        <?php foreach ($buckets as [$label, $list, $color, $hint]): if (!$list) continue; ?>
          <div class="card" style="margin:0;border-left:3px solid <?=$color?>">
            <div style="display:flex;align-items:baseline;justify-content:space-between">
              <b><?=$label?></b><span style="font-size:22px;font-weight:800;color:<?=$color?>"><?=count($list)?></span>
            </div>
            <div class="sub" style="margin:2px 0 8px"><?=$hint?></div>
            <?php foreach (array_slice($list, 0, 4) as $q): ?>
              <a href="?id=<?=urlencode($q['id'])?>" style="display:flex;justify-content:space-between;gap:8px;font-size:13px;padding:3px 0;color:var(--text-2);text-decoration:none">
                <span><?=htmlspecialchars(($q['customer'] ?: ($q['crm_email'] ?? '')) ?: '客户')?> · <?=htmlspecialchars(mb_substr($q['goods_title'] ?? '收款', 0, 12))?></span>
                <span class="mono">¥<?=number_format((float)($q['amount'] ?? 0), 0)?></span>
              </a>
            <?php endforeach; ?>
            <?php if (count($list) > 4): ?><div class="sub" style="font-size:12px;margin-top:4px">…共 <?=count($list)?> 单</div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($detail): $d = $detail; $dStage = quote_stage_of($d); ?>
      <div class="card" style="border:1px solid var(--accent)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
          <div>
            <h2 style="margin:0 0 2px;font-size:16px"><?=htmlspecialchars($d['goods_title'] ?? '收款')?></h2>
            <div class="sub"><?=htmlspecialchars($d['customer'] ?: ($d['crm_email'] ?? '—'))?> · ¥<?=number_format((float)($d['amount'] ?? 0), 2)?> · <span class="badge <?= ($d['status']??'')==='paid'?'ok':(($d['status']??'')==='refunded'?'danger':'') ?>"><?=htmlspecialchars($statusLabel[$d['status'] ?? 'pending'] ?? '')?></span></div>
          </div>
          <a href="/xmp/quotes" class="btn btn-ghost btn-sm">收起</a>
        </div>

        <div style="margin:14px 0">
          <div class="sub" style="margin-bottom:6px">交付阶段</div>
          <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
            <?= csrf_field() ?><input type="hidden" name="action" value="set_stage"><input type="hidden" name="id" value="<?=htmlspecialchars($d['id'])?>">
            <?php foreach ($stages as $k => $lbl): ?>
              <button name="stage" value="<?=$k?>" class="btn btn-sm <?=$dStage===$k?'btn-primary':'btn-ghost'?>"><?=$lbl?></button>
            <?php endforeach; ?>
          </form>
        </div>

        <div class="sub" style="margin-bottom:6px">待办</div>
        <?php foreach ((array)($d['todos'] ?? []) as $i => $t): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:4px 0">
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_todo"><input type="hidden" name="id" value="<?=htmlspecialchars($d['id'])?>"><input type="hidden" name="idx" value="<?=$i?>">
              <button style="background:none;border:none;cursor:pointer;font-size:16px"><?=!empty($t['done'])?'✅':'⬜'?></button></form>
            <span style="flex:1;<?=!empty($t['done'])?'text-decoration:line-through;color:var(--text-3)':''?>"><?=htmlspecialchars($t['text'] ?? '')?>
              <?php if (!empty($t['due'])): $overdue = empty($t['done']) && $t['due'] <= date('Y-m-d'); ?>
                <span style="font-size:12px;color:<?=$overdue?'var(--danger)':'var(--text-3)'?>">· <?=htmlspecialchars($t['due'])?><?=$overdue?' 已到期':''?></span>
              <?php endif; ?></span>
            <form method="post" style="display:inline" onsubmit="return confirm('删除这条待办?')"><?= csrf_field() ?><input type="hidden" name="action" value="del_todo"><input type="hidden" name="id" value="<?=htmlspecialchars($d['id'])?>"><input type="hidden" name="idx" value="<?=$i?>">
              <button class="btn btn-ghost btn-sm" style="color:var(--text-3)">×</button></form>
          </div>
        <?php endforeach; ?>
        <?php if (empty($d['todos'])): ?><div class="sub" style="font-size:13px">还没有待办。</div><?php endif; ?>
        <form method="post" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_todo"><input type="hidden" name="id" value="<?=htmlspecialchars($d['id'])?>">
          <input type="text" name="text" placeholder="加一条待办，如：交初稿 / 催尾款 ¥3000" style="flex:2;min-width:200px" required>
          <input type="date" name="due" style="width:150px" title="截止日期（选填）">
          <button class="btn btn-primary btn-sm">添加</button>
        </form>
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
        <thead><tr><th>事由</th><th>客户</th><th>金额</th><th>收款</th><th>交付</th><th>待办</th><th style="width:1%">操作</th></tr></thead>
        <tbody>
          <?php if (!$quotes): ?><tr><td colspan="7" class="empty">还没有收款单</td></tr><?php endif; ?>
          <?php foreach ($quotes as $q): $st = $q['status'] ?? 'pending'; $stg = quote_stage_of($q); $open = quote_open_todos($q); ?>
            <tr>
              <td><a href="?id=<?=urlencode($q['id'])?>" style="color:var(--accent);text-decoration:none"><?=htmlspecialchars($q['goods_title'] ?? '收款')?></a></td>
              <td style="font-size:12px"><?=htmlspecialchars($q['customer'] ?: ($q['crm_email'] ?? '—'))?></td>
              <td class="mono">¥<?=number_format((float)($q['amount'] ?? 0), 2)?></td>
              <td><span class="badge <?= $st==='paid'?'ok':($st==='refunded'?'danger':'') ?>"><?=htmlspecialchars($statusLabel[$st] ?? $st)?></span></td>
              <td>
                <?php $delivered = $stg === 'delivered'; ?>
                <span class="badge <?=$delivered?'ok':''?>" style="<?=$delivered?'':'background:var(--surface-2)'?>"><?=htmlspecialchars($stages[$stg])?></span>
                <?php if ($st === 'paid' && !$delivered): ?><span title="收了钱还没交付" style="color:var(--danger)">●</span><?php endif; ?>
                <?php if ($delivered && !in_array($st, ['paid','refunded'], true)): ?><span title="交付了还没收款" style="color:var(--warn)">●</span><?php endif; ?>
              </td>
              <td><?php if ($open): ?><a href="?id=<?=urlencode($q['id'])?>" style="text-decoration:none"><?=$open?> 待办</a><?php else: ?><span class="sub" style="font-size:12px">—</span><?php endif; ?></td>
              <td style="white-space:nowrap">
                <a href="?id=<?=urlencode($q['id'])?>" class="btn btn-ghost btn-sm">管理</a>
                <?php if ($st === 'pending'): ?>
                  <button class="btn btn-ghost btn-sm" onclick="copyLink('<?=htmlspecialchars(quote_pay_url($q), ENT_QUOTES)?>')">复制链接</button>
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
