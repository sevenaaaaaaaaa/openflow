<?php
/**
 * 人机协同修复中心 — 系统生成修复方案，人工确认才应用，可回滚
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$message = '';
$error = '';

// 生成补丁方案
if (isset($_GET['generate'])) {
    csrf_verify();
    $patches = SafeFix::generatePatches();
    SafeFix::savePatches($patches);
    $message = '已生成 ' . count($patches) . ' 个修复方案，请审核后确认应用。';
    header('Location: /xmp/safefix?done=1');
    exit;
}

// 应用 / 回滚
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = trim($_POST['id'] ?? '');
    if (isset($_POST['apply'])) {
        $r = SafeFix::apply($id);
        if ($r['ok']) $message = '✅ 修复已应用，原文件已备份，可随时回滚。';
        else $error = '应用失败：' . ($r['error'] ?? '未知');
        header('Location: /xmp/safefix');
        exit;
    }
    if (isset($_POST['rollback'])) {
        $r = SafeFix::rollback($id);
        if ($r['ok']) $message = '↩️ 已回滚到修复前状态。';
        else $error = '回滚失败：' . ($r['error'] ?? '未知');
        header('Location: /xmp/safefix');
        exit;
    }
}

$patches = SafeFix::patches();
$applied = SafeFix::state()['applied'] ?? [];

admin_header('人机协同修复');
?>
<style>
  .fix-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;margin-bottom:14px}
  .fix-diff{background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:12px;font-family:var(--font-mono);font-size:12px;overflow-x:auto;line-height:1.7}
  .fix-old{color:var(--danger);text-decoration:line-through;background:var(--danger-soft);border-radius:4px;padding:1px 4px}
  .fix-new{color:var(--ok);background:var(--ok-soft);border-radius:4px;padding:1px 4px}
  .fix-note{background:var(--warn-soft);border:1px solid var(--warn);border-radius:10px;padding:12px;font-size:13px;color:var(--warn);margin-bottom:14px}
</style>

<div class="admin-layout">
  <?php admin_sidebar('safefix'); ?>
  <div class="main">
    <h1 style="font-size:20px;font-weight:800">🛡️ 人机协同修复</h1>
    <p class="sub" style="margin-top:2px">系统只生成修复方案，绝不自动改代码。你审核确认后才应用，且可一键回滚。</p>

    <div class="fix-note">
      <b>⚠️ 安全原则</b>：本页面所有修复都需要你手动点击「应用」才生效。应用前系统会备份原文件，出问题可回滚。系统永远不会自动执行修复。
    </div>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div style="margin-bottom:16px">
      <a href="safefix.php?generate=1&_csrf_token=<?=htmlspecialchars(csrf_token())?>" class="btn btn-primary">🔍 扫描生成修复方案</a>
    </div>

    <?php if (empty($patches)): ?>
    <div class="card text-muted text-center" style="padding:40px">暂无修复方案。点击"扫描生成修复方案"让系统体检代码。</div>
    <?php else: ?>
    <?php foreach ($patches as $p): $st = $p['status'] ?? 'pending'; ?>
    <div class="fix-card" style="<?=$st==='applied'?'border-color:var(--ok)':($st==='rolled_back'?'opacity:.6':'')?>">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
        <span class="evo-badge" style="background:<?=$p['severity']==='high'?'var(--danger)':'#ea580c'?>;color:#fff"><?=$p['severity']==='high'?'严重':'高'?></span>
        <span class="evo-tag"><?=htmlspecialchars($p['category'] ?? 'bug')?></span>
        <b style="font-size:15px"><?=htmlspecialchars($p['title'])?></b>
        <span class="text-xs text-muted" style="margin-left:auto">
          <?php if ($st === 'applied'): ?><span style="color:var(--ok)">✅ 已应用 <?=htmlspecialchars($p['applied_at'] ?? '')?></span>
          <?php elseif ($st === 'rolled_back'): ?><span style="color:var(--muted)">↩️ 已回滚</span>
          <?php else: ?><span style="color:var(--warn)">⏳ 待审核</span><?php endif; ?>
        </span>
      </div>

      <div class="text-sm text-muted mb-3" style="line-height:1.6"><?=htmlspecialchars($p['reason'])?></div>

      <div class="fix-diff mb-3">
        <div style="font-size:10px;color:var(--faint);margin-bottom:6px">📄 <?=htmlspecialchars($p['file'])?></div>
        <div><span class="fix-old">- <?=htmlspecialchars($p['old'])?></span></div>
        <div><span class="fix-new">+ <?=htmlspecialchars($p['new'])?></span></div>
      </div>

      <?php if ($st === 'pending'): ?>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>"><button class="btn btn-primary" name="apply" value="1" onclick="return confirm('确认应用此修复？应用前会自动备份原文件。')">✅ 审核通过，应用修复</button></form>
      <?php elseif ($st === 'applied'): ?>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>"><button class="btn btn-ghost" name="rollback" value="1" onclick="return confirm('确认回滚到修复前？')">↩️ 回滚修复</button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($applied)): ?>
    <h2 style="font-size:15px;font-weight:800;margin:20px 0 12px">📜 应用记录</h2>
    <?php foreach (array_slice(array_reverse($applied), 0, 10) as $a): ?>
    <div class="evo-history">
      <div class="font-bold text-sm">✅ <?=htmlspecialchars($a['title'])?></div>
      <div class="text-xs text-muted mt-1"><?=htmlspecialchars($a['file'])?> · <?=htmlspecialchars($a['applied_at'])?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();
