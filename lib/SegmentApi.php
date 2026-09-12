<?php
/**
 * SegmentApi — 分群即服务的纯逻辑（供 api/segments.php 复用，可单测）
 */

/** 邮箱脱敏：a***@b.com */
function segapi_mask_email(string $email): string {
    if ($email === '' || strpos($email, '@') === false) return '';
    [$u, $d] = explode('@', $email, 2);
    return mb_substr($u, 0, 1) . '***@' . $d;
}

/** 每个分群的成员数：segId => count */
function segapi_counts(array $profiles): array {
    $counts = [];
    foreach ($profiles as $p) {
        foreach (array_keys((array)($p['segment_memberships'] ?? [])) as $sid) {
            if ($sid !== '') $counts[$sid] = ($counts[$sid] ?? 0) + 1;
        }
    }
    return $counts;
}

/** 某分群的成员（脱敏） */
function segapi_members(array $profiles, string $segmentId, int $limit = 100): array {
    if ($segmentId === '') return [];
    $limit = max(1, min(1000, $limit));
    $out = [];
    foreach ($profiles as $p) {
        if (!isset(($p['segment_memberships'] ?? [])[$segmentId])) continue;
        $props = (array)($p['properties'] ?? []);
        $out[] = [
            'visitor_id' => (string)($p['visitor_id'] ?? ''),
            'email' => segapi_mask_email((string)($props['email'] ?? '')),
            'name' => (string)($props['name'] ?? ''),
            'score' => (int)($p['score'] ?? 0),
            'last_seen' => (string)($p['last_seen'] ?? ''),
            'joined_at' => (string)(($p['segment_memberships'][$segmentId]['joined_at'] ?? '')),
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}
