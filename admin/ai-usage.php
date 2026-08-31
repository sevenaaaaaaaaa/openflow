<?php
/**
 * AI 用量与预算 —— 这个月 AI 花在哪、还剩多少（docs/ROADMAP.md 阶段一第 2 件）
 *
 * 对一人公司，「这个月 AI 花了多少钱」比「数据库能存多少行」重要得多。
 * 这一页回答三个问题：花了多少 · 花在哪个功能上 · 什么时候该踩刹车。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AiBudget.php';
require_login();
require_perm('ai-config');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save_budget'])) {
        ai_budget_save([
            'daily_cost_cap'   => $_POST['daily_cost_cap'] ?? 0,
            'daily_token_cap'  => $_POST['daily_token_cap'] ?? 0,
            'public_cost_cap'  => $_POST['public_cost_cap'] ?? 0,
            'public_token_cap' => $_POST['public_token_cap'] ?? 0,
            'public_call_cap'  => $_POST['public_call_cap'] ?? 0,
            'currency'         => $_POST['currency'] ?? '¥',
        ]);
        audit('更新 AI 预算', 'ai-config');
        $msg = '预算已保存。超过上限后 AI 调用会被拦下，访客侧自动降级为仅检索站内知识。';
    }
    if (isset($_POST['save_price'])) {
        ai_price_save((string)($_POST['price_model'] ?? ''),
                      (float)($_POST['price_in'] ?? 0), (float)($_POST['price_out'] ?? 0));
        audit('更新 AI 单价', 'ai-config');
        $msg = '单价已保存。成本是按这个单价推算的，token 数本身是实测的。';
    }
}

$b        = ai_budget_settings();
$cur      = $b['currency'];
$today    = ai_spend();
$pubToday = ai_spend('', 'public');
$days     = max(1, min(90, (int)($_GET['days'] ?? 7)));
$byFeature= ai_spend_by('feature', $days);
$byModel  = ai_spend_by('model', $days);
$byDay    = ai_spend_by('day', $days);
$noPrice  = ai_models_without_price(30);

$rangeCost = 0.0; $rangeTokens = 0; $rangeCalls = 0; $rangeFailed = 0;
foreach ($byFeature as $r) {
    $rangeCost   += (float)$r['cost'];
    $rangeTokens += (int)$r['in_tokens'] + (int)$r['out_tokens'];
    $rangeCalls  += (int)$r['calls'];
    $rangeFailed += (int)$r['failed'];
}

/** 额度用了百分之多少（没设上限就返回 null）。 */
function ai_pct($used, $cap) { return ($cap > 0) ? min(100, round($used / $cap * 100)) : null; }
function ai_bar($pct) {
    if ($pct === null) return '<span style="color:#9aa">未设上限</span>';
    $c = $pct >= 90 ? '#c0392b' : ($pct >= 70 ? '#c87f0a' : '#2c6a4c');
    return '<div style="background:#eceee9;border-radius:3px;height:8px;overflow:hidden;max-width:180px">'
         . '<div style="width:' . $pct . '%;background:' . $c . ';height:100%"></div></div>'
         . '<span style="font-size:12px;color:' . $c . '">' . $pct . '%</span>';
}
function ai_n($x) { return number_format((float)$x); }

admin_header('AI 用量与预算');
?>
<div style="max-width:980px">
  <h1 style="margin:0 0 4px">⚡ AI 用量与预算</h1>
  <p style="color:#666;margin:0 0 18px">
    每次 AI 调用都记一行：哪个功能、哪个模型、进出多少 token、多久、成没成。
    <strong>token 是实测的</strong>；金额是按下方单价推算的，模型没配单价就只记 token 不记钱。
  </p>
  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- 今日 -->
  <h2 style="font-size:16px;margin:22px 0 8px">今天</h2>
  <table class="table" style="margin-bottom:6px">
    <tr>
      <th style="width:120px">范围</th><th>调用</th><th>token</th><th>花费</th><th>失败</th><th style="width:220px">额度</th>
    </tr>
    <tr>
      <td><strong>全站</strong></td>
      <td><?= ai_n($today['calls']) ?></td>
      <td><?= ai_n($today['tokens']) ?></td>
      <td><?= $cur . number_format($today['cost'], 4) ?></td>
      <td><?= $today['failed'] > 0 ? '<span style="color:#c0392b">' . ai_n($today['failed']) . '</span>' : '0' ?></td>
      <td><?= ai_bar(ai_pct($today['cost'], $b['daily_cost_cap']) ?? ai_pct($today['tokens'], $b['daily_token_cap'])) ?></td>
    </tr>
    <tr>
      <td>访客侧<br><span style="font-size:11px;color:#999">公开接口</span></td>
      <td><?= ai_n($pubToday['calls']) ?></td>
      <td><?= ai_n($pubToday['tokens']) ?></td>
      <td><?= $cur . number_format($pubToday['cost'], 4) ?></td>
      <td><?= $pubToday['failed'] > 0 ? '<span style="color:#c0392b">' . ai_n($pubToday['failed']) . '</span>' : '0' ?></td>
      <td><?= ai_bar(ai_pct($pubToday['calls'], $b['public_call_cap'])) ?></td>
    </tr>
  </table>
  <p style="font-size:12px;color:#888;margin:0 0 20px">
    访客侧另有一道更低的闸，所以外部滥用烧不穿你自己的用量。触顶后站点客服自动降级为
    <strong>仅检索站内知识、不调模型</strong>，不会白屏。
  </p>

  <!-- 花在哪 -->
  <h2 style="font-size:16px;margin:26px 0 8px">
    钱花在哪（近
    <?php foreach ([7, 30, 90] as $d): ?>
      <a href="?days=<?= $d ?>" style="<?= $d === $days ? 'font-weight:600' : 'color:#888' ?>"><?= $d ?></a><?= $d !== 90 ? ' /' : '' ?>
    <?php endforeach; ?>
    天）
  </h2>
  <p style="color:#666;margin:0 0 10px">
    合计 <strong><?= ai_n($rangeCalls) ?></strong> 次调用 ·
    <strong><?= ai_n($rangeTokens) ?></strong> token ·
    <strong><?= $cur . number_format($rangeCost, 4) ?></strong>
    <?php if ($rangeFailed > 0): ?> · <span style="color:#c0392b">失败 <?= ai_n($rangeFailed) ?> 次</span><?php endif; ?>
  </p>
  <table class="table">
    <tr><th>功能</th><th>调用</th><th>输入 token</th><th>输出 token</th><th>花费</th><th>平均耗时</th><th>失败</th></tr>
    <?php if (!$byFeature): ?>
      <tr><td colspan="7" style="color:#999">这段时间还没有 AI 调用记录。</td></tr>
    <?php endif; ?>
    <?php foreach ($byFeature as $r): ?>
      <tr>
        <td><code><?= htmlspecialchars((string)$r['k']) ?></code></td>
        <td><?= ai_n($r['calls']) ?></td>
        <td><?= ai_n($r['in_tokens']) ?></td>
        <td><?= ai_n($r['out_tokens']) ?></td>
        <td><?= $cur . number_format((float)$r['cost'], 4) ?></td>
        <td><?= ai_n(round((float)$r['avg_ms'])) ?> ms</td>
        <td><?= (int)$r['failed'] > 0 ? '<span style="color:#c0392b">' . ai_n($r['failed']) . '</span>' : '0' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <details style="margin:14px 0 24px">
    <summary style="cursor:pointer;color:#666">按模型 / 按天看</summary>
    <table class="table" style="margin-top:10px">
      <tr><th>模型</th><th>调用</th><th>token</th><th>花费</th><th>平均耗时</th></tr>
      <?php foreach ($byModel as $r): ?>
        <tr><td><code><?= htmlspecialchars((string)$r['k']) ?></code></td>
            <td><?= ai_n($r['calls']) ?></td>
            <td><?= ai_n((int)$r['in_tokens'] + (int)$r['out_tokens']) ?></td>
            <td><?= $cur . number_format((float)$r['cost'], 4) ?></td>
            <td><?= ai_n(round((float)$r['avg_ms'])) ?> ms</td></tr>
      <?php endforeach; ?>
    </table>
    <table class="table" style="margin-top:10px">
      <tr><th>日期</th><th>调用</th><th>token</th><th>花费</th><th>失败</th></tr>
      <?php foreach ($byDay as $r): ?>
        <tr><td><?= htmlspecialchars((string)$r['k']) ?></td>
            <td><?= ai_n($r['calls']) ?></td>
            <td><?= ai_n((int)$r['in_tokens'] + (int)$r['out_tokens']) ?></td>
            <td><?= $cur . number_format((float)$r['cost'], 4) ?></td>
            <td><?= (int)$r['failed'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </details>

  <!-- 单价 -->
  <h2 style="font-size:16px;margin:26px 0 8px">模型单价</h2>
  <p style="color:#666;margin:0 0 10px">
    各家单价差几十倍而且经常变，所以这里<strong>不预置任何猜的价钱</strong>——
    没配价的模型只记 token、金额算 0。按你实际在用的模型填一下，上面的金额就准了。
  </p>
  <?php if ($noPrice): ?>
    <div class="alert" style="background:#fdf6e3;border:1px solid #e8d9a8;padding:10px 12px;border-radius:4px;margin-bottom:12px">
      <strong>这些模型用过但还没配单价：</strong>
      <?php foreach ($noPrice as $m): ?>
        <code><?= htmlspecialchars($m['model']) ?></code>（<?= ai_n($m['tokens']) ?> token）
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px">
    <?= csrf_field() ?>
    <label style="font-size:13px">模型名<br>
      <input name="price_model" placeholder="如 deepseek-chat" style="width:200px" required>
      <span style="display:block;font-size:11px;color:#999">支持前缀匹配</span>
    </label>
    <label style="font-size:13px">输入单价<br>
      <input name="price_in" type="number" step="0.0001" min="0" value="0" style="width:110px">
      <span style="display:block;font-size:11px;color:#999"><?= htmlspecialchars($cur) ?> / 百万 token</span>
    </label>
    <label style="font-size:13px">输出单价<br>
      <input name="price_out" type="number" step="0.0001" min="0" value="0" style="width:110px">
      <span style="display:block;font-size:11px;color:#999"><?= htmlspecialchars($cur) ?> / 百万 token</span>
    </label>
    <button class="btn btn-s" name="save_price" value="1">保存单价</button>
  </form>
  <?php $prices = ai_price_table(); if ($prices): ?>
    <table class="table" style="margin-bottom:24px">
      <tr><th>模型</th><th>输入（<?= htmlspecialchars($cur) ?>/百万）</th><th>输出（<?= htmlspecialchars($cur) ?>/百万）</th></tr>
      <?php foreach ($prices as $m => $p): ?>
        <tr><td><code><?= htmlspecialchars((string)$m) ?></code></td>
            <td><?= $p['in'] ?></td><td><?= $p['out'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <!-- 预算 -->
  <h2 style="font-size:16px;margin:26px 0 8px">每日上限（保险丝）</h2>
  <p style="color:#666;margin:0 0 10px">
    填 <code>0</code> = 不限。<strong>默认全是 0</strong>，因为合适的数字只有你自己知道——
    先看几天上面的实际用量，再定一个心里踏实的数。<br>
    超过上限后：后台的 AI 功能会提示额度用尽；访客侧的站点客服自动降级为仅检索站内知识，
    <strong>不会白屏、不会报错给访客看</strong>。
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <table class="table" style="max-width:720px">
      <tr><th style="width:220px">项目</th><th>上限</th><th>说明</th></tr>
      <tr><td>全站每日花费</td>
          <td><input name="daily_cost_cap" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)$b['daily_cost_cap']) ?>" style="width:120px"> <?= htmlspecialchars($cur) ?></td>
          <td style="font-size:12px;color:#888">需要先配单价才有意义</td></tr>
      <tr><td>全站每日 token</td>
          <td><input name="daily_token_cap" type="number" min="0" value="<?= (int)$b['daily_token_cap'] ?>" style="width:120px"></td>
          <td style="font-size:12px;color:#888">不依赖单价，配不配价都能用</td></tr>
      <tr><td>访客侧每日调用次数</td>
          <td><input name="public_call_cap" type="number" min="0" value="<?= (int)$b['public_call_cap'] ?>" style="width:120px"></td>
          <td style="font-size:12px;color:#888">公开接口专用，默认 500</td></tr>
      <tr><td>访客侧每日花费</td>
          <td><input name="public_cost_cap" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)$b['public_cost_cap']) ?>" style="width:120px"> <?= htmlspecialchars($cur) ?></td>
          <td style="font-size:12px;color:#888">0 = 跟随全站上限</td></tr>
      <tr><td>访客侧每日 token</td>
          <td><input name="public_token_cap" type="number" min="0" value="<?= (int)$b['public_token_cap'] ?>" style="width:120px"></td>
          <td style="font-size:12px;color:#888">0 = 跟随全站上限</td></tr>
      <tr><td>币种符号</td>
          <td><input name="currency" value="<?= htmlspecialchars($cur) ?>" style="width:60px" maxlength="4"></td>
          <td style="font-size:12px;color:#888">只影响显示</td></tr>
    </table>
    <button class="btn btn-s" name="save_budget" value="1" style="margin-top:10px">保存上限</button>
  </form>

  <p style="font-size:12px;color:#999;margin-top:24px">
    另外，公开的站点客服接口还有一层单 IP 限流（10 分钟 20 次），
    防的是"一个人几分钟刷爆全天额度"——额度和限流是两层，都需要。
  </p>
</div>
<?php admin_footer(); ?>
