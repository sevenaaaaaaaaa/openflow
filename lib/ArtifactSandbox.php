<?php
/**
 * ArtifactSandbox —— 生成物安全体系（AUDIT-06 / BACKLOG T2-9）
 *
 * 【为什么】T1-15 给了「描述即造」的三道护栏（权限声明/静态审查/AI 预审），
 * 但那是**单件**把关。OIA 一旦规模化——人人都在造——还需要三样：
 *   ① 权限模型：生成物运行时只拿到它声明过的能力，未声明即拒（最小权限）
 *   ② 沙箱执行：prompt/tool 型只做受限的数据取用，不允许任意代码执行
 *   ③ 审核队列：需人工的排队、可批准/驳回、决定留痕
 *
 * 【边界诚实】PHP 没有真进程级沙箱；这里做的是**能力白名单 + 受控执行器**：
 * 生成物不执行任意代码，只能调用我们显式提供的少量安全操作。这比"生成 PHP 再
 * include"安全一个数量级，也是这种架构下务实的做法。
 */

if (!function_exists('sandbox_capabilities')) {

    /** 沙箱内允许的操作（白名单即全集，未列出的一律不可用）。 */
    function sandbox_capabilities(): array {
        return [
            'text.template'   => '用变量渲染一段文本模板',
            'content.search'  => '检索站内已发布内容',
            'math.compute'    => '做简单数值计算',
        ];
    }

    /** 生成物声明的权限是否都在白名单内。 */
    function sandbox_check_permissions(array $declared): array {
        $allowed = array_keys(sandbox_capabilities());
        $unknown = array_values(array_diff($declared, $allowed));
        return ['ok' => empty($unknown), 'unknown' => $unknown];
    }

    /**
     * 受控执行：只跑白名单能力，且必须已声明。
     * $artifact: ['permissions'=>[], 'type'=>..., 'content'=>...]
     * $op: 能力名；$args: 参数
     */
    function sandbox_run(array $artifact, string $op, array $args = []): array {
        $caps = sandbox_capabilities();
        if (!isset($caps[$op])) return ['ok' => false, 'error' => "未知能力：{$op}"];

        $declared = array_map('strval', (array)($artifact['permissions'] ?? []));
        if (!in_array($op, $declared, true)) {
            return ['ok' => false, 'error' => "未声明该能力，拒绝执行：{$op}（最小权限原则）"];
        }

        try {
            switch ($op) {
                case 'text.template':
                    $tpl = (string)($args['template'] ?? ($artifact['content'] ?? ''));
                    $vars = (array)($args['vars'] ?? []);
                    $out = preg_replace_callback('/\{([a-zA-Z0-9_]{1,32})\}/', function ($m) use ($vars) {
                        $v = $vars[$m[1]] ?? '';
                        return is_scalar($v) ? htmlspecialchars((string)$v, ENT_QUOTES) : '';
                    }, $tpl);
                    return ['ok' => true, 'result' => mb_substr($out, 0, 5000)];

                case 'content.search':
                    $q = trim((string)($args['q'] ?? ''));
                    if ($q === '') return ['ok' => false, 'error' => '缺少查询词'];
                    $rows = [];
                    if (function_exists('knowledge_search')) {
                        foreach (knowledge_search($q, 3) as $d) {
                            $rows[] = ['title' => (string)($d['title'] ?? ''), 'url' => (string)($d['url'] ?? '')];
                        }
                    }
                    return ['ok' => true, 'result' => $rows];

                case 'math.compute':
                    $a = (float)($args['a'] ?? 0); $b = (float)($args['b'] ?? 0);
                    $fn = (string)($args['fn'] ?? 'add');
                    $map = ['add' => $a + $b, 'sub' => $a - $b, 'mul' => $a * $b,
                            'div' => ($b == 0.0 ? null : $a / $b)];
                    if (!array_key_exists($fn, $map)) return ['ok' => false, 'error' => '不支持的运算'];
                    if ($map[$fn] === null) return ['ok' => false, 'error' => '除数不能为 0'];
                    return ['ok' => true, 'result' => round((float)$map[$fn], 6)];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => '执行异常：' . $e->getMessage()];
        }
        return ['ok' => false, 'error' => '未实现'];
    }

    /* ─────────── 审核队列 ─────────── */

    function sandbox_queue_file(): string { return DATA_DIR . '/ecosystem/review-queue.json'; }
    function sandbox_queue(): array {
        $d = function_exists('json_read') ? json_read(sandbox_queue_file()) : [];
        return is_array($d) ? $d : [];
    }
    function sandbox_queue_save(array $l): void {
        if (function_exists('json_write')) { @mkdir(dirname(sandbox_queue_file()), 0755, true); json_write(sandbox_queue_file(), $l); }
    }

    /** 入队（T1-15 判为 review 的生成物走这里）。幂等：同 artifact_id 只排一次。 */
    function sandbox_enqueue(array $artifact, array $review, string $submitter = ''): array {
        $aid = (string)($artifact['id'] ?? ($artifact['title'] ?? ''));
        if ($aid === '') return ['ok' => false, 'error' => '缺少生成物标识'];
        $q = sandbox_queue();
        foreach ($q as $x) if (($x['artifact_id'] ?? '') === $aid && ($x['status'] ?? '') === 'pending') {
            return ['ok' => true, 'dup' => true, 'item' => $x];
        }
        $row = [
            'id' => 'rq_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4),
            'artifact_id' => $aid,
            'title' => (string)($artifact['title'] ?? ''),
            'submitter' => $submitter,
            'permissions' => array_values((array)($artifact['permissions'] ?? [])),
            'verdict' => (string)($review['verdict'] ?? 'review'),
            'risks' => array_values((array)($review['risks'] ?? [])),
            'notes' => array_values((array)($review['notes'] ?? [])),
            'status' => 'pending', 'decided_by' => '', 'decided_at' => '', 'decision_note' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $q[] = $row;
        sandbox_queue_save($q);
        return ['ok' => true, 'dup' => false, 'item' => $row];
    }

    /** 人工裁决：approve / reject（留痕）。 */
    function sandbox_decide(string $queueId, string $decision, string $by = 'admin', string $note = ''): array {
        if (!in_array($decision, ['approve', 'reject'], true)) return ['ok' => false, 'error' => '非法决定'];
        $q = sandbox_queue(); $hit = null;
        foreach ($q as &$x) {
            if (($x['id'] ?? '') === $queueId) {
                if (($x['status'] ?? '') !== 'pending') return ['ok' => false, 'error' => '该条已处理过'];
                $x['status'] = $decision === 'approve' ? 'approved' : 'rejected';
                $x['decided_by'] = $by; $x['decided_at'] = date('Y-m-d H:i:s');
                $x['decision_note'] = mb_substr($note, 0, 300);
                $hit = $x; break;
            }
        }
        unset($x);
        if (!$hit) return ['ok' => false, 'error' => '未找到该条'];
        sandbox_queue_save($q);
        return ['ok' => true, 'item' => $hit];
    }

    function sandbox_pending(int $limit = 100): array {
        return array_slice(array_values(array_filter(sandbox_queue(), fn($x) => ($x['status'] ?? '') === 'pending')), 0, $limit);
    }
    function sandbox_queue_stats(): array {
        $s = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach (sandbox_queue() as $x) { $st = $x['status'] ?? 'pending'; if (isset($s[$st])) $s[$st]++; }
        return $s;
    }
}
