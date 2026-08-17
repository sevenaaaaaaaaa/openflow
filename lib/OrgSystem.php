<?php
/**
 * 企业实体（ToB）— OpenFlow 商业发行版
 * 设计理念：all-in-one，不新增系统。企业客户与 C 端用户共享同一套账号体系，
 * 通过 org（组织）实体承载企业维度：成员、状态机、部署/订阅、支持。
 *
 * 状态机：lead(意向) → qualified(有效) → proposal(报价) → contract(签约)
 *        → deploying(部署中) → active(使用中) → churned(流失)
 * plan_type：saas(SaaS订阅) / private(私有化部署) / custom(定制开发)
 */

function org_file(): string { return DATA_DIR . '/orgs.json'; }

function org_get_all(): array {
    $d = json_read(org_file());
    return is_array($d) ? $d : [];
}

function org_save_all(array $orgs): bool { return json_write(org_file(), $orgs); }

function org_get(string $id): ?array {
    foreach (org_get_all() as $o) if ($o['id'] === $id) return $o;
    return null;
}

function org_save(array $org): bool {
    $orgs = org_get_all();
    $found = false;
    foreach ($orgs as &$o) if ($o['id'] === $org['id']) { $o = array_merge($o, $org); $found = true; break; }
    if (!$found) { $org['id'] = $org['id'] ?? 'org_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8); $orgs[] = $org; }
    return org_save_all($orgs);
}

function org_create(array $data): array {
    $org = array_merge([
        'id' => 'org_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
        'name' => $data['name'] ?? '未命名企业',
        'industry' => $data['industry'] ?? '',
        'size' => $data['size'] ?? '',
        'website' => $data['website'] ?? '',
        'plan_type' => $data['plan_type'] ?? 'saas',   // saas / private / custom
        'status' => $data['status'] ?? 'lead',          // 状态机
        'admin_member_id' => $data['admin_member_id'] ?? '',
        'members' => $data['members'] ?? [],
        'source_lead' => $data['source_lead'] ?? '',    // 关联 CRM 线索 key
        'budget' => $data['budget'] ?? '',
        'notes' => $data['notes'] ?? '',
        'contact_name' => $data['contact_name'] ?? '',
        'contact_email' => $data['contact_email'] ?? '',
        'contact_phone' => $data['contact_phone'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ], $data);
    org_save($org);
    return $org;
}

/* ─── 状态机定义 ─── */
function org_statuses(): array {
    return [
        'lead'       => ['label' => '意向', 'color' => 'oklch(60% .12 250)'],
        'qualified'  => ['label' => '有效商机', 'color' => 'oklch(62% .14 220)'],
        'proposal'   => ['label' => '报价中', 'color' => 'oklch(66% .13 80)'],
        'contract'   => ['label' => '已签约', 'color' => 'oklch(55% .16 290)'],
        'deploying'  => ['label' => '部署中', 'color' => 'oklch(58% .15 200)'],
        'active'     => ['label' => '使用中', 'color' => 'oklch(58% .17 152)'],
        'churned'    => ['label' => '已流失', 'color' => 'oklch(50% .12 25)'],
    ];
}

function org_status_label(string $status): string {
    $s = org_statuses();
    return $s[$status]['label'] ?? $status;
}

/* ─── 方案类型 ─── */
function org_plans(): array {
    return [
        'saas'    => ['label' => 'SaaS 订阅', 'desc' => '托管版，按席位/周期订阅'],
        'private' => ['label' => '私有化部署', 'desc' => '部署到客户环境，数据不出域'],
        'custom'  => ['label' => '定制开发', 'desc' => '基于底座二次开发 + Skill 定制'],
    ];
}

function org_plan_label(string $plan): string {
    $p = org_plans();
    return $p[$plan]['label'] ?? $plan;
}

/* ─── 成员关系 ─── */
// 用户所属企业（一个用户当前归属一个企业）
function org_by_member(string $memberId): ?array {
    foreach (org_get_all() as $o) {
        if ($o['admin_member_id'] === $memberId || in_array($memberId, (array)($o['members'] ?? []), true)) return $o;
    }
    return null;
}

// 用户在企业内的角色
function org_role_of(string $memberId, array $org): string {
    if (($org['admin_member_id'] ?? '') === $memberId) return 'owner';
    return in_array($memberId, (array)($org['members'] ?? []), true) ? 'member' : 'none';
}

// 添加成员（避免重复）
function org_add_member(string $orgId, string $memberId): bool {
    $org = org_get($orgId);
    if (!$org) return false;
    $org['members'] = array_values(array_unique(array_merge((array)($org['members'] ?? []), [$memberId])));
    $org['updated_at'] = date('Y-m-d H:i:s');
    return org_save($org);
}
