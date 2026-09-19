<?php
declare(strict_types=1);
/**
 * CptSystem —— 轻量自定义内容类型（AUDIT-01 P0 / BACKLOG T0-2）
 *
 * 【为什么】此前所有内容只能塞进「文章」或「页面」两种固定结构。想做「案例」
 * 「产品」「FAQ」「职位」这类有自己字段的内容，只能拿文章硬凑——长期撞墙。
 * 本模块给一个轻量 CPT：后台定义内容类型（名称/slug/字段集），按类型存取条目。
 * 不追 WordPress 的全套，够「一个人给站点加一种新内容」用即可。
 *
 * 存储：类型 data/cpt/types.json；条目 data/cpt/entries/{typeSlug}.json（一类型一文件）。
 * 字段类型：text/textarea/richtext/number/date/url/image/select/bool。
 */

if (!function_exists('cpt_types_file')) {
    function cpt_types_file(): string { return DATA_DIR . '/cpt/types.json'; }
    function cpt_entries_file(string $type): string { return DATA_DIR . '/cpt/entries/' . preg_replace('/[^a-z0-9_-]/', '', $type) . '.json'; }
}

if (!function_exists('cpt_field_types')) {
    function cpt_field_types(): array {
        return [
            'text'     => '单行文本', 'textarea' => '多行文本', 'richtext' => '富文本',
            'number'   => '数字', 'date' => '日期', 'url' => '链接',
            'image'    => '图片URL', 'select' => '下拉选择', 'bool' => '开关',
            // 多维表格三件套：先有关系，才有汇总与查值
            'relation' => '关联记录', 'rollup' => '汇总（关联）', 'lookup' => '查值（关联）',
        ];
    }
}

if (!function_exists('cpt_slugify')) {
    function cpt_slugify(string $s): string {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}]+/u', '-', $s);
        return trim($s, '-') ?: ('item-' . substr(bin2hex(random_bytes(3)), 0, 5));
    }
}

/* ─────────── 类型 ─────────── */

if (!function_exists('cpt_types')) {
    function cpt_types(): array {
        $d = function_exists('json_read') ? json_read(cpt_types_file()) : [];
        return is_array($d) ? $d : [];
    }
    function cpt_type(string $slug): ?array {
        foreach (cpt_types() as $t) if (($t['slug'] ?? '') === $slug) return $t;
        return null;
    }
    /**
     * 新建/更新类型。$data: name,name_plural,slug,icon,fields[],public,menu。
     * 返回 ['ok'=>bool,'type'|'error']。slug 唯一；字段 key 规范化。
     */
    function cpt_type_save(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'error' => '类型名不能为空'];
        $slug = cpt_slugify((string)($data['slug'] ?? $name));
        // 只允许英数 slug（用于 URL / 文件名）
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?: ('type-' . substr(bin2hex(random_bytes(3)), 0, 5));

        $fields = [];
        foreach ((array)($data['fields'] ?? []) as $f) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($f['key'] ?? '')));
            if ($key === '') continue;
            $ft = (string)($f['type'] ?? 'text');
            if (!isset(cpt_field_types()[$ft])) $ft = 'text';
            $field = ['key' => $key, 'label' => trim((string)($f['label'] ?? $key)) ?: $key, 'type' => $ft, 'required' => !empty($f['required'])];
            if ($ft === 'select') {
                $opts = $f['options'] ?? [];
                if (is_string($opts)) $opts = array_values(array_filter(array_map('trim', explode(',', $opts))));
                $field['options'] = array_values((array)$opts);
            }
            if ($ft === 'relation') {
                $field['target'] = cpt_clean_slug((string)($f['target'] ?? ''));
                $field['multiple'] = !isset($f['multiple']) || !empty($f['multiple']);
            }
            if ($ft === 'rollup' || $ft === 'lookup') {
                $field['relation_field'] = cpt_clean_key((string)($f['relation_field'] ?? ''));
                $field['target_field'] = cpt_clean_key((string)($f['target_field'] ?? ''));
                if ($ft === 'rollup') {
                    $fn = (string)($f['function'] ?? 'count');
                    $field['function'] = isset(cpt_relation_functions()[$fn]) ? $fn : 'count';
                }
            }
            $fields[] = $field;
        }

        $types = cpt_types();
        $saved = null;
        $now = date('Y-m-d H:i:s');
        $found = false;
        foreach ($types as &$t) {
            if (($t['slug'] ?? '') === $slug) {
                $t = array_merge($t, [
                    'name' => $name, 'name_plural' => trim((string)($data['name_plural'] ?? '')) ?: $name,
                    'icon' => (string)($data['icon'] ?? ($t['icon'] ?? '📄')),
                    'fields' => $fields, 'public' => !empty($data['public']),
                    'menu' => !empty($data['menu']), 'updated_at' => $now,
                ]);
                $found = true; $saved = $t; break;
            }
        }
        unset($t);
        if (!$found) {
            $saved = [
                'slug' => $slug, 'name' => $name, 'name_plural' => trim((string)($data['name_plural'] ?? '')) ?: $name,
                'icon' => (string)($data['icon'] ?? '📄'), 'fields' => $fields,
                'public' => !empty($data['public']), 'menu' => !empty($data['menu']),
                'created_at' => $now, 'updated_at' => $now,
            ];
            $types[] = $saved;
        }
        json_write(cpt_types_file(), $types);
        return ['ok' => true, 'type' => $saved];
    }
    function cpt_type_delete(string $slug): bool {
        $types = cpt_types();
        $n = count($types);
        $types = array_values(array_filter($types, fn($t) => ($t['slug'] ?? '') !== $slug));
        if (count($types) === $n) return false;
        json_write(cpt_types_file(), $types);
        // 条目文件保留（作备份，不随类型删除而丢数据）
        return true;
    }
}

/* ─────────── 条目 ─────────── */

if (!function_exists('cpt_entries')) {
    function cpt_entries(string $type, array $opts = []): array {
        $d = function_exists('json_read') ? json_read(cpt_entries_file($type)) : [];
        $list = is_array($d) ? $d : [];
        if (!empty($opts['status'])) $list = array_values(array_filter($list, fn($e) => ($e['status'] ?? '') === $opts['status']));
        usort($list, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
        return $list;
    }
    function cpt_entry(string $type, string $id): ?array {
        foreach (cpt_entries($type) as $e) if (($e['id'] ?? '') === $id) return $e;
        return null;
    }
    function cpt_entry_by_slug(string $type, string $slug): ?array {
        foreach (cpt_entries($type) as $e) if (($e['slug'] ?? '') === $slug) return $e;
        return null;
    }
    /** 公开条目（已发布），供前台/主题/MCP 消费。 */
    function cpt_public_entries(string $type): array {
        return cpt_entries($type, ['status' => 'published']);
    }

    /** 校验条目字段是否满足类型定义。返回错误信息数组（空=通过）。 */
    function cpt_validate_entry(array $type, array $fields): array {
        $errs = [];
        foreach (($type['fields'] ?? []) as $f) {
            $k = $f['key']; $v = $fields[$k] ?? '';
            if (!empty($f['required']) && (is_string($v) ? trim($v) === '' : ($v === null || $v === []))) {
                $errs[] = "字段「{$f['label']}」必填";
            }
            if (($f['type'] ?? '') === 'number' && $v !== '' && !is_numeric($v)) $errs[] = "字段「{$f['label']}」需为数字";
            if (($f['type'] ?? '') === 'select' && $v !== '' && !in_array($v, $f['options'] ?? [], true)) $errs[] = "字段「{$f['label']}」取值非法";
            if (($f['type'] ?? '') === 'relation' && $v !== '' && $v !== []) {
                $target = (string)($f['target'] ?? '');
                if ($target === '' || cpt_type($target) === null) {
                    $errs[] = "字段「{$f['label']}」的目标类型不存在";
                } else {
                    foreach (cpt_normalize_relation($v) as $rid) {
                        if (cpt_entry($target, $rid) === null) { $errs[] = "字段「{$f['label']}」关联的记录不存在"; break; }
                    }
                }
            }
        }
        return $errs;
    }

    /**
     * 新建/更新条目。$data: id?,title,slug?,status,fields{}。
     * 按类型字段校验；返回 ['ok'=>bool,'entry'|'errors'|'error']。
     */
    function cpt_entry_save(string $typeSlug, array $data): array {
        $type = cpt_type($typeSlug);
        if (!$type) return ['ok' => false, 'error' => '内容类型不存在'];
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => '标题不能为空'];

        // 只保留类型定义里的字段，按类型清洗
        $clean = [];
        foreach (($type['fields'] ?? []) as $f) {
            $k = $f['key']; $v = $data['fields'][$k] ?? '';
            if (in_array($f['type'], cpt_computed_types(), true)) continue;   // 汇总/查值由解析层算，不落库
            if ($f['type'] === 'relation') {
                $ids = cpt_normalize_relation($v);
                // 单值关联就存标量：数据形态跟语义一致，读的时候不用再取 [0]
                $v = (($f['multiple'] ?? true) === false) ? (string)($ids[0] ?? '') : $ids;
            }
            elseif ($f['type'] === 'bool') $v = !empty($v);
            elseif ($f['type'] === 'number') $v = ($v === '' ? '' : (float)$v);
            else $v = is_string($v) ? $v : (string)$v;
            $clean[$k] = $v;
        }
        $errs = cpt_validate_entry($type, $clean);
        if ($errs) return ['ok' => false, 'errors' => $errs];

        $list = cpt_entries($typeSlug);
        $entry = null;
        $now = date('Y-m-d H:i:s');
        $id = (string)($data['id'] ?? '');
        $slug = cpt_slugify((string)($data['slug'] ?? $title));
        // slug 唯一（同类型内）
        foreach ($list as $e) {
            if (($e['slug'] ?? '') === $slug && ($e['id'] ?? '') !== $id) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3); break; }
        }
        $status = $data['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published'], true)) $status = 'draft';

        if ($id !== '') {
            $hit = false;
            foreach ($list as &$e) {
                if (($e['id'] ?? '') === $id) {
                    $e = array_merge($e, ['title' => $title, 'slug' => $slug, 'status' => $status, 'fields' => $clean, 'updated_at' => $now]);
                    $hit = true; $entry = $e; break;
                }
            }
            unset($e);
            if (!$hit) return ['ok' => false, 'error' => '条目不存在'];
        } else {
            $entry = [
                'id' => 'cpt_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
                'type' => $typeSlug, 'title' => $title, 'slug' => $slug, 'status' => $status,
                'fields' => $clean, 'author' => (string)($data['author'] ?? ''),
                'created_at' => $now, 'updated_at' => $now,
            ];
            $list[] = $entry;
        }
        $f = cpt_entries_file($typeSlug);
        @mkdir(dirname($f), 0755, true);
        json_write($f, $list);
        return ['ok' => true, 'entry' => $entry];
    }
    function cpt_entry_delete(string $typeSlug, string $id): bool {
        $list = cpt_entries($typeSlug);
        $n = count($list);
        $list = array_values(array_filter($list, fn($e) => ($e['id'] ?? '') !== $id));
        if (count($list) === $n) return false;
        json_write(cpt_entries_file($typeSlug), $list);
        return true;
    }
}

/* ─────────── 关联 / 汇总 / 查值（多维表格三件套）─────────── */
/**
 * 【为什么】多维表格真正好用的地方不是「字段多」，而是记录之间能建立关系：
 * 有了关联，才能在一张表里看到另一张表的数——汇总（rollup）与查值（lookup）都是关联的下游。
 * 三者分开而不是塞进一个「公式」字段：配置即界面，不需要用户写表达式。
 */

if (!function_exists('cpt_relation_functions')) {
    function cpt_relation_functions(): array {
        return ['count' => '计数', 'sum' => '求和', 'avg' => '平均', 'min' => '最小', 'max' => '最大'];
    }
    /** 计算类字段：值由解析层产出，不接受直接写入 */
    function cpt_computed_types(): array {
        return ['rollup', 'lookup'];
    }
    function cpt_clean_slug(string $s): string {
        return preg_replace('/[^a-z0-9-]/', '', strtolower(trim($s))) ?? '';
    }
    function cpt_clean_key(string $s): string {
        return preg_replace('/[^a-z0-9_]/', '', strtolower(trim($s))) ?? '';
    }
    /** relation 值 → 去重后的 id 数组（单值/逗号串/数组都能吃） */
    function cpt_normalize_relation(mixed $v): array {
        if (is_string($v)) $v = array_filter(array_map('trim', explode(',', $v)));
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $x) {
            $x = trim((string)$x);
            if ($x !== '' && !in_array($x, $out, true)) $out[] = $x;
        }
        return $out;
    }
    /** 把 id 列表补成可读记录（id/title/slug/type），顺带丢掉已删的 */
    function cpt_relate_entries(string $target, array $ids): array {
        $type = cpt_type($target);
        if ($type === null) return [];
        $out = [];
        foreach ($ids as $id) {
            $e = cpt_entry($target, (string)$id);
            if ($e === null) continue;
            $out[] = ['id' => (string)($e['id'] ?? ''), 'title' => (string)($e['title'] ?? ''), 'slug' => (string)($e['slug'] ?? ''), 'type' => $target];
        }
        return $out;
    }
    /** 汇总函数：count/sum/avg/min/max */
    function cpt_rollup_value(string $fn, array $values): int|float|string {
        if ($fn === 'count') return count($values);
        $nums = [];
        foreach ($values as $v) if (is_numeric($v)) $nums[] = (float)$v;
        if ($nums === []) return $fn === 'sum' ? 0 : '';
        return match ($fn) {
            'sum' => array_sum($nums),
            'avg' => array_sum($nums) / count($nums),
            'min' => min($nums),
            'max' => max($nums),
            default => count($values),
        };
    }
    /**
     * 解析一条记录：relation 补成记录、rollup/lookup 算出值。结果放 resolved[fieldKey]。
     * $depth 防环（A 汇总 B、B 汇总 A 也不会转不出来）。
     */
    function cpt_resolve_entry(string $typeSlug, array $entry, int $depth = 0): array {
        $type = cpt_type($typeSlug);
        if ($type === null) return $entry;
        $fields = (array)($entry['fields'] ?? []);
        $resolved = [];
        foreach ((array)($type['fields'] ?? []) as $f) {
            $key = (string)($f['key'] ?? '');
            $ft = (string)($f['type'] ?? 'text');
            if ($ft === 'relation') {
                $resolved[$key] = cpt_relate_entries((string)($f['target'] ?? ''), cpt_normalize_relation($fields[$key] ?? []));
                continue;
            }
            if (($ft === 'rollup' || $ft === 'lookup') && $depth < 3) {
                $relKey = (string)($f['relation_field'] ?? '');
                $relField = null;
                foreach ((array)($type['fields'] ?? []) as $rf) if (($rf['key'] ?? '') === $relKey) $relField = $rf;
                if ($relField === null || (string)($relField['type'] ?? '') !== 'relation') { $resolved[$key] = $ft === 'rollup' ? 0 : ''; continue; }
                $target = (string)($relField['target'] ?? '');
                $ids = cpt_normalize_relation($fields[$relKey] ?? []);
                $tKey = (string)($f['target_field'] ?? '');
                $values = [];
                foreach ($ids as $id) {
                    $te = cpt_entry($target, (string)$id);
                    if ($te === null) continue;
                    if ($tKey === '') { $values[] = 1; continue; }   // 计数用
                    // 目标字段本身也可能是算出来的，递归解析（深度受限）
                    $resolvedTarget = cpt_resolve_entry($target, $te, $depth + 1);
                    $rv = $resolvedTarget['resolved'][$tKey] ?? (($te['fields'] ?? [])[$tKey] ?? '');
                    if ($ft === 'lookup') {
                        $values[] = is_array($rv) ? implode(', ', array_map(fn($x) => (string)($x['title'] ?? $x), $rv)) : (string)$rv;
                    } else {
                        $values[] = $rv;
                    }
                }
                if ($ft === 'rollup') {
                    $resolved[$key] = cpt_rollup_value((string)($f['function'] ?? 'count'), $values);
                } else {
                    // 单值关联的查值给标量（Airtable 一律返回数组，但这里我们知道 multiple=false，不该让用户再取 [0]）
                    $resolved[$key] = (($relField['multiple'] ?? true) === false) ? ($values[0] ?? '') : $values;
                }
                continue;
            }
        }
        $entry['resolved'] = $resolved;
        return $entry;
    }
    /** 便捷入口：按 id 取一条并解析 */
    function cpt_entry_resolved(string $typeSlug, string $id): ?array {
        $e = cpt_entry($typeSlug, $id);
        return $e === null ? null : cpt_resolve_entry($typeSlug, $e);
    }
    /** 列表级解析（表格视图用） */
    function cpt_entries_resolved(string $typeSlug, array $opts = []): array {
        return array_map(fn(array $e): array => cpt_resolve_entry($typeSlug, $e), cpt_entries($typeSlug, $opts));
    }
}
