<?php
/**
 * 外部协作者页面 —— 主线 B（2026-09-04）
 *
 * 外面的人（外包写手、顾问、客户）凭一条限时链接进来，只看得到被授权的那一篇，
 * 可以留块级批注、按权限改内容。不需要注册账号——一人公司找的临时协作者，
 * 不会为了改一篇稿子去注册。
 *
 * 【token 不留在地址栏】/c/{token} 第一次打开就把 token 换成一个作用域会话，
 * 然后跳到干净的 /c/。这样 token 不会留在浏览器历史、书签和 referrer 里。
 *
 * 【这里绝不 require_login()】它是公开入口，鉴权完全靠 grant。
 * 但 CSRF 不能因此漏掉：后台是在 require_login() 里统一收口的，
 * 这个页面走不到那条路，所以自己在门口调一次 csrf_guard_auto()，
 * 同样是「守在入口」而不是每个处理器各写各的。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/CollabAccess.php';
require_once __DIR__ . '/lib/CollabReview.php';
require_once __DIR__ . '/lib/BlockContract.php';
require_once __DIR__ . '/lib/BlockRegistry.php';

/* ── 1. token → 会话 ── */
$incoming = trim((string)($_GET['t'] ?? ''));
if ($incoming !== '') {
    $g = collab_verify($incoming);
    if ($g) {
        session_regenerate_id(true);              // 防会话固定
        $_SESSION['collab_grant'] = $g['id'];
        collab_touch($g['id']);
        collab_audit('外部协作者打开链接', $g);
        header('Location: /c/');                  // 把 token 从地址栏里去掉
        exit;
    }
    unset($_SESSION['collab_grant']);
    collab_deny();
}

/* ── 2. 会话 → grant（每次都重新校验，吊销与过期立刻生效）── */
$gid = (string)($_SESSION['collab_grant'] ?? '');
$grant = $gid !== '' ? collab_get($gid) : null;
if (!$grant) { unset($_SESSION['collab_grant']); collab_deny(); }

// 入口处统一做 CSRF，和后台 require_login() 里的收口是同一个思路
csrf_guard_auto();

$type = (string)$grant['type'];
$tid  = (string)$grant['target_id'];
$actor = ['name' => (string)$grant['label'], 'kind' => 'external'];
$canComment = collab_can($grant, 'comment');
$canEdit    = collab_can($grant, 'edit');

$notice = ''; $error = '';

/* ── 3. 动作 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'comment') {
        if (!$canComment) { $error = '这条链接没有批注权限'; }
        else {
            $r = note_add($type, $tid, (string)($_POST['anchor'] ?? ''), (string)($_POST['text'] ?? ''), $actor);
            $r['ok'] ? $notice = '批注已提交' : $error = $r['error'];
            if ($r['ok']) collab_audit('外部协作者留下批注', $grant, ['anchor' => (string)($_POST['anchor'] ?? '')]);
        }
    } elseif ($act === 'reply') {
        if (!$canComment) { $error = '这条链接没有批注权限'; }
        else {
            $r = note_reply($type, $tid, (string)($_POST['note_id'] ?? ''), (string)($_POST['text'] ?? ''), $actor);
            $r['ok'] ? $notice = '已回复' : $error = $r['error'];
        }
    } elseif ($act === 'edit') {
        // 只有文章能被外部编辑：落地页没有版本记录，见 CollabAccess 里的说明
        if (!$canEdit || $type !== 'article') { $error = '这条链接没有编辑权限'; }
        else {
            collab_set_actor($grant);             // 改动以「外部协作者」身份进修订历史
            $ok = save_article($tid, [
                'title'   => (string)($_POST['title'] ?? ''),
                'content' => (string)($_POST['content'] ?? ''),
            ]);
            $ok ? $notice = '已保存，你的改动记进了版本历史' : $error = '保存失败';
            if ($ok) collab_audit('外部协作者修改内容', $grant);
        }
    }
    // POST 后重定向，避免刷新重复提交
    $_SESSION['collab_flash'] = ['n' => $notice, 'e' => $error];
    header('Location: /c/'); exit;
}
if (!empty($_SESSION['collab_flash'])) {
    $notice = (string)($_SESSION['collab_flash']['n'] ?? '');
    $error  = (string)($_SESSION['collab_flash']['e'] ?? '');
    unset($_SESSION['collab_flash']);
}

/* ── 4. 取内容 ── */
$title  = note_target_title($type, $tid);
$blocks = note_blocks($type, $tid);
$notesByAnchor = note_by_anchor($type, $tid);
$aliveAnchors = [];
foreach ($blocks as $b) $aliveAnchors[block_key_of($b)] = true;
$orphans = [];
foreach ($notesByAnchor as $anchor => $ns) {
    if ($anchor !== '' && !isset($aliveAnchors[$anchor])) foreach ($ns as $n) $orphans[] = $n;
}
$general = $notesByAnchor[''] ?? [];
$article = ($type === 'article' && function_exists('get_article')) ? get_article($tid) : null;
$expLeft = max(0, strtotime((string)$grant['expires_at']) - time());
$expText = $expLeft > 86400 ? floor($expLeft / 86400) . ' 天后到期'
         : ($expLeft > 3600 ? floor($expLeft / 3600) . ' 小时后到期' : '不到 1 小时后到期');

/** 访问被拒：不说是猜错了还是过期了 */
function collab_deny(): void {
    http_response_code(403);
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>链接无效</title>'
       . '<meta name="robots" content="noindex,nofollow"><style>body{font-family:system-ui,-apple-system,"PingFang SC",sans-serif;'
       . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#fafaf9;color:#44403c}'
       . 'div{text-align:center;padding:40px}h1{font-size:19px;margin:0 0 8px}p{color:#78716c;font-size:14px;margin:0;line-height:1.7}</style>'
       . '</head><body><div><h1>链接无效或已过期</h1>'
       . '<p>协作链接是限时的。如果你还需要访问，<br>请向发给你链接的人重新要一条。</p></div></body></html>';
    exit;
}

function ctext(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=ctext($title)?> · 协作</title>
<style>
:root{--bg:#fafaf9;--surface:#fff;--border:#e7e5e4;--text:#292524;--muted:#78716c;--faint:#a8a29e;
  --accent:#0d9488;--accent-soft:#f0fdfa;--warn:#b45309;--warn-soft:#fffbeb;--ok:#15803d;--radius:10px}
@media (prefers-color-scheme:dark){:root{--bg:#1c1917;--surface:#292524;--border:#44403c;--text:#f5f5f4;
  --muted:#a8a29e;--faint:#78716c;--accent:#2dd4bf;--accent-soft:#134e4a;--warn:#fbbf24;--warn-soft:#422006;--ok:#4ade80}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:15px/1.75 system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif}
.bar{position:sticky;top:0;z-index:10;background:var(--surface);border-bottom:1px solid var(--border);padding:12px 20px;
  display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.bar h1{font-size:15px;margin:0;font-weight:650}
.pill{font-size:12px;padding:2px 9px;border-radius:99px;background:var(--accent-soft);color:var(--accent);white-space:nowrap}
.pill.warn{background:var(--warn-soft);color:var(--warn)}
.wrap{max-width:820px;margin:0 auto;padding:24px 20px 80px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;margin-bottom:16px}
.blk{position:relative;border-left:3px solid transparent;padding:2px 0 2px 14px;margin:2px 0;transition:border-color .15s}
.blk:hover{border-left-color:var(--border)}
.blk.has-note{border-left-color:var(--accent)}
.blk .add{position:absolute;right:-2px;top:2px;opacity:0;font-size:12px;color:var(--accent);background:none;border:0;cursor:pointer;padding:2px 6px}
.blk:hover .add{opacity:1}
.note{background:var(--surface);border:1px solid var(--border);border-left:3px solid var(--accent);border-radius:8px;
  padding:10px 12px;margin:8px 0 8px 14px;font-size:13.5px}
.note.done{border-left-color:var(--faint);opacity:.6}
.note .meta{font-size:12px;color:var(--muted);margin-bottom:4px}
.note .quote{font-size:12px;color:var(--muted);border-left:2px solid var(--border);padding-left:8px;margin:4px 0}
.note .rep{margin:6px 0 0 12px;padding-top:6px;border-top:1px dashed var(--border);font-size:13px}
.who{display:inline-block;font-size:11px;padding:0 6px;border-radius:3px;background:var(--accent-soft);color:var(--accent)}
.who.admin{background:var(--border);color:var(--muted)}
textarea,input[type=text]{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:7px;
  font:inherit;font-size:14px;background:var(--bg);color:var(--text);resize:vertical}
button.btn{background:var(--accent);color:#fff;border:0;border-radius:7px;padding:8px 16px;font:inherit;font-size:14px;cursor:pointer}
button.btn.ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.msg{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:14px}
.msg.ok{background:var(--accent-soft);color:var(--accent)}
.msg.err{background:var(--warn-soft);color:var(--warn)}
.hint{color:var(--muted);font-size:13px}
h2.sec{font-size:14px;margin:26px 0 10px;color:var(--muted);font-weight:600}
dialog{border:1px solid var(--border);border-radius:var(--radius);padding:18px;background:var(--surface);color:var(--text);max-width:520px;width:92%}
dialog::backdrop{background:rgba(0,0,0,.35)}
.prose :where(h1,h2,h3){line-height:1.4}
.prose img{max-width:100%}
</style>
</head>
<body>
<div class="bar">
  <h1><?=ctext($title)?></h1>
  <span class="pill"><?=ctext($grant['label'])?></span>
  <span class="pill <?=$expLeft < 86400 ? 'warn' : ''?>"><?=ctext($expText)?></span>
  <span class="pill"><?=$canEdit ? '可编辑' : ($canComment ? '可批注' : '只读')?></span>
</div>

<div class="wrap">
  <?php if ($notice): ?><div class="msg ok"><?=ctext($notice)?></div><?php endif; ?>
  <?php if ($error): ?><div class="msg err"><?=ctext($error)?></div><?php endif; ?>

  <?php if ($canEdit && $type === 'article' && $article): ?>
  <form method="post" class="card">
    <?=csrf_field()?><input type="hidden" name="action" value="edit">
    <p class="hint" style="margin-top:0">你的每次保存都会记进版本历史，标明是你改的，作者随时可以还原。</p>
    <input type="text" name="title" value="<?=ctext((string)($article['title'] ?? ''))?>" placeholder="标题" style="margin-bottom:10px">
    <textarea name="content" rows="16" placeholder="正文"><?=ctext((string)($article['content'] ?? ''))?></textarea>
    <div style="margin-top:10px"><button class="btn">保存改动</button></div>
  </form>
  <?php endif; ?>

  <h2 class="sec">内容<?=$canComment ? ' · 鼠标移到段落上可以留批注' : ''?></h2>
  <div class="card prose">
    <?php if (!$blocks): ?>
      <p class="hint">这篇内容还是空的。</p>
    <?php endif; ?>
    <?php foreach ($blocks as $b):
      $k = block_key_of($b);
      $ns = $notesByAnchor[$k] ?? [];
      $html = block_is_text($b) ? block_text_to_html($b) : builder_render_block($b);
    ?>
    <div class="blk <?=$ns ? 'has-note' : ''?>" id="b-<?=ctext($k)?>">
      <?php if ($canComment): ?>
      <button class="add" type="button" onclick="noteOn('<?=ctext($k)?>')">＋ 批注</button>
      <?php endif; ?>
      <?=$html?>
      <?php foreach ($ns as $n) echo render_note($n, $canComment); ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($orphans): ?>
  <h2 class="sec">原文已改动的批注</h2>
  <div class="card">
    <p class="hint" style="margin-top:0">这些批注针对的段落后来被改写或删掉了，所以对不上位置了。内容保留在这里。</p>
    <?php foreach ($orphans as $n) echo render_note($n, $canComment); ?>
  </div>
  <?php endif; ?>

  <h2 class="sec">整篇批注</h2>
  <div class="card">
    <?php foreach ($general as $n) echo render_note($n, $canComment); ?>
    <?php if (!$general): ?><p class="hint" style="margin-top:0">还没有针对整篇的批注。</p><?php endif; ?>
    <?php if ($canComment): ?>
    <form method="post" style="margin-top:12px">
      <?=csrf_field()?><input type="hidden" name="action" value="comment"><input type="hidden" name="anchor" value="">
      <textarea name="text" rows="3" placeholder="对整篇的意见…"></textarea>
      <div style="margin-top:8px"><button class="btn">提交批注</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($canComment): ?>
<dialog id="dlg">
  <form method="post">
    <?=csrf_field()?><input type="hidden" name="action" value="comment">
    <input type="hidden" name="anchor" id="dlgAnchor">
    <p style="margin:0 0 6px;font-size:13px;color:var(--muted)">针对这一段：</p>
    <p id="dlgQuote" class="hint" style="margin:0 0 12px;border-left:2px solid var(--border);padding-left:9px"></p>
    <textarea name="text" rows="4" placeholder="写下你的意见…" required></textarea>
    <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
      <button type="button" class="btn ghost" onclick="document.getElementById('dlg').close()">取消</button>
      <button class="btn">提交批注</button>
    </div>
  </form>
</dialog>
<script>
function noteOn(k){
  var el = document.getElementById('b-' + k);
  document.getElementById('dlgAnchor').value = k;
  document.getElementById('dlgQuote').textContent = (el ? el.innerText : '').trim().slice(0, 120);
  document.getElementById('dlg').showModal();
}
</script>
<?php endif; ?>

<?php
/** 一条批注（含回复）。所有外部输入都在这里转义。 */
function render_note(array $n, bool $canReply): string {
    $kind = ($n['by_kind'] ?? 'external') === 'admin' ? 'admin' : '';
    $h  = '<div class="note' . (!empty($n['resolved']) ? ' done' : '') . '">';
    $h .= '<div class="meta"><span class="who ' . $kind . '">' . ctext((string)($n['by'] ?? '')) . '</span> '
        . ctext((string)($n['at'] ?? '')) . (!empty($n['resolved']) ? ' · 已处理' : '') . '</div>';
    if (!empty($n['quote'])) $h .= '<div class="quote">' . ctext((string)$n['quote']) . '</div>';
    $h .= '<div>' . nl2br(ctext((string)($n['text'] ?? ''))) . '</div>';
    foreach ((array)($n['replies'] ?? []) as $r) {
        $rk = ($r['by_kind'] ?? '') === 'admin' ? 'admin' : '';
        $h .= '<div class="rep"><span class="who ' . $rk . '">' . ctext((string)($r['by'] ?? '')) . '</span> '
            . '<span class="meta">' . ctext((string)($r['at'] ?? '')) . '</span><br>'
            . nl2br(ctext((string)($r['text'] ?? ''))) . '</div>';
    }
    if ($canReply && empty($n['resolved'])) {
        $h .= '<form method="post" style="margin-top:8px;display:flex;gap:6px">' . csrf_field()
            . '<input type="hidden" name="action" value="reply">'
            . '<input type="hidden" name="note_id" value="' . ctext((string)($n['id'] ?? '')) . '">'
            . '<input type="text" name="text" placeholder="回复…" required>'
            . '<button class="btn ghost" style="white-space:nowrap">回复</button></form>';
    }
    return $h . '</div>';
}
?>
</body>
</html>
