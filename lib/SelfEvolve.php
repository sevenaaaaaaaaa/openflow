<?php
/**
 * 自我进化系统 SelfEvolve
 * 让平台定期"体检"前后端数据，自动发现改进点并产出结构化迭代建议。
 *
 * 数据来源：
 *  1. PHP 错误日志（php-error.log）— 捕获运行时 bug
 *  2. 前端 JS 错误（/api/evolution-report.php 上报）— 捕获浏览器端 bug
 *  3. 404 路由统计 — 发现死链/缺失页面
 *  4. 空数据模块 — 发现内容缺失（课程/播客/活动等）
 *  5. 页面性能 — 识别慢页面
 *  6. 审计日志 — 管理员操作洞察
 *
 * 产出：
 *  - 建议清单（按严重度排序）：修复 bug / 补内容 / 优化性能 / 改进交互
 *  - 历史迭代记录（标记已采纳/已解决）
 */
require_once __DIR__ . '/../admin/config.php';

class SelfEvolve {
    private static string $file = DATA_DIR . '/evolution.json';

    /* ─── 数据读取 ─── */

    /** 读取 PHP 错误日志尾部（避免解析整个大文件） */
    public static function readPhpErrors(int $tail = 400): array {
        $path = __DIR__ . '/../php-error.log';
        if (!is_file($path)) return [];
        $lines = file($path);
        if (!$lines) return [];
        $lines = array_slice($lines, -$tail);
        $errors = [];
        foreach ($lines as $line) {
            if (preg_match('/PHP (Fatal error|Warning|Notice|Deprecated|Parse error|TypeError):?\s+(.*?)(?: in |$)/', $line, $m)) {
                $err = ['type' => $m[1], 'msg' => trim($m[2])];
                // 提取文件 + 行号（用于关联诊断）
                if (preg_match('/ in (.+?) on line (\d+)/', $line, $fm)) {
                    $err['file'] = basename($fm[1]);
                    $err['line'] = (int)$fm[2];
                }
                $errors[] = $err;
            }
        }
        return $errors;
    }

    /** 读取前端 JS 错误上报 */
    public static function readJsErrors(): array {
        $file = DATA_DIR . '/evolution-js-errors.json';
        return json_read($file);
    }

    /** 读取 404 记录 */
    public static function read404s(): array {
        $file = DATA_DIR . '/evolution-404.json';
        return json_read($file);
    }

    /* ─── 分析引擎 ─── */

    /** 生成建议清单 */
    public static function analyze(): array {
        $suggestions = [];

        // 1. PHP 错误聚类
        $php = self::readPhpErrors();
        if (!empty($php)) {
            $fatal = array_filter($php, fn($e) => $e['type'] === 'Fatal error' || $e['type'] === 'TypeError' || $e['type'] === 'Parse error');
            $warns = array_filter($php, fn($e) => $e['type'] === 'Warning' || $e['type'] === 'Notice' || $e['type'] === 'Deprecated');
            if (count($fatal) > 0) {
                $first = array_values($fatal)[0];
                $suggestions[] = [
                    'id' => 'bug_fatal', 'severity' => 'critical', 'category' => 'bug',
                    'title' => '检测到 ' . count($fatal) . ' 个致命 PHP 错误',
                    'detail' => $first['msg'],
                    'hint' => '近期最严重：' . $first['type'] . ' — ' . mb_substr($first['msg'], 0, 120),
                    'action' => '查看 php-error.log，定位并修复导致 Fatal/TypeError 的代码路径',
                ];
            }
            if (count($warns) > 0) {
                $suggestions[] = [
                    'id' => 'bug_warn', 'severity' => 'high', 'category' => 'bug',
                    'title' => count($warns) . ' 个 PHP Warning/Notice 待清理',
                    'detail' => '多为数组偏移/未定义键等，建议开启 display_errors 逐一修复或加空值保护',
                    'hint' => 'Warning 占总错误 ' . round(count($warns) / max(1, count($php)) * 100) . '%',
                    'action' => '修复 Undefined array key / Trying to access null 等常见警告',
                ];
            }
        } else {
            $suggestions[] = ['id' => 'ok_php', 'severity' => 'info', 'category' => 'bug', 'title' => 'PHP 错误日志干净', 'detail' => '近期无 PHP 运行时错误', 'action' => '保持现状'];
        }

        // 2. 前端 JS 错误
        $js = self::readJsErrors();
        if (!empty($js)) {
            $recent = array_filter($js, fn($e) => ($e['ts'] ?? 0) > time() - 86400 * 7);
            if (count($recent) > 0) {
                $byMsg = [];
                foreach ($recent as $e) $byMsg[$e['msg'] ?? 'unknown'] = ($byMsg[$e['msg'] ?? 'unknown'] ?? 0) + 1;
                arsort($byMsg);
                $top = array_key_first($byMsg);
                $suggestions[] = [
                    'id' => 'js_error', 'severity' => count($recent) > 20 ? 'high' : 'medium', 'category' => 'bug',
                    'title' => '前端 JS 错误 ' . count($recent) . ' 次（7天）',
                    'detail' => '最频繁：' . mb_substr($top, 0, 100) . '（' . $byMsg[$top] . ' 次）',
                    'hint' => '多为脚本注入、DOM 操作或 API 失败',
                    'action' => '根据错误消息定位前端脚本问题',
                ];
            }
        }

        // 3. 404 路由
        $f404 = self::read404s();
        if (!empty($f404)) {
            arsort($f404);
            $top404 = array_slice($f404, 0, 5, true);
            $suggestions[] = [
                'id' => 'routing_404', 'severity' => 'medium', 'category' => 'routing',
                'title' => count($f404) . ' 个路由产生 404',
                'detail' => '高频：' . implode(', ', array_map(fn($k, $v) => "$k($v)", array_keys($top404), $top404)),
                'hint' => '可能是死链、伪静态规则缺失或详情页数据为空',
                'action' => '检查 404 路由对应的页面/数据是否缺失',
            ];
        }

        // 4. 空数据模块
        $emptyModules = self::findEmptyModules();
        if (!empty($emptyModules)) {
            $suggestions[] = [
                'id' => 'content_empty', 'severity' => 'high', 'category' => 'content',
                'title' => count($emptyModules) . ' 个内容模块为空',
                'detail' => implode('、', $emptyModules),
                'hint' => '空模块会导致详情页 404 或页面显得空',
                'action' => '补充这些模块的内容（见各模块管理页）',
            ];
        }

        // 5. 前端性能信号（从 JS 上报的加载时间）
        $perf = self::readPerfData();
        if (!empty($perf)) {
            $slow = array_filter($perf, fn($p) => ($p['load_ms'] ?? 0) > 3000);
            if (count($slow) > 0) {
                $pages = [];
                foreach ($slow as $p) $pages[$p['page'] ?? '?'] = ($pages[$p['page'] ?? '?'] ?? 0) + 1;
                arsort($pages);
                $suggestions[] = [
                    'id' => 'perf_slow', 'severity' => 'medium', 'category' => 'perf',
                    'title' => count($slow) . ' 次慢加载（>3s）',
                    'detail' => '慢页面：' . implode(', ', array_map(fn($k, $v) => "$k($v)", array_keys($pages), $pages)),
                    'hint' => '多为 API 串行、脚本阻塞或大资源',
                    'action' => '启用页面缓存 / 并行化 API / 压缩资源',
                ];
            }
        }

        // 6. 跨数据关联诊断（症状链，定位根因）
        $correlations = self::diagnoseCorrelations($suggestions);
        $suggestions = array_merge($suggestions, $correlations);

        return $suggestions;
    }

    /** 发现空数据模块 */
    public static function findEmptyModules(): array {
        $empty = [];
        $checks = [
            '课程' => DATA_DIR . '/courses/index.json',
            '播客' => DATA_DIR . '/podcasts.json',
            '活动' => DATA_DIR . '/events/index.json',
            '咨询师' => DATA_DIR . '/consultation/mentors.json',
            'Skill' => DATA_DIR . '/skills/index.json',
            '资料' => DATA_DIR . '/downloads.json',
            '专题' => DATA_DIR . '/topics.json',
        ];
        foreach ($checks as $name => $path) {
            $data = json_read($path);
            $count = 0;
            if (is_array($data)) {
                $items = $data['items'] ?? $data;
                $count = is_array($items) ? count($items) : 0;
            }
            if ($count === 0) $empty[] = $name;
        }
        return $empty;
    }

    /** 读取前端性能上报 */
    public static function readPerfData(): array {
        $file = DATA_DIR . '/evolution-perf.json';
        return json_read($file);
    }

    /**
     * 跨数据关联诊断 — 把多个信号关联成"症状链"，定位根因而非表象。
     * 例：空模块「课程」+ 404 路由 /course/* = 同一根因（课程数据为空）
     */
    public static function diagnoseCorrelations(array $suggestions): array {
        $empty = self::findEmptyModules();
        $f404 = self::read404s();
        $php = self::readPhpErrors();
        $correlations = [];

        // 关联 1：空模块 ↔ 对应 404 路由
        $moduleRoutes = [
            '课程' => ['/course', 'course-player', 'courses'],
            '播客' => ['/podcasts', 'podcasts'],
            '活动' => ['/event', 'events'],
            '咨询师' => ['/consultation', 'consultation'],
            'Skill' => ['/marketplace', 'skill'],
            '资料' => ['/download', 'downloads'],
            '专题' => ['/topic', 'topics'],
        ];
        foreach ($empty as $mod) {
            $routes = $moduleRoutes[$mod] ?? [];
            $hit404 = [];
            foreach ($f404 as $path => $cnt) {
                foreach ($routes as $r) if (stripos($path, $r) !== false) $hit404[$path] = $cnt;
            }
            if (!empty($hit404)) {
                $correlations[] = [
                    'id' => 'corr_' . $mod,
                    'severity' => 'high', 'category' => 'correlation',
                    'title' => '症状链：' . $mod . '模块为空 → 详情页 404',
                    'detail' => $mod . ' 数据为 0，导致 ' . implode('、', array_map(fn($k, $v) => "$k($v)", array_keys($hit404), $hit404)) . ' 等路由 404',
                    'hint' => '根因是「' . $mod . '」数据缺失，补数据即可同时解决 404',
                    'action' => '在 ' . $mod . ' 管理页补充内容（一劳永逸解决关联 404）',
                    'correlated_ids' => ['content_empty', 'routing_404'],
                ];
            }
        }

        // 关联 2：高频错误文件 ↔ 相关页面（定位 bug 到具体文件）
        $fileCount = [];
        foreach ($php as $e) {
            if (!empty($e['file'])) $fileCount[$e['file']] = ($fileCount[$e['file']] ?? 0) + 1;
        }
        arsort($fileCount);
        $topFile = array_key_first($fileCount);
        if ($topFile && ($fileCount[$topFile] ?? 0) >= 5) {
            $correlations[] = [
                'id' => 'corr_file_' . $topFile,
                'severity' => 'critical', 'category' => 'correlation',
                'title' => '高频错误源：' . $topFile . '（' . $fileCount[$topFile] . ' 次）',
                'detail' => '文件 ' . $topFile . ' 是近期错误集中点，需优先排查',
                'hint' => '同一文件反复报错通常是逻辑缺陷或数据边界未处理',
                'action' => '打开 ' . $topFile . ' 排查对应代码路径',
                'correlated_ids' => ['bug_fatal', 'bug_warn'],
            ];
        }

        return $correlations;
    }

    /* ─── 记录与状态 ─── */

    /** 获取（或初始化）进化记录 */
    public static function state(): array {
        $s = json_read(self::$file);
        if (empty($s)) {
            $s = ['suggestions' => [], 'history' => [], 'last_scan' => 0, 'scan_count' => 0];
            json_write(self::$file, $s);
        }
        return $s;
    }

    /** 运行一次完整扫描，合并新建议，返回本次新增数 */
    public static function runScan(): array {
        $state = self::state();
        $suggestions = self::analyze();

        // 去重合并：按 id 保留最新，移除旧的
        $existing = [];
        foreach ($state['suggestions'] as $s) $existing[$s['id']] = $s;

        $newCount = 0;
        foreach ($suggestions as $s) {
            if (!isset($existing[$s['id']])) $newCount++;
            $s['first_seen'] = $existing[$s['id']]['first_seen'] ?? date('Y-m-d H:i:s');
            $s['seen_count'] = ($existing[$s['id']]['seen_count'] ?? 0) + 1;
            $s['status'] = $existing[$s['id']]['status'] ?? 'open';
            $s['resolved_at'] = $existing[$s['id']]['resolved_at'] ?? null;
            $existing[$s['id']] = $s;
        }
        // 已解决的保留（供历史），未解决的更新为最新
        $state['suggestions'] = array_values($existing);
        $state['last_scan'] = time();
        $state['scan_count'] = ($state['scan_count'] ?? 0) + 1;

        // 审计
        try { AuditLog::log('self_evolve_scan', 'system', ['new' => $newCount, 'total' => count($state['suggestions'])]); } catch (\Throwable $e) {}
        // 时间线
        try { GrowthEngine::timeline('scan', "第 " . ($state['scan_count']) . " 次体检", "新增 {$newCount} 条建议"); } catch (\Throwable $e) {}

        json_write(self::$file, $state);
        return ['new' => $newCount, 'total' => count($state['suggestions'])];
    }

    /** 标记建议为已解决/已采纳，记录到历史 */
    public static function resolve(string $id, string $note = ''): bool {
        $state = self::state();
        foreach ($state['suggestions'] as &$s) {
            if ($s['id'] === $id) {
                $s['status'] = 'resolved';
                $s['resolved_at'] = date('Y-m-d H:i:s');
                $s['resolve_note'] = $note;
                $state['history'][] = [
                    'id' => $id, 'title' => $s['title'], 'category' => $s['category'],
                    'resolved_at' => $s['resolved_at'], 'note' => $note,
                    'snapshot' => self::snapshotSignals(), // 修复验证快照
                ];
                // 通知生长引擎：这是一次"采纳"，影响个性权重
                try { GrowthEngine::suggestionResolved($id, $s['category'] ?? 'other'); } catch (\Throwable $e) {}
                try { GrowthEngine::timeline('resolve', "采纳建议：" . $s['title'], $note); } catch (\Throwable $e) {}
                break;
            }
        }
        unset($s);
        json_write(self::$file, $state);
        return true;
    }

    /** 归档：移除已解决的建议（保留历史） */
    public static function archiveResolved(): int {
        $state = self::state();
        $keep = array_filter($state['suggestions'], fn($s) => $s['status'] !== 'resolved');
        $removed = count($state['suggestions']) - count($keep);
        $state['suggestions'] = array_values($keep);
        json_write(self::$file, $state);
        return $removed;
    }

    /**
     * 建议过期：超过 N 天未处理且非严重问题，自动降级/归档
     * 严重(critical)永不自动归档，仅降级提示
     * @return array [archived, degraded]
     */
    public static function expireStale(int $days = 30): array {
        $state = self::state();
        $now = time();
        $archived = 0;
        $degraded = 0;
        foreach ($state['suggestions'] as &$s) {
            if (($s['status'] ?? 'open') !== 'open') continue;
            $first = strtotime($s['first_seen'] ?? '');
            if (!$first) continue;
            $ageDays = ($now - $first) / 86400;
            if ($ageDays < $days) continue;

            if (($s['severity'] ?? '') === 'critical') {
                // 严重问题不归档，只降级为"长期未处理"标记
                if (empty($s['stale'])) { $s['stale'] = true; $degraded++; }
            } else {
                // 非严重：归档
                $s['status'] = 'archived';
                $s['archived_at'] = date('Y-m-d H:i:s');
                $archived++;
                try { GrowthEngine::timeline('expire', "建议过期归档：" . $s['title']); } catch (\Throwable $e) {}
            }
        }
        unset($s);
        json_write(self::$file, $state);
        return ['archived' => $archived, 'degraded' => $degraded];
    }

    /**
     * 建议转待办：把一条建议转成 tasks 任务（写入 tasks.json）
     * @return array [ok, task_id, error]
     */
    public static function convertToTask(string $id, string $assignee = ''): array {
        $state = self::state();
        $target = null;
        foreach ($state['suggestions'] as $s) {
            if ($s['id'] === $id) { $target = $s; break; }
        }
        if (!$target) return ['ok' => false, 'error' => '建议不存在'];

        $users = get_users();
        if (empty($assignee) || !isset($users[$assignee])) {
            // 默认分配给 admin 角色第一个用户
            foreach ($users as $un => $u) {
                if (($u['role'] ?? '') === 'admin') { $assignee = $un; break; }
            }
        }
        if (empty($assignee)) return ['ok' => false, 'error' => '无可用负责人'];

        $tasks = json_read(DATA_DIR . '/tasks.json');
        $task = [
            'id' => 'task_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'title' => $target['title'],
            'description' => ($target['detail'] ?? '') . "\n\n→ 建议动作：" . ($target['action'] ?? ''),
            'assignee' => $assignee,
            'assigner' => 'self-evolve',
            'priority' => $target['severity'] === 'critical' ? 'high' : ($target['severity'] === 'high' ? 'high' : 'medium'),
            'status' => 'pending',
            'progress' => 0,
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'comments' => [],
            'source' => 'evolution',
            'source_id' => $id,
            'created_at' => date('Y-m-d H:i:s'),
            'completed_at' => '',
        ];
        // 去重：同建议不重复建任务
        foreach ($tasks as $t) {
            if (($t['source_id'] ?? '') === $id && ($t['status'] ?? '') !== 'cancelled') {
                return ['ok' => false, 'error' => '该建议已存在任务'];
            }
        }
        $tasks[] = $task;
        json_write(DATA_DIR . '/tasks.json', $tasks);
        notify('进化', '进化建议已转为任务', $target['title'], 'tasks.php');
        return ['ok' => true, 'task_id' => $task['id']];
    }

    /**
     * 修复验证：采纳后复扫，确认同类问题是否消失
     * 记录采纳时的信号指纹，供下次扫描对比
     */
    public static function snapshotSignals(): array {
        return [
            'fatal_count' => count(array_filter(self::readPhpErrors(), fn($e) => in_array($e['type'], ['Fatal error', 'TypeError', 'Parse error']))),
            'warn_count' => count(array_filter(self::readPhpErrors(), fn($e) => in_array($e['type'], ['Warning', 'Notice', 'Deprecated']))),
            'empty_modules' => count(self::findEmptyModules()),
            'js_errors' => count(self::readJsErrors()),
            'ts' => time(),
        ];
    }

    /**
     * 验证最近采纳的修复是否生效：对比采纳时快照 vs 当前信号
     * @return array [{title, resolved_at, before, after, improved}]
     */
    public static function verifyRecentResolutions(): array {
        $state = self::state();
        $now = self::snapshotSignals();
        $results = [];
        foreach (array_slice($state['history'] ?? [], -10) as $h) {
            $snap = $h['snapshot'] ?? null;
            if (!$snap) continue;
            $improved = false;
            $delta = 0;
            switch ($h['category'] ?? '') {
                case 'bug':
                    $delta = ($snap['fatal_count'] ?? 0) - $now['fatal_count'] + ($snap['warn_count'] ?? 0) - $now['warn_count'];
                    $improved = $delta > 0;
                    break;
                case 'content':
                    $delta = ($snap['empty_modules'] ?? 0) - $now['empty_modules'];
                    $improved = $delta > 0;
                    break;
                default:
                    $improved = null; // 无法量化
            }
            $results[] = [
                'title' => $h['title'], 'resolved_at' => $h['resolved_at'],
                'category' => $h['category'], 'improved' => $improved, 'delta' => $delta,
            ];
        }
        return $results;
    }

    /**
     * 价值排序：严重度 × 个性权重 × 复发趋势 × 影响面
     *  - 严重度：critical/high/medium/info 基础分
     *  - 个性权重：生长引擎（采纳/访问/忽略）
     *  - 复发趋势：建议被扫描到的次数越多，说明问题持续，加分
     *  - 影响面：关联诊断（症状链）视为根因，加分
     */
    public static function personalizedOrder(array $suggestions): array {
        $weighted = array_map(function ($s) {
            try {
                $w = GrowthEngine::weightFor($s['category'] ?? 'other');
                $s['personal_weight'] = $w;
                $sev = ['critical' => 100, 'high' => 70, 'medium' => 40, 'info' => 10];
                $base = $sev[$s['severity'] ?? 'medium'] ?? 40;

                // 复发趋势：seen_count 越多，问题越顽固
                $recurrence = min(15, (($s['seen_count'] ?? 1) - 1) * 5);

                // 影响面：关联诊断（根因）加分
                $impact = !empty($s['correlated_ids']) ? 12 : 0;

                // 价值分 = 严重度 + 个性权重×25 + 复发 + 影响面
                $s['score'] = $base + $w * 25 + $recurrence + $impact;
                $s['recurrence'] = $recurrence;
                $s['impact'] = $impact;
            } catch (\Throwable $e) {
                $s['score'] = 50;
                $s['personal_weight'] = 0;
                $s['recurrence'] = 0;
                $s['impact'] = 0;
            }
            return $s;
        }, $suggestions);
        usort($weighted, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        return $weighted;
    }
}
