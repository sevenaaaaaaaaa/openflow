<?php
/**
 * 结业证书页 — /certificate/{no} 验证与展示；/certificate 列出我的证书
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CertificateSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';

$no = trim((string)($_GET['no'] ?? ''));
$cert = $no !== '' ? cert_get($no) : null;
$valid = $cert && cert_verify($no);
$member = member_current();
$mine = $member ? cert_for_member((string)$member['id']) : [];
$site = (string)site_config_get('site_name');
?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=$cert ? ('证书 ' . htmlspecialchars($no)) : '我的证书'?> · <?=htmlspecialchars($site)?></title>
<style>
body{margin:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#111;padding:32px 16px;-webkit-font-smoothing:antialiased}
.cert{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:48px 52px;position:relative}
.cert:before{content:'';position:absolute;inset:14px;border:2px solid #d4af37;border-radius:10px;pointer-events:none;opacity:.5}
.cert .brand{font-size:15px;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af}
.cert h1{font-size:30px;margin:6px 0 26px;letter-spacing:-.02em}
.cert .who{font-size:26px;font-weight:800;border-bottom:1px solid #d1d5db;display:inline-block;padding:0 18px 6px}
.cert .do{font-size:15px;color:#4b5563;line-height:2;margin-top:18px}
.cert .course{font-size:19px;font-weight:700;color:#111}
.cert .meta{display:flex;justify-content:space-between;gap:20px;margin-top:38px;font-size:12.5px;color:#6b7280;flex-wrap:wrap}
.cert .ok{color:#16a34a;font-weight:700}.cert .bad{color:#dc2626;font-weight:700}
.list{max-width:760px;margin:0 auto}
.list a{display:block;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;margin-bottom:10px;text-decoration:none;color:#111}
.list a:hover{border-color:#2563eb}
@media print{body{background:#fff;padding:0}.noprint{display:none}}
</style>
</head>
<body>
<?php if ($cert): ?>
  <div class="cert">
    <div class="brand"><?=htmlspecialchars($site)?> · 结业证书</div>
    <h1>Certificate of Completion</h1>
    <div class="who"><?=htmlspecialchars($cert['name'] ?: '学员')?></div>
    <div class="do">兹证明该学员已完成课程<br><span class="course"><?=htmlspecialchars($cert['course_title'])?></span></div>
    <div class="meta">
      <div>证书编号：<span class="mono"><?=htmlspecialchars($cert['cert_no'])?></span></div>
      <div>颁发日期：<?=htmlspecialchars(substr((string)$cert['issued_at'], 0, 10))?></div>
      <div>验真：<?=$valid ? '<span class="ok">✓ 有效</span>' : '<span class="bad">✕ 校验失败</span>'?></div>
    </div>
    <p class="noprint" style="margin-top:26px"><a href="/certificate" style="color:#2563eb;text-decoration:none">查看我的全部证书 →</a></p>
  </div>
<?php elseif ($no !== ''): ?>
  <div class="list"><div style="text-align:center;padding:60px 0"><h1>证书不存在或校验失败</h1><p style="color:#6b7280">请核对证书编号。</p><a href="/" style="color:#2563eb">返回首页</a></div></div>
<?php else: ?>
  <div class="list">
    <h1>我的证书</h1>
    <?php if (!$member): ?><p style="color:#6b7280">请先登录后查看 <a href="/account?view=login&next=/certificate" style="color:#2563eb">登录</a></p>
    <?php elseif (!$mine): ?><p style="color:#6b7280">还没有证书——完成认证课后会自动颁发。</p>
    <?php else: foreach ($mine as $c): ?>
      <a href="/certificate/<?=urlencode($c['cert_no'])?>">
        <b><?=htmlspecialchars($c['course_title'])?></b>
        <span style="float:right;color:#6b7280;font-size:12.5px"><?=htmlspecialchars(substr((string)$c['issued_at'], 0, 10))?></span>
        <div style="color:#6b7280;font-size:12px;margin-top:4px" class="mono"><?=htmlspecialchars($c['cert_no'])?></div>
      </a>
    <?php endforeach; endif; ?>
  </div>
<?php endif; ?>
</body>
</html>
