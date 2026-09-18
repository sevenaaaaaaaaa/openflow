<?php
declare(strict_types=1);
/**
 * AgentPost — AI 员工(岗位)体系 v1
 *
 * 岗位 = 有目标(KPI)、有工具边界、有工作节奏、有交班汇报、有考核的持久 Agent。
 * 与「跑一次任务」的 AgentRuntime 区别:岗位常驻,每天交班,按周期被考核,
 * 连续不达标自动降级为「仅建议」——像对待一名真员工。
 *
 * v1 内置「内容编辑」岗位:看热点 → 转选题 → 写草稿(健康分自检) → 向今日主线交班。
 * 全部动作落在 safe 风险级:草稿永远待审,发布永远由人拍板。
 *
 * 数据:data/agent-posts.json(注册表) · data/agent-posts/{id}-shifts.json(交班日志)
 */
require_once __DIR__ . '/AgentRuntime.php';

function agent_posts_file(): string { return DATA_DIR . '/agent-posts.json'; }
function agent_post_shifts_file(string $id): string { return DATA_DIR . '/agent-posts/' . preg_replace('/[^a-z0-9-]/', '', $id) . '-shifts.json'; }

/** 岗位注册表(空则播种默认岗位) */
function agent_posts(): array {
    $posts = json_read(agent_posts_file());
    if (!is_array($posts) || !$posts) {
        $posts = [agent_post_default_content_editor()];
        agent_posts_save($posts);
    }
    return $posts;
}

function agent_posts_save(array $posts): bool {
    foreach ($posts as &$p) $p['updated_at'] = date('Y-m-d H:i:s');
    return json_write(agent_posts_file(), array_values($posts));
}

function agent_post_get(string $id): ?array {
    foreach (agent_posts() as $p) if (($p['id'] ?? '') === $id) return $p;
    return null;
}

/** 默认岗位:内容编辑 —— 踩点 AgentRuntime 风险白名单,只含 safe 能力 */
function agent_post_default_content_editor(): array {
    return [
        'id'          => 'content-editor',
        'name'        => '内容编辑',
        'emoji'       => '✍️',
        'motto'       => '盯热点、出选题、写草稿,每天交班',
        'goal'        => '每周产出 3 篇待审草稿,内容健康分 ≥ 70',
        'kpi'         => ['metric' => 'weekly_drafts', 'target' => 3, 'period_days' => 7, 'min_score' => 70],
        'tools'       => ['create_content'],          // AgentRuntime 白名单子集(全部 safe)
        'risk_policy' => 'safe_only',                 // 永不 publish_content
        'schedule'    => 'daily',
        'status'      => 'active',                    // active | suggest_only | suspended
        'note'        => '',
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ];
}

function agent_post_shifts(string $id): array {
    $d = json_read(agent_post_shifts_file($id));
    return is_array($d) ? $d : [];
}

function agent_post_shifts_save(string $id, array $shifts): bool {
    $dir = dirname(agent_post_shifts_file($id));
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return json_write(agent_post_shifts_file($id), array_slice(array_values($shifts), -120));
}

/** 执行一次日常交班;AI 不可用/无新热点时优雅降级,绝不假成功 */
function agent_post_run(string $id, array $opts = []): array {
    $post = agent_post_get($id);
    if (!$post) return ['ok' => false, 'error' => '岗位不存在'];
    if (($post['status'] ?? '') === 'suspended') return ['ok' => false, 'error' => '岗位已停用'];

    $date = date('Y-m-d');
    $shifts = agent_post_shifts($id);
    foreach ($shifts as $s) if (($s['date'] ?? '') === $date && empty($opts['force'])) {
        return ['ok' => true, 'skipped' => true, 'detail' => '今天已交班', 'shift' => $s];
    }

    $steps = [];
    $step = function (string $name, callable $fn) use (&$steps) {
        $t0 = microtime(true);
        try {
            $r = $fn();
            $r = is_array($r) ? $r : ['ok' => (bool)$r];
            $r['ms'] = (int)round((microtime(true) - $t0) * 1000);
        } catch (Throwable $e) {
            $r = ['ok' => false, 'detail' => '异常: ' . $e->getMessage(), 'ms' => (int)round((microtime(true) - $t0) * 1000)];
        }
        $r['step'] = $name;
        $steps[] = $r;
        return $r;
    };

    /* ① 看热点:取雷达最新未处理话题 */
    $picked = null;
    $step('scan_trends', function () use (&$picked, $shifts) {
        if (!function_exists('trend_radar')) require_once __DIR__ . '/TrendRadar.php';
        $radar = trend_radar();
        $topics = (array)($radar['clusters'] ?? []);
        if (!$topics) $topics = (array)($radar['topics'] ?? []);
        $doneTitles = [];
        foreach ($shifts as $s) if (!empty($s['topic'])) $doneTitles[mb_substr((string)$s['topic'], 0, 40)] = true;
        foreach ($topics as $t) {
            $title = (string)($t['topic'] ?? $t['title'] ?? '');
            if ($title === '' || isset($doneTitles[mb_substr($title, 0, 40)])) continue;
            $picked = ['topic' => $title, 'angle' => (string)($t['angle'] ?? ''), 'why' => (string)($t['why'] ?? ''), 'heat' => $t['heat'] ?? $t['score'] ?? null];
            break;
        }
        return ['ok' => true, 'detail' => $picked ? ('选定: ' . mb_substr($picked['topic'], 0, 30)) : '暂无合适的新热点(已看过的跳过)'];
    });

    /* ② 转选题:进 GEO 选题库 */
    $topicId = '';
    if ($picked) $step('promote_topic', function () use ($picked, &$topicId) {
        if (!function_exists('trend_promote')) require_once __DIR__ . '/TrendRadar.php';
        $r = trend_promote($picked['topic'], $picked['angle'], $picked['why']);
        if (!empty($r['ok'])) { $topicId = $r['topic_id'] ?? ''; return ['ok' => true, 'detail' => '已转入 GEO 选题库']; }
        return ['ok' => false, 'detail' => $r['error'] ?? '转选题失败'];
    });

    /* ③ 写稿:AI 生成草稿(待审)+ 健康分自检;AI 不可用则诚实顺延 */
    $draftId = '';
    $health = null;
    if ($picked) $step('write_draft', function () use ($picked, &$draftId, &$health, $post) {
        if (!function_exists('geo_ai_generate_article')) require_once __DIR__ . '/GeoSystem.php';
        $art = geo_ai_generate_article(['topic' => $picked['topic'], 'angle' => $picked['angle'], 'why' => $picked['why']]);
        if (!$art) return ['ok' => false, 'detail' => 'AI 不可用或生成失败,写稿顺延(不假成功)'];

        $a = [
            'id' => 'article_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'title' => $art['title'],
            'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', (string)$art['title']),
            'content' => $art['content'],
            'editor_mode' => 'richtext',
            'category' => $art['category'] ?? 'insight',
            'tags' => [],
            'cover' => '',
            'author' => ($post['emoji'] ?? '') . ' ' . ($post['name'] ?? 'AI 岗位'),
            'status' => 'draft',                  // 永远待审:岗位只写不发布
            'seo_title' => $art['title'],
            'seo_desc' => $art['excerpt'] ?? '',
            'seo_keywords' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!function_exists('save_article')) return ['ok' => false, 'detail' => 'save_article 不可用'];
        save_article($a['id'], $a);
        $draftId = $a['id'];

        if (function_exists('seo_audit_article')) {
            $audit = seo_audit_article($a);
            $health = ['score' => $audit['score'] ?? null, 'issues' => array_slice((array)($audit['issues'] ?? []), 0, 3)];
        }
        $okScore = !isset($health['score']) || $health['score'] === null || $health['score'] >= ((int)($post['kpi']['min_score'] ?? 0));
        return ['ok' => true, 'detail' => '草稿已入待审' . ($health['score'] !== null ? ",健康分 {$health['score']}" . ($okScore ? '' : '(低于岗位标准,附改进项)') : ''), 'article_id' => $draftId];
    });

    /* ④ 交班:汇总 + 今日主线 + 学习回流 */
    $draftsOk = 0;
    foreach ($steps as $s) if (($s['step'] ?? '') === 'write_draft' && !empty($s['ok'])) $draftsOk++;
    $summaryParts = [];
    if ($picked) $summaryParts[] = '选题「' . mb_substr($picked['topic'], 0, 24) . '」';
    $summaryParts[] = $draftsOk > 0 ? '草稿 ×' . $draftsOk . '(待审)' : '今日未出稿';
    $summary = implode(' · ', $summaryParts);

    $shift = [
        'date' => $date,
        'at' => date('Y-m-d H:i:s'),
        'post' => ['id' => $id, 'name' => $post['name'] ?? $id],
        'topic' => $picked['topic'] ?? '',
        'drafts' => $draftsOk,
        'article_id' => $draftId,
        'health' => $health,
        'summary' => $summary,
        'steps' => $steps,
    ];
    $step('report', function () use ($id, $shift) {
        // 交班呈现走 mainline_src_agent_posts() provider(实时聚合),此处只做学习回流
        if ($shift['drafts'] > 0 && function_exists('growth_learning_verdict')) {
            try { growth_learning_verdict('agent_post', $id, 'done'); } catch (Throwable $e) {}
        }
        return ['ok' => true, 'detail' => '已交班(主线来源:agent_posts)'];
    });

    agent_post_shifts_save($id, array_merge($shifts, [$shift]));
    $eval = agent_post_evaluate($id);
    $shift['kpi'] = $eval;
    return ['ok' => true, 'shift' => $shift];
}

/** KPI 考核:统计周期内出稿数 vs 目标;连续两个周期 <50% 自动降级为「仅建议」 */
function agent_post_evaluate(string $id): array {
    $post = agent_post_get($id);
    if (!$post) return ['ok' => false];
    $kpi = $post['kpi'] ?? [];
    $days = max(1, (int)($kpi['period_days'] ?? 7));
    $target = max(1, (int)($kpi['target'] ?? 3));
    $shifts = agent_post_shifts($id);
    $since = strtotime("-{$days} days");
    $count = 0;
    foreach ($shifts as $s) if (strtotime((string)($s['at'] ?? '')) >= $since) $count += (int)($s['drafts'] ?? 0);
    // 上一周期(用于连续不达标判定)
    $prevCount = 0;
    $prevSince = strtotime('-' . (2 * $days) . ' days');
    foreach ($shifts as $s) { $t = strtotime((string)($s['at'] ?? '')); if ($t >= $prevSince && $t < $since) $prevCount += (int)($s['drafts'] ?? 0); }

    $ratio = round($count / $target, 2);
    $below = $ratio < 0.5;
    $prevBelow = ($prevCount / $target) < 0.5;
    $res = ['ok' => true, 'count' => $count, 'target' => $target, 'ratio' => $ratio, 'met' => $count >= $target];

    if ($below && $prevBelow && ($post['status'] ?? '') === 'active' && count($shifts) >= 4) {
        $res['downgraded'] = true;
        foreach (agent_posts() as $i => $p) if (($p['id'] ?? '') === $id) {
            $posts = agent_posts();
            $posts[$i]['status'] = 'suggest_only';
            $posts[$i]['note'] = date('m-d') . ' 连续两个周期 KPI <50%,自动降级为仅建议;人工确认后可恢复';
            agent_posts_save($posts);
        }
    }
    return $res;
}

/** cron 入口:每个 active 岗位每天交班一次(由 api/cron.php 心跳驱动) */
function agent_posts_cron(): array {
    $out = [];
    foreach (agent_posts() as $p) {
        $id = (string)($p['id'] ?? '');
        if ($id === '' || ($p['status'] ?? '') !== 'active') continue;
        $r = agent_post_run($id);
        $out[$id] = ['skipped' => !empty($r['skipped']), 'ok' => !empty($r['ok']), 'summary' => $r['shift']['summary'] ?? ($r['detail'] ?? '')];
    }
    return $out;
}
