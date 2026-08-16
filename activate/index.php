<?php
/** /activate/ → activate.php（免 rewrite 兜底） */
$_GET['q'] = $_GET['q'] ?? $_GET['slug'] ?? '';
require __DIR__ . '/../activate.php';
