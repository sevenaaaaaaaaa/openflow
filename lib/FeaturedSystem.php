<?php
/**
 * 内容置顶/推荐位管理
 * 支持全局置顶、首页推荐、分类推荐位
 */
require_once __DIR__ . '/../admin/config.php';

class FeaturedSystem {
    private static string $file = DATA_DIR . '/featured.json';

    /**
     * 获取所有推荐位配置
     */
    public static function all(): array {
        return json_read(self::$file);
    }

    /**
     * 获取推荐内容列表
     */
    public static function getItems(string $position = ''): array {
        $config = self::all();
        $items = $config['items'] ?? [];

        if ($position) {
            $items = array_filter($items, fn($item) => $item['position'] === $position);
        }

        // 按排序值排序
        usort($items, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return $items;
    }

    /**
     * 添加推荐内容
     */
    public static function add(array $data): array {
        $config = self::all();
        if (!isset($config['items'])) $config['items'] = [];

        $item = [
            'id' => 'feat_' . bin2hex(random_bytes(6)),
            'target_type' => $data['target_type'] ?? 'article',
            'target_id' => $data['target_id'] ?? '',
            'title' => $data['title'] ?? '',
            'cover' => $data['cover'] ?? '',
            'position' => $data['position'] ?? 'homepage',
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'enabled' => true,
            'start_at' => $data['start_at'] ?? '',
            'end_at' => $data['end_at'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $config['items'][] = $item;
        json_write(self::$file, $config);
        return $item;
    }

    /**
     * 更新推荐内容
     */
    public static function update(string $id, array $data): ?array {
        $config = self::all();
        if (!isset($config['items'])) return null;

        foreach ($config['items'] as &$item) {
            if ($item['id'] === $id) {
                foreach ($data as $key => $value) {
                    if (in_array($key, ['title', 'cover', 'position', 'sort_order', 'enabled', 'start_at', 'end_at'])) {
                        $item[$key] = $value;
                    }
                }
                json_write(self::$file, $config);
                return $item;
            }
        }

        return null;
    }

    /**
     * 删除推荐内容
     */
    public static function remove(string $id): bool {
        $config = self::all();
        if (!isset($config['items'])) return false;

        $before = count($config['items']);
        $config['items'] = array_values(array_filter($config['items'], fn($item) => $item['id'] !== $id));

        if (count($config['items']) < $before) {
            json_write(self::$file, $config);
            return true;
        }

        return false;
    }

    /**
     * 获取可用推荐位
     */
    public static function positions(): array {
        return [
            'homepage' => '首页轮播',
            'sidebar' => '侧边栏推荐',
            'article_top' => '文章顶部推荐',
            'article_bottom' => '文章底部推荐',
            'category' => '分类页推荐',
            'footer' => '底部推荐',
        ];
    }

    /**
     * 获取当前有效的推荐内容（考虑时间范围）
     */
    public static function getActive(string $position = ''): array {
        $items = self::getItems($position);
        $now = date('Y-m-d H:i:s');

        return array_filter($items, function ($item) use ($now) {
            if (!$item['enabled']) return false;
            if (!empty($item['start_at']) && $item['start_at'] > $now) return false;
            if (!empty($item['end_at']) && $item['end_at'] < $now) return false;
            return true;
        });
    }
}
