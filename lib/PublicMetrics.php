<?php
declare(strict_types=1);
/**
 * PublicMetrics — counts quoted on public pages (API endpoints, MCP tools).
 *
 * Pages must not hard-code these numbers: they drift as soon as code changes.
 * scripts/metrics.php uses the same functions, so docs markers and pages agree.
 */

/** Number of API endpoint files: every *.php under api/ (including api/v1/). */
function of_count_api_endpoints(?string $root = null): int
{
    static $cache = [];
    $root ??= dirname(__DIR__);
    if (isset($cache[$root])) return $cache[$root];
    $n = 0;
    if (is_dir($root . '/api')) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/api', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') $n++;
        }
    }
    return $cache[$root] = $n;
}

/** Number of MCP tools: length of the registry in lib/McpTools.php (shared with mcp-server.php). */
function of_count_mcp_tools(): int
{
    require_once __DIR__ . '/McpTools.php';
    return function_exists('mcp_tools') ? count(mcp_tools()) : 0;
}
