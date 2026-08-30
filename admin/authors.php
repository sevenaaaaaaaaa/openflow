<?php
/**
 * 作者管理 —— 把散落的作者名字收成一等身份
 *
 * 三件事：给作者建带简介/头像/链接/绑定账号的档案；发现内容里
 * 「已署名但没建档」的作者一键登记；把「同一个人两种写法」合并成一个。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AuthorSystem.php';
require_login();
require_perm('authors');

$message = ''; $error = '';

if (($_POST['action'] ?? '') === 'save') {
    $links = [];
    foreach ((array)($_POST['link_label'] ?? []) as $i => $lbl) {
        $url = trim((string)(($_POST['link_url'] ?? [])[$i] ?? ''));
        if ($url === '') continue;
        $links[] = ['label' => trim((string)$lbl) ?: '链接', 'url' => $url];
    }
    $r = author_save([
        'id'        => $_POST['id'] ?? '',
        'name'      => $_POST['name'] ?? '',
        'slug'      => $_POST['slug'] ?? '',
        'title'     => $_POST['title'] ?? '',
        'bio'       => $_POST['bio'] ?? '',
        'avatar'    => $_POST['avatar'] ?? '',
        'member_id' => $_POST['member_id'] ?? '',
        'aliases'   => array_filter(array_map('trim', explode(',', $_POST['aliases'] ?? ''))),
        'links'     => $links,
        'featured'  => !empty($_POST['featured']),
        'created_at'=> $_POST['created_at'] ?? '',
    ]);
    if ($r['ok']) { $message = "作者「{$r['author']['name']}」已保存。"; audit('保存作者 ' . $r['author']['name'], 'content'); }
    else $error = $r['error'];
}

if (isset($_GET['delete'])) {
    author_delete((string)$_GET['delete']);
    audit('删除作者档案 ' . $_GET['delete'], 'content');
    $message = '作者档案已删除（内容里的署名不受影响）。';
}

// 快速登记：从"未登记作者"一键建档
if (($_POST['action'] ?? '') === 'quick_register') {
    $r = author_save(['name' => $_POST['name'] ?? '']);
    $message = $r['ok'] ? "已为「{$_POST['name']}」建档，去补全简介吧。" : $r['error'];
}

// 合并
if (($_POST['action'] ?? '') === 'merge') {
    $n = author_merge(trim($_POST['from_name'] ?? ''), trim($_POST['to_id'] ?? ''));
    audit('合并作者 ' . ($_POST['from_name'] ?? '') . ' → ' . ($_POST['to_id'] ?? ''), 'content', ['articles' => $n]);
    $message = "已合并：改写了 {$n} 篇文章的署名，旧名已收作别名。";
}

$authors  = author_all();
$discover = author_discover();
$editId   = trim($_GET['edit'] ?? '');
$editing  = $editId !== '' ? author_get($editId) : null;
$members  = json_read(DATA_DIR . '/members/index.json');

admin_header('作者管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('authors'); ?>
  <div class="main">
    <h1>作者管理</h1>
    <p class="sub">作者是文章/课程/skills/插件的统一署名身份，也可绑定平台账号——作者、创作者、开发者收成一个人。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <?php if ($discover): ?>
      <div class="card" style="border-left:3px solid var(--warn)">
        <h2 style="margin-top:0;font-size:15px">未登记作者 · <?=count($discover)?> 位</h2>
        <p class="sub">这些名字在内容里署了名，但还没建档（也就没有简介/头像/主页聚合）。一键建档，或合并到已有作者。</p>
        <table class="table"><tbody>
          <?php foreach ($discover as $name => $c): $total = array_sum($c); ?>
            <tr>
              <td><b><?=htmlspecialchars($name)?></b></td>
              <td class="sub" style="font-size:12px">文章 <?=$c['articles']?> · 课程 <?=$c['courses']?> · skills <?=$c['skills']?> · 插件 <?=$c['plugins']?></td>
              <td style="white-space:nowrap;text-align:right">
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="quick_register"><input type="hidden" name="name" value="<?=htmlspecialchars($name)?>"><button class="btn btn-ghost btn-sm">建档</button></form>
                <?php if ($authors): ?>
                <form method="post" style="display:inline-flex;gap:4px;align-items:center"><?= csrf_field() ?><input type="hidden" name="action" value="merge"><input type="hidden" name="from_name" value="<?=htmlspecialchars($name)?>">
                  <select name="to_id" style="font-size:12px"><option value="">合并到…</option><?php foreach ($authors as $a): ?><option value="<?=htmlspecialchars($a['id'])?>"><?=htmlspecialchars($a['name'])?></option><?php endforeach; ?></select>
                  <button class="btn btn-ghost btn-sm" onclick="return this.form.to_id.value?confirm('把「<?=htmlspecialchars($name)?>」的署名并入所选作者?'):false">合并</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:0;overflow-x:auto">
      <table class="table">
        <thead><tr><th>作者</th><th>职位</th><th>内容</th><th>绑定账号</th><th style="width:1%">操作</th></tr></thead>
        <tbody>
          <?php if (!$authors): ?><tr><td colspan="5" class="empty">还没有作者档案（上方"未登记作者"可一键建档）</td></tr><?php endif; ?>
          <?php foreach ($authors as $a): $c = author_content_counts($a['name']); ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <?php if (!empty($a['avatar'])): ?><img src="<?=htmlspecialchars($a['avatar'])?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover"><?php endif; ?>
                  <div><b><?=htmlspecialchars($a['name'])?></b> <?php if (!empty($a['featured'])): ?><span class="badge ok">推荐</span><?php endif; ?>
                  <div class="sub" style="font-size:11px">/authors/<?=htmlspecialchars($a['slug'])?></div></div>
                </div>
              </td>
              <td class="sub" style="font-size:12px"><?=htmlspecialchars($a['title'] ?: '—')?></td>
              <td class="sub" style="font-size:12px"><?=$c['articles']?>文 · <?=$c['courses']?>课 · <?=$c['skills']?>技 · <?=$c['plugins']?>件</td>
              <td class="sub" style="font-size:12px"><?=!empty($a['member_id']) ? '已绑定' : '—'?></td>
              <td style="white-space:nowrap">
                <a href="?edit=<?=urlencode($a['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
                <a href="/authors/<?=urlencode($a['slug'])?>" target="_blank" class="btn btn-ghost btn-sm">主页</a>
                <a href="?delete=<?=urlencode($a['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('删除档案?内容里的署名不受影响')">删</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h2 style="margin-top:26px"><?= $editing ? '编辑作者：' . htmlspecialchars($editing['name']) : '新建作者' ?></h2>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?=htmlspecialchars($editing['id'] ?? '')?>">
      <input type="hidden" name="created_at" value="<?=htmlspecialchars($editing['created_at'] ?? '')?>">
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div class="field" style="flex:1;min-width:160px"><label>名字 <span class="hint">与内容署名一致</span></label><input type="text" name="name" value="<?=htmlspecialchars($editing['name'] ?? '')?>" required></div>
        <div class="field" style="flex:1;min-width:140px"><label>Slug <span class="hint">主页地址，留空自动生成</span></label><input type="text" name="slug" value="<?=htmlspecialchars($editing['slug'] ?? '')?>" placeholder="自动"></div>
        <div class="field" style="flex:1;min-width:140px"><label>职位 / 头衔</label><input type="text" name="title" value="<?=htmlspecialchars($editing['title'] ?? '')?>" placeholder="增长顾问 / 独立开发者"></div>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div class="field" style="flex:1;min-width:200px"><label>头像 URL</label><input type="text" name="avatar" value="<?=htmlspecialchars($editing['avatar'] ?? '')?>" placeholder="/uploads/... 或 https://..."></div>
        <div class="field" style="flex:1;min-width:160px"><label>绑定平台账号 <span class="hint">作者=创作者/开发者</span></label>
          <select name="member_id"><option value="">不绑定</option>
            <?php foreach ($members as $m): ?><option value="<?=htmlspecialchars($m['id'] ?? '')?>" <?=($editing['member_id'] ?? '')===($m['id'] ?? '')?'selected':''?>><?=htmlspecialchars(($m['name'] ?? '') ?: ($m['email'] ?? $m['id'] ?? ''))?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field"><label>简介</label><textarea name="bio" rows="3" placeholder="一句话介绍这位作者"><?=htmlspecialchars($editing['bio'] ?? '')?></textarea></div>
      <div class="field"><label>别名 <span class="hint">逗号分隔，历史上其它写法都写这里，按旧名也能聚合到 TA</span></label><input type="text" name="aliases" value="<?=htmlspecialchars(implode(', ', (array)($editing['aliases'] ?? [])))?>" placeholder="张三, 张 三, Zhang San"></div>

      <div class="field"><label>外部链接</label>
        <div id="links">
          <?php foreach ((array)($editing['links'] ?? []) as $l): ?>
            <div style="display:flex;gap:8px;margin-bottom:6px">
              <input name="link_label[]" value="<?=htmlspecialchars($l['label'] ?? '')?>" placeholder="标签(网站/微博)" style="width:150px">
              <input name="link_url[]" value="<?=htmlspecialchars($l['url'] ?? '')?>" placeholder="https://..." style="flex:1">
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addLink()">+ 加链接</button>
      </div>
      <label style="display:flex;gap:8px;align-items:center;margin:10px 0"><input type="checkbox" name="featured" value="1" <?=!empty($editing['featured'])?'checked':''?>> 设为推荐作者</label>

      <div style="display:flex;gap:8px">
        <button class="btn btn-primary"><?= $editing ? '保存' : '创建' ?></button>
        <?php if ($editing): ?><a href="/xmp/authors" class="btn btn-ghost">取消</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<script>
function addLink() {
  var w = document.getElementById('links'), r = document.createElement('div');
  r.style.cssText = 'display:flex;gap:8px;margin-bottom:6px';
  r.innerHTML = '<input name="link_label[]" placeholder="标签(网站/微博)" style="width:150px"><input name="link_url[]" placeholder="https://..." style="flex:1">';
  w.appendChild(r);
}
</script>
<?php admin_footer(); ?>
