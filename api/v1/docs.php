<?php
/**
 * API 文档 — Swagger UI
 * GET /api/v1/docs
 */
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../lib/ApiDocs.php';

header('Content-Type: text/html; charset=utf-8');
ApiDocs::renderHtml();
