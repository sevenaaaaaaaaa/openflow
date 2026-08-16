<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DataExport.php';
require_login();
require_perm('export');

// 处理导出下载
if (isset($_GET['download'])) {
    $type = $_GET['download'];
    $format = $_GET['format'] ?? 'csv';
    DataExport::downloadExport($type, $format);
}

// 处理导入
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'import_csv' && !empty($_FILES['csv_file']['tmp_name'])) {
        $content = file_get_contents($_FILES['csv_file']['tmp_name']);
        $type = $_POST['import_type'] ?? 'articles';
        if ($type === 'articles') {
            $result = DataExport::importArticlesFromCsv($content);
        }
        $message = $result ? "导入完成：{$result['imported']} 条成功，{$result['skipped']} 条跳过" : '导入失败';
    }

    if ($action === 'import_json' && !empty($_FILES['json_file']['tmp_name'])) {
        $content = file_get_contents($_FILES['json_file']['tmp_name']);
        $type = $_POST['import_type'] ?? 'articles';
        $result = DataExport::importFromJson($content, $type);
        $message = isset($result['imported']) ? "导入完成：{$result['imported']} 条成功" : ($result['error'] ?? '导入失败');
    }

    header('Location: data-export.php?msg=' . urlencode($message));
    exit;
}

if (!empty($_GET['msg'])) $message = $_GET['msg'];

// 统计数据
$articleCount = count(json_read(ARTICLES_DIR . '/index.json'));
$memberCount = count(json_read(DATA_DIR . '/members.json'));
$courseCount = count(json_read(DATA_DIR . '/courses.json'));
$leadCount = count(json_read(DATA_DIR . '/leads.csv'));
$profileCount = count(json_read(DATA_DIR . '/cdp/profiles.json'));

admin_header('数据导入/导出');
?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <h1>数据导入/导出</h1>
    <p class="sub">支持 CSV / JSON 格式的数据导入和导出</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 导出区 -->
    <div class="card">
      <h2>导出数据</h2>
      <p class="text-sm text-muted mb-4">选择要导出的数据类型和格式</p>
      <table>
        <thead><tr><th>数据类型</th><th>记录数</th><th>操作</th></tr></thead>
        <tbody>
          <tr>
            <td style="font-weight:600">文章</td>
            <td><?=$articleCount?> 篇</td>
            <td>
              <a href="?download=articles&format=csv" class="btn btn-sm btn-ghost">下载 CSV</a>
              <a href="?download=articles&format=json" class="btn btn-sm btn-ghost">下载 JSON</a>
            </td>
          </tr>
          <tr>
            <td style="font-weight:600">会员</td>
            <td><?=$memberCount?> 人</td>
            <td>
              <a href="?download=members&format=csv" class="btn btn-sm btn-ghost">下载 CSV</a>
              <a href="?download=members&format=json" class="btn btn-sm btn-ghost">下载 JSON</a>
            </td>
          </tr>
          <tr>
            <td style="font-weight:600">课程</td>
            <td><?=$courseCount?> 门</td>
            <td>
              <a href="?download=courses&format=csv" class="btn btn-sm btn-ghost">下载 CSV</a>
              <a href="?download=courses&format=json" class="btn btn-sm btn-ghost">下载 JSON</a>
            </td>
          </tr>
          <tr>
            <td style="font-weight:600">线索</td>
            <td><?=$leadCount?> 条</td>
            <td>
              <a href="?download=leads&format=csv" class="btn btn-sm btn-ghost">下载 CSV</a>
              <a href="?download=leads&format=json" class="btn btn-sm btn-ghost">下载 JSON</a>
            </td>
          </tr>
          <tr>
            <td style="font-weight:600">CDP 用户画像</td>
            <td><?=$profileCount?> 个</td>
            <td>
              <a href="?download=cdp_profiles&format=csv" class="btn btn-sm btn-ghost">下载 CSV</a>
              <a href="?download=cdp_profiles&format=json" class="btn btn-sm btn-ghost">下载 JSON</a>
            </td>
          </tr>
          <tr>
            <td style="font-weight:600">全量备份</td>
            <td style="color:var(--muted)">所有数据</td>
            <td>
              <a href="?download=all" class="btn btn-sm btn-primary">下载全量 JSON</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 导入区 -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
      <div class="card">
        <h2>导入 CSV</h2>
        <form method="post" enctype="multipart/form-data">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="import_csv">
          <div class="field"><label>数据类型</label>
            <select name="import_type" class="inp">
              <option value="articles">文章</option>
            </select>
          </div>
          <div class="field"><label>选择 CSV 文件</label><input type="file" name="csv_file" accept=".csv" required style="font-size:13px"></div>
          <button type="submit" class="btn btn-primary">导入 CSV</button>
        </form>
      </div>
      <div class="card">
        <h2>导入 JSON</h2>
        <form method="post" enctype="multipart/form-data">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="import_json">
          <div class="field"><label>数据类型</label>
            <select name="import_type" class="inp">
              <option value="articles">文章</option>
              <option value="members">会员</option>
            </select>
          </div>
          <div class="field"><label>选择 JSON 文件</label><input type="file" name="json_file" accept=".json" required style="font-size:13px"></div>
          <button type="submit" class="btn btn-primary">导入 JSON</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
