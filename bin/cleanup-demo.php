<?php
/**
 * 清理早期跑 demo 的残留（幂等 / 可 dry-run / 先备份）
 *
 *   php bin/cleanup-demo.php --dry     # 只报告，不改
 *   php bin/cleanup-demo.php           # 真删（先备份到 data/backups/demo-cleanup-<ts>/）
 *
 * 清理对象：
 *   - id 含 demo 标记的实体（course_demo_* / dl_demo_* / evt_demo_* / live_demo_* /
 *     landing_demo_* / cmp_demo_* / m_demo_* / demo_*）
 *   - 社区种子帖 post_seed_*
 *   - 引用的文章全部失效的专题（跑 demo 留下的死专题：离职预警/职场能量/内容策略）
 *   - Well Q / 组织幸福 / 积极领导力 等早期品牌残留（专题、落地页、Skill）
 *   - 与已删 demo 实体关联的进度 / 订阅状态
 *
 * 只删"可判定为 demo"的记录，不动真实业务数据。
 */

$base = dirname(__DIR__);
$data = $base . '/data';
$dry = in_array('--dry', $argv ?? [], true);
$stamp = date('Ymd-His');
$backupDir = $data . '/backups/demo-cleanup-' . $stamp;
$report = [];

if (!is_dir($data)) { fwrite(STDERR, "找不到 data/：{$data}\n"); exit(1); }

function cd_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function cd_write(string $f, array $d): void {
    @mkdir(dirname($f), 0755, true);
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
/** 备份原文件（首次改动时） */
function cd_backup(string $f, string $backupDir, array &$done): void {
    if (isset($done[$f])) return;
    $rel = preg_replace('#^.*/data/#', '', $f);
    $dest = $backupDir . '/' . $rel;
    @mkdir(dirname($dest), 0755, true);
    @copy($f, $dest);
    $done[$f] = true;
}

$isDemoId = function ($id): bool {
    $id = (string)$id;
    if ($id === '') return false;
    if (preg_match('/(^|[_-])demo([_-]|$)/i', $id)) return true;
    if (str_starts_with($id, 'post_seed_') || str_starts_with($id, 'seed_')) return true;
    if (preg_match('/^m_demo_/i', $id)) return true;
    return false;
};
$isWellQ = function (array $it): bool {
    $hay = mb_strtolower(($it['title'] ?? '') . ' ' . ($it['name'] ?? '') . ' ' . ($it['slug'] ?? '') . ' ' . ($it['description'] ?? '') . ' ' . implode(' ', (array)($it['tags'] ?? [])));
    foreach (['well q', 'wellq', '组织幸福', '积极领导力', '员工投入度', '积极心理学'] as $t) {
        if (mb_strpos($hay, mb_strtolower($t)) !== false) return true;
    }
    return false;
};

$backedUp = [];

/* ── 1. id 数组型文件：按 demo id 过滤 ── */
$idFiles = [
    'topics.json'            => ['kind' => 'topics'],
    'downloads.json'         => ['kind' => 'flat'],
    'landing-pages.json'     => ['kind' => 'flat'],
    'campaigns.json'         => ['kind' => 'flat'],
    'events/index.json'      => ['kind' => 'flat'],
    'courses/index.json'     => ['kind' => 'flat'],
    'live/index.json'        => ['kind' => 'flat'],
    'community-posts.json'   => ['kind' => 'flat'],
    'skills/index.json'      => ['kind' => 'flat'],
];

foreach ($idFiles as $rel => $opt) {
    $f = $data . '/' . $rel;
    if (!is_file($f)) continue;
    $arr = cd_read($f);
    if (!$arr) continue;
    $kept = []; $dropped = [];
    foreach ($arr as $it) {
        $id = is_array($it) ? ($it['id'] ?? $it['slug'] ?? '') : '';
        $drop = is_array($it) && ($isDemoId($id) || $isWellQ($it));
        // 专题：引用的文章全部不存在 → 死专题，一并清
        if (!$drop && $opt['kind'] === 'topics' && is_array($it)) {
            $refs = (array)($it['article_ids'] ?? []);
            if ($refs) {
                $all = cd_read($data . '/articles/index.json');
                $have = array_flip(array_map(fn($a) => $a['id'] ?? '', $all));
                $valid = 0; foreach ($refs as $r) if (isset($have[$r])) $valid++;
                if ($valid === 0) $drop = true;
            }
        }
        if ($drop) $dropped[] = (is_array($it) ? ($it['title'] ?? $id) : $it);
        else $kept[] = $it;
    }
    if ($dropped) {
        $report[$rel] = $dropped;
        if (!$dry) { cd_backup($f, $backupDir, $backedUp); cd_write($f, $kept); }
    }
}

/* ── 2. 映射型文件：memberId → data（去掉 demo 会员）── */
foreach (['membership/state.json', 'courses/progress.json'] as $rel) {
    $f = $data . '/' . $rel;
    if (!is_file($f)) continue;
    $map = cd_read($f);
    if (!$map) continue;
    $kept = []; $dropped = [];
    foreach ($map as $k => $v) {
        if ($isDemoId($k)) $dropped[] = $k;
        else $kept[$k] = $v;
    }
    if ($dropped) {
        $report[$rel] = $dropped;
        if (!$dry) { cd_backup($f, $backupDir, $backedUp); cd_write($f, $kept); }
    }
}

/* ── 3. 会员表（members.json）里的 demo 会员 ── */
foreach (['members.json', 'members/index.json'] as $rel) {
    $f = $data . '/' . $rel;
    if (!is_file($f)) continue;
    $arr = cd_read($f);
    if (!$arr) continue;
    $kept = []; $dropped = [];
    foreach ($arr as $it) {
        $id = is_array($it) ? ($it['id'] ?? '') : '';
        if ($isDemoId($id)) $dropped[] = $id; else $kept[] = $it;
    }
    if ($dropped) {
        $report[$rel] = $dropped;
        if (!$dry) { cd_backup($f, $backupDir, $backedUp); cd_write($f, $kept); }
    }
}

/* ── 输出 ── */
$total = array_sum(array_map('count', $report));
echo ($dry ? "🔍 DRY-RUN（未改动）\n" : "🧹 已清理\n");
if ($total === 0) { echo "没有发现 demo 残留。\n"; exit(0); }
foreach ($report as $rel => $items) {
    echo "── {$rel}（" . count($items) . "）──\n";
    foreach (array_slice($items, 0, 40) as $t) echo "   - " . (is_string($t) ? $t : json_encode($t, JSON_UNESCAPED_UNICODE)) . "\n";
    if (count($items) > 40) echo "   … 还有 " . (count($items) - 40) . " 项\n";
}
echo "\n合计 " . $total . " 项。";
if (!$dry) echo " 备份：data/backups/demo-cleanup-{$stamp}/";
echo "\n";
