<?php
/**
 * 自动装配 — 投喂资料 → 系统按 TIPS/用户旅程生成就位的东西 → 逐个确认
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Provisioner.php';
require_login();
require_perm('settings');

$brief = prov_brief();
$plan = prov_plan();
$items = array_values((array)($plan['items'] ?? []));
$tpsMeta = [
    'touch' => ['触达 Touch', '内容 / 渠道 / 落地页'],
    'insight' => ['洞察 Insight', '目标 / 转化目标 / 分群'],
    'personalize' => ['个性化 Personalize', '自动化 flow / 触发'],
    'sell' => ['成交 Sell', '报价 / 交付 / 复购'],
];
$accepted = count(array_filter($items, fn($i) => ($i['status'] ?? '') === 'accepted'));
admin_header('自动装配');
?>
<div class="admin-layout">
  <?php admin_sidebar('provision'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>自动装配</h1><p class="v-sub">把你的项目/知识库/内容交进来，系统按 TIPS 与用户旅程生成一整套就位的东西（流程/目标/分群/内容/落地页/待办），你只需逐个点确认。</p></div>
      <div class="v-actions"><span class="note mono" style="margin:0" id="pvMsg"></span></div>
    </div>

    <!-- 1. 投喂 -->
    <div class="card" style="margin-bottom:16px">
      <h2 style="font-size:15px">① 投喂资料 <span class="hint">· 文本 / 站点 URL / 文本文件（PDF/DOCX 请先转文本）</span></h2>
      <div class="field"><label>项目名</label><input class="inp" id="pvTitle" value="<?=htmlspecialchars($brief['title'] ?? '')?>" placeholder="如：OpenFlow 增长系统"></div>
      <div class="field"><label>项目介绍 / 知识库 <span class="hint">· 产品、目标用户、已有内容、可分享的知识</span></label>
        <textarea class="inp" id="pvText" rows="7" placeholder="把项目介绍、产品、目标用户、已有内容、知识库一股脑写进来…"><?=htmlspecialchars($brief['text'] ?? '')?></textarea></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-p btn-sm" onclick="pvSaveBrief()">保存并入库</button>
        <input class="inp" id="pvUrl" placeholder="https://站点或文章 URL" style="flex:1;min-width:200px">
        <button class="btn btn-s btn-sm" onclick="pvUrl()">抓取 URL</button>
        <input type="file" id="pvFile" style="display:none" onchange="pvFile()">
        <button class="btn btn-s btn-sm" onclick="document.getElementById('pvFile').click()">上传文件</button>
      </div>
      <?php if (!empty($brief['urls']) || !empty($brief['files'])): ?>
      <div class="text-xs text-muted" style="margin-top:8px">已投喂：<?php foreach ((array)($brief['urls'] ?? []) as $u) echo htmlspecialchars($u) . ' '; foreach ((array)($brief['files'] ?? []) as $f) echo '📄' . htmlspecialchars($f) . ' '; ?></div>
      <?php endif; ?>
    </div>

    <!-- 2. 生成 -->
    <div class="card" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <h2 style="font-size:15px;margin:0">② 生成装配计划</h2>
      <button class="btn btn-p btn-sm" id="pvGen" onclick="pvGenerate()">生成 / 重新生成</button>
      <span class="text-sm text-muted" id="pvGenInfo"><?=!empty($plan['generated_at']) ? ('上次生成：' . htmlspecialchars($plan['generated_at']) . ' · ' . count($items) . ' 项 · 已接受 ' . $accepted) : '还没有计划'?></span>
    </div>

    <!-- 3. 计划 -->
    <div id="pvPlan">
      <?php if (!empty($plan['summary'])): ?><div class="card" style="padding:12px 16px;margin-bottom:12px;border-left:3px solid var(--accent)"><?=htmlspecialchars($plan['summary'])?></div><?php endif; ?>
      <?php foreach ($tpsMeta as $k => [$label, $sub]): $group = array_values(array_filter($items, fn($i) => ($i['tps'] ?? '') === $k)); if (!$group) continue; ?>
      <div class="card" style="margin-bottom:12px">
        <h3 style="font-size:14px;margin:0 0 10px"><?=htmlspecialchars($label)?> <span class="hint">· <?=htmlspecialchars($sub)?></span></h3>
        <?php foreach ($group as $it): ?>
        <div style="display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-bottom:1px solid var(--border-soft,var(--border))">
          <span class="badge badge-gray"><?=htmlspecialchars($it['category'] ?: $it['artifact']['type'])?></span>
          <div style="flex:1;min-width:0">
            <b><?=htmlspecialchars($it['title'])?></b>
            <?php if (!empty($it['why'])): ?><div class="text-sm text-muted"><?=htmlspecialchars($it['why'])?></div><?php endif; ?>
          </div>
          <?php if (($it['status'] ?? '') === 'accepted'): ?>
            <span class="badge badge-green">已接受</span>
            <?php if (!empty($it['created']['url'])): ?><a class="btn btn-ghost btn-sm" href="<?=htmlspecialchars($it['created']['url'])?>">查看</a><?php endif; ?>
          <?php elseif (($it['status'] ?? '') === 'skipped'): ?>
            <span class="badge badge-gray">已跳过</span>
          <?php else: ?>
            <button class="btn btn-p btn-sm" onclick="pvApply('<?=htmlspecialchars($it['id'])?>',this)">接受</button>
            <button class="btn btn-ghost btn-sm" onclick="pvSkip('<?=htmlspecialchars($it['id'])?>',this)">跳过</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$items): ?><div class="card"><div class="empty" style="padding:24px">投喂资料后点「生成装配计划」，这里会出现按 TIPS 分组的就位项。</div></div><?php endif; ?>
    </div>
  </div>
</div>
<script>
const PV_CSRF = <?=json_encode(csrf_token())?>;
function pvMsg(t) { document.getElementById('pvMsg').textContent = t; }
async function pvPost(payload, isForm) {
  const opt = { method: 'POST', headers: {'X-CSRF-Token': PV_CSRF} };
  if (isForm) opt.body = payload;
  else { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(payload); }
  return (await fetch('/api/provision.php', opt)).json();
}
async function pvSaveBrief() {
  pvMsg('保存中…');
  const r = await pvPost({action: 'brief_save', title: document.getElementById('pvTitle').value, text: document.getElementById('pvText').value});
  pvMsg(r.ok ? ('已入库 ' + r.chars + ' 字') : (r.error || '失败'));
}
async function pvUrl() {
  const u = document.getElementById('pvUrl').value.trim(); if (!u) return;
  pvMsg('抓取中…');
  const r = await pvPost({action: 'ingest_url', url: u});
  pvMsg(r.ok ? ('已抓取 ' + r.chars + ' 字') : (r.error || '失败'));
}
async function pvFile() {
  const f = document.getElementById('pvFile').files[0]; if (!f) return;
  const fd = new FormData(); fd.append('action', 'ingest_file'); fd.append('file', f);
  pvMsg('上传中…');
  const r = await pvPost(fd, true);
  pvMsg(r.ok ? ('已入库 ' + r.chars + ' 字') : (r.error || '失败'));
}
async function pvGenerate() {
  const b = document.getElementById('pvGen'); b.disabled = true; b.textContent = '生成中…';
  pvMsg('小福正在读资料并规划…');
  const r = await pvPost({action: 'generate', force: 1});
  b.disabled = false; b.textContent = '生成 / 重新生成';
  if (!r.ok) { pvMsg(r.error || '生成失败'); return; }
  pvMsg('计划已生成，正在刷新…'); location.reload();
}
async function pvApply(id, btn) {
  btn.disabled = true; pvMsg('正在创建…');
  const r = await pvPost({action: 'apply', id});
  if (!r.ok) { pvMsg(r.error || '创建失败'); btn.disabled = false; return; }
  pvMsg('已创建'); location.reload();
}
async function pvSkip(id, btn) {
  btn.disabled = true;
  const r = await pvPost({action: 'skip', id});
  if (r.ok) location.reload(); else { pvMsg('跳过失败'); btn.disabled = false; }
}
</script>
<?php admin_footer(); ?>
