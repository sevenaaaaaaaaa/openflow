<?php
/**
 * 公司知识库 — AI agent 可检索
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/KnowledgeSystem.php';
require_login();
require_perm('settings');

$docs = knowledge_get();
$message = '';

// 添加文档
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    knowledge_add([
        'title' => trim($_POST['title'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'category' => $_POST['category'] ?? 'general',
        'tags' => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
    ]);
    $message = '知识已添加';
}

// 删除
if (isset($_GET['delete'])) {
    $docs = array_values(array_filter($docs, fn($d) => $d['id'] !== $_GET['delete']));
    knowledge_save($docs);
    flash('success', '知识已删除');
    header('Location: /xmp/knowledge');
    exit;
}

// 测试检索
$searchResult = null;
if (isset($_GET['q'])) $searchResult = knowledge_search($_GET['q'], 5);

$cats = ['general'=>'通用','product'=>'产品','company'=>'公司','hr'=>'人力资源','market'=>'市场','ops'=>'运营'];

admin_header('知识库');
?>
<div class="admin-layout">
  <?php admin_sidebar('knowledge'); ?>
  <div class="main">
    <h1> 公司知识库</h1>
    <p class="sub">沉淀公司知识 · AI agent 自动检索参考（RAG）</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="kb-grid">
      <!-- 添加 -->
      <div>
        <form method="post" class="card">
          <?= csrf_field() ?>
          <h2>➕ 添加知识</h2>
          <div class="field"><label>标题</label><input type="text" name="title" required placeholder="知识标题"></div>
          <div class="field-row">
            <div class="field"><label>分类</label><select name="category"><?php foreach ($cats as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></div>
            <div class="field"><label>标签 <span class="hint">· 逗号分隔</span></label><input type="text" name="tags" placeholder="产品, 定价, 网站增长"></div>
          </div>
          <div class="field"><label>内容 <span class="hint">· 供 AI 参考</span></label><textarea name="content" rows="8" placeholder="详细知识内容（产品信息、FAQ、公司介绍等）"></textarea></div>
          <button type="submit" name="save" class="btn btn-primary">保存知识</button>
        </form>

        <!-- 测试检索 -->
        <form method="get" class="card" style="margin-top:16px">
          <h2>🔍 测试 AI 检索</h2>
          <div style="display:flex;gap:8px">
            <input type="text" name="q" value="<?=htmlspecialchars($_GET['q'] ?? '')?>" placeholder="输入问题，如：你们的价格体系" style="flex:1;padding:9px;border:1.5px solid var(--border);border-radius:8px">
            <button type="submit" class="btn btn-primary">检索</button>
          </div>
          <?php if ($searchResult !== null): ?>
          <div style="margin-top:12px">
            <?php if (empty($searchResult)): ?>
            <p class="text-sm text-muted">未找到相关知识</p>
            <?php else: foreach ($searchResult as $d): ?>
            <div style="padding:8px;background:var(--surface-2);border-radius:8px;margin-bottom:6px;font-size:13px"><b><?=htmlspecialchars($d['title'])?></b><div class="text-muted" style="font-size:11px"><?=htmlspecialchars(mb_substr(strip_tags($d['content']),0,80))?>…</div></div>
            <?php endforeach; endif; ?>
          </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- 列表 -->
      <div class="card" style="padding:0;overflow:auto;align-self:start">
        <h2 style="padding:20px 20px 0">📄 知识列表（<?=count($docs)?>）</h2>
        <table>
          <thead><tr><th>标题</th><th>分类</th><th>标签</th><th>操作</th></tr></thead>
          <tbody>
            <?php if (empty($docs)): ?><tr><td colspan="4" class="empty">暂无知识，先添加</td></tr><?php endif; ?>
            <?php foreach (array_reverse($docs) as $d): ?>
            <tr>
              <td style="max-width:200px"><strong><?=htmlspecialchars($d['title'])?></strong>
                <div class="text-muted" style="font-size:11px"><?=htmlspecialchars(mb_substr(strip_tags($d['content']),0,50))?>…</div>
              </td>
              <td class="text-sm text-muted"><?=$cats[$d['category'] ?? 'general'] ?? $d['category']?></td>
              <td class="text-sm text-muted"><?=htmlspecialchars(implode('、', array_slice($d['tags'] ?? [],0,3)))?></td>
              <td><a href="?delete=<?=urlencode($d['id'])?>" class="btn btn-danger btn-sm" data-confirm="确认删除?">删除</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.kb-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
