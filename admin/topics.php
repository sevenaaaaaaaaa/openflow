<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('topics');

$message = '';
$topics = get_topics();
$articles = get_articles();
$publishedArticles = array_values(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));

// 文章标题索引
$artTitles = [];
foreach ($articles as $a) $artTitles[$a['id']] = $a['title'] ?? $a['slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['add'])) {
        $t = [
            'id' => 'topic_' . substr(bin2hex(random_bytes(6)), 0, 12),
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'description' => $_POST['description'] ?? '',
            'cover' => $_POST['cover'] ?? '',
            'seo_title' => $_POST['seo_title'] ?? '',
            'seo_desc' => $_POST['seo_desc'] ?? '',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'status' => $_POST['status'] ?? 'draft',
            'article_ids' => array_filter($_POST['article_ids'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($t['slug'])) {
            $t['slug'] = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}-]/u', '-', $t['title']);
            $t['slug'] = preg_replace('/-+/', '-', trim($t['slug'], '-'));
        }
        $topics[] = $t;
        save_topics($topics);
        $message = '专题已添加';
    }
    if (isset($_POST['update'])) {
        foreach ($topics as &$t) {
            if ($t['id'] === $_POST['id']) {
                $t['title'] = $_POST['title'] ?? $t['title'];
                $t['slug'] = $_POST['slug'] ?? $t['slug'];
                $t['description'] = $_POST['description'] ?? $t['description'];
                $t['cover'] = $_POST['cover'] ?? $t['cover'];
                $t['seo_title'] = $_POST['seo_title'] ?? $t['seo_title'] ?? '';
                $t['seo_desc'] = $_POST['seo_desc'] ?? $t['seo_desc'] ?? '';
                $t['sort_order'] = (int)($_POST['sort_order'] ?? $t['sort_order'] ?? 0);
                $t['status'] = $_POST['status'] ?? $t['status'];
                $t['article_ids'] = array_filter($_POST['article_ids'] ?? $t['article_ids'] ?? []);
                break;
            }
        }
        save_topics($topics);
        $message = '专题已更新';
    }
    if (isset($_POST['delete'])) {
        $topics = array_values(array_filter($topics, fn($t) => $t['id'] !== $_POST['delete']));
        save_topics($topics);
        $message = '专题已删除';
    }
    $topics = get_topics();
}

$sortTop = $topics;
usort($sortTop, fn($a, $b) => (($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0)));

admin_header('专题管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('topics'); ?>
  <div class="main">
    <h1>专题管理</h1>
    <p class="sub">管理精选专题（Topic），用于 Flow 社区首页展示 · 按「排序权重」升序展示</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">当前专题（<?=count($topics)?>）</h2>
      <table>
        <thead><tr><th>封面</th><th>标题</th><th>文章</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($topics)): ?><tr><td colspan="6" class="empty">暂无专题</td></tr><?php endif; ?>
          <?php foreach ($sortTop as $t):
            $cnt = count($t['article_ids'] ?? []);
          ?>
          <tr>
            <td><?php if (!empty($t['cover'])): ?><img src="/<?=ltrim($t['cover'],'/')?>" style="width:64px;height:40px;object-fit:cover;border-radius:8px" onerror="this.style.display='none'"><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            <td>
              <strong><?=htmlspecialchars($t['title'])?></strong>
              <div class="text-sm text-muted" style="font-size:11px">/<?=htmlspecialchars($t['slug'])?></div>
            </td>
            <td><span class="badge <?=$cnt>0?'badge-green':'badge-gray'?>"><?=$cnt?> 篇</span></td>
            <td class="text-sm text-muted"><?=$t['sort_order'] ?? 0?></td>
            <td><span class="badge <?=$t['status']==='published'?'badge-green':'badge-yellow'?>"><?=$t['status']??'draft'?></span></td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick='editTopic(<?=json_encode($t, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>)'>编辑</button>
              <a href="../content-preview.php?type=topic&id=<?=htmlspecialchars($t['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a>
              <form method="post" style="display:inline" data-confirm="确认删除?">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="addForm">
      <h2 id="formTitle">添加专题</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="add" id="formAction" value="1">
        <input type="hidden" name="id" id="editId" value="">
        <div class="field-row">
          <div class="field"><label>标题</label><input type="text" name="title" id="f_title" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" id="f_slug" placeholder="自动生成"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>描述</label><textarea name="description" id="f_desc" rows="2"></textarea></div>
          <div class="field"><label>封面图 URL</label><input type="text" name="cover" id="f_cover" placeholder="assets/images/..." oninput="document.getElementById('coverPrev').src=this.value||'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2764%27 height=%2740%27%3E%3Crect width=%2764%27 height=%2740%27 rx=%278%27 fill=%27%23e5e7eb%27/%3E%3C/svg%3E'"><div style="margin-top:6px"><img id="coverPrev" src="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2764%27 height=%2740%27%3E%3Crect width=%2764%27 height=%2740%27 rx=%278%27 fill=%27%23e5e7eb%27/%3E%3C/svg%3E" style="height:64px;border-radius:8px;object-fit:cover;background:var(--surface-2)" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2764%27 height=%2740%27%3E%3Crect width=%2764%27 height=%2740%27 rx=%278%27 fill=%27%23e5e7eb%27/%3E%3C/svg%3E'"></div></div>
        </div>
        <div class="field-row">
          <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" id="f_seo_title"></div>
          <div class="field"><label>排序权重 <span class="hint">· 小的在前</span></label><input type="number" name="sort_order" id="f_sort_order" value="0"></div>
        </div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" id="f_seo_desc" rows="2"></textarea></div>

        <!-- 关联文章选择 -->
        <div class="card" style="margin:14px 0;padding:14px;background:var(--surface-2)">
          <h2>📄 关联文章（已选 <span id="artCount">0</span> 篇）</h2>
          <div id="artList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px;max-height:240px;overflow-y:auto;margin-top:8px">
            <?php foreach ($publishedArticles as $a): ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;cursor:pointer;padding:6px 8px;border-radius:8px;background:var(--surface)">
              <input type="checkbox" name="article_ids[]" value="<?=htmlspecialchars($a['id'])?>" class="art-chk" onchange="updateArtCount()">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(mb_substr($a['title'],0,26))?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php if (empty($publishedArticles)): ?><p class="text-sm text-muted">暂无可关联的已发布文章</p><?php endif; ?>
        </div>

        <div class="field"><label>状态</label><select name="status" id="f_status"><option value="draft">草稿</option><option value="published">已发布</option></select></div>
        <button type="submit" class="btn btn-primary" id="submitBtn">添加</button>
        <button type="button" class="btn btn-ghost" onclick="resetForm()" style="display:none" id="cancelBtn">取消</button>
      </form>
    </div>
  </div>
</div>
<script>
function updateArtCount(){ document.getElementById('artCount').textContent = document.querySelectorAll('.art-chk:checked').length; }
function editTopic(t){
  document.getElementById('formTitle').textContent='编辑专题';
  document.getElementById('formAction').name='update';
  document.getElementById('editId').value=t.id;
  document.getElementById('f_title').value=t.title||'';
  document.getElementById('f_slug').value=t.slug||'';
  document.getElementById('f_desc').value=t.description||'';
  document.getElementById('f_cover').value=t.cover||'';
  document.getElementById('f_seo_title').value=t.seo_title||'';
  document.getElementById('f_seo_desc').value=t.seo_desc||'';
  document.getElementById('f_sort_order').value=t.sort_order||0;
  document.getElementById('f_status').value=t.status||'draft';
  var prev=document.getElementById('coverPrev');
  if(t.cover) prev.src='/'+t.cover.replace(/^\//,'');
  document.querySelectorAll('.art-chk').forEach(function(c){ c.checked = (t.article_ids||[]).indexOf(c.value)>=0; });
  updateArtCount();
  document.getElementById('submitBtn').textContent='保存';
  document.getElementById('cancelBtn').style.display='inline-flex';
  document.getElementById('addForm').scrollIntoView({behavior:'smooth'});
}
function resetForm(){
  document.getElementById('formTitle').textContent='添加专题';
  document.getElementById('formAction').name='add';
  document.getElementById('editId').value='';
  document.getElementById('f_title').value='';document.getElementById('f_slug').value='';
  document.getElementById('f_desc').value='';document.getElementById('f_cover').value='';
  document.getElementById('f_seo_title').value='';document.getElementById('f_seo_desc').value='';
  document.getElementById('f_sort_order').value='0';
  document.getElementById('f_status').value='draft';
  document.getElementById('coverPrev').src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2764%27 height=%2740%27%3E%3Crect width=%2764%27 height=%2740%27 rx=%278%27 fill=%27%23e5e7eb%27/%3E%3C/svg%3E';
  document.querySelectorAll('.art-chk').forEach(function(c){ c.checked=false; });
  updateArtCount();
  document.getElementById('submitBtn').textContent='添加';
  document.getElementById('cancelBtn').style.display='none';
}
</script>
<?php admin_footer(); ?>
