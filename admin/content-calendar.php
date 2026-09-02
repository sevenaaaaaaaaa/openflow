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
        'color' => !empty($a['publish_at']) ? 'var(--warn)' : 'oklch(52% .12 160)',
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
        'color' => 'var(--accent)',
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
        'color' => 'oklch(55% .13 250)',
    ];
}

// 分发发布计划（定时发布队列：某天发到哪些渠道）
$publishQueue = json_read(DATA_DIR . '/publish-queue.json');
foreach ((array)$publishQueue as $pq) {
    if (($pq['status'] ?? '') === 'done') continue;
    $calendarItems[] = [
        'id' => $pq['id'] ?? ('pub_' . $pq['article_id']),
        'type' => 'publish',
        'title' => '分发：' . ($pq['title'] ?? '内容'),
        'platforms' => (array)($pq['platforms'] ?? []),
        'date' => substr($pq['send_at'] ?? '', 0, 10),
        'scheduled' => true,
        'color' => 'oklch(60% .14 300)',
    ];
}
// 已发布记录
$publishLog = json_read(DATA_DIR . '/publish-log.json');
foreach ((array)$publishLog as $pl) {
    if (empty($pl['send_at'] ?? '') && empty($pl['created_at'] ?? '')) continue;
    $calendarItems[] = [
        'id' => ($pl['id'] ?? '') ?: ('plog_' . md5(json_encode($pl))),
        'type' => 'publish_done',
        'title' => '已分发：' . ($pl['title'] ?? '内容'),
        'platforms' => array_keys((array)($pl['results'] ?? [])),
        'date' => substr($pl['send_at'] ?? ($pl['created_at'] ?? ''), 0, 10),
        'scheduled' => false,
        'color' => 'var(--ok)',
    ];
}

// 按日期排序
usort($calendarItems, fn($a, $b) => strcmp($a['date'], $b['date']));

admin_header('内容日历');
?>
<style>
.cal-head{display:flex;align-items:flex-start;gap:16px;margin-bottom:14px}
.cal-head .sub{margin-bottom:0}
.cal-count{margin-left:auto;font-size:12.5px;color:var(--muted);white-space:nowrap;padding-top:14px}
.cal-count b{font-family:var(--font-mono);color:var(--fg)}
.cal-toolbar{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
.cal-nav{display:flex;gap:4px;align-items:center}
.cal-nav button,.cal-seg button{height:34px;padding:0 12px;border-radius:9px;border:1px solid var(--border);background:var(--surface);font-size:13px;font-weight:600;cursor:pointer;color:var(--fg)}
.cal-nav button:hover,.cal-seg button:hover{border-color:var(--border-strong);background:var(--hover)}
.cal-month{font-size:15px;font-weight:800;min-width:120px;text-align:center;letter-spacing:-.01em}
.cal-seg{display:inline-flex;padding:3px;border-radius:11px;background:var(--hover);gap:2px}
.cal-seg button{height:28px;border:0;background:transparent;color:var(--muted);border-radius:8px}
.cal-seg button.cal-view-on{background:var(--surface-strong);color:var(--fg);box-shadow:var(--shadow-sm)}
.cal-help{position:relative;display:inline-flex}
.cal-help summary{list-style:none;width:22px;height:22px;border-radius:50%;border:1px solid var(--border);display:grid;place-items:center;font-size:12px;font-weight:700;color:var(--muted);cursor:pointer}
.cal-help summary::-webkit-details-marker{display:none}
.cal-help .cal-tip{position:absolute;right:0;top:calc(100% + 8px);width:320px;z-index:20;box-shadow:var(--shadow);border:1px solid var(--border);line-height:1.7}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
.cal-day{min-height:96px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:4px;transition:background .15s}
.cal-day.today{background:var(--accent-soft);border-color:var(--accent)}
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
.cal-day.resize-target{outline:2px dashed var(--accent);outline-offset:-2px;cursor:ew-resize}
.cal-legend{display:flex;gap:12px;margin-left:auto;align-items:center;flex-wrap:wrap;font-size:12px;color:var(--text-2)}
.cal-legend .lg{display:flex;align-items:center;gap:5px}
.cal-legend .dot{width:10px;height:10px;border-radius:3px}
.cal-legend .dashed{outline:2px dashed rgba(0,0,0,.3);outline-offset:-1px}
.cal-tip{font-size:12px;color:var(--muted);background:var(--surface-strong);padding:10px 14px;border-radius:10px}
.cal-toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:var(--fg);color:var(--bg);padding:10px 22px;border-radius:10px;font-size:13px;z-index:9999;display:none;box-shadow:0 8px 24px rgba(0,0,0,.3)}
@media(max-width:840px){.cal-grid{grid-template-columns:repeat(7,minmax(76px,1fr))}.main{overflow-x:auto}.cal-legend{margin-left:0}}
</style>
<div class="admin-layout">
  <?php admin_sidebar('content-calendar'); ?>
  <div class="main">
    <div class="cal-head">
      <div><h1>内容日历</h1><p class="sub">拖拽文章 / 资料到日期即可改发布日期；未来日期 = 定时发布，活动支持起止日期</p></div>
      <span class="cal-count"><b><?=count($calendarItems)?></b> 项内容</span>
    </div>

    <div class="cal-toolbar">
      <div class="cal-nav">
        <button onclick="calNav(-1)" aria-label="上一月">‹</button>
        <span id="calMonthLabel" class="cal-month"></span>
        <button onclick="calNav(1)" aria-label="下一月">›</button>
        <button onclick="calToday()" style="margin-left:6px">今天</button>
      </div>
      <div class="cal-seg" role="tablist"><button id="calViewMonth" onclick="calSetView('month')" class="cal-view-on" role="tab">月</button><button id="calViewWeek" onclick="calSetView('week')" role="tab">周</button></div>
      <div class="cal-legend">
        <span class="lg"><span class="dot" style="background:oklch(52% .12 160)"></span>文章</span>
        <span class="lg"><span class="dot" style="background:var(--accent)"></span>活动</span>
        <span class="lg"><span class="dot" style="background:oklch(55% .13 250)"></span>资料</span>
        <span class="lg"><span class="dot" style="background:oklch(60% .14 300)"></span>分发计划</span>
        <span class="lg"><span class="dot" style="background:var(--ok)"></span>已分发</span>
        <span class="lg"><span class="dot dashed" style="background:var(--warn)"></span>定时</span>
        <details class="cal-help"><summary title="拖拽说明">?</summary><div class="cal-tip">拖拽带虚线的「定时」卡片到未来日期 = 定时发布，拖到今天 / 过去 = 立即发布。活动卡片整卡拖拽 = 整体移动；拖动卡片两侧的 <b>↔</b> 把手可分别调整开始日 / 结束日。</div></details>
      </div>
    </div>

    <div id="calGrid" class="cal-grid"></div>
    <div id="calToast" class="cal-toast"></div>
  </div>
</div>

<script>
var CAL = {
  year: <?=date('Y')?>,
  month: <?=date('n')?>, // 1-12
  today: '<?=date('Y-m-d')?>',
  view: 'month', // month / week
  weekStart: null, // 周视图起始日（Date）
  items: <?=json_encode($calendarItems, JSON_UNESCAPED_UNICODE)?>
};

var WEEK_NAMES = ['日','一','二','三','四','五','六'];
var TYPE_NAMES = { article: '📝 文章', event: '🎪 活动', download: '📄 资料', publish: '📣 分发计划', publish_done: '✅ 已分发' };

// 平台短名
function platLabel(p) {
  var map = { wechat: '公众号', zhihu: '知乎', bilibili: 'B站', weibo: '微博', xiaohongshu: '小红书', douyin: '抖音', official: '官网', custom: '自定义', webhook: 'Webhook' };
  return map[p] || p;
}

function calSetView(v) {
  CAL.view = v;
  document.getElementById('calViewMonth').className = v === 'month' ? 'cal-view-on' : '';
  document.getElementById('calViewWeek').className = v === 'week' ? 'cal-view-on' : '';
  if (v === 'week' && !CAL.weekStart) {
    var t = new Date();
    var dow = t.getDay();
    CAL.weekStart = new Date(t.getFullYear(), t.getMonth(), t.getDate() - dow);
  }
  calRender();
}

function calRender() {
  var grid = document.getElementById('calGrid');
  document.getElementById('calMonthLabel').textContent = CAL.year + ' 年 ' + CAL.month + ' 月';

  var html = WEEK_NAMES.map(function(w) { return '<div style="text-align:center;font-size:12px;font-weight:600;color:var(--text-3);padding:4px 0">' + w + '</div>'; }).join('');

  if (CAL.view === 'week') {
    // 周视图：7 天为一列，内容更展开
    var days = [];
    for (var wi = 0; wi < 7; wi++) {
      var d = new Date(CAL.weekStart.getFullYear(), CAL.weekStart.getMonth(), CAL.weekStart.getDate() + wi);
      days.push(CAL.weekStart.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'));
    }
    for (var wi2 = 0; wi2 < 7; wi2++) {
      var ds2 = days[wi2];
      var isToday2 = ds2 === CAL.today;
      var chips2 = CAL.items.filter(function(it) {
        if (it.type === 'event') return it.date <= ds2 && ds2 <= (it.end_date || it.date);
        return it.date === ds2;
      }).map(function(it) {
        var isStart = it.date === ds2;
        var scheduled = it.scheduled ? ' scheduled' : '';
        var chipClass = 'cal-chip' + scheduled + (it.type === 'event' && !isStart ? ' cal-chip-cont' : '');
        if (it.type === 'event' && !isStart) {
          return '<div class="' + chipClass + '" style="background:' + it.color + ';min-height:6px;padding:0" data-id="' + it.id + '" data-type="' + it.type + '" data-title="' + it.title.replace(/"/g,'&quot;') + '" data-start="' + it.date + '" data-end="' + (it.end_date||it.date) + '" title="' + TYPE_NAMES[it.type] + ' · ' + it.title + '"></div>';
        }
        return '<div class="' + chipClass + '" draggable="true" ondragstart="calDragStart(event)" ondragend="calDragEnd(event)" data-id="' + it.id + '" data-type="' + it.type + '" data-title="' + it.title.replace(/"/g,'&quot;') + '" data-start="' + it.date + '" data-end="' + (it.end_date||it.date) + '" style="background:' + it.color + '" title="' + TYPE_NAMES[it.type] + ' · ' + it.title + '">' + (it.scheduled ? '⏰ ' : '') + it.title.substring(0, 14) + (it.title.length > 14 ? '…' : '') + (it.platforms && it.platforms.length ? ' <span style="opacity:.85">→' + it.platforms.map(platLabel).join('/') + '</span>' : '') + '</div>';
      }).join('');
      html += '<div class="cal-day' + (isToday2 ? ' today' : '') + '" data-date="' + ds2 + '" ondragover="calDragOver(event)" ondrop="calDrop(event)" style="min-height:120px">' +
        '<div class="dnum">' + (isToday2 ? '今天' : WEEK_NAMES[wi2]) + ' ' + ds2.substring(5) + '</div>' + chips2 + '</div>';
    }
    grid.innerHTML = html;
    return;
  }

  // 月视图
  var first = new Date(CAL.year, CAL.month - 1, 1);
  var startDow = first.getDay();
  var daysInMonth = new Date(CAL.year, CAL.month, 0).getDate();
  for (var i = 0; i < startDow; i++) html += '<div class="cal-day dim"></div>';
  for (var d = 1; d <= daysInMonth; d++) {
    var ds = CAL.year + '-' + String(CAL.month).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    var isToday = ds === CAL.today;
    var chips = CAL.items.filter(function(it) {
      if (it.type === 'event') return it.date <= ds && ds <= (it.end_date || it.date);
      return it.date === ds;
    }).map(function(it) {
      var isStart = it.date === ds;
      var isEnd = (it.end_date || it.date) === ds;
      var scheduled = it.scheduled ? ' scheduled' : '';
      var chipClass = 'cal-chip' + scheduled + (it.type === 'event' && !isStart ? ' cal-chip-cont' : '');
      var label = it.title;
      if (it.type === 'event' && !isStart) {
        return '<div class="' + chipClass + '" style="background:' + it.color + ';min-height:6px;padding:0" ' +
          'data-id="' + it.id + '" data-type="' + it.type + '" data-title="' + it.title.replace(/"/g,'&quot;') + '" ' +
          'data-start="' + it.date + '" data-end="' + (it.end_date||it.date) + '" title="' + TYPE_NAMES[it.type] + ' · ' + it.title + '"></div>';
      }
      return '<div class="' + chipClass + '" draggable="true" ondragstart="calDragStart(event)" ondragend="calDragEnd(event)" ' +
        'data-id="' + it.id + '" data-type="' + it.type + '" data-title="' + it.title.replace(/"/g,'&quot;') + '" ' +
        'data-start="' + it.date + '" data-end="' + (it.end_date||it.date) + '" ' +
        'style="background:' + it.color + '" title="' + TYPE_NAMES[it.type] + ' · ' + it.title + (it.end_date!==it.date ? '（' + it.date + ' ~ ' + (it.end_date||it.date) + '）' : '') + '">' +
        (it.type === 'event' && it.end_date !== it.date ? '<span class="cal-handle cal-handle-l" onmousedown="startResize(event,\'start\')" title="拖动调整开始日">↔</span>' : '') +
        (it.scheduled ? '⏰ ' : '') + it.title.substring(0, 10) + (it.title.length > 10 ? '…' : '') +
        (it.platforms && it.platforms.length ? ' <span style="opacity:.85;font-weight:400">→' + it.platforms.map(platLabel).join('/') + '</span>' : '') +
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
