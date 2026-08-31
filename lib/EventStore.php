<?php
/**
 * EventStore — events 行为事件表的统一读写层（分层存储第一步）
 *
 * 【为什么存在】events 是唯一会膨胀到百万级的表。SQLite 单文件在事件量大后
 * 会遇到写锁与查询瓶颈，但全量迁移 MySQL 又破坏一人公司零依赖的定位。
 * 因此：events 表单独抽象，默认 SQLite，配置了 MySQL 则用 MySQL。
 *
 * 【分档】
 *   Tier1 默认：SQLite（data/db/openflow.db 的 events 表）
 *   Tier2 可选：MySQL（settings.json 配置 mysql_dsn/mysql_user/mysql_pass）
 *
 * 【用法】
 *   EventStore::record($entry)        写一条
 *   EventStore::query($sql, $params)  读（sql 用 MySQL 语法写，SQLite 由本类转义）
 *
 * 【原则】上层业务不感知底层是 SQLite 还是 MySQL；本类保证 SQL 兼容。
 */

class EventStore {

    private static ?PDO $conn = null;
    private static ?bool $isMysql = null;

    /**
     * 读取 MySQL 配置（settings.json → 环境变量）
     */
    public static function mysqlConfig(): array {
        $settings = [];
        $file = DATA_DIR . '/settings.json';
        if (file_exists($file)) {
            $settings = json_decode(file_get_contents($file), true) ?: [];
        }
        return [
            'host'    => $settings['mysql_host']    ?? ($_ENV['MYSQL_HOST']    ?? 'localhost'),
            'port'    => (int)($settings['mysql_port']    ?? ($_ENV['MYSQL_PORT']    ?? 3306)),
            'dbname'  => $settings['mysql_dbname']  ?? ($_ENV['MYSQL_DBNAME']  ?? 'openflow'),
            'user'    => $settings['mysql_user']    ?? ($_ENV['MYSQL_USER']    ?? 'openflow'),
            'pass'    => $settings['mysql_pass']    ?? ($_ENV['MYSQL_PASS']    ?? ''),
            'enabled' => !empty($settings['mysql_enabled'] ?? ($_ENV['MYSQL_ENABLED'] ?? false)),
        ];
    }

    /**
     * 是否启用 MySQL 存储
     */
    public static function isMysql(): bool {
        if (self::$isMysql !== null) return self::$isMysql;
        $cfg = self::mysqlConfig();
        self::$isMysql = !empty($cfg['enabled']) && class_exists('PDO')
            && in_array('mysql', PDO::getAvailableDrivers(), true);
        return self::$isMysql;
    }

    /**
     * 获取 events 连接
     */
    public static function conn(): PDO {
        if (self::$conn !== null) return self::$conn;
        if (self::isMysql()) {
            $cfg = self::mysqlConfig();
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8mb4";
            self::$conn = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            // SQLite：复用 Database 的连接（同一 openflow.db，避免双连接）
            if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
            self::$conn = Database::conn();
        }
        return self::$conn;
    }

    /**
     * 查询（SQL 用 MySQL 语法写；SQLite 下部分函数本类转义）
     * 注意：本方法按"MySQL 语法"书写，若底层是 SQLite 会做轻量改写。
     */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::conn()->prepare(self::translateSql($sql));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 写一条事件（返回受影响行数；失败返回 0）
     * $entry 结构同 CdpSystem::track() 的 properties 规范化后的数组：
     *   ['event'=>..,'visitor_id'=>..,'properties'=>[],'url'=>..,'ip'=>..,
     *    'timestamp'=>..,'ts'=>..,'session_id'=>..,'message_id'=>..,
     *    'member_id'=>..,'member_email'=>..]
     */
    public static function record(array $entry): int {
        if (!self::ensureTable()) return 0;
        $props = $entry['properties'] ?? [];
        $category = '';
        if (is_array($props) && isset($props['event_category'])) {
            $category = $props['event_category'];
            unset($props['event_category']);
        }
        $messageId = $entry['message_id'] ?? ('evt_' . md5(($entry['visitor_id'] ?? '') . ($entry['event'] ?? '') . ($entry['timestamp'] ?? '') . uniqid()));
        $ts = (int)($entry['ts'] ?? (strtotime($entry['timestamp'] ?? '') ?: time()) * 1000);

        $sql = "INSERT INTO events (event, label, variant, page, uid, member_id, member_email, props, ip, created_at, session_id, message_id, ts, event_category)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE id=id";
        // MySQL 用 ON DUPLICATE KEY；SQLite 用 INSERT OR IGNORE
        if (!self::isMysql()) {
            $sql = "INSERT OR IGNORE INTO events (event, label, variant, page, uid, member_id, member_email, props, ip, created_at, session_id, message_id, ts, event_category)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        }
        try {
            // 14 列按序: event,label,variant,page,uid,member_id,member_email,props,ip,created_at,session_id,message_id,ts,event_category
            self::conn()->prepare($sql)->execute([
                $entry['event'] ?? '', '', '',
                $entry['url'] ?? $entry['page'] ?? '', $entry['visitor_id'] ?? '',
                $entry['member_id'] ?? '', $entry['member_email'] ?? '', json_encode($props, JSON_UNESCAPED_UNICODE),
                $entry['ip'] ?? '', $entry['timestamp'] ?? date('Y-m-d H:i:s'),
                $entry['session_id'] ?? '', $messageId, $ts, $category,
            ]);
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 批量写入（走事务）
     */
    public static function recordBatch(array $entries): int {
        if (empty($entries)) return 0;
        $n = 0;
        $conn = self::conn();
        $own = !$conn->inTransaction();
        if ($own) { try { $conn->beginTransaction(); } catch (Exception $e) {} }
        try {
            foreach ($entries as $e) {
                $n += self::record($e);
            }
            if ($own) $conn->commit();
            return $n;
        } catch (Exception $e) {
            if ($own && $conn->inTransaction()) { try { $conn->rollBack(); } catch (Exception $e2) {} }
            return 0;
        }
    }

    /**
     * 确保 events 表存在
     */
    public static function ensureTable(): bool {
        static $ready = false;
        if ($ready) return true;
        try {
            $conn = self::conn();
            if (self::isMysql()) {
                $conn->exec("CREATE TABLE IF NOT EXISTS events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event VARCHAR(64) NOT NULL DEFAULT '',
                    label VARCHAR(255) NOT NULL DEFAULT '',
                    variant VARCHAR(64) NOT NULL DEFAULT '',
                    page VARCHAR(500) NOT NULL DEFAULT '',
                    uid VARCHAR(64) NOT NULL DEFAULT '',
                    member_id VARCHAR(64) NOT NULL DEFAULT '',
                    member_email VARCHAR(255) NOT NULL DEFAULT '',
                    props MEDIUMTEXT,
                    ip VARCHAR(64) NOT NULL DEFAULT '',
                    created_at DATETIME DEFAULT NULL,
                    session_id VARCHAR(64) NOT NULL DEFAULT '',
                    message_id VARCHAR(64) NOT NULL DEFAULT '',
                    ts BIGINT DEFAULT 0,
                    event_category VARCHAR(64) NOT NULL DEFAULT '',
                    UNIQUE KEY uk_message (message_id),
                    KEY idx_event_created (event, created_at),
                    KEY idx_created (created_at),
                    KEY idx_uid (uid, id),
                    KEY idx_event_page (event, page),
                    KEY idx_member (member_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $ready = true;
                return true;
            }
            // SQLite：Database::migrate() 已建 events 表，但需确保 message_id 唯一索引
            // （去重依赖它；旧版部分索引在 SQLite<3.8 建失败，用普通唯一索引兜底）
            if (!class_exists('Database')) require_once __DIR__ . '/Database.php';
            Database::migrate();
            try {
                $conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_events_message ON events(message_id) WHERE message_id != ''");
            } catch (Exception $e) {
                // SQLite<3.8 不支持部分唯一索引 → 不建唯一索引，避免空 message_id 冲突。
                // 去重由 CdpSystem 生成唯一 message_id + 代码层保证；直接 INSERT 的场景
                // （如测试/工具）不应被唯一索引拦住。
            }
            $ready = true;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 清空 events 表（迁移/清理用）
     */
    public static function truncate(): bool {
        try {
            self::conn()->exec(self::isMysql() ? 'TRUNCATE TABLE events' : 'DELETE FROM events');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取 events 表行数
     */
    public static function count(): int {
        try {
            return (int)self::conn()->query('SELECT COUNT(*) FROM events')->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * SQLite 下的函数转义（MySQL 语法 → SQLite 兼容）
     * 当前需处理：substr() 在两者一致；暂无其他差异。
     * 若 SQLite 下遇到 MySQL 专属函数在此扩展。
     */
    private static function translateSql(string $sql): string {
        return $sql;
    }

    /**
     * 写操作转义：MySQL 与 SQLite 语法差异点在这里统一。
     * 目前 DELETE FROM events 等语句两者语法一致，直接透传。
     * 未来如遇 INSERT ... ON DUPLICATE 等差异在此分支。
     */
    public static function translateWrite(string $sql): string {
        if (!self::isMysql()) {
            // SQLite 下把 MySQL 的 IGNORE/REPLACE 语义转成 INSERT OR IGNORE 不需要，
            // 现有调用点都是标准 DELETE/INSERT，直接透传。
        }
        return $sql;
    }
}
