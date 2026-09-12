<?php
/**
 * GEO 管理 — 话题监控 / AI 生成 / 自动提交
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/GeoSystem.php';
require_once __DIR__ . '/../lib/GeoEntities.php';
require_once __DIR__ . '/../lib/GeoCompetitor.php';
require_once __DIR__ . '/../lib/GeoCitable.php';
require_login();
require_perm('settings');

$settings = geo_settings();
$sources = geo_sources();
$topics = geo_get_topics();
$message = '';
$error = '';

// 实体图 / 竞品 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['geo_panel'])) {
    csrf_verify();
    $panel = (string)$_POST['geo_panel'];
    if ($panel === 'build_entities') { $g = geo_entities_build(); $message = '实体图已重建：' . count($g['nodes']) . ' 个实体 / ' . count($g['edges']) . ' 条关系'; }
    elseif ($panel === 'add_competitor') { $r = geo_competitor_add((string)($_POST['domain'] ?? ''), (string)($_POST['name'] ?? '')); if (!$r['ok']) $error = $r['error']; else $message = '竞品已添加'; }
    elseif ($panel === 'remove_competitor') { geo_competitor_remove((string)($_POST['domain'] ?? '')); $message = '竞品已移除'; }
}
$entReport = geo_entities_report();
$compRows = geo_competitor_scan();

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    $settings['enabled'] = isset($_POST['enabled']);
    $settings['rss_enabled'] = isset($_POST['rss_enabled']);
    $settings['ai_enabled'] = isset($_POST['ai_enabled']);
    $settings['trends_enabled'] = isset($_POST['trends_enabled']);
    $settings['trends_provider'] = $_POST['trends_provider'] ?? '';
    $settings['trends_api_key'] = trim($_POST['trends_api_key'] ?? '');
    $settings['search_api_enabled'] = isset($_POST['search_api_enabled']);
    // 搜索 API 供应商配置
    $providers = [];
    foreach (($_POST['sp_type'] ?? []) as $i => $st) {
        if (empty(trim($st))) continue;
        $providers[] = [
            'id' => ($_POST['sp_id'][$i] ?? '') ?: 'sp_' . substr(bin2hex(random_bytes(3)), 0, 5),
            'name' => trim($_POST['sp_name'][$i] ?? ''),
            'type' => trim($st),
            'api_key' => trim($_POST['sp_key'][$i] ?? ''),
            'base_url' => trim($_POST['sp_url'][$i] ?? ''),
            'search_engine_id' => trim($_POST['sp_seid'][$i] ?? ''),
            'enabled' => isset($_POST['sp_enabled'][$i]),
        ];
    }
    $settings['search_providers'] = $providers;
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
    <h1>GEO 话题监控</h1>
    <p class="sub">RSS / 搜索 API 聚合 → AI 提炼热点话题 → 生成文章 → 自动提交搜索引擎</p>
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
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="search_api_enabled" value="1" <?=!empty($settings['search_api_enabled'])?'checked':''?> style="width:16px;height:16px"> 搜索 API 聚合</label>
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

    <!-- 搜索 API 供应商 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>  搜索 API 供应商</h2>
        <p class="text-sm text-muted mb-4">配置搜索 API，采集全网渠道数据并输出结构化结论。支持 SerpAPI / Google Custom Search / Bing Search API / 百度 / 自定义。</p>
        <div id="spList">
          <?php $searchProviders = $settings['search_providers'] ?? []; foreach ($searchProviders as $si => $sp): ?>
          <div style="padding:12px;margin-bottom:8px;background:var(--surface-2);border-radius:10px">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
              <input type="hidden" name="sp_id[]" value="<?=htmlspecialchars($sp['id'] ?? '')?>">
              <input type="text" name="sp_name[]" value="<?=htmlspecialchars($sp['name'] ?? '')?>" placeholder="供应商名称" style="width:140px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <select name="sp_type[]" style="padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
                <option value="serpapi" <?=($sp['type'] ?? '')==='serpapi'?'selected':''?>>SerpAPI</option>
                <option value="google_custom_search" <?=($sp['type'] ?? '')==='google_custom_search'?'selected':''?>>Google Custom Search</option>
                <option value="bing_search" <?=($sp['type'] ?? '')==='bing_search'?'selected':''?>>Bing Search API</option>
                <option value="baidu_search" <?=($sp['type'] ?? '')==='baidu_search'?'selected':''?>>百度搜索 API</option>
                <option value="custom" <?=($sp['type'] ?? '')==='custom'?'selected':''?>>自定义 API</option>
              </select>
              <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="sp_enabled[]" value="1" <?=!empty($sp['enabled'])?'checked':''?> style="width:15px;height:15px">启用</label>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div.card').querySelector('#spList').removeChild(this.closest('div[style*=background]'))">✕</button>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <input type="password" name="sp_key[]" value="<?=htmlspecialchars($sp['api_key'] ?? '')?>" placeholder="API Key" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <input type="text" name="sp_url[]" value="<?=htmlspecialchars($sp['base_url'] ?? '')?>" placeholder="自定义 API 地址（custom 类型必填）" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <input type="text" name="sp_seid[]" value="<?=htmlspecialchars($sp['search_engine_id'] ?? '')?>" placeholder="Google CSE ID（仅 Google Custom Search 必填）" style="width:220px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addSearchProvider()">+ 添加供应商</button>
        <button type="submit" name="save_settings" class="btn btn-ghost btn-sm">保存供应商配置</button>
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
          <a href="?delete_topic=<?=urlencode($t['id'])?>" class="btn btn-danger btn-sm" data-confirm="删除该话题?">删除</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <!-- 实体图 -->
    <div class="card">
      <h2>🕸️ 实体图 <span class="hint">· 品牌/产品/人物/话题 的一致实体与共现关系</span></h2>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
        <span class="text-sm text-muted">共 <?=$entReport['total']?> 个实体 · <?=$entReport['links']?> 条关系<?=($entReport['built_at'] ? ' · 构建于 ' . htmlspecialchars($entReport['built_at']) : ' · 尚未构建')?></span>
        <form method="post" style="margin-left:auto" data-no-guard><?= csrf_field() ?><input type="hidden" name="geo_panel" value="build_entities"><button class="btn btn-primary btn-sm">重建实体图</button></form>
      </div>
      <?php if ($entReport['total']): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
        <?php foreach ($entReport['by_type'] as $type => $n): ?><span class="badge badge-gray"><?=htmlspecialchars(['brand'=>'品牌','product'=>'产品','person'=>'人物','topic'=>'话题'][$type] ?? $type)?> <?=$n?></span><?php endforeach; ?>
      </div>
      <table><thead><tr><th>实体</th><th>类型</th><th>提及</th><th>出现位置</th></tr></thead><tbody>
        <?php foreach ($entReport['top_nodes'] as $n): ?>
        <tr><td><b><?=htmlspecialchars($n['name'])?></b></td><td class="text-sm text-muted"><?=htmlspecialchars(['brand'=>'品牌','product'=>'产品','person'=>'人物','topic'=>'话题'][$n['type']] ?? $n['type'])?></td><td class="mono"><?=(int)$n['mentions']?></td><td class="text-sm text-muted"><?=htmlspecialchars(implode('、', array_slice((array)($n['sources'] ?? []), 0, 3)))?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <div class="text-xs text-muted" style="margin-top:8px">Top 关系：<?php foreach ($entReport['top_edges'] as $e) echo htmlspecialchars(substr($e['a'], strpos($e['a'], ':') + 1) . '↔' . substr($e['b'], strpos($e['b'], ':') + 1) . '（' . $e['weight'] . '）　'); ?></div>
      <?php else: ?><div class="empty" style="padding:16px">点「重建实体图」从内容里抽取实体与关系。</div><?php endif; ?>
    </div>

    <!-- 竞品引用 -->
    <div class="card">
      <h2>⚔️ 竞品引用分析 <span class="hint">· 同一话题里谁被更多提及（share of voice）</span></h2>
      <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px" data-no-guard>
        <?= csrf_field() ?><input type="hidden" name="geo_panel" value="add_competitor">
        <input class="inp" name="domain" placeholder="竞品域名，如 competitor.com" style="flex:1;min-width:200px">
        <input class="inp" name="name" placeholder="显示名（可选）" style="width:160px">
        <button class="btn btn-primary btn-sm">添加竞品</button>
      </form>
      <?php if (count($compRows) > 1): ?>
      <table><thead><tr><th>主体</th><th>域名</th><th>提及</th><th>话题份额</th><th></th></tr></thead><tbody>
        <?php foreach ($compRows as $r): ?>
        <tr>
          <td><b><?=htmlspecialchars($r['name'])?></b><?=$r['self'] ? ' <span class="badge badge-green">本品牌</span>' : ''?></td>
          <td class="text-sm text-muted"><?=htmlspecialchars($r['domain'])?></td>
          <td class="mono"><?=(int)$r['mentions']?></td>
          <td><div style="background:var(--hover);border-radius:99px;height:8px;width:120px;overflow:hidden;display:inline-block;vertical-align:middle"><div style="height:100%;width:<?=(float)$r['share']?>%;background:var(--accent)"></div></div> <span class="text-sm"><?=(float)$r['share']?>%</span></td>
          <td><?php if (!$r['self']): ?><form method="post" data-no-guard><?= csrf_field() ?><input type="hidden" name="geo_panel" value="remove_competitor"><input type="hidden" name="domain" value="<?=htmlspecialchars($r['domain'])?>"><button class="btn btn-ghost btn-sm" style="color:var(--danger)">移除</button></form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php else: ?><div class="empty" style="padding:16px">添加竞品域名后，统计站内内容与选题库中的提及份额。</div><?php endif; ?>
    </div>

    <!-- 可引用改写 -->
    <div class="card">
      <h2>✂️ 可引用格式改写 <span class="hint">· 把文章重排成 AI 引擎愿直接引用的结构（结论+关键事实+Q&A）</span></h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input class="inp" id="gcId" placeholder="文章 id" style="width:220px">
        <label class="text-sm" style="display:flex;align-items:center;gap:5px"><input type="checkbox" id="gcAi" checked> 用 AI 重写</label>
        <button type="button" class="btn btn-primary btn-sm" onclick="gcPreview()">生成预览</button>
        <span id="gcMsg" class="text-sm text-muted"></span>
      </div>
      <div id="gcOut" style="margin-top:12px"></div>
    </div>
  </div>
</div>
<script>
var GC_CSRF = <?=json_encode(csrf_token())?>;
function gcPreview() {
  var id = document.getElementById('gcId').value.trim();
  var ai = document.getElementById('gcAi').checked ? '1' : '';
  var msg = document.getElementById('gcMsg');
  if (!id) { msg.textContent = '填文章 id'; return; }
  msg.textContent = '生成中…';
  var fd = new FormData(); fd.append('action', 'preview'); fd.append('id', id); if (ai) fd.append('ai', '1');
  fetch('/api/geo-citable.php', { method: 'POST', headers: {'X-CSRF-Token': GC_CSRF}, body: fd })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { msg.textContent = d.error || '失败'; return; }
      msg.textContent = (d.source === 'ai' ? 'AI 生成' : '规则抽取');
      window.__gcBlocks = d.blocks;
      document.getElementById('gcOut').innerHTML = d.html + '<div style="margin-top:10px"><button class="btn btn-primary btn-sm" onclick="gcApply()">写回正文</button></div>';
    }).catch(function () { msg.textContent = '网络错误'; });
}
function gcApply() {
  var id = document.getElementById('gcId').value.trim();
  var fd = new FormData(); fd.append('action', 'apply'); fd.append('id', id); fd.append('blocks', JSON.stringify(window.__gcBlocks || {}));
  fetch('/api/geo-citable.php', { method: 'POST', headers: {'X-CSRF-Token': GC_CSRF}, body: fd })
    .then(function (r) { return r.json(); })
    .then(function (d) { document.getElementById('gcMsg').textContent = d.ok ? '已写回正文' : (d.error || '失败'); });
}
function addSource() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap';
  d.innerHTML = '<input type="hidden" name="src_id[]" value="src_' + Date.now() + '"><input type="text" name="src_name[]" placeholder="源名称" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="text" name="src_url[]" placeholder="RSS URL" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="src_enabled[]" value="1" checked style="width:15px;height:15px">启用</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('srcList').appendChild(d);
}
function addSearchProvider() {
  var d = document.createElement('div');
  d.style.cssText = 'padding:12px;margin-bottom:8px;background:var(--surface-2);border-radius:10px';
  d.innerHTML = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px"><input type="hidden" name="sp_id[]" value="sp_' + Date.now() + '"><input type="text" name="sp_name[]" placeholder="供应商名称" style="width:140px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><select name="sp_type[]" style="padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="serpapi">SerpAPI</option><option value="google_custom_search">Google Custom Search</option><option value="bing_search">Bing Search API</option><option value="baidu_search">百度搜索 API</option><option value="custom">自定义 API</option></select><label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" name="sp_enabled[]" value="1" checked style="width:15px;height:15px">启用</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div[style*=padding]\').remove()">✕</button></div><div style="display:flex;gap:8px;flex-wrap:wrap"><input type="password" name="sp_key[]" placeholder="API Key" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="text" name="sp_url[]" placeholder="自定义 API 地址（custom 类型必填）" style="flex:1;min-width:200px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><input type="text" name="sp_seid[]" placeholder="Google CSE ID（仅 Google Custom Search 必填）" style="width:220px;padding:7px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"></div>';
  document.getElementById('spList').appendChild(d);
}
</script>
<?php admin_footer(); ?>
