<?php
/**
 * 历史数据迁移助手 — 老系统 → OpenFlow 切换
 * 支持导入：文章 / 页面 / 线索 / 用户会员 / 评论
 * 核心：字段映射（老系统字段名 → 新系统字段），预览 + 冲突处理 + 报告
 */

// 各类型的可映射目标字段
function migrate_fields(string $type): array {
    $map = [
        'articles' => [
            'title' => '标题 *', 'content' => '正文', 'slug' => 'URL 别名', 'category' => '分类',
            'tags' => '标签(逗号分隔)', 'status' => '状态(published/draft)', 'excerpt' => '摘要',
            'cover' => '封面图', 'author' => '作者', 'created_at' => '创建时间',
            'seo_title' => 'SEO 标题', 'seo_desc' => 'SEO 描述', 'seo_keywords' => 'SEO 关键词',
            'member_only' => '仅会员(yes/no)', 'publish_at' => '定时发布时间',
        ],
        'leads' => [
            'name' => '姓名', 'email' => '邮箱', 'phone' => '手机号', 'company' => '公司',
            'stage' => '阶段(new/contacted/qualified/opportunity/won/lost)', 'score' => '评分(0-100)',
            'source' => '来源', 'note' => '备注', 'value' => '商机金额', 'expected_close' => '预计成交日期',
            'owner' => '跟进人', 'created_at' => '创建时间',
        ],
        'members' => [
            'name' => '昵称', 'email' => '邮箱', 'phone' => '手机号', 'company' => '公司',
            'role' => '角色(user/ambassador)', 'created_at' => '注册时间', 'bio' => '简介',
        ],
        'comments' => [
            'article_key' => '文章标识(slug/title)', 'author' => '昵称', 'email' => '邮箱',
            'content' => '内容 *', 'created_at' => '时间', 'status' => '状态(approved/pending)',
        ],
        'orders' => [
            'member_email' => '客户邮箱', 'course_title' => '商品/课程', 'amount' => '金额',
            'status' => '状态(paid/pending/cancelled/refunded)', 'paid_at' => '支付时间',
            'created_at' => '下单时间', 'utm_source' => '来源渠道',
        ],
        'pages' => [
            'title' => '标题 *', 'content' => '正文', 'slug' => 'URL 别名', 'template' => '模板',
            'seo_title' => 'SEO 标题', 'seo_desc' => 'SEO 描述', 'status' => '状态(published/draft)',
            'created_at' => '创建时间',
        ],
    ];
    return $map[$type] ?? [];
}

// 解析上传文件为行数组，支持 CSV / JSON / XLSX（Excel），返回 [header, rows]
function migrate_parse_file(string $tmpPath): array {
    $ext = strtolower(pathinfo($tmpPath, PATHINFO_EXTENSION));
    if ($ext === 'json') {
        $arr = json_decode(file_get_contents($tmpPath), true);
        if (!is_array($arr)) return [[], []];
        if (isset($arr['data']) && is_array($arr['data'])) $arr = $arr['data'];
        $rows = array_values($arr);
        $header = $rows ? array_keys($rows[0]) : [];
        return [$header, $rows];
    }
    if ($ext === 'xlsx' || $ext === 'xlsm') {
        return migrate_parse_xlsx($tmpPath);
    }
    // CSV
    $header = []; $rows = [];
    if (($h = fopen($tmpPath, 'r')) !== false) {
        $header = fgetcsv($h) ?: [];
        while (($line = fgetcsv($h)) !== false) {
            if (count($line) === 1 && trim($line[0]) === '') continue;
            $row = array_combine($header, array_pad($line, count($header), ''));
            $rows[] = array_map('trim', $row);
        }
        fclose($h);
    }
    return [$header, $rows];
}

// 轻量 XLSX 解析（无第三方依赖）：解压 sheet + sharedStrings
function migrate_parse_xlsx(string $path): array {
    $rows = [];
    $header = [];
    if (!class_exists('ZipArchive')) return [[], []];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [[], []];
    // 共享字符串
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false && preg_match_all('/<si>(.*?)<\/si>/s', $sharedXml, $sm)) {
        foreach ($sm[1] as $si) {
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $tm)) $shared[] = html_entity_decode(implode('', $tm[1]), ENT_QUOTES, 'UTF-8');
            else $shared[] = '';
        }
    }
    // 找第一个 sheet
    $sheetName = 'xl/worksheets/sheet1.xml';
    $sheetXml = $zip->getFromName($sheetName);
    if ($sheetXml === false) { $zip->close(); return [[], []]; }
    $zip->close();
    if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rm)) {
        foreach ($rm[1] as $rowXml) {
            $row = [];
            if (preg_match_all('/<c[^>]*r="([A-Z]+)(\d+)"[^>]*?(?:\/>|>)(.*?)<\/c>/s', $rowXml, $cm)) {
                foreach ($cm[1] as $i => $col) {
                    $type = '';
                    if (preg_match('/<c[^>]*t="([^"]*)"/', $rowXml, $tm2) && $tm2[1]) $type = $tm2[1]; // 简化：逐 c 处理
                    // 重新逐单元格解析
                    if (preg_match('/<c[^>]*r="' . $col . '\d+"(?:[^>]*?)>(.*?)<\/c>/s', $rowXml, $cellM)) {
                        $v = '';
                        if (preg_match('/t="s"/', $cellM[0])) {
                            if (preg_match('/<v>(\d+)<\/v>/', $cellM[1], $vm)) $v = $shared[(int)$vm[1]] ?? '';
                        } else {
                            if (preg_match('/<v>(.*?)<\/v>/s', $cellM[1], $vm)) $v = $vm[1];
                        }
                        $row[$col] = trim($v);
                    }
                }
            }
            if (!empty($row)) $rows[] = $row;
        }
    }
    if (empty($rows)) return [[], []];
    // 第一行作为表头，按列字母排序
    $cols = array_keys($rows[0]);
    $header = [];
    $dataRows = [];
    $isHeader = true;
    foreach ($rows as $r) {
        $assoc = [];
        foreach ($cols as $c) $assoc[] = $r[$c] ?? '';
        if ($isHeader) { $header = array_values($r); $isHeader = false; continue; }
        $dataRows[] = array_combine($header, array_pad($assoc, count($header), ''));
    }
    return [$header, $dataRows];
}

// 应用字段映射：rows（老字段） → map（新字段←老列名）
function migrate_apply_map(array $rows, array $map): array {
    $mapped = [];
    foreach ($rows as $row) {
        $out = [];
        foreach ($map as $newField => $oldCol) {
            if ($oldCol !== '' && isset($row[$oldCol])) $out[$newField] = $row[$oldCol];
        }
        if (!empty($out)) $mapped[] = $out;
    }
    return $mapped;
}

// 导入文章
function migrate_import_articles(array $rows): array {
    $imported = 0; $skipped = 0; $errors = [];
    foreach ($rows as $i => $r) {
        $title = trim($r['title'] ?? '');
        if ($title === '') { $skipped++; continue; }
        $slug = trim($r['slug'] ?? '');
        if (empty($slug)) {
            $slug = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}-]/u', '-', $title);
            $slug = preg_replace('/-+/', '-', trim($slug, '-'));
            $slug = mb_substr($slug, 0, 80);
        }
        if (article_slug_exists($slug, null)) { $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6); }
        $article = [
            'id' => 'article_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'title' => $title,
            'slug' => $slug,
            'content' => $r['content'] ?? '',
            'category' => $r['category'] ?? '',
            'tags' => array_filter(array_map('trim', explode(',', $r['tags'] ?? ''))),
            'excerpt' => $r['excerpt'] ?? '',
            'cover' => $r['cover'] ?? '',
            'author' => $r['author'] ?? '迁移',
            'status' => in_array($r['status'] ?? '', ['published','draft']) ? $r['status'] : 'published',
            'member_only' => (($r['member_only'] ?? '') === 'yes' || ($r['member_only'] ?? '') === '1' || ($r['member_only'] ?? '') === 'true'),
            'publish_at' => $r['publish_at'] ?? '',
            'seo_title' => $r['seo_title'] ?? '',
            'seo_desc' => $r['seo_desc'] ?? '',
            'seo_keywords' => $r['seo_keywords'] ?? '',
            'created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        save_article($article['id'], $article);
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
}

// 导入线索
function migrate_import_leads(array $rows): array {
    require_once __DIR__ . '/../lib/CrmSystem.php';
    $imported = 0; $skipped = 0;
    foreach ($rows as $r) {
        $email = mb_strtolower(trim($r['email'] ?? ''));
        $phone = trim($r['phone'] ?? '');
        if ($email === '' && $phone === '') { $skipped++; continue; }
        $lead = crm_ensure_lead($email ?: $phone, trim($r['name'] ?? ''), $phone);
        $updates = ['source' => $r['source'] ?? 'migrate'];
        if (!empty($r['company'])) $updates['company'] = $r['company'];
        if (!empty($r['stage'])) $updates['stage'] = $r['stage'];
        if (isset($r['score'])) $updates['score'] = (int)$r['score'];
        if (!empty($r['note'])) $updates['notes'] = ($lead['notes'] ?? '') . $r['note'];
        crm_update_lead($email ?: $phone, $updates);
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
}

// 导入会员
function migrate_import_members(array $rows): array {
    $imported = 0; $skipped = 0;
    foreach ($rows as $r) {
        $email = mb_strtolower(trim($r['email'] ?? ''));
        if ($email === '') { $skipped++; continue; }
        if (member_find($email)) { $skipped++; continue; } // 已存在跳过
        $member = [
            'id' => 'm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'name' => trim($r['name'] ?? '') ?: $email,
            'phone' => trim($r['phone'] ?? ''),
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role' => in_array($r['role'] ?? '', ['user','ambassador']) ? $r['role'] : 'user',
            'company' => trim($r['company'] ?? ''),
            'ambassador' => ($r['role'] ?? '') === 'ambassador',
            'teacher_status' => 'none',
            'points' => 0,
            'source' => 'migrate',
            'created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
        ];
        member_save($member);
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
}

// 导入订单
function migrate_import_orders(array $rows): array {
    $imported = 0; $skipped = 0;
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    foreach ($rows as $r) {
        $amount = (float)($r['amount'] ?? 0);
        if ($amount <= 0 && empty($r['course_title'])) { $skipped++; continue; }
        $order = [
            'id' => 'order_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'member_id' => '',
            'member_email' => trim($r['member_email'] ?? ''),
            'course_title' => $r['course_title'] ?? '历史订单',
            'amount' => $amount,
            'status' => in_array($r['status'] ?? '', ['paid','pending','cancelled','refunded']) ? $r['status'] : 'paid',
            'utm' => ['utm_source' => $r['utm_source'] ?? 'migrate'],
            'created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
            'paid_at' => ($r['status'] ?? '') === 'paid' ? ($r['paid_at'] ?? ($r['created_at'] ?? date('Y-m-d H:i:s'))) : '',
            'source' => 'migrate',
        ];
        $orders[] = $order;
        $imported++;
    }
    json_write(DATA_DIR . '/shop/orders.json', $orders);
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
}

// 导入落地页/CMS 页面
function migrate_import_pages(array $rows): array {
    $imported = 0; $skipped = 0;
    $pages = json_read(DATA_DIR . '/pages.json');
    foreach ($rows as $r) {
        $title = trim($r['title'] ?? '');
        if ($title === '') { $skipped++; continue; }
        $slug = trim($r['slug'] ?? '');
        if (empty($slug)) {
            $slug = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}-]/u', '-', $title);
            $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        }
        $pages[] = [
            'id' => 'page_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'title' => $title,
            'slug' => $slug,
            'content' => $r['content'] ?? '',
            'template' => $r['template'] ?? 'default',
            'seo_title' => $r['seo_title'] ?? '',
            'seo_desc' => $r['seo_desc'] ?? '',
            'status' => in_array($r['status'] ?? '', ['published','draft']) ? $r['status'] : 'published',
            'created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
        ];
        $imported++;
    }
    json_write(DATA_DIR . '/pages.json', $pages);
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
}

// 导入评论
function migrate_import_comments(array $rows): array {
    $imported = 0; $skipped = 0;
    $comments = json_read(DATA_DIR . '/comments.json');
    foreach ($rows as $r) {
        $content = trim($r['content'] ?? '');
        if ($content === '') { $skipped++; continue; }
        $articleKey = trim($r['article_key'] ?? '');
        $articleId = $articleKey;
        if ($articleKey) {
            foreach (get_articles() as $a) {
                if (($a['slug'] ?? '') === $articleKey || ($a['title'] ?? '') === $articleKey) { $articleId = $a['id']; break; }
            }
        }
        $comments[] = [
            'id' => 'c_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'article_id' => $articleId,
            'author' => trim($r['author'] ?? '匿名'),
            'email' => trim($r['email'] ?? ''),
            'content' => $content,
            'status' => in_array($r['status'] ?? '', ['approved','pending','spam']) ? $r['status'] : 'approved',
            'created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
        ];
        $imported++;
    }
    json_write(DATA_DIR . '/comments.json', $comments);
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
}
