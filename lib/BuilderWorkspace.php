<?php
/**
 * BuilderWorkspace —— 参与者工作台 / OIA（AUDIT-06 创新一 / BACKLOG T1-14）
 *
 * 【为什么】现状是三道独立的闸：普通会员 → 申请 developer_status 走审核 →
 * 作者是另一套身份 → 上架商品又一层。一个人想「既写点东西、又做个小工具、
 * 还上架卖」，要跨三道门、被三次审核、在三个后台之间穿梭。
 *
 * 【OIA】合成一个「参与者（Builder）」身份：一加入就**同时**拥有写内容、
 * 做技能/插件、上架变现的能力与入口。审核从"配不配当开发者"降级为
 * **安全护栏**（只审内容与代码安全，不审身份资格）。
 *
 * 本模块提供：身份判定（人人都是 Builder）、我的贡献聚合、能力清单。
 */

if (!function_exists('builder_can_contribute')) {

    /**
     * OIA 核心：只要是登录会员，就是参与者——不再需要"申请开发者"。
     * 仅在被明确封禁时关闭（安全护栏，不是资格门）。
     */
    function builder_can_contribute(?array $member): bool {
        if (!$member) return false;
        if (($member['status'] ?? 'active') === 'banned') return false;
        if (!empty($member['contrib_blocked'])) return false;
        return true;
    }

    /**
     * 参与者一次拥有的三种能力（OIA：一次加入，全平台赋能）。
     * 返回 [key => ['label','desc','url','enabled']]
     */
    function builder_capabilities(?array $member): array {
        $ok = builder_can_contribute($member);
        return [
            'write'  => ['label' => '写内容', 'desc' => '文章 / 知识，发布即进知识库、可被 Agent 调用', 'url' => '/member.php?view=contribute&kind=article', 'enabled' => $ok],
            'build'  => ['label' => '做工具', 'desc' => '用一句话描述生成技能/插件，不用会写代码',   'url' => '/member.php?view=contribute&kind=skill',   'enabled' => $ok],
            'sell'   => ['label' => '上架卖', 'desc' => '把内容或工具打包成商品，平台帮你分发变现',   'url' => '/member.php?view=contribute&kind=product', 'enabled' => $ok],
        ];
    }

    /**
     * 我的贡献聚合（跨内容/技能/商品，一个人一张主页）。
     * $inject 可注入各源（测试）：['articles'=>[], 'skills'=>[], 'products'=>[]]
     */
    function builder_contributions(?array $member, ?array $inject = null): array {
        if (!$member) return ['articles' => [], 'skills' => [], 'products' => [], 'total' => 0];
        $id = (string)($member['id'] ?? '');
        $name = (string)($member['name'] ?? '');
        $email = (string)($member['email'] ?? '');
        $read = fn(string $rel) => function_exists('json_read') ? json_read(DATA_DIR . $rel) : [];
        $mine = function ($rows, array $keys) use ($id, $name, $email) {
            $out = [];
            foreach ((array)$rows as $r) {
                if (!is_array($r)) continue;
                foreach ($keys as $k) {
                    $v = (string)($r[$k] ?? '');
                    if ($v !== '' && ($v === $id || ($name !== '' && $v === $name) || ($email !== '' && strcasecmp($v, $email) === 0))) {
                        $out[] = $r; break;
                    }
                }
            }
            return $out;
        };

        $articles = $mine($inject['articles'] ?? $read('/articles/index.json'), ['author', 'author_id', 'member_id']);
        $skills   = $mine($inject['skills']   ?? $read('/skills/index.json'),   ['submitter', 'author', 'member_id']);
        $products = $mine($inject['products'] ?? $read('/products.json'),       ['author', 'author_name', 'member_id']);

        return [
            'articles' => $articles, 'skills' => $skills, 'products' => $products,
            'total' => count($articles) + count($skills) + count($products),
        ];
    }

    /**
     * 贡献者档案摘要（"我的贡献"主页头部）。
     */
    function builder_profile(?array $member, ?array $contrib = null): array {
        $c = $contrib ?? builder_contributions($member);
        $published = 0;
        foreach ($c['articles'] as $a) if (($a['status'] ?? '') === 'published') $published++;
        foreach ($c['products'] as $p) if (($p['status'] ?? '') === 'active') $published++;
        foreach ($c['skills'] as $s) if (($s['status'] ?? '') === 'approved') $published++;
        return [
            'name' => (string)($member['name'] ?? ($member['email'] ?? '参与者')),
            'is_builder' => builder_can_contribute($member),
            'counts' => ['article' => count($c['articles']), 'skill' => count($c['skills']), 'product' => count($c['products'])],
            'total' => $c['total'],
            'published' => $published,
            'next_step' => builder_next_step($c),
        ];
    }

    /** 下一步建议：引导参与者用满三种能力（OIA 的"一次加入全平台赋能"）。 */
    function builder_next_step(array $c): string {
        if (count($c['articles']) === 0) return '写第一篇内容——发布即进知识库，也能被站点 Agent 引用。';
        if (count($c['skills']) === 0)   return '把你会的做成一个技能——描述一句话，AI 帮你生成，不用写代码。';
        if (count($c['products']) === 0) return '把已有的内容或技能打包上架，平台帮你分发和变现。';
        return '三种能力都用上了。接下来看「我的买家」，按增长建议放大。';
    }
}
