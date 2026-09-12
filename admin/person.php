<?php
/**
 * 统一「人」详情 — /xmp/person?id=<email|member_id|uid|手机号>
 *
 * 一等公民的"这个人"：把 CDP 画像、CRM 线索、会员、订单、课程学习、订阅、互动
 * 合并成一个对象 + 一条跨模块时间线。CDP / CRM / 客户 三个 360 页都可以跳到这里。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ObjectGraph.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('crm');

// 加跟进（真实写回 CRM）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_followup'])) {
    csrf_verify();
    $q = trim((string)($_POST['id'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $person = og_person($q);
    if ($person && $content !== '') {
        $email = $person['emails'][0] ?? '';
        if ($email !== '' && function_exists('crm_add_followup')) { crm_add_followup($email, $content, $_SESSION['admin_name'] ?? ''); }
    }
    header('Location: /xmp/person?id=' . urlencode($q));
    exit;
}

$id = trim((string)($_GET['id'] ?? $_GET['uid'] ?? $_GET['email'] ?? ''));
$person = $id !== '' ? og_person($id) : null;
$summary = $person ? og_summary($person) : null;
$timeline = $person ? og_timeline($person, 100) : [];

$profile = $summary['profile'] ?? null;
$lead = $summary['lead'] ?? null;
$member = $summary['member'] ?? null;
$name = $person['name'] ?? ($member['name'] ?? ($lead['name'] ?? ($profile['properties']['name'] ?? '未命名')));

admin_header('统一用户 · ' . ($person ? $name : '查找'));
?>
<style>
.pn-head{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.pn-av{width:52px;height:52px;border-radius:14px;background:var(--accent-soft,oklch(0.95 0.03 262));color:var(--accent);display:grid;place-items:center;font-size:20px;font-weight:800;flex:none}
.pn-badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.pn-badge{font-size:11px;color:var(--muted);border:1px solid var(--border);border-radius:999px;padding:1px 9px}
.pn-tl{position:relative;padding-left:22px}
.pn-tl:before{content:'';position:absolute;left:6px;top:4px;bottom:4px;width:1px;background:var(--border)}
.pn-ev{position:relative;padding:9px 0}
.pn-ev:before{content:'';position:absolute;left:-19px;top:14px;width:9px;height:9px;border-radius:50%;background:var(--accent);box-shadow:0 0 0 3px var(--surface)}
.pn-ev .pn-at{font-size:11px;color:var(--faint,var(--muted));font-family:var(--font-mono,monospace)}
.pn-ev .pn-ti{font-size:13.5px;font-weight:600;display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.pn-ev .pn-de{font-size:12.5px;color:var(--muted);margin-top:2px;word-break:break-word}
.pn-attr{display:flex;justify-content:space-between;gap:10px;font-size:12.5px;padding:6px 0;border-bottom:1px solid var(--border-soft,var(--border))}
.pn-attr .k{color:var(--muted)}.pn-attr .v{text-align:right;word-break:break-all}
</style>
<div class="admin-layout">
  <?php admin_sidebar(''); ?>
  <div class="main">
    <div class="v-head">
      <div>
        <h1>统一用户</h1>
        <p class="v-sub">一个人 = 画像 + 线索 + 会员 + 订单 + 学习 + 互动，合并成一条时间线。输入邮箱 / 会员ID / 访客ID / 手机号即可。</p>
      </div>
      <div class="v-actions">
        <form method="get" style="display:flex;gap:8px">
          <input class="inp sm" name="id" value="<?=htmlspecialchars($id)?>" placeholder="邮箱 / 会员ID / 访客ID" style="min-width:220px">
          <button class="btn btn-p btn-sm">查找</button>
        </form>
      </div>
    </div>

    <?php if (!$id): ?>
    <div class="panel"><div class="p-body text-muted">输入一个邮箱、会员 ID、访客 ID 或手机号，查看这个人在系统里的全貌。</div></div>
    <?php elseif (!$person): ?>
    <div class="panel"><div class="p-body">没有找到「<?=htmlspecialchars($id)?>」对应的任何记录（画像 / 线索 / 会员 / 订单 / 学习）。</div></div>
    <?php else: ?>

    <div class="panel" style="margin-bottom:16px">
      <div class="p-body pn-head">
        <div class="pn-av"><?=htmlspecialchars(mb_substr($name, 0, 1))?></div>
        <div style="flex:1;min-width:220px">
          <div style="font-size:17px;font-weight:750"><?=htmlspecialchars($name)?></div>
          <div class="pn-badges">
            <span class="pn-badge"><?=htmlspecialchars($person['emails'][0] ?? '无邮箱')?></span>
            <?php if ($person['phone']): ?><span class="pn-badge"><?=htmlspecialchars($person['phone'])?></span><?php endif; ?>
            <?php foreach ($person['channels'] as $ch): ?><span class="pn-badge"><?=htmlspecialchars(['member'=>'会员','lead'=>'线索','customer'=>'客户','cdp'=>'画像'][$ch] ?? $ch)?></span><?php endforeach; ?>
            <?php if ($person['uids']): ?><span class="pn-badge">访客 <?=htmlspecialchars(mb_substr($person['uids'][0], 0, 10))?></span><?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($profile): ?><a class="btn btn-s btn-sm" href="/xmp/profile-detail?v=<?=urlencode($person['primary_uid'] ?: ($profile['visitor_id'] ?? ''))?>">CDP 画像</a><?php endif; ?>
          <?php if ($lead): ?><a class="btn btn-s btn-sm" href="/xmp/crm-lead-detail?email=<?=urlencode($person['emails'][0] ?? '')?>">CRM 线索</a><?php endif; ?>
          <a class="btn btn-s btn-sm" href="/xmp/orders">订单</a>
          <?php if (!$lead && ($person['emails'][0] ?? '') !== ''): ?><a class="btn btn-s btn-sm" href="/xmp/crm?tab=pipeline">建线索</a><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">累计消费</div><div class="k-val mono">¥<?=number_format((float)$summary['orders']['total'], 0)?></div><div class="k-sub"><?=$summary['orders']['paid']?> 笔已付 / 共 <?=$summary['orders']['count']?> 单</div></div>
      <div class="kpi"><div class="k-label">线索阶段</div><div class="k-val"><?=htmlspecialchars((string)($lead['stage'] ?? '—'))?></div><div class="k-sub"><?=$lead ? ('金额 ¥' . number_format((float)($lead['value'] ?? 0), 0)) : '未进入线索池'?></div></div>
      <div class="kpi"><div class="k-label">会员</div><div class="k-val"><?=$member ? '是' : '否'?></div><div class="k-sub"><?=htmlspecialchars((string)($member['plan'] ?? $member['level'] ?? ($member ? '普通会员' : '—')))?></div></div>
      <div class="kpi"><div class="k-label">学习课程</div><div class="k-val mono"><?=count($summary['courses'])?></div><div class="k-sub"><?=array_sum(array_column($summary['courses'], 'done'))?> 节完成</div></div>
      <div class="kpi"><div class="k-label">订阅</div><div class="k-val"><?=$summary['sub'] ? htmlspecialchars((string)($summary['sub']['status'] ?? '活跃')) : '—'?></div><div class="k-sub"><?=htmlspecialchars((string)($summary['sub']['expires_at'] ?? '无'))?></div></div>
      <div class="kpi"><div class="k-label">最后订单</div><div class="k-val" style="font-size:15px"><?=htmlspecialchars(substr((string)$summary['orders']['last'], 0, 10) ?: '—')?></div><div class="k-sub">时间线最近见右</div></div>
    </div>

    <div class="panels" style="margin-top:16px;display:grid;grid-template-columns:1fr 1.4fr;gap:16px;align-items:start">
      <div class="panel">
        <div class="p-head"><h3>画像属性</h3></div>
        <div class="p-body">
          <?php $props = (array)($profile['properties'] ?? []); ?>
          <?php if (!$props): ?><div class="text-muted" style="font-size:13px">暂无 CDP 属性。</div><?php endif; ?>
          <?php foreach ($props as $k => $v): ?>
          <div class="pn-attr"><span class="k"><?=htmlspecialchars((string)$k)?></span><span class="v"><?=htmlspecialchars(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v)?></span></div>
          <?php endforeach; ?>
          <?php $tags = array_keys((array)($profile['tags'] ?? [])); if ($tags): ?>
          <div style="margin-top:12px"><div class="text-muted" style="font-size:12px;margin-bottom:6px">标签</div>
            <div class="pn-badges"><?php foreach ($tags as $t): ?><span class="pn-badge"><?=htmlspecialchars((string)$t)?></span><?php endforeach; ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="p-head"><h3>跨模块时间线</h3><span class="p-sub mono"><?=count($timeline)?> 条</span></div>
        <div class="p-body">
          <?php if ($lead): ?>
          <form method="post" style="display:flex;gap:8px;margin-bottom:14px" data-no-guard>
            <input type="hidden" name="_csrf_token" value="<?=csrf_token()?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars($id)?>">
            <input class="inp sm" name="content" placeholder="加一条跟进记录…" style="flex:1">
            <button class="btn btn-p btn-sm" name="add_followup" value="1">加跟进</button>
          </form>
          <?php endif; ?>
          <?php if (!$timeline): ?><div class="text-muted" style="font-size:13px">还没有可展示的行为记录。</div><?php endif; ?>
          <div class="pn-tl">
            <?php foreach ($timeline as $t): ?>
            <div class="pn-ev">
              <div class="pn-at"><?=htmlspecialchars(substr((string)$t['at'], 0, 16))?></div>
              <div class="pn-ti"><span><?=htmlspecialchars($t['icon'])?></span><span><?=htmlspecialchars($t['title'])?></span><span class="pn-badge"><?=htmlspecialchars($t['type'])?></span></div>
              <?php if ($t['detail'] !== ''): ?><div class="pn-de"><?=htmlspecialchars($t['detail'])?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
