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

// 工具表：icon 为 24×24 线框 path，渲染时包进 <svg>
$tools = [
    ['id' => 'seo', 'name' => 'SEO 检查器', 'icon' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>', 'desc' => '输入标题/描述/关键词，一键检查 SEO 健康状况'],
    ['id' => 'meta', 'name' => 'Meta 生成器', 'icon' => '<path d="M3 3h7l11 11-7 7L3 10V3Z"/><circle cx="8" cy="8" r="1.5"/>', 'desc' => '输入标题关键词，自动生成 SEO meta 标签代码'],
    ['id' => 'readability', 'name' => '文章可读性分析', 'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/>', 'desc' => '粘贴文本，分析字数/阅读时间/关键词密度'],
    ['id' => 'ltv', 'name' => 'LTV/CAC 计算器', 'icon' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>', 'desc' => '估算商业模式健康度与回本周期'],
    ['id' => 'funnel', 'name' => '转化漏斗计算器', 'icon' => '<path d="M5 3v18M8 3v18M5 8h3M8 13h3M5 18h3M13 5l6 14M15.5 9.5l1.5-1M17 13l1.5-1"/>', 'desc' => '输入各环节转化率，定位流失点'],
];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>增长工具箱 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="免费网站增长工具：SEO 检查、Meta 生成、可读性分析、LTV/CAC 计算、转化漏斗诊断">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 工具箱独有：首屏窗内工具目录、工具面板、结果盒。tab 切换走外壳通用 tab。 */
.hero-win .link-grid{grid-template-columns:1fr;gap:2px;padding:10px}
.hero-win .link-it{padding:12px 14px}
.tools .tab-panel.on{display:block}
.tools .ph{display:flex;flex-direction:column;gap:6px;margin-bottom:22px}
.tools .ph h2{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:800;letter-spacing:-.02em}
.tools .ph h2 .ic{width:34px;height:34px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center}
.tools .ph h2 .ic svg{width:17px;height:17px}
.tools .ph p{font-size:14px;color:var(--muted)}
.tools .two{display:grid;grid-template-columns:1fr 1fr;gap:clamp(18px,3vw,32px);align-items:start}
.tools .inp{min-height:46px;padding:11px 14px;font-size:14px}
.tools textarea.inp{min-height:0}
.tools .run{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
.res{background:var(--bg-soft);border:1px solid var(--border-soft);border-radius:var(--r-md);padding:16px 18px;font-size:13.5px;line-height:1.8}
.res b{font-weight:700}
.res .tip{font-size:12.5px;color:var(--muted)}
.res .ok{color:var(--ok)}.res .bad{color:var(--danger)}.res .warn{color:var(--warn)}
.res.bad{color:var(--danger)}
pre.meta-out{background:var(--fg);color:var(--on-accent);padding:16px 18px;border-radius:var(--r-md);font-size:12.5px;line-height:1.7;overflow-x:auto;font-family:var(--font-mono)}
.funnel-stage{display:grid;grid-template-columns:1fr 1fr 36px;gap:8px;align-items:center;margin-bottom:8px}
.funnel-stage .mx{color:var(--danger)}
@media (max-width:860px){.tools .two{grid-template-columns:1fr}}
</style>
</head>
<body data-of-main>
<?php of_shell('tools'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="tools-hero">
    <div class="hero">
      <div class="hero-copy">
        <span class="kicker">FREE GROWTH TOOLS</span>
        <h1>增长工具箱<br><i class="si">免费 · 即用 · 可落地</i></h1>
        <p class="lead">SEO 检查 · 文案优化 · 商业模型诊断。给增长动作配好趁手的工具。</p>
        <div class="trust"><span class="dot"></span><?=count($tools)?> 个工具 · 全部免费 · 开箱即用</div>
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">tools · <?=count($tools)?> 个</div></div>
        <div class="link-grid">
          <?php foreach ($tools as $t): ?>
          <a class="link-it" href="#tool-<?=htmlspecialchars($t['id'])?>" data-tool-link="<?=htmlspecialchars($t['id'])?>"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?=$t['icon']?></svg></span><span class="lt"><b><?=htmlspecialchars($t['name'])?></b><span><?=htmlspecialchars($t['desc'])?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section id="toolbox" class="sec tools reveal" data-od-anchor data-od-id="tools-box">
    <div class="tab-bar dense" role="tablist" data-tabs id="toolTabs" aria-label="选择工具">
      <?php foreach ($tools as $k => $t): ?>
      <button class="tab-p" role="tab" type="button" id="tab-<?=$t['id']?>" aria-controls="tool-<?=$t['id']?>" aria-selected="<?=$k===0?'true':'false'?>"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?=$t['icon']?></svg></span><?=htmlspecialchars($t['name'])?></button>
      <?php endforeach; ?>
    </div>

    <!-- SEO 检查器 -->
    <div class="tab-panel on card" id="tool-seo" role="tabpanel" aria-labelledby="tab-seo">
    <div class="ph"><h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>SEO 检查器</h2><p>检查标题、描述、关键词的健康度，获得优化建议</p></div>
      <div class="two">
        <div class="form-grid">
          <div class="field"><label for="seoTitle">页面标题</label><input id="seoTitle" class="inp" placeholder="你的 SEO 标题"></div>
          <div class="field"><label for="seoDesc">Meta 描述</label><textarea id="seoDesc" class="inp" rows="3" placeholder="页面描述（50-160字最佳）"></textarea></div>
          <div class="field"><label for="seoKw">关键词（逗号分隔）</label><input id="seoKw" class="inp" placeholder="关键词1, 关键词2, 关键词3"></div>
          <div class="run"><button type="button" class="btn primary" onclick="runSeo()">检查 SEO</button></div>
        </div>
        <div id="seoResult" class="res">输入信息后点击「检查 SEO」</div>
      </div>
    </div>

    <!-- Meta 生成器 -->
    <div class="tab-panel card" id="tool-meta" role="tabpanel" aria-labelledby="tab-meta">
    <div class="ph"><h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7l11 11-7 7L3 10V3Z"/><circle cx="8" cy="8" r="1.5"/></svg></span>Meta 生成器</h2><p>输入标题和关键词，自动生成完整的 SEO meta 标签代码</p></div>
      <div class="form-grid">
        <div class="field"><label for="metaTitle">文章标题</label><input id="metaTitle" class="inp" placeholder="文章标题"></div>
        <div class="field"><label for="metaKw">关键词（逗号分隔）</label><input id="metaKw" class="inp" placeholder="关键词1, 关键词2"></div>
        <div class="field"><label for="metaDesc">描述（可选）</label><textarea id="metaDesc" class="inp" rows="2" placeholder="留空自动生成"></textarea></div>
      </div>
      <div class="run"><button type="button" class="btn primary" onclick="runMeta()">生成 Meta</button></div>
      <div id="metaResult" style="margin-top:16px"></div>
    </div>

    <!-- 可读性分析 -->
    <div class="tab-panel card" id="tool-readability" role="tabpanel" aria-labelledby="tab-readability">
    <div class="ph"><h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span>文章可读性分析</h2><p>粘贴你的文章，分析字数、阅读时间、标题结构和关键词密度</p></div>
      <div class="field"><label for="readText">文章内容</label><textarea id="readText" class="inp" rows="10" placeholder="粘贴你的文章内容（支持 Markdown）"></textarea></div>
      <div class="run"><button type="button" class="btn primary" onclick="runReadability()">分析</button></div>
      <div id="readResult" style="margin-top:16px"></div>
    </div>

    <!-- LTV/CAC -->
    <div class="tab-panel card" id="tool-ltv" role="tabpanel" aria-labelledby="tab-ltv">
    <div class="ph"><h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span>LTV/CAC 计算器</h2><p>估算客户终身价值、获客成本比与回本周期</p></div>
      <div class="form-grid" style="grid-template-columns:1fr 1fr">
        <div class="field"><label for="ltvArpu">月均客单价 ¥</label><input id="ltvArpu" class="inp" type="number" value="100"></div>
        <div class="field"><label for="ltvChurn">月流失率 %</label><input id="ltvChurn" class="inp" type="number" value="5"></div>
        <div class="field"><label for="ltvCac">获客成本 CAC ¥</label><input id="ltvCac" class="inp" type="number" value="300"></div>
        <div class="field"><label for="ltvMargin">毛利率 %</label><input id="ltvMargin" class="inp" type="number" value="60"></div>
      </div>
      <div class="run"><button type="button" class="btn primary" onclick="runLtv()">计算</button></div>
      <div id="ltvResult" style="margin-top:16px"></div>
    </div>

    <!-- 转化漏斗 -->
    <div class="tab-panel card" id="tool-funnel" role="tabpanel" aria-labelledby="tab-funnel">
    <div class="ph"><h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v18M8 3v18M5 8h3M8 13h3M5 18h3M13 5l6 14M15.5 9.5l1.5-1M17 13l1.5-1"/></svg></span>转化漏斗计算器</h2><p>输入各环节人数，定位转化流失点</p></div>
      <div id="funnelStages">
        <div class="funnel-stage">
          <input class="inp" placeholder="环节名（如 访问）">
          <input class="inp" type="number" placeholder="人数">
          <span></span>
        </div>
      </div>
      <div class="run"><button type="button" class="btn ghost" onclick="addFunnelStage()">+ 添加环节</button><button type="button" class="btn primary" onclick="runFunnel()">计算</button></div>
      <div id="funnelResult" style="margin-top:16px"></div>
    </div>
  </section>

  <section class="reveal" data-od-id="tools-cta">
    <div class="cta-band">
      <span class="kicker">NEXT STEP</span>
      <h2>工具算出了问题，系统来解决问题</h2>
      <p class="lead">OpenFlow 把这些检查变成 Agent 每天自动跑的动作：诊断、建议、执行、复盘，一条线走完。</p>
      <div class="cta-row"><a href="/product" class="btn primary">看看 OpenFlow 怎么做</a><a href="/docs" class="btn ghost">阅读文档</a></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
/* 工具箱使用 → 行为触发 */
if (window.fcTrack) { try { fcTrack('tool_use', { tool_name: '工具箱', page: location.pathname }); } catch (e) {} }
function gotoTool(id) {
  var t = document.querySelector('#toolTabs [aria-controls="tool-' + id + '"]');
  if (t) { t.click(); document.getElementById('toolbox').scrollIntoView({behavior:'smooth', block:'start'}); }
}
document.querySelectorAll('a[data-tool-link]').forEach(function(a){ a.addEventListener('click', function(e){ e.preventDefault(); gotoTool(a.getAttribute('data-tool-link')); }); });
if (location.hash.indexOf('#tool-') === 0) { var _t = document.querySelector('#toolTabs [aria-controls="' + location.hash.slice(1) + '"]'); if (_t) _t.click(); }
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
function item(ok, label, tip){ return '<div style="padding:6px 0;border-bottom:1px solid var(--border-soft)"><span class="'+(ok?'ok':'bad')+'">'+(ok?'✓':'✗')+'</span> <b>'+label+'</b><div class="tip">'+esc(tip)+'</div></div>'; }

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
    var h = '<div class="res"><div class="grid g3">' +
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
    document.getElementById('ltvResult').innerHTML = '<div class="res">' +
      '<div class="grid g2">' +
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
    div.innerHTML = '<input class="inp" placeholder="环节名"><input class="inp" type="number" placeholder="人数"><button type="button" class="mx" aria-label="删除环节" onclick="this.parentElement.remove()">✕</button>';
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
    var h = '<div class="res">';
    x.forEach(function(s, i){
      var loss = i>0 ? ' 流失 ' + s.dropoff + ' (' + (100-s.step_conv) + '%)' : '';
      h += '<div style="padding:6px 0;border-bottom:1px solid var(--border-soft)">' +
        '<b>'+esc(s.name)+'</b>: '+s.count+' 人' +
        '<span class="'+(s.step_conv>=50?'ok':'warn')+'"> · 环节转化 '+(s.step_conv===100&&i===0?'100%':s.step_conv+'%')+'</span>' +
        '<span class="tip"> · 总转化 '+s.total_conv+'%'+loss+'</span></div>';
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
