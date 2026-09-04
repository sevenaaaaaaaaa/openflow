<?php
/**
 * 落地页存储 —— 唯一写入口（2026-09-04）
 *
 * 【为什么有这个文件】落地页一直是「谁想写就 json_write 一下」：
 * admin/page-builder.php 里一处、api/ai-landing.php 里一处，各写各的。
 * 后果是它**没有版本记录**——改错了退不回去，也说不清是谁改的。
 * 文章和普通页面早就有了（修订层挂在 save_article / save_page_content 里），
 * 唯独落地页漏着。
 *
 * 这个漏洞直接卡住了外部协作：上一版做外部临时协作时，落地页只能批注不能编辑，
 * 就是因为「谁改的、能不能退回去」在落地页上给不出来。与其放出一条没人看得见的
 * 外部写入路径，不如先把这里补上。
 *
 * 做法和文章那边一致：**挂在写入的咽喉处**，而不是逐个页面去补。
 * 只要经过 save_builder_page()，不管是后台手改、AI 生成还是外部协作者改，
 * 都会记一版并标明来源。
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/BlockContract.php';

if (!function_exists('builder_pages_file')) {

function builder_pages_file(): string { return DATA_DIR . '/builder-pages.json'; }

function builder_pages_all(): array {
    $d = json_read(builder_pages_file());
    return is_array($d) ? $d : [];
}

function builder_page_get(string $id): ?array {
    if ($id === '') return null;
    foreach (builder_pages_all() as $p) if (($p['id'] ?? '') === $id) return $p;
    return null;
}

/**
 * 落地页的唯一写入口。新建传空 id，返回新 id；更新传 id。
 *
 * @return string 写入后的页面 id；失败返回空串
 */
function save_builder_page(string $id, array $data): string {
    $pages = builder_pages_all();
    $before = null; $idx = -1;

    if ($id !== '') {
        foreach ($pages as $i => $p) if (($p['id'] ?? '') === $id) { $before = $p; $idx = $i; break; }
        if ($idx < 0) return '';                       // 要改的页面不存在
    }

    // 区块永远经过契约：形状统一，_key 唯一且沿用
    if (isset($data['blocks'])) $data['blocks'] = block_normalize_all($data['blocks']);

    if ($idx >= 0) {
        $merged = array_merge($pages[$idx], $data);
        $merged['id'] = $id;
        $merged['updated_at'] = date('Y-m-d H:i:s');
        $pages[$idx] = $merged;
    } else {
        $merged = $data;
        $merged['id'] = $merged['id'] ?? ('lp_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8));
        $merged['created_at'] = date('Y-m-d H:i:s');
        $merged['updated_at'] = $merged['created_at'];
        $id = (string)$merged['id'];
        $pages[] = $merged;
    }

    if (!json_write(builder_pages_file(), $pages)) return '';

    // 记一版。挂在这里而不是各个页面——落地页有后台、AI 生成、外部协作三条写入路径。
    try {
        require_once __DIR__ . '/RevisionSystem.php';
        rev_record('landing', $id, $before, $merged);
    } catch (Throwable $e) {}

    return $id;
}

function builder_page_delete(string $id): bool {
    $pages = builder_pages_all();
    $n = count($pages);
    $pages = array_values(array_filter($pages, fn($p) => ($p['id'] ?? '') !== $id));
    if (count($pages) === $n) return false;
    return json_write(builder_pages_file(), $pages);
}

}
