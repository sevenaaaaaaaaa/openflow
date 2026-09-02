<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('courses');

$coursesFile = DATA_DIR . '/courses/index.json';
$courses = json_read($coursesFile);

if (isset($_GET['delete'])) {
    $courses = array_values(array_filter($courses, fn($c) => $c['id'] !== $_GET['delete']));
    json_write($coursesFile, $courses);
    flash('success', '课程已删除');
    header('Location: /xmp/courses');
    exit;
}

// 课程审核（开发者/讲师提交的待审核课程）
if (isset($_GET['approve']) || isset($_GET['reject'])) {
    $cid = $_GET['approve'] ?? $_GET['reject'];
    foreach ($courses as &$c) {
        if ($c['id'] === $cid) {
            if (isset($_GET['approve'])) { $c['status'] = 'published'; $c['reviewed_at'] = date('Y-m-d H:i:s'); flash('success', "课程「{$c['title']}」已通过上架"); }
            else { $c['status'] = 'rejected'; $c['reviewed_at'] = date('Y-m-d H:i:s'); flash('error', "课程「{$c['title']}」已拒绝"); }
            break;
        }
    }
    unset($c);
    json_write($coursesFile, $courses);
    header('Location: /xmp/courses');
    exit;
}

// 导出 CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="courses-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['title', 'type', 'status', 'description', 'category']);
    foreach ($courses as $c) {
        fputcsv($fp, [$c['title'] ?? '', $c['type'] ?? '课程', $c['status'] ?? 'draft', $c['description'] ?? '', $c['category'] ?? '']);
    }
    fclose($fp);
    exit;
}

// 导入 CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_csv'])) {
    csrf_verify();
    $file = $_FILES['import_csv'];
    if ($file['error'] === UPLOAD_ERR_OK && ($ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) === 'csv') {
        $imported = 0; $skipped = 0;
        if (($fp = fopen($file['tmp_name'], 'r')) !== false) {
            $header = fgetcsv($fp);
            while (($row = fgetcsv($fp)) !== false) {
                $data = array_combine($header, array_pad($row, count($header), ''));
                $title = trim($data['title'] ?? '');
                if (empty($title)) { $skipped++; continue; }
                // 去重
                $dup = false;
                foreach ($courses as $c) if (($c['title'] ?? '') === $title) { $dup = true; break; }
                if ($dup) { $skipped++; continue; }
                $courses[] = [
                    'id' => 'course_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
                    'title' => $title,
                    'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $title),
                    'type' => $data['type'] ?? '课程',
                    'status' => $data['status'] ?? 'draft',
                    'description' => $data['description'] ?? '',
                    'category' => $data['category'] ?? '',
                    'chapters' => [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $imported++;
            }
            fclose($fp);
            json_write($coursesFile, $courses);
            flash('success', "导入完成：新增 {$imported} 门，跳过 {$skipped} 门（空/重复）");
        } else {
            flash('error', '无法读取 CSV 文件');
        }
    } else {
        flash('error', '请上传 CSV 文件');
    }
    header('Location: /xmp/courses');
    exit;
}

admin_header('课程管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('courses'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">课程管理</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
          <?= csrf_field() ?>
          <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin-bottom:0">📥 导入 CSV<input type="file" name="import_csv" accept=".csv" style="display:none" onchange="this.form.submit()"></label>
        </form>
        <a href="?export=1" class="btn btn-ghost btn-sm">📤 导出 CSV</a>
        <a href="course-edit.php" class="btn btn-primary btn-sm">创建课程</a>
      </div>
    </div>
    <p class="sub">管理课程、课时、章节与专栏打包 · <?=count($courses)?> 门课程</p>

    <?php
    // 筛选
    $typeFilter = $_GET['type'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $catFilter = $_GET['cat'] ?? '';
    $display = $courses;
    if ($typeFilter) $display = array_values(array_filter($display, fn($c) => ($c['type'] ?? '') === $typeFilter));
    if ($statusFilter) $display = array_values(array_filter($display, fn($c) => ($c['status'] ?? '') === $statusFilter));
    if ($catFilter) $display = array_values(array_filter($display, fn($c) => ($c['category'] ?? '') === $catFilter));
    $totalStudents = array_sum(array_map(fn($c) => (int)($c['students'] ?? 0), $courses));
    $publishedCount = count(array_filter($courses, fn($c) => ($c['status'] ?? '') === 'published'));
    $courseCats = get_categories('course');
    ?>
    <!-- 统计卡 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px">
      <div class="card" style="text-align:center;padding:14px"><div style="font-size:22px;font-weight:700"><?=count($courses)?></div><div class="text-sm text-muted" style="font-size:11px">总课程</div></div>
      <div class="card" style="text-align:center;padding:14px"><div style="font-size:22px;font-weight:700;color:var(--ok)"><?=$publishedCount?></div><div class="text-sm text-muted" style="font-size:11px">已发布</div></div>
      <div class="card" style="text-align:center;padding:14px"><div style="font-size:22px;font-weight:700"><?=$totalStudents?></div><div class="text-sm text-muted" style="font-size:11px">累计学员</div></div>
    </div>

    <!-- 筛选 -->
    <div class="flex gap-2 mb-4" style="flex-wrap:wrap">
      <a href="courses.php" class="btn btn-sm <?=!$typeFilter&&!$statusFilter?'btn-primary':'btn-ghost'?>">全部</a>
      <?php foreach (['课程','专栏','认证课','系列课'] as $t): ?>
      <a href="?type=<?=urlencode($t)?><?=$statusFilter?'&status='.$statusFilter:''?>" class="btn btn-sm <?=$typeFilter===$t?'btn-primary':'btn-ghost'?>"><?=htmlspecialchars($t)?></a>
      <?php endforeach; ?>
      <span style="width:10px"></span>
      <a href="?status=published<?=$typeFilter?'&type='.$typeFilter:''?><?=$catFilter?'&cat='.$catFilter:''?>" class="btn btn-sm <?=$statusFilter==='published'?'btn-primary':'btn-ghost'?>">已发布</a>
      <a href="?status=pending<?=$typeFilter?'&type='.$typeFilter:''?><?=$catFilter?'&cat='.$catFilter:''?>" class="btn btn-sm <?=$statusFilter==='pending'?'btn-primary':'btn-ghost'?>">待审核</a>
      <a href="?status=draft<?=$typeFilter?'&type='.$typeFilter:''?><?=$catFilter?'&cat='.$catFilter:''?>" class="btn btn-sm <?=$statusFilter==='draft'?'btn-primary':'btn-ghost'?>">草稿</a>
      <?php if (!empty($courseCats)): ?>
      <span style="width:10px"></span>
      <?php foreach ($courseCats as $c): ?>
      <a href="?cat=<?=urlencode($c['key'])?><?=$typeFilter?'&type='.$typeFilter:''?><?=$statusFilter?'&status='.$statusFilter:''?>" class="btn btn-sm <?=$catFilter===$c['key']?'btn-primary':'btn-ghost'?>"><?=htmlspecialchars($c['name'])?></a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <?php if (empty($display)): ?>
      <div class="empty">
        <div style="font-size:48px;margin-bottom:12px">📚</div>
        <p>暂无课程</p>
        <a href="course-edit.php" class="btn btn-primary" style="margin-top:12px">创建第一门课程</a>
      </div>
      <?php else: ?>
      <table>
        <thead><tr><th>课程名称</th><th>类型</th><th>价格</th><th>讲师</th><th>难度</th><th>学员</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($display as $c): ?>
          <tr>
            <td style="max-width:220px"><strong><?=htmlspecialchars($c['title'])?></strong>
              <?php if (!empty($c['tags'])): ?><div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars(implode('、', array_slice($c['tags'],0,3)))?></div><?php endif; ?>
            </td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($c['type'] ?? '课程')?></span></td>
            <td><strong><?=!empty($c['price']) && $c['price']>0 ? '¥'.number_format($c['price']) : '免费'?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['instructor'] ?? '') ?: '—'?></td>
            <td class="text-sm text-muted"><?=['beginner'=>'入门','intermediate'=>'进阶','advanced'=>'高级'][$c['difficulty'] ?? 'beginner']?></td>
            <td><?=$c['students'] ?? 0?></td>
            <td><span class="badge <?=($c['status']??'draft')==='published'?'badge-green':(($c['status']??'')==='pending'?'badge-yellow':(($c['status']??'')==='rejected'?'badge-red':'badge-gray'))?>"><?=['published'=>'已发布','pending'=>'待审核','rejected'=>'已拒绝','draft'=>'草稿'][$c['status']??'draft']?></span></td>
            <td style="white-space:nowrap">
              <?php if (($c['status'] ?? '') === 'pending'): ?>
              <a href="courses.php?approve=<?=urlencode($c['id'])?>" class="btn btn-success btn-sm">通过上架</a>
              <a href="courses.php?reject=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" data-confirm="确认拒绝该课程?">拒绝</a>
              <?php endif; ?>
              <a href="course-edit.php?id=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="../content-preview.php?type=course&id=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a><a href="courses.php?delete=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" data-confirm="确认删除?">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>课程体系规划</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
        <div style="padding:16px;background:var(--surface-2);border-radius:12px"><strong>📖 单课</strong><p class="text-sm text-muted">独立课程，包含多个课时</p></div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px"><strong>📦 专栏</strong><p class="text-sm text-muted">多门课打包，统一售卖/学习</p></div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px"><strong>🎯 认证课</strong><p class="text-sm text-muted">完成后颁发证书</p></div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px"><strong>📊 系列课</strong><p class="text-sm text-muted">按主题编排的学习路径</p></div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
