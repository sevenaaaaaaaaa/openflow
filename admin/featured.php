<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('featured');
admin_header('推荐位管理');
$positions = FeaturedSystem::positions();
$allItems = FeaturedSystem::getItems();
$activeItems = FeaturedSystem::getActive();
?>
<div class="admin-layout">
  <?php admin_sidebar('featured'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 推荐位管理</h1>
      <div class="flex gap-2 ml-auto">
        <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">+ 添加推荐</button>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--accent)"><?=count($allItems)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">全部推荐</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--ok)"><?=count($activeItems)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">当前生效</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--muted)"><?=count($positions)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">推荐位类型</div>
      </div>
    </div>
    <?php foreach ($positions as $posKey => $posLabel): ?>
      <?php $posItems = array_filter($allItems, fn($i) => $i['position'] === $posKey); ?>
      <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);margin-bottom:16px;overflow:hidden">
        <div style="padding:16px 20px;background:var(--surface-2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
          <h3 style="margin:0;font-size:16px;color:var(--text)"><?=$posLabel?></h3>
          <span style="font-size:13px;color:var(--muted)"><?=count($posItems)?> 条</span>
        </div>
        <?php if (empty($posItems)): ?>
          <div style="padding:30px;text-align:center;color:var(--muted);font-size:14px">该推荐位暂无内容</div>
        <?php else: ?>
          <table style="width:100%;border-collapse:collapse">
            <thead><tr>
              <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">标题</th>
              <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">类型</th>
              <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">排序</th>
              <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">时间范围</th>
              <th style="padding:10px 20px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">状态</th>
              <th style="padding:10px 20px;text-align:right;font-weight:600;color:var(--muted);font-size:13px">操作</th>
            </tr></thead>
            <tbody>
            <?php foreach ($posItems as $item): ?>
              <tr style="border-top:1px solid var(--border)">
                <td style="padding:10px 20px;font-size:14px"><?=h($item['title'] ?? '')?></td>
                <td style="padding:10px 20px;font-size:13px"><span style="padding:2px 8px;border-radius:8px;background:var(--surface-2);color:var(--text)"><?=h($item['target_type'] ?? '')?></span></td>
                <td style="padding:10px 20px;font-size:13px;color:var(--muted)"><?=($item['sort_order'] ?? 0)?></td>
                <td style="padding:10px 20px;font-size:12px;color:var(--muted)"><?=h($item['start_at'] ?? '不限')?> ~ <?=h($item['end_at'] ?? '不限')?></td>
                <td style="padding:10px 20px">
                  <?php if ($item['enabled']): ?>
                    <span style="padding:2px 8px;border-radius:10px;font-size:12px;background:#d1fae5;color:#065f46">启用</span>
                  <?php else: ?>
                    <span style="padding:2px 8px;border-radius:10px;font-size:12px;background:var(--surface-2);color:var(--muted)">禁用</span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 20px;text-align:right">
                  <button onclick="toggleFeatured('<?=h($item['id'])?>','<?= $item['enabled'] ? 'false' : 'true' ?>')" style="padding:4px 8px;border-radius:4px;border:1px solid var(--border);background:none;cursor:pointer;font-size:12px;margin-right:4px"><?=$item['enabled']?'禁用':'启用'?></button>
                  <button onclick="removeFeatured('<?=h($item['id'])?>')" style="padding:4px 8px;border-radius:4px;border:1px solid var(--danger);color:var(--danger);background:none;cursor:pointer;font-size:12px">删除</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:480px;max-width:90vw;max-height:90vh;overflow-y:auto">
    <h3 style="margin:0 0 20px">添加推荐内容</h3>
    <form id="addForm">
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">推荐位</label><select name="position" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"><?php foreach ($positions as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">内容类型</label><select name="target_type" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"><option value="article">文章</option><option value="course">课程</option><option value="post">帖子</option><option value="product">商品</option></select></div>
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">内容ID</label><input name="target_id" required style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">标题</label><input name="title" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">排序 (越小越靠前)</label><input name="sort_order" type="number" value="0" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
        <div><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">开始时间</label><input name="start_at" type="datetime-local" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
        <div><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">结束时间</label><input name="end_at" type="datetime-local" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="padding:8px 20px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer">取消</button>
        <button type="submit" style="padding:8px 20px;border-radius:8px;background:var(--accent);color:var(--on-accent);border:none;cursor:pointer;font-weight:600">添加</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('addForm').onsubmit=function(e){
  e.preventDefault();
  const fd=new FormData(this);
  fd.append('csrf_token', '<?=csrf_token()?>');
  fetch('../api/featured-manage.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else ofAlert(d.error||'添加失败')});
};
function toggleFeatured(id,enabled){
  fetch('../api/featured-manage.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=toggle&id='+id+'&enabled='+enabled+'&csrf_token=<?=csrf_token()?>'}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else ofAlert(d.error||'操作失败')});
}
async function removeFeatured(id){
  if(!await ofConfirm('确定删除？'))return;
  fetch('../api/featured-manage.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=remove&id='+id+'&csrf_token=<?=csrf_token()?>'}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else ofAlert(d.error||'删除失败')});
}
</script>
<?php admin_footer(); ?>
