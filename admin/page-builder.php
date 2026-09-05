<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/BlockTargeting.php';
// 注册表与块契约必须在顶部引入：下面的保存逻辑要用 block_new_key() / block_normalize_all()，
// 而原来的 require 在第 120 多行，保存路径根本走不到。
require_once __DIR__ . '/../lib/BlockRegistry.php';
require_once __DIR__ . '/../lib/BlockContract.php';
require_once __DIR__ . '/../lib/BuilderPages.php';
require_login();
require_perm('pages');

$pages = builder_pages_all();

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
        // 块身份必须活过这次保存。以前这里每次都 random 一个新 id，
        // 等于每保存一次就把所有区块换成「新块」——按块比对版本、给块留批注、
        // 看单块转化，全都做不了。现在表单会把 _key 带回来，只有新块才生成。
        $postedKey = trim((string)($_POST['block_key'][$bi] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $postedKey)) $postedKey = block_new_key();
        $block = ['_key' => $postedKey, '_type' => $bt];
        // 模块工厂：自定义模块按它的 schema 字段名动态收集（含 repeat 列表）
        if (function_exists('blockschema_is_custom') && blockschema_is_custom($bt)) {
            $schema = blockschema_get($bt);
            foreach (($schema['fields'] ?? []) as $sf) {
                $sk = (string)$sf['key'];
                if (($sf['type'] ?? '') === 'repeat') {
                    // repeat: POST 形如 block_{key}[{sub}][][][]; 用下标 bi 过滤
                    $rows = [];
                    foreach ((array)($_POST['block_' . $sk][$bi] ?? []) as $typeTag => $subVals) {
                        // $_POST['block_{k}'][$bi] 是 [ '0' => [ 'sub1'=>..., 'sub2'=>... ] ] 加 bsAddRepeat 新增行
                        if (!is_array($subVals)) continue;
                        $row = [];
                        foreach ((array)($sf['children'] ?? []) as $cf) {
                            $ck = (string)$cf['key'];
                            if (isset($subVals[$ck])) $row[$ck] = $subVals[$ck];
                        }
                        if ($row) $rows[] = $row;
                    }
                    $block[$sk] = $rows;
                } else {
                    if (isset($_POST['block_' . $sk][$bi])) $block[$sk] = $_POST['block_' . $sk][$bi];
                }
            }
        } else {
            foreach (['title','subtitle','content','image','bg_color','button_text','button_url','video_url','icon','columns','count','items','form_slug','layout','module_id'] as $fk) {
                if (isset($_POST['block_' . $fk][$bi])) $block[$fk] = $_POST['block_' . $fk][$bi];
            }
        }
        // 区块级人群定向（BACKLOG T1-8）：全为不限/空则不写 audience，保持默认全员可见
        $aud = [
            'login'   => (string)($_POST['block_aud_login'][$bi] ?? 'any'),
            'visitor' => (string)($_POST['block_aud_visitor'][$bi] ?? 'any'),
            'segment' => trim((string)($_POST['block_aud_segment'][$bi] ?? '')),
            'utm'     => trim((string)($_POST['block_aud_utm'][$bi] ?? '')),
        ];
        $hasAud = ($aud['login'] !== 'any') || ($aud['visitor'] !== 'any') || $aud['segment'] !== '' || $aud['utm'] !== '';
        if ($hasAud) $block['audience'] = $aud;
        $data['blocks'][] = $block;
    }
    // 归一化 + 去重 _key：重复的 key 比没有 key 更糟，
    // 它会让「按 key 定位块」悄悄指向错的那一个。
    $data['blocks'] = block_normalize_all($data['blocks']);

    // 走唯一写入口：顺带记一版（此前落地页是直接 json_write，改错了退不回去）
    $savedId = save_builder_page($id, $data);
    $pages = builder_pages_all();
    $message = $savedId !== '' ? '落地页已保存' : '保存失败：页面不存在';
    if ($savedId !== '') $id = $savedId;
}

if (isset($_POST['delete'])) {
    builder_page_delete((string)$_POST['delete']);
    $pages = builder_pages_all();
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

// 类型表来自注册表，别再各抄一份（此前这里 13 种、模块库 17 种、前台渲染器 13 种，三份对不上）
$blockTypes = block_types();
$moduleLib  = block_modules();
// 自定义模块（模块工厂）：编辑器按 schema 动态生成字段；JS 也用它渲染新块输入框
$customSchemas = function_exists('blockschema_all') ? blockschema_all() : [];

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
              <form method="post" style="display:inline" data-confirm="删除页面「<?=htmlspecialchars($p['title'] ?? '',ENT_QUOTES)?>」？">
                <?= csrf_field() ?>
                <button type="submit" name="delete" value="<?=htmlspecialchars($p['id'])?>" class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button>
              </form>
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
          <p class="text-sm text-muted mb-4">从上到下排列 · 按住 ☰ 拖拽排序 · 每个区块下方有实时预览 · <button type="button" class="btn btn-s btn-sm" id="ofLivePrev">👁 整页实时预览</button></p>
          <div id="blocksList">
            <?php foreach (($editPage['blocks'] ?? []) as $bi => $blk): ?>
            <div class="block-item" draggable="true" ondragstart="blkDragStart(event)" ondragover="blkDragOver(event)" ondrop="blkDrop(event)" ondragend="this.classList.remove('dragging')" style="border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface);cursor:grab">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <span title="拖拽排序" style="cursor:grab;color:var(--faint)">☰</span>
                <?php $bkType = block_type_of($blk); $bkKey = block_key_of($blk); ?>
                <?php /* 块身份随表单往返，保存后不变 —— 块级批注/比对全靠它 */ ?>
                <input type="hidden" name="block_key[]" value="<?=htmlspecialchars($bkKey)?>">
                <span style="font-weight:600;font-size:14px">🧱 <?=htmlspecialchars($blockTypes[$bkType] ?? $bkType)?></span>
                <select name="block_type[]" onchange="renameBlock(this)" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                  <?php foreach ($blockTypes as $btk => $btv): ?>
                  <option value="<?=$btk?>" <?=$bkType===$btk?'selected':''?>><?=htmlspecialchars($btv)?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest('.block-item').remove()">✕</button>
              </div>
              <div class="block-fields" style="display:grid;gap:8px">
                <?php /* 引用模块库：定义一次、到处插入。选中后其余字段由模块本身决定 */ ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)">
                  <span style="flex:0 0 auto">引用模块</span>
                  <select name="block_module_id[]" style="flex:1;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                    <option value="">不引用（用下面的字段自己填）</option>
                    <?php foreach ($moduleLib as $mid => $mod): ?>
                    <option value="<?=htmlspecialchars($mid)?>" <?=($blk['module_id'] ?? '')===$mid?'selected':''?>>
                      <?=htmlspecialchars($mod['name'] ?? $mid)?> · <?=htmlspecialchars(block_type_label(block_type_of($mod)))?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <?php if (blockschema_is_custom($bkType)): /* 模块工厂：按 schema 动态渲染字段 */ ?>
                <?php echo blockschema_editor_fields($customSchemas[$bkType] ?? [], $blk); ?>
                <?php else: /* 内置块：原有统一 15 字段 */ ?>
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
                <?php endif; ?>
                <?php /* 可视化所见即所得：每个块显示真实渲染预览（builder_render_block），随编辑实时更新 */ ?>
                <div class="block-preview-wrap" data-k="<?=htmlspecialchars($bkKey)?>" style="border:1.5px dashed var(--border);border-radius:10px;margin-top:8px;background:var(--bg)">
                  <div style="padding:6px 12px;font-size:11px;color:var(--faint);display:flex;justify-content:space-between"><span>👁 实时预览</span><button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.block-preview-wrap').classList.toggle('collapsed')">收起</button></div>
                  <div class="block-preview" style="padding:14px;overflow:auto;max-height:360px"><?php echo builder_render_block($blk); ?></div>
                </div>
                <?php $ba = $blk['audience'] ?? []; $bopts = blocktarget_options(); ?>
                <details <?=blocktarget_has_rules($blk)?'open':''?> style="border:1px dashed var(--border);border-radius:6px;padding:8px 10px">
                  <summary style="cursor:pointer;font-size:12px;color:var(--faint)">🎯 只给特定人群看<?=blocktarget_has_rules($blk)?'（已定向）':'（默认所有人）'?></summary>
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:6px;margin-top:8px">
                    <select name="block_aud_login[]"><?php foreach ($bopts['login'] as $k=>$v): ?><option value="<?=$k?>" <?=($ba['login']??'any')===$k?'selected':''?>>登录：<?=$v?></option><?php endforeach; ?></select>
                    <select name="block_aud_visitor[]"><?php foreach ($bopts['visitor'] as $k=>$v): ?><option value="<?=$k?>" <?=($ba['visitor']??'any')===$k?'selected':''?>>访客：<?=$v?></option><?php endforeach; ?></select>
                    <input type="text" name="block_aud_segment[]" value="<?=htmlspecialchars($ba['segment']??'')?>" placeholder="CDP 分群 id">
                    <input type="text" name="block_aud_utm[]" value="<?=htmlspecialchars($ba['utm']??'')?>" placeholder="UTM 来源">
                  </div>
                </details>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px">
            <?php foreach ($blockTypes as $btk => $btv): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addBlock('<?=$btk?>','<?=htmlspecialchars($btv)?>')">+ <?=htmlspecialchars($btv)?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">保存落地页</button>
        <a href="page-builder.php" class="btn btn-ghost">取消</a>
        <?php if (!empty($editPage['id'])):
          require_once __DIR__ . '/../lib/RevisionSystem.php';
          require_once __DIR__ . '/../lib/CollabReview.php';
          $lrev = rev_count('landing', (string)$editPage['id']);
          $lnotes = note_open_count('page', (string)$editPage['id']);
        ?>
        <a href="/xmp/revisions?type=landing&id=<?=urlencode((string)$editPage['id'])?>" class="btn btn-ghost">
          修订历史<?=$lrev ? '（' . $lrev . ' 版）' : ''?>
        </a>
        <a href="/xmp/collaborators" class="btn btn-ghost">
          外部协作<?=$lnotes ? '（' . $lnotes . ' 条待处理批注）' : ''?>
        </a>
        <?php endif; ?>
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

// 模块工厂：自定义模块的 schema 数据，JS 端按它生成新块输入框
var CUSTOM_SCHEMAS = <?=json_encode($customSchemas, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;

// 按 schema 生成一个自定义模块的字段输入框 HTML（JS 版，与 PHP blockschema_editor_fields 对齐）
function schemaFieldsHtml(schema, vals) {
  vals = vals || {};
  var h = '';
  (schema.fields || []).forEach(function(f) {
    var k = f.key, v = vals[k] != null ? (Array.isArray(vals[k]) ? '' : vals[k]) : '';
    var vv = (v === true || v === 1) ? '1' : (v || '');
    if (f.type === 'title') h += '<label class="text-xs text-muted" style="margin-top:4px">' + (f.label||k) + '</label><input type="text" name="block_' + k + '[]" value="' + vv + '" placeholder="' + (f.placeholder||'') + '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
    else if (f.type === 'richtext' || f.type === 'text') h += '<textarea name="block_' + k + '[]" rows="2" placeholder="' + (f.placeholder||'') + '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' + vv + '</textarea>';
    else if (f.type === 'image' || f.type === 'color' || f.type === 'url' || f.type === 'number' || f.type === 'form') h += '<label class="text-xs text-muted" style="margin-top:4px">' + (f.label||k) + '</label><input type="' + (f.type==='number'?'number':'text') + '" name="block_' + k + '[]" value="' + vv + '" placeholder="' + (f.placeholder||'') + '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
    else if (f.type === 'select') { var ops = '<option value="">—</option>'; (f.options||[]).forEach(function(o){ ops += '<option value="'+o+'"'+(vv===o?' selected':'')+'>'+o+'</option>'; }); h += '<label class="text-xs text-muted" style="margin-top:4px">' + (f.label||k) + '</label><select name="block_' + k + '[]" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' + ops + '</select>'; }
    else if (f.type === 'bool') h += '<label class="text-xs text-muted" style="display:flex;align-items:center;gap:6px;margin-top:4px"><input type="checkbox" name="block_' + k + '[]" value="1" ' + (vv?'checked':'') + ' style="width:16px;height:16px">' + (f.label||k) + '</label>';
    else if (f.type === 'repeat') { var rows = vals[k] || []; h += '<div class="bs-repeat" data-key="'+k+'" style="border:1px dashed var(--border);border-radius:6px;padding:8px 10px;margin-top:4px"><div class="text-xs text-muted" style="margin-bottom:6px">' + (f.label||k) + '（可加多条）</div><div class="bs-rows" data-children="' + JSON.stringify(f.children||[]) + '">'; rows.forEach(function(r){ h += schemaRepeatRowHtml(k, f.children||[], r, true); }); h += '</div><button type="button" class="btn btn-ghost btn-sm" onclick="bsAddRepeat(this)">+ 添加一条</button></div>'; }
  });
  return h;
}
function schemaRepeatRowHtml(baseK, children, row, fromServer) {
  var tag = fromServer ? '<div class="bs-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:6px;margin-bottom:6px">' : '<div class="bs-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:6px;margin-bottom:6px">';
  var h = tag;
  children.forEach(function(c) {
    var cv = (row && row[c.key]) || '';
    var name = 'block_' + baseK + '[' + c.key + ']';
    h += '<input type="text" name="' + name + '" value="' + cv + '" placeholder="' + (c.label||c.key) + '" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">';
  });
  h += '<button type="button" class="btn btn-ghost btn-sm" onclick="this.closest(\'.bs-row\').remove()">✕</button></div>';
  return h;
}
function bsAddRepeat(btn) {
  var rep = btn.closest('.bs-repeat');
  var k = rep.getAttribute('data-key');
  var rowsEl = rep.querySelector('.bs-rows');
  var children = rowsEl.getAttribute('data-children') ? JSON.parse(rowsEl.getAttribute('data-children')) : [];
  var r = document.createElement('div'); r.innerHTML = schemaRepeatRowHtml(k, children, {}, false);
  rowsEl.appendChild(r.firstElementChild);
}

function addBlock(type, label) {
  var div = document.createElement('div');
  div.className = 'block-item';
  div.draggable = true;
  div.ondragstart = blkDragStart; div.ondragover = blkDragOver; div.ondrop = blkDrop;
  div.ondragend = function(){ this.classList.remove('dragging'); };
  div.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface);cursor:grab';
  var idx = blockIdx++;
  // 模块工厂：自定义模块用 schema 动态字段，内置块走通用 15 框
  var isCustom = !!(CUSTOM_SCHEMAS[type]);
  var fieldsHtml;
  if (isCustom) {
    fieldsHtml = schemaFieldsHtml(CUSTOM_SCHEMAS[type], {});
  } else {
    fieldsHtml = '<input type="text" name="block_title[]" placeholder="标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' +
      '<input type="text" name="block_subtitle[]" placeholder="副标题" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' +
      '<textarea name="block_content[]" rows="2" placeholder="内容 (支持 HTML)" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px;font-family:var(--mono)"></textarea>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
        '<input type="text" name="block_image[]" placeholder="图片路径">' +
        '<input type="text" name="block_bg_color[]" placeholder="背景色">' +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
        '<input type="text" name="block_button_text[]" placeholder="按钮文字">' +
        '<input type="text" name="block_button_url[]" placeholder="按钮链接">' +
      '</div>';
  }
  div.innerHTML =
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">' +
      '<span style="cursor:grab;color:var(--faint)" title="拖拽排序">☰</span>' +
      '<span style="font-weight:600;font-size:14px">🧱 ' + label + '</span>' +
      '<select name="block_type[]" style="padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
        '<?php foreach ($blockTypes as $btk => $btv): ?><option value="<?=$btk?>" ' + (type === '<?=$btk?>' ? 'selected' : '') + '><?=htmlspecialchars($btv)?></option><?php endforeach; ?>' +
      '</select>' +
      '<button type="button" class="btn btn-danger btn-sm" style="margin-left:auto" onclick="this.closest(\'.block-item\').remove()">✕</button>' +
    '</div>' +
    '<input type="hidden" name="block_key[]" value="">' +
    '<div class="block-fields" style="display:grid;gap:8px">' +
      // 新块也必须输出这些字段，否则 block_key[] / block_module_id[] 与 block_type[] 的下标会错位
      '<label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)"><span style="flex:0 0 auto">引用模块</span>' +
      '<select name="block_module_id[]" style="flex:1;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
        '<option value="">不引用（用下面的字段自己填）</option>' +
        '<?php foreach ($moduleLib as $mid => $mod): ?><option value="<?=htmlspecialchars($mid)?>"><?=htmlspecialchars(($mod['name'] ?? $mid) . ' · ' . block_type_label(block_type_of($mod)))?></option><?php endforeach; ?>' +
      '</select></label>' +
      fieldsHtml +
      // 区块级人群定向（T1-8）：与 PHP 渲染保持同样的 4 个字段，确保并行数组下标对齐
      '<details style="border:1px dashed var(--border);border-radius:6px;padding:8px 10px">' +
        '<summary style="cursor:pointer;font-size:12px;color:var(--faint)">🎯 只给特定人群看（默认所有人）</summary>' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:6px;margin-top:8px">' +
          '<select name="block_aud_login[]"><option value="any">登录：不限</option><option value="in">登录：已登录</option><option value="out">登录：未登录</option></select>' +
          '<select name="block_aud_visitor[]"><option value="any">访客：不限</option><option value="new">访客：新访客</option><option value="return">访客：回访客</option></select>' +
          '<input type="text" name="block_aud_segment[]" placeholder="CDP 分群 id">' +
          '<input type="text" name="block_aud_utm[]" placeholder="UTM 来源">' +
        '</div>' +
      '</details>' +
    '</div>';
  document.getElementById('blocksList').appendChild(div);
  setTimeout(function(){ bindPreview(div); var pv = div.querySelector('.block-preview'); if (pv) refreshBlockPreview(div); }, 50);
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

/* ── 可视化所见即所得：块输入实时刷新预览 ── */
function collectBlockValues(item) {
  var b = { _type: '', _key: '' };
  item.querySelectorAll('input,textarea,select').forEach(function(el) {
    if (!el.name) return;
    var m = el.name.match(/^block_(\w+)\[\]$/);
    if (m) { b[m[1]] = el.value; }
  });
  var t = item.querySelector('select[name="block_type[]"]'); if (t) b._type = t.value;
  var k = item.querySelector('input[name="block_key[]"]'); if (k) b._key = k.value;
  return b;
}
function refreshBlockPreview(item) {
  var pv = item.querySelector('.block-preview'); if (!pv) return;
  var b = collectBlockValues(item);
  clearTimeout(item._pvTimer);
  item._pvTimer = setTimeout(function() {
    fetch('/api/builder-preview.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({block:b}) })
      .then(function(r){ return r.text(); }).then(function(h){ pv.innerHTML = h || '<div class="of-empty">预览为空</div>'; })
      .catch(function(){ pv.innerHTML = '<div class="of-empty" style="padding:10px">预览加载失败</div>'; });
  }, 250);
}
function bindPreview(item) {
  item.addEventListener('input', function(e){ if (e.target.closest('input,textarea,select')) refreshBlockPreview(item); });
  item.addEventListener('change', function(e){ if (e.target.name === 'block_type[]') setTimeout(function(){ refreshBlockPreview(item); }, 10); });
}
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#blocksList .block-item').forEach(function(it){ bindPreview(it); });
  // 主视图「实时预览」按钮：切换整页预览（仅展示，不含表单操作）
  var prog = document.getElementById('ofLivePrev');
  if (prog) prog.addEventListener('click', function(){
    var list = document.getElementById('blocksList');
    list.classList.toggle('live-prev');
    prog.textContent = list.classList.contains('live-prev') ? '回到编辑' : '👁 整页实时预览';
    if (list.classList.contains('live-prev')) document.querySelectorAll('#blocksList .block-item').forEach(function(it){ refreshBlockPreview(it); });
  });
});
</script>
<style>
/* 整页实时预览：隐藏编辑表单项，只展示预览，形成所见即所得画布 */
#blocksList.live-prev .block-fields{display:none}
#blocksList.live-prev .block-preview-wrap{border:1px solid var(--border);margin:0 0 16px}
#blocksList.live-prev .block-preview{max-height:none;padding:24px 18px}
#blocksList.live-prev .block-item{cursor:default;border:0;padding:0;background:transparent}
#blocksList.live-prev .block-item .del,#blocksList.live-prev .block-item .block-ctrl{display:none}
</style>
<?php admin_footer(); ?>
