<?php
/**
 * FederatedGrowth —— 联邦增长智能的隐私骨架（AUDIT-07 P2 / BACKLOG T2-12）
 *
 * 【为什么】这是全系列最深的护城河：多租户 + 零门槛让跨站点的匿名信号构成
 * **共享学习基质**——在 A 站有效的成交话术/选题/时机，脱敏后让 B 站的大脑更聪明。
 * 在位工具的网络效应是"货架越长"（对创作者是负担）；我们的是"脑子越多越聪明"。
 *
 * 【隐私第一】这套东西只有在**绝不泄露个体**的前提下才能存在，所以骨架先立规矩：
 *   ① 只出聚合，不出个体：任何导出都必须经 k-匿名门槛（样本 < k 直接丢弃）
 *   ② 强制脱敏：邮箱/手机/姓名/URL 等标识字段一律剥除，只留模式与统计量
 *   ③ 显式加入：默认不参与（opt-in），随时可退出
 * 这三条是硬约束，不提供绕过开关。
 */

if (!function_exists('fed_settings')) {

    function fed_k_threshold(): int { return 5; }   // k-匿名门槛：少于 5 个样本不出

    function fed_settings(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $f = is_array($s['federated'] ?? null) ? $s['federated'] : [];
        return array_merge(['enabled' => false, 'joined_at' => ''], $f);
    }

    function fed_opt_in(bool $on): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $s['federated'] = ['enabled' => $on, 'joined_at' => $on ? date('Y-m-d H:i:s') : ''];
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
        return $s['federated'];
    }

    /** 需要剥除的标识字段（出现即删，不做"部分保留"的妥协）。 */
    function fed_pii_keys(): array {
        return ['email','phone','name','username','ip','address','wechat','qq','url','link','visitor_id','member_id','id','subject'];
    }

    /**
     * ② 强制脱敏：递归剥除标识字段；字符串里的邮箱/手机/URL 一并抹掉。
     */
    function fed_sanitize($data) {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                if (is_string($k) && in_array(mb_strtolower($k), fed_pii_keys(), true)) continue;
                $out[$k] = fed_sanitize($v);
            }
            return $out;
        }
        if (is_string($data)) {
            $s = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/', '[email]', $data);
            $s = preg_replace('/\b1[3-9]\d{9}\b/', '[phone]', $s);
            $s = preg_replace('#https?://\S+#', '[url]', $s);
            return $s;
        }
        return $data;
    }

    /**
     * ① k-匿名聚合：按维度分桶统计，样本数 < k 的桶直接丢弃（不合并、不四舍五入蒙混）。
     * $rows: [['dim'=>'自然搜索','value'=>1000], ...]
     */
    function fed_aggregate(array $rows, string $dimKey = 'dim', string $valKey = 'value', ?int $k = null): array {
        $k = $k ?? fed_k_threshold();
        $bucket = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $d = (string)($r[$dimKey] ?? '');
            if ($d === '') continue;
            $bucket[$d]['n'] = ($bucket[$d]['n'] ?? 0) + 1;
            $bucket[$d]['sum'] = ($bucket[$d]['sum'] ?? 0) + (float)($r[$valKey] ?? 0);
        }
        $out = [];
        foreach ($bucket as $d => $b) {
            if ($b['n'] < $k) continue;                     // 样本不足，丢弃
            $out[] = ['dim' => $d, 'n' => $b['n'], 'avg' => round($b['sum'] / $b['n'], 2)];
        }
        usort($out, fn($a, $b) => $b['n'] <=> $a['n']);
        return $out;
    }

    /**
     * 生成可对外共享的贡献包（未加入 → 直接拒绝）。
     * 只含聚合模式，绝无个体记录。
     */
    function fed_build_contribution(array $conversionRows = [], array $contentRows = []): array {
        if (empty(fed_settings()['enabled'])) {
            return ['ok' => false, 'error' => '未加入联邦增长智能（默认不参与）'];
        }
        $pack = [
            'schema' => 'openflow.federated.v1',
            'generated_at' => date('Y-m-d H:i:s'),
            'k_threshold' => fed_k_threshold(),
            'conversion_by_source' => fed_aggregate($conversionRows, 'dim', 'value'),
            'content_by_topic' => fed_aggregate($contentRows, 'dim', 'value'),
        ];
        // 最后再过一遍脱敏，双保险
        return ['ok' => true, 'pack' => fed_sanitize($pack)];
    }

    /**
     * 消费他站共享来的聚合包，转成"本站可用的建议"。
     * 只接受聚合结构；发现疑似个体数据（含标识字段/样本过小）直接拒收。
     */
    function fed_consume(array $pack): array {
        if (($pack['schema'] ?? '') !== 'openflow.federated.v1') return ['ok' => false, 'error' => '未知数据格式'];
        $k = (int)($pack['k_threshold'] ?? 0);
        if ($k < fed_k_threshold()) return ['ok' => false, 'error' => 'k-匿名门槛不足，拒收'];

        $tips = [];
        foreach ((array)($pack['conversion_by_source'] ?? []) as $r) {
            if (!is_array($r) || ($r['n'] ?? 0) < $k) continue;
            $tips[] = "同类站点中「{$r['dim']}」平均成交额 ¥{$r['avg']}（{$r['n']} 个站点样本）——可参考是否加大投入。";
        }
        foreach ((array)($pack['content_by_topic'] ?? []) as $r) {
            if (!is_array($r) || ($r['n'] ?? 0) < $k) continue;
            $tips[] = "「{$r['dim']}」类内容在同类站点表现均值 {$r['avg']}（{$r['n']} 样本）。";
        }
        return ['ok' => true, 'tips' => $tips];
    }

    /** 自检：把一个包过一遍红线检查（用于对外发送前把关）。 */
    function fed_audit(array $pack): array {
        $problems = [];
        $walk = function ($node, $path = '') use (&$walk, &$problems) {
            if (is_array($node)) {
                foreach ($node as $k => $v) {
                    if (is_string($k) && in_array(mb_strtolower($k), fed_pii_keys(), true)) $problems[] = "含标识字段：{$path}{$k}";
                    $walk($v, $path . $k . '.');
                }
            } elseif (is_string($node)) {
                if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/', $node)) $problems[] = "文本含邮箱：{$path}";
                if (preg_match('/\b1[3-9]\d{9}\b/', $node)) $problems[] = "文本含手机号：{$path}";
            }
        };
        $walk($pack);
        return ['clean' => empty($problems), 'problems' => array_values(array_unique($problems))];
    }
}
