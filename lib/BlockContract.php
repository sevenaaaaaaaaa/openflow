<?php
/**
 * 块契约 —— Portable Text 兼容形状（2026-09-04）
 *
 * 【为什么要有一份契约】止血那一步统一了「类型表和渲染器」，但**形状**还是各说各话：
 *
 *   1. 落地页区块   builder-pages.json   {id, type, title, content, audience}
 *   2. 模块库       page-modules.json    {id, name, type, title, content, enabled}
 *   3. BlockModel   （休眠）             {type: heading|paragraph|list|quote|code|...}
 *   4. thank-you.php 里写死的卡片         {type: tip|link, icon, title, desc, url, btn}
 *   5. 文章正文     一坨 HTML + 短代码
 *
 * 再往里塞一个块编辑器，它只会变成第六套。所以先把形状定下来，再谈换编辑器。
 *
 * 【为什么选 Portable Text 的形状】它是公开、与厂商无关的规范，规则只有两条硬要求：
 * 数组里每个块都带 `_type` 和 `_key`；文本块用 `_type: "block"` + children spans，
 * 其余 `_type` 一律是自定义对象块。这两条恰好是我们缺的东西，而且**零外部依赖**——
 * 这里没有引入任何库，就是一组普通函数。将来接 Editor.js / Sanity / 任何 PT 生态的
 * 工具，是接到这个形状上，而不是再造一套。
 *
 * 【真正解决的问题：块身份】改造前 admin/page-builder.php 每次保存都这么干：
 *
 *     $block = ['id' => 'blk_' . $bi . '_' . substr(bin2hex(random_bytes(4)), 0, 6), ...];
 *
 * 也就是说，**每保存一次，所有区块的 id 全部重新生成**。块身份根本不存在。
 * 后果是一连串功能压根做不了：按块比对版本（每块都像新的）、给某个块留批注
 * （外部协作者的批注下次保存就成了孤儿）、按块看转化数据。
 * 全站没有任何代码持久引用过区块 id——不是没想到，是引用了也没用。
 * `_key` 就是来钉住这件事的：一旦生成，跟着这个块活下去。
 *
 * 【兼容】normalize 认得上面全部历史形状，老数据不用迁移、照常渲染；
 * 需要老形状的消费方用 denormalize 拿回 {type, id}。
 * 读块的类型/标识**只能**经 block_type_of() / block_key_of()，不要直接读数组下标——
 * 契约测试会拦。
 */

if (!function_exists('block_new_key')) {

/** PT 的 _key：块内唯一即可，不需要全局唯一。12 位十六进制足够。 */
function block_new_key(): string {
    return substr(bin2hex(random_bytes(8)), 0, 12);
}

/** 文本块的 style 白名单（PT 惯例） */
function block_text_styles(): array {
    return ['normal' => '正文', 'h1' => '标题 1', 'h2' => '标题 2', 'h3' => '标题 3',
            'h4' => '标题 4', 'blockquote' => '引用'];
}

/**
 * 任意历史形状 → 规范形状。
 *
 * 认得：{_type,_key,...}（已规范）、{type,id,...}（落地页 / 模块库 / BlockModel）。
 * 不认得类型的块**原样保留字段**，只补 _type/_key——宁可留着让人看见，
 * 也不要在归一化时悄悄丢数据。
 */
function block_normalize($b, string $fallbackKey = ''): array {
    if (!is_array($b)) return [];
    $out = [];

    // 类型：_type 优先，其次 type；都没有当文本块处理
    $type = '';
    if (isset($b['_type']) && is_string($b['_type'])) $type = trim($b['_type']);
    if ($type === '' && isset($b['type']) && is_string($b['type'])) $type = trim($b['type']);
    if ($type === '') $type = 'text';

    // 标识：_key 优先，其次 id（老的 blk_xxx 直接沿用，不制造新的孤儿）
    $key = '';
    if (isset($b['_key']) && is_scalar($b['_key'])) $key = trim((string)$b['_key']);
    if ($key === '' && isset($b['id']) && is_scalar($b['id'])) $key = trim((string)$b['id']);
    if ($key === '') $key = $fallbackKey !== '' ? $fallbackKey : block_new_key();

    $out['_type'] = $type;
    $out['_key']  = $key;

    // 其余字段原样带过；_type/_key 的老写法不再重复保留，避免两个真源打架
    foreach ($b as $k => $v) {
        if ($k === 'type' || $k === 'id' || $k === '_type' || $k === '_key') continue;
        $out[$k] = $v;
    }

    // 文本块：补齐 PT 的必需结构
    if ($type === 'block') {
        $out['style'] = (string)($out['style'] ?? 'normal');
        if (!isset($out['markDefs']) || !is_array($out['markDefs'])) $out['markDefs'] = [];
        $out['children'] = block_normalize_spans($out['children'] ?? []);
    }
    return $out;
}

/** span 也要有 _type/_key，否则 PT 生态的编辑器认不出来 */
function block_normalize_spans($spans): array {
    if (!is_array($spans)) return [];
    $out = [];
    foreach ($spans as $s) {
        if (is_string($s)) { $s = ['text' => $s]; }
        if (!is_array($s)) continue;
        $out[] = [
            '_type' => (string)($s['_type'] ?? 'span'),
            '_key'  => ($s['_key'] ?? '') !== '' ? (string)$s['_key'] : block_new_key(),
            'text'  => (string)($s['text'] ?? ''),
            'marks' => array_values(array_filter((array)($s['marks'] ?? []), 'is_string')),
        ];
    }
    return $out;
}

/**
 * 整个数组归一化，并保证 _key 在数组内唯一。
 * 重复的 key 比没有 key 更糟——它会让「按 key 定位块」悄悄指向错的那个。
 */
function block_normalize_all($blocks): array {
    if (!is_array($blocks)) return [];
    $out = []; $seen = [];
    foreach (array_values($blocks) as $i => $b) {
        $n = block_normalize($b);
        if ($n === []) continue;
        while (isset($seen[$n['_key']])) $n['_key'] = block_new_key();   // 撞了就换
        $seen[$n['_key']] = true;
        $out[] = $n;
    }
    return $out;
}

/** 规范形状 → 老形状 {type, id, ...}，给还没改过来的消费方 */
function block_denormalize(array $b): array {
    $out = ['id' => block_key_of($b), 'type' => block_type_of($b)];
    foreach ($b as $k => $v) {
        if ($k === '_type' || $k === '_key') continue;
        $out[$k] = $v;
    }
    return $out;
}

/** 读块类型的唯一入口（两种形状都认） */
function block_type_of($b): string {
    if (!is_array($b)) return '';
    $t = '';
    if (isset($b['_type']) && is_string($b['_type'])) $t = trim($b['_type']);
    if ($t === '' && isset($b['type']) && is_string($b['type'])) $t = trim($b['type']);
    return $t;
}

/** 读块标识的唯一入口（两种形状都认；没有就返回空串，不要现编一个） */
function block_key_of($b): string {
    if (!is_array($b)) return '';
    if (isset($b['_key']) && is_scalar($b['_key']) && (string)$b['_key'] !== '') return (string)$b['_key'];
    if (isset($b['id'])   && is_scalar($b['id'])   && (string)$b['id']   !== '') return (string)$b['id'];
    return '';
}

/** 按 _key 找块（块级批注 / 块级比对的基础动作） */
function block_find($blocks, string $key): ?array {
    if ($key === '') return null;
    foreach ((array)$blocks as $b) if (block_key_of($b) === $key) return (array)$b;
    return null;
}

function block_is_text($b): bool { return block_type_of($b) === 'block'; }

/**
 * 体检：返回问题列表（空数组 = 没问题）。
 * 用在保存前和契约测试里，把「配了不生效」挡在写盘之前。
 */
function block_validate_all($blocks): array {
    $problems = []; $seen = [];
    if (!is_array($blocks)) return ['区块不是数组'];
    foreach (array_values($blocks) as $i => $b) {
        $n = $i + 1;
        if (!is_array($b)) { $problems[] = "第 {$n} 块不是对象"; continue; }
        $t = block_type_of($b);
        $k = block_key_of($b);
        if ($t === '') $problems[] = "第 {$n} 块没有类型";
        if ($k === '') $problems[] = "第 {$n} 块没有 _key（保存后会失去身份）";
        elseif (isset($seen[$k])) $problems[] = "第 {$n} 块的 _key「{$k}」和前面重复";
        else $seen[$k] = true;
        if ($t === 'block' && !is_array($b['children'] ?? null)) {
            $problems[] = "第 {$n} 块是文本块但没有 children";
        }
    }
    return $problems;
}

/* ─────────────────────────────────────────────────────────
 * 文本块 ⇄ HTML
 * 这是 BlockModel 那套（heading/paragraph/list/quote）与 PT 的接口：
 * 它的结构块在这里变成 _type:"block" 的文本块，而不是另一套并行模型。
 * ───────────────────────────────────────────────────────── */

/** 纯文本 → 一个 PT 文本块 */
function block_text(string $text, string $style = 'normal'): array {
    return ['_type' => 'block', '_key' => block_new_key(), 'style' => $style, 'markDefs' => [],
            'children' => [['_type' => 'span', '_key' => block_new_key(), 'text' => $text, 'marks' => []]]];
}

/** PT 文本块 → 纯文本（拼接 children） */
function block_plain_text($b): string {
    if (!is_array($b)) return '';
    $s = '';
    foreach ((array)($b['children'] ?? []) as $c) $s .= (string)($c['text'] ?? '');
    return $s;
}

/** PT 文本块 → HTML（marks 只认最常用的几种，认不得的忽略而不是吐出脏标签） */
function block_text_to_html(array $b): string {
    $style = (string)($b['style'] ?? 'normal');
    $inner = '';
    $defs = [];
    foreach ((array)($b['markDefs'] ?? []) as $d) if (!empty($d['_key'])) $defs[(string)$d['_key']] = $d;
    foreach ((array)($b['children'] ?? []) as $c) {
        $t = htmlspecialchars((string)($c['text'] ?? ''), ENT_QUOTES, 'UTF-8');
        foreach (array_reverse((array)($c['marks'] ?? [])) as $m) {
            $m = (string)$m;
            if ($m === 'strong')      { $t = '<strong>' . $t . '</strong>'; }
            elseif ($m === 'em')      { $t = '<em>' . $t . '</em>'; }
            elseif ($m === 'code')    { $t = '<code>' . $t . '</code>'; }
            elseif ($m === 'underline'){ $t = '<u>' . $t . '</u>'; }
            elseif (isset($defs[$m]) && ($defs[$m]['_type'] ?? '') === 'link') {
                $href = htmlspecialchars((string)($defs[$m]['href'] ?? ''), ENT_QUOTES, 'UTF-8');
                $t = '<a href="' . $href . '">' . $t . '</a>';
            }
        }
        $inner .= $t;
    }
    $li = (string)($b['listItem'] ?? '');
    if ($li !== '') return '<li>' . $inner . '</li>';
    if (preg_match('/^h([1-6])$/', $style, $m)) return "<h{$m[1]}>" . $inner . "</h{$m[1]}>";
    if ($style === 'blockquote') return '<blockquote>' . $inner . '</blockquote>';
    return '<p>' . $inner . '</p>';
}

/** 一串块 → HTML；连续的 listItem 会被合并进同一个 ul/ol */
function block_blocks_to_html($blocks): string {
    $out = ''; $openList = '';
    foreach (block_normalize_all($blocks) as $b) {
        if (block_is_text($b)) {
            $li = (string)($b['listItem'] ?? '');
            $want = $li === 'number' ? 'ol' : ($li === 'bullet' ? 'ul' : '');
            if ($want !== $openList) {
                if ($openList !== '') $out .= "</{$openList}>";
                if ($want !== '')     $out .= "<{$want}>";
                $openList = $want;
            }
            $out .= block_text_to_html($b);
        } else {
            if ($openList !== '') { $out .= "</{$openList}>"; $openList = ''; }
            $out .= block_object_to_html($b);
        }
    }
    if ($openList !== '') $out .= "</{$openList}>";
    return $out;
}

/**
 * 对象块 → HTML。落地页那套区块交给区块注册表渲染（唯一的渲染真源）；
 * 注册表不认的，走这里的保守兜底，绝不静默丢内容。
 */
function block_object_to_html(array $b): string {
    $t = block_type_of($b);
    if ($t === 'html') return (string)($b['html'] ?? '');
    if (function_exists('block_types') && isset(block_types()[$t])) {
        return builder_render_block($b);
    }
    if ($t === 'image') {
        $src = htmlspecialchars((string)($b['src'] ?? ''), ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars((string)($b['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
        return $src !== '' ? '<figure><img src="' . $src . '" alt="' . $alt . '"></figure>' : '';
    }
    if ($t === 'code') {
        return '<pre><code>' . htmlspecialchars((string)($b['code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code></pre>';
    }
    // 认不得：保留原文，让人看得见，而不是悄悄消失
    $txt = block_plain_text($b);
    if ($txt === '') $txt = (string)($b['text'] ?? $b['content'] ?? '');
    return $txt !== '' ? '<p>' . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '</p>' : '';
}

/* ─────────────────────────────────────────────────────────
 * BlockModel ⇄ 契约
 *
 * lib/BlockModel.php 有一套自己的块类型（heading/paragraph/list/quote/code/
 * image/embed/cta/snippet），它一直是休眠的——全站只有它自己的测试引用它。
 * 与其留着当第三套模型，不如把它降级成**契约的 HTML 解析器**：
 * 它的结构块在这里映射成 PT 的文本块（_type:"block" + style），
 * 其余映射成对象块。于是 HTML → 块 这条能力保留下来，模型却只剩一套。
 *
 * 语义映射（不是简单改个字段名）：
 *   heading(level) → block(style:h{level})      list(items[]) → N 个 block(listItem)
 *   paragraph      → block(style:normal)        quote        → block(style:blockquote)
 *   code/image/embed/cta/snippet/html → 同名对象块
 * ───────────────────────────────────────────────────────── */

/** BlockModel 形状 → 契约形状。list 是 1→N，所以这里按数组整体转换。 */
function block_from_blockmodel($blocks): array {
    $out = [];
    foreach ((array)$blocks as $b) {
        if (!is_array($b)) continue;
        $t = (string)($b['type'] ?? 'html');
        if ($t === 'heading') {
            $lv = max(1, min(6, (int)($b['level'] ?? 2)));
            $out[] = block_text((string)($b['text'] ?? ''), 'h' . $lv);
        } elseif ($t === 'paragraph') {
            // paragraph 存的是 HTML 片段；PT 的 span 只装纯文本，
            // 带标签的整体留成 html 对象块，避免把用户的标记吃掉。
            $html = (string)($b['html'] ?? '');
            if (strip_tags($html) === $html) $out[] = block_text($html, 'normal');
            else $out[] = ['_type' => 'html', '_key' => block_new_key(), 'html' => '<p>' . $html . '</p>'];
        } elseif ($t === 'quote') {
            $out[] = block_text((string)($b['text'] ?? ''), 'blockquote');
        } elseif ($t === 'list') {
            $kind = !empty($b['ordered']) ? 'number' : 'bullet';
            foreach ((array)($b['items'] ?? []) as $it) {
                $blk = block_text((string)$it, 'normal');
                $blk['listItem'] = $kind;
                $blk['level'] = 1;
                $out[] = $blk;
            }
        } else {
            $n = block_normalize($b);
            $out[] = $n;
        }
    }
    return block_normalize_all($out);
}

/** HTML → 契约块序列（复用 BlockModel 的保守解析器，结果直接是 PT 形状） */
function block_from_html(string $html): array {
    if (trim($html) === '') return [];
    require_once __DIR__ . '/BlockModel.php';
    return block_from_blockmodel(blockmodel_from_html($html));
}

}
