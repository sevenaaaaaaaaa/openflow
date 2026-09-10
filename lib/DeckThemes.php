<?php
/**
 * DeckThemes — 幻灯片主题系统（F1）
 *
 * 预设风格包（每套 = CSS 变量包 + 字体搭配 + 版式气质）+ design.md 品牌契约解析。
 * design.md 是「品牌书即代码」：Markdown 里写 主色/强调色/字体/Logo/语气，
 * 解析后一半注入 AI 生成提示词（语气/禁忌），一半编译成 CSS 变量覆盖（颜色/字体）。
 */
class DeckThemes {

    /** 五套预设风格 */
    public static function all(): array {
        return [
            'stage' => [
                'name' => '暗夜舞台', 'desc' => '发布会 keynote 风，深色大标题',
                'vars' => [
                    '--stage-bg' => 'oklch(0.14 0.02 260)', '--stage-fg' => 'oklch(0.96 0.01 260)',
                    '--stage-accent' => 'oklch(0.72 0.16 250)', '--stage-soft' => 'rgba(255,255,255,.08)',
                    '--stage-heading-font' => 'var(--font-sans,system-ui)', '--stage-body-font' => 'var(--font-sans,system-ui)',
                    '--stage-heading-weight' => '800', '--stage-radius' => '18px',
                ],
            ],
            'magazine' => [
                'name' => '杂志编辑', 'desc' => '纸感衬线，编辑部排版',
                'vars' => [
                    '--stage-bg' => 'oklch(0.97 0.01 90)', '--stage-fg' => 'oklch(0.20 0.02 60)',
                    '--stage-accent' => 'oklch(0.52 0.19 25)', '--stage-soft' => 'rgba(0,0,0,.06)',
                    '--stage-heading-font' => '"Noto Serif SC","Songti SC",serif', '--stage-body-font' => '"Noto Serif SC","Songti SC",serif',
                    '--stage-heading-weight' => '700', '--stage-radius' => '4px',
                ],
            ],
            'minimal' => [
                'name' => '极简白', 'desc' => '大量留白，单点强调色',
                'vars' => [
                    '--stage-bg' => 'oklch(0.99 0 0)', '--stage-fg' => 'oklch(0.16 0.01 260)',
                    '--stage-accent' => 'oklch(0.55 0.20 250)', '--stage-soft' => 'rgba(0,0,0,.05)',
                    '--stage-heading-font' => 'var(--font-sans,system-ui)', '--stage-body-font' => 'var(--font-sans,system-ui)',
                    '--stage-heading-weight' => '700', '--stage-radius' => '12px',
                ],
            ],
            'brand' => [
                'name' => '品牌主色', 'desc' => '整版品牌色浸润，高识别度',
                'vars' => [
                    '--stage-bg' => 'oklch(0.30 0.09 250)', '--stage-fg' => 'oklch(0.98 0.01 250)',
                    '--stage-accent' => 'oklch(0.85 0.15 95)', '--stage-soft' => 'rgba(255,255,255,.10)',
                    '--stage-heading-font' => 'var(--font-sans,system-ui)', '--stage-body-font' => 'var(--font-sans,system-ui)',
                    '--stage-heading-weight' => '800', '--stage-radius' => '20px',
                ],
            ],
            'cyber' => [
                'name' => '赛博霓虹', 'desc' => '黑底荧光，等宽字体科技感',
                'vars' => [
                    '--stage-bg' => 'oklch(0.12 0.03 300)', '--stage-fg' => 'oklch(0.92 0.05 160)',
                    '--stage-accent' => 'oklch(0.85 0.25 160)', '--stage-soft' => 'rgba(140,255,200,.08)',
                    '--stage-heading-font' => 'var(--font-mono,monospace)', '--stage-body-font' => 'var(--font-sans,system-ui)',
                    '--stage-heading-weight' => '700', '--stage-radius' => '2px',
                ],
            ],
        ];
    }

    public static function get(string $id): array {
        $all = self::all();
        return $all[$id] ?? $all['stage'];
    }

    /* ═══ design.md 品牌契约 ═══ */

    public static function brandFile(): string { return DATA_DIR . '/brand.md'; }

    public static function globalBrand(): string {
        return is_file(self::brandFile()) ? trim((string)file_get_contents(self::brandFile())) : '';
    }

    /**
     * 解析 design.md：键值行（主色:#hex / 强调色: / 标题字体: / 正文字体: / Logo: / 语气: / 禁忌:）
     * + 其余自由文本原样保留（注入提示词）。
     */
    public static function parseBrand(string $md): array {
        $out = ['vars' => [], 'tone' => '', 'avoid' => '', 'logo' => '', 'free' => []];
        $map = [
            '主色' => '--stage-bg', '背景色' => '--stage-bg',
            '文字色' => '--stage-fg', '前景色' => '--stage-fg',
            '强调色' => '--stage-accent', '品牌色' => '--stage-accent', '主题色' => '--stage-accent',
        ];
        foreach (preg_split('/\R/', $md) as $line) {
            $line = trim($line, " \t-*#>");
            if ($line === '') continue;
            if (preg_match('/^(主色|背景色|文字色|前景色|强调色|品牌色|主题色|标题字体|正文字体|Logo|logo|语气|禁忌)\s*[:：]\s*(.+)$/u', $line, $m)) {
                $k = $m[1]; $v = trim($m[2]);
                if (isset($map[$k])) $out['vars'][$map[$k]] = $v;
                elseif ($k === '标题字体') $out['vars']['--stage-heading-font'] = $v;
                elseif ($k === '正文字体') $out['vars']['--stage-body-font'] = $v;
                elseif ($k === 'Logo' || $k === 'logo') $out['logo'] = $v;
                elseif ($k === '语气') $out['tone'] = $v;
                elseif ($k === '禁忌') $out['avoid'] = $v;
            } else {
                $out['free'][] = $line;
            }
        }
        return $out;
    }

    /** 给 AI 提示词用的品牌上下文 */
    public static function brandPrompt(string $md): string {
        if (trim($md) === '') return '';
        $b = self::parseBrand($md);
        $parts = [];
        if ($b['tone'] !== '') $parts[] = '品牌语气：' . $b['tone'];
        if ($b['avoid'] !== '') $parts[] = '禁忌（不要出现）：' . $b['avoid'];
        if ($b['free']) $parts[] = '品牌背景：' . mb_substr(implode('；', $b['free']), 0, 300);
        return $parts ? "\n" . implode("\n", $parts) : '';
    }

    /** 合并最终 CSS 变量：主题包 ← design.md 覆盖 */
    public static function cssVars(string $themeId, string $brandMd = ''): string {
        $vars = self::get($themeId)['vars'];
        if (trim($brandMd) !== '') {
            $b = self::parseBrand($brandMd);
            $vars = array_merge($vars, $b['vars']);
        }
        $css = '';
        foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
        return $css;
    }

    public static function brandLogo(string $brandMd): string {
        return self::parseBrand($brandMd)['logo'];
    }
}
