<?php
/**
 * 评论/点评管理 — 文章评论 + 导航站点评（审核/置顶/隐藏/删除）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CommentSystem.php';
require_once __DIR__ . '/../lib/KnowledgeSystem.php';
require_login();
require_perm('community-mod');

// 操作
if (isset($_GET['act']) && isset($_GET['id'])) {
    comment_admin($_GET['id'], $_GET['act']);
    flash('success', '操作成功');
    header('Location: comments.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
    exit;
}

$all = comments_all();
$type = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

$list = $all;
if ($type !== 'all') $list = array_values(array_filter($list, fn($c) => ($c['target_type'] ?? '') === $type));
if ($statusFilter !== 'all') $list = array_values(array_filter($list, fn($c) => ($c['status'] ?? 'approved') === $statusFilter));
usort($list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// 目标标题解析
$articles = [];
foreach (get_articles() as $a) $articles[$a['id']] = $a['title'];
$navSites = [];
foreach (json_read(DATA_DIR . '/navigation.json')['sites'] ?? [] as $s) $navSites[$s['id']] = $s['name'];

$pendingCount = count(array_filter($all, fn($c) => ($c['status'] ?? '') === 'pending'));

admin_header('评论管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('comments'); ?>
  <div class="main">
    <h1>💬 评论 / 点评</h1>
    <p class="sub">文章评论 + 导航站用户点评（打分） · 共 <?=count($all)?> 条<?php if ($pendingCount): ?> · <b style="color:var(--warn)"><?=$pendingCount?> 条待审核</b><?php endif; ?></p>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?" class="btn btn-ghost btn-sm <?=$type==='all'?'btn-primary':''?>">全部</a>
      <?php foreach (['article' => '文章', 'site' => '网站点评', 'product' => '产品', 'book' => '书籍', 'event' => '活动'] as $t => $label): ?>
      <a href="?type=<?=$t?>" class="btn btn-ghost btn-sm <?=$type===$t?'btn-primary':''?>"><?=$label?></a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($list)): ?>
    <div class="card empty" style="padding:40px">暂无<?=$type==='all' ? '' : '该类' ?>评论</div>
    <?php else: foreach ($list as $c): $isPending = ($c['status'] ?? '') === 'pending'; ?>
    <div class="card" style="margin-bottom:12px;<?=$isPending ? 'border-left:4px solid var(--warn)' : ''?>">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <strong style="font-size:14px"><?=htmlspecialchars($c['author'] ?? '')?></strong>
        <span class="badge" style="background:var(--surface-2);padding:2px 8px;border-radius:999px;font-size:11px"><?=comment_target_label($c['target_type'] ?? '')?></span>
        <span class="text-sm text-muted"><?=htmlspecialchars($targetTitle = $c['target_type']==='site' ? ($navSites[$c['target_id']] ?? $c['target_id']) : ($c['target_type']==='article' ? ($articles[$c['target_id']] ?? $c['target_id']) : $c['target_id']))?></span>
        <?php if (!empty($c['rating'])): ?><span style="color:#f59e0b;font-size:12px"><?=str_repeat('★', (int)$c['rating'])?><?=str_repeat('☆', 5 - (int)$c['rating'])?></span><?php endif; ?>
        <?php if ($isPending): ?><span class="badge" style="background:var(--warn);color:#fff;padding:2px 8px;border-radius:999px;font-size:11px">待审核</span><?php endif; ?>
        <?php if (!empty($c['pinned'])): ?><span class="badge" style="background:#b45309;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px">置顶</span><?php endif; ?>
        <span class="text-sm text-muted" style="margin-left:auto"><?=htmlspecialchars($c['created_at'] ?? '')?></span>
      </div>
      <div class="text-sm" style="margin-top:8px;line-height:1.7"><?=nl2br(htmlspecialchars($c['text']))?></div>
      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        <?php if ($isPending): ?><a href="?act=approve&id=<?=urlencode($c['id'])?>" class="btn btn-success btn-sm" style="background:var(--ok);color:#fff">✅ 通过</a><?php endif; ?>
        <a href="?act=pin&id=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm"><?=!empty($c['pinned']) ? '取消置顶' : '📌 置顶'?></a>
        <?php if (($c['status'] ?? '') !== 'hidden'): ?><a href="?act=hide&id=<?=urlencode($c['id'])?>" class="btn btn-ghost btn-sm">🙈 隐藏</a><?php endif; ?>
        <a href="?act=delete&id=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除？')">🗑 删除</a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
