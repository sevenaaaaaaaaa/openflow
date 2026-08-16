<?php
/**
 * 数据连接器 DataConnector
 * 把 CRM 线索 / 商城订单 / 课程进度 / 会员资料 自动回填到 CDP 画像
 * 每次运行按身份解析合并，给画像打上业务标签（已购/高价值/活跃学员等）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/CdpSystem.php';
require_once __DIR__ . '/IdentityResolver.php';
require_once __DIR__ . '/CrmSystem.php';
require_once __DIR__ . '/ShopSystem.php';
require_once __DIR__ . '/ProgressSystem.php';
require_once __DIR__ . '/MemberSystem.php';
require_once __DIR__ . '/Database.php';

class DataConnector {
    /**
     * 全量同步：CRM + 订单 + 课程 + 会员 → CDP
     * @return array 同步统计
     */
    public static function syncAll(): array {
        $stats = [];
        $stats['crm'] = self::syncCrm();
        $stats['shop'] = self::syncShop();
        $stats['courses'] = self::syncCourses();
        $stats['members'] = self::syncMembers();
        return $stats;
    }

    /**
     * CRM 线索回填：按 email 解析身份 → 打「线索」标签 + 记录来源/阶段
     */
    public static function syncCrm(): array {
        $count = 0;
        try { $data = crm_get(); } catch (Exception $e) { return ['count' => 0, 'error' => $e->getMessage()]; }
        $leads = $data['leads'] ?? [];

        foreach ($leads as $l) {
            $email = $l['email'] ?? '';
            $memberId = $l['member_id'] ?? '';
            if (!$email && !$memberId) continue;
            $canonical = IdentityResolver::merge('', $memberId, $email);
            if (!$canonical) continue;

            // 更新画像业务属性
            $props = [];
            if (!empty($l['name'])) $props['name'] = $l['name'];
            if (!empty($l['phone'])) $props['phone'] = $l['phone'];
            if (!empty($l['company'])) $props['company'] = $l['company'];
            if (!empty($l['stage'])) $props['lead_stage'] = $l['stage'];
            if (isset($l['score'])) $props['lead_score'] = (int)$l['score'];

            self::addToCdp($canonical, $props, ['线索']);
            $count++;
        }
        return ['count' => $count];
    }

    /**
     * 商城订单回填：统计购买金额/订单数 → 打「已购」「高价值」标签
     */
    public static function syncShop(): array {
        $count = 0;
        try { $orders = shop_get_orders(); } catch (Exception $e) { return ['count' => 0, 'error' => $e->getMessage()]; }

        $byMember = [];
        foreach ($orders as $o) {
            $mid = $o['member_id'] ?? '';
            if (!$mid) continue;
            if (!isset($byMember[$mid])) $byMember[$mid] = ['orders' => 0, 'total' => 0.0, 'first' => '', 'last' => ''];
            $byMember[$mid]['orders']++;
            $byMember[$mid]['total'] += (float)($o['amount'] ?? 0);
            if (!$byMember[$mid]['first'] || ($o['created_at'] ?? '') < $byMember[$mid]['first']) $byMember[$mid]['first'] = $o['created_at'] ?? '';
            if (($o['created_at'] ?? '') > $byMember[$mid]['last']) $byMember[$mid]['last'] = $o['created_at'] ?? '';
        }

        foreach ($byMember as $mid => $agg) {
            $canonical = IdentityResolver::resolve('', $mid);
            if (!$canonical) continue;
            $tags = ['已购'];
            if ($agg['total'] >= 1000) $tags[] = '高价值';
            self::addToCdp($canonical, [
                'order_count' => $agg['orders'],
                'total_spent' => round($agg['total'], 2),
                'first_order_at' => $agg['first'],
                'last_order_at' => $agg['last'],
            ], $tags);
            $count++;
        }
        return ['count' => $count, 'members' => count($byMember)];
    }

    /**
     * 课程进度回填：学员画像 → 打「课程学习者」「活跃学员」标签
     */
    public static function syncCourses(): array {
        $count = 0;
        try { $progress = progress_all(); } catch (Exception $e) { return ['count' => 0, 'error' => $e->getMessage()]; }

        $byMember = [];
        foreach ($progress as $p) {
            $mid = $p['member_id'] ?? '';
            if (!$mid) continue;
            $cid = $p['course_id'] ?? '';
            $done = $p['done'] ?? false;
            if (!isset($byMember[$mid])) $byMember[$mid] = ['courses' => [], 'done' => 0, 'last' => ''];
            if (!in_array($cid, $byMember[$mid]['courses'], true)) $byMember[$mid]['courses'][] = $cid;
            if ($done) $byMember[$mid]['done']++;
            if (($p['updated_at'] ?? '') > $byMember[$mid]['last']) $byMember[$mid]['last'] = $p['updated_at'] ?? '';
        }

        foreach ($byMember as $mid => $agg) {
            $canonical = IdentityResolver::resolve('', $mid);
            if (!$canonical) continue;
            $tags = ['课程学习者'];
            if ($agg['done'] > 0) $tags[] = '活跃学员';
            self::addToCdp($canonical, [
                'courses_enrolled' => count($agg['courses']),
                'courses_completed' => $agg['done'],
                'last_lesson_at' => $agg['last'],
            ], $tags);
            $count++;
        }
        return ['count' => $count, 'members' => count($byMember)];
    }

    /**
     * 会员资料回填：同步基础资料到画像
     */
    public static function syncMembers(): array {
        $count = 0;
        try { $members = member_get_all(); } catch (Exception $e) { return ['count' => 0, 'error' => $e->getMessage()]; }

        foreach ($members as $m) {
            $mid = $m['id'] ?? '';
            if (!$mid) continue;
            $canonical = IdentityResolver::resolve('', $mid);
            if (!$canonical) continue;
            $props = [];
            if (!empty($m['name'])) $props['name'] = $m['name'];
            if (!empty($m['email'])) $props['email'] = $m['email'];
            if (!empty($m['phone'])) $props['phone'] = $m['phone'];
            if (!empty($m['company'])) $props['company'] = $m['company'];
            if (!empty($m['city'])) $props['city'] = $m['city'];
            if (!empty($m['level'])) $props['member_level'] = $m['level'];
            self::addToCdp($canonical, $props, [], $m['id']);
            $count++;
        }
        return ['count' => $count];
    }

    /**
     * 写入 CDP 画像（统一入口：身份画像 + CdpSystem 画像同步）
     */
    private static function addToCdp(string $canonical, array $props, array $tags, string $memberId = ''): void {
        $g = json_read(DATA_DIR . '/cdp/identity.json');
        if (isset($g['profile'][$canonical])) {
            $p = &$g['profile'][$canonical];
            foreach ($props as $k => $v) {
                if ($k === 'email') $p['email'] = $v;
                elseif ($k === 'phone') $p['phone'] = $v;
                else $p['properties'][$k] = $v;
            }
            foreach ($tags as $t) {
                if (!in_array($t, $p['tags'] ?? [], true)) $p['tags'][] = $t;
            }
            if ($memberId && empty($p['member_id'])) $p['member_id'] = $memberId;
            $p['last_seen'] = date('Y-m-d H:i:s');
            json_write(DATA_DIR . '/cdp/identity.json', $g);
        }

        // 同步 CdpSystem 画像
        $profiles = CdpSystem::allProfiles();
        if (!isset($profiles[$canonical])) {
            $profiles[$canonical] = [
                'visitor_id' => $canonical,
                'member_id' => $memberId,
                'first_seen' => date('Y-m-d H:i:s'),
                'last_seen' => date('Y-m-d H:i:s'),
                'properties' => [],
                'events_count' => 0,
                'tags' => [],
            ];
        }
        $cp = &$profiles[$canonical];
        foreach ($props as $k => $v) $cp['properties'][$k] = $v;
        foreach ($tags as $t) {
            if (!in_array($t, $cp['tags'], true)) $cp['tags'][] = $t;
        }
        if ($memberId) $cp['member_id'] = $memberId;
        $cp['last_seen'] = date('Y-m-d H:i:s');
        CdpSystem::saveProfiles($profiles);
    }
}
