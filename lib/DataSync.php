<?php
/**
 * DataSync — 外部数据连接器（主动拉取方向）
 * 支持：REST API 拉取 / CSV 导入
 * 目标：CDP 事件 / CRM 线索 / 画像属性
 * 复用 crm_ensure_lead / CdpSystem::track 写入，与 InboundReceiver 互补（主动 vs 推送）
 */

function datasync_file(): string { return DATA_DIR . '/sync-connectors.json'; }
function datasync_log_file(): string { return DATA_DIR . '/sync-log.json'; }
function datasync_dir(): string { return DATA_DIR . '/connector-files'; }

function datasync_connectors(): array {
    return json_read(datasync_file());
}
function datasync_save(array $list): void {
    json_write(datasync_file(), $list);
}
function datasync_connector(string $id): ?array {
    foreach (datasync_connectors() as $c) if (($c['id'] ?? '') === $id) return $c;
    return null;
}
function datasync_log(string $connId, array $entry): void {
    $log = json_read(datasync_log_file());
    array_unshift($log, array_merge(['connector_id' => $connId, 'at' => date('Y-m-d H:i:s')], $entry));
    json_write(datasync_log_file(), array_slice($log, 0, 200));
}

// 行数据 → 内部 payload（按 mapping 映射）
function datasync_map_row(array $conn, array $row): array {
    $map = (array)($conn['mapping'] ?? []);
    $payload = [];
    foreach ($map as $target => $source) {
        $payload[$target] = $row[$source] ?? null;
    }
    // 未映射但目标是 cdp_event 时，透传其余字段
    if (($conn['target'] ?? '') === 'cdp_event') {
        foreach ($row as $k => $v) {
            if (!in_array($k, array_values($map), true)) $payload[$k] = $v;
        }
    }
    return array_filter($payload, fn($v) => $v !== null && $v !== '');
}

// 执行单行写入
function datasync_write_row(array $conn, array $row): bool {
    $payload = datasync_map_row($conn, $row);
    $target = $conn['target'] ?? 'cdp_event';
    try {
        if ($target === 'lead') {
            require_once __DIR__ . '/CrmSystem.php';
            $email = $payload['email'] ?? '';
            $phone = $payload['phone'] ?? '';
            if ($email === '' && $phone === '') return false;
            $lead = crm_ensure_lead($email, $payload['name'] ?? '', $phone);
            $updates = [];
            foreach (['company','source'] as $f) if (!empty($payload[$f])) $updates[$f] = $payload[$f];
            if (!empty($updates)) crm_update_lead($email ?: $phone, $updates);
            return true;
        }
        require_once __DIR__ . '/CdpSystem.php';
        if ($target === 'contact') {
            CdpSystem::track('contact_sync', $payload, $payload['visitor_id'] ?? '');
            return true;
        }
        // cdp_event
        $event = $payload['event'] ?? ($conn['event'] ?? 'external_event');
        $props = $payload;
        unset($props['event'], $props['visitor_id']);
        $props['source'] = $conn['source'] ?? $conn['name'];
        CdpSystem::track($event, $props, $payload['visitor_id'] ?? '');
        return true;
    } catch (Throwable $e) {
        datasync_log($conn['id'] ?? '?', ['status' => 'error', 'target' => $target, 'error' => $e->getMessage()]);
        return false;
    }
}

// 拉取数据行（REST API / CSV）
function datasync_fetch_rows(array $conn): array {
    $kind = $conn['kind'] ?? 'rest_api';
    if ($kind === 'csv') {
        $file = datasync_dir() . '/' . ($conn['id'] ?? 'x') . '.csv';
        if (!is_file($file)) return [];
        $rows = [];
        if (($fp = fopen($file, 'r')) !== false) {
            $header = fgetcsv($fp);
            while (($row = fgetcsv($fp)) !== false) {
                if (count($row) !== count($header)) continue;
                $rows[] = array_combine($header, array_map('trim', $row));
            }
            fclose($fp);
        }
        return $rows;
    }
    // rest_api
    $cfg = (array)($conn['config'] ?? []);
    $url = $cfg['url'] ?? '';
    if ($url === '') return [];
    $ch = curl_init($url);
    $headers = [];
    if (!empty($cfg['token'])) $headers[] = 'Authorization: Bearer ' . $cfg['token'];
    $extra = json_decode($cfg['headers'] ?? '{}', true) ?: [];
    foreach ($extra as $hk => $hv) $headers[] = $hk . ': ' . $hv;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        datasync_log($conn['id'] ?? '?', ['status' => 'http_error', 'code' => $code]);
        return [];
    }
    $json = json_decode($body, true);
    if (!is_array($json)) return [];
    $key = trim($cfg['data_key'] ?? '');
    if ($key !== '' && isset($json[$key]) && is_array($json[$key])) return $json[$key];
    if (array_keys($json) === range(0, count($json) - 1)) return $json; // 纯数组
    return [$json]; // 单对象
}

// 执行连接器同步
function datasync_run_connector(string $id): array {
    $conn = datasync_connector($id);
    if (!$conn) return ['ok' => false, 'error' => '连接器不存在'];
    if (empty($conn['enabled'])) return ['ok' => false, 'error' => '已停用'];
    $rows = datasync_fetch_rows($conn);
    $ok = 0; $fail = 0;
    foreach ($rows as $row) {
        if (datasync_write_row($conn, $row)) $ok++; else $fail++;
    }
    $conn['last_sync'] = date('Y-m-d H:i:s');
    $conn['last_status'] = ($ok > 0 || ($ok === 0 && $fail === 0)) ? 'ok' : 'partial';
    $conn['last_count'] = $ok;
    $conns = datasync_connectors();
    foreach ($conns as &$c) if (($c['id'] ?? '') === $id) { $c = $conn; break; }
    unset($c);
    datasync_save($conns);
    datasync_log($id, ['status' => 'ok', 'rows' => $ok, 'failed' => $fail]);
    return ['ok' => true, 'rows' => $ok, 'failed' => $fail, 'source' => $conn['source'] ?? ''];
}

// 跑所有启用的连接器（cron 调用）
function datasync_run_all(): array {
    $result = [];
    foreach (datasync_connectors() as $c) {
        if (!empty($c['enabled'])) $result[$c['id']] = datasync_run_connector($c['id']);
    }
    return $result;
}
