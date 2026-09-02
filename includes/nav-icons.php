<?php
/**
 * 增长导航的图标：分类 → 线框 svg（按关键词匹配），站点 → 真实 favicon + 首字母兜底。
 *
 *   nav_cat_icon(string $id, string $name = ''): string   // <svg …>
 *   nav_site_icon(array $site): string                   // <span class="fav">…</span>
 *
 * 分类数据来自后台（data/navigation.json），后台里配的 emoji 不再直接输出：
 * 这里按 id / 名称里的关键词映射到同一套 24×24 线框图标，找不到就用指南针。
 * 站点图标直接取该站自己的 /favicon.ico（onerror 自动隐藏，露出首字母格），不依赖第三方图标服务。
 */
if (!function_exists('nav_cat_icon')) {
    function nav_cat_icon(string $id, string $name = ''): string {
        $hay = mb_strtolower($id . ' ' . $name);
        $map = [
            ['seo|搜索|search|排名',        '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'],
            ['数据|分析|data|analytic|统计|监测','<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>'],
            ['ai|智能|模型|gpt|agent',      '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>'],
            ['设计|design|图|视觉|ui|ux',   '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>'],
            ['视频|video|剪辑|直播|播客|音频','<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/>'],
            ['内容|写作|文案|文章|content|write|博客|笔记','<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>'],
            ['社群|社交|社区|social|community|私域|微信|群','<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>'],
            ['邮件|mail|newsletter|edm|订阅','<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
            ['建站|网站|站点|site|web|落地页|landing|cms','<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 20V9"/>'],
            ['自动化|automation|工作流|workflow|流程|集成|api','<path d="M4 6h5v5H4zM15 13h5v5h-5z"/><path d="M9 8.5h3a3 3 0 0 1 3 3V13"/>'],
            ['增长|growth|营销|marketing|获客|广告|投放|转化','<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>'],
            ['销售|crm|客户|sales|线索|销转','<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>'],
            ['效率|工具|tool|productivity|办公|协作|文档|笔记','<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4L15 12l-3-3 2.7-2.7Z"/><path d="m15 3 6 6"/>'],
            ['支付|电商|商城|pay|shop|商品|变现','<path d="M6 7h12l1.5 13.5a1 1 0 0 1-1 1.1H5.5a1 1 0 0 1-1-1.1L6 7Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>'],
            ['学习|课程|教育|learn|course|教程','<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/>'],
            ['开发|代码|code|dev|开源|github|程序','<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>'],
            ['资讯|新闻|news|趋势|trend|媒体','<path d="M4 5h13a3 3 0 0 1 3 3v11H7a3 3 0 0 1-3-3V5Z"/><path d="M8 9h6M8 13h9M8 17h9M17 8v11"/>'],
        ];
        $path = '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>'; // 指南针
        foreach ($map as [$re, $p]) { if (preg_match('/' . $re . '/u', $hay)) { $path = $p; break; } }
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }

    function nav_site_icon(array $site): string {
        $url = (string)($site['url'] ?? '');
        $host = parse_url(strpos($url, '//') === false ? 'https://' . $url : $url, PHP_URL_HOST) ?: '';
        $name = trim((string)($site['name'] ?? ''));
        $letter = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
        $img = $host ? '<img src="https://' . htmlspecialchars($host) . '/favicon.ico" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">' : '';
        return '<span class="fav" aria-hidden="true"><span class="ltr">' . htmlspecialchars($letter) . '</span>' . $img . '</span>';
    }

    function nav_region_icon(string $region): string {
        $p = $region === 'cn'
            ? '<path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>'  // 国内：定位
            : ($region === 'intl' ? '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>' // 海外：地球
            : '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>');
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }
}
