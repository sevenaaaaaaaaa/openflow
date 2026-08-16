<?php
/**
 * 风控中心 — 审核队列 + 规则配置 + 一键扫描 + AI 辅助审核
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ModerationSystem.php';
require_login();
require_perm('moderation');

$rules = mod_rules();
$queue = mod_queue();
$message = '';

// 审核队列操作
if (isset($_POST['queue_action'])) {
    $idx = (int)($_POST['idx'] ?? -1);
    $targetType = $_POST['target_type'] ?? '';
    $targetId = $_POST['target_id'] ?? '';
    $text = $_POST['text'] ?? '';
    $act = $_POST['queue_action'];

    if ($act === 'allow') {
        // 放行：若目标存在则恢复 approved
        if ($targetType === 'comment') {
            $comments = json_read(DATA_DIR . '/comments.json');
            foreach ($comments as &$c) if ($c['id'] === $targetId) { $c['status'] = 'approved'; break; }
            unset($c);
            json_write(DATA_DIR . '/comments.json', $comments);
        }
        mod_log('manual_allow', ['target_type' => $targetType, 'target_id' => $targetId], '人工放行', 0);
        $message = '已放行';
    } elseif ($act === 'block') {
        if ($targetType === 'comment') {
            $comments = json_read(DATA_DIR . '/comments.json');
            foreach ($comments as &$c) if ($c['id'] === $targetId) { $c['status'] = 'hidden'; break; }
            unset($c);
            json_write(DATA_DIR . '/comments.json', $comments);
        }
        mod_log('manual_block', ['target_type' => $targetType, 'target_id' => $targetId], trim($_POST['reason'] ?? ''), 0);
        $message = '已删除/隐藏';
    }
    // 移出队列
    $q = $queue;
    unset($q[$idx]);
    mod_queue_save(array_values($q));
    $queue = mod_queue();
}
// 一键扫描
if (isset($_POST['scan'])) {
    $result = mod_scan_all();
    $message = "扫描完成：{$result['scanned']} 条 · 拦截 {$result['blocked']} · 标记 {$result['flagged']}";
    $queue = mod_queue();
}
// AI 审核队列
if (isset($_POST['ai_audit'])) {
    $n = 0;
    foreach ($queue as $i => $item) {
        if ($n >= 10) break;
        if (!empty($item['ai_done'])) continue;
        $ai = mod_ai_review($item['text'] ?? '');
        if ($ai['risk'] === 'high' && !empty($rules['ai_auto_hide'])) {
            if (($item['target_type'] ?? '') === 'comment') {
                $comments = json_read(DATA_DIR . '/comments.json');
                foreach ($comments as &$c) if ($c['id'] === ($item['target_id'] ?? '')) { $c['status'] = 'hidden'; break; }
                unset($c);
                json_write(DATA_DIR . '/comments.json', $comments);
            }
            $queue[$i]['ai_result'] = 'block';
        } elseif ($ai['risk'] === 'low') {
            $queue[$i]['ai_result'] = 'allow';
        } else {
            $queue[$i]['ai_result'] = 'review';
        }
        $queue[$i]['ai_done'] = true;
        $queue[$i]['ai_reason'] = $ai['reason'];
        $n++;
    }
    mod_queue_save($queue);
    $message = "AI 已辅助审核 {$n} 条";
}
// 保存规则
if (isset($_POST['save_rules'])) {
    $rules['banned_words'] = array_filter(array_map('trim', explode("\n", $_POST['banned_words'] ?? '')));
    $rules['sensitive_words'] = array_filter(array_map('trim', explode("\n", $_POST['sensitive_words'] ?? '')));
    $rules['spam_chars'] = array_filter(array_map('trim', explode("\n", $_POST['spam_chars'] ?? '')));
    $rules['ai_enabled'] = isset($_POST['ai_enabled']);
    $rules['ai_auto_hide'] = isset($_POST['ai_auto_hide']);
    mod_save_rules($rules);
    $message = '规则已保存';
}

$tabs = $_GET['tab'] ?? 'queue';
$scanLog = [];
try {
    $scanLog = Database::query("SELECT * FROM moderation_log ORDER BY id DESC LIMIT 50");
} catch (Exception $e) {}

admin_header('风控中心');
?>
<div class="admin-layout">
  <?php admin_sidebar('moderation'); ?>
  <div class="main">
    <h1>🛡️ 风控与审核</h1>
    <p class="sub">精细审核 · 定期扫描 · AI Agent 辅助 · 人工复核</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?tab=queue" class="btn <?=$tabs==='queue'?'btn-primary':'btn-ghost'?> btn-sm">⏳ 审核队列 <?=count($queue)?></a>
      <a href="?tab=scan" class="btn <?=$tabs==='scan'?'btn-primary':'btn-ghost'?> btn-sm">🔍 扫描 & 日志</a>
      <a href="?tab=rules" class="btn <?=$tabs==='rules'?'btn-primary':'btn-ghost'?> btn-sm">⚙️ 规则</a>
    </div>

    <?php if ($tabs === 'queue'): ?>
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <form method="post">
        <?= csrf_field() ?>
      <form method="post">
        <?= csrf_field() ?>
    </div>

    <?php if (empty($queue)): ?>
    <div class="card empty" style="padding:40px">✅ 队列为空，无待审核内容</div>
    <?php else: foreach ($queue as $i => $item): ?>
    <div class="card" style="margin-bottom:12px;<?=($item['risk']??'')==='high'?'border-left:4px solid #dc2626':''?>">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <strong style="font-size:14px"><?=htmlspecialchars($item['author'] ?? '匿名')?></strong>
        <span class="badge" style="background:<?=($item['risk']??'')==='high'?'#dc2626':(($item['risk']??'')==='mid'?'#d97706':'#16a34a')?>;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px"><?=htmlspecialchars($item['risk'] ?? '')?></span>
        <span class="text-sm text-muted"><?=htmlspecialchars($item['source'] ?? '')?> · <?=htmlspecialchars($item['created_at'] ?? '')?></span>
        <?php if (($item['target_type'] ?? '') !== ''): ?><span class="text-sm text-muted"><?=htmlspecialchars($item['target_type'] . ' / ' . $item['target_id'])?></span><?php endif; ?>
      </div>
      <div class="text-sm" style="margin-top:8px;line-height:1.7"><?=nl2br(htmlspecialchars($item['text'] ?? ''))?></div>
      <div class="text-xs text-muted" style="margin-top:6px">⚠️ <?=htmlspecialchars($item['reason'] ?? '')?></div>
      <?php if (!empty($item['ai_result'])): ?>
      <div class="text-xs" style="margin-top:4px;color:<?=$item['ai_result']==='block'?'#dc2626':($item['ai_result']==='allow'?'#16a34a':'#d97706')?>">🤖 AI 判定：<?=htmlspecialchars($item['ai_result'])?><?php if (!empty($item['ai_reason'])): ?> · <?=htmlspecialchars($item['ai_reason'])?><?php endif; ?></div>
      <?php endif; ?>
      <div style="display:flex;gap:8px;margin-top:10px">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="idx" value="<?=$i?>">
          <input type="hidden" name="target_type" value="<?=htmlspecialchars($item['target_type'] ?? '')?>">
          <input type="hidden" name="target_id" value="<?=htmlspecialchars($item['target_id'] ?? '')?>">
          <input type="hidden" name="text" value="<?=htmlspecialchars($item['text'] ?? '')?>">
          <button type="submit" name="queue_action" value="allow" class="btn btn-success btn-sm" style="background:#16a34a;color:#fff">✅ 放行</button>
        </form>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="idx" value="<?=$i?>">
          <input type="hidden" name="target_type" value="<?=htmlspecialchars($item['target_type'] ?? '')?>">
          <input type="hidden" name="target_id" value="<?=htmlspecialchars($item['target_id'] ?? '')?>">
          <input type="hidden" name="text" value="<?=htmlspecialchars($item['text'] ?? '')?>">
          <input type="text" name="reason" placeholder="处理原因" style="padding:6px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;width:160px">
          <button type="submit" name="queue_action" value="block" class="btn btn-danger btn-sm">🚫 拦截</button>
        </form>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php elseif ($tabs === 'scan'): ?>
    <div style="display:flex;gap:8px;margin-bottom:14px">
      <form method="post">
        <?= csrf_field() ?>
      <span class="text-sm text-muted" style="align-self:center">cron 每 12 小时自动扫描一次</span>
    </div>
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📋 审核日志（最近 50 条）</h2>
      <table>
        <thead><tr><th>时间</th><th>动作</th><th>目标</th><th>原因</th><th>分数</th></tr></thead>
        <tbody>
          <?php if (empty($scanLog)): ?><tr><td colspan="5" class="empty">暂无日志</td></tr><?php endif; ?>
          <?php foreach ($scanLog as $l): ?>
          <tr>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['created_at'])?></td>
            <td><span class="badge" style="background:var(--surface-2);padding:2px 8px;border-radius:999px;font-size:11px"><?=htmlspecialchars($l['action'])?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($l['target_type'] . ' / ' . $l['target_id'])?></td>
            <td class="text-sm text-muted" style="max-width:300px"><?=htmlspecialchars($l['reason'])?></td>
            <td><?=number_format((float)$l['ai_score'], 0)?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php else: ?>
    <div class="card" style="max-width:720px">
      <h2>⚙️ 审核规则</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_rules" value="1">
        <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="ai_enabled" <?=$rules['ai_enabled']?'checked':''?> style="width:16px;height:16px"> 启用 AI Agent 辅助审核</label></div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="ai_auto_hide" <?=$rules['ai_auto_hide']?'checked':''?> style="width:16px;height:16px"> AI 判高风险自动隐藏</label></div>
        <div class="field"><label>违规词 <span class="hint">· 命中即拦截，每行一个</span></label><textarea name="banned_words" rows="5"><?=htmlspecialchars(implode("\n", $rules['banned_words']))?></textarea></div>
        <div class="field"><label>敏感词 <span class="hint">· 中风险入队，每行一个</span></label><textarea name="sensitive_words" rows="4"><?=htmlspecialchars(implode("\n", $rules['sensitive_words']))?></textarea></div>
        <div class="field"><label>导流特征词 <span class="hint">· 每行一个</span></label><textarea name="spam_chars" rows="4"><?=htmlspecialchars(implode("\n", $rules['spam_chars']))?></textarea></div>
        <button type="submit" class="btn btn-primary">保存规则</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
