<?php
/**
 * SQLite 数据库层 — 增量数据存储
 * 用于高并发/关联查询/事务场景（社区、订单、提交、日志）
 * PHP 内置 PDO 驱动，无需额外安装
 */

class Database {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo === null) {
            $dir = DATA_DIR . '/db';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $file = $dir . '/openflow.db';
            self::$pdo = new PDO('sqlite:' . $file);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->exec('PRAGMA journal_mode=WAL;');
            self::$pdo->exec('PRAGMA busy_timeout=5000;');
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function execute(string $sql, array $params = []): int {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int {
        $keys = array_keys($data);
        $cols = implode(',', $keys);
        $place = implode(',', array_fill(0, count($keys), '?'));
        self::execute("INSERT INTO {$table} ({$cols}) VALUES ({$place})", array_values($data));
        return (int)self::conn()->lastInsertId();
    }

    public static function lastInsertId(): int {
        return (int)self::conn()->lastInsertId();
    }

    // 迁移建表
    public static function migrate(): void {
        $db = self::conn();
        // 社区帖子
        $db->exec("CREATE TABLE IF NOT EXISTS community_posts (
            id TEXT PRIMARY KEY,
            title TEXT, content TEXT, topic TEXT,
            author_id TEXT, author_name TEXT,
            votes INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            status TEXT DEFAULT 'published',
            created_at TEXT
        )");
        // 社区评论
        $db->exec("CREATE TABLE IF NOT EXISTS community_comments (
            id TEXT PRIMARY KEY,
            post_id TEXT, author_id TEXT, author_name TEXT,
            content TEXT, created_at TEXT
        )");
        // 订单
        $db->exec("CREATE TABLE IF NOT EXISTS orders (
            id TEXT PRIMARY KEY,
            member_id TEXT, course_id TEXT, course_title TEXT,
            amount REAL DEFAULT 0, status TEXT DEFAULT 'pending',
            payment_method TEXT, referrer_id TEXT, commission REAL DEFAULT 0,
            created_at TEXT, paid_at TEXT
        )");
        // 数字商品订单扩展列（兼容旧库：尝试添加）
        foreach (['goods_type TEXT DEFAULT \'\'', 'product_id TEXT DEFAULT \'\'', 'period TEXT DEFAULT \'\'', 'author TEXT DEFAULT \'\'', 'commission_rate REAL DEFAULT 0.7', 'distributor_rate REAL DEFAULT 0', 'platform_fee REAL DEFAULT 0', 'original_amount REAL DEFAULT 0', 'coupon_discount REAL DEFAULT 0', 'refunded_at TEXT DEFAULT \'\'', 'refund_reason TEXT DEFAULT \'\'', 'shipped_at TEXT DEFAULT \'\''] as $col) {
            $colName = explode(' ', $col)[0];
            try { $db->exec("ALTER TABLE orders ADD COLUMN {$col}"); } catch (Exception $e) {}
        }
        // 表单提交
        $db->exec("CREATE TABLE IF NOT EXISTS submissions (
            id TEXT PRIMARY KEY,
            form_id TEXT, form_type TEXT, data TEXT,
            created_at TEXT
        )");
        // 访问日志（埋点）
        $db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event TEXT, label TEXT, variant TEXT,
            page TEXT, uid TEXT,
            member_id TEXT, member_email TEXT, props TEXT,
            ip TEXT, created_at TEXT
        )");
        // 舆情监测结果
        $db->exec("CREATE TABLE IF NOT EXISTS sentiment_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            topic_id TEXT, source TEXT, title TEXT, url TEXT, snippet TEXT,
            sentiment TEXT DEFAULT '中性', created_at TEXT
        )");
        // 评论/点评（UGC 高并发场景）
        $db->exec("CREATE TABLE IF NOT EXISTS comments (
            id TEXT PRIMARY KEY,
            target_type TEXT, target_id TEXT,
            member_id TEXT, author TEXT, rating INTEGER DEFAULT 0,
            text TEXT, parent_id TEXT, likes INTEGER DEFAULT 0,
            pinned INTEGER DEFAULT 0, status TEXT DEFAULT 'approved',
            created_at TEXT
        )");
        // 站内信
        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id TEXT PRIMARY KEY,
            recipient TEXT, title TEXT, content TEXT, type TEXT,
            link TEXT, icon TEXT, read_flag INTEGER DEFAULT 0,
            created_at TEXT
        )");
        // 审核日志（风控）
        $db->exec("CREATE TABLE IF NOT EXISTS moderation_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target_type TEXT, target_id TEXT, action TEXT, reason TEXT,
            ai_score REAL DEFAULT 0, reviewer TEXT,
            created_at TEXT
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sentiment_topic ON sentiment_results(topic_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sentiment_date ON sentiment_results(created_at)");
        // 新表索引
        $db->exec("CREATE INDEX IF NOT EXISTS idx_comments_target ON comments(target_type, target_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_comments_status ON comments(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_recipient ON messages(recipient, read_flag)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_moderation_target ON moderation_log(target_type, target_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_moderation_date ON moderation_log(created_at)");
        
        // ─── 商城模块 ───
        // 商品表
        $db->exec("CREATE TABLE IF NOT EXISTS products (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT,
            price REAL DEFAULT 0,
            original_price REAL DEFAULT 0,
            stock INTEGER DEFAULT 0,
            sales_count INTEGER DEFAULT 0,
            cover TEXT,
            category TEXT,
            status TEXT DEFAULT 'active',
            sort_order INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )");
        // 优惠券表
        $db->exec("CREATE TABLE IF NOT EXISTS coupons (
            id TEXT PRIMARY KEY,
            code TEXT UNIQUE NOT NULL,
            name TEXT,
            type TEXT DEFAULT 'fixed',
            value REAL DEFAULT 0,
            min_amount REAL DEFAULT 0,
            max_uses INTEGER DEFAULT 0,
            used_count INTEGER DEFAULT 0,
            start_time TEXT,
            end_time TEXT,
            status TEXT DEFAULT 'active',
            created_at TEXT
        )");
        // 优惠券使用记录
        $db->exec("CREATE TABLE IF NOT EXISTS coupon_uses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coupon_id TEXT,
            member_id TEXT,
            order_id TEXT,
            used_at TEXT
        )");
        // 收货地址表
        $db->exec("CREATE TABLE IF NOT EXISTS addresses (
            id TEXT PRIMARY KEY,
            member_id TEXT,
            name TEXT,
            phone TEXT,
            province TEXT,
            city TEXT,
            district TEXT,
            address TEXT,
            is_default INTEGER DEFAULT 0,
            created_at TEXT
        )");
        // 物流表
        $db->exec("CREATE TABLE IF NOT EXISTS shipments (
            id TEXT PRIMARY KEY,
            order_id TEXT,
            company TEXT,
            tracking_no TEXT,
            status TEXT DEFAULT 'pending',
            created_at TEXT,
            updated_at TEXT
        )");
        
        // ─── 课程模块 ───
        // 课时内容表
        $db->exec("CREATE TABLE IF NOT EXISTS lessons (
            id TEXT PRIMARY KEY,
            course_id TEXT,
            chapter_id TEXT,
            title TEXT,
            content TEXT,
            video_url TEXT,
            duration INTEGER DEFAULT 0,
            sort_order INTEGER DEFAULT 0,
            is_free INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )");
        // 学习进度表
        $db->exec("CREATE TABLE IF NOT EXISTS learning_progress (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id TEXT,
            course_id TEXT,
            lesson_id TEXT,
            progress INTEGER DEFAULT 0,
            completed INTEGER DEFAULT 0,
            last_position INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT,
            UNIQUE(member_id, lesson_id)
        )");
        // 课程评论表
        $db->exec("CREATE TABLE IF NOT EXISTS course_reviews (
            id TEXT PRIMARY KEY,
            course_id TEXT,
            member_id TEXT,
            rating INTEGER DEFAULT 5,
            content TEXT,
            status TEXT DEFAULT 'approved',
            created_at TEXT
        )");
        // 课程证书表
        $db->exec("CREATE TABLE IF NOT EXISTS certificates (
            id TEXT PRIMARY KEY,
            course_id TEXT,
            member_id TEXT,
            cert_no TEXT UNIQUE,
            issued_at TEXT
        )");
        
        // ─── CRM 模块 ───
        // 线索表
        $db->exec("CREATE TABLE IF NOT EXISTS leads (
            id TEXT PRIMARY KEY,
            name TEXT,
            email TEXT,
            phone TEXT,
            company TEXT,
            source TEXT,
            stage TEXT DEFAULT 'new',
            score INTEGER DEFAULT 0,
            amount REAL DEFAULT 0,
            assignee TEXT,
            expected_close TEXT,
            tags TEXT,
            notes TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        // 跟进记录表
        $db->exec("CREATE TABLE IF NOT EXISTS lead_activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lead_id TEXT,
            type TEXT,
            content TEXT,
            member_id TEXT,
            created_at TEXT
        )");
        
        // ─── 会员模块 ───
        // 会员表（前台用户）
        $db->exec("CREATE TABLE IF NOT EXISTS members (
            id TEXT PRIMARY KEY,
            email TEXT UNIQUE,
            phone TEXT,
            password_hash TEXT,
            nickname TEXT,
            avatar TEXT,
            level TEXT DEFAULT 'free',
            points INTEGER DEFAULT 0,
            balance REAL DEFAULT 0,
            referred_by TEXT,
            last_login TEXT,
            created_at TEXT
        )");
        // 会员数字资产/API套餐扩展列
        foreach (['unlocked_skills TEXT DEFAULT \'[]\'', 'api_plans TEXT DEFAULT \'[]\'', 'membership_plan TEXT DEFAULT \'\'', 'membership_expires TEXT DEFAULT \'\''] as $col) {
            try { $db->exec("ALTER TABLE members ADD COLUMN {$col}"); } catch (Exception $e) {}
        }
        // 会员积分记录
        $db->exec("CREATE TABLE IF NOT EXISTS point_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id TEXT,
            points INTEGER,
            type TEXT,
            description TEXT,
            created_at TEXT
        )");
        
        // ─── 索引 ───
        $db->exec("CREATE INDEX IF NOT EXISTS idx_products_status ON products(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_addresses_member ON addresses(member_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_shipments_order ON shipments(order_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_lessons_course ON lessons(course_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_progress_member ON learning_progress(member_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_progress_course ON learning_progress(course_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_course_reviews_course ON course_reviews(course_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_certificates_member ON certificates(member_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_leads_stage ON leads(stage)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_leads_assignee ON leads(assignee)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_leads_source ON leads(source)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_lead_activities_lead ON lead_activities(lead_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_members_email ON members(email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_members_phone ON members(phone)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_members_level ON members(level)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_point_logs_member ON point_logs(member_id)");
        
        // 兼容旧表：补充列
        try { $db->exec("ALTER TABLE events ADD COLUMN member_id TEXT"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE events ADD COLUMN member_email TEXT"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE events ADD COLUMN props TEXT"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE events ADD COLUMN ip TEXT"); } catch (Exception $e) {}
        // 索引
        $db->exec("CREATE INDEX IF NOT EXISTS idx_comments_post ON community_comments(post_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_member ON orders(member_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_subs_form ON submissions(form_id)");
    }
}

// 确保表存在（require 时自动迁移）
Database::migrate();
