<?php
/**
 * GrowthAction —— 一键采纳：大脑提议 → 行动闭环（AUDIT-07 P1-7）
 *
 * 【它解决什么】P0-3 的大脑只会"提议"，判断到行动之间是断的。本模块让人对一条
 * 提议"一键采纳"：不自动执行客户侧动作（护栏——发消息/收款仍由对应模块的确认步
 * 把关），而是把提议落成一条**待办（采纳箱）**，带着这个人的上下文指向对应模块，
 * 并记下"采纳了什么"作为反馈信号，同时让已采纳的提议不再重复冒到建议里。
 *
 * 【原则】纯本地 JSON、无外部依赖；采纳=建待办(不代执行)；按"人+动作"去重。
 * 数据：data/growth/actions.json。
 */

if (!function_exists('growth_action_file')) {
    function growth_action_file(): string { return DATA_DIR . '/growth/actions.json'; }
}

if (!function_exists('growth_action_all')) {
    function growth_action_all(): array {
        $f = growth_action_file();
        if (!is_file($f)) return [];
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }
    function growth_action_save_all(array $list): void {
        $f = growth_action_file();
        @mkdir(dirname($f), 0777, true);
        @file_put_contents($f, json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

if (!function_exists('growth_action_key')) {
    /** 去重键：人(id/邮箱/名) + 动作。 */
    function growth_action_key(string $who, string $action): string {
        return mb_strtolower(trim($who)) . '|' . trim($action);
    }
}

if (!function_exists('growth_action_open_keys')) {
    /** 当前 pending 的去重键集合——用来把"已采纳"的提议从建议里滤掉，避免重复冒。 */
    function growth_action_open_keys(): array {
        $keys = [];
        foreach (growth_action_all() as $a) {
            if (($a['status'] ?? '') !== 'pending') continue;
            $who = (string)($a['profile_id'] ?? '') ?: ((string)($a['profile_email'] ?? '') ?: (string)($a['profile_name'] ?? ''));
            $keys[growth_action_key($who, (string)($a['action'] ?? ''))] = true;
        }
        return $keys;
    }
}

if (!function_exists('growth_action_link_for')) {
    /** 采纳时解析"去处理"的落点：把画像上下文带进对应模块（报价单可预填）。 */
    function growth_action_link_for(string $module, string $action, array $profile): string {
        $name  = rawurlencode((string)($profile['name'] ?? ''));
        $email = rawurlencode((string)($profile['email'] ?? ''));
        if (strpos($action, '报价') !== false || strpos($action, '推成交') !== false) {
            return "/xmp/quotes?brain=1&prefill_customer={$name}&prefill_email={$email}";
        }
        if ($module === 'MA')      return '/xmp/canvas';
        if ($module === 'Content') return '/xmp/promos';
        return '/xmp/crm';
    }
}

if (!function_exists('growth_action_adopt')) {
    /**
     * 采纳一条提议 → 建 pending 待办（按人+动作去重，不重复建）。
     * $item: ['profile_id','profile_name','profile_email','module','action','reason','cta','goal_metric'?]
     * 返回 ['ok'=>true,'dup'=>bool,'action'=>row]。
     */
    function growth_action_adopt(array $item): array {
        $action = trim((string)($item['action'] ?? ''));
        if ($action === '') return ['ok' => false, 'error' => 'no_action'];
        $who = (string)($item['profile_id'] ?? '') ?: ((string)($item['profile_email'] ?? '') ?: (string)($item['profile_name'] ?? ''));
        $key = growth_action_key($who, $action);

        $list = growth_action_all();
        foreach ($list as $a) {
            if (($a['status'] ?? '') === 'pending') {
                $aWho = (string)($a['profile_id'] ?? '') ?: ((string)($a['profile_email'] ?? '') ?: (string)($a['profile_name'] ?? ''));
                if (growth_action_key($aWho, (string)($a['action'] ?? '')) === $key) {
                    return ['ok' => true, 'dup' => true, 'action' => $a];
                }
            }
        }

        $profile = ['name' => $item['profile_name'] ?? '', 'email' => $item['profile_email'] ?? ''];
        $row = [
            'id'            => 'act_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4),
            'profile_id'    => (string)($item['profile_id'] ?? ''),
            'profile_name'  => (string)($item['profile_name'] ?? ''),
            'profile_email' => (string)($item['profile_email'] ?? ''),
            'module'        => (string)($item['module'] ?? ''),
            'action'        => $action,
            'reason'        => (string)($item['reason'] ?? ''),
            'cta'           => (string)($item['cta'] ?? '去处理'),
            'goal_metric'   => (string)($item['goal_metric'] ?? ''),
            'link'          => growth_action_link_for((string)($item['module'] ?? ''), $action, $profile),
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s'),
        ];
        $list[] = $row;
        growth_action_save_all($list);
        return ['ok' => true, 'dup' => false, 'action' => $row];
    }
}

if (!function_exists('growth_action_pending')) {
    function growth_action_pending(): array {
        $rows = array_values(array_filter(growth_action_all(), fn($a) => ($a['status'] ?? '') === 'pending'));
        usort($rows, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $rows;
    }
}

if (!function_exists('growth_action_set_status')) {
    /** 完成 / 忽略一条待办。返回是否命中。 */
    function growth_action_set_status(string $id, string $status): bool {
        if (!in_array($status, ['done', 'dismissed', 'pending'], true)) return false;
        $list = growth_action_all(); $hit = false;
        foreach ($list as &$a) {
            if (($a['id'] ?? '') === $id) {
                $a['status'] = $status;
                $a[$status === 'done' ? 'done_at' : 'updated_at'] = date('Y-m-d H:i:s');
                $hit = true; break;
            }
        }
        unset($a);
        if ($hit) growth_action_save_all($list);
        return $hit;
    }
    function growth_action_complete(string $id): bool { return growth_action_set_status($id, 'done'); }
    function growth_action_dismiss(string $id): bool { return growth_action_set_status($id, 'dismissed'); }
}

if (!function_exists('growth_action_stats')) {
    /** 反馈信号：采纳/完成/忽略计数——大脑将来据此学"哪些建议真被执行"。 */
    function growth_action_stats(): array {
        $s = ['pending' => 0, 'done' => 0, 'dismissed' => 0, 'total' => 0];
        foreach (growth_action_all() as $a) {
            $st = $a['status'] ?? 'pending';
            if (isset($s[$st])) $s[$st]++;
            $s['total']++;
        }
        return $s;
    }
}
