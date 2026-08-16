<?php
/**
 * 内容审核引擎 — 检测有害/违规/低质/偏离定位/竞品词
 */

// ─── 词库配置 ───
function review_rules(): array {
    $cfg = json_read(DATA_DIR . '/review-rules.json');
    $default = [
        'banned_words' => [],        // 违禁违规词
        'competitor_words' => [],    // 竞品词
        'low_quality' => [],         // 低质信号词
        'positioning_keywords' => [], // 产品定位关键词
    ];
    return array_merge($default, $cfg);
}

// ─── 待审核记录存储 ───
function review_queue_file(): string { return DATA_DIR . '/reviews.json'; }
function review_get_queue(): array { return json_read(review_queue_file()); }
function review_add(array $item): void {
    $q = review_get_queue();
    $q[] = $item;
    json_write(review_queue_file(), $q);
}

/**
 * 检测内容，返回命中的问题列表
 * @param string $title 标题
 * @param string $content 正文（HTML）
 * @param string $type 类型：article / page / email
 * @return array ['issues' => [...], 'score' => int]
 */
function review_content(string $title, string $content, string $type = 'article'): array {
    $rules = review_rules();
    $plain = strip_tags($content);
    $full = $title . "\n" . $plain; // 全文字符串（用于命中检测）
    $issues = [];

    // 1. 违禁违规词
    foreach ($rules['banned_words'] as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strpos($full, $w) !== false) {
            $issues[] = ['rule' => 'banned', 'word' => $w, 'desc' => "命中违禁词「{$w}」"];
        }
    }

    // 2. 竞品词
    foreach ($rules['competitor_words'] as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strpos($full, $w) !== false) {
            $issues[] = ['rule' => 'competitor', 'word' => $w, 'desc' => "出现竞品词「{$w}」"];
        }
    }

    // 3. 低质量信号
    $textLen = mb_strlen($plain);
    if ($textLen < 100) {
        $issues[] = ['rule' => 'low_quality', 'word' => '', 'desc' => "内容过短（{$textLen} 字），可能质量不高"];
    }
    // 大量无意义填充
    if (preg_match('/[。！？]{6,}/u', $plain)) {
        $issues[] = ['rule' => 'low_quality', 'word' => '', 'desc' => '存在连续标点填充，疑似低质内容'];
    }
    // 空洞模板词
    foreach ($rules['low_quality'] as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strpos($plain, $w) !== false) {
            $issues[] = ['rule' => 'low_quality', 'word' => $w, 'desc' => "命中低质信号词「{$w}」"];
        }
    }

    // 4. 产品定位（通过关键词亲和度检测偏离）
    $positioning = $rules['positioning_keywords'] ?? [];
    if (!empty($positioning)) {
        $hits = 0;
        foreach ($positioning as $k) {
            if ($k !== '' && mb_strpos($plain, $k) !== false) $hits++;
        }
        $ratio = $hits / count(array_filter($positioning));
        if ($ratio < 0.15 && $textLen > 200) {
            $issues[] = ['rule' => 'off_topic', 'word' => '', 'desc' => '内容与产品定位关键词关联度低，疑似偏离主题'];
        }
    }

    return ['issues' => $issues, 'score' => count($issues)];
}

/**
 * 判断是否需要审核（命中任一规则）
 */
function review_needed(array $result): bool {
    return !empty($result['issues']);
}

/**
 * 审核后处理：设置 review_status
 * @param string $targetType article / page / email
 * @param string $targetId
 * @param array $result 审核结果
 */
function review_apply(string $targetType, string $targetId, array $result, array $meta = []): array {
    $issues = $result['issues'];
    $approved = empty($issues);
    $review = [
        'id' => 'review_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'type' => $targetType,
        'target_id' => $targetId,
        'title' => $meta['title'] ?? '',
        'issues' => $issues,
        'status' => $approved ? 'approved' : 'pending',
        'submitted_by' => $_SESSION['admin_user'] ?? '',
        'submitted_at' => date('Y-m-d H:i:s'),
        'reviewed_by' => '',
        'reviewed_at' => '',
        'review_note' => '',
    ];
    review_add($review);
    return $review;
}

/**
 * 获取待审核数量
 */
function review_pending_count(): int {
    $q = review_get_queue();
    return count(array_filter($q, fn($r) => ($r['status'] ?? '') === 'pending'));
}
