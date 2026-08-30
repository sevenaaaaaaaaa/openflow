<?php
/**
 * BlockModel —— 正文块模型 + 可复用区块（AUDIT-01 / BACKLOG T2-1）
 *
 * 【为什么】正文现在是一坨 HTML：想"把这篇的要点重排成短视频脚本/邮件/社媒"
 * 就得整篇重写；想复用一段固定的 CTA/免责声明，只能到处复制粘贴。
 * 块模型把正文变成**结构化的块序列**，于是能：一写多编译(repurpose)、
 * 跨内容复用区块、按块做个性化。
 *
 * 【设计】不强制迁移：HTML ⇄ 块 双向可转，老内容照常工作。
 *   块类型：heading/paragraph/list/quote/code/image/embed/cta/snippet
 *   snippet 块引用「可复用区块」(data/content-snippets.json)，渲染时展开——
 *   改一处，所有引用它的内容同步更新。
 */

if (!function_exists('blockmodel_types')) {

    function blockmodel_types(): array {
        return ['heading'=>'标题','paragraph'=>'段落','list'=>'列表','quote'=>'引用',
                'code'=>'代码','image'=>'图片','embed'=>'嵌入','cta'=>'行动号召','snippet'=>'复用区块'];
    }

    /**
     * HTML → 块序列（保守解析：认得的成结构块，认不得的整段保留为 html 块）。
     */
    function blockmodel_from_html(string $html): array {
        $blocks = [];
        $html = trim($html);
        if ($html === '') return $blocks;
        // 按顶层标签切分
        $pattern = '/<(h[1-6]|p|ul|ol|blockquote|pre|figure|img)\b[^>]*>.*?<\/\1>|<img\b[^>]*\/?>/is';
        $offset = 0;
        if (preg_match_all($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $hit) {
                [$frag, $pos] = $hit;
                if ($pos > $offset) {
                    $between = trim(substr($html, $offset, $pos - $offset));
                    if ($between !== '') $blocks[] = ['type'=>'html','html'=>$between];
                }
                $tag = strtolower($m[1][$i][0] ?? '');
                $inner = preg_replace('/^<[^>]+>|<\/[^>]+>$/', '', $frag);
                if (preg_match('/^h([1-6])$/', $tag, $hm)) {
                    $blocks[] = ['type'=>'heading','level'=>(int)$hm[1],'text'=>trim(strip_tags($inner))];
                } elseif ($tag === 'p') {
                    $blocks[] = ['type'=>'paragraph','html'=>trim($inner)];
                } elseif ($tag === 'ul' || $tag === 'ol') {
                    preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $inner, $lm);
                    $blocks[] = ['type'=>'list','ordered'=>$tag==='ol','items'=>array_map(fn($x)=>trim(strip_tags($x)), $lm[1] ?? [])];
                } elseif ($tag === 'blockquote') {
                    $blocks[] = ['type'=>'quote','text'=>trim(strip_tags($inner))];
                } elseif ($tag === 'pre') {
                    $blocks[] = ['type'=>'code','code'=>html_entity_decode(trim(strip_tags($inner)))];
                } else {
                    $blocks[] = ['type'=>'html','html'=>$frag];
                }
                $offset = $pos + strlen($frag);
            }
        }
        $tail = trim(substr($html, $offset));
        if ($tail !== '') $blocks[] = ['type'=>'html','html'=>$tail];
        if (!$blocks) $blocks[] = ['type'=>'html','html'=>$html];
        return $blocks;
    }

    /** 块序列 → HTML（渲染；snippet 块展开）。 */
    function blockmodel_to_html(array $blocks, ?array $snippets = null): string {
        $out = '';
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            switch ($b['type'] ?? 'html') {
                case 'heading':
                    $lv = max(1, min(6, (int)($b['level'] ?? 2)));
                    $out .= "<h{$lv}>" . htmlspecialchars((string)($b['text'] ?? '')) . "</h{$lv}>"; break;
                case 'paragraph': $out .= '<p>' . (string)($b['html'] ?? '') . '</p>'; break;
                case 'list':
                    $tag = !empty($b['ordered']) ? 'ol' : 'ul';
                    $out .= "<{$tag}>";
                    foreach ((array)($b['items'] ?? []) as $it) $out .= '<li>' . htmlspecialchars((string)$it) . '</li>';
                    $out .= "</{$tag}>"; break;
                case 'quote': $out .= '<blockquote>' . htmlspecialchars((string)($b['text'] ?? '')) . '</blockquote>'; break;
                case 'code': $out .= '<pre><code>' . htmlspecialchars((string)($b['code'] ?? '')) . '</code></pre>'; break;
                case 'image':
                    $out .= '<img src="' . htmlspecialchars((string)($b['src'] ?? '')) . '" alt="' . htmlspecialchars((string)($b['alt'] ?? '')) . '" loading="lazy">'; break;
                case 'cta':
                    $out .= '<div class="of-cta"><a href="' . htmlspecialchars((string)($b['url'] ?? '#')) . '">'
                          . htmlspecialchars((string)($b['text'] ?? '了解更多')) . '</a></div>'; break;
                case 'snippet':
                    $sn = blockmodel_snippet((string)($b['ref'] ?? ''), $snippets);
                    $out .= $sn ? blockmodel_to_html((array)($sn['blocks'] ?? []), $snippets) : ''; break;
                default: $out .= (string)($b['html'] ?? '');
            }
        }
        return $out;
    }

    /* ─────────── 可复用区块 ─────────── */

    function blockmodel_snippets_file(): string { return DATA_DIR . '/content-snippets.json'; }
    function blockmodel_snippets(): array {
        $d = function_exists('json_read') ? json_read(blockmodel_snippets_file()) : [];
        return is_array($d) ? $d : [];
    }
    function blockmodel_snippet(string $id, ?array $all = null): ?array {
        foreach (($all ?? blockmodel_snippets()) as $s) if (($s['id'] ?? '') === $id) return $s;
        return null;
    }
    function blockmodel_snippet_save(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return ['ok'=>false,'error'=>'名称不能为空'];
        $list = blockmodel_snippets();
        $id = (string)($data['id'] ?? '') ?: ('snip_' . substr(bin2hex(random_bytes(4)), 0, 6));
        $row = ['id'=>$id,'name'=>$name,'blocks'=>array_values((array)($data['blocks'] ?? [])),'updated_at'=>date('Y-m-d H:i:s')];
        $hit=false;
        foreach ($list as &$s) if (($s['id'] ?? '')===$id) { $s=$row; $hit=true; break; }
        unset($s);
        if (!$hit) $list[] = $row;
        if (function_exists('json_write')) json_write(blockmodel_snippets_file(), $list);
        return ['ok'=>true,'snippet'=>$row];
    }

    /**
     * 一写多编译（repurpose）：把块序列压成另一种载体的草稿。
     * $target: outline(提纲) / social(社媒) / email(邮件) / script(口播脚本)
     */
    function blockmodel_repurpose(array $blocks, string $target = 'outline', ?array $snippets = null): string {
        // 先展开 snippet，保证复用内容也被纳入
        $flat = [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'snippet') {
                $sn = blockmodel_snippet((string)($b['ref'] ?? ''), $snippets);
                foreach ((array)($sn['blocks'] ?? []) as $sb) $flat[] = $sb;
            } else { $flat[] = $b; }
        }
        $heads = []; $paras = []; $items = [];
        foreach ($flat as $b) {
            switch ($b['type'] ?? '') {
                case 'heading': $heads[] = (string)$b['text']; break;
                case 'paragraph': $t = trim(strip_tags((string)($b['html'] ?? ''))); if ($t !== '') $paras[] = $t; break;
                case 'list': foreach ((array)($b['items'] ?? []) as $i) $items[] = (string)$i; break;
                case 'quote': $paras[] = (string)($b['text'] ?? ''); break;
            }
        }
        switch ($target) {
            case 'social':
                $hook = $paras[0] ?? ($heads[0] ?? '');
                $pts = array_slice(array_merge($items, $heads), 0, 3);
                return trim(mb_substr($hook, 0, 80)) . "\n\n" . implode("\n", array_map(fn($p) => '· ' . $p, $pts));
            case 'email':
                return "主题：" . ($heads[0] ?? '给你的一点想法') . "\n\n"
                     . implode("\n\n", array_slice($paras, 0, 3)) . "\n\n—— 完整版见原文。";
            case 'script':
                $lines = [];
                foreach (array_slice($heads, 0, 5) as $i => $h) $lines[] = ($i+1) . '. ' . $h;
                return "口播脚本提纲：\n" . implode("\n", $lines ?: ['（正文没有小标题，先补结构）']);
            case 'outline':
            default:
                $lines = [];
                foreach ($heads as $h) $lines[] = '- ' . $h;
                foreach (array_slice($items, 0, 8) as $i) $lines[] = '  · ' . $i;
                return implode("\n", $lines ?: array_map(fn($p)=>'- '.mb_substr($p,0,40), array_slice($paras,0,5)));
        }
    }
}
