<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/RealtimeData.php';
require_login();
require_perm('analytics');

$message = '';
$settings = RealtimeData::settings();

// 清缓存
if (isset($_GET['clear_cache'])) {
    csrf_verify();
    RealtimeData::clearCache();
    $message = '实时数据缓存已清空';
}

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    RealtimeData::saveSettings(['bing_key' => trim($_POST['bing_key'] ?? '')]);
    $message = '实时数据设置已保存';
    $settings = RealtimeData::settings();
}

// 关键词列表（SERP 监控）
$keywordsFile = DATA_DIR . '/serp-keywords.json';
$keywords = json_read($keywordsFile);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_keyword'])) {
    csrf_verify();
    $kw = trim($_POST['keyword'] ?? '');
    if ($kw && !in_array($kw, $keywords, true)) {
        $keywords[] = $kw;
        json_write($keywordsFile, $keywords);
        $message = '已添加监控关键词';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_keyword'])) {
    csrf_verify();
    $keywords = array_values(array_filter($keywords, fn($k) => $k !== $_POST['keyword']));
    json_write($keywordsFile, $keywords);
    $message = '已移除关键词';
}

admin_header('实时数据');
?>
<div class="admin-layout">
  <?php admin_sidebar('realtime'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">⚡ 实时数据</h1>
      <div class="flex gap-2 ml-auto">
        <a href="?clear_cache=1&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm">清缓存</a>
      </div>
    </div>
    <p class="sub">实时 SERP 排名 · 舆情采集 · 站点实时指标</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 站点实时指标 -->
    <div class="card" style="margin-bottom:24px;padding:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="margin:0">📊 站点实时指标</h2>
        <button type="button" class="btn btn-ghost btn-sm" onclick="loadLocal()">🔄 刷新</button>
      </div>
      <div id="localBox" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
        <div class="stat-card"><div class="num">—</div><div class="label">24h 行为事件</div></div>
      </div>
    </div>

    <!-- SERP 监控 -->
    <div class="card" style="margin-bottom:24px">
      <h2>🔍 实时 SERP 监控</h2>
      <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
        <?= csrf_field() ?>
        <input type="hidden" name="add_keyword" value="1">
        <input type="text" name="keyword" placeholder="输入要监控的关键词…" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px">
        <button type="submit" class="btn btn-primary">➕ 添加</button>
      </form>
      <?php if ($keywords): ?>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
        <?php foreach ($keywords as $kw): ?>
        <span style="display:flex;align-items:center;gap:6px;padding:4px 10px;background:var(--surface-2);border:1px solid var(--border);border-radius:99px;font-size:12px">
          <?=htmlspecialchars($kw)?>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="remove_keyword" value="1">
            <input type="hidden" name="keyword" value="<?=htmlspecialchars($kw)?>">
            <button class="btn btn-ghost btn-sm" style="padding:0 4px;color:var(--danger)">✕</button>
          </form>
        </span>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-primary btn-sm" onclick="checkSerpBatch()">🚀 查询全部排名</button>
      <?php endif; ?>
      <div id="serpResults" style="margin-top:12px"></div>
    </div>

    <!-- 舆情监测 -->
    <div class="card" style="margin-bottom:24px">
      <h2>📡 实时舆情采集</h2>
      <div style="display:flex;gap:8px;align-items:center">
        <input type="text" id="sentTopic" placeholder="输入舆情主题…" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px">
        <button type="button" class="btn btn-primary" onclick="checkSentiment()">🔍 采集</button>
        <button type="button" class="btn btn-ghost" onclick="sentimentSummary()">🤖 AI 摘要</button>
      </div>
      <div id="sentBox" style="margin-top:12px"></div>
      <div id="sentSummaryBox" style="margin-top:12px"></div>
    </div>

    <!-- 设置 -->
    <div class="card">
      <h2>⚙️ 数据源设置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_settings" value="1">
        <div class="field"><label>Bing Web Search API Key <span class="hint">· 免费额度 · 用于实时 SERP 与舆情</span></label><input type="text" name="bing_key" value="<?=htmlspecialchars($settings['bing_key'] ?? '')?>" placeholder="Azure Bing Search Key"></div>
        <p class="text-sm text-muted">未配置 Key 时自动使用 DuckDuckGo / 百度 HTML 抓取（免费、无需 Key，但结果有限）。</p>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </form>
    </div>
  </div>
</div>

<script>
function el(html){var d=document.createElement('div');d.innerHTML=html;return d.firstElementChild;}

// 本地实时指标
function loadLocal() {
  fetch('../api/realtime.php?type=local', {credentials:'include'}).then(function(r){return r.json();}).then(function(d){
    var box = document.getElementById('localBox');
    if (!d.ok) return;
    var x = d.data;
    box.innerHTML =
      '<div class="stat-card"><div class="num">' + (x.events_24h||0) + '</div><div class="label">24h 行为事件</div></div>' +
      '<div class="stat-card"><div class="num">' + (x.active_visitors_5min||0) + '</div><div class="label">5min 活跃访客</div></div>' +
      '<div class="stat-card"><div class="num">' + (x.new_members_24h||0) + '</div><div class="label">24h 新会员</div></div>' +
      '<div class="stat-card"><div class="num">' + (x.form_submissions_24h||0) + '</div><div class="label">24h 表单提交</div></div>';
  });
}

// SERP 批量查询
function checkSerpBatch() {
  var box = document.getElementById('serpResults');
  var keywords = <?=json_encode($keywords)?>;
  if (!keywords.length) { box.innerHTML = '<div class="text-sm text-muted">请先添加关键词</div>'; return; }
  box.innerHTML = '<div class="text-sm text-muted">查询中…</div>';
  var out = '';
  var i = 0;
  function next() {
    if (i >= keywords.length) { box.innerHTML = out || '<div class="text-sm text-muted">完成</div>'; return; }
    var kw = keywords[i++];
    fetch('../api/realtime.php?type=serp&q=' + encodeURIComponent(kw), {credentials:'include'})
      .then(function(r){return r.json();})
      .then(function(d){
        var s = d.data || {};
        var badge = s.found ? '<span class="badge badge-green">第 ' + s.rank + ' 名</span>' : '<span class="badge badge-gray">未上榜</span>';
        var engine = s.engine === 'baidu' ? '百度' : (s.engine === 'duckduckgo' ? 'DDG' : 'Bing');
        out += '<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--surface-2);border-radius:8px;margin-bottom:6px;font-size:13px">' +
          '<span style="font-weight:600">' + kw + '</span><span class="text-sm text-muted" style="font-size:11px">' + engine + '</span>' + badge +
          '<span class="text-sm text-muted" style="font-size:11px;margin-left:auto">' + (s.results? s.results.length:0) + ' 条结果</span></div>';
        next();
      })
      .catch(function(){ out += '<div style="padding:8px">' + kw + ' 查询失败</div>'; next(); });
  }
  next();
}

// 舆情采集
function checkSentiment() {
  var topic = document.getElementById('sentTopic').value.trim();
  var box = document.getElementById('sentBox');
  if (!topic) { box.innerHTML = '<div class="text-sm text-muted">请输入主题</div>'; return; }
  box.innerHTML = '<div class="text-sm text-muted">采集中…</div>';
  fetch('../api/realtime.php?type=sentiment&topic=' + encodeURIComponent(topic), {credentials:'include'})
    .then(function(r){return r.json();})
    .then(function(d){
      var s = d.data || {};
      var h = '<div style="margin-bottom:8px;font-size:13px;font-weight:600">采集到 ' + (s.count||0) + ' 条信息</div>';
      (s.results||[]).slice(0,12).forEach(function(r){
        h += '<div style="display:flex;gap:8px;padding:6px 10px;background:var(--surface-2);border-radius:6px;margin-bottom:4px;font-size:12.5px">' +
          '<a href="' + (r.url||'#') + '" target="_blank" style="text-decoration:none;color:var(--accent);font-weight:600">' + (r.title||'') + '</a>' +
          '<span class="text-sm text-muted" style="font-size:11px">' + (r.date||'') + '</span></div>';
      });
      box.innerHTML = h;
    });
}

// 舆情 AI 摘要
function sentimentSummary() {
  var topic = document.getElementById('sentTopic').value.trim();
  var box = document.getElementById('sentSummaryBox');
  if (!topic) { box.innerHTML = '<div class="text-sm text-muted">请输入主题</div>'; return; }
  box.innerHTML = '<div class="text-sm text-muted">AI 分析中…</div>';
  fetch('../api/realtime.php?type=sentiment_summary&topic=' + encodeURIComponent(topic), {credentials:'include'})
    .then(function(r){return r.json();})
    .then(function(d){
      box.innerHTML = '<div style="padding:12px;background:var(--surface-2);border-radius:10px;font-size:13.5px;line-height:1.7">' +
        '<div style="font-weight:600;margin-bottom:6px">🤖 舆情摘要' + (d.ai?' <span class="badge badge-green">AI</span>':'') + '</div>' +
        (d.summary||'') +
        (d.tone ? '<div style="margin-top:6px;color:var(--text-3)">倾向：' + d.tone + '</div>' : '') +
        (d.hot_points && d.hot_points.length ? '<div style="margin-top:6px">热点：' + d.hot_points.join(' · ') + '</div>' : '') +
        '</div>';
    });
}
document.addEventListener('DOMContentLoaded', function(){ loadLocal(); });
</script>
<?php admin_footer(); ?>
