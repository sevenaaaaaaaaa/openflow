<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

$eventsFile = DATA_DIR . '/events/index.json';
$events = json_read($eventsFile);
$message = '';

// Delete
if (isset($_GET['delete'])) {
    $events = array_values(array_filter($events, fn($e) => $e['id'] !== $_GET['delete']));
    json_write($eventsFile, $events);
    flash('success', '活动已删除');
    header('Location: events.php');
    exit;
}

// 导出 CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="events-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['title', 'start_date', 'end_date', 'location', 'status', 'description']);
    foreach ($events as $e) {
        fputcsv($fp, [$e['title'] ?? '', $e['start_date'] ?? '', $e['end_date'] ?? '', $e['location'] ?? '', $e['status'] ?? 'draft', $e['description'] ?? '']);
    }
    fclose($fp);
    exit;
}

// 导入 CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_csv'])) {
    csrf_verify();
    $file = $_FILES['import_csv'];
    if ($file['error'] === UPLOAD_ERR_OK && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'csv') {
        $imported = 0; $skipped = 0;
        if (($fp = fopen($file['tmp_name'], 'r')) !== false) {
            $header = fgetcsv($fp);
            while (($row = fgetcsv($fp)) !== false) {
                $data = array_combine($header, array_pad($row, count($header), ''));
                $title = trim($data['title'] ?? '');
                if (empty($title)) { $skipped++; continue; }
                $dup = false;
                foreach ($events as $e) if (($e['title'] ?? '') === $title) { $dup = true; break; }
                if ($dup) { $skipped++; continue; }
                $events[] = [
                    'id' => 'evt_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
                    'title' => $title,
                    'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $title),
                    'description' => $data['description'] ?? '',
                    'content' => '',
                    'start_date' => $data['start_date'] ?? '',
                    'end_date' => $data['end_date'] ?? '',
                    'location' => $data['location'] ?? '',
                    'location_url' => '',
                    'speakers' => [], 'gallery' => [], 'video_url' => '', 'cover' => '',
                    'registration_form' => '', 'registration_url' => '',
                    'status' => $data['status'] ?? 'draft',
                    'seo_title' => '', 'seo_desc' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $imported++;
            }
            fclose($fp);
            json_write($eventsFile, $events);
            flash('success', "导入完成：新增 {$imported} 个，跳过 {$skipped} 个（空/重复）");
        } else {
            flash('error', '无法读取 CSV 文件');
        }
    } else {
        flash('error', '请上传 CSV 文件');
    }
    header('Location: events.php');
    exit;
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $data = [
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'description' => $_POST['description'] ?? '',
        'content' => $_POST['content'] ?? '',
        'start_date' => $_POST['start_date'] ?? '',
        'end_date' => $_POST['end_date'] ?? '',
        'location' => $_POST['location'] ?? '',
        'location_url' => $_POST['location_url'] ?? '',
        'speakers' => [],
        'gallery' => array_filter(explode("\n", str_replace("\r", "", $_POST['gallery'] ?? ''))),
        'video_url' => $_POST['video_url'] ?? '',
        'cover' => $_POST['cover'] ?? '',
        'registration_form' => $_POST['registration_form'] ?? '',
        'registration_url' => $_POST['registration_url'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'seo_title' => $_POST['seo_title'] ?? '',
        'seo_desc' => $_POST['seo_desc'] ?? '',
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($data['slug'])) $data['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $data['title']);

    // Speakers
    $speakerNames = $_POST['speaker_name'] ?? [];
    foreach ($speakerNames as $i => $sn) {
        if (empty(trim($sn))) continue;
        $data['speakers'][] = [
            'name' => $sn,
            'title' => $_POST['speaker_title'][$i] ?? '',
            'avatar' => $_POST['speaker_avatar'][$i] ?? '',
            'bio' => $_POST['speaker_bio'][$i] ?? '',
        ];
    }

    if (empty($id)) {
        $data['id'] = 'event_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $data['created_at'] = date('Y-m-d H:i:s');
        $events[] = $data;
    } else {
        foreach ($events as &$e) { if ($e['id'] === $id) { $e = array_merge($e, $data); break; } }
    }
    json_write($eventsFile, $events);
    $message = '活动已保存';
    $events = json_read($eventsFile);
}

// Edit mode
$editEvent = null;
if (isset($_GET['edit'])) {
    foreach ($events as $e) { if ($e['id'] === $_GET['edit']) { $editEvent = $e; break; } }
}

// 状态过滤 + 时间状态
$f_status = $_GET['f'] ?? 'all';
$now = time();
$filtered = array_filter($events, function ($e) use ($f_status) {
    if ($f_status === 'all') return true;
    return ($e['status'] ?? 'draft') === $f_status;
});
usort($filtered, fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));
$counts = ['all' => count($events), 'draft' => 0, 'published' => 0];
foreach ($events as $e) $counts[$e['status'] ?? 'draft'] = ($counts[$e['status'] ?? 'draft'] ?? 0) + 1;

$forms = json_read(DATA_DIR . '/forms/index.json');

admin_header('活动管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('events'); ?>
  <div class="main">
    <div class="flex items-center gap-4" style="align-items:center">
      <h1 style="margin-bottom:0">活动管理</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
          <?= csrf_field() ?>
          <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin-bottom:0">📥 导入 CSV<input type="file" name="import_csv" accept=".csv" style="display:none" onchange="this.form.submit()"></label>
        </form>
        <a href="?export=1" class="btn btn-ghost btn-sm">📤 导出 CSV</a>
      </div>
    </div>
    <p class="sub">线上/线下活动 · 独立落地页 · 嘉宾 · 组图 · 报名 · 视频回顾</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- List -->
    <div class="card" style="padding:0;overflow:auto">
      <div style="display:flex;gap:6px;padding:16px 20px 0;flex-wrap:wrap">
        <a href="?f=all" class="badge <?=$f_status==='all'?'badge-yellow':''?>" style="cursor:pointer;text-decoration:none">全部 (<?=$counts['all']?>)</a>
        <a href="?f=published" class="badge <?=$f_status==='published'?'badge-green':''?>" style="cursor:pointer;text-decoration:none">已发布 (<?=$counts['published']??0?>)</a>
        <a href="?f=draft" class="badge <?=$f_status==='draft'?'badge-yellow':''?>" style="cursor:pointer;text-decoration:none">草稿 (<?=$counts['draft']??0?>)</a>
      </div>
      <table>
        <thead><tr><th>活动标题</th><th>时间</th><th>地点</th><th>嘉宾</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($filtered)): ?><tr><td colspan="6" class="empty">暂无活动 · <a href="?edit=new" style="color:var(--accent)">创建第一个</a></td></tr><?php endif; ?>
          <?php foreach ($filtered as $e):
            $sd = strtotime($e['start_date'] ?? '');
            $ed = strtotime($e['end_date'] ?? '');
            $timeTag = '';
            if ($e['status'] === 'published' && $sd && $ed) {
                if ($ed < $now) $timeTag = '<span class="badge badge-gray" style="font-size:10px">已结束</span>';
                elseif ($sd <= $now) $timeTag = '<span class="badge badge-green" style="font-size:10px">进行中</span>';
                elseif ($sd - $now < 7 * 86400) $timeTag = '<span class="badge badge-yellow" style="font-size:10px">即将</span>';
            }
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($e['title'])?></strong></td>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($e['start_date'] ?? '', 0, 16))?> <?=$timeTag?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($e['location'] ?? '')?></td>
            <td><?=count($e['speakers'] ?? [])?> 位</td>
            <td><span class="badge <?=($e['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$e['status']??'draft'?></span></td>
            <td><a href="?edit=<?=urlencode($e['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="../content-preview.php?type=event&id=<?=urlencode($e['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a><a href="?delete=<?=urlencode($e['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除?')">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:12px 20px;border-top:1px solid var(--border)"><a href="?edit=new" class="btn btn-primary btn-sm">+ 创建活动</a></div>
    </div>

    <!-- Editor -->
    <?php if (isset($_GET['edit'])): ?>
    <div class="card">
      <h2><?=$editEvent?'编辑活动':'创建活动'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?=htmlspecialchars($editEvent['id']??'')?>">
        <div class="field-row">
          <div class="field"><label>活动标题</label><input type="text" name="title" value="<?=htmlspecialchars($editEvent['title']??'')?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($editEvent['slug']??'')?>" placeholder="自动生成"></div>
        </div>
        <div class="field"><label>描述</label><textarea name="description" rows="2"><?=htmlspecialchars($editEvent['description']??'')?></textarea></div>

        <!-- Time & Location -->
        <div class="card" style="margin:12px 0;padding:16px;background:var(--surface-2)">
          <h2>📅 时间地点</h2>
          <div class="field-row">
            <div class="field"><label>开始时间</label><input type="datetime-local" name="start_date" value="<?=htmlspecialchars($editEvent['start_date']??'')?>"></div>
            <div class="field"><label>结束时间</label><input type="datetime-local" name="end_date" value="<?=htmlspecialchars($editEvent['end_date']??'')?>"></div>
          </div>
          <div class="field-row">
            <div class="field"><label>地点</label><input type="text" name="location" value="<?=htmlspecialchars($editEvent['location']??'')?>" placeholder="线上 / 上海市..."></div>
            <div class="field"><label>地图链接</label><input type="url" name="location_url" value="<?=htmlspecialchars($editEvent['location_url']??'')?>" placeholder="https://..."></div>
          </div>
        </div>

        <!-- Speakers -->
        <div class="card" style="margin:12px 0;padding:16px;background:var(--surface-2)">
          <h2>🎤 嘉宾</h2>
          <div id="speakersList">
            <?php foreach (($editEvent['speakers'] ?? []) as $si => $sp): ?>
            <div class="speaker-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;padding:8px;background:var(--surface);border-radius:8px">
              <input type="text" name="speaker_name[]" value="<?=htmlspecialchars($sp['name'])?>" placeholder="姓名" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">
              <input type="text" name="speaker_title[]" value="<?=htmlspecialchars($sp['title'])?>" placeholder="头衔" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">
              <input type="text" name="speaker_avatar[]" value="<?=htmlspecialchars($sp['avatar']??'')?>" placeholder="头像" style="width:80px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:12px">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addSpeaker()">+ 添加嘉宾</button>
        </div>

        <!-- Content -->
        <div class="field"><label>活动内容 (HTML)</label><textarea name="content" rows="6" style="font-family:var(--mono);font-size:14px;line-height:1.6"><?=htmlspecialchars($editEvent['content']??'')?></textarea></div>

        <!-- Gallery & Video -->
        <div class="field-row">
          <div class="field"><label>活动组图 <span class="hint">每行一个路径</span></label><textarea name="gallery" rows="3" style="font-family:var(--mono);font-size:13px"><?=htmlspecialchars(implode("\n", $editEvent['gallery'] ?? []))?></textarea></div>
          <div class="field"><label>视频回顾 URL</label><input type="url" name="video_url" value="<?=htmlspecialchars($editEvent['video_url']??'')?>" placeholder="https://..."></div>
        </div>
        <div class="field-row">
          <div class="field"><label>封面图</label><input type="text" name="cover" value="<?=htmlspecialchars($editEvent['cover']??'')?>" placeholder="uploads/..."></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editEvent['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editEvent['status']??'')==='published'?'selected':''?>>已发布</option></select></div>
        </div>

        <!-- Registration -->
        <div class="card" style="margin:12px 0;padding:16px;background:var(--surface-2)">
          <h2>📋 报名设置</h2>
          <div class="field-row">
            <div class="field"><label>关联表单</label><select name="registration_form"><option value="">无</option><?php foreach ($forms as $f): ?><option value="<?=htmlspecialchars($f['slug'])?>" <?=($editEvent['registration_form']??'')===$f['slug']?'selected':''?>><?=htmlspecialchars($f['title'])?></option><?php endforeach; ?></select></div>
            <div class="field"><label>外部报名链接</label><input type="url" name="registration_url" value="<?=htmlspecialchars($editEvent['registration_url']??'')?>" placeholder="https://..."></div>
          </div>
        </div>

        <!-- SEO -->
        <div class="field-row">
          <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($editEvent['seo_title']??'')?>"></div>
          <div class="field"><label>SEO 描述</label><input type="text" name="seo_desc" value="<?=htmlspecialchars($editEvent['seo_desc']??'')?>"></div>
        </div>

        <button type="submit" class="btn btn-primary">保存活动</button>
        <a href="events.php" class="btn btn-ghost">取消</a>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function addSpeaker() {
  var div = document.createElement('div');
  div.className = 'speaker-row';
  div.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;align-items:center;padding:8px;background:var(--surface);border-radius:8px';
  div.innerHTML =
    '<input type="text" name="speaker_name[]" placeholder="姓名" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">' +
    '<input type="text" name="speaker_title[]" placeholder="头衔" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">' +
    '<input type="text" name="speaker_avatar[]" placeholder="头像" style="width:80px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:12px">' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('speakersList').appendChild(div);
}
</script>
<?php admin_footer(); ?>
