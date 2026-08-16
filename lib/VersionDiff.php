<?php
/**
 * 文章版本对比 Diff 查看器
 * 支持任意两版本间的逐行对比
 */
require_once __DIR__ . '/../admin/config.php';

class VersionDiff {
    /**
     * 计算两个文本的行级 Diff
     * 返回格式: ['type' => 'equal/insert/delete', 'line' => '内容']
     */
    public static function diff(string $old, string $new): array {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);
        $result = [];

        $lcs = self::lcs($oldLines, $newLines);
        $oldIdx = 0;
        $newIdx = 0;
        $lcsIdx = 0;

        while ($oldIdx < count($oldLines) || $newIdx < count($newLines)) {
            $inLcs = $lcsIdx < count($lcs) ? $lcs[$lcsIdx] : null;

            if ($inLcs !== null && $oldIdx < count($oldLines) && $newIdx < count($newLines)
                && $oldLines[$oldIdx] === $inLcs && $newLines[$newIdx] === $inLcs) {
                $result[] = ['type' => 'equal', 'line' => $inLcs];
                $oldIdx++;
                $newIdx++;
                $lcsIdx++;
            } elseif ($oldIdx < count($oldLines) && ($inLcs === null || $oldLines[$oldIdx] !== $inLcs)) {
                $result[] = ['type' => 'delete', 'line' => $oldLines[$oldIdx]];
                $oldIdx++;
            } elseif ($newIdx < count($newLines) && ($inLcs === null || $newLines[$newIdx] !== $inLcs)) {
                $result[] = ['type' => 'insert', 'line' => $newLines[$newIdx]];
                $newIdx++;
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * 计算 LCS (最长公共子序列)
     */
    private static function lcs(array $a, array $b): array {
        $m = count($a);
        $n = count($b);
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        // 回溯
        $result = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($result, $a[$i - 1]);
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $result;
    }

    /**
     * 统计变更行数
     */
    public static function stats(array $diff): array {
        $inserts = 0;
        $deletes = 0;
        foreach ($diff as $d) {
            if ($d['type'] === 'insert') $inserts++;
            if ($d['type'] === 'delete') $deletes++;
        }
        return ['inserts' => $inserts, 'deletes' => $deletes];
    }

    /**
     * 渲染 HTML Diff 输出
     */
    public static function renderHtml(array $diff): string {
        $html = '<div class="diff-viewer">';
        foreach ($diff as $d) {
            $escaped = htmlspecialchars($d['line']);
            switch ($d['type']) {
                case 'equal':
                    $html .= "<div class=\"diff-line diff-equal\">  {$escaped}</div>";
                    break;
                case 'insert':
                    $html .= "<div class=\"diff-line diff-insert\">+ {$escaped}</div>";
                    break;
                case 'delete':
                    $html .= "<div class=\"diff-line diff-delete\">- {$escaped}</div>";
                    break;
            }
        }
        $html .= '</div>';
        return $html;
    }
}
