<?php
/**
 * API 分佣系统 — 推荐 API 聚合平台 + 赚取分佣
 */
require_once __DIR__ . '/../admin/config.php';

class ApiAffiliate {
    private static string $configFile = DATA_DIR . '/api-affiliate.json';
    private static string $referralsFile = DATA_DIR . '/api-referrals.json';

    // 推荐的 API 聚合平台
    public static function recommendedPlatforms(): array {
        return [
            [
                'id' => 'openai',
                'name' => 'OpenAI',
                'description' => 'GPT-4、DALL·E、Whisper 等 AI API',
                'category' => 'AI',
                'affiliate_url' => 'https://platform.openai.com',
                'commission' => '5-10%',
                'cookie_days' => 30,
                'icon' => '🤖',
            ],
            [
                'id' => 'anthropic',
                'name' => 'Anthropic',
                'description' => 'Claude AI API',
                'category' => 'AI',
                'affiliate_url' => 'https://console.anthropic.com',
                'commission' => '5%',
                'cookie_days' => 30,
                'icon' => '🧠',
            ],
            [
                'id' => 'stripe',
                'name' => 'Stripe',
                'description' => '支付处理 API',
                'category' => '支付',
                'affiliate_url' => 'https://stripe.com',
                'commission' => '$100/推荐',
                'cookie_days' => 45,
                'icon' => '💳',
            ],
            [
                'id' => 'vercel',
                'name' => 'Vercel',
                'description' => '前端部署平台',
                'category' => '托管',
                'affiliate_url' => 'https://vercel.com',
                'commission' => '15%',
                'cookie_days' => 30,
                'icon' => '▲',
            ],
            [
                'id' => 'cloudflare',
                'name' => 'Cloudflare',
                'description' => 'CDN、安全、边缘计算',
                'category' => '基础设施',
                'affiliate_url' => 'https://www.cloudflare.com',
                'commission' => '10%',
                'cookie_days' => 45,
                'icon' => '☁️',
            ],
            [
                'id' => 'sendgrid',
                'name' => 'SendGrid',
                'description' => '邮件发送 API',
                'category' => '邮件',
                'affiliate_url' => 'https://sendgrid.com',
                'commission' => '15%',
                'cookie_days' => 30,
                'icon' => '📧',
            ],
            [
                'id' => 'twilio',
                'name' => 'Twilio',
                'description' => '短信/电话/视频 API',
                'category' => '通信',
                'affiliate_url' => 'https://twilio.com',
                'commission' => '$20/推荐',
                'cookie_days' => 30,
                'icon' => '📱',
            ],
            [
                'id' => 'algolia',
                'name' => 'Algolia',
                'description' => '搜索即服务 API',
                'category' => '搜索',
                'affiliate_url' => 'https://algolia.com',
                'commission' => '20%',
                'cookie_days' => 45,
                'icon' => '🔍',
            ],
        ];
    }

    /**
     * 获取配置
     */
    public static function getConfig(): array {
        return json_read(self::$configFile);
    }

    /**
     * 保存配置
     */
    public static function saveConfig(array $config): bool {
        return json_write(self::$configFile, $config);
    }

    /**
     * 记录推荐
     */
    public static function trackReferral(string $platformId, string $visitorId = ''): bool {
        $referrals = json_read(self::$referralsFile);

        $referrals[] = [
            'id' => 'ref_' . bin2hex(random_bytes(6)),
            'platform_id' => $platformId,
            'visitor_id' => $visitorId ?: ($_COOKIE['cdp_vid'] ?? ''),
            'member_id' => $_SESSION['admin_id'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'pending', // pending, approved, paid
        ];

        return json_write(self::$referralsFile, $referrals);
    }

    /**
     * 获取推荐记录
     */
    public static function getReferrals(string $platformId = ''): array {
        $referrals = json_read(self::$referralsFile);

        if ($platformId) {
            $referrals = array_filter($referrals, fn($r) => $r['platform_id'] === $platformId);
        }

        return array_values($referrals);
    }

    /**
     * 获取推荐统计
     */
    public static function getStats(): array {
        $referrals = json_read(self::$referralsFile);
        $platforms = self::recommendedPlatforms();

        $stats = [
            'total' => count($referrals),
            'pending' => 0,
            'approved' => 0,
            'paid' => 0,
            'by_platform' => [],
        ];

        foreach ($referrals as $r) {
            $status = $r['status'] ?? 'pending';
            $stats[$status]++;

            $pid = $r['platform_id'];
            if (!isset($stats['by_platform'][$pid])) {
                $stats['by_platform'][$pid] = 0;
            }
            $stats['by_platform'][$pid]++;
        }

        return $stats;
    }

    /**
     * 生成推荐链接
     */
    public static function getReferralUrl(string $platformId): string {
        $platforms = self::recommendedPlatforms();
        $platform = null;

        foreach ($platforms as $p) {
            if ($p['id'] === $platformId) {
                $platform = $p;
                break;
            }
        }

        if (!$platform) return '';

        $config = self::getConfig();
        $refCode = $config['referral_code'] ?? '';

        if ($refCode) {
            $separator = strpos($platform['affiliate_url'], '?') === false ? '?' : '&';
            return $platform['affiliate_url'] . $separator . 'ref=' . $refCode;
        }

        return $platform['affiliate_url'];
    }
}
