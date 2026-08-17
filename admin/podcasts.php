<?php
/**
 * 播客与视频管理 — 列表 + 独立编辑页 + RSS 生成 + 播放统计
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$podFile = DATA_DIR . '/podcasts.json';
$pods = json_read($podFile);
$message = '';
$error = '';

// ─── 保存单条（编辑页）───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_one'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $item = [
        'id' => $id ?: ('pod_' . substr(bin2hex(random_bytes(4)), 0, 6)),
        'title' => trim($_POST['title'] ?? ''),
        'type' => $_POST['type'] ?? 'audio',
        'file' => trim($_POST['file'] ?? ''),
        'cover' => trim($_POST['cover'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'tags' => array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))),
        'duration' => trim($_POST['duration'] ?? ''),
        'pub_date' => trim($_POST['pub_date'] ?? ''),
        'featured' => isset($_POST['featured']),
        'status' => $_POST['status'] ?? 'published',
        'episode' => (int)($_POST['episode'] ?? 0),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $items = $pods['items'] ?? [];
    $found = false;
    foreach ($items as &$x) if ($x['id'] === $item['id']) { $x = array_merge($x, $item); $found = true; break; }
    unset($x);
    if (!$found) $items[] = $item;
    // 保持分类设置
    $pods['items'] = $items;
    json_write($podFile, $pods);
    $message = '播客/视频已保存';
}

// ─── 保存分类 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cats'])) {
    csrf_verify();
    $pods['categories'] = array_filter(array_map('trim', explode("\n", $_POST['categories'] ?? '')));
    json_write($podFile, $pods);
    $message = '分类已保存';
}

// ─── 删除 ───
if (isset($_GET['delete'])) {
    $pods['items'] = array_values(array_filter($pods['items'] ?? [], fn($p) => $p['id'] !== $_GET['delete']));
    json_write($podFile, $pods);
    flash('success', '已删除');
    header('Location: /xmp/podcasts');
    exit;
}

// ─── 复制 ───
if (isset($_GET['copy'])) {
    foreach (($pods['items'] ?? []) as $p) {
        if ($p['id'] === $_GET['copy']) {
            $p['id'] = 'pod_' . substr(bin2hex(random_bytes(4)), 0, 6);
            $p['title'] .= ' (副本)';
            $p['status'] = 'draft';
            $pods['items'][] = $p;
            json_write($podFile, $pods);
            flash('success', '已复制');
            break;
        }
    }
    header('Location: /xmp/podcasts');
    exit;
}

// ─── 编辑模式 ───
$editPod = null;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $editPod = ['id' => '', 'title' => '', 'type' => 'audio', 'file' => '', 'cover' => '', 'description' => '',
                    'category' => '', 'duration' => '', 'pub_date' => date('Y-m-d'), 'featured' => false, 'status' => 'published', 'episode' => 0];
    } else {
        foreach (($pods['items'] ?? []) as $p) if ($p['id'] === $_GET['edit']) { $editPod = $p; break; }
    }
}

// 已上传的媒体文件
$mediaFiles = [];
foreach (glob(UPLOAD_DIR . '/{podcasts,general}/*', GLOB_BRACE) as $f) if (is_file($f)) $mediaFiles[] = str_replace(UPLOAD_DIR . '/', 'uploads/', $f);

admin_header('播客与视频');
?>
<div class="admin-layout">
  <?php admin_sidebar('podcasts'); ?>
  <div class="main">
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <?php if ($editPod): ?>
    <!-- ═══ 独立编辑页 ═══ -->
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"><?=empty($editPod['id'])?'➕ 新增播客':'✏️ 编辑：'.htmlspecialchars($editPod['title'])?></h1>
      <a href="podcasts" class="btn btn-ghost ml-auto">← 返回列表</a>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save_one" value="1">
      <input type="hidden" name="id" value="<?=htmlspecialchars($editPod['id'])?>">
      <div class="card">
        <h2>基本信息</h2>
        <div class="field"><label>标题 <span class="hint">· 必填</span></label><input type="text" name="title" value="<?=htmlspecialchars($editPod['title'])?>" required></div>
        <div class="field-row">
          <div class="field"><label>类型</label><select name="type"><option value="audio" <?=$editPod['type']==='audio'?'selected':''?>>🎵 音频</option><option value="video" <?=$editPod['type']==='video'?'selected':''?>>🎬 视频</option></select></div>
          <div class="field"><label>状态</label><select name="status"><option value="published" <?=($editPod['status']??'')==='published'?'selected':''?>>已发布</option><option value="draft" <?=($editPod['status']??'')==='draft'?'selected':''?>>草稿</option></select></div>
          <div class="field"><label>集数</label><input type="number" name="episode" value="<?=htmlspecialchars($editPod['episode']??0)?>" min="0"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>分类</label><select name="category"><option value="">未分类</option><?php foreach ($pods['categories'] ?? [] as $cat): ?><option value="<?=htmlspecialchars($cat)?>" <?=$editPod['category']===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option><?php endforeach; ?></select></div>
          <div class="field"><label>标签 <span class="hint">· 逗号分隔</span></label><input type="text" name="tags" value="<?=htmlspecialchars(implode(', ', $editPod['tags'] ?? []))?>" placeholder="访谈, 案例"></div>
          <div class="field"><label>时长 <span class="hint">· 如 35:20</span></label><input type="text" name="duration" value="<?=htmlspecialchars($editPod['duration']??'')?>" placeholder="35:20"></div>
          <div class="field"><label>发布日期</label><input type="date" name="pub_date" value="<?=htmlspecialchars($editPod['pub_date']??'')?>"></div>
        </div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:14px"><input type="checkbox" name="featured" value="1" <?=!empty($editPod['featured'])?'checked':''?> style="width:16px;height:16px">⭐ 推荐 / 置顶</label></div>
      </div>

      <div class="card">
        <h2>媒体文件</h2>
        <div class="field"><label>媒体路径 <span class="hint">· 音频 mp3/wav 或视频 mp4/webm</span></label><input type="text" name="file" value="<?=htmlspecialchars($editPod['file'])?>" placeholder="uploads/general/xxx.mp3" style="font-family:var(--mono)"></div>
        <div class="field"><label>封面路径</label><input type="text" name="cover" value="<?=htmlspecialchars($editPod['cover'])?>" placeholder="uploads/images/cover.jpg"></div>
        <?php if (!empty($editPod['cover'])): ?><img src="/<?=htmlspecialchars($editPod['cover'])?>" style="max-width:160px;border-radius:10px;margin-top:8px" onerror="this.style.display='none'"><?php endif; ?>
        <div class="msg msg-info" style="margin-top:12px">先在<a href="media.php?dir=general">媒体库</a>上传音频/视频文件，再把路径粘贴到上面。</div>
      </div>

      <div class="card">
        <h2>简介</h2>
        <textarea name="description" rows="5" placeholder="播客简介 / 节目说明"><?=htmlspecialchars($editPod['description']??'')?></textarea>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">保存</button>
        <a href="podcasts" class="btn btn-ghost">取消</a>
      </div>
    </form>

    <?php else: ?>
    <!-- ═══ 列表页 ═══ -->
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 播客与视频</h1>
      <a href="podcasts.php?edit=new" class="btn btn-primary ml-auto">➕ 新增播客</a>
    </div>
    <p class="sub">前台展示于 /podcasts.php · 支持 RSS 订阅 · 点编辑进详情</p>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px">
        <h2 style="margin-bottom:0">分类管理</h2>
        <form method="post" style="display:flex;gap:8px;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="save_cats" value="1">
          <input type="text" name="categories" value="<?=htmlspecialchars(implode("\n", $pods['categories'] ?? []))?>" placeholder="每行一个分类" style="flex:1;min-width:220px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
          <button class="btn btn-ghost btn-sm">保存分类</button>
        </form>
      </div>
    </div>

    <div class="card" style="padding:0;overflow-x:auto">
      <table>
        <thead><tr><th>#</th><th>播客</th><th>类型</th><th>分类</th><th>时长</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pods['items'])): ?>
          <tr><td colspan="7" class="empty">暂无播客，点击右上角新增</td></tr>
          <?php endif; ?>
          <?php foreach (($pods['items'] ?? []) as $i => $p): ?>
          <tr>
            <td class="text-sm text-muted"><?=$i+1?></td>
            <td>
              <strong><?=htmlspecialchars($p['title'])?></strong>
              <?php if (!empty($p['featured'])): ?> <span class="badge badge-yellow">⭐</span><?php endif; ?>
              <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars(mb_substr($p['description']??'',0,40))?></div>
            </td>
            <td><?=$p['type']==='video'?'🎬 视频':'🎵 音频'?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['category']??'—')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['duration']??'—')?></td>
            <td><span class="badge <?=($p['status']??'published')==='published'?'badge-green':'badge-gray'?>"><?=($p['status']??'published')==='published'?'已发布':'草稿'?></span></td>
            <td style="white-space:nowrap">
              <a href="podcasts.php?edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">✏️ 编辑</a>
              <a href="podcasts.php?copy=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">📋</a>
              <a href="podcasts.php?delete=<?=urlencode($p['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除该播客?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div>
        <h2 style="margin-bottom:4px">📡 RSS 订阅源</h2>
        <p class="text-sm text-muted">订阅链接：<code><?=SITE_URL?>/podcasts?rss=1</code></p>
      </div>
      <div>
        <a href="../podcasts.php" class="btn btn-ghost btn-sm" target="_blank">👁 查看前台</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
