<?php
/**
 * 问数据 —— 一句话问，AI 读真实指标快照作答（BACKLOG T1-3）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CdpInsight.php';
require_once __DIR__ . '/../lib/GrowthSignal.php';
require_once __DIR__ . '/../lib/AskData.php';
require_login();
require_perm('insights');

$q = trim($_POST['q'] ?? ($_GET['q'] ?? ''));
$res = null;
if ($q !== '') { csrf_verify_or_get(); $res = askdata_answer($q); }

function csrf_verify_or_get() { if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify(); }

$samples = ['哪个来源转化成收入最多？', '最近 30 天新增用户趋势如何？', 'VIP/高价值用户占比多少？', '哪个分群最值钱？', '有多少会员和线索？'];
admin_header('问数据');
?>
<div style="max-width:820px">
  <h1 style="margin:0 0 4px">💬 问数据</h1>
  <p class="v-sub" style="margin:0 0 16px">用一句话问，AI 读你站点**已算好的真实指标**作答——不用自己翻报表。它只用现成数据、不编数字。</p>

  <form method="post" style="display:flex;gap:8px;margin-bottom:10px">
    <?= csrf_field() ?>
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="例如：上个月哪个来源带来的成交额最高？" style="flex:1" autofocus>
    <button class="btn btn-primary">问</button>
  </form>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px">
    <?php foreach ($samples as $s): ?>
    <a href="/xmp/ask-data?q=<?=urlencode($s)?>" class="btn btn-ghost btn-sm" style="font-size:12px"><?=htmlspecialchars($s)?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($res !== null): ?>
    <?php if ($res['ok']): ?>
    <div class="card" style="padding:18px;line-height:1.8;white-space:pre-wrap"><?=htmlspecialchars($res['answer'])?></div>
    <?php else: ?>
    <div class="card" style="padding:16px;color:#b45309;border-left:3px solid #f59e0b"><?=htmlspecialchars($res['error'] ?? '未能回答')?></div>
    <?php endif; ?>
    <?php if (!empty($res['data'])): ?>
    <details style="margin-top:12px"><summary style="cursor:pointer;color:var(--faint);font-size:13px">查看 AI 参考的数据快照</summary>
      <pre style="background:var(--card,#fff);border:1px solid var(--border);border-radius:10px;padding:14px;overflow:auto;font-size:12px;max-height:360px"><?=htmlspecialchars(json_encode($res['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))?></pre>
    </details>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
