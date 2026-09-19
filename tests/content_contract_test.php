<?php
declare(strict_types=1);
/**
 * 内容契约 —— 从「读者体感」出发的可执行标准（2026-09-19 立）
 *
 *   php tests/content_contract_test.php
 *
 * 背景：此前验收只看「区块契约(结构/样式)」，导致结构完整但阅读体感为空：
 *   缺节标题、单节文案过薄、纯文字无视觉装置、同一段文案跨页复制。
 * 本契约把「一屏要给读者什么」变成检查项，覆盖三页 demo 的每个 section。
 *
 * 规则（按节）：
 *   C1 每节必须有 H2 标题（首屏可用 H1；shell/main 节豁免）
 *   C2 每节必须有 kicker 或 lead（给这一节一个"为什么看"的理由）
 *   C3 每节必须有至少一个**视觉或结构化装置**：图片 / 窗口 mock / 表格 / tab / 手风琴 /
 *      卡片网格 / 数据条 / 代码块 / ≥3 条列表 / ≥3 个表单字段
 *   C4 节内静态文本 ≥ 120 字（表单/收口等短节可 ≤120，但必须命中 C3）
 *   C5 跨页复用限制：同一节文案（前 80 字）不得出现在 ≥2 个页面
 *      （白名单：诊断表单这类「转化位」允许每页各一份）
 */

$ROOT = dirname(__DIR__);
$PAGES = ['index.php', 'hub-products.php', 'hub-capabilities.php'];

/* 允许跨页复用的节（转化位）：出现多次不算违规 */
const CROSS_PAGE_ALLOW = ['contact'];

/* hero 类节允许用 H1 代替 H2 */
const HERO_IDS = ['hero', 'top', 'product-hero', 'capability-hero'];

$pass = 0; $fail = 0;
function cc(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

function cc_text(string $html): string
{
    $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/s', '', $html) ?? $html;
    $html = preg_replace('/<\?php.*?\?>/s', ' ', $html) ?? $html;
    return trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
}

$sections = [];   // [page, id, metrics]
foreach ($PAGES as $file) {
    $src = (string) file_get_contents($ROOT . '/' . $file);
    $parts = preg_split('/(?=<section[^>]*data-od-id=)/', $src) ?: [];
    foreach ($parts as $seg) {
        if (preg_match('/data-od-id="([^"]+)"/', $seg, $m) !== 1) continue;
        $id = $m[1];
        $body = explode('</section>', $seg)[0];
        $h2 = preg_match('/<h2[^>]*>/', $body) === 1;
        $h1 = preg_match('/<h1[^>]*>/', $body) === 1;
        $kicker = str_contains($body, 'class="kicker"');
        $lead = str_contains($body, 'class="lead"');
        $text = cc_text($body);
        // 视觉 / 结构化装置
        $devices = [
            'img' => substr_count($body, '<img'),
            'spwin' => substr_count($body, 'sp-win'),
            'table' => substr_count($body, '<table'),
            'tabs' => substr_count($body, 'tab-bar'),
            'accordion' => substr_count($body, 'class="fq') + substr_count($body, 'class="hacc"') + substr_count($body, 'class="vacc"'),
            'grid' => substr_count($body, 'link-grid') + substr_count($body, 'toolgrid') + substr_count($body, 'class="bento"') + substr_count($body, 'class="qr') + substr_count($body, 'cmp-wrap'),
            'stats' => substr_count($body, 'class="stats"') + substr_count($body, 'proof') + substr_count($body, 'ticker'),
            'code' => substr_count($body, '<pre') + substr_count($body, 'class="cs-body"'),
            'svg' => substr_count($body, '<svg') >= 3 ? 1 : 0,
            'list' => substr_count($body, '<li') >= 3 ? 1 : 0,
            'rows' => (substr_count($body, 'class="a-row"') + substr_count($body, 'class="rank"')) > 0 ? 1 : 0,
            'fields' => substr_count($body, 'class="inp"') >= 3 ? 1 : 0,
        ];
        $devCount = array_sum(array_map(static fn($v) => $v > 0 ? 1 : 0, $devices));
        $dynamic = str_contains($body, 'foreach (') || str_contains($body, 'foreach(') || str_contains($body, 'endforeach');
        $sections[] = [
            'page' => $file, 'id' => $id, 'h2' => $h2, 'h1' => $h1, 'dynamic' => $dynamic,
            'kicker' => $kicker, 'lead' => $lead, 'text' => $text,
            'textLen' => mb_strlen($text), 'devCount' => $devCount, 'devices' => $devices,
        ];
    }
}

echo "内容契约（读者体感）\n\n";

foreach ($sections as $s) {
    $label = "{$s['page']}:{$s['id']}";
    if ($s['id'] === 'main' || str_starts_with((string) $s['id'], 's-')) continue;   // 外壳/侧栏锚点，豁免
    // C1 标题
    $isHero = in_array($s['id'], HERO_IDS, true);
    cc("{$label}：有节标题", $s['h2'] || ($isHero && $s['h1']), '缺 H2' . ($isHero ? '/H1' : ''));
    // C2 为什么看
    cc("{$label}：有 kicker 或 lead", $s['kicker'] || $s['lead'], '两者都缺');
    // C3 视觉/结构化装置
    cc("{$label}：有视觉或结构化装置", $s['devCount'] > 0, '纯文字且无列表/表单');
    // C4 文本体量：动态节（内容由数据/PHP 循环生成）只要求有装置，不做字数判定
    if (!empty($s['dynamic'])) {
        cc("{$label}：动态节（数据生成）有装置", $s['devCount'] > 0, '装置为 0');
    } else {
        $floor = 120;
        $okLen = $s['textLen'] >= $floor || $s['devCount'] >= 2;   // 文字少但有 ≥2 个装置也算合格
        cc("{$label}：文本 ≥{$floor} 字或装置补齐", $okLen, "仅 {$s['textLen']} 字 / 装置 {$s['devCount']}");
    }
}

/* C5 跨页复用 */
echo "\n跨页复用检查\n";
$bySig = [];
foreach ($sections as $s) {
    if (in_array($s['id'], CROSS_PAGE_ALLOW, true)) continue;
    $sig = mb_substr($s['text'], 0, 80);
    if ($sig === '') continue;
    $bySig[$sig][] = "{$s['page']}:{$s['id']}";
}
foreach ($bySig as $sig => $where) {
    if (count($where) < 2) continue;
    cc('跨页唯一：' . implode(' + ', $where), false, '前 80 字：' . mb_substr($sig, 0, 50) . '…');
}
if (!array_filter($bySig, static fn($w) => count($w) >= 2)) {
    cc('无跨页重复节（转化位白名单除外）', true);
}

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
