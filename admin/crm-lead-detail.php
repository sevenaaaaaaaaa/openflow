<?php
/**
 * 线索 360° 详情 —— 对标 Salesforce Lead 详情 / HubSpot contact record
 * 聚合：线索基本信息(可编辑) + 阶段/评分/金额/负责人 + 跟进记录 + 待办任务 + CDP 画像 + 来源归因
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('settings');

$crm = crm_get();
$leads = $crm['leads'] ?? [];
$email = strtolower(trim((string)($_GET['email'] ?? '')));
$leadKey = ''; $lead = null;
foreach ($leads as $key => $l) if (mb_strtolower($l['email'] ?? '') === $email) { $leadKey = $key; $lead = $l; break; }
if (!$lead) { header('Location: /xmp/crm?tab=pipeline'); exit; }

// 保存编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'save_lead') {
        $update = [
            'name' => trim($_POST['name'] ?? ''),
            'company' => trim($_POST['company'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'stage' => $_POST['stage'] ?? 'new',
            'value' => (float)($_POST['value'] ?? 0),
            'owner' => trim($_POST['owner'] ?? ''),
            'expected_close' => trim($_POST['expected_close'] ?? ''),
        ];
        crm_update_lead($lead['email'], $update);
        flash('success', '线索已更新');
        header('Location: /xmp/crm-lead-detail?email=' . urlencode($lead['email'])); exit;
    } elseif ($act === 'add_followup') {
        crm_add_followup($lead['email'], trim($_POST['content'] ?? ''), trim($_POST['owner'] ?? ''));
        flash('success', '跟进记录已添加');
        header('Location: /xmp/crm-lead-detail?email=' . urlencode($lead['email'])); exit;
    }
}

// 重新读取（更新后）
$lead = $leads[$leadKey] ?? $lead;
$stages = crm_stages();
// CDP 画像（按 email）
$profile = null;
try { $profiles = CdpSystem::allProfiles(); foreach ((array)$profiles as $p) if (mb_strtolower($p['properties']['email'] ?? '') === mb_strtolower($lead['email'] ?? '')) { $profile = $p; break; } } catch (Throwable $e) {}
$followUps = $lead['follow_ups'] ?? [];
$tasks = $lead['tasks'] ?? [];
$daysSince = crm_days_since_activity($lead['email']);

admin_header('线索详情');
?>
<div class="admin-layout"><?php admin_sidebar('crm'); ?><div class="main">
  <div class="v-head"><div><h1><?=htmlspecialchars($lead['name'] ?: $lead['email'])?> <span style="font-size:14px;color:var(--muted);font-weight:400"><?=htmlspecialchars($lead['company'] ?? '')?></span></h1>
    <p class="v-sub"><?=htmlspecialchars($lead['email'])?> · 来源 <?=htmlspecialchars($lead['source'] ?? '—')?> · 距上次动作 <?=is_numeric($daysSince)?$daysSince.' 天':'—'?></p></div>
    <div class="v-actions"><a href="/xmp/crm?tab=pipeline" class="btn btn-s btn-sm">← 返回管道</a></div></div>

  <!-- 概览卡 -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
    <div class="card" style="padding:16px;text-align:center"><div style="font-size:22px;font-weight:800;color:var(--accent)"><?=htmlspecialchars($stages[$lead['stage']] ?? $lead['stage'])?></div><div style="font-size:12px;color:var(--muted)">阶段</div></div>
    <div class="card" style="padding:16px;text-align:center"><div style="font-size:24px;font-weight:800;color:<?=($lead['score']??0)>=60?'var(--ok)':(($lead['score']??0)>=30?'var(--warn)':'var(--danger)')?>"><?=$lead['score'] ?? 0?></div><div style="font-size:12px;color:var(--muted)">评分</div></div>
    <div class="card" style="padding:16px;text-align:center"><div style="font-size:24px;font-weight:800;color:var(--ok)">¥<?=number_format($lead['value'] ?? 0,0)?></div><div style="font-size:12px;color:var(--muted)">商机金额</div></div>
    <div class="card" style="padding:16px;text-align:center"><div style="font-size:20px;font-weight:800"><?=htmlspecialchars($lead['owner'] ?? '未分配')?></div><div style="font-size:12px;color:var(--muted)">跟进人</div></div>
    <div class="card" style="padding:16px;text-align:center"><div style="font-size:20px;font-weight:800"><?=count($followUps)?></div><div style="font-size:12px;color:var(--muted)">跟进次数</div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="lead-grid">
    <!-- 左：编辑基本信息 -->
    <div class="card" style="padding:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">编辑线索</h3>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_lead">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
          <div><label class="text-xs text-muted">姓名</label><input class="inp" name="name" value="<?=htmlspecialchars($lead['name']??'')?>"></div>
          <div><label class="text-xs text-muted">公司</label><input class="inp" name="company" value="<?=htmlspecialchars($lead['company']??'')?>"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
          <div><label class="text-xs text-muted">电话</label><input class="inp" name="phone" value="<?=htmlspecialchars($lead['phone']??'')?>"></div>
          <div><label class="text-xs text-muted">跟进人</label><input class="inp" name="owner" value="<?=htmlspecialchars($lead['owner']??'')?>" placeholder="指定负责人"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
          <div><label class="text-xs text-muted">阶段</label><select class="inp" name="stage"><?php foreach ($stages as $k=>$v): ?><option value="<?=$k?>" <?=($lead['stage']??'')===$k?'selected':''?>><?=htmlspecialchars($v)?></option><?php endforeach; ?></select></div>
          <div><label class="text-xs text-muted">商机金额 ¥</label><input class="inp" type="number" name="value" value="<?=htmlspecialchars($lead['value']??0)?>"></div>
        </div>
        <div style="margin-bottom:12px"><label class="text-xs text-muted">预计成交</label><input class="inp" type="date" name="expected_close" value="<?=htmlspecialchars($lead['expected_close']??'')?>"></div>
        <button class="btn btn-primary btn-sm">保存</button>
      </form>
    </div>

    <!-- 右：CDP 画像 + 来源归因 -->
    <div class="card" style="padding:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">CDP 画像 · 来源归因</h3>
      <?php if ($profile): $prop = $profile['properties'] ?? []; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12.5px">
        <div style="padding:8px;background:var(--bg);border-radius:8px">渠道：<b><?=htmlspecialchars($prop['channel'] ?? '—')?></b></div>
        <div style="padding:8px;background:var(--bg);border-radius:8px">设备：<b><?=htmlspecialchars($prop['os'] ?? '—')?> / <?=htmlspecialchars($prop['browser'] ?? '—')?></b></div>
        <div style="padding:8px;background:var(--bg);border-radius:8px">城市：<b><?=htmlspecialchars($prop['city'] ?? '—')?></b></div>
        <div style="padding:8px;background:var(--bg);border-radius:8px">事件：<b><?=$profile['events_count'] ?? 0?></b></div>
        <div style="padding:8px;background:var(--bg);border-radius:8px">生命周期：<b><?=htmlspecialchars($profile['lifecycle']['stage'] ?? '—')?></b></div>
        <div style="padding:8px;background:var(--bg);border-radius:8px">累计消费：<b style="color:var(--ok)">¥<?=number_format($profile['summaries']['purchase_amount_total'] ?? 0,0)?></b></div>
      </div>
      <div style="margin-top:10px;padding:8px;background:var(--bg);border-radius:8px;font-size:12.5px">标签：<?php $tags=$profile['tags']??[]; foreach (array_keys($tags) as $t): ?><span class="inline-tag"><?=htmlspecialchars($t)?></span> <?php endforeach; if(!$tags) echo '<span class="text-muted">—</span>';?></div>
      <?php else: ?><p style="color:var(--faint);font-size:13px">无 CDP 画像（该邮箱无行为数据）</p><?php endif; ?>
    </div>
  </div>

  <!-- 跟进记录 + 待办 -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px" class="lead-grid">
    <div class="card" style="padding:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">跟进记录</h3>
      <form method="post" style="margin-bottom:14px"><?= csrf_field() ?><input type="hidden" name="action" value="add_followup">
        <div style="display:flex;gap:8px"><input class="inp" name="content" placeholder="记录一次跟进…" required style="flex:1"><input class="inp" name="owner" placeholder="跟进人" value="<?=htmlspecialchars($lead['owner']??'')?>" style="width:110px"><button class="btn btn-primary btn-sm">添加</button></div>
      </form>
      <?php if (empty($followUps)): ?><p style="color:var(--faint);font-size:13px">暂无跟进记录</p>
      <?php else: foreach (array_reverse($followUps) as $f): ?>
      <div style="padding:10px 12px;border-radius:10px;background:var(--bg);margin-bottom:8px;font-size:12.5px">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--faint)"><b><?=htmlspecialchars($f['owner'] ?? '')?></b><span><?=htmlspecialchars($f['time'] ?? '')?></span></div>
        <div style="margin-top:4px;color:var(--muted)"><?=htmlspecialchars($f['content'] ?? '')?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="card" style="padding:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">待办任务</h3>
      <?php if (empty($tasks)): ?><p style="color:var(--faint);font-size:13px">暂无待办任务</p>
      <?php else: foreach ($tasks as $t): ?>
      <div style="padding:10px 12px;border-radius:10px;background:var(--bg);margin-bottom:8px;font-size:12.5px">
        <div style="display:flex;justify-content:space-between"><b><?=htmlspecialchars($t['title'] ?? '')?></b><span style="color:<?=!empty($t['due'])?'var(--warn)':'var(--faint)'?>"><?=htmlspecialchars($t['due'] ?? '')?></span></div>
        <div style="margin-top:4px;color:var(--muted)"><?=htmlspecialchars($t['done'] ? '已完成' : '进行中')?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- 订单记录 -->
  <?php
  $orders = [];
  try { $orders = Database::query("SELECT * FROM orders WHERE member_email = ? OR member_id = ? ORDER BY created_at DESC LIMIT 20", [$lead['email'] ?? '', $lead['member_id'] ?? '']); } catch (Exception $e) {}
  ?>
  <div class="card" style="padding:20px;margin-top:20px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">订单记录</h3>
    <?php if (empty($orders)): ?><p style="color:var(--faint);font-size:13px">暂无订单</p>
    <?php else: ?>
    <div style="overflow:auto"><table style="width:100%;font-size:13px">
      <thead><tr style="text-align:left;color:var(--muted);border-bottom:1px solid var(--border)"><th style="padding:8px">订单号</th><th>商品</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): $st = ['paid'=>'已支付','pending'=>'待支付','shipped'=>'已发货','refunded'=>'已退款','cancelled'=>'已取消'][$o['status']] ?? $o['status']; ?>
        <tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:8px;color:var(--muted)"><?=htmlspecialchars(substr($o['id']??'',-12))?></td><td><?=htmlspecialchars(mb_substr($o['course_title'] ?? '',0,24))?></td><td style="color:var(--ok);font-weight:600">¥<?=number_format($o['amount']??0,2)?></td><td style="color:<?=$o['status']==='paid'?'var(--ok)':'var(--warn)'?>"><?=$st?></td><td style="color:var(--faint);font-size:12px"><?=substr($o['created_at']??'',0,10)?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <!-- 历史活动（CDP 行为时间线） -->
  <div class="card" style="padding:20px;margin-top:20px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">历史活动</h3>
    <?php
    $timeline = [];
    try {
      foreach (array_slice(array_reverse(CdpSystem::allEvents()), 0, 1000) as $e) {
        $evEmail = (string)($e['properties']['email'] ?? '');
        if (mb_strtolower($evEmail) === mb_strtolower($lead['email'] ?? '') || ($e['visitor_id'] ?? '') === ($profile['visitor_id'] ?? '')) $timeline[] = $e;
        if (count($timeline) >= 30) break;
      }
    } catch (Throwable $e) {}
    ?>
    <?php if (empty($timeline)): ?><p style="color:var(--faint);font-size:13px">暂无行为记录（此邮箱无埋点事件）</p>
    <?php else: ?>
    <div style="max-height:360px;overflow:auto">
      <?php foreach ($timeline as $e): ?>
      <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px dashed var(--border-soft);font-size:12.5px">
        <span class="st st-faint" style="flex-shrink:0"><?=htmlspecialchars(substr($e['event'] ?? '',0,24))?></span>
        <span style="flex:1;color:var(--muted)"><?=htmlspecialchars(substr($e['timestamp'] ?? '',0,16))?> · <?=htmlspecialchars($e['properties']['page'] ?? $e['properties']['channel'] ?? '—')?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div></div>
<style>@media(max-width:900px){.lead-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
