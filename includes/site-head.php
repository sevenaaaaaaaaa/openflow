<?php
/**
 * 共享 <head> 资源 —— 主题早绑定脚本 + fonts / tokens / modules 三条 <link>。
 *
 *   <?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
 *
 * 版本号与 includes/site-nav.php 的 OF_SHELL_VER 同源：改一处，全站失效缓存。
 */
require_once __DIR__ . '/site-nav.php'; // 只定义函数与 OF_SHELL_VER，不输出
if (!function_exists('of_head_assets')) {
    function of_head_assets(): void {
        $v = defined('OF_SHELL_VER') ? OF_SHELL_VER : '20260903a';
        echo '<script>try{var t=JSON.parse(localStorage.getItem(\'openflow-site-v3\')||\'{}\');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia(\'(prefers-reduced-motion: reduce)\').matches)document.documentElement.classList.add(\'rm\');}catch(e){}</script>' . "\n";
        echo '<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=' . $v . '">' . "\n";
    }
}
