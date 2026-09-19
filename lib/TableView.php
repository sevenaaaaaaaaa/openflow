<?php
declare(strict_types=1);
/**
 * TableView —— 同一批记录的多视图布局（多维表格的「多维」）
 *
 * 【为什么】记录只有一种看法时，用户会去别的工具里建第二张表。
 * 表格看全貌、看板看进度、日历看排期、甘特看时间跨度——同一份数据四种看法。
 *
 * 本文件只做「数据 → 布局」的纯计算（分组/分桶/时间轴刻度），不拼 HTML：
 * 这样视图逻辑能单测，页面只负责展示。
 */

if (!function_exists('tv_views')) {
    function tv_views(): array {
        return ['grid' => '表格', 'board' => '看板', 'calendar' => '日历', 'gantt' => '甘特', 'tree' => '树视图'];
    }

    /** 记录行：原值 + 解析值 + 展示文本，视图层统一吃这个结构 */
    function tv_rows(string $typeSlug, array $opts = []): array {
        $type = cpt_type($typeSlug);
        if ($type === null) return [];
        $rows = [];
        foreach (cpt_entries_resolved($typeSlug, $opts) as $e) {
            $values = (array) ($e['fields'] ?? []);
            $resolved = (array) ($e['resolved'] ?? []);
            $cells = [];
            foreach ((array) ($type['fields'] ?? []) as $f) {
                $k = (string) ($f['key'] ?? '');
                $cells[$k] = tv_display($f, $resolved[$k] ?? ($values[$k] ?? ''));
            }
            $rows[] = [
                'id' => (string) ($e['id'] ?? ''),
                'title' => (string) ($e['title'] ?? ''),
                'slug' => (string) ($e['slug'] ?? ''),
                'status' => (string) ($e['status'] ?? 'draft'),
                'values' => $values,
                'resolved' => $resolved,
                'cells' => $cells,
            ];
        }
        return $rows;
    }

    /** 字段值的展示文本（关联取标题、布尔取是/否、数组用顿号连接） */
    function tv_display(array $field, mixed $v): string {
        $t = (string) ($field['type'] ?? 'text');
        if ($t === 'bool') return !empty($v) ? '是' : '否';
        if ($t === 'relation') {
            $names = [];
            foreach ((array) $v as $x) $names[] = is_array($x) ? (string) ($x['title'] ?? '') : (string) $x;
            return implode('、', array_filter($names, static fn(string $s): bool => $s !== ''));
        }
        if (is_array($v)) {
            $flat = [];
            foreach ($v as $x) $flat[] = is_array($x) ? (string) ($x['title'] ?? '') : (string) $x;
            return implode('、', array_filter($flat, static fn(string $s): bool => $s !== ''));
        }
        if ($t === 'rollup' && is_numeric($v) && (float) $v != (int) (float) $v) return (string) round((float) $v, 2);
        return (string) $v;
    }

    /** 看板分组字段：第一个 select 字段；没有就按发布状态分 */
    function tv_group_field(array $type): ?array {
        foreach ((array) ($type['fields'] ?? []) as $f) {
            if (($f['type'] ?? '') === 'select') return $f;
        }
        return null;
    }

    /** 看板列：[['key'=>列标识,'label'=>列名,'rows'=>[...]]]（通用：字段规格 + 行） */
    function tv_board_columns_of(array $fields, array $rows, ?array $groupField = null): array {
        $gf = $groupField;
        if ($gf === null) foreach ($fields as $f) if (($f['type'] ?? '') === 'select') { $gf = $f; break; }
        $cols = [];
        if ($gf !== null) {
            $key = (string) ($gf['key'] ?? '');
            foreach (array_merge([''], (array) ($gf['options'] ?? [])) as $o) {
                $cols[(string) $o] = ['key' => (string) $o, 'label' => $o === '' ? '未填' : (string) $o, 'rows' => []];
            }
            foreach ($rows as $r) {
                $v = (string) ($r['values'][$key] ?? '');
                if (!isset($cols[$v])) $cols[$v] = ['key' => $v, 'label' => $v === '' ? '未填' : $v, 'rows' => []];
                $cols[$v]['rows'][] = $r;
            }
        } else {
            $cols = [
                'draft' => ['key' => 'draft', 'label' => '草稿', 'rows' => []],
                'published' => ['key' => 'published', 'label' => '已发布', 'rows' => []],
            ];
            foreach ($rows as $r) {
                $s = $r['status'] === 'published' ? 'published' : 'draft';
                $cols[$s]['rows'][] = $r;
            }
        }
        return array_values($cols);
    }
    function tv_board_columns(string $typeSlug, array $opts = []): array {
        $type = cpt_type($typeSlug);
        if ($type === null) return [];
        return tv_board_columns_of((array) ($type['fields'] ?? []), tv_rows($typeSlug, $opts));
    }

    /** 日期类字段 key（按定义顺序：甘特用第 1 个当开始、第 2 个当结束） */
    function tv_date_fields(array $type): array {
        $out = [];
        foreach ((array) ($type['fields'] ?? []) as $f) {
            if (($f['type'] ?? '') === 'date') $out[] = (string) ($f['key'] ?? '');
        }
        return $out;
    }

    /**
     * 日历默认用哪个日期字段：优先「截止类」语义（due/deadline/end/截止/结束/日期），
     * 否则退回首个月日期字段。日历问的是「什么时候到期」，不是「什么时候开始」。
     */
    function tv_default_date_field(array $type): string {
        $dates = [];
        foreach ((array) ($type['fields'] ?? []) as $f) {
            if (($f['type'] ?? '') !== 'date') continue;
            $dates[] = (string) ($f['key'] ?? '');
            $probe = strtolower((string) ($f['key'] ?? '') . ' ' . (string) ($f['label'] ?? ''));
            foreach (['due', 'deadline', 'end', 'date', '截止', '结束', '到期', '日期'] as $hint) {
                if (str_contains($probe, $hint)) return (string) ($f['key'] ?? '');
            }
        }
        return (string) ($dates[0] ?? '');
    }

    /** 取一条记录的日期值（支持 'YYYY-MM-DD' 或 'YYYY-MM-DD HH:MM'），空则 null */
    function tv_date_value(array $row, string $key): ?string {
        $v = trim((string) ($row['values'][$key] ?? ''));
        if ($v === '') return null;
        $d = substr($v, 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 ? $d : null;
    }

    /**
     * 日历：按天分桶。
     * @return array{ym:string,first:string,days:int,lead:int,cells:array<int,array{date:string,day:int,rows:array}>,undated:array}
     */
    function tv_calendar_of(array $fields, array $rows, string $ym, string $dateKey = ''): array {
        if ($dateKey === '') $dateKey = tv_default_date_field(['fields' => $fields]);
        $byday = [];
        $undated = [];
        foreach ($rows as $r) {
            $d = $dateKey !== '' ? tv_date_value($r, $dateKey) : null;
            if ($d === null || !str_starts_with($d, $ym . '-')) { $undated[] = $r; continue; }
            $byday[$d][] = $r;
        }
        $days = (int) date('t', strtotime($ym . '-01') ?: time());
        $lead = (int) date('N', strtotime($ym . '-01') ?: time()) - 1;   // 周一为第一列
        $cells = [];
        for ($i = 1; $i <= $days; $i++) {
            $date = $ym . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $cells[] = ['date' => $date, 'day' => $i, 'rows' => $byday[$date] ?? []];
        }
        return ['ym' => $ym, 'first' => $ym . '-01', 'days' => $days, 'lead' => $lead, 'cells' => $cells, 'undated' => $undated];
    }
    function tv_calendar(string $typeSlug, string $ym, string $dateKey = '', array $opts = []): array {
        $type = cpt_type($typeSlug);
        if ($type === null) return ['ym' => $ym, 'first' => $ym . '-01', 'days' => 0, 'lead' => 0, 'cells' => [], 'undated' => []];
        return tv_calendar_of((array) ($type['fields'] ?? []), tv_rows($typeSlug, $opts), $ym, $dateKey);
    }

    /** 上/下月（用于日历翻页） */
    function tv_month_shift(string $ym, int $delta): string {
        $ts = strtotime($ym . '-01 12:00:00') ?: time();
        return date('Y-m', strtotime(($delta >= 0 ? '+' : '') . $delta . ' month', $ts) ?: $ts);
    }

    /**
     * 甘特：把每行映射成时间轴上的条。
     * 只有 1 个日期字段 → 里程碑（宽 0）；2 个 → 起止条。
     * @return array{start:string,end:string,days:int,bars:array<int,array{id:string,title:string,start:string,end:string,offset:int,span:int,milestone:bool}>}
     */
    function tv_gantt_of(array $fields, array $rows, array $dateKeys = []): array {
        $empty = ['start' => '', 'end' => '', 'days' => 0, 'bars' => []];
        $keys = $dateKeys !== [] ? $dateKeys : tv_date_fields(['fields' => $fields]);
        if ($keys === []) return $empty;
        $raw = [];
        $min = null; $max = null;
        foreach ($rows as $r) {
            $s = tv_date_value($r, $keys[0]);
            $e = isset($keys[1]) ? tv_date_value($r, $keys[1]) : $s;
            if ($s === null) { $s = $e; }
            if ($e === null) { $e = $s; }
            if ($s === null) continue;
            if ($e !== null && $e < $s) { $tmp = $s; $s = $e; $e = $tmp; }   // 起止写反也能画
            $raw[] = ['id' => $r['id'], 'title' => $r['title'], 'start' => $s, 'end' => (string) $e];
            if ($min === null || strcmp($s, $min) < 0) $min = $s;
            if ($max === null || strcmp((string) $e, $max) > 0) $max = (string) $e;
        }
        if ($raw === [] || $min === null || $max === null) return $empty;
        $span = static function (string $a, string $b): int {
            $ta = strtotime($a) ?: 0; $tb = strtotime($b) ?: 0;
            return (int) round(($tb - $ta) / 86400);
        };
        $total = max(1, $span($min, $max) + 1);
        $bars = [];
        foreach ($raw as $b) {
            $offset = $span($min, $b['start']);
            $len = $span($b['start'], $b['end']);
            $bars[] = [
                'id' => $b['id'], 'title' => $b['title'], 'start' => $b['start'], 'end' => $b['end'],
                'offset' => max(0, $offset), 'span' => max(1, $len + 1),
                'milestone' => $b['start'] === $b['end'],
            ];
        }
        return ['start' => $min, 'end' => $max, 'days' => $total, 'bars' => $bars];
    }
    function tv_gantt(string $typeSlug, array $opts = []): array {
        $type = cpt_type($typeSlug);
        if ($type === null) return ['start' => '', 'end' => '', 'days' => 0, 'bars' => []];
        return tv_gantt_of((array) ($type['fields'] ?? []), tv_rows($typeSlug, $opts));
    }

    /** 甘特轴上「今天」的位置百分比（在区间外返回 null） */
    function tv_gantt_today(array $gantt): ?float {
        if (($gantt['start'] ?? '') === '' || ($gantt['end'] ?? '') === '') return null;
        $today = date('Y-m-d');
        if (strcmp($today, (string) $gantt['start']) < 0 || strcmp($today, (string) $gantt['end']) > 0) return null;
        $ta = strtotime((string) $gantt['start']) ?: 0;
        $tt = strtotime($today) ?: 0;
        $total = max(1, (int) ($gantt['days'] ?? 1));
        return min(100.0, max(0.0, (($tt - $ta) / 86400) / $total * 100));
    }
}
