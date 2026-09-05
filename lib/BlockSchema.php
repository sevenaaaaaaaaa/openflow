<?php
/**
 * BlockSchema —— 模块工厂核心（2026-09-05）
 *
 * 【为什么有它】BlockRegistry 里 18 种块是「写死的」，每种块能填哪些字段
 * 由 PHP 决定；而 page-builder 采集了 15 个字段，渲染器只读 8 个——大量字段
 * 是「采了不渲染」的死字段。这套东西不可扩展：要新增一个模块就得改 PHP。
 *
 * BlockSchema 把「模块」变成**数据**，让后台能可视化地定义新模块：
 *   1. 字段列表（type/text/image/repeat/...）——决定这个模块有哪些输入框
 *   2. 样式定义（bg/radius/align/...）——决定这个模块长什么样
 *   3. 代码模式（custom_html / template）——高级开发者直接写 HTML/模板，
 *      用 {{字段}} 占位符映射字段值，绕开可视化渲染
 *
 * 它复用 BlockContract 的 Portable Text 形状（_type/_key/归一化/校验），
 * 只新增「模块 = schema」这一层抽象，绝不另起一套并行模型。
 */

if (!function_exists('blockschema_field_types')) {

/** 支持的字段类型 => 中文名 + 类别。category: single|repeat|special */
function blockschema_field_types(): array {
    return [
        'title'     => ['label'=>'标题',       'category'=>'single'],
        'subtitle'  => ['label'=>'副标题',     'category'=>'single'],
        'text'      => ['label'=>'单行文本',   'category'=>'single'],
        'richtext'  => ['label'=>'富文本',     'category'=>'single'],
        'image'     => ['label'=>'图片',       'category'=>'single'],
        'url'       => ['label'=>'链接',       'category'=>'single'],
        'color'     => ['label'=>'颜色',       'category'=>'single'],
        'number'    => ['label'=>'数字',       'category'=>'single'],
        'select'    => ['label'=>'下拉选项',   'category'=>'single'],
        'bool'      => ['label'=>'开关',       'category'=>'single'],
        'repeat'    => ['label'=>'重复列表',   'category'=>'repeat'],
        'form'      => ['label'=>'表单嵌入',   'category'=>'special'],
        'module'    => ['label'=>'引用模块',   'category'=>'special'],
    ];
}

function blockschema_field_labels(): array {
    return array_map(fn($f) => $f['label'], blockschema_field_types());
}

/** 用户自定义模块的存储文件 */
function blockschema_file(): string { return DATA_DIR . '/block-types.json'; }

/**
 * 读取全部模块 schema（用户自定义 + 内置白名单）。
 * 返回 id => {id, name, key, status, fields:[], style:[], custom_html, created_at, updated_at}
 */
function blockschema_all(): array {
    $all = json_read(blockschema_file());
    if (!is_array($all)) $all = [];
    $out = [];
    foreach ($all as $mod) {
        if (!is_array($mod) || empty($mod['key'])) continue;
        $n = blockschema_normalize($mod);
        if ($n === []) continue;
        $out[$n['key']] = $n;
    }
    return $out;
}

/** 单个模块 schema 归一化 */
function blockschema_normalize(array $mod): array {
    $key = trim((string)($mod['key'] ?? $mod['id'] ?? ''));
    if ($key === '' || $key === 'module') return [];
    $key = preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
    if ($key === '') return [];
    $fields = [];
    foreach ((array)($mod['fields'] ?? []) as $f) {
        $n = blockschema_field($f);
        if ($n !== null) $fields[] = $n;
    }
    $style = [];
    foreach ((array)($mod['style'] ?? []) as $k => $v) {
        $style[preg_replace('/[^a-z_]/', '', strtolower((string)$k))] = (string)$v;
    }
    return [
        'id'          => (string)($mod['id'] ?? ''),
        'key'         => $key,
        'name'        => trim((string)($mod['name'] ?? $key)),
        'status'      => (($mod['status'] ?? 'active') === 'active' ? 'active' : 'draft'),
        'fields'      => $fields,
        'style'       => $style,
        'custom_html' => (string)($mod['custom_html'] ?? ''),
        'created_at'  => (string)($mod['created_at'] ?? ''),
        'updated_at'  => (string)($mod['updated_at'] ?? ''),
    ];
}

/** 单个字段归一化；不合法返回 null */
function blockschema_field(array $f): ?array {
    $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($f['key'] ?? '')));
    if ($key === '') return null;
    $type = (string)($f['type'] ?? 'text');
    if (!isset(blockschema_field_types()[$type])) return null;
    $out = ['key' => $key, 'type' => $type, 'label' => trim((string)($f['label'] ?? $key))];
    if ($type === 'select') {
        $opts = [];
        foreach ((array)($f['options'] ?? []) as $o) {
            $o = trim((string)($o ?? ''));
            if ($o !== '') $opts[] = $o;
        }
        $out['options'] = $opts;
        if (empty($out['options'])) $out['options'] = ['是', '否'];
    }
    if ($type === 'repeat') {
        $sub = blockschema_field($f['children'] ?? []);
        // repeat 的子字段用 _type 字段中声明（用整个子 schema）
        $out['children'] = is_array($f['children'] ?? null) ? blockschema_fields($f['children']) : [];
    }
    $out['required'] = !empty($f['required']);
    $out['placeholder'] = (string)($f['placeholder'] ?? '');
    return $out;
}

/** 字段数组归一化（过滤非法） */
function blockschema_fields(array $fields): array {
    $out = [];
    $seen = [];
    foreach ($fields as $f) {
        $n = blockschema_field($f);
        if ($n === null) continue;
        if (isset($seen[$n['key']])) continue;
        $seen[$n['key']] = true;
        $out[] = $n;
    }
    return $out;
}

/** 模块内字段 key 是否已占用 */
function blockschema_has_key(array $fields, string $key): bool {
    foreach ($fields as $f) if (($f['key'] ?? '') === $key) return true;
    return false;
}

/**
 * schema 体检：返回问题列表（空 = 合法）。
 * 用在保存前把「配置了不生效」挡在写盘之前。
 */
function blockschema_validate(array $mod): array {
    $problems = [];
    $key = trim((string)($mod['key'] ?? ''));
    if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key)) $problems[] = '模块 key 只能是小写字母+数字+下划线';
    if (trim((string)($mod['name'] ?? '')) === '') $problems[] = '模块名不能为空';
    $seen = [];
    foreach ((array)($mod['fields'] ?? []) as $f) {
        $fk = (string)($f['key'] ?? '');
        if ($fk === '') { $problems[] = '存在没有 key 的字段'; continue; }
        if (isset($seen[$fk])) { $problems[] = "字段 key「{$fk}」重复"; continue; }
        $seen[$fk] = true;
        $ft = (string)($f['type'] ?? '');
        if (!isset(blockschema_field_types()[$ft])) $problems[] = "字段「{$fk}」类型 {$ft} 不合法";
        if ($ft === 'select' && empty($f['options'])) $problems[] = "选择字段「{$fk}」没有选项";
    }
    return $problems;
}

/** 保存一个模块 schema；冲突则返回错误 */
function blockschema_save(array $mod): array {
    $problems = blockschema_validate($mod);
    if ($problems) return ['ok'=>false, 'errors'=>$problems];
    $n = blockschema_normalize($mod);
    if ($n === []) return ['ok'=>false, 'errors'=>['模块 key 非法']];
    $all = blockschema_all();
    $all[$n['key']] = $n;
    $ok = json_write(blockschema_file(), $all);
    return $ok ? ['ok'=>true, 'schema'=>$n] : ['ok'=>false, 'errors'=>['写入失败']];
}

/** 删除模块 */
function blockschema_delete(string $key): bool {
    $all = blockschema_all();
    unset($all[$key]);
    return json_write(blockschema_file(), $all);
}

/** 按 key 取单个模块 */
function blockschema_get(string $key): ?array {
    $all = blockschema_all();
    return $all[$key] ?? null;
}

/** 值能否用颜色常量命名字符串（如 #f4f3e9、red、var(--accent)） */
function blockschema_safe_color(string $v): string {
    return preg_replace('/[^#a-zA-Z0-9(),.\-_\s]/', '', $v);
}

/**
 * schema 校验字段值（渲染前），返回 [ok, errors]。
 * 只防脏值，不强制必填（空值允许，避免拖入即报错）。
 */
function blockschema_validate_values(array $schema, array $values): array {
    $errors = [];
    foreach (($schema['fields'] ?? []) as $f) {
        $k = $f['key'];
        $v = $values[$k] ?? '';
        // 简单类型空值放行；只对 select/bool 校验取值范围
        if ($f['type'] === 'select' && $v !== '' && !in_array($v, $f['options'] ?? [], true)) $errors[] = "字段「{$k}」值不在选项内";
        if ($f['type'] === 'images' && $v === '' && !empty($f['required'])) $errors[] = "字段「{$k}」必填";
    }
    return $errors;
}

/**
 * 编辑器字段 HTML —— 落地页构建器按 schema 动态生成输入框。
 * 供 admin/page-builder.php 使用（自定义模块走这里，内置块仍走原15框）。
 * 输入框 name 前缀用 block_{key}[]（与内置一致，保存循环存取）。
 */
function blockschema_editor_fields(array $schema, array $values = []): string {
    $vals = $values ?? [];
    $html = '';
    foreach (($schema['fields'] ?? []) as $f) {
        $k = (string)$f['key'];
        $label = (string)($f['label'] ?? $k);
        $v = (string)($vals[$k] ?? '');
        $type = (string)$f['type'];
        $ph = (string)($f['placeholder'] ?? '');
        $nameBase = 'block_' . $k;
        if ($type === 'title') {
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '</label><input type="text" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($ph, ENT_QUOTES) . '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        } elseif ($type === 'richtext' || $type === 'text') {
            $html .= '<textarea name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" rows="2" placeholder="' . htmlspecialchars($ph, ENT_QUOTES) . '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">' . htmlspecialchars($v, ENT_QUOTES) . '</textarea>';
        } elseif ($type === 'image') {
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '</label><input type="text" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="/uploads/xxx.jpg" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        } elseif ($type === 'select') {
            $ops = '';
            foreach (($f['options'] ?? []) as $o) $ops .= '<option value="' . htmlspecialchars($o, ENT_QUOTES) . '" ' . ($v === $o ? 'selected' : '') . '>' . htmlspecialchars($o, ENT_QUOTES) . '</option>';
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '</label><select name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px"><option value="">—</option>' . $ops . '</select>';
        } elseif ($type === 'color') {
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '</label><input type="text" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="#f4f3e9" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        } elseif ($type === 'number') {
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '</label><input type="number" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($ph, ENT_QUOTES) . '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        } elseif ($type === 'bool') {
            $html .= '<label class="text-xs text-muted" style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="1" style="width:16px;height:16px" ' . ($v ? 'checked' : '') . '>' . htmlspecialchars($label, ENT_QUOTES) . '</label>';
        } elseif ($type === 'repeat') {
            $rows = (array)($vals[$k] ?? []);
            $html .= '<div class="bs-repeat" data-key="' . htmlspecialchars($k, ENT_QUOTES) . '" style="border:1px dashed var(--border);border-radius:6px;padding:8px 10px">';
            $html .= '<div class="text-xs text-muted" style="margin-bottom:6px">' . htmlspecialchars($label, ENT_QUOTES) . '（可加多条）</div>';
            $html .= '<div class="bs-rows">';
            foreach ($rows as $row) if (is_array($row)) $html .= blockschema_editor_repeat_row($f, $row, $k);
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-ghost btn-sm" onclick="bsAddRepeat(this)">+ 添加一条</button>';
            $html .= '</div>';
        } elseif ($type === 'form') {
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '（表单 id）</label><input type="text" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="form_xxx" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        } elseif ($type === 'url') {
            $html .= '<label class="text-xs text-muted">' . htmlspecialchars($label, ENT_QUOTES) . '</label><input type="text" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="/xxx 或 https://" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        } else {
            $html .= '<input type="text" name="' . htmlspecialchars($nameBase, ENT_QUOTES) . '[]" value="' . htmlspecialchars($v, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($label, ENT_QUOTES) . '" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:14px">';
        }
    }
    return $html;
}

/** repeat 单行子字段（编辑器） */
function blockschema_editor_repeat_row(array $f, array $row, string $k): string {
    $html = '<div class="bs-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:6px;margin-bottom:6px">';
    foreach ((array)($f['children'] ?? []) as $cf) {
        $ck = (string)$cf['key'];
        $cv = (string)($row[$ck] ?? '');
        $name = 'block_' . $k . '[' . $ck . ']';
        $html .= '<input type="text" name="' . htmlspecialchars($name, ENT_QUOTES) . '" value="' . htmlspecialchars($cv, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($cf['label'] ?? $ck, ENT_QUOTES) . '" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">';
    }
    $html .= '<button type="button" class="btn btn-ghost btn-sm" onclick="this.closest(\'.bs-row\').remove()">✕</button>';
    $html .= '</div>';
    return $html;
}

}
