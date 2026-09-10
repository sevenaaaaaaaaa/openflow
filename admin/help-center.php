<?php
/**
 * 帮助中心管理 — 分类与指南文章的 CRUD
 * 数据：data/help-center.json（前台 /help 即时生效）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/HelpCenter.php';
require_login();
require_perm('articles');

$data = HelpCenter::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_article') {
        $id = trim((string)($_POST['id'] ?? ''));
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '' || $slug === '') {
            flash('error', '标题与 slug 不能为空');
            header('Location: /xmp/help-center');
            exit;
        }
        // slug 唯一性
        foreach ($data['articles'] as $a) {
            if (($a['slug'] ?? '') === $slug && ($a['id'] ?? '') !== $id) {
                flash('error', 'slug「' . $slug . '」已被占用');
                header('Location: /xmp/help-center');
                exit;
            }
        }
        $row = [
            'slug' => $slug,
            'cat' => (string)($_POST['cat'] ?? 'start'),
            'title' => $title,
            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
            'content' => (string)($_POST['content'] ?? ''),
            'hot' => isset($_POST['hot']),
            'order' => (int)($_POST['order'] ?? 99),
            'status' => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'updated_at' => date('Y-m-d'),
        ];
        if ($id === '') {
            $row['id'] = 'h' . date('YmdHis') . substr(bin2hex(random_bytes(2)), 0, 3);
            $data['articles'][] = $row;
        } else {
            foreach ($data['articles'] as &$a) if (($a['id'] ?? '') === $id) { $a = array_merge($a, $row); break; }
            unset($a);
        }
        HelpCenter::save($data);
        flash('success', '指南已保存');
        header('Location: /xmp/help-center');
        exit;
    }

    if ($action === 'delete_article') {
        $id = (string)($_POST['id'] ?? '');
        $data['articles'] = array_values(array_filter($data['articles'], fn($a) => ($a['id'] ?? '') !== $id));
        HelpCenter::save($data);
        flash('success', '指南已删除');
        header('Location: /xmp/help-center');
        exit;
    }

    if ($action === 'save_cats') {
        $cats = [];
        foreach ((array)($_POST['cat_id'] ?? []) as $i => $cid) {
            $cid = preg_replace('/[^a-z0-9_]/', '', (string)$cid);
            $name = trim((string)($_POST['cat_name'][$i] ?? ''));
            if ($cid === '' || $name === '') continue;
            $cats[] = [
                'id' => $cid, 'name' => $name,
                'icon' => trim((string)($_POST['cat_icon'][$i] ?? '📄')) ?: '📄',
                'desc' => trim((string)($_POST['cat_desc'][$i] ?? '')),
            ];
        }
        if ($cats) { $data['categories'] = $cats; HelpCenter::save($data); flash('success', '分类已保存'); }
        header('Location: /xmp/help-center');
        exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') $edit = ['id' => '', 'slug' => '', 'cat' => 'start', 'title' => '', 'excerpt' => '', 'content' => '', 'hot' => false, 'order' => 99, 'status' => 'published'];
    else $edit = HelpCenter::getRaw((string)$_GET['edit']);
}

$fbAll = json_read(HelpCenter::feedbackFile());
admin_header('帮助中心管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('help-center'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>帮助中心</h1><p class="v-sub">前台 <a href="/help" target="_blank" style="color:var(--accent)">/help</a> 的内容源 · 保存即上线</p></div>
      <div class="v-actions"><a href="?edit=new" class="btn btn-p btn-sm">+ 新建指南</a></div>
    </div>

    <?php if ($edit !== null): ?>
    <!-- ═══ 指南编辑 ═══ -->
    <div class="card">
      <h2><?=$edit['id'] === '' ? '新建指南' : '编辑：' . htmlspecialchars($edit['title'])?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_article">
        <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px">
          <div class="field"><label>标题</label><input type="text" name="title" required value="<?=htmlspecialchars($edit['title'])?>"></div>
          <div class="field"><label>Slug（URL）</label><input type="text" name="slug" required pattern="[a-z0-9-]+" value="<?=htmlspecialchars($edit['slug'])?>" placeholder="getting-started"></div>
          <div class="field"><label>分类</label>
            <select name="cat"><?php foreach ($data['categories'] as $c): ?><option value="<?=$c['id']?>" <?=($edit['cat'] ?? '') === $c['id'] ? 'selected' : ''?>><?=$c['icon']?> <?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="field"><label>摘要（搜索与列表用）</label><input type="text" name="excerpt" value="<?=htmlspecialchars($edit['excerpt'] ?? '')?>" placeholder="一句话说明这篇指南解决什么"></div>
        <div class="field"><label>正文（Markdown，支持 ## 小节 / 列表 / 表格 / 代码）</label>
          <textarea name="content" rows="16" style="font-family:var(--font-mono);font-size:13px;line-height:1.7"><?=htmlspecialchars($edit['content'] ?? '')?></textarea>
        </div>
        <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
          <label style="font-size:13px;display:flex;gap:6px;align-items:center"><input type="checkbox" name="hot" <?=!empty($edit['hot']) ? 'checked' : ''?>> 热门推荐</label>
          <label style="font-size:13px;display:flex;gap:6px;align-items:center">排序 <input type="number" name="order" value="<?=(int)($edit['order'] ?? 99)?>" style="width:70px"></label>
          <label style="font-size:13px;display:flex;gap:6px;align-items:center">状态
            <select name="status" style="width:auto"><option value="published" <?=($edit['status'] ?? '') === 'published' ? 'selected' : ''?>>发布</option><option value="draft" <?=($edit['status'] ?? '') === 'draft' ? 'selected' : ''?>>草稿</option></select>
          </label>
        </div>
        <div style="margin-top:14px">
          <button type="submit" class="btn btn-primary">保存指南</button>
          <a href="/xmp/help-center" class="btn btn-ghost">取消</a>
          <?php if ($edit['id'] !== ''): ?><a href="/help/<?=urlencode($edit['slug'])?>" target="_blank" class="btn btn-ghost">预览 →</a><?php endif; ?>
        </div>
      </form>
    </div>

    <?php else: ?>
    <!-- ═══ 指南列表 ═══ -->
    <div class="card" style="padding:0;overflow:auto;margin-bottom:20px">
      <table>
        <thead><tr><th>指南</th><th>分类</th><th>反馈</th><th>状态</th><th>更新</th><th class="actions">操作</th></tr></thead>
        <tbody>
          <?php if (empty($data['articles'])): ?><tr><td colspan="6" class="empty">还没有指南，点右上角新建。</td></tr><?php endif; ?>
          <?php foreach ($data['articles'] as $a):
            $cat = HelpCenter::category((string)($a['cat'] ?? ''));
            $fb = $fbAll[$a['slug'] ?? ''] ?? ['up' => 0, 'down' => 0]; ?>
          <tr>
            <td><strong><?=htmlspecialchars($a['title'] ?? '')?></strong><?=!empty($a['hot']) ? ' <span class="badge" style="background:oklch(0.72 0.15 85 / 0.15);color:oklch(0.55 0.13 85)">🔥 热门</span>' : ''?><br><span class="hint" style="font-size:11.5px">/help/<?=htmlspecialchars($a['slug'] ?? '')?></span></td>
            <td><span class="badge badge-gray"><?=$cat['icon'] ?? ''?> <?=htmlspecialchars($cat['name'] ?? '—')?></span></td>
            <td class="text-sm text-muted">👍 <?=$fb['up']?> · 👎 <?=$fb['down']?></td>
            <td><span class="badge <?=($a['status'] ?? 'published') === 'published' ? 'badge-green' : 'badge-gray'?>"><?=($a['status'] ?? 'published') === 'published' ? '已发布' : '草稿'?></span></td>
            <td class="text-sm text-muted nowrap"><?=htmlspecialchars($a['updated_at'] ?? '')?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?=urlencode($a['id'] ?? '')?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline" data-confirm="删除「<?=htmlspecialchars($a['title'] ?? '')?>」？">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_article">
                <input type="hidden" name="id" value="<?=htmlspecialchars($a['id'] ?? '')?>">
                <button type="submit" class="btn btn-danger btn-sm">删</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ═══ 分类管理 ═══ -->
    <div class="card">
      <h2>分类（<?=count($data['categories'])?>）</h2>
      <p class="hint" style="margin-bottom:12px">行顺序即前台展示顺序。删除分类不会删文章，但文章会归入「未分类」。</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_cats">
        <table style="width:100%;max-width:760px">
          <thead><tr><th style="width:110px">ID</th><th style="width:70px">图标</th><th>名称</th><th>描述</th></tr></thead>
          <tbody>
            <?php foreach ($data['categories'] as $c): ?>
            <tr>
              <td><input type="text" name="cat_id[]" value="<?=htmlspecialchars($c['id'])?>" readonly style="background:var(--hover);font-family:var(--font-mono);font-size:12px"></td>
              <td><input type="text" name="cat_icon[]" value="<?=htmlspecialchars($c['icon'])?>" style="width:52px;text-align:center"></td>
              <td><input type="text" name="cat_name[]" value="<?=htmlspecialchars($c['name'])?>"></td>
              <td><input type="text" name="cat_desc[]" value="<?=htmlspecialchars($c['desc'] ?? '')?>"></td>
            </tr>
            <?php endforeach; ?>
            <tr>
              <td><input type="text" name="cat_id[]" placeholder="new_id" style="font-family:var(--font-mono);font-size:12px"></td>
              <td><input type="text" name="cat_icon[]" placeholder="🧭" style="width:52px;text-align:center"></td>
              <td><input type="text" name="cat_name[]" placeholder="新分类"></td>
              <td><input type="text" name="cat_desc[]" placeholder="一句话描述"></td>
            </tr>
          </tbody>
        </table>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">保存分类</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
