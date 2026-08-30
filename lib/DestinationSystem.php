<?php
/**
 * DestinationSystem —— 人群激活 / 反向 ETL（AUDIT-02 P0战略 / BACKLOG T0-6）
 *
 * 【为什么】CDP 能圈人群，却"圈了推不出去"——没有把人群送到外部(广告/邮件/webhook)
 * 的最后一公里。本层给一个轻量 destinations：人群 → 字段映射 → 推到目的地，
 * 支持 segment_enter 实时触发 + 手动全量同步。复用已有 WebhookSystem / ConversionApi。
 *
 * 存储：data/cdp/destinations.json。目的地类型：webhook / capi / (email 复用 webhook)。
 * 发送可注入（$GLOBALS['DEST_SENDER']）便于测试，否则走真实 HTTP。
 */

if (!function_exists('dest_file')) {

    function dest_file(): string { return DATA_DIR . '/cdp/destinations.json'; }

    function dest_all(): array {
        $d = function_exists('json_read') ? json_read(dest_file()) : [];
        return is_array($d) ? $d : [];
    }
    function dest_get(string $id): ?array {
        foreach (dest_all() as $d) if (($d['id'] ?? '') === $id) return $d;
        return null;
    }
    function dest_types(): array { return ['webhook' => 'Webhook', 'capi' => '广告转化API', 'email' => '邮件列表(webhook)']; }

    /** 新建/更新目的地。返回 ['ok'=>bool,'dest'|'error']。 */
    function dest_save(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'error' => '名称不能为空'];
        $type = isset(dest_types()[$data['type'] ?? '']) ? $data['type'] : 'webhook';
        $list = dest_all();
        $now = date('Y-m-d H:i:s');
        $id = (string)($data['id'] ?? '');
        $fieldMap = $data['field_map'] ?? [];
        if (is_string($fieldMap)) { $tmp = []; foreach (array_filter(array_map('trim', explode("\n", $fieldMap))) as $ln) { if (strpos($ln, '=') !== false) { [$k,$v] = explode('=', $ln, 2); $tmp[trim($k)] = trim($v); } } $fieldMap = $tmp; }
        $row = [
            'id' => $id ?: ('dest_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4)),
            'name' => $name, 'type' => $type,
            'segment_id' => (string)($data['segment_id'] ?? ''),
            'url' => (string)($data['url'] ?? ''), 'token' => (string)($data['token'] ?? ''),
            'field_map' => is_array($fieldMap) ? $fieldMap : [],
            'trigger' => in_array(($data['trigger'] ?? 'realtime'), ['realtime', 'manual'], true) ? ($data['trigger'] ?? 'realtime') : 'realtime',
            'enabled' => !empty($data['enabled']),
            'created_at' => $now, 'updated_at' => $now,
        ];
        if ($id !== '') {
            $hit = false;
            foreach ($list as &$d) if (($d['id'] ?? '') === $id) { $row['created_at'] = $d['created_at'] ?? $now; $row['stats'] = $d['stats'] ?? []; $d = $row; $hit = true; break; }
            unset($d);
            if (!$hit) return ['ok' => false, 'error' => '目的地不存在'];
        } else { $list[] = $row; }
        json_write(dest_file(), $list);
        return ['ok' => true, 'dest' => $row];
    }
    function dest_delete(string $id): bool {
        $list = dest_all(); $n = count($list);
        $list = array_values(array_filter($list, fn($d) => ($d['id'] ?? '') !== $id));
        if (count($list) === $n) return false;
        json_write(dest_file(), $list); return true;
    }

    /** 从画像按点路径取值（如 properties.email）。 */
    function _dest_pick(array $profile, string $path) {
        $cur = $profile;
        foreach (explode('.', $path) as $seg) {
            if (is_array($cur) && array_key_exists($seg, $cur)) $cur = $cur[$seg];
            else return null;
        }
        return $cur;
    }

    /**
     * 按字段映射组装要发送的载荷（纯函数，可测）。
     * 无映射时给一份合理默认（visitor_id/member_id/email/tags）。
     */
    function dest_build_payload(array $dest, array $profile): array {
        $map = $dest['field_map'] ?? [];
        if (!$map) {
            return array_filter([
                'visitor_id' => $profile['visitor_id'] ?? null,
                'member_id'  => $profile['member_id'] ?? null,
                'email'      => _dest_pick($profile, 'properties.email'),
                'tags'       => $profile['tags'] ?? null,
            ], fn($v) => $v !== null && $v !== []);
        }
        $out = [];
        foreach ($map as $destField => $srcPath) {
            $v = _dest_pick($profile, (string)$srcPath);
            if ($v !== null) $out[$destField] = $v;
        }
        return $out;
    }

    /** 实际发送（可注入 sender 便于测试）。返回 bool。 */
    function dest_send(array $dest, array $payload): bool {
        if (isset($GLOBALS['DEST_SENDER']) && is_callable($GLOBALS['DEST_SENDER'])) {
            return (bool)call_user_func($GLOBALS['DEST_SENDER'], $dest, $payload);
        }
        $type = $dest['type'] ?? 'webhook';
        try {
            if ($type === 'capi' && function_exists('conv_track')) {
                conv_track(array_merge(['event_name' => 'AudienceSync'], $payload));
                return true;
            }
            // webhook / email → HTTP POST
            $url = (string)($dest['url'] ?? '');
            if ($url === '') return false;
            $ch = curl_init($url);
            $headers = ['Content-Type: application/json'];
            if (!empty($dest['token'])) $headers[] = 'Authorization: Bearer ' . $dest['token'];
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        } catch (\Throwable $e) { return false; }
    }

    /** 把一个画像推给一个目的地（组装+发送）。 */
    function dest_dispatch_profile(array $dest, array $profile): bool {
        if (empty($dest['enabled'])) return false;
        return dest_send($dest, dest_build_payload($dest, $profile));
    }

    /**
     * segment_enter 实时触发：把进群画像推给该人群下所有 realtime 目的地。
     * 由 CdpSystem 在进群时旁路调用。返回派发条数。
     */
    function dest_on_segment_enter(string $segmentId, array $profile): int {
        $n = 0;
        foreach (dest_all() as $d) {
            if (empty($d['enabled']) || ($d['trigger'] ?? '') !== 'realtime') continue;
            if (($d['segment_id'] ?? '') !== $segmentId) continue;
            if (dest_dispatch_profile($d, $profile)) $n++;
        }
        return $n;
    }

    /**
     * 手动全量同步：把目的地对应人群的当前成员全部推一遍（反向 ETL 批量）。
     * $members 可注入（测试用）；否则用 CdpSystem 按人群规则查。
     */
    function dest_sync_full(string $destId, ?array $members = null): array {
        $dest = dest_get($destId);
        if (!$dest) return ['ok' => false, 'error' => '目的地不存在'];
        if ($members === null) {
            $members = [];
            if (class_exists('CdpSystem') && ($dest['segment_id'] ?? '') !== '') {
                foreach (\CdpSystem::allSegments() as $s) {
                    if (($s['id'] ?? '') === $dest['segment_id']) { $members = \CdpSystem::getSegmentUsers($s['rules'] ?? [], 100000); break; }
                }
            }
        }
        $ok = 0; $fail = 0;
        foreach ($members as $p) { if (is_array($p) && dest_dispatch_profile($dest, $p)) $ok++; else $fail++; }
        // 记录 last_run/stats
        $list = dest_all();
        foreach ($list as &$d) if (($d['id'] ?? '') === $destId) { $d['last_run'] = date('Y-m-d H:i:s'); $d['stats'] = ['synced' => $ok, 'failed' => $fail, 'at' => date('Y-m-d H:i:s')]; break; }
        unset($d);
        json_write(dest_file(), $list);
        return ['ok' => true, 'synced' => $ok, 'failed' => $fail];
    }
}
