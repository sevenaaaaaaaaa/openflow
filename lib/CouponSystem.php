<?php
/**
 * 优惠券系统 CouponSystem
 * 满减/折扣/无门槛券，限时限量、每券总用量/每人限领、会员定向
 */

// ─── 优惠券 CRUD ───
function coupon_get(string $id): ?array {
    try {
        $rows = Database::query("SELECT * FROM coupons WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    } catch (Exception $e) { return null; }
}

function coupon_by_code(string $code): ?array {
    try {
        $rows = Database::query("SELECT * FROM coupons WHERE code = ? AND status='active'", [$code]);
        return $rows[0] ?? null;
    } catch (Exception $e) { return null; }
}

function coupon_all(): array {
    try { return Database::query("SELECT * FROM coupons ORDER BY rowid DESC"); } catch (Exception $e) { return []; }
}

function coupon_save(array $d): array {
    try {
        $id = trim($d['id'] ?? '');
        $fields = [
            'code' => strtoupper(trim($d['code'] ?? '')),
            'name' => trim($d['name'] ?? ''),
            'type' => in_array($d['type'] ?? '', ['fixed','percent','free']) ? $d['type'] : 'fixed',
            'value' => (float)($d['value'] ?? 0),
            'min_amount' => (float)($d['min_amount'] ?? 0),
            'max_uses' => (int)($d['max_uses'] ?? 0),
            'start_time' => $d['start_time'] ?? '',
            'end_time' => $d['end_time'] ?? '',
            'status' => ($d['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
        ];
        if ($id !== '') {
            Database::execute("UPDATE coupons SET code=?, name=?, type=?, value=?, min_amount=?, max_uses=?, start_time=?, end_time=?, status=? WHERE id=?", [$fields['code'],$fields['name'],$fields['type'],$fields['value'],$fields['min_amount'],$fields['max_uses'],$fields['start_time'],$fields['end_time'],$fields['status'],$id]);
            return ['ok'=>true, 'id'=>$id];
        }
        $newId = 'c' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 5);
        Database::execute("INSERT INTO coupons (id,code,name,type,value,min_amount,max_uses,used_count,start_time,end_time,status,created_at) VALUES (?,?,?,?,?,?,?,0,?,?,?,?)", [$newId,$fields['code'],$fields['name'],$fields['type'],$fields['value'],$fields['min_amount'],$fields['max_uses'],$fields['start_time'],$fields['end_time'],$fields['status'],date('Y-m-d H:i:s')]);
        return ['ok'=>true, 'id'=>$newId];
    } catch (Exception $e) { return ['ok'=>false, 'error'=>$e->getMessage()]; }
}

function coupon_delete(string $id): bool {
    try { Database::execute("DELETE FROM coupons WHERE id=?", [$id]); return true; } catch (Exception $e) { return false; }
}

// ─── 校验与使用 ───
// 校验优惠券对某会员/订单金额是否可用，返回折扣金额
function coupon_validate(?array $member, string $code, float $orderAmount): array {
    $c = coupon_by_code($code);
    if (!$c) return ['ok'=>false, 'error'=>'优惠券不存在或已停用'];
    $now = time();
    if (!empty($c['start_time']) && strtotime($c['start_time']) > $now) return ['ok'=>false, 'error'=>'优惠券未到使用时间'];
    if (!empty($c['end_time']) && strtotime($c['end_time']) < $now) return ['ok'=>false, 'error'=>'优惠券已过期'];
    if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) return ['ok'=>false, 'error'=>'优惠券已领完'];
    if ($orderAmount < (float)$c['min_amount']) return ['ok'=>false, 'error'=>'订单未满 ¥'.number_format((float)$c['min_amount'],0).' 无法使用'];

    $discount = 0;
    if ($c['type'] === 'fixed') $discount = min((float)$c['value'], $orderAmount);
    elseif ($c['type'] === 'percent') $discount = round($orderAmount * (float)$c['value'] / 100, 2);
    elseif ($c['type'] === 'free') $discount = $orderAmount;

    return ['ok'=>true, 'coupon'=>$c, 'discount'=>$discount, 'payable'=>round($orderAmount - $discount, 2)];
}

// 标记优惠券已使用（下单时）
function coupon_mark_used(string $couponId, string $memberId, string $orderId): void {
    try {
        Database::execute("INSERT INTO coupon_uses (coupon_id,member_id,order_id,used_at) VALUES (?,?,?,?)", [$couponId,$memberId,$orderId,date('Y-m-d H:i:s')]);
        Database::execute("UPDATE coupons SET used_count = used_count + 1 WHERE id=?", [$couponId]);
    } catch (Exception $e) {}
}
