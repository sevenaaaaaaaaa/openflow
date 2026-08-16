<?php
/**
 * 关注/粉丝系统
 * 支持用户间关注、取关、粉丝列表、关注列表
 */
require_once __DIR__ . '/../admin/config.php';

class FollowSystem {
    private static string $file = DATA_DIR . '/follows.json';

    /**
     * 获取所有关注关系
     */
    public static function all(): array {
        return json_read(self::$file);
    }

    /**
     * 关注用户
     */
    public static function follow(string $followerId, string $followingId): bool {
        if ($followerId === $followingId) return false;

        $follows = self::all();
        $key = "{$followerId}:{$followingId}";

        if (isset($follows[$key])) return false; // 已关注

        $follows[$key] = [
            'follower_id' => $followerId,
            'following_id' => $followingId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        json_write(self::$file, $follows);
        return true;
    }

    /**
     * 取消关注
     */
    public static function unfollow(string $followerId, string $followingId): bool {
        $follows = self::all();
        $key = "{$followerId}:{$followingId}";

        if (!isset($follows[$key])) return false;

        unset($follows[$key]);
        json_write(self::$file, $follows);
        return true;
    }

    /**
     * 切换关注状态
     */
    public static function toggle(string $followerId, string $followingId): array {
        $key = "{$followerId}:{$followingId}";
        $follows = self::all();

        if (isset($follows[$key])) {
            unset($follows[$key]);
            json_write(self::$file, $follows);
            return ['following' => false];
        } else {
            if ($followerId === $followingId) return ['following' => false, 'error' => '不能关注自己'];
            $follows[$key] = [
                'follower_id' => $followerId,
                'following_id' => $followingId,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            json_write(self::$file, $follows);
            return ['following' => true];
        }
    }

    /**
     * 检查是否关注
     */
    public static function isFollowing(string $followerId, string $followingId): bool {
        $follows = self::all();
        return isset($follows["{$followerId}:{$followingId}"]);
    }

    /**
     * 获取关注列表
     */
    public static function getFollowing(string $userId, int $limit = 100): array {
        $follows = self::all();
        $result = [];

        foreach ($follows as $f) {
            if ($f['follower_id'] === $userId) {
                $result[] = $f;
            }
        }

        usort($result, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($result, 0, $limit);
    }

    /**
     * 获取粉丝列表
     */
    public static function getFollowers(string $userId, int $limit = 100): array {
        $follows = self::all();
        $result = [];

        foreach ($follows as $f) {
            if ($f['following_id'] === $userId) {
                $result[] = $f;
            }
        }

        usort($result, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($result, 0, $limit);
    }

    /**
     * 获取关注数
     */
    public static function followingCount(string $userId): int {
        $follows = self::all();
        $count = 0;
        foreach ($follows as $f) {
            if ($f['follower_id'] === $userId) $count++;
        }
        return $count;
    }

    /**
     * 获取粉丝数
     */
    public static function followersCount(string $userId): int {
        $follows = self::all();
        $count = 0;
        foreach ($follows as $f) {
            if ($f['following_id'] === $userId) $count++;
        }
        return $count;
    }

    /**
     * 获取互相关注列表
     */
    public static function getMutual(string $userId): array {
        $follows = self::all();
        $result = [];

        foreach ($follows as $f) {
            if ($f['follower_id'] === $userId) {
                if (isset($follows["{$f['following_id']}:{$userId}"])) {
                    $result[] = $f['following_id'];
                }
            }
        }

        return $result;
    }

    /**
     * 删除用户相关的所有关注关系
     */
    public static function deleteByUser(string $userId): int {
        $follows = self::all();
        $deleted = 0;

        foreach ($follows as $key => $f) {
            if ($f['follower_id'] === $userId || $f['following_id'] === $userId) {
                unset($follows[$key]);
                $deleted++;
            }
        }

        if ($deleted > 0) json_write(self::$file, $follows);
        return $deleted;
    }
}
