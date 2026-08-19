<?php
/**
 * 客户 360° 聚合视图 — 对标 Salesforce 统一客户画像
 * 聚合：客户信息 + ARR/合同 + 关联线索 + CDP 画像 + 订单 + 跟进记录
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('settings');

$cid = $_GET['c'] ?? '';
$customer = null;
foreach (crm_get_customers() as $c) if (($c['id'] ?? '') === $cid) { $customer = $c; break; }
if (!$customer) { header('Location: /xmp/crm?tab=customers'); exit; }

// 关联线索（按 email）
$lead = null;
$crm = crm_get();
$leadKey = $customer['lead_key'] ?? '';
$lead = $crm['leads'][$leadKey] ?? null;

// CDP 画像（按 email）
$profile = null;
try {
    $profiles = CdpSystem::allProfiles();
    foreach ((array)$profiles as $p) {
        if (mb_strtolower($p['properties']['email'] ?? '') === mb_strtolower($customer['email'] ?? '')) { $profile = $p; break; }
    }
} catch (Throwable $e) {}

// 订单（按 email 或 member）
$orders = [];
try {
    $orders = Database::query("SELECT * FROM orders WHERE member_email = ? OR member_id = ? ORDER BY created_at DESC LIMIT 20", [$customer['email'] ?? '', $customer['lead_key'] ?? '']);
} catch (Exception $e) {}

// 客户健康度
$healthMap = ['healthy'=>'健康','at_risk'=>'有风险','churned'=>'流失'];
$statusMap = ['active'=>'使用中','churned'=>'已流失'];

admin_header('客户详情');
?>
<div class="admin-layout">
  <?php admin_sidebar('crm'); ?>
  <div class="main">
    <div class="v-head">
      <div>
        <h1><?=htmlspecialchars($customer['name'] ?: '—')?> <span style="font-size:14px;color:var(--muted);font-weight:400"><?=htmlspecialchars($customer['company'] ?? '')?></span></h1>
        <p class="v-sub"><?=htmlspecialchars($customer['email'] ?? '')?> · <?=htmlspecialchars($customer['plan_type'] ?? '')?> · <?=$statusMap[$customer['status']] ?? $customer['status']?></p>
      </div>
      <div class="v-actions"><a href="/xmp/crm?tab=customers" class="btn btn-s btn-sm">← 返回客户</a></div>
    </div>

    <!-- 概览卡 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
      <div class="card" style="padding:16px;text-align:center"><div style="font-size:26px;font-weight:800;color:var(--ok)">¥<?=number_format($customer['arr'] ?? 0,0)?></div><div style="font-size:12px;color:var(--muted)">年化 ARR</div></div>
      <div class="card" style="padding:16px;text-align:center"><div style="font-size:20px;font-weight:800"><?=substr($customer['contract_start'] ?? '',0,10)?></div><div style="font-size:12px;color:var(--muted)">合同开始</div></div>
      <div class="card" style="padding:16px;text-align:center"><div style="font-size:20px;font-weight:800;color:var(--warn)"><?=substr($customer['contract_end'] ?? '',0,10)?></div><div style="font-size:12px;color:var(--muted)">合同到期</div></div>
      <div class="card" style="padding:16px;text-align:center"><div style="font-size:20px;font-weight:800;color:<?=$customer['health']==='healthy'?'var(--ok)':($customer['health']==='at_risk'?'var(--warn)':'var(--danger)')?>"><?=$healthMap[$customer['health']] ?? $customer['health']?></div><div style="font-size:12px;color:var(--muted)">客户健康度</div></div>
      <div class="card" style="padding:16px;text-align:center"><div style="font-size:20px;font-weight:800"><?=count($orders)?></div><div style="font-size:12px;color:var(--muted)">订单数</div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="c360-grid">
      <!-- 关联线索 -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">关联线索</h3>
        <?php if ($lead): ?>
        <div style="font-size:13px;line-height:1.9">
          <div>阶段：<b style="color:var(--accent)"><?=htmlspecialchars(crm_stages()[$lead['stage']] ?? $lead['stage'])?></b> · 评分 <b><?=$lead['score'] ?? 0?></b> · 金额 <b style="color:var(--ok)">¥<?=number_format($lead['value'] ?? 0,0)?></b></div>
          <div>来源：<?=htmlspecialchars($lead['source'] ?? '—')?> · 跟进人：<?=htmlspecialchars($lead['owner'] ?? '未分配')?></div>
        </div>
        <?php else: ?><p style="color:var(--faint);font-size:13px">无关联线索</p><?php endif; ?>

        <h3 style="font-size:14px;font-weight:700;margin:20px 0 12px">CDP 画像</h3>
        <?php if ($profile): $prop = $profile['properties'] ?? []; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12.5px">
          <div style="padding:8px;background:var(--bg);border-radius:8px">设备：<b><?=htmlspecialchars($prop['os'] ?? '—')?> / <?=htmlspecialchars($prop['browser'] ?? '—')?></b></div>
          <div style="padding:8px;background:var(--bg);border-radius:8px">渠道：<b><?=htmlspecialchars($prop['channel'] ?? '—')?></b></div>
          <div style="padding:8px;background:var(--bg);border-radius:8px">城市：<b><?=htmlspecialchars($prop['city'] ?? '—')?></b></div>
          <div style="padding:8px;background:var(--bg);border-radius:8px">事件：<b><?=$profile['events_count'] ?? 0?></b></div>
          <div style="padding:8px;background:var(--bg);border-radius:8px">生命周期：<b><?=htmlspecialchars($profile['lifecycle']['stage'] ?? '—')?></b></div>
          <div style="padding:8px;background:var(--bg);border-radius:8px">累计消费：<b style="color:var(--ok)">¥<?=number_format($profile['summaries']['purchase_amount_total'] ?? 0,0)?></b></div>
        </div>
        <?php else: ?><p style="color:var(--faint);font-size:13px">无 CDP 画像</p><?php endif; ?>
      </div>

      <!-- 跟进记录 -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">跟进记录</h3>
        <?php $followUps = $lead['follow_ups'] ?? []; ?>
        <?php if (empty($followUps)): ?><p style="color:var(--faint);font-size:13px">暂无跟进记录</p>
        <?php else: foreach (array_reverse($followUps) as $f): ?>
        <div style="padding:10px 12px;border-radius:10px;background:var(--bg);margin-bottom:8px;font-size:12.5px">
          <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--faint)"><b><?=htmlspecialchars($f['owner'] ?? '')?></b><span><?=htmlspecialchars($f['time'] ?? '')?></span></div>
          <div style="margin-top:4px;color:var(--muted)"><?=htmlspecialchars($f['content'] ?? '')?></div>
        </div>
        <?php endforeach; endif; ?>
        <?php if ($customer['notes']): ?><div style="margin-top:14px;padding:10px 12px;border-radius:10px;background:var(--warn-soft);color:var(--warn);font-size:12.5px"><b>需求备注：</b><?=htmlspecialchars($customer['notes'])?></div><?php endif; ?>
      </div>
    </div>

    <!-- 订单记录 -->
    <div class="card" style="padding:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:12px">订单记录</h3>
      <?php if (empty($orders)): ?><p style="color:var(--faint);font-size:13px">暂无订单</p>
      <?php else: ?>
      <table style="width:100%;font-size:13px">
        <thead><tr style="text-align:left;color:var(--muted);border-bottom:1px solid var(--border)"><th style="padding:8px">订单号</th><th>商品</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): $st = ['paid'=>'已支付','pending'=>'待支付','shipped'=>'已发货','refunded'=>'已退款','cancelled'=>'已取消'][$o['status']] ?? $o['status']; ?>
          <tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:8px;color:var(--muted)"><?=htmlspecialchars(substr($o['id'],-12))?></td><td><?=htmlspecialchars(mb_substr($o['course_title'] ?? '',0,24))?></td><td style="color:var(--ok);font-weight:600">¥<?=number_format($o['amount']??0,2)?></td><td style="color:<?=$o['status']==='paid'?'var(--ok)':'var(--warn)'?>"><?=$st?></td><td style="color:var(--faint);font-size:12px"><?=substr($o['created_at']??'',0,10)?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.c360-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
