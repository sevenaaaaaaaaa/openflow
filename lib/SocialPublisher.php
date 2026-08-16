<?php
/**
 * 多平台内容发布器 SocialPublisher
 * 把文章/内容推送到各平台：
 *  - 公众号：图文/文本群发（走 WechatMp）
 *  - 其他平台：Webhook / 开放接口 / 链接生成
 * 支持：平台内容变体（标题/摘要/标签按平台改写）、定时发布、发布记录
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/WechatMp.php';

class SocialPublisher {
    private static string $logFile = DATA_DIR . '/publish-log.json';
    private static string $queueFile = DATA_DIR . '/publish-queue.json';

    /**
     * 支持的平台
     */
    public static function platforms(): array {
        return [
            'wechat' => ['name' => '微信公众号', 'variant' => true],
            'zhihu' => ['name' => '知乎', 'variant' => true, 'manual' => true],
            'xiaohongshu' => ['name' => '小红书', 'variant' => true, 'manual' => true],
            'linkedin' => ['name' => 'LinkedIn', 'variant' => true, 'manual' => true],
            'twitter' => ['name' => 'X (Twitter)', 'variant' => true, 'manual' => true],
            'facebook' => ['name' => 'Facebook', 'variant' => true, 'manual' => true],
            'bilibili' => ['name' => 'B站', 'manual' => true],
            'email' => ['name' => '邮件推送', 'variant' => true],
        ];
    }

    /**
     * 平台内容变体：根据平台改写标题/摘要
     */
    public static function variantFor(array $article, string $platform): array {
        $title = $article['title'] ?? '';
        $excerpt = $article['excerpt'] ?? '';
        $url = SITE_URL . '/article/' . ($article['slug'] ?? '');
        $tags = $article['tags'] ?? [];

        $out = ['title' => $title, 'excerpt' => $excerpt, 'tags' => $tags, 'url' => $url];

        switch ($platform) {
            case 'wechat':
                // 公众号：保留完整标题，摘要作导语
                $out['excerpt'] = mb_substr($excerpt, 0, 120);
                break;
            case 'xiaohongshu':
                // 小红书：标题带 emoji + 话题标签，内容短句化
                $out['title'] = '📌 ' . $title;
                $out['excerpt'] = mb_substr($excerpt, 0, 200) . "\n\n# " . implode(' #', array_slice($tags, 0, 3));
                break;
            case 'zhihu':
                // 知乎：疑问式标题 + 问题描述
                $out['title'] = '如何理解「' . $title . '」？';
                $out['excerpt'] = mb_substr($excerpt, 0, 150) . "\n\n" . $url;
                break;
            case 'linkedin':
                // LinkedIn：专业口吻，带 emoji 分段
                $out['title'] = $title;
                $out['excerpt'] = "📊 " . mb_substr($excerpt, 0, 180) . "\n\nFull article: " . $url;
                break;
            case 'twitter':
                // X：短标题 + 链接 + 话题
                $hash = array_map(fn($t) => '#' . str_replace(' ', '', $t), array_slice($tags, 0, 2));
                $out['title'] = mb_substr($title, 0, 60);
                $out['excerpt'] = mb_substr($excerpt, 0, 100) . ' ' . implode(' ', $hash) . ' ' . $url;
                break;
            case 'email':
                $out['excerpt'] = $excerpt;
                break;
        }
        return $out;
    }

    /**
     * 发布内容到平台
     * @return array ['ok'=>bool, 'message'=>string, 'platform_id'=>string]
     */
    public static function publish(array $article, string $platform, array $opts = []): array {
        $cfg = self::config();
        $var = self::variantFor($article, $platform);

        if ($platform === 'wechat') {
            // 公众号：需要图文素材 media_id 或群发文本
            $mediaId = $opts['media_id'] ?? '';
            if (!empty($mediaId)) {
                $r = WechatMp::massSendByTag(['media_id' => $mediaId], 'mpnews', 0);
            } else {
                $content = ($opts['send_type'] ?? 'text') === 'text'
                    ? $var['title'] . "\n\n" . $var['excerpt'] . "\n\n" . $var['url']
                    : $var['title'] . "\n\n" . $var['excerpt'];
                $r = WechatMp::massSendByTag(['content' => $content], 'text', 0);
            }
            return ['ok' => ($r['errcode'] ?? 1) === 0, 'message' => $r['errmsg'] ?? '', 'platform_id' => $r['msg_id'] ?? ''];
        }

        if ($platform === 'email') {
            // 邮件推送：给订阅者发文章
            try {
                require_once __DIR__ . '/../admin/config.php';
                $subscribers = json_read(DATA_DIR . '/newsletter/subscribers.json');
                $bm = BillionMail::fromConfig();
                $sent = 0;
                if ($bm) {
                    foreach ($subscribers as $s) {
                        if (($s['status'] ?? '') === 'subscribed' && !empty($s['email'])) {
                            $bm->send($s['email'], $var['title'], $var['excerpt'] . "\n\n" . $var['url']);
                            $sent++;
                        }
                    }
                }
                return ['ok' => $sent > 0, 'message' => "已推送 {$sent} 位订阅者", 'platform_id' => ''];
            } catch (Exception $e) {
                return ['ok' => false, 'message' => $e->getMessage(), 'platform_id' => ''];
            }
        }

        // 其他平台：无开放 API → 生成带链接的分享文案（mark as manual）
        $msg = "已生成「{$platform}」分享内容，请手动发布：" . $var['title'] . "\n" . $var['excerpt'];
        return ['ok' => true, 'message' => $msg, 'platform_id' => 'manual_' . substr(bin2hex(random_bytes(4)), 0, 6), 'variant' => $var];
    }

    /**
     * 定时发布到多平台
     */
    public static function schedule(array $article, array $platforms, string $sendAt): array {
        $queue = json_read(self::$queueFile);
        $task = [
            'id' => 'pub_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 6),
            'article_id' => $article['id'] ?? '',
            'title' => $article['title'] ?? '',
            'platforms' => $platforms,
            'send_at' => $sendAt,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $queue[] = $task;
        json_write(self::$queueFile, $queue);
        return $task;
    }

    /**
     * cron 处理定时发布队列
     */
    public static function processQueue(): array {
        $queue = json_read(self::$queueFile);
        $done = [];
        $remaining = [];
        foreach ($queue as $task) {
            if (($task['status'] ?? '') === 'sent') continue;
            if (strtotime($task['send_at'] ?? '') > time()) { $remaining[] = $task; continue; }
            $article = get_article($task['article_id'] ?? '');
            $results = [];
            foreach (($task['platforms'] ?? []) as $p) {
                if ($article) $results[$p] = self::publish($article, $p);
                else $results[$p] = ['ok' => false, 'message' => '文章不存在'];
            }
            $task['status'] = 'sent';
            $task['sent_at'] = date('Y-m-d H:i:s');
            $task['results'] = $results;
            $remaining[] = $task;
            self::log($task, $results);
            $done[] = $task['id'];
        }
        json_write(self::$queueFile, $remaining);
        return $done;
    }

    /**
     * 平台连接配置（公众号/邮件等凭据）
     */
    public static function config(): array {
        return [
            'wechat_configured' => !empty((WechatMp::config())['appid']),
            'email_configured' => (bool)BillionMail::fromConfig(),
        ];
    }

    /**
     * 发布记录
     */
    public static function log(array $task, array $results): void {
        $log = json_read(self::$logFile);
        $log[] = [
            'time' => date('Y-m-d H:i:s'),
            'article_id' => $task['article_id'] ?? '',
            'title' => $task['title'] ?? '',
            'platforms' => $task['platforms'] ?? [],
            'results' => $results,
            'status' => 'sent',
        ];
        json_write(self::$logFile, array_slice($log, -200));
    }

    public static function recentLog(int $limit = 30): array {
        return array_slice(array_reverse(json_read(self::$logFile)), 0, $limit);
    }

    public static function queue(): array {
        return json_read(self::$queueFile);
    }

    public static function cancelTask(string $id): void {
        $queue = array_values(array_filter(json_read(self::$queueFile), fn($t) => $t['id'] !== $id));
        json_write(self::$queueFile, $queue);
    }
}
