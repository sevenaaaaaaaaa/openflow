<?php
/**
 * API 文档 — OpenAPI 3.0 JSON
 * GET /api/v1/docs.json
 */
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../lib/ApiDocs.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$spec = ApiDocs::generate();
echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
