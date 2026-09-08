<?php
/**
 * 统一市场 — 插件 / 技能 / 主题 三类资产聚合
 * 前台市场页 + 搜索 + 打分 的单一数据源
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/SkillSystem.php';
require_once __DIR__ . '/../lib/PluginSystem.php';

// 市场资产分类
function mkt_categories(): array {
    return [
        'plugin' => ['name' => '插件', 'icon' => '🧩', 'desc' => '扩展后台功能的 PHP 模块'],
        'skill' => ['name' => '技能', 'icon' => '⚡', 'desc' => '可复用的 AI / Agent 能力包'],
        'theme' => ['name' => '主题', 'icon' => '🎨', 'desc' => '前端视觉与布局主题'],
        'bundle' => ['name' => '组合包', 'icon' => '📦', 'desc' => '多个产品打包的套装'],
        'membership' => ['name' => '会员', 'icon' => '👑', 'desc' => '商品会员计划'],
    ];
}

// 聚合三类资产
function mkt_assets(): array {
    $items = [];

    // 插件（来自 PluginSystem）
    try {
        foreach (PluginSystem::get_plugins() as $p) {
            $items[] = [
                'type' => 'plugin',
                'id' => $p['id'],
                'title' => $p['name'] ?? $p['id'],
                'description' => $p['description'] ?? '',
                'icon' => '🧩',
                'author' => $p['author'] ?? 'OpenFlow',
                'version' => $p['version'] ?? '1.0.0',
                'installs' => 0, 'rating' => 0, 'rating_count' => 0,
                'installed' => true,
                'url' => '/plugin/' . urlencode($p['id']),
                'tags' => ['插件'],
            ];
        }
    } catch (Exception $e) {}

    // 技能（SkillSystem）
    foreach (skills_all() as $s) {
        $items[] = [
            'type' => 'skill',
            'id' => $s['id'],
            'title' => $s['title'] ?? $s['id'],
            'description' => $s['description'] ?? '',
            'icon' => $s['icon'] ?? '⚡',
            'author' => $s['author'] ?? 'OpenFlow',
            'author_type' => $s['author_type'] ?? 'official',
            'version' => $s['version'] ?? '1.0.0',
            'installs' => $s['installs'] ?? 0,
            'rating' => $s['rating'] ?? 0,
            'rating_count' => $s['rating_count'] ?? 0,
            'status' => $s['status'] ?? 'published',
            'url' => '/skill/' . urlencode($s['id']),
            'tags' => $s['tags'] ?? [],
            'source' => $s['source'] ?? 'local',
            'created_at' => $s['created_at'] ?? '',
        ];
    }

    // 主题（ThemeSystem：预设 + 自定义）
    try {
        $allThemes = ThemeSystem::all();
        $activeId = ThemeSystem::activeId();
        foreach ($allThemes as $tid => $t) {
            $items[] = [
                'type' => 'theme',
                'id' => $tid,
                'title' => $t['name'] ?? $tid,
                'description' => $t['desc'] ?? ($t['description'] ?? ''),
                'icon' => '🎨',
                'author' => $t['author'] ?? (ThemeSystem::isPreset($tid) ? 'OpenFlow' : '社区'),
                'version' => $t['version'] ?? '1.0.0',
                'installs' => $t['installs'] ?? 0, 'rating' => $t['rating'] ?? 0, 'rating_count' => $t['rating_count'] ?? 0,
                'installed' => $activeId === $tid,
                'url' => '/theme/' . urlencode((string)$tid), // 以前指向后台 /admin/themes.php：访客点主题卡进的是后台登录页
                'tags' => ['主题', 'theme'],
                'preset' => ThemeSystem::isPreset($tid),
            ];
        }
    } catch (\Throwable $e) {}

    // 组合包 / 会员商品（CommerceSystem 数字商品）
    try {
        foreach (CommerceSystem::allPublished() as $p) {
            if (($p['type'] ?? '') === 'bundle') {
                $items[] = [
                    'type' => 'bundle',
                    'id' => $p['id'],
                    'title' => $p['title'] ?? '',
                    'description' => $p['description'] ?? '',
                    'icon' => '📦',
                    'author' => $p['author_name'] ?? 'OpenFlow',
                    'version' => '1.0.0',
                    'installs' => $p['sales_count'] ?? 0, 'rating' => $p['rating'] ?? 0, 'rating_count' => 0,
                    'status' => 'published',
                    'price' => (float)($p['pricing']['price'] ?? 0),
                    'items_count' => count(CommerceSystem::bundleContents($p)),
                    'url' => '/marketplace?type=bundle',
                    'tags' => ['组合包', 'bundle'],
                    'bundle_items' => CommerceSystem::bundleContents($p),
                ];
            } elseif (($p['type'] ?? '') === 'membership') {
                $items[] = [
                    'type' => 'membership',
                    'id' => $p['asset_id'] ?? $p['id'],
                    'title' => $p['title'] ?? '',
                    'description' => $p['description'] ?? '',
                    'icon' => '👑',
                    'author' => 'OpenFlow',
                    'version' => '1.0.0',
                    'installs' => $p['sales_count'] ?? 0, 'rating' => 0, 'rating_count' => 0,
                    'status' => 'published',
                    'price' => (float)($p['pricing']['price'] ?? 0),
                    'url' => '/member.php?view=membership',
                    'tags' => ['会员', 'membership'],
                ];
            }
        }
    } catch (\Throwable $e) {}

    return $items;
}

// 搜索 + 筛选
function mkt_search(string $q = '', string $type = 'all', string $sort = 'popular'): array {
    $items = mkt_assets();
    if ($type !== 'all') $items = array_values(array_filter($items, fn($a) => $a['type'] === $type));
    if ($q !== '') {
        $items = array_values(array_filter($items, function ($a) use ($q) {
            $hay = mb_strtolower(($a['title'] ?? '') . ' ' . ($a['description'] ?? '') . ' ' . implode(' ', $a['tags'] ?? []));
            return mb_strpos($hay, mb_strtolower($q)) !== false;
        }));
    }
    if ($sort === 'rating') usort($items, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));
    elseif ($sort === 'new') usort($items, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    else usort($items, fn($a, $b) => ($b['installs'] ?? 0) <=> ($a['installs'] ?? 0));
    return $items;
}

// 统计
function mkt_stats(): array {
    $items = mkt_assets();
    return [
        'total' => count($items),
        'plugins' => count(array_filter($items, fn($a) => $a['type'] === 'plugin')),
        'skills' => count(array_filter($items, fn($a) => $a['type'] === 'skill')),
        'themes' => count(array_filter($items, fn($a) => $a['type'] === 'theme')),
    ];
}

/** 资产类型 → 线框图标 path / 色相 / 封面代号（前台渲染用；data 里配的 emoji 不再直出） */
function mkt_type_meta(string $type): array {
    $m = [
        'plugin'     => ['hue' => 'ok',     'code' => 'PLUGIN', 'icon' => '<path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/>'],
        'skill'      => ['hue' => 'accent', 'code' => 'SKILL',  'icon' => '<path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/>'],
        'theme'      => ['hue' => 'warn',   'code' => 'THEME',  'icon' => '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/>'],
        'bundle'     => ['hue' => 'danger', 'code' => 'BUNDLE', 'icon' => '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>'],
        'membership' => ['hue' => 'warn',   'code' => 'VIP',    'icon' => '<path d="M6 3h12l4 6-10 12L2 9l4-6Z"/><path d="M2 9h20M9 3 7 9l5 12M15 3l2 6-5 12"/>'],
    ];
    return $m[$type] ?? ['hue' => 'neutral', 'code' => strtoupper($type ?: 'ASSET'), 'icon' => '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>'];
}
function mkt_type_icon(string $type): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . mkt_type_meta($type)['icon'] . '</svg>';
}
/** 资产卡的生成式封面（与 CoverRenderer 同一套 .gcov 样式） */
function mkt_asset_cover(array $a, array $typeNames = []): string {
    $t = (string)($a['type'] ?? '');
    $m = mkt_type_meta($t);
    $label = htmlspecialchars($typeNames[$t]['name'] ?? $t);
    return '<div class="gcov h-' . $m['hue'] . '"><span class="gc-code" aria-hidden="true">' . $m['code'] . '</span><span class="gc-k">' . mkt_type_icon($t) . $label . '</span></div>';
}

/**
 * 在资产目录里查找资产（支持 plugin/skill/theme 等类型）
 */
function mkt_find_asset(string $type, string $id): ?array {
    foreach (mkt_assets() as $a) {
        if (($a['type'] ?? '') === $type && ($a['id'] ?? '') === $id) return $a;
    }
    return null;
}

// ═══ C1：市场上架定价 ═══

function mkt_prices_file(): string { return DATA_DIR . '/marketplace/prices.json'; }
function mkt_purchases_file(): string { return DATA_DIR . '/marketplace/purchases.json'; }

/**
 * 获取资产定价（market JSON 里的 price 字段）
 */
function mkt_asset_price(string $type, string $id): ?array {
    $prices = json_read(mkt_prices_file());
    $key = $type . ':' . $id;
    return $prices[$key] ?? null;
}

/**
 * 设置资产定价（后台操作）
 */
function mkt_set_price(string $type, string $id, float $price, string $currency = 'CNY', string $period = ''): array {
    $prices = json_read(mkt_prices_file());
    $key = $type . ':' . $id;
    $prices[$key] = [
        'type' => $type,
        'id' => $id,
        'price' => max(0, $price),
        'currency' => $currency,
        'period' => $period, // lifetime / month / year，空=一次性
        'enabled' => true,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (!is_dir(dirname(mkt_prices_file()))) @mkdir(dirname(mkt_prices_file()), 0755, true);
    json_write(mkt_prices_file(), $prices);
    return ['ok' => true, 'price' => $prices[$key]];
}

/**
 * 批量上架定价（后台批量操作）
 */
function mkt_bulk_price(array $items): int {
    $prices = json_read(mkt_prices_file());
    $n = 0;
    foreach ($items as $item) {
        $key = ($item['type'] ?? '') . ':' . ($item['id'] ?? '');
        if (empty($key) || $key === ':') continue;
        $prices[$key] = [
            'type' => $item['type'] ?? '',
            'id' => $item['id'] ?? '',
            'price' => max(0, (float)($item['price'] ?? 0)),
            'currency' => $item['currency'] ?? 'CNY',
            'period' => $item['period'] ?? '',
            'enabled' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $n++;
    }
    json_write(mkt_prices_file(), $prices);
    return $n;
}

// ═══ C2：市场购买 ═══

/**
 * 购买资产（复用 ShopSystem 订单 + 支付通道）
 */
function mkt_purchase(string $memberId, string $type, string $id, string $method = ''): array {
    $asset = mkt_find_asset($type, $id);
    if (!$asset) return ['ok' => false, 'error' => '资产不存在'];

    $priceInfo = mkt_asset_price($type, $id);
    if (!$priceInfo || ($priceInfo['price'] ?? 0) <= 0) {
        // 免费资产，直接交付
        return mkt_deliver_asset($memberId, $type, $id);
    }

    // 创建订单（复用 ShopSystem 订单格式）
    $orderId = 'mkt_' . $memberId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    $order = [
        'id' => $orderId,
        'member_id' => $memberId,
        'type' => 'marketplace',
        'asset_type' => $type,
        'asset_id' => $id,
        'amount' => $priceInfo['price'],
        'currency' => $priceInfo['currency'] ?? 'CNY',
        'status' => 'pending',
        'payment_method' => $method,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // 持久化订单
    $ordersFile = DATA_DIR . '/shop/orders.json';
    $orders = json_read($ordersFile);
    $orders[] = $order;
    json_write($ordersFile, $orders);

    // 发起支付
    if ($method && function_exists('payment_channel_create')) {
        $payResult = payment_channel_create($method, $order);
        if (!empty($payResult['pay_url'])) {
            return ['ok' => true, 'order_id' => $orderId, 'pay_url' => $payResult['pay_url']];
        }
    }

    return ['ok' => true, 'order_id' => $orderId, 'amount' => $priceInfo['price']];
}

/**
 * 交付资产（安装/记录购买）
 */
function mkt_deliver_asset(string $memberId, string $type, string $id): array {
    $purchases = json_read(mkt_purchases_file());
    // 检查是否已购买
    foreach ($purchases as $p) {
        if ($p['member_id'] === $memberId && $p['type'] === $type && $p['id'] === $id) {
            return ['ok' => true, 'message' => '已拥有'];
        }
    }
    $purchases[] = [
        'member_id' => $memberId,
        'type' => $type,
        'id' => $id,
        'purchased_at' => date('Y-m-d H:i:s'),
    ];
    if (!is_dir(dirname(mkt_purchases_file()))) @mkdir(dirname(mkt_purchases_file()), 0755, true);
    json_write(mkt_purchases_file(), $purchases);
    return ['ok' => true, 'message' => '已交付'];
}

/**
 * 查询用户已购买资产
 */
function mkt_purchases(string $memberId): array {
    return array_values(array_filter(json_read(mkt_purchases_file()), fn($p) => $p['member_id'] === $memberId));
}

// ═══ 远程市场同步 ═══
function mkt_remote_url(): string {
    return json_read(DATA_DIR . '/marketplace-settings.json')['remote_url'] ?? '';
}
function mkt_save_remote_settings(array $s): void {
    json_write(DATA_DIR . '/marketplace-settings.json', $s);
}

// 从远程 marketplace.json 拉取技能并合并（远程技能标记 source=remote，可被本地覆盖）
// 返回同步结果
function mkt_sync_remote(): array {
    $url = mkt_remote_url();
    if (empty($url)) return ['ok' => false, 'error' => '未配置远程市场 URL'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => 'OpenFlow-Market/1.0']);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http !== 200 || !$resp) return ['ok' => false, 'error' => '远程市场拉取失败'];

    $remote = json_decode($resp, true);
    if (!$remote) return ['ok' => false, 'error' => '远程市场 JSON 解析失败'];

    $imported = 0;
    // skills 数组：要么顶层 skills，要么 [{type:'skill',...}]
    $remoteSkills = $remote['skills'] ?? (is_array($remote) && isset($remote[0]) ? $remote : []);
    $local = skills_all();
    foreach ($remoteSkills as $rs) {
        if (empty($rs['id'])) continue;
        $id = $rs['id'];
        $merged = false;
        foreach ($local as &$x) {
            if ($x['id'] === $id) {
                // 本地已存在：若本地不是 remote 来源则保留本地；否则更新
                if (($x['source'] ?? '') === 'remote' || ($rs['force'] ?? false)) {
                    $x = array_merge($x, $rs, ['source' => 'remote', 'updated_at' => date('Y-m-d H:i:s')]);
                    $imported++;
                }
                $merged = true;
                break;
            }
        }
        unset($x);
        if (!$merged) {
            $local[] = array_merge([
                'type' => 'prompt', 'title' => $rs['id'], 'description' => '', 'author' => 'Remote',
                'author_type' => 'developer', 'icon' => '🌍', 'tags' => [], 'content' => '',
                'steps' => [], 'status' => 'published', 'installs' => 0, 'rating' => 0,
                'rating_count' => 0, 'version' => '1.0.0', 'source' => 'remote',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ], $rs);
            $imported++;
        }
    }
    skills_save($local);
    return ['ok' => true, 'imported' => $imported];
}
