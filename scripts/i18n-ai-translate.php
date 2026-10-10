<?php
/**
 * 分段字典批量机翻（走站点已配置的 AI 供应商，只能在服务器上跑 —— 密钥不下发本地）
 *
 *   php scripts/i18n-ai-translate.php --todo /tmp/todo.json --out /tmp/out.shard0.jsonl [--shard=0/4] [--batch=20]
 *
 * todo.json：{ "中文原文": ["en","ja",...缺的语言] }
 * out：JSONL，每行 {"src":"中文原文","en":"...","ja":"..."}；已存在于 out 的 src 自动跳过（可断点续跑）
 *
 * 质量闸（脚本内置，失败的批次最多重试 3 次，仍不过的 src 不落盘，写入 out.fail.json 留给人工）：
 *   - 每个片段必须返回所有被请求的语言
 *   - en/ko/ru/fr 译文不得残留汉字（品牌名统一拉丁写法）
 *   - 原文里的拉丁词（产品名/缩写）必须出现在译文里（防 AI 丢信息）
 *   - 译文里不得出现旧品牌「芭乐派」
 * 额度熔断沿用 AiBudget（用尽即停，不烧钱）。
 */
if (PHP_SAPI !== 'cli') exit(1);
$ROOT = getenv('OF_ROOT') ?: dirname(__DIR__);   // 服务器上临时跑时：OF_ROOT=/www/wwwroot/nownexts_com
require_once $ROOT . '/lib/AiCenter.php';
require_once $ROOT . '/lib/I18nContent.php';

$opt = getopt('', ['todo:', 'out:', 'shard::', 'batch::']);
$todo = json_decode((string)file_get_contents($opt['todo'] ?? ''), true);
$outFile = $opt['out'] ?? '';
if (!is_array($todo) || $outFile === '') { fwrite(STDERR, "用法: --todo f.json --out f.jsonl [--shard=i/n] [--batch=20]\n"); exit(2); }
[$si, $sn] = array_map('intval', explode('/', $opt['shard'] ?? '0/1') + [0, 1]);
$batchSize = (int)($opt['batch'] ?? 20);

// 已完成的跳过
$done = [];
if (is_file($outFile)) foreach (file($outFile) as $l) { $r = json_decode($l, true); if (isset($r['src'])) $done[$r['src']] = true; }

$keys = array_keys($todo);
$mine = [];
foreach ($keys as $i => $k) if ($i % $sn === $si && !isset($done[$k])) $mine[] = $k;
echo "shard {$si}/{$sn}: todo " . count($mine) . "\n";

$LANG_NAME = ['en' => 'English', 'ja' => 'Japanese (日本語)', 'ko' => 'Korean (한국어)', 'ru' => 'Russian (Русский)', 'fr' => 'French (Français)'];
$NO_CJK = ['en', 'ko', 'ru', 'fr'];

$SYSTEM = <<<'TXT'
你是一名资深的软件/SaaS 产品本地化译者，负责把中文官网文案翻译成目标语言。风格：专业、简洁、自然的营销文案，不逐字直译，不增删信息，保持原有的标点结构、emoji、换行(\n)和数字。

品牌与术语（必须严格遵守）：
- 「林下 Understory」是品牌/产品矩阵名：en/ko/ru/fr 写 `Understory`；ja 写 `林下 Understory`。
- 「OpenFlow」是产品名，所有语言保持 `OpenFlow`，不翻译。「增长」= growth（ja 成長 / ko 성장 / ru рост / fr croissance）。
- 产品名一律用拉丁写法：鹿蕊 → Litmus，松萝 → Liana，林可兔 → LinkTo，零真 → ZeroZen，ConFlow、MFlow、WebsFlow（魔块）、UserLoop、inFlow、PayFlow、LearnFlow 保持原样。
- 三层结构：入口层 / 进阶层 / 工作台层 → en: Entry layer / Advanced layer / Studio layer；ja: エントリー層 / 発展層 / Studio層；ko: 입구층 / 심화층 / Studio층；ru: Входной слой / Продвинутый слой / Слой Studio；fr: Couche d'entrée / Couche avancée / Couche Studio。
- 「一人公司」= solo company / one-person company（ja 一人会社 / ko 1인 기업 / ru компания одного человека / fr entreprise unipersonnelle）。
- API、CRM、CDP、Agent、AI、SEO、GitHub、REST、MCP、rsync、Apache 等技术词保持原样。
- 不要翻译 URL、代码、以 / 开头的路径、`{}`/`%s` 占位符；保持原样。

输出：只输出一个 JSON 对象，不要任何解释或代码块标记。输入是 {"编号": "中文原文"}；输出必须是 {"编号": {"语言代码": "译文", ...}}，语言代码只能是请求的那几个，每个编号都要有。
TXT;

function ai_batch(string $sys, array $items, array $langs, array $LANG_NAME): ?array {
    $langDesc = implode('、', array_map(fn($l) => "$l = {$LANG_NAME[$l]}", $langs));
    $user = "目标语言：$langDesc\n请翻译下面这些中文片段：\n" . json_encode($items, JSON_UNESCAPED_UNICODE);
    $r = AiCenter::json($sys, $user, ['feature' => 'i18n-translate', 'tier' => 'batch', 'manual' => true, 'temperature' => 0.2, 'timeout' => 170]);
    if (empty($r['ok'])) {
        $err = (string)($r['error'] ?? '');
        if (strpos($err, '上限') !== false) { fwrite(STDERR, "budget stop: {$err}\n"); exit(3); }
        fwrite(STDERR, '  AI error: ' . mb_substr($err, 0, 120) . "\n");
        return null;
    }
    return $r['data'];
}

function check_one(string $src, string $loc, string $tr, array $NO_CJK): ?string {
    $tr = trim($tr);
    if ($tr === '') return '空';
    if (in_array($loc, $NO_CJK, true) && i18n_has_cjk($tr)) return '残留汉字';
    if (mb_strpos($tr, '芭乐派') !== false) return '旧品牌';
    // 原文里的拉丁词（产品名/缩写）必须保留；纯数字不强制（可能被本地化格式化）
    if (preg_match_all('/[A-Za-z][A-Za-z0-9.\-]{2,}/u', $src, $m)) {
        foreach (array_unique($m[0]) as $tok) {
            if (stripos($tr, $tok) === false) return "丢失 $tok";
        }
    }
    return null;
}

$out = fopen($outFile, 'a');
$total = 0; $fail = [];
$nb = (int)ceil(count($mine) / $batchSize);
foreach (array_chunk($mine, $batchSize) as $bi => $chunk) {
    $items = []; $need = [];
    foreach ($chunk as $i => $src) { $items[(string)($i + 1)] = $src; foreach ($todo[$src] as $l) $need[$l] = true; }
    $langs = array_keys($need);
    $idToSrc = $items;
    $pending = $items;
    for ($try = 0; $try < 3 && $pending; $try++) {
        $res = ai_batch($SYSTEM, $pending, $langs, $LANG_NAME);
        if (!is_array($res)) { sleep(2); continue; }
        foreach ($pending as $id => $src) {
            $row = $res[$id] ?? null;
            if (!is_array($row)) continue;
            $ok = true; $rec = ['src' => $src];
            foreach ($todo[$src] as $l) {
                $tr = isset($row[$l]) && is_string($row[$l]) ? $row[$l] : '';
                $why = check_one($src, $l, $tr, $NO_CJK);
                if ($why) { $ok = false; if ($try === 2) $fail[$src][$l] = $why; break; }
                $rec[$l] = trim($tr);
            }
            if ($ok) { fwrite($out, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n"); unset($pending[$id]); $total++; unset($fail[$src]); }
        }
    }
    foreach ($pending as $src) $fail[$src] ??= ['*' => '无有效返回'];
    echo sprintf("  batch %d/%d, saved %d, failed %d\n", $bi + 1, $nb, $total, count($fail));
}
fclose($out);
if ($fail) file_put_contents($outFile . '.fail.json', json_encode($fail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "shard {$si} done: saved {$total}, failed " . count($fail) . "\n";
