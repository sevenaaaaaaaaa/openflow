<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('pages');

$builderFile = DATA_DIR . '/builder-pages.json';
$pages = json_read($builderFile);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $data = [
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'seo_title' => $_POST['seo_title'] ?? '',
        'seo_desc' => $_POST['seo_desc'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'is_ad_landing' => isset($_POST['is_ad_landing']),
        'blocks' => [],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (empty($data['slug'])) $data['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $data['title']);

    // Build blocks from POST
    $blockTypes = $_POST['block_type'] ?? [];
    foreach ($blockTypes as $bi => $bt) {
        if (empty($bt)) continue;
        $block = ['id' => 'blk_' . $bi . '_' . substr(bin2hex(random_bytes(4)), 0, 6), 'type' => $bt];
        foreach (['title','subtitle','content','image','bg_color','button_text','button_url','video_url','icon','columns','count','items','form_slug','layout'] as $fk) {
            if (isset($_POST['block_' . $fk][$bi])) $block[$fk] = $_POST['block_' . $fk][$bi];
        }
        $data['blocks'][] = $block;
    }

    if (empty($id)) {
        $data['id'] = 'lp_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $data['created_at'] = date('Y-m-d H:i:s');
        $pages[] = $data;
    } else {
        foreach ($pages as &$p) { if ($p['id'] === $id) { $p = array_merge($p, $data); break; } }
    }
    json_write($builderFile, $pages);
    $message = '落地页已保存';
}

if (isset($_POST['delete'])) {
    $pages = array_values(array_filter($pages, fn($p) => $p['id'] !== $_POST['delete']));
    json_write($builderFile, $pages);
    header('Location: /xmp/page-builder');
    exit;
}

$editPage = null;
if (isset($_GET['edit'])) {
    // 基础页编辑（edit=base:/slug）
    if (str_starts_with($_GET['edit'], 'base:')) {
        $baseSlug = substr($_GET['edit'], 5);
        $sitePages = json_read(DATA_DIR . '/site-pages.json');
        $seo = json_read(DATA_DIR . '/seo.json');
        $basePage = null;
        foreach ((array)$sitePages as $bp) if (($bp['slug'] ?? '') === $baseSlug) { $basePage = $bp; break; }
        if ($basePage) {
            $sm = $seo[$baseSlug] ?? [];
            // 保存基础页 SEO
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_base_seo'])) {
                csrf_verify();
                $seo[$baseSlug] = ['title'=>trim($_POST['seo_title']??''),'desc'=>trim($_POST['seo_desc']??''),'keywords'=>trim($_POST['seo_keywords']??'')];
                json_write(DATA_DIR . '/seo.json', $seo);
                flash('success', '基础页 SEO 已更新');
                header('Location: /xmp/page-builder?edit=' . urlencode($_GET['edit']));
                exit;
            }
            admin_header('编辑页面');
            ?>
            <div class="admin-layout">
              <?php admin_sidebar('pages-list'); ?>
              <div class="main">
                <div class="v-head">
                  <div><h1><?=htmlspecialchars($basePage['title'] ?? $baseSlug)?></h1><p class="v-sub">基础页（静态模板） · <?=htmlspecialchars($baseSlug)?> · 内容由模板控制，可管理 SEO 与状态</p></div>
                  <div class="v-actions"><a href="pages-list.php" class="btn btn-s btn-sm">← 返回列表</a></div>
                </div>
                <div class="card" style="padding:24px;max-width:720px">
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="save_base_seo" value="1">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px">SEO 设置</h3>
                    <div class="fld"><label style="font-size:12px;color:var(--faint)">SEO 标题</label><input class="inp" name="seo_title" value="<?=htmlspecialchars($sm['title']??'')?>" style="height:38px"></div>
                    <div class="fld"><label style="font-size:12px;color:var(--faint)">SEO 描述</label><textarea class="inp" name="seo_desc" rows="3"><?=htmlspecialchars($sm['desc']??'')?></textarea></div>
                    <div class="fld"><label style="font-size:12px;color:var(--faint)">关键词</label><input class="inp" name="seo_keywords" value="<?=htmlspecialchars($sm['keywords']??'')?>" style="height:38px"></div>
                    <button class="btn btn-p btn-sm">保存</button>
                  </form>
                  <div style="margin-top:20px;padding:14px;border-radius:12px;background:var(--bg);font-size:12.5px;color:var(--muted);line-height:1.7">
                    <b>说明：</b>「<?=htmlspecialchars($basePage['title'] ?? '')?>」是平台的固定模板页（内容由模板定义）。如需完全自定义区块，请创建「模块化页」或「落地页」。
                    <a href="/xmp/pages-list" style="color:var(--accent)">查看其他页面 →</a>
                  </div>
                </div>
              </div>
            </div>
            <?php admin_footer(); exit;
        }
    }
    foreach ($pages as $p) { if ($p['id'] === $_GET['edit']) { $editPage = $p; break; } }
}

$blockTypes = [
    'hero' => 'Hero 大标题', 'features' => '功能列表', 'cta' => 'CTA 行动号召',
    'text' => '文本段落', 'image-text' => '图文混排', 'stats' => '数据指标',
    'testimonials' => '客户证言', 'logo-wall' => 'Logo 墙', 'faq' => 'FAQ',
    'gallery' => '图片画廊', 'form' => '表单嵌入', 'newsletter' => '订阅表单',
    'video' => '视频嵌入', 'embed' => '自定义代码（嵌入）',
];

admin_header('落地页构建器');
?>
<div class="admin-layout">
  <?php admin_sidebar('page-builder'); ?>
  <div class="main">
    <h1>落地页构建器</h1>
    <p class="sub">模块化搭建营销落地页 · 广告页独立入口 · 支持 Hero/CTA/表单等 13 种区块</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>页面标题</th><th>Slug</th><th>区块数</th><th>广告页</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($pages)): ?><tr><td colspan="6" class="empty">暂无落地页</td></tr><?php endif; ?>
          <?php foreach ($pages as $p): ?>
          <tr>
            <td><strong><?=htmlspecialchars($p['title'])?></strong></td>
            <td><code>/lp/<?=htmlspecialchars($p['slug'])?></code></td>
            <td><?=count($p['blocks'] ?? [])?></td>
            <td><?=($p['is_ad_landing'] ?? false) ? '<span class="badge badge-yellow">📢 广告页</span>' : '<span class="text-sm text-muted">—</span>'?></td>
            <td><span class="badge <?=($p['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$p['status']??'draft'?></span></td>
            <td>
              <a href="?edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline" onsubmit="return confirm('确认删除?')">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:12px 20px;border-top:1px solid var(--border)">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
          <input type="text" id="aiDesc" placeholder="🤖 AI 一键生成：如「AI 增长课程落地页，突出限时优惠与学员评价」" style="flex:1;min-width:260px;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px">
          <button type="button" class="btn btn-s btn-sm" onclick="aiGenerate()">⚡ AI 生成落地页</button>
          <span id="aiMsg" style="font-size:12.5px"></span>
        </div>
        <a href="?edit=new" class="btn btn-primary btn-sm">+ 新建落地页</a>
        <a href="?edit=new&ad=1" class="btn btn-ghost btn-sm">+ 新建广告落地页</a>
      </div>
    </div>

    <?php if (isset($_GET['edit'])): ?>
    <div class="card">
      <h2><?=$editPage?'编辑：'.htmlspecialchars($editPage['title']):'新建落地页'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?=htmlspecialchars($editPage['id']??'')?>">
        <div class="field-row">
          <div class="field"><label>页面标题</label><input type="text" name="title" value="<?=htmlspecialchars($editPage['title']??'')?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($editPage['slug']??'')?>" placeholder="自动生成"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($editPage['seo_title']??'')?>"></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editPage['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editPage['status']??'')==='published'?'selected':''?>>已发布</option></select></div>
        </div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" rows="2"><?=htmlspecialchars($editPage['seo_desc']??'')?></textarea></div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_ad_landing" value="1" <?=($editPage['is_ad_landing']??false)?'checked':''?> style="width:18px;height:18px">📢 标记为广告落地页（独立入口）</label></div>

        <!-- Blocks Editor -->
        <div class="card" style="margin:16px 0;padding:16px">
          <h2>🧱 页面区块</h2>
          <p class="text-sm text-muted mb-4">从上到下排列 · 按住 ☰ 拖拽排序 · 添加区块后填写内容</p>
          <div id="blocksList">
            <?php foreach (($editPage['blocks'] ?? []) as $bi => $blk): ?>
            <div class="block-item" draggable="true" ondragstart="blkDragStart(event)" ondragover="blkDragOver(event)" ondrop="blkDrop(event)" ondragend="this.classList.remove('dragging')" style="border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface);cursor:grab">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <span title="拖拽排序" style="cursor:grab;color:var(--faint)">☰</span>
                <span style="font-weight:600;font-size:14px">🧱 <?=htmlspecialchars($blockTypes[$blk['type']] ?? $blk['type'])?></span>
                <select name="block_type[]" onchange="renameBlock(this)" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                  <?php foreach ($blockTypes as $btk => $btv): ?>
                  <option value="<?=$btk?>" <?=$blk['type']===$btk?'selected':''?>><?=htmlspecialchars($btv)?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest('.block-item').remove()">✕</button>
              </div>
              <div class="block-fields" style="display:grid;gap:8px">
                <input type="text" name="block_title[]" value="<?=htmlspecialchars($blk['title']??'')?>" placeholder="标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">
                <input type="text" name="block_subtitle[]" value="<?=htmlspecialchars($blk['subtitle']??'')?>" placeholder="副标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">
                <textarea name="block_content[]" rows="2" placeholder="内容 (支持 HTML)" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px;font-family:var(--mono)"><?=htmlspecialchars($blk['content']??'')?></textarea>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                  <input type="text" name="block_image[]" value="<?=htmlspecialchars($blk['image']??'')?>" placeholder="图片路径">
                  <input type="text" name="block_bg_color[]" value="<?=htmlspecialchars($blk['bg_color']??'')?>" placeholder="背景色 (如 #f4f3e9)">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                  <input type="text" name="block_button_text[]" value="<?=htmlspecialchars($blk['button_text']??'')?>" placeholder="按钮文字">
                  <input type="text" name="block_button_url[]" value="<?=htmlspecialchars($blk['button_url']??'')?>" placeholder="按钮链接">
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px">
            <?php foreach ($blockTypes as $btk => $btv): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addBlock('<?=$btk?>','<?=htmlspecialchars($btv)?>')">+ <?=htmlspecialchars($btv)?></button>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:10px;padding-top:12px;border-top:1px dashed var(--border)">
            <span style="font-size:12px;font-weight:600;color:var(--muted);margin-right:8px">📋 快速套用模板：</span>
            <button type="button" class="btn btn-s btn-sm" onclick="applyTemplate('saas')">🚀 SaaS 产品页</button>
            <button type="button" class="btn btn-s btn-sm" onclick="applyTemplate('marketing')">🎯 营销落地页</button>
            <button type="button" class="btn btn-s btn-sm" onclick="applyTemplate('launch')">🛠 新品发布页</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">保存落地页</button>
        <a href="page-builder.php" class="btn btn-ghost">取消</a>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function aiGenerate() {
  var desc = document.getElementById('aiDesc').value.trim();
  var msg = document.getElementById('aiMsg');
  if (!desc) { msg.textContent = '请先描述需求'; msg.style.color = 'var(--danger)'; return; }
  msg.textContent = 'AI 生成中…'; msg.style.color = 'var(--muted)';
  var fd = new FormData(); fd.append('desc', desc);
  fetch('/api/ai-landing.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)';
      msg.textContent = d.message || d.error;
      if (d.ok && d.edit_url) setTimeout(function(){ location.href = d.edit_url; }, 1200);
    })
    .catch(function(){ msg.textContent = '网络异常'; msg.style.color = 'var(--danger)'; });
}
var blockIdx = <?=count($editPage['blocks'] ?? [])?>;

/* 预设模板（Webflow/Wix 式一键套用） */
var TEMPLATES = {
  saas: [
    {type:'hero', title:'让增长像呼吸一样自然', subtitle:'OpenFlow — AI 时代的网站增长操作系统，内容/获客/转化一体化', button_text:'免费开始', button_url:'#', bg_color:'#f4f3e9'},
    {type:'features', title:'四大核心能力', content:'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px"><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>内容引擎</b><p style="color:var(--muted)">AI 选题成文 · 定时发布</p></div><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>CDP 用户数据</b><p style="color:var(--muted)">画像/分群/洞察</p></div><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>营销自动化</b><p style="color:var(--muted)">触发式触达闭环</p></div><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>商业转化</b><p style="color:var(--muted)">电商/课程/分销</p></div></div>'},
    {type:'cta', title:'准备好开始了吗？', subtitle:'30 分钟上线自带增长能力的网站', button_text:'立即体验', button_url:'#'}
  ],
  marketing: [
    {type:'hero', title:'限时优惠：增长系统实操课', subtitle:'7 天学会增长系统，从流量思维到价值复利', button_text:'立即报名', button_url:'#', bg_color:'#fdfce9'},
    {type:'stats', title:'数据说话', content:'<div style="display:flex;gap:20px;justify-content:center;text-align:center"><div><div style="font-size:32px;font-weight:800;color:var(--accent)">3000+</div><div style="color:var(--muted)">学员</div></div><div><div style="font-size:32px;font-weight:800;color:var(--accent)">4.9</div><div style="color:var(--muted)">平均评分</div></div><div><div style="font-size:32px;font-weight:800;color:var(--accent)">92%</div><div style="color:var(--muted)">完课率</div></div></div>'},
    {type:'testimonials', title:'学员评价', content:'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px"><div style="padding:16px;border:1px solid var(--border);border-radius:12px"><p>“终于把增长做成了一套系统”</p><div style="color:var(--faint);font-size:12px">— 某一人公司创始人</div></div><div style="padding:16px;border:1px solid var(--border);border-radius:12px"><p>“工具 + 策略都有了”</p><div style="color:var(--faint);font-size:12px">— 某 SaaS 运营</div></div></div>'},
    {type:'form', title:'抢占名额', content:'（配置表单 slug 后嵌入报名表单）', button_text:'报名', button_url:'#'},
    {type:'cta', title:'现在加入，立即成长', button_text:'立即报名', button_url:'#'}
  ],
  launch: [
    {type:'hero', title:'新品发布：Agent 增长引擎', subtitle:'自生长的 AI 增长团队成员，7×24 主动推进', button_text:'了解详情', button_url:'#', bg_color:'#1e1e1e'},
    {type:'features', title:'核心特性', content:'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px"><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>热点爬取</b></div><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>AI 撰写</b></div><div style="padding:20px;border:1px solid var(--border);border-radius:14px"><b>SEO 优化</b></div></div>'},
    {type:'video', title:'产品演示', content:'（粘贴视频地址）'},
    {type:'newsletter', title:'订阅发布通知', subtitle:'第一时间获取更新', button_text:'订阅'},
    {type:'cta', title:'加入内测', button_text:'申请体验', button_url:'#'}
  ]
};
function applyTemplate(name) {
  if (!confirm('套用模板将替换当前区块布局，确定？')) return;
  var list = document.getElementById('blocksList');
  list.innerHTML = '';
  (TEMPLATES[name] || []).forEach(function(b, i) {
    var div = document.createElement('div');
    div.className = 'block-item';
    div.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)';
    var idx = blockIdx++;
    div.innerHTML =
      '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><b style="font-size:14px">🧱 ' + b.title + '</b><button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest(\'.block-item\').remove()">✕</button></div>' +
      '<input type="hidden" name="block_type[]" value="' + b.type + '">' +
      '<input type="text" name="block_title[]" value="' + escHtml(b.title) + '" placeholder="标题" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:14px;margin-bottom:8px">' +
      '<input type="text" name="block_subtitle[]" value="' + escHtml(b.subtitle || '') + '" placeholder="副标题" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:14px;margin-bottom:8px">' +
      '<textarea name="block_content[]" rows="3" placeholder="内容 (支持 HTML)" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:var(--mono);margin-bottom:8px">' + escHtml(b.content || '') + '</textarea>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">' +
        '<input type="text" name="block_image[]" value="" placeholder="图片路径" style="padding:8px;border:1px solid var(--border);border-radius:6px;font-size:12px">' +
        '<input type="text" name="block_bg_color[]" value="' + escHtml(b.bg_color || '') + '" placeholder="背景色" style="padding:8px;border:1px solid var(--border);border-radius:6px;font-size:12px">' +
        '<input type="text" name="block_button_text[]" value="' + escHtml(b.button_text || '') + '" placeholder="按钮文字" style="padding:8px;border:1px solid var(--border);border-radius:6px;font-size:12px">' +
      '</div>' +
      '<input type="text" name="block_button_url[]" value="' + escHtml(b.button_url || '') + '" placeholder="按钮链接" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:12px;margin-top:8px">';
    list.appendChild(div);
  });
}
function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function addBlock(type, label) {
  var div = document.createElement('div');
  div.className = 'block-item';
  div.draggable = true;
  div.ondragstart = blkDragStart; div.ondragover = blkDragOver; div.ondrop = blkDrop;
  div.ondragend = function(){ this.classList.remove('dragging'); };
  div.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface);cursor:grab';
  var idx = blockIdx++;
  div.innerHTML =
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">' +
      '<span style="cursor:grab;color:var(--faint)" title="拖拽排序">☰</span>' +
      '<span style="font-weight:600;font-size:14px">🧱 ' + label + '</span>' +
      '<select name="block_type[]" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
        '<?php foreach ($blockTypes as $btk => $btv): ?><option value="<?=$btk?>" ' + (type === '<?=$btk?>' ? 'selected' : '') + '><?=htmlspecialchars($btv)?></option><?php endforeach; ?>' +
      '</select>' +
      '<button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest(\'.block-item\').remove()">✕</button>' +
    '</div>' +
    '<div class="block-fields" style="display:grid;gap:8px">' +
      '<input type="text" name="block_title[]" placeholder="标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' +
      '<input type="text" name="block_subtitle[]" placeholder="副标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' +
      '<textarea name="block_content[]" rows="2" placeholder="内容 (支持 HTML)" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px;font-family:var(--mono)"></textarea>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
        '<input type="text" name="block_image[]" placeholder="图片路径">' +
        '<input type="text" name="block_bg_color[]" placeholder="背景色">' +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
        '<input type="text" name="block_button_text[]" placeholder="按钮文字">' +
        '<input type="text" name="block_button_url[]" placeholder="按钮链接">' +
      '</div>' +
    '</div>';
  document.getElementById('blocksList').appendChild(div);
}

function renameBlock(sel) {
  var label = sel.options[sel.selectedIndex].text;
  var title = sel.parentElement.querySelector('span');
  if (title) title.textContent = '🧱 ' + label;
}

/* 区块拖拽排序 */
var blkDragEl = null;
function blkDragStart(e) {
  blkDragEl = e.target.closest('.block-item');
  blkDragEl.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function blkDragOver(e) {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  var target = e.target.closest('.block-item');
  if (target && target !== blkDragEl) {
    var list = document.getElementById('blocksList');
    var rect = target.getBoundingClientRect();
    list.insertBefore(blkDragEl, e.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
  }
}
function blkDrop(e) { e.preventDefault(); blkDragEl = null; }
</script>
<?php admin_footer(); ?>
