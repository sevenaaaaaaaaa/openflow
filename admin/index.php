<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CommandPalette.php';
require_login();

// ─── 我的常用：添加/移除（AJAX）───
$fcFavAction = $_GET['fc_fav'] ?? '';
if ($fcFavAction !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $favUser = $_SESSION['admin_user'] ?? 'admin';
    $favFile = DATA_DIR . '/user-favorites/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $favUser) . '.json';
    if (!is_dir(dirname($favFile))) mkdir(dirname($favFile), 0755, true);
    $myFavs = json_read($favFile);
    $url = trim($_POST['url'] ?? '');
    if ($fcFavAction === 'add' && $url) {
        if (!in_array($url, $myFavs)) $myFavs[] = $url;
        json_write($favFile, $myFavs);
        echo json_encode(['ok' => true]);
    } elseif ($fcFavAction === 'remove' && $url) {
        $myFavs = array_values(array_filter($myFavs, fn($u) => $u !== $url));
        json_write($favFile, $myFavs);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => '参数错误']);
    }
    exit;
}

// 首次登录 → 引导向导
$onboard = json_read(DATA_DIR . '/onboarding.json');
if (empty($onboard['completed']) && ($_SESSION['admin_role'] ?? '') === 'admin') {
    header('Location: onboarding.php');
    exit;
}

// Stats
$articleCount = has_perm('articles') ? count(get_articles()) : null;
$leadCount = 0; $todayLeads = 0;
$leads = has_perm('leads') ? get_leads() : [];
$today = date('Y-m-d');
foreach ($leads as $l) {
    $leadCount++;
    if (isset($l['时间']) && substr($l['时间'], 0, 10) === $today) $todayLeads++;
}
$draftCount = 0;
if (has_perm('articles')) {
    foreach (get_articles() as $a) if (($a['status'] ?? '') === 'draft') $draftCount++;
}
$topicCount = count(json_read(DATA_DIR . '/topics.json') ?? []);
$courseCount = count(json_read(DATA_DIR . '/courses/index.json') ?? []);
$kbCount = count(json_read(DATA_DIR . '/knowledge/index.json') ?? []);

// 最近文章
$recentArticles = [];
if (has_perm('articles')) {
    $all = get_articles();
    usort($all, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $recentArticles = array_slice($all, 0, 6);
}

// 快捷命令（按分类分组）
$cmdGroups = [];
foreach (cp_items() as $it) $cmdGroups[$it['section']][] = $it;
$allCmds = cp_items();

// ─── 我的常用（自定义快速入口）───
$favUser = $_SESSION['admin_user'] ?? 'admin';
$favFile = DATA_DIR . '/user-favorites/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $favUser) . '.json';
if (!is_dir(dirname($favFile))) mkdir(dirname($favFile), 0755, true);
$myFavs = json_read($favFile);
// 维护：删除已被权限过滤掉的
$cmdMap = [];
foreach ($allCmds as $c) $cmdMap[$c['url']] = $c;
$myFavs = array_values(array_filter($myFavs, fn($u) => isset($cmdMap[$u])));
json_write($favFile, $myFavs);
$favCmds = array_values(array_filter(array_map(fn($u) => $cmdMap[$u] ?? null, $myFavs)));

$hour = (int)date('H');
$greet = $hour < 6 ? '夜深了' : ($hour < 12 ? '早上好' : ($hour < 18 ? '下午好' : '晚上好'));
$name = $_SESSION['admin_user'] ?? '';

admin_header('工作台');
?>
<style>
  .wb-hero{background:linear-gradient(135deg,#1a1625 0%,#2b5f7e 60%,#3a7d9c 100%);color:#fff;border-radius:var(--radius-lg);padding:26px 30px;position:relative;overflow:hidden;margin-bottom:20px}
  .wb-hero::after{content:'';position:absolute;right:-40px;top:-40px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(221,255,14,.25),transparent 70%)}
  .wb-hero h1{font-size:22px;margin-bottom:6px}
  .wb-hero p{opacity:.82;font-size:13.5px;max-width:560px}
  .wb-hero .wb-tools{margin-top:14px;display:flex;flex-wrap:wrap;gap:8px}
  .wb-hero .wb-tool{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#fff;padding:6px 13px;border-radius:999px;font-size:12.5px;text-decoration:none;transition:.15s}
  .wb-hero .wb-tool:hover{background:var(--accent);color:#1e1e1e;border-color:var(--accent)}
  .wb-stat{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;position:relative}
  .wb-stat .n{font-size:26px;font-weight:800}
  .wb-stat .l{font-size:12px;color:var(--text-2)}
  .wb-stat .ic{position:absolute;right:14px;top:14px;font-size:20px;opacity:.55}
  .wb-cmd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
  .wb-cmd{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface);text-decoration:none;color:inherit;transition:.15s;cursor:pointer}
  .wb-cmd:hover{border-color:var(--accent);transform:translateY(-1px);box-shadow:var(--shadow)}
  .wb-cmd .c-ic{font-size:19px}
  .wb-cmd .c-lb{font-size:13px;font-weight:600}
  .wb-cmd .c-url{font-size:10.5px;color:var(--text-3);font-family:var(--mono)}
  .wb-group-title{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin:18px 0 8px}
  .wb-art{display:flex;gap:12px;align-items:center;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface);margin-bottom:8px;text-decoration:none;color:inherit;transition:.15s}
  .wb-art:hover{border-color:var(--accent)}
  .wb-art img{width:52px;height:38px;object-fit:cover;border-radius:8px;background:var(--surface-2)}
  .wb-art .t{font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .wb-art .d{font-size:11px;color:var(--text-3);margin-top:2px}
  .badge{font-size:10.5px;padding:2px 8px;border-radius:999px;font-weight:600}
  .badge.pub{background:rgba(22,163,74,.12);color:#16a34a}
  .badge.draft{background:rgba(217,119,6,.12);color:#d97706}
</style>
<div class="admin-layout">
  <?php admin_sidebar('dashboard'); ?>
  <div class="main">
    <!-- 问候横幅 -->
    <div class="wb-hero">
      <h1><?=htmlspecialchars($greet)?>，<?=htmlspecialchars($name ?: '管理员')?> 👋</h1>
      <p><?=date('Y年m月d日 l')?> · 这是你今天的 OpenFlow 工作台。点下方快捷工具或按 <kbd style="background:rgba(255,255,255,.18);border-radius:4px;padding:1px 6px;font-size:11px">⌘K</kbd> 快速跳转。</p>
      <div class="wb-tools">
        <a class="wb-tool" href="article-edit.php">✍️ 写文章</a>
        <a class="wb-tool" href="ingest.php">🔌 外部导入</a>
        <a class="wb-tool" href="knowledge.php">📚 知识库</a>
        <a class="wb-tool" href="crm.php">👥 线索</a>
        <a class="wb-tool" href="health-check.php">🩺 健康检测</a>
        <a class="wb-tool" href="dashboard.php">📊 经营驾驶舱</a>
      </div>
    </div>

    <!-- 快速统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:8px">
      <?php if ($articleCount !== null): ?>
      <div class="wb-stat"><span class="ic">📝</span><div class="l">文章</div><div class="n"><?=$articleCount?></div></div>
      <?php endif; ?>
      <?php if (has_perm('articles')): ?>
      <div class="wb-stat"><span class="ic">📋</span><div class="l">草稿</div><div class="n"><?=$draftCount?></div></div>
      <?php endif; ?>
      <?php if ($leadCount !== null): ?>
      <div class="wb-stat"><span class="ic">👥</span><div class="l">总线索</div><div class="n"><?=$leadCount?></div><div class="l" style="margin-top:2px">今日 +<?=$todayLeads?></div></div>
      <?php endif; ?>
      <div class="wb-stat"><span class="ic">📚</span><div class="l">专题</div><div class="n"><?=$topicCount?></div></div>
      <div class="wb-stat"><span class="ic">🎓</span><div class="l">课程</div><div class="n"><?=$courseCount?></div></div>
      <?php if (has_perm('knowledge')): ?>
      <div class="wb-stat"><span class="ic">🧠</span><div class="l">知识库</div><div class="n"><?=$kbCount?></div></div>
      <?php endif; ?>
    </div>

    <!-- 健康提示 -->
    <?php if (has_perm('settings')): $hcIssues = 0; $hcEmptySeo = 0;
      foreach (get_articles() as $a) if (empty(trim($a['seo_title'] ?? '')) || empty(trim($a['seo_desc'] ?? ''))) $hcEmptySeo++;
      $hcIssues = $hcEmptySeo + count(json_read(DATA_DIR . '/trash.json'));
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;border:1px solid var(--border);background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.07));margin-bottom:4px">
      <span style="font-size:20px">🩺</span>
      <span class="text-sm" style="flex:1">
        <?php if ($hcIssues > 0): ?><b><?=$hcIssues?> 项内容待优化</b>（SEO 缺失 <?=$hcEmptySeo?> · 回收站 <?=count(json_read(DATA_DIR . '/trash.json'))?>）
        <?php else: ?><b>站点很健康</b> 🎉
        <?php endif; ?>
      </span>
      <a href="health-check.php" class="btn btn-primary btn-sm">一键检测 →</a>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1.7fr 1fr;gap:20px" class="wb-grid">
      <!-- 快捷命令 -->
      <div>
        <!-- 我的常用 -->
        <div class="wb-group-title">⭐ 我的常用
          <button onclick="fcFavPicker()" style="margin-left:8px;font-size:11px;font-weight:600;padding:2px 10px;border-radius:999px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-2);cursor:pointer">+ 添加入口</button>
        </div>
        <?php if (empty($favCmds)): ?>
        <div class="empty" style="padding:16px;font-size:13px;color:var(--text-3)">暂无常用入口，点击「+ 添加入口」把常用功能放这里</div>
        <?php else: ?>
        <div class="wb-cmd-grid">
          <?php foreach ($favCmds as $c): ?>
          <a href="<?=htmlspecialchars($c['url'])?>" class="wb-cmd" style="border-color:rgba(221,255,14,.5);background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.08))">
            <span class="c-ic"><?=htmlspecialchars($c['icon'])?></span>
            <span style="flex:1;min-width:0"><div class="c-lb"><?=htmlspecialchars($c['label'])?></div><div class="c-url"><?=htmlspecialchars(basename($c['url']))?></div></span>
            <span style="font-size:12px;color:var(--text-3);cursor:pointer" title="移除" onclick="event.preventDefault();fcFavRemove('<?=htmlspecialchars($c['url'])?>',this)">✕</span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php $groupOrder = ['CMS 内容','营销获客','知识与 AI','增长与分析','系统设置'];
        foreach ($groupOrder as $g):
          if (empty($cmdGroups[$g])) continue; ?>
        <div class="wb-group-title"><?=htmlspecialchars($g)?></div>
        <div class="wb-cmd-grid">
          <?php foreach (array_slice($cmdGroups[$g], 0, 6) as $c): ?>
          <a href="<?=htmlspecialchars($c['url'])?>" class="wb-cmd">
            <span class="c-ic"><?=htmlspecialchars($c['icon'])?></span>
            <span style="flex:1;min-width:0"><div class="c-lb"><?=htmlspecialchars($c['label'])?></div><div class="c-url"><?=htmlspecialchars(basename($c['url']))?></div></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 最近文章 -->
      <div>
        <div class="wb-group-title">🕒 最近文章</div>
        <?php if (empty($recentArticles)): ?>
        <div class="empty" style="padding:24px">暂无文章，点击「写文章」开始创作</div>
        <?php else: foreach ($recentArticles as $a): ?>
        <a href="article-edit.php?id=<?=urlencode($a['id'])?>" class="wb-art">
          <?php if (!empty($a['cover'])): ?><img src="<?=htmlspecialchars($a['cover'])?>" onerror="this.style.display='none'"><?php endif; ?>
          <span style="flex:1;min-width:0">
            <div class="t"><?=htmlspecialchars($a['title'])?></div>
            <div class="d"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 16))?> · <?=htmlspecialchars($a['category'] ?? '')?></div>
          </span>
          <span class="badge <?=($a['status'] ?? '') === 'draft' ? 'draft' : 'pub'?>"><?=($a['status'] ?? '') === 'draft' ? '草稿' : '已发布'?></span>
        </a>
        <?php endforeach; endif; ?>

        <?php if (has_perm('leads') && !empty($leads)): ?>
        <div class="wb-group-title" style="margin-top:18px">📥 最新线索</div>
        <?php foreach (array_slice($leads, 0, 4) as $l): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;border:1px solid var(--border);border-radius:10px;background:var(--surface);margin-bottom:6px">
          <span style="font-size:16px">👤</span>
          <span style="flex:1;font-size:12.5px"><b><?=htmlspecialchars($l['姓名'] ?? '')?></b><span class="text-muted"> · <?=htmlspecialchars($l['公司'] ?? '')?></span></span>
          <span class="text-muted" style="font-size:11px"><?=htmlspecialchars(substr($l['时间'] ?? '', 5, 11))?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:980px){.wb-grid{grid-template-columns:1fr!important}}</style>
<script>
var FC_ALL_CMDS = <?=json_encode($allCmds, JSON_UNESCAPED_UNICODE)?>;
var FC_MY_FAVS = <?=json_encode($myFavs, JSON_UNESCAPED_UNICODE)?>;
function fcFavPicker() {
  var existing = FC_MY_FAVS || [];
  var opts = FC_ALL_CMDS.filter(function(c){ return existing.indexOf(c.url) < 0; });
  var sel = window.prompt('输入要添加的功能关键词（如：文章 / 线索 / 邮件）');
  if (!sel) return;
  var q = sel.toLowerCase();
  var found = opts.filter(function(c){
    var hay = (c.label + ' ' + (c.keywords||'') + ' ' + c.section).toLowerCase();
    return hay.indexOf(q) >= 0;
  });
  if (!found.length) { alert('没有找到匹配的功能'); return; }
  if (found.length > 1) {
    var pick = window.prompt('找到多个，输入要添加的序号：\n' + found.map(function(c,i){ return (i+1) + '. ' + c.label; }).join('\n'));
    var idx = parseInt(pick, 10) - 1;
    if (isNaN(idx) || !found[idx]) { alert('序号无效'); return; }
    found = [found[idx]];
  }
  var body = new FormData();
  body.append('url', found[0].url);
  fetch('index.php?fc_fav=add', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) location.reload(); else alert(d.error || '添加失败'); });
}
function fcFavRemove(url, el) {
  var body = new FormData();
  body.append('url', url);
  fetch('index.php?fc_fav=remove', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) location.reload(); });
}
</script>
<?php admin_footer(); ?>
