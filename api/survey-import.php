<?php
/**
 * 调研问卷文档解析 API
 * POST /api/survey-import.php  (multipart/form-data, file=xxx)
 * 支持：TXT/MD（纯文本）、DOCX（ZipArchive 提取）、PDF（文本层提取）
 * 返回：解析出的题目数组 [{title, type, options, required}]
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '请选择文件']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$tmp = $file['tmp_name'];

// ─── 提取纯文本 ───
$text = '';
switch ($ext) {
    case 'txt':
    case 'md':
    case 'text':
        $text = @file_get_contents($tmp);
        break;

    case 'docx':
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => '服务器缺少 ZipArchive 扩展，无法解析 DOCX']);
            exit;
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => '无法打开 DOCX 文件']);
            exit;
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'DOCX 中未找到文档内容']);
            exit;
        }
        // 段落转换为换行
        $xml = preg_replace('/<w:p[ >]/', "\n", $xml);
        $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);
        $text = preg_replace('/<[^>]+>/', '', $xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        break;

    case 'pdf':
        // 提取 PDF 文本层（适用文本型 PDF；扫描件需 OCR，暂不支持）
        $raw = @file_get_contents($tmp);
        // 尝试用 pdftotext（若服务器有 poppler）
        if (function_exists('exec')) {
            $out = [];
            @exec('which pdftotext 2>/dev/null', $out);
            if (!empty($out)) {
                $text = @shell_exec('pdftotext -layout ' . escapeshellarg($tmp) . ' - 2>/dev/null');
                break;
            }
        }
        // 回退：从 PDF 流提取文本（基础解析）
        $text = '';
        if ($raw) {
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj|TJ/m', $raw, $m)) {
                $text = implode(' ', $m[1]);
                $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
            }
            if (preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $m2)) {
                foreach ($m2[1] as $seg) {
                    if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/m', $seg, $m3)) {
                        $text .= ' ' . implode(' ', array_map(function ($s) {
                            return str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
                        }, $m3[1]));
                    }
                }
            }
            // PDF 中文常是十六进制编码
            if (preg_match_all('/<([0-9A-Fa-f]{2,})>\s*Tj/m', $raw, $m4)) {
                $hexText = '';
                foreach ($m4[1] as $hex) {
                    if (strlen($hex) % 2 === 0) {
                        $decoded = @iconv('UTF-16BE', 'UTF-8', pack('H*', $hex));
                        if ($decoded !== false) $hexText .= $decoded;
                    }
                }
                if ($hexText) $text .= ' ' . $hexText;
            }
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '不支持的文件类型，请上传 TXT / MD / DOCX / PDF']);
        exit;
}

$text = trim($text ?? '');
if (empty($text)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '未能从文件中解析出文字内容（若是 PDF 扫描件，请使用文本型 PDF 或转 DOCX 后导入）'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 从文本解析题目 ───
// 支持：1. 2. (1) 一、 等序号开头为题目；A. B. ①②③ 为选项
$lines = preg_split('/\r\n|\r|\n/', $text);
$questions = [];
$current = null;
$optionPattern = '/^\s*(?:[A-Ha-h][.、．)）:：]|(?:①②③④⑤⑥⑦⑧⑨⑩)|[（(][A-Ha-h][)）])[\s]*(.+)\s*$/';
$qnumPattern = '/^\s*(?:\d{1,2}[.、．)）]|[（(]\d{1,2}[)）]|[一二三四五六七八九十]{1,3}[、.．])\s*(.+)\s*$/';

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;

    $qm = preg_match($qnumPattern, $line, $qmMatch);
    $om = preg_match($optionPattern, $line, $omMatch);
    $endsQ = preg_match('/[？?:：]$/u', $line) === 1;

    if ($qm) {
        if ($current && !empty($current['title'])) {
            $current['type'] = !empty($current['options']) ? 'single' : 'text';
            $questions[] = $current;
        }
        $current = ['title' => trim($qmMatch[1]), 'options' => [], 'type' => 'text', 'required' => false];
    } elseif ($om && $current) {
        $current['options'][] = trim($omMatch[1]);
    } elseif ($current && !empty($current['title']) && empty($current['options']) && ($endsQ || mb_strlen($line) < 60)) {
        $current['title'] .= ' ' . $line;
    } elseif (!$current) {
        continue; // 跳过问卷标题等非题目行
    }
}

if ($current && !empty($current['title'])) {
    $current['type'] = !empty($current['options']) ? 'single' : 'text';
    $questions[] = $current;
}

// 过滤无效
$questions = array_values(array_filter($questions, fn($q) => !empty(trim($q['title'])) && mb_strlen($q['title']) > 2));

if (empty($questions)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => '未能识别出问卷题目，请检查文档格式（建议每题一行，选项用 A/B/C）'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 限制题目数
$questions = array_slice($questions, 0, 30);

echo json_encode([
    'ok' => true,
    'filename' => $file['name'],
    'questions' => $questions,
    'count' => count($questions),
], JSON_UNESCAPED_UNICODE);
