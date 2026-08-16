<?php
/**
 * 前台搜索引擎 SearchEngine
 * 跨文章/专题/课程/资料/技能 搜索，供 search.php 页面和 search-public.php API 共用
 */
require_once __DIR__ . '/../admin/config.php';

class SearchEngine {
    /**
     * 执行搜索
     * @return array ['ok'=>true, 'q'=>, 'articles'=>[], 'topics'=>[], 'courses'=>[], 'downloads'=>[], 'skills'=>[]]
     */
    public static function search(string $q): array {
        $result = ['ok' => true, 'q' => $q, 'articles' => [], 'topics' => [], 'courses' => [], 'downloads' => [], 'skills' => []];
        $q = trim($q);
        if (mb_strlen($q) < 1) return $result;

        // 文章
        foreach (json_read(ARTICLES_DIR . '/index.json') as $a) {
            if (($a['status'] ?? 'draft') !== 'published') continue;
            $hay = ($a['title'] ?? '') . ' ' . ($a['seo_desc'] ?? '') . ' ' . ($a['excerpt'] ?? '') . ' ' . implode(' ', $a['tags'] ?? []);
            if (mb_stripos($hay, $q) !== false) {
                $result['articles'][] = [
                    'title' => $a['title'] ?? '', 'slug' => $a['slug'] ?? '',
                    'cover' => $a['cover'] ?? '', 'category' => $a['category'] ?? '',
                    'date' => $a['created_at'] ?? '',
                ];
                if (count($result['articles']) >= 12) break;
            }
        }

        // 专题
        foreach (json_read(DATA_DIR . '/topics.json') as $t) {
            if (($t['status'] ?? '') !== 'published') continue;
            if (mb_stripos(($t['title'] ?? '') . ($t['description'] ?? ''), $q) !== false) {
                $result['topics'][] = ['title' => $t['title'] ?? '', 'slug' => $t['slug'] ?? '', 'description' => $t['description'] ?? ''];
            }
        }

        // 课程
        foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
            if (mb_stripos(($c['title'] ?? '') . ($c['desc'] ?? ''), $q) !== false) {
                $result['courses'][] = ['title' => $c['title'] ?? '', 'id' => $c['id'] ?? '', 'price' => $c['price'] ?? ''];
            }
        }

        // 资料
        foreach (json_read(DATA_DIR . '/downloads.json') as $d) {
            if (mb_stripos(($d['title'] ?? '') . ($d['desc'] ?? ''), $q) !== false) {
                $result['downloads'][] = ['title' => $d['title'] ?? '', 'id' => $d['id'] ?? ''];
            }
        }

        // Skills
        foreach (json_read(DATA_DIR . '/skills/index.json') as $s) {
            if (mb_stripos(($s['title'] ?? '') . ($s['description'] ?? ''), $q) !== false) {
                $result['skills'][] = ['title' => $s['title'] ?? '', 'id' => $s['id'] ?? ''];
            }
        }

        return $result;
    }
}
