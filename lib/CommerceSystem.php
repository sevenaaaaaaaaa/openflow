<?php
/**
 * 数字商品系统 CommerceSystem
 * 统一管理四类数字资产商品：Skill / 插件 / 主题 / API 套餐
 * 提供：商品 CRUD、定价（一次性/订阅/Go订阅/Zen按量）、
 *       购买下单（复用 ShopSystem 订单+支付）、自动交付、作者分成
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/ShopSystem.php';
require_once __DIR__ . '/ApiKeyAuth.php';
require_once __DIR__ . '/SkillSystem.php';

class CommerceSystem {
    private static string $productsFile = DATA_DIR . '/products.json';
    private static string $entitlementsFile = DATA_DIR . '/entitlements.json';

    // ─── 商品 CRUD ───

    public static function products(): array {
        return json_read(self::$productsFile);
    }

    public static function saveProducts(array $products): bool {
        return json_write(self::$productsFile, $products);
    }

    public static function getProduct(string $id): ?array {
        foreach (self::products() as $p) if ($p['id'] === $id) return $p;
        return null;
    }

    public static function createProduct(array $data): array {
        $products = self::products();
        $p = [
            'id' => 'prod_' . substr(bin2hex(random_bytes(6)), 0, 12),
            'type' => $data['type'] ?? 'skill',
            'asset_id' => $data['asset_id'] ?? '',
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'cover' => $data['cover'] ?? '',
            'pricing' => $data['pricing'] ?? ['mode' => 'one_time', 'price' => 0],
            'items' => $data['items'] ?? [],  // 组合包内容（type=bundle 时）
            'author' => $data['author'] ?? '',
            'author_name' => $data['author_name'] ?? '',
            'commission_rate' => (float)($data['commission_rate'] ?? 0.9),
            'distribution_enabled' => !empty($data['distribution_enabled']),
            'distributor_rate' => (float)($data['distributor_rate'] ?? 0.3),
            'status' => $data['status'] ?? 'draft',
            'sales_count' => 0,
            'rating' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $products[] = $p;
        self::saveProducts($products);
        return $p;
    }

    public static function updateProduct(string $id, array $data): ?array {
        $products = self::products();
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                foreach ($data as $k => $v) {
                    if (in_array($k, ['type','asset_id','title','description','cover','pricing','items','author','author_name','commission_rate','distribution_enabled','distributor_rate','status'], true)) $p[$k] = $v;
                }
                self::saveProducts($products);
                return $p;
            }
        }
        return null;
    }

    public static function deleteProduct(string $id): bool {
        $products = array_values(array_filter(self::products(), fn($p) => $p['id'] !== $id));
        self::saveProducts($products);
        return true;
    }

    // ─── 发布资产到商品 ───

    /**
     * 把 Skill 发布为可售商品
     */
    public static function publishSkill(string $skillId, array $pricing, string $author = '', float $rate = 0.7): array {
        $skill = skill_get($skillId);
        if (!$skill) return ['ok' => false, 'error' => 'Skill 不存在'];
        // 检查是否已发布
        foreach (self::products() as $p) {
            if ($p['type'] === 'skill' && $p['asset_id'] === $skillId) {
                return self::updateProduct($p['id'], ['pricing' => $pricing, 'status' => 'published']);
            }
        }
        return self::createProduct([
            'type' => 'skill', 'asset_id' => $skillId,
            'title' => $skill['title'] ?? $skillId,
            'description' => $skill['description'] ?? '',
            'pricing' => $pricing,
            'author' => $author ?: ($skill['author'] ?? ''),
            'author_name' => $author ?: ($skill['author'] ?? 'OpenFlow'),
            'commission_rate' => $rate,
            'status' => 'published',
        ]);
    }

    /**
     * 已发布的资产（供前端生态商店）
     */
    public static function publishedByType(string $type): array {
        return array_values(array_filter(self::products(), fn($p) => $p['type'] === $type && $p['status'] === 'published'));
    }

    public static function allPublished(): array {
        return array_values(array_filter(self::products(), fn($p) => $p['status'] === 'published'));
    }

    // ─── 购买与交付 ───

    /**
     * 创建数字商品订单（复用 ShopSystem 订单表）
     * @return array ['ok'=>, 'order'=>, 'pay_url'=>]
     */
    public static function purchase(string $memberId, string $productId, string $referrer = ''): array {
        $product = self::getProduct($productId);
        if (!$product) return ['ok' => false, 'error' => '商品不存在'];
        if ($product['status'] !== 'published') return ['ok' => false, 'error' => '商品未上架'];
        $price = (float)($product['pricing']['price'] ?? 0);
        if ($price <= 0) return ['ok' => false, 'error' => '商品未定价'];

        // 已拥有则直接交付
        if (self::owns($memberId, $productId)) {
            self::deliver($memberId, $product, 'already_owned');
            return ['ok' => true, 'order' => null, 'already_owned' => true];
        }

        // 解析分销者（一级分销）：ref = 分销者 referral_code 或 member_id
        $referrerId = '';
        if (!empty($referrer) && !empty($product['distribution_enabled'])) {
            $referrerId = commerce_resolve_referrer($referrer, $memberId);
        }

        // 创建订单（goods_type=product）
        $orderId = 'order_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $period = $product['pricing']['period'] ?? 'month';
        $distRate = (float)($product['distributor_rate'] ?? 0.3);
        $order = [
            'id' => $orderId,
            'member_id' => $memberId,
            'course_id' => $productId,
            'course_title' => $product['title'],
            'amount' => $price,
            'status' => 'pending',
            'payment_method' => '',
            'referrer_id' => $referrerId,
            'commission' => $referrerId ? round($price * $distRate, 2) : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'paid_at' => '',
            'goods_type' => 'product',
            'product_id' => $productId,
            'period' => $period,
            'author' => $product['author'] ?? '',
            'commission_rate' => (float)($product['commission_rate'] ?? 0.9),
            'distributor_rate' => $distRate,
            'platform_fee' => round($price * 0.1, 2),
        ];
        Database::insert('orders', $order);
        $order['utm'] = shop_current_utm();
        return ['ok' => true, 'order' => $order];
    }

    /**
     * 支付回调后交付（在 shop_mark_paid 之后调用）
     */
    public static function deliverOnPaid(string $orderId): void {
        $order = shop_get_order($orderId);
        if (!$order || $order['status'] !== 'paid') return;
        if (($order['goods_type'] ?? '') !== 'product') return;
        $product = self::getProduct($order['product_id'] ?? '');
        if (!$product) return;
        self::deliver($order['member_id'] ?? '', $product, 'paid', $order);
    }

    /**
     * 交付资产
     */
    private static function deliver(string $memberId, array $product, string $method, array $order = []): void {
        // 记录权益
        $ents = json_read(self::$entitlementsFile);
        $key = $memberId . ':' . $product['id'];
        $ents[$key] = [
            'member_id' => $memberId,
            'product_id' => $product['id'],
            'type' => $product['type'],
            'asset_id' => $product['asset_id'] ?? '',
            'method' => $method,
            'granted_at' => date('Y-m-d H:i:s'),
            'expires_at' => self::expiryFor($product),
        ];
        json_write(self::$entitlementsFile, $ents);

        // 组合包：递归交付所有 items（支持 skills 包 / 主题包 / 功能组合 / 大组合嵌套）
        if (($product['type'] ?? '') === 'bundle') {
            foreach ((array)($product['items'] ?? []) as $item) {
                self::deliverItem($memberId, $item, $order);
            }
        } else {
            self::deliverItem($memberId, $product, $order);
        }

        // 作者分成 + 分销者佣金（一级分销）
        // 佣金结构：平台抽 10%（覆盖支付手续费），分销者拿 distributor_rate%，作者拿剩余
        $paid = (float)($order['amount'] ?? 0);
        $platformFee = round($paid * 0.1, 2);
        $distAmount = (float)($order['commission'] ?? 0); // 已在 purchase 计算
        $authorAmount = round($paid - $platformFee - $distAmount, 2);
        if (!empty($product['author']) && $authorAmount > 0) {
            try {
                Database::execute("UPDATE members SET balance = balance + ? WHERE id = ?", [$authorAmount, $product['author']]);
                Database::insert('point_logs', [
                    'member_id' => $product['author'], 'points' => 0, 'type' => 'commission',
                    'description' => "商品「{$product['title']}」销售分成 ¥{$authorAmount}（平台费 ¥{$platformFee}）", 'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {}
        }
        // 分销者佣金入账（一级分销，仅直接推荐人）
        if (!empty($order['referrer_id']) && $distAmount > 0) {
            try {
                Database::execute("UPDATE members SET balance = balance + ? WHERE id = ?", [$distAmount, $order['referrer_id']]);
                Database::insert('point_logs', [
                    'member_id' => $order['referrer_id'], 'points' => 0, 'type' => 'distribution',
                    'description' => "推广「{$product['title']}」分销佣金 ¥{$distAmount}", 'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {}
        }

        // 递增销量
        $products = self::products();
        foreach ($products as &$p) {
            if ($p['id'] === $product['id']) { $p['sales_count'] = ($p['sales_count'] ?? 0) + 1; break; }
        }
        self::saveProducts($products);
    }

    /**
     * 交付单个商品/资产（组合包递归调用）
     */
    private static function deliverItem(string $memberId, array $item, array $order = []): void {
        if (($item['type'] ?? '') === 'bundle' && !empty($item['product_id'])) {
            $sub = self::getProduct($item['product_id']);
            if ($sub && ($sub['type'] ?? '') === 'bundle') {
                foreach ((array)($sub['items'] ?? []) as $subItem) self::deliverItem($memberId, $subItem, $order);
            }
            return;
        }
        $type = $item['type'] ?? '';
        $assetId = $item['asset_id'] ?? '';
        switch ($type) {
            case 'skill':
                if (!$assetId) break;
                try {
                    Database::execute(
                        "UPDATE members SET unlocked_skills = json_insert(COALESCE(unlocked_skills, '[]'), '$[#]', ?) WHERE id = ?",
                        [$assetId, $memberId]
                    );
                } catch (Exception $e) {}
                break;
            case 'api_plan':
                self::activateApiPlan($memberId, $item);
                break;
            case 'plugin':
            case 'theme':
                break;
        }
        if ($assetId && $type !== 'bundle') {
            $ents = json_read(self::$entitlementsFile);
            $ents[$memberId . ':' . $type . ':' . $assetId] = [
                'member_id' => $memberId, 'product_id' => $item['product_id'] ?? '', 'type' => $type,
                'asset_id' => $assetId, 'method' => 'bundle', 'granted_at' => date('Y-m-d H:i:s'), 'expires_at' => '',
            ];
            json_write(self::$entitlementsFile, $ents);
        }
    }

    /**
     * 创建组合包（可嵌套：skills/主题/功能/子组合包）
     */
    public static function createBundle(array $data): array {
        $items = $data['items'] ?? [];
        if (empty($items)) return ['ok' => false, 'error' => '组合包至少需要一个内容'];
        $seen = []; $clean = [];
        foreach ($items as $it) {
            $key = ($it['type'] ?? '') . ':' . ($it['asset_id'] ?? ($it['product_id'] ?? ''));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $clean[] = $it;
        }
        $p = self::createProduct([
            'type' => 'bundle',
            'title' => $data['title'] ?? '组合包',
            'description' => $data['description'] ?? '',
            'pricing' => $data['pricing'] ?? ['mode' => 'one_time', 'price' => 0],
            'items' => $clean,
            'author' => $data['author'] ?? '',
            'author_name' => $data['author_name'] ?? '',
            'commission_rate' => (float)($data['commission_rate'] ?? 0.9),
            'distribution_enabled' => !empty($data['distribution_enabled']),
            'distributor_rate' => (float)($data['distributor_rate'] ?? 0.3),
            'status' => $data['status'] ?? 'published',
        ]);
        return ['ok' => true, 'product' => $p];
    }

    /**
     * 组合包内容清单（展开嵌套，供展示）
     */
    public static function bundleContents(array $product, int $depth = 0): array {
        $out = [];
        foreach ((array)($product['items'] ?? []) as $item) {
            if (($item['type'] ?? '') === 'bundle' && !empty($item['product_id'])) {
                $sub = self::getProduct($item['product_id']);
                if ($sub && $depth < 3) $out = array_merge($out, self::bundleContents($sub, $depth + 1));
                else $out[] = ['type' => 'bundle', 'title' => $sub['title'] ?? '组合包', 'asset_id' => $item['product_id']];
            } else {
                $title = $item['title'] ?? '';
                if (!$title && !empty($item['asset_id'])) {
                    if (($item['type'] ?? '') === 'skill') { $s = skill_get($item['asset_id']); $title = $s['title'] ?? $item['asset_id']; }
                    else $title = $item['asset_id'];
                }
                $out[] = ['type' => $item['type'] ?? '', 'title' => $title, 'asset_id' => $item['asset_id'] ?? ''];
            }
        }
        return $out;
    }

    private static function expiryFor(array $product): string {
        $pricing = $product['pricing'] ?? [];
        if (($pricing['mode'] ?? 'one_time') === 'one_time') return '';
        $period = $pricing['period'] ?? 'month';
        return date('Y-m-d H:i:s', strtotime('+' . ($period === 'year' ? '1 year' : '1 month')));
    }

    /**
     * 激活 API 套餐（Go 订阅 / Zen 按量）
     */
    private static function activateApiPlan(string $memberId, array $product): void {
        $member = null;
        try {
            $rows = Database::query("SELECT * FROM members WHERE id = ?", [$memberId]);
            $member = $rows[0] ?? null;
        } catch (Exception $e) {}
        if (!$member) return;

        $pricing = $product['pricing'] ?? [];
        $planId = $product['asset_id'] ?: $product['id'];
        $mode = $pricing['mode'] ?? 'go'; // go=订阅 / zen=按量

        // 记录到 member 的 api_plans 字段
        $plans = json_decode($member['api_plans'] ?? '[]', true) ?: [];
        $found = false;
        foreach ($plans as &$pl) {
            if ($pl['plan_id'] === $planId) {
                $pl['mode'] = $mode;
                $pl['expires_at'] = ($mode === 'go') ? self::expiryFor($product) : ($pl['expires_at'] ?? '');
                $pl['quota_reset'] = $mode === 'go' ? ['h5' => 0, 'day' => 0, 'week' => 0, 'last_reset' => date('Y-m-d H:i:s')] : null;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $plans[] = [
                'plan_id' => $planId, 'mode' => $mode,
                'expires_at' => ($mode === 'go') ? self::expiryFor($product) : '',
                'quota_reset' => $mode === 'go' ? ['h5' => 0, 'day' => 0, 'week' => 0, 'last_reset' => date('Y-m-d H:i:s')] : null,
                'balance' => ($mode === 'zen') ? (float)($pricing['price'] ?? 0) : null,
            ];
        }
        try {
            Database::execute("UPDATE members SET api_plans = ? WHERE id = ?", [json_encode($plans, JSON_UNESCAPED_UNICODE), $memberId]);
        } catch (Exception $e) {}
    }

    public static function owns(string $memberId, string $productId): bool {
        $ents = json_read(self::$entitlementsFile);
        $key = $memberId . ':' . $productId;
        if (!isset($ents[$key])) return false;
        $e = $ents[$key];
        if (!empty($e['expires_at']) && strtotime($e['expires_at']) < time()) return false;
        return true;
    }

    public static function memberEntitlements(string $memberId): array {
        $ents = json_read(self::$entitlementsFile);
        return array_values(array_filter($ents, fn($e) => $e['member_id'] === $memberId));
    }

    public static function stats(): array {
        $products = self::products();
        return [
            'total' => count($products),
            'published' => count(array_filter($products, fn($p) => $p['status'] === 'published')),
            'sales' => array_sum(array_column($products, 'sales_count')),
            'by_type' => [
                'skill' => count(array_filter($products, fn($p) => $p['type'] === 'skill')),
                'plugin' => count(array_filter($products, fn($p) => $p['type'] === 'plugin')),
                'theme' => count(array_filter($products, fn($p) => $p['type'] === 'theme')),
                'api_plan' => count(array_filter($products, fn($p) => $p['type'] === 'api_plan')),
            ],
        ];
    }
}

// ─── 分销辅助（一级分销，class 外顶层函数） ───
// 解析分销者：ref 可以是 referral_code 或 member_id（不能是自己）
function commerce_resolve_referrer(string $ref, string $buyerId): string {
    $ref = trim($ref);
    if ($ref === '') return '';
    try {
        $rows = Database::query("SELECT id, referral_code FROM members WHERE id = ? OR referral_code = ? LIMIT 1", [$ref, $ref]);
        $m = $rows[0] ?? null;
        if ($m && ($m['id'] ?? '') !== $buyerId) return $m['id'];
    } catch (Exception $e) {}
    return '';
}

// 平台费率（10%，覆盖支付手续费）
function commerce_platform_fee_rate(): float { return 0.1; }

// 分销者看板：带来的订单 + 佣金统计
function commerce_distributor_stats(string $memberId): array {
    $stats = ['products' => [], 'total_orders' => 0, 'total_commission' => 0, 'pending_commission' => 0];
    try {
        $orders = Database::query("SELECT * FROM orders WHERE referrer_id = ? ORDER BY id DESC LIMIT 100", [$memberId]);
        $total = 0; $pending = 0;
        foreach ($orders as $o) {
            $total += (float)($o['commission'] ?? 0);
            if (($o['status'] ?? '') !== 'paid') $pending += (float)($o['commission'] ?? 0);
        }
        $stats['total_orders'] = count($orders);
        $stats['total_commission'] = round($total, 2);
        $stats['pending_commission'] = round($pending, 2);
    } catch (Exception $e) {}
    return $stats;
}
