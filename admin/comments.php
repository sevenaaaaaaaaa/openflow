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
    header('Location: /xmp/comments' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
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
    <div class="v-head">
      <div><h1>评论 / 点评</h1><p class="v-sub">文章评论 + 导航站用户点评（打分） · 共 <?=count($all)?> 条<?php if ($pendingCount): ?> · <b style="color:var(--warn)"><?=$pendingCount?> 条待审核</b><?php endif; ?></p></div>
      <div class="v-actions"></div>
    </div>

    <div class="toolbar">
      <div class="ftabs">
        <a class="ftab <?=$type==='all'?'on':''?>" href="?">全部</a>
        <?php foreach (['article' => '文章', 'site' => '网站点评', 'product' => '产品', 'book' => '书籍', 'event' => '活动'] as $t => $label): ?>
        <a class="ftab <?=$type===$t?'on':''?>" href="?type=<?=$t?>"><?=$label?></a>
        <?php endforeach; ?>
      </div>
      <span class="tbar-meta"><?=count($list)?> 条</span>
    </div>

    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>作者</th><th>类型</th><th>目标</th><th>内容</th><th>评分</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($list)): ?>
          <tr><td colspan="7"><div class="empty">暂无<?=$type==='all' ? '' : '该类' ?>评论</div></td></tr>
          <?php else: foreach ($list as $c): $isPending = ($c['status'] ?? '') === 'pending'; ?>
          <tr>
            <td><span class="t-main"><?=htmlspecialchars($c['author'] ?? '')?></span><div class="t-sub mono" style="font-size:11px"><?=htmlspecialchars($c['created_at'] ?? '')?></div></td>
            <td><span class="tag"><?=comment_target_label($c['target_type'] ?? '')?></span></td>
            <td style="max-width:160px"><span class="t-main" style="font-weight:500"><?=htmlspecialchars($targetTitle = $c['target_type']==='site' ? ($navSites[$c['target_id']] ?? $c['target_id']) : ($c['target_type']==='article' ? ($articles[$c['target_id']] ?? $c['target_id']) : $c['target_id']))?></span></td>
            <td style="max-width:320px"><span class="t-sub" style="color:var(--muted);line-height:1.6"><?=htmlspecialchars(mb_substr($c['text'],0,80))?><?=mb_strlen($c['text'])>80?'…':''?></span></td>
            <td><?php if (!empty($c['rating'])): ?><span class="mono" style="color:var(--warn)"><?=str_repeat('★', (int)$c['rating'])?><?=str_repeat('☆', 5 - (int)$c['rating'])?></span><?php else: ?><span class="t-sub">—</span><?php endif; ?></td>
            <td>
              <?php if ($isPending): ?><span class="st st-warn">待审核</span>
              <?php elseif (!empty($c['pinned'])): ?><span class="st st-accent">置顶</span>
              <?php elseif (($c['status'] ?? '') === 'hidden'): ?><span class="st st-faint">已隐藏</span>
              <?php else: ?><span class="st st-ok">已通过</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <?php if ($isPending): ?><a href="?act=approve&id=<?=urlencode($c['id'])?>" class="btn btn-p btn-sm">通过</a><?php endif; ?>
              <a href="?act=pin&id=<?=urlencode($c['id'])?>" class="btn btn-s btn-sm"><?=!empty($c['pinned']) ? '取消置顶' : '置顶'?></a>
              <?php if (($c['status'] ?? '') !== 'hidden'): ?><a href="?act=hide&id=<?=urlencode($c['id'])?>" class="btn btn-s btn-sm">隐藏</a><?php endif; ?>
              <a href="?act=delete&id=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除？')">删除</a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
