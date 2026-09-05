<?php
/**
 * CDP 默认规则种子数据 —— 让深引擎（分群/自动打标/评分/生命周期）开箱即用
 *
 * 用法：php bin/seed-cdp.php [--force]
 * 安全：data/cdp/*.json 已有数据时跳过，除非 --force（覆盖前自动 .bak）
 * 效果：写入 lifecycle.json / tag_rules.json / scoring_rules.json / segments.json，
 *       与 lib/CdpDefaults.php 的默认规则一致（引擎空则回退默认，这里是让后台可见可编辑）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CdpDefaults.php';

$force = in_array('--force', $argv ?? [], true);
$dir = DATA_DIR . '/cdp';
@mkdir($dir, 0775, true);

$targets = [
    'lifecycle'     => cdp_default_lifecycle(),
    'tag_rules'     => cdp_default_tag_rules(),
    'scoring_rules' => cdp_default_scoring_rules(),
    'segments'      => cdp_default_segments(),
];

foreach ($targets as $name => $data) {
    $file = $dir . '/' . $name . '.json';
    $existing = json_read($file);
    if (!empty($existing) && !$force) { echo "⌛ {$name}.json 已有数据，跳过（--force 覆盖）\n"; continue; }
    if (!empty($existing) && $force && is_file($file)) { @copy($file, $file . '.bak-' . date('YmdHis')); }
    json_write($file, $data);
    echo "✓ 写入 {$name}.json（" . (is_array($data) ? count($data) : 0) . " 条）\n";
}

echo "\n完成。可在 管理后台 /xmp/cdp?tab=segments 查看预设分群，/xmp/cdp?tab=insights 看分析。\n";
