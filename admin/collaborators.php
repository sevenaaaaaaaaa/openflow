<?php
/**
 * 外部临时协作 —— 后台管理（主线 B）
 *
 * 发一条限时链接，把某一篇内容交给外面的人；随时能看谁看过、留了什么意见，
 * 随时能一键收回。链接的明文只在发出的那一刻显示一次——之后系统里只剩哈希，
 * 找不回来（找得回来的东西，泄漏一次就等于一直泄漏）。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CollabAccess.php';
require_once __DIR__ . '/../lib/CollabReview.php';
require_login();
require_perm('articles');

$message = ''; $error = ''; $freshToken = ''; $freshGrant = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $r = collab_create([
            'label'     => $_POST['label'] ?? '',
            'note'      => $_POST['note'] ?? '',
            'type'      => $_POST['type'] ?? '',
            'target_id' => $_POST['target_id'] ?? '',
            'caps'      => (array)($_POST['caps'] ?? ['view']),
            'days'      => (int)($_POST['days'] ?? 7),
        ]);
        if ($r['ok']) { $freshToken = $r['token']; $freshGrant = $r['grant']; $message = '协作链接已生成'; }
        else $error = $r['error'];
    } elseif ($act === 'revoke') {
        collab_revoke((string)($_POST['id'] ?? '')) ? $message = '链接已吊销，立刻失效' : $error = '链接不存在';
    } elseif ($act === 'resolve') {
        $r = note_resolve((string)$_POST['type'], (string)$_POST['target_id'], (string)$_POST['note_id'],
                            (string)($_SESSION['admin_user'] ?? ''), ($_POST['to'] ?? '1') === '1');
        $r['ok'] ? $message = '已更新批注状态' : $error = $r['error'];
    } elseif ($act === 'reply') {
        $r = note_reply((string)$_POST['type'], (string)$_POST['target_id'], (string)$_POST['note_id'],
                          (string)($_POST['text'] ?? ''), ['name' => (string)($_SESSION['admin_user'] ?? '作者'), 'kind' => 'admin']);
        $r['ok'] ? $message = '已回复' : $error = $r['error'];
    }
}

$grants = array_reverse(collab_all());
$pending = note_pending_all(40);

// 可共享的内容：文章 + 落地页
$articles = array_slice(json_read(ARTICLES_DIR . '/index.json'), 0, 300);
require_once __DIR__ . '/../lib/BuilderPages.php';
$lps = builder_pages_all();

admin_header('外部协作');
?>
<style>
.cb-tok{font-family:var(--font-mono);font-size:12.5px;background:var(--hover);border:1px dashed var(--accent);
  border-radius:8px;padding:10px 12px;word-break:break-all;margin:8px 0}
.cb-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.cb-note{border-left:3px solid var(--accent);background:var(--hover);border-radius:8px;padding:10px 12px;margin-bottom:10px}
.cb-note.done{border-left-color:var(--border);opacity:.6}
.cb-q{font-size:12px;color:var(--muted);border-left:2px solid var(--border);padding-left:8px;margin:4px 0}
.cb-caps label{display:inline-flex;align-items:center;gap:6px;margin-right:14px;font-size:13.5px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('content-hub'); ?>
  <div class="main">
    <h1>外部协作</h1>
    <p class="sub">把一篇内容限时交给外面的人——外包写手、顾问、客户。对方不需要注册账号，
      改动会记进版本历史并标明是谁改的，到期自动收回，也可以随时手动吊销。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <?php if ($freshToken): ?>
    <div class="card" style="border-color:var(--accent)">
      <h3 style="margin-top:0">把这条链接发给「<?=htmlspecialchars($freshGrant['label'])?>」</h3>
      <div class="cb-tok" id="tok"><?=htmlspecialchars(of_abs_url('/c/' . $freshToken))?></div>
      <div class="cb-row">
        <button class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('tok').textContent.trim());this.textContent='已复制'">复制链接</button>
        <span class="hint">⚠️ 这条链接只显示这一次。关掉这个提示就再也看不到了——系统里只存了它的哈希。</span>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <h3 style="margin-top:0">发一条新的协作链接</h3>
      <form method="post" class="form-grid">
        <?=csrf_field()?><input type="hidden" name="action" value="create">
        <label>给谁用
          <input class="inp" name="label" required placeholder="例如：王编辑 / 李顾问" maxlength="60">
        </label>
        <label>共享哪一篇
          <select class="inp" name="target_id" id="tsel" required>
            <optgroup label="文章">
              <?php foreach ($articles as $a): ?>
              <option value="<?=htmlspecialchars($a['id'])?>" data-type="article"><?=htmlspecialchars(mb_substr($a['title'] ?? $a['id'], 0, 40))?></option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="落地页">
              <?php foreach ($lps as $p): ?>
              <option value="<?=htmlspecialchars($p['id'])?>" data-type="page"><?=htmlspecialchars(mb_substr($p['title'] ?? $p['id'], 0, 40))?></option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </label>
        <input type="hidden" name="type" id="ttype" value="article">
        <label>有效期
          <select class="inp" name="days">
            <option value="1">1 天</option><option value="3">3 天</option>
            <option value="7" selected>7 天</option><option value="14">14 天</option>
            <option value="30">30 天</option><option value="90">90 天（最长）</option>
          </select>
        </label>
        <label style="grid-column:1/-1">能做什么
          <div class="cb-caps" style="margin-top:6px">
            <?php foreach (collab_caps() as $ck => $cv): ?>
            <label><input type="checkbox" name="caps[]" value="<?=$ck?>" <?=$ck !== 'edit' ? 'checked' : ''?>
              <?=$ck === 'view' ? 'disabled' : ''?>> <?=htmlspecialchars($cv)?></label>
            <?php endforeach; ?>
            <input type="hidden" name="caps[]" value="view">
          </div>
          <span class="hint">编辑权限给出去之前想一下：对方的每次保存都会记进版本历史并标明是谁改的，随时可以还原；
            落地页只能改区块文案，增删和排序仍然只有你能做。</span>
        </label>
        <label style="grid-column:1/-1">备注（自己看的）
          <input class="inp" name="note" maxlength="200" placeholder="例如：本周五前给意见">
        </label>
        <div style="grid-column:1/-1"><button class="btn btn-primary">生成链接</button></div>
      </form>
    </div>

    <?php if ($pending): ?>
    <h2 style="margin-top:28px">待处理的批注 <span class="badge badge-orange"><?=count($pending)?></span></h2>
    <div class="card">
      <?php foreach ($pending as $n): ?>
      <div class="cb-note">
        <div class="cb-row" style="justify-content:space-between">
          <span><b><?=htmlspecialchars($n['by'] ?? '')?></b>
            <span class="hint">· <?=htmlspecialchars($n['at'] ?? '')?> ·
            <a href="/xmp/collaborators#"><?=htmlspecialchars(note_target_title((string)$n['_type'], (string)$n['_id']))?></a></span></span>
          <form method="post" style="display:flex;gap:6px">
            <?=csrf_field()?><input type="hidden" name="action" value="resolve">
            <input type="hidden" name="type" value="<?=htmlspecialchars($n['_type'])?>">
            <input type="hidden" name="target_id" value="<?=htmlspecialchars($n['_id'])?>">
            <input type="hidden" name="note_id" value="<?=htmlspecialchars($n['id'])?>">
            <input type="hidden" name="to" value="1">
            <button class="btn btn-sm">标记已处理</button>
          </form>
        </div>
        <?php if (!empty($n['quote'])): ?><div class="cb-q"><?=htmlspecialchars($n['quote'])?></div><?php endif; ?>
        <div><?=nl2br(htmlspecialchars($n['text'] ?? ''))?></div>
        <form method="post" style="margin-top:8px;display:flex;gap:6px">
          <?=csrf_field()?><input type="hidden" name="action" value="reply">
          <input type="hidden" name="type" value="<?=htmlspecialchars($n['_type'])?>">
          <input type="hidden" name="target_id" value="<?=htmlspecialchars($n['_id'])?>">
          <input type="hidden" name="note_id" value="<?=htmlspecialchars($n['id'])?>">
          <input class="inp" name="text" placeholder="回复协作者…" required>
          <button class="btn btn-sm">回复</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 style="margin-top:28px">已发出的链接</h2>
    <?php if (!$grants): ?>
      <div class="of-empty">还没有发过协作链接。</div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>给谁</th><th>内容</th><th>权限</th><th>状态</th><th>到期</th><th>访问</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($grants as $g): [$st, $color] = collab_status($g); ?>
        <tr>
          <td><b><?=htmlspecialchars($g['label'] ?? '')?></b>
            <?php if (!empty($g['note'])): ?><br><span class="hint"><?=htmlspecialchars($g['note'])?></span><?php endif; ?></td>
          <td><?=htmlspecialchars(mb_substr(note_target_title((string)$g['type'], (string)$g['target_id']), 0, 30))?>
            <br><span class="hint"><?=htmlspecialchars(collab_types()[$g['type']] ?? $g['type'])?></span></td>
          <td><span class="hint"><?php
            echo htmlspecialchars(implode(' · ', array_map(fn($c) => collab_caps()[$c] ?? $c, (array)($g['caps'] ?? []))));
          ?></span></td>
          <td><span class="badge badge-<?=$color?>"><?=$st?></span></td>
          <td class="text-sm"><?=htmlspecialchars(substr((string)($g['expires_at'] ?? ''), 0, 16))?></td>
          <td class="text-sm"><?php
            echo (int)($g['seen_count'] ?? 0) . ' 次';
            if (!empty($g['last_seen_at'])) echo '<br><span class="hint">' . htmlspecialchars(substr($g['last_seen_at'], 5, 11)) . '</span>';
          ?></td>
          <td>
            <?php if (empty($g['revoked'])): ?>
            <form method="post" data-confirm="吊销后这条链接立刻失效，对方再打开会看到「链接已过期」。确定吗？">
              <?=csrf_field()?><input type="hidden" name="action" value="revoke">
              <input type="hidden" name="id" value="<?=htmlspecialchars($g['id'])?>">
              <button class="btn btn-danger btn-sm">吊销</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<script>
// 选中哪一篇，就把对应的类型一起带上（文章 / 落地页判定不同的能力）
(function(){
  var sel = document.getElementById('tsel'), ty = document.getElementById('ttype');
  function sync(){ var o = sel.options[sel.selectedIndex]; ty.value = o ? (o.dataset.type || 'article') : 'article'; }
  sel.addEventListener('change', sync); sync();
})();
</script>
<?php admin_footer(); ?>
