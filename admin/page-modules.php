<?php
/**
 * 落地页模块列表 — 可复用模块库
 * 前端页面由各种模块/区块组成，这里集中管理模块：新建、复用、编辑、启用/停用
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('landing');

$modulesFile = DATA_DIR . '/page-modules.json';
$modules = json_read($modulesFile);

$blockTypes = [
    'hero' => 'Hero 大标题', 'features' => '功能列表', 'cta' => 'CTA 行动号召',
    'text' => '文本段落', 'image-text' => '图文混排', 'stats' => '数据指标',
    'testimonials' => '客户证言', 'logo-wall' => 'Logo 墙', 'faq' => 'FAQ',
    'gallery' => '图片画廊', 'form' => '表单嵌入', 'newsletter' => '订阅表单',
    'video' => '视频嵌入', 'contact' => '联系表单', 'pricing' => '价格表',
    'timeline' => '时间线', 'comparison' => '对比表',
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'text',
            'description' => trim($_POST['description'] ?? ''),
            'block' => [
                'type' => $_POST['type'] ?? 'text',
                'title' => $_POST['block_title'] ?? '',
                'subtitle' => $_POST['block_subtitle'] ?? '',
                'content' => $_POST['block_content'] ?? '',
                'image' => $_POST['block_image'] ?? '',
                'button_text' => $_POST['block_button_text'] ?? '',
                'button_url' => $_POST['block_button_url'] ?? '',
                'bg_color' => $_POST['block_bg_color'] ?? '',
            ],
            'enabled' => isset($_POST['enabled']),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($id)) {
            $data['id'] = 'mdl_' . substr(bin2hex(random_bytes(6)), 0, 12);
            $data['created_at'] = date('Y-m-d H:i:s');
            $modules[] = $data;
        } else {
            foreach ($modules as &$m) { if ($m['id'] === $id) { $m = array_merge($m, $data); break; } }
        }
        json_write($modulesFile, $modules);
        $message = '模块已保存';
    }
    if ($action === 'delete') {
        $modules = array_values(array_filter($modules, fn($m) => $m['id'] !== ($_POST['id'] ?? '')));
        json_write($modulesFile, $modules);
        $message = '模块已删除';
    }
    if ($action === 'toggle') {
        foreach ($modules as &$m) {
            if ($m['id'] === ($_POST['id'] ?? '')) { $m['enabled'] = !($m['enabled'] ?? false); break; }
        }
        json_write($modulesFile, $modules);
        $message = '模块状态已更新';
    }
    $modules = json_read($modulesFile);
}

$editModule = null;
if (isset($_GET['edit'])) {
    foreach ($modules as $m) { if ($m['id'] === $_GET['edit']) { $editModule = $m; break; } }
}

admin_header('落地页模块');
?>
<div class="admin-layout">
  <?php admin_sidebar('page-modules'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 落地页模块库</h1>
      <div class="flex gap-2 ml-auto">
        <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ 新建模块</button>
      </div>
    </div>
    <p class="sub">模块化管理前端页面组件 · 可复用到任意落地页 · 共 <?=count($blockTypes)?> 种区块类型</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 统计卡片 -->
    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
      <div class="stat-card"><div class="num"><?=count($modules)?></div><div class="label">模块总数</div></div>
      <div class="stat-card"><div class="num" style="color:var(--ok)"><?=count(array_filter($modules, fn($m) => $m['enabled'] ?? false))?></div><div class="label">启用中</div></div>
      <div class="stat-card"><div class="num" style="color:var(--warn)"><?=count($blockTypes)?></div><div class="label">区块类型</div></div>
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=count(get_landing_pages())?></div><div class="label">落地页</div></div>
    </div>

    <!-- 模块类型速查 -->
    <div class="card" style="margin-bottom:24px;padding:16px">
      <h2 style="margin-bottom:12px">📦 可用区块类型</h2>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($blockTypes as $bt => $bl): ?>
        <span class="badge badge-gray" style="font-size:12px;padding:4px 12px"><?=$bl?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 模块列表 -->
    <div class="card" style="padding:0;overflow:auto;margin-bottom:24px">
      <table>
        <thead><tr><th>模块</th><th>类型</th><th>描述</th><th>状态</th><th>使用中</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($modules)): ?><tr><td colspan="6" class="empty">暂无模块，先创建一个可复用模块</td></tr><?php endif; ?>
          <?php foreach ($modules as $m):
            $typeLabel = $blockTypes[$m['type']] ?? $m['type'];
            $usageCount = count(array_filter(get_landing_pages(), function($lp) use ($m) {
                return isset($lp['modules']) && in_array($m['id'], $lp['modules']);
            }));
          ?>
          <tr>
            <td>
              <strong><?=htmlspecialchars($m['name'])?></strong>
              <div class="text-sm text-muted"><?=htmlspecialchars(substr($m['block']['title'] ?? '', 0, 40) ?: '')?></div>
            </td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($typeLabel)?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(mb_substr($m['description'] ?? '', 0, 40))?></td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick="toggleModule('<?=htmlspecialchars($m['id'])?>')"><?=($m['enabled'] ?? false) ? '<span style="color:var(--ok)">● 启用</span>' : '<span style="color:var(--text-3)">○ 停用</span>'?></button>
            </td>
            <td><span class="badge badge-gray"><?=$usageCount?> 页</span></td>
            <td>
              <a href="page-builder.php?module=<?=urlencode($m['id'])?>" class="btn btn-ghost btn-sm">➕ 应用到页面</a>
              <a href="?edit=<?=urlencode($m['id'])?>" class="btn btn-ghost btn-sm">✏️</a>
              <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="deleteModule('<?=htmlspecialchars($m['id'])?>')">🗑</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 模块预览 / 使用说明 -->
    <div class="card" style="padding:16px">
      <h2 style="margin-bottom:12px">🔌 在落地页中使用模块</h2>
      <div style="font-size:13.5px;line-height:1.9;color:var(--text-3)">
        <p>1. 在「落地页构建器」中新建或编辑页面</p>
        <p>2. 点击「+ 从模块库插入」，选择已创建的模块</p>
        <p>3. 模块的内容会自动带入页面区块，可再微调</p>
        <p style="color:var(--muted)">当前版本：模块库用于统一管理可复用区块。应用按钮会跳转构建器并预填模块参数。</p>
      </div>
    </div>
  </div>
</div>

<!-- 新建/编辑模块弹窗 -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:560px;max-width:92vw;max-height:90vh;overflow-y:auto">
    <h3 style="margin:0 0 20px"><?=$editModule?'编辑模块':'新建模块'?></h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?=htmlspecialchars($editModule['id'] ?? '')?>">
      <div class="field-row">
        <div class="field"><label>模块名称</label><input type="text" name="name" required value="<?=htmlspecialchars($editModule['name'] ?? '')?>" placeholder="如：首页 Hero"></div>
        <div class="field"><label>区块类型</label><select name="type"><?php foreach ($blockTypes as $bt=>$bl): ?><option value="<?=$bt?>" <?=($editModule['type']??'')===$bt?'selected':''?>><?=$bl?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>描述</label><input type="text" name="description" value="<?=htmlspecialchars($editModule['description'] ?? '')?>" placeholder="这个模块用来做什么"></div>
      <div class="field"><label>标题</label><input type="text" name="block_title" value="<?=htmlspecialchars($editModule['block']['title'] ?? '')?>"></div>
      <div class="field"><label>副标题</label><input type="text" name="block_subtitle" value="<?=htmlspecialchars($editModule['block']['subtitle'] ?? '')?>"></div>
      <div class="field"><label>内容</label><textarea name="block_content" rows="3"><?=htmlspecialchars($editModule['block']['content'] ?? '')?></textarea></div>
      <div class="field-row">
        <div class="field"><label>按钮文案</label><input type="text" name="block_button_text" value="<?=htmlspecialchars($editModule['block']['button_text'] ?? '')?>"></div>
        <div class="field"><label>按钮链接</label><input type="text" name="block_button_url" value="<?=htmlspecialchars($editModule['block']['button_url'] ?? '')?>"></div>
      </div>
      <div class="field-row">
        <div class="field"><label>背景色</label><input type="text" name="block_bg_color" value="<?=htmlspecialchars($editModule['block']['bg_color'] ?? '')?>" placeholder="#f5f5f5"></div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=($editModule['enabled'] ?? true)?'checked':''?> style="width:18px;height:18px">启用模块</label></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">保存模块</button>
      </div>
    </form>
  </div>
</div>

<script>
function deleteModule(id) {
  if (!confirm('确定删除模块？')) return;
  var fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  fetch('page-modules.php', {method: 'POST', body: fd, headers: {'X-CSRF-Token': '<?=csrf_token()?>'}}).then(function(){ location.reload(); });
}
function toggleModule(id) {
  var fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('id', id);
  fetch('page-modules.php', {method: 'POST', body: fd, headers: {'X-CSRF-Token': '<?=csrf_token()?>'}}).then(function(){ location.reload(); });
}
</script>
<?php admin_footer(); ?>
