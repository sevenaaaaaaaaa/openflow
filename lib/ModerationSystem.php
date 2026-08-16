<?php
/**
 * 风控与内容审核系统
 *
 * 能力：
 *  1. 精细审核：内容发布前的规则拦截（敏感词/正则/水军特征/链接检测）
 *  2. 定期扫描：全量扫描存量 UGC（评论/点评/社区/投稿）自动标记风险
 *  3. AI Agent 辅助：调用 AI 供应商对可疑内容做风险分级与分类
 *  4. 人工复核队列：管理员可在后台一键通过/删除
 *
 * 数据：
 *  - 规则：data/moderation/rules.json
 *  - 队列：data/moderation/queue.json
 *  - 扫描日志：SQLite moderation_log
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Database.php';

function mod_rules_file(): string { return DATA_DIR . '/moderation/rules.json'; }
function mod_queue_file(): string { return DATA_DIR . '/moderation/queue.json'; }

// ─── 规则配置 ───
function mod_rules(): array {
    return array_merge([
        'banned_words' => ['赌博', '博彩', '色情', '诈骗', '代开发票', '贷款广告', '刷单', '传销', '毒品', '违禁品', '高利贷', '加微信', '加V信', '私聊我'],
        'sensitive_words' => ['政治', '暴力', '歧视', '地域黑', '人身攻击'],
        'url_patterns' => ['/https?:\/\//i', '/www\./i'],
        'phone_pattern' => '/1[3-9]\d{9}/',
        'email_pattern' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        'spam_chars' => ['vx', 'weixin', 'q群', '扣扣', '加我', '私我'],
        'ai_enabled' => false,       // 是否启用 AI 审核
        'ai_auto_hide' => true,      // AI 判高风险是否自动隐藏
        'max_rate' => 3,             // 同一用户单位时间最大发布数（自动扫描用）
        'rate_window' => 600,        // 秒
    ], json_read(mod_rules_file()));
}
function mod_save_rules(array $rules): void {
    if (!is_dir(dirname(mod_rules_file()))) mkdir(dirname(mod_rules_file()), 0755, true);
    json_write(mod_rules_file(), $rules);
}

// ─── 审核队列 ───
function mod_queue(): array { return json_read(mod_queue_file()); }
function mod_queue_save(array $q): void {
    if (!is_dir(dirname(mod_queue_file()))) mkdir(dirname(mod_queue_file()), 0755, true);
    json_write(mod_queue_file(), $q);
}
function mod_queue_add(array $item): void {
    $q = mod_queue();
    $q[] = $item;
    mod_queue_save(array_slice($q, -2000));
}

// ─── 规则审核（本地，无 AI）───
// 返回 ['ok'=>bool, 'risk'=>'low|mid|high', 'reason'=>string, 'score'=>int]
function mod_check_text(string $text): array {
    $rules = mod_rules();
    $score = 0;
    $reasons = [];
    $lower = mb_strtolower($text);

    // 违规词 → 高风险
    foreach (($rules['banned_words'] ?? []) as $w) {
        if ($w !== '' && mb_strpos($lower, mb_strtolower($w)) !== false) {
            return ['ok' => false, 'risk' => 'high', 'reason' => '含违规词：' . $w, 'score' => 100];
        }
    }
    // 敏感词 → 中风险
    foreach (($rules['sensitive_words'] ?? []) as $w) {
        if ($w !== '' && mb_strpos($lower, mb_strtolower($w)) !== false) {
            $score += 30;
            $reasons[] = '含敏感词：' . $w;
        }
    }
    // 链接
    foreach (($rules['url_patterns'] ?? []) as $p) {
        if (preg_match($p, $text)) { $score += 25; $reasons[] = '含外链'; break; }
    }
    // 手机号 / 邮箱（导流特征）
    if (preg_match($rules['phone_pattern'] ?? '/1[3-9]\d{9}/', $text)) { $score += 20; $reasons[] = '含手机号'; }
    if (preg_match($rules['email_pattern'] ?? '/@/', $text)) { $score += 15; $reasons[] = '含邮箱'; }
    // 导流词
    foreach (($rules['spam_chars'] ?? []) as $w) {
        if ($w !== '' && mb_strpos($lower, mb_strtolower($w)) !== false) { $score += 15; $reasons[] = '疑似导流：' . $w; }
    }

    if ($score >= 60) return ['ok' => false, 'risk' => 'high', 'reason' => implode('、', $reasons), 'score' => $score];
    if ($score >= 30) return ['ok' => true, 'risk' => 'mid', 'reason' => implode('、', $reasons), 'score' => $score];
    return ['ok' => true, 'risk' => 'low', 'reason' => '', 'score' => $score];
}

// ─── AI Agent 审核（调用配置的 AI 供应商）───
function mod_ai_review(string $text): array {
    $ai = json_read(DATA_DIR . '/ai-config.json');
    $provider = null;
    foreach (($ai['providers'] ?? []) as $p) {
        if (!empty($p['enabled']) && !empty($p['api_key'])) { $provider = $p; break; }
    }
    if (!$provider) return ['ok' => true, 'risk' => 'unknown', 'reason' => 'AI 未配置', 'score' => 0];

    $model = $provider['model'] ?? 'gpt-4o';
    $apiUrl = rtrim($provider['api_url'], '/');
    $prompt = "你是内容安全审核员。请判断以下内容的风险等级：高风险（违规/导流/诈骗/广告）、中风险（疑似营销或引战）、低风险（正常）。只输出 JSON：{\"risk\":\"low|mid|high\",\"reason\":\"简短原因\"}\n内容：\n" . mb_substr($text, 0, 800);

    $payload = json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0,
        'max_tokens' => 200,
    ]);
    if ($provider['id'] === 'claude') {
        $headers = ['x-api-key: ' . $provider['api_key'], 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
        $payload = json_encode(['model' => $model, 'max_tokens' => 200, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $endpoint = $apiUrl . '/messages';
    } else {
        $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
        $endpoint = $apiUrl . ($provider['id'] === 'minimax' ? '/text/chatcompletion_v2' : '/chat/completions');
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $resp = curl_exec($ch);
    if (!$resp) return ['ok' => true, 'risk' => 'unknown', 'reason' => 'AI 请求失败', 'score' => 0];

    $data = json_decode($resp, true);
    $content = $data['content'][0]['text'] ?? $data['choices'][0]['message']['content'] ?? $data['output_text'] ?? '';
    preg_match('/"risk"\s*:\s*"(low|mid|high)"/', $content, $m);
    preg_match('/"reason"\s*:\s*"([^"]*)"/', $content, $r);
    $risk = $m[1] ?? 'low';
    $score = ['low' => 0, 'mid' => 40, 'high' => 90][$risk] ?? 0;
    return ['ok' => $risk !== 'high', 'risk' => $risk, 'reason' => $r[1] ?? '', 'score' => $score];
}

// ─── 主入口：内容提交时调用 ───
// 返回 ['action'=>'allow|queue|block', 'check'=>array]
function mod_review_content(string $text, array $meta = []): array {
    $rules = mod_rules();
    $check = mod_check_text($text);

    // 本地规则直接拦截
    if (!$check['ok']) {
        mod_log('auto_block', $meta, $check['reason'], $check['score']);
        return ['action' => 'block', 'check' => $check];
    }

    // 中风险 → 尝试 AI
    if ($check['risk'] === 'mid' && !empty($rules['ai_enabled'])) {
        $ai = mod_ai_review($text);
        $check = array_merge($check, ['ai' => $ai]);
        if ($ai['risk'] === 'high') {
            mod_log('ai_block', $meta, $ai['reason'], $ai['score']);
            if ($rules['ai_auto_hide']) return ['action' => 'block', 'check' => $check];
            mod_queue_add(array_merge($meta, ['text' => $text, 'risk' => 'high', 'reason' => $ai['reason'], 'source' => 'ai', 'created_at' => date('Y-m-d H:i:s')]));
            return ['action' => 'queue', 'check' => $check];
        }
        if ($ai['risk'] === 'mid') {
            mod_queue_add(array_merge($meta, ['text' => $text, 'risk' => 'mid', 'reason' => $ai['reason'] ?: $check['reason'], 'source' => 'ai', 'created_at' => date('Y-m-d H:i:s')]));
            mod_log('ai_queue', $meta, $ai['reason'], $ai['score']);
            return ['action' => 'queue', 'check' => $check];
        }
        return ['action' => 'allow', 'check' => $check];
    }

    // 中风险无 AI → 入队人工复核
    if ($check['risk'] === 'mid') {
        mod_queue_add(array_merge($meta, ['text' => $text, 'risk' => 'mid', 'reason' => $check['reason'], 'source' => 'rule', 'created_at' => date('Y-m-d H:i:s')]));
        mod_log('rule_queue', $meta, $check['reason'], $check['score']);
        return ['action' => 'queue', 'check' => $check];
    }

    return ['action' => 'allow', 'check' => $check];
}

// ─── 定期全量扫描 ───
function mod_scan_all(): array {
    $results = ['scanned' => 0, 'flagged' => 0, 'blocked' => 0];
    $rules = mod_rules();

    // 评论（JSON）
    $comments = json_read(DATA_DIR . '/comments.json');
    foreach ($comments as &$c) {
        $results['scanned']++;
        $check = mod_check_text($c['text'] ?? '');
        if (!$check['ok']) {
            $c['status'] = 'hidden';
            $results['blocked']++;
            mod_log('scan_block', ['target_type' => 'comment', 'target_id' => $c['id'] ?? ''], $check['reason'], $check['score']);
        } elseif ($check['risk'] === 'mid') {
            $results['flagged']++;
            mod_queue_add(['target_type' => 'comment', 'target_id' => $c['id'] ?? '', 'text' => $c['text'] ?? '', 'author' => $c['author'] ?? '', 'risk' => 'mid', 'reason' => $check['reason'], 'source' => 'scan', 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
    unset($c);
    json_write(DATA_DIR . '/comments.json', $comments);

    // 社区帖子（JSON）
    $communityPosts = json_read(DATA_DIR . '/community-posts.json');
    foreach ($communityPosts as &$p) {
        $results['scanned']++;
        $check = mod_check_text(($p['title'] ?? '') . ' ' . ($p['content'] ?? ''));
        if (!$check['ok']) {
            $p['status'] = 'hidden';
            $results['blocked']++;
            mod_log('scan_block', ['target_type' => 'community_post', 'target_id' => $p['id'] ?? ''], $check['reason'], $check['score']);
        }
    }
    unset($p);
    json_write(DATA_DIR . '/community-posts.json', $communityPosts);

    // 投稿（JSON）
    $subs = json_read(DATA_DIR . '/member-submissions.json');
    foreach ($subs as &$s) {
        if (($s['status'] ?? '') === 'pending') continue;
        $results['scanned']++;
        $check = mod_check_text(($s['title'] ?? '') . ' ' . ($s['content'] ?? ''));
        if (!$check['ok']) {
            $s['status'] = 'rejected';
            $results['blocked']++;
            mod_log('scan_block', ['target_type' => 'submission', 'target_id' => $s['id'] ?? ''], $check['reason'], $check['score']);
        }
    }
    json_write(DATA_DIR . '/member-submissions.json', $subs);

    // 点评/社区用 SQLite 的表
    try {
        $db = Database::conn();
        $rows = $db->query("SELECT id, text FROM comments WHERE status='approved'");
        foreach ($rows as $row) {
            $results['scanned']++;
            $check = mod_check_text($row['text'] ?? '');
            if (!$check['ok']) {
                $db->execute("UPDATE comments SET status='hidden' WHERE id=?", [$row['id']]);
                $results['blocked']++;
                mod_log('scan_block', ['target_type' => 'comment_sqlite', 'target_id' => $row['id']], $check['reason'], $check['score']);
            }
        }
    } catch (Exception $e) {}

    return $results;
}

// ─── 审核日志 ───
function mod_log(string $action, array $meta, string $reason = '', float $score = 0): void {
    try {
        Database::insert('moderation_log', [
            'target_type' => $meta['target_type'] ?? '',
            'target_id' => $meta['target_id'] ?? '',
            'action' => $action,
            'reason' => mb_substr($reason, 0, 200),
            'ai_score' => $score,
            'reviewer' => 'auto',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {}
}

// 队列操作
function mod_queue_remove(string $key): void {
    $q = mod_queue();
    $q = array_values(array_filter($q, fn($item) => ($item['created_at'] ?? '') . ($item['text'] ?? '') . ($item['target_id'] ?? '') !== $key));
    mod_queue_save($q);
}
