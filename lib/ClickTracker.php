<?php
/**
 * ClickTracker —— 可视化圈选埋点（AUDIT-02 / BACKLOG T1-4）
 *
 * 【为什么】想统计"某个按钮被点了多少次"，此前得会写代码埋点。对无团队用户是门槛。
 * 本模块把埋点降成"填个选择器（或用圈选器点一下）+ 起个事件名"，前台自动绑定采集。
 *
 * 存储：data/cdp/click_tracks.json。
 * 前台由 assets/inject.js 拉取启用中的定义并绑 click 监听，命中即 fcTrack(event)。
 */

if (!function_exists('clicktrack_file')) {

    function clicktrack_file(): string { return DATA_DIR . '/cdp/click_tracks.json'; }

    function clicktrack_all(): array {
        $d = function_exists('json_read') ? json_read(clicktrack_file()) : [];
        return is_array($d) ? $d : [];
    }

    function clicktrack_get(string $id): ?array {
        foreach (clicktrack_all() as $t) if (($t['id'] ?? '') === $id) return $t;
        return null;
    }

    /** 事件名规范化：小写英数下划线，供 CDP 事件名使用。 */
    function clicktrack_norm_event(string $name): string {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z0-9_]+/', '_', $n);
        return trim($n, '_');
    }

    /**
     * 选择器安全校验：只允许常见 CSS 选择器字符，挡掉引号/尖括号等注入风险。
     * 前台是把它交给 querySelectorAll，非法字符会破坏脚本。
     */
    function clicktrack_valid_selector(string $sel): bool {
        $sel = trim($sel);
        if ($sel === '' || mb_strlen($sel) > 200) return false;
        return (bool)preg_match('/^[a-zA-Z0-9\s\.\#\[\]\=\-\_\:\(\)\>\,\*\"\']+$/', $sel)
            && strpos($sel, '<') === false && strpos($sel, '>') !== 0;
    }

    /**
     * 新建/更新埋点定义。$data: id?,name,selector,event,page(可选前缀匹配),enabled。
     * 返回 ['ok'=>bool,'track'|'error']。
     */
    function clicktrack_save(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        $sel  = trim((string)($data['selector'] ?? ''));
        if ($name === '') return ['ok' => false, 'error' => '名称不能为空'];
        if (!clicktrack_valid_selector($sel)) return ['ok' => false, 'error' => '选择器为空或含非法字符'];
        $event = clicktrack_norm_event((string)($data['event'] ?? '')) ?: clicktrack_norm_event($name) ?: 'click_custom';

        $list = clicktrack_all();
        $now  = date('Y-m-d H:i:s');
        $id   = (string)($data['id'] ?? '');
        $row  = [
            'id'       => $id ?: ('ct_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4)),
            'name'     => $name,
            'selector' => $sel,
            'event'    => $event,
            'page'     => trim((string)($data['page'] ?? '')),   // 空=全站；否则路径前缀匹配
            'enabled'  => !empty($data['enabled']),
            'hits'     => 0,
            'created_at' => $now, 'updated_at' => $now,
        ];
        if ($id !== '') {
            $hit = false;
            foreach ($list as &$t) {
                if (($t['id'] ?? '') === $id) {
                    $row['created_at'] = $t['created_at'] ?? $now;
                    $row['hits'] = (int)($t['hits'] ?? 0);
                    $t = $row; $hit = true; break;
                }
            }
            unset($t);
            if (!$hit) return ['ok' => false, 'error' => '埋点不存在'];
        } else {
            $list[] = $row;
        }
        json_write(clicktrack_file(), $list);
        return ['ok' => true, 'track' => $row];
    }

    function clicktrack_delete(string $id): bool {
        $list = clicktrack_all();
        $n = count($list);
        $list = array_values(array_filter($list, fn($t) => ($t['id'] ?? '') !== $id));
        if (count($list) === $n) return false;
        json_write(clicktrack_file(), $list);
        return true;
    }

    function clicktrack_toggle(string $id): bool {
        $list = clicktrack_all(); $hit = false;
        foreach ($list as &$t) if (($t['id'] ?? '') === $id) { $t['enabled'] = empty($t['enabled']); $hit = true; break; }
        unset($t);
        if ($hit) json_write(clicktrack_file(), $list);
        return $hit;
    }

    /** 命中计数（由采集端上报后累加，用于后台看"这个埋点有没有在跑"）。 */
    function clicktrack_hit(string $event): void {
        $list = clicktrack_all(); $hit = false;
        foreach ($list as &$t) if (($t['event'] ?? '') === $event) { $t['hits'] = (int)($t['hits'] ?? 0) + 1; $hit = true; }
        unset($t);
        if ($hit) json_write(clicktrack_file(), $list);
    }

    /**
     * 给前台的启用定义（按当前路径过滤）。只输出采集所需的最小字段。
     */
    function clicktrack_for_page(string $path = '/'): array {
        $out = [];
        foreach (clicktrack_all() as $t) {
            if (empty($t['enabled'])) continue;
            $page = trim((string)($t['page'] ?? ''));
            if ($page !== '' && strpos($path, $page) !== 0) continue;
            $out[] = ['selector' => $t['selector'], 'event' => $t['event'], 'name' => $t['name']];
        }
        return $out;
    }
}
