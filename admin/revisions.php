<?php
/**
 * 内容修订历史（P0-03）
 *
 * 一条内容的所有版本、每版动了哪些字段、谁改的（人 / AI / 外部协作者），
 * 任意两版并排比对，一键还原。还原本身也记一版，所以还原也能撤销。
 *
 * 访问：/xmp/revisions?type=article&id=xxx
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/RevisionSystem.php';
require_once __DIR__ . '/../lib/VersionDiff.php';
require_login();
require_perm('articles');

$type = preg_replace('/[^a-z]/', '', $_GET['type'] ?? 'article');
$id   = (string)($_GET['id'] ?? '');
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    csrf_verify();
    $r = rev_restore($type, (string)$_POST['id'], (int)$_POST['rev']);
    header('Location: /xmp/revisions?type=' . urlencode($type) . '&id=' . urlencode((string)$_POST['id'])
         . '&msg=' . urlencode($r['ok'] ? ('已还原到第 ' . (int)$_POST['rev'] . ' 版') : $r['error']));
    exit;
}
if (!empty($_GET['msg'])) $message = (string)$_GET['msg'];

$revs = $id !== '' ? rev_all($type, $id) : [];
$revs = array_reverse($revs);                       // 新的在上
$latestRev = $revs ? (int)$revs[0]['rev'] : 0;

// 比对的两版：默认「上一版 vs 最新版」
$revB = isset($_GET['b']) ? (int)$_GET['b'] : $latestRev;
$revA = isset($_GET['a']) ? (int)$_GET['a'] : max(0, $latestRev - 1);
$diffField = (string)($_GET['field'] ?? 'content');

$title = '';
if ($type === 'article') { $a = get_article($id); $title = $a['title'] ?? $id; }
elseif ($type === 'landing') {
    require_once __DIR__ . '/../lib/BuilderPages.php';
    $lp = builder_page_get($id); $title = $lp['title'] ?? $id;
}
else { $p = json_read(PAGES_DIR . '/' . $id . '.json'); $title = $p['title'] ?? $id; }

// 回到哪里去：不同类型的编辑页不一样
$backUrl = $type === 'article' ? '/xmp/article-edit?id=' . urlencode($id)
        : ($type === 'landing' ? '/xmp/page-builder?edit=' . urlencode($id) : '/xmp/content-hub?tab=pages');

admin_header('修订历史');
?>
<style>
.rv-wrap{display:grid;grid-template-columns:minmax(0,320px) minmax(0,1fr);gap:22px;align-items:start}
.rv-item{display:block;padding:12px 14px;border-radius:10px;border:1px solid transparent;text-decoration:none;color:inherit;transition:background .15s,border-color .15s}
.rv-item:hover{background:var(--hover)}
.rv-item.on{border-color:var(--accent);background:var(--accent-soft)}
.rv-head{display:flex;align-items:baseline;gap:8px}
.rv-no{font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--accent)}
.rv-when{font-size:12px;color:var(--muted);margin-left:auto;font-family:var(--font-mono)}
.rv-by{font-size:12.5px;color:var(--muted);margin-top:3px}
.rv-fields{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.rv-fields span{font-size:11px;padding:1px 7px;border-radius:999px;background:var(--hover);color:var(--muted)}
.rv-src{font-size:10.5px;font-family:var(--font-mono);padding:1px 6px;border-radius:3px;background:var(--hover);color:var(--muted)}
.rv-src.mcp{background:var(--accent-soft);color:var(--accent)}
.rv-src.external{background:var(--warn-soft);color:var(--warn)}
.diff-viewer{font-family:var(--font-mono);font-size:12.5px;line-height:1.75;white-space:pre-wrap;word-break:break-word;
  border:1px solid var(--border);border-radius:10px;overflow:hidden;max-height:62vh;overflow-y:auto}
.diff-line{padding:1px 12px}
.diff-equal{color:var(--muted)}
.diff-insert{background:var(--ok-soft);color:var(--ok)}
.diff-delete{background:var(--danger-soft);color:var(--danger);text-decoration:line-through}
.rv-pick{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.rv-pick select{height:34px}
@media(max-width:900px){.rv-wrap{grid-template-columns:1fr}}
</style>
<div class="admin-layout">
  <?php admin_sidebar('content-hub'); ?>
  <div class="main">
    <h1>修订历史</h1>
    <p class="sub"><?=htmlspecialchars($title)?> · 共 <?=count($revs)?> 版。每次保存自动记一版；还原也会记一版，所以还原本身也能撤销。
      <a href="<?=htmlspecialchars($backUrl)?>">← 回到编辑</a></p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <?php if ($id === ''): ?>
      <div class="of-empty">没有指定内容。请从文章编辑页点「修订历史」进入。</div>
    <?php elseif (!$revs): ?>
      <div class="of-empty">这条内容还没有修订记录。下次保存后就会出现第一版。</div>
    <?php else: ?>

    <div class="rv-wrap">
      <div class="card" style="padding:10px">
        <div class="sb-panel-h" style="padding:6px 10px 8px">版本</div>
        <?php foreach ($revs as $r): $on = (int)$r['rev'] === $revB; ?>
        <a class="rv-item<?=$on ? ' on' : ''?>"
           href="?type=<?=urlencode($type)?>&id=<?=urlencode($id)?>&a=<?=max(0,(int)$r['rev']-1)?>&b=<?=(int)$r['rev']?>&field=<?=urlencode($diffField)?>">
          <div class="rv-head">
            <span class="rv-no">#<?=(int)$r['rev']?></span>
            <?php if ((int)$r['rev'] === $latestRev): ?><span class="badge badge-green">当前</span><?php endif; ?>
            <span class="rv-when"><?=htmlspecialchars(substr((string)$r['at'], 5, 14))?></span>
          </div>
          <div class="rv-by"><?=htmlspecialchars((string)$r['by'])?>
            <span class="rv-src <?=htmlspecialchars((string)$r['source'])?>"><?=htmlspecialchars(rev_source_label((string)$r['source']))?></span>
          </div>
          <?php if (!empty($r['changed'])): ?>
          <div class="rv-fields"><?php foreach ($r['changed'] as $f): ?><span><?=htmlspecialchars(rev_field_label($f))?></span><?php endforeach; ?></div>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>

      <div>
        <?php
        $ra = rev_get($type, $id, $revA);
        $rb = rev_get($type, $id, $revB);
        $fields = [];
        foreach ([$ra, $rb] as $x) if ($x) $fields = array_unique(array_merge($fields, array_keys((array)$x['data'])));
        if (!in_array($diffField, $fields, true) && $fields) $diffField = $fields[0];
        ?>
        <div class="rv-pick">
          <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars($id)?>">
            <label class="hint">比对</label>
            <select name="a" class="inp" onchange="this.form.submit()">
              <?php foreach (array_reverse($revs) as $r): ?>
              <option value="<?=(int)$r['rev']?>"<?=(int)$r['rev']===$revA?' selected':''?>>#<?=(int)$r['rev']?> · <?=htmlspecialchars(substr((string)$r['at'],5,11))?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">→</span>
            <select name="b" class="inp" onchange="this.form.submit()">
              <?php foreach (array_reverse($revs) as $r): ?>
              <option value="<?=(int)$r['rev']?>"<?=(int)$r['rev']===$revB?' selected':''?>>#<?=(int)$r['rev']?> · <?=htmlspecialchars(substr((string)$r['at'],5,11))?></option>
              <?php endforeach; ?>
            </select>
            <select name="field" class="inp" onchange="this.form.submit()">
              <?php foreach ($fields as $f): ?>
              <option value="<?=htmlspecialchars($f)?>"<?=$f===$diffField?' selected':''?>><?=htmlspecialchars(rev_field_label($f))?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php if ($rb && (int)$rb['rev'] !== $latestRev): ?>
          <form method="post" style="margin-left:auto" data-confirm="确定把内容还原到第 <?=(int)$rb['rev']?> 版？当前版本会先存为一版，可以再撤销。">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars($id)?>">
            <input type="hidden" name="rev" value="<?=(int)$rb['rev']?>">
            <button class="btn btn-primary btn-sm">还原到第 <?=(int)$rb['rev']?> 版</button>
          </form>
          <?php endif; ?>
        </div>

        <?php
        $diff = ($ra && $rb) ? rev_field_diff($type, $id, $revA, $revB, $diffField) : null;
        if ($diff === null): ?>
          <div class="of-empty">选中的两版里没有可比对的内容。</div>
        <?php else:
          $st = VersionDiff::stats($diff); ?>
          <p class="hint" style="margin-bottom:8px">
            #<?=$revA?> → #<?=$revB?> 的「<?=htmlspecialchars(rev_field_label($diffField))?>」：
            <span style="color:var(--ok)">+<?=$st['inserts']?></span> ·
            <span style="color:var(--danger)">−<?=$st['deletes']?></span>
            <?php if ($st['inserts'] === 0 && $st['deletes'] === 0): ?>（这个字段没有变化）<?php endif; ?>
          </p>
          <?=VersionDiff::renderHtml($diff)?>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
