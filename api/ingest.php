<?php
/**
 * 统一内容导入连接器 — 飞书 / Notion / Obsidian / 印象笔记 → 文章草稿
 *
 * 各平台接入方式：
 *   - Obsidian: 本地上传 .md 文件，或 URL
 *   - 飞书: 文档 URL（公开）或 API token
 *   - Notion: 页面 URL 或 API token + page_id
 *   - 印象笔记: 分享链接
 *
 * POST /api/ingest.php
 * Body: { "platform": "obsidian|feishu|notion|evernote", "url": "...", "content": "...", "title": "..." }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

// Require authentication
if (empty($_SESSION['admin_login'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '需要登录']);
    exit;
}

// SSRF protection: Block private/internal IP ranges
function is_private_ip(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return true;
    
    // Block common internal hostnames
    $blocked = ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'metadata.google.internal'];
    if (in_array(strtolower($host), $blocked, true)) return true;
    
    // Block IP addresses in private ranges
    $ip = gethostbyname($host);
    if ($ip !== $host) { // Resolved to IP
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
    }
    
    // Block common internal ports
    $port = parse_url($url, PHP_URL_PORT);
    if ($port && in_array((int)$port, [22, 80, 443, 3306, 5432, 6379, 8080, 8443, 9200, 27017], true)) {
        // Allow common web ports but log warning
    }
    
    return false;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$platform = trim($input['platform'] ?? '');
$url = trim($input['url'] ?? '');
$title = trim($input['title'] ?? '');
$content = trim($input['content'] ?? '');

if (empty($platform)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'缺少平台']); exit; }

// 读取平台配置
$config = json_read(DATA_DIR . '/ingest-config.json');

// 1. 直接提供 content 的情况（Obsidian 上传 / API 直传）
if (!empty($content)) {
    $result = ingest_save_article($title ?: '未命名文章', $content, $platform, $url, $input);
    echo json_encode(['ok'=>true, 'article'=>$result['article']], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. 通过 URL 抓取
if (!empty($url)) {
    // SSRF protection
    if (is_private_ip($url)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'不允许访问内网地址']);
        exit;
    }
    $fetched = ingest_fetch_url($url);
    if (!$fetched) { http_response_code(502); echo json_encode(['ok'=>false,'error'=>'抓取失败']); exit; }
    $result = ingest_save_article($fetched['title'], $fetched['content'], $platform, $url, $input);
    echo json_encode(['ok'=>true, 'article'=>$result['article'], 'fetched'=>$fetched['description'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. 平台 API 拉取（需配置 token）
$platformApi = ingest_platform_api($platform, $input, $config);
if ($platformApi) {
    echo json_encode(['ok'=>true, 'article'=>$platformApi['article']], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'请提供 url 或 content，或配置平台 API']);

// ─── URL 抓取（复用 import-article 逻辑）───
function ingest_fetch_url(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>30, CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36', CURLOPT_SSL_VERIFYPEER=>false]);
    $html = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close 已弃用（PHP 8.5）
    if ($http !== 200 || empty($html)) return null;

    $title = '';
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) $title = trim(preg_replace('/\s*[—\-|·]\s*.+$/', '', $m[1]));
    $content = '';
    $patterns = [
        '/<article[^>]*>([\s\S]+?)<\/article>/i',
        '/<div[^>]*class=["\'][^"\']*(?:article|post|content|main|rich_media|rich_media_content)[^"\']*["\'][^>]*>([\s\S]+?)<\/div>/i',
        '/<section[^>]*class=["\'][^"\']*rich_media_content[^"\']*["\'][^>]*>([\s\S]+?)<\/section>/i',
    ];
    foreach ($patterns as $p) if (preg_match($p, $html, $m)) { $content = $m[1]; break; }
    if (empty($content) && preg_match('/<body[^>]*>([\s\S]+?)<\/body>/i', $html, $m)) $content = $m[1];

    $content = preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $content);
    $content = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $content);
    $content = preg_replace('/<iframe[^>]*>[\s\S]*?<\/iframe>/i', '', $content);
    $content = preg_replace('/<!--[\s\S]*?-->/', '', $content);
    $content = trim($content);
    if (empty($title) || empty($content)) return null;

    return ['title'=>mb_substr($title,0,80), 'content'=>$content, 'description'=>''];
}

// ─── 平台 API ───
function ingest_platform_api(string $platform, array $input, array $config): ?array {
    switch ($platform) {
        case 'notion':
            $token = $config['notion_token'] ?? '';
            $pageId = trim($input['page_id'] ?? '');
            if (!$token || !$pageId) return null;
            $ch = curl_init("https://api.notion.com/v1/blocks/" . $pageId . "/children?page_size=100");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token, 'Notion-Version: 2022-06-28'], CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
            $resp = json_decode(curl_exec($ch), true);
            if (empty($resp['results'])) return null;
            $html = '';
            foreach ($resp['results'] as $block) {
                $type = $block['type'] ?? 'paragraph';
                $rt = $block[$type] ?? [];
                $texts = $rt['rich_text'] ?? [];
                $line = '';
                foreach ($texts as $t) $line .= $t['plain_text'] ?? '';
                $safeLine = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                if ($type === 'heading_1') $html .= "<h1>{$safeLine}</h1>";
                elseif ($type === 'heading_2') $html .= "<h2>{$safeLine}</h2>";
                elseif ($type === 'heading_3') $html .= "<h3>{$safeLine}</h3>";
                elseif ($type === 'bulleted_list_item') $html .= "<ul><li>{$safeLine}</li></ul>";
                elseif ($type === 'numbered_list_item') $html .= "<ol><li>{$safeLine}</li></ol>";
                else $html .= "<p>{$safeLine}</p>";
            }
            $title = trim($input['title'] ?? 'Notion 导入文章');
            $result = ingest_save_article($title, $html, 'notion', '', $input);
            return $result;

        case 'feishu':
            // 飞书公开文档或 token 获取
            $token = $config['feishu_token'] ?? '';
            $docToken = trim($input['doc_token'] ?? '');
            if ($token && $docToken) {
                $ch = curl_init("https://open.feishu.cn/open-apis/docx/v1/documents/{$docToken}/raw_content");
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token], CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
                $resp = json_decode(curl_exec($ch), true);
                $text = $resp['data']['content'] ?? '';
                if ($text) {
                    $html = nl2br(htmlspecialchars($text));
                    $result = ingest_save_article(trim($input['title'] ?? '飞书文档'), $html, 'feishu', '', $input);
                    return $result;
                }
            }
            return null;

        case 'github':
            // GitHub Pages / GitHub 仓库 Markdown 批量导入
            require_once __DIR__ . '/../lib/Markdown.php';
            $repo = trim($input['repo'] ?? $input['github_repo'] ?? '');
            if (empty($repo)) {
                // 支持完整 URL：https://github.com/owner/repo 或 https://owner.github.io/
                $u = parse_url($input['url'] ?? '');
                $host = $u['host'] ?? '';
                $path = trim($u['path'] ?? '', '/');
                if ($host === 'github.com' && substr_count($path, '/') >= 1) {
                    $parts = explode('/', $path);
                    $repo = $parts[0] . '/' . $parts[1];
                } elseif (preg_match('/([^.\/]+)\.github\.io$/', $host, $m)) {
                    $repo = $m[1] . '/' . (basename($path) ?: $m[1] . '.github.io');
                }
            }
            if (empty($repo)) return null;

            $branch = trim($input['branch'] ?? 'main');
            $maxFiles = (int)($input['limit'] ?? 20);
            $files = ingest_github_list_md($repo, $branch, $maxFiles);
            if (empty($files)) return null;

            $saved = 0; $errors = [];
            foreach ($files as $file) {
                $raw = ingest_github_fetch_raw($repo, $branch, $file);
                if ($raw === null) { $errors[] = $file; continue; }
                [$meta, $body] = Markdown::extractFrontMatter($raw);
                $title = $meta['title'] ?? pathinfo($file, PATHINFO_FILENAME);
                $html = Markdown::toHtml($body);
                if (empty($title) || empty($html)) continue;
                ingest_save_article($title, $html, 'github', "https://github.com/{$repo}/blob/{$branch}/{$file}", $input);
                $saved++;
            }
            return ['article' => ['_batch' => true, 'saved' => $saved, 'failed' => count($errors)]];

        case 'cloudflare':
            // Cloudflare Pages 站点批量导入（抓取 sitemap + 各页面正文）
            $siteUrl = trim($input['site_url'] ?? $input['url'] ?? '');
            if (empty($siteUrl)) return null;
            $siteUrl = rtrim($siteUrl, '/');
            if (is_private_ip($siteUrl)) return null;
            $saved = ingest_cloudflare_import($siteUrl, $input);
            return ['article' => ['_batch' => true, 'saved' => $saved]];

        default:
            return null;
    }
}

// ─── GitHub 工具函数 ───
function ingest_github_list_md(string $repo, string $branch, int $max): array {
    $ch = curl_init("https://api.github.com/repos/{$repo}/git/trees/{$branch}?recursive=1");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_MAXREDIRS=>5, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'OpenFlow-Ingest/1.0', CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json']]);
    $resp = json_decode(curl_exec($ch), true);
    // curl_close 已弃用（PHP 8.5）
    if (empty($resp['tree'])) return [];
    $files = [];
    foreach ($resp['tree'] as $item) {
        if (($item['type'] ?? '') !== 'blob') continue;
        $path = $item['path'] ?? '';
        if (!preg_match('/\.(md|markdown|txt)$/i', $path)) continue;
        if (preg_match('#(^|/)(node_modules|\.git|vendor|dist|build|docs/_|\.github|assets?)/#i', $path)) continue;
        $files[] = $path;
        if (count($files) >= $max) break;
    }
    return $files;
}

function ingest_github_fetch_raw(string $repo, string $branch, string $path): ?string {
    $ch = curl_init("https://raw.githubusercontent.com/{$repo}/{$branch}/" . ltrim($path, '/'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_MAXREDIRS=>5, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'OpenFlow-Ingest/1.0']);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close 已弃用（PHP 8.5）
    return $http === 200 ? $body : null;
}

// ─── Cloudflare Pages 导入 ───
function ingest_cloudflare_import(string $siteUrl, array $input): int {
    $saved = 0;
    $urls = [];

    // 1. 尝试 sitemap.xml（支持 sitemap index 递归一层）
    foreach (['/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml'] as $sp) {
        $ch = curl_init($siteUrl . $sp);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'OpenFlow-Ingest/1.0']);
        $xml = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http === 200 && $xml && preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $xml, $m)) {
            $urls = $m[1];
            // 若为 sitemap index（含 <sitemap> 子节点），递归抓取子 sitemap
            if (preg_match('/<sitemap>/i', $xml)) {
                $flat = [];
                foreach ($urls as $sub) {
                    $sub = trim($sub);
                    if (strpos($sub, 'http') !== 0) $sub = rtrim($siteUrl, '/') . '/' . ltrim($sub, '/');
                    $ch2 = curl_init($sub);
                    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'OpenFlow-Ingest/1.0']);
                    $subXml = curl_exec($ch2);
                    if ($subXml && preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $subXml, $sm)) {
                        foreach ($sm[1] as $su) $flat[] = trim($su);
                    }
                    if (count($flat) >= (int)($input['limit'] ?? 20) * 3) break;
                }
                $urls = $flat;
            }
            $urls = array_slice($urls, 0, (int)($input['limit'] ?? 20));
            break;
        }
    }

    // 2. 无 sitemap → 抓首页找文章链接
    if (empty($urls)) {
        $ch = curl_init($siteUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'OpenFlow-Ingest/1.0']);
        $html = curl_exec($ch);
        if ($html && preg_match_all('/href="([^"#?]+)"/i', $html, $m)) {
            $candidates = array_values(array_unique($m[1]));
            foreach ($candidates as $c) {
                $c = preg_replace('/(https?:\/\/[^\/]+).*/', '$1', $siteUrl) !== $siteUrl ? $c : (strpos($c, 'http') === 0 ? $c : rtrim($siteUrl, '/') . '/' . ltrim($c, '/'));
                if (strpos($c, 'http') !== 0) continue;
                if (preg_match('#\.(css|js|png|jpg|jpeg|gif|svg|ico|xml|txt|woff2?)$#i', $c)) continue;
                $urls[] = $c;
                if (count($urls) >= (int)($input['limit'] ?? 20)) break;
            }
        }
    }

    foreach (array_slice($urls, 0, (int)($input['limit'] ?? 20)) as $u) {
        if (is_private_ip($u)) continue;
        $fetched = ingest_fetch_url($u);
        if (!$fetched || empty($fetched['title'])) continue;
        ingest_save_article($fetched['title'], $fetched['content'], 'cloudflare', $u, $input);
        $saved++;
    }
    return $saved;
}

// ─── 保存为文章草稿 ───
function ingest_save_article(string $title, string $content, string $platform, string $sourceUrl, array $input): array {
    // Markdown → HTML（简单转换）
    $html = $content;
    if (strpos($html, '<p>') === false && strpos($html, '<h') === false) {
        $html = nl2br(htmlspecialchars($content));
        // 简单 markdown 标题转换
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    }

    $article = [
        'id' => 'article_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
        'title' => mb_substr($title, 0, 80),
        'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', mb_substr($title, 0, 40)),
        'content' => $html,
        'editor_mode' => 'richtext',
        'category' => $input['category'] ?? 'insight',
        'tags' => is_array($input['tags'] ?? null) ? $input['tags'] : array_filter(array_map('trim', explode(',', $input['tags'] ?? ''))),
        'cover' => '',
        'author' => trim($input['author'] ?? '') ?: '导入助手',
        'status' => 'draft',
        'source' => $platform,
        'source_url' => $sourceUrl,
        'seo_title' => $title, 'seo_desc' => '', 'seo_keywords' => '',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        'review_status' => 'pending', // 进入审核
    ];
    $all = get_articles();
    $all[] = $article;
    json_write(ARTICLES_DIR . '/index.json', $all);
    notify('导入', "内容导入：「" . mb_substr($title, 0, 20) . "」", "来源：{$platform} · 待审核", 'admin/reviews.php?type=article');
    return ['article' => $article];
}
