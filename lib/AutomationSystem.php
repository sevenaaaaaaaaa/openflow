<?php
/**
 * 营销自动化引擎 — 触发条件 + 流程 + 动作执行
 * 触发器：表单提交 / 用户注册 / NPS 评分 / 定时
 * 动作：发送邮件（BillionMail/Mautic）/ 打标签 / 加 Campaign / 延迟
 *
 * ── 流程编排三件套：本文件是「营销自动化执行器」 ──
 * 关系：FlowSystem = 总事件总线（入口，接收事件并分发）；
 *       CanvasSystem = 可视化画布执行器（nodes/edges 编排）；
 *       本文件 = 触发器+动作的自动化（automation.json 配置）。
 * 加代码指引：触发器类型、动作类型、延迟逻辑加这里，
 *             不要在这里加画布编排（归 CanvasSystem）。
 */

function automation_file(): string { return DATA_DIR . '/automation.json'; }
function automation_log_file(): string { return DATA_DIR . '/automation-log.json'; }

function automation_get(): array {
    return json_read(automation_file());
}
function automation_save(array $flows): bool {
    return json_write(automation_file(), $flows);
}

// 日志
function automation_log(string $flowId, string $msg, string $level = 'info', array $shadow = []): void {
    $log = json_read(automation_log_file());
    $entry = ['time'=>date('Y-m-d H:i:s'),'flow'=>$flowId,'level'=>$level,'message'=>$msg];
    // Optional shadow fields. Existing readers keep consuming the four fields above.
    foreach (['run_id','trigger','status','idempotency_key','tenant_id','result','approval','execution'] as $key) {
        if (array_key_exists($key, $shadow)) $entry[$key] = $shadow[$key];
    }
    $log[] = $entry;
    $log = array_slice($log, -200);
    json_write(automation_log_file(), $log);
}

/** 线B：单流程统计（进入/步骤执行/各渠道触达/成功失败），供洞察 */
function automation_flow_stats(string $flowId): array {
    $log = json_read(automation_log_file());
    $stats = ['entered'=>0, 'steps'=>[], 'channels'=>['email'=>0,'wecom'=>0,'wechat'=>0,'inbox'=>0,'notify'=>0,'coupon'=>0], 'sent'=>0, 'failed'=>0, 'events'=>0];
    foreach ($log as $e) {
        if (($e['flow'] ?? '') !== $flowId) continue;
        if (($e['level'] ?? '') === 'error' || str_contains((string)($e['message'] ?? ''), '失败')) { $stats['failed']++; continue; }
        $msg = (string)($e['message'] ?? '');
        if (str_contains($msg, '进入')) $stats['entered']++;
        if (str_contains($msg, '发送邮件')) $stats['channels']['email']++;
        if (str_contains($msg, '企业微信')) $stats['channels']['wecom']++;
        if (str_contains($msg, '公众号')) $stats['channels']['wechat']++;
        if (str_contains($msg, '站内信')) $stats['channels']['inbox']++;
        if (str_contains($msg, '发券')) $stats['channels']['coupon']++;
        if (str_contains($msg, '任务触发')) $stats['sent']++;
    }
    return $stats;
}

/** 线B：全部流程漏斗（每流程：进入/成功/失败/转化率） */
function automation_flows_stats(): array {
    $flows = automation_get();
    $out = [];
    foreach ($flows as $f) {
        $fid = (string)($f['id'] ?? '');
        $stats = automation_flow_stats($fid);
        $out[] = ['id'=>$fid, 'name'=>(string)($f['name'] ?? $fid), 'trigger'=>(string)($f['trigger'] ?? ''),
            'status'=>(string)($f['enabled'] ?? 0) ? 'enabled' : 'disabled',
            'entered'=>$stats['entered'], 'sent'=>$stats['sent'], 'failed'=>$stats['failed'],
            'channels'=>$stats['channels'],
            'conversion'=>$stats['entered'] > 0 ? round($stats['sent'] / $stats['entered'] * 100, 1) : 0];
    }
    return $out;
}

/** Only a single low-risk add_tag flow with a stable event key enters shadow mode. */
function automation_shadow_add_tag(array $flow, array $context): ?array {
    $steps = array_values((array)($flow['steps'] ?? []));
    if (($flow['enabled'] ?? false) !== true || count($steps) !== 1 || ($steps[0]['action'] ?? '') !== 'add_tag') return null;
    $eventKey = trim((string)($context['idempotency_key'] ?? ($context['event_id'] ?? ($context['_event_id'] ?? ''))));
    $flowId = trim((string)($flow['id'] ?? ''));
    $uid = trim((string)($context['uid'] ?? ''));
    $tag = trim((string)($steps[0]['tag'] ?? ''));
    if ($eventKey === '' || $flowId === '' || $uid === '' || $tag === '') return null;

    require_once __DIR__ . '/DomainContract.php';
    $run = domain_flow_run([
        'flow_id'=>$flowId, 'trigger'=>$flow['trigger'] ?? ($context['event'] ?? 'unknown'),
        'idempotency_key'=>$eventKey, 'tenant_id'=>$context['tenant_id'] ?? 'default',
        'created_at'=>date('Y-m-d H:i:s'),
    ]);
    $actionId = 'act_' . substr(hash('sha256', $run['tenant_id'] . '|' . $flowId . '|0|add_tag|' . $tag), 0, 20);
    return [
        'run_id'=>$run['id'], 'trigger'=>$run['trigger'], 'status'=>'running',
        'idempotency_key'=>$run['idempotency_key'], 'tenant_id'=>$run['tenant_id'],
        'uid'=>$uid, 'tag'=>$tag, 'action_id'=>$actionId,
        'action_version'=>max(1, (int)($flow['version'] ?? 1)),
        'approved_at'=>(string)($flow['updated_at'] ?? ($flow['created_at'] ?? $run['created_at'])),
        'created_at'=>$run['created_at'],
    ];
}

function automation_shadow_log(array $shadow, string $status, array $result = []): void {
    require_once __DIR__ . '/DomainContract.php';
    $approval = domain_approval([
        'action_id'=>$shadow['action_id'] ?? '', 'subject_version'=>$shadow['action_version'] ?? 1,
        'tenant_id'=>$shadow['tenant_id'] ?? 'default', 'decision'=>'approved',
        'actor_type'=>'policy', 'actor_id'=>'enabled_flow_configuration',
        'policy_ref'=>'flow-definition:' . ($shadow['flow_id'] ?? '') . ':enabled',
        'reason'=>'已启用的确定性 Flow 授权执行低风险 add_tag 节点',
        'decided_at'=>$shadow['approved_at'] ?? '',
    ]);
    $execution = domain_execution([
        'action_id'=>$shadow['action_id'] ?? '', 'approval_id'=>$approval['id'],
        'flow_run_id'=>$shadow['run_id'] ?? '', 'tenant_id'=>$shadow['tenant_id'] ?? 'default',
        'status'=>$status, 'executor'=>$result['executor'] ?? 'CdpSync::cdp_add_tag',
        'idempotency_key'=>($shadow['idempotency_key'] ?? '') . ':add_tag:0',
        'result_ref'=>$result['result_ref'] ?? '', 'error'=>$result['error'] ?? '',
        'created_at'=>$shadow['created_at'] ?? date('Y-m-d H:i:s'),
        'completed_at'=>in_array($status, ['succeeded','failed','cancelled'], true) ? date('Y-m-d H:i:s') : '',
    ]);
    $meta = $shadow;
    unset($meta['uid'], $meta['tag'], $meta['flow_id'], $meta['action_id'], $meta['action_version'], $meta['approved_at'], $meta['created_at']);
    $meta['status'] = $status;
    if ($result) $meta['result'] = $result;
    $meta['approval'] = $approval;
    $meta['execution'] = $execution;
    automation_log(
        (string)($shadow['flow_id'] ?? ''),
        '影子运行：' . $status,
        $status === 'failed' ? 'error' : 'info',
        $meta
    );
}

/**
 * 触发自动化流程
 * @param string $trigger 触发器类型：
 *   form_submit / member_register / nps_submit / cron / 定时
 *   行为类：page_view / article_view / element_click / download / purchase / course_complete / course_start / course_enroll / lesson_complete / role_selected / tool_use
 * @param array $context 上下文数据（含 event/props/email/member_id 等）
 */
function automation_trigger(string $trigger, array $context): void {
    // 归一化触发器名（flow_handle 会加 flow_ 前缀）
    if (str_starts_with($trigger, 'flow_')) $trigger = substr($trigger, 5);

    $flows = automation_get();
    foreach ($flows as $flow) {
        if (($flow['enabled'] ?? false) !== true) continue;
        if (($flow['trigger'] ?? '') !== $trigger) continue;
        // 触发器匹配（按类型 + 条件）
        if (!automation_match_trigger($flow, $trigger, $context)) continue;
        if (class_exists('PluginSystem')) {
            PluginSystem::do_action('ma_flow_triggered', $flow['id'] ?? '', $trigger, $context, $flow);
        }
        automation_execute_flow($flow, $context);
        if (class_exists('PluginSystem')) {
            PluginSystem::do_action('ma_flow_completed', $flow['id'] ?? '', $trigger, $context, $flow);
        }
    }
}

/**
 * 判断流程触发器是否与上下文匹配（支持行为条件）
 */
function automation_match_trigger(array $flow, string $trigger, array $context): bool {
    $tt = $flow['trigger_type'] ?? 'all';

    // 表单
    if ($trigger === 'form_submit' && $tt === 'form') {
        $formSlug = $flow['form_slug'] ?? '';
        if ($formSlug && ($context['form_slug'] ?? '') !== $formSlug) return false;
    }
    // NPS
    if ($trigger === 'nps_submit' && $tt === 'nps_above') {
        $threshold = (int)($flow['nps_threshold'] ?? 7);
        if ((int)($context['score'] ?? 0) < $threshold) return false;
    }
    // 行为类触发：页面访问 / 文章浏览 / 元素点击 / 分群进出
    if (in_array($trigger, ['page_view', 'article_view', 'element_click', 'download', 'purchase', 'course_complete', 'course_start', 'course_enroll', 'lesson_complete', 'role_selected', 'tool_use', 'segment_enter', 'segment_exit'], true)) {
        // 条件：指定页面/文章/元素/标签/分群
        $matchField = $flow['match_field'] ?? '';   // page / url / label / element / course_id / tag / segment_id
        $matchValue = trim($flow['match_value'] ?? '');
        if ($matchField && $matchValue) {
            $actual = $context[$matchField] ?? ($context['segment_name'] ?? '');
            if (is_array($actual)) $actual = implode(',', $actual);
            if (mb_strpos((string)$actual, $matchValue) === false) return false;
        }
        // 行为条件：仅当事件带指定 label / props 命中
        $propsMatch = $flow['match_props'] ?? '';
        if ($propsMatch) {
            $props = $context['props'] ?? [];
            $allHit = true;
            foreach (explode(',', $propsMatch) as $kv) {
                $kv = trim($kv); if (!$kv) continue;
                $pair = explode('=', $kv, 2);
                $k = trim($pair[0] ?? ''); $v = trim($pair[1] ?? '');
                if ($k && (string)($props[$k] ?? '') !== $v) { $allHit = false; break; }
            }
            if (!$allHit) return false;
        }
    }
    return true;
}

// 执行一个流程（按步骤）
function automation_execute_flow(array $flow, array $context, int $startAt = 0): void {
    $steps = $flow['steps'] ?? [];
    $shadow = automation_shadow_add_tag($flow, $context);
    if ($shadow && $startAt === 0) {
        $shadow['flow_id'] = (string)$flow['id'];
        automation_shadow_log($shadow, 'running');
    }
    foreach ($steps as $si => $step) {
        if ($si < $startAt) continue;   // 线B：真延时续流，从 delay 后的步骤继续
        switch ($step['action'] ?? '') {
            case 'send_email':
                // 频控检查（疲劳度管理）
                $midCtx = $context['member_id'] ?? '';
                if ($midCtx !== '') {
                    try {
                        require_once __DIR__ . '/FrequencyCap.php';
                        if (!freq_can_send($midCtx, 'email')) { automation_log($flow['id'], '频控：今日邮件触达已达上限，跳过', 'info'); break; }
                    } catch (Exception $e) {}
                }
                automation_send_email($step, $context, $flow['id']);
                if ($midCtx !== '') { try { require_once __DIR__ . '/FrequencyCap.php'; freq_log($midCtx, 'email', $step['subject'] ?? ''); } catch (Exception $e) {} }
                break;
            case 'delay':
                // 线B：真延时续流 — 记录 flow_id + 下一个步骤索引，cron 到点从该处继续
                automation_schedule_delay($step, $context, $flow['id'], $si + 1, $flow['name'] ?? '');
                break;
            case 'notify':
                $midCtx = $context['member_id'] ?? '';
                if ($midCtx !== '') { try { require_once __DIR__ . '/FrequencyCap.php'; if (!freq_can_send($midCtx, 'notify')) { automation_log($flow['id'], '频控：通知触达已达上限', 'info'); break; } } catch (Exception $e) {} }
                notify('自动化', $step['title'] ?? '流程通知', $context['email'] ?? '', $step['link'] ?? '');
                if ($midCtx !== '') { try { require_once __DIR__ . '/FrequencyCap.php'; freq_log($midCtx, 'notify', $step['title'] ?? ''); } catch (Exception $e) {} }
                automation_log($flow['id'], '发送通知: ' . ($step['title'] ?? ''));
                break;
            case 'add_tag':
                // 站内动作：CDP 打标签
                $uid = $context['uid'] ?? '';
                $tag = trim($step['tag'] ?? '');
                if ($uid && $tag) {
                    try {
                        if (!function_exists('cdp_add_tag')) require_once __DIR__ . '/CdpSync.php';
                        cdp_add_tag($uid, $tag);
                        automation_log($flow['id'], '打标签: ' . $tag);
                        if ($shadow && function_exists('cdp_get_by_id')) {
                            $customer = cdp_get_by_id($uid);
                            $tags = json_decode((string)($customer['tags'] ?? '[]'), true) ?: [];
                            if (in_array($tag, $tags, true)) {
                                automation_shadow_log($shadow, 'succeeded', ['executor'=>'CdpSync::cdp_add_tag','result_ref'=>'cdp_customer:' . $uid]);
                            }
                        }
                    } catch (Exception $e) {
                        automation_log($flow['id'], '打标签失败: ' . $e->getMessage(), 'error');
                        if ($shadow) automation_shadow_log($shadow, 'failed', ['executor'=>'CdpSync::cdp_add_tag','error'=>$e->getMessage()]);
                    }
                }
                break;
            case 'award_points':
                // 站内动作：奖励积分
                $mid = $context['member_id'] ?? '';
                $pts = (int)($step['points'] ?? 0);
                if ($mid && $pts > 0) {
                    try {
                        require_once __DIR__ . '/Gamification.php';
                        gamification_award($mid, $pts, 'automation');
                        automation_log($flow['id'], '奖励积分: ' . $pts);
                    } catch (Exception $e) { automation_log($flow['id'], '积分失败: ' . $e->getMessage(), 'error'); }
                }
                break;
            case 'inbox':
                // 站内动作：站内信
                $mid = $context['member_id'] ?? '';
                if ($mid) {
                    try {
                        require_once __DIR__ . '/FrequencyCap.php';
                        if (!freq_can_send($mid, 'inbox')) { automation_log($flow['id'], '频控：站内信触达已达上限，跳过', 'info'); break; }
                    } catch (Exception $e) {}
                    try {
                        require_once __DIR__ . '/MessageSystem.php';
                        inbox_send($mid, $step['title'] ?? '流程通知', $step['content'] ?? '');
                        require_once __DIR__ . '/FrequencyCap.php'; freq_log($mid, 'inbox', $step['title'] ?? '');
                        automation_log($flow['id'], '站内信: ' . ($step['title'] ?? ''));
                    } catch (Exception $e) { automation_log($flow['id'], '站内信失败: ' . $e->getMessage(), 'error'); }
                }
                break;
            case 'connection_action':
                // 开放能力：按连接动作模板向外部服务发请求（鉴权/SSRF 防护/留痕都在连接层）
                $aid = (string)($step['action_id'] ?? '');
                if ($aid !== '') {
                    require_once __DIR__ . '/ConnectionActions.php';
                    $ok = action_run_safe($aid, $context);
                    automation_log($flow['id'], ($ok ? '连接动作成功: ' : '连接动作失败: ') . $aid, $ok ? 'info' : 'error');
                }
                break;
            case 'send_coupon':
                // 站内动作：发放优惠券 + 站内信通知
                $mid = $context['member_id'] ?? '';
                $email = $context['email'] ?? '';
                if ($mid || $email) {
                    try {
                        require_once __DIR__ . '/CouponSystem.php';
                        $val = (float)($step['coupon_value'] ?? 0);
                        $type = in_array($step['coupon_type'] ?? '', ['fixed','percent','free']) ? $step['coupon_type'] : 'fixed';
                        $code = strtoupper('AUTO' . substr(bin2hex(random_bytes(4)), 0, 6));
                        $r = coupon_save([
                            'code' => $code,
                            'name' => $step['coupon_name'] ?? '自动化优惠券',
                            'type' => $type,
                            'value' => $val,
                            'min_amount' => (float)($step['coupon_min'] ?? 0),
                            'max_uses' => 1,
                            'status' => 'active',
                        ]);
                        if (!empty($r['ok'])) {
                            automation_log($flow['id'], '发券: ' . $code . ' ¥' . $val);
                            if ($mid) {
                                require_once __DIR__ . '/MessageSystem.php';
                                inbox_send($mid, '🎁 您的专属优惠券', "优惠码 {$code}（" . ($type === 'percent' ? $val . '% 折扣' : '¥' . $val . ' 减免') . "），下单时输入即可使用。");
                            }
                        }
                    } catch (Exception $e) { automation_log($flow['id'], '发券失败: ' . $e->getMessage(), 'error'); }
                }
                break;
            // 线A：触达渠道接入 MA —— 企业微信（复用已通 qyapi API）
            case 'send_wecom':
                automation_send_wecom($step, $context, $flow['id']);
                break;
            // 线A：触达渠道接入 MA —— 公众号/服务号（复用已通 api.weixin.qq.com API）
            case 'send_wechat':
                automation_send_wechat($step, $context, $flow['id']);
                break;
        }
    }
}

// 发送邮件
function automation_send_email(array $step, array $context, string $flowId): void {
    $email = $context['email'] ?? '';
    if (empty($email)) { automation_log($flowId, '无邮箱，跳过邮件', 'error'); return; }
    $subject = $step['subject'] ?? '来自 OpenFlow';
    $content = $step['content'] ?? '';
    // 变量替换
    foreach ($context as $k => $v) {
        if (is_string($v)) $content = str_replace('{' . $k . '}', $v, $content);
    }
    // 动态推荐块：{recommend} 插入个性化推荐文章
    if (strpos($content, '{recommend}') !== false) {
        try {
            require_once __DIR__ . '/Personalizer.php';
            $memberId = $context['member_id'] ?? '';
            $pref = Personalizer::buildProfile('', $memberId, $email);
            $recs = Personalizer::recommendArticles($pref, 3);
            $html = "\n\n— 为你推荐 —\n";
            foreach ($recs as $rid => $score) {
                $ra = get_article($rid);
                if (!$ra) continue;
                $html .= "• " . ($ra['title'] ?? '') . "\n  " . SITE_URL . "/article/" . ($ra['slug'] ?? $rid) . "\n";
            }
            $content = str_replace('{recommend}', $html, $content);
        } catch (Exception $e) {
            $content = str_replace('{recommend}', '', $content);
        }
    }
    // 优先 Mautic 联系人发送，其次 BillionMail
    $mautic = Mautic::fromConfig();
    if ($mautic && !empty($step['mautic_email_id'])) {
        $res = $mautic->createContact($email, ['email'=>$email, 'firstname'=>$context['name'] ?? '']);
        if ($res && isset($res['contact']['id'])) {
            $mautic->sendEmail((int)$step['mautic_email_id'], $res['contact']['id']);
            automation_log($flowId, "Mautic 邮件已发送给 {$email}");
            if (class_exists('PluginSystem')) PluginSystem::do_action('ma_email_sent', $email, $subject, $content, $flowId, 'mautic');
            return;
        }
    }
    $bm = BillionMail::fromConfig();
    if ($bm) {
        $bm->send($email, $subject, $content);
        automation_log($flowId, "BillionMail 邮件已发送给 {$email}");
        if (class_exists('PluginSystem')) PluginSystem::do_action('ma_email_sent', $email, $subject, $content, $flowId, 'billionmail');
        return;
    }
    automation_log($flowId, '无可用邮件服务，发送失败', 'error');
}

// 延迟动作（存入队列，cron 到点从该步骤之后续流，而非只补发一封邮件）
function automation_schedule_delay(array $step, array $context, string $flowId, int $stepIndex = 0, string $flowName = ''): void {
    $delayMin = (int)($step['delay_minutes'] ?? 60);
    $queue = json_read(DATA_DIR . '/automation-queue.json');
    $queue[] = [
        'id' => 'aq_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'flow_id' => $flowId,
        'flow_name' => $flowName,
        'step_index' => $stepIndex,
        'context' => $context,
        'run_at' => date('Y-m-d H:i:s', time() + $delayMin * 60),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write(DATA_DIR . '/automation-queue.json', $queue);
    automation_log($flowId, "延迟 {$delayMin} 分钟后继续流程（已缓存上下文）");
}

// cron 处理延迟队列
function automation_process_queue(): void {
    $queue = json_read(DATA_DIR . '/automation-queue.json');
    $now = time();
    $remaining = [];
    foreach ($queue as $q) {
        if (strtotime($q['run_at']) <= $now) {
            // 线B：真延时续流 —— 取回整个 flow，从 delay 后的步骤继续执行（不是只补发邮件）
            $flows = automation_get();
            $flow = null;
            foreach ($flows as $fl) if (($fl['id'] ?? '') === ($q['flow_id'] ?? '')) { $flow = $fl; break; }
            if ($flow) {
                $startAt = max(0, (int)($q['step_index'] ?? 0));
                automation_execute_flow($flow, (array)($q['context'] ?? []), $startAt);
            } else {
                automation_log((string)($q['flow_id'] ?? ''), '延迟续流失败：流程不存在', 'error');
            }
        } else {
            $remaining[] = $q;
        }
    }
    json_write(DATA_DIR . '/automation-queue.json', $remaining);
}
