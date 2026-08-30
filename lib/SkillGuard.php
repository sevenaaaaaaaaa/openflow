<?php
/**
 * SkillGuard —— 「描述即造」的安全护栏（AUDIT-06 创新二 / BACKLOG T1-15）
 *
 * 【为什么】把开发降到"描述"是 OIA 的技术命门：只有当不会写代码的人也能造出
 * 可执行的能力，"创作者=开发者"才不是空话。但**人人能生成可执行物**的前提是
 * 有护栏——否则一个描述就能造出往外发数据、删文件、执行任意代码的东西。
 *
 * 【三道护栏】
 *   ① 权限声明：扫描生成物需要什么能力（网络/文件/数据库/执行），显式列出给人看；
 *   ② 危险模式静态审查：命中黑名单直接拦（eval/exec/反序列化/凭证外带…）；
 *   ③ AI 安全预审（可选）：规则过了再让 AI 看一眼意图是否与描述相符。
 * 判定：safe（可发布草稿）/ review（需人工看）/ blocked（拒绝）。**永不自动上架。**
 */

if (!function_exists('skillguard_permissions')) {

    /** 可声明的权限。 */
    function skillguard_permissions(): array {
        return [
            'network' => '访问外部网络',
            'files'   => '读写文件',
            'db'      => '访问数据库',
            'exec'    => '执行系统命令',
            'members' => '读取会员/客户数据',
        ];
    }

    /** 从生成物文本推断需要的权限（透明化，给人看）。 */
    function skillguard_detect_permissions(string $code): array {
        $need = [];
        $map = [
            'network' => ['curl_', 'file_get_contents(\'http', 'file_get_contents("http', 'fsockopen', 'fopen(\'http', 'stream_context_create', 'httpPost'],
            'files'   => ['file_put_contents', 'unlink(', 'fwrite', 'mkdir(', 'rename(', 'copy('],
            'db'      => ['Database::', 'PDO', 'mysqli', 'SELECT ', 'INSERT ', 'DELETE ', 'UPDATE '],
            'exec'    => ['shell_exec', 'exec(', 'system(', 'passthru', 'popen', 'proc_open'],
            'members' => ['members/index.json', 'member_current', 'crm.json', 'cdp/profiles'],
        ];
        foreach ($map as $perm => $needles) {
            foreach ($needles as $n) {
                if (stripos($code, $n) !== false) { $need[] = $perm; break; }
            }
        }
        return array_values(array_unique($need));
    }

    /**
     * 危险模式静态审查。返回命中的风险项（空=通过）。
     * 这是硬拦截线：命中即 blocked，不交给 AI 判断。
     */
    function skillguard_scan(string $code): array {
        $hits = [];
        $danger = [
            'eval('            => '动态执行代码（eval）',
            'assert('          => '动态执行代码（assert）',
            'create_function'  => '动态创建函数',
            'unserialize('     => '反序列化（对象注入风险）',
            'shell_exec'       => '执行系统命令',
            'passthru'         => '执行系统命令',
            'proc_open'        => '执行系统命令',
            '`'                => '反引号执行命令',
            '$_ENV'            => '读取环境变量（可能含密钥）',
            'getenv('          => '读取环境变量（可能含密钥）',
            'base64_decode'    => '解码执行（常见混淆手法）',
            'preg_replace(\'/e' => 'preg_replace /e 修饰符',
            '../'              => '路径穿越',
            'DATA_DIR . \'/../'=> '越出数据目录',
        ];
        foreach ($danger as $needle => $label) {
            if (stripos($code, $needle) !== false) $hits[] = $label;
        }
        // 凭证外带：同时出现密钥关键字与外发
        $hasSecret = preg_match('/api[_-]?key|secret|password|token|凭证/i', $code);
        $hasOut    = preg_match('/curl_|file_get_contents\(\s*[\'"]http|fsockopen|httpPost/i', $code);
        if ($hasSecret && $hasOut) $hits[] = '疑似把密钥/凭证发往外部';

        return array_values(array_unique($hits));
    }

    /**
     * 综合审查一个生成物。
     * $artifact: ['title','description','type','content','steps'?,'code'?]
     * 返回 ['verdict'=>'safe|review|blocked','risks'=>[],'permissions'=>[],'notes'=>[]]
     */
    function skillguard_review(array $artifact): array {
        $type = (string)($artifact['type'] ?? 'prompt');
        $code = (string)($artifact['code'] ?? '') . "\n" . (string)($artifact['content'] ?? '');
        foreach ((array)($artifact['steps'] ?? []) as $s) {
            $code .= "\n" . (is_array($s) ? json_encode($s, JSON_UNESCAPED_UNICODE) : (string)$s);
        }

        $risks = skillguard_scan($code);
        $perms = skillguard_detect_permissions($code);
        $notes = [];

        // prompt 型只是文本，风险面最小
        if ($type === 'prompt' && !$risks) {
            $notes[] = '提示词型技能不执行代码，风险面小';
        }
        // 高危权限即使没命中黑名单也要人看
        $highRisk = array_intersect($perms, ['exec', 'files', 'members']);

        if ($risks) {
            $verdict = 'blocked';
        } elseif ($highRisk) {
            $verdict = 'review';
            $notes[] = '申请了高风险权限（' . implode('、', array_map(fn($p) => skillguard_permissions()[$p] ?? $p, $highRisk)) . '），需人工确认';
        } elseif (mb_strlen(trim((string)($artifact['description'] ?? ''))) < 10) {
            $verdict = 'review';
            $notes[] = '描述过短，说不清它做什么';
        } else {
            $verdict = 'safe';
        }

        return ['verdict' => $verdict, 'risks' => $risks, 'permissions' => $perms, 'notes' => $notes];
    }

    /**
     * AI 安全预审（可选第三道）：规则过了之后，让 AI 判断"意图是否与描述相符"。
     * 未配 AI / 失败 → 原判定不变（不因缺 AI 放松，也不因缺 AI 收紧）。
     * 可注入 $GLOBALS['SKILLGUARD_AI_FN']。
     */
    function skillguard_ai_review(array $artifact, array $result): array {
        if ($result['verdict'] === 'blocked') return $result;   // 已拦，无需再问
        try {
            $payload = json_encode([
                'declared' => (string)($artifact['description'] ?? ''),
                'content'  => mb_substr((string)($artifact['content'] ?? ''), 0, 2000),
                'permissions' => $result['permissions'],
            ], JSON_UNESCAPED_UNICODE);

            $verdictText = null;
            if (isset($GLOBALS['SKILLGUARD_AI_FN']) && is_callable($GLOBALS['SKILLGUARD_AI_FN'])) {
                $verdictText = (string)call_user_func($GLOBALS['SKILLGUARD_AI_FN'], $payload);
            } elseif (class_exists('AiCenter') && \AiCenter::isConfigured()) {
                $r = \AiCenter::chat(
                    '你是代码安全审查员。判断这个生成物的实际内容是否与它声称的用途相符，'
                    . '有没有隐藏的越权/外带数据行为。只回一个词：ok 或 suspicious。',
                    $payload, ['max_tokens' => 20]
                );
                $verdictText = trim((string)($r['text'] ?? ''));
            }
            if ($verdictText !== null && stripos($verdictText, 'suspicious') !== false) {
                $result['verdict'] = 'review';
                $result['notes'][] = 'AI 预审认为内容与描述不符，建议人工确认';
            }
        } catch (\Throwable $e) {}
        return $result;
    }

    function skillguard_verdict_label(string $v): string {
        return ['safe' => '可发布（草稿）', 'review' => '需人工确认', 'blocked' => '已拦截'][$v] ?? $v;
    }

    /**
     * 「描述即造」入口：生成 → 审查 → 只在通过时返回可发布草稿。
     * 永远返回草稿态，绝不自动上架。$generator 可注入便于测试。
     */
    function skillguard_build(string $description, string $author = '', ?callable $generator = null): array {
        $description = trim($description);
        if (mb_strlen($description) < 5) return ['ok' => false, 'error' => '描述太短，说清你想要什么'];

        $gen = null;
        try {
            if ($generator) $gen = $generator($description, $author);
            elseif (function_exists('skill_generate')) $gen = skill_generate($description, $author ?: 'OpenFlow');
        } catch (\Throwable $e) { return ['ok' => false, 'error' => '生成失败：' . $e->getMessage()]; }

        if (!is_array($gen) || empty($gen['ok']) || empty($gen['skill'])) {
            return ['ok' => false, 'error' => $gen['error'] ?? 'AI 未配置或生成失败'];
        }
        $skill = $gen['skill'];
        $review = skillguard_ai_review($skill, skillguard_review($skill));

        return [
            'ok' => $review['verdict'] !== 'blocked',
            'verdict' => $review['verdict'],
            'skill' => array_merge($skill, ['status' => 'draft', 'permissions' => $review['permissions']]),
            'risks' => $review['risks'], 'notes' => $review['notes'],
            'error' => $review['verdict'] === 'blocked' ? ('存在安全风险，已拦截：' . implode('；', $review['risks'])) : null,
        ];
    }
}
