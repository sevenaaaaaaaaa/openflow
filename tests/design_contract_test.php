<?php
/**
 * 设计系统契约检查（docs/DESIGN-SYSTEM.md 第四节）
 *
 * 只检查已迁到共享 archetype 的页面（$MIGRATED）。没迁的页在 $PENDING 里只报数不判失败，
 * 迁完一页就把它从 PENDING 挪到 MIGRATED —— 名单本身就是进度表。
 *
 * 用法：php tests/design_contract_test.php
 */
declare(strict_types=1);
$ROOT = dirname(__DIR__);
chdir($ROOT);

$MIGRATED = ['index.php','about.php','product.php','capability.php','courses.php','academy.php','enterprise.php','navigation.php','events.php','community.php','marketplace.php'];
$PENDING  = ['shop.php','member.php','live.php','course-player.php','docs.php','search.php','articles.php','article.php','downloads.php','podcasts.php','tools.php','consultation.php','event.php','community-post.php','author.php','category.php','reviews.php','survey.php','nps.php'];

$SHARED_CLASSES = ['btn','card','sec-head','kicker','foot','stats','hero','hero-center','cta-row','trust','worlds','wf','tl','scn','split','cols','qr','link-grid','cta-band','tab-bar','tab-p','faq','inp','field'];
$MAX_STYLE_LINES = 60;

$fail = 0; $pass = 0;
function check(bool $ok, string $msg): void { global $fail, $pass; if ($ok) { $pass++; } else { $fail++; echo "  ✗ $msg\n"; } }

function page_style(string $src): string {
    // 只取 <head> 里真正的 <style> 块（PHP 注释里提到 <style> 不算）
    if (!preg_match('/\n<style>\n(.*?)\n<\/style>/s', $src, $m)) return '';
    return $m[1];
}

echo "== 已迁页面（必须全部通过）==\n";
foreach ($MIGRATED as $f) {
    $src = file_get_contents($f);
    $css = page_style($src);
    $lines = $css === '' ? 0 : count(explode("\n", $css));
    $tag = str_pad($f, 16);

    check($lines <= $MAX_STYLE_LINES, "$tag 私有 <style> $lines 行 > $MAX_STYLE_LINES");
    check(strpos($src, 'tailwind-build.css') === false, "$tag 仍引用 tailwind-build.css");
    check(strpos($src, 'id="of-modules-css"') !== false, "$tag 没有引 modules.css（id=of-modules-css）");
    check(strpos($src, 'of_shell(') !== false, "$tag 没有通过 of_shell() 接外壳");
    check(!preg_match('/#[0-9a-fA-F]{3,8}\b/', preg_replace('/url\([^)]*\)/', '', $css)), "$tag 私有 CSS 里有 hex 色");
    check(!preg_match('/\brgba?\(/', $css), "$tag 私有 CSS 里有 rgb()/rgba()");
    // 行内样式里的 hex / rgb
    preg_match_all('/style="([^"]*)"/', $src, $mm);
    $inline = implode(' ', $mm[1]);
    check(!preg_match('/#[0-9a-fA-F]{3,8}\b|\brgba?\(/', $inline), "$tag 行内 style 里有 hex / rgb 色");
    // 页面不得重定义共享类（允许 .shared .child 或 .shared.modifier 这种加特异性的覆盖）
    foreach ($SHARED_CLASSES as $c) {
        if (preg_match('/(^|\n|\})\s*\.' . preg_quote($c, '/') . '\s*\{/', $css)) {
            check(false, "$tag 私有 CSS 重定义了共享类 .$c（只能以更高特异性覆盖，不能裸重定义）");
        }
    }
    // 页脚必须是共享 .foot
    check(strpos($src, 'class="foot"') !== false || $f === 'navigation.php', "$tag 没有共享页脚 .foot");
    // JS 拼正文的老习惯
    check(!preg_match('/\.innerHTML\s*=\s*[\'"]<(div|section|article) class="(card|prin|course|tl|fq)/', $src), "$tag 仍用 JS 拼正文卡片");
}

echo "\n== 共享层自检 ==\n";
$mod = file_get_contents('assets/modules.css');
$tok = file_get_contents('assets/tokens.css');
check(!preg_match('/#[0-9a-fA-F]{3,8}\b/', preg_replace('/url\([^)]*\)|#[a-zA-Z][\w-]*/', '', $mod)), "modules.css 里有 hex 色");
check(!preg_match('/\brgba?\(/', $mod), "modules.css 里有 rgb()");
foreach (['.hero-center','.worlds','.wf','.tl','.scn','.split','.cols','.stats','.qr','.link-grid','.cta-band','.faq','.contact-wrap','.foot','.tab-bar','.a-card','.strip','.g-main-aside','.empty'] as $a) {
    check(strpos($mod, "\n$a{") !== false || strpos($mod, "\n$a ") !== false || strpos($mod, "\n$a,") !== false || strpos($mod, "\n$a>") !== false, "modules.css 缺 archetype $a");
}
// 首页的 token 块不能再回来
$idx = file_get_contents('index.php');
check(substr_count($idx, '--accent: oklch') === 0, "index.php 又内联了一份 token（应只在 tokens.css）");
check(strlen(page_style($idx)) < 5000, "index.php 私有样式又长回去了（" . strlen(page_style($idx)) . " 字节）");

echo "\n== 待迁页面（只报数）==\n";
$still = 0;
foreach ($PENDING as $f) { if (!is_file($f)) continue; if (strpos(file_get_contents($f), 'tailwind-build.css') !== false) $still++; }
echo "  仍在 tailwind 上：$still / " . count(array_filter($PENDING, 'is_file')) . "\n";

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
