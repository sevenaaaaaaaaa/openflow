<?php
/**
 * P1-4 验收：内部知识回流（文章 → 站内知识库）
 *
 *   php tests/knowledge_ingest_test.php
 *
 * 验四件事：
 *   1. 发布文章 → 入库，HTML 被剥成纯文本；
 *   2. 幂等：同一文章再存不重复堆积，而是原地更新（id 不变、内容更新）；
 *   3. 下架/草稿 → 从知识库撤下；
 *   4. upsert/remove 的 source 去重不误伤其它来源。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-kb-test-' . getmypid());
@mkdir(DATA_DIR . '/knowledge', 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

require_once __DIR__ . '/../lib/KnowledgeSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 发布 → 入库 + HTML 剥离 ──\n";
$art = [
    'id' => 'article_1', 'status' => 'published', 'title' => 'AI 增长指南',
    'content' => '<p>第一段<strong>重点</strong></p><p>第二段</p>', 'category' => 'growth',
    'tags' => '增长, AI', 'slug' => 'ai-growth',
];
$r = knowledge_ingest_article($art);
check('返回 upserted', ($r['action'] ?? '') === 'upserted', json_encode($r));
$docs = knowledge_get();
check('知识库有 1 条', count($docs) === 1, '数量=' . count($docs));
check('HTML 已剥成纯文本(块边界留空格)', ($docs[0]['content'] ?? '') === '第一段 重点 第二段', $docs[0]['content'] ?? '');
check('标签字符串已转数组', ($docs[0]['tags'] ?? []) === ['增长', 'AI']);
check('source=article', ($docs[0]['source'] ?? '') === 'article');
check('source_id=article_1', ($docs[0]['source_id'] ?? '') === 'article_1');
$firstId = $docs[0]['id'];

echo "\n── 2. 幂等：再存不重复，原地更新 ──\n";
$art['title'] = 'AI 增长指南（修订）';
$art['content'] = '<p>改过的内容</p>';
knowledge_ingest_article($art);
$docs = knowledge_get();
check('仍然只有 1 条（不重复堆积）', count($docs) === 1, '数量=' . count($docs));
check('id 不变（原地更新）', ($docs[0]['id'] ?? '') === $firstId);
check('标题已更新', ($docs[0]['title'] ?? '') === 'AI 增长指南（修订）');
check('内容已更新', ($docs[0]['content'] ?? '') === '改过的内容');

echo "\n── 3. 下架/草稿 → 撤下 ──\n";
$art['status'] = 'draft';
$r3 = knowledge_ingest_article($art);
check('返回 removed', ($r3['action'] ?? '') === 'removed', json_encode($r3));
check('知识库已空', count(knowledge_get()) === 0);

echo "\n── 4. source 去重不误伤其它来源 ──\n";
knowledge_add(['id' => 'manual_1', 'title' => '手写文档', 'content' => '不该被动']);
knowledge_ingest_article(['id' => 'article_2', 'status' => 'published', 'title' => 'B', 'content' => 'bbb']);
check('共 2 条', count(knowledge_get()) === 2);
$removed = knowledge_remove_source('article', 'article_2');
check('移除 article_2 命中 1 条', $removed === 1);
$left = knowledge_get();
check('手写文档仍在', count($left) === 1 && ($left[0]['id'] ?? '') === 'manual_1');

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR . '/knowledge/*'));
@rmdir(DATA_DIR . '/knowledge'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
