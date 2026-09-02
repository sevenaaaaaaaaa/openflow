<?php
/**
 * 增长工具箱 — 免费工具集（获客 + 留客）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('tools', 1800)) exit;

$siteName = site_config_get('site_name', 'OpenFlow');
admin_header_reset(); // 确保无残留输出

// 用 docs.php 相同的头部风格
$tools = [
    ['id' => 'seo', 'name' => 'SEO 检查器', 'icon' => '🔍', 'desc' => '输入标题/描述/关键词，一键检查 SEO 健康状况'],
    ['id' => 'meta', 'name' => 'Meta 生成器', 'icon' => '🏷', 'desc' => '输入标题关键词，自动生成 SEO meta 标签代码'],
    ['id' => 'readability', 'name' => '文章可读性分析', 'icon' => '📖', 'desc' => '粘贴文本，分析字数/阅读时间/关键词密度'],
    ['id' => 'ltv', 'name' => 'LTV/CAC 计算器', 'icon' => '📈', 'desc' => '估算商业模式健康度与回本周期'],
    ['id' => 'funnel', 'name' => '转化漏斗计算器', 'icon' => '🪜', 'desc' => '输入各环节转化率，定位流失点'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>增长工具箱 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="免费网站增长工具：SEO 检查、Meta 生成、可读性分析、LTV/CAC 计算、转化漏斗诊断">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<style>
body{background:var(--bg);font-family:var(--font-body)}
.tool-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:22px;transition:.15s}
.tool-card:hover{box-shadow:var(--shadow-sm)}
.tool-btn{background:var(--accent);color:var(--on-accent);font-weight:700;padding:10px 20px;border-radius:999px;border:none;cursor:pointer}
.tool-input{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:14px;background:var(--bg-soft)}
.tool-input:focus{outline:none;border-color:var(--accent)}
.result-box{background:var(--bg);border-radius:var(--r-sm);padding:14px;font-size:13.5px;line-height:1.8}
.ok{color:var(--ok)}.bad{color:var(--danger)}.warn{color:var(--warn)}
pre.meta-out{background:var(--accent);color:var(--on-accent);padding:14px;border-radius:var(--r-sm);font-size:12.5px;overflow-x:auto}

  /* 设计语言统一：token 语义工具类（终版契约） */
  .text-faint{color:var(--faint)}.text-muted{color:var(--muted)}.text-fg{color:var(--fg)}
  .text-ok{color:var(--ok)}.text-accent{color:var(--accent)}.text-danger{color:var(--danger)}
  .bg-surface{background:var(--surface)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/inject.js?v=20260830b" data-cfasync="false" data-site-inject></script>
<script src="/assets/site-shell.js?v=20260901a" data-cfasync="false" data-page="tools"></script>

<section style="padding:clamp(20px,4vw,44px) 0 clamp(28px,4vw,48px)">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);align-items:center">
      <div style="display:flex;flex-direction:column;gap:16px">
        <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">FREE GROWTH TOOLS</span>
        <h1 style="font-size:clamp(30px,4.5vw,46px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">增长工具箱<span style="font-family:var(--font-display);font-style:italic">免费、即用、可落地</span></h1>
        <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:540px">SEO 检查 · 文案优化 · 商业模型诊断。给增长动作配好趁手的工具。</p>
        <div style="display:flex;gap:18px;margin-top:8px;color:var(--faint);font-size:12.5px;flex-wrap:wrap">
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4L15 12l-3-3 2.7-2.7Z"/><path d="m15 3 6 6"/></svg></span> <b style="color:var(--fg)"><?=count($tools)?></b> 个工具</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span> 全部免费</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2-.7-3 0Z"/><path d="M12 15l-3-3c2-5.5 5-9 9-9s3 6-1 11l-5 1Z"/><path d="M9 12c-2.5 1-4 3-4.5 5M15 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg></span> 开箱即用</span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <?php foreach (array_slice($tools, 0, 4) as $tk => $t): $tcolors = [['var(--accent-soft)','var(--accent)'],['var(--ok-soft)','var(--ok)'],['oklch(70% .13 305/.14)','oklch(60% .18 300)'],['oklch(70% .13 75/.14)','oklch(62% .15 70)']]; ?>
        <a href="#tool-<?=htmlspecialchars($t['id'])?>" style="display:flex;flex-direction:column;gap:10px;padding:18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%);text-decoration:none;transition:transform .25s var(--ease-spring),box-shadow .25s,border-color .25s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-sm)';this.style.borderColor='var(--border-strong)'" onmouseout="this.style.transform='none';this.style.boxShadow='none';this.style.borderColor='var(--border)'">
          <span style="width:38px;height:38px;border-radius:12px;background:<?=$tcolors[$tk][0]?>;color:<?=$tcolors[$tk][1]?>;display:grid;place-items:center;font-size:18px"><?=$t['icon']?></span>
          <b style="font-size:14.5px;color:var(--fg)"><?=htmlspecialchars($t['name'])?></b>
          <span style="font-size:12px;color:var(--muted);line-height:1.5"><?=htmlspecialchars($t['desc'])?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<div class="mx-auto px-5 py-10" style="max-width:1000px">
  <div class="flex gap-3 mb-8 flex-wrap" id="toolTabs">
    <?php foreach ($tools as $t): ?>
    <button class="tool-btn" data-tool="<?=$t['id']?>" style="background:<?=$t['id']==='seo'?'var(--accent)':'var(--surface)'?>;color:<?=$t['id']==='seo'?'var(--on-accent)':'var(--muted)'?>;border:1px solid var(--border)" onclick="switchTool('<?=$t['id']?>',this)"><?=$t['icon']?> <?=$t['name']?></button>
    <?php endforeach; ?>
  </div>

  <!-- SEO 检查器 -->
  <div class="tool-card" id="tool-seo">
    <h2 class="text-xl font-extrabold mb-1" style="display:flex;align-items:center"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-3px;margin-right:6px"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg> SEO 检查器</h2>
    <p class="text-sm text-muted mb-5">检查标题、描述、关键词的健康度，获得优化建议</p>
    <div class="grid gap-4" style="grid-template-columns:1fr 1fr;align-items:start">
      <div>
        <div class="mb-3"><label class="text-sm font-semibold block mb-1">页面标题</label><input id="seoTitle" class="tool-input" placeholder="你的 SEO 标题"></div>
        <div class="mb-3"><label class="text-sm font-semibold block mb-1">Meta 描述</label><textarea id="seoDesc" class="tool-input" rows="3" placeholder="页面描述（50-160字最佳）"></textarea></div>
        <div class="mb-3"><label class="text-sm font-semibold block mb-1">关键词（逗号分隔）</label><input id="seoKw" class="tool-input" placeholder="关键词1, 关键词2, 关键词3"></div>
        <button class="tool-btn" onclick="runSeo()">检查 SEO</button>
      </div>
      <div id="seoResult" class="result-box">输入信息后点击「检查 SEO」</div>
    </div>
  </div>

  <!-- Meta 生成器 -->
  <div class="tool-card" id="tool-meta" style="display:none">
    <h2 class="text-xl font-extrabold mb-1" style="display:flex;align-items:center"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-3px;margin-right:6px"><path d="M3 3h7l11 11-7 7L3 10V3Z"/><circle cx="8" cy="8" r="1.5"/></svg> Meta 生成器</h2>
    <p class="text-sm text-muted mb-5">输入标题和关键词，自动生成完整的 SEO meta 标签代码</p>
    <div class="mb-3"><label class="text-sm font-semibold block mb-1">文章标题</label><input id="metaTitle" class="tool-input" placeholder="文章标题"></div>
    <div class="mb-3"><label class="text-sm font-semibold block mb-1">关键词（逗号分隔）</label><input id="metaKw" class="tool-input" placeholder="关键词1, 关键词2"></div>
    <div class="mb-3"><label class="text-sm font-semibold block mb-1">描述（可选）</label><textarea id="metaDesc" class="tool-input" rows="2" placeholder="留空自动生成"></textarea></div>
    <button class="tool-btn" onclick="runMeta()">生成 Meta</button>
    <div id="metaResult" style="margin-top:12px"></div>
  </div>

  <!-- 可读性分析 -->
  <div class="tool-card" id="tool-readability" style="display:none">
    <h2 class="text-xl font-extrabold mb-1"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> 文章可读性分析</h2>
    <p class="text-sm text-muted mb-5">粘贴你的文章，分析字数、阅读时间、标题结构和关键词密度</p>
    <div class="mb-3"><textarea id="readText" class="tool-input" rows="10" placeholder="粘贴你的文章内容（支持 Markdown）"></textarea></div>
    <button class="tool-btn" onclick="runReadability()">分析</button>
    <div id="readResult" style="margin-top:12px"></div>
  </div>

  <!-- LTV/CAC -->
  <div class="tool-card" id="tool-ltv" style="display:none">
    <h2 class="text-xl font-extrabold mb-1" style="display:flex;align-items:center"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-3px;margin-right:6px"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg> LTV/CAC 计算器</h2>
    <p class="text-sm text-muted mb-5">估算客户终身价值、获客成本比与回本周期</p>
    <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
      <div><label class="text-sm font-semibold block mb-1">月均客单价 ¥</label><input id="ltvArpu" class="tool-input" type="number" value="100"></div>
      <div><label class="text-sm font-semibold block mb-1">月流失率 %</label><input id="ltvChurn" class="tool-input" type="number" value="5"></div>
      <div><label class="text-sm font-semibold block mb-1">获客成本 CAC ¥</label><input id="ltvCac" class="tool-input" type="number" value="300"></div>
      <div><label class="text-sm font-semibold block mb-1">毛利率 %</label><input id="ltvMargin" class="tool-input" type="number" value="60"></div>
    </div>
    <button class="tool-btn mt-4" onclick="runLtv()">计算</button>
    <div id="ltvResult" style="margin-top:12px"></div>
  </div>

  <!-- 转化漏斗 -->
  <div class="tool-card" id="tool-funnel" style="display:none">
    <h2 class="text-xl font-extrabold mb-1"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v18M8 3v18M5 8h3M8 13h3M5 18h3M13 5l6 14M15.5 9.5l1.5-1M17 13l1.5-1"/></svg></span> 转化漏斗计算器</h2>
    <p class="text-sm text-muted mb-5">输入各环节人数，定位转化流失点</p>
    <div id="funnelStages">
      <div class="funnel-stage" style="display:flex;gap:8px;margin-bottom:8px">
        <input class="tool-input" placeholder="环节名（如 访问）" style="flex:1">
        <input class="tool-input" type="number" placeholder="人数" style="flex:1">
      </div>
    </div>
    <button class="tool-btn mt-2" style="background:var(--surface);color:var(--muted);border:1px solid var(--border)" onclick="addFunnelStage()">+ 添加环节</button>
    <button class="tool-btn mt-2" onclick="runFunnel()">计算</button>
    <div id="funnelResult" style="margin-top:12px"></div>
  </div>
</div>

<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto px-5 text-center text-sm" style="max-width:1120px">
    <div class="mb-2"><?=htmlspecialchars($siteName)?> · 增长工具箱</div>
    <div class="flex gap-6 justify-center mb-3 text-xs">
      <a href="/academy" class="transition" style="color:var(--muted)">学院</a>
      <a href="/community" class="transition" style="color:var(--muted)">论坛</a>
      <a href="/docs" class="transition" style="color:var(--muted)">文档</a>
      <a href="/marketplace" class="transition" style="color:var(--muted)">生态市场</a>
    </div>
  </div>
</footer>

 <script>
/* 工具箱使用 → 行为触发 */
if (window.fcTrack) { try { fcTrack('tool_use', { tool_name: '工具箱', page: location.pathname }); } catch (e) {} }
function switchTool(id, btn) {
  document.querySelectorAll('[id^="tool-"]').forEach(function(el){ el.style.display = 'none'; });
  document.getElementById('tool-' + id).style.display = '';
  document.querySelectorAll('#toolTabs .tool-btn').forEach(function(b){ b.style.background = 'var(--surface)'; b.style.color = 'var(--muted)'; });
  btn.style.background = 'var(--accent)'; btn.style.color = 'var(--on-accent)';
}
function api(action, data, cb) {
  fetch('/api/tools', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(Object.assign({action:action}, data))})
    .then(function(r){return r.json();}).then(cb);
}
function esc(s){ var d=document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

// SEO
function runSeo() {
  api('seo_check', {title:document.getElementById('seoTitle').value, description:document.getElementById('seoDesc').value, keywords:document.getElementById('seoKw').value}, function(d){
    if (!d.ok) return;
    var x = d.data;
    var h = '<div style="margin-bottom:8px"><b>综合评分：' + x.score + '/100（' + x.grade + '）</b></div>';
    h += item(x.title.ok, '标题', x.title.length + '字 · ' + x.title.tip);
    h += item(x.description.ok, '描述', x.description.length + '字 · ' + x.description.tip);
    h += item(x.keywords.ok, '关键词', x.keywords.count + '个 · ' + x.keywords.tip);
    h += item(x.coverage.title_hits > 0, '关键词覆盖', x.coverage.tip);
    document.getElementById('seoResult').innerHTML = h;
  });
}
function item(ok, label, tip){ return '<div style="padding:6px 0;border-bottom:1px solid var(--border)"><span class="'+(ok?'ok':'bad')+'">'+(ok?'✓':'✗')+'</span> <b>'+label+'</b><div class="text-sm" style="color:var(--muted)">'+esc(tip)+'</div></div>'; }

// Meta
function runMeta() {
  api('generate_meta', {title:document.getElementById('metaTitle').value, keywords:document.getElementById('metaKw').value, description:document.getElementById('metaDesc').value}, function(d){
    if (!d.ok) return;
    document.getElementById('metaResult').innerHTML = '<pre class="meta-out">' + esc(d.data.html) + '</pre>';
  });
}

// 可读性
function runReadability() {
  api('readability', {text:document.getElementById('readText').value}, function(d){
    if (!d.ok) { document.getElementById('readResult').innerHTML = '<div class="result-box bad">文本太短</div>'; return; }
    var x = d.data;
    var h = '<div class="result-box"><div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr">' +
      '<div><b>'+x.cn_chars+'</b>字</div><div><b>'+x.read_minutes+'</b>分钟阅读</div><div><b>'+x.sentences+'</b>句</div>' +
      '</div><div style="margin-top:8px">段落 '+x.paragraphs+' · 标题 '+x.headings+' 个</div>';
    if (x.heading_list.length) h += '<div style="margin-top:8px;font-size:12.5px;color:var(--muted)">标题结构：' + x.heading_list.map(function(hd){return esc(hd);}).join(' → ') + '</div>';
    h += '<div style="margin-top:8px">高频词：' + (x.top_keywords||[]).map(function(k){return k.word + '(' + k.count + '次)';}).join('、') + '</div></div>';
    document.getElementById('readResult').innerHTML = h;
  });
}

// LTV/CAC
function runLtv() {
  api('ltv_cac', {arpu:document.getElementById('ltvArpu').value, churn:document.getElementById('ltvChurn').value, cac:document.getElementById('ltvCac').value, margin:document.getElementById('ltvMargin').value}, function(d){
    if (!d.ok) return;
    var x = d.data;
    var color = x.health === '健康' ? 'var(--ok)' : (x.health === '需改善' ? 'var(--warn)' : 'var(--danger)');
    document.getElementById('ltvResult').innerHTML = '<div class="result-box">' +
      '<div class="grid gap-3" style="grid-template-columns:1fr 1fr">' +
      '<div>客户生命周期 <b>'+x.life_months+'</b> 月</div><div>客户终身价值 <b>¥'+x.ltv+'</b></div>' +
      '<div>LTV/CAC <b>'+x.ltv_cac_ratio+'</b></div><div>回本周期 <b>'+x.payback_months+'</b> 月</div></div>' +
      '<div style="margin-top:8px;color:'+color+';font-weight:700">健康度：'+x.health+'</div>' +
      '<div class="text-sm" style="color:var(--muted);margin-top:4px">'+esc(x.tip)+'</div></div>';
  });
}

// 漏斗
function addFunnelStage() {
  var div = document.createElement('div');
  div.className = 'funnel-stage';
  div.style.cssText = 'display:flex;gap:8px;margin-bottom:8px';
  div.innerHTML = '<input class="tool-input" placeholder="环节名" style="flex:1"><input class="tool-input" type="number" placeholder="人数" style="flex:1"><button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--danger);cursor:pointer">✕</button>';
  document.getElementById('funnelStages').appendChild(div);
}
function runFunnel() {
  var stages = [];
  document.querySelectorAll('.funnel-stage').forEach(function(el){
    var name = el.querySelector('input:first-child').value;
    var count = el.querySelector('input[type=number]').value;
    if (name || count) stages.push({name:name||'环节', count:parseInt(count)||0});
  });
  api('funnel', {stages:stages}, function(d){
    if (!d.ok) return;
    var x = d.data;
    var h = '<div class="result-box">';
    x.forEach(function(s, i){
      var loss = i>0 ? ' 流失 ' + s.dropoff + ' (' + (100-s.step_conv) + '%)' : '';
      h += '<div style="padding:6px 0;border-bottom:1px solid var(--border)">' +
        '<b>'+esc(s.name)+'</b>: '+s.count+' 人' +
        '<span class="'+(s.step_conv>=50?'ok':'warn')+'"> · 环节转化 '+(s.step_conv===100&&i===0?'100%':s.step_conv+'%')+'</span>' +
        '<span class="text-sm" style="color:var(--muted)"> · 总转化 '+s.total_conv+'%'+loss+'</span></div>';
    });
    h += '</div>';
    document.getElementById('funnelResult').innerHTML = h;
  });
}
// 默认两个环节
document.addEventListener('DOMContentLoaded', function(){ addFunnelStage(); addFunnelStage(); });
</script>
</body>
</html>
<?php PageCache::end('tools', 1800); ?>
<?php
function admin_header_reset(): void {
    // 确保无残留输出（占位，防止前面 require 产生输出）
    if (ob_get_level() > 0) while (ob_get_level()) ob_end_clean();
    ob_start();
}
