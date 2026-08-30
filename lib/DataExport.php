<?php
/**
 * 数据导入/导出系统
 * 支持 CSV / JSON 格式的数据导入和导出
 */
require_once __DIR__ . '/../admin/config.php';

class DataExport {

    // ─── 导出功能 ──────────────────────────────────

    /**
     * 导出文章为 CSV
     */
    public static function exportArticles(string $format = 'csv'): string {
        $articles = json_read(ARTICLES_DIR . '/index.json');
        if ($format === 'json') return json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $csv = self::arrayToCsv($articles, ['id', 'title', 'slug', 'category', 'tags', 'status', 'created_at', 'updated_at']);
        return $csv;
    }

    /**
     * 导出用户为 CSV
     */
    public static function exportMembers(string $format = 'csv'): string {
        $members = json_read(DATA_DIR . '/members.json');
        if ($format === 'json') return json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $rows = [];
        foreach ($members as $id => $m) {
            $rows[] = [
                'id' => $id,
                'name' => $m['name'] ?? '',
                'email' => $m['email'] ?? '',
                'phone' => $m['phone'] ?? '',
                'created_at' => $m['created_at'] ?? '',
            ];
        }
        return self::arrayToCsv($rows);
    }

    /**
     * 导出课程为 CSV
     */
    public static function exportCourses(string $format = 'csv'): string {
        $courses = json_read(DATA_DIR . '/courses.json');
        if ($format === 'json') return json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $rows = [];
        foreach ($courses as $id => $c) {
            $rows[] = [
                'id' => $id,
                'title' => $c['title'] ?? '',
                'price' => $c['price'] ?? 0,
                'status' => $c['status'] ?? '',
                'students' => $c['students_count'] ?? 0,
                'created_at' => $c['created_at'] ?? '',
            ];
        }
        return self::arrayToCsv($rows);
    }

    /**
     * 导出线索为 CSV
     */
    public static function exportLeads(string $format = 'csv'): string {
        $leads = self::readCsv(DATA_DIR . '/leads.csv');
        if ($format === 'json') return json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (empty($leads)) return '';
        $headers = array_keys($leads[0]);
        return self::arrayToCsv($leads, $headers);
    }

    /**
     * 导出 CDP 用户画像为 CSV
     */
    public static function exportCdpProfiles(string $format = 'csv'): string {
        require_once __DIR__ . '/CdpProfileStore.php';
        $profiles = cdp_profile_all();
        if ($format === 'json') return json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $rows = [];
        foreach ($profiles as $vid => $p) {
            $rows[] = [
                'visitor_id' => $vid,
                'member_id' => $p['member_id'] ?? '',
                'name' => $p['properties']['name'] ?? '',
                'email' => $p['properties']['email'] ?? '',
                'tags' => implode(',', $p['tags'] ?? []),
                'events_count' => $p['events_count'] ?? 0,
                'first_seen' => $p['first_seen'] ?? '',
                'last_seen' => $p['last_seen'] ?? '',
            ];
        }
        return self::arrayToCsv($rows);
    }

    /**
     * 全量导出
     */
    public static function exportAll(): array {
        return [
            'articles' => json_read(ARTICLES_DIR . '/index.json'),
            'members' => json_read(DATA_DIR . '/members.json'),
            'courses' => json_read(DATA_DIR . '/courses.json'),
            'leads' => self::readCsv(DATA_DIR . '/leads.csv'),
            'settings' => json_read(DATA_DIR . '/settings.json'),
            'categories' => json_read(DATA_DIR . '/categories.json'),
            'tags' => json_read(DATA_DIR . '/tags.json'),
            'cdp_profiles' => (function(){ require_once __DIR__ . '/CdpProfileStore.php'; return cdp_profile_all(); })(),
            'cdp_events' => json_read(DATA_DIR . '/cdp/events.json'),
            'exported_at' => date('Y-m-d H:i:s'),
        ];
    }

    // ─── 导入功能 ──────────────────────────────────

    /**
     * 从 CSV 导入文章
     */
    public static function importArticlesFromCsv(string $csvContent): array {
        $rows = self::csvToArray($csvContent);
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $id = $row['id'] ?? ('art_' . bin2hex(random_bytes(6)));
            if (empty($row['title'])) { $skipped++; continue; }

            $data = [
                'id' => $id,
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? slugify($row['title'] ?? ''),
                'category' => $row['category'] ?? '',
                'tags' => array_filter(explode(',', $row['tags'] ?? '')),
                'status' => $row['status'] ?? 'draft',
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            ];

            save_article($id, $data);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'total' => count($rows)];
    }

    /**
     * 从 JSON 导入
     */
    public static function importFromJson(string $jsonContent, string $type = 'articles'): array {
        $data = json_decode($jsonContent, true);
        if (!$data) return ['error' => 'JSON 解析失败'];

        $imported = 0;
        $skipped = 0;

        foreach ($data as $item) {
            if (empty($item['title']) && empty($item['name'])) { $skipped++; continue; }

            switch ($type) {
                case 'articles':
                    $id = $item['id'] ?? ('art_' . bin2hex(random_bytes(6)));
                    save_article($id, $item);
                    break;
                case 'members':
                    $id = $item['id'] ?? ('mem_' . bin2hex(random_bytes(6)));
                    $members = json_read(DATA_DIR . '/members.json');
                    $members[$id] = $item;
                    json_write(DATA_DIR . '/members.json', $members);
                    break;
            }
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'total' => count($data)];
    }

    // ─── 文件下载 ──────────────────────────────────

    /**
     * 发送文件下载响应
     */
    public static function sendDownload(string $content, string $filename, string $mimeType = 'text/csv'): void {
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $content;
        exit;
    }

    /**
     * 导出并下载
     */
    public static function downloadExport(string $type, string $format = 'csv'): void {
        switch ($type) {
            case 'articles':
                $content = self::exportArticles($format);
                $ext = $format === 'json' ? 'json' : 'csv';
                self::sendDownload($content, "articles_export.{$ext}");
                break;
            case 'members':
                $content = self::exportMembers($format);
                $ext = $format === 'json' ? 'json' : 'csv';
                self::sendDownload($content, "members_export.{$ext}");
                break;
            case 'courses':
                $content = self::exportCourses($format);
                $ext = $format === 'json' ? 'json' : 'csv';
                self::sendDownload($content, "courses_export.{$ext}");
                break;
            case 'leads':
                $content = self::exportLeads($format);
                $ext = $format === 'json' ? 'json' : 'csv';
                self::sendDownload($content, "leads_export.{$ext}");
                break;
            case 'cdp_profiles':
                $content = self::exportCdpProfiles($format);
                $ext = $format === 'json' ? 'json' : 'csv';
                self::sendDownload($content, "cdp_profiles_export.{$ext}");
                break;
            case 'all':
                $content = json_encode(self::exportAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                self::sendDownload($content, "full_export.json", 'application/json');
                break;
        }
    }

    // ─── CSV 工具函数 ──────────────────────────────

    /**
     * 数组转 CSV 字符串
     */
    public static function arrayToCsv(array $data, ?array $headers = null): string {
        if (empty($data)) return '';

        if ($headers === null) {
            $headers = array_keys($data[0]);
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                $line[] = $val;
            }
            fputcsv($output, $line);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * CSV 字符串转数组
     */
    public static function csvToArray(string $csv): array {
        $rows = [];
        $lines = explode("\n", trim($csv));
        if (empty($lines)) return [];

        $headers = str_getcsv(array_shift($lines));
        foreach ($lines as $line) {
            $values = str_getcsv($line);
            if (count($values) === count($headers)) {
                $rows[] = array_combine($headers, $values);
            }
        }

        return $rows;
    }

    /**
     * 读取 CSV 文件
     */
    private static function readCsv(string $path): array {
        if (!file_exists($path)) return [];
        $content = file_get_contents($path);
        return self::csvToArray($content);
    }
}
