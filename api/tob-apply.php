<?php
/**
 * ToB 企业申请 API — 商业发行版申请（SaaS / 私有化 / 定制开发）
 *
 * POST /api/tob-apply.php
 * 流程：游客可提交 → CRM 线索 + 自动创建 C 端账户 + 邮件告知设置密码
 * 关联 C 端与 B 端：提交的邮箱自动建号，邮件发送"设置密码"链接，
 * 用户设置密码后即可登录 C 端，企业申请（org）与该账号关联。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_once __DIR__ . '/../lib/OrgSystem.php';
require_once __DIR__ . '/../lib/MailChannel.php';

header('Content-Type: application/json; charset=utf-8');

// ─── 校验 ───
$company = trim($_POST['company'] ?? '');
$industry = trim($_POST['industry'] ?? '');
$size = trim($_POST['size'] ?? '');
$planType = trim($_POST['plan_type'] ?? 'saas');
$budget = trim($_POST['budget'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$email = mb_strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
$note = trim($_POST['note'] ?? '');
$website = trim($_POST['website'] ?? ''); // honeypot

if ($website !== '') {
    echo json_encode(['ok' => true, 'message' => '申请已提交，商务顾问将在 1 个工作日内联系您。'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($company === '' || $contactName === '' || ($email === '' && $phone === '')) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => '请填写企业名称、联系人姓名、联系邮箱或手机号'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => '邮箱格式不正确'], JSON_UNESCAPED_UNICODE);
    exit;
}
$plans = org_plans();
if (!isset($plans[$planType])) $planType = 'saas';

// ─── 保存提交记录 ───
$formData = [
    'company' => $company, 'industry' => $industry, 'size' => $size,
    'plan_type' => $planType, 'budget' => $budget,
    'contact_name' => $contactName, 'email' => $email, 'phone' => $phone, 'note' => $note,
];
$submission = [
    'id' => 'sub_tob_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
    'form_id' => 'form_tob_apply',
    'slug' => 'tob_apply',
    'type' => 'tob',
    'data' => $formData,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'created_at' => date('Y-m-d H:i:s'),
];
$subs = json_read(DATA_DIR . '/submissions/index.json');
$subs[] = $submission;
json_write(DATA_DIR . '/submissions/index.json', $subs);

// ─── CRM 线索（ToB 商机） ───
try {
    $lead = crm_ensure_lead($email ?: $phone, $contactName, $phone);
    crm_update_lead($email ?: $phone, [
        'source' => 'tob_apply',
        'stage' => 'opportunity',
        'company' => $company,
        'tags' => array_values(array_unique(array_merge((array)($lead['tags'] ?? []), ['tob', '商业版', org_plan_label($planType)]))),
        'notes' => ($lead['notes'] ?? '') . "\n[企业申请] {$company} / {$industry} / {$size} / 预算:{$budget} / 需求:{$note}",
    ]);
} catch (Exception $e) {}

// ─── 自动创建 C 端账户 + 发送设置密码邮件 ───
$memberId = '';
$autoCreated = false;
$existingUser = false;
if ($email !== '') {
    $member = member_find($email);
    if (!$member) {
        // 自动建号：随机初始密码，用户通过邮件链接设置真实密码
        $member = [
            'id' => 'm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'name' => $contactName,
            'phone' => $phone,
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role' => 'user',
            'referral_code' => 'of' . substr(bin2hex(random_bytes(4)), 0, 8),
            'ambassador' => false,
            'teacher_status' => 'none',
            'points' => 0,
            'source' => 'tob_apply',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        member_save($member);
        $autoCreated = true;
    } else {
        $existingUser = true;
    }
    $memberId = $member['id'] ?? '';

    // 生成一次性"设置密码"token（复用密码重置机制）
    $token = bin2hex(random_bytes(32));
    $member['reset_token'] = $token;
    $member['reset_token_expires'] = time() + 72 * 3600; // 72 小时有效期
    member_save($member);

    // 发送设置密码邮件
    $siteUrl = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
    $siteUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'example.com');
    $setUrl = $siteUrl . '/member.php?view=reset-password&step=newpassword&token=' . urlencode($token);
    $subject = $autoCreated
        ? "【OpenFlow】企业申请已收到，请设置你的账户密码"
        : "【OpenFlow】你的企业申请已收到";
    $body = "<h3>企业申请已提交</h3>"
        . "<p><b>{$company}</b> 的商业版申请已收到（" . org_plan_label($planType) . "），商务顾问将在 1 个工作日内联系您。</p>"
        . ($autoCreated
            ? "<p>我们已为你的邮箱 <b>{$email}</b> 创建了 OpenFlow 账户。请点击下方链接设置密码，完成后即可登录个人中心，并在此关联你的企业。</p>"
            : "<p>你的账户已存在，可登录后关注企业申请进度。</p>")
        . "<p style='margin:18px 0'><a href='{$setUrl}' style='display:inline-block;padding:11px 22px;background:oklch(52% .17 258);color:#fff;border-radius:999px;text-decoration:none;font-weight:700'>设置密码并登录 →</a></p>"
        . "<p style='color:#888;font-size:12px'>链接 72 小时内有效。如非本人操作请忽略此邮件。</p>";
    try { mail_send($email, $subject, $body); } catch (Throwable $e) {}
}

// ─── 创建企业实体（lead 状态，关联线索与管理员） ───
$orgId = '';
try {
    $org = org_create([
        'name' => $company,
        'industry' => $industry,
        'size' => $size,
        'website' => '',
        'plan_type' => $planType,
        'status' => 'lead',
        'admin_member_id' => $memberId,
        'members' => $memberId ? [$memberId] : [],
        'source_lead' => $email ?: $phone,
        'budget' => $budget,
        'notes' => $note,
        'contact_name' => $contactName,
        'contact_email' => $email,
        'contact_phone' => $phone,
    ]);
    $orgId = $org['id'];
} catch (Exception $e) {}

// ─── 通知运营 ───
try {
    notify('tob', "企业申请：" . $company, ($contactName ?: '匿名') . ' · ' . $email . ' · ' . org_plan_label($planType), '/xmp/orgs?status=lead');
} catch (Throwable $e) {}
try {
    $settings = json_read(DATA_DIR . '/settings.json');
    $adminEmail = $settings['email'] ?? '';
    if (!empty($adminEmail)) {
        $mailBody = "<h3>新的企业申请</h3>"
            . "<p><b>企业：</b>" . htmlspecialchars($company) . "</p>"
            . "<p><b>行业/规模：</b>" . htmlspecialchars($industry) . " / " . htmlspecialchars($size) . "</p>"
            . "<p><b>方案：</b>" . org_plan_label($planType) . "</p>"
            . "<p><b>预算：</b>" . htmlspecialchars($budget) . "</p>"
            . "<p><b>联系人：</b>" . htmlspecialchars($contactName) . " / " . htmlspecialchars($email) . " / " . htmlspecialchars($phone) . "</p>"
            . "<p><b>需求：</b>" . htmlspecialchars($note) . "</p>"
            . "<p><b>时间：</b>" . date('Y-m-d H:i:s') . "</p>";
        mail_send($adminEmail, "【OpenFlow】新企业申请：{$company}", $mailBody);
    }
} catch (Throwable $e) {}

echo json_encode([
    'ok' => true,
    'message' => '申请已提交，商务顾问将在 1 个工作日内联系您。' . ($autoCreated ? ' 已为你创建账户，请查收邮件设置密码。' : ''),
    'org_id' => $orgId,
    'auto_created' => $autoCreated,
    'existing_user' => $existingUser,
], JSON_UNESCAPED_UNICODE);
