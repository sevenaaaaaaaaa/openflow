<?php
/**
 * 收藏/书签系统
 * 支持文章、帖子、课程等内容的收藏与取消收藏
 */
require_once __DIR__ . '/../admin/config.php';

class BookmarkSystem {
    private static string $file = DATA_DIR . '/bookmarks.json';

    /**
     * 获取所有收藏
     */
    public static function all(): array {
        return json_read(self::$file);
    }

    /**
     * 添加收藏
     */
    public static function add(string $userId, string $targetType, string $targetId, string $title = ''): bool {
        $bookmarks = self::all();
        $key = "{$userId}:{$targetType}:{$targetId}";

        if (isset($bookmarks[$key])) return false; // 已收藏

        $bookmarks[$key] = [
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'title' => $title,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        json_write(self::$file, $bookmarks);
        return true;
    }

    /**
     * 取消收藏
     */
    public static function remove(string $userId, string $targetType, string $targetId): bool {
        $bookmarks = self::all();
        $key = "{$userId}:{$targetType}:{$targetId}";

        if (!isset($bookmarks[$key])) return false;

        unset($bookmarks[$key]);
        json_write(self::$file, $bookmarks);
        return true;
    }

    /**
     * 切换收藏状态
     */
    public static function toggle(string $userId, string $targetType, string $targetId, string $title = ''): array {
        $key = "{$userId}:{$targetType}:{$targetId}";
        $bookmarks = self::all();

        if (isset($bookmarks[$key])) {
            unset($bookmarks[$key]);
            json_write(self::$file, $bookmarks);
            return ['bookmarked' => false, 'count' => self::count($targetType, $targetId)];
        } else {
            $bookmarks[$key] = [
                'user_id' => $userId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'title' => $title,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            json_write(self::$file, $bookmarks);
            return ['bookmarked' => true, 'count' => self::count($targetType, $targetId)];
        }
    }

    /**
     * 检查是否已收藏
     */
    public static function isBookmarked(string $userId, string $targetType, string $targetId): bool {
        $bookmarks = self::all();
        $key = "{$userId}:{$targetType}:{$targetId}";
        return isset($bookmarks[$key]);
    }

    /**
     * 获取用户的收藏列表
     */
    public static function getUserBookmarks(string $userId, ?string $type = null, int $limit = 50, int $offset = 0): array {
        $bookmarks = self::all();
        $result = [];

        foreach ($bookmarks as $b) {
            if ($b['user_id'] !== $userId) continue;
            if ($type && $b['target_type'] !== $type) continue;
            $result[] = $b;
        }

        // 按时间倒序
        usort($result, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return array_slice($result, $offset, $limit);
    }

    /**
     * 统计某内容的收藏数
     */
    public static function count(string $targetType, string $targetId): int {
        $bookmarks = self::all();
        $count = 0;
        foreach ($bookmarks as $b) {
            if ($b['target_type'] === $targetType && $b['target_id'] === $targetId) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 批量获取收藏数
     */
    public static function countBatch(string $targetType, array $targetIds): array {
        $bookmarks = self::all();
        $counts = array_fill_keys($targetIds, 0);

        foreach ($bookmarks as $b) {
            if ($b['target_type'] === $targetType && isset($counts[$b['target_id']])) {
                $counts[$b['target_id']]++;
            }
        }

        return $counts;
    }

    /**
     * 删除用户的收藏（如删除内容时清理）
     */
    public static function deleteByTarget(string $targetType, string $targetId): int {
        $bookmarks = self::all();
        $deleted = 0;

        foreach ($bookmarks as $key => $b) {
            if ($b['target_type'] === $targetType && $b['target_id'] === $targetId) {
                unset($bookmarks[$key]);
                $deleted++;
            }
        }

        if ($deleted > 0) json_write(self::$file, $bookmarks);
        return $deleted;
    }
}
