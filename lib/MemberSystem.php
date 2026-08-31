<?php
/**
 * 前台用户体系 — 注册/登录/验证码/防攻击白名单/会话
 * 数据存储：data/members/index.json
 */

function member_file(): string { return DATA_DIR . '/members/index.json'; }
function member_log_file(): string { return DATA_DIR . '/members/login-log.json'; }
function member_captcha_file(): string { return DATA_DIR . '/members/captcha.json'; }
function member_settings_file(): string { return DATA_DIR . '/members/settings.json'; }

// ─── 用户数据 ───
function member_get_all(): array {
    return json_read(member_file());
}
function member_get(string $id): ?array {
    foreach (member_get_all() as $m) if ($m['id'] === $id) return $m;
    return null;
}
function member_save(array $member): bool {
    $all = member_get_all();
    $found = false;
    foreach ($all as &$m) if ($m['id'] === $member['id']) { $m = array_merge($m, $member); $found = true; break; }
    if (!$found) { $member['id'] = $member['id'] ?? 'm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8); $all[] = $member; }
    $ok = json_write(member_file(), $all);

    // 同步到 Database members 表（供 CommerceSystem 的 unlocked_skills/balance/会员额度使用）
    try {
        member_sync_db($member['id']);
    } catch (Exception $e) {}

    return $ok;
}

// 把某个会员同步到 Database members 表（运行时数据镜像）
function member_sync_db(string $memberId): void {
    $m = member_get($memberId);
    if (!$m) return;
    $vals = [
        $m['id'], $m['email'] ?? '', $m['phone'] ?? '', $m['password_hash'] ?? '',
        $m['name'] ?? '', $m['role'] ?? 'user', (int)($m['points'] ?? 0), (float)($m['balance'] ?? 0),
        $m['referred_by'] ?? '', $m['membership_plan'] ?? '', $m['membership_expires'] ?? '',
        $m['created_at'] ?? date('Y-m-d H:i:s'),
        $m['unlocked_skills'] ?? '[]', $m['api_plans'] ?? '[]',
    ];
    $exists = Database::query("SELECT id FROM members WHERE id = ? LIMIT 1", [$m['id']]);
    if ($exists) {
        Database::execute(
            "UPDATE members SET email=?, phone=?, password_hash=?, nickname=?, level=?, points=?, balance=?, referred_by=?, membership_plan=?, membership_expires=?, created_at=?, unlocked_skills=?, api_plans=? WHERE id=?",
            array_merge(array_slice($vals, 1), [$m['id']])
        );
    } else {
        Database::execute(
            "INSERT INTO members (id, email, phone, password_hash, nickname, level, points, balance, referred_by, membership_plan, membership_expires, created_at, unlocked_skills, api_plans)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            $vals
        );
    }
}

// 按邮箱/手机找用户
function member_find(string $emailOrPhone): ?array {
    $key = mb_strtolower(trim($emailOrPhone));
    foreach (member_get_all() as $m) {
        if (mb_strtolower($m['email'] ?? '') === $key || ($m['phone'] ?? '') === $key) return $m;
    }
    return null;
}

// ─── 防攻击白名单配置 ───
function member_settings(): array {
    $d = json_read(member_settings_file());
    return array_merge([
        'email_whitelist' => [],      // 允许注册的邮箱域名白名单，如 gmail.com
        'phone_whitelist' => [],      // 允许注册的手机号前缀，如 138, 139
        'allow_any_email' => true,    // 是否允许白名单外邮箱（false 则严格白名单）
        'allow_any_phone' => true,
        'captcha_enabled' => true,    // 注册是否需验证码
        'max_login_attempts' => 5,    // 登录失败锁定
        'rate_limit' => ['window' => 60, 'max' => 10], // 验证码发送频率
    ], $d);
}

// 白名单校验
function member_check_whitelist(string $email, string $phone): array {
    $s = member_settings();
    $errors = [];
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if (!$s['allow_any_email'] && !empty($s['email_whitelist']) && !in_array($domain, $s['email_whitelist'])) {
        $errors[] = '该邮箱域名不在允许注册范围内';
    }
    $matchedPhone = empty($s['phone_whitelist']);
    foreach ($s['phone_whitelist'] as $p) if (strpos($phone, $p) === 0) { $matchedPhone = true; break; }
    if (!$s['allow_any_phone'] && !empty($s['phone_whitelist']) && !$matchedPhone) {
        $errors[] = '该手机号不在允许注册范围内';
    }
    return $errors;
}

// ─── 验证码 ───
function member_send_captcha(string $target): bool {
    $s = member_settings();
    // 频率限制
    $captcha = json_read(member_captcha_file());
    $now = time();
    $recent = array_filter($captcha, fn($c) => $now - ($c['time'] ?? 0) < ($s['rate_limit']['window'] ?? 60));
    $count = count(array_filter($recent, fn($c) => ($c['target'] ?? '') === $target));
    if ($count >= ($s['rate_limit']['max'] ?? 10)) return false;

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $captcha[] = ['target' => $target, 'code' => $code, 'time' => $now, 'used' => false];
    // 保留最近 50 条
    $captcha = array_slice($captcha, -50);
    json_write(member_captcha_file(), $captcha);

    // 发送（demo 模式：存文件；真实环境接短信/邮件服务）
    // TODO: 接入短信网关/邮件 SMTP
    $sentLog = DATA_DIR . '/members/captcha-sent.json';
    $sent = json_read($sentLog);
    $sent[] = ['target' => $target, 'code' => $code, 'time' => date('Y-m-d H:i:s')];
    json_write($sentLog, $sent);
    return true;
}

function member_verify_captcha(string $target, string $code): bool {
    $captcha = json_read(member_captcha_file());
    foreach ($captcha as &$c) {
        if (($c['target'] ?? '') === $target && ($c['code'] ?? '') === $code && empty($c['used']) && (time() - ($c['time'] ?? 0)) < 600) {
            $c['used'] = true;
            json_write(member_captcha_file(), $captcha);
            return true;
        }
    }
    return false;
}

// ─── 会话 ───
function member_start_session(array $member): void {
    $_SESSION['member_id'] = $member['id'];
    $_SESSION['member_email'] = $member['email'] ?? '';
    $_SESSION['member_name'] = $member['name'] ?? '';
}
function member_current(): ?array {
    return isset($_SESSION['member_id']) ? member_get($_SESSION['member_id']) : null;
}
function member_logout(): void {
    unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_name']);
}
function member_require_login() {
    if (!member_current()) {
        header('Location: member.php?view=login&next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}

// ─── 登录日志（防暴力破解）───
function member_log_attempt(string $email, bool $success): void {
    $log = json_read(member_log_file());
    $log[] = ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'success' => $success, 'time' => date('Y-m-d H:i:s')];
    $log = array_slice($log, -500);
    json_write(member_log_file(), $log);
}
function member_is_locked(string $email): bool {
    $s = member_settings();
    $log = json_read(member_log_file());
    $recent = array_filter($log, fn($l) => ($l['email'] ?? '') === $email && !$l['success'] && (time() - strtotime($l['time'])) < 900);
    return count($recent) >= ($s['max_login_attempts'] ?? 5);
}

// ─── 个人资料编辑 ───
function member_update_profile(string $memberId, array $data): array {
    $member = member_get($memberId);
    if (!$member) return ['ok' => false, 'error' => '会员不存在'];
    
    // 更新允许的字段
    $allowedFields = ['nickname', 'avatar', 'phone', 'bio', 'website', 'company', 'job_title', 'role', 'developer_bio', 'developer_skills', 'developer_website'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $member[$field] = $data[$field];
        }
    }
    $member['updated_at'] = date('Y-m-d H:i:s');
    
    member_save($member);
    return ['ok' => true, 'member' => $member];
}

function member_change_password(string $memberId, string $oldPassword, string $newPassword): array {
    $member = member_get($memberId);
    if (!$member) return ['ok' => false, 'error' => '会员不存在'];
    
    // 验证旧密码
    if (!password_verify($oldPassword, $member['password_hash'] ?? '')) {
        return ['ok' => false, 'error' => '当前密码错误'];
    }
    
    // 密码强度校验
    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'error' => '新密码至少需要 8 位'];
    }
    
    $member['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $member['updated_at'] = date('Y-m-d H:i:s');
    
    member_save($member);
    return ['ok' => true];
}

// ─── 密码重置 ───
function member_request_password_reset(string $emailOrPhone): array {
    $member = member_find($emailOrPhone);
    if (!$member) {
        // 安全考虑：不透露用户是否存在
        return ['ok' => true, 'message' => '如果该账号存在，我们将发送验证码'];
    }
    
    // 生成重置 token
    $token = bin2hex(random_bytes(32));
    $member['reset_token'] = $token;
    $member['reset_token_expires'] = time() + 3600; // 1小时有效期
    member_save($member);
    
    // 发送验证码
    $target = !empty($member['email']) ? $member['email'] : $member['phone'];
    if (!empty($target)) {
        member_send_captcha($target);
    }
    
    return ['ok' => true, 'token' => $token, 'message' => '验证码已发送'];
}

function member_verify_reset_token(string $token, string $code): array {
    // 查找用户
    foreach (member_get_all() as $m) {
        if (($m['reset_token'] ?? '') === $token) {
            // 检查过期
            if (empty($m['reset_token_expires']) || $m['reset_token_expires'] < time()) {
                return ['ok' => false, 'error' => '验证码已过期'];
            }
            
            // 验证验证码
            $target = !empty($m['email']) ? $m['email'] : $m['phone'];
            if (!member_verify_captcha($target, $code)) {
                return ['ok' => false, 'error' => '验证码错误'];
            }
            
            return ['ok' => true, 'member_id' => $m['id']];
        }
    }
    return ['ok' => false, 'error' => '无效的重置链接'];
}

function member_reset_password(string $token, string $newPassword): array {
    // 查找用户
    foreach (member_get_all() as &$m) {
        if (($m['reset_token'] ?? '') === $token) {
            // 检查过期
            if (empty($m['reset_token_expires']) || $m['reset_token_expires'] < time()) {
                return ['ok' => false, 'error' => '重置链接已过期'];
            }
            
            // 密码强度校验
            if (strlen($newPassword) < 8) {
                return ['ok' => false, 'error' => '新密码至少需要 8 位'];
            }
            
            $m['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            unset($m['reset_token'], $m['reset_token_expires']);
            $m['updated_at'] = date('Y-m-d H:i:s');
            member_save($m);
            
            return ['ok' => true, 'message' => '密码重置成功'];
        }
    }
    return ['ok' => false, 'error' => '无效的重置链接'];
}

// ─── 会员管理（管理员用）───
function member_admin_list(array $filters = []): array {
    $all = member_get_all();
    
    if (!empty($filters['search'])) {
        $s = mb_strtolower($filters['search']);
        $all = array_filter($all, function($m) use ($s) {
            return mb_strpos(mb_strtolower($m['email'] ?? ''), $s) !== false
                || mb_strpos(mb_strtolower($m['nickname'] ?? ''), $s) !== false
                || str_contains($m['phone'] ?? '', $s);
        });
    }
    
    if (!empty($filters['level'])) {
        $all = array_filter($all, fn($m) => ($m['level'] ?? 'free') === $filters['level']);
    }
    
    return array_values($all);
}

function member_admin_update(string $memberId, array $data): array {
    $member = member_get($memberId);
    if (!$member) return ['ok' => false, 'error' => '会员不存在'];
    
    // 管理员可以更新更多字段
    $adminFields = ['nickname', 'avatar', 'phone', 'email', 'level', 'points', 'balance', 'status'];
    foreach ($adminFields as $field) {
        if (isset($data[$field])) {
            $member[$field] = $data[$field];
        }
    }
    $member['updated_at'] = date('Y-m-d H:i:s');
    
    member_save($member);
    return ['ok' => true, 'member' => $member];
}
