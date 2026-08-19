<?php
/**
 * I18n — 轻量多语言支持
 * 语言检测：URL 前缀 /en/、/ja/ 优先，其次 cookie，最后浏览器语言
 * 翻译：data/lang/{locale}.json 语言包，__('key') 或 __('default文本')
 * 配套设置：admin/settings.php 的 multilang_*（enabled/default/languages）
 */

function i18n_settings(): array {
    return json_read(DATA_DIR . '/settings.json')['multilang'] ?? [];
}
function i18n_default_locale(): string {
    return i18n_settings()['default'] ?? 'zh-CN';
}
function i18n_supported(): array {
    $raw = i18n_settings()['languages'] ?? 'zh-CN,en,ja';
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}
function i18n_enabled(): bool {
    return !empty(i18n_settings()['enabled']);
}

// 解析当前语言（URL /en/ 前缀 → cookie → 默认）
function i18n_current(): string {
    static $locale = null;
    if ($locale !== null) return $locale;
    $default = i18n_default_locale();
    $supported = i18n_supported();
    $locale = $default;
    // URL 前缀：/en/xxx 或 /en 或 ?lang=en
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/([a-z]{2}(?:-[A-Z]{2})?)(?:/|$)#', $uri, $m) && in_array($m[1], $supported, true)) {
        $locale = $m[1];
    } elseif (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $locale = $_GET['lang'];
        setcookie('of_lang', $locale, time() + 86400 * 365, '/');
    } elseif (!empty($_COOKIE['of_lang']) && in_array($_COOKIE['of_lang'], $supported, true)) {
        $locale = $_COOKIE['of_lang'];
    } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $l) {
            $lang = strtolower(explode(';', trim($l))[0]);
            if ($lang === 'zh-cn' || $lang === 'zh') { $locale = 'zh-CN'; break; }
            if ($lang === 'en') { $locale = 'en'; break; }
        }
    }
    $locale = in_array($locale, $supported, true) ? $locale : $default;
    return $locale;
}

// 语言包翻译
function __(string $key, string $fallback = ''): string {
    static $dict = null;
    $locale = i18n_current();
    if ($dict === null || $dict['_locale'] !== $locale) {
        $file = DATA_DIR . '/lang/' . $locale . '.json';
        $dict = ['_locale' => $locale, 'data' => is_file($file) ? (json_read($file) ?: []) : []];
    }
    return $dict['data'][$key] ?? ($fallback !== '' ? $fallback : $key);
}

// 语言切换链接（把当前路径换成目标语言前缀）
function i18n_switch_url(string $targetLocale): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = preg_replace('#^/([a-z]{2}(?:-[A-Z]{2})?)(/|$)#', '/', $uri) ?: '/';
    $default = i18n_default_locale();
    if ($targetLocale === $default) return $path;
    return '/' . $targetLocale . $path;
}

// 输出语言切换器 HTML
function i18n_switcher(): string {
    if (!i18n_enabled()) return '';
    $supported = i18n_supported();
    if (count($supported) < 2) return '';
    $current = i18n_current();
    $labels = ['zh-CN' => '中文', 'en' => 'EN', 'ja' => '日本語'];
    $html = '<span style="display:inline-flex;gap:6px;font-size:12px;align-items:center">';
    foreach ($supported as $l) {
        $active = $l === $current;
        $html .= '<a href="' . htmlspecialchars(i18n_switch_url($l)) . '" style="padding:2px 8px;border-radius:999px;' . ($active ? 'background:var(--accent);color:var(--on-accent)' : 'color:var(--muted)') . ';text-decoration:none">' . ($labels[$l] ?? $l) . '</a>';
    }
    $html .= '</span>';
    return $html;
}
