<?php
/**
 * PHPStan 引导：声明**运行时才定义**的常量，避免 75% 的 constant.notFound 噪音。
 * 仅静态分析使用；不进入任何运行时/部署路径。
 */
defined('DATA_DIR')    || define('DATA_DIR', __DIR__ . '/data');
defined('UPLOAD_DIR')  || define('UPLOAD_DIR', __DIR__ . '/uploads');
defined('ROOT_DIR')    || define('ROOT_DIR', __DIR__);
defined('PERMISSIONS') || define('PERMISSIONS', []);
