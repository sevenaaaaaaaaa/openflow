<?php
/**
 * 激活码体系 — 渠道批量购买 + 用户自助激活课程/服务
 *
 * 存储：data/activation/batches.json + codes.json
 * 结构：
 *   batches: [{id, title, goods_type, goods_id, total, created_at, note}]
 *   codes:   [{code, batch_id, goods_type, goods_id, status: unsold|sold|activated,
 *              sold_to, activated_by, activated_at, created_at}]
 */

if (!function_exists('act_batches_file')) {

function act_batches_file(): string { return DATA_DIR . '/activation/batches.json'; }
function act_codes_file(): string { return DATA_DIR . '/activation/codes.json'; }

// 生成激活码（去易混淆字符 0/O/1/I）
function act_gen_code(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i > 0 && $i % 4 === 0) $code .= '-';
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// 批量生成激活码
function act_create_batch(string $title, string $goodsType, string $goodsId, int $total): array {
    $batches = json_read(act_batches_file());
    $codes = json_read(act_codes_file());
    $batchId = 'b' . substr(md5(uniqid('', true)), 0, 8);

    $batches[] = [
        'id' => $batchId,
        'title' => $title,
        'goods_type' => $goodsType,
        'goods_id' => $goodsId,
        'total' => $total,
        'created_at' => date('Y-m-d H:i:s'),
        'note' => '',
    ];
    for ($i = 0; $i < $total; $i++) {
        $codes[] = [
            'code' => act_gen_code(),
            'batch_id' => $batchId,
            'goods_type' => $goodsType,
            'goods_id' => $goodsId,
            'status' => 'unsold',
            'sold_to' => '',
            'activated_by' => '',
            'activated_at' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
    json_write(act_batches_file(), $batches);
    json_write(act_codes_file(), $codes);
    return ['batch_id' => $batchId, 'total' => $total];
}

// 校验激活码
function act_validate(string $code): array {
    $code = strtoupper(trim($code));
    $codes = json_read(act_codes_file());
    foreach ($codes as $c) {
        if (strtoupper($c['code']) === $code) {
            if ($c['status'] === 'activated') {
                return ['ok' => false, 'error' => '该激活码已被使用'];
            }
            if ($c['status'] === 'sold') {
                return ['ok' => false, 'error' => '该激活码已售出，请联系渠道方激活'];
            }
            return ['ok' => true, 'code' => $c];
        }
    }
    return ['ok' => false, 'error' => '激活码不存在'];
}

// 激活（绑定到会员）
function act_activate(string $code, string $memberId): array {
    $r = act_validate($code);
    if (!$r['ok']) return $r;

    $codes = json_read(act_codes_file());
    foreach ($codes as &$c) {
        if (strtoupper($c['code']) === strtoupper($code)) {
            $c['status'] = 'activated';
            $c['activated_by'] = $memberId;
            $c['activated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($c);
    json_write(act_codes_file(), $codes);

    // 写入会员权益：激活课程或标记已激活的服务
    $members = json_read(DATA_DIR . '/members/index.json');
    foreach ($members as &$m) {
        if ($m['id'] === $memberId) {
            $m['activated_products'] = $m['activated_products'] ?? [];
            $entry = ['code' => strtoupper($code), 'goods_type' => $r['code']['goods_type'], 'goods_id' => $r['code']['goods_id'], 'activated_at' => date('Y-m-d H:i:s')];
            $m['activated_products'][] = $entry;
            break;
        }
    }
    unset($m);
    json_write(DATA_DIR . '/members/index.json', $members);

    return ['ok' => true, 'goods_type' => $r['code']['goods_type'], 'goods_id' => $r['code']['goods_id']];
}

// 批次统计
function act_batch_stats(): array {
    $batches = json_read(act_batches_file());
    $codes = json_read(act_codes_file());
    $out = [];
    foreach ($batches as $b) {
        $bc = array_filter($codes, fn($c) => $c['batch_id'] === $b['id']);
        $out[] = [
            'batch' => $b,
            'total' => count($bc),
            'sold' => count(array_filter($bc, fn($c) => $c['status'] === 'sold')),
            'activated' => count(array_filter($bc, fn($c) => $c['status'] === 'activated')),
            'remaining' => count(array_filter($bc, fn($c) => $c['status'] === 'unsold')),
        ];
    }
    return $out;
}

// 列出某批次的激活码（分页）
function act_batch_codes(string $batchId): array {
    return array_values(array_filter(json_read(act_codes_file()), fn($c) => $c['batch_id'] === $batchId));
}

// 标记某码已售（渠道方登记）
function act_mark_sold(string $code, string $buyerNote = ''): array {
    $r = act_validate($code);
    if (!$r['ok']) return $r;
    $codes = json_read(act_codes_file());
    foreach ($codes as &$c) {
        if (strtoupper($c['code']) === strtoupper($code)) {
            $c['status'] = 'sold';
            $c['sold_to'] = $buyerNote;
            break;
        }
    }
    unset($c);
    json_write(act_codes_file(), $codes);
    return ['ok' => true];
}

// 查询会员已激活的产品
function act_member_activated(string $memberId): array {
    $members = json_read(DATA_DIR . '/members/index.json');
    foreach ($members as $m) {
        if ($m['id'] === $memberId) return $m['activated_products'] ?? [];
    }
    return [];
}

} // end if function_exists
