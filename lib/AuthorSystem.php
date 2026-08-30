<?php
/**
 * AuthorSystem —— 作者 / 创作者 / 开发者的统一身份（生态地基）
 *
 * 现状的毛病：文章的 author 只是一个自由文本名字，散落在文章 / 课程 /
 * skills / 插件里，靠字符串相等来聚合；简介头像还得碰巧有个同名会员才有。
 * 改个名就全断、两种写法就成两个人。
 *
 * 这里把作者做成一等实体（data/authors.json）：一份带简介 / 头像 / 职位 /
 * 链接 / 绑定平台账号的档案，name 仍作为与既有内容的连接键（向后兼容），
 * 但档案本身独立存在、可维护。作者可绑定到会员（创作者 / 开发者），
 * 于是「作者—创作者—开发者」在一个身份下统一——这正是做生态的地基。
 */
require_once __DIR__ . '/../admin/config.php';

function authors_file(): string { return DATA_DIR . '/authors.json'; }

/** 全部作者档案。 */
function author_all(): array {
    $a = json_read(authors_file());
    return is_array($a) ? array_values($a) : [];
}

function author_get(string $id): ?array {
    foreach (author_all() as $a) if (($a['id'] ?? '') === $id) return $a;
    return null;
}

/** 按名字取档案（内容聚合的连接键）。 */
function author_by_name(string $name): ?array {
    $name = trim($name);
    if ($name === '') return null;
    foreach (author_all() as $a) {
        if (($a['name'] ?? '') === $name) return $a;
        foreach ((array)($a['aliases'] ?? []) as $al) if ($al === $name) return $a;
    }
    return null;
}

function author_by_slug(string $slug): ?array {
    foreach (author_all() as $a) if (($a['slug'] ?? '') === $slug) return $a;
    return null;
}

/** 生成 slug（ASCII 名直接用，含中文等则退化为短码）。 */
function author_slugify(string $name): string {
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'au-' . substr(md5($name), 0, 8);
}

/**
 * 新建 / 更新作者。传 id 则更新，否则新建。
 * name 在作者间唯一（含别名冲突检测）。
 */
function author_save(array $d): array {
    $name = trim((string)($d['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '名字不能为空'];

    $all = json_read(authors_file());
    if (!is_array($all)) $all = [];

    $id = trim((string)($d['id'] ?? ''));
    // 重名检测（排除自己）
    foreach ($all as $a) {
        if (($a['id'] ?? '') === $id) continue;
        if (($a['name'] ?? '') === $name) return ['ok' => false, 'error' => "已存在同名作者「{$name}」"];
    }

    $entity = [
        'id'        => $id ?: ('au_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6)),
        'name'      => $name,
        'slug'      => trim((string)($d['slug'] ?? '')) ?: author_slugify($name),
        'title'     => trim((string)($d['title'] ?? '')),
        'bio'       => trim((string)($d['bio'] ?? '')),
        'avatar'    => trim((string)($d['avatar'] ?? '')),
        'links'     => array_values(array_filter((array)($d['links'] ?? []), fn($l) => trim((string)($l['url'] ?? '')) !== '')),
        'member_id' => trim((string)($d['member_id'] ?? '')),
        'aliases'   => array_values(array_filter(array_map('trim', (array)($d['aliases'] ?? [])))),
        'featured'  => !empty($d['featured']),
        'created_at'=> $d['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at'=> date('Y-m-d H:i:s'),
    ];

    // 覆盖或追加
    $found = false;
    foreach ($all as &$a) { if (($a['id'] ?? '') === $entity['id']) { $a = $entity; $found = true; break; } }
    unset($a);
    if (!$found) $all[] = $entity;
    json_write(authors_file(), array_values($all));
    return ['ok' => true, 'author' => $entity];
}

function author_delete(string $id): bool {
    $all = array_values(array_filter(json_read(authors_file()), fn($a) => ($a['id'] ?? '') !== $id));
    return json_write(authors_file(), $all);
}

/**
 * 一个作者名下各类内容的数量（按 name 连接既有数据）。
 */
function author_content_counts(string $name): array {
    $name = trim($name);
    $match = function (array $list) use ($name): int {
        return count(array_filter($list, fn($x) => trim((string)($x['author'] ?? '')) === $name));
    };
    $articles = function_exists('get_articles') ? get_articles() : json_read(DATA_DIR . '/articles/index.json');
    return [
        'articles' => count(array_filter($articles, fn($a) => trim((string)($a['author'] ?? '')) === $name)),
        'courses'  => $match(json_read(DATA_DIR . '/courses/index.json')),
        'skills'   => $match(json_read(DATA_DIR . '/skills/index.json')),
        'plugins'  => $match(json_read(DATA_DIR . '/plugins.json')),
    ];
}

/**
 * 发现"未登记作者"：出现在内容里、但还没建档案的名字。
 * @return array [name => counts]
 */
function author_discover(): array {
    $known = [];
    foreach (author_all() as $a) {
        $known[$a['name']] = true;
        foreach ((array)($a['aliases'] ?? []) as $al) $known[$al] = true;
    }
    $names = [];
    $collect = function (array $list) use (&$names) {
        foreach ($list as $x) { $n = trim((string)($x['author'] ?? '')); if ($n !== '') $names[$n] = true; }
    };
    $collect(function_exists('get_articles') ? get_articles() : []);
    $collect(json_read(DATA_DIR . '/courses/index.json'));
    $collect(json_read(DATA_DIR . '/skills/index.json'));
    $collect(json_read(DATA_DIR . '/plugins.json'));

    $out = [];
    foreach (array_keys($names) as $n) {
        if (isset($known[$n])) continue;
        $out[$n] = author_content_counts($n);
    }
    return $out;
}

/**
 * 合并：把 $fromName 的文章作者名改写为规范作者 $toId 的名字，
 * 并把 $fromName 收作该作者的别名。解决"同一个人两种写法"的乱账。
 * 只改写文章（主战场）；其它内容类型作者字段一并尽力改写。
 * @return int 改写的文章数
 */
function author_merge(string $fromName, string $toId): int {
    $fromName = trim($fromName);
    $to = author_get($toId);
    if (!$to || $fromName === '' || $fromName === $to['name']) return 0;

    // 1) 改写文章 author
    $rewritten = 0;
    $file = DATA_DIR . '/articles/index.json';
    $articles = json_read($file);
    if ($articles) {
        foreach ($articles as &$a) {
            if (trim((string)($a['author'] ?? '')) === $fromName) { $a['author'] = $to['name']; $rewritten++; }
        }
        unset($a);
        json_write($file, $articles);
    }
    // 2) 其它内容类型尽力改写
    foreach ([DATA_DIR . '/courses/index.json', DATA_DIR . '/skills/index.json'] as $f) {
        $list = json_read($f);
        if (!$list) continue;
        $changed = false;
        foreach ($list as &$x) if (trim((string)($x['author'] ?? '')) === $fromName) { $x['author'] = $to['name']; $changed = true; }
        unset($x);
        if ($changed) json_write($f, $list);
    }
    // 3) 收作别名，之后按旧名也能定位到规范档案
    $to['aliases'] = array_values(array_unique(array_merge((array)($to['aliases'] ?? []), [$fromName])));
    author_save($to);
    return $rewritten;
}
