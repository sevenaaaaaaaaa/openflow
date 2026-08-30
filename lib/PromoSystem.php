<?php
/**
 * PromoSystem —— 统一站内营销投放（Touch）
 *
 * 把原来散在三处、且都是"单例"的站内营销收成一个模型：
 *   - conversion.json：一条通知条 + 一个弹窗 + 一个底部 CTA（各只能有一个）
 *   - AdSystem：5 个写死的广告位
 *   - page-modules：inline 模块
 * 统一成「投放（promo）」这一个实体，一种类型可以有任意多条，每条都能定
 * **页面 × 位置 × 类型 × 人群 × 定时 × 频次**。这是站内营销，和 MA 的
 * 触发式流程不是一回事，所以留在 Touch 内容大栏目下。
 *
 * 匹配逻辑（promo_match）是纯函数，吃一个 context 数组，便于单测；
 * api/promo.php 把真实请求上下文喂进来，前端 inject.js 负责按频次展示与埋点。
 */
require_once __DIR__ . '/../admin/config.php';

function promos_file(): string { return DATA_DIR . '/promos.json'; }

function promo_types(): array {
    return ['bar' => '通知条', 'popup' => '弹窗', 'inline' => '内嵌模块'];
}

/** 内嵌位（inline）可选的命名位置——统一了原 AdSystem 的固定位。 */
function promo_slots(): array {
    return [
        'article_top'    => '文章顶部',
        'article_bottom' => '文章底部',
        'home_hero'      => '首页头部',
        'feed'           => '信息流',
        'sidebar'        => '侧边栏',
    ];
}

function promo_all(): array {
    $a = json_read(promos_file());
    return is_array($a) ? array_values($a) : [];
}

function promo_get(string $id): ?array {
    foreach (promo_all() as $p) if (($p['id'] ?? '') === $id) return $p;
    return null;
}

/** 新建 / 更新一条投放。 */
function promo_save(array $d): array {
    $name = trim((string)($d['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '投放名称不能为空'];
    $type = (string)($d['type'] ?? 'bar');
    if (!isset(promo_types()[$type])) return ['ok' => false, 'error' => '未知投放类型'];

    $all = json_read(promos_file());
    if (!is_array($all)) $all = [];
    $id = trim((string)($d['id'] ?? ''));

    $entity = [
        'id'          => $id ?: ('promo_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6)),
        'name'        => $name,
        'type'        => $type,
        'enabled'     => !empty($d['enabled']),
        'priority'    => (int)($d['priority'] ?? 0),
        // 内容
        'title'       => trim((string)($d['title'] ?? '')),
        'body'        => trim((string)($d['body'] ?? '')),
        'image'       => trim((string)($d['image'] ?? '')),
        'cta_text'    => trim((string)($d['cta_text'] ?? '')),
        'cta_link'    => trim((string)($d['cta_link'] ?? '')),
        'color'       => trim((string)($d['color'] ?? '')),
        'dismissible' => !isset($d['dismissible']) || !empty($d['dismissible']),
        // 位置
        'position'      => (string)($d['position'] ?? ($type === 'bar' ? 'top' : 'center')),
        'trigger'       => (string)($d['trigger'] ?? 'immediate'),
        'trigger_delay' => max(0, (int)($d['trigger_delay'] ?? 5)),
        'scroll_pct'    => max(0, min(100, (int)($d['scroll_pct'] ?? 50))),
        'slot'          => (string)($d['slot'] ?? 'article_top'),
        // 页面定向
        'page_mode'   => (string)($d['page_mode'] ?? 'all'),      // all|include|exclude|type
        'page_paths'  => array_values(array_filter(array_map('trim', (array)($d['page_paths'] ?? [])))),
        'page_types'  => array_values(array_filter((array)($d['page_types'] ?? []))),
        // 人群
        'aud_login'   => (string)($d['aud_login'] ?? 'any'),      // any|in|out
        'aud_visitor' => (string)($d['aud_visitor'] ?? 'any'),    // any|new|return
        'aud_segment' => trim((string)($d['aud_segment'] ?? '')),
        'aud_utm'     => trim((string)($d['aud_utm'] ?? '')),
        // 定时 + 频次
        'start'       => trim((string)($d['start'] ?? '')),
        'end'         => trim((string)($d['end'] ?? '')),
        'frequency'   => (string)($d['frequency'] ?? 'session'),  // always|session|daily|once
        // 统计
        'impressions' => (int)($d['impressions'] ?? 0),
        'clicks'      => (int)($d['clicks'] ?? 0),
        'dismissed'   => (int)($d['dismissed'] ?? 0),
        'created_at'  => $d['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ];

    $found = false;
    foreach ($all as &$p) { if (($p['id'] ?? '') === $entity['id']) { $p = $entity; $found = true; break; } }
    unset($p);
    if (!$found) $all[] = $entity;
    json_write(promos_file(), array_values($all));
    return ['ok' => true, 'promo' => $entity];
}

function promo_delete(string $id): bool {
    $all = array_values(array_filter(json_read(promos_file()), fn($p) => ($p['id'] ?? '') !== $id));
    return json_write(promos_file(), $all);
}

/** 路径匹配：支持精确、前缀通配（/article/*）、根路径 /。 */
function promo_path_match(string $pattern, string $path): bool {
    $pattern = trim($pattern);
    if ($pattern === '') return false;
    if ($pattern === $path) return true;
    if (substr($pattern, -1) === '*') {
        return strpos($path, rtrim($pattern, '*')) === 0;
    }
    return false;
}

/**
 * 一条投放是否命中当前上下文。纯函数，便于单测。
 *
 * $ctx: [
 *   'path' => '/article/xxx', 'page_type' => 'article',
 *   'logged_in' => bool, 'visitor' => 'new'|'return',
 *   'segments' => [...], 'utm_source' => 'weibo', 'now' => 'Y-m-d H:i:s'
 * ]
 */
function promo_match(array $p, array $ctx): bool {
    if (empty($p['enabled'])) return false;

    // 定时窗口
    $now = $ctx['now'] ?? date('Y-m-d H:i:s');
    if (!empty($p['start']) && $now < $p['start']) return false;
    if (!empty($p['end'])   && $now > $p['end'])   return false;

    // 页面定向
    $path = (string)($ctx['path'] ?? '');
    $ptype = (string)($ctx['page_type'] ?? '');
    switch ($p['page_mode'] ?? 'all') {
        case 'include':
            $hit = false;
            foreach ((array)($p['page_paths'] ?? []) as $pat) if (promo_path_match($pat, $path)) { $hit = true; break; }
            if (!$hit) return false;
            break;
        case 'exclude':
            foreach ((array)($p['page_paths'] ?? []) as $pat) if (promo_path_match($pat, $path)) return false;
            break;
        case 'type':
            if (!in_array($ptype, (array)($p['page_types'] ?? []), true)) return false;
            break;
        // 'all' 不限
    }

    // 人群：登录态
    $login = $p['aud_login'] ?? 'any';
    if ($login === 'in'  && empty($ctx['logged_in'])) return false;
    if ($login === 'out' && !empty($ctx['logged_in'])) return false;

    // 人群：新老访客
    $visitor = $p['aud_visitor'] ?? 'any';
    if ($visitor !== 'any' && ($ctx['visitor'] ?? '') !== $visitor) return false;

    // 人群：CDP 分群
    if (!empty($p['aud_segment'])) {
        if (!in_array($p['aud_segment'], (array)($ctx['segments'] ?? []), true)) return false;
    }

    // 人群：UTM 来源
    if (!empty($p['aud_utm'])) {
        if (strcasecmp((string)($ctx['utm_source'] ?? ''), $p['aud_utm']) !== 0) return false;
    }

    return true;
}

/**
 * 返回命中当前上下文的投放，按 priority 降序。
 * @param string $type 只取某类型（''=全部）
 */
function promo_serve(array $ctx, string $type = ''): array {
    $out = [];
    foreach (promo_all() as $p) {
        if ($type !== '' && ($p['type'] ?? '') !== $type) continue;
        if (promo_match($p, $ctx)) $out[] = $p;
    }
    usort($out, fn($a, $b) => ((int)($b['priority'] ?? 0)) <=> ((int)($a['priority'] ?? 0)));
    return $out;
}

/** 记录一次曝光/点击/关闭（旁路，失败不影响前台）。 */
function promo_hit(string $id, string $kind): bool {
    $field = ['impression' => 'impressions', 'click' => 'clicks', 'dismiss' => 'dismissed'][$kind] ?? '';
    if ($field === '') return false;
    $all = json_read(promos_file());
    $hit = false;
    foreach ($all as &$p) { if (($p['id'] ?? '') === $id) { $p[$field] = (int)($p[$field] ?? 0) + 1; $hit = true; break; } }
    unset($p);
    return $hit ? json_write(promos_file(), $all) : false;
}

/**
 * 服务端渲染某个命名内嵌位（inline）——取代原 ads_render()。
 * 给内容页在版位处调用，输出该位上优先级最高的一条 inline 投放。
 */
function promo_slot(string $slot, array $ctx = []): string {
    $ctx = array_merge(['path' => $_SERVER['REQUEST_URI'] ?? '', 'now' => date('Y-m-d H:i:s')], $ctx);
    foreach (promo_serve($ctx, 'inline') as $p) {
        if (($p['slot'] ?? '') !== $slot) continue;
        promo_hit($p['id'], 'impression');
        $title = htmlspecialchars($p['title'] ?? '');
        $body  = htmlspecialchars($p['body'] ?? '');
        $link  = htmlspecialchars($p['cta_link'] ?? '');
        $cta   = htmlspecialchars($p['cta_text'] ?? '');
        $img   = $p['image'] ?? '';
        $imgTag = $img ? '<img src="' . htmlspecialchars($img) . '" alt="" style="max-width:100%;border-radius:10px;margin-bottom:8px">' : '';
        $ctaTag = ($cta && $link) ? '<a href="' . $link . '" data-promo-click="' . htmlspecialchars($p['id']) . '" style="display:inline-block;margin-top:8px;padding:8px 16px;border-radius:8px;background:var(--accent,#2563eb);color:#fff;text-decoration:none;font-size:14px">' . $cta . '</a>' : '';
        return '<div class="of-promo-inline" data-promo="' . htmlspecialchars($p['id']) . '" style="border:1px solid var(--border,#e5e7eb);border-radius:12px;padding:16px;background:var(--surface,#fff)">'
             . $imgTag
             . ($title ? '<div style="font-weight:700;font-size:15px">' . $title . '</div>' : '')
             . ($body ? '<div style="color:var(--muted,#6b7280);font-size:14px;margin-top:4px">' . nl2br($body) . '</div>' : '')
             . $ctaTag . '</div>';
    }
    return '';
}
