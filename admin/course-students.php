<?php
/**
 * 课程学员进度 — 某课程的学员名单 + 学习进度 + 课程数据分析（完课率/课时流失）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CourseSystem.php';
require_once __DIR__ . '/../lib/ProgressSystem.php';
require_login();
require_perm('courses');

$courses = json_read(DATA_DIR . '/courses/index.json');
$courseId = (string)($_GET['course'] ?? ($courses[0]['id'] ?? ''));
$course = $courseId ? course_find($courseId) : null;

// 学员：购买过该课的会员 + 有进度记录的会员
$students = [];
try {
    if (!function_exists('shop_all_orders')) require_once __DIR__ . '/../lib/ShopSystem.php';
    foreach (shop_all_orders() as $o) {
        if (($o['course_id'] ?? '') !== $courseId || ($o['status'] ?? '') !== 'paid') continue;
        $mid = (string)($o['member_id'] ?? '');
        if ($mid === '') continue;
        $students[$mid] = ['member_id' => $mid, 'name' => (string)($o['email'] ?? $mid), 'paid_at' => (string)($o['paid_at'] ?? '')];
    }
} catch (\Throwable $e) {}
foreach (progress_all() as $mid => $byCourse) {
    if (!isset($byCourse[$courseId])) continue;
    if (!isset($students[$mid])) $students[$mid] = ['member_id' => $mid, 'name' => (string)$mid, 'paid_at' => ''];
}
// 补名字
try {
    if (!function_exists('member_get')) require_once __DIR__ . '/../lib/MemberSystem.php';
    foreach ($students as $mid => &$s) { $m = member_get($mid); if ($m) { $s['name'] = (string)($m['name'] ?? $s['name']); if (!empty($m['email'])) $s['email'] = $m['email']; } }
    unset($s);
} catch (\Throwable $e) {}

// 进度
$rows = [];
foreach ($students as $mid => $s) {
    $sum = $course ? progress_summary($mid, $courseId, $course) : ['done' => 0, 'total' => 0, 'percent' => 0];
    $pg = progress_get($mid, $courseId);
    $last = ''; foreach ($pg as $st) if (($st['last_at'] ?? '') > $last) $last = (string)$st['last_at'];
    $rows[] = $s + ['done' => $sum['done'], 'total' => $sum['total'], 'percent' => $sum['percent'], 'last_at' => $last, 'completed' => ($sum['total'] > 0 && $sum['done'] >= $sum['total'])];
}
usort($rows, fn($a, $b) => $b['percent'] <=> $a['percent']);

$analytics = $course ? course_analytics($courseId) : [];
$certInfo = ($course && (!empty($course['certificate']) || ($course['type'] ?? '') === '认证课'));

admin_header('课程学员');
?>
<div class="admin-layout">
  <?php admin_sidebar('course-students'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>课程学员与数据</h1><p class="v-sub">看谁在学、学到哪、有没有学完——课程维度的完课率与课时流失。</p></div>
      <div class="v-actions"><a class="btn btn-s btn-sm" href="/xmp/courses">← 课程管理</a></div>
    </div>

    <form method="get" style="margin-bottom:14px;display:flex;gap:8px">
      <select name="course" onchange="this.form.submit()" style="min-width:260px">
        <?php foreach ($courses as $c): ?><option value="<?=htmlspecialchars($c['id'])?>" <?=$c['id'] === $courseId ? 'selected' : ''?>><?=htmlspecialchars($c['title'])?></option><?php endforeach; ?>
      </select>
      <?php if ($course): ?><a class="btn btn-ghost btn-sm" href="/xmp/course-edit?id=<?=urlencode($courseId)?>">编辑课程</a><?php endif; ?>
    </form>

    <?php if (!$course): ?>
    <div class="card"><div class="empty" style="padding:24px">还没有课程。</div></div>
    <?php else: ?>
    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="kpi"><div class="k-label">营收</div><div class="k-val mono">¥<?=number_format((float)($analytics['revenue'] ?? 0), 0)?></div><div class="k-sub"><?=$analytics['orders'] ?? 0?> 单</div></div>
      <div class="kpi"><div class="k-label">学员</div><div class="k-val mono"><?=count($rows)?></div><div class="k-sub">购买 <?=$analytics['buyers'] ?? 0?> 人</div></div>
      <div class="kpi"><div class="k-label">完课率</div><div class="k-val mono"><?=$analytics['completion_rate'] ?? 0?>%</div><div class="k-sub"><?=$analytics['completed'] ?? 0?> 人学完</div></div>
      <div class="kpi"><div class="k-label">平均进度</div><div class="k-val mono"><?=$analytics['avg_progress'] ?? 0?>%</div><div class="k-sub"><?=$analytics['lessons_total'] ?? 0?> 节</div></div>
    </div>

    <div class="panels" style="display:grid;grid-template-columns:1.4fr 1fr;gap:16px;align-items:start">
      <div class="card" style="padding:0;overflow:auto">
        <table>
          <thead><tr><th>学员</th><th>进度</th><th>完成</th><th>最近学习</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="4" class="empty" style="padding:20px">暂无学员</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?=htmlspecialchars($r['name'])?></b><?php if (!empty($r['email'])): ?><div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($r['email'])?></div><?php endif; ?></td>
              <td style="min-width:140px"><div style="background:var(--hover);border-radius:99px;height:8px;overflow:hidden"><div style="height:100%;width:<?=(int)$r['percent']?>%;background:var(--accent)"></div></div><span class="text-sm text-muted"><?=$r['done']?>/<?=$r['total']?> · <?=$r['percent']?>%</span></td>
              <td><?=$r['completed'] ? '<span class="badge badge-green">已学完' . ($certInfo ? ' · 有证书' : '') . '</span>' : '<span class="text-sm text-muted">进行中</span>'?></td>
              <td class="text-sm text-muted"><?=htmlspecialchars(substr($r['last_at'], 0, 16) ?: '—')?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2 style="font-size:15px">📉 课时流失</h2>
        <p class="text-sm text-muted" style="margin:0 0 10px">每节完成人数——骤降处就是流失点。</p>
        <?php foreach (($analytics['lessons'] ?? []) as $i => $l): ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;padding:4px 0">
          <span style="width:20px;color:var(--faint)" class="mono"><?=$i + 1?></span>
          <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($l['title'])?></span>
          <div style="width:90px;background:var(--hover);border-radius:99px;height:7px;overflow:hidden"><div style="height:100%;width:<?=count($rows) > 0 ? min(100, (int)round($l['done'] / max(1, count($rows)) * 100)) : 0?>%;background:var(--ok)"></div></div>
          <span class="mono" style="width:34px;text-align:right"><?=$l['done']?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
