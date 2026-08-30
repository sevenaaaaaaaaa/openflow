<?php
/**
 * ImageVariants —— 图片自动 WebP + 多尺寸（AUDIT-01 / BACKLOG T1-7）
 *
 * 【为什么】上传的图原样发出去，手机也在下 1920px 的 JPG——LCP 和 SEO 实打实吃亏。
 * 原上传只在 >300KB 或 png 时转一次 webp，且**只有一个尺寸**，没有 srcset。
 * 本模块补齐：一次上传 → 生成 webp + 2~3 个宽度，前台用 srcset 让浏览器按需取。
 *
 * 【零外部依赖】只用 PHP 内置 GD（imagewebp）；GD/webp 不可用时原样返回、不报错。
 * 命名：foo.jpg → foo-480.webp / foo-960.webp / foo-1920.webp（不覆盖原图）。
 */

if (!function_exists('imgvar_widths')) {

    /** 默认响应式宽度（升序）。超过原图宽度的档位会被跳过，不放大。 */
    function imgvar_widths(): array { return [480, 960, 1920]; }

    function imgvar_supported(): bool {
        return function_exists('imagecreatetruecolor') && function_exists('imagewebp');
    }

    /** 从路径读成 GD 资源（支持 jpg/png/gif/webp）。失败返回 null。 */
    function imgvar_load(string $path) {
        if (!is_file($path)) return null;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $img = null;
        switch ($ext) {
            case 'jpg': case 'jpeg': $img = @imagecreatefromjpeg($path); break;
            case 'png':  $img = @imagecreatefrompng($path); break;
            case 'gif':  $img = @imagecreatefromgif($path); break;
            case 'webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null; break;
        }
        return $img ?: null;
    }

    /**
     * 为一张图生成 webp 多尺寸。
     * 返回 ['ok'=>bool,'variants'=>[width=>相对文件名],'srcset'=>string,'error'?]。
     * $urlBase 用于拼 srcset（如 /uploads/media）；留空则 srcset 只给文件名。
     * 幂等：已存在同名产物会被覆盖重写。
     */
    function imgvar_generate(string $srcPath, string $urlBase = '', array $widths = [], int $quality = 82): array {
        if (!imgvar_supported()) return ['ok' => false, 'error' => 'GD/webp 不可用', 'variants' => [], 'srcset' => ''];
        $img = imgvar_load($srcPath);
        if (!$img) return ['ok' => false, 'error' => '无法读取图片', 'variants' => [], 'srcset' => ''];

        $ow = imagesx($img); $oh = imagesy($img);
        if ($ow < 1 || $oh < 1) { imagedestroy($img); return ['ok' => false, 'error' => '尺寸异常', 'variants' => [], 'srcset' => '']; }

        $dir  = dirname($srcPath);
        $base = pathinfo($srcPath, PATHINFO_FILENAME);
        $widths = $widths ?: imgvar_widths();
        sort($widths);

        $variants = [];
        foreach ($widths as $w) {
            $w = (int)$w;
            if ($w < 1) continue;
            if ($w > $ow) continue;                       // 不放大
            $h = max(1, (int)round($oh * $w / $ow));
            $canvas = imagecreatetruecolor($w, $h);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $img, 0, 0, 0, 0, $w, $h, $ow, $oh);
            $out = $dir . '/' . $base . '-' . $w . '.webp';
            if (@imagewebp($canvas, $out, $quality)) $variants[$w] = basename($out);
            imagedestroy($canvas);
        }
        // 原图比最小档还小：至少产出一份原尺寸 webp，保证总有 webp 可用
        if (!$variants) {
            $out = $dir . '/' . $base . '-' . $ow . '.webp';
            if (@imagewebp($img, $out, $quality)) $variants[$ow] = basename($out);
        }
        imagedestroy($img);

        return [
            'ok' => !empty($variants),
            'variants' => $variants,
            'srcset' => imgvar_srcset($variants, $urlBase),
            'width' => $ow, 'height' => $oh,
        ];
    }

    /** 由 variants 组装 srcset 字符串。 */
    function imgvar_srcset(array $variants, string $urlBase = ''): string {
        $parts = [];
        ksort($variants);
        foreach ($variants as $w => $file) {
            $u = $urlBase !== '' ? rtrim($urlBase, '/') . '/' . $file : $file;
            $parts[] = $u . ' ' . (int)$w . 'w';
        }
        return implode(', ', $parts);
    }

    /**
     * 渲染响应式 <img>：有 variants 用 srcset，否则回落普通 img。
     * $sizes 默认按常见正文宽度给个合理值。
     */
    function imgvar_img_tag(string $fallbackUrl, array $variants = [], string $urlBase = '', string $alt = '', string $sizes = '(max-width: 768px) 100vw, 720px'): string {
        $alt = htmlspecialchars($alt, ENT_QUOTES);
        $src = htmlspecialchars($fallbackUrl, ENT_QUOTES);
        if (!$variants) return '<img src="' . $src . '" alt="' . $alt . '" loading="lazy">';
        $srcset = htmlspecialchars(imgvar_srcset($variants, $urlBase), ENT_QUOTES);
        return '<img src="' . $src . '" srcset="' . $srcset . '" sizes="' . htmlspecialchars($sizes, ENT_QUOTES) . '" alt="' . $alt . '" loading="lazy" decoding="async">';
    }
}
