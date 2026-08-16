<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

define('DATA_DIR', __DIR__ . '/../data');
define('PAGES_DIR', DATA_DIR . '/pages');
define('ARTICLES_DIR', DATA_DIR . '/articles');
define('LEADS_CSV', __DIR__ . '/../leads.csv');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('SITE_URL', '//' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

foreach ([DATA_DIR, PAGES_DIR, ARTICLES_DIR, UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

require_once __DIR__ . '/seo-functions.php';
require_once __DIR__ . '/review-lib.php';
require_once __DIR__ . '/../lib/PluginSystem.php';
require_once __DIR__ . '/../lib/CommandPalette.php';
PluginSystem::load_plugins();

// ─── RBAC ──────────────────────────────────────────
// Users stored in data/users.json
function get_users(): array {
    return json_read(DATA_DIR . '/users.json');
}

function save_users(array $users): bool {
    return json_write(DATA_DIR . '/users.json', $users);
}

// Bootstrap default users if none exist
$users = json_read(DATA_DIR . '/users.json');
if (empty($users)) {
    $users = [
        'Seven' => [
            'password_hash' => password_hash('jWj2HB3SmZM6a&4zir', PASSWORD_DEFAULT),
            'role' => 'admin',
            'name' => '超级管理员',
        ],
        'marketing' => [
            'password_hash' => password_hash('marketing2024', PASSWORD_DEFAULT),
            'role' => 'marketing',
            'name' => '市场总监',
        ],
        'sales' => [
            'password_hash' => password_hash('sales2024', PASSWORD_DEFAULT),
            'role' => 'sales',
            'name' => '销售总监',
        ],
    ];
    save_users($users);
}

// Role permission map
function role_perms(): array {
    return [
        'admin'     => ['pages', 'articles', 'ingest', 'categories', 'tags', 'topics', 'landing', 'events', 'courses', 'downloads', 'community-config', 'tasks', 'survey', 'nps', 'campaigns', 'ai-config', 'knowledge', 'sms', 'forms', 'submissions', 'channels', 'wechat-mp', 'social', 'conversion', 'seo', 'seo-tools', 'seo-batch', 'redirects', 'structured', 'geo', 'sentiment', 'seo-console', 'profiling', 'notify-channels', 'cdp', 'themes', 'plugins', 'activity', 'media', 'dam', 'qr', 'utm-builder', 'scripts', 'abtests', 'ma-sync', 'reviews', 'approvals', 'shop-settings', 'navigation', 'site-builder', 'podcasts', 'community-mod', 'automation', 'insights', 'subscription', 'consultation', 'live', 'membership', 'messages', 'storage', 'moderation', 'marketplace', 'flow', 'tracking', 'canvas', 'analytics', 'dashboard', 'crm', 'leads', 'export', 'settings', 'devops', 'users', 'email'],
        'marketing' => ['pages', 'articles', 'ingest', 'categories', 'tags', 'topics', 'landing', 'events', 'courses', 'downloads', 'community-config', 'tasks', 'survey', 'nps', 'campaigns', 'ai-config', 'knowledge', 'sms', 'forms', 'submissions', 'channels', 'wechat-mp', 'social', 'conversion', 'seo', 'seo-tools', 'seo-batch', 'redirects', 'structured', 'geo', 'sentiment', 'seo-console', 'profiling', 'notify-channels', 'cdp', 'themes', 'plugins', 'activity', 'media', 'dam', 'qr', 'utm-builder', 'reviews', 'approvals', 'shop-settings', 'navigation', 'site-builder', 'podcasts', 'community-mod', 'automation', 'insights', 'subscription', 'consultation', 'live', 'membership', 'messages', 'storage', 'moderation', 'marketplace', 'flow', 'tracking', 'canvas', 'analytics', 'dashboard', 'crm', 'email'],
        'sales'     => ['leads'],
    ];
}

function has_perm(string $perm): bool {
    $role = $_SESSION['admin_role'] ?? '';

    // Fallback: if admin_user exists but no role, treat as admin (migration safety)
    if (empty($role) && isset($_SESSION['admin_user'])) {
        $role = 'admin';
        $_SESSION['admin_role'] = 'admin';
    }

    $perms = role_perms()[$role] ?? [];
    return in_array($perm, $perms, true);
}

function require_perm(string $perm): void {
    if (!has_perm($perm)) {
        http_response_code(403);
        $role = $_SESSION['admin_role'] ?? '未设置';
        $user = $_SESSION['admin_user'] ?? '未登录';
        die("权限不足 (角色: {$role}, 用户: {$user}, 所需权限: {$perm})，请联系管理员。");
    }
}

// ─── Auth ──────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// ── Login URL & password reset ───
function user_login_url(string $username): string {
    return site_config_get('site_url') . '/login';
}

function user_reset_password(string $username, string $newPassword): array {
    $users = get_users();
    if (!isset($users[$username])) return ['ok' => false, 'error' => '用户不存在'];
    $users[$username]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    save_users($users);
    return ['ok' => true];
}

// Send password reset email (requires SMTP)
function user_send_reset_email(string $username): array {
    $users = get_users();
    if (!isset($users[$username])) return ['ok' => false, 'error' => '用户不存在'];
    $u = $users[$username];
    if (empty($u['email'])) return ['ok' => false, 'error' => '该账户未设置邮箱'];

    $token = bin2hex(random_bytes(16));
    $users[$username]['reset_token'] = $token;
    $users[$username]['reset_token_expires'] = time() + 3600;
    save_users($users);

    $url = site_config_get('site_url') . '/admin/reset-password.php?token=' . $token;
    $subject = 'OpenFlow 密码重置';
    $body = "你好 {$u['name']}，\n\n点击以下链接重置密码（1小时内有效）：\n{$url}\n\n如非本人操作请忽略。";

    $settings = site_config();
    if (!empty($settings['smtp_host']) && !empty($settings['smtp_user'])) {
        $sent = smtp_send($u['email'], $subject, $body, $settings);
        return $sent ? ['ok' => true] : ['ok' => false, 'error' => '邮件发送失败'];
    }
    return ['ok' => false, 'error' => 'SMTP 未配置，请联系管理员手动重置', 'token_url' => $url];
}

// ─── JSON helpers ──────────────────────────────────
function json_read(string $path): array {
    return file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
}

function json_write(string $path, array $data): bool {
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function msg(string $type, string $text): string {
    return '<div class="msg msg-' . $type . '">' . htmlspecialchars($text) . '</div>';
}

// ─── Pages ─────────────────────────────────────────
function default_page_content(string $page): array {
    $d = [
        'index' => [
            'hero_title' => '让网站，成为',
            'hero_title_highlight' => '自己的增长引擎',
            'hero_subtitle' => 'AI 重塑了搜索、内容与转化方式。当「做好一个网站」不再等于「获得增长」，被数据驱动的增长链路才是穿越周期的唯一变量。',
            'hero_chip' => '从建站时代 → AI 增长时代',
            'hero_trust_text' => '服务 1000+ 网站与品牌',
            'problem_title' => '网站还在，增长却先「失灵」了',
            'capability_title' => '三大核心能力，内容 → 增长 → 转化',
            'solutions_title' => '分层设计，覆盖增长的每一环',
            'cases_title' => '1000+ 网站的共同选择',
            'vision_title' => '推动 100 万网站成为自动增长型站点',
            'cta_title' => '先做一次网站增长诊断',
            'cta_phone' => '13166373667',
        ],
        'about' => [
            'founder_quote' => '增长不是运气，是一套可以被构建的系统',
            'mission' => '让网站的增长能力成为每个组织的基础设施',
        ],
        'capability' => [
            'banner_title' => '一套闭环能力，把「内容」变成「增长」',
            'wellq_title' => '内容引擎 + SEO/GEO',
        ],
        'courses' => [
            'banner_title' => '分层设计，覆盖增长的每一环',
        ],
        'flow-community' => [
            'hero_title' => 'Flow社区：把网站增长讲清楚、用起来',
        ],
    ];
    return $d[$page] ?? [];
}

function page_content(string $page): array {
    $saved = json_read(PAGES_DIR . '/' . $page . '.json');
    return array_merge(default_page_content($page), $saved);
}

function save_page_content(string $page, array $data): bool {
    $existing = json_read(PAGES_DIR . '/' . $page . '.json');
    return json_write(PAGES_DIR . '/' . $page . '.json', array_merge($existing, $data));
}

// ─── Leads ─────────────────────────────────────────
function get_leads(): array {
    if (!file_exists(LEADS_CSV)) return [];
    $fp = fopen(LEADS_CSV, 'r');
    if (!$fp) return [];
    $headers = fgetcsv($fp, 0, ',', '"', '\\');
    if (!$headers) { fclose($fp); return []; }
    $rows = [];
    while ($row = fgetcsv($fp, 0, ',', '"', '\\')) {
        if (count($row) === count($headers)) $rows[] = array_combine($headers, $row);
    }
    fclose($fp);
    return array_reverse($rows);
}

// ─── Articles ──────────────────────────────────────
function get_articles(): array {
    $all = json_read(ARTICLES_DIR . '/index.json');
    usort($all, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $all;
}

function get_article(string $id): ?array {
    foreach (get_articles() as $a) {
        if ($a['id'] === $id) return $a;
    }
    return null;
}

function save_article(string $id, array $data): bool {
    $all = json_read(ARTICLES_DIR . '/index.json');
    $found = false;
    foreach ($all as &$a) {
        if ($a['id'] === $id) { $a = array_merge($a, $data); $found = true; break; }
    }
    if (!$found) {
        $data['id'] = $id;
        $all[] = $data;
    }
    return json_write(ARTICLES_DIR . '/index.json', $all);
}

function delete_article(string $id): bool {
    $all = json_read(ARTICLES_DIR . '/index.json');
    $all = array_values(array_filter($all, fn($a) => $a['id'] !== $id));
    return json_write(ARTICLES_DIR . '/index.json', $all);
}

function article_slug_exists(string $slug, ?string $excludeId = null): bool {
    foreach (get_articles() as $a) {
        if (($a['slug'] ?? '') === $slug && $a['id'] !== $excludeId) return true;
    }
    return false;
}

// ─── Categories (type-aware) ──────────────────────
function get_categories(string $type = 'article'): array {
    $all = json_read(DATA_DIR . '/categories.json');
    return $all[$type] ?? [];
}
function save_categories(string $type, array $data): bool {
    $all = json_read(DATA_DIR . '/categories.json');
    $all[$type] = $data;
    return json_write(DATA_DIR . '/categories.json', $all);
}
function get_category_options(string $type): array {
    $cats = get_categories($type);
    $opts = ['' => '未分类'];
    foreach ($cats as $c) {
        $prefix = empty($c['parent']) ? '' : '— ';
        $opts[$c['key']] = $prefix . $c['name'];
    }
    return $opts;
}
function get_tags(): array {
    return json_read(DATA_DIR . '/tags.json');
}
function save_tags(array $data): bool {
    return json_write(DATA_DIR . '/tags.json', $data);
}

// ─── UI ───────────────────────────────────────────
function admin_header(string $title): void {
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>try{var t=localStorage.getItem('of_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}</script>
<title><?=htmlspecialchars($title)?> | OpenFlow XMP</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:oklch(96.5% .016 85); --bg-soft:oklch(94% .02 85);
  --surface:oklch(100% 0 0 / .62); --surface-strong:oklch(100% 0 0 / .88);
  --fg:oklch(22% .02 70); --muted:oklch(44% .016 70); --faint:oklch(52% .012 75);
  --border:oklch(86% .014 80); --border-strong:oklch(76% .02 80);
  --hover:oklch(22% .02 70 / .055); --hover-strong:oklch(22% .02 70 / .11);
  --accent:oklch(68% .18 140); --accent-strong:oklch(60% .18 140); --accent-soft:oklch(68% .18 140 / .12); --on-accent:oklch(22% .02 70);
  --ok:oklch(47% .15 152); --ok-soft:oklch(47% .15 152 / .12);
  --warn:oklch(52% .14 75); --warn-soft:oklch(52% .14 75 / .14);
  --danger:oklch(52% .2 25); --danger-soft:oklch(52% .2 25 / .12);
  --glass:oklch(100% 0 0 / .5); --glass-bright:oklch(100% 0 0 / .68);
  --shadow:0 24px 60px -24px oklch(30% .04 80 / .3); --shadow-sm:0 10px 28px -14px oklch(30% .04 80 / .24);
  --r-lg:26px; --r-md:18px; --r-sm:12px;
  --chrome-h:56px; --sb-w:240px;
  --grad:linear-gradient(135deg,oklch(70% .18 145),oklch(66% .16 180));
  --font-body:-apple-system,BlinkMacSystemFont,'PingFang SC','Segoe UI',system-ui,sans-serif;
  --font-mono:ui-monospace,'SF Mono','JetBrains Mono',Menlo,monospace;
  color-scheme:light;
}
[data-theme="dark"]{
  --bg:oklch(19% .014 70); --bg-soft:oklch(22.5% .014 72);
  --surface:oklch(27% .016 75 / .55); --surface-strong:oklch(30% .016 75 / .82);
  --fg:oklch(93% .008 85); --muted:oklch(72% .014 80); --faint:oklch(62% .012 80);
  --border:oklch(100% 0 0 / .1); --border-strong:oklch(100% 0 0 / .22);
  --hover:oklch(93% .008 85 / .07); --hover-strong:oklch(93% .008 85 / .13);
  --accent:oklch(74% .15 155); --accent-strong:oklch(82% .14 155); --accent-soft:oklch(74% .15 155 / .15);
  --ok:oklch(76% .15 152); --ok-soft:oklch(76% .15 152 / .15);
  --warn:oklch(78% .13 80); --warn-soft:oklch(78% .13 80 / .15);
  --danger:oklch(74% .17 25); --danger-soft:oklch(74% .17 25 / .14);
  --glass:oklch(30% .014 75 / .5); --glass-bright:oklch(34% .014 75 / .64);
  --shadow:0 24px 60px -24px oklch(0% 0 0 / .55); --shadow-sm:0 10px 28px -14px oklch(0% 0 0 / .5);
  --grad:linear-gradient(135deg,oklch(72% .16 150),oklch(68% .15 180));
  color-scheme:dark;
}
body{font-family:var(--font-body); color:var(--fg); background:var(--bg); -webkit-font-smoothing:antialiased; overflow-x:clip; line-height:1.5}
::selection{background:var(--accent-soft)}
:focus-visible{outline:2px solid var(--accent); outline-offset:2px; border-radius:8px}
button{font:inherit; color:inherit; background:none; border:0; cursor:pointer}
input,select,textarea{font:inherit; color:inherit}
h1,h2,h3,h4,p{margin:0}
svg{display:block}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:var(--border-strong); border-radius:99px; border:3px solid transparent; background-clip:padding-box}
::-webkit-scrollbar-track{background:transparent}

/* ── top chrome ── */
#chrome{position:fixed; inset:0 0 auto 0; z-index:60; padding:8px 14px}
.bar{position:relative; height:var(--chrome-h); display:flex; align-items:center; gap:10px; padding:0 10px; border-radius:18px;
  background:var(--glass); -webkit-backdrop-filter:blur(22px) saturate(170%); backdrop-filter:blur(22px) saturate(170%);
  border:1px solid var(--border); box-shadow:var(--shadow-sm)}
.brand{display:flex; align-items:center; gap:9px; padding:0 6px; flex:0 0 auto; font-size:14px; font-weight:800; letter-spacing:-.01em; white-space:nowrap}
.brand-logo{width:26px;height:26px; border-radius:9px; background:var(--grad); display:grid; place-items:center; color:var(--on-accent); box-shadow:0 8px 20px -8px oklch(68% .18 140/.6); flex:0 0 auto}
.brand-logo svg{width:14px;height:14px}
.search-bar{flex:1 1 auto; min-width:0; max-width:480px; height:40px; display:flex; align-items:center; gap:9px; padding:0 12px; margin:0 auto;
  border-radius:13px; border:1px solid var(--border); background:var(--surface); color:var(--faint); cursor:pointer; transition:border-color .2s, background .2s}
.search-bar:hover{border-color:var(--border-strong); background:var(--surface-strong)}
.search-bar svg{width:15px;height:15px; flex:0 0 auto}
.search-bar input{flex:1; border:0; outline:0; font-size:13px; background:transparent; color:var(--fg)}
.search-bar input::placeholder{color:var(--faint)}
.search-bar .kbd{font-family:var(--font-mono); font-size:11px; padding:2px 6px; border-radius:6px; background:var(--hover); border:1px solid var(--border); color:var(--muted); flex:0 0 auto}
.controls{flex:0 0 auto; display:flex; align-items:center; gap:6px}
.cbtn{width:40px;height:40px; border-radius:12px; display:grid; place-items:center; color:var(--muted); transition:background .2s, color .2s; position:relative}
.cbtn:hover{background:var(--hover); color:var(--fg)}
.cbtn svg{width:18px;height:18px}
.cbtn .ndot{position:absolute; top:9px; right:10px; width:7px; height:7px; border-radius:50%; background:var(--accent); border:2px solid var(--glass-bright)}
.user{display:flex; align-items:center; gap:8px; padding:0 6px 0 10px; cursor:pointer; position:relative}
.user-av{width:36px;height:36px; border-radius:50%; background:var(--grad); display:grid; place-items:center; color:var(--on-accent); font-size:13px; font-weight:800; flex:0 0 auto}
.user-drop{position:absolute; top:calc(100% + 8px); right:0; width:240px; background:var(--surface-strong); -webkit-backdrop-filter:blur(30px) saturate(170%); backdrop-filter:blur(30px) saturate(170%);
  border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow); padding:8px; opacity:0; pointer-events:none; transform:translateY(-6px) scale(.98); transition:opacity .2s, transform .3s ease; z-index:80}
.user-drop.open{opacity:1; pointer-events:auto; transform:none}
.user-drop .dr-item{display:flex; align-items:center; gap:10px; width:100%; height:42px; padding:0 12px; border-radius:12px; color:var(--fg); font-size:13.5px; font-weight:600; text-align:left; transition:background .18s}
.user-drop .dr-item:hover{background:var(--hover)}
.user-drop .dr-item.danger{color:var(--danger)}
.user-drop .dr-item svg{width:16px;height:16px; color:var(--muted); flex:0 0 auto}
.dr-head{display:flex; align-items:center; gap:10px; padding:8px 10px 12px; border-bottom:1px solid var(--border); margin-bottom:6px; font-size:14px; font-weight:700}

/* ── sidebar ── */
#sidebar{position:fixed; top:76px; left:14px; bottom:14px; width:var(--sb-w); z-index:50; display:flex; flex-direction:column;
  padding:12px 10px; border-radius:var(--r-lg); background:var(--glass); -webkit-backdrop-filter:blur(24px) saturate(170%); backdrop-filter:blur(24px) saturate(170%);
  border:1px solid var(--border); overflow:hidden; transition:width .35s ease, transform .35s ease}
.sb-scroll{display:flex; flex-direction:column; gap:2px; height:100%; overflow-y:auto; overflow-x:hidden; scrollbar-width:none}
.sb-scroll::-webkit-scrollbar{display:none}
.sec{padding-top:12px}
.sec-title{display:flex; align-items:center; height:22px; padding:0 10px; font-family:var(--font-mono); font-size:10.5px; font-weight:700; letter-spacing:.12em; color:var(--faint); white-space:nowrap; overflow:hidden; cursor:pointer; user-select:none}
.s-item{display:flex; align-items:center; gap:10px; width:100%; height:40px; padding:0 10px; border-radius:12px; color:var(--muted); font-size:13px; font-weight:600;
  white-space:nowrap; overflow:hidden; text-align:left; text-decoration:none; transition:background .18s, color .18s}
.s-item:hover{background:var(--hover); color:var(--fg)}
.s-item.active{background:var(--accent-soft); color:var(--accent)}
.s-item svg{width:16px;height:16px; flex:0 0 auto; color:var(--faint)}
.s-item.active svg{color:var(--accent)}
.s-item .sl{overflow:hidden; text-overflow:ellipsis}
.s-item.sub{padding-left:32px; font-size:12.5px; height:36px}
.s-item .sbad{margin-left:auto; font-family:var(--font-mono); font-size:10.5px; font-weight:700; color:var(--faint); background:var(--hover); border-radius:99px; padding:2px 7px; flex:0 0 auto}
.s-item.active .sbad{color:var(--accent); background:var(--accent-soft)}
.db-entry{display:flex; align-items:center; justify-content:center; gap:8px; height:42px; margin-bottom:8px; border-radius:14px; background:var(--grad); color:var(--on-accent);
  font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 10px 24px -10px oklch(68% .18 140/.6); transition:transform .2s ease, box-shadow .2s}
.db-entry:hover{transform:translateY(-1px)}
.db-entry svg{width:15px;height:15px; flex:0 0 auto}
.sb-foot{margin-top:auto; padding-top:8px; border-top:1px solid var(--border)}
.sb-usr{display:flex; align-items:center; gap:8px; padding:8px 8px 0; font-size:12px; color:var(--muted)}
.sb-usr .role{font-family:var(--font-mono); font-size:10px; font-weight:700; background:var(--hover); border-radius:99px; padding:2px 8px; color:var(--faint); margin-left:auto}

/* ── main ── */
main{margin-left:calc(var(--sb-w) + 26px); margin-right:14px; padding-top:96px; padding-bottom:60px; position:relative; z-index:10; min-width:0; max-width:1240px}
main h1{font-size:28px; font-weight:800; letter-spacing:-.02em; margin-bottom:6px}
main .sub{color:var(--muted); font-size:14px; margin-bottom:28px; max-width:560px; line-height:1.6}

/* ── cards / forms / tables ── */
.card{background:var(--surface); -webkit-backdrop-filter:blur(20px) saturate(160%); backdrop-filter:blur(20px) saturate(160%); border:1px solid var(--border); border-radius:var(--r-md); padding:22px; box-shadow:var(--shadow-sm); margin-bottom:18px}
.card h2{font-size:17px; font-weight:800; margin-bottom:16px}
.fld{display:flex; flex-direction:column; gap:6px; margin-bottom:14px}
.fld label{font-size:12.5px; font-weight:700; color:var(--muted)}
.fld label .hint{font-weight:400; color:var(--faint); font-size:11.5px}
.inp{height:40px; padding:0 12px; border-radius:12px; border:1px solid var(--border); background:var(--surface-strong); color:var(--fg); font-size:13.5px; outline:none; transition:border-color .2s, box-shadow .2s; width:100%}
.inp:focus{border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft)}
textarea.inp{height:auto; padding:10px 12px; resize:vertical; line-height:1.6}
select.inp{appearance:none; background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%); background-position:calc(100% - 18px) 17px,calc(100% - 13px) 17px; background-size:5px 5px; background-repeat:no-repeat; padding-right:34px}
.fld-row{display:grid; grid-template-columns:1fr 1fr; gap:14px}
@media(max-width:768px){.fld-row{grid-template-columns:1fr}}
.btn{display:inline-flex; align-items:center; justify-content:center; gap:7px; height:40px; padding:0 18px; border-radius:13px; font-size:14px; font-weight:700; border:1px solid transparent; cursor:pointer; text-decoration:none; transition:transform .2s ease, box-shadow .25s, background .2s; white-space:nowrap}
.btn.primary{background:var(--grad); color:var(--on-accent); box-shadow:0 10px 24px -10px oklch(68% .18 140/.5)}
.btn.primary:hover{transform:translateY(-1px)}
.btn.ghost{background:var(--surface-strong); border-color:var(--border); color:var(--fg)}
.btn.ghost:hover{background:var(--hover-strong); border-color:var(--border-strong)}
.btn.danger{background:var(--danger-soft); color:var(--danger)}
.btn.danger:hover{background:var(--danger)}
.btn.sm{height:34px; padding:0 12px; font-size:13px; border-radius:11px}
.btn.sm svg{width:14px;height:14px}
.btn:disabled{cursor:default; opacity:.5}
.msg{padding:12px 16px; border-radius:12px; font-size:13.5px; margin-bottom:14px}
.msg-success{background:var(--ok-soft); color:var(--ok)}
.msg-error{background:var(--danger-soft); color:var(--danger)}
.msg-info{background:var(--accent-soft); color:var(--accent)}
.pill{display:inline-flex; align-items:center; gap:6px; height:24px; padding:0 10px; border-radius:99px; font-size:12px; font-weight:700}
.pill.ok{background:var(--ok-soft); color:var(--ok)}
.pill.err{background:var(--danger-soft); color:var(--danger)}
.pill.gray{background:var(--hover); color:var(--muted)}
table{width:100%; border-collapse:collapse; font-size:13.5px}
table th{text-align:left; padding:10px 14px; font-family:var(--font-mono); font-size:10.5px; font-weight:700; letter-spacing:.08em; color:var(--faint); border-bottom:1px solid var(--border-strong)}
table td{padding:10px 14px; border-bottom:1px solid var(--border)}
table tr:hover td{background:var(--hover)}
.tabs{display:flex; gap:4px; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:0}
.tabs a{padding:10px 18px; font-size:13.5px; font-weight:600; color:var(--muted); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .15s}
.tabs a:hover{color:var(--fg)}
.tabs a.active{color:var(--accent); border-bottom-color:var(--accent)}
.stats{display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:20px}
.stat-card{background:var(--surface); border:1px solid var(--border); border-radius:var(--r-md); padding:18px; box-shadow:var(--shadow-sm)}
.stat-card .num{font-size:30px; font-weight:800; font-family:var(--font-mono)}
.stat-card .num{background:var(--grad); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent}
.stat-card .label{font-size:12.5px; color:var(--muted); margin-top:4px}
.empty{text-align:center; padding:40px; color:var(--faint); font-size:13.5px}
.tag{display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:99px; background:var(--hover); font-size:12px; font-weight:600; color:var(--muted)}
.tag .rem{cursor:pointer; color:var(--faint); font-size:15px; line-height:1}
.tag .rem:hover{color:var(--danger)}
code{font-family:var(--font-mono); font-size:12.5px; background:var(--hover); padding:2px 6px; border-radius:5px}
.pagination{display:flex; gap:6px; align-items:center; padding:12px 0; flex-wrap:wrap}
.pagination a,.pagination span{padding:6px 13px; border-radius:10px; font-size:13px; text-decoration:none; color:var(--muted); border:1px solid var(--border); transition:all .15s}
.pagination a:hover{border-color:var(--accent); color:var(--fg)}
.pagination .active{background:var(--accent); border-color:var(--accent); color:var(--on-accent); font-weight:700}
.pagination .disabled{opacity:.4; pointer-events:none}
.pagination .info{font-size:13px; color:var(--faint); border:none; padding:6px 0}
.ml-auto{margin-left:auto}.flex{display:flex}.items-center{align-items:center}.gap-2{gap:8px}.gap-4{gap:16px}.mb-4{margin-bottom:16px}.mt-4{margin-top:16px}.mt-6{margin-top:24px}.text-sm{font-size:13px}.text-muted{color:var(--muted)}

/* ── login page ── */
.login-page{display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg)}
.login-box{background:var(--surface); border:1px solid var(--border); border-radius:var(--r-lg); padding:44px; width:420px; max-width:90vw; box-shadow:var(--shadow); text-align:center}
.login-box h1{font-size:26px; font-weight:800; margin-bottom:6px; letter-spacing:-.02em}
.login-box .sub{color:var(--muted); font-size:13.5px; margin-bottom:28px}
.login-box .fld{text-align:left}

/* ── mobile ── */
@media(max-width:840px){
  main{margin:0 14px; padding-top:88px}
  #sidebar{transform:translateX(-110%); transition:transform .35s ease}
  #sidebar.open{transform:translateX(0)}
  .stats{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<?php }

function admin_sidebar(string $current): void {
    $role = $_SESSION['admin_role'] ?? '';
    $name = $_SESSION['admin_name'] ?? '';
    $roleLabels = ['admin' => '超管', 'marketing' => '市场', 'sales' => '销售'];
    $roleLabel = $roleLabels[$role] ?? $role;
?>
<div class="sidebar">
  <div class="brand">
    <span class="dot">F</span>
    <span>OpenFlow XMP</span>
  </div>

  <?php if (has_perm('dashboard')): ?>
  <a href="dashboard.php" class="dash-entry <?=$current==='dashboard'?'active':''?>" style="display:flex;align-items:center;gap:10px;margin:0 12px 12px;padding:11px 12px;border-radius:10px;background:linear-gradient(135deg,#1e1e1e,#2b5f7e);color:#fff;font-weight:600;font-size:14px;text-decoration:none;transition:.15s">
    <span>🚀</span> 经营驾驶舱
    <span style="margin-left:auto;font-size:10px;background:rgba(221,255,14,.2);color:#ddff0e;padding:2px 8px;border-radius:999px">大屏</span>
  </a>
  <?php endif; ?>

  <?php if (has_perm('flow')): ?>
  <a href="flow.php" class="dash-entry <?=$current==='flow'?'active':''?>" style="display:flex;align-items:center;gap:10px;margin:0 12px 12px;padding:11px 12px;border-radius:10px;background:linear-gradient(135deg,#0e5a3a,#16a34a);color:#fff;font-weight:600;font-size:14px;text-decoration:none;transition:.15s">
    <span>🔄</span> 运营主线
    <span style="margin-left:auto;font-size:10px;background:rgba(255,255,255,.2);color:#fff;padding:2px 8px;border-radius:999px">三流联动</span>
  </a>
  <?php endif; ?>

  <div class="global-search" id="globalSearchBox">
    <input type="text" id="globalSearchInput" placeholder="搜索文章、页面、课程…  (⌘K)">
    <div class="global-search-results" id="globalSearchResults"></div>
  </div>

  <!-- 模块切换器 -->
  <div class="module-switch" id="moduleSwitch">
    <button class="ms-btn" data-sec="CMS">CMS</button>
    <button class="ms-btn" data-sec="Knowledge">Knowledge</button>
    <button class="ms-btn" data-sec="Community">Community</button>
    <button class="ms-btn" data-sec="Growth">Growth</button>
    <button class="ms-btn" data-sec="Insight">Insight</button>
    <button class="ms-btn" data-sec="MKTG">MKTG</button>
    <button class="ms-btn" data-sec="Calendar">Calendar</button>
    <button class="ms-btn" data-sec="Survey">Survey</button>
    <button class="ms-btn" data-sec="Settings">Settings</button>
  </div>

  <?php if (has_perm('pages') || has_perm('articles') || has_perm('topics') || has_perm('landing') || has_perm('events') || has_perm('media') || has_perm('dam') || has_perm('navigation') || has_perm('site-builder')): ?>
  <div class="section" data-sec="CMS">CMS<span class="caret"></span></div>
  <?php if (has_perm('pages')): ?>
  <a href="pages-list.php" class="<?=$current==='pages-list'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
    页面管理
  </a>
  <a href="pages.php?page=index" class="<?=$current==='pages'?'active':''?>" style="padding-left:44px;font-size:13px">页面编辑器</a>
  <?php endif; ?>
  <?php if (has_perm('articles')): ?>
  <a href="articles.php" class="<?=$current==='articles'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
    文章管理
  </a>
  <a href="categories.php?type=article" class="<?=$current==='categories'?'active':''?>" style="padding-left:44px;font-size:13px">文章分类</a>
  <a href="tags.php" class="<?=$current==='tags'?'active':''?>" style="padding-left:44px;font-size:13px">标签管理</a>
  <a href="api-batch.php" class="<?=$current==='api-batch'?'active':''?>" style="padding-left:44px;font-size:13px">批量导入</a>
  <?php if (has_perm('ingest')): ?>
  <a href="ingest.php" class="<?=$current==='ingest'?'active':''?>" style="padding-left:44px;font-size:13px">外部导入</a>
  <?php endif; ?>
  <?php endif; ?>
  <?php if (has_perm('topics')): ?>
  <a href="topics" class="<?=$current==='topics'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a.5.5 0 01.5.5v3a.5.5 0 01-.5.5H7a1 1 0 01-1-1V4a1 1 0 011-1zm4 4h.01M16 3h5a.5.5 0 01.5.5v3a.5.5 0 01-.5.5h-5a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
    专题管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('events')): ?>
  <a href="events.php" class="<?=$current==='events'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    活动管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('landing')): ?>
  <a href="landing-pages.php" class="<?=$current==='landing'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 3h10m-10 4h6m-6 4h4"/></svg>
    聚合页
  </a>
  <?php endif; ?>
  <?php if (has_perm('dam')): ?>
  <a href="dam.php" class="<?=$current==='dam'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
    品牌资产
  </a>
  <?php endif; ?>
  <?php if (has_perm('media')): ?>
  <a href="media.php" class="<?=$current==='media'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    媒体管理
  </a>
  <a href="stock-photos.php" class="<?=$current==='stock-photos'?'active':''?>" style="padding-left:44px;font-size:13px">免费图库</a>
  <?php endif; ?>
  <?php if (has_perm('site-builder')): ?>
  <a href="site-builder.php" class="<?=$current==='site-builder'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 12h18M3 19h18M7 5v14m10-14v14"/></svg>
    站点结构
  </a>
  <?php endif; ?>
  <?php if (has_perm('navigation')): ?>
  <a href="navigation.php" class="<?=$current==='navigation'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
    增长导航
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (has_perm('courses') || has_perm('consultation') || has_perm('live') || has_perm('membership') || has_perm('marketplace') || has_perm('downloads') || has_perm('podcasts') || has_perm('subscription') || has_perm('shop-settings')): ?>
  <div class="section" data-sec="Knowledge">Knowledge<span class="caret"></span></div>
  <?php if (has_perm('courses')): ?>
  <a href="courses.php" class="<?=$current==='courses'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
    课程管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('consultation')): ?>
  <a href="consultation" class="<?=$current==='consultation'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 3h18v7H3V3z"/></svg>
    1v1 咨询
  </a>
  <?php endif; ?>
  <?php if (has_perm('live')): ?>
  <a href="live.php" class="<?=$current==='live'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
    直播管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('downloads')): ?>
  <a href="downloads" class="<?=$current==='downloads'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/></svg>
    资料下载
  </a>
  <?php endif; ?>
  <?php if (has_perm('podcasts')): ?>
  <a href="podcasts" class="<?=$current==='podcasts'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m14 0a7 7 0 01-14 0m14 0V9a7 7 0 00-14 0v2m14 0c0 3 2 4 2 4H5s2-1 2-4m7 8a4 4 0 11-8 0"/></svg>
    播客视频
  </a>
  <?php endif; ?>
  <?php if (has_perm('subscription')): ?>
  <a href="subscription.php" class="<?=$current==='subscription'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
    付费订阅
  </a>
  <?php endif; ?>
  <?php if (has_perm('membership')): ?>
  <a href="membership.php" class="<?=$current==='membership'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
    会员体系
  </a>
  <?php endif; ?>
  <?php if (has_perm('marketplace')): ?>
  <a href="marketplace" class="<?=$current==='marketplace'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l2-2h14l2 2m-18 0l2 12a2 2 0 002 2h10a2 2 0 002-2l2-12m-18 0h18m-12 3a4 4 0 006 0"/></svg>
    生态市场
  </a>
  <?php endif; ?>
  <?php if (has_perm('shop-settings')): ?>
  <a href="shop-settings.php" class="<?=$current==='shop-settings'?'active':''?>" style="padding-left:44px;font-size:13px">商城设置</a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (has_perm('community-mod') || has_perm('moderation')): ?>
  <div class="section" data-sec="Community">Community<span class="caret"></span></div>
  <a href="community-mod.php" class="<?=$current==='community-mod'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    社区管理
  </a>
  <?php if (has_perm('community-config')): ?>
  <a href="community-config.php" class="<?=$current==='community-config'?'active':''?>" style="padding-left:44px;font-size:13px">社区首页配置</a>
  <?php endif; ?>
  <?php if (has_perm('community-mod') || has_perm('moderation')): ?>
  <a href="comments.php" class="<?=$current==='comments'?'active':''?>" style="padding-left:44px;font-size:13px">评论 / 点评</a>
  <?php endif; ?>
  <?php if (has_perm('moderation')): ?>
  <a href="moderation.php" class="<?=$current==='moderation'?'active':''?>" style="padding-left:44px;font-size:13px">🛡️ 风控中心</a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (has_perm('seo') || has_perm('seo-tools') || has_perm('redirects') || has_perm('structured') || has_perm('geo') || has_perm('sentiment') || has_perm('seo-console') || has_perm('profiling')): ?>
  <div class="section" data-sec="Growth">Growth<span class="caret"></span></div>
  <?php if (has_perm('seo')): ?>
  <a href="seo.php" class="<?=$current==='seo'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 4a7 7 0 100 14 7 7 0 000-14z"/></svg>
    页面 SEO
  </a>
  <?php endif; ?>
  <?php if (has_perm('seo-tools')): ?>
  <a href="seo-tools.php" class="<?=$current==='seo-tools'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    SEO 工具
  </a>
  <a href="seo-batch.php" class="<?=$current==='seo-batch'?'active':''?>" style="padding-left:44px;font-size:13px">批量 SEO 策略</a>
  <?php endif; ?>
  <?php if (has_perm('redirects')): ?>
  <a href="redirects.php" class="<?=$current==='redirects'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
    301 重定向
  </a>
  <?php endif; ?>
  <?php if (has_perm('structured')): ?>
  <a href="structured-data.php" class="<?=$current==='structured'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
    结构化数据
  </a>
  <?php endif; ?>
  <?php if (has_perm('geo')): ?>
  <a href="geo.php" class="<?=$current==='geo'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h-4l1-1 1-4m6-4l2-2m-3.5 5L8.5 14m5.5 6l.75-3L22 9.5a2.12 2.12 0 00-3-3L12 13l-3 .75V17l5.5 5.5a2.12 2.12 0 003-3L14 17.75 17 14.75"/></svg>
    GEO 话题监控
  </a>
  <?php endif; ?>
  <?php if (has_perm('sentiment')): ?>
  <a href="sentiment.php" class="<?=$current==='sentiment'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M8 21V12m8 9V12M5 7h14M5 7l2-4h10l2 4M5 7h14"/></svg>
    舆情监测
  </a>
  <?php endif; ?>
  <?php if (has_perm('seo-console')): ?>
  <a href="seo-console.php" class="<?=$current==='seo-console'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
    SEO 站长工具
  </a>
  <?php endif; ?>
  
  
  <?php endif; ?>

  






  <?php if (has_perm('cdp') || has_perm('profiling') || has_perm('analytics') || has_perm('insights') || has_perm('tracking') || has_perm('abtests')): ?>
  <div class="section" data-sec="Insight">Insight<span class="caret"></span></div>
<?php if (has_perm('cdp')): ?>
  <a href="cdp.php" class="<?=$current==='cdp'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-6.13a4 4 0 11-8 0 4 4 0 018 0zm12 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    客户数据平台
  </a>
  <?php endif; ?>
<?php if (has_perm('profiling')): ?>
  <a href="profiling.php" class="<?=$current==='profiling'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-6.13a4 4 0 11-8 0 4 4 0 018 0zm12 6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    用户画像
  </a>
  <?php endif; ?>
<?php if (has_perm('analytics')): ?>
  <a href="analytics.php" class="<?=$current==='analytics'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13l4-4 4 4 5-6 5 5M3 21h18M5 21v-6m6 6v-6m6 6v-4"/></svg>
    运营分析
  </a>
  <?php endif; ?>
<?php if (has_perm('insights')): ?>
  <a href="insights.php" class="<?=$current==='insights'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13l4-4 4 4 5-6 5 5M3 21h18M5 21v-6m6 6v-6m6 6v-4"/></svg>
    营销洞察
  </a>
  <?php endif; ?>
<?php if (has_perm('tracking')): ?>
  <a href="tracking.php" class="<?=$current==='tracking'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
    行为追踪
  </a>
  <?php endif; ?>
<?php if (has_perm('abtests')): ?>
  <a href="abtests.php" class="<?=$current==='abtests'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M7 15h2m-2 4h2m6-8h2m-2 4h2"/></svg>
    A/B 测试
  </a>
  <?php endif; ?>
  <?php endif; ?>

<?php if (has_perm('email') || has_perm('channels') || has_perm('social') || has_perm('conversion') || has_perm('campaigns') || has_perm('sms') || has_perm('wechat-mp') || has_perm('leads') || has_perm('crm') || has_perm('forms') || has_perm('submissions') || has_perm('qr') || has_perm('utm-builder') || has_perm('scripts') || has_perm('ma-sync') || has_perm('automation') || has_perm('canvas')): ?>
  <div class="section" data-sec="MKTG">MKTG<span class="caret"></span></div>
  <?php if (has_perm('wechat-mp')): ?>
  <a href="wechat-mp.php" class="<?=$current==='wechat-mp'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    WeChat 微信
  </a>
  <?php endif; ?>
  <?php if (has_perm('leads')): ?>
  <a href="leads.php" class="<?=$current==='leads'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
    线索管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('crm')): ?>
  <a href="crm.php" class="<?=$current==='crm'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10a1.5 1.5 0 113 0v4a1.5 1.5 0 01-3 0v-4zm6 0a1.5 1.5 0 113 0v4a1.5 1.5 0 01-3 0v-4z"/></svg>
    CRM 线索
  </a>
  <?php endif; ?>
  <?php if (has_perm('forms')): ?>
  <a href="forms.php" class="<?=$current==='forms'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
    表单管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('submissions')): ?>
  <a href="submissions.php" class="<?=$current==='submissions'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    提交记录
  </a>
  <?php endif; ?>
  <?php if (has_perm('campaigns')): ?>
  <a href="campaigns.php" class="<?=$current==='campaigns'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
    Campaign
  </a>
  <?php endif; ?>
  <?php if (has_perm('channels')): ?>
  <a href="channels.php" class="<?=$current==='channels'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
    分发渠道
  </a>
  <?php endif; ?>
  <?php if (has_perm('social')): ?>
  <a href="social.php" class="<?=$current==='social'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    社交媒体
  </a>
  <?php endif; ?>
  <?php if (has_perm('conversion')): ?>
  <a href="conversion.php" class="<?=$current==='conversion'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
    转化组件
  </a>
  <?php endif; ?>
  <?php if (has_perm('email')): ?>
  <a href="email.php" class="<?=$current==='email'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    邮件营销
  </a>
  <?php endif; ?>
  <?php if (has_perm('automation')): ?>
  <a href="automation.php" class="<?=$current==='automation'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    营销自动化
  </a>
  <?php endif; ?>
  <?php if (has_perm('canvas')): ?>
  <a href="canvas.php" class="<?=$current==='canvas'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    画布流程
  </a>
  <?php endif; ?>
  
  <?php if (has_perm('ma-sync')): ?>
  <a href="ma-sync.php" class="<?=$current==='ma-sync'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
    MA 融合同步
  </a>
  <?php endif; ?>
  <?php if (has_perm('sms')): ?>
  <a href="sms.php" class="<?=$current==='sms'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    短信管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('qr')): ?>
  <a href="qr.php" class="<?=$current==='qr'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h4v4h-4v-4zm4 0h4v4h-4v-4z"/></svg>
    二维码
  </a>
  <?php endif; ?>
  <?php if (has_perm('utm-builder')): ?>
  <a href="utm-builder.php" class="<?=$current==='utm-builder'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
    UTM 生成器
  </a>
  <?php endif; ?>
  <?php if (has_perm('scripts')): ?>
  <a href="scripts.php" class="<?=$current==='scripts'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h-4l1-1 1-4m6-4l2-2m-3.5 5L8.5 14m5.5 6l.75-3L22 9.5a2.12 2.12 0 00-3-3L12 13l-3 .75V17l5.5 5.5a2.12 2.12 0 003-3L14 17.75 17 14.75"/></svg>
    脚本 & 埋点
  </a>
  <?php endif; ?>
  
  
  
  <?php endif; ?>

  <?php if (has_perm('activity') || has_perm('export') || has_perm('settings') || has_perm('users') || has_perm('devops') || has_perm('themes') || has_perm('plugins') || has_perm('ai-config') || has_perm('tasks')): ?>
  <div class="section" data-sec="Calendar">Calendar<span class="caret"></span></div>
  <?php if (has_perm('tasks')): ?>
  <a href="tasks.php" class="<?=$current==='tasks'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
    任务分配
  </a>
  <a href="content-calendar.php" class="<?=$current==='content-calendar'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    内容日历
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (has_perm('survey') || has_perm('nps')): ?>
  <div class="section" data-sec="Survey">Survey<span class="caret"></span></div>
  <a href="survey" class="<?=$current==='survey'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
    问卷管理
  </a>
  <a href="survey-stats.php" class="<?=$current==='survey-stats'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13l4-4 4 4 5-6 5 5M3 21h18M5 21v-6m6 6v-6m6 6v-4"/></svg>
    统计查看
  </a>
  <a href="survey-org.php" class="<?=$current==='survey-org'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
    组织架构
  </a>
  <a href="survey-agent.php" class="<?=$current==='survey-agent'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    官方 Agent
  </a>
  <?php if (has_perm('nps')): ?>
  <a href="nps.php" class="<?=$current==='nps'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17h5l3-6 4 10 3-7h3"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 5l1 3h1l1-3h1l1 3h1l1-3"/></svg>
    NPS 调研
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (has_perm('activity') || has_perm('export') || has_perm('settings') || has_perm('users') || has_perm('devops') || has_perm('themes') || has_perm('plugins') || has_perm('ai-config') || has_perm('knowledge') || has_perm('messages') || has_perm('storage') || has_perm('reviews') || has_perm('approvals') || has_perm('notify-channels')): ?>
  <div class="section" data-sec="Settings">Settings<span class="caret"></span></div>
  <?php if (has_perm('settings')): ?>
  <a href="settings.php" class="<?=$current==='settings'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
    系统设置
  </a>
  <a href="devops.php" class="<?=$current==='devops'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
    运维工具
  </a>
  <a href="health-check.php" class="<?=$current==='health-check'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    健康检测
  </a>
  <?php if (has_perm('notify-channels')): ?>
  <a href="notify-channels.php" class="<?=$current==='notify-channels'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    通知渠道
  </a>
  <?php endif; ?>
  <?php if (has_perm('messages')): ?>
  <a href="messages.php" class="<?=$current==='messages'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    站内信
  </a>
  <?php endif; ?>
  <?php if (has_perm('storage')): ?>
  <a href="storage.php" class="<?=$current==='storage'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    存储与性能
  </a>
  <?php endif; ?>
  <?php endif; ?>
  <?php if (has_perm('reviews')): ?>
  <a href="reviews" class="<?=$current==='reviews'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    内容审核<?php $reviewPending = review_pending_count(); if ($reviewPending > 0): ?><span style="margin-left:auto;background:#dc2626;color:#fff;font-size:10px;padding:1px 6px;border-radius:999px"><?=$reviewPending?></span><?php endif; ?>
  </a>
  <?php if (has_perm('settings')): ?>
  <a href="review-settings.php" class="<?=$current==='review-settings'?'active':''?>" style="padding-left:44px;font-size:13px">审核规则</a>
  <?php endif; ?>
  <?php endif; ?>
  <?php if (has_perm('approvals')): ?>
  <a href="approvals.php" class="<?=$current==='approvals'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    审核中心
  </a>
  <?php endif; ?>
  <?php if (has_perm('ai-config')): ?>
  <a href="ai-config.php" class="<?=$current==='ai-config'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3"/></svg>
    AI Agent
  </a>
  <?php endif; ?>
  <?php if (has_perm('knowledge')): ?>
  <a href="knowledge.php" class="<?=$current==='knowledge'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
    知识库
  </a>
  <?php endif; ?>
  <?php if (has_perm('plugins')): ?>
  <a href="plugins.php" class="<?=$current==='plugins'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
    插件管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('settings')): ?>
  <a href="themes.php" class="<?=$current==='themes'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
    主题管理
  </a>
  <?php endif; ?>
  <?php if (has_perm('activity')): ?>
  <a href="activity.php" class="<?=$current==='activity'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    操作日志
  </a>
  <?php endif; ?>
  <?php if (has_perm('export')): ?>
  <a href="export.php" class="<?=$current==='export'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/></svg>
    数据导出
  </a>
  <?php endif; ?>
  <?php if (has_perm('users')): ?>
  <a href="users.php" class="<?=$current==='users'?'active':''?>">
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
    权限管理
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <?php PluginSystem::do_action('admin_sidebar_menu', $current); ?>

   <div style="border-top:1px solid var(--border);margin:8px 12px 0;padding:8px 0">
     <a href="/docs/FEATURES.md" target="_blank" style="display:flex;align-items:center;gap:8px;padding:6px 12px;border-radius:8px;font-size:12.5px;color:var(--faint);text-decoration:none" onmouseover="this.style.color='var(--muted)'" onmouseout="this.style.color='var(--faint)'">📖 项目文档</a>
   </div>

  <div class="user-info">
    <button class="theme-toggle" id="themeToggle" onclick="fcToggleTheme()" title="切换深色/浅色模式" style="background:none;border:1px solid var(--border);border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:15px;display:grid;place-items:center">🌙</button>
    <div class="notification-bell" onclick="toggleNotif(event)">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php $unreadCount = get_unread_count(); ?>
      <?php if ($unreadCount > 0): ?><span class="badge-dot" id="notifDot"></span><?php endif; ?>
    </div>
    <div class="notif-dropdown" id="notifDropdown">
      <div style="padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
        <span>通知</span>
        <a href="javascript:markNotifRead()" style="font-size:12px;color:var(--text-3);text-decoration:none">全部已读</a>
      </div>
      <?php foreach (get_notifications(10) as $nn): ?>
      <div class="notif-item" onclick="<?=htmlspecialchars($nn['link'] ? "location.href='{$nn['link']}'" : '')?>">
        <div><span class="tag <?=$nn['type']?>"><?=htmlspecialchars($nn['type'])?></span><span class="title"><?=htmlspecialchars($nn['title'])?></span></div>
        <div class="msg"><?=htmlspecialchars($nn['message'])?></div>
        <div class="time"><?=htmlspecialchars($nn['created_at'] ?? '')?></div>
      </div>
      <?php endforeach; ?>
      <?php if ($unreadCount === 0): ?><div class="notif-item"><div class="msg" style="text-align:center;padding:12px">暂无新通知</div></div><?php endif; ?>
    </div>
    <span class="role-badge"><?=htmlspecialchars($roleLabel)?></span>
    <span><?=htmlspecialchars($name)?></span>
    <a href="logout.php" style="margin-left:auto;color:#dc2626;padding:4px;font-size:13px;text-decoration:none">退出</a>
  </div>
</div>
<script>
function toggleNotif(e) { e.stopPropagation(); document.getElementById('notifDropdown').classList.toggle('show'); }
document.addEventListener('click', function() { document.getElementById('notifDropdown')?.classList.remove('show'); });
// ─── 主题切换 ───
function fcToggleTheme() {
  var html = document.documentElement;
  var dark = html.getAttribute('data-theme') === 'dark';
  html.setAttribute('data-theme', dark ? '' : 'dark');
  try { localStorage.setItem('fc_theme', dark ? '' : 'dark'); } catch (e) {}
  var btn = document.getElementById('themeToggle');
  if (btn) btn.textContent = dark ? '🌙' : '☀️';
}
(function() {
  var dark = document.documentElement.getAttribute('data-theme') === 'dark';
  var btn = document.getElementById('themeToggle');
  if (btn) btn.textContent = dark ? '☀️' : '🌙';
})();
function markNotifRead() {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/notifications.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    var dot = document.getElementById('notifDot'); if (dot) dot.style.display = 'none';
    document.getElementById('notifDropdown').innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3);font-size:14px">已全部标为已读</div>';
  };
  xhr.send(JSON.stringify({action: 'mark_read'}));
}
// ─── 全局搜索 ───
(function() {
  var input = document.getElementById('globalSearchInput');
  var results = document.getElementById('globalSearchResults');
  if (!input || !results) return;
  var timer = null;
  function doSearch() {
    var q = input.value.trim();
    if (q.length < 2) { results.style.display = 'none'; return; }
    fetch('../api/search.php?q=' + encodeURIComponent(q))
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.results.length) {
          results.innerHTML = '<div class="gs-empty">未找到相关内容</div>';
          results.style.display = 'block';
          return;
        }
        results.innerHTML = d.results.map(function(r) {
          return '<a class="gs-item" href="' + r.link + '">' +
            '<span class="gs-type">' + r.type + '</span>' +
            '<span class="gs-title">' + r.title + '</span>' +
            '<span style="font-size:11px;color:var(--text-3)">' + r.hint + '</span></a>';
        }).join('');
        results.style.display = 'block';
      });
  }
  input.addEventListener('input', function() {
    clearTimeout(timer);
    timer = setTimeout(doSearch, 250);
  });
  input.addEventListener('focus', function() { if (input.value.trim().length >= 2) doSearch(); });
  document.addEventListener('click', function(e) {
    var box = document.getElementById('globalSearchBox');
    if (box && !box.contains(e.target)) results.style.display = 'none';
  });
  // Cmd/Ctrl+K 唤起
  document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      input.focus();
      input.select();
    }
  });
})();
// ─── 模块切换器 ───
// 文件 → 分区映射（决定当前页面默认激活哪个模块）
var MS_CURRENT = <?=json_encode(basename($_SERVER['SCRIPT_NAME'] ?? ''))?>;
var MS_MAP = {
  'pages-list':'CMS','pages':'CMS','page-builder':'CMS','landing-pages':'CMS','articles':'CMS','article-edit':'CMS','ingest':'CMS','api-batch':'CMS','categories':'CMS','tags':'CMS','topics':'CMS','events':'CMS','media':'CMS','media-upload':'CMS','dam':'CMS','stock-photos':'CMS','navigation':'CMS','site-builder':'CMS','content-preview':'CMS','page-preview':'CMS',
  'courses':'Knowledge','course-edit':'Knowledge','downloads':'Knowledge','download-edit':'Knowledge','podcasts':'Knowledge','subscription':'Knowledge','shop-settings':'Knowledge',
  'community-mod':'Community','community-config':'Community','comments':'Community','moderation':'Community',
  'seo':'Growth','seo-tools':'Growth','seo-batch':'Growth','redirects':'Growth','structured-data':'Growth','geo':'Growth','sentiment':'Growth','seo-console':'Growth','health-check':'Growth','abtests':'Growth','abtests-stats':'Growth','insights':'Growth','analytics':'Growth','dashboard':'Growth',
  'crm':'MKTG','leads':'MKTG','forms':'MKTG','submissions':'MKTG','wechat-mp':'MKTG','email':'MKTG','channels':'MKTG','social':'MKTG','conversion':'MKTG','campaigns':'MKTG','sms':'MKTG','qr':'MKTG','utm-builder':'MKTG','scripts':'MKTG','automation':'MKTG','canvas':'MKTG','ma-sync':'MKTG',
  'tasks':'Calendar','content-calendar':'Calendar',
  'survey':'Survey','survey-stats':'Survey','survey-org':'Survey','survey-agent':'Survey','nps':'Survey',
  'tracking':'Insight','abtests':'Insight','insights':'Insight','analytics':'Insight','cdp':'Insight','profiling':'Insight','settings':'Settings','devops':'Settings','plugins':'Settings','themes':'Settings','ai-config':'Settings','knowledge':'Settings','users':'Settings','activity':'Settings','export':'Settings','notify-channels':'Settings','messages':'Settings','storage':'Settings','reviews':'Settings','review-settings':'Settings','approvals':'Settings','onboarding':'Settings'
};
document.addEventListener('DOMContentLoaded', function() {
  var secs = document.querySelectorAll('.sidebar .section[data-sec]');
  // 找到当前页面分区
  var cur = MS_MAP[MS_CURRENT] || 'CMS';
  var btns = document.querySelectorAll('.module-switch .ms-btn');
  function showModule(name) {
    // 高亮按钮
    btns.forEach(function(b) { b.classList.toggle('active', b.dataset.sec === name); });
    // 显示对应分区及其菜单项
    secs.forEach(function(sec) {
      var secName = sec.getAttribute('data-sec');
      var isTarget = (secName === name);
      sec.style.display = isTarget ? 'flex' : 'none';
      // 遍历 sec 之后的兄弟，直到下一个 section，控制显示
      var n = sec.nextElementSibling;
      while (n && !n.classList.contains('section')) {
        if (isTarget) n.style.display = '';
        else if (!n.classList.contains('brand') && !n.classList.contains('global-search') && !n.classList.contains('module-switch') && !n.classList.contains('dash-entry') && !n.classList.contains('user-info')) n.style.display = 'none';
        n = n.nextElementSibling;
      }
    });
    // 始终显示非分区元素
    ['.sidebar .brand','.sidebar .global-search','.sidebar .module-switch','.sidebar .user-info','.sidebar .dash-entry'].forEach(function(sel) {
      var el = document.querySelector(sel);
      if (el) el.style.display = '';
    });
    try { localStorage.setItem('fc_module', name); } catch(e) {}
  }
  // 恢复上次模块
  var saved = ''; try { saved = localStorage.getItem('fc_module') || ''; } catch(e) {}
  showModule(saved || cur);
  btns.forEach(function(b) {
    b.addEventListener('click', function() { showModule(b.dataset.sec); });
  });
});
// ─── 侧边栏分区折叠 ───
document.addEventListener('DOMContentLoaded', function() {
  var STORE = 'fc_sidebar_collapsed';
  var collapsed = {};
  try { collapsed = JSON.parse(localStorage.getItem(STORE) || '{}'); } catch (e) {}
  var sections = document.querySelectorAll('.sidebar .section');
  var i;
  for (i = 0; i < sections.length; i++) {
    (function(sec) {
      var name = sec.getAttribute('data-sec');
      var items = [];
      var n = sec.nextElementSibling;
      while (n && !n.classList.contains('section')) { items.push(n); n = n.nextElementSibling; }
      // 默认全部展开（不恢复历史折叠状态），用户可手动折叠
      sec.addEventListener('click', function() {
        var isCollapsed = sec.classList.toggle('collapsed');
        for (var k = 0; k < items.length; k++) items[k].style.display = isCollapsed ? 'none' : '';
        collapsed[name] = isCollapsed;
        try { localStorage.setItem(STORE, JSON.stringify(collapsed)); } catch (e) {}
      });
    })(sections[i]);
  }
});
// ─── 侧边栏分区拖拽排序 ───
document.addEventListener('DOMContentLoaded', function() {
  var sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;
  var ORDER_STORE = 'fc_sidebar_order';
  var sidebarInner = sidebar;

  // 将 sidebar 内的 section + 跟随 items 视为组，重新组织到一个容器
  function groupSections() {
    var groups = [];
    var secs = sidebarInner.querySelectorAll(':scope > .section');
    secs.forEach(function(sec) {
      var group = { sec: sec, items: [] };
      var n = sec.nextElementSibling;
      while (n && !n.classList.contains('section') && !n.classList.contains('user-info')) {
        group.items.push(n);
        n = n.nextElementSibling;
      }
      groups.push(group);
    });
    return groups;
  }

  // 应用保存的顺序
  function applyOrder() {
    var saved = [];
    try { saved = JSON.parse(localStorage.getItem(ORDER_STORE) || '[]'); } catch (e) {}
    if (!saved.length) return;
    var groups = groupSections();
    var byName = {};
    groups.forEach(function(g) { byName[g.sec.getAttribute('data-sec')] = g; });
    var ordered = [];
    saved.forEach(function(name) { if (byName[name]) { ordered.push(byName[name]); delete byName[name]; } });
    // 追加未保存的分区
    Object.keys(byName).forEach(function(n) { ordered.push(byName[n]); });
    // 重排 DOM（移到 sidebar 的 user-info 之前）
    var userInfo = sidebarInner.querySelector('.user-info');
    ordered.forEach(function(g) {
      sidebarInner.insertBefore(g.sec, userInfo || null);
      g.items.forEach(function(it) { sidebarInner.insertBefore(it, userInfo || null); });
    });
  }

  // 拖拽处理
  var dragSec = null;
  sidebarInner.addEventListener('dragstart', function(e) {
    var sec = e.target.closest('.section');
    if (!sec || e.target.closest('.caret')) return;
    dragSec = sec;
    sec.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.stopPropagation();
  });
  sidebarInner.addEventListener('dragend', function(e) {
    if (dragSec) { dragSec.classList.remove('dragging'); dragSec = null; }
  });
  sidebarInner.addEventListener('dragover', function(e) {
    var sec = e.target.closest('.section');
    if (!dragSec || !sec || sec === dragSec) return;
    e.preventDefault();
    var rect = sec.getBoundingClientRect();
    var after = (e.clientY - rect.top) > (rect.height / 2);
    var ref = after ? sec.nextElementSibling : sec;
    var userInfo = sidebarInner.querySelector('.user-info');
    // 移动整组
    moveGroupBefore(dragSec, after ? ref : ref, userInfo);
  });
  sidebarInner.addEventListener('drop', function(e) {
    e.preventDefault();
    if (!dragSec) return;
    saveOrder();
    dragSec.classList.remove('dragging');
    dragSec = null;
  });

  function moveGroupBefore(movingSec, refNode, userInfo) {
    if (!refNode) return;
    var groups = groupSections();
    var moving = null, ref = null;
    groups.forEach(function(g) {
      if (g.sec === movingSec) moving = g;
      if (refNode === g.sec || groups.some(function(x){ return x.sec === refNode && (x.sec===g.sec) }) ) {}
    });
    // 简化：按 refNode 的前后插入
    var movingGroup = null;
    groups.forEach(function(g){ if (g.sec === movingSec) movingGroup = g; });
    if (!movingGroup) return;
    // 先移除再插入
    movingGroup.items.forEach(function(it){ it.remove(); });
    movingGroup.sec.remove();
    if (refNode && refNode.parentNode) {
      sidebarInner.insertBefore(movingGroup.sec, refNode);
      movingGroup.items.forEach(function(it){ sidebarInner.insertBefore(it, refNode); });
    } else {
      sidebarInner.insertBefore(movingGroup.sec, userInfo || null);
      movingGroup.items.forEach(function(it){ sidebarInner.insertBefore(it, userInfo || null); });
    }
  }

  function saveOrder() {
    var groups = groupSections();
    var order = groups.map(function(g){ return g.sec.getAttribute('data-sec'); });
    try { localStorage.setItem(ORDER_STORE, JSON.stringify(order)); } catch (e) {}
  }

  applyOrder();
});
</script>
<?php }

function admin_footer(): void {
    // Toast notification support
    $flashType = '';
    $flashText = '';
    if (isset($_SESSION['_flash'])) {
        $f = $_SESSION['_flash'];
        $flashType = $f['type'];
        $flashText = $f['text'];
        unset($_SESSION['_flash']);
    }
    echo '<style>@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:none}}
    @keyframes toastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
    .fc-toast{position:fixed;right:22px;bottom:22px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
    .fc-toast .t-item{pointer-events:auto;min-width:280px;max-width:420px;padding:12px 18px;border-radius:12px;background:#1e1e1e;color:#fff;font-size:13.5px;line-height:1.6;box-shadow:0 12px 32px rgba(0,0,0,.25);animation:toastIn .25s;display:flex;align-items:flex-start;gap:10px}
    .fc-toast .t-item.success{background:#16a34a}
    .fc-toast .t-item.error{background:#dc2626}
    .fc-toast .t-item.warning{background:#d97706}
    .fc-toast .t-item .t-close{background:none;border:none;color:#fff;font-size:14px;cursor:pointer;margin-left:auto;opacity:.7;padding:0 2px}
    </style>';
?>
<div class="fc-toast" id="fcToastWrap"></div>
<script>
// ─── 全局 Toast ───
window.fcToast = function(msg, type) {
  var wrap = document.getElementById('fcToastWrap');
  if (!wrap) { alert(msg); return; }
  var el = document.createElement('div');
  el.className = 't-item ' + (type || 'info');
  el.innerHTML = '<span style="flex:1">' + msg.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span><button class="t-close" onclick="this.parentElement.remove()">✕</button>';
  wrap.appendChild(el);
  setTimeout(function() {
    el.style.transition = 'opacity .3s';
    el.style.opacity = '0';
    setTimeout(function() { el.remove(); }, 300);
  }, 3500);
};
<?php if ($flashType && $flashText): ?>
window.fcToast(<?=json_encode($flashText, JSON_UNESCAPED_UNICODE)?>, <?=json_encode($flashType, JSON_UNESCAPED_UNICODE)?>);
<?php endif; ?>
// ─── 全局表单增强：必填校验 + 错误高亮 + 防止误提交 ───
document.addEventListener('submit', function(e) {
  var form = e.target;
  // 跳过有特殊处理（有 onsubmit 返回 false 控制）的表单
  if (form.dataset.noEnhance) return;
  if (form.hasAttribute('onsubmit')) return;
  var invalid = null;
  form.querySelectorAll('input[required], textarea[required], select[required]').forEach(function(field) {
    if (field.disabled) return;
    if (!field.value.trim()) {
      if (!invalid) invalid = field;
      field.style.borderColor = '#dc2626';
      field.style.boxShadow = '0 0 0 3px rgba(220,38,38,.12)';
      // 提示
      var label = field.closest('.field');
      if (label) {
        var old = label.querySelector('.fc-field-error');
        if (old) old.remove();
        var tip = document.createElement('div');
        tip.className = 'fc-field-error';
        tip.style.cssText = 'color:#dc2626;font-size:12px;margin-top:4px';
        tip.textContent = '此项为必填';
        label.appendChild(tip);
      }
      var clear = function() {
        field.style.borderColor = '';
        field.style.boxShadow = '';
        var t = (field.closest('.field') || document).querySelector('.fc-field-error');
        if (t) t.remove();
      };
      field.addEventListener('input', clear, { once: true });
    }
  });
  if (invalid) {
    e.preventDefault();
    invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (window.fcToast) window.fcToast('请先填写必填项', 'warning');
    invalid.focus();
  }
});
</script>
<script>
// fcMarkErrors({title: '标题不能为空', cover: '请选择封面'})  → 标红对应字段并提示
window.fcMarkErrors = function(errors) {
  var hasError = false;
  Object.keys(errors || {}).forEach(function(name) {
    var input = document.querySelector('input[name="' + name + '"], textarea[name="' + name + '"], select[name="' + name + '"]');
    if (!input) return;
    input.style.borderColor = '#dc2626';
    input.style.boxShadow = '0 0 0 3px rgba(220,38,38,.12)';
    // 清除之前的错误提示
    var old = input.parentElement.querySelector('.fc-field-error');
    if (old) old.remove();
    var tip = document.createElement('div');
    tip.className = 'fc-field-error';
    tip.style.cssText = 'color:#dc2626;font-size:12px;margin-top:4px';
    tip.textContent = errors[name];
    input.parentElement.appendChild(tip);
    input.addEventListener('input', function() {
      input.style.borderColor = '';
      input.style.boxShadow = '';
      var t = input.parentElement.querySelector('.fc-field-error');
      if (t) t.remove();
    }, { once: true });
    hasError = true;
    if (input.scrollIntoView) { input.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  });
  return hasError;
};
</script>
<!-- ═══ 全局 AI 小助手 ═══ -->
<link href="https://cdn.jsdelivr.net/npm/@fontsource/jetbrains-mono@5.0.3/index.min.css" rel="stylesheet" media="print" onload="this.media='all'">
<style>
  :root{
    --h-head-grad:linear-gradient(135deg,#1a1625,#2b5f7e);
    --h-head-text:#fff;
    --h-body-bg:#faf9f4;
    --h-user-bg:#1a1625;
    --h-user-text:#fff;
    --h-bot-bg:#fff;
    --h-bot-border:#e2dfd2;
    --h-accent:linear-gradient(135deg,#ddff0e,#86efac);
    --h-accent-text:#1e1e1e;
    --h-btn-bg:#e2dfd2;
    --h-btn-text:#5b5b52;
    --h-suggest-hover:#ddff0e;
    --h-focus:#2b5f7e;
  }
  /* 主题：黑夜 */
  [data-helper-theme="dark"]{
    --h-head-grad:linear-gradient(135deg,#0f0f13,#1f2430);
    --h-head-text:#e8e8ee;
    --h-body-bg:#16161c;
    --h-user-bg:#3d3d55;
    --h-user-text:#fff;
    --h-bot-bg:#232330;
    --h-bot-border:#353545;
    --h-accent:linear-gradient(135deg,#a78bfa,#7dd3fc);
    --h-accent-text:#101018;
    --h-btn-bg:#353545;
    --h-btn-text:#b5b5c2;
    --h-suggest-hover:#7dd3fc;
    --h-focus:#a78bfa;
  }
  /* 主题：清新 */
  [data-helper-theme="fresh"]{
    --h-head-grad:linear-gradient(135deg,#065f46,#059669);
    --h-head-text:#f0fdf4;
    --h-body-bg:#f0fdf4;
    --h-user-bg:#059669;
    --h-user-text:#fff;
    --h-bot-bg:#fff;
    --h-bot-border:#a7f3d0;
    --h-accent:linear-gradient(135deg,#34d399,#a7f3d0);
    --h-accent-text:#064e3b;
    --h-btn-bg:#d1fae5;
    --h-btn-text:#065f46;
    --h-suggest-hover:#34d399;
    --h-focus:#059669;
  }
  /* 主题：玉石 */
  [data-helper-theme="jade"]{
    --h-head-grad:linear-gradient(135deg,#1e3a5f,#2e6b4f);
    --h-head-text:#eef6f0;
    --h-body-bg:#f2f7f3;
    --h-user-bg:#2e6b4f;
    --h-user-text:#fff;
    --h-bot-bg:#fff;
    --h-bot-border:#cfe3d5;
    --h-accent:linear-gradient(135deg,#86efac,#7dd3fc);
    --h-accent-text:#1a2e24;
    --h-btn-bg:#e0efe5;
    --h-btn-text:#2e6b4f;
    --h-suggest-hover:#86efac;
    --h-focus:#2e6b4f;
  }
  .fc-helper-fab{position:fixed;right:22px;bottom:22px;width:60px;height:60px;border-radius:50%;border:none;cursor:pointer;z-index:9990;background:linear-gradient(135deg,#ddff0e,#86efac 60%,#7dd3fc);box-shadow:0 8px 24px rgba(30,30,30,.28);display:grid;place-items:center;transition:transform .2s,box-shadow .2s;padding:0}
  .fc-helper-fab:hover{transform:scale(1.08);box-shadow:0 12px 32px rgba(30,30,30,.34)}
  .fc-helper-fab img{width:42px;height:42px;border-radius:50%;object-fit:cover}
  .fc-helper-fab .pulse{position:absolute;inset:0;border-radius:50%;border:3px solid #ddff0e;animation:fcPulse 2s infinite}
  @keyframes fcPulse{0%{transform:scale(1);opacity:.7}70%{transform:scale(1.35);opacity:0}100%{opacity:0}}
  .fc-helper-window{position:fixed;right:22px;bottom:94px;width:380px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 120px);background:var(--h-bot-bg);border-radius:18px;box-shadow:0 24px 64px rgba(30,30,30,.3);z-index:9991;display:none;flex-direction:column;overflow:hidden;border:1px solid var(--h-bot-border)}
  .fc-helper-window.open{display:flex}
  .fc-helper-head{background:var(--h-head-grad);color:var(--h-head-text);padding:14px 18px;display:flex;align-items:center;gap:12px}
  .fc-helper-head img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--h-suggest-hover)}
  .fc-helper-head .name{font-weight:700;font-size:15px}
  .fc-helper-head .status{font-size:11px;opacity:.75}
  .fc-helper-head .close{margin-left:auto;background:none;border:none;color:var(--h-head-text);font-size:20px;cursor:pointer;opacity:.7}
  .fc-helper-head .close:hover{opacity:1}
  .fc-helper-head .theme-btn{background:none;border:1px solid rgba(255,255,255,.3);border-radius:8px;color:var(--h-head-text);font-size:13px;cursor:pointer;padding:3px 8px;opacity:.8;margin-left:6px}
  .fc-helper-head .theme-btn:hover{opacity:1}
  .fc-helper-body{flex:1;overflow-y:auto;padding:16px;background:var(--h-body-bg);display:flex;flex-direction:column;gap:10px}
  .fc-msg{max-width:82%;padding:10px 14px;border-radius:14px;font-size:13.5px;line-height:1.65;white-space:pre-wrap;word-break:break-word}
  .fc-msg.user{align-self:flex-end;background:var(--h-user-bg);color:var(--h-user-text);border-bottom-right-radius:4px}
  .fc-msg.bot{align-self:flex-start;background:var(--h-bot-bg);border:1px solid var(--h-bot-border);border-bottom-left-radius:4px}
  .fc-msg.bot .thinking{color:#9a94ac;font-style:italic;font-size:12px}
  .fc-helper-input{display:flex;gap:8px;padding:12px;border-top:1px solid var(--h-bot-border);background:var(--h-bot-bg)}
  .fc-helper-input textarea{flex:1;resize:none;border:1.5px solid var(--h-bot-border);border-radius:10px;padding:9px 12px;font-size:13.5px;font-family:inherit;outline:none;max-height:90px;min-height:40px;background:var(--h-bot-bg);color:inherit}
  .fc-helper-input textarea:focus{border-color:var(--h-focus)}
  .fc-helper-input button{background:var(--h-accent);color:var(--h-accent-text);border:none;border-radius:10px;padding:0 16px;font-weight:700;cursor:pointer;font-size:13px}
  .fc-helper-input button:disabled{opacity:.5;cursor:not-allowed}
  .fc-helper-suggest{display:flex;flex-wrap:wrap;gap:6px;padding:10px 12px 0;background:var(--h-body-bg)}
  .fc-helper-suggest button{font-size:11.5px;padding:5px 11px;border-radius:999px;border:1px solid var(--h-btn-bg);background:var(--h-bot-bg);color:var(--h-btn-text);cursor:pointer}
  .fc-helper-suggest button:hover{border-color:var(--h-suggest-hover);background:var(--h-suggest-hover);color:#1e1e1e}
  .fc-msg.bot .fc-act-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:8px;background:var(--h-accent);color:var(--h-accent-text);font-size:12.5px;font-weight:600;text-decoration:none}
  .fc-msg.bot .fc-act-btn:hover{filter:brightness(1.05)}
</style>
<div class="fc-helper-window" id="fcHelperWin">
  <div class="fc-helper-head">
    <img id="fcHelperAvatarWin" src="" alt="小福" style="display:none">
    <svg id="fcHelperAvatarSvgWin" viewBox="0 0 64 64" width="40" height="40" style="border-radius:50%;background:linear-gradient(160deg,#7dd3fc,#86efac 55%,#ddff0e);border:2px solid #ddff0e"><circle cx="32" cy="26" r="15" fill="#1e1e1e"/><path d="M12 50c3-12 9-17 20-17s17 5 20 17" fill="#1e1e1e"/><circle cx="26" cy="26" r="2.4" fill="#fff"/><circle cx="38" cy="26" r="2.4" fill="#fff"/><circle cx="26" cy="26" r="1" fill="#1e1e1e"/><circle cx="38" cy="26" r="1" fill="#1e1e1e"/><path d="M28 33c2.6 1.6 5.4 1.6 8 0" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/><circle cx="32" cy="14" r="2.6" fill="#fff" opacity=".9"/></svg>
    <div>
      <div class="name">小福 · 后台助手</div>
      <div class="status">🟢 在线 · 随时提问</div>
    </div>
    <button class="theme-btn" onclick="fcHelperCycleTheme()" title="切换皮肤">🎨</button>
    <button class="close" onclick="fcHelperReset()" title="清空会话" style="font-size:12px;margin-left:auto">↺</button>
    <button class="close" onclick="fcHelperToggle(false)">✕</button>
  </div>
  <div class="fc-helper-body" id="fcHelperBody"></div>
  <div class="fc-helper-suggest" id="fcHelperSuggest"></div>
  <div class="fc-helper-input">
    <textarea id="fcHelperInput" rows="1" placeholder="问我任何后台操作问题…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();fcHelperSend()}"></textarea>
    <button id="fcHelperBtn" onclick="fcHelperSend()">发送</button>
  </div>
</div>
<button class="fc-helper-fab" id="fcHelperFab" onclick="fcHelperToggle()" title="小福助手">
  <span class="pulse"></span>
  <img id="fcHelperAvatarImgFab" src="" alt="小福" style="display:none">
  <svg id="fcHelperAvatarSvgFab" viewBox="0 0 64 64" width="44" height="44" style="border-radius:50%"><circle cx="32" cy="26" r="15" fill="#1e1e1e"/><path d="M12 50c3-12 9-17 20-17s17 5 20 17" fill="#1e1e1e"/><circle cx="26" cy="26" r="2.4" fill="#fff"/><circle cx="38" cy="26" r="2.4" fill="#fff"/><circle cx="26" cy="26" r="1" fill="#1e1e1e"/><circle cx="38" cy="26" r="1" fill="#1e1e1e"/><path d="M28 33c2.6 1.6 5.4 1.6 8 0" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/><circle cx="32" cy="14" r="2.6" fill="#fff" opacity=".9"/></svg>
</button>
<script>
var FC_HELPER_AVATAR = '<?=htmlspecialchars(json_read(DATA_DIR . "/ai-config.json")['assistant_avatar'] ?? '', ENT_QUOTES)?>';
if (FC_HELPER_AVATAR) {
  ['fcHelperAvatarWin','fcHelperAvatarImgFab'].forEach(function(id) {
    var img = document.getElementById(id);
    if (img) { img.src = FC_HELPER_AVATAR; img.style.display = 'block'; }
  });
  var svg1 = document.getElementById('fcHelperAvatarSvgWin'), svg2 = document.getElementById('fcHelperAvatarSvgFab');
  if (svg1) svg1.style.display = 'none';
  if (svg2) svg2.style.display = 'none';
}
var FC_HELPER = {
  open: false,
  history: [],
  themes: ['default', 'dark', 'fresh', 'jade'],
  themeNames: { 'default': '默认', 'dark': '暗夜', 'fresh': '清新', 'jade': '玉石' },
  suggestions: ['怎么发布一篇文章？','公司知识库里有什么？','如何批量导入文章？','写个公众号标题','看看运营数据','怎么配置微信公众号？','如何添加一个表单？','SEO 标题怎么批量设置？','健康检测怎么用？','去管理线索','生态市场有什么技能？']
};
// 小助手皮肤
function fcHelperApplyTheme(t) {
  var win = document.getElementById('fcHelperWin');
  if (!t || !FC_HELPER.themes.indexOf(t)) return;
  win.setAttribute('data-helper-theme', t);
  try { localStorage.setItem('fc_helper_theme', t); } catch(e) {}
  var btn = win.querySelector('.theme-btn');
  if (btn) btn.title = '皮肤：' + (FC_HELPER.themeNames[t] || t);
}
function fcHelperCycleTheme() {
  var win = document.getElementById('fcHelperWin');
  var cur = win.getAttribute('data-helper-theme') || 'default';
  var i = FC_HELPER.themes.indexOf(cur);
  var next = FC_HELPER.themes[(i + 1) % FC_HELPER.themes.length];
  fcHelperApplyTheme(next);
  fcToast('小助手皮肤已切换为「' + (FC_HELPER.themeNames[next] || next) + '」');
}
// 初始化皮肤
(function(){
  try {
    var t = localStorage.getItem('fc_helper_theme') || 'default';
    fcHelperApplyTheme(t);
  } catch(e) {}
})();
function fcHelperReset() {
  if (!confirm('清空当前对话记录，开始新会话？')) return;
  fetch('../api/assistant.php?action=reset', { method: 'POST' }).catch(function() {});
  document.getElementById('fcHelperBody').innerHTML = '';
  FC_HELPER.open = true;
  fcHelperBoot();
}
function fcHelperToggle(force) {
  var win = document.getElementById('fcHelperWin');
  var open = (typeof force === 'boolean') ? force : !FC_HELPER.open;
  FC_HELPER.open = open;
  win.classList.toggle('open', open);
  try { localStorage.setItem('fc_helper_open', open ? '1' : '0'); } catch(e) {}
  if (open && !document.getElementById('fcHelperBody').innerHTML) fcHelperBoot();
  if (open) setTimeout(function(){ document.getElementById('fcHelperInput').focus(); }, 100);
}
// 记住上次打开状态（常驻体验）
(function(){
  try { if (localStorage.getItem('fc_helper_open') === '1') fcHelperToggle(true); } catch(e) {}
})();
function fcHelperBoot() {
  var body = document.getElementById('fcHelperBody');
  var greet = '嗨，我是「小福」✨ 后台小助手！\n\n有什么不会用的地方尽管问我，比如：\n• 怎么发布一篇文章\n• 如何配置微信公众号\n• 怎么批量导入 100 篇旧文章\n• 健康检测 / SEO 工具怎么用\n\n也可以让我帮你写标题、摘要或文案～';
  body.innerHTML = fcMsgHtml('bot', greet);
  renderSuggestions();
}
function fcMsgHtml(role, text) {
  return '<div class="fc-msg ' + role + '">' + fcEscape(text).replace(/\n/g, '<br>') + '</div>';
}
function fcEscape(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
function renderSuggestions() {
  var box = document.getElementById('fcHelperSuggest');
  box.innerHTML = FC_HELPER.suggestions.slice(0, 4).map(function(s) {
    return '<button onclick="fcHelperAsk(\'' + s.replace(/'/g,"\\'") + '\')">' + s + '</button>';
  }).join('');
}
function fcHelperAsk(q) {
  document.getElementById('fcHelperInput').value = q;
  fcHelperSend();
}
function fcHelperSend() {
  var input = document.getElementById('fcHelperInput');
  var msg = input.value.trim();
  if (!msg) return;
  var body = document.getElementById('fcHelperBody');
  body.insertAdjacentHTML('beforeend', fcMsgHtml('user', msg));
  body.scrollTop = body.scrollHeight;
  input.value = '';
  document.getElementById('fcHelperBtn').disabled = true;
  var thinking = '<div class="fc-msg bot" id="fcThinking"><span class="thinking">小福正在思考…</span></div>';
  body.insertAdjacentHTML('beforeend', thinking);
  body.scrollTop = body.scrollHeight;
  fetch('../api/assistant.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: msg })
  }).then(function(r) { return r.json(); }).then(function(d) {
    var t = document.getElementById('fcThinking');
    if (t) t.remove();
    if (d.ok) {
      var replyHtml = fcMsgHtml('bot', d.reply || '（没有回复）');
      if (d.actions && d.actions.length) {
        replyHtml = replyHtml.slice(0, -5) + '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">' +
          (d.actions || []).map(function(a) {
            return '<a href="' + fcEscape(a.url) + '" class="fc-act-btn">' + fcEscape(a.icon || '') + ' ' + fcEscape(a.label) + ' →</a>';
          }).join('') + '</div></div>';
      }
      if (d.sources && d.sources.length) {
        replyHtml = replyHtml.slice(0, -5) + '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #e2dfd2;font-size:11px;color:#9a94ac">📚 参考：' +
          d.sources.map(function(s) { return fcEscape(s.title); }).join(' · ') + '</div></div>';
      }
      if (d.skill && d.skill.title) {
        replyHtml = replyHtml.slice(0, -5) + '<div style="margin-top:8px;padding:6px 10px;border-radius:8px;background:rgba(221,255,14,.15);font-size:11px;color:#5b7a00">⚡ 已调用技能「' + fcEscape(d.skill.title) + '」辅助回答</div></div>';
      }
      body.insertAdjacentHTML('beforeend', replyHtml);
    } else {
      body.insertAdjacentHTML('beforeend', fcMsgHtml('bot', '😅 ' + (d.error || '出错了，稍后再试')));
    }
    body.scrollTop = body.scrollHeight;
    document.getElementById('fcHelperBtn').disabled = false;
    renderSuggestions();
  }).catch(function() {
    var t = document.getElementById('fcThinking');
    if (t) t.remove();
    body.insertAdjacentHTML('beforeend', fcMsgHtml('bot', '😅 网络异常，请检查 AI 供应商配置'));
    document.getElementById('fcHelperBtn').disabled = false;
  });
}
</script>
<!-- ═══ 全局命令面板 (Ctrl+K) ═══ -->
<style>
  .fc-palette{position:fixed;inset:0;z-index:9995;background:rgba(20,18,25,.45);backdrop-filter:blur(3px);display:none;align-items:flex-start;justify-content:center;padding-top:14vh}
  .fc-palette.open{display:flex}
  .fc-palette-box{width:620px;max-width:calc(100vw - 40px);background:var(--surface);border:1px solid var(--border-2);border-radius:16px;box-shadow:var(--shadow-lg);overflow:hidden;animation:fcPalIn .16s}
  @keyframes fcPalIn{from{opacity:0;transform:translateY(-8px) scale(.98)}to{opacity:1;transform:none}}
  .fc-palette input{width:100%;padding:18px 20px;font-size:16px;border:none;outline:none;background:transparent;color:var(--text);font-family:var(--font)}
  .fc-palette .pal-hint{padding:0 20px 10px;font-size:11px;color:var(--text-3);display:flex;gap:14px}
  .fc-palette .pal-hint kbd{background:var(--surface-2);border-radius:4px;padding:1px 5px;font-size:10px;font-family:var(--mono)}
  .fc-palette-list{max-height:52vh;overflow-y:auto;border-top:1px solid var(--border);padding:8px}
  .fc-palette-item{display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:10px;cursor:pointer;font-size:14px}
  .fc-palette-item .p-ic{font-size:18px;width:26px;text-align:center}
  .fc-palette-item .p-sec{font-size:11px;color:var(--text-3);margin-left:auto;white-space:nowrap;padding-left:12px}
  .fc-palette-item.sel{background:linear-gradient(135deg,rgba(221,255,14,.28),rgba(134,239,172,.18))}
  .fc-palette-empty{padding:28px;text-align:center;color:var(--text-3);font-size:13px}
  .fc-palette-grp{font-size:11px;color:var(--text-3);padding:10px 14px 4px;font-weight:600}
</style>
<div class="fc-palette" id="fcPalette">
  <div class="fc-palette-box">
    <input id="fcPaletteInput" placeholder="搜索功能…（Ctrl+K）" autocomplete="off">
    <div class="pal-hint"><span>⌨️ 输入跳转 · 方向键选择 · 回车打开</span><span style="margin-left:auto">ESC 关闭</span></div>
    <div class="fc-palette-list" id="fcPaletteList"></div>
  </div>
</div>
<script>
var FC_PALETTE_ITEMS = <?=json_encode(cp_items(), JSON_UNESCAPED_UNICODE)?>;
(function(){
  var box = document.getElementById('fcPalette');
  var input = document.getElementById('fcPaletteInput');
  var list = document.getElementById('fcPaletteList');
  var items = [], sel = -1, allItems = FC_PALETTE_ITEMS || [];
  function open(){ box.classList.add('open'); items = allItems.slice(0, 14); sel = -1; render(); input.value=''; input.focus(); }
  function close(){ box.classList.remove('open'); }
  function render(){
    var q = input.value.trim().toLowerCase();
    if (q) {
      items = allItems.filter(function(it){
        var hay = (it.label + ' ' + (it.keywords||'') + ' ' + it.section).toLowerCase();
        if (hay.indexOf(q) >= 0) return true;
        if (q.length >= 2) {
          var all = true;
          for (var i=0;i<q.length;i++){ if (hay.indexOf(q[i])<0){ all=false; break; } }
          return all;
        }
        return false;
      }).slice(0, 14);
    } else {
      items = allItems.slice(0, 14);
    }
    sel = items.length ? 0 : -1;
    var html = '', lastSec = '';
    for (var i=0;i<items.length;i++){
      var it = items[i];
      var sec = it.section || '';
      if (sec !== lastSec){ html += '<div class="fc-palette-grp">' + fcEscape(sec) + '</div>'; lastSec = sec; }
      html += '<div class="fc-palette-item" data-i="' + i + '" onclick="location.href=\'' + it.url + '\'">' +
        '<span class="p-ic">' + (it.icon||'') + '</span><span>' + fcEscape(it.label) + '</span>' +
        '<span class="p-sec">' + fcEscape(it.url) + '</span></div>';
    }
    list.innerHTML = html || '<div class="fc-palette-empty">没有找到匹配的功能</div>';
    var els = list.querySelectorAll('.fc-palette-item');
    if (els[sel]) els[sel].classList.add('sel');
  }
  document.addEventListener('keydown', function(e){
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k'){ e.preventDefault(); box.classList.contains('open') ? close() : open(); }
    if (!box.classList.contains('open')) return;
    if (e.key === 'Escape'){ e.preventDefault(); close(); return; }
    if (e.key === 'ArrowDown'){ e.preventDefault(); sel = Math.min(sel+1, items.length-1); mark(); return; }
    if (e.key === 'ArrowUp'){ e.preventDefault(); sel = Math.max(sel-1, 0); mark(); return; }
    if (e.key === 'Enter' && sel >= 0 && items[sel]){ location.href = items[sel].url; }
  });
  function mark(){
    var els = list.querySelectorAll('.fc-palette-item');
    els.forEach(function(el, i){ el.classList.toggle('sel', i===sel); });
  }
  input.addEventListener('input', render);
  box.addEventListener('click', function(e){ if (e.target === box) close(); });
})();
</script>
</body></html>
<?php }

function paginate(array $items, int $page = 1, int $perPage = 50): array {
    $total = count($items);
    $maxPage = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $maxPage));
    $offset = ($page - 1) * $perPage;
    return [
        'items' => array_slice($items, $offset, $perPage),
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'maxPage' => $maxPage,
        'offset' => $offset,
    ];
}

function pagination_html(array $pag, string $urlBase): string {
    $html = '<div class="pagination"><span class="info">共 ' . $pag['total'] . ' 条</span>';
    if ($pag['maxPage'] <= 1) return $html . '</div>';

    $prev = $pag['page'] > 1 ? '<a href="' . $urlBase . '&page=' . ($pag['page'] - 1) . '">‹ 上一页</a>' : '<span class="disabled">‹ 上一页</span>';
    $next = $pag['page'] < $pag['maxPage'] ? '<a href="' . $urlBase . '&page=' . ($pag['page'] + 1) . '">下一页 ›</a>' : '<span class="disabled">下一页 ›</span>';
    $html .= $prev;

    $start = max(1, $pag['page'] - 2);
    $end = min($pag['maxPage'], $pag['page'] + 2);
    if ($start > 1) $html .= '<a href="' . $urlBase . '&page=1">1</a>' . ($start > 2 ? '<span>…</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $pag['page'] ? '<span class="active">' . $i . '</span>' : '<a href="' . $urlBase . '&page=' . $i . '">' . $i . '</a>';
    }
    if ($end < $pag['maxPage']) $html .= ($end < $pag['maxPage'] - 1 ? '<span>…</span>' : '') . '<a href="' . $urlBase . '&page=' . $pag['maxPage'] . '">' . $pag['maxPage'] . '</a>';
    $html .= $next . '</div>';
    return $html;
}

function flash(string $type, string $text): void {
    $_SESSION['_flash'] = ['type' => $type, 'text' => $text];
}

// ─── Notifications ────────────────────────────
/**
 * 发送通知
 * @param string $type 类型
 * @param string $title 标题
 * @param string $message 内容
 * @param string $link 跳转链接
 * @param mixed $audience 受众：'all' 全部 / 角色数组 ['admin','marketing'] / 定向用户 'user:username' / 数组混排
 */
function notify(string $type, string $title, string $message, string $link = '', $audience = 'all'): void {
    $n = json_read(DATA_DIR . '/notifications.json');
    $n['unread'][] = [
        'id' => 'notif_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'type' => $type, // lead, submission, system, article, review
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'audience' => is_array($audience) ? $audience : [$audience],
        'created_at' => date('Y-m-d H:i:s'),
        'read' => false,
    ];
    // Keep max 200 notifications
    if (count($n['unread']) > 200) $n['unread'] = array_slice($n['unread'], -200);
    json_write(DATA_DIR . '/notifications.json', $n);

    // 外部渠道通知（企业微信/飞书/WhatsApp）
    static $channelsLoaded = false;
    if (!$channelsLoaded) {
        $channelsLib = __DIR__ . '/../lib/NotifyChannels.php';
        if (file_exists($channelsLib)) require_once $channelsLib;
        $channelsLoaded = true;
    }
    if (function_exists('notify_channels_send')) {
        notify_channels_send($title, $message, $link);
    }
}

// 当前用户是否可见某条通知
function notif_visible_to(array $nn): bool {
    $aud = $nn['audience'] ?? ['all'];
    if (in_array('all', $aud)) return true;
    $role = $_SESSION['admin_role'] ?? '';
    $user = $_SESSION['admin_user'] ?? '';
    foreach ($aud as $a) {
        if ($a === $role) return true;
        if (strpos($a, 'user:') === 0 && substr($a, 5) === $user) return true;
    }
    return false;
}

function get_notifications(int $limit = 10): array {
    $n = json_read(DATA_DIR . '/notifications.json');
    $all = array_reverse($n['unread'] ?? []);
    $mine = array_values(array_filter($all, 'notif_visible_to'));
    return array_slice($mine, 0, $limit);
}

function get_unread_count(): int {
    $n = json_read(DATA_DIR . '/notifications.json');
    return count(array_filter($n['unread'] ?? [], fn($nn) => !$nn['read'] && notif_visible_to($nn)));
}

function mark_notifications_read(): void {
    $n = json_read(DATA_DIR . '/notifications.json');
    foreach ($n['unread'] as &$nn) $nn['read'] = true;
    json_write(DATA_DIR . '/notifications.json', $n);
}

// ─── Activity Log ─────────────────────────────
function log_activity(string $action, string $target_type, string $target_id, string $details = ''): void {
    $logFile = DATA_DIR . '/activity.json';
    $log = json_read($logFile);
    $log[] = [
        'time' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['admin_user'] ?? 'system',
        'user_name' => $_SESSION['admin_name'] ?? 'System',
        'action' => $action,
        'target_type' => $target_type,
        'target_id' => $target_id,
        'details' => $details,
    ];
    if (count($log) > 500) $log = array_slice($log, -500);
    json_write($logFile, $log);
}
