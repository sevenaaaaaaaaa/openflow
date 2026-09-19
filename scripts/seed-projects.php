#!/usr/bin/env php
<?php
declare(strict_types=1);
/** 幂等种入「产品迭代 · 团队视角试点」项目：php scripts/seed-projects.php */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ProjectSystem.php';
$r = ps_seed_default();
echo ($r['ok'] ?? false)
    ? (($r['created'] ?? false) ? "✅ 已创建项目 {$r['project']}（{$r['tasks']} 条任务）\n" : "ℹ 已有项目，跳过\n")
    : "✗ 失败：{$r['error']}\n";
exit(($r['ok'] ?? false) ? 0 : 1);
