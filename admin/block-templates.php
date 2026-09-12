<?php
/**
 * 模块组合模板 — 成套区块一次插入；内置业务模板 + 另存为/导出导入/应用
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/BlockTemplate.php';
require_once __DIR__ . '/../lib/BuilderPages.php';
require_login();
require_perm('pages');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'save') {
        $blocks = json_decode((string)($_POST['blocks_json'] ?? '[]'), true);
        $r = btpl_save(['id' => $_POST['id'] ?? '', 'name' => $_POST['name'] ?? '', 'category' => $_POST['category'] ?? '', 'description' => $_POST['description'] ?? '', 'blocks' => is_array($blocks) ? $blocks : []]);
        $msg = $r['ok'] ? '模板已保存' : ('保存失败：' . ($r['error'] ?? ''));
    } elseif ($act === 'delete') {
        btpl_delete((string)($_POST['id'] ?? ''));
        $msg = '模板已删除';
    } elseif ($act === 'apply') {
        $r = btpl_apply((string)($_POST['page_id'] ?? ''), (string)($_POST['id'] ?? ''));
        $msg = $r['ok'] ? ('已插入 ' . $r['added'] . ' 个区块到页面') : ('插入失败：' . ($r['error'] ?? ''));
    } elseif ($act === 'from_page') {
        $p = builder_page_get((string)($_POST['page_id'] ?? ''));
        $msg = $p ? ('已载入页面「' . ($p['title'] ?? '') . '」的 ' . count((array)($p['blocks'] ?? [])) . ' 个区块，可另存为模板') : '页面不存在';
    }
}

$templates = btpl_all();
$pages = builder_pages_all();
$edit = isset($_GET['edit']) ? btpl_get((string)$_GET['edit']) : null;
$prefillBlocks = '';
if ($edit && !empty($edit['blocks'])) $prefillBlocks = json_encode($edit['blocks'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
elseif (!empty($_POST['from_page']) || (($_POST['action'] ?? '') === 'from_page')) {
    $p = builder_page_get((string)($_POST['page_id'] ?? ''));
    if ($p) $prefillBlocks = json_encode($p['blocks'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
$catLabel = fn($t) => ($t['category'] ?? '') !== '' ? $t['category'] : '其他';
admin_header('模块组合模板');
?>
<div class="admin-layout">
  <?php admin_sidebar('block-templates'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>模块组合模板</h1><p class="v-sub">一组成套区块一次插入——内置课程招生/活动报名/产品发布/预约诊断 四套业务模板。</p></div>
      <div class="v-actions"><a class="btn btn-s btn-sm" href="/xmp/page-builder">落地页构建器</a></div>
    </div>
    <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid var(--ok)"><?=htmlspecialchars($msg)?></div><?php endif; ?>

    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="flex:2;min-width:320px">
        <?php if (!$templates): ?><div class="card"><div class="empty" style="padding:20px">暂无模板。</div></div><?php endif; ?>
        <?php foreach ($templates as $t): $seed = btpl_is_seed((string)$t['id']); ?>
        <div class="card" style="margin-bottom:10px">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <b><?=htmlspecialchars($t['name'])?></b>
            <span class="badge badge-gray"><?=htmlspecialchars($catLabel($t))?></span>
            <?php if ($seed): ?><span class="badge badge-green">内置</span><?php endif; ?>
            <span class="text-sm text-muted"><?=count((array)($t['blocks'] ?? []))?> 个区块</span>
          </div>
          <?php if (!empty($t['description'])): ?><div class="text-sm text-muted" style="margin-top:4px"><?=htmlspecialchars($t['description'])?></div><?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center">
            <form method="post" style="display:flex;gap:6px;align-items:center" data-no-guard>
              <?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>">
              <select name="page_id" style="min-width:180px"><option value="">选择页面…</option><?php foreach ($pages as $p): ?><option value="<?=htmlspecialchars($p['id'])?>"><?=htmlspecialchars($p['title'] ?? $p['id'])?></option><?php endforeach; ?></select>
              <button class="btn btn-p btn-sm">插入此模板</button>
            </form>
            <?php if (!$seed): ?>
            <a class="btn btn-ghost btn-sm" href="?edit=<?=urlencode($t['id'])?>">编辑</a>
            <form method="post" data-no-guard data-confirm="删除该模板？"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>"><button class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button></form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="flex:1;min-width:300px">
        <div class="card">
          <h2 style="font-size:15px"><?=$edit ? '编辑模板' : '新建模板'?></h2>
          <form method="post" style="margin-bottom:12px">
            <?= csrf_field() ?><input type="hidden" name="action" value="from_page">
            <div class="field"><label>从落地页另存为模板</label>
              <div style="display:flex;gap:6px"><select name="page_id" style="flex:1"><option value="">选择页面…</option><?php foreach ($pages as $p): ?><option value="<?=htmlspecialchars($p['id'])?>"><?=htmlspecialchars($p['title'] ?? $p['id'])?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm">载入区块</button></div>
            </div>
          </form>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit && !btpl_is_seed((string)$edit['id'])): ?><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>"><?php endif; ?>
            <div class="field"><label>模板名</label><input class="inp" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required></div>
            <div class="field"><label>分类</label><input class="inp" name="category" value="<?=htmlspecialchars($edit['category'] ?? '')?>" placeholder="课程 / 活动 / 产品 / 服务"></div>
            <div class="field"><label>描述</label><input class="inp" name="description" value="<?=htmlspecialchars($edit['description'] ?? '')?>"></div>
            <div class="field"><label>区块(JSON 数组) <span class="hint">· 每项含 _type 与字段，如 [{"_type":"hero","title":"标题"}]</span></label>
              <textarea class="inp" name="blocks_json" rows="8" style="font-family:var(--mono);font-size:12px" placeholder='[{"_type":"hero","title":"标题","button_text":"立即报名","button_url":"#enroll"}]'><?=htmlspecialchars($prefillBlocks)?></textarea>
            </div>
            <button class="btn btn-p btn-sm"><?=$edit ? '更新模板' : '创建模板'?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
