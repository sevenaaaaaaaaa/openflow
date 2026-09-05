<?php
/**
 * 区块注册表 —— 模块化的单一真源（2026-09-03）
 *
 * 【为什么有这个文件】改造前同一个「区块类型」列表被抄了三份：
 *   admin/page-builder.php 13 种、admin/page-modules.php 17 种、front-builder.php 认 13 种。
 * 三份各自演进的结果是：后台能配 contact / pricing / timeline / comparison 四种模块，
 * 前台渲染器根本不认，落到 default 分支静默降级；而「落地页模块库」写出的
 * data/page-modules.json **全站没有任何前台代码读过**——配了半天，永远不会出现。
 *
 * 现在类型表和渲染器都只有这一份，三个页面共用。新增类型要么同时补渲染器，
 * 要么根本加不进来（契约测试会拦）。
 *
 * 这也是「块契约」的第一步：先让唯一一条跑通的块链路成为真源，
 * 之后再谈 Portable Text 形状与块编辑器，而不是再造第五套模型。
 */

require_once __DIR__ . '/BlockSchema.php';

// 内置类型表（模块工厂生态：用户自定义模块由此合并进来，见 block_types()）
if (!function_exists('block_types')) {

/** 唯一的类型表：type => 中文名。渲染器必须认得这里的每一种。
 *  内置类型 + 用户自定义模块（schema）合并；自定义模块标记为可渲染。 */
function block_types(): array {
    $builtin = [
        'hero' => 'Hero 大标题', 'features' => '功能列表', 'cta' => 'CTA 行动号召',
        'text' => '文本段落', 'image-text' => '图文混排', 'stats' => '数据指标',
        'testimonials' => '客户证言', 'logo-wall' => 'Logo 墙', 'faq' => 'FAQ',
        'gallery' => '图片画廊', 'form' => '表单嵌入', 'newsletter' => '订阅表单',
        'video' => '视频嵌入', 'contact' => '联系表单', 'pricing' => '价格表',
        'timeline' => '时间线', 'comparison' => '对比表',
        'module' => '引用模块库',
    ];
    // 合并用户自定义模块（模块工厂）：key 不与内置冲突时加入
    if (function_exists('blockschema_all')) {
        foreach (blockschema_all() as $key => $mod) {
            if (isset($builtin[$key])) continue;
            if (($mod['status'] ?? '') !== 'active') continue;
            $builtin[$key] = $mod['name'] ?? $key;
        }
    }
    return $builtin;
}

function block_type_label(string $t): string { return block_types()[$t] ?? $t; }

/** 可复用模块库（admin/page-modules.php 维护），只返回启用的 */
function block_modules(): array {
    require_once __DIR__ . '/BlockContract.php';
    $all = json_read(DATA_DIR . '/page-modules.json');
    $out = [];
    foreach ((array)$all as $m) {
        if (!is_array($m) || empty($m['id'])) continue;
        if (isset($m['enabled']) && !$m['enabled']) continue;
        $mid = (string)$m['id'];

        // admin/page-modules.php 把区块字段**嵌在 block 子对象里**存：
        //   {id, name, type, description, block:{type,title,subtitle,content,...}, enabled}
        // 而渲染器读的是顶层 title / content。不摊平的话，后台配好的模块
        // 会渲染成一个空壳（标题空、内容空）——又是一次「配了不生效」。
        $payload = (isset($m['block']) && is_array($m['block'])) ? $m['block'] : $m;

        // 类型以模块顶层为准（表单里两处写的是同一个值，顶层是权威）
        $type = (string)($m['type'] ?? '');
        if ($type === '') $type = block_type_of($payload);
        if ($type === '' || $type === 'module') $type = 'text';   // 模块库不允许再嵌套引用

        // 模块的 id 是它在库里的身份（被区块引用），必须保留
        $n = block_normalize($payload, $mid);
        $n['_type'] = $type;
        $n['id']    = $mid;
        $n['name']  = (string)($m['name'] ?? $mid);
        $out[$mid]  = $n;
    }
    return $out;
}

// 区块渲染器 —— v7：每种区块映射到 modules.css 的共享 archetype，后台搭出来的页与站点其他页同一套零件。
function builder_render_block(array $b): string {
    require_once __DIR__ . '/BlockContract.php';
    $t = block_type_of($b);
    if ($t === '') $t = 'text';
    $title = htmlspecialchars($b['title'] ?? '');
    $sub = htmlspecialchars($b['subtitle'] ?? '');
    $content = $b['content'] ?? '';
    $img = htmlspecialchars($b['image'] ?? '');
    $btnText = htmlspecialchars($b['button_text'] ?? '');
    $btnUrl = htmlspecialchars($b['button_url'] ?? '');
    $bg = $b['bg_color'] ?? '';
    $bgStyle = $bg ? ' style="background:' . htmlspecialchars($bg) . ';border-radius:var(--r-lg);padding:clamp(28px,4vw,48px)"' : '';
    $btn = $btnText && $btnUrl ? '<div class="cta-row"><a class="btn primary" href="' . $btnUrl . '">' . $btnText . '</a></div>' : '';
    $head = fn(string $tag = 'h2', bool $center = true) => '<div class="sec-head' . ($center ? ' center' : '') . '">' . ($sub && $tag === 'h1' ? '<span class="kicker">' . $sub . '</span>' : '') . '<' . $tag . '>' . $title . '</' . $tag . '>' . ($sub && $tag !== 'h1' ? '<p class="lead">' . $sub . '</p>' : '') . '</div>';
    $muted = fn(string $html) => '<div class="prose" style="color:var(--muted)">' . $html . '</div>';
    // 用户自定义模块（模块工厂 schema）：非内置类型且能取到 schema → 走通用渲染
    if (blockschema_is_custom($t)) {
        $custom = builder_render_schema_module($b);
        if ($custom !== null) return $custom;
    }
    // 引用模块库：把「定义一次、到处插入」真正接通（此前 page-modules.json 无人读取）
    if ($t === 'module') {
        static $depth = 0;
        $mid = (string)($b['module_id'] ?? '');
        if ($mid === '' || $depth > 3) return '';          // 未选模块 / 防自引用套娃
        $mods = block_modules();
        if (!isset($mods[$mid])) return '';                 // 模块被删或停用 → 静默不渲染，不报错
        $depth++;
        // 模块库里的条目本身也是一个块：去掉库内身份（id / name / enabled），
        // 按它自己的类型渲染。类型缺失时退回 text，绝不静默变成空白。
        $inner = $mods[$mid];
        unset($inner['id'], $inner['name'], $inner['enabled']);
        if (block_type_of($inner) === '' || block_type_of($inner) === 'module') $inner['_type'] = 'text';
        $html = builder_render_block($inner);
        $depth--;
        return $html;
    }

    switch ($t) {
        case 'hero':
            return '<section class="reveal in"' . $bgStyle . '><div class="hero-center">' . ($sub ? '<span class="kicker">' . $sub . '</span>' : '') . '<h1>' . $title . '</h1>' . ($content ? '<p class="lead">' . $content . '</p>' : '') . $btn . '</div></section>';
        case 'features':
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? '<div class="cols n4">' . $content . '</div>' : '<div class="empty">配置区块内容</div>') . '</section>';
        case 'cta':
        case 'newsletter':
            return '<section class="reveal"' . $bgStyle . '><div class="cta-band">' . ($sub ? '<span class="kicker">' . $sub . '</span>' : '') . '<h2>' . $title . '</h2>' . ($content ? '<p class="lead">' . $content . '</p>' : '') . $btn . '</div></section>';
        case 'text':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head('h2', false) . $muted($content) . '</section>';
        case 'image-text':
            return '<section class="sec reveal"' . $bgStyle . '><div class="split"><div class="sp-txt"><h3>' . $title . '</h3>' . ($content ? '<p class="lead">' . $content . '</p>' : '') . $btn . '</div><div class="sp-vis">' . ($img ? '<img src="' . $img . '" alt="" style="width:100%;border-radius:var(--r-md);border:1px solid var(--border)">' : '') . '</div></div></section>';
        case 'stats':
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? '<div class="stats">' . $content . '</div>' : '<div class="empty">配置数据</div>') . '</section>';
        case 'form':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head() . '<div class="form-card">' . ($content ?: '<p class="note" style="text-align:center">' . ($sub ?: '配置表单 slug') . '</p>') . '</div></section>';
        case 'video':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head() . '<div class="sp-win">' . ($content ?: '<div class="empty" style="margin:18px;border:none">配置视频地址</div>') . '</div></section>';
        case 'contact':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head() . '<div class="form-card">' . ($content ?: '<p class="note" style="text-align:center">配置联系方式或表单 slug</p>') . '</div></section>';
        case 'pricing':
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? '<div class="cols n3">' . $content . '</div>' : '<div class="empty">配置价格方案</div>') . $btn . '</section>';
        case 'timeline':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head() . ($content ? '<div class="prose timeline">' . $content . '</div>' : '<div class="empty">配置时间线条目</div>') . '</section>';
        case 'comparison':
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? '<div class="scroll-x">' . $content . '</div>' : '<div class="empty">配置对比项</div>') . '</section>';
        case 'testimonials':
        case 'logo-wall':
        case 'faq':
        case 'gallery':
        default:
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? $muted($content) : '<div class="empty">区块内容</div>') . '</section>';
    }
}

/** 该类型是否为用户自定义模块（模块工厂 schema） */
function blockschema_is_custom(string $type): bool {
    if (!function_exists('blockschema_all')) return false;
    $all = blockschema_all();
    return isset($all[$type]);
}

/**
 * 通用 schema 渲染引擎 —— 模块工厂的核心。
 * 按模块的 schema（字段列表 + 样式）把块值渲染成 HTML；支持：
 *   - 字段映射模板变量：{{title}} {{items.name}} 等（代码模式）
 *   - style 定义：bg/radius/align/padding 等 CSS 变量
 *   - custom_html：开发者直接给整段 HTML，{block} 占位符替回渲染结果
 *   - repeat 列表：子字段逐条渲染
 * 非用户自定义模块返回 null，交回内置渲染。
 */
function builder_render_schema_module(array $b): ?string {
    require_once __DIR__ . '/BlockContract.php';
    $type = block_type_of($b);
    if ($type === '') return null;
    $schema = blockschema_get($type);
    if ($schema === null) return null;

    // 代码模式：custom_html 非空 → 用模板变量映射渲染
    $customHtml = (string)($schema['custom_html'] ?? '');
    $fields = (array)($schema['fields'] ?? []);

    // 组装字段值（单值 + repeat 子项）
    $values = [];
    foreach ($fields as $f) {
        $k = (string)$f['key'];
        if ($f['type'] === 'repeat') {
            // 子字段 schema
            $children = (array)($f['children'] ?? []);
            $rows = [];
            foreach ((array)($b[$k] ?? []) as $row) {
                if (!is_array($row)) continue;
                $r = [];
                foreach ($children as $cf) $r[(string)$cf['key']] = $row[(string)$cf['key']] ?? '';
                $rows[] = $r;
            }
            $values[$k] = $rows;
        } else {
            $values[$k] = $b[$k] ?? '';
        }
    }

    // 样式（CSS 变量映射到 style 内联）
    $styleCss = '';
    $style = (array)($schema['style'] ?? []);
    $styleCss .= !empty($style['bg']) ? 'background:' . blockschema_safe_color((string)$style['bg']) . ';' : '';
    $styleCss .= !empty($style['radius']) ? 'border-radius:' . (preg_match('/^\d+$/',(string)$style['radius'])?(string)$style['radius'].'px':(string)$style['radius']) . ';' : '';
    $styleCss .= !empty($style['align']) ? 'text-align:' . (in_array($style['align'], ['left','center','right'], true)?$style['align']:'left') . ';' : '';
    $styleCss .= !empty($style['padding']) ? 'padding:' . (preg_match('/^\d+$/',(string)$style['padding'])?(string)$style['padding'].'px':(string)$style['padding']) . ';' : '';
    $styleAttr = $styleCss ? ' style="' . htmlspecialchars($styleCss, ENT_QUOTES) . '"' : '';

    // [A] 代码模式：custom_html + 模板变量 → 用 {block} 包裹，{field} 逐个替换
    if ($customHtml !== '') {
        $rendered = blockschema_tpl_render($customHtml, $values, $fields);
        // custom_html 可包含外层区块骨架；无则套统一 shell
        if (preg_match('/<(section|div|article)[\s>]/', $rendered)) {
            return $rendered;
        }
        return '<section class="sec reveal"' . $styleAttr . '>' . $rendered . '</section>';
    }

    // [B] 默认渲染：单个字段 + repeat 列表，套用共享 archetype 壳
    $inner = '';
    foreach ($fields as $f) {
        $k = (string)$f['key'];
        $v = $values[$k] ?? '';
        if ($f['type'] === 'repeat') {
            if (!empty($v)) {
                $rows = [];
                foreach ($v as $row) {
                    $cols = '';
                    foreach ((array)($f['children'] ?? []) as $cf) {
                        $ck = (string)$cf['key'];
                        if (($row[$ck] ?? '') === '') continue;
                        $colType = $cf['type'] ?? 'text';
                        $val = $row[$ck];
                        if ($colType === 'image') $cols .= '<img src="' . htmlspecialchars((string)$val, ENT_QUOTES) . '" style="width:48px;height:48px;border-radius:12px;object-fit:cover">';
                        elseif ($colType === 'richtext') $cols .= '<p class="lead" style="color:var(--muted)">' . $val . '</p>';
                        else $cols .= '<div style="font-size:13px;color:var(--muted)">' . htmlspecialchars((string)$val, ENT_QUOTES) . '</div>';
                    }
                    $rows[] = '<div class="c-item" style="display:flex;flex-direction:column;gap:6px">' . $cols . '</div>';
                }
                $inner .= '<div class="cols n' . max(1, min(4, count((array)($style['cols'] ?? [])))) . '">' . implode('', $rows) . '</div>';
            }
        } elseif ($f['type'] === 'image') {
            if ($v !== '') $inner .= '<img src="' . htmlspecialchars((string)$v, ENT_QUOTES) . '" loading="lazy" style="width:100%;border-radius:12px;object-fit:cover">';
        } elseif ($f['type'] === 'form') {
            // 表单嵌入：读表单配置，输出真实可提交表单
            $inner .= blockschema_render_form((string)$v);
        } else {
            $label = (string)($f['label'] ?? $k);
            $cls = in_array($f['type'], ['title','subtitle'], true) ? 'style="font-size:18px;font-weight:800"' : '';
            $inner .= '<div class="s-field"><div class="text-xs" style="color:var(--faint)">' . htmlspecialchars($label, ENT_QUOTES) . '</div>' . ($v !== '' ? '<div ' . $cls . '>' . (in_array($f['type'], ['richtext'], true) ? $v : htmlspecialchars((string)$v, ENT_QUOTES)) . '</div>' : '') . '</div>';
        }
    }
    if ($inner === '') {
        $inner = '<div class="empty">配置模块内容</div>';
    }
    return '<section class="sec reveal"' . $styleAttr . '>' . $inner . '</section>';
}

/**
 * 代码模式模板渲染：把 {{field}} / {{repeat.#.sub}} 替换成字段值。
 * unknown 字段保留 {{name}} 原文（让开发者看见可用的占位符，避免静默丢值）。
 */
function blockschema_tpl_render(string $tpl, array $values, array $fields): string {
    $out = $tpl;
    foreach ($fields as $f) {
        $k = (string)$f['key'];
        if ($f['type'] === 'repeat') {
            // 整段重复：{{#items}}…{{/items}} 内的子字段逐条展开
            $rows = (array)($values[$k] ?? []);
            if (preg_match_all('/\{\{#(' . preg_quote($k, '/') . ')\}\}(.*?)\{\{\/\1\}\}/s', $out, $ms, PREG_SET_ORDER)) {
                foreach ($ms as $m) {
                    $block = $m[2]; $repl = '';
                    foreach ($rows as $row) {
                        $r = $block;
                        foreach ((array)($f['children'] ?? []) as $cf) {
                            $ck = (string)$cf['key'];
                            $r = str_replace('{{' . $ck . '}}', (string)($row[$ck] ?? ''), $r);
                        }
                        $repl .= $r;
                    }
                    $out = str_replace($m[0], $repl, $out);
                }
            }
        } else {
            $v = (string)($values[$k] ?? '');
            // 标量字段，富文本/图片原样，其余做 HTML 转义防注入
            if (in_array($f['type'], ['richtext','image','html'], true)) {
                $out = str_replace('{{' . $k . '}}', $v, $out);
            } else {
                $out = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES), $out);
            }
        }
    }
    return $out;
}

/** 表单块：读 forms 配置渲染真实可提交表单（连上 api/form-submit） */
function blockschema_render_form(string $formId): string {
    $forms = json_read(DATA_DIR . '/forms/index.json');
    $form = null;
    foreach ((array)$forms as $f) if (($f['id'] ?? '') === $formId || ($f['slug'] ?? '') === $formId) { $form = $f; break; }
    if (!$form) return '<div class="note" style="text-align:center">选择表单</div>';
    $fields = '';
    foreach ((array)($form['fields'] ?? []) as $fld) {
        $fkey = (string)($fld['key'] ?? $fld['name'] ?? '');
        $flabel = (string)($fld['label'] ?? $fkey);
        $ftype = (string)($fld['type'] ?? 'text');
        if ($ftype === 'textarea') $fields .= '<textarea name="' . htmlspecialchars($fkey, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($flabel, ENT_QUOTES) . '" class="inp" rows="3" style="height:auto"></textarea>';
        else $fields .= '<input class="inp" type="' . ($ftype === 'email' ? 'email' : 'text') . '" name="' . htmlspecialchars($fkey, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($flabel, ENT_QUOTES) . '">';
    }
    return '<form class="form-card" method="post" action="/api/form-submit.php"><input type="hidden" name="form_id" value="' . htmlspecialchars((string)$form['id'], ENT_QUOTES) . '">' . $fields . '<button class="btn primary" type="submit">提交</button></form>';
}
}
