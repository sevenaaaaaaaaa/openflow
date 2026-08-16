<?php
/**
 * PHPUnit bootstrap — 加载项目核心文件
 */

// 加载 Composer 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 加载项目配置（如果 Composer 自动加载未覆盖）
if (!function_exists('json_read')) {
    require_once __DIR__ . '/../admin/config.php';
}

// 加载需要的库
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ProgressSystem.php';
require_once __DIR__ . '/../lib/MembershipSystem.php';
require_once __DIR__ . '/../lib/Database.php';
