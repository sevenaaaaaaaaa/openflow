<?php
/**
 * 数据迁移脚本 — 将 JSON 文件数据迁移到 SQLite
 * 运行方式：php bin/migrate-to-sqlite.php
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Database.php';

echo "=== OpenFlow 数据迁移脚本 ===\n\n";

$migrated = 0;

// ─── 迁移商城数据 ───
echo "[1/5] 迁移商城数据...\n";

// 迁移商品
$mallData = json_read(DATA_DIR . '/mall.json');
if (!empty($mallData['products'])) {
    foreach ($mallData['products'] as $p) {
        Database::insert('products', [
            'id' => $p['id'] ?? 'product_' . bin2hex(random_bytes(8)),
            'name' => $p['name'] ?? '',
            'description' => $p['description'] ?? '',
            'price' => $p['price'] ?? 0,
            'original_price' => $p['original_price'] ?? 0,
            'stock' => $p['stock'] ?? 0,
            'sales_count' => $p['sales_count'] ?? 0,
            'cover' => $p['cover'] ?? '',
            'category' => $p['category'] ?? '',
            'status' => $p['status'] ?? 'active',
            'sort_order' => $p['sort_order'] ?? 0,
            'created_at' => $p['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $p['updated_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $migrated++;
    }
    echo "  ✓ 迁移 " . count($mallData['products']) . " 个商品\n";
}

// 迁移订单到 SQLite（如果还在 JSON）
$ordersJson = json_read(DATA_DIR . '/shop/orders.json');
if (!empty($ordersJson)) {
    foreach ($ordersJson as $o) {
        Database::insert('orders', [
            'id' => $o['id'],
            'member_id' => $o['member_id'] ?? '',
            'course_id' => $o['course_id'] ?? '',
            'course_title' => $o['course_title'] ?? '',
            'amount' => $o['amount'] ?? 0,
            'status' => $o['status'] ?? 'pending',
            'payment_method' => $o['payment_method'] ?? '',
            'referrer_id' => $o['referrer_id'] ?? '',
            'commission' => $o['commission'] ?? 0,
            'created_at' => $o['created_at'] ?? '',
            'paid_at' => $o['paid_at'] ?? '',
        ]);
        $migrated++;
    }
    echo "  ✓ 迁移 " . count($ordersJson) . " 个订单\n";
}

// ─── 迁移课程数据 ───
echo "\n[2/5] 迁移课程数据...\n";

$coursesIndex = json_read(DATA_DIR . '/courses/index.json');
if (!empty($coursesIndex)) {
    foreach ($coursesIndex as $c) {
        // 课程基本信息已存储在 JSON，这里创建课程表结构
        $chapters = json_read(DATA_DIR . '/courses/' . $c['id'] . '/chapters.json');
        if (!empty($chapters)) {
            foreach ($chapters as $ch) {
                $lessons = $ch['lessons'] ?? [];
                foreach ($lessons as $idx => $l) {
                    Database::insert('lessons', [
                        'id' => $l['id'] ?? 'lesson_' . bin2hex(random_bytes(8)),
                        'course_id' => $c['id'],
                        'chapter_id' => $ch['id'] ?? '',
                        'title' => $l['title'] ?? '',
                        'content' => $l['content'] ?? '',
                        'video_url' => $l['video_url'] ?? '',
                        'duration' => $l['duration'] ?? 0,
                        'sort_order' => $idx,
                        'is_free' => $l['is_free'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $migrated++;
                }
            }
        }
    }
    echo "  ✓ 迁移 " . count($coursesIndex) . " 个课程结构\n";
}

// ─── 迁移 CRM 数据 ───
echo "\n[3/5] 迁移 CRM 数据...\n";

$crmData = json_read(DATA_DIR . '/crm.json');
if (!empty($crmData['leads'])) {
    foreach ($crmData['leads'] as $l) {
        Database::insert('leads', [
            'id' => $l['id'] ?? 'lead_' . bin2hex(random_bytes(8)),
            'name' => $l['name'] ?? '',
            'email' => $l['email'] ?? '',
            'phone' => $l['phone'] ?? '',
            'company' => $l['company'] ?? '',
            'source' => $l['source'] ?? '',
            'stage' => $l['stage'] ?? 'new',
            'score' => $l['score'] ?? 0,
            'amount' => $l['amount'] ?? 0,
            'assignee' => $l['assignee'] ?? '',
            'expected_close' => $l['expected_close'] ?? '',
            'tags' => json_encode($l['tags'] ?? []),
            'notes' => $l['notes'] ?? '',
            'created_at' => $l['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $l['updated_at'] ?? date('Y-m-d H:i:s'),
        ]);
        
        // 迁移跟进记录
        if (!empty($l['activities'])) {
            foreach ($l['activities'] as $act) {
                Database::insert('lead_activities', [
                    'lead_id' => $l['id'],
                    'type' => $act['type'] ?? 'note',
                    'content' => $act['content'] ?? '',
                    'member_id' => $act['member_id'] ?? '',
                    'created_at' => $act['created_at'] ?? '',
                ]);
            }
        }
        $migrated++;
    }
    echo "  ✓ 迁移 " . count($crmData['leads']) . " 个线索\n";
}

// ─── 迁移会员数据 ───
echo "\n[4/5] 迁移会员数据...\n";

$membersIndex = json_read(DATA_DIR . '/members/index.json');
if (!empty($membersIndex)) {
    foreach ($membersIndex as $m) {
        Database::insert('members', [
            'id' => $m['id'],
            'email' => $m['email'] ?? '',
            'phone' => $m['phone'] ?? '',
            'password_hash' => $m['password_hash'] ?? '',
            'nickname' => $m['nickname'] ?? '',
            'avatar' => $m['avatar'] ?? '',
            'level' => $m['level'] ?? 'free',
            'points' => $m['points'] ?? 0,
            'balance' => $m['balance'] ?? 0,
            'referred_by' => $m['referred_by'] ?? '',
            'last_login' => $m['last_login'] ?? '',
            'created_at' => $m['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $migrated++;
    }
    echo "  ✓ 迁移 " . count($membersIndex) . " 个会员\n";
}

// ─── 迁移表单提交到 SQLite ───
echo "\n[5/5] 迁移表单提交数据...\n";

$submissionsDir = DATA_DIR . '/submissions';
if (is_dir($submissionsDir)) {
    $files = glob($submissionsDir . '/*.json');
    foreach ($files as $f) {
        $data = json_read($f);
        if (!empty($data)) {
            foreach ($data as $s) {
                Database::insert('submissions', [
                    'id' => $s['id'] ?? 'sub_' . bin2hex(random_bytes(8)),
                    'form_id' => $s['form_id'] ?? '',
                    'form_type' => $s['form_type'] ?? '',
                    'data' => json_encode($s['data'] ?? $s, JSON_UNESCAPED_UNICODE),
                    'created_at' => $s['created_at'] ?? '',
                ]);
            }
            $migrated++;
        }
    }
    echo "  ✓ 迁移表单提交数据\n";
}

echo "\n=== 迁移完成 ===\n";
echo "共迁移 {$migrated} 条记录到 SQLite\n";
echo "\n注意：JSON 文件仍保留作为备份，建议验证数据后删除\n";
