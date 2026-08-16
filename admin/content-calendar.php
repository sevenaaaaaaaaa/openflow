<?php
/**
 * 内容日历 — 拖拽调整发布日期/定时发布/活动时间段
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$articles = get_articles();
$events = json_read(DATA_DIR . '/events/index.json');
$downloads = json_read(DATA_DIR . '/downloads.json');

// 收集日历事件（含 demo 数据的显示）
$calendarItems = [];

// 文章：用 created_at（无 publish_at 则用 created_at），标记是否定时
foreach ($articles as $a) {
    if (($a['status'] ?? 'draft') !== 'published' && empty($a['publish_at'])) continue;
    $date = ($a['publish_at'] ?? '') ?: ($a['created_at'] ?? '');
    $calendarItems[] = [
        'id' => $a['id'],
        'type' => 'article',
        'title' => $a['title'] ?? '',
        'date' => substr($date, 0, 10),
        'scheduled' => !empty($a['publish_at']),
        'color' => !empty($a['publish_at']) ? 'var(--warn)' : '#2e6b4f',
    ];
}
// 活动：开始/结束日期
foreach ($events as $e) {
    if (($e['status'] ?? 'draft') !== 'published') continue;
    $sDate = substr($e['start_date'] ?? '', 0, 10);
    $eDate = substr($e['end_date'] ?? '', 0, 10);
    if (empty($eDate)) $eDate = $sDate; // 未设结束日则视为当天
    $calendarItems[] = [
        'id' => $e['id'],
        'type' => 'event',
        'title' => $e['title'] ?? '',
        'date' => $sDate,
        'end_date' => $eDate,
        'scheduled' => false,
        'color' => '#7c3aed',
    ];
}
// 资料下载
foreach ($downloads as $d) {
    if (($d['status'] ?? 'draft') !== 'published') continue;
    $calendarItems[] = [
        'id' => $d['id'],
        'type' => 'download',
        'title' => $d['title'] ?? '',
        'date' => substr($d['created_at'] ?? '', 0, 10),
        'scheduled' => false,
        'color' => 'var(--accent)',
    ];
}

// 按日期排序
usort($calendarItems, fn($a, $b) => strcmp($a['date'], $b['date']));

admin_header('内容日历');
?>
<style>
.cal-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.cal-nav{display:flex;gap:6px;align-items:center}
.cal-nav button{padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);font-size:13px;font-weight:600;cursor:pointer;color:var(--text)}
.cal-nav button:hover{border-color:var(--accent)}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
.cal-day{min-height:96px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:4px;transition:background .15s}
.cal-day.today{background:rgba(221,255,14,.12);border-color:var(--accent)}
.cal-day.dim{opacity:.4}
.cal-day .dnum{font-size:12px;font-weight:600;color:var(--text-3);margin-bottom:2px}
.cal-chip{font-size:11px;padding:3px 7px;border-radius:6px;color:#fff;font-weight:600;cursor:grab;display:flex;align-items:center;gap:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%}
.cal-chip:hover{filter:brightness(1.1)}
.cal-chip.scheduled{outline:2px dashed rgba(255,255,255,.5)}
.cal-chip.dragging{opacity:.5}
.cal-chip-cont{min-height:6px;opacity:.75}
.cal-handle{display:inline-flex;align-items:center;padding:0 3px;font-size:9px;cursor:ew-resize;opacity:.7;background:rgba(255,255,255,.2);border-radius:3px}
.cal-handle:hover{opacity:1;background:rgba(255,255,255,.4)}
.cal-handle-l{margin-right:3px}
.cal-handle-r{margin-left:3px}
.cal-day.resize-target{outline:2px dashed #7c3aed;outline-offset:-2px;cursor:ew-resize}
.cal-legend{display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap;font-size:12px;color:var(--text-2)}
.cal-legend .lg{display:flex;align-items:center;gap:5px}
.cal-legend .dot{width:10px;height:10px;border-radius:3px}
.cal-legend .dashed{outline:2px dashed rgba(0,0,0,.3);outline-offset:-1px}
.cal-tip{font-size:12px;color:var(--text-3);background:var(--surface-2);padding:8px 14px;border-radius:8px;margin-bottom:16px}
.cal-toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#1e1e1e;color:#fff;padding:10px 22px;border-radius:10px;font-size:13px;z-index:9999;display:none;box-shadow:0 8px 24px rgba(0,0,0,.3)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('content-calendar'); ?>
  <div class="main">
    <h1>📅 内容日历</h1>
    <p class="sub">拖拽文章 / 资料到日历上的日期即可修改发布日期 · 未来日期 = 定时发布 · 活动支持起止日期</p>

    <div class="cal-toolbar">
      <div class="cal-nav">
        <button onclick="calNav(-1)">‹ 上月</button>
        <span id="calMonthLabel" style="font-size:16px;font-weight:700;min-width:120px;text-align:center"></span>
        <button onclick="calNav(1)">下月 ›</button>
        <button onclick="calToday()" style="margin-left:6px">今天</button>
      </div>
      <span style="margin-left:auto" class="text-sm text-muted">当前显示 <?=count($calendarItems)?> 项内容</span>
    </div>

    <div class="cal-legend">
      <span class="lg"><span class="dot" style="background:#2e6b4f"></span>文章</span>
      <span class="lg"><span class="dot" style="background:#7c3aed"></span>活动</span>
      <span class="lg"><span class="dot" style="background:var(--accent)"></span>资料</span>
      <span class="lg"><span class="dot dashed" style="background:var(--warn)"></span>定时发布</span>
    </div>

    <div class="cal-tip">💡 拖拽带虚线的「定时」卡片到未来日期 = 定时发布，拖到今天/过去 = 立即发布。活动卡片整卡拖拽 = 整体移动；拖动卡片两侧的 <b>↔</b> 把手可分别调整「开始日 / 结束日」。</div>

    <div id="calGrid" class="cal-grid"></div>
    <div id="calToast" class="cal-toast"></div>
  </div>
</div>

<script>
var CAL = {
  year: <?=date('Y')?>,
  month: <?=date('n')?>, // 1-12
  today: '<?=date('Y-m-d')?>',
  items: <?=json_encode($calendarItems, JSON_UNESCAPED_UNICODE)?>
};

var WEEK_NAMES = ['日','一','二','三','四','五','六'];
var TYPE_NAMES = { article: '📝 文章', event: '🎪 活动', download: '📄 资料' };

function calRender() {
  var grid = document.getElementById('calGrid');
  var first = new Date(CAL.year, CAL.month - 1, 1);
  var startDow = first.getDay();
  var daysInMonth = new Date(CAL.year, CAL.month, 0).getDate();
  document.getElementById('calMonthLabel').textContent = CAL.year + ' 年 ' + CAL.month + ' 月';

  var html = WEEK_NAMES.map(function(w) { return '<div style="text-align:center;font-size:12px;font-weight:600;color:var(--text-3);padding:4px 0">' + w + '</div>'; }).join('');

  for (var i = 0; i < startDow; i++) html += '<div class="cal-day dim"></div>';
  for (var d = 1; d <= daysInMonth; d++) {
    var ds = CAL.year + '-' + String(CAL.month).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    var isToday = ds === CAL.today;
    // 该日命中的内容：文章/资料按日期精确；活动按 [start,end] 区间
    var chips = CAL.items.filter(function(it) {
      if (it.type === 'event') return it.date <= ds && ds <= (it.end_date || it.date);
      return it.date === ds;
    }).map(function(it) {
      var isStart = it.date === ds;
      var isEnd = (it.end_date || it.date) === ds;
      var scheduled = it.scheduled ? ' scheduled' : '';
      var chipClass = 'cal-chip' + scheduled + (it.type === 'event' && !isStart ? ' cal-chip-cont' : '');
      var label = it.title;
      // 活动区间内的非开始日，只显示小圆点（压缩避免占满格子）
      if (it.type === 'event' && !isStart) {
        return '<div class="' + chipClass + '" style="background:' + it.color + ';min-height:6px;padding:0" ' +
          'data-id="' + it.id + '" data-type="' + it.type + '" data-title="' + it.title.replace(/"/g,'&quot;') + '" ' +
          'data-start="' + it.date + '" data-end="' + (it.end_date||it.date) + '" title="' + TYPE_NAMES[it.type] + ' · ' + it.title + '"></div>';
      }
      // 开始日：完整卡片，带左右边缘拖拽把手
      return '<div class="' + chipClass + '" draggable="true" ondragstart="calDragStart(event)" ondragend="calDragEnd(event)" ' +
        'data-id="' + it.id + '" data-type="' + it.type + '" data-title="' + it.title.replace(/"/g,'&quot;') + '" ' +
        'data-start="' + it.date + '" data-end="' + (it.end_date||it.date) + '" ' +
        'style="background:' + it.color + '" title="' + TYPE_NAMES[it.type] + ' · ' + it.title + (it.end_date!==it.date ? '（' + it.date + ' ~ ' + (it.end_date||it.date) + '）' : '') + '">' +
        (it.type === 'event' && it.end_date !== it.date ? '<span class="cal-handle cal-handle-l" onmousedown="startResize(event,\'start\')" title="拖动调整开始日">↔</span>' : '') +
        (it.scheduled ? '⏰ ' : '') + it.title.substring(0, 10) + (it.title.length > 10 ? '…' : '') +
        (it.type === 'event' && it.end_date !== it.date ? '<span class="cal-handle cal-handle-r" onmousedown="startResize(event,\'end\')" title="拖动调整结束日">↔</span>' : '') +
        '</div>';
    }).join('');
    html += '<div class="cal-day' + (isToday ? ' today' : '') + '" data-date="' + ds + '" ondragover="calDragOver(event)" ondrop="calDrop(event)">' +
      '<div class="dnum">' + d + (isToday ? ' ●' : '') + '</div>' + chips + '</div>';
  }
  grid.innerHTML = html;
}

var calDragItem = null;
var calResizeItem = null;
function calDragStart(e) {
  if (e.target.classList.contains('cal-handle')) return;
  var el = e.target.closest('.cal-chip');
  if (!el) return;
  calDragItem = { id: el.dataset.id, type: el.dataset.type, title: el.dataset.title, el: el };
  el.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function calDragEnd(e) { if (e.target) e.target.classList.remove('dragging'); calDragItem = null; }
function calDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
function calDrop(e) {
  e.preventDefault();
  var dayEl = e.target.closest('.cal-day');
  if (!dayEl) return;
  var date = dayEl.dataset.date;
  if (!calDragItem || !date) return;
  var payload = { id: calDragItem.id, type: calDragItem.type, date: date };
  // 活动拖拽：整体移动（保持跨度），日期为目标日
  if (calDragItem.type === 'event') {
    var span = calEndOf(calDragItem.id) - calStartOf(calDragItem.id); // 天数差
    payload.move_span = span;
  }
  calSendUpdate(payload);
}
function calStartOf(id) {
  for (var i = 0; i < CAL.items.length; i++) if (CAL.items[i].id === id) return CAL.items[i].date;
  return CAL.today;
}
function calEndOf(id) {
  for (var i = 0; i < CAL.items.length; i++) if (CAL.items[i].id === id) return CAL.items[i].end_date || CAL.items[i].date;
  return CAL.today;
}
// 活动起止拖拽：记录当前拖拽的 handle
function startResize(e, which) {
  var el = e.target.closest('.cal-chip');
  if (!el) return;
  e.preventDefault();
  e.stopPropagation();
  calResizeItem = { id: el.dataset.id, type: el.dataset.type, which: which };
  // 让所有格子监听 dragover/drop 以显示高亮
  document.querySelectorAll('.cal-day').forEach(function(d) {
    d.classList.add('resize-target');
  });
}
// 事件委托：resize 模式下的 dragover/drop
document.addEventListener('dragover', function(e) {
  if (!calResizeItem) return;
  var dayEl = e.target.closest('.cal-day');
  if (dayEl) e.preventDefault();
});
document.addEventListener('drop', function(e) {
  if (!calResizeItem) return;
  var dayEl = e.target.closest('.cal-day');
  if (!dayEl) return;
  e.preventDefault();
  var date = dayEl.dataset.date;
  calSendUpdate({ id: calResizeItem.id, type: calResizeItem.type, date: date, resize: calResizeItem.which });
  calResizeItem = null;
  document.querySelectorAll('.cal-day').forEach(function(d) { d.classList.remove('resize-target'); });
});
// 点击页面空白取消 resize
document.addEventListener('mouseup', function() {
  if (calResizeItem) {
    setTimeout(function() {
      calResizeItem = null;
      document.querySelectorAll('.cal-day').forEach(function(d) { d.classList.remove('resize-target'); });
    }, 100);
  }
});

function calSendUpdate(payload) {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/calendar.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    var r = JSON.parse(xhr.responseText);
    if (r.ok) {
      calToast(r.msg || '已更新');
      setTimeout(function() { location.reload(); }, 800);
    } else {
      calToast(r.error || '更新失败', true);
    }
  };
  xhr.send(JSON.stringify(payload));
}

function calNav(dir) {
  CAL.month += dir;
  if (CAL.month < 1) { CAL.month = 12; CAL.year--; }
  if (CAL.month > 12) { CAL.month = 1; CAL.year++; }
  calRender();
}
function calToday() {
  var t = new Date();
  CAL.year = t.getFullYear(); CAL.month = t.getMonth() + 1;
  calRender();
}
function calToast(msg, isErr) {
  var t = document.getElementById('calToast');
  t.textContent = msg;
  t.style.background = isErr ? 'var(--danger)' : '#1e1e1e';
  t.style.display = 'block';
  setTimeout(function() { t.style.display = 'none'; }, 2500);
}

calRender();
</script>
<?php admin_footer(); ?>
