<?php
/**
 * 内容修订与还原 —— P0-03（2026-09-03）
 *
 * 背景：此前全站只有 VersionDiff 这个「两段文本比一比」的渲染器，**没有版本存储、
 * 也没有还原**。写错发出去只能靠记忆往回改。WordPress / Sanity 都把修订当地板功能，
 * 而单人场景其实更需要——没有同事帮你发现改错了。
 *
 * 这一层同时是「外部临时协作」（主线 B）的底座：外包写手改了什么、是谁改的、
 * 能不能退回去，全靠这里的快照与作者标记。
 *
 * 挂载点选在 save_article() / save_page_content() 内部而不是各个页面：
 * 光文章就有 37 个写入点（后台、MCP、批量导入、定时任务、API），逐个去补必然漏。
 *
 * 存储：data/revisions/{type}/{id}.json，每条目最多保留 REV_KEEP 版，
 * 连续两次内容完全相同不重复记版。
 */

if (!function_exists('rev_dir')) {

define('REV_KEEP', 30);              // 每个条目保留的版本数
define('REV_MAX_FIELD', 2000000);    // 单字段超过 2MB 不入快照，避免版本库爆掉

function rev_dir(string $type): string {
    $d = DATA_DIR . '/revisions/' . preg_replace('/[^a-z0-9_-]/i', '', $type);
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function rev_file(string $type, string $id): string {
    return rev_dir($type) . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $id) . '.json';
}

/**
 * 当前操作者。后台会话优先；MCP / 自动化在调用前会设 $GLOBALS['of_actor']。
 * @return array{name:string,source:string}
 */
function rev_actor(): array {
    $set = $GLOBALS['of_actor'] ?? null;
    if (is_array($set) && !empty($set['name'])) {
        return ['name' => (string)$set['name'], 'source' => (string)($set['source'] ?? 'system')];
    }
    if (!empty($_SESSION['admin_user'])) {
        return ['name' => (string)$_SESSION['admin_user'], 'source' => 'admin'];
    }
    if (PHP_SAPI === 'cli') return ['name' => '命令行', 'source' => 'cli'];
    return ['name' => '系统', 'source' => 'system'];
}

/** 参与比对与还原的字段：正文类在前，便于展示 */
function rev_tracked_fields(string $type): array {
    if ($type === 'page') return ['title', 'content', 'blocks', 'seo_title', 'seo_desc', 'seo_keywords'];
    return ['title', 'content', 'excerpt', 'status', 'category', 'tags', 'cover',
            'slug', 'author', 'seo_title', 'seo_desc', 'seo_keywords', 'publish_at'];
}

/** 从整条记录里取出要入快照的字段 */
function rev_snapshot(string $type, array $rec): array {
    $snap = [];
    foreach (rev_tracked_fields($type) as $f) {
        if (!array_key_exists($f, $rec)) continue;
        $v = $rec[$f];
        if (is_string($v) && strlen($v) > REV_MAX_FIELD) $v = substr($v, 0, REV_MAX_FIELD);
        $snap[$f] = $v;
    }
    return $snap;
}

/** 哪些字段变了（用于列表上「这一版动了什么」） */
function rev_changed_fields(array $a, array $b): array {
    $out = [];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
        $x = $a[$k] ?? null; $y = $b[$k] ?? null;
        if (is_array($x)) $x = json_encode($x, JSON_UNESCAPED_UNICODE);
        if (is_array($y)) $y = json_encode($y, JSON_UNESCAPED_UNICODE);
        if ((string)$x !== (string)$y) $out[] = $k;
    }
    return $out;
}

function rev_all(string $type, string $id): array {
    $f = rev_file($type, $id);
    if (is_file($f)) return json_decode((string)file_get_contents($f), true) ?: [];
    return rev_legacy($type, $id);   // 没有新格式时，回读旧的 data/versions/articles/*.json
}

/**
 * 兼容旧版本记录：改造前 admin/article-edit.php 会往 data/versions/articles/{id}.json
 * 写一份「只有正文 + 两个 SEO 字段、且只有后台这一条路径会写」的历史。
 * 那批数据是用户真实的历史，不能因为换了实现就看不见——这里只读不写，
 * 下次保存时新格式接着往后编号。
 */
function rev_legacy(string $type, string $id): array {
    if ($type !== 'article') return [];
    $f = DATA_DIR . '/versions/articles/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $id) . '.json';
    if (!is_file($f)) return [];
    $old = json_decode((string)file_get_contents($f), true) ?: [];
    $out = [];
    foreach ($old as $v) {
        $out[] = [
            'rev'     => (int)($v['version'] ?? (count($out) + 1)),
            'at'      => (string)($v['saved_at'] ?? ''),
            'by'      => '（旧版本记录）',
            'source'  => 'legacy',
            'note'    => '改造前记录，仅含正文与 SEO 字段',
            'changed' => [],
            'data'    => ['content' => (string)($v['content'] ?? ''),
                          'seo_title' => (string)($v['seo_title'] ?? ''),
                          'seo_desc'  => (string)($v['seo_desc'] ?? '')],
        ];
    }
    return $out;
}

/**
 * 记一版。返回新版本号；无实质变化返回 0（不记）。
 * $before 为 null 表示新建。
 */
function rev_record(string $type, string $id, ?array $before, array $after, string $note = ''): int {
    if ($id === '') return 0;
    $snapAfter = rev_snapshot($type, $after);
    if (!$snapAfter) return 0;

    $revs = rev_all($type, $id);

    // 首次记录：先把「改之前」补一版，否则第一次修改就无处可退
    if (!$revs && $before !== null) {
        $snapBefore = rev_snapshot($type, $before);
        if ($snapBefore) {
            $revs[] = ['rev' => 1, 'at' => date('Y-m-d H:i:s'), 'by' => '（改动前）', 'source' => 'baseline',
                       'note' => '首次记录修订前的原始版本', 'changed' => [], 'data' => $snapBefore];
        }
    }

    $last = $revs ? end($revs) : null;
    if ($last && rev_changed_fields((array)$last['data'], $snapAfter) === []) return 0;  // 没有实质变化

    $actor = rev_actor();
    $rev = $last ? ((int)$last['rev'] + 1) : 1;
    $revs[] = [
        'rev'     => $rev,
        'at'      => date('Y-m-d H:i:s'),
        'by'      => $actor['name'],
        'source'  => $actor['source'],
        'note'    => $note,
        'changed' => $last ? rev_changed_fields((array)$last['data'], $snapAfter) : array_keys($snapAfter),
        'data'    => $snapAfter,
    ];
    if (count($revs) > REV_KEEP) $revs = array_slice($revs, -REV_KEEP);

    @file_put_contents(rev_file($type, $id), json_encode($revs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    return $rev;
}

function rev_get(string $type, string $id, int $rev): ?array {
    foreach (rev_all($type, $id) as $r) if ((int)$r['rev'] === $rev) return $r;
    return null;
}

function rev_count(string $type, string $id): int { return count(rev_all($type, $id)); }

/**
 * 还原到某一版。还原本身也会记成新的一版，所以「还原」这个动作也能再撤销。
 * @return array{ok:bool,error:string}
 */
function rev_restore(string $type, string $id, int $rev): array {
    $target = rev_get($type, $id, $rev);
    if (!$target) return ['ok' => false, 'error' => '该版本不存在'];

    $actor = rev_actor();
    $GLOBALS['of_actor'] = ['name' => $actor['name'], 'source' => $actor['source']];

    if ($type === 'article') {
        if (!function_exists('save_article')) return ['ok' => false, 'error' => '保存函数不可用'];
        $cur = function_exists('get_article') ? get_article($id) : null;
        if (!$cur) return ['ok' => false, 'error' => '文章不存在或已删除'];
        $ok = save_article($id, (array)$target['data'] + ['updated_at' => date('Y-m-d H:i:s')]);
    } elseif ($type === 'page') {
        if (!function_exists('save_page_content')) return ['ok' => false, 'error' => '保存函数不可用'];
        $ok = save_page_content($id, (array)$target['data']);
    } else {
        return ['ok' => false, 'error' => '不支持的内容类型：' . $type];
    }
    if (!$ok) return ['ok' => false, 'error' => '写入失败'];

    try {
        require_once __DIR__ . '/AuditLog.php';
        AuditLog::log('内容还原到第 ' . $rev . ' 版', 'content',
            ['type' => $type, 'id' => $id, 'rev' => $rev, 'by' => $actor['name'], 'source' => $actor['source']]);
    } catch (Throwable $e) {}
    return ['ok' => true, 'error' => ''];
}

/** 取两版之间某个字段的行级 diff（复用既有的 VersionDiff） */
function rev_field_diff(string $type, string $id, int $revA, int $revB, string $field): ?array {
    $a = rev_get($type, $id, $revA); $b = rev_get($type, $id, $revB);
    if (!$a || !$b) return null;
    $va = $a['data'][$field] ?? ''; $vb = $b['data'][$field] ?? '';
    if (is_array($va)) $va = json_encode($va, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (is_array($vb)) $vb = json_encode($vb, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    require_once __DIR__ . '/VersionDiff.php';
    return VersionDiff::diff((string)$va, (string)$vb);
}

/** 字段中文名，用于界面 */
function rev_field_label(string $f): string {
    return [
        'title' => '标题', 'content' => '正文', 'excerpt' => '摘要', 'status' => '状态',
        'category' => '分类', 'tags' => '标签', 'cover' => '封面', 'slug' => '短链',
        'author' => '作者', 'blocks' => '区块', 'publish_at' => '定时发布',
        'seo_title' => 'SEO 标题', 'seo_desc' => 'SEO 描述', 'seo_keywords' => 'SEO 关键词',
    ][$f] ?? $f;
}

/** 来源中文名 —— 分清是人改的还是 Agent 改的 */
function rev_source_label(string $s): string {
    return ['admin' => '后台', 'mcp' => 'AI · MCP', 'api' => 'API', 'import' => '导入',
            'cli' => '命令行', 'baseline' => '原始', 'legacy' => '旧记录', 'external' => '外部协作者', 'system' => '系统'][$s] ?? $s;
}

}
