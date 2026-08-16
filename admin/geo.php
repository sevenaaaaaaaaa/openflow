<?php
/**
 * GEO 管理 — 话题监控 / AI 生成 / 自动提交
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/GeoSystem.php';
require_login();
require_perm('settings');

$settings = geo_settings();
$sources = geo_sources();
$topics = geo_get_topics();
$message = '';
$error = '';

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    $settings['enabled'] = isset($_POST['enabled']);
    $settings['rss_enabled'] = isset($_POST['rss_enabled']);
    $settings['ai_enabled'] = isset($_POST['ai_enabled']);
    $settings['trends_enabled'] = isset($_POST['trends_enabled']);
    $settings['trends_provider'] = $_POST['trends_provider'] ?? '';
    $settings['trends_api_key'] = trim($_POST['trends_api_key'] ?? '');
    $settings['auto_submit'] = isset($_POST['auto_submit']);
    $settings['bing_api_key'] = trim($_POST['bing_api_key'] ?? '');
    $settings['baidu_token'] = trim($_POST['baidu_token'] ?? '');
    geo_save_settings($settings);
    $message = 'GEO 设置已保存';
}

// 保存 RSS 源
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sources'])) {
    csrf_verify();
    $sources = [];
    foreach (($_POST['src_name'] ?? []) as $i => $sn) {
        if (empty(trim($sn))) continue;
        $sources[] = ['id'=>($_POST['src_id'][$i] ?? '') ?: 'src_'.substr(bin2hex(random_bytes(4)),0,6), 'name'=>trim($sn), 'url'=>trim($_POST['src_url'][$i] ?? ''), 'enabled'=>isset($_POST['src_enabled'][$i])];
    }
    geo_save_sources($sources);
    $message = 'RSS 源已保存';
}

// 抓取 + AI 提炼
$extracted = [];
if (isset($_GET['extract'])) {
    $items = geo_fetch_all();
    $extracted = geo_ai_extract_topics($items);
    if (empty($extracted)) $error = 'AI 未提炼出话题，请检查 AI 供应商配置';
}

// 保存提炼的话题
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_topics'])) {
    csrf_verify();
    foreach (($_POST['topic_name'] ?? []) as $i => $tn) {
        if (empty(trim($tn))) continue;
        geo_add_topic(['topic'=>trim($tn), 'angle'=>$_POST['topic_angle'][$i] ?? '', 'why'=>$_POST['topic_why'][$i] ?? '']);
    }
    $message = '话题已加入选题库';
    header('Location: /xmp/geo');
    exit;
}

// AI 生成文章（从话题库）
if (isset($_GET['generate'])) {
    $topic = null;
    foreach ($topics as $t) if ($t['id'] === $_GET['generate']) { $topic = $t; break; }
    if ($topic) {
        $article = geo_ai_generate_article($topic);
        if ($article) {
            // 创建文章草稿（进入内容审核）
            $a = [
                'id' => 'article_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(4)),0,8),
                'title' => $article['title'],
                'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u','-',$article['title']),
                'content' => $article['content'],
                'editor_mode' => 'richtext',
                'category' => 'insight',
                'tags' => [],
                'cover' => '',
                'author' => 'OpenFlow AI 助手',
                'status' => 'draft',
                'seo_title' => $article['title'],
                'seo_desc' => $article['excerpt'],
                'seo_keywords' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'review_status' => 'pending',
            ];
            $all = get_articles();
            $all[] = $a;
            json_write(ARTICLES_DIR . '/index.json', $all);
            notify('GEO', 'AI 生成文章：'.$article['title'], 'AI 生成初稿，待审核', 'admin/reviews.php?type=article');
            flash('success', 'AI 已生成文章并进入审核：'.$article['title']);
        } else {
            flash('error', 'AI 生成失败，请检查 AI 供应商');
        }
    }
    header('Location: /xmp/geo');
    exit;
}

// 删除话题
if (isset($_GET['delete_topic'])) {
    $topics = array_values(array_filter($topics, fn($t) => $t['id'] !== $_GET['delete_topic']));
    geo_save_topics($topics);
    header('Location: /xmp/geo');
    exit;
}

admin_header('GEO 话题监控');
?>
<style>
.geo-topic{border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:10px;background:var(--surface)}
.geo-topic h3{font-size:15px;font-weight:700;margin-bottom:6px}
.geo-topic .angle{font-size:13px;color:var(--text-2);margin-bottom:4px}
.geo-topic .why{font-size:12px;color:var(--text-3)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('geo'); ?>
  <div class="main">
    <h1>🧠 GEO 话题监控</h1>
    <p class="sub">RSS 聚合行业动态 → AI 提炼热点话题 → 生成文章 → 自动提交搜索引擎</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 设置 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>⚙️ GEO 设置</h2>
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:12px">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=$settings['enabled']?'checked':''?> style="width:16px;height:16px"> 启用 GEO</label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="rss_enabled" value="1" <?=$settings['rss_enabled']?'checked':''?> style="width:16px;height:16px"> RSS 聚合</label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="ai_enabled" value="1" <?=$settings['ai_enabled']?'checked':''?> style="width:16px;height:16px"> AI 提炼/生成</label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="auto_submit" value="1" <?=$settings['auto_submit']?'checked':''?> style="width:16px;height:16px"> 发布后自动提交</label>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:10px">
          <p class="text-sm text-muted mb-4" style="font-size:13px">📈 选配：百度指数 / Google Trends</p>
          <div class="field-row">
            <div class="field"><label>趋势源 <span class="hint">· 选配</span></label><select name="trends_provider"><option value="">不启用</option><option value="baidu" <?=$settings['trends_provider']==='baidu'?'selected':''?>>百度指数</option><option value="google" <?=$settings['trends_provider']==='google'?'selected':''?>>Google Trends</option></select></div>
            <div class="field"><label>趋势 API Key</label><input type="password" name="trends_api_key" value="<?=htmlspecialchars($settings['trends_api_key'])?>" placeholder="选配平台的 API Key"></div>
          </div>
          <div class="field-row">
            <div class="field"><label>必应 Webmaster Key <span class="hint">· 选配</span></label><input type="password" name="bing_api_key" value="<?=htmlspecialchars($settings['bing_api_key'])?>" placeholder="自动提交必应"></div>
            <div class="field"><label>百度站长 Token <span class="hint">· 选配</span></label><input type="password" name="baidu_token" value="<?=htmlspecialchars($settings['baidu_token'])?>" placeholder="自动提交百度"></div>
          </div>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary" style="margin-top:12px">保存设置</button>
      </div>
    </form>

    <!-- RSS 源 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>📡 RSS 监控源</h2>
        <div id="srcList">
          <?php foreach ($sources as $si => $src): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
            <input type="hidden" name="src_id[]" value="<?=htmlspecialchars($src['id'])?>">
            <input type="text" name="src_name[]" value="<?=htmlspecialchars($src['name'])?>" placeholder="源名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="text" name="src_url[]" value="<?=htmlspecialchars($src['url'])?>" placeholder="RSS URL" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="src_enabled[]" value="1" <?=!empty($src['enabled'])?'checked':''?> style="width:15px;height:15px">启用</label>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addSource()">+ 添加源</button>
        <button type="submit" name="save_sources" class="btn btn-ghost btn-sm">保存源</button>
        <div style="margin-top:12px">
          <a href="?extract=1" class="btn btn-primary">⚡ 抓取并 AI 提炼话题</a>
        </div>
      </div>
    </form>

    <!-- AI 提炼结果 -->
    <?php if (!empty($extracted)): ?>
    <div class="card">
      <h2>🧠 AI 提炼的热点话题</h2>
      <form method="post">
        <?= csrf_field() ?>
        <?php foreach ($extracted as $i => $t): ?>
        <div style="padding:14px;background:var(--surface-2);border-radius:12px;margin-bottom:10px">
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
            <input type="text" name="topic_name[]" value="<?=htmlspecialchars($t['topic'] ?? '')?>" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-weight:600">
          </div>
          <div style="display:flex;gap:8px">
            <input type="text" name="topic_angle[]" value="<?=htmlspecialchars($t['angle'] ?? '')?>" placeholder="切入角度" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
            <input type="text" name="topic_why[]" value="<?=htmlspecialchars($t['why'] ?? '')?>" placeholder="为什么值得写" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
          </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" name="save_topics" class="btn btn-primary">加入选题库</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- 选题库 -->
    <div class="card">
      <h2>🗂️ 选题库（<?=count($topics)?>）</h2>
      <?php if (empty($topics)): ?><div class="empty" style="padding:24px">暂无话题，先抓取 RSS 提炼</div><?php endif; ?>
      <?php foreach (array_reverse($topics) as $t): ?>
      <div class="geo-topic">
        <h3><?=htmlspecialchars($t['topic'])?></h3>
        <div class="angle">💡 <?=htmlspecialchars($t['angle'] ?? '')?></div>
        <div class="why"><?=htmlspecialchars($t['why'] ?? '')?></div>
        <div style="margin-top:8px">
          <a href="?generate=<?=urlencode($t['id'])?>" class="btn btn-primary btn-sm">🤖 AI 生成文章</a>
          <a href="?delete_topic=<?=urlencode($t['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('删除该话题?')">删除</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
function addSource() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap';
  d.innerHTML = '<input type="hidden" name="src_id[]" value="src_' + Date.now() + '"><input type="text" name="src_name[]" placeholder="源名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="text" name="src_url[]" placeholder="RSS URL" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="src_enabled[]" value="1" checked style="width:15px;height:15px">启用</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('srcList').appendChild(d);
}
</script>
<?php admin_footer(); ?>
