<?php
/**
 * 外部临时协作 —— 块级批注（主线 B，2026-09-04）
 *
 * 【为什么另起一套存储，而不是复用 CommentSystem】
 * CommentSystem 是给**读者**用的：公开、带评分点赞、走审核队列，
 * 而且 api/comment.php 会把 type 和 target_id 直接从请求里取出来查。
 * 评审批注是内部的东西——「这段数据来源要补」「这里的价格写错了」——
 * 一旦和公开评论混在一个库里，就只隔着 api/comment.php 里那一行白名单。
 * 那种「差一行就泄漏」的设计不值得为省一个文件去冒险。
 *
 * 【锚点】批注钉在块上：
 *   落地页 → 区块本身的 _key（块契约保证它活过保存）
 *   文章   → 按正文内容算出来的锚点（block_anchored_from_html）
 * 两种都可能失效（块被删、那段话被改写），所以**每条批注都存一份当时的引文**：
 * 锚点断了，批注依然知道自己说的是哪一句，界面上标成「原文已改动」而不是丢掉。
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/BlockContract.php';

// 函数一律用 note_ 前缀：admin/review-lib.php 已经占了 review_ —— 那是内容审核引擎
// （违禁词、待审队列），和这里的评审批注完全是两回事。撞名会直接致命错误。
if (!function_exists('note_file')) {

define('NOTE_MAX_PER_TARGET', 500);      // 单个对象的批注上限，防止外部人灌爆磁盘
define('NOTE_MAX_LEN', 2000);       // 单条批注长度上限

function note_file(string $type, string $id): string {
    $dir = DATA_DIR . '/collab/review';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $type . '_' . $id);
    return $dir . '/' . $safe . '.json';
}

function note_all(string $type, string $id): array {
    $d = json_read(note_file($type, $id));
    return is_array($d) ? $d : [];
}

function note_save(string $type, string $id, array $list): bool {
    return json_write(note_file($type, $id), array_values($list));
}

/**
 * 取这个对象的块序列（带锚点），批注就挂在这些锚上。
 * 文章按正文解析，落地页直接用它自己的区块。
 */
function note_blocks(string $type, string $id): array {
    if ($type === 'article') {
        $a = function_exists('get_article') ? get_article($id) : null;
        if (!$a) return [];
        return block_anchored_from_html((string)($a['content'] ?? ''));
    }
    if ($type === 'page') {
        foreach ((array)json_read(DATA_DIR . '/builder-pages.json') as $p) {
            if (($p['id'] ?? '') === $id) return block_normalize_all($p['blocks'] ?? []);
        }
    }
    return [];
}

/** 对象标题，给界面和后台列表用 */
function note_target_title(string $type, string $id): string {
    if ($type === 'article') { $a = function_exists('get_article') ? get_article($id) : null; return (string)($a['title'] ?? $id); }
    if ($type === 'page') {
        foreach ((array)json_read(DATA_DIR . '/builder-pages.json') as $p) if (($p['id'] ?? '') === $id) return (string)($p['title'] ?? $id);
    }
    return $id;
}

/**
 * 加一条批注。
 * @param string $anchor 块锚点；空串表示「针对整篇」
 * @param array  $actor  ['name'=>..,'kind'=>'external'|'admin']
 */
function note_add(string $type, string $id, string $anchor, string $text, array $actor, string $quote = ''): array {
    $text = trim($text);
    if ($text === '') return ['ok' => false, 'error' => '批注内容不能为空'];
    if (mb_strlen($text) > NOTE_MAX_LEN) $text = mb_substr($text, 0, NOTE_MAX_LEN);

    $list = note_all($type, $id);
    if (count($list) >= NOTE_MAX_PER_TARGET) return ['ok' => false, 'error' => '这篇内容的批注已达上限'];

    // 引文：锚点将来失效了，还知道当初说的是哪一句
    if ($quote === '' && $anchor !== '') {
        $b = block_find(note_blocks($type, $id), $anchor);
        if ($b) $quote = mb_substr(trim(block_plain_text($b)) ?: strip_tags((string)($b['title'] ?? '')), 0, 120);
    }

    $note = [
        'id'       => 'rv_' . substr(bin2hex(random_bytes(6)), 0, 12),
        'anchor'   => $anchor,
        'quote'    => mb_substr($quote, 0, 120),
        'text'     => $text,
        'by'       => mb_substr((string)($actor['name'] ?? '匿名'), 0, 60),
        'by_kind'  => in_array($actor['kind'] ?? '', ['external', 'admin'], true) ? $actor['kind'] : 'external',
        'at'       => date('Y-m-d H:i:s'),
        'resolved' => false,
        'replies'  => [],
    ];
    $list[] = $note;
    note_save($type, $id, $list);
    return ['ok' => true, 'error' => '', 'note' => $note];
}

/** 回复一条批注（作者和协作者都能回，形成一来一回） */
function note_reply(string $type, string $id, string $noteId, string $text, array $actor): array {
    $text = trim($text);
    if ($text === '') return ['ok' => false, 'error' => '回复不能为空'];
    $list = note_all($type, $id); $hit = false;
    foreach ($list as &$n) {
        if (($n['id'] ?? '') !== $noteId) continue;
        $n['replies'] = (array)($n['replies'] ?? []);
        if (count($n['replies']) >= 50) return ['ok' => false, 'error' => '这条批注的回复太多了'];
        $n['replies'][] = [
            'text' => mb_substr($text, 0, NOTE_MAX_LEN),
            'by'   => mb_substr((string)($actor['name'] ?? '匿名'), 0, 60),
            'by_kind' => in_array($actor['kind'] ?? '', ['external', 'admin'], true) ? $actor['kind'] : 'external',
            'at'   => date('Y-m-d H:i:s'),
        ];
        $hit = true; break;
    }
    unset($n);
    if (!$hit) return ['ok' => false, 'error' => '批注不存在'];
    note_save($type, $id, $list);
    return ['ok' => true, 'error' => ''];
}

/** 标记处理完 / 重新打开 */
function note_resolve(string $type, string $id, string $noteId, string $by, bool $resolved = true): array {
    $list = note_all($type, $id); $hit = false;
    foreach ($list as &$n) {
        if (($n['id'] ?? '') !== $noteId) continue;
        $n['resolved'] = $resolved;
        $n['resolved_by'] = $resolved ? mb_substr($by, 0, 60) : '';
        $n['resolved_at'] = $resolved ? date('Y-m-d H:i:s') : '';
        $hit = true; break;
    }
    unset($n);
    if (!$hit) return ['ok' => false, 'error' => '批注不存在'];
    note_save($type, $id, $list);
    return ['ok' => true, 'error' => ''];
}

function note_open_count(string $type, string $id): int {
    return count(array_filter(note_all($type, $id), fn($n) => empty($n['resolved'])));
}

/** 批注按锚点分组，渲染时一次取用 */
function note_by_anchor(string $type, string $id): array {
    $out = [];
    foreach (note_all($type, $id) as $n) $out[(string)($n['anchor'] ?? '')][] = $n;
    return $out;
}

/**
 * 锚点还在不在。断了的批注不能丢，要标出来让人知道「原文已改动」。
 */
function note_anchor_alive(string $type, string $id, string $anchor, ?array $blocks = null): bool {
    if ($anchor === '') return true;                  // 整篇批注永远有效
    $blocks = $blocks ?? note_blocks($type, $id);
    return block_find($blocks, $anchor) !== null;
}

/** 全站待处理批注（后台首页/列表用） */
function note_pending_all(int $limit = 50): array {
    $dir = DATA_DIR . '/collab/review';
    $out = [];
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $base = basename($f, '.json');
        $pos = strpos($base, '_');
        if ($pos === false) continue;
        $type = substr($base, 0, $pos); $id = substr($base, $pos + 1);
        foreach (json_read($f) as $n) {
            if (!empty($n['resolved'])) continue;
            $n['_type'] = $type; $n['_id'] = $id;
            $out[] = $n;
            if (count($out) >= $limit) return $out;
        }
    }
    return $out;
}

}
