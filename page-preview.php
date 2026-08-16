<?php
require_once __DIR__ . '/admin/config.php';

$page = req_str('page', 'index');
$allowed = ['index', 'about', 'capability', 'courses', 'flow-community'];
if (!in_array($page, $allowed)) $page = 'index';

$content = page_content($page);
$pageNames = ['index'=>'首页','about'=>'关于我们','capability'=>'产品','courses'=>'解决方案','flow-community'=>'Flow社区'];

// Fields that contain HTML (not escaped in preview)
$htmlFields = ['hero_subtitle', 'capability_card_1_desc', 'capability_card_2_desc', 'capability_card_3_desc',
    'solutions_subtitle', 'cases_subtitle', 'founder_intro', 'founder_middle', 'founder_approach',
    'founder_belief', 'founder_vision', 'company_intro', 'company_detail', 'wellq_desc', 'courses_desc',
    'platform_desc', 'banner_subtitle', 'cta_description', 'problem_description'];

function preview_val(array $content, string $key, int $maxLen = 0, bool $isHtml = false): string {
    $val = $content[$key] ?? '';
    if (empty($val)) return '<span style="color:var(--faint);font-style:italic">（空）</span>';
    if ($maxLen > 0 && mb_strlen(strip_tags($val)) > $maxLen) {
        $val = mb_substr(strip_tags($val), 0, $maxLen) . '…';
        $isHtml = false;
    }
    return $isHtml ? $val : htmlspecialchars($val);
}

$htmlFieldsSet = [];
foreach ($htmlFields as $h) $htmlFieldsSet[$h] = true;

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>预览 - <?=htmlspecialchars($pageNames[$page] ?? $page)?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,'Inter','PingFang SC','Noto Sans SC',system-ui,sans-serif;background:var(--bg);color:var(--fg);line-height:1.6;-webkit-font-smoothing:antialiased;padding:0}
.preview-bar{position:sticky;top:0;z-index:100;background:var(--surface);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:12px;font-size:14px}
.preview-bar .tag{padding:3px 10px;border-radius:999px;background:var(--accent);font-weight:600;font-size:12px}
.preview-content{max-width:900px;margin:0 auto;padding:40px 24px 80px}
.section{margin-bottom:24px;padding:24px;background:var(--surface);border-radius:16px;border:1px solid rgba(0,0,0,0.08)}
.section-label{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--faint);margin-bottom:8px;border-bottom:2px solid var(--accent);padding-bottom:4px;display:inline-block}
h1{font-size:32px;font-weight:700;letter-spacing:-.02em;line-height:1.2;margin-bottom:8px}
h2{font-size:20px;font-weight:600;margin-bottom:6px;margin-top:12px}
p,li{font-size:14px;color:#555;line-height:1.7;margin-bottom:6px}
.highlight{background:linear-gradient(90deg,#65a30d,#059669,#0284c7);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;font-weight:700}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:12px}
.stat-card{padding:14px;background:#ebe9e1;border-radius:12px}
.stat-card .num{font-size:30px;font-weight:800;background:linear-gradient(135deg,#65a30d,#059669,#0284c7);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.stat-card .label{font-size:12px;color:#6e6e6e;margin-top:2px}
.card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px}
.card-grid .c{padding:14px;background:#ebe9e1;border-radius:12px}
.card-grid .c strong{font-size:14px}
.card-grid .c p{font-size:12px;margin-top:4px}
.preview-missing{padding:24px;text-align:center;color:var(--faint);font-style:italic}
.preview-foot{text-align:center;padding:20px;color:var(--faint);font-size:12px}
.pill{display:inline-block;padding:3px 12px;border-radius:999px;background:#ebe9e1;font-size:12px;font-weight:500;margin:3px}
</style>
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
</head>
<body>

<div class="preview-bar">
  <span class="tag">预览</span>
  <span><?=htmlspecialchars($pageNames[$page] ?? $page)?></span>
  <span style="margin-left:auto;font-size:13px;color:#6e6e6e">简化渲染 · 完整效果以实际页面为准</span>
</div>

<div class="preview-content">

<?php if ($page === 'index'): ?>

  <div class="section">
    <div class="section-label">🏠 Hero 区域</div>
    <h1><?=preview_val($content, 'hero_title')?> <span class="highlight"><?=preview_val($content, 'hero_title_highlight')?></span></h1>
    <div><?=preview_val($content, 'hero_subtitle', 0, true)?></div>
    <div style="margin-top:8px"><span class="pill"><?=preview_val($content, 'hero_chip')?></span></div>
    <div style="margin-top:8px;font-size:13px;color:#999">信任: <?=preview_val($content, 'hero_trust_text')?> · <?=preview_val($content, 'hero_trust_brands')?></div>
  </div>

  <div class="section">
    <div class="section-label">⚠️ 问题陈述</div>
    <h2><?=preview_val($content, 'problem_title')?></h2>
    <p><?=preview_val($content, 'problem_description', 200)?></p>
    <div class="stat-grid">
      <div class="stat-card"><div class="num"><?=preview_val($content, 'problem_stat_1_num')?></div><div class="label"><?=preview_val($content, 'problem_stat_1_desc', 60)?></div></div>
      <div class="stat-card"><div class="num"><?=preview_val($content, 'problem_stat_2_num')?></div><div class="label"><?=preview_val($content, 'problem_stat_2_desc', 60)?></div></div>
      <div class="stat-card"><div class="num"><?=preview_val($content, 'problem_stat_3_num')?></div><div class="label"><?=preview_val($content, 'problem_stat_3_desc', 60)?></div></div>
    </div>
  </div>

  <div class="section">
    <div class="section-label">⚡ 核心能力</div>
    <h2><?=preview_val($content, 'capability_title')?></h2>
    <p><?=preview_val($content, 'capability_subtitle', 150)?></p>
    <div class="card-grid">
      <div class="c"><strong><?=preview_val($content, 'capability_card_1_title')?></strong><p><?=preview_val($content, 'capability_card_1_desc', 80)?></p></div>
      <div class="c"><strong><?=preview_val($content, 'capability_card_2_title')?></strong><p><?=preview_val($content, 'capability_card_2_desc', 80)?></p></div>
      <div class="c"><strong><?=preview_val($content, 'capability_card_3_title')?></strong><p><?=preview_val($content, 'capability_card_3_desc', 80)?></p></div>
    </div>
  </div>

  <div class="section">
    <div class="section-label">📊 数据指标</div>
    <div class="stat-grid">
      <div class="stat-card"><div class="num"><?=preview_val($content, 'data_count_1')?>+</div><div class="label"><?=preview_val($content, 'data_label_1')?></div></div>
      <div class="stat-card"><div class="num"><?=preview_val($content, 'data_count_2')?>+</div><div class="label"><?=preview_val($content, 'data_label_2')?></div></div>
      <div class="stat-card"><div class="num"><?=preview_val($content, 'data_count_3')?></div><div class="label"><?=preview_val($content, 'data_label_3')?></div></div>
      <div class="stat-card"><div class="num"><?=preview_val($content, 'data_count_4')?>+</div><div class="label"><?=preview_val($content, 'data_label_4')?></div></div>
    </div>
  </div>

  <div class="section">
    <div class="section-label">🎯 愿景 & CTA</div>
    <h2><?=preview_val($content, 'vision_title')?></h2>
    <p><span class="pill">使命: <?=preview_val($content, 'vision_mission')?></span> <span class="pill">价值观: <?=preview_val($content, 'vision_values')?></span></p>
    <p style="margin-top:8px">📞 <?=preview_val($content, 'cta_phone')?> · <?=preview_val($content, 'cta_title')?></p>
  </div>

<?php elseif ($page === 'about'): ?>

  <div class="section">
    <div class="section-label">👤 创始人寄语</div>
    <h2><?=preview_val($content, 'founder_quote')?></h2>
    <p><?=preview_val($content, 'founder_intro', 200)?></p>
    <p style="margin-top:8px">— <?=preview_val($content, 'founder_name')?>, <?=preview_val($content, 'founder_title')?></p>
  </div>

  <div class="section">
    <div class="section-label">🏢 公司简介</div>
    <p><?=preview_val($content, 'company_intro', 200)?></p>
  </div>

  <div class="section">
    <div class="section-label">🎯 使命愿景</div>
    <h2><?=preview_val($content, 'mission')?></h2>
    <p><span class="pill">🌏 <?=preview_val($content, 'vision_1')?></span> <span class="pill">🌏 <?=preview_val($content, 'vision_2')?></span></p>
    <p>价值观: <?=preview_val($content, 'values')?></p>
  </div>

<?php elseif ($page === 'capability'): ?>

  <div class="section">
    <div class="section-label">📌 页面横幅</div>
    <h2><?=preview_val($content, 'banner_title')?></h2>
    <p><?=preview_val($content, 'banner_subtitle', 200)?></p>
  </div>
  <div class="section">
    <div class="section-label">📝 内容引擎 + SEO/GEO</div>
    <h2><?=preview_val($content, 'wellq_title')?></h2>
    <p><?=preview_val($content, 'wellq_desc', 200)?></p>
  </div>
  <div class="section">
    <div class="section-label">🤖 AI Agent</div>
    <h2><?=preview_val($content, 'courses_title')?></h2>
    <p><?=preview_val($content, 'courses_desc', 200)?></p>
  </div>
  <div class="section">
    <div class="section-label">💻 智能平台</div>
    <h2><?=preview_val($content, 'platform_title')?></h2>
    <p><?=preview_val($content, 'platform_desc', 200)?></p>
  </div>

<?php elseif ($page === 'courses'): ?>

  <div class="section">
    <div class="section-label">📌 页面横幅</div>
    <h2><?=preview_val($content, 'banner_title')?></h2>
    <p><?=preview_val($content, 'banner_subtitle', 200)?></p>
  </div>
  <?php foreach ([['executive','👔 高管层'],['leadership','👥 管理层'],['employee','👤 员工层'],['newcomer','🌟 新员工']] as $sec): ?>
  <div class="section">
    <div class="section-label"><?=$sec[1]?></div>
    <h2><?=preview_val($content, $sec[0].'_title')?></h2>
    <p><?=preview_val($content, $sec[0].'_desc', 200)?></p>
  </div>
  <?php endforeach; ?>

<?php elseif ($page === 'flow-community'): ?>

  <div class="section">
    <div class="section-label">🌐 Hero</div>
    <h2><?=preview_val($content, 'hero_title')?></h2>
    <p><?=preview_val($content, 'hero_subtitle', 200)?></p>
  </div>

<?php endif; ?>

  <div class="preview-missing">—— 预览结束 ——</div>
</div>
</body>
</html>
