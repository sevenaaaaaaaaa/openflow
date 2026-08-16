<?php
/**
 * 安全修复系统 SafeFix — 人机协同修复
 * 系统只生成"修复方案"（补丁），绝不自动改代码。必须由人审核确认后才应用，且可一键回滚。
 *
 * 流程：
 *  1. 系统扫描出可确定性修复的问题（空值保护 / 未定义键 / 明显逻辑缺陷）
 *  2. 生成补丁方案（文件 + 定位 + 原代码 + 建议修改），呈现给人
 *  3. 人点「应用」→ 备份原文件 → 应用补丁
 *  4. 应用后发现问题 → 一键回滚到备份
 *
 * 安全原则：永不自动执行，人工确认是最后一道闸。
 */
require_once __DIR__ . '/../admin/config.php';

class SafeFix {
    private static string $file = DATA_DIR . '/safefix.json';

    /* ─── 状态 ─── */
    public static function state(): array {
        $s = json_read(self::$file);
        if (empty($s)) {
            $s = ['patches' => [], 'applied' => [], 'backups' => []];
            json_write(self::$file, $s);
        }
        return $s;
    }

    /** 备份目录 */
    private static function backupDir(): string {
        $d = DATA_DIR . '/safefix-backups';
        if (!is_dir($d)) mkdir($d, 0755, true);
        return $d;
    }

    /* ─── 生成补丁方案 ─── */

    /**
     * 扫描并生成可确定性修复的补丁方案（只生成，不应用）
     * @return array 补丁方案列表
     */
    public static function generatePatches(): array {
        $patches = [];
        $root = __DIR__ . '/..';

        // 规则 1：article.php comment_stats null 保护
        $p1 = self::makePatch(
            'article_null_guard',
            'article.php',
            '文章详情页 comment_stats 空值保护',
            '当文章不存在（notFound）时，$article 为 null，导致 comment_stats() 收到 null 触发 TypeError',
            'bug', 'high',
            'article.php',
            '$cs = comment_stats(\'article\', $article[\'id\']);',
            '$cs = $article ? comment_stats(\'article\', $article[\'id\'] ?? \'\') : [\'count\' => 0, \'likes\' => 0];'
        );
        if ($p1) $patches[] = $p1;

        // 规则 2：course-player.php 参数兼容（slug 已修过，跳过）
        // 规则 3：扫描常见 Undefined array key 模式（后续可扩展）

        return $patches;
    }

    /** 生成单个补丁方案（验证文件与原文存在） */
    private static function makePatch(string $id, string $file, string $title, string $reason, string $category, string $severity, string $targetFile, string $old, string $new): ?array {
        $root = __DIR__ . '/..';
        $path = $root . '/' . $targetFile;
        if (!is_file($path)) return null;
        $content = file_get_contents($path);
        if (strpos($content, $old) === false) return null; // 原文不存在，可能已修

        return [
            'id' => $id, 'title' => $title, 'reason' => $reason,
            'category' => $category, 'severity' => $severity,
            'file' => $targetFile, 'old' => $old, 'new' => $new,
            'status' => 'pending', 'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** 保存补丁方案到状态 */
    public static function savePatches(array $patches): void {
        $s = self::state();
        foreach ($patches as $p) {
            $s['patches'][$p['id']] = $p; // 按 id 覆盖，保留已应用的
        }
        json_write(self::$file, $s);
    }

    /** 获取所有补丁 */
    public static function patches(): array {
        return self::state()['patches'] ?? [];
    }

    /* ─── 应用 / 回滚 ─── */

    /**
     * 应用补丁（人工确认后调用）
     * 1. 备份原文件
     * 2. 应用替换
     * 3. 记录 applied 记录（可回滚）
     */
    public static function apply(string $id): array {
        $s = self::state();
        $p = $s['patches'][$id] ?? null;
        if (!$p) return ['ok' => false, 'error' => '补丁不存在'];
        if (($p['status'] ?? '') === 'applied') return ['ok' => false, 'error' => '补丁已应用'];

        $root = __DIR__ . '/..';
        $path = $root . '/' . $p['file'];
        if (!is_file($path)) return ['ok' => false, 'error' => '目标文件不存在'];

        // 1. 备份
        $backupName = $p['file'] . '.bak.' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $backupPath = self::backupDir() . '/' . str_replace('/', '__', $backupName);
        if (!copy($path, $backupPath)) return ['ok' => false, 'error' => '备份失败'];

        // 2. 应用
        $content = file_get_contents($path);
        if (strpos($content, $p['old']) === false) {
            return ['ok' => false, 'error' => '原文不匹配，可能已变更'];
        }
        $newContent = str_replace($p['old'], $p['new'], $content);
        if (file_put_contents($path, $newContent) === false) {
            return ['ok' => false, 'error' => '写入失败'];
        }

        // 3. 记录
        $p['status'] = 'applied';
        $p['applied_at'] = date('Y-m-d H:i:s');
        $p['backup'] = $backupPath;
        $s['patches'][$id] = $p;
        $s['applied'][] = [
            'id' => $id, 'title' => $p['title'], 'file' => $p['file'],
            'backup' => $backupPath, 'applied_at' => $p['applied_at'],
        ];
        json_write(self::$file, $s);

        // 审计 + 时间线
        try { AuditLog::log('safefix_apply', 'system', ['patch' => $id, 'file' => $p['file']]); } catch (\Throwable $e) {}
        try { GrowthEngine::timeline('fix', "应用修复：" . $p['title']); } catch (\Throwable $e) {}
        // 清页面缓存（修复后立即生效）
        try { (new FileCache())->flush(); } catch (\Throwable $e) {}

        return ['ok' => true, 'backup' => $backupPath];
    }

    /** 回滚补丁（从备份恢复） */
    public static function rollback(string $id): array {
        $s = self::state();
        $p = $s['patches'][$id] ?? null;
        if (!$p) return ['ok' => false, 'error' => '补丁不存在'];
        if (($p['status'] ?? '') !== 'applied') return ['ok' => false, 'error' => '补丁未应用，无需回滚'];
        if (empty($p['backup']) || !is_file($p['backup'])) return ['ok' => false, 'error' => '备份文件缺失'];

        $root = __DIR__ . '/..';
        $path = $root . '/' . $p['file'];
        if (!copy($p['backup'], $path)) return ['ok' => false, 'error' => '回滚失败'];

        $p['status'] = 'rolled_back';
        $p['rolled_back_at'] = date('Y-m-d H:i:s');
        $s['patches'][$id] = $p;
        json_write(self::$file, $s);
        try { AuditLog::log('safefix_rollback', 'system', ['patch' => $id, 'file' => $p['file']]); } catch (\Throwable $e) {}
        try { GrowthEngine::timeline('fix', "回滚修复：" . $p['title']); } catch (\Throwable $e) {}
        try { (new FileCache())->flush(); } catch (\Throwable $e) {}
        return ['ok' => true];
    }
}
