<?php
/**
 * CDP AI 洞察
 * 汇总 CDP 关键指标 → 交给 AI 生成自然语言运营洞察 / 异常发现 / 行动建议
 * 无 AI 配置时回退为规则化洞察（基于统计阈值）
 *
 * ── CDP 三层架构：第 2 层「洞察」 ──
 * 本文件只做「把指标翻译成人话」：读 CdpSystem 的指标，交给 AiCenter 生成洞察。
 * 依赖：CdpSystem（读指标）+ AiCenter（调 AI）。
 * 加代码指引：洞察规则、异常检测阈值、行动建议文案加这里，
 *            不要在这里写画像计算（归 CdpSystem）或落库（归 CdpSync）。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/CdpSystem.php';
require_once __DIR__ . '/AiCenter.php';

class CdpInsight {
    /**
     * 汇总关键指标（喂给 AI 的数据快照）
     */
    public static function snapshot(int $days = 30): array {
        $snap = [];
        try {
            $snap['growth'] = CdpSystem::getUserGrowth('day', min(30, $days));
            $snap['sources'] = CdpSystem::getSourceDistribution();
            $snap['devices'] = CdpSystem::getDeviceDistribution();
            $snap['top_events'] = CdpSystem::getTopEvents(8);
            $snap['lifecycle'] = CdpSystem::getLifecycleDistribution();
            $snap['retention'] = CdpSystem::getRetention($days);
            $snap['rfm'] = CdpSystem::getRFMDistribution();
            $snap['top_pages'] = CdpSystem::getPageViews(8);
            $snap['tags'] = CdpSystem::getTagDistribution();
            $snap['hourly'] = CdpSystem::getHourlyHeatmap(7);
            $snap['channel'] = CdpSystem::getChannelAttribution();
            // 新增维度数据（F1/F2）
            $snap['channel_dist'] = CdpSystem::getChannelDistribution(10);
            $snap['device_dist'] = CdpSystem::getDimensionDistribution('device', 6);
            $snap['browser_dist'] = CdpSystem::getBrowserDistribution(6);
            $snap['os_dist'] = CdpSystem::getOsDistribution(6);
            $snap['revenue_channel'] = CdpSystem::getRevenueAttribution('channel', $days);
            $snap['conversion_channel'] = CdpSystem::getConversionByDimension('channel');
        } catch (Exception $e) {}
        return $snap;
    }

    /**
     * 生成洞察（AI 优先，规则回退）
     * @return array ['insights'=>[], 'anomalies'=>[], 'actions'=>[], 'summary'=>'', 'ai'=>bool]
     */
    public static function generate(int $days = 30): array {
        // 结果缓存：AI 洞察同步调用会阻塞页面，缓存 1 小时
        $cacheFile = DATA_DIR . '/cache/cdp-insight-' . $days . '.json';
        try {
            if (is_file($cacheFile)) {
                $c = json_decode(file_get_contents($cacheFile), true);
                if (is_array($c) && ($c['_t'] ?? 0) > time() - 3600) {
                    unset($c['_t']);
                    return $c;
                }
            }
        } catch (\Throwable $e) {}

        $snap = self::snapshot($days);
        $rule = self::ruleInsights($snap);

        if (!AiCenter::isConfigured()) {
            $rule['ai'] = false;
            self::saveCache($cacheFile, $rule);
            return $rule;
        }

        // AI 生成
        try {
            $system = '你是一位资深的增长/数据分析专家。根据提供的 CDP 用户行为数据（含渠道/设备/营收归因维度），输出结构化 JSON 洞察。请特别关注：渠道效率对比、设备偏好、营收来源结构、转化瓶颈。';
            $user = "以下是最近 {$days} 天的用户行为数据快照（JSON）：\n" . json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
                "\n\n请分析并输出 JSON（不要任何其他文字）：{\n" .
                "  \"summary\": \"用3-5句话总结整体运营状况\",\n" .
                "  \"insights\": [{\"title\":\"洞察标题\",\"detail\":\"详细分析\",\"metric\":\"相关指标\",\"confidence\":\"high|medium|low\"}],\n" .
                "  \"anomalies\": [{\"title\":\"异常标题\",\"detail\":\"异常说明\",\"severity\":\"high|medium|low\"}],\n" .
                "  \"actions\": [{\"title\":\"行动建议\",\"detail\":\"具体做法\",\"priority\":\"high|medium|low\"}]\n" .
                "}";

            $r = AiCenter::json($system, $user, ['temperature' => 0.3]);
            if ($r['ok']) {
                $data = $r['data'];
                $result = [
                    'summary' => $data['summary'] ?? $rule['summary'],
                    'insights' => $data['insights'] ?? $rule['insights'],
                    'anomalies' => $data['anomalies'] ?? $rule['anomalies'],
                    'actions' => $data['actions'] ?? $rule['actions'],
                    'ai' => true,
                    'raw' => $r['raw'],
                ];
                self::saveCache($cacheFile, $result);
                return $result;
            }
        } catch (Exception $e) {}

        $rule['ai'] = false;
        self::saveCache($cacheFile, $rule);
        return $rule;
    }

    /** 写洞察缓存（带时间戳） */
    private static function saveCache(string $file, array $result): void {
        try {
            if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
            $result['_t'] = time();
            @file_put_contents($file, json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {}
    }

    /**
     * 规则化洞察（AI 不可用时回退，基于统计）
     */
    public static function ruleInsights(array $snap): array {
        $insights = [];
        $anomalies = [];
        $actions = [];

        // 来源分析
        $sources = $snap['sources'] ?? [];
        if (!empty($sources)) {
            $top = arsort($sources) ? array_key_first($sources) : '';
            if ($top) $insights[] = ['title' => '主要流量来源：' . $top, 'detail' => "占比最高的获客渠道是「{$top}」，建议加大该渠道投入。", 'metric' => 'source', 'confidence' => 'high'];
            // 占比过高的单一来源（风险）
            $total = array_sum($sources);
            foreach ($sources as $src => $cnt) {
                if ($total > 0 && $cnt / $total > 0.8) {
                    $anomalies[] = ['title' => '流量来源过于集中', 'detail' => "「{$src}」占比超过 80%，存在单渠道依赖风险。", 'severity' => 'medium'];
                    break;
                }
            }
        }

        // 留存分析（cohort 结构：{date: {cohort_size, retained:[{day,count,rate}]}}）
        $retention = $snap['retention'] ?? [];
        if (!empty($retention)) {
            $largestCohort = null;
            foreach ($retention as $cohort) {
                if (($cohort['cohort_size'] ?? 0) > ($largestCohort['cohort_size'] ?? 0)) $largestCohort = $cohort;
            }
            if ($largestCohort && ($largestCohort['cohort_size'] ?? 0) >= 3) {
                $retained = $largestCohort['retained'] ?? [];
                $day1 = null; $day7 = null;
                foreach ($retained as $r) {
                    if (($r['day'] ?? -1) === 1) $day1 = $r['rate'] ?? 0;
                    if (($r['day'] ?? -1) === 7) $day7 = $r['rate'] ?? null;
                }
                if ($day1 !== null && $day1 > 0 && $day7 !== null && $day7 / $day1 < 0.3) {
                    $anomalies[] = ['title' => '7日留存偏低', 'detail' => '首日到第7日留存下降明显，可能存在内容或体验问题。', 'severity' => 'high'];
                    $actions[] = ['title' => '提升7日留存', 'detail' => '对首日用户设计召回内容（二次推送/邮件），分析流失节点。', 'priority' => 'high'];
                }
            }
        }

        // 活跃度异常（对比最近7天新增）
        $growth = $snap['growth'] ?? [];
        if (count($growth) >= 7) {
            // 提取每日新增（兼容 {date: {new_users}} 结构）
            $dailyNew = [];
            foreach ($growth as $day => $val) {
                if (is_array($val)) $dailyNew[] = (int)($val['new_users'] ?? 0);
                elseif (is_numeric($val)) $dailyNew[] = (int)$val;
            }
            $recent = array_slice($dailyNew, -7);
            $avg = array_sum($recent) / max(1, count($recent));
            if ($avg > 0) {
                $last = end($recent);
                $diff = ($last - $avg) / $avg;
                if ($diff < -0.3) {
                    $anomalies[] = ['title' => '新增用户骤降', 'detail' => '今日新增比近7日均值下降 ' . round(abs($diff) * 100) . '%，需关注流量入口变化。', 'severity' => 'high'];
                } elseif ($diff > 0.5) {
                    $insights[] = ['title' => '新增用户大幅上涨', 'detail' => '今日新增比近7日均值上涨 ' . round($diff * 100) . '%，可能来自某次推广或内容爆发。', 'metric' => 'growth', 'confidence' => 'medium'];
                }
            }
        }

        // RFM
        $rfm = $snap['rfm'] ?? [];
        if (!empty($rfm)) {
            $hasHighValue = false;
            foreach ($rfm as $seg => $cnt) {
                if (in_array($seg, ['高价值', 'high_value', '核心用户', '重点用户'], true) && $cnt > 0) $hasHighValue = true;
            }
            if (!$hasHighValue) {
                $actions[] = ['title' => '高价值用户培养', 'detail' => '当前没有高价值(RFM)用户，建议针对已购用户做复购激励。', 'priority' => 'medium'];
            }
        }

        // 渠道归因
        $channel = $snap['channel'] ?? [];
        if (!empty($channel) && count($channel) > 0) {
            $topCh = array_key_first($channel);
            $actions[] = ['title' => '优化转化渠道', 'detail' => "转化表现最好的渠道是「{$topCh}」，可向其倾斜预算。", 'priority' => 'low'];
        }

        // 渠道营收归因（F1）
        $revChannel = $snap['revenue_channel'] ?? [];
        if (count($revChannel) >= 2) {
            $totalRev = array_sum(array_column($revChannel, 'revenue'));
            $maxCh = array_key_first($revChannel);
            $maxRev = $revChannel[$maxCh]['revenue'] ?? 0;
            if ($totalRev > 0 && $maxRev / $totalRev > 0.6) {
                $anomalies[] = ['title' => '营收渠道过于集中', 'detail' => "「{$maxCh}」贡献了营收的 " . round($maxRev / $totalRev * 100) . '%，存在单渠道依赖风险。', 'severity' => 'medium'];
            }
            $insights[] = ['title' => '最赚钱的渠道：' . $maxCh, 'detail' => "「{$maxCh}」带来 ¥" . number_format($maxRev, 0) . " 营收（{$revChannel[$maxCh]['orders']} 单），可重点投入。", 'metric' => 'revenue_channel', 'confidence' => 'high'];
        }

        // 渠道转化效率（F2）
        $convChannel = $snap['conversion_channel'] ?? [];
        if (count($convChannel) >= 2) {
            $bestConv = null; $bestRate = 0; $totalConv = 0;
            foreach ($convChannel as $ch => $d) {
                $totalConv += $d['conversions'] ?? 0;
                if (($d['rate'] ?? 0) > $bestRate) { $bestRate = $d['rate']; $bestConv = $ch; }
            }
            if ($bestConv && $bestRate > 0) {
                $insights[] = ['title' => '最高转化渠道：' . $bestConv, 'detail' => "「{$bestConv}」转化率达 {$bestRate}%，远超其他渠道，可分析其引流方式并复制。", 'metric' => 'conversion_channel', 'confidence' => 'high'];
            }
            // 高访问低转化渠道（机会）
            foreach ($convChannel as $ch => $d) {
                if (($d['visits'] ?? 0) > 0 && ($d['conversions'] ?? 0) === 0) {
                    $actions[] = ['title' => '优化「' . $ch . '」落地转化', 'detail' => "该渠道有 {$d['visits']} 次访问但无转化，可能是落地页与渠道意图不匹配。", 'priority' => 'medium'];
                    break;
                }
            }
        }

        if (empty($insights) && empty($anomalies)) {
            $insights[] = ['title' => '数据运行平稳', 'detail' => '近期无明显异常波动，可关注内容更新与渠道扩展。', 'metric' => 'overview', 'confidence' => 'low'];
        }

        $summary = '基于近 ' . (count($snap['growth'] ?? []) ?: 30) . ' 天数据，系统生成 ' . count($insights) . ' 条洞察、' . count($anomalies) . ' 条异常提示、' . count($actions) . ' 条行动建议。';
        return ['summary' => $summary, 'insights' => $insights, 'anomalies' => $anomalies, 'actions' => $actions, 'ai' => false];
    }
}
