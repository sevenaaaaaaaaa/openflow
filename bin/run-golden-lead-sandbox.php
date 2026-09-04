#!/usr/bin/env php
<?php
require_once dirname(__DIR__).'/lib/GoldenLeadLoopSandbox.php';
echo json_encode(golden_lead_sandbox_run(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
