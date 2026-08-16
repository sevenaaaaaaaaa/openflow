<?php
/**
 * CRM 系统 — 线索阶段 / 打分 / 跟进 / 交接 / 商机转化
 */

function crm_file(): string { return DATA_DIR . '/crm.json'; }
function crm_get(): array { return json_read(crm_file()); }
function crm_save(array $data): bool { return json_write(crm_file(), $data); }

function crm_stages(): array {
    return ['new'=>'新线索','contacted'=>'已联系','qualified'=>'有意向','opportunity'=>'商机','won'=>'已成交','lost'=>'无效'];
}

// 获取或创建线索 CRM 记录（按 email 关联）
function crm_ensure_lead(string $email, string $name = '', string $phone = ''): array {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if ($key === '') { $key = $phone ?: ('lead_' . time()); }
    if (empty($data['leads'][$key])) {
        $data['leads'][$key] = [
            'email' => $email, 'name' => $name, 'phone' => $phone,
            'stage' => 'new', 'score' => 0, 'owner' => '',
            'value' => 0, 'expected_close' => '',
            'source' => '', 'tags' => [],
            'follow_ups' => [], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    crm_save($data);
    return $data['leads'][$key];
}

// 更新线索
function crm_update_lead(string $email, array $updates): void {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (isset($data['leads'][$key])) {
        $data['leads'][$key] = array_merge($data['leads'][$key], $updates);
        $data['leads'][$key]['updated_at'] = date('Y-m-d H:i:s');
        crm_save($data);
    }
}

// 添加跟进记录
function crm_add_followup(string $email, string $content, string $owner = ''): void {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (isset($data['leads'][$key])) {
        $data['leads'][$key]['follow_ups'][] = [
            'content' => $content, 'owner' => $owner ?: ($_SESSION['admin_name'] ?? ''),
            'time' => date('Y-m-d H:i:s'),
        ];
        $data['leads'][$key]['updated_at'] = date('Y-m-d H:i:s');
        crm_save($data);
    }
}

// 线索打分（自动 + 手动）
function crm_score(string $email, int $manualDelta = 0): int {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (!isset($data['leads'][$key])) return 0;
    $lead = &$data['leads'][$key];

    // 自动打分：按阶段/行为
    $auto = 0;
    $stageBonus = ['new'=>10, 'contacted'=>30, 'qualified'=>50, 'opportunity'=>70, 'won'=>100];
    $auto += $stageBonus[$lead['stage']] ?? 10;
    if (count($lead['follow_ups'] ?? []) > 0) $auto += 10;
    if (!empty($lead['value'])) $auto += 5;

    $lead['score'] = max(0, min(100, $auto + $manualDelta));
    crm_save($data);
    return $lead['score'];
}

// 阶段转化：线索 → 商机 → 成交
function crm_convert(string $email, string $toStage, float $value = 0, string $expectedClose = ''): void {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (isset($data['leads'][$key])) {
        $data['leads'][$key]['stage'] = $toStage;
        if ($value > 0) $data['leads'][$key]['value'] = $value;
        if ($expectedClose) $data['leads'][$key]['expected_close'] = $expectedClose;
        if ($toStage === 'won') $data['leads'][$key]['won_at'] = date('Y-m-d H:i:s');
        $data['leads'][$key]['updated_at'] = date('Y-m-d H:i:s');
        crm_save($data);
        crm_score($email);
        // 通知
        notify('CRM', '线索阶段更新', ($data['leads'][$key]['name'] ?: $email) . ' → ' . crm_stages()[$toStage], 'admin/crm.php');
    }
}

// 线索交接
function crm_transfer(string $email, string $newOwner): void {
    crm_update_lead($email, ['owner' => $newOwner]);
    crm_add_followup($email, "线索交接给 {$newOwner}");
}

// 合并：从表单提交生成 CRM 线索
function crm_from_submission(array $submission): void {
    $data = $submission['data'] ?? [];
    // email 或 contact（表单字段名不同）
    $email = $data['email'] ?? ($data['contact'] ?? '');
    if (!$email || strpos($email, '@') === false) return; // 非邮箱则跳过
    $lead = crm_ensure_lead($email, $data['name'] ?? '', $data['phone'] ?? ($data['contact'] ?? ''));
    if (empty($lead['source'])) crm_update_lead($email, ['source' => $submission['type'] ?? 'form']);
    if (empty($lead['follow_ups']) && ($submission['type'] ?? '') === 'lead') {
        crm_add_followup($email, '通过表单首次提交：' . ($data['company'] ?? ''));
    }
}
