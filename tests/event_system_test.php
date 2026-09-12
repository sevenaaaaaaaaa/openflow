<?php
/**
 * 活动体系 契约 —— 票种/议程/签到/统计 + 报名联动 CDP/CRM/MA
 *   php tests/event_system_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-events-' . getmypid());
@mkdir(DATA_DIR . '/events', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

// 联动 stub
$GLOBALS['LINK'] = ['lead' => 0, 'cdp' => 0, 'flow' => 0, 'flow_event' => ''];
function crm_ensure_lead(string $e, string $n): void { $GLOBALS['LINK']['lead']++; }
function crm_update_lead(string $e, array $d): void {}
function crm_add_followup(string $e, string $c, string $o = ''): void {}
class CdpSystem { public static function track(string $ev, array $d = [], string $v = ''): bool { $GLOBALS['LINK']['cdp']++; return true; } }
function flow_handle(string $event, array $d = []): void { $GLOBALS['LINK']['flow']++; $GLOBALS['LINK']['flow_event'] = $event; }

require_once __DIR__ . '/../lib/EventSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "活动体系\n";

$event = ['id' => 'ev1', 'title' => '增长工作坊', 'capacity' => 100, 'tickets' => [
    ['id' => 'free', 'name' => '免费票', 'price' => 0, 'capacity' => 50],
    ['id' => 'vip', 'name' => 'VIP 票', 'price' => 199, 'capacity' => 10],
], 'agenda' => [['time' => '14:00', 'title' => '开场', 'speaker' => 'A'], ['title' => '']]];

check('票种解析', count(event_tickets($event)) === 2 && event_ticket($event, 'vip')['price'] === 199.0);
check('无票种回退普通票', event_tickets(['id' => 'x'])[0]['name'] === '普通票');
check('议程去掉空标题', count(event_agenda($event)) === 1);

// 报名记录
$reg = ['id' => 'reg1', 'event_id' => 'ev1', 'member_id' => 'm1', 'name' => '小明', 'email' => 'm@b.com', 'status' => 'approved', 'ticket_name' => '免费票', 'checked_in' => false, 'created_at' => date('Y-m-d H:i:s')];
event_regs_save('ev1', [$reg]);
event_regs_save('ev1', array_merge(event_regs('ev1'), [array_merge($reg, ['id' => 'reg2', 'email' => 'n@b.com', 'status' => 'pending', 'ticket_name' => 'VIP 票'])]));

$st = event_stats('ev1');
check('统计总数/通过/待审', $st['total'] === 2 && $st['approved'] === 1 && $st['pending'] === 1);
check('票种分布统计', ($st['by_ticket']['免费票'] ?? 0) === 1 && ($st['by_ticket']['VIP 票'] ?? 0) === 1);

$tk = event_checkin_token('ev1', 'reg1');
check('签到码稳定', strlen($tk) === 16 && $tk === event_checkin_token('ev1', 'reg1'));
$ci = event_checkin_by_token('ev1', $tk);
check('按签到码核销', ($ci['ok'] ?? false) === true && ($ci['name'] ?? '') === '小明');
check('重复核销幂等', (event_checkin('ev1', 'reg1')['already'] ?? false) === true);
$st2 = event_stats('ev1');
check('签到率统计', $st2['checked_in'] === 1 && $st2['checkin_rate'] === 100.0);

check('报名状态变更', event_set_reg_status('ev1', 'reg2', 'approved') === true);
check('非法状态被拒', event_set_reg_status('ev1', 'reg2', 'bogus') === false);

// 报名联动（CRM + CDP + flow）
event_register_linkage($event, $reg);
check('报名建 CRM 线索', $GLOBALS['LINK']['lead'] === 1);
check('报名写 CDP 事件', $GLOBALS['LINK']['cdp'] === 1);
check('报名触发自动化为 event_register', $GLOBALS['LINK']['flow'] === 1 && $GLOBALS['LINK']['flow_event'] === 'event_register');

// 结构性
$as = file_get_contents(__DIR__ . '/../lib/AutomationSystem.php');
check('自动化识别 event_register 触发', strpos($as, "'event_register'") !== false);
$adm = file_get_contents(__DIR__ . '/../admin/events.php');
check('活动后台用 events 权限', strpos($adm, "require_perm('events')") !== false);
$api = file_get_contents(__DIR__ . '/../api/event-register.php');
check('报名 API 调用联动', strpos($api, 'event_register_linkage(') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
