<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('submissions');

$subsFile = DATA_DIR . '/submissions/index.json';
$submissions = json_read($subsFile);
$forms = json_read(DATA_DIR . '/forms/index.json');

// Build form lookup
$formMap = [];
foreach ($forms as $f) $formMap[$f['id']] = $f;

// Filter
$formFilter = $_GET['form_id'] ?? '';
if ($formFilter) $submissions = array_values(array_filter($submissions, fn($s) => ($s['form_id'] ?? '') === $formFilter));

// Sort newest first
usort($submissions, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$allSubmissions = $submissions;
$pag = paginate($submissions, $page, 100);
$submissions = $pag['items'];

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="submissions-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    if (!empty($allSubmissions)) {
        $headers = ['时间', '表单', '表单类型'];
        // Collect all possible field keys
        $allKeys = [];
        foreach ($allSubmissions as $s) {
            if (isset($s['data']) && is_array($s['data'])) $allKeys = array_merge($allKeys, array_keys($s['data']));
        }
        $allKeys = array_unique($allKeys);
        $rows = array_merge($headers, $allKeys);
        fputcsv(fopen('php://output', 'w'), $rows);
        foreach ($allSubmissions as $s) {
            $row = [$s['created_at'] ?? '', $formMap[$s['form_id']]['title'] ?? $s['form_id'], $formMap[$s['form_id']]['type'] ?? ''];
            foreach ($allKeys as $k) $row[] = $s['data'][$k] ?? '';
            fputcsv(fopen('php://output', 'w'), $row);
        }
    }
    exit;
}

admin_header('提交记录');
?>
<div class="admin-layout">
  <?php admin_sidebar('submissions'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">提交记录</h1>
      <a href="?export=1&form_id=<?=urlencode($formFilter)?>" class="btn btn-ghost btn-sm ml-auto">导出 CSV</a>
    </div>
    <p class="sub">全站表单提交数据 · 共 <?=count($submissions)?> 条记录</p>

    <div class="flex gap-3 mb-4">
      <select onchange="location.href='?form_id='+this.value" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--surface)">
        <option value="">全部表单</option>
        <?php foreach ($forms as $f): ?>
        <option value="<?=htmlspecialchars($f['id'])?>" <?=$formFilter===$f['id']?'selected':''?>><?=htmlspecialchars($f['title'])?> (<?=htmlspecialchars($f['type'])?>)</option>
        <?php endforeach; ?>
      </select>
      <span class="text-sm text-muted" style="align-self:center"><?=count($submissions)?> 条</span>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <?php if (empty($submissions)): ?>
      <div class="empty" style="padding:48px">
        <div style="font-size:40px;margin-bottom:12px">📋</div>
        <p>暂无提交记录</p>
      </div>
      <?php else: ?>
      <table>
        <thead><tr><th>时间</th><th>表单</th><th>类型</th><th>提交数据</th></tr></thead>
        <tbody>
          <?php foreach ($submissions as $s):
            $form = $formMap[$s['form_id']] ?? null;
          ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars(substr($s['created_at'] ?? '', 0, 16))?></td>
            <td><strong><?=htmlspecialchars($form['title'] ?? $s['form_id'])?></strong></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars(['lead'=>'预约','download'=>'下载','newsletter'=>'订阅'][$form['type'] ?? ''] ?? $form['type'] ?? '')?></span></td>
            <td style="max-width:400px">
              <div style="display:flex;flex-wrap:wrap;gap:4px">
                <?php foreach (($s['data'] ?? []) as $k => $v): ?>
                <span class="tag-item" style="font-size:12px"><strong><?=htmlspecialchars($k)?>:</strong> <?=htmlspecialchars(mb_substr($v, 0, 30))?></span>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?=pagination_html($pag, 'submissions.php?form_id=' . urlencode($formFilter))?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
