<?php
declare(strict_types=1);
/**
 * 多维表格视图 —— /xmp/table-views?type=&view=grid|board|calendar|gantt
 *
 * 同一批记录四种看法：表格看全貌、看板看进度、日历看排期、甘特看跨度。
 * 布局计算在 lib/TableView.php（可单测），这里只负责展示。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CptSystem.php';
require_once __DIR__ . '/../lib/TableView.php';
require_login();
require_perm('cpt');

$types = cpt_types();
$curType = preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['type'] ?? ''));
if ($curType === '' || cpt_type($curType) === null) $curType = (string) ($types[0]['slug'] ?? '');
$type = $curType !== '' ? cpt_type($curType) : null;
$view = (string) ($_GET['view'] ?? 'grid');
if (!isset(tv_views()[$view])) $view = 'grid';
$ym = (string) ($_GET['m'] ?? date('Y-m'));
if (preg_match('/^\d{4}-\d{2}$/', $ym) !== 1) $ym = date('Y-m');
$dateKey = preg_replace('/[^a-z0-9_]/', '', (string) ($_GET['d'] ?? ''));

$rows = $type !== null ? tv_rows($curType) : [];
$q = static fn(array $extra = []): string => '/xmp/table-views?' . http_build_query(array_merge(['type' => $curType, 'view' => $view], $extra));

admin_header('多维表格视图');
?>
<div style="max-width:1180px">
  <h1 style="margin:0 0 4px">🗂 多维表格视图</h1>
  <p class="sub">同一批记录，四种看法：表格看全貌、看板看进度、日历看排期、甘特看时间跨度。</p>

  <?php if ($types === []): ?>
    <div class="empty">还没有自定义内容类型。先到 <a href="/xmp/cpt">自定义内容类型</a> 建一个。</div>
  <?php else: ?>
  <div class="tv-bar">
    <?php foreach ($types as $t): $slug = (string) ($t['slug'] ?? ''); $n = count(cpt_entries($slug)); ?>
    <a href="/xmp/table-views?type=<?=urlencode($slug)?>&view=<?=$view?>" class="btn btn-s btn-sm<?=$slug === $curType ? ' btn-p' : ''?>"><?=htmlspecialchars((string) ($t['icon'] ?? '📄'))?> <?=htmlspecialchars((string) ($t['name'] ?? $slug))?> <span class="note"><?=$n?></span></a>
    <?php endforeach; ?>
    <span class="note" style="margin-left:auto">共 <b><?=count($rows)?></b> 条</span>
  </div>
  <div class="tv-bar">
    <?php foreach (tv_views() as $vk => $vl): ?>
    <a href="/xmp/table-views?type=<?=urlencode($curType)?>&view=<?=$vk?>" class="btn btn-s btn-sm<?=$vk === $view ? ' btn-p' : ''?>"><?=htmlspecialchars($vl)?></a>
    <?php endforeach; ?>
    <?php if ($view === 'calendar'): ?>
    <a href="<?=htmlspecialchars($q(['m' => tv_month_shift($ym, -1)]))?>" class="btn btn-s btn-sm">← 上月</a>
    <b style="font-size:13px"><?=htmlspecialchars($ym)?></b>
    <a href="<?=htmlspecialchars($q(['m' => tv_month_shift($ym, 1)]))?>" class="btn btn-s btn-sm">下月 →</a>
    <a href="<?=htmlspecialchars($q(['m' => date('Y-m')]))?>" class="btn btn-s btn-sm">回到本月</a>
    <?php endif; ?>
    <a href="/xmp/cpt?type=<?=urlencode($curType)?>" class="btn btn-s btn-sm" style="margin-left:auto">编辑字段与条目 →</a>
  </div>

  <?php if ($type === null || $rows === []): ?>
    <div class="empty">这个类型还没有条目。</div>
  <?php elseif ($view === 'grid'): ?>
    <table class="tv-grid">
      <thead><tr>
        <th>标题</th>
        <?php foreach ((array) ($type['fields'] ?? []) as $f): ?><th><?=htmlspecialchars((string) ($f['label'] ?? ''))?></th><?php endforeach; ?>
        <th>发布状态</th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="/xmp/cpt?type=<?=urlencode($curType)?>&edit=<?=urlencode($r['id'])?>"><?=htmlspecialchars($r['title'])?></a></td>
          <?php foreach ((array) ($type['fields'] ?? []) as $f): $k = (string) ($f['key'] ?? ''); ?>
          <td><?=htmlspecialchars((string) ($r['cells'][$k] ?? ''))?></td>
          <?php endforeach; ?>
          <td><?=$r['status'] === 'published' ? '已发布' : '草稿'?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($view === 'board'): $cols = tv_board_columns($curType); $gf = tv_group_field($type); ?>
    <?php if ($gf === null): ?><p class="note" style="font-size:12px;color:var(--muted)">这个类型没有「下拉选择」字段，看板按发布状态分列。加一个下拉字段就能按自己的阶段分组。</p><?php endif; ?>
    <div class="tv-kb">
      <?php foreach ($cols as $c): ?>
      <div class="tv-kb-col">
        <div class="tv-kb-h"><b><?=htmlspecialchars((string) $c['label'])?></b><span><?=count($c['rows'])?></span></div>
        <?php foreach ($c['rows'] as $r): ?>
        <div class="tv-kb-card">
          <b><?=htmlspecialchars($r['title'])?></b>
          <?php $bits = [];
          foreach ((array) ($type['fields'] ?? []) as $f) {
              $k = (string) ($f['key'] ?? '');
              if ($gf !== null && $k === (string) ($gf['key'] ?? '')) continue;   // 分组字段不必重复显示
              $v = (string) ($r['cells'][$k] ?? '');
              if ($v !== '') $bits[] = (string) ($f['label'] ?? $k) . '：' . $v;
          } ?>
          <?php if ($bits): ?><p><?=htmlspecialchars(implode(' · ', $bits))?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($view === 'calendar'): $cal = tv_calendar($curType, $ym, $dateKey); $df = $dateKey !== '' ? $dateKey : tv_default_date_field($type); ?>
    <div class="tv-bar">
      <span class="note">按「<?=htmlspecialchars($df !== '' ? $df : '（无日期字段）')?>」排期</span>
      <?php foreach (tv_date_fields($type) as $dk): ?>
      <a href="<?=htmlspecialchars($q(['d' => $dk]))?>" class="btn btn-s btn-sm<?=$dk === $df ? ' btn-p' : ''?>"><?=htmlspecialchars($dk)?></a>
      <?php endforeach; ?>
      <?php if ($df === ''): ?><span class="note">给类型加一个「日期」字段后，这里就会按天排布。</span><?php endif; ?>
    </div>
    <div class="tv-cal">
      <?php foreach (['一', '二', '三', '四', '五', '六', '日'] as $d): ?><div class="tv-cal-head"><?=$d?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $cal['lead']; $i++): ?><div class="tv-cal-cell empty"></div><?php endfor; ?>
      <?php foreach ($cal['cells'] as $c): ?>
      <div class="tv-cal-cell<?=$c['date'] === date('Y-m-d') ? ' today' : ''?>">
        <div class="tv-cal-day"><?=$c['day']?></div>
        <?php foreach ($c['rows'] as $r): ?>
        <a class="tv-cal-ev" style="display:block;text-decoration:none;color:inherit" href="/xmp/cpt?type=<?=urlencode($curType)?>&edit=<?=urlencode($r['id'])?>"><?=htmlspecialchars($r['title'])?></a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($cal['undated']): ?>
    <div style="margin-top:14px"><b style="font-size:13px">未排期（<?=count($cal['undated'])?>）</b>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
        <?php foreach ($cal['undated'] as $r): ?><a class="tv-cal-ev" style="text-decoration:none;color:inherit" href="/xmp/cpt?type=<?=urlencode($curType)?>&edit=<?=urlencode($r['id'])?>"><?=htmlspecialchars($r['title'])?></a><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <?php else: $g = tv_gantt($curType); $dkeys = tv_date_fields($type); $todayPct = tv_gantt_today($g); ?>
    <?php if ($g['bars'] === []): ?>
      <div class="empty">甘特需要日期字段（第 1 个当开始、第 2 个当结束；只有一个就按里程碑画）。当前类型有 <?=count($dkeys)?> 个日期字段。</div>
    <?php else: ?>
      <div class="tv-bar">
        <span class="note"><?=htmlspecialchars($g['start'])?> → <?=htmlspecialchars($g['end'])?>（共 <?=$g['days']?> 天）· 起止字段：<?=htmlspecialchars(implode(' / ', array_slice($dkeys, 0, 2)))?></span>
      </div>
      <div class="tv-gantt">
        <div class="tv-gantt-scale"><div></div><div class="ticks"><span><?=htmlspecialchars($g['start'])?></span><span><?=htmlspecialchars($g['end'])?></span></div></div>
        <?php foreach ($g['bars'] as $b): ?>
        <div class="tv-gantt-row">
          <div class="tv-gantt-label"><?=htmlspecialchars($b['title'])?><div class="text-xs text-muted"><?=htmlspecialchars($b['start'])?><?=$b['milestone'] ? '' : ' → ' . htmlspecialchars($b['end'])?></div></div>
          <div class="tv-gantt-track">
            <div class="tv-gantt-bar<?=$b['milestone'] ? ' ms' : ''?>" style="left:<?=round($b['offset'] / max(1, $g['days']) * 100, 3)?>%;width:<?=round($b['span'] / max(1, $g['days']) * 100, 3)?>" title="<?=htmlspecialchars($b['start'] . ' → ' . $b['end'])?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if ($todayPct !== null): ?><div class="tv-gantt-today" style="left:calc(200px + (100% - 200px) * <?=round($todayPct / 100, 4)?>)"></div><?php endif; ?>
      </div>
      <p class="text-xs text-muted" style="margin-top:10px">条的位置=开始、长度=持续天数；红色竖线是今天。<?=$dkeys === [] ? '' : '想换字段？把日期字段的顺序调一下即可。'?></p>
    <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
