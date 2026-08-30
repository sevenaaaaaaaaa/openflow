<?php
/**
 * ContributionPipeline —— 贡献自动三通（AUDIT-06 创新三 / BACKLOG T1-16）
 *
 * 【为什么】OIA 的第三拆是"一次贡献，全平台赋能"：你做的任何东西发布后，
 * 应当自动 ① 进知识库（喂站点 Agent）② 暴露为 MCP 可调用能力 ③ 纳入平台分发。
 * 第①腿已在 T1-4 完成（文章→知识库）；本模块补齐②③，并把三腿收成一个入口。
 *
 * 【设计】三个开关默认全开、可关不可缺（贡献者可选择不公开某一腿）。
 * 分发池 data/ecosystem/distribution.json 是"平台可推荐的贡献物"清单，
 * 供 CDP/MA 与推荐位消费——让创作者什么都不用配，平台替他把东西送到对的人面前。
 */

if (!function_exists('contrib_dist_file')) {

    function contrib_dist_file(): string { return DATA_DIR . '/ecosystem/distribution.json'; }

    function contrib_dist_all(): array {
        $d = function_exists('json_read') ? json_read(contrib_dist_file()) : [];
        return is_array($d) ? $d : [];
    }

    /** 三腿开关（贡献物上可覆盖）。 */
    function contrib_channels(array $item = []): array {
        $c = is_array($item['channels'] ?? null) ? $item['channels'] : [];
        return [
            'knowledge' => !isset($c['knowledge']) || !empty($c['knowledge']),
            'mcp'       => !isset($c['mcp'])       || !empty($c['mcp']),
            'distribute'=> !isset($c['distribute'])|| !empty($c['distribute']),
        ];
    }

    /**
     * 第②腿：暴露为 MCP 可调用能力（登记到分发池并标记 mcp=true）。
     * 真正的调用仍走 mcp-server 的 skills_list / skill_execute / contributions_list。
     */
    function contrib_expose_mcp(array $item): bool {
        return !empty(contrib_channels($item)['mcp']);
    }

    /**
     * 第③腿：纳入平台分发池。幂等（按 kind+id 覆盖）。
     * $item: ['kind'=>'skill|article|product','id','title','summary','author','tags'=>[],'url','channels'=>[]]
     */
    function contrib_distribute(array $item): array {
        $kind = (string)($item['kind'] ?? '');
        $id   = (string)($item['id'] ?? '');
        if ($kind === '' || $id === '') return ['ok' => false, 'error' => 'kind/id 必填'];
        $ch = contrib_channels($item);
        $uid = $kind . ':' . $id;

        $list = contrib_dist_all();
        // 关掉分发 → 从池中移除
        if (!$ch['distribute']) {
            $list = array_values(array_filter($list, fn($x) => ($x['uid'] ?? '') !== $uid));
            if (function_exists('json_write')) { @mkdir(dirname(contrib_dist_file()), 0755, true); json_write(contrib_dist_file(), $list); }
            return ['ok' => true, 'removed' => true];
        }

        $row = [
            'uid' => $uid, 'kind' => $kind, 'id' => $id,
            'title' => trim((string)($item['title'] ?? '')),
            'summary' => mb_substr(trim((string)($item['summary'] ?? '')), 0, 200),
            'author' => (string)($item['author'] ?? ''),
            'tags' => array_values(array_slice((array)($item['tags'] ?? []), 0, 8)),
            'url' => (string)($item['url'] ?? ''),
            'mcp' => $ch['mcp'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $hit = false;
        foreach ($list as &$x) if (($x['uid'] ?? '') === $uid) { $row['created_at'] = $x['created_at'] ?? $row['updated_at']; $x = $row; $hit = true; break; }
        unset($x);
        if (!$hit) { $row['created_at'] = $row['updated_at']; $list[] = $row; }
        if (function_exists('json_write')) { @mkdir(dirname(contrib_dist_file()), 0755, true); json_write(contrib_dist_file(), $list); }
        return ['ok' => true, 'entry' => $row];
    }

    /**
     * 三通入口：一次贡献发布 → 知识库 / MCP / 分发 三腿同时接上。
     * 每腿独立 try/catch：一腿失败不影响其它腿与发布本身。
     */
    function contrib_publish(array $item): array {
        $ch = contrib_channels($item);
        $res = ['knowledge' => false, 'mcp' => false, 'distribute' => false];

        // ① 知识库（复用 T1-4 的幂等 upsert）
        if ($ch['knowledge']) {
            try {
                if (function_exists('knowledge_upsert')) {
                    knowledge_upsert([
                        'source' => 'contribution', 'source_id' => (string)($item['kind'] ?? '') . ':' . (string)($item['id'] ?? ''),
                        'title' => (string)($item['title'] ?? ''),
                        'content' => (string)($item['summary'] ?? ''),
                        'category' => (string)($item['kind'] ?? 'contribution'),
                        'tags' => (array)($item['tags'] ?? []),
                        'url' => (string)($item['url'] ?? ''),
                    ]);
                    $res['knowledge'] = true;
                }
            } catch (\Throwable $e) {}
        }
        // ② MCP
        try { $res['mcp'] = contrib_expose_mcp($item); } catch (\Throwable $e) {}
        // ③ 分发
        try { $r = contrib_distribute($item); $res['distribute'] = !empty($r['ok']) && empty($r['removed']); } catch (\Throwable $e) {}

        return ['ok' => true, 'channels' => $res];
    }

    /**
     * 平台分发：按标签/关键词给"对的人"挑贡献物（供推荐位/MA 消费）。
     * $interests 为画像标签或关键词；无匹配则回落最新。
     */
    function contrib_recommend(array $interests = [], int $limit = 5, ?array $pool = null): array {
        $pool = $pool ?? contrib_dist_all();
        if (!$pool) return [];
        $interests = array_map(fn($s) => mb_strtolower(trim((string)$s)), $interests);
        $scored = [];
        foreach ($pool as $p) {
            $hay = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['summary'] ?? '') . ' ' . implode(' ', (array)($p['tags'] ?? [])));
            $score = 0;
            foreach ($interests as $i) { if ($i !== '' && mb_strpos($hay, $i) !== false) $score++; }
            $scored[] = ['score' => $score, 'item' => $p];
        }
        usort($scored, fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($b['item']['updated_at'] ?? '', $a['item']['updated_at'] ?? ''));
        return array_map(fn($x) => $x['item'], array_slice($scored, 0, $limit));
    }

    /** MCP 可调用的贡献物清单（供 mcp-server 的 contributions_list 用）。 */
    function contrib_mcp_list(int $limit = 50): array {
        $out = [];
        foreach (contrib_dist_all() as $p) {
            if (empty($p['mcp'])) continue;
            $out[] = ['kind' => $p['kind'], 'id' => $p['id'], 'title' => $p['title'], 'summary' => $p['summary'], 'url' => $p['url']];
            if (count($out) >= $limit) break;
        }
        return $out;
    }
}
