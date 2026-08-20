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

// 各阶段赢率（对标 Pipedrive：用于加权金额与销售预测）
function crm_stage_win_rates(): array {
    $cfg = json_read(DATA_DIR . '/crm/win-rates.json');
    $default = ['new'=>0.05,'contacted'=>0.1,'qualified'=>0.3,'opportunity'=>0.6,'won'=>1.0,'lost'=>0];
    return is_array($cfg) ? array_merge($default, $cfg) : $default;
}

// 管道加权金额（Σ 金额 × 阶段赢率）
function crm_pipeline_weighted(): array {
    $data = crm_get();
    $rates = crm_stage_win_rates();
    $weighted = 0; $byStage = [];
    foreach ($data['leads'] ?? [] as $l) {
        $stage = $l['stage'] ?? 'new';
        $value = (float)($l['value'] ?? 0);
        $rate = $rates[$stage] ?? 0;
        $weighted += $value * $rate;
        $byStage[$stage] = ($byStage[$stage] ?? 0) + $value * $rate;
    }
    return ['weighted'=>round($weighted,2), 'by_stage'=>$byStage];
}

// 销售预测：加权金额 + 机会数 + 预计成交时间分布
function crm_forecast(): array {
    $data = crm_get();
    $rates = crm_stage_win_rates();
    $weighted = 0; $opportunities = 0; $expected = [];
    foreach ($data['leads'] ?? [] as $l) {
        if (!in_array($l['stage'] ?? '', ['qualified','opportunity'], true)) continue;
        $value = (float)($l['value'] ?? 0);
        $weighted += $value * ($rates[$l['stage']] ?? 0);
        $opportunities++;
        $month = substr($l['expected_close'] ?? '', 0, 7) ?: '未排期';
        $expected[$month] = ($expected[$month] ?? 0) + $value * ($rates[$l['stage']] ?? 0);
    }
    ksort($expected);
    return ['weighted'=>round($weighted,2), 'opportunities'=>$opportunities, 'by_month'=>$expected];
}

// 获取或创建线索 CRM 记录（按 email 关联）
// 线索查重/防撞单：按邮箱/手机/公司名查已有线索（返回冲突列表）
function crm_find_duplicate(string $email = '', string $phone = '', string $company = ''): array {
    $data = crm_get();
    $conflicts = [];
    $email = mb_strtolower(trim($email));
    foreach ($data['leads'] ?? [] as $key => $l) {
        if ($email !== '' && mb_strtolower($l['email'] ?? '') === $email) {
            $conflicts[] = ['field' => 'email', 'key' => $key, 'name' => $l['name'] ?? '', 'stage' => $l['stage'] ?? ''];
            continue;
        }
        if ($phone !== '' && ($l['phone'] ?? '') === $phone) {
            $conflicts[] = ['field' => 'phone', 'key' => $key, 'name' => $l['name'] ?? '', 'stage' => $l['stage'] ?? ''];
            continue;
        }
        if ($company !== '' && mb_strtolower($l['company'] ?? '') === mb_strtolower($company)) {
            $conflicts[] = ['field' => 'company', 'key' => $key, 'name' => $l['name'] ?? '', 'stage' => $l['stage'] ?? ''];
        }
    }
    return $conflicts;
}

function crm_ensure_lead(string $email, string $name = '', string $phone = ''): array {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if ($key === '') { $key = $phone ?: ('lead_' . time()); }
    $isNew = empty($data['leads'][$key]);
    if ($isNew) {
        $data['leads'][$key] = [
            'email' => $email, 'name' => $name, 'phone' => $phone,
            'stage' => 'new', 'score' => 0, 'owner' => '',
            'value' => 0, 'expected_close' => '',
            'source' => '', 'tags' => [],
            'follow_ups' => [], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    crm_save($data);
    // 新线索 → 出站 webhook
    if ($isNew && class_exists('WebhookSystem')) {
        try { \WebhookSystem::trigger('lead.created', ['email' => $email, 'name' => $name, 'phone' => $phone]); } catch (Exception $e) {}
    }
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
        // 线索更新 → 出站 webhook
        if (class_exists('WebhookSystem')) {
            try { \WebhookSystem::trigger('lead.updated', array_merge(['email' => $email], $updates)); } catch (Exception $e) {}
        }
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
    // 统一提取：email / contact / phone（contact 可能是邮箱或手机号）
    $email = trim($data['email'] ?? '');
    $contact = trim($data['contact'] ?? '');
    $phone = trim($data['phone'] ?? '');

    // contact 字段兼容：含 @ 视为邮箱，否则视为手机号
    if (!$email && $contact) {
        if (strpos($contact, '@') !== false) { $email = $contact; }
        elseif (!$phone) { $phone = $contact; }
    }
    if (!$email && !$phone) return; // 既无邮箱也无手机号则跳过

    $key = $email !== '' ? $email : $phone;
    $lead = crm_ensure_lead($email, $data['name'] ?? '', $phone);
    if (empty($lead['source'])) crm_update_lead($key, ['source' => $submission['type'] ?? 'form']);
    if (empty($lead['follow_ups']) && ($submission['type'] ?? '') === 'lead') {
        crm_add_followup($key, '通过表单首次提交：' . ($data['company'] ?? ''));
    }
}

// ─── 客户管理（won 后转客户，合同/续费/健康度） ───
function crm_customers_file(): string { return DATA_DIR . '/customers.json'; }

function crm_get_customers(): array {
    $d = json_read(crm_customers_file());
    return is_array($d) ? $d : [];
}

function crm_save_customers(array $customers): bool { return json_write(crm_customers_file(), $customers); }

// 线索转客户（won → 客户）
function crm_to_customer(string $email, array $extra = []): ?array {
    $customers = crm_get_customers();
    $key = mb_strtolower(trim($email));
    foreach ($customers as &$c) if (($c['email'] ?? '') === $key) return $c; // 已存在
    $lead = crm_get()['leads'][$key] ?? [];
    $customer = array_merge([
        'id' => 'cus_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
        'lead_key' => $key,
        'name' => $lead['name'] ?? '',
        'email' => $key,
        'phone' => $lead['phone'] ?? '',
        'company' => $lead['company'] ?? '',
        'plan_type' => $extra['plan_type'] ?? 'saas',   // saas / private / custom
        'arr' => (float)($extra['arr'] ?? 0),            // 年度经常性收入
        'contract_start' => $extra['contract_start'] ?? date('Y-m-d'),
        'contract_end' => $extra['contract_end'] ?? date('Y-m-d', strtotime('+1 year')),
        'health' => $extra['health'] ?? 'healthy',       // healthy / at_risk / churned
        'status' => 'active',                            // active / churned
        'notes' => $extra['notes'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ], $extra);
    $customers[] = $customer;
    crm_save_customers($customers);
    // 线索标记已转客户
    crm_update_lead($key, ['customer_id' => $customer['id'], 'stage' => 'won']);
    return $customer;
}

// ─── ARR 报表（年度经常性收入） ───
function crm_arr(): array {
    $customers = crm_get_customers();
    $active = array_values(array_filter($customers, fn($c) => ($c['status'] ?? '') === 'active'));
    $arr = round(array_sum(array_map(fn($c) => (float)($c['arr'] ?? 0), $active)), 2);

    // 商机漏斗（opportunity 阶段的线索）
    $leads = crm_get()['leads'] ?? [];
    $deals = array_values(array_filter($leads, fn($l) => ($l['stage'] ?? '') === 'opportunity'));
    $pipeline = round(array_sum(array_map(fn($d) => (float)($d['value'] ?? 0), $deals)), 2);
    $won = array_values(array_filter($leads, fn($l) => ($l['stage'] ?? '') === 'won'));

    // 客户生命周期价值（按 ARR 估算）
    $customerCount = count($active);
    $avgArr = $customerCount > 0 ? round($arr / $customerCount, 2) : 0;
    $churned = array_values(array_filter($customers, fn($c) => ($c['status'] ?? '') === 'churned'));
    $churnRate = $customerCount + count($churned) > 0 ? round(count($churned) / ($customerCount + count($churned)) * 100, 1) : 0;

    return [
        'arr' => $arr,
        'active_customers' => $customerCount,
        'avg_arr' => $avgArr,
        'pipeline_value' => $pipeline,
        'open_deals' => count($deals),
        'won_deals' => count($won),
        'churn_rate' => $churnRate,
        'churned_count' => count($churned),
    ];
}

// ═══ Salesforce 对标增强：活动历史 / 工单 / 报价 / 公司账户 / 报表 ═══

// 类型化活动历史（Call/Email/Meeting/WeChat/Task/Note）
function crm_add_activity(string $email, string $type, string $content, string $owner = ''): void {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (isset($data['leads'][$key])) {
        $data['leads'][$key]['activities'] = $data['leads'][$key]['activities'] ?? [];
        $data['leads'][$key]['activities'][] = ['type'=>$type, 'content'=>$content, 'owner'=>$owner, 'at'=>date('Y-m-d H:i:s')];
        $data['leads'][$key]['activities'] = array_slice($data['leads'][$key]['activities'], -200);
        $data['leads'][$key]['last_activity'] = date('Y-m-d H:i:s');
        crm_save($data);
    }
}
function crm_get_activities(string $email): array {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    return array_reverse($data['leads'][$key]['activities'] ?? []);
}

// 工单 Case（客户服务）
function crm_create_case(string $email, string $subject, string $priority = 'normal', string $assignee = ''): ?string {
    $data = crm_get();
    $id = 'case_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 5);
    $data['cases'][$id] = ['id'=>$id, 'email'=>mb_strtolower(trim($email)), 'subject'=>$subject, 'priority'=>$priority, 'assignee'=>$assignee, 'status'=>'open', 'created_at'=>date('Y-m-d H:i:s'), 'resolved_at'=>''];
    crm_save($data);
    return $id;
}
function crm_update_case(string $id, array $updates): void {
    $data = crm_get();
    if (isset($data['cases'][$id])) {
        $data['cases'][$id] = array_merge($data['cases'][$id], $updates);
        if (!empty($updates['status']) && $updates['status'] === 'resolved') $data['cases'][$id]['resolved_at'] = date('Y-m-d H:i:s');
        crm_save($data);
    }
}

// 报价单 Quote（商机 → 报价）
function crm_create_quote(string $email, array $items, float $total = 0): ?string {
    $data = crm_get();
    $id = 'quote_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 5);
    $data['quotes'][$id] = ['id'=>$id, 'email'=>mb_strtolower(trim($email)), 'items'=>$items, 'total'=>$total > 0 ? $total : array_sum(array_column($items, 'amount')), 'status'=>'draft', 'sent_at'=>'', 'decision_at'=>'', 'decision'=>''];
    crm_save($data);
    return $id;
}
function crm_quote_decision(string $id, string $decision): void {
    $data = crm_get();
    if (isset($data['quotes'][$id])) {
        $data['quotes'][$id]['decision'] = $decision; // accepted / rejected
        $data['quotes'][$id]['decision_at'] = date('Y-m-d H:i:s');
        if ($decision === 'accepted' && isset($data['quotes'][$id]['email'])) {
            $email = $data['quotes'][$id]['email'];
            if (isset($data['leads'][$email])) $data['leads'][$email]['stage'] = 'won';
        }
        crm_save($data);
    }
}

// 公司账户 Account（多联系人 / 多线索归账户）
function crm_ensure_account(string $name, string $industry = '', string $size = ''): string {
    $data = crm_get();
    $key = mb_strtolower(trim($name));
    foreach (($data['accounts'] ?? []) as $a) if (mb_strtolower(($a['name'] ?? '')) === $key) return $a['id'];
    $id = 'acc_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 5);
    $data['accounts'][$id] = ['id'=>$id, 'name'=>$name, 'industry'=>$industry, 'size'=>$size, 'contacts'=>[], 'lead_keys'=>[], 'created_at'=>date('Y-m-d H:i:s')];
    crm_save($data);
    return $id;
}
function crm_account_attach_lead(string $accountId, string $email): void {
    $data = crm_get();
    if (isset($data['accounts'][$accountId])) {
        $k = mb_strtolower(trim($email));
        if (!in_array($k, $data['accounts'][$accountId]['lead_keys'], true)) $data['accounts'][$accountId]['lead_keys'][] = $k;
        crm_save($data);
    }
}

// CRM 报表（管线汇总 / 阶段分布 / 赢单 / 活动统计）
function crm_reports(): array {
    $data = crm_get();
    $leads = $data['leads'] ?? [];
    $byStage = []; $stageValue = []; $wonTotal = 0; $wonCount = 0; $activityCount = 0; $taskCount = 0;
    foreach ($leads as $l) {
        $st = $l['stage'] ?? 'new';
        $byStage[$st] = ($byStage[$st] ?? 0) + 1;
        $stageValue[$st] = ($stageValue[$st] ?? 0) + (float)($l['value'] ?? 0);
        if ($st === 'won') { $wonTotal += (float)($l['value'] ?? 0); $wonCount++; }
        $activityCount += count($l['activities'] ?? []);
    }
    arsort($byStage);
    // 活跃线索（近7天有活动）
    $active7 = count(array_filter($leads, fn($l) => strtotime(($l['last_activity'] ?? '2000-01-01')) > time() - 7 * 86400));
    return [
        'by_stage' => $byStage, 'stage_value' => $stageValue,
        'won_total' => round($wonTotal, 2), 'won_count' => $wonCount,
        'activity_count' => $activityCount, 'active7' => $active7,
        'total_leads' => count($leads), 'total_cases' => count($data['cases'] ?? []), 'total_quotes' => count($data['quotes'] ?? []),
        'cases_open' => count(array_filter($data['cases'] ?? [], fn($c) => ($c['status'] ?? '') !== 'resolved')),
        'quotes_accepted' => count(array_filter($data['quotes'] ?? [], fn($q) => ($q['decision'] ?? '') === 'accepted')),
    ];
}
