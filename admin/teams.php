<?php
declare(strict_types=1);
/**
 * Teams+ —— 团队协作入口（/xmp/teams）
 *
 * 【为什么单开一个入口】项目 / 任务 / 多维表格 / 成员权限散在「今日主线」和「内容结构」两处，
 * 用的人记不住路径。这里把它们收成一个入口，放在顶栏右侧图标区（Teams+），
 * 不跟侧栏那些内容模块混在一起。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ProjectSystem.php';
require_once __DIR__ . '/../lib/TableView.php';
require_once __DIR__ . '/../lib/CptSystem.php';
require_login();
require_perm('tasks');

$siteAdmin = ps_is_site_admin();
$myUser = ps_current_user();
$projects = ps_visible_projects($myUser, $siteAdmin);
$due = ps_due_buckets();
$dueTop = array_slice(array_merge($due['overdue'], $due['today']), 0, 6);
$cptTypes = function_exists('cpt_types') ? cpt_types() : [];

// 每个项目的骨架数据（成员 / 任务统计 / 进度）
$rows = [];
foreach ($projects as $p) {
    $pid = ps_safe_id((string) ($p['id'] ?? ''));
    if ($pid === '') continue;
    $st = ps_stats($pid);
    $members = ps_project_members($pid);
    $done = (int) ($st['by_status']['done'] ?? 0);
    $total = max(1, (int) $st['total']);
    $rows[] = [
        'id' => $pid,
        'name' => (string) ($p['name'] ?? $pid),
        'desc' => (string) ($p['desc'] ?? ''),
        'members' => $members,
        'stats' => $st,
        'pct' => (int) round($done / $total * 100),
        'can_edit' => ps_can($pid, 'edit', $myUser, $siteAdmin),
        'can_manage' => ps_can($pid, 'manage', $myUser, $siteAdmin),
        'my_role' => ps_project_role($pid, $myUser),
    ];
}

admin_header('Teams+');
?>
<div style="max-width:1160px">
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;margin-bottom:6px">
    <div style="flex:1;min-width:280px">
      <h1 style="margin:0 0 4px">Teams+</h1>
      <p class="sub" style="margin:0">团队协作与多维表格：项目、任务、看板 / 表格 / 日历 / 甘特 / 树，成员权限，到期提醒。</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="/xmp/today" class="btn btn-s btn-sm">我的主线</a>
      <a href="/xmp/table-views" class="btn btn-s btn-sm">多维表格视图</a>
      <a href="/xmp/cpt" class="btn btn-s btn-sm">自定义内容类型</a>
      <a href="/xmp/today?view=team" class="btn btn-p btn-sm">进入团队视角</a>
    </div>
  </div>

  <?php if ($dueTop !== []): ?>
  <div class="panel" style="margin-top:14px;border-left:3px solid var(--danger,#dc2626)">
    <div class="p-body">
      <b style="font-size:13px">任务到期 · 逾期 <?=$due['counts']['overdue']?> · 今天 <?=$due['counts']['today']?> · 近 7 天 <?=$due['counts']['soon']?></b>
      <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
        <?php foreach ($dueTop as $d): $t = (array) $d['task']; $od = substr((string) ($t['due'] ?? ''), 0, 10) < date('Y-m-d'); ?>
        <div style="display:flex;gap:8px;align-items:center;font-size:12.5px;flex-wrap:wrap">
          <span class="pill<?=$od ? ' hl' : ''?>"><?=$od ? '逾期' : '今天'?></span>
          <b><?=htmlspecialchars((string) ($t['title'] ?? ''))?></b>
          <span class="text-muted">· <?=htmlspecialchars((string) $d['project_name'])?></span>
          <?php if ((string) ($t['assignee'] ?? '') !== ''): ?><span class="text-muted">· @<?=htmlspecialchars((string) $t['assignee'])?></span><?php endif; ?>
          <a href="/xmp/today?view=team&project=<?=urlencode((string) $d['project'])?>" style="margin-left:auto">去处理 →</a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($due['counts']['overdue'] + $due['counts']['today'] > count($dueTop)): ?>
      <p class="text-xs text-muted" style="margin:8px 0 0">还有更多，见团队视角。</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <h2 style="font-size:14px;margin:22px 0 10px">项目 <span class="text-muted" style="font-weight:400">（我可见的 <?=count($rows)?> 个）</span></h2>
  <?php if ($rows === []): ?>
    <div class="empty">你还没有可见的项目。到 <a href="/xmp/today?view=team">团队视角</a> 新建一个，你就是它的负责人。</div>
  <?php else: ?>
  <div class="teams-grid">
    <?php foreach ($rows as $r): ?>
    <div class="teams-card">
      <div class="teams-card-h">
        <b><?=htmlspecialchars($r['name'])?></b>
        <?php if ($r['my_role'] !== ''): ?><span class="pill"><?=htmlspecialchars(ps_project_roles()[$r['my_role']] ?? $r['my_role'])?></span><?php endif; ?>
        <?php if (!$r['can_edit']): ?><span class="pill hl">只读</span><?php endif; ?>
      </div>
      <?php if ($r['desc'] !== ''): ?><p class="teams-desc"><?=htmlspecialchars(mb_substr($r['desc'], 0, 80))?></p><?php endif; ?>
      <div class="teams-meta">
        <span>任务 <b><?=(int) $r['stats']['total']?></b><?=((int) ($r['stats']['roots'] ?? 0)) !== (int) $r['stats']['total'] ? '（顶层 ' . (int) $r['stats']['roots'] . '）' : ''?></span>
        <span>逾期 <b class="<?=((int) $r['stats']['overdue']) > 0 ? 'over' : ''?>"><?=(int) $r['stats']['overdue']?></b></span>
        <span>已完成 <b><?=(int) ($r['stats']['by_status']['done'] ?? 0)?></b></span>
      </div>
      <div class="teams-prog"><span style="width:<?=$r['pct']?>%"></span></div>
      <div class="teams-members">
        <?php if ($r['members'] === []): ?>
        <span class="text-muted" style="font-size:11.5px">未设成员（公共项目）</span>
        <?php else: foreach ($r['members'] as $mu => $mr): ?>
        <span class="pill" title="<?=htmlspecialchars((string) $mu)?>"><?=htmlspecialchars(ps_user_display((string) $mu))?> · <?=htmlspecialchars(ps_project_roles()[$mr] ?? $mr)?></span>
        <?php endforeach; endif; ?>
      </div>
      <div class="teams-acts">
        <?php foreach (tv_views() as $vk => $vl): ?>
        <a href="/xmp/today?view=team&project=<?=urlencode($r['id'])?>&vt=<?=$vk?>" class="btn btn-s btn-sm"><?=htmlspecialchars($vl)?></a>
        <?php endforeach; ?>
        <?php if ($r['can_manage']): ?><a href="/xmp/today?view=team&project=<?=urlencode($r['id'])?>" class="btn btn-s btn-sm">成员与设置</a><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 style="font-size:14px;margin:24px 0 10px">多维表格</h2>
  <div class="teams-grid">
    <div class="teams-card">
      <div class="teams-card-h"><b>视图</b><span class="pill"><?=count(tv_views())?> 种</span></div>
      <p class="teams-desc">同一批记录五种看法：表格看全貌、看板看进度、日历看排期、甘特看时间跨度、树看层级。</p>
      <div class="teams-acts">
        <?php foreach (tv_views() as $vk => $vl): ?>
        <a href="/xmp/table-views?view=<?=$vk?>" class="btn btn-s btn-sm"><?=htmlspecialchars($vl)?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="teams-card">
      <div class="teams-card-h"><b>内容类型</b><span class="pill"><?=count($cptTypes)?> 个</span></div>
      <p class="teams-desc">自定义类型 + 关联 / 汇总 / 查值字段 + 层级（自关联即层级）。这是多维表格的底座。</p>
      <div class="teams-acts">
        <a href="/xmp/cpt" class="btn btn-s btn-sm">管理类型与记录</a>
        <?php if ($cptTypes !== []): ?><a href="/xmp/table-views" class="btn btn-s btn-sm">看记录</a><?php endif; ?>
      </div>
    </div>
  </div>

  <h2 style="font-size:14px;margin:24px 0 10px">接下来要做</h2>
  <div class="panel"><div class="p-body">
    <ul style="margin:0;padding-left:18px;font-size:12.5px;line-height:1.9;color:var(--muted)">
      <li>跨项目汇总视图（一次看所有项目的任务，同一套多视图）—— 下一步就做</li>
      <li>视图级公开只读分享（把某个视图给外部看）</li>
      <li>任务重复规则（每周 / 每月自动生成）</li>
    </ul>
  </div></div>
</div>
<?php admin_footer(); ?>
