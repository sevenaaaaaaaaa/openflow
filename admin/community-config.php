<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('community-config');

$configFile = DATA_DIR . '/community.json';
$config = json_read($configFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $academy = $config['academy'] ?? [];
    $academy = [
        'downloads_enabled' => isset($_POST['acd_downloads']),
        'podcasts_enabled' => isset($_POST['acd_podcasts']),
        'videos_enabled' => isset($_POST['acd_videos']),
        'download_count' => max(0, (int)($_POST['acd_download_count'] ?? 4)),
        'podcast_count' => max(0, (int)($_POST['acd_podcast_count'] ?? 4)),
        'video_count' => max(0, (int)($_POST['acd_video_count'] ?? 4)),
    ];
    $config = [
        'featured_article' => $_POST['featured_article'] ?? '',
        'featured_event' => $_POST['featured_event'] ?? '',
        'show_events_section' => isset($_POST['show_events_section']),
        'floors' => [
            'insight' => ['enabled' => isset($_POST['floor_insight']), 'title' => $_POST['floor_insight_title'] ?? '增长洞察', 'desc' => $_POST['floor_insight_desc'] ?? '', 'categories' => $_POST['floor_insight_categories'] ?? ['insight']],
            'leadership' => ['enabled' => isset($_POST['floor_leadership']), 'title' => $_POST['floor_leadership_title'] ?? '内容与 SEO 实践', 'desc' => $_POST['floor_leadership_desc'] ?? '', 'categories' => $_POST['floor_leadership_categories'] ?? ['leadership']],
            'ai_ops' => ['enabled' => isset($_POST['floor_ai_ops']), 'title' => $_POST['floor_ai_ops_title'] ?? 'AI 运营实践', 'desc' => $_POST['floor_ai_ops_desc'] ?? '', 'categories' => $_POST['floor_ai_ops_categories'] ?? ['ai_ops']],
            'industry' => ['enabled' => isset($_POST['floor_industry']), 'title' => $_POST['floor_industry_title'] ?? '行业实践', 'desc' => $_POST['floor_industry_desc'] ?? '', 'categories' => $_POST['floor_industry_categories'] ?? ['industry']],
        ],
        'hot_read_count' => (int)($_POST['hot_read_count'] ?? 5),
        'show_report_section' => isset($_POST['show_report_section']),
        'academy' => $academy,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    json_write($configFile, $config);
    $message = '配置已保存';
}

$articles = get_articles();
$publishedArticles = array_values(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));
$allArticleCats = get_categories('article');
$acd = $config['academy'] ?? [];

admin_header('Academy 内容首页配置');
?>
<div class="admin-layout">
  <?php admin_sidebar('community-config'); ?>
  <div class="main">
    <h1> Academy 内容首页配置</h1>
    <p class="sub">配置 Academy 统一内容首页（文章 / 资料下载 / 播客 / 视频教程）的楼层与推荐</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🔝 头条推荐</h2>
        <div class="field">
          <label>推荐文章（编辑精选）</label>
          <select name="featured_article">
            <option value="">— 不设置 —</option>
            <?php foreach ($publishedArticles as $a): ?>
            <option value="<?=htmlspecialchars($a['id'])?>" <?=($config['featured_article']??'')===$a['id']?'selected':''?>><?=htmlspecialchars($a['title'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>推荐活动</label>
          <select name="featured_event">
            <option value="">— 不设置 —</option>
            <?php foreach (json_read(DATA_DIR . '/events/index.json') as $ev): if (($ev['status']??'') !== 'published') continue; ?>
            <option value="<?=htmlspecialchars($ev['id'])?>" <?=($config['featured_event']??'')===$ev['id']?'selected':''?>><?=htmlspecialchars($ev['title'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="show_events_section" value="1" <?=($config['show_events_section']??false)?'checked':''?> style="width:18px;height:18px">在社区首页显示活动区域</label></div>
      </div>

      <div class="card">
        <h2>📊 内容楼层</h2>
        <p class="text-sm text-muted mb-4">控制每个楼层在社区首页的显示</p>
        <?php
        $floorDefs = [
            'insight' => ['label' => '增长洞察', 'color' => '#ddf0d6'],
            'leadership' => ['label' => '内容与 SEO 实践', 'color' => '#d9eaf6'],
            'ai_ops' => ['label' => 'AI 运营实践', 'color' => '#fef08a33'],
            'industry' => ['label' => '行业实践', 'color' => '#fecdd333'],
        ];
        foreach ($floorDefs as $fk => $fv):
          $floor = $config['floors'][$fk] ?? [];
          $enabled = $floor['enabled'] ?? true;
          $selCats = $floor['categories'] ?? [$fk]; // 默认取同名分类
        ?>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px;margin-bottom:12px;border-left:4px solid <?=$fv['color']?>">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px">
            <input type="checkbox" name="floor_<?=$fk?>" value="1" <?=$enabled?'checked':''?> style="width:18px;height:18px">
            <strong style="font-size:15px"><?=$fv['label']?></strong>
          </label>
          <div class="field-row">
            <div class="field"><label>标题</label><input type="text" name="floor_<?=$fk?>_title" value="<?=htmlspecialchars($floor['title'] ?? $fv['label'])?>"></div>
            <div class="field"><label>描述</label><input type="text" name="floor_<?=$fk?>_desc" value="<?=htmlspecialchars($floor['desc'] ?? '')?>" placeholder="楼层描述"></div>
          </div>
          <div class="field" style="margin-top:8px;margin-bottom:0">
            <label>拉取分类 <span class="hint">· 勾选的文章分类将显示在本楼层</span></label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
              <?php foreach ($allArticleCats as $ac):
                $checked = in_array($ac['key'], $selCats);
              ?>
              <label style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:999px;background:<?=$checked?'#ddf0d6':'var(--surface)'?>;font-size:12px;cursor:pointer">
                <input type="checkbox" name="floor_<?=$fk?>_categories[]" value="<?=htmlspecialchars($ac['key'])?>" <?=$checked?'checked':''?> style="width:14px;height:14px">
                <?=htmlspecialchars($ac['name'])?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <h2>⚙️ 其他设置</h2>
        <div class="field-row">
          <div class="field"><label>热读榜显示数量</label><input type="number" name="hot_read_count" value="<?=htmlspecialchars($config['hot_read_count'] ?? 5)?>" min="3" max="20"></div>
          <div class="field">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:24px">
              <input type="checkbox" name="show_report_section" value="1" <?=($config['show_report_section'] ?? true)?'checked':''?> style="width:18px;height:18px">
              显示报告下载区域
            </label>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>🎓 Academy 楼层</h2>
        <p class="text-sm text-muted mb-4">控制 Academy 内容学院首页的楼层与显示条数（文章楼层见上方「内容楼层」）</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
          <div style="padding:16px;background:var(--surface-2);border-radius:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="acd_downloads" value="1" <?=($acd['downloads_enabled']??true)?'checked':''?> style="width:17px;height:17px"><strong>📄 资料下载楼层</strong></label>
            <div class="field" style="margin-bottom:0"><label>显示条数</label><input type="number" name="acd_download_count" value="<?=$acd['download_count']??4?>" min="0" max="8" style="width:80px"></div>
          </div>
          <div style="padding:16px;background:var(--surface-2);border-radius:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="acd_podcasts" value="1" <?=($acd['podcasts_enabled']??true)?'checked':''?> style="width:17px;height:17px"><strong>🎙 播客楼层</strong></label>
            <div class="field" style="margin-bottom:0"><label>显示条数</label><input type="number" name="acd_podcast_count" value="<?=$acd['podcast_count']??4?>" min="0" max="8" style="width:80px"></div>
          </div>
          <div style="padding:16px;background:var(--surface-2);border-radius:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="acd_videos" value="1" <?=($acd['videos_enabled']??true)?'checked':''?> style="width:17px;height:17px"><strong>🎬 视频教程楼层</strong></label>
            <div class="field" style="margin-bottom:0"><label>显示条数</label><input type="number" name="acd_video_count" value="<?=$acd['video_count']??4?>" min="0" max="8" style="width:80px"></div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">保存配置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
