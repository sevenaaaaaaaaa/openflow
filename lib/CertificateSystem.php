<?php
/**
 * CertificateSystem — 结业证书（完课自动颁发 + 可验证）
 *
 * 完课（进度 100%）且课程开启「颁发证书」时自动出证；证书号唯一、带校验码，
 * 前台 /certificate/{no} 可验证真伪。认证课默认出证，普通课可在编辑页开启。
 */

function cert_file(): string { return DATA_DIR . '/courses/certificates.json'; }
function cert_all(): array { $d = json_read(cert_file()); return is_array($d) ? $d : []; }
function cert_secret(): string { return (string)(json_read(DATA_DIR . '/settings.json')['install_secret'] ?? 'openflow'); }

/** 颁发（幂等：同一人同一课只发一张） */
function cert_issue(string $memberId, string $courseId, string $courseTitle, string $name = ''): ?array {
    if ($memberId === '' || $courseId === '') return null;
    $all = cert_all();
    foreach ($all as $c) if (($c['member_id'] ?? '') === $memberId && ($c['course_id'] ?? '') === $courseId) return $c;
    $certNo = 'OF-CERT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $row = [
        'cert_no' => $certNo, 'member_id' => $memberId, 'course_id' => $courseId,
        'course_title' => mb_substr($courseTitle, 0, 80), 'name' => mb_substr($name, 0, 40),
        'issued_at' => date('Y-m-d H:i:s'),
    ];
    $row['verify'] = substr(hash_hmac('sha256', $certNo . '|' . $memberId, cert_secret()), 0, 12);
    $all[$certNo] = $row;
    json_write(cert_file(), $all);
    return $row;
}

function cert_for_member(string $memberId): array {
    $out = [];
    foreach (cert_all() as $c) if (($c['member_id'] ?? '') === $memberId) $out[] = $c;
    usort($out, fn($a, $b) => strcmp($b['issued_at'] ?? '', $a['issued_at'] ?? ''));
    return $out;
}

function cert_get(string $certNo): ?array {
    $all = cert_all();
    return $all[$certNo] ?? null;
}

function cert_verify(string $certNo): bool {
    $c = cert_get($certNo);
    if (!$c) return false;
    return hash_equals((string)$c['verify'], substr(hash_hmac('sha256', $certNo . '|' . ($c['member_id'] ?? ''), cert_secret()), 0, 12));
}

/** 课程完成时按需出证（供 ProgressSystem 调用） */
function cert_maybe_issue(string $memberId, array $course, string $name = ''): ?array {
    $cfg = !empty($course['certificate']) || (($course['type'] ?? '') === '认证课');
    if (!$cfg) return null;
    return cert_issue($memberId, (string)($course['id'] ?? ''), (string)($course['title'] ?? ''), $name);
}
