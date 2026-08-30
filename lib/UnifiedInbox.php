<?php
/**
 * UnifiedInbox —— 统一会话收件箱（AUDIT-04 创新三 / BACKLOG T1-10）
 *
 * 【为什么】客服是散的：表单提交、评论、站内信、咨询预约、微信各一摊，没有一处汇总。
 * 访客问一句话找不到人 → 直接的漏斗流失。本模块把散落来源汇成**一个只读收件箱**，
 * 每条带联系方式与来源，可一键转成 CRM 线索（客服→售前→成交在一条线上）。
 *
 * 【定位】不做传统 Intercom（工单系统重）。这里只做"汇总 + 认领 + 转线索"，
 * 真正的对话由站点 Agent（见 SiteAgent）承接。
 *
 * 存储：来源各自的文件只读聚合；处理状态存 data/inbox-state.json（按 uid 标记）。
 */

if (!function_exists('uinbox_state_file')) {

    function uinbox_state_file(): string { return DATA_DIR . '/inbox-state.json'; }
    function uinbox_state(): array {
        $d = function_exists('json_read') ? json_read(uinbox_state_file()) : [];
        return is_array($d) ? $d : [];
    }
    function uinbox_set_state(string $uid, string $status, string $note = ''): bool {
        if (!in_array($status, ['open', 'done', 'ignored'], true)) return false;
        $s = uinbox_state();
        $s[$uid] = ['status' => $status, 'note' => $note, 'at' => date('Y-m-d H:i:s')];
        if (function_exists('json_write')) json_write(uinbox_state_file(), $s);
        return true;
    }

    /** 统一条目结构。 */
    function uinbox_item(string $source, string $id, array $d): array {
        $uid = $source . ':' . $id;
        $st  = uinbox_state()[$uid] ?? null;
        return [
            'uid'     => $uid,
            'source'  => $source,
            'id'      => $id,
            'name'    => trim((string)($d['name'] ?? '')),
            'email'   => trim((string)($d['email'] ?? '')),
            'phone'   => trim((string)($d['phone'] ?? '')),
            'content' => trim((string)($d['content'] ?? '')),
            'at'      => (string)($d['at'] ?? ''),
            'link'    => (string)($d['link'] ?? ''),
            'status'  => $st['status'] ?? 'open',
        ];
    }

    function uinbox_sources(): array {
        return ['form' => '表单', 'comment' => '评论', 'message' => '站内信', 'consultation' => '咨询预约'];
    }

    /**
     * 聚合所有来源。$inject 可注入各来源原始数据（测试用）：
     *   ['form'=>[], 'comment'=>[], 'message'=>[], 'consultation'=>[]]
     * 返回按时间倒序的条目数组。
     */
    function uinbox_all(?array $inject = null): array {
        $read = function (string $rel) use ($inject) {
            return function_exists('json_read') ? json_read(DATA_DIR . $rel) : [];
        };
        $items = [];

        // 表单提交
        foreach (($inject['form'] ?? $read('/submissions/index.json')) as $s) {
            if (!is_array($s)) continue;
            $fields = is_array($s['data'] ?? null) ? $s['data'] : $s;
            $items[] = uinbox_item('form', (string)($s['id'] ?? md5(json_encode($s))), [
                'name'    => $fields['name'] ?? ($fields['姓名'] ?? ''),
                'email'   => $fields['email'] ?? ($fields['邮箱'] ?? ''),
                'phone'   => $fields['phone'] ?? ($fields['手机'] ?? ''),
                'content' => $fields['message'] ?? ($fields['留言'] ?? ($s['form_title'] ?? '表单提交')),
                'at'      => $s['created_at'] ?? ($s['at'] ?? ''),
            ]);
        }

        // 评论
        foreach (($inject['comment'] ?? $read('/comments.json')) as $c) {
            if (!is_array($c)) continue;
            $items[] = uinbox_item('comment', (string)($c['id'] ?? ''), [
                'name'    => $c['author'] ?? ($c['name'] ?? ''),
                'email'   => $c['email'] ?? '',
                'content' => $c['content'] ?? '',
                'at'      => $c['created_at'] ?? '',
                'link'    => !empty($c['target_id']) ? ('/' . ltrim((string)($c['target_type'] ?? 'article'), '/') . '/' . $c['target_id']) : '',
            ]);
        }

        // 站内信（访客/会员发来的）
        foreach (($inject['message'] ?? $read('/messages/index.json')) as $m) {
            if (!is_array($m)) continue;
            if (($m['direction'] ?? 'out') === 'out') continue;   // 只收进站的
            $items[] = uinbox_item('message', (string)($m['id'] ?? ''), [
                'name'    => $m['from_name'] ?? '',
                'email'   => $m['from_email'] ?? '',
                'content' => $m['content'] ?? ($m['title'] ?? ''),
                'at'      => $m['created_at'] ?? '',
            ]);
        }

        // 咨询预约
        foreach (($inject['consultation'] ?? $read('/consultation/bookings.json')) as $b) {
            if (!is_array($b)) continue;
            $items[] = uinbox_item('consultation', (string)($b['id'] ?? ''), [
                'name'    => $b['name'] ?? '',
                'email'   => $b['email'] ?? '',
                'phone'   => $b['phone'] ?? '',
                'content' => $b['topic'] ?? ($b['note'] ?? '咨询预约'),
                'at'      => $b['created_at'] ?? ($b['slot'] ?? ''),
            ]);
        }

        usort($items, fn($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));
        return $items;
    }

    /** 过滤：来源 / 状态。 */
    function uinbox_filter(array $items, string $source = '', string $status = ''): array {
        return array_values(array_filter($items, function ($i) use ($source, $status) {
            if ($source !== '' && $i['source'] !== $source) return false;
            if ($status !== '' && $i['status'] !== $status) return false;
            return true;
        }));
    }

    /** 统计各来源待处理数。 */
    function uinbox_counts(array $items): array {
        $c = ['open' => 0, 'done' => 0, 'ignored' => 0];
        foreach ($items as $i) { $st = $i['status']; if (isset($c[$st])) $c[$st]++; }
        return $c;
    }

    /**
     * 转成 CRM 线索（客服→售前闭环）。需要邮箱。返回 ['ok'=>bool,...]。
     */
    function uinbox_to_lead(array $item): array {
        $email = trim((string)($item['email'] ?? ''));
        if ($email === '') return ['ok' => false, 'error' => '该条没有邮箱，无法建线索'];
        if (!function_exists('crm_ensure_lead')) return ['ok' => false, 'error' => 'CRM 不可用'];
        try {
            crm_ensure_lead($email, (string)($item['name'] ?? ''), (string)($item['phone'] ?? ''));
            if (function_exists('crm_update_lead')) {
                crm_update_lead($email, ['source' => '客服:' . ($item['source'] ?? ''), 'note' => mb_substr((string)($item['content'] ?? ''), 0, 200)]);
            }
            uinbox_set_state((string)$item['uid'], 'done', '已转线索');
            return ['ok' => true, 'email' => $email];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
