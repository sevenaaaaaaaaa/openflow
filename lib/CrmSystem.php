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
    // 新线索 → 插件钩子（旁路）
    if ($isNew && class_exists('PluginSystem')) {
        PluginSystem::do_action('crm_lead_created', $email, $data['leads'][$key]);
    }
    return $data['leads'][$key];
}

// 更新线索
function crm_update_lead(string $email, array $updates): void {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (isset($data['leads'][$key])) {
        // 合并前先留存旧阶段——array_merge 之后就拿不到了
        $oldStage = (string)($data['leads'][$key]['stage'] ?? '');
        $data['leads'][$key] = array_merge($data['leads'][$key], $updates);
        $data['leads'][$key]['updated_at'] = date('Y-m-d H:i:s');
        crm_save($data);
        $lead = $data['leads'][$key];
        $newStage = (string)($lead['stage'] ?? '');

        // 线索更新 → 出站 webhook
        if (class_exists('WebhookSystem')) {
            try { \WebhookSystem::trigger('lead.updated', array_merge(['email' => $email], $updates)); } catch (Exception $e) {}
        }

        // ── 阶段变化联动（旁路，失败不影响 CRM 主流程）──
        if ($newStage !== '' && $newStage !== $oldStage) {
            // 事件总线：CDP 打标 + MA/画布触发器
            if (function_exists('flow_crm_stage_change')) {
                try { flow_crm_stage_change($email, $oldStage, $newStage, $lead); } catch (\Throwable $e) {}
            }
            // 插件钩子
            if (class_exists('PluginSystem')) {
                PluginSystem::do_action('crm_stage_changed', $email, $oldStage, $newStage, $lead);
                if ($newStage === 'won')  PluginSystem::do_action('crm_deal_won', $email, $lead);
                if ($newStage === 'lost') PluginSystem::do_action('crm_deal_lost', $email, $lead);
            }
        }
    }
}

// 添加跟进记录
function crm_add_followup(string $email, string $content, string $owner = ''): void {
    $data = crm_get();
    $key = mb_strtolower(trim($email));
    if (isset($data['leads'][$key])) {
        $entry = [
            'content' => $content, 'owner' => $owner ?: ($_SESSION['admin_name'] ?? ''),
            'time' => date('Y-m-d H:i:s'),
        ];
        $data['leads'][$key]['follow_ups'][] = $entry;
        $data['leads'][$key]['updated_at'] = date('Y-m-d H:i:s');
        crm_save($data);
        if (class_exists('PluginSystem')) {
            PluginSystem::do_action('crm_followup_added', $email, $entry, $data['leads'][$key]);
        }
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

// ─── 批量建线索（A3：CDP 分群 → CRM）────────────────────────
//
// 为什么不循环调 crm_ensure_lead()：它每建一条就 crm_get() + crm_save()
// 一次，等于把整个 crm.json 读写 N 遍。5000 人的分群就是 5000 次全量
// 读写，O(n²)，必然超时，中途失败还会留下写坏的文件。
// 这里全程只读一次、只写一次，钩子等旁路动作留到落盘之后再发。

/**
 * 批量创建/更新线索。
 *
 * @param array $rows 每行 ['email'=>, 'name'=>, 'phone'=>, 'company'=>,
 *                          'source'=>, 'tags'=>[], 'owner'=>, 'stage'=>, 'value'=>]
 * @param array $opts
 *        - update_existing bool 已存在的线索是否补齐空字段（默认 false，只跳过）
 *        - source          string 统一来源，行内 source 优先
 *        - owner           string 统一负责人，行内 owner 优先
 *        - stage           string 新线索初始阶段（默认 new）
 *        - tags            array  统一追加的标签
 *        - fire_hooks      bool   是否发 crm_lead_created（默认 true）
 *        - fire_webhooks   bool   是否逐条发出站 webhook（默认 false，见下）
 *        - dry_run         bool   只统计不落盘
 * @return array ['created','updated','skipped','no_email','total','emails'=>新建的邮箱]
 *
 * fire_webhooks 默认关闭：出站 webhook 是同步 HTTP，批量导入逐条发会把
 * 一次请求拖成几十分钟。批量结束后会发一次 crm_leads_bulk_imported 汇总钩子。
 */
function crm_bulk_create_leads(array $rows, array $opts = []): array {
    $updateExisting = (bool)($opts['update_existing'] ?? false);
    $defSource      = (string)($opts['source'] ?? '');
    $defOwner       = (string)($opts['owner'] ?? '');
    $defStage       = (string)($opts['stage'] ?? 'new');
    $defTags        = array_values(array_filter((array)($opts['tags'] ?? [])));
    $fireHooks      = (bool)($opts['fire_hooks'] ?? true);
    $fireWebhooks   = (bool)($opts['fire_webhooks'] ?? false);
    $dryRun         = (bool)($opts['dry_run'] ?? false);

    $stat = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'no_email'=>0,
             'total'=>count($rows), 'emails'=>[]];

    $data = crm_get();
    if (!isset($data['leads']) || !is_array($data['leads'])) $data['leads'] = [];
    $now = date('Y-m-d H:i:s');
    $newLeads = [];   // 落盘后再发钩子

    foreach ($rows as $row) {
        $email = mb_strtolower(trim((string)($row['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $stat['no_email']++; continue; }

        $tags = array_values(array_unique(array_merge($defTags, (array)($row['tags'] ?? []))));

        if (isset($data['leads'][$email])) {
            if (!$updateExisting) { $stat['skipped']++; continue; }
            // 只补空字段，绝不覆盖销售已经填过的内容
            $lead = $data['leads'][$email];
            $changed = false;
            foreach (['name','phone','company','source'] as $f) {
                $v = trim((string)($row[$f] ?? ''));
                if ($v !== '' && ($lead[$f] ?? '') === '') { $lead[$f] = $v; $changed = true; }
            }
            if ($tags) {
                $merged = array_values(array_unique(array_merge((array)($lead['tags'] ?? []), $tags)));
                if ($merged !== ($lead['tags'] ?? [])) { $lead['tags'] = $merged; $changed = true; }
            }
            if ($changed) {
                $lead['updated_at'] = $now;
                $data['leads'][$email] = $lead;
                $stat['updated']++;
            } else {
                $stat['skipped']++;
            }
            continue;
        }

        $lead = [
            'email'   => $email,
            'name'    => trim((string)($row['name'] ?? '')),
            'phone'   => trim((string)($row['phone'] ?? '')),
            'company' => trim((string)($row['company'] ?? '')),
            'stage'   => (string)($row['stage'] ?? $defStage),
            'score'   => (int)($row['score'] ?? 0),
            'owner'   => (string)($row['owner'] ?? $defOwner),
            'value'   => (float)($row['value'] ?? 0),
            'expected_close' => '',
            'source'  => (string)($row['source'] ?? $defSource),
            'tags'    => $tags,
            'follow_ups' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $data['leads'][$email] = $lead;
        $stat['created']++;
        $stat['emails'][] = $email;
        $newLeads[$email] = $lead;
    }

    if ($dryRun) return $stat;
    if ($stat['created'] === 0 && $stat['updated'] === 0) return $stat;

    crm_save($data);

    // ── 以下全部是旁路：失败不影响已经落盘的线索 ──
    if ($fireHooks && class_exists('PluginSystem')) {
        foreach ($newLeads as $email => $lead) {
            PluginSystem::do_action('crm_lead_created', $email, $lead);
        }
        PluginSystem::do_action('crm_leads_bulk_imported', $stat, $opts);
    }
    if ($fireWebhooks && class_exists('WebhookSystem')) {
        foreach ($newLeads as $email => $lead) {
            try { \WebhookSystem::trigger('lead.created', ['email' => $email, 'name' => $lead['name'], 'phone' => $lead['phone']]); }
            catch (Exception $e) {}
        }
    }

    return $stat;
}

/**
 * 从 CDP 画像里提取可入库的线索行。
 *
 * 邮箱来源按可靠性排序：画像属性 → 画像顶层字段 → member_id 反查会员表。
 * 拿不到邮箱的画像会被计入 no_email，不会造出一条没法联系的空线索。
 */
function crm_rows_from_profiles(array $profiles): array {
    // member_id → 会员，只读一次
    $membersById = [];
    $members = json_read(DATA_DIR . '/members/index.json');
    foreach (is_array($members) ? $members : [] as $m) {
        if (!empty($m['id'])) $membersById[(string)$m['id']] = $m;
    }

    $rows = [];
    foreach ($profiles as $p) {
        $props  = is_array($p['properties'] ?? null) ? $p['properties'] : [];
        $member = $membersById[(string)($p['member_id'] ?? '')] ?? [];

        $pick = function (string $key) use ($props, $p, $member) {
            foreach ([$props[$key] ?? null, $p[$key] ?? null, $member[$key] ?? null] as $v) {
                if (is_string($v) && trim($v) !== '') return trim($v);
            }
            return '';
        };

        $rows[] = [
            'email'   => $pick('email'),
            'name'    => $pick('name') ?: $pick('nickname') ?: ($member['username'] ?? ''),
            'phone'   => $pick('phone'),
            'company' => $pick('company'),
            'source'  => $pick('source') ?: $pick('utm_source'),
            'tags'    => array_values(array_filter((array)($p['tags'] ?? []), 'is_string')),
        ];
    }
    return $rows;
}

/**
 * 把一个分群里的人批量灌进 CRM。
 *
 * 分群有两套引擎（SegmentEngine 面向运营配置，CdpSystem 面向行为规则），
 * 但两者评估的都是同一份 cdp/profiles.json，这里按 id 依次尝试。
 *
 * @return array crm_bulk_create_leads() 的统计，外加 'segment' 与 'matched'
 */
function crm_leads_from_segment(string $segmentId, array $opts = []): array {
    require_once __DIR__ . '/CdpProfileStore.php';
    $profiles = cdp_profile_all();
    if (!is_array($profiles)) $profiles = [];
    $segment = null; $matched = [];

    if (class_exists('SegmentEngine')) {
        foreach (\SegmentEngine::getSegments() as $s) {
            if (($s['id'] ?? '') === $segmentId) { $segment = $s; break; }
        }
        if ($segment) {
            foreach ($profiles as $p) {
                if (\SegmentEngine::matchSegment($segment, $p)) $matched[] = $p;
            }
        }
    }
    if (!$segment && class_exists('CdpSystem')) {
        foreach (\CdpSystem::allSegments() as $s) {
            if (($s['id'] ?? '') === $segmentId) { $segment = $s; break; }
        }
        if ($segment) $matched = \CdpSystem::getSegmentUsers($segment['rules'] ?? [], PHP_INT_MAX);
    }

    if (!$segment) {
        return ['created'=>0,'updated'=>0,'skipped'=>0,'no_email'=>0,'total'=>0,
                'emails'=>[], 'segment'=>'', 'matched'=>0, 'error'=>'分群不存在'];
    }

    $opts['tags']   = array_values(array_unique(array_merge(
        (array)($opts['tags'] ?? []), ['分群:' . ($segment['name'] ?? $segmentId)])));
    $opts['source'] = $opts['source'] ?? ('CDP 分群 · ' . ($segment['name'] ?? $segmentId));

    $stat = crm_bulk_create_leads(crm_rows_from_profiles($matched), $opts);
    $stat['segment'] = (string)($segment['name'] ?? $segmentId);
    $stat['matched'] = count($matched);
    return $stat;
}

// ─── 未跟进自动提醒（Sales）────────────────────────────
//
// 复用画布已有的 days_since_followup 口径：以最后一条跟进的时间为准，
// 从未跟进则以创建时间为准。won/lost 已闭环，不提醒。
//
// 这是 cron 触发的旁路任务：定时扫一遍，把"躺太久没人管"的线索
// 拎出来提醒，避免线索静默流失——一人公司 / 小团队最容易漏的就是这个。

/** 一条线索距上次活动的天数（跟进 > 创建）。 */
function crm_days_since_activity(array $lead): int {
    $ups = (array)($lead['follow_ups'] ?? []);
    if ($ups) {
        $last = end($ups);
        $ts = strtotime((string)($last['time'] ?? ''));
    } else {
        $ts = strtotime((string)($lead['created_at'] ?? ''));
    }
    return $ts ? (int)floor((time() - $ts) / 86400) : 0;
}

/** 开放阶段里，距上次活动 ≥ $days 天的线索。 */
function crm_stale_leads(int $days): array {
    $open = ['new', 'contacted', 'qualified', 'opportunity'];
    $out = [];
    foreach ((crm_get()['leads'] ?? []) as $key => $lead) {
        if (!in_array($lead['stage'] ?? '', $open, true)) continue;
        if (crm_days_since_activity($lead) >= $days) $out[$key] = $lead;
    }
    return $out;
}

/**
 * 发未跟进提醒。cron 调用。
 *
 * 冷却：同一条线索提醒后 $days 天内不重复提醒（在 lead.last_reminder 上打戳），
 * 否则每天 cron 都会重复轰炸。仍未跟进的话，下一个周期会再次提醒。
 *
 * @return array ['reminded'=>int, 'owners'=>int]
 */
function crm_send_followup_reminders(int $days = 7): array {
    $stale = crm_stale_leads($days);
    if (!$stale) return ['reminded' => 0, 'owners' => 0];

    $data = crm_get();
    $now = date('Y-m-d H:i:s');
    $byOwner = [];
    foreach ($stale as $key => $lead) {
        $last = strtotime((string)($lead['last_reminder'] ?? ''));
        if ($last && (time() - $last) < $days * 86400) continue;   // 冷却中
        $owner = trim((string)($lead['owner'] ?? '')) ?: '未分配';
        $byOwner[$owner][] = $lead;
        $data['leads'][$key]['last_reminder'] = $now;
    }
    $count = array_sum(array_map('count', $byOwner));
    if ($count === 0) return ['reminded' => 0, 'owners' => 0];

    crm_save($data);

    // 组一条按负责人分组的摘要，发到已配置的通知渠道（未配置则安全空转）
    if (function_exists('notify_channels_send')) {
        $lines = [];
        foreach ($byOwner as $owner => $leads) {
            $lines[] = "【{$owner}】" . count($leads) . " 条：";
            foreach (array_slice($leads, 0, 8) as $l) {
                $d = crm_days_since_activity($l);
                $who = $l['name'] ?: ($l['email'] ?? '匿名');
                $lines[] = "  · {$who}（{$l['stage']}，已 {$d} 天没跟进）";
            }
            if (count($leads) > 8) $lines[] = '  · …等共 ' . count($leads) . ' 条';
        }
        try {
            notify_channels_send(
                "有 {$count} 条线索超过 {$days} 天没跟进",
                implode("\n", $lines),
                '/xmp/crm'
            );
        } catch (\Throwable $e) {}
    }

    if (class_exists('PluginSystem')) {
        try { PluginSystem::do_action('crm_followup_reminders_sent', $byOwner, $days); } catch (\Throwable $e) {}
    }

    return ['reminded' => $count, 'owners' => count($byOwner)];
}
