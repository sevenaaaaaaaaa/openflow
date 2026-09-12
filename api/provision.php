<?php
/**
 * 自动装配 API（后台专用）
 *   POST action=brief_save {title,text}        投喂文本→知识库 + 存简介
 *   POST action=ingest_url {url}               抓取站点/文章
 *   POST action=ingest_file {file}             上传文本/Markdown
 *   POST action=generate [force]               生成装配计划（AI 读知识库+现状）
 *   POST action=apply {id} / skip {id}         接受（真实创建）/ 跳过
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/RateLimiter.php';
require_once __DIR__ . '/../lib/Provisioner.php';
require_login();
require_perm('settings');

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
try { RateLimiter::throttle('provision:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 40, 600); } catch (\Throwable $e) {}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));

switch ($action) {
    case 'brief_save': {
        $brief = prov_brief();
        $brief['title'] = mb_substr(trim((string)($input['title'] ?? $brief['title'] ?? '')), 0, 120);
        $brief['text'] = mb_substr(trim((string)($input['text'] ?? $brief['text'] ?? '')), 0, 40000);
        $brief['updated_at'] = date('Y-m-d H:i:s');
        prov_save_brief($brief);
        prov_ingest_text($brief['text'], $brief['title']);
        echo json_encode(['ok' => true, 'chars' => mb_strlen($brief['text'])], JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'ingest_url': {
        $r = prov_ingest_url((string)($input['url'] ?? ''));
        if ($r['ok']) { $b = prov_brief(); $b['urls'] = array_values(array_unique(array_merge((array)($b['urls'] ?? []), [(string)$input['url']]))); prov_save_brief($b); }
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'ingest_file': {
        if (empty($_FILES['file']['tmp_name'])) { echo json_encode(['ok' => false, 'error' => '未收到文件'], JSON_UNESCAPED_UNICODE); break; }
        $name = (string)($_FILES['file']['name'] ?? 'file');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'md', 'markdown', 'csv', 'html', 'htm', 'json'], true)) { echo json_encode(['ok' => false, 'error' => '仅支持文本/Markdown/CSV/HTML（PDF/DOCX 请先转文本）'], JSON_UNESCAPED_UNICODE); break; }
        $raw = (string)file_get_contents($_FILES['file']['tmp_name']);
        $text = in_array($ext, ['html', 'htm'], true) ? strip_tags($raw) : $raw;
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) < 20) { echo json_encode(['ok' => false, 'error' => '内容太短'], JSON_UNESCAPED_UNICODE); break; }
        prov_ingest_text(mb_substr($text, 0, 20000), $name);
        $b = prov_brief(); $b['files'] = array_values(array_unique(array_merge((array)($b['files'] ?? []), [$name]))); prov_save_brief($b);
        echo json_encode(['ok' => true, 'file' => $name, 'chars' => mb_strlen($text)], JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'generate': {
        $r = prov_generate(!empty($input['force']));
        if (empty($r['ok'])) { echo json_encode(['ok' => false, 'error' => $r['error'] ?? '生成失败'], JSON_UNESCAPED_UNICODE); break; }
        echo json_encode(['ok' => true, 'plan' => $r['plan'], 'cached' => !empty($r['cached'])], JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'apply': {
        echo json_encode(prov_apply((string)($input['id'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'skip': {
        echo json_encode(['ok' => prov_skip((string)($input['id'] ?? ''))], JSON_UNESCAPED_UNICODE);
        break;
    }
    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
}
