<?php
declare(strict_types=1);
/**
 * Report Hub —— 报告托管平台（项目 / 版本 / 权限 / 托管交互页）
 *
 * 设计取舍：
 * - 复用 OpenFlow 自己的登录体系（admin 账号 = 多用户），不引入第二套账号。
 * - 存储放 data/reports/<slug>/<version>/，data/ 对外 403，只能经 r.php 出流。
 * - 「版本」是一次发布的完整快照；回滚 = 把旧版本标为 active，不删文件。
 * - 权限四档：public（任何人）/ link（凭 token 链接）/ password（口令）/ members（登录用户）。
 * - 任意静态产物都能托管：单文件 HTML、含 JS/CSS/图片的目录、甚至 SPA（有 index.html 即可交互）。
 */

require_once __DIR__ . '/Database.php';

function rh_root(): string { return DATA_DIR . '/reports'; }

function rh_ensure(): void {
    static $done = false;
    if ($done) return;
    Database::execute("CREATE TABLE IF NOT EXISTS rh_projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT DEFAULT '',
        visibility TEXT DEFAULT 'members',
        password_hash TEXT DEFAULT '',
        owner TEXT DEFAULT '',
        created_at TEXT DEFAULT '',
        updated_at TEXT DEFAULT ''
    )");
    Database::execute("CREATE TABLE IF NOT EXISTS rh_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        version INTEGER NOT NULL,
        note TEXT DEFAULT '',
        entry TEXT DEFAULT 'index.html',
        file_count INTEGER DEFAULT 0,
        bytes INTEGER DEFAULT 0,
        status TEXT DEFAULT 'archived',
        created_by TEXT DEFAULT '',
        created_at TEXT DEFAULT '',
        UNIQUE(project_id, version)
    )");
    Database::execute("CREATE TABLE IF NOT EXISTS rh_shares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        token TEXT UNIQUE NOT NULL,
        note TEXT DEFAULT '',
        expires_at TEXT DEFAULT '',
        created_at TEXT DEFAULT ''
    )");
    Database::execute("CREATE TABLE IF NOT EXISTS rh_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        version INTEGER DEFAULT 0,
        path TEXT DEFAULT '',
        ip TEXT DEFAULT '',
        ua TEXT DEFAULT '',
        created_at TEXT DEFAULT ''
    )");
    Database::execute("CREATE INDEX IF NOT EXISTS idx_rh_versions ON rh_versions(project_id, version)");
    $done = true;
}

function rh_slug_ok(string $slug): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{1,48}$/', $slug);
}

function rh_projects(): array {
    rh_ensure();
    return Database::query("SELECT * FROM rh_projects ORDER BY updated_at DESC");
}

function rh_project(string $slug): ?array {
    rh_ensure();
    $r = Database::query("SELECT * FROM rh_projects WHERE slug = ?", [$slug]);
    return $r[0] ?? null;
}

function rh_create_project(string $slug, string $name, string $desc = '', string $visibility = 'members', string $owner = ''): array {
    rh_ensure();
    if (!rh_slug_ok($slug)) return ['ok' => false, 'error' => 'slug 只能用小写字母/数字/短横线，2–49 位'];
    if (rh_project($slug)) return ['ok' => false, 'error' => 'slug 已存在'];
    $now = date('Y-m-d H:i:s');
    Database::insert('rh_projects', [
        'slug' => $slug, 'name' => $name, 'description' => $desc,
        'visibility' => in_array($visibility, ['public','link','password','members'], true) ? $visibility : 'members',
        'owner' => $owner, 'created_at' => $now, 'updated_at' => $now,
    ]);
    return ['ok' => true, 'project' => rh_project($slug)];
}

/**
 * 发布：把服务器上的一个目录（或单个 HTML 文件）整体快照成新版本。
 * $src 必须是绝对路径且可读；目录里找不到 index.html 时取第一个 html。
 */
function rh_publish(string $slug, string $src, string $note = '', string $by = ''): array {
    rh_ensure();
    $p = rh_project($slug);
    if (!$p) return ['ok' => false, 'error' => '项目不存在'];
    if (!file_exists($src)) return ['ok' => false, 'error' => '源路径不存在: ' . $src];

    $pid = (int)$p['id'];
    $next = (int)(Database::query("SELECT COALESCE(MAX(version),0) AS v FROM rh_versions WHERE project_id = ?", [$pid])[0]['v'] ?? 0) + 1;
    $dest = rh_root() . '/' . $slug . '/' . $next;
    if (!is_dir($dest) && !mkdir($dest, 0755, true)) return ['ok' => false, 'error' => '无法创建版本目录'];

    $count = 0; $bytes = 0; $entry = '';
    $copy = function (string $from, string $to) use (&$count, &$bytes, &$entry, &$copy, $dest) {
        if (is_dir($from)) {
            $items = scandir($from) ?: [];
            foreach ($items as $it) {
                if ($it === '.' || $it === '..' || $it === '.git') continue;
                $copy($from . '/' . $it, $to . '/' . $it);
            }
            return;
        }
        if (!is_file($from)) return;
        $dir = dirname($to);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (@copy($from, $to)) {
            $count++; $bytes += (int)filesize($to);
            if ($entry === '' && strtolower(pathinfo($to, PATHINFO_EXTENSION)) === 'html') {
                $entry = ltrim(str_replace($dest, '', $to), '/');
            }
        }
    };
    if (is_file($src)) {
        $copy($src, $dest . '/' . basename($src));
    } else {
        $copy(rtrim($src, '/'), $dest);
    }
    if ($entry === '' && is_file($dest . '/index.html')) $entry = 'index.html';
    if ($count === 0) return ['ok' => false, 'error' => '没有可发布的文件'];

    Database::execute("UPDATE rh_versions SET status = 'archived' WHERE project_id = ?", [$pid]);
    Database::insert('rh_versions', [
        'project_id' => $pid, 'version' => $next, 'note' => $note, 'entry' => $entry,
        'file_count' => $count, 'bytes' => $bytes, 'status' => 'active',
        'created_by' => $by, 'created_at' => date('Y-m-d H:i:s'),
    ]);
    Database::execute("UPDATE rh_projects SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $pid]);
    return ['ok' => true, 'version' => $next, 'files' => $count, 'bytes' => $bytes, 'entry' => $entry];
}

function rh_versions(int $projectId): array {
    rh_ensure();
    return Database::query("SELECT * FROM rh_versions WHERE project_id = ? ORDER BY version DESC", [$projectId]);
}

function rh_active_version(int $projectId): ?array {
    rh_ensure();
    $r = Database::query("SELECT * FROM rh_versions WHERE project_id = ? AND status = 'active' ORDER BY version DESC LIMIT 1", [$projectId]);
    return $r[0] ?? null;
}

/** 回滚/切换：把指定版本设为 active，其余归档（不删文件） */
function rh_activate(int $projectId, int $version): bool {
    rh_ensure();
    $ok = Database::query("SELECT id FROM rh_versions WHERE project_id = ? AND version = ?", [$projectId, $version]);
    if (!$ok) return false;
    Database::execute("UPDATE rh_versions SET status = 'archived' WHERE project_id = ?", [$projectId]);
    Database::execute("UPDATE rh_versions SET status = 'active' WHERE project_id = ? AND version = ?", [$projectId, $version]);
    Database::execute("UPDATE rh_projects SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $projectId]);
    return true;
}

function rh_set_visibility(int $projectId, string $visibility, string $password = ''): bool {
    rh_ensure();
    if (!in_array($visibility, ['public','link','password','members'], true)) return false;
    $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';
    Database::execute("UPDATE rh_projects SET visibility = ?, password_hash = COALESCE(NULLIF(?,''), password_hash), updated_at = ? WHERE id = ?",
        [$visibility, $hash, date('Y-m-d H:i:s'), $projectId]);
    return true;
}

function rh_make_share(string $slug, string $note = '', string $expires = ''): string {
    rh_ensure();
    $p = rh_project($slug);
    if (!$p) return '';
    $token = bin2hex(random_bytes(16));
    Database::insert('rh_shares', [
        'project_id' => (int)$p['id'], 'token' => $token, 'note' => $note,
        'expires_at' => $expires, 'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $token;
}

function rh_shares(int $projectId): array {
    rh_ensure();
    return Database::query("SELECT * FROM rh_shares WHERE project_id = ? ORDER BY id DESC", [$projectId]);
}

function rh_share_valid(string $token): ?array {
    if ($token === '') return null;
    rh_ensure();
    $r = Database::query("SELECT * FROM rh_shares WHERE token = ?", [$token]);
    if (!$r) return null;
    $s = $r[0];
    if (!empty($s['expires_at']) && strtotime((string)$s['expires_at']) < time()) return null;
    return $s;
}

function rh_log_view(int $projectId, int $version, string $path): void {
    try {
        rh_ensure();
        Database::insert('rh_views', [
            'project_id' => $projectId, 'version' => $version, 'path' => $path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {}
}

function rh_mime(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return [
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
    ][$ext] ?? 'application/octet-stream';
}
