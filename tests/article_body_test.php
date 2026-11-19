<?php
/**
 * 正文输出回归：Markdown 文章必须转成 HTML（P0）
 *   php tests/article_body_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-ab-' . getmypid());
require_once __DIR__ . '/../lib/Markdown.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

$md = "# 为什么要做增长\n\n因为 **流量** 会变贵。\n\n- 降低获客成本\n- 提高复购\n";
$html = article_body_html(['content' => $md, 'editor_mode' => 'markdown']);
check('Markdown 标题 → h2', strpos($html, '<h2>为什么要做增长</h2>') !== false, $html);
check('加粗 → strong', strpos($html, '<strong>流量</strong>') !== false, $html);
check('不残留 ## 语法', strpos($html, '##') === false, $html);
check('列表 → ul/li', strpos($html, '<ul>') !== false && strpos($html, '<li>') !== false, $html);

$rich = '<p>已经 <strong>是 HTML</strong></p>';
check('richtext 原样返回', article_body_html(['content' => $rich, 'editor_mode' => 'richtext']) === $rich);
check('markdown 模式但内容是 HTML → 不二次转换', article_body_html(['content' => $rich, 'editor_mode' => 'markdown']) === $rich);
check('缺省 editor_mode 视为 richtext', article_body_html(['content' => $rich]) === $rich);
check('空内容返回空串', article_body_html(['content' => '']) === '');
check('无字段也不报错', article_body_html([]) === '');

echo "\n通过 {$pass} · 失败 {$fail}\n";
exit($fail > 0 ? 1 : 0);
