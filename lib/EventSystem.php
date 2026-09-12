<?php
/**
 * EventSystem — 活动领域层（从"独立 CMS 模块"接进增长系统）
 *
 * 做的事：
 *   - 报名联动：报名即建 CRM 线索 + CDP 画像事件 + 触发自动化 flow（不再是一座孤岛）
 *   - 票种：活动可配多票种（免费/付费），报名记录票种与订单
 *   - 签到：每个报名一个签到码，后台一键核销（出勤进记录）
 *   - 统计：报名/通过/签到 按票种与状态；报名名单可导出
 *   - 议程：活动含分时段议程
 */

function event_regs_file(): string { return DATA_DIR . '/event-registrations.json'; }
function event_regs_all(): array { return json_read(event_regs_file()); }
function event_regs(string $eventId): array { $d = event_regs_all(); return is_array($d[$eventId] ?? null) ? $d[$eventId] : []; }
function event_regs_save(string $eventId, array $list): void { $d = event_regs_all(); $d[$eventId] = array_values($list); json_write(event_regs_file(), $d); }

/** 票种归一：无配置时给一个默认免费票 */
function event_tickets(array $event): array {
    $t = $event['tickets'] ?? null;
    if (!is_array($t) || !$t) return [['id' => 'general', 'name' => '普通票', 'price' => 0, 'capacity' => (int)($event['capacity'] ?? 0), 'desc' => '']];
    $out = [];
    foreach ($t as $i => $x) {
        if (!is_array($x)) continue;
        $out[] = ['id' => (string)($x['id'] ?? ('t' . $i)), 'name' => (string)($x['name'] ?? '票种'), 'price' => (float)($x['price'] ?? 0), 'capacity' => (int)($x['capacity'] ?? 0), 'desc' => (string)($x['desc'] ?? '')];
    }
    return $out ?: [['id' => 'general', 'name' => '普通票', 'price' => 0, 'capacity' => 0, 'desc' => '']];
}
function event_ticket(array $event, string $id): ?array {
    foreach (event_tickets($event) as $t) if ($t['id'] === $id) return $t;
    return null;
}

/** 议程归一 */
function event_agenda(array $event): array {
    $a = $event['agenda'] ?? [];
    if (!is_array($a)) return [];
    $out = [];
    foreach ($a as $x) {
        if (!is_array($x) || trim((string)($x['title'] ?? '')) === '') continue;
        $out[] = ['time' => (string)($x['time'] ?? ''), 'title' => (string)$x['title'], 'speaker' => (string)($x['speaker'] ?? ''), 'desc' => (string)($x['desc'] ?? '')];
    }
    return $out;
}

/** 签到码（稳定可复现，供二维码/链接） */
function event_checkin_token(string $eventId, string $regId): string {
    $seed = (string)(json_read(DATA_DIR . '/settings.json')['install_secret'] ?? 'openflow');
    return substr(hash_hmac('sha256', $eventId . '|' . $regId, $seed), 0, 16);
}

/** 报名联动：CRM 线索 + CDP 画像事件 + 自动化 flow（报名即增长信号） */
function event_register_linkage(array $event, array $reg): void {
    $email = (string)($reg['email'] ?? '');
    $name = (string)($reg['name'] ?? '');
    $memberId = (string)($reg['member_id'] ?? '');
    $title = (string)($event['title'] ?? '活动');
    $eid = (string)($event['id'] ?? '');

    // 1) CRM 线索（报名即线索，来源标注活动）
    try {
        if (!function_exists('crm_ensure_lead')) require_once __DIR__ . '/CrmSystem.php';
        if ($email !== '' && function_exists('crm_ensure_lead')) {
            crm_ensure_lead($email, $name);
            if (function_exists('crm_update_lead')) crm_update_lead($email, ['source' => '活动：' . $title]);
            if (function_exists('crm_add_followup')) crm_add_followup($email, '报名活动「' . $title . '」', 'system');
        }
    } catch (\Throwable $e) {}

    // 2) CDP 画像事件
    try {
        if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
        CdpSystem::track('event_register', ['member_id' => $memberId, 'email' => $email, 'props' => ['event_id' => $eid, 'event_title' => $title, 'ticket' => $reg['ticket_name'] ?? '']]);
    } catch (\Throwable $e) {}

    // 3) 自动化 flow（新触发器 event_register）
    try {
        if (function_exists('flow_handle')) {
            flow_handle('event_register', ['member_id' => $memberId, 'email' => $email, 'name' => $name, 'event_id' => $eid, 'label' => $title]);
        }
    } catch (\Throwable $e) {}
}

/** 核销签到 */
function event_checkin(string $eventId, string $regId): array {
    $list = event_regs($eventId);
    $hit = false;
    foreach ($list as &$r) {
        if (($r['id'] ?? '') === $regId) {
            if (!empty($r['checked_in'])) return ['ok' => true, 'already' => true, 'name' => $r['name'] ?? ''];
            $r['checked_in'] = true; $r['checkin_at'] = date('Y-m-d H:i:s');
            $hit = true; break;
        }
    }
    unset($r);
    if ($hit) event_regs_save($eventId, $list);
    return ['ok' => $hit];
}

function event_checkin_by_token(string $eventId, string $token): array {
    foreach (event_regs($eventId) as $r) {
        if (hash_equals(event_checkin_token($eventId, (string)($r['id'] ?? '')), $token)) return event_checkin($eventId, (string)$r['id']) + ['name' => $r['name'] ?? ''];
    }
    return ['ok' => false, 'error' => '签到码无效'];
}

function event_set_reg_status(string $eventId, string $regId, string $status): bool {
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) return false;
    $list = event_regs($eventId); $hit = false;
    foreach ($list as &$r) if (($r['id'] ?? '') === $regId) { $r['status'] = $status; $hit = true; break; }
    unset($r);
    if ($hit) event_regs_save($eventId, $list);
    return $hit;
}

/** 统计：总报名/通过/待审/已签到 + 按票种 */
function event_stats(string $eventId): array {
    $list = event_regs($eventId);
    $s = ['total' => count($list), 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'checked_in' => 0, 'by_ticket' => []];
    foreach ($list as $r) {
        $st = $r['status'] ?? 'pending';
        if (isset($s[$st])) $s[$st]++;
        if (!empty($r['checked_in'])) $s['checked_in']++;
        $tn = (string)($r['ticket_name'] ?? '普通票');
        $s['by_ticket'][$tn] = ($s['by_ticket'][$tn] ?? 0) + 1;
    }
    $s['checkin_rate'] = $s['approved'] > 0 ? round($s['checked_in'] / $s['approved'] * 100, 1) : 0;
    return $s;
}
