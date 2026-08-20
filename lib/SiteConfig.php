<?php
/**
 * 站点品牌配置 — 统一读取 + 默认值
 * 所有与品牌/公司相关的字段集中在此，前端/后台调用 site_config() 获取
 * 客户可自定义：站点名、公司名、标语、描述、备案号、联系方式等
 */
require_once __DIR__ . '/../admin/config.php';

function site_config(): array {
    $d = json_read(DATA_DIR . '/settings.json');
    return array_merge([
        'site_name' => '芭乐派 · OpenFlow',
        'site_url' => 'https://example.com',
        'company_name' => '芭乐派（OpenFlow）科技有限公司',
        'company_year' => '2026',
        'company_address' => '上海市',
        'site_slogan' => '帮一人公司设计 Agent 能跑的增长系统',
        'site_desc' => '芭乐派增长操作系统的开源底座：TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长',
        'site_keywords' => '增长系统, 一人公司, Agent, 利润公式, R.B.E 训练营, TIPS 框架, 芭乐派, OpenFlow',
        'site_logo' => '',                      // logo URL（空则用默认 SVG）
        'footer_about' => '帮一人公司设计 Agent 能跑的增长系统',
        'footer_contact' => '',                 // footer 联系信息（多行）
        'phone' => '',
        'email' => '',
        'address' => '上海市',
        'beian' => '',
        'copyright_text' => '',                 // 自定义版权文案（空则自动生成）
        'assistant_theme' => 'default',         // 小助手皮肤 default/dark/fresh/jade
    ], $d);
}

// 快捷读取
function site_config_get(string $key, string $default = ''): string {
    $c = site_config();
    return (string)($c[$key] ?? $default);
}

// 版权行
function site_copyright(): string {
    $c = site_config();
    if (!empty($c['copyright_text'])) return $c['copyright_text'];
    $txt = '© ' . date('Y');
    if (!empty($c['company_name'])) $txt .= ' ' . $c['company_name'];
    if (!empty($c['beian'])) $txt .= '　<a href="https://beian.miit.gov.cn" target="_blank" rel="noopener" style="color:inherit;text-decoration:none">' . htmlspecialchars($c['beian']) . '</a>';
    return $txt;
}

// 站点 logo（有则返回 URL，无则空）
function site_logo_url(): string {
    $c = site_config();
    if (!empty($c['site_logo'])) {
        $l = $c['site_logo'];
        return strpos($l, 'http') === 0 ? $l : site_config_get('site_url') . '/' . ltrim($l, '/');
    }
    return '';
}
