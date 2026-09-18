<?php
declare(strict_types=1);
/**
 * 适配人审队列（Adapter Review Queue）
 *
 * 设计：AI 只负责产出草稿与自证；**是否上架由人决定**。本文件负责：
 *   - 扫描草稿目录 → 生成队列（含状态/失败项/待补 TODO/扩权建议）
 *   - 记录人审决定（approve / reject + 备注）
 *   - 上架（copy 到 plugins/<id> + 写入插件注册表，默认 enabled=false）
 *
 * 规则（与 docs/ADAPTER-SPEC.md 一致）：
 *   blocked            → 不可上架（先修问题）
 *   needs-review       → 需人审 approve 后才可上架
 *   passed（verified） → 可直接上架
 */

require_once __DIR__ . '/AdapterIntake.php';

/** 从生成物里解析 AI 的扩权建议：`@adapter-suggest: <surface> 理由` */
function adapter_review_suggestions(string $code): array
{
    $out = [];
    if (preg_match_all('/@adapter-suggest:\s*([A-Za-z_]+)\s*([^\n\r]*)/', $code, $m, PREG_SET_ORDER) !== false) {
        foreach ($m as $row) {
            $surface = (string) $row[1];
            $why = trim((string) $row[2]);
            $out[] = ['surface' => $surface, 'reason' => $why, 'known' => isset(ADAPTER_SURFACES[$surface])];
        }
    }
    return $out;
}

/** 单条草稿 → 队列条目 */
function adapter_review_entry(string $dir): array
{
    $dir = rtrim($dir, '/');
    $id = basename($dir);
    $manifest = [];
    if (is_file($dir . '/plugin.json')) {
        $d = json_decode((string) file_get_contents($dir . '/plugin.json'), true);
        if (is_array($d)) $manifest = $d;
    }
    $code = is_file($dir . '/plugin.php') ? (string) file_get_contents($dir . '/plugin.php') : '';
    $report = [];
    if (is_file($dir . '/verification-report.json')) {
        $r = json_decode((string) file_get_contents($dir . '/verification-report.json'), true);
        if (is_array($r)) $report = $r;
    }
    $src = (array) ($manifest['source'] ?? []);
    $verif = (array) ($manifest['verification'] ?? []);
    $status = (string) ($verif['status'] ?? ($report['status'] ?? 'pending'));
    $statusNote = '';
    // 诚信修正：历史草稿的 manifest 可能仍是 passed，但骨架里还有 TODO(适配) → 队列降级为待审
    if ($status === 'passed' && substr_count($code, 'TODO') > 0) {
        $status = 'needs-review';
        $statusNote = '骨架未补完（仍有 TODO），按规范不得视为已验证';
    }

    return [
        'id' => (string) ($manifest['id'] ?? $id),
        'dir' => $dir,
        'name' => (string) ($manifest['name'] ?? $id),
        'repo' => (string) ($src['repo'] ?? ''),
        'license' => (string) ($src['license'] ?? ''),
        'version' => (string) ($src['version'] ?? ''),
        'class' => (string) (ADAPTER_CLASSES[(string) ($manifest['class'] ?? '')]['need'] ?? ($manifest['class'] ?? '')),
        'surfaces' => implode('/', array_keys((array) ($manifest['surfaces'] ?? []))),
        'permissions' => implode(',', (array) ($manifest['permissions'] ?? [])),
        'status' => $status,
        'status_note' => $statusNote,
        'badge' => $status === 'passed' ? (string) ($verif['badge'] ?? 'unverified') : 'unverified',
        'failed' => array_values((array) ($report['failed'] ?? [])),
        'todos' => substr_count($code, 'TODO'),
        'suggestions' => adapter_review_suggestions($code),
        'lines' => $code === '' ? 0 : substr_count($code, "\n") + 1,
        'updated_at' => date('c', (int) @filemtime($dir . '/plugin.json') ?: time()),
        'decision' => '',
        'note' => '',
    ];
}

/** 扫描草稿目录 → 队列（按状态优先级排序：needs-review → passed → blocked → 其它） */
function adapter_review_scan(string $draftsRoot): array
{
    $out = [];
    foreach ((array) glob(rtrim($draftsRoot, '/') . '/*', GLOB_ONLYDIR) as $dir) {
        if (!is_file($dir . '/plugin.json')) continue;
        $out[] = adapter_review_entry($dir);
    }
    $rank = ['needs-review' => 0, 'passed' => 1, 'blocked' => 2];
    usort($out, static function (array $a, array $b) use ($rank): int {
        $ra = $rank[$a['status']] ?? 3;
        $rb = $rank[$b['status']] ?? 3;
        return $ra === $rb ? strcmp($a['id'], $b['id']) : $ra <=> $rb;
    });
    return $out;
}

/** 是否允许上架（含人审决定） */
function adapter_review_publishable(array $entry): array
{
    $status = (string) ($entry['status'] ?? 'pending');
    if ($status === 'blocked') {
        return ['ok' => false, 'reason' => '状态 blocked：先修失败项（' . implode(',', (array) ($entry['failed'] ?? [])) . '）'];
    }
    if ($status === 'needs-review' && ($entry['decision'] ?? '') !== 'approved') {
        return ['ok' => false, 'reason' => '需人审 approve 后才能上架'];
    }
    if ($status === 'passed' && ($entry['decision'] ?? '') === 'rejected') {
        return ['ok' => false, 'reason' => '已被人工拒绝'];
    }
    if (($entry['todos'] ?? 0) > 0) {
        return ['ok' => true, 'warn' => '仍有 ' . (int) $entry['todos'] . ' 处 TODO(适配)：上架前建议先补完或确认可接受'];
    }
    return ['ok' => true];
}

/** 队列读写 */
function adapter_review_queue_read(string $file): array
{
    if (!is_file($file)) return [];
    $d = json_decode((string) file_get_contents($file), true);
    if (!is_array($d)) return [];
    // 自愈：历史/错误写入可能产生 [[...]] 或 [ 'items' => [...] ] 的嵌套
    $items = $d['items'] ?? $d;
    if (is_array($items) && count($items) === 1 && isset($items[0]) && is_array($items[0]) && !isset($items[0]['id'])) {
        $items = $items[0];
    }
    return ['items' => array_values(array_filter((array) $items, 'is_array'))];
}

function adapter_review_queue_write(array $entries, string $file): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) return false;
    $json = json_encode(['generated_at' => date('c'), 'count' => count($entries), 'items' => array_values($entries)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return @file_put_contents($file, ($json === false ? '{}' : $json) . "\n") !== false;
}

/** 记录人审决定；返回更新后的条目 */
function adapter_review_decide(string $id, string $decision, string $note, string $queueFile): array
{
    $entries = adapter_review_queue_read($queueFile);
    $items = (array) ($entries['items'] ?? []);
    $found = null;
    foreach ($items as $i => $e) {
        if ((string) ($e['id'] ?? '') === $id) {
            $items[$i]['decision'] = $decision;
            $items[$i]['note'] = $note;
            $items[$i]['decided_at'] = date('c');
            $found = $items[$i];
            break;
        }
    }
    if ($found === null) return [];
    adapter_review_queue_write($items, $queueFile);   // write() 接收条目列表（勿再包一层 items）
    return $found;
}

/** 递归复制（跳过审阅工件，保留插件运行所需文件） */
function adapter_review_copy_tree(string $src, string $dst, array $skip = []): array
{
    $default = ['plugin.ai.php', 'plugin.php.prev.bak', 'plugin.template.php.bak'];
    $skip = array_merge($default, $skip);
    $written = [];
    $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($rit as $item) {
        /** @var SplFileInfo $item */
        $rel = ltrim(str_replace($src, '', $item->getPathname()), '/');
        if (in_array(basename($rel), $skip, true)) continue;
        $target = rtrim($dst, '/') . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
                return ['ok' => false, 'written' => $written, 'error' => "无法创建目录 {$target}"];
            }
            continue;
        }
        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            return ['ok' => false, 'written' => $written, 'error' => "无法创建目录 " . dirname($target)];
        }
        if (@copy($item->getPathname(), $target) === false) {
            return ['ok' => false, 'written' => $written, 'error' => "复制失败 {$rel}"];
        }
        $written[] = $rel;
    }
    return ['ok' => true, 'written' => $written, 'error' => ''];
}

/**
 * 已上架的适配器（读插件注册表）——供后台「已上架」区块与生态市场复用
 * @return list<array{id:string,name:string,version:string,source:string,license:string,badge:string,enabled:bool,installed_at:string}>
 */
function adapter_review_published(string $registryFile): array
{
    if (!is_file($registryFile)) return [];
    $reg = json_decode((string) file_get_contents($registryFile), true);
    if (!is_array($reg)) return [];
    $installed = (array) ($reg['installed'] ?? []);
    $enabled = (array) ($reg['enabled'] ?? []);
    $out = [];
    foreach ($installed as $id => $meta) {
        $m = (array) $meta;
        if (empty($m['adapter'])) continue;   // 只列「官方适配」，社区作者投稿走市场的另一条路
        $out[] = [
            'id' => (string) ($m['id'] ?? $id),
            'name' => (string) ($m['name'] ?? $id),
            'version' => (string) ($m['version'] ?? ''),
            'source' => (string) ($m['source'] ?? ''),
            'license' => (string) ($m['license'] ?? ''),
            'badge' => (string) ($m['badge'] ?? ''),
            'enabled' => (bool) ($enabled[$id] ?? false),
            'installed_at' => (string) ($m['installed_at'] ?? ''),
        ];
    }
    usort($out, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
    return $out;
}

/**
 * 上架：把草稿复制到 plugins/<id> 并写入插件注册表（默认 enabled=false，等人在后台开启）
 * @return array{ok:bool,dir:string,error:string,written:list<string>}
 */
function adapter_review_publish(array $entry, string $pluginsRoot, string $registryFile): array
{
    $gate = adapter_review_publishable($entry);
    if (!($gate['ok'] ?? false)) {
        return ['ok' => false, 'dir' => '', 'error' => (string) ($gate['reason'] ?? '不允许上架'), 'written' => []];
    }
    $id = (string) ($entry['id'] ?? '');
    $src = (string) ($entry['dir'] ?? '');
    if ($id === '' || !is_dir($src)) {
        return ['ok' => false, 'dir' => '', 'error' => '草稿目录不存在', 'written' => []];
    }
    $dst = rtrim($pluginsRoot, '/') . '/' . $id;
    if (is_dir($dst)) {
        return ['ok' => false, 'dir' => $dst, 'error' => "目标已存在：plugins/{$id}（请先人工处理同名插件）", 'written' => []];
    }
    $copy = adapter_review_copy_tree($src, $dst);
    if (!($copy['ok'] ?? false)) {
        return ['ok' => false, 'dir' => $dst, 'error' => (string) ($copy['error'] ?? '复制失败'), 'written' => []];
    }

    // 清单自描述：写入 adapter/official 标记（生态市场据此展示「官方适配 · 免费」）
    $mf = $dst . '/plugin.json';
    if (is_file($mf)) {
        $m = json_decode((string) file_get_contents($mf), true);
        if (is_array($m)) {
            $m['adapter'] = true;
            $m['official'] = true;
            $m['price'] = 0;
            $m['adapted_at'] = date('c');
            @file_put_contents($mf, json_encode($m, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    // 注册表：installed + enabled=false
    $reg = is_file($registryFile) ? (array) (json_decode((string) file_get_contents($registryFile), true) ?: []) : [];
    $reg['installed'] = (array) ($reg['installed'] ?? []);
    $reg['enabled'] = (array) ($reg['enabled'] ?? []);
    $manifest = is_file($dst . '/plugin.json') ? (array) (json_decode((string) file_get_contents($dst . '/plugin.json'), true) ?: []) : [];
    $reg['installed'][$id] = [
        'id' => $id,
        'name' => (string) ($manifest['name'] ?? $id),
        'version' => (string) ($manifest['version'] ?? '0.1.0'),
        'installed_at' => date('Y-m-d H:i:s'),
        'adapter' => true,
        'source' => (string) ($entry['repo'] ?? ''),
        'license' => (string) ($entry['license'] ?? ''),
        'badge' => (string) ($entry['badge'] ?? 'unverified'),
        'published_by' => 'adapter-review',
    ];
    $reg['enabled'][$id] = false;   // 默认不启用：由人在「插件」页开启
    @mkdir(dirname($registryFile), 0777, true);
    @file_put_contents($registryFile, json_encode($reg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

    return ['ok' => true, 'dir' => $dst, 'error' => '', 'written' => (array) ($copy['written'] ?? [])];
}
