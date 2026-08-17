<?php
/**
 * API 分佣管理 — 推荐平台 + 分佣追踪
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ApiAffiliate.php';
require_login();
require_perm('settings');

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $config = [
            'referral_code' => trim($_POST['referral_code'] ?? ''),
            'payout_email' => trim($_POST['payout_email'] ?? ''),
            'auto_track' => !empty($_POST['auto_track']),
        ];
        ApiAffiliate::saveConfig($config);
        flash('success', '配置已保存');
        header('Location: /xmp/api-affiliate');
        exit;
    } elseif ($action === 'track') {
        $platformId = $_POST['platform_id'] ?? '';
        if ($platformId) {
            ApiAffiliate::trackReferral($platformId);
            flash('success', '推荐已记录');
        }
        header('Location: /xmp/api-affiliate');
        exit;
    }
}

$platforms = ApiAffiliate::recommendedPlatforms();
$stats = ApiAffiliate::getStats();
$config = ApiAffiliate::getConfig();
$referrals = ApiAffiliate::getReferrals();

admin_header('API 分佣管理');
?>
<style>
.platform-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;transition:.15s}
.platform-card:hover{border-color:var(--accent)}
.platform-icon{font-size:32px;margin-bottom:8px}
.platform-name{font-weight:700;font-size:16px}
.platform-desc{font-size:12px;color:var(--muted);margin-top:4px}
.platform-commission{display:inline-block;background:var(--accent);color:#1e1e1e;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;margin-top:8px}
.ref-row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;align-items:center}
.ref-row:last-child{border:none}
</style>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0"> API 分佣</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=$stats['total']?> 次推荐</span>
        <span class="badge badge-green"><?=$stats['approved']?> 已确认</span>
        <span class="badge badge-yellow"><?=$stats['pending']?> 待确认</span>
      </div>
    </div>
    <p class="sub">推荐 API 聚合平台 · 赚取分佣 · 追踪推荐效果</p>

    <!-- 统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:28px;font-weight:800;color:var(--accent)"><?=$stats['total']?></div>
        <div style="font-size:12px;color:var(--muted)">总推荐</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:28px;font-weight:800;color:var(--ok)"><?=$stats['approved']?></div>
        <div style="font-size:12px;color:var(--muted)">已确认</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:28px;font-weight:800;color:var(--warn)"><?=$stats['pending']?></div>
        <div style="font-size:12px;color:var(--muted)">待确认</div>
      </div>
    </div>

    <!-- 推荐平台 -->
    <div class="card mb-4">
      <h2>🔌 推荐 API 平台</h2>
      <p class="text-sm text-muted mb-4">通过以下平台的推荐链接注册，可获得分佣</p>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
        <?php foreach ($platforms as $p): ?>
        <div class="platform-card">
          <div class="platform-icon"><?=$p['icon']?></div>
          <div class="platform-name"><?=htmlspecialchars($p['name'])?></div>
          <div class="platform-desc"><?=htmlspecialchars($p['description'])?></div>
          <div class="platform-commission">分佣 <?=$p['commission']?></div>
          <div style="margin-top:12px;display:flex;gap:8px">
            <a href="<?=htmlspecialchars(ApiAffiliate::getReferralUrl($p['id']))?>" target="_blank" rel="nofollow" class="btn btn-primary btn-sm" onclick="trackReferral('<?=htmlspecialchars($p['id'])?>')">🔗 注册并获取分佣</a>
            <span class="text-sm text-muted" style="align-self:center"><?=$stats['by_platform'][$p['id']] ?? 0?> 次</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 分佣设置 -->
    <div class="card mb-4">
      <h2>⚙️ 分佣设置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_config">
        <div class="field-row">
          <div class="field">
            <label>推荐码</label>
            <input type="text" name="referral_code" value="<?=htmlspecialchars($config['referral_code'] ?? '')?>" placeholder="你的推荐码">
          </div>
          <div class="field">
            <label>收款邮箱</label>
            <input type="email" name="payout_email" value="<?=htmlspecialchars($config['payout_email'] ?? '')?>" placeholder="分佣收款邮箱">
          </div>
        </div>
        <div class="field">
          <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="auto_track" value="1" <?=!empty($config['auto_track']) ? 'checked' : ''?>> 自动追踪推荐（用户点击链接后自动记录）
          </label>
        </div>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </form>
    </div>

    <!-- 推荐记录 -->
    <div class="card">
      <h2>📋 推荐记录</h2>
      <?php if (empty($referrals)): ?>
      <p class="text-sm text-muted" style="padding:20px 0">暂无推荐记录</p>
      <?php else: ?>
      <div style="max-height:400px;overflow-y:auto">
        <?php foreach (array_reverse(array_slice($referrals, -50)) as $r): ?>
        <div class="ref-row">
          <span style="min-width:80px;font-weight:600"><?=htmlspecialchars($r['platform_id'])?></span>
          <span style="flex:1;color:var(--muted)"><?=htmlspecialchars($r['timestamp'])?></span>
          <span class="badge badge-<?php
            $status = $r['status'] ?? 'pending';
            echo $status === 'paid' ? 'green' : ($status === 'approved' ? 'blue' : 'yellow');
          ?>"><?=htmlspecialchars($status)?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 使用说明 -->
    <div class="card" style="margin-top:20px">
      <h2>📖 如何赚取分佣</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px;margin-top:12px">
        <div style="padding:16px;background:var(--surface-2);border-radius:8px">
          <div style="font-size:24px;margin-bottom:8px">1️⃣</div>
          <strong>设置推荐码</strong>
          <p class="text-sm text-muted">在各平台注册时填写你的推荐码</p>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:8px">
          <div style="font-size:24px;margin-bottom:8px">2️⃣</div>
          <strong>分享推荐链接</strong>
          <p class="text-sm text-muted">在文档、教程中嵌入推荐链接</p>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:8px">
          <div style="font-size:24px;margin-bottom:8px">3️⃣</div>
          <strong>用户注册</strong>
          <p class="text-sm text-muted">用户通过你的链接注册并付费</p>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:8px">
          <div style="font-size:24px;margin-bottom:8px">4️⃣</div>
          <strong>获得分佣</strong>
          <p class="text-sm text-muted">平台按比例返还佣金</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function trackReferral(platformId) {
  var fd = new FormData();
  fd.append('action', 'track');
  fd.append('platform_id', platformId);
  fetch('api-affiliate.php', { method: 'POST', body: fd });
}
</script>
<?php admin_footer(); ?>
