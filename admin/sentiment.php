<?php
/**
 * 舆情监测中心 — 多源搜索 + AI 洞察 + 舆情报告
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/SentimentSystem.php';
require_login();
require_perm('settings');

$message = '';
$error = '';

// 添加主题
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_topic'])) {
    csrf_verify();
    $name = trim($_POST['topic_name'] ?? '');
    if (empty($name)) { $error = '请输入监控主题'; }
    else { sent_add_topic($name); $message = '监控主题已添加'; header('Location: sentiment.php'); exit; }
}

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    sent_save_settings([
        'bing_key' => trim($_POST['bing_key'] ?? ''),
        'sources' => array_keys(array_filter(['bing'=>isset($_POST['src_bing']),'baidu'=>isset($_POST['src_baidu']),'rss'=>isset($_POST['src_rss'])])),
    ]);
    $message = '设置已保存';
}

// 删除主题
if (isset($_GET['delete'])) {
    $d = sent_get();
    $d['topics'] = array_values(array_filter($d['topics'], fn($t) => $t['id'] !== $_GET['delete']));
    sent_save($d);
    try { Database::execute("DELETE FROM sentiment_results WHERE topic_id = ?", [$_GET['delete']]); } catch (Exception $e) {}
    header('Location: sentiment.php');
    exit;
}

// 一键采集
if (isset($_GET['scan'])) {
    $result = sent_run_scan($_GET['scan']);
    if ($result['ok']) {
        $message = "采集完成：新增 {$result['results']} 条 · 正面 {$result['positive']} · 负面 {$result['negative']} · 中性 {$result['neutral']}";
    } else { $error = $result['error'] ?? '采集失败'; }
}

// 报告（当前主题）
if (isset($_GET['report'])) {
    $topicId = $_GET['report'];
    $topics = sent_topics();
    $topic = null; foreach ($topics as $t) if ($t['id'] === $topicId) { $topic = $t; break; }
    if ($topic) {
        // 收集数据
        $rows = [];
        try { $rows = Database::query("SELECT * FROM sentiment_results WHERE topic_id = ? ORDER BY id DESC LIMIT 200", [$topicId]); } catch (Exception $e) {}
        $pos = count(array_filter($rows, fn($r) => $r['sentiment'] === '正面'));
        $neg = count(array_filter($rows, fn($r) => $r['sentiment'] === '负面'));
        $neu = count(array_filter($rows, fn($r) => $r['sentiment'] === '中性'));
        $total = count($rows);
        // 媒体来源分布
        $sources = [];
        foreach ($rows as $r) $sources[$r['source']] = ($sources[$r['source']] ?? 0) + 1;
        // 热词统计
        $wordCounts = [];
        foreach ($rows as $r) {
            preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', ($r['title'] ?? '') . ' ' . ($r['snippet'] ?? ''), $m);
            foreach ($m[0] as $w) $wordCounts[$w] = ($wordCounts[$w] ?? 0) + 1;
        }
        arsort($wordCounts);
        $topWords = array_slice($wordCounts, 0, 15, true);
        // 情感倾向
        $sentimentPct = $total > 0 ? round($pos / $total * 100) : 0;
        $risk = $neg > 0 && $neg / max(1,$total) > 0.3 ? '⚠️ 负面舆情偏高，建议关注' : '✅ 舆情总体正常';
        include 'sentiment-report.php';
        exit;
    }
}

$topics = sent_topics();
$settings = sent_settings();
// 各主题采集情况
$topicStats = [];
foreach ($topics as $t) {
    $cnt = 0; $pos=0; $neg=0;
    try {
        $cnt = (int)Database::query("SELECT COUNT(*) as c FROM sentiment_results WHERE topic_id = ?", [$t['id']])[0]['c'];
        $neg = (int)Database::query("SELECT COUNT(*) as c FROM sentiment_results WHERE topic_id = ? AND sentiment='负面'", [$t['id']])[0]['c'];
    } catch (Exception $e) {}
    $topicStats[$t['id']] = ['count'=>$cnt, 'negative'=>$neg];
}

admin_header('舆情监测');
?>
<div class="admin-layout">
  <?php admin_sidebar('sentiment'); ?>
  <div class="main">
    <h1>📡 舆情监测</h1>
    <p class="sub">多源搜索 + AI 洞察 · 自动生成舆情报告 · 参考 BettaFish 微舆思路</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <button class="btn btn-primary" onclick="document.getElementById('addTopicBox').style.display='block'">➕ 添加监控主题</button>
      <a href="?tab=settings" class="btn btn-ghost">⚙️ 搜索源设置</a>
    </div>

    <!-- 添加主题 -->
    <div class="card" id="addTopicBox" style="display:none">
      <h2>➕ 添加监控主题</h2>
      <form method="post" style="display:flex;gap:8px">
        <?= csrf_field() ?>
        <input type="text" name="topic_name" placeholder="如：OpenFlow / 网站增长 / GEO / 公司名" required style="flex:1;padding:10px;border:1.5px solid var(--border);border-radius:8px">
        <button type="submit" name="add_topic" class="btn btn-primary">添加</button>
      </form>
    </div>

    <!-- 搜索源设置 -->
    <?php if (isset($_GET['tab']) && $_GET['tab'] === 'settings'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🔍 搜索源配置</h2>
        <div class="field-row">
          <div class="field"><label>Bing Web Search API Key <span class="hint">· 推荐</span></label><input type="password" name="bing_key" value="<?=htmlspecialchars($settings['bing_key'] ?? '')?>" placeholder="微软 Bing Search API Key"></div>
          <div class="field"><label>启用搜索源</label>
            <div style="display:flex;gap:14px;margin-top:8px">
              <label style="font-size:13px;display:flex;align-items:center;gap:5px"><input type="checkbox" name="src_bing" value="1" <?=in_array('bing', $settings['sources'] ?? [])?'checked':''?> style="width:15px;height:15px"> Bing</label>
              <label style="font-size:13px;display:flex;align-items:center;gap:5px"><input type="checkbox" name="src_baidu" value="1" <?=in_array('baidu', $settings['sources'] ?? [])?'checked':''?> style="width:15px;height:15px"> 百度</label>
              <label style="font-size:13px;display:flex;align-items:center;gap:5px"><input type="checkbox" name="src_rss" value="1" <?=in_array('rss', $settings['sources'] ?? [])?'checked':''?> style="width:15px;height:15px"> RSS</label>
            </div>
          </div>
        </div>
        <p class="text-sm text-muted">Bing Key 到 <a href="https://www.microsoft.com/en-us/bing/apis/bing-web-search-api" target="_blank" rel="noopener" style="color:var(--accent)">Microsoft 免费申请</a>（每月免费额度）</p>
        <button type="submit" name="save_settings" class="btn btn-primary">保存设置</button>
      </div>
    </form>
    <?php endif; ?>

    <!-- 主题列表 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">🎯 监控主题</h2>
      <table>
        <thead><tr><th>主题</th><th>已采集</th><th>负面</th><th>最近扫描</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($topics)): ?><tr><td colspan="5" class="empty">暂无监控主题，点击上方添加</td></tr><?php endif; ?>
          <?php foreach ($topics as $t): $st = $topicStats[$t['id']] ?? ['count'=>0,'negative'=>0]; ?>
          <tr>
            <td><strong><?=htmlspecialchars($t['name'])?></strong>
              <?php if (!empty($t['keywords'])): ?><div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars(implode('、', array_slice($t['keywords'],0,3)))?></div><?php endif; ?>
            </td>
            <td><?=$st['count']?></td>
            <td><?php if ($st['negative'] > 0): ?><span class="badge badge-red" style="font-size:11px"><?=$st['negative']?></span><?php else: ?>0<?php endif; ?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($t['last_scan'] ?: '未采集')?></td>
            <td style="white-space:nowrap">
              <a href="?scan=<?=urlencode($t['id'])?>" class="btn btn-primary btn-sm">⚡ 采集</a>
              <a href="?report=<?=urlencode($t['id'])?>" class="btn btn-ghost btn-sm">📊 报告</a>
              <a href="?delete=<?=urlencode($t['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('删除该主题及数据?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
