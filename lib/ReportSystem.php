<?php
/**
 * 用户举报系统
 * 前台用户举报内容，管理端审核处理
 */
require_once __DIR__ . '/../admin/config.php';

class ReportSystem {
    private static string $file = DATA_DIR . '/reports.json';

    /**
     * 获取所有举报
     */
    public static function all(): array {
        return json_read(self::$file);
    }

    /**
     * 提交举报
     */
    public static function submit(array $data): array {
        $reports = self::all();

        // 防重复：同一用户对同一内容24小时内只能举报一次
        $userId = $data['user_id'] ?? '';
        $targetType = $data['target_type'] ?? '';
        $targetId = $data['target_id'] ?? '';

        foreach ($reports as $r) {
            if ($r['user_id'] === $userId && $r['target_type'] === $targetType && $r['target_id'] === $targetId) {
                $createdAt = strtotime($r['created_at']);
                if (time() - $createdAt < 86400) {
                    return ['ok' => false, 'error' => '您已举报过此内容，请等待处理'];
                }
            }
        }

        $report = [
            'id' => 'rpt_' . bin2hex(random_bytes(8)),
            'user_id' => $userId,
            'user_name' => $data['user_name'] ?? '',
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_title' => $data['target_title'] ?? '',
            'reason' => $data['reason'] ?? '',
            'category' => $data['category'] ?? 'other',
            'status' => 'pending',
            'admin_note' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'resolved_at' => '',
        ];

        $reports[] = $report;
        json_write(self::$file, $reports);
        return ['ok' => true, 'report' => $report];
    }

    /**
     * 处理举报
     */
    public static function resolve(string $id, string $status, string $note = ''): bool {
        $reports = self::all();

        foreach ($reports as &$r) {
            if ($r['id'] === $id) {
                $r['status'] = $status; // resolved / dismissed
                $r['admin_note'] = $note;
                $r['resolved_at'] = date('Y-m-d H:i:s');
                json_write(self::$file, $reports);
                return true;
            }
        }

        return false;
    }

    /**
     * 获取待处理举报数
     */
    public static function pendingCount(): int {
        $reports = self::all();
        $count = 0;
        foreach ($reports as $r) {
            if ($r['status'] === 'pending') $count++;
        }
        return $count;
    }

    /**
     * 按状态筛选
     */
    public static function byStatus(string $status): array {
        return array_filter(self::all(), fn($r) => $r['status'] === $status);
    }

    /**
     * 举报原因选项
     */
    public static function reasons(): array {
        return [
            'spam' => '垃圾信息 / 广告',
            'abuse' => '辱骂 / 攻击性内容',
            'illegal' => '违法信息',
            'plagiarism' => '抄袭 / 侵权',
            'offtopic' => '不相关 / 偏题',
            'low_quality' => '低质量内容',
            'other' => '其他',
        ];
    }
}
