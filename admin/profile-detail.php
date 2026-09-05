<?php
/**
 * 用户画像 360° 详情 — 对标 Segment Profile Explorer / 神策用户详情
 * 展示：身份/属性（按 scope 分组）/行为时间线/标签/分群/评分/消费
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_login();
require_perm('settings');

$vid = $_GET['v'] ?? '';
if ($vid === '') { header('Location: /xmp/cdp?tab=profiles'); exit; }
$profile = CdpSystem::getProfile($vid);
if (!$profile) { header('Location: /xmp/cdp?tab=profiles'); exit; }

$props = $profile['properties'] ?? [];
$dict = json_read(DATA_DIR . '/cdp/properties.json');
if (!is_array($dict)) $dict = [];

// 手动打标
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    csrf_verify();
    $tag = trim($_POST['tag'] ?? '');
    if ($tag !== '') {
        $profiles = CdpSystem::allProfiles();
        $profiles[$vid]['tags'][$tag] = ['type'=>'manual','by'=>$_SESSION['admin_name'] ?? 'admin','at'=>date('Y-m-d H:i:s')];
        CdpSystem::saveProfiles($profiles);
        flash('success', "已打标签：{$tag}");
    }
    header('Location: /xmp/profile-detail?v=' . urlencode($vid));
    exit;
}

// 移除标签
if (isset($_GET['rm_tag'])) {
    $profiles = CdpSystem::allProfiles();
    unset($profiles[$vid]['tags'][$_GET['rm_tag']]);
    CdpSystem::saveProfiles($profiles);
    header('Location: /xmp/profile-detail?v=' . urlencode($vid));
    exit;
}

// 行为时间线
$timeline = [];
foreach (array_slice(array_reverse(CdpSystem::allEvents()), 0, 1000) as $e) {
    if (($e['visitor_id'] ?? '') === $vid) $timeline[] = $e;
    if (count($timeline) >= 100) break;
}
// 消费记录（从 summaries + 事件）
$summaries = $profile['summaries'] ?? [];
$scores = $profile['scores'] ?? [];
$lifecycle = $profile['lifecycle'] ?? [];
$memberships = $profile['segment_memberships'] ?? [];
$tags = $profile['tags'] ?? [];
$segments = json_read(DATA_DIR . '/cdp/segments.json');
if (!is_array($segments)) $segments = [];
$health = CdpSystem::getHealthScore($vid);
// P2：单用户 RFM/LTV/倾向分（360°）
$rfm = CdpSystem::getRFMForUser($vid);

$scopeNames = ['identity'=>'身份','business'=>'业务','device'=>'设备','attribution'=>'渠道归因','preference'=>'偏好'];

admin_header('用户画像详情');
?>
<div class="admin-layout">
  <?php admin_sidebar('cdp'); ?>
  <div class="main">
    <div class="v-head">
      <div>
        <h1><?=htmlspecialchars($props['name'] ?? $vid)?></h1>
        <p class="v-sub">
          <?=htmlspecialchars($props['email'] ?? '')?> · 首访 <?=substr($profile['first_seen'] ?? '',0,10)?> · 最近 <?=substr($profile['last_seen'] ?? '',0,16)?> · 事件 <?=$profile['events_count']??0?>
          · 生命周期 <span style="color:var(--accent);font-weight:600"><?=$lifecycle['stage'] ?? 'new'?></span>
        </p>
      </div>
      <div class="v-actions"><a href="/xmp/cdp?tab=profiles" class="btn btn-s btn-sm">← 返回画像</a></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="p360-grid">
      <!-- 左侧：评分概览 -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">评分与概览</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
          <div style="padding:14px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:26px;font-weight:800;color:<?=$health>=70?'var(--ok)':($health>=40?'var(--warn)':'var(--danger)')?>"><?=$health?></div><div style="font-size:11px;color:var(--muted)">健康度</div></div>
          <div style="padding:14px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:26px;font-weight:800"><?=$scores['engagement'] ?? '—'?></div><div style="font-size:11px;color:var(--muted)">活跃度</div></div>
          <div style="padding:14px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:26px;font-weight:800;color:var(--ok)">¥<?=number_format($summaries['purchase_amount_total'] ?? 0,0)?></div><div style="font-size:11px;color:var(--muted)">累计消费</div></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px">
          <div style="padding:12px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:20px;font-weight:800"><?=$summaries['page_views_30d'] ?? 0?></div><div style="font-size:11px;color:var(--muted)">30天浏览</div></div>
          <div style="padding:12px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:20px;font-weight:800"><?=$summaries['purchase_count'] ?? 0?></div><div style="font-size:11px;color:var(--muted)">购买次数</div></div>
          <div style="padding:12px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:20px;font-weight:800"><?=$summaries['courses_completed'] ?? 0?></div><div style="font-size:11px;color:var(--muted)">完课数</div></div>
        </div>
        <?php if ($rfm): ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px">
          <div style="padding:12px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:20px;font-weight:800"><?=$rfm['segment']?></div><div style="font-size:11px;color:var(--muted)">RFM 分段</div></div>
          <div style="padding:12px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:20px;font-weight:800"><?=$rfm['ltv_tier']?></div><div style="font-size:11px;color:var(--muted)">LTV 档位</div></div>
          <div style="padding:12px;border-radius:12px;background:var(--bg);text-align:center"><div style="font-size:20px;font-weight:800;color:<?=$rfm['propensity']>=60?'var(--ok)':($rfm['propensity']>=30?'var(--warn)':'var(--muted)')?>"><?=$rfm['propensity']?>%</div><div style="font-size:11px;color:var(--muted)">倾向分</div></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
          <span style="font-size:11px;padding:3px 8px;border-radius:999px;background:var(--bg)">R <b><?=$rfm['r_score']?></b>(<?=htmlspecialchars((string)($rfm['recency']??''))?>天)</span>
          <span style="font-size:11px;padding:3px 8px;border-radius:999px;background:var(--bg)">F <b><?=$rfm['f_score']?></b>(<?=htmlspecialchars((string)($rfm['frequency']??''))?>次)</span>
          <span style="font-size:11px;padding:3px 8px;border-radius:999px;background:var(--bg)">M <b><?=$rfm['m_score']?></b>(¥<?=htmlspecialchars((string)($rfm['monetary']??''))?>)</span>
        </div>
        <?php endif; ?>
        <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:var(--bg);font-size:12.5px;color:var(--muted)">
          活跃偏好：<b><?=htmlspecialchars($props['channel'] ?? '—')?></b> 渠道 · <b><?=htmlspecialchars($props['os'] ?? '—')?></b> 设备 · <b><?=htmlspecialchars($props['language'] ?? '—')?></b> 语言
        </div>
      </div>

      <!-- 右侧：标签与分群 -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">标签与分群</h3>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
          <?php foreach ($tags as $tag => $meta): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:600">
            <?=htmlspecialchars(is_string($meta)?$meta:$tag)?>
            <a href="?v=<?=urlencode($vid)?>&rm_tag=<?=urlencode($tag)?>" style="color:inherit;opacity:.6;text-decoration:none" title="移除">✕</a>
          </span>
          <?php endforeach; ?>
          <?php if (empty($tags)): ?><span style="font-size:12px;color:var(--faint)">暂无标签</span><?php endif; ?>
        </div>
        <form method="post" style="display:flex;gap:8px;margin-bottom:16px">
          <?= csrf_field() ?>
          <input type="hidden" name="add_tag" value="1">
          <input type="text" name="tag" placeholder="添加标签（如：高价值）" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px">
          <button class="btn btn-s btn-sm">添加</button>
        </form>
        <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:8px">所属分群</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php $foundSeg = false; foreach ($memberships as $sid => $m): foreach ($segments as $sg): if (($sg['id'] ?? '') === $sid): $foundSeg = true; ?>
          <span style="padding:4px 12px;border-radius:999px;background:var(--ok-soft);color:var(--ok);font-size:12px"><?=htmlspecialchars($sg['name'] ?? $sid)?></span>
          <?php endif; endforeach; endforeach; if (!$foundSeg): ?><span style="font-size:12px;color:var(--faint)">未命中分群</span><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 属性（按 scope 分组） -->
    <div class="card" style="padding:20px;margin-bottom:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">用户属性</h3>
      <?php $groups = []; foreach ($props as $k => $v) { $scope = $dict[$k]['scope'] ?? 'identity'; $groups[$scope][$k] = $v; } ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px">
        <?php foreach ($scopeNames as $sk => $sname): if (empty($groups[$sk])) continue; ?>
        <div>
          <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px"><?=$sname?></div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach ($groups[$sk] as $k => $v): ?>
            <div style="display:flex;justify-content:space-between;padding:7px 10px;border-radius:8px;background:var(--bg);font-size:12.5px">
              <span style="color:var(--muted)"><?=htmlspecialchars($dict[$k]['name'] ?? $k)?></span>
              <b><?=is_array($v)?htmlspecialchars(implode(', ', $v)):htmlspecialchars((string)$v)?></b>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($props)): ?><p style="color:var(--faint);font-size:13px">暂无属性数据</p><?php endif; ?>
      </div>
    </div>

    <!-- 行为时间线 -->
    <div class="card" style="padding:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">行为时间线（最近 100 条）</h3>
      <?php if (empty($timeline)): ?><p style="color:var(--faint);font-size:13px">暂无行为记录</p><?php else: ?>
      <div style="display:flex;flex-direction:column;gap:4px;max-height:400px;overflow-y:auto">
        <?php foreach ($timeline as $e): $ep = $e['properties'] ?? []; ?>
        <div style="display:flex;gap:12px;padding:8px 10px;border-bottom:1px solid var(--border-soft);font-size:12.5px">
          <span style="font-family:var(--font-mono);font-size:11px;color:var(--faint);min-width:120px"><?=substr($e['timestamp']??'',0,16)?></span>
          <span style="font-weight:600;min-width:120px;color:var(--accent)"><?=htmlspecialchars($e['event'])?></span>
          <span style="color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(is_array($ep)?json_encode($ep,JSON_UNESCAPED_UNICODE):'')?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.p360-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
