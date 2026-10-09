<?php
declare(strict_types=1);
/**
 * I18nContent — 页面内容的「分段字典」翻译
 *
 * 为什么不是每页存一份译文：营销页是 HTML 区块，整段重写既难维护又易漏。
 * 这里把 HTML 切成「文本节点 + 少数属性值」，逐段查 data/lang/content-{locale}.json
 * （{ 中文原文: 译文 }）。命中即替换，未命中保持原文（中文）。
 *
 * - 同一句话在多页出现（矩阵块 / 口碑墙 / FAQ）只译一次；
 * - 提取（scripts/i18n-content.php extract）与运行时共用 i18n_segments()/i18n_tr_html()，
 *   所以「抽出来的 key」与「运行时查的 key」必然一致；
 * - 译文源在 scripts/i18n/content.{locale}.json（受版本控制），部署到 data/lang/。
 */

/** 参与翻译的属性（值含中文才处理） */
const I18N_CONTENT_ATTRS = ['alt', 'title', 'aria-label', 'placeholder', 'data-tab', 'data-l', 'content'];

function i18n_has_cjk(string $s): bool {
    return (bool)preg_match('/[\x{4e00}-\x{9fff}]/u', $s);
}

/** 读取某语言的分段字典（进程内缓存）；默认语言/无字典返回 [] */
function i18n_content_map(string $locale): array {
    static $cache = [];
    if (isset($cache[$locale])) return $cache[$locale];
    $map = [];
    $file = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/lang/content-' . $locale . '.json';
    if (is_file($file)) {
        $j = json_decode((string)file_get_contents($file), true);
        if (is_array($j)) $map = $j;
    }
    return $cache[$locale] = $map;
}

/** 单段：保留首尾空白，只对 trim 后的正文查表 */
function i18n_tr_segment(string $s, array $map): string {
    if ($map === [] || !i18n_has_cjk($s)) return $s;
    $t = trim($s);
    if ($t === '' || !isset($map[$t])) return $s;
    $lead = substr($s, 0, strlen($s) - strlen(ltrim($s)));
    $tail = substr($s, strlen(rtrim($s)));
    return $lead . $map[$t] . $tail;
}

/**
 * 切分 HTML：返回 [ [kind, raw] ... ]，kind = 'tag' | 'text' | 'raw'
 * script/style 内容整体当 raw（不翻译）。
 */
function i18n_split_html(string $html): array {
    $out = [];
    $parts = preg_split('/(<!--.*?-->|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<[^>]+>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $p) {
        if ($p === '') continue;
        if ($p[0] === '<') $out[] = [preg_match('/^<(script|style)\b/i', $p) || strncmp($p, '<!--', 4) === 0 ? 'raw' : 'tag', $p];
        else $out[] = ['text', $p];
    }
    return $out;
}

/** 标签内属性值回调：返回 [原值 => 新值] 的处理，translator 为 null 时用于收集 */
function i18n_tag_attrs(string $tag, callable $fn): string {
    $re = '/(?<![\w-])(' . implode('|', array_map('preg_quote', I18N_CONTENT_ATTRS)) . ')=(?:"([^"]*)"|\'([^\']*)\')/i';
    return preg_replace_callback($re, function ($m) use ($fn) {
        $dq = $m[2] !== null;
        $val = $dq ? $m[2] : $m[3];
        if (!i18n_has_cjk($val)) return $m[0];
        $q = $dq ? '"' : "'";
        $new = $fn($val);
        if ($new !== $val) $new = str_replace($q, $dq ? '&quot;' : '&#39;', $new); // 译文里的引号不能截断属性
        return $m[1] . '=' . $q . $new . $q;
    }, $tag, -1, $count, PREG_UNMATCHED_AS_NULL);
}

/**
 * 站内链接带语言前缀：/product → /en/product，/ → /en/。
 * 不动：应用/后台路由、带扩展名的静态文件、已带语言前缀的链接、外链/锚点。
 */
function i18n_localize_href(string $href, string $loc): string {
    if ($href === '' || $href[0] !== '/' || strncmp($href, '//', 2) === 0) return $href;
    $path = parse_url($href, PHP_URL_PATH) ?: '/';
    if (preg_match('#^/(xmp|admin|api|assets|data|uploads|login|logout|lp|en|ja|ko|zh-CN|zh-TW|ru|fr)(/|$)#', $path)) return $href;
    if (preg_match('#\.[a-z0-9]{2,5}$#i', $path)) return $href;
    return '/' . $loc . ($href === '/' ? '/' : $href);
}

/** <a href="/x"> 标签补语言前缀 */
function i18n_localize_tag(string $tag, string $loc): string {
    if (!preg_match('/^<a\s/i', $tag)) return $tag;
    return preg_replace_callback('/(?<![\w-])href=(["\'])(\/[^"\']*)\1/i', fn($m) => 'href=' . $m[1] . i18n_localize_href($m[2], $loc) . $m[1], $tag);
}

/** 翻译整段 HTML（文本节点 + 属性） */
function i18n_tr_html(string $html, ?string $locale = null): string {
    $locale = $locale ?? (function_exists('i18n_current') ? i18n_current() : 'zh-CN');
    $map = i18n_content_map($locale);
    if ($map === [] || !i18n_has_cjk($html)) return $html;
    $buf = '';
    foreach (i18n_split_html($html) as [$kind, $raw]) {
        if ($kind === 'text') $buf .= i18n_tr_segment($raw, $map);
        elseif ($kind === 'tag') $buf .= i18n_localize_tag(i18n_tag_attrs($raw, fn($v) => i18n_tr_segment($v, $map)), $locale);
        else $buf .= $raw;
    }
    return $buf;
}

/** 提取一段 HTML 中所有待译中文片段（trim 后去重） */
function i18n_segments(string $html): array {
    $seg = [];
    foreach (i18n_split_html($html) as [$kind, $raw]) {
        if ($kind === 'text') {
            if (i18n_has_cjk($raw)) $seg[trim($raw)] = true;
        } elseif ($kind === 'tag') {
            i18n_tag_attrs($raw, function ($v) use (&$seg) { $seg[trim($v)] = true; return $v; });
        }
    }
    return array_keys($seg);
}

/** 递归翻译结构化数据（如 nav.json）里的字符串值；href 补语言前缀，icon/id 等非文案键原样保留 */
function i18n_tr_deep($v, array $map, ?string $key = null, ?string $loc = null) {
    static $skip = ['icon', 'id', 'url', 'slug', 'img', 'image', '_type', '_key'];
    if (is_array($v)) {
        foreach ($v as $k => $x) $v[$k] = i18n_tr_deep($x, $map, is_string($k) ? $k : $key, $loc);
        return $v;
    }
    if (!is_string($v)) return $v;
    if ($key === 'href') return $loc ? i18n_localize_href($v, $loc) : $v;
    if ($key !== null && in_array($key, $skip, true)) return $v;
    return i18n_tr_segment($v, $map);
}

/** 收集结构化数据里的中文字符串（与 i18n_tr_deep 同口径） */
function i18n_deep_segments($v, ?string $key = null, array &$out = []): array {
    static $skip = ['href', 'icon', 'id', 'url', 'slug', 'img', 'image', '_type', '_key'];
    if (is_array($v)) {
        foreach ($v as $k => $x) i18n_deep_segments($x, is_string($k) ? $k : $key, $out);
    } elseif (is_string($v) && ($key === null || !in_array($key, $skip, true)) && i18n_has_cjk($v)) {
        $out[trim($v)] = true;
    }
    return array_keys($out);
}

/** 当前请求是否需要对「已声明支持该语言」的内容做翻译 */
function i18n_content_locale(array $supportedByPage = []): ?string {
    if (!function_exists('i18n_current') || !function_exists('i18n_default_locale')) return null;
    $cur = i18n_current();
    if ($cur === i18n_default_locale()) return null;
    return in_array($cur, $supportedByPage, true) ? $cur : null;
}
