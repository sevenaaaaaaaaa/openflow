<?php
declare(strict_types=1);
/**
 * AiSchedule —— 后台 AI 任务的「执行时段」闸
 *
 * 【为什么】cron 每分钟跑一次，里面挂着一批会调 AI 的后台任务（运营洞察预热、GEO、热点雷达、
 * 飞轮选题、产品发现、入驻队列、岗位交班）。这些任务没有人在等结果，却会花钱；
 * 9 月曾因重试风暴一周烧掉两个月用量的大头。所以给它们定规矩：
 *
 *   ① 只在工作日执行（周末、法定节假日不跑；调休上班日默认也不跑，除非后台打开）
 *   ② 只在凌晨 00:00–06:00 执行；06:00 前必须结束 —— 每次 AI 调用的超时被压到「距窗口结束的剩余秒数」，
 *      所以最后一次调用最迟 06:00 就会断开，不会拖到白天
 *
 * 作用范围：**仅 cron 驱动的后台 AI 调用**（api/cron.php 置 $GLOBALS['OF_AI_BACKGROUND']）。
 *   - 定时发布、邮件、Webhook 重试、收款等「重要任务」不花 AI 额度，不受此限。
 *   - 人在后台点按钮 / 访客用客服触发的 AI 不受时段限制（只受预算限制，见 AiBudget）。
 *   - 手动脚本传 ['manual'=>true] 同样绕过时段（仍受预算限制）。
 *
 * 配置：settings.json 的 ai_schedule：
 *   enabled(默认 true) · window_start(0) · window_end(6) · run_on_makeup(默认 false，调休上班日是否执行)
 *   extra_holidays / extra_workdays：['YYYY-MM-DD', ...]，补录/修正内置放假表
 */
final class AiSchedule {

    /**
     * 内置放假表（按国务院年度放假安排录入）。off = 放假区间（含首尾），makeup = 调休上班日（周末补班）。
     * 表里没有的年份只按周末判断，并在后台提示「请补录」——宁可提示，不假装知道。
     */
    public const HOLIDAYS = [
        '2026' => [
            'off' => [
                ['2026-01-01', '2026-01-03'],   // 元旦
                ['2026-02-15', '2026-02-23'],   // 春节
                ['2026-04-04', '2026-04-06'],   // 清明
                ['2026-05-01', '2026-05-05'],   // 劳动节
                ['2026-06-19', '2026-06-21'],   // 端午
                ['2026-09-25', '2026-09-27'],   // 中秋
                ['2026-10-01', '2026-10-07'],   // 国庆
            ],
            'makeup' => ['2026-01-04', '2026-02-14', '2026-02-28', '2026-05-09', '2026-09-20', '2026-10-10'],
        ],
    ];

    public static function settings(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $c = is_array($s['ai_schedule'] ?? null) ? $s['ai_schedule'] : [];
        $out = array_merge([
            'enabled'        => true,
            'window_start'   => 0,
            'window_end'     => 6,
            'run_on_makeup'  => false,
            'extra_holidays' => [],
            'extra_workdays' => [],
        ], $c);
        $out['enabled']       = (bool)$out['enabled'];
        $out['run_on_makeup'] = (bool)$out['run_on_makeup'];
        $out['window_start']  = max(0, min(23, (int)$out['window_start']));
        $out['window_end']    = max($out['window_start'] + 1, min(24, (int)$out['window_end']));
        foreach (['extra_holidays', 'extra_workdays'] as $k) $out[$k] = self::cleanDates($out[$k]);
        return $out;
    }

    public static function save(array $patch): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        if (!is_array($s)) $s = [];
        $cur = self::settings();
        if (array_key_exists('enabled', $patch))       $cur['enabled'] = (bool)$patch['enabled'];
        if (array_key_exists('run_on_makeup', $patch)) $cur['run_on_makeup'] = (bool)$patch['run_on_makeup'];
        if (isset($patch['window_start'])) $cur['window_start'] = (int)$patch['window_start'];
        if (isset($patch['window_end']))   $cur['window_end'] = (int)$patch['window_end'];
        foreach (['extra_holidays', 'extra_workdays'] as $k) if (isset($patch[$k])) $cur[$k] = self::cleanDates($patch[$k]);
        $s['ai_schedule'] = $cur;
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
        return self::settings();
    }

    /** 接受数组或「换行/逗号/空格」分隔的字符串，返回合法 YYYY-MM-DD 去重排序 */
    public static function cleanDates($v): array {
        if (is_string($v)) $v = preg_split('/[\s,，;；]+/u', $v) ?: [];
        $out = [];
        foreach ((array)$v as $d) {
            $d = trim((string)$d);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false) $out[$d] = true;
        }
        $k = array_keys($out); sort($k);
        return $k;
    }

    /** 该年份是否在内置放假表里（后台据此提醒「明年请补录」） */
    public static function yearCovered(int $year): bool {
        return isset(self::HOLIDAYS[(string)$year]);
    }

    /**
     * 某天的类型：workday | weekend | holiday | makeup（调休上班日）
     * 优先级：后台补录的节假日 > 后台补录的工作日 > 内置放假表 > 周末。
     */
    public static function dayType(string $ymd, ?array $cfg = null): string {
        $cfg = $cfg ?? self::settings();
        if (in_array($ymd, $cfg['extra_holidays'], true)) return 'holiday';
        if (in_array($ymd, $cfg['extra_workdays'], true)) return 'workday';
        $tbl = self::HOLIDAYS[substr($ymd, 0, 4)] ?? null;
        if ($tbl) {
            foreach ($tbl['off'] as [$a, $b]) if ($ymd >= $a && $ymd <= $b) return 'holiday';
            if (in_array($ymd, $tbl['makeup'], true)) return 'makeup';
        }
        $dow = (int)date('N', strtotime($ymd . ' 12:00:00'));
        return $dow >= 6 ? 'weekend' : 'workday';
    }

    /**
     * 此刻后台 AI 任务能不能跑。
     * @return array{allowed:bool, reason:string, type:string, seconds_left:?int}
     *   seconds_left：距执行窗口结束的秒数（allowed 时有值；限制关闭时为 null，表示不设限）
     */
    public static function decision(?int $ts = null): array {
        $cfg = self::settings();
        if (!$cfg['enabled']) return ['allowed' => true, 'reason' => '时段限制未开启', 'type' => 'off', 'seconds_left' => null];
        $ts = $ts ?? time();
        $ymd = date('Y-m-d', $ts);
        $type = self::dayType($ymd, $cfg);
        $label = ['weekend' => '周末', 'holiday' => '法定节假日', 'makeup' => '调休上班日（按设置不执行）'];
        if ($type === 'weekend' || $type === 'holiday' || ($type === 'makeup' && !$cfg['run_on_makeup'])) {
            return ['allowed' => false, 'reason' => $label[$type] . '不执行后台 AI 任务', 'type' => $type, 'seconds_left' => 0];
        }
        $start = strtotime($ymd . sprintf(' %02d:00:00', $cfg['window_start']));
        $end   = $cfg['window_end'] >= 24 ? strtotime($ymd . ' 00:00:00') + 86400 : strtotime($ymd . sprintf(' %02d:00:00', $cfg['window_end']));
        if ($ts < $start || $ts >= $end) {
            return ['allowed' => false, 'reason' => sprintf('不在执行窗口（%02d:00–%02d:00）', $cfg['window_start'], $cfg['window_end']),
                    'type' => $type, 'seconds_left' => 0];
        }
        return ['allowed' => true, 'reason' => '在执行窗口内', 'type' => $type, 'seconds_left' => $end - $ts];
    }

    /** 便捷：此刻后台 AI 任务可否执行 */
    public static function allowedNow(): bool {
        return self::decision()['allowed'];
    }

    /** 下一个执行窗口的开始时间（后台展示用；最多往后找 400 天） */
    public static function nextWindowStart(?int $from = null): ?int {
        $cfg = self::settings();
        $from = $from ?? time();
        for ($i = 0; $i < 400; $i++) {
            $day = strtotime(date('Y-m-d', $from) . ' 00:00:00') + $i * 86400;
            $ymd = date('Y-m-d', $day);
            $t = self::dayType($ymd, $cfg);
            if ($t === 'weekend' || $t === 'holiday' || ($t === 'makeup' && !$cfg['run_on_makeup'])) continue;
            $start = strtotime($ymd . sprintf(' %02d:00:00', $cfg['window_start']));
            if ($start > $from) return $start;
        }
        return null;
    }
}
