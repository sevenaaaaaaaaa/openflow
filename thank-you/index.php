<?php
/** /thank-you/ → thank-you.php（免 rewrite 兜底） */
$_GET['q'] = $_GET['q'] ?? $_GET['slug'] ?? '';
require __DIR__ . '/../thank-you.php';
