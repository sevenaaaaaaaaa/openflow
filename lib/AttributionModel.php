<?php
/**
 * 多触点归因模型
 * 支持首次触点、末次触点、线性归因、时间衰减、U型归因
 */
require_once __DIR__ . '/../admin/config.php';

class AttributionModel {
    private static string $file = DATA_DIR . '/attribution_touchpoints.json';

    /**
     * 记录触点
     */
    public static function addTouchpoint(array $data): array {
        $touchpoints = json_read(self::$file);
        $tp = [
            'id' => 'tp_' . bin2hex(random_bytes(6)),
            'user_id' => $data['user_id'] ?? '',
            'source' => $data['source'] ?? '',
            'medium' => $data['medium'] ?? '',
            'campaign' => $data['campaign'] ?? '',
            'content' => $data['content'] ?? '',
            'page' => $data['page'] ?? '',
            'event' => $data['event'] ?? 'pageview',
            'value' => (float)($data['value'] ?? 0),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $touchpoints[] = $tp;
        json_write(self::$file, $touchpoints);
        return $tp;
    }

    /**
     * 获取用户的所有触点
     */
    public static function getUserTouchpoints(string $userId): array {
        $touchpoints = json_read(self::$file);
        return array_values(array_filter($touchpoints, fn($tp) => $tp['user_id'] === $userId));
    }

    /**
     * 获取指定时间范围内的触点
     */
    public static function getTouchpoints(string $startDate, string $endDate, ?string $campaign = null): array {
        $touchpoints = json_read(self::$file);
        return array_values(array_filter($touchpoints, function ($tp) use ($startDate, $endDate, $campaign) {
            if ($tp['timestamp'] < $startDate || $tp['timestamp'] > $endDate) return false;
            if ($campaign && $tp['campaign'] !== $campaign) return false;
            return true;
        }));
    }

    /**
     * 首次触点归因 (First Touch)
     * 100% 归因给第一个触点
     */
    public static function firstTouch(array $touchpoints): array {
        if (empty($touchpoints)) return [];
        $first = reset($touchpoints);
        return [$first['source'] => 100];
    }

    /**
     * 末次触点归因 (Last Touch)
     * 100% 归因给最后一个触点
     */
    public static function lastTouch(array $touchpoints): array {
        if (empty($touchpoints)) return [];
        $last = end($touchpoints);
        return [$last['source'] => 100];
    }

    /**
     * 线性归因 (Linear)
     * 平均分配给所有触点
     */
    public static function linear(array $touchpoints): array {
        if (empty($touchpoints)) return [];
        $count = count($touchpoints);
        $weight = 100 / $count;
        $result = [];
        foreach ($touchpoints as $tp) {
            $src = $tp['source'];
            $result[$src] = ($result[$src] ?? 0) + $weight;
        }
        return $result;
    }

    /**
     * 时间衰减归因 (Time Decay)
     * 越接近转化的触点获得越高权重
     */
    public static function timeDecay(array $touchpoints, float $halfLifeDays = 7): array {
        if (empty($touchpoints)) return [];
        $lastTime = end($touchpoints)['timestamp'];
        $lastTs = strtotime($lastTime);
        $decay = log(2) / ($halfLifeDays * 86400);
        $weights = [];

        foreach ($touchpoints as $tp) {
            $hoursAgo = ($lastTs - strtotime($tp['timestamp'])) / 3600;
            $weights[] = exp(-$decay * $hoursAgo * 3600);
        }

        $total = array_sum($weights);
        $result = [];
        foreach ($touchpoints as $i => $tp) {
            $pct = ($weights[$i] / $total) * 100;
            $src = $tp['source'];
            $result[$src] = ($result[$src] ?? 0) + $pct;
        }

        return $result;
    }

    /**
     * U 型归因 (Position Based / U-Shaped)
     * 首次和末次各 40%，中间触点平分 20%
     */
    public static function uShaped(array $touchpoints): array {
        if (empty($touchpoints)) return [];
        $count = count($touchpoints);
        $result = [];
        $first = $touchpoints[0]['source'];
        $last = end($touchpoints)['source'];

        if ($count === 1) {
            return [$first => 100];
        }

        $result[$first] = 40;
        $result[$last] = ($result[$last] ?? 0) + 40;

        if ($count > 2) {
            $midWeight = 20 / ($count - 2);
            for ($i = 1; $i < $count - 1; $i++) {
                $src = $touchpoints[$i]['source'];
                $result[$src] = ($result[$src] ?? 0) + $midWeight;
            }
        }

        return $result;
    }

    /**
     * W 型归因 (W-Shaped)
     * 首次、中间关键触点、末次各 30%，其余平分 10%
     */
    public static function wShaped(array $touchpoints): array {
        if (empty($touchpoints)) return [];
        $count = count($touchpoints);
        $result = [];
        $first = $touchpoints[0]['source'];
        $last = end($touchpoints)['source'];

        if ($count <= 2) {
            return [$first => 50, $last => 50];
        }

        $midIndex = intdiv($count, 2);
        $mid = $touchpoints[$midIndex]['source'];

        $result[$first] = 30;
        $result[$mid] = ($result[$mid] ?? 0) + 30;
        $result[$last] = ($result[$last] ?? 0) + 30;

        $remaining = 10;
        for ($i = 0; $i < $count; $i++) {
            if ($i === 0 || $i === $midIndex || $i === $count - 1) continue;
            $src = $touchpoints[$i]['source'];
            $result[$src] = ($result[$src] ?? 0) + ($remaining / max(1, $count - 3));
        }

        return $result;
    }

    /**
     * 使用指定模型计算归因
     */
    public static function calculate(string $model, string $userId): array {
        $touchpoints = self::getUserTouchpoints($userId);
        usort($touchpoints, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        return match ($model) {
            'first_touch' => self::firstTouch($touchpoints),
            'last_touch' => self::lastTouch($touchpoints),
            'linear' => self::linear($touchpoints),
            'time_decay' => self::timeDecay($touchpoints),
            'u_shaped' => self::uShaped($touchpoints),
            'w_shaped' => self::wShaped($touchpoints),
            default => [],
        };
    }

    /**
     * 批量归因统计
     */
    public static function batchStats(string $model, string $startDate, string $endDate): array {
        $touchpoints = self::getTouchpoints($startDate, $endDate);
        $userGroups = [];
        foreach ($touchpoints as $tp) {
            $userGroups[$tp['user_id']][] = $tp;
        }

        $sourceTotals = [];
        foreach ($userGroups as $userId => $tps) {
            usort($tps, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));
            $attribution = match ($model) {
                'first_touch' => self::firstTouch($tps),
                'last_touch' => self::lastTouch($tps),
                'linear' => self::linear($tps),
                'time_decay' => self::timeDecay($tps),
                'u_shaped' => self::uShaped($tps),
                'w_shaped' => self::wShaped($tps),
                default => [],
            };
            foreach ($attribution as $src => $pct) {
                $sourceTotals[$src] = ($sourceTotals[$src] ?? 0) + $pct;
            }
        }

        arsort($sourceTotals);
        return $sourceTotals;
    }

    /**
     * 可用归因模型
     */
    public static function models(): array {
        return [
            'first_touch' => '首次触点 (100% 第一个来源)',
            'last_touch' => '末次触点 (100% 最后来源)',
            'linear' => '线性归因 (所有触点均分)',
            'time_decay' => '时间衰减 (越近权重越高)',
            'u_shaped' => 'U型归因 (首末各40%，中间20%)',
            'w_shaped' => 'W型归因 (首中末各30%，其余10%)',
        ];
    }
}
