<?php
/**
 * 主题系统 ThemeSystem
 * 主题不是"换皮"，而是不同的视觉 + 交互 + 布局，但完全兼容所有前后端功能。
 *
 * 实现：所有页面/组件统一消费一套 oklch CSS 变量（--bg/--accent/--fg…），
 * 主题 = 这套变量的一组不同取值 + 布局偏好（圆角/字体/玻璃强度/动效/密度）。
 * 改变量即改全站视觉，功能接口不变。
 *
 * 预设主题：
 *  - default：官方默认（奶油底 + 蓝紫 accent + 玻璃拟态 + 衬线标题）
 *  - notion：Notion Like（纯白极简 + 中性灰 + 无玻璃 + 小圆角）
 *  - claude：Claude Like（温暖深色 + 珊瑚 accent + 等宽字体 + 直角）
 *  - apple：Apple Like（浅灰白 + 蓝 accent + 强毛玻璃 + 大圆角 + SF 字体）
 *  - google：Google Like（Material 白 + 蓝 accent + 8px 圆角 + Roboto）
 *  - linear：Linear Like（近黑深色 + 紫蓝 accent + 细边框 + Inter + 无玻璃）
 */
require_once __DIR__ . '/../admin/config.php';

class ThemeSystem {
    private static string $file = DATA_DIR . '/themes.json';

    /* ─── 变量键清单（所有主题都必须完整提供） ─── */
    public const VARS = [
        'bg', 'bg-soft', 'surface', 'surface-strong', 'fg', 'muted', 'faint',
        'border', 'border-strong', 'hover', 'hover-strong',
        'accent', 'accent-strong', 'accent-soft', 'on-accent',
        'glass', 'glass-bright', 'glass-border',
        'blob-a', 'blob-b', 'blob-c',
        'shadow', 'shadow-sm',
    ];

    /* ─── 主题预设 ─── */
    public static function presets(): array {
        return [
            'default' => [
                'name' => '官方默认',
                'desc' => '奶油底 + 蓝紫 accent + 玻璃拟态 + 衬线标题',
                'light' => [
                    'bg' => 'oklch(96.5% .016 85)', 'bg-soft' => 'oklch(94% .02 85)',
                    'surface' => 'oklch(100% 0 0/.62)', 'surface-strong' => 'oklch(100% 0 0/.88)',
                    'fg' => 'oklch(22% .02 70)', 'muted' => 'oklch(46% .016 70)', 'faint' => 'oklch(60% .012 75)',
                    'border' => 'oklch(86% .014 80)', 'border-strong' => 'oklch(76% .02 80)',
                    'hover' => 'oklch(22% .02 70/.055)', 'hover-strong' => 'oklch(22% .02 70/.11)',
                    'accent' => 'oklch(52% .17 258)', 'accent-strong' => 'oklch(46% .17 258)', 'accent-soft' => 'oklch(52% .17 258/.12)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/.5)', 'glass-bright' => 'oklch(100% 0 0/.66)', 'glass-border' => 'oklch(100% 0 0/.68)',
                    'blob-a' => 'oklch(72% .12 262/.30)', 'blob-b' => 'oklch(70% .13 305/.24)', 'blob-c' => 'oklch(74% .11 200/.22)',
                    'shadow' => '0 24px 60px -24px oklch(30% .04 80/.28)', 'shadow-sm' => '0 10px 28px -14px oklch(30% .04 80/.22)',
                ],
                'dark' => [
                    'bg' => 'oklch(19% .014 70)', 'bg-soft' => 'oklch(22.5% .014 72)',
                    'surface' => 'oklch(27% .016 75/.55)', 'surface-strong' => 'oklch(30% .016 75/.82)',
                    'fg' => 'oklch(93% .008 85)', 'muted' => 'oklch(70% .014 80)', 'faint' => 'oklch(55% .012 80)',
                    'border' => 'oklch(100% 0 0/.1)', 'border-strong' => 'oklch(100% 0 0/.2)',
                    'hover' => 'oklch(93% .008 85/.07)', 'hover-strong' => 'oklch(93% .008 85/.13)',
                    'accent' => 'oklch(74% .13 258)', 'accent-strong' => 'oklch(80% .12 258)', 'accent-soft' => 'oklch(74% .13 258/.15)', 'on-accent' => 'oklch(16% .03 260)',
                    'glass' => 'oklch(30% .014 75/.5)', 'glass-bright' => 'oklch(34% .014 75/.62)', 'glass-border' => 'oklch(100% 0 0/.15)',
                    'blob-a' => 'oklch(62% .13 262/.18)', 'blob-b' => 'oklch(58% .14 305/.15)', 'blob-c' => 'oklch(60% .12 200/.13)',
                    'shadow' => '0 24px 60px -24px oklch(0% 0 0/.55)', 'shadow-sm' => '0 10px 28px -14px oklch(0% 0 0/.5)',
                ],
                'layout' => [
                    'r-lg' => '26px', 'r-md' => '18px', 'r-sm' => '12px',
                    'font-display' => '"Songti SC","Iowan Old Style",Georgia,serif',
                    'font-body' => '-apple-system,BlinkMacSystemFont,"PingFang SC","Segoe UI",system-ui,sans-serif',
                    'font-mono' => 'ui-monospace,"SF Mono","JetBrains Mono",Menlo,monospace',
                    'glass-strength' => 'medium', 'density' => 'comfortable', 'motion' => 'spring',
                ],
            ],

            'notion' => [
                'name' => 'Notion Like',
                'desc' => '纯白极简 + 中性灰 + 无玻璃 + 3px 圆角 + 密集排版',
                'light' => [
                    'bg' => 'oklch(100% 0 0)', 'bg-soft' => 'oklch(97% 0 0)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(24% 0 0)', 'muted' => 'oklch(45% 0 0)', 'faint' => 'oklch(60% 0 0)',
                    'border' => 'oklch(88% 0 0)', 'border-strong' => 'oklch(78% 0 0)',
                    'hover' => 'oklch(96% 0 0)', 'hover-strong' => 'oklch(92% 0 0)',
                    'accent' => 'oklch(30% 0 0)', 'accent-strong' => 'oklch(20% 0 0)', 'accent-soft' => 'oklch(30% 0 0/.08)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/1)', 'glass-bright' => 'oklch(100% 0 0/1)', 'glass-border' => 'oklch(88% 0 0)',
                    'blob-a' => 'oklch(95% 0 0/.5)', 'blob-b' => 'oklch(95% 0 0/.4)', 'blob-c' => 'oklch(95% 0 0/.4)',
                    'shadow' => '0 1px 2px oklch(0% 0 0/.04)', 'shadow-sm' => '0 1px 1px oklch(0% 0 0/.03)',
                ],
                'dark' => [
                    'bg' => 'oklch(20% 0 0)', 'bg-soft' => 'oklch(24% 0 0)',
                    'surface' => 'oklch(24% 0 0/1)', 'surface-strong' => 'oklch(28% 0 0/1)',
                    'fg' => 'oklch(92% 0 0)', 'muted' => 'oklch(70% 0 0)', 'faint' => 'oklch(55% 0 0)',
                    'border' => 'oklch(100% 0 0/.08)', 'border-strong' => 'oklch(100% 0 0/.16)',
                    'hover' => 'oklch(100% 0 0/.04)', 'hover-strong' => 'oklch(100% 0 0/.08)',
                    'accent' => 'oklch(85% 0 0)', 'accent-strong' => 'oklch(95% 0 0)', 'accent-soft' => 'oklch(85% 0 0/.1)', 'on-accent' => 'oklch(15% 0 0)',
                    'glass' => 'oklch(24% 0 0/1)', 'glass-bright' => 'oklch(28% 0 0/1)', 'glass-border' => 'oklch(100% 0 0/.08)',
                    'blob-a' => 'oklch(22% 0 0/.4)', 'blob-b' => 'oklch(22% 0 0/.3)', 'blob-c' => 'oklch(22% 0 0/.3)',
                    'shadow' => '0 1px 2px oklch(0% 0 0/.3)', 'shadow-sm' => '0 1px 1px oklch(0% 0 0/.25)',
                ],
                'layout' => [
                    'r-lg' => '4px', 'r-md' => '3px', 'r-sm' => '2px',
                    'font-display' => 'system-ui,sans-serif',
                    'font-body' => 'system-ui,-apple-system,"PingFang SC",sans-serif',
                    'font-mono' => 'ui-monospace,Menlo,monospace',
                    'glass-strength' => 'none', 'density' => 'compact', 'motion' => 'minimal',
                ],
            ],

            'claude' => [
                'name' => 'Claude Like',
                'desc' => '温暖深色 + 珊瑚 accent + 等宽字体 + 直角 + 无玻璃',
                'light' => [
                    'bg' => 'oklch(97% .005 40)', 'bg-soft' => 'oklch(94% .006 40)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(24% .008 40)', 'muted' => 'oklch(46% .01 40)', 'faint' => 'oklch(60% .01 40)',
                    'border' => 'oklch(88% .006 40)', 'border-strong' => 'oklch(78% .008 40)',
                    'hover' => 'oklch(95% .005 40)', 'hover-strong' => 'oklch(91% .006 40)',
                    'accent' => 'oklch(62% .13 45)', 'accent-strong' => 'oklch(55% .14 45)', 'accent-soft' => 'oklch(62% .13 45/.12)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/1)', 'glass-bright' => 'oklch(100% 0 0/1)', 'glass-border' => 'oklch(88% .006 40)',
                    'blob-a' => 'oklch(75% .05 45/.25)', 'blob-b' => 'oklch(72% .06 20/.2)', 'blob-c' => 'oklch(73% .04 60/.2)',
                    'shadow' => '0 2px 8px oklch(30% .02 40/.1)', 'shadow-sm' => '0 1px 4px oklch(30% .02 40/.08)',
                ],
                'dark' => [
                    'bg' => 'oklch(20% .006 40)', 'bg-soft' => 'oklch(23% .006 40)',
                    'surface' => 'oklch(24% .007 40/1)', 'surface-strong' => 'oklch(28% .008 40/1)',
                    'fg' => 'oklch(92% .006 40)', 'muted' => 'oklch(70% .008 40)', 'faint' => 'oklch(55% .01 40)',
                    'border' => 'oklch(100% 0 0/.09)', 'border-strong' => 'oklch(100% 0 0/.18)',
                    'hover' => 'oklch(100% 0 0/.05)', 'hover-strong' => 'oklch(100% 0 0/.09)',
                    'accent' => 'oklch(68% .13 45)', 'accent-strong' => 'oklch(73% .12 45)', 'accent-soft' => 'oklch(68% .13 45/.16)', 'on-accent' => 'oklch(15% .02 40)',
                    'glass' => 'oklch(24% .007 40/1)', 'glass-bright' => 'oklch(28% .008 40/1)', 'glass-border' => 'oklch(100% 0 0/.09)',
                    'blob-a' => 'oklch(55% .06 45/.16)', 'blob-b' => 'oklch(52% .07 20/.14)', 'blob-c' => 'oklch(53% .05 60/.14)',
                    'shadow' => '0 4px 16px oklch(0% 0 0/.4)', 'shadow-sm' => '0 2px 8px oklch(0% 0 0/.3)',
                ],
                'layout' => [
                    'r-lg' => '4px', 'r-md' => '3px', 'r-sm' => '2px',
                    'font-display' => '"SF Mono","JetBrains Mono",ui-monospace,monospace',
                    'font-body' => '"SF Mono","JetBrains Mono",ui-monospace,Menlo,monospace',
                    'font-mono' => '"SF Mono","JetBrains Mono",ui-monospace,monospace',
                    'glass-strength' => 'none', 'density' => 'compact', 'motion' => 'minimal',
                ],
            ],

            'apple' => [
                'name' => 'Apple Like',
                'desc' => '浅灰白 + 蓝 accent + 强毛玻璃 + 大圆角 + SF 字体',
                'light' => [
                    'bg' => 'oklch(98% .004 250)', 'bg-soft' => 'oklch(95% .006 250)',
                    'surface' => 'oklch(100% 0 0/.72)', 'surface-strong' => 'oklch(100% 0 0/.92)',
                    'fg' => 'oklch(20% .01 255)', 'muted' => 'oklch(45% .01 255)', 'faint' => 'oklch(58% .008 255)',
                    'border' => 'oklch(88% .005 250)', 'border-strong' => 'oklch(78% .008 250)',
                    'hover' => 'oklch(20% .01 255/.05)', 'hover-strong' => 'oklch(20% .01 255/.1)',
                    'accent' => 'oklch(55% .18 255)', 'accent-strong' => 'oklch(50% .18 255)', 'accent-soft' => 'oklch(55% .18 255/.12)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/.55)', 'glass-bright' => 'oklch(100% 0 0/.75)', 'glass-border' => 'oklch(100% 0 0/.8)',
                    'blob-a' => 'oklch(80% .1 255/.25)', 'blob-b' => 'oklch(78% .1 305/.2)', 'blob-c' => 'oklch(82% .08 200/.2)',
                    'shadow' => '0 20px 50px -20px oklch(25% .02 255/.25)', 'shadow-sm' => '0 8px 24px -10px oklch(25% .02 255/.2)',
                ],
                'dark' => [
                    'bg' => 'oklch(16% .01 255)', 'bg-soft' => 'oklch(20% .012 255)',
                    'surface' => 'oklch(24% .012 255/.6)', 'surface-strong' => 'oklch(28% .012 255/.85)',
                    'fg' => 'oklch(95% .004 250)', 'muted' => 'oklch(72% .008 255)', 'faint' => 'oklch(58% .01 255)',
                    'border' => 'oklch(100% 0 0/.1)', 'border-strong' => 'oklch(100% 0 0/.2)',
                    'hover' => 'oklch(95% .004 250/.07)', 'hover-strong' => 'oklch(95% .004 250/.13)',
                    'accent' => 'oklch(68% .16 255)', 'accent-strong' => 'oklch(75% .15 255)', 'accent-soft' => 'oklch(68% .16 255/.16)', 'on-accent' => 'oklch(14% .01 255)',
                    'glass' => 'oklch(24% .012 255/.55)', 'glass-bright' => 'oklch(28% .012 255/.7)', 'glass-border' => 'oklch(100% 0 0/.14)',
                    'blob-a' => 'oklch(58% .12 255/.15)', 'blob-b' => 'oklch(55% .12 305/.13)', 'blob-c' => 'oklch(58% .1 200/.13)',
                    'shadow' => '0 24px 50px -24px oklch(0% 0 0/.5)', 'shadow-sm' => '0 10px 24px -12px oklch(0% 0 0/.42)',
                ],
                'layout' => [
                    'r-lg' => '22px', 'r-md' => '16px', 'r-sm' => '10px',
                    'font-display' => '-apple-system,BlinkMacSystemFont,"SF Pro Display",system-ui,sans-serif',
                    'font-body' => '-apple-system,BlinkMacSystemFont,"SF Pro Text",system-ui,sans-serif',
                    'font-mono' => '"SF Mono",ui-monospace,monospace',
                    'glass-strength' => 'strong', 'density' => 'comfortable', 'motion' => 'smooth',
                ],
            ],

            'google' => [
                'name' => 'Google Like',
                'desc' => 'Material 白 + 蓝 accent + 8px 圆角 + Roboto + 卡片阴影',
                'light' => [
                    'bg' => 'oklch(99% .002 255)', 'bg-soft' => 'oklch(96% .004 255)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(22% .01 260)', 'muted' => 'oklch(45% .012 260)', 'faint' => 'oklch(58% .01 260)',
                    'border' => 'oklch(88% .005 260)', 'border-strong' => 'oklch(76% .01 260)',
                    'hover' => 'oklch(22% .01 260/.04)', 'hover-strong' => 'oklch(22% .01 260/.08)',
                    'accent' => 'oklch(53% .17 260)', 'accent-strong' => 'oklch(48% .17 260)', 'accent-soft' => 'oklch(53% .17 260/.1)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/1)', 'glass-bright' => 'oklch(100% 0 0/1)', 'glass-border' => 'oklch(88% .005 260)',
                    'blob-a' => 'oklch(75% .1 260/.18)', 'blob-b' => 'oklch(78% .1 150/.14)', 'blob-c' => 'oklch(80% .1 70/.14)',
                    'shadow' => '0 1px 3px oklch(25% .02 260/.12),0 1px 2px oklch(25% .02 260/.06)', 'shadow-sm' => '0 1px 2px oklch(25% .02 260/.08)',
                ],
                'dark' => [
                    'bg' => 'oklch(21% .006 260)', 'bg-soft' => 'oklch(24% .008 260)',
                    'surface' => 'oklch(26% .008 260/1)', 'surface-strong' => 'oklch(30% .01 260/1)',
                    'fg' => 'oklch(93% .004 260)', 'muted' => 'oklch(72% .01 260)', 'faint' => 'oklch(57% .01 260)',
                    'border' => 'oklch(100% 0 0/.1)', 'border-strong' => 'oklch(100% 0 0/.2)',
                    'hover' => 'oklch(93% .004 260/.06)', 'hover-strong' => 'oklch(93% .004 260/.12)',
                    'accent' => 'oklch(68% .15 260)', 'accent-strong' => 'oklch(75% .14 260)', 'accent-soft' => 'oklch(68% .15 260/.15)', 'on-accent' => 'oklch(15% .01 260)',
                    'glass' => 'oklch(26% .008 260/1)', 'glass-bright' => 'oklch(30% .01 260/1)', 'glass-border' => 'oklch(100% 0 0/.1)',
                    'blob-a' => 'oklch(55% .11 260/.14)', 'blob-b' => 'oklch(58% .11 150/.12)', 'blob-c' => 'oklch(60% .11 70/.12)',
                    'shadow' => '0 2px 6px oklch(0% 0 0/.4)', 'shadow-sm' => '0 1px 3px oklch(0% 0 0/.3)',
                ],
                'layout' => [
                    'r-lg' => '8px', 'r-md' => '6px', 'r-sm' => '4px',
                    'font-display' => 'Roboto,"PingFang SC",system-ui,sans-serif',
                    'font-body' => 'Roboto,"PingFang SC",system-ui,sans-serif',
                    'font-mono' => '"Roboto Mono",ui-monospace,monospace',
                    'glass-strength' => 'none', 'density' => 'comfortable', 'motion' => 'standard',
                ],
            ],

            'linear' => [
                'name' => 'Linear Like',
                'desc' => '近黑深色 + 紫蓝 accent + 细边框 + 6px 圆角 + Inter',
                'light' => [
                    'bg' => 'oklch(97% .003 280)', 'bg-soft' => 'oklch(94% .004 280)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(22% .01 280)', 'muted' => 'oklch(45% .012 280)', 'faint' => 'oklch(58% .01 280)',
                    'border' => 'oklch(88% .005 280)', 'border-strong' => 'oklch(78% .008 280)',
                    'hover' => 'oklch(22% .01 280/.04)', 'hover-strong' => 'oklch(22% .01 280/.08)',
                    'accent' => 'oklch(56% .18 285)', 'accent-strong' => 'oklch(50% .18 285)', 'accent-soft' => 'oklch(56% .18 285/.1)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/1)', 'glass-bright' => 'oklch(100% 0 0/1)', 'glass-border' => 'oklch(88% .005 280)',
                    'blob-a' => 'oklch(70% .12 285/.16)', 'blob-b' => 'oklch(68% .12 305/.14)', 'blob-c' => 'oklch(72% .1 200/.14)',
                    'shadow' => '0 1px 2px oklch(25% .01 280/.08)', 'shadow-sm' => '0 1px 1px oklch(25% .01 280/.05)',
                ],
                'dark' => [
                    'bg' => 'oklch(16% .004 280)', 'bg-soft' => 'oklch(19% .006 280)',
                    'surface' => 'oklch(21% .006 280/1)', 'surface-strong' => 'oklch(24% .008 280/1)',
                    'fg' => 'oklch(94% .004 280)', 'muted' => 'oklch(72% .008 280)', 'faint' => 'oklch(56% .01 280)',
                    'border' => 'oklch(100% 0 0/.08)', 'border-strong' => 'oklch(100% 0 0/.16)',
                    'hover' => 'oklch(94% .004 280/.05)', 'hover-strong' => 'oklch(94% .004 280/.1)',
                    'accent' => 'oklch(62% .17 285)', 'accent-strong' => 'oklch(68% .16 285)', 'accent-soft' => 'oklch(62% .17 285/.14)', 'on-accent' => 'oklch(14% .01 280)',
                    'glass' => 'oklch(21% .006 280/1)', 'glass-bright' => 'oklch(24% .008 280/1)', 'glass-border' => 'oklch(100% 0 0/.08)',
                    'blob-a' => 'oklch(52% .13 285/.13)', 'blob-b' => 'oklch(50% .13 305/.11)', 'blob-c' => 'oklch(53% .11 200/.11)',
                    'shadow' => '0 2px 6px oklch(0% 0 0/.4)', 'shadow-sm' => '0 1px 3px oklch(0% 0 0/.3)',
                ],
                'layout' => [
                    'r-lg' => '6px', 'r-md' => '5px', 'r-sm' => '3px',
                    'font-display' => 'Inter,-apple-system,"PingFang SC",sans-serif',
                    'font-body' => 'Inter,-apple-system,"PingFang SC",system-ui,sans-serif',
                    'font-mono' => '"JetBrains Mono",ui-monospace,monospace',
                    'glass-strength' => 'none', 'density' => 'compact', 'motion' => 'minimal',
                ],
            ],
            'market' => [
                'name' => '鲜活市集（新零售）',
                'desc' => '活力橙 + 奶油白 + 牛油果绿 · 超大圆角 · 烟火气',
                'light' => [
                    'bg' => 'oklch(98% .01 85)', 'bg-soft' => 'oklch(96% .015 85)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(28% .03 45)', 'muted' => 'oklch(48% .03 50)', 'faint' => 'oklch(62% .02 55)',
                    'border' => 'oklch(90% .02 80)', 'border-strong' => 'oklch(82% .03 70)',
                    'hover' => 'oklch(30% .03 60/.05)', 'hover-strong' => 'oklch(30% .03 60/.1)',
                    'accent' => 'oklch(62% .19 55)', 'accent-strong' => 'oklch(56% .19 50)', 'accent-soft' => 'oklch(62% .19 55/.14)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/.72)', 'glass-bright' => 'oklch(100% 0 0/.88)', 'glass-border' => 'oklch(100% 0 0/.9)',
                    'blob-a' => 'oklch(78% .16 70/.3)', 'blob-b' => 'oklch(72% .14 150/.26)', 'blob-c' => 'oklch(80% .14 60/.24)',
                    'shadow' => '0 20px 50px -20px oklch(40% .05 60/.35)', 'shadow-sm' => '0 8px 24px -12px oklch(40% .05 60/.28)',
                ],
                'dark' => [
                    'bg' => 'oklch(20% .02 55)', 'bg-soft' => 'oklch(24% .025 55)',
                    'surface' => 'oklch(27% .028 55/.85)', 'surface-strong' => 'oklch(30% .03 55/1)',
                    'fg' => 'oklch(94% .01 80)', 'muted' => 'oklch(74% .02 75)', 'faint' => 'oklch(58% .02 70)',
                    'border' => 'oklch(100% 0 0/.12)', 'border-strong' => 'oklch(100% 0 0/.24)',
                    'hover' => 'oklch(94% .01 80/.07)', 'hover-strong' => 'oklch(94% .01 80/.13)',
                    'accent' => 'oklch(72% .18 55)', 'accent-strong' => 'oklch(78% .17 55)', 'accent-soft' => 'oklch(72% .18 55/.16)', 'on-accent' => 'oklch(20% .03 50)',
                    'glass' => 'oklch(27% .028 55/.7)', 'glass-bright' => 'oklch(30% .03 55/.9)', 'glass-border' => 'oklch(100% 0 0/.14)',
                    'blob-a' => 'oklch(60% .17 70/.2)', 'blob-b' => 'oklch(55% .15 150/.18)', 'blob-c' => 'oklch(62% .16 55/.18)',
                    'shadow' => '0 20px 50px -20px oklch(0% 0 0/.6)', 'shadow-sm' => '0 8px 24px -12px oklch(0% 0 0/.5)',
                ],
                'layout' => [
                    'r-lg' => '28px', 'r-md' => '20px', 'r-sm' => '14px',
                    'font-display' => '"Space Grotesk","PingFang SC","HarmonyOS Sans SC",sans-serif',
                    'font-body' => '"Space Grotesk","PingFang SC","HarmonyOS Sans SC",system-ui,sans-serif',
                    'font-mono' => 'ui-monospace,"SF Mono",Menlo,monospace',
                    'glass-strength' => 'soft', 'density' => 'airy', 'motion' => 'lively',
                ],
            ],
            'commerce' => [
                'name' => '促销冲刺（电商）',
                'desc' => '红黄高对比 + 大号数字 + 白底卡片 · 急迫感',
                'light' => [
                    'bg' => 'oklch(99% .004 30)', 'bg-soft' => 'oklch(97% .01 30)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(25% .025 25)', 'muted' => 'oklch(50% .02 30)', 'faint' => 'oklch(62% .015 30)',
                    'border' => 'oklch(92% .01 30)', 'border-strong' => 'oklch(84% .02 30)',
                    'hover' => 'oklch(30% .02 30/.05)', 'hover-strong' => 'oklch(30% .02 30/.1)',
                    'accent' => 'oklch(58% .22 30)', 'accent-strong' => 'oklch(52% .22 25)', 'accent-soft' => 'oklch(58% .22 30/.13)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/.8)', 'glass-bright' => 'oklch(100% 0 0/.95)', 'glass-border' => 'oklch(100% 0 0/1)',
                    'blob-a' => 'oklch(80% .16 50/.26)', 'blob-b' => 'oklch(84% .14 75/.24)', 'blob-c' => 'oklch(82% .13 20/.2)',
                    'shadow' => '0 16px 40px -16px oklch(45% .06 30/.3)', 'shadow-sm' => '0 6px 18px -8px oklch(45% .06 30/.22)',
                ],
                'dark' => [
                    'bg' => 'oklch(18% .02 25)', 'bg-soft' => 'oklch(22% .025 25)',
                    'surface' => 'oklch(26% .028 25/.9)', 'surface-strong' => 'oklch(29% .03 25/1)',
                    'fg' => 'oklch(94% .01 60)', 'muted' => 'oklch(74% .015 55)', 'faint' => 'oklch(58% .015 50)',
                    'border' => 'oklch(100% 0 0/.12)', 'border-strong' => 'oklch(100% 0 0/.24)',
                    'hover' => 'oklch(94% .01 60/.07)', 'hover-strong' => 'oklch(94% .01 60/.13)',
                    'accent' => 'oklch(70% .21 35)', 'accent-strong' => 'oklch(76% .2 35)', 'accent-soft' => 'oklch(70% .21 35/.16)', 'on-accent' => 'oklch(20% .03 25)',
                    'glass' => 'oklch(26% .028 25/.75)', 'glass-bright' => 'oklch(29% .03 25/.95)', 'glass-border' => 'oklch(100% 0 0/.14)',
                    'blob-a' => 'oklch(60% .18 50/.2)', 'blob-b' => 'oklch(64% .16 75/.18)', 'blob-c' => 'oklch(58% .16 20/.18)',
                    'shadow' => '0 16px 40px -16px oklch(0% 0 0/.55)', 'shadow-sm' => '0 6px 18px -8px oklch(0% 0 0/.45)',
                ],
                'layout' => [
                    'r-lg' => '12px', 'r-md' => '10px', 'r-sm' => '8px',
                    'font-display' => '"PingFang SC","HarmonyOS Sans SC","MiSans",sans-serif',
                    'font-body' => '"PingFang SC","HarmonyOS Sans SC","MiSans",system-ui,sans-serif',
                    'font-mono' => 'ui-monospace,"SF Mono",Menlo,monospace',
                    'glass-strength' => 'none', 'density' => 'dense', 'motion' => 'snappy',
                ],
            ],
            'editorial' => [
                'name' => '报纸极简（传媒）',
                'desc' => '黑白灰 + 克制品牌色 · 宋体标题 · 内容至上',
                'light' => [
                    'bg' => 'oklch(98% .002 75)', 'bg-soft' => 'oklch(96% .002 75)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(18% .005 75)', 'muted' => 'oklch(42% .008 75)', 'faint' => 'oklch(55% .008 75)',
                    'border' => 'oklch(90% .004 75)', 'border-strong' => 'oklch(80% .005 75)',
                    'hover' => 'oklch(20% .005 75/.04)', 'hover-strong' => 'oklch(20% .005 75/.08)',
                    'accent' => 'oklch(45% .16 25)', 'accent-strong' => 'oklch(40% .16 25)', 'accent-soft' => 'oklch(45% .16 25/.1)', 'on-accent' => 'oklch(100% 0 0)',
                    'glass' => 'oklch(100% 0 0/.8)', 'glass-bright' => 'oklch(100% 0 0/.95)', 'glass-border' => 'oklch(100% 0 0/1)',
                    'blob-a' => 'oklch(70% .1 25/.1)', 'blob-b' => 'oklch(70% .08 200/.08)', 'blob-c' => 'oklch(72% .07 60/.08)',
                    'shadow' => '0 1px 2px oklch(25% .01 75/.08)', 'shadow-sm' => '0 1px 1px oklch(25% .01 75/.05)',
                ],
                'dark' => [
                    'bg' => 'oklch(15% .003 75)', 'bg-soft' => 'oklch(18% .004 75)',
                    'surface' => 'oklch(21% .005 75/1)', 'surface-strong' => 'oklch(24% .006 75/1)',
                    'fg' => 'oklch(94% .004 75)', 'muted' => 'oklch(70% .006 75)', 'faint' => 'oklch(54% .006 75)',
                    'border' => 'oklch(100% 0 0/.1)', 'border-strong' => 'oklch(100% 0 0/.18)',
                    'hover' => 'oklch(94% .004 75/.05)', 'hover-strong' => 'oklch(94% .004 75/.1)',
                    'accent' => 'oklch(68% .15 25)', 'accent-strong' => 'oklch(72% .14 25)', 'accent-soft' => 'oklch(68% .15 25/.14)', 'on-accent' => 'oklch(15% .005 75)',
                    'glass' => 'oklch(21% .005 75/1)', 'glass-bright' => 'oklch(24% .006 75/1)', 'glass-border' => 'oklch(100% 0 0/.1)',
                    'blob-a' => 'oklch(55% .12 25/.1)', 'blob-b' => 'oklch(55% .1 200/.08)', 'blob-c' => 'oklch(56% .09 60/.08)',
                    'shadow' => '0 2px 6px oklch(0% 0 0/.45)', 'shadow-sm' => '0 1px 3px oklch(0% 0 0/.35)',
                ],
                'layout' => [
                    'r-lg' => '8px', 'r-md' => '6px', 'r-sm' => '4px',
                    'font-display' => '"Noto Serif SC","Songti SC",SimSun,serif',
                    'font-body' => '"PingFang SC","Noto Sans SC","MiSans",system-ui,sans-serif',
                    'font-mono' => 'ui-monospace,"SF Mono",Menlo,monospace',
                    'glass-strength' => 'none', 'density' => 'airy', 'motion' => 'minimal',
                ],
            ],
            'neon' => [
                'name' => '霓虹暗夜（游戏）',
                'desc' => '深蓝黑底 + 荧光蓝紫 · 全屏视觉 · 沉浸',
                'light' => [
                    'bg' => 'oklch(16% .02 260)', 'bg-soft' => 'oklch(20% .025 265)',
                    'surface' => 'oklch(23% .03 265/.9)', 'surface-strong' => 'oklch(27% .035 265/1)',
                    'fg' => 'oklch(94% .01 260)', 'muted' => 'oklch(72% .02 260)', 'faint' => 'oklch(58% .015 260)',
                    'border' => 'oklch(100% 0 0/.12)', 'border-strong' => 'oklch(100% 0 0/.22)',
                    'hover' => 'oklch(94% .01 260/.07)', 'hover-strong' => 'oklch(94% .01 260/.13)',
                    'accent' => 'oklch(70% .2 290)', 'accent-strong' => 'oklch(76% .19 290)', 'accent-soft' => 'oklch(70% .2 290/.18)', 'on-accent' => 'oklch(14% .02 260)',
                    'glass' => 'oklch(23% .03 265/.7)', 'glass-bright' => 'oklch(27% .035 265/.9)', 'glass-border' => 'oklch(100% 0 0/.16)',
                    'blob-a' => 'oklch(62% .22 300/.3)', 'blob-b' => 'oklch(58% .2 250/.28)', 'blob-c' => 'oklch(65% .2 200/.24)',
                    'shadow' => '0 20px 50px -16px oklch(0% 0 0/.7)', 'shadow-sm' => '0 8px 24px -10px oklch(0% 0 0/.6)',
                ],
                'dark' => [
                    'bg' => 'oklch(13% .02 260)', 'bg-soft' => 'oklch(17% .025 265)',
                    'surface' => 'oklch(20% .03 265/.9)', 'surface-strong' => 'oklch(24% .035 265/1)',
                    'fg' => 'oklch(95% .01 260)', 'muted' => 'oklch(74% .02 260)', 'faint' => 'oklch(60% .015 260)',
                    'border' => 'oklch(100% 0 0/.12)', 'border-strong' => 'oklch(100% 0 0/.22)',
                    'hover' => 'oklch(95% .01 260/.06)', 'hover-strong' => 'oklch(95% .01 260/.12)',
                    'accent' => 'oklch(72% .22 295)', 'accent-strong' => 'oklch(78% .21 295)', 'accent-soft' => 'oklch(72% .22 295/.2)', 'on-accent' => 'oklch(12% .02 260)',
                    'glass' => 'oklch(20% .03 265/.75)', 'glass-bright' => 'oklch(24% .035 265/.95)', 'glass-border' => 'oklch(100% 0 0/.16)',
                    'blob-a' => 'oklch(64% .23 300/.26)', 'blob-b' => 'oklch(60% .21 250/.24)', 'blob-c' => 'oklch(67% .2 200/.2)',
                    'shadow' => '0 20px 50px -16px oklch(0% 0 0/.75)', 'shadow-sm' => '0 8px 24px -10px oklch(0% 0 0/.65)',
                ],
                'layout' => [
                    'r-lg' => '16px', 'r-md' => '12px', 'r-sm' => '8px',
                    'font-display' => '"Rajdhani","Space Grotesk","PingFang SC",sans-serif',
                    'font-body' => '"PingFang SC","HarmonyOS Sans SC","MiSans",system-ui,sans-serif',
                    'font-mono' => '"JetBrains Mono",ui-monospace,monospace',
                    'glass-strength' => 'glass', 'density' => 'airy', 'motion' => 'epic',
                ],
            ],
            'industrial' => [
                'name' => '重工沉稳（实体产业）',
                'desc' => '深灰藏蓝 + 工程黄 · 直角硬线条 · 实力感',
                'light' => [
                    'bg' => 'oklch(97% .006 240)', 'bg-soft' => 'oklch(94% .008 240)',
                    'surface' => 'oklch(100% 0 0/1)', 'surface-strong' => 'oklch(100% 0 0/1)',
                    'fg' => 'oklch(22% .012 240)', 'muted' => 'oklch(45% .014 240)', 'faint' => 'oklch(58% .012 240)',
                    'border' => 'oklch(88% .008 240)', 'border-strong' => 'oklch(78% .012 240)',
                    'hover' => 'oklch(22% .012 240/.04)', 'hover-strong' => 'oklch(22% .012 240/.08)',
                    'accent' => 'oklch(52% .16 90)', 'accent-strong' => 'oklch(47% .16 90)', 'accent-soft' => 'oklch(52% .16 90/.12)', 'on-accent' => 'oklch(15% .02 240)',
                    'glass' => 'oklch(100% 0 0/.85)', 'glass-bright' => 'oklch(100% 0 0/.97)', 'glass-border' => 'oklch(100% 0 0/1)',
                    'blob-a' => 'oklch(70% .12 90/.16)', 'blob-b' => 'oklch(70% .1 230/.12)', 'blob-c' => 'oklch(72% .09 200/.12)',
                    'shadow' => '0 4px 12px oklch(30% .02 240/.12)', 'shadow-sm' => '0 2px 6px oklch(30% .02 240/.08)',
                ],
                'dark' => [
                    'bg' => 'oklch(15% .012 240)', 'bg-soft' => 'oklch(18% .014 240)',
                    'surface' => 'oklch(22% .016 240/1)', 'surface-strong' => 'oklch(25% .018 240/1)',
                    'fg' => 'oklch(93% .008 240)', 'muted' => 'oklch(72% .012 240)', 'faint' => 'oklch(56% .012 240)',
                    'border' => 'oklch(100% 0 0/.1)', 'border-strong' => 'oklch(100% 0 0/.18)',
                    'hover' => 'oklch(93% .008 240/.05)', 'hover-strong' => 'oklch(93% .008 240/.1)',
                    'accent' => 'oklch(68% .17 90)', 'accent-strong' => 'oklch(72% .16 90)', 'accent-soft' => 'oklch(68% .17 90/.15)', 'on-accent' => 'oklch(15% .02 240)',
                    'glass' => 'oklch(22% .016 240/1)', 'glass-bright' => 'oklch(25% .018 240/1)', 'glass-border' => 'oklch(100% 0 0/.1)',
                    'blob-a' => 'oklch(60% .14 90/.14)', 'blob-b' => 'oklch(60% .12 230/.1)', 'blob-c' => 'oklch(62% .11 200/.1)',
                    'shadow' => '0 4px 12px oklch(0% 0 0/.5)', 'shadow-sm' => '0 2px 6px oklch(0% 0 0/.4)',
                ],
                'layout' => [
                    'r-lg' => '6px', 'r-md' => '4px', 'r-sm' => '2px',
                    'font-display' => '"Noto Sans SC","PingFang SC","HarmonyOS Sans SC",sans-serif',
                    'font-body' => '"Noto Sans SC","PingFang SC","HarmonyOS Sans SC",system-ui,sans-serif',
                    'font-mono' => 'ui-monospace,"SF Mono",Menlo,monospace',
                    'glass-strength' => 'none', 'density' => 'balanced', 'motion' => 'minimal',
                ],
            ],
        ];
    }

    /* ─── 读取 / 管理 ─── */

    /** 获取全部主题（预设 + 用户自定义覆盖） */
    public static function all(): array {
        $presets = self::presets();
        $saved = json_read(self::$file);
        // 合并：预设为基础，保存的用户自定义主题覆盖/追加
        $custom = $saved['custom'] ?? [];
        foreach ($custom as $id => $t) {
            $presets[$id] = $t;
        }
        return $presets;
    }

    /** 获取单个主题 */
    public static function get(string $id): ?array {
        $all = self::all();
        return $all[$id] ?? null;
    }

    /** 当前激活主题 id */
    public static function activeId(): string {
        $s = json_read(self::$file);
        $active = $s['active'] ?? 'default';
        // 校验存在
        return self::get($active) ? $active : 'default';
    }

    /** 激活主题 */
    public static function activate(string $id): bool {
        if (!self::get($id)) return false;
        $s = json_read(self::$file);
        $s['active'] = $id;
        json_write(self::$file, $s);
        return true;
    }

    /** 保存自定义主题（覆盖预设同名或新建） */
    public static function saveCustom(string $id, array $theme): bool {
        $s = json_read(self::$file);
        $s['custom'][$id] = $theme;
        json_write(self::$file, $s);
        return true;
    }

    /** 删除自定义主题（预设不可删） */
    public static function deleteCustom(string $id): bool {
        $s = json_read(self::$file);
        if (isset($s['custom'][$id])) {
            unset($s['custom'][$id]);
            if (($s['active'] ?? 'default') === $id) $s['active'] = 'default';
            json_write(self::$file, $s);
            return true;
        }
        return false;
    }

    /** 判断是否为内置预设（不可删） */
    public static function isPreset(string $id): bool {
        return isset(self::presets()[$id]);
    }

    /* ─── 生成 CSS ─── */

    /** 生成主题的完整 CSS 变量（:root + dark + 布局） */
    public static function cssFor(string $id): string {
        $theme = self::get($id);
        if (!$theme) $theme = self::get('default');
        $light = $theme['light'] ?? [];
        $dark = $theme['dark'] ?? [];
        $layout = $theme['layout'] ?? [];

        $root = [];
        foreach ($light as $k => $v) $root[] = "--$k:$v";
        // 布局变量（圆角/字体）
        foreach ($layout as $k => $v) $root[] = "--$k:$v";
        $root[] = 'color-scheme:light';

        $darkVars = [];
        foreach ($dark as $k => $v) $darkVars[] = "--$k:$v";
        $darkVars[] = 'color-scheme:dark';

        $css = ':root{' . implode(';', $root) . '}' . "\n";
        $css .= 'html[data-theme="dark"]{' . implode(';', $darkVars) . '}' . "\n";

        // 主题特有交互差异（玻璃拟态强度）
        $glass = $layout['glass-strength'] ?? 'medium';
        if ($glass === 'none') {
            $css .= 'html[data-brand]{--glass:var(--surface-strong);--glass-bright:var(--surface-strong);--glass-border:var(--border)}' . "\n";
        }

        return $css;
    }
}
