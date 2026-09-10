<?php
/**
 * 标签管理 — 标签云 + 用量统计 + 重命名/删除
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('tags');

$message = '';
$tags = get_tags();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $t = trim($_POST['tag'] ?? '');
        if ($t === '') {
            $message = '标签名不能为空';
        } elseif (in_array($t, $tags, true)) {
            $message = '标签「' . $t . '」已存在';
        } else {
            $tags[] = $t;
            sort($tags);
            save_tags($tags);
            $message = '标签已添加';
        }
    }
    if ($action === 'delete' && isset($_POST['tag'])) {
        $tags = array_values(array_filter($tags, fn($v) => $v !== $_POST['tag']));
        save_tags($tags);
        $message = '标签已删除（文章上的同名标签不受影响，会随下次编辑同步）';
    }
    if ($action === 'rename') {
        $from = trim($_POST['from'] ?? '');
        $to = trim($_POST['to'] ?? '');
        if ($from !== '' && $to !== '' && $from !== $to && in_array($from, $tags, true)) {
            if (!in_array($to, $tags, true)) {
                $tags = array_values(array_filter($tags, fn($v) => $v !== $from));
                $tags[] = $to;
                sort($tags);
                save_tags($tags);
            }
            // 同步替换所有文章上的标签
            $arts = json_read(ARTICLES_DIR . '/index.json');
            $touched = 0;
            foreach ($arts as &$a) {
                $at = (array)($a['tags'] ?? []);
                if (in_array($from, $at, true)) {
                    $a['tags'] = array_values(array_unique(array_map(fn($v) => $v === $from ? $to : $v, $at)));
                    $touched++;
                }
            }
            unset($a);
            if ($touched) json_write(ARTICLES_DIR . '/index.json', $arts);
            $message = '已重命名为「' . $to . '」' . ($touched ? "，同步更新 {$touched} 篇文章" : '');
        }
    }
    $tags = get_tags();
}

// 用量统计：每标签被多少篇文章引用
$usage = array_fill_keys($tags, 0);
try {
    foreach (json_read(ARTICLES_DIR . '/index.json') as $a) {
        foreach ((array)($a['tags'] ?? []) as $t) {
            if (isset($usage[$t])) $usage[$t]++;
        }
    }
} catch (Exception $e) {}
$maxUse = max(1, $usage ? max($usage) : 1);

if (!defined('OF_EMBED')) admin_header('标签管理');
?>
<style>
.tag-cloud{display:flex;flex-wrap:wrap;gap:10px}
.tag-chip{display:inline-flex;align-items:center;gap:8px;padding:7px 8px 7px 13px;border-radius:99px;background:var(--surface);border:1px solid var(--border);font-size:13px;font-weight:600;color:var(--fg);transition:all .18s}
.tag-chip:hover{border-color:var(--accent);box-shadow:0 2px 10px oklch(from var(--accent) l c h / 0.15);transform:translateY(-1px)}
.tag-chip .tc-n{font-family:var(--font-mono);font-size:10.5px;font-weight:700;color:var(--accent);background:var(--accent-soft,oklch(0.95 0.03 262));border-radius:99px;padding:2px 7px;min-width:22px;text-align:center}
.tag-chip .tc-acts{display:inline-flex;gap:2px}
.tag-chip .tc-btn{border:0;background:transparent;cursor:pointer;width:20px;height:20px;border-radius:6px;display:grid;place-items:center;font-size:12px;color:var(--faint);padding:0;transition:all .15s}
.tag-chip .tc-btn:hover{background:var(--hover);color:var(--fg)}
.tag-chip .tc-btn.del:hover{background:oklch(from var(--danger,#dc2626) l c h / 0.12);color:var(--danger,#dc2626)}
.tag-search{max-width:260px}
.tag-usage-bar{height:3px;border-radius:99px;background:var(--hover);overflow:hidden;margin-top:3px}
.tag-usage-bar i{display:block;height:100%;background:var(--accent);border-radius:99px}
</style>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('tags'); ?>
  <div class="main">
<?php endif; ?>
    <h1>标签管理</h1>
    <p class="sub">文章标签体系 · 用量统计 · 重命名自动同步到所有文章</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <h2 style="margin:0">标签云（<?=count($tags)?>）</h2>
        <input type="text" class="tag-search" id="tagFilter" placeholder="🔍 筛选标签…" style="margin-left:auto">
      </div>
      <?php if (empty($tags)): ?>
        <p class="hint">暂无标签。在下方添加第一个，或在文章编辑器里直接打标签。</p>
      <?php else: ?>
      <div class="tag-cloud" id="tagCloud">
        <?php foreach ($tags as $t): $n = $usage[$t] ?? 0; ?>
        <span class="tag-chip" data-name="<?=htmlspecialchars(mb_strtolower($t))?>">
          <span><?=htmlspecialchars($t)?></span>
          <span class="tc-n" title="<?=$n?> 篇文章使用"><?=$n?></span>
          <span class="tc-acts">
            <button type="button" class="tc-btn" title="重命名" onclick="tagRename('<?=htmlspecialchars($t, ENT_QUOTES)?>', this)">✎</button>
            <form method="post" style="display:inline" data-confirm="删除标签「<?=htmlspecialchars($t)?>」？">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tag" value="<?=htmlspecialchars($t)?>">
              <button type="submit" class="tc-btn del" title="删除">×</button>
            </form>
          </span>
        </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>添加标签</h2>
      <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field" style="margin-bottom:0;flex:1;min-width:220px"><label>标签名称</label><input type="text" name="tag" required placeholder="如：GEO 优化"></div>
        <button type="submit" class="btn btn-primary">添加</button>
      </form>
    </div>

    <form method="post" id="tagRenameForm" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="from" id="tagRenameFrom">
      <input type="hidden" name="to" id="tagRenameTo">
    </form>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<script>
/* 标签筛选 */
document.getElementById('tagFilter').addEventListener('input', function () {
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('#tagCloud .tag-chip').forEach(function (c) {
    c.style.display = (!q || c.dataset.name.indexOf(q) >= 0) ? '' : 'none';
  });
});
/* 重命名：行内编辑（点 ✎ 标签名变输入框，回车保存 / Esc 取消） */
function tagRename(name, btn) {
  var chip = btn.closest('.tag-chip');
  var nameEl = chip.querySelector('span:first-child');
  var input = document.createElement('input');
  input.type = 'text'; input.value = name;
  input.style.cssText = 'width:' + Math.max(60, name.length * 14) + 'px;padding:2px 6px;border:1.5px solid var(--accent);border-radius:6px;font-size:13px;background:var(--surface);color:var(--fg);outline:none';
  nameEl.replaceWith(input);
  input.focus(); input.select();
  var done = false;
  function submit() {
    if (done) return; done = true;
    var to = input.value.trim();
    if (to && to !== name) {
      document.getElementById('tagRenameFrom').value = name;
      document.getElementById('tagRenameTo').value = to;
      document.getElementById('tagRenameForm').submit();
    } else { input.replaceWith(nameEl); }
  }
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); submit(); }
    if (e.key === 'Escape') { done = true; input.replaceWith(nameEl); }
  });
  input.addEventListener('blur', submit);
}
</script>
<?php admin_footer(); endif; ?>
