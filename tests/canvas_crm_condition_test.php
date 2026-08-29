<?php
/**
 * A2 验收：画布条件节点读 CRM 字段
 *
 *   php tests/canvas_crm_condition_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-canvas-test-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

// 只取 CanvasSystem 里被测的三个纯函数，避开它顶部对邮件/通知模块的依赖
$src = file_get_contents(__DIR__ . '/../lib/CanvasSystem.php');
foreach (['canvas_condition_fields', 'canvas_resolve_field', 'canvas_eval_condition'] as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', $src, $m)) {
        fwrite(STDERR, "无法抽取 {$fn}()\n"); exit(2);
    }
    eval($m[0]);
}
require_once __DIR__ . '/../lib/CrmSystem.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else     { $fail++; echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n"; }
}
function cond(string $field, string $op, $value, array $ctx): bool {
    return canvas_eval_condition(['field'=>$field, 'op'=>$op, 'value'=>$value], $ctx);
}

// 造一条线索
crm_ensure_lead('vip@test.com', '大客户');
crm_update_lead('vip@test.com', [
    'stage' => 'opportunity', 'score' => 80, 'owner' => '小李',
    'value' => 50000, 'source' => '官网表单', 'tags' => ['高意向','企业版'],
]);
crm_add_followup('vip@test.com', '第一次电话', '小李');
$ctx = ['email' => 'vip@test.com', 'score' => 3, 'form_type' => 'demo'];

echo "\n── 1. 事件上下文字段（原有行为不能坏）──\n";
check('email 等于', cond('email', 'eq', 'vip@test.com', $ctx));
check('form_type 等于', cond('form_type', 'eq', 'demo', $ctx));
check('事件 score 大于', cond('score', 'gt', '1', $ctx));
check('事件 score 不等于 CRM score', !cond('score', 'eq', '80', $ctx));

echo "\n── 2. CRM 字段实时回查 ──\n";
check('crm.stage = opportunity', cond('crm.stage', 'eq', 'opportunity', $ctx));
check('crm.score > 50', cond('crm.score', 'gt', '50', $ctx));
check('crm.score >= 80（边界）', cond('crm.score', 'gte', '80', $ctx));
check('crm.score > 80 为假（边界）', !cond('crm.score', 'gt', '80', $ctx));
check('crm.owner = 小李', cond('crm.owner', 'eq', '小李', $ctx));
check('crm.value <= 50000', cond('crm.value', 'lte', '50000', $ctx));
check('crm.tags 包含 企业版', cond('crm.tags', 'contains', '企业版', $ctx));
check('crm.followup_count = 1', cond('crm.followup_count', 'eq', '1', $ctx));
check('crm.exists 不为空', cond('crm.exists', 'not_empty', '', $ctx));

echo "\n── 3. in 运算符 ──\n";
check('stage 属于 qualified,opportunity', cond('crm.stage', 'in', 'qualified,opportunity', $ctx));
check('stage 不属于 new,lost', !cond('crm.stage', 'in', 'new,lost', $ctx));

echo "\n── 4. 距上次跟进天数 ──\n";
check('刚跟进 → 0 天', cond('crm.days_since_followup', 'eq', '0', $ctx));
check('0 天 < 7', cond('crm.days_since_followup', 'lt', '7', $ctx));

echo "\n── 5. 关键：线索不存在时必须安全降级 ──\n";
$noCtx = ['email' => 'ghost@test.com'];
check('crm.stage 返回空', canvas_resolve_field('crm.stage', $noCtx) === '');
check('crm.exists 为空', cond('crm.exists', 'empty', '', $noCtx));
check('不匹配任何 eq 条件', !cond('crm.stage', 'eq', 'opportunity', $noCtx));
check('empty 分支可用', cond('crm.stage', 'empty', '', $noCtx));

echo "\n── 6. 上下文无 email 时不报错 ──\n";
$bare = ['form_type' => 'demo'];
$ok = true;
try { cond('crm.stage', 'eq', 'x', $bare); } catch (\Throwable $e) { $ok = false; }
check('无 email 不抛异常', $ok);
check('无 email 返回空', canvas_resolve_field('crm.stage', $bare) === '');

echo "\n── 7. 从未跟进的线索 ──\n";
crm_ensure_lead('new@test.com', '新线索');
$newCtx = ['email' => 'new@test.com'];
check('days_since_followup 为空而非 0', canvas_resolve_field('crm.days_since_followup', $newCtx) === '');
check('followup_count = 0', canvas_resolve_field('crm.followup_count', $newCtx) === 0);

echo "\n── 8. UI 字段清单与解析器一致 ──\n";
$fields = canvas_condition_fields();
$crmKeys = array_keys($fields['CRM 线索'] ?? []);
check('清单含 9 个 CRM 字段', count($crmKeys) === 9, '实际 ' . count($crmKeys));
$allResolvable = true;
foreach ($crmKeys as $k) {
    $v = canvas_resolve_field($k, $ctx);
    if ($v === null) { $allResolvable = false; break; }
}
check('清单里每个字段都能解析', $allResolvable);

foreach (glob(DATA_DIR . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 44) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
