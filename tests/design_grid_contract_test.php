<?php
declare(strict_types=1);
/**
 * Bento 拼接契约 —— 防止「孤块 / 空洞」（2026-09-19 由用户反馈立约）
 *
 *   php tests/design_grid_contract_test.php
 *
 * 背景：bento 用 6 列栅格 + data-w（跨列）/ data-r（跨行）拼贴。
 *   ① 无空洞：按 CSS Grid 稀疏自动放置模拟后，每行都必须被填满
 *   ② 无孤块：任何一行只有 1 张卡 = 视觉孤块（≥3 卡的 bento 一律不允许）
 *   ③ 宽度合法：data-w ∈ [1,6]，data-r ∈ [1,4]
 *
 * 覆盖两处来源：手写页（*.php）与 builder 数据（data/builder-pages.json）。
 */

$ROOT = dirname(__DIR__);
const COLS = 6;

/** 从 HTML 片段里按出现顺序取 (w, r) */
function bg_spans(string $html): array
{
    $out = [];
    if (preg_match_all('/<div([^>]*)>/', $html, $ms, PREG_SET_ORDER) === false) return [];
    foreach ($ms as $m) {
        if (preg_match('/data-w="(\d+)"/', $m[1], $w) !== 1) continue;
        $r = preg_match('/data-r="(\d+)"/', $m[1], $rr) === 1 ? (int) $rr[1] : 1;
        $out[] = [(int) $w[1], max(1, $r)];
    }
    return $out;
}

/**
 * 按 CSS Grid 稀疏自动放置模拟
 * @param list<array{0:int,1:int}> $spans
 * @return array{filled:int,rows:int,holes:int,lone_rows:int}
 */
function bg_simulate(array $spans, int $cols = COLS): array
{
    $occ = [];              // 已占用格
    $cardsPerRow = [];      // 每行的「卡片张数」（跨行卡计入它覆盖的每一行）
    $row = 1; $col = 1;
    foreach ($spans as [$w, $h]) {
        $guard = 0;
        while (true) {
            if (++$guard > 10000) break;
            if ($col + $w - 1 > $cols) { $row++; $col = 1; continue; }
            $clash = false;
            for ($dr = 0; $dr < $h; $dr++) {
                for ($dc = 0; $dc < $w; $dc++) {
                    if (isset($occ[($row + $dr) . ':' . ($col + $dc)])) { $clash = true; break 2; }
                }
            }
            if ($clash) { $col++; continue; }
            for ($dr = 0; $dr < $h; $dr++) {
                for ($dc = 0; $dc < $w; $dc++) $occ[($row + $dr) . ':' . ($col + $dc)] = true;
                $cardsPerRow[$row + $dr] = ($cardsPerRow[$row + $dr] ?? 0) + 1;
            }
            $col += $w;
            break;
        }
    }
    $maxRow = 0; $perRow = [];
    foreach (array_keys($occ) as $k) {
        [$r, $c] = array_map('intval', explode(':', (string) $k));
        $maxRow = max($maxRow, $r);
        $perRow[$r] = ($perRow[$r] ?? 0) + 1;
    }
    $holes = 0;
    for ($r = 1; $r <= $maxRow; $r++) $holes += $cols - ($perRow[$r] ?? 0);
    $lone = 0;
    foreach ($cardsPerRow as $n) if ((int) $n === 1) $lone++;
    return ['filled' => count($occ), 'rows' => $maxRow, 'holes' => $holes, 'lone_rows' => $lone];
}

$pass = 0; $fail = 0;
function bg_check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

echo "Bento 拼接契约\n";

/** 校验一组 spans，逐条断言 */
function bg_verify(string $label, array $spans): void
{
    if ($spans === []) return;
    // ③ 宽度合法
    $bad = array_filter($spans, static fn(array $s): bool => $s[0] < 1 || $s[0] > COLS || $s[1] < 1 || $s[1] > 4);
    bg_check("{$label}：data-w/r 合法", $bad === [], json_encode(array_values($bad)));
    $sim = bg_simulate($spans);
    // ① 无空洞
    bg_check("{$label}：拼接无空洞（{$sim['rows']} 行 / 空洞 {$sim['holes']}）", $sim['holes'] === 0,
        'w=' . json_encode(array_column($spans, 0)));
    // ② 无孤块（≥3 卡才判定，1~2 卡的 bento 允许）
    if (count($spans) >= 3) {
        bg_check("{$label}：无「独占整行的孤块」", (int) $sim['lone_rows'] === 0,
            "孤行 {$sim['lone_rows']} 行；w=" . json_encode(array_column($spans, 0)));
    }
}

/* ── 来源 A：手写页 ── */
$pageHits = 0;
foreach (glob($ROOT . '/*.php') ?: [] as $file) {
    $src = (string) file_get_contents($file);
    if (!str_contains($src, 'class="bento"')) continue;
    $name = basename($file);
    if (preg_match_all('/<div class="bento"[^>]*>(.*?)<\/section>/s', $src, $ms) !== false) {
        foreach ($ms[1] as $i => $seg) {
            $spans = bg_spans($seg);
            if ($spans === []) continue;
            $pageHits++;
            bg_verify("{$name}#{$i}", $spans);
        }
    }
}
bg_check('手写页存在 bento（扫描生效）', $pageHits > 0, "命中 {$pageHits} 处");

/* ── 来源 B：builder 数据 ── */
$bp = $ROOT . '/data/builder-pages.json';
$builderHits = 0;
if (is_file($bp)) {
    $data = json_decode((string) file_get_contents($bp), true) ?: [];
    foreach ((array) $data as $page) {
        foreach ((array) ($page['blocks'] ?? []) as $i => $b) {
            if (((string) ($b['_type'] ?? '')) !== 'bento') continue;
            $spans = bg_spans((string) ($b['content'] ?? ''));
            if ($spans === []) continue;
            $builderHits++;
            bg_verify(((string) ($page['slug'] ?? '?')) . '#' . $i, $spans);
        }
    }
    bg_check('builder 数据存在 bento（扫描生效）', $builderHits > 0, "命中 {$builderHits} 处");
} else {
    echo "  （跳过 builder 数据：文件不存在）\n";
}

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
