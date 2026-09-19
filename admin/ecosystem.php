<?php
declare(strict_types=1);
/**
 * 生态适配 · 人审队列（后台）
 *
 * 闭环：AI 产出草稿 → 本页人工审阅（批准/拒绝/上架）→ 上架到 plugins/（默认不启用）
 * 规范：docs/ADAPTER-SPEC.md；队列数据：data/ecosystem/review-queue.json
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('plugins');

require_once __DIR__ . '/../lib/AdapterIntake.php';
require_once __DIR__ . '/../lib/AdapterReview.php';

$drafts = dirname(__DIR__) . '/plugins/_drafts';
$pluginsRoot = dirname(__DIR__) . '/plugins';
$queueFile = DATA_DIR . '/ecosystem/review-queue.json';
$registryFile = DATA_DIR . '/plugins.json';

/** 刷新队列（保留人审决定） */
function eco_refresh(string $drafts, string $pluginsRoot, string $queueFile): array
{
    $entries = adapter_review_scan($drafts);
    $prev = [];
    foreach ((array) (adapter_review_queue_read($queueFile)['items'] ?? []) as $e) {
        $prev[(string) ($e['id'] ?? '')] = $e;
    }
    foreach ($entries as $i => $e) {
        $p = $prev[$e['id']] ?? null;
        if ($p) {
            $entries[$i]['decision'] = (string) ($p['decision'] ?? '');
            $entries[$i]['note'] = (string) ($p['note'] ?? '');
        }
        $entries[$i]['published'] = is_dir(rtrim($pluginsRoot, '/') . '/' . $e['id']);
    }
    adapter_review_queue_write($entries, $queueFile);
    return $entries;
}

$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eco_action'])) {
    csrf_verify();
    $id = trim((string) ($_POST['id'] ?? ''));
    $act = (string) $_POST['eco_action'];
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($id === '') {
        $message = '❌ 缺少 id';
        $messageType = 'err';
    } elseif ($act === 'approve' || $act === 'reject') {
        $updated = adapter_review_decide($id, $act, $note, $queueFile);
        $message = $updated !== [] ? ($act === 'approve' ? "✅ 已批准 {$id}" : "✅ 已拒绝 {$id}") : "❌ 操作失败：{$id}";
        $messageType = $updated !== [] ? 'ok' : 'err';
    } elseif ($act === 'publish') {
        eco_refresh($drafts, $pluginsRoot, $queueFile);
        $entry = null;
        foreach (eco_refresh($drafts, $pluginsRoot, $queueFile) as $e) {
            if ($e['id'] === $id) { $entry = $e; break; }
        }
        if ($entry === null) {
            $message = "❌ 未找到草稿：{$id}";
            $messageType = 'err';
        } else {
            $res = adapter_review_publish($entry, $pluginsRoot, $registryFile);
            if ($res['ok'] ?? false) {
                adapter_review_decide($id, 'published', $note !== '' ? $note : '后台人工上架', $queueFile);
                $message = '✅ 已上架 plugins/' . $id . '（默认未启用，请到「插件」页开启）';
            } else {
                $message = '❌ 上架失败：' . (string) ($res['error'] ?? '');
                $messageType = 'err';
            }
        }
    }
}

$entries = eco_refresh($drafts, $pluginsRoot, $queueFile);
$statusLabel = ['passed' => '通过', 'needs-review' => '待审', 'blocked' => '拦截', 'pending' => '待验'];

admin_header('生态适配');
?>
<style>
.eco-wrap{max-width:1180px}
.eco-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:14px}
.eco-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.eco-id{font-family:var(--font-mono);font-weight:700}
.eco-badge{font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:999px}
.eco-badge.passed{background:var(--ok-soft,#e7f6ec);color:var(--ok,#1a7f4b)}
.eco-badge.needs-review{background:#fff5e5;color:#9a6a00}
.eco-badge.blocked{background:#fdeaea;color:#a02525}
.eco-meta{font-size:12.5px;color:var(--muted);line-height:1.9;margin-top:8px}
.eco-meta code{font-family:var(--font-mono);background:var(--hover);padding:1px 6px;border-radius:6px}
.eco-actions{display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap}
.eco-note{flex:1;min-width:220px}
.eco-sug{margin-top:8px;font-size:12.5px;color:#9a6a00}
.eco-empty{color:var(--muted);padding:24px 0}
</style>

<div class="admin-layout">
  <?php admin_sidebar('ecosystem'); ?>
  <div class="main eco-wrap">
    <h1>生态适配 · 人审队列</h1>
    <p class="sub">AI 生成草稿并自证；这里由人决定是否上架。上架后默认<b>不启用</b>，需到「插件」页开启。</p>

    <?php if ($message !== ''): ?>
    <div class="card" style="border-left:3px solid <?= $messageType === 'ok' ? 'var(--ok,#1a7f4b)' : 'var(--danger,#a02525)' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($entries === []): ?>
    <div class="eco-empty">暂无草稿。用 <code>php scripts/forge-adapter.php &lt;owner/repo&gt; --complete</code> 生成一条。</div>
    <?php endif; ?>

    <?php foreach ($entries as $e): ?>
    <?php
      $status = (string) $e['status'];
      $gate = adapter_review_publishable($e);
      $canPublish = (bool) ($gate['ok'] ?? false);
    ?>
    <div class="eco-card">
      <div class="eco-head">
        <span class="eco-id"><?= htmlspecialchars((string) $e['id']) ?></span>
        <span class="eco-badge <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLabel[$status] ?? $status) ?></span>
        <?php if (($e['badge'] ?? '') === 'verified'): ?><span class="eco-badge passed">已验证</span><?php endif; ?>
        <?php if (!empty($e['decision'])): ?><span class="eco-badge needs-review">人审：<?= htmlspecialchars((string) $e['decision']) ?></span><?php endif; ?>
        <?php if (($e['published'] ?? false)): ?><span class="eco-badge passed">已上架</span><?php endif; ?>
      </div>
      <div class="eco-meta">
        上游 <code><?= htmlspecialchars((string) ($e['repo'] ?: '—')) ?></code>
        · 许可证 <code><?= htmlspecialchars((string) ($e['license'] ?: '—')) ?></code>
        · 版本 <code><?= htmlspecialchars((string) ($e['version'] ?: '—')) ?></code>
        · 落点 <code><?= htmlspecialchars((string) ($e['surfaces'] ?: '—')) ?></code><br>
        权限 <code><?= htmlspecialchars((string) ($e['permissions'] ?: '—')) ?></code>
        · 待补 TODO <b><?= (int) $e['todos'] ?></b> 处 · 代码 <?= (int) $e['lines'] ?> 行
        <?php if ((array) $e['failed'] !== []): ?> · <span style="color:#a02525">失败项：<?= htmlspecialchars(implode(', ', (array) $e['failed'])) ?></span><?php endif; ?>
        <?php if (!empty($e['note'])): ?> · 备注：<?= htmlspecialchars((string) $e['note']) ?><?php endif; ?>
      </div>
      <?php if ((array) $e['suggestions'] !== []): ?>
      <div class="eco-sug">扩权建议（AI 想加但被拦下的落点，需人工判断）：
        <?php foreach ((array) $e['suggestions'] as $s): ?>
          <code><?= htmlspecialchars((string) $s['surface']) ?></code> <?= htmlspecialchars((string) $s['reason']) ?>;
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (is_file($e['dir'] . '/verification-report.json')): ?>
      <div class="eco-meta">验证报告：<code>plugins/_drafts/<?= htmlspecialchars((string) $e['id']) ?>/verification-report.json</code></div>
      <?php endif; ?>

      <?php if (!($e['published'] ?? false)): ?>
      <form method="post" class="eco-actions">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $e['id']) ?>">
        <input class="inp eco-note" type="text" name="note" placeholder="备注（可选，如：法务确认、需补 TODO 等）">
        <button class="btn" name="eco_action" value="approve" type="submit">批准</button>
        <button class="btn" name="eco_action" value="reject" type="submit">拒绝</button>
        <button class="btn primary" name="eco_action" value="publish" type="submit" <?= $canPublish ? '' : 'disabled title="' . htmlspecialchars((string) ($gate['reason'] ?? '')) . '"' ?>>上架</button>
      </form>
      <?php if (!$canPublish): ?>
      <div class="eco-meta" style="color:#9a6a00">上架条件未满足：<?= htmlspecialchars((string) ($gate['reason'] ?? '')) ?><?= !empty($gate['warn']) ? ' · ' . htmlspecialchars((string) $gate['warn']) : '' ?></div>
      <?php elseif (!empty($gate['warn'])): ?>
      <div class="eco-meta" style="color:#9a6a00">提醒：<?= htmlspecialchars((string) $gate['warn']) ?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php $published = adapter_review_published($registryFile); ?>
    <h2 style="margin-top:26px">已上架适配（<?=count($published)?>）</h2>
    <p class="sub">这些是已经进入 <code>plugins/</code> 的官方适配插件：默认<b>未启用</b>，需要到「插件」页开启；同时会以「官方适配 · 免费」出现在生态市场。</p>
    <?php if ($published === []): ?>
    <div class="eco-empty">还没有上架的适配插件。</div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:13.5px">
        <thead><tr style="background:var(--surface-2);text-align:left">
          <th style="padding:10px 14px">插件</th><th style="padding:10px 14px">上游</th><th style="padding:10px 14px">许可证</th>
          <th style="padding:10px 14px">状态</th><th style="padding:10px 14px">上架时间</th><th style="padding:10px 14px"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($published as $pub): ?>
          <tr style="border-top:1px solid var(--border-soft)">
            <td style="padding:10px 14px"><b><?=htmlspecialchars((string) $pub['name']) ?></b><br><span class="eco-id" style="font-size:12px;color:var(--muted)"><?=htmlspecialchars((string) $pub['id']) ?> v<?=htmlspecialchars((string) $pub['version']) ?></span></td>
            <td style="padding:10px 14px"><?=htmlspecialchars((string) $pub['source']) ?: '—' ?></td>
            <td style="padding:10px 14px"><code><?=htmlspecialchars((string) $pub['license']) ?: '—' ?></code></td>
            <td style="padding:10px 14px"><?= $pub['enabled'] ? '<span class="eco-badge passed">已启用</span>' : '<span class="eco-badge needs-review">未启用</span>' ?></td>
            <td style="padding:10px 14px;color:var(--muted)"><?=htmlspecialchars((string) $pub['installed_at']) ?></td>
            <td style="padding:10px 14px;text-align:right"><a class="btn" href="/xmp/plugins">去启用 →</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
