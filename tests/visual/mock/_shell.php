<?php
/* 方案对比用静态壳：拿真实 tokens / modules / 外壳渲染，只用于 tests/visual 截图对比，不是站点页面。 */
function mock_head(string $title, string $extraCss = ''): void { ?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$title?></title>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css">
<style><?=$extraCss?></style>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/../../../includes/site-nav.php'; }
function mock_foot(): void { ?>
</main></body></html>
<?php }
