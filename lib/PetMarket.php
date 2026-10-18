<?php
declare(strict_types=1);
/**
 * 桌宠市场（松萝 Liana 纸偶形象 `.lianapet`）
 *
 * 【定位】生态市场的一类资产：认证开发者上传 → 服务端校验 → 人工审核 → 上架。
 * 松萝客户端把 `/api/pets` 当官方形象源读取（index.json，format 1）。
 * 本地怎么用、用什么图片做形象，松萝都不干预；**只有发布到这里**要求原创或已获授权（rights=shareable）并经过审核。
 *
 * 【状态机】（以投稿为单位，pack_id 是对外的形象 id，可多次投稿迭代版本）
 *   pending ──通过──▶ published ──新版本通过──▶ superseded
 *      └────驳回──▶ rejected        published ──下架──▶ unlisted
 *
 * 数据：data/pets/pets.json（flock 串行化）；文件：data/pets/files/<sub>.lianapet、data/pets/previews/<sub>.png
 * `data/` 整体禁止直接访问，文件只经 api/pets.php 输出（下载计数 + 付费校验）。
 */

function pet_dir(): string
{
    return (defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data') . '/pets';
}

function pet_file(): string { return pet_dir() . '/pets.json'; }

/** 与松萝客户端 PuppetPackStore 同一套白名单 */
const PET_ALLOWED_EXT = ['png', 'jpg', 'jpeg', 'webp', 'json'];
const PET_MAX_BYTES = 40 * 1024 * 1024;
const PET_MAX_ENTRIES = 200;
const PET_PER_SUBMITTER_DAILY = 3;
const PET_LICENSES = ['CC-BY-4.0', 'CC-BY-SA-4.0', 'CC-BY-NC-4.0', 'CC0-1.0', 'All-Rights-Reserved'];

/**
 * 带排他锁的读-改-写（与 AdapterSubmission 同构）。
 * @param callable(list<array<string,mixed>>): array{items?:list<array<string,mixed>>, ret?:mixed} $mutator
 * @return mixed
 */
function pet_transact(callable $mutator)
{
    $dir = pet_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return null;
    $fh = @fopen(pet_file(), 'c+');
    if ($fh === false) return null;
    try {
        if (!flock($fh, LOCK_EX)) return null;
        $raw = (string) stream_get_contents($fh);
        $d = $raw !== '' ? json_decode($raw, true) : null;
        $items = (is_array($d) && is_array($d['items'] ?? null)) ? $d['items'] : [];
        $r = $mutator($items);
        $next = is_array($r['items'] ?? null) ? $r['items'] : $items;
        $json = json_encode(['items' => array_values($next)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $json);
            fflush($fh);
        }
        return $r['ret'] ?? null;
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}

/** @return list<array<string,mixed>> */
function pet_all(): array
{
    if (!is_file(pet_file())) return [];
    $d = json_decode((string) @file_get_contents(pet_file()), true);
    return (is_array($d) && is_array($d['items'] ?? null)) ? $d['items'] : [];
}

function pet_sub_find(string $subId): ?array
{
    foreach (pet_all() as $it) {
        if ((string) ($it['sub_id'] ?? '') === $subId) return $it;
    }
    return null;
}

/** 当前上架的形象（每个 pack_id 只取已发布的那一版），按上架时间倒序 */
function pet_published(): array
{
    $out = [];
    foreach (pet_all() as $it) {
        if (($it['status'] ?? '') === 'published') $out[(string) $it['pack_id']] = $it;
    }
    $list = array_values($out);
    usort($list, fn($a, $b) => strcmp((string) ($b['reviewed_at'] ?? ''), (string) ($a['reviewed_at'] ?? '')));
    return $list;
}

function pet_find(string $packId): ?array
{
    foreach (pet_published() as $it) {
        if ((string) $it['pack_id'] === $packId) return $it;
    }
    return null;
}

/** @return list<array<string,mixed>> 某作者的全部投稿（新的在前） */
function pet_by_author(string $memberId): array
{
    $list = array_values(array_filter(pet_all(), fn($it) => (string) ($it['author_id'] ?? '') === $memberId));
    usort($list, fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return $list;
}

function pet_file_path(array $it): string { return pet_dir() . '/files/' . $it['sub_id'] . '.lianapet'; }
function pet_preview_path(array $it): string { return pet_dir() . '/previews/' . $it['sub_id'] . '.png'; }

function pet_valid_pack_id(string $id): bool
{
    return (bool) preg_match('/^puppet-[a-z0-9][a-z0-9-]{0,56}$/', $id);
}

/**
 * 校验上传的 `.lianapet`：zip 结构、白名单、大小、清单字段、原创声明、平时图。
 * 只读不解压到磁盘（ZipArchive 逐项检查），不执行任何内容。
 *
 * @return array{ok:bool, error?:string, pack_id?:string, name?:string, description?:string, preview?:string}
 */
function pet_inspect(string $path): array
{
    $size = @filesize($path);
    if ($size === false || $size <= 0) return ['ok' => false, 'error' => '文件为空'];
    if ($size > PET_MAX_BYTES) return ['ok' => false, 'error' => '形象包超过 40MB'];
    $head = (string) @file_get_contents($path, false, null, 0, 4);
    if ($head !== "PK\x03\x04") return ['ok' => false, 'error' => '不是 .lianapet 文件（应为 zip 格式）'];
    if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => '服务器缺少 zip 扩展'];

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) return ['ok' => false, 'error' => '形象包损坏，无法读取'];
    try {
        if ($zip->numFiles > PET_MAX_ENTRIES) return ['ok' => false, 'error' => '形象包文件数过多'];
        $total = 0;
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if ($st === false) return ['ok' => false, 'error' => '形象包损坏'];
            $name = (string) $st['name'];
            if (str_ends_with($name, '/')) continue;
            $base = basename($name);
            if (str_starts_with($name, '__MACOSX/') || str_starts_with($base, '.')) continue;
            $parts = explode('/', $name);
            if (str_starts_with($name, '/') || in_array('..', $parts, true) || str_contains($name, '\\')) {
                return ['ok' => false, 'error' => '形象包里有不安全的路径：' . $name];
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, PET_ALLOWED_EXT, true)) {
                return ['ok' => false, 'error' => '形象包里有不支持的文件：' . $name . '（只允许图片和 JSON）'];
            }
            $total += (int) $st['size'];
            if ($total > PET_MAX_BYTES) return ['ok' => false, 'error' => '形象包解压后超过 40MB'];
            $names[] = $name;
        }

        // pet.json 在根目录，或多包了一层文件夹
        $petName = in_array('pet.json', $names, true) ? 'pet.json' : null;
        if ($petName === null) {
            foreach ($names as $n) {
                if (substr_count($n, '/') === 1 && basename($n) === 'pet.json') { $petName = $n; break; }
            }
        }
        if ($petName === null) return ['ok' => false, 'error' => '缺少 pet.json'];
        $prefix = substr($petName, 0, -strlen('pet.json'));
        $pet = json_decode((string) $zip->getFromName($petName), true);
        if (!is_array($pet) || ($pet['renderer'] ?? '') !== 'puppet') {
            return ['ok' => false, 'error' => '不是松萝纸偶形象（pet.json 的 renderer 应为 puppet）'];
        }
        $puppet = (array) ($pet['puppet'] ?? []);
        if (($puppet['rights'] ?? '') !== 'shareable') {
            return ['ok' => false, 'error' => '发布到社区需要原创或已获授权：请在形象工坊勾选「这些图片是我原创或已获授权」后重新导出'];
        }
        $idle = $puppet['images']['idle'] ?? null;
        $idleFile = is_string($idle) ? $idle : (string) (($idle['src'] ?? ''));
        if ($idleFile === '' || str_contains($idleFile, '..') || !in_array($prefix . $idleFile, $names, true)) {
            return ['ok' => false, 'error' => '缺少「平时」图片'];
        }
        $idleBytes = (string) $zip->getFromName($prefix . $idleFile);
        if (@getimagesizefromstring($idleBytes) === false) return ['ok' => false, 'error' => '「平时」图片无法识别'];

        $plugin = json_decode((string) $zip->getFromName($prefix . 'plugin.json'), true);
        $plugin = is_array($plugin) ? $plugin : [];
        $packId = (string) ($plugin['id'] ?? '');
        if (!pet_valid_pack_id($packId)) return ['ok' => false, 'error' => 'plugin.json 的 id 无效（应以 puppet- 开头）'];

        return [
            'ok' => true,
            'pack_id' => $packId,
            'name' => mb_substr(trim((string) ($plugin['name'] ?? $packId)), 0, 40),
            'description' => mb_substr(trim((string) ($plugin['description'] ?? '')), 0, 200),
            'preview' => pet_make_preview($idleBytes),
        ];
    } finally {
        $zip->close();
    }
}

/** 预览图：高 256 的 PNG（无 GD 时原样返回） */
function pet_make_preview(string $bytes): string
{
    if (!function_exists('imagecreatefromstring')) return $bytes;
    $src = @imagecreatefromstring($bytes);
    if ($src === false) return $bytes;
    $w = imagesx($src);
    $h = imagesy($src);
    $nh = min(256, $h);
    $nw = max(1, (int) round($w * $nh / max(1, $h)));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagepng($dst, null, 9);
    return (string) ob_get_clean();
}

function pet_count_today(array $items, string $memberId): int
{
    $today = date('Y-m-d');
    $n = 0;
    foreach ($items as $it) {
        if ((string) ($it['author_id'] ?? '') === $memberId && substr((string) ($it['created_at'] ?? ''), 0, 10) === $today) $n++;
    }
    return $n;
}

/**
 * 投稿（只做校验与入队；审核在后台）。调用方负责登录、开发者资格与 CSRF。
 *
 * @param array{id:string, name?:string} $member
 * @param array{price?:mixed, license?:string, description?:string, tags?:string} $fields
 * @return array{ok:bool, error?:string, sub_id?:string, pack_id?:string}
 */
function pet_submit(array $member, string $tmpPath, array $fields): array
{
    $memberId = (string) ($member['id'] ?? '');
    if ($memberId === '') return ['ok' => false, 'error' => '请先登录'];
    $info = pet_inspect($tmpPath);
    if (!$info['ok']) return $info;

    $license = (string) ($fields['license'] ?? 'CC-BY-4.0');
    if (!in_array($license, PET_LICENSES, true)) return ['ok' => false, 'error' => '许可证无效'];
    $price = round(max(0, min(9999, (float) ($fields['price'] ?? 0))), 2);
    $desc = mb_substr(trim((string) ($fields['description'] ?? '')), 0, 500);
    $tags = array_values(array_slice(array_filter(array_map(
        fn($t) => mb_substr(trim((string) $t), 0, 12),
        preg_split('/[,，\s]+/u', (string) ($fields['tags'] ?? '')) ?: []
    )), 0, 6));
    $sha = (string) hash_file('sha256', $tmpPath);
    $bytes = (int) filesize($tmpPath);
    $subId = 'pet_' . date('Ymd') . '_' . bin2hex(random_bytes(4));

    $r = pet_transact(function (array $items) use ($memberId, $member, $info, $license, $price, $desc, $tags, $sha, $bytes, $subId) {
        if (pet_count_today($items, $memberId) >= PET_PER_SUBMITTER_DAILY) {
            return ['ret' => ['ok' => false, 'error' => '今天的投稿次数已用完（每天 ' . PET_PER_SUBMITTER_DAILY . ' 次）']];
        }
        $revision = 1;
        foreach ($items as $it) {
            if ((string) $it['pack_id'] !== $info['pack_id']) continue;
            // 同一形象 id 只能由原作者迭代版本
            if ((string) ($it['author_id'] ?? '') !== $memberId) {
                return ['ret' => ['ok' => false, 'error' => '这个形象 id 已被其他作者使用：请在形象工坊里「重新开始」生成新形象后再投稿']];
            }
            if (($it['status'] ?? '') === 'pending') {
                return ['ret' => ['ok' => false, 'error' => '这个形象还有一版在审核中，请等审核结束再提交新版本']];
            }
            if (($it['sha256'] ?? '') === $sha && in_array($it['status'] ?? '', ['published', 'pending'], true)) {
                return ['ret' => ['ok' => false, 'error' => '内容和已上架的版本完全一样']];
            }
            $revision = max($revision, (int) ($it['revision'] ?? 1) + 1);
        }
        $now = date('Y-m-d H:i:s');
        $items[] = [
            'sub_id' => $subId,
            'pack_id' => $info['pack_id'],
            'revision' => $revision,
            'name' => $info['name'],
            'description' => $desc !== '' ? $desc : $info['description'],
            'tags' => $tags,
            'license' => $license,
            'price' => $price,
            'author_id' => $memberId,
            'author_name' => (string) ($member['name'] ?? '开发者'),
            'sha256' => $sha,
            'bytes' => $bytes,
            'status' => 'pending',
            'review_note' => '',
            'downloads' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        return ['items' => $items, 'ret' => ['ok' => true, 'sub_id' => $subId, 'pack_id' => $info['pack_id']]];
    });
    if (!is_array($r)) return ['ok' => false, 'error' => '保存失败，请稍后再试'];
    if (!$r['ok']) return $r;

    // 入队成功后再落文件（失败则回滚这条投稿）
    @mkdir(pet_dir() . '/files', 0755, true);
    @mkdir(pet_dir() . '/previews', 0755, true);
    $it = ['sub_id' => $subId];
    $moved = is_uploaded_file($tmpPath) ? @move_uploaded_file($tmpPath, pet_file_path($it)) : @copy($tmpPath, pet_file_path($it));
    if (!$moved || @file_put_contents(pet_preview_path($it), (string) $info['preview']) === false) {
        pet_transact(fn(array $items) => ['items' => array_values(array_filter($items, fn($x) => $x['sub_id'] !== $subId))]);
        return ['ok' => false, 'error' => '保存文件失败，请稍后再试'];
    }
    return $r;
}

/**
 * 审核：approve（上架，旧版本 superseded）/ reject（驳回，附理由）/ unlist（下架当前版本）。
 * 有售价的上架时同步成 CommerceSystem 的 pet 商品（复用订单、支付、交付、作者分成）。
 */
function pet_review(string $subId, string $decision, string $note = ''): array
{
    $r = pet_transact(function (array $items) use ($subId, $decision, $note) {
        $idx = null;
        foreach ($items as $i => $it) if ($it['sub_id'] === $subId) { $idx = $i; break; }
        if ($idx === null) return ['ret' => ['ok' => false, 'error' => '投稿不存在']];
        $cur = $items[$idx];
        $now = date('Y-m-d H:i:s');
        if ($decision === 'approve') {
            if (($cur['status'] ?? '') !== 'pending') return ['ret' => ['ok' => false, 'error' => '只能审核待审核的投稿']];
            foreach ($items as $i => $it) {
                if ($it['pack_id'] === $cur['pack_id'] && ($it['status'] ?? '') === 'published') {
                    $items[$i]['status'] = 'superseded';
                    $items[$i]['updated_at'] = $now;
                    $cur['downloads'] = (int) ($cur['downloads'] ?? 0) + (int) ($it['downloads'] ?? 0); // 下载量随版本累计
                }
            }
            $cur['status'] = 'published';
        } elseif ($decision === 'reject') {
            if (($cur['status'] ?? '') !== 'pending') return ['ret' => ['ok' => false, 'error' => '只能审核待审核的投稿']];
            $cur['status'] = 'rejected';
        } elseif ($decision === 'unlist') {
            if (($cur['status'] ?? '') !== 'published') return ['ret' => ['ok' => false, 'error' => '只能下架已上架的形象']];
            $cur['status'] = 'unlisted';
        } else {
            return ['ret' => ['ok' => false, 'error' => '未知操作']];
        }
        $cur['review_note'] = mb_substr(trim($note), 0, 300);
        $cur['reviewed_at'] = $now;
        $cur['updated_at'] = $now;
        $items[$idx] = $cur;
        return ['items' => $items, 'ret' => ['ok' => true, 'item' => $cur]];
    });
    if (!is_array($r)) return ['ok' => false, 'error' => '保存失败'];
    if ($r['ok']) pet_sync_product($r['item']);
    return $r;
}

/** 上架且有售价 → 发布 / 更新 pet 商品；免费或下架 → 商品转草稿（已购买的权益不受影响） */
function pet_sync_product(array $it): void
{
    if (!class_exists('CommerceSystem')) require_once __DIR__ . '/CommerceSystem.php';
    $existing = null;
    foreach (CommerceSystem::products() as $p) {
        if (($p['type'] ?? '') === 'pet' && ($p['asset_id'] ?? '') === $it['pack_id']) { $existing = $p; break; }
    }
    $live = ($it['status'] ?? '') === 'published' && (float) ($it['price'] ?? 0) > 0;
    if (!$live) {
        if ($existing && ($it['status'] ?? '') !== 'rejected') CommerceSystem::updateProduct($existing['id'], ['status' => 'draft']);
        return;
    }
    $fields = [
        'title' => $it['name'],
        'description' => $it['description'] ?? '',
        'pricing' => ['mode' => 'one_time', 'price' => (float) $it['price']],
        'author' => $it['author_id'],
        'status' => 'published',
    ];
    if ($existing) {
        CommerceSystem::updateProduct($existing['id'], $fields);
    } else {
        CommerceSystem::createProduct($fields + [
            'type' => 'pet',
            'asset_id' => $it['pack_id'],
            'author_name' => $it['author_name'] ?? '',
            'commission_rate' => 0.7,
            'distribution_enabled' => 0,
            'distributor_rate' => 0,
        ]);
    }
}

/** 该形象对应的已发布商品（付费才有） */
function pet_product(string $packId): ?array
{
    if (!class_exists('CommerceSystem')) require_once __DIR__ . '/CommerceSystem.php';
    foreach (CommerceSystem::products() as $p) {
        if (($p['type'] ?? '') === 'pet' && ($p['asset_id'] ?? '') === $packId && ($p['status'] ?? '') === 'published') return $p;
    }
    return null;
}

/** 会员能否下载：免费直接可以；付费需已购买，作者本人也可以 */
function pet_can_download(array $it, ?array $member): bool
{
    if ((float) ($it['price'] ?? 0) <= 0) return true;
    if (!$member) return false;
    if ((string) ($member['id'] ?? '') === (string) ($it['author_id'] ?? '')) return true;
    $product = pet_product((string) $it['pack_id']);
    return $product !== null && CommerceSystem::owns((string) $member['id'], (string) $product['id']);
}

function pet_hit_download(string $subId): void
{
    pet_transact(function (array $items) use ($subId) {
        foreach ($items as $i => $it) {
            if ($it['sub_id'] === $subId) { $items[$i]['downloads'] = (int) ($it['downloads'] ?? 0) + 1; break; }
        }
        return ['items' => $items];
    });
}

function pet_site_base(): string
{
    $base = function_exists('site_config_get') ? rtrim((string) site_config_get('site_url'), '/') : '';
    return $base !== '' ? $base : 'https://nownexts.com';
}

/**
 * 公开目录条目（松萝形象源 format 1）。付费形象不给下载地址，给详情页让用户去购买。
 * 不含作者联系方式与内部 id。
 */
function pet_public_entry(array $it): array
{
    $base = pet_site_base();
    $id = rawurlencode((string) $it['pack_id']);
    $entry = [
        'id' => (string) $it['pack_id'],
        'name' => (string) $it['name'],
        'author' => (string) ($it['author_name'] ?? ''),
        'license' => (string) ($it['license'] ?? ''),
        'description' => (string) ($it['description'] ?? ''),
        'sha256' => (string) $it['sha256'],
        'bytes' => (int) ($it['bytes'] ?? 0),
        'preview' => $base . '/api/pets?action=preview&id=' . $id,
        'page' => $base . '/pet/' . $id,
        'tags' => array_values((array) ($it['tags'] ?? [])),
        'price' => (float) ($it['price'] ?? 0),
        'downloads' => (int) ($it['downloads'] ?? 0),
        'version' => (int) ($it['revision'] ?? 1),
    ];
    if ($entry['price'] <= 0) $entry['url'] = $base . '/api/pets?action=download&id=' . $id;
    return $entry;
}

function pet_index(): array
{
    return ['format' => 1, 'name' => '松萝社区', 'packs' => array_map('pet_public_entry', pet_published())];
}

/** 生态市场的统一资产行 */
function pet_market_rows(): array
{
    return array_map(fn($it) => [
        'type' => 'pet',
        'id' => (string) $it['pack_id'],
        'title' => (string) $it['name'],
        'description' => (string) ($it['description'] ?? ''),
        'icon' => '',
        'author' => (string) ($it['author_name'] ?? ''),
        'author_type' => 'developer',
        'version' => (string) ($it['revision'] ?? 1),
        'installs' => (int) ($it['downloads'] ?? 0),
        'rating' => 0,
        'rating_count' => 0,
        'status' => 'published',
        'price' => (float) ($it['price'] ?? 0),
        'license' => (string) ($it['license'] ?? ''),
        'url' => '/pet/' . rawurlencode((string) $it['pack_id']),
        'preview' => '/api/pets?action=preview&id=' . rawurlencode((string) $it['pack_id']),
        'tags' => array_merge(['桌宠'], (array) ($it['tags'] ?? [])),
        'source' => 'local',
        'created_at' => (string) ($it['reviewed_at'] ?? $it['created_at'] ?? ''),
    ], pet_published());
}
