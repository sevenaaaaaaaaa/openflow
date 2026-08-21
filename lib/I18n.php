<?php
/**
 * I18n — 轻量多语言支持（11 语言）
 * 语言检测：URL 前缀 /en/、/ja/、/zh-TW/… 优先，其次 cookie，最后浏览器语言
 * 翻译：data/lang/{locale}.json 语言包，__('key') 或 __('default文本')
 * 配套设置：admin/settings.php 的 multilang_*（enabled/default/languages）+ admin/languages.php 翻译管理
 */

// 语言元数据（native 显示名 + RTL 方向）
function i18n_languages(): array {
    return [
        'zh-CN' => ['name' => '简体中文', 'native' => '简体中文', 'rtl' => false],
        'zh-TW' => ['name' => '繁體中文', 'native' => '繁體中文', 'rtl' => false],
        'en'    => ['name' => 'English', 'native' => 'English', 'rtl' => false],
        'ja'    => ['name' => '日本語', 'native' => '日本語', 'rtl' => false],
        'ko'    => ['name' => '한국어', 'native' => '한국어', 'rtl' => false],
        'ru'    => ['name' => 'Русский', 'native' => 'Русский', 'rtl' => false],
        'es'    => ['name' => 'Español', 'native' => 'Español', 'rtl' => false],
        'pt'    => ['name' => 'Português', 'native' => 'Português', 'rtl' => false],
        'ar'    => ['name' => 'العربية', 'native' => 'العربية', 'rtl' => true],
        'fr'    => ['name' => 'Français', 'native' => 'Français', 'rtl' => false],
        'de'    => ['name' => 'Deutsch', 'native' => 'Deutsch', 'rtl' => false],
    ];
}

function i18n_settings(): array {
    return json_read(DATA_DIR . '/settings.json')['multilang'] ?? [];
}
function i18n_default_locale(): string {
    $d = i18n_settings()['default'] ?? 'zh-CN';
    return isset(i18n_languages()[$d]) ? $d : 'zh-CN';
}
function i18n_supported(): array {
    $raw = trim(i18n_settings()['languages'] ?? '');
    if ($raw !== '') {
        $list = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $list = array_values(array_filter($list, fn($l) => isset(i18n_languages()[$l])));
        if ($list) return $list;
    }
    // 未配置则默认启用全部语言
    return array_keys(i18n_languages());
}
function i18n_enabled(): bool {
    return !empty(i18n_settings()['enabled']);
}
function i18n_is_rtl(?string $locale = null): bool {
    $locale = $locale ?? i18n_current();
    return !empty(i18n_languages()[$locale]['rtl']);
}
function i18n_native(string $locale): string {
    return i18n_languages()[$locale]['native'] ?? $locale;
}

// 解析当前语言（URL 前缀 → ?lang= → cookie → 浏览器语言）
function i18n_current(): string {
    static $locale = null;
    if ($locale !== null) return $locale;
    $default = i18n_default_locale();
    $supported = i18n_supported();
    $locale = $default;
    // URL 前缀：/zh-TW/xxx、/en、/ko/… 或 ?lang=
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/([a-z]{2}(?:-[A-Z]{2})?|[a-z]{2})(?:/|$)#i', $uri, $m) && in_array($m[1], $supported, true)) {
        $locale = $m[1];
    } elseif (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $locale = $_GET['lang'];
        setcookie('of_lang', $locale, time() + 86400 * 365, '/');
    } elseif (!empty($_COOKIE['of_lang']) && in_array($_COOKIE['of_lang'], $supported, true)) {
        $locale = $_COOKIE['of_lang'];
    } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $map = ['zh-tw' => 'zh-TW', 'zh' => 'zh-CN', 'en' => 'en', 'ja' => 'ja', 'ko' => 'ko', 'ru' => 'ru',
                'es' => 'es', 'pt' => 'pt', 'ar' => 'ar', 'fr' => 'fr', 'de' => 'de'];
        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $l) {
            $lang = strtolower(explode(';', trim($l))[0]);
            foreach ($map as $al => $loc) {
                if ($lang === $al || strpos($lang, $al) === 0) {
                    if (in_array($loc, $supported, true)) { $locale = $loc; break 2; }
                }
            }
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
    $path = preg_replace('#^/([a-z]{2}(?:-[A-Z]{2})?)(/|$)#i', '/', $uri) ?: '/';
    $default = i18n_default_locale();
    if ($targetLocale === $default) return $path;
    return '/' . $targetLocale . $path;
}

// 输出语言切换器 HTML（下拉选择器，避免平铺占满导航）
function i18n_switcher(): string {
    if (!i18n_enabled()) return '';
    $supported = i18n_supported();
    if (count($supported) < 2) return '';
    $current = i18n_current();
    $rtl = i18n_is_rtl($current);
    $html = '<details class="of-lang" style="position:relative;display:inline-block;margin:0">'
        . '<summary style="display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:999px;background:var(--glass);border:1px solid var(--border);font-size:12.5px;font-weight:600;color:var(--fg);cursor:pointer;list-style:none;user-select:none;white-space:nowrap">🌐 ' . htmlspecialchars(i18n_native($current)) . ' <span style="font-size:9px;opacity:.55">▾</span></summary>'
        . '<div style="position:absolute;top:calc(100% + 8px);' . ($rtl ? 'left:0' : 'right:0') . ';min-width:150px;background:var(--surface-strong);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:5px;z-index:100;max-height:340px;overflow:auto">';
    foreach ($supported as $l) {
        $active = $l === $current;
        $html .= '<a href="' . htmlspecialchars(i18n_switch_url($l)) . '" lang="' . $l . '" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9px;font-size:13px;color:' . ($active ? 'var(--accent-strong)' : 'var(--fg)') . ';font-weight:' . ($active ? '700' : '500') . ';text-decoration:none;background:' . ($active ? 'var(--accent-soft)' : 'transparent') . '">' . htmlspecialchars(i18n_native($l)) . ($active ? '<span style="margin-left:auto">✓</span>' : '') . '</a>';
    }
    $html .= '</div></details>';
    // 一次性输出：隐藏 details 原生三角 + 点击外部关闭
    static $jsOut = false;
    if (!$jsOut) {
        $jsOut = true;
        $html .= '<style>details.of-lang summary::-webkit-details-marker{display:none}</style>'
            . '<script>(function(){document.addEventListener("click",function(e){document.querySelectorAll("details.of-lang[open]").forEach(function(d){if(!d.contains(e.target))d.removeAttribute("open")})});})();</script>';
    }
    return $html;
}
