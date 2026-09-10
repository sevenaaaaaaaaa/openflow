<?php
/**
 * 创作台 — 统一内容创作入口（E2）
 * 三条引导流：深度专栏（大纲→成文→草稿）/ 口播脚本（结构化节拍）/ 幻灯片（生成→/deck 放映）
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

// 删除历史
if (isset($_GET['del_script'])) { csrf_verify(); $s = json_read(DATA_DIR . '/scripts.json'); unset($s[$_GET['del_script']]); json_write(DATA_DIR . '/scripts.json', $s); header('Location: /xmp/create?tab=script'); exit; }
if (isset($_GET['del_deck'])) { csrf_verify(); $d = json_read(DATA_DIR . '/slide-decks.json'); unset($d[$_GET['del_deck']]); json_write(DATA_DIR . '/slide-decks.json', $d); header('Location: /xmp/create?tab=slides'); exit; }

$scripts = array_reverse(json_read(DATA_DIR . '/scripts.json'));
$decks = array_reverse(json_read(DATA_DIR . '/slide-decks.json'));
$aiReady = AiCenter::isConfigured();
$tab = in_array($_GET['tab'] ?? '', ['column', 'script', 'slides'], true) ? $_GET['tab'] : 'column';

admin_header('创作台');
?>
<div class="admin-layout">
  <?php admin_sidebar('create'); ?>
  <div class="main">
    <h1>创作台</h1>
    <p class="sub">一个入口产出三种深度内容：专栏文章 · 口播视频脚本 · 发布会级幻灯片</p>

    <?php if (!$aiReady): ?>
    <?=msg('error', 'AI 未配置。请先到 <a href="/xmp/ai-config">设置 → AI Agent</a> 配置模型 Key，创作台全部功能依赖 AI。')?>
    <?php endif; ?>

    <div class="tabs" style="margin-bottom:20px">
      <a href="?tab=column" class="<?=$tab==='column'?'active':''?>">📝 深度专栏</a>
      <a href="?tab=script" class="<?=$tab==='script'?'active':''?>">🎙️ 口播脚本</a>
      <a href="?tab=slides" class="<?=$tab==='slides'?'active':''?>">📽️ 幻灯片</a>
    </div>

    <!-- ═══════ 1. 深度专栏 ═══════ -->
    <?php if ($tab === 'column'): ?>
    <div class="card" style="margin-bottom:24px">
      <h2>第 1 步 · 选题与观点</h2>
      <div class="field"><label>专栏主题</label><input type="text" id="col-topic" placeholder="如：为什么一人公司应该先做内容再做产品"></div>
      <div class="field"><label>你的观点 / 角度（可选，写了文章更锐利）</label><input type="text" id="col-angle" placeholder="如：内容是最便宜的获客实验"></div>
      <button class="btn btn-primary" id="col-outline-btn" <?=$aiReady?'':'disabled'?>>生成大纲 →</button>
    </div>

    <div class="card" id="col-step2" style="display:none;margin-bottom:24px">
      <h2>第 2 步 · 确认大纲（可改，每行一节）</h2>
      <div class="field"><label>专栏标题</label><input type="text" id="col-title"></div>
      <div class="field"><label>大纲</label><textarea id="col-outline" rows="7"></textarea></div>
      <p class="hint" id="col-thesis" style="margin-bottom:12px"></p>
      <button class="btn btn-primary" id="col-write-btn">生成全文并存为草稿 →</button>
      <span class="hint" style="margin-left:10px">约 30-60 秒，生成后进文章草稿箱。</span>
    </div>

    <div class="card" id="col-done" style="display:none">
      <h2>✅ 草稿已生成</h2>
      <p id="col-done-title" style="font-weight:700"></p>
      <a href="#" id="col-edit-link" class="btn btn-primary btn-sm">去编辑器精修 / 发布 →</a>
    </div>

    <!-- ═══════ 2. 口播脚本 ═══════ -->
    <?php elseif ($tab === 'script'): ?>
    <div class="card" style="margin-bottom:24px">
      <h2>新脚本</h2>
      <div class="field"><label>视频主题</label><input type="text" id="sc-topic" placeholder="如：3 分钟讲清什么是增长飞轮"></div>
      <div class="field-row">
        <div class="field"><label>时长</label>
          <select id="sc-duration"><option value="60s">60 秒（短视频）</option><option value="3min" selected>3 分钟（中视频）</option><option value="10min">10 分钟（深度）</option></select>
        </div>
        <div class="field"><label>风格</label>
          <select id="sc-style"><option>教程</option><option>犀利</option><option>温和</option></select>
        </div>
      </div>
      <button class="btn btn-primary" id="sc-btn" <?=$aiReady?'':'disabled'?>>生成脚本 →</button>
    </div>

    <div class="card" id="sc-result" style="display:none;margin-bottom:24px"></div>

    <?php
    // 查看历史脚本详情
    $viewId = (string)($_GET['view'] ?? '');
    if ($viewId !== ''):
        foreach ($scripts as $vs) { if ($vs['id'] === $viewId) { $viewScript = $vs; break; } }
        if (!empty($viewScript)): $vd = $viewScript['data']; ?>
    <div class="card" style="margin-bottom:24px">
      <h2><?=htmlspecialchars($vd['title'] ?? $viewScript['topic'])?></h2>
      <div class="card" style="background:var(--accent-soft,#f0f6ff);border-color:transparent"><strong>开场钩子：</strong><?=htmlspecialchars($vd['hook'] ?? '')?></div>
      <table class="tbl" style="margin-top:14px"><thead><tr><th>时间</th><th>台词</th><th>镜头/情绪</th></tr></thead><tbody>
      <?php foreach ((array)($vd['beats'] ?? []) as $b): ?>
        <tr><td class="nowrap mono"><?=htmlspecialchars($b['t'] ?? '')?></td><td><?=htmlspecialchars($b['say'] ?? '')?></td><td class="hint"><?=htmlspecialchars($b['note'] ?? '')?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <p style="margin-top:14px"><strong>结尾 CTA：</strong><?=htmlspecialchars($vd['cta'] ?? '')?></p>
      <p><strong>发布文案：</strong><?=htmlspecialchars($vd['caption'] ?? '')?></p>
      <p><strong>物料清单：</strong><?=htmlspecialchars(implode('、', (array)($vd['shot_list'] ?? [])))?></p>
    </div>
    <?php endif; endif; ?>

    <div class="card">
      <h2>历史脚本（<?=count($scripts)?>）</h2>
      <?php if (!$scripts): ?><p class="hint">暂无。</p><?php else: ?>
      <table class="tbl"><thead><tr><th>标题</th><th>时长/风格</th><th>创建时间</th><th></th></tr></thead><tbody>
      <?php foreach (array_slice($scripts, 0, 20) as $s): ?>
        <tr>
          <td><strong><?=htmlspecialchars($s['data']['title'] ?? $s['topic'])?></strong></td>
          <td><?=htmlspecialchars($s['duration'])?> · <?=htmlspecialchars($s['style'])?></td>
          <td class="nowrap"><?=htmlspecialchars($s['created_at'])?></td>
          <td class="nowrap"><a href="?tab=script&view=<?=urlencode($s['id'])?>" class="btn btn-sm btn-ghost">查看</a>
            <a href="?del_script=<?=urlencode($s['id'])?>&csrf_token=<?=csrf_token()?>" class="btn btn-sm btn-ghost" data-confirm="删除这条脚本？">删</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    </div>

    <!-- ═══════ 3. 幻灯片 ═══════ -->
    <?php else: ?>
    <div class="card" style="margin-bottom:24px">
      <h2>新幻灯片</h2>
      <div class="field"><label>主题</label><input type="text" id="sl-topic" placeholder="如：OpenFlow 产品发布会"></div>
      <div class="field-row">
        <div class="field"><label>页数</label>
          <select id="sl-pages"><?php for ($i = 5; $i <= 15; $i++): ?><option value="<?=$i?>" <?=$i===8?'selected':''?>><?=$i?> 页</option><?php endfor; ?></select>
        </div>
        <div class="field"><label>观众（可选）</label><input type="text" id="sl-audience" placeholder="如：潜在客户 / 开发者"></div>
      </div>
      <div class="field"><label>设计风格</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px" id="sl-themes">
          <?php foreach (DeckThemes::all() as $tid => $t): $v = $t['vars']; ?>
          <label class="sl-theme" style="cursor:pointer;border:2px solid var(--border);border-radius:12px;overflow:hidden;transition:border-color .15s">
            <input type="radio" name="sl-theme" value="<?=$tid?>" <?=$tid==='stage'?'checked':''?> style="position:absolute;opacity:0">
            <div style="height:64px;background:<?=$v['--stage-bg']?>;display:flex;align-items:center;justify-content:center">
              <span style="color:<?=$v['--stage-fg']?>;font-weight:800;font-size:15px">Aa <span style="color:<?=$v['--stage-accent']?>">●</span></span>
            </div>
            <div style="padding:7px 10px;font-size:12px"><b><?=$t['name']?></b><br><span class="hint"><?=$t['desc']?></span></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <details style="margin-bottom:14px">
        <summary style="cursor:pointer;font-size:13px;color:var(--muted)">品牌契约 design.md（本套覆盖全局，可选）</summary>
        <div class="field" style="margin-top:10px">
          <textarea id="sl-brand" rows="5" placeholder="主色: oklch(0.25 0.08 250)&#10;强调色: oklch(0.75 0.18 95)&#10;Logo: /assets/images/logo.png&#10;语气: 专业但不失锐度&#10;禁忌: 不用「赋能」「抓手」"><?=htmlspecialchars(DeckThemes::globalBrand())?></textarea>
          <span class="hint">识别键：主色/文字色/强调色/标题字体/正文字体/Logo/语气/禁忌；其余文本作为品牌背景注入 AI。保存全局默认值见下方。</span>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" id="sl-brand-save">存为全局默认品牌契约</button>
        <span class="hint" id="sl-brand-msg"></span>
      </details>
      <button class="btn btn-primary" id="sl-btn" <?=$aiReady?'':'disabled'?>>生成幻灯片 →</button>
    </div>
    <style>
    .sl-theme:has(input:checked){border-color:var(--accent)!important}
    </style>

    <div class="card" id="sl-done" style="display:none;margin-bottom:24px"></div>

    <div class="card">
      <h2>历史幻灯片（<?=count($decks)?>）</h2>
      <?php if (!$decks): ?><p class="hint">暂无。</p><?php else: ?>
      <table class="tbl"><thead><tr><th>标题</th><th>风格</th><th>页数</th><th>创建时间</th><th></th></tr></thead><tbody>
      <?php foreach (array_slice($decks, 0, 20) as $d): ?>
        <tr>
          <td><strong><?=htmlspecialchars($d['title'])?></strong><br><span class="hint"><?=htmlspecialchars($d['topic'])?></span></td>
          <td><span class="badge"><?=htmlspecialchars(DeckThemes::get((string)($d['theme'] ?? 'stage'))['name'])?></span></td>
          <td><?=count((array)$d['slides'])?> 页</td>
          <td class="nowrap"><?=htmlspecialchars($d['created_at'])?></td>
          <td class="nowrap"><a href="/deck/<?=urlencode($d['id'])?>" target="_blank" class="btn btn-sm btn-primary">放映</a>
            <a href="?del_deck=<?=urlencode($d['id'])?>&csrf_token=<?=csrf_token()?>" class="btn btn-sm btn-ghost" data-confirm="删除这套幻灯片？">删</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF = <?=json_encode(csrf_token())?>;
async function createCall(payload, btn) {
  btn.disabled = true; const old = btn.textContent; btn.textContent = '生成中…';
  try {
    const body = new URLSearchParams({...payload, csrf_token: CSRF});
    const r = await fetch('/api/create.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || '生成失败');
    return j;
  } finally { btn.disabled = false; btn.textContent = old; }
}
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

/* ── 专栏 ── */
const colBtn = document.getElementById('col-outline-btn');
if (colBtn) colBtn.onclick = async () => {
  const topic = document.getElementById('col-topic').value.trim();
  if (!topic) return ofAlert('先填主题');
  try {
    const j = await createCall({action: 'outline', topic, angle: document.getElementById('col-angle').value.trim()}, colBtn);
    document.getElementById('col-title').value = j.data.title || topic;
    document.getElementById('col-outline').value = (j.data.outline || []).join('\n');
    document.getElementById('col-thesis').textContent = j.data.thesis ? '核心论点：' + j.data.thesis : '';
    document.getElementById('col-step2').style.display = '';
  } catch (e) { ofAlert(e.message); }
};
const colWrite = document.getElementById('col-write-btn');
if (colWrite) colWrite.onclick = async () => {
  try {
    const j = await createCall({action: 'column',
      topic: document.getElementById('col-topic').value.trim(),
      angle: document.getElementById('col-angle').value.trim(),
      title: document.getElementById('col-title').value.trim(),
      outline: JSON.stringify(document.getElementById('col-outline').value.split('\n').map(s => s.trim()).filter(Boolean)),
    }, colWrite);
    document.getElementById('col-done-title').textContent = '《' + j.title + '》';
    document.getElementById('col-edit-link').href = '/xmp/article-edit?id=' + encodeURIComponent(j.id);
    document.getElementById('col-done').style.display = '';
    window.scrollTo({top: document.getElementById('col-done').offsetTop - 80, behavior: 'smooth'});
  } catch (e) { ofAlert(e.message); }
};

/* ── 脚本 ── */
function renderScript(d) {
  const beats = (d.beats || []).map(b =>
    `<tr><td class="nowrap mono">${esc(b.t)}</td><td>${esc(b.say)}</td><td class="hint">${esc(b.note)}</td></tr>`).join('');
  return `<h2>${esc(d.title)}</h2>
    <div class="card" style="background:var(--accent-soft,#f0f6ff);border-color:transparent"><strong>开场钩子：</strong>${esc(d.hook)}</div>
    <table class="tbl" style="margin-top:14px"><thead><tr><th>时间</th><th>台词</th><th>镜头/情绪</th></tr></thead><tbody>${beats}</tbody></table>
    <p style="margin-top:14px"><strong>结尾 CTA：</strong>${esc(d.cta)}</p>
    <p><strong>发布文案：</strong>${esc(d.caption)}</p>
    <p><strong>物料清单：</strong>${esc((d.shot_list || []).join('、'))}</p>`;
}
const scBtn = document.getElementById('sc-btn');
if (scBtn) scBtn.onclick = async () => {
  const topic = document.getElementById('sc-topic').value.trim();
  if (!topic) return ofAlert('先填主题');
  try {
    const j = await createCall({action: 'script', topic,
      duration: document.getElementById('sc-duration').value, style: document.getElementById('sc-style').value}, scBtn);
    const box = document.getElementById('sc-result');
    box.innerHTML = renderScript(j.data); box.style.display = '';
    window.scrollTo({top: box.offsetTop - 80, behavior: 'smooth'});
  } catch (e) { ofAlert(e.message); }
};

/* ── 幻灯片 ── */
const slBrandSave = document.getElementById('sl-brand-save');
if (slBrandSave) slBrandSave.onclick = async () => {
  try {
    await createCall({action: 'save_brand', brand_md: document.getElementById('sl-brand').value}, slBrandSave);
    document.getElementById('sl-brand-msg').textContent = '已存为全局默认';
  } catch (e) { ofAlert(e.message); }
};
const slBtn = document.getElementById('sl-btn');
if (slBtn) slBtn.onclick = async () => {
  const topic = document.getElementById('sl-topic').value.trim();
  if (!topic) return ofAlert('先填主题');
  const theme = (document.querySelector('input[name=sl-theme]:checked') || {}).value || 'stage';
  try {
    const j = await createCall({action: 'slides', topic, theme,
      brand_md: document.getElementById('sl-brand').value,
      pages: document.getElementById('sl-pages').value, audience: document.getElementById('sl-audience').value.trim()}, slBtn);
    const box = document.getElementById('sl-done');
    box.innerHTML = `<h2>✅ 《${esc(j.title)}》已生成（${j.pages} 页）</h2>
      <a href="/deck/${encodeURIComponent(j.id)}" target="_blank" class="btn btn-primary btn-sm">全屏放映 →</a>
      <span class="hint" style="margin-left:10px">← → 翻页 · F 全屏 · 支持触屏滑动</span>`;
    box.style.display = '';
  } catch (e) { ofAlert(e.message); }
};
</script>
<?php admin_footer(); ?>
