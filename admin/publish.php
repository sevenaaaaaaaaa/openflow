<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

$platforms = SocialPublisher::platforms();
$articles = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));
$message = '';
$error = '';

// 发布文章
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    csrf_verify();
    $articleId = $_POST['article_id'] ?? '';
    $article = get_article($articleId);
    $selPlatforms = $_POST['platforms'] ?? [];
    if (!$article) { $error = '请选择文章'; }
    elseif (empty($selPlatforms)) { $error = '请选择发布平台'; }
    else {
        $results = [];
        foreach ($selPlatforms as $p) $results[$p] = SocialPublisher::publish($article, $p);
        $ok = count(array_filter($results, fn($r) => $r['ok']));
        $message = "已发布到 {$ok}/" . count($results) . " 个平台";
        // 记录到发布日志
        SocialPublisher::log(['article_id' => $articleId, 'title' => $article['title'], 'platforms' => $selPlatforms], $results);
    }
}

// 定时发布
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule'])) {
    csrf_verify();
    $articleId = $_POST['article_id'] ?? '';
    $article = get_article($articleId);
    $selPlatforms = $_POST['platforms'] ?? [];
    $sendAt = $_POST['send_at'] ?? '';
    if (!$article || empty($selPlatforms) || empty($sendAt)) { $error = '请完善发布信息'; }
    else {
        SocialPublisher::schedule($article, $selPlatforms, $sendAt);
        $message = '定时发布已排入队列';
    }
}

// 取消定时
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    csrf_verify();
    SocialPublisher::cancelTask($_POST['id'] ?? '');
    $message = '定时任务已取消';
}

$log = SocialPublisher::recentLog();
$queue = SocialPublisher::queue();

// 分发统计
$publishStats = [];
foreach ($log as $l) {
    foreach (($l['platforms'] ?? []) as $p) {
        $publishStats[$p] = ($publishStats[$p] ?? 0) + 1;
    }
}

admin_header('内容分发');
?>
<div class="admin-layout">
  <?php admin_sidebar('publish'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 内容分发</h1>
      <div class="flex gap-2 ml-auto">
        <a href="content-calendar.php" class="btn btn-ghost btn-sm">内容日历</a>
        <?php if (CloudflareApi::configured()): ?>
        <a href="../admin/cloudflare.php?purge=1&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm" data-confirm="发布后清理 Cloudflare 缓存？">☁️ 清缓存</a>
        <?php endif; ?>
      </div>
    </div>
    <p class="sub">一键发布文章到多平台 · 平台内容变体 · 定时分发 · 分发记录</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 分发统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:20px">
      <div class="stat-card"><div class="num"><?=count($log)?></div><div class="label">累计发布</div></div>
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=count($queue)?></div><div class="label">待发布</div></div>
      <?php foreach ($platforms as $pk => $pv): if (($publishStats[$pk] ?? 0) > 0): ?>
      <div class="stat-card"><div class="num" style="color:var(--ok)"><?=$publishStats[$pk]?></div><div class="label"><?=$pv['name']?></div></div>
      <?php endif; endforeach; ?>
    </div>

    <!-- 发布表单 -->
    <div class="card" style="margin-bottom:24px">
      <h2>🚀 发布文章</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field">
            <label>选择文章</label>
            <select name="article_id" required style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px">
              <option value="">— 选择已发布文章 —</option>
              <?php foreach ($articles as $a): ?>
              <option value="<?=htmlspecialchars($a['id'])?>"><?=htmlspecialchars($a['title'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label>发布平台</label>
          <div style="display:flex;flex-wrap:wrap;gap:10px">
            <?php foreach ($platforms as $pk => $pv): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:13px">
              <input type="checkbox" name="platforms[]" value="<?=$pk?>" style="width:16px;height:16px">
              <?=$pv['name']?><?=!empty($pv['variant'])?' <span style="color:var(--text-3);font-size:11px">·自动变体</span>':''?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="flex gap-3" style="margin-top:12px">
          <button type="submit" name="publish" class="btn btn-primary" data-confirm="确认立即发布？">📤 立即发布</button>
          <div class="field" style="display:flex;align-items:center;gap:8px;margin:0">
            <input type="datetime-local" name="send_at" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px">
            <button type="submit" name="schedule" class="btn btn-ghost">⏰ 定时发布</button>
          </div>
        </div>
      </form>
    </div>

    <!-- 平台说明 -->
    <div class="card" style="margin-bottom:24px;padding:16px">
      <h2 style="margin-bottom:12px">🔌 平台说明</h2>
      <table style="width:100%">
        <thead><tr><th>平台</th><th>发布方式</th><th>说明</th></tr></thead>
        <tbody>
          <tr><td><strong>微信公众号</strong></td><td>自动群发</td><td>需在「企业微信/公众号设置」配置 AppID/Secret；图文需先上传素材获取 media_id</td></tr>
          <tr><td><strong>邮件推送</strong></td><td>自动群发</td><td>推送给 newsletter 订阅者，需配置 SMTP</td></tr>
          <tr><td><strong>知乎/小红书/LinkedIn/X/Facebook/B站</strong></td><td>生成分享内容</td><td>自动按平台生成标题/摘要变体 + 链接，复制到对应平台手动发布</td></tr>
        </tbody>
      </table>
    </div>

    <!-- 定时队列 -->
    <?php if ($queue): ?>
    <div class="card" style="margin-bottom:24px;padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">⏰ 定时发布队列</h2></div>
      <table>
        <thead><tr><th>文章</th><th>平台</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($queue as $t): if (($t['status']??'')==='sent') continue; ?>
          <tr>
            <td class="text-sm"><?=htmlspecialchars($t['title'] ?? '')?></td>
            <td><?=implode(', ', array_map(fn($p)=>$platforms[$p]['name']??$p, $t['platforms'] ?? []))?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($t['send_at'] ?? '')?></td>
            <td><span class="badge badge-yellow">待发布</span></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="cancel" value="1">
                <input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>">
                <button class="btn btn-ghost btn-sm" style="color:var(--danger)">取消</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- 发布记录 -->
    <?php if ($log): ?>
    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">🕘 发布记录</h2></div>
      <table>
        <thead><tr><th>时间</th><th>文章</th><th>平台</th><th>结果</th></tr></thead>
        <tbody>
        <?php foreach ($log as $l): ?>
          <tr>
            <td class="text-sm"><?=htmlspecialchars($l['time'] ?? '')?></td>
            <td class="text-sm"><?=htmlspecialchars($l['title'] ?? '')?></td>
            <td>
              <?php foreach ($l['platforms'] ?? [] as $p): ?>
              <span class="badge badge-gray" style="font-size:11px"><?=$platforms[$p]['name'] ?? $p?></span>
              <?php endforeach; ?>
            </td>
            <td>
              <?php foreach ($l['results'] ?? [] as $p => $r): ?>
              <span style="font-size:11px;margin-right:6px"><?=$platforms[$p]['name'] ?? $p?>: <?=$r['ok']?'✅':'❌'?></span>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
