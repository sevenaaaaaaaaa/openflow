<?php
/**
 * 跨 SQLite 与 JSON 文件的一致性写入 —— P0-05（2026-09-03）
 *
 * 【问题】退款、订单联动分成这类操作要连着改好几处：订单状态、分销佣金、作者分成、
 * 订阅权益、技能解锁、购物积分。这些写入散落在 SQLite 表和 JSON 文件两种存储里，
 * 而 Database 层从来没有用过事务——中途任何一步失败，前面已经写下去的就留在那儿了。
 *
 * 【更要命的是】原来的写法还把每一步都套在 `try { ... } catch (Exception $e) {}` 里：
 * 佣金回收失败被静默吞掉，函数继续往下跑，最后返回 ok=true。于是账面上
 * 「订单已退款」，而佣金还留在推广人余额里——没有任何日志、没有任何提示。
 * 事务只是解决了「中途挂掉」，把吞异常改成让它抛出来，才是解决「假装成功」。
 *
 * 【为什么不能只用 SQLite 事务】订单是双源存储：SQLite 的 orders 表 +
 * data/shop/orders.json（订阅类与历史订单在 JSON 里）；订阅状态在
 * data/subscription/state.json；积分在 data/members/index.json。
 * SQLite 的事务管不到文件写。
 *
 * 【做法】两段式 + 补偿：
 *   1. 先给要动的 JSON 文件拍快照（读一份内容到内存）
 *   2. 开 SQLite 事务
 *   3. 跑业务逻辑（SQLite 写 + JSON 写都在里面）
 *   4. 成功 → 提交；失败 → 回滚 SQLite，并把 JSON 文件按快照还原
 *
 * 剩下的唯一不安全窗口是「JSON 已写、SQLite 提交前进程被杀」，只有毫秒级；
 * 真正的分布式事务需要的基础设施，不是这个「上传即跑」的定位该背的。
 *
 * 用法：
 *   $r = txn_run(function () use (...) { ...多步写入...; return ['ok'=>true]; },
 *                [shop_orders_file(), sub_state_file()]);   // 本次会改到的 JSON 文件
 */

if (!function_exists('txn_run')) {

/**
 * @param callable $fn        业务逻辑；抛异常即视为失败，已发生的写入会被撤销
 * @param string[] $jsonFiles 本次可能被改写的 JSON 文件路径，失败时按快照还原
 * @return mixed              $fn 的返回值
 * @throws Throwable          原样抛出，让调用方决定怎么呈现（绝不吞）
 */
function txn_run(callable $fn, array $jsonFiles = []) {
    // 1) JSON 快照。文件不存在也要记（null 表示「本来没有」，回滚时删掉）
    $snap = [];
    foreach (array_unique($jsonFiles) as $f) {
        if (!is_string($f) || $f === '') continue;
        $snap[$f] = is_file($f) ? file_get_contents($f) : null;
    }

    // 2) SQLite 事务。拿不到连接不是致命的——有些路径只动 JSON，
    //    这时降级成「只做 JSON 补偿」，比直接报错可用得多。
    $pdo = null; $owns = false;
    try {
        if (class_exists('Database') && method_exists('Database', 'conn')) {
            $pdo = Database::conn();
            // PDO 不支持嵌套事务：已经在事务里就不再开，交给最外层提交/回滚
            if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $owns = true; }
        }
    } catch (Throwable $e) { $pdo = null; $owns = false; }

    try {
        $result = $fn();
        if ($owns && $pdo && $pdo->inTransaction()) $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($owns && $pdo) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignore) {}
        }
        // 3) JSON 补偿还原
        foreach ($snap as $f => $content) {
            try {
                if ($content === null) { if (is_file($f)) @unlink($f); }
                else { @file_put_contents($f, $content, LOCK_EX); }
            } catch (Throwable $ignore) {}
        }
        // 4) 留痕。回滚是「什么都没发生」，如果连日志也没有，
        //    用户只会看到一次莫名其妙的失败，无从查起。
        txn_log_rollback($e, array_keys($snap));
        throw $e;
    }
}

/**
 * 回滚留痕。
 *
 * 这里刻意**不 require AuditLog**：它会一路把 admin/config.php 拉进来（开 session、
 * 定义常量、发 header）。回滚往往发生在支付回调、cron、CLI 这些上下文里，
 * 为了记一条日志去引导整个后台是本末倒置，还会掩盖原始异常。
 * 所以：审计日志已经在场就用它，不在场就退回 error_log——总之一定留下点什么，
 * 回滚是「什么都没发生」，连日志都没有的话用户只会看到一次莫名其妙的失败。
 */
function txn_log_rollback(Throwable $e, array $files = []): void {
    $brief = mb_substr($e->getMessage(), 0, 300);
    $where = basename($e->getFile()) . ':' . $e->getLine();
    $names = implode(',', array_map('basename', $files));
    try {
        if (class_exists('AuditLog', false)) {
            AuditLog::log('多步写入失败已回滚', 'system',
                ['error' => $brief, 'where' => $where, 'json_files' => array_map('basename', $files)]);
            return;
        }
    } catch (Throwable $ignore) {}
    @error_log("[txn] 多步写入失败已回滚 @{$where}: {$brief}" . ($names !== '' ? " [json: {$names}]" : ''));
}

/** 当前是否处在事务中（供调用方判断要不要自己开） */
function txn_active(): bool {
    try {
        if (!class_exists('Database') || !method_exists('Database', 'conn')) return false;
        return Database::conn()->inTransaction();
    } catch (Throwable $e) { return false; }
}

}
