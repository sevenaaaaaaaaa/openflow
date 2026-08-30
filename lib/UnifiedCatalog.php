<?php
/**
 * UnifiedCatalog —— 统一商品目录（AUDIT-05 / BACKLOG T1-13）
 *
 * 【为什么】同样是"一件在卖的东西"，数字商品在 CommerceSystem、实物/积分在
 * MallSystem、生态资产在 MarketplaceSystem，三套各有各的存储与后台——
 * 跨类型的「所有在售商品」没有一处能统一看、统一管。这是"岛"的问题。
 * 本模块给一个**只读统一视图**：一处看全平台在卖什么、谁在卖、卖得怎么样。
 *
 * 【定位】只聚合与检索，不改写各系统存储（改写仍回各自后台）——先把"平台"
 * 这个词立起来，避免一上来做大重构。
 */

if (!function_exists('catalog_kinds')) {

    function catalog_kinds(): array {
        return ['digital' => '数字商品', 'physical' => '实物商品', 'points' => '积分商品', 'course' => '课程'];
    }

    /** 统一条目结构。 */
    function catalog_item(string $kind, array $d): array {
        return [
            'uid'     => $kind . ':' . (string)($d['id'] ?? ''),
            'kind'    => $kind,
            'id'      => (string)($d['id'] ?? ''),
            'title'   => trim((string)($d['title'] ?? '')),
            'price'   => (float)($d['price'] ?? 0),
            'points'  => (int)($d['points'] ?? 0),
            'author'  => (string)($d['author'] ?? ''),
            'status'  => (string)($d['status'] ?? 'active'),
            'stock'   => isset($d['stock']) ? (int)$d['stock'] : null,
            'cover'   => (string)($d['cover'] ?? ''),
            'created_at' => (string)($d['created_at'] ?? ''),
            'edit_url'=> (string)($d['edit_url'] ?? ''),
        ];
    }

    /**
     * 聚合全平台商品。$inject 可注入各源（测试）：
     *   ['digital'=>[], 'physical'=>[], 'points'=>[], 'course'=>[]]
     */
    function catalog_all(?array $inject = null): array {
        $read = fn(string $rel) => function_exists('json_read') ? json_read(DATA_DIR . $rel) : [];
        $items = [];

        // 数字商品（CommerceSystem: data/products.json）
        foreach (($inject['digital'] ?? $read('/products.json')) as $p) {
            if (!is_array($p)) continue;
            $items[] = catalog_item('digital', [
                'id' => $p['id'] ?? '', 'title' => $p['title'] ?? '',
                'price' => $p['pricing']['price'] ?? ($p['price'] ?? 0),
                'author' => $p['author_name'] ?? ($p['author'] ?? ''),
                'status' => $p['status'] ?? 'active', 'stock' => $p['stock'] ?? null,
                'cover' => $p['cover'] ?? '', 'created_at' => $p['created_at'] ?? '',
                'edit_url' => '/xmp/commerce',
            ]);
        }
        // 实物商品（MallSystem: data/shop/products.json）
        foreach (($inject['physical'] ?? $read('/shop/products.json')) as $p) {
            if (!is_array($p)) continue;
            $items[] = catalog_item('physical', [
                'id' => $p['id'] ?? '', 'title' => $p['title'] ?? '', 'price' => $p['price'] ?? 0,
                'status' => $p['status'] ?? 'active', 'stock' => $p['stock'] ?? null,
                'cover' => $p['image'] ?? '', 'created_at' => $p['created_at'] ?? '',
                'edit_url' => '/xmp/mall',
            ]);
        }
        // 积分商品（MallSystem: data/shop/points_products.json）
        foreach (($inject['points'] ?? $read('/shop/points_products.json')) as $p) {
            if (!is_array($p)) continue;
            $items[] = catalog_item('points', [
                'id' => $p['id'] ?? '', 'title' => $p['title'] ?? '', 'points' => $p['points'] ?? 0,
                'status' => $p['status'] ?? 'active', 'stock' => $p['stock'] ?? null,
                'cover' => $p['image'] ?? '', 'created_at' => $p['created_at'] ?? '',
                'edit_url' => '/xmp/mall',
            ]);
        }
        // 课程（data/courses/index.json）
        foreach (($inject['course'] ?? $read('/courses/index.json')) as $p) {
            if (!is_array($p)) continue;
            $items[] = catalog_item('course', [
                'id' => $p['id'] ?? '', 'title' => $p['title'] ?? '', 'price' => $p['price'] ?? 0,
                'author' => $p['author'] ?? '', 'status' => $p['status'] ?? 'active',
                'cover' => $p['cover'] ?? '', 'created_at' => $p['created_at'] ?? '',
                'edit_url' => '/xmp/courses',
            ]);
        }

        usort($items, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $items;
    }

    /** 检索：关键词 / 类型 / 状态。 */
    function catalog_search(array $items, string $q = '', string $kind = '', string $status = ''): array {
        $q = trim(mb_strtolower($q));
        return array_values(array_filter($items, function ($i) use ($q, $kind, $status) {
            if ($kind !== '' && $i['kind'] !== $kind) return false;
            if ($status !== '' && $i['status'] !== $status) return false;
            if ($q !== '' && mb_strpos(mb_strtolower($i['title'] . ' ' . $i['author'] . ' ' . $i['id']), $q) === false) return false;
            return true;
        }));
    }

    /** 平台概览：各类型数量、在售数、缺货数、创作者数。 */
    function catalog_summary(array $items): array {
        $byKind = []; $active = 0; $oos = 0; $authors = [];
        foreach ($items as $i) {
            $byKind[$i['kind']] = ($byKind[$i['kind']] ?? 0) + 1;
            if ($i['status'] === 'active') $active++;
            if ($i['stock'] !== null && $i['stock'] <= 0) $oos++;
            if ($i['author'] !== '') $authors[$i['author']] = true;
        }
        return [
            'total' => count($items), 'active' => $active, 'out_of_stock' => $oos,
            'creators' => count($authors), 'by_kind' => $byKind,
        ];
    }
}
