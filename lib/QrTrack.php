<?php
/**
 * 二维码扫描追踪 — 扫描计数 / 扫码后注册归因
 *
 * 流程：
 *   1. 后台生成二维码，data 指向 https://site/qr.php?t=qr_xxx&url=<目标>
 *   2. 用户扫码 → qr.php → 记录 scan 计数 → 302 到目标 url（携带 utm_source=qr&utm_medium=qr_xxx）
 *   3. 若用户之后注册 → 通过 cookie 中的 qr_track_id 归因到该二维码
 *
 * 存储：SQLite share 同库，表 qr_scans / qr_registrations
 */

if (!function_exists('qr_track_db')) {

function qr_track_db(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DATA_DIR . '/db/openflow.db');
        $db->exec("CREATE TABLE IF NOT EXISTS qr_scans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_id TEXT,
            target_url TEXT,
            visitor_ip TEXT DEFAULT '',
            visitor_ua TEXT DEFAULT '',
            created_at TEXT
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS qr_registrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_id TEXT,
            member_id TEXT,
            created_at TEXT
        )");
    }
    return $db;
}

// 记录一次扫码
function qr_track_scan(string $qrId, string $targetUrl, string $ip = '', string $ua = ''): void {
    $db = qr_track_db();
    $db->prepare("INSERT INTO qr_scans (qr_id, target_url, visitor_ip, visitor_ua, created_at)
        VALUES (:q, :t, :ip, :ua, :now)")
        ->execute([':q' => $qrId, ':t' => mb_substr($targetUrl, 0, 500), ':ip' => $ip, ':ua' => mb_substr($ua, 0, 500), ':now' => date('Y-m-d H:i:s')]);
    // 设置 30 天归因 cookie
    if (!headers_sent()) {
        setcookie('of_qr_id', $qrId, time() + 86400 * 30, '/');
    }
}

// 记录一次由二维码带来的注册
function qr_track_register(string $qrId, string $memberId): void {
    if (!$qrId || !$memberId) return;
    $db = qr_track_db();
    $db->prepare("INSERT INTO qr_registrations (qr_id, member_id, created_at)
        VALUES (:q, :m, :now)")
        ->execute([':q' => $qrId, ':m' => $memberId, ':now' => date('Y-m-d H:i:s')]);
}

// 某个二维码的统计
function qr_track_stats(string $qrId): array {
    $db = qr_track_db();
    $s = $db->prepare("SELECT COUNT(*) FROM qr_scans WHERE qr_id = :q");
    $s->execute([':q' => $qrId]);
    $r = $db->prepare("SELECT COUNT(DISTINCT member_id) FROM qr_registrations WHERE qr_id = :q");
    $r->execute([':q' => $qrId]);
    return ['scans' => (int)$s->fetchColumn(), 'registrations' => (int)$r->fetchColumn()];
}

// 全部二维码汇总
function qr_track_all(): array {
    $db = qr_track_db();
    $scans = $db->query("SELECT qr_id, COUNT(*) c, MAX(created_at) last FROM qr_scans GROUP BY qr_id");
    $regs = $db->query("SELECT qr_id, COUNT(DISTINCT member_id) c FROM qr_registrations GROUP BY qr_id");
    $map = [];
    foreach ($scans->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[$r['qr_id']] = ['scans' => (int)$r['c'], 'registrations' => 0, 'last' => $r['last']];
    }
    foreach ($regs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($map[$r['qr_id']])) $map[$r['qr_id']] = ['scans' => 0, 'registrations' => 0, 'last' => ''];
        $map[$r['qr_id']]['registrations'] = (int)$r['c'];
    }
    return $map;
}

} // end if function_exists
