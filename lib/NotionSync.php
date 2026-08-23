<?php
/**
 * NotionSync — 全内容类型双向同步管理器
 * 支持：导航站、文章、课程、活动、落地页、技能
 * 双向：OpenFlow ↔ Notion，自动检测变更，按需推送/拉取
 */
require_once __DIR__ . '/NotionClient.php';

class NotionSync {

    // ─── 配置 & 状态 ───

    public static function config(): array {
        return json_read(DATA_DIR . '/notion-sync.json') ?: [];
    }
    public static function saveConfig(array $cfg): void {
        json_write(DATA_DIR . '/notion-sync.json', $cfg);
    }
    public static function state(): array {
        return json_read(DATA_DIR . '/notion-sync-state.json') ?: [];
    }
    public static function saveState(array $s): void {
        json_write(DATA_DIR . '/notion-sync-state.json', $s);
    }
    public static function client(): ?NotionClient {
        $cfg = self::config();
        $token = $cfg['token'] ?? '';
        if (!$token) {
            // 回退到 ingest-config
            $ic = json_read(DATA_DIR . '/ingest-config.json');
            $token = $ic['notion_token'] ?? '';
        }
        return $token ? new NotionClient($token) : null;
    }

    // ─── 数据库 Schema ───

    public static function dbSchema(string $type): array {
        $obj = new \stdClass(); // → JSON {}
        $schemas = [
            'navigation' => [
                'Name'        => ['title' => $obj],
                'URL'         => ['url' => $obj],
                '分类'        => ['select' => ['options' => array_map(fn($c) => ['name' => $c], ['ai','open','growth','data','site','content','agent','design','commerce'])]],
                '子分类'      => ['rich_text' => $obj],
                '描述'        => ['rich_text' => $obj],
                '地区'        => ['select' => ['options' => [['name' => 'cn'], ['name' => 'intl']]]],
                '推荐'        => ['checkbox' => $obj],
                '权重'        => ['number' => ['format' => 'number']],
                '标签'        => ['multi_select' => $obj],
                '推荐理由'    => ['rich_text' => $obj],
                '状态'        => ['status' => ['options' => [['name' => 'published'], ['name' => 'draft'], ['name' => 'pending']]]],
            ],
            'articles' => [
                'Title'     => ['title' => $obj],
                'Slug'      => ['rich_text' => $obj],
                '分类'      => ['select' => $obj],
                '状态'      => ['status' => ['options' => [['name' => 'published'], ['name' => 'draft']]]],
                'SEO Title' => ['rich_text' => $obj],
                'SEO Desc'  => ['rich_text' => $obj],
                '标签'      => ['multi_select' => $obj],
            ],
            'courses' => [
                'Title'     => ['title' => $obj],
                'Slug'      => ['rich_text' => $obj],
                '类型'      => ['select' => ['options' => [['name' => '大课程'], ['name' => '训练营'], ['name' => '专栏'], ['name' => '直播']]]],
                '状态'      => ['status' => ['options' => [['name' => 'published'], ['name' => 'draft']]]],
                '价格'      => ['number' => ['format' => 'yuan']],
                '讲师'      => ['rich_text' => $obj],
                '难度'      => ['select' => ['options' => [['name' => 'beginner'], ['name' => 'intermediate'], ['name' => 'advanced']]]],
                '评分'      => ['number' => ['format' => 'number']],
                '标签'      => ['multi_select' => $obj],
            ],
            'events' => [
                'Title'     => ['title' => $obj],
                'Slug'      => ['rich_text' => $obj],
                '类型'      => ['select' => ['options' => [['name' => 'online'], ['name' => 'offline'], ['name' => 'hybrid']]]],
                '日期'      => ['date' => $obj],
                '地点'      => ['rich_text' => $obj],
                '容量'      => ['number' => $obj],
                '状态'      => ['status' => ['options' => [['name' => 'published'], ['name' => 'draft'], ['name' => 'cancelled']]]],
            ],
            'pages' => [
                'Title'     => ['title' => $obj],
                'Slug'      => ['rich_text' => $obj],
                '模板'      => ['select' => $obj],
                '状态'      => ['status' => ['options' => [['name' => 'published'], ['name' => 'draft']]]],
                '描述'      => ['rich_text' => $obj],
            ],
            'skills' => [
                'Title'     => ['title' => $obj],
                'ID'        => ['rich_text' => $obj],
                '类型'      => ['select' => ['options' => [['name' => 'prompt'], ['name' => 'tool'], ['name' => 'workflow']]]],
                '状态'      => ['status' => ['options' => [['name' => 'published'], ['name' => 'draft']]]],
                '价格'      => ['number' => ['format' => 'yuan']],
                '标签'      => ['multi_select' => $obj],
                '描述'      => ['rich_text' => $obj],
            ],
        ];
        return $schemas[$type] ?? [];
    }

    // ─── OpenFlow → Notion 属性映射 ───

    public static function toNotion(string $type, array $item): array {
        switch ($type) {
            case 'navigation':
                return [
                    'Name'     => NotionClient::propTitle($item['name'] ?? ''),
                    'URL'      => NotionClient::propUrl($item['url'] ?? ''),
                    '分类'     => NotionClient::propSelect($item['category'] ?? $item['cat'] ?? ''),
                    '子分类'   => NotionClient::propRichText($item['sub'] ?? ''),
                    '描述'     => NotionClient::propRichText($item['description'] ?? $item['desc'] ?? ''),
                    '地区'     => NotionClient::propSelect($item['region'] ?? 'cn'),
                    '推荐'     => NotionClient::propCheckbox(!empty($item['featured'])),
                    '权重'     => NotionClient::propNumber($item['weight'] ?? 0),
                    '标签'     => NotionClient::propMultiSelect($item['tags'] ?? []),
                    '推荐理由' => NotionClient::propRichText($item['reason'] ?? ''),
                    '状态'     => NotionClient::propStatus($item['status'] ?? 'published'),
                ];
            case 'articles':
                return [
                    'Title'     => NotionClient::propTitle($item['title'] ?? ''),
                    'Slug'      => NotionClient::propRichText($item['slug'] ?? ''),
                    '分类'      => NotionClient::propSelect($item['category'] ?? ''),
                    '状态'      => NotionClient::propStatus($item['status'] ?? 'draft'),
                    'SEO Title' => NotionClient::propRichText($item['seo_title'] ?? ''),
                    'SEO Desc'  => NotionClient::propRichText($item['seo_desc'] ?? ''),
                    '标签'      => NotionClient::propMultiSelect($item['tags'] ?? []),
                ];
            case 'courses':
                return [
                    'Title'  => NotionClient::propTitle($item['title'] ?? ''),
                    'Slug'   => NotionClient::propRichText($item['slug'] ?? ''),
                    '类型'   => NotionClient::propSelect($item['type'] ?? '大课程'),
                    '状态'   => NotionClient::propStatus($item['status'] ?? 'draft'),
                    '价格'   => NotionClient::propNumber($item['price'] ?? 0),
                    '讲师'   => NotionClient::propRichText($item['instructor'] ?? ''),
                    '难度'   => NotionClient::propSelect($item['difficulty'] ?? 'beginner'),
                    '评分'   => NotionClient::propNumber($item['rating'] ?? 0),
                    '标签'   => NotionClient::propMultiSelect($item['tags'] ?? []),
                ];
            case 'events':
                return [
                    'Title' => NotionClient::propTitle($item['title'] ?? ''),
                    'Slug'  => NotionClient::propRichText($item['slug'] ?? ''),
                    '类型'  => NotionClient::propSelect($item['type'] ?? 'online'),
                    '日期'  => !empty($item['date']) ? NotionClient::propDate($item['date']) : null,
                    '地点'  => NotionClient::propRichText($item['location'] ?? ''),
                    '容量'  => NotionClient::propNumber($item['capacity'] ?? 0),
                    '状态'  => NotionClient::propStatus($item['status'] ?? 'draft'),
                ];
            case 'pages':
                return [
                    'Title' => NotionClient::propTitle($item['title'] ?? ''),
                    'Slug'  => NotionClient::propRichText($item['slug'] ?? ''),
                    '模板'  => NotionClient::propSelect($item['template'] ?? 'default'),
                    '状态'  => NotionClient::propStatus($item['status'] ?? 'draft'),
                    '描述'  => NotionClient::propRichText($item['desc'] ?? ''),
                ];
            case 'skills':
                return [
                    'Title' => NotionClient::propTitle($item['title'] ?? $item['name'] ?? ''),
                    'ID'    => NotionClient::propRichText($item['id'] ?? ''),
                    '类型'  => NotionClient::propSelect($item['type'] ?? 'prompt'),
                    '状态'  => NotionClient::propStatus($item['status'] ?? 'published'),
                    '价格'  => NotionClient::propNumber($item['price'] ?? 0),
                    '标签'  => NotionClient::propMultiSelect($item['tags'] ?? []),
                    '描述'  => NotionClient::propRichText($item['description'] ?? ''),
                ];
            default: return [];
        }
    }

    // ─── Notion → OpenFlow 数据映射 ───

    public static function fromNotion(string $type, array $page): array {
        $P = $page['properties'] ?? [];
        $id = $page['id'] ?? '';
        switch ($type) {
            case 'navigation':
                return [
                    'id'          => 'site_' . substr(str_replace('-', '', $id), 0, 12),
                    'name'        => NotionClient::getTitle($page),
                    'name_en'     => '',
                    'url'         => NotionClient::getUrl($P['URL'] ?? []),
                    'category'    => NotionClient::getSelect($P['分类'] ?? []),
                    'cat'         => NotionClient::getSelect($P['分类'] ?? []),
                    'sub'         => NotionClient::getRichText($P['子分类'] ?? []),
                    'description' => NotionClient::getRichText($P['描述'] ?? []),
                    'desc'        => NotionClient::getRichText($P['描述'] ?? []),
                    'region'      => NotionClient::getSelect($P['地区'] ?? []) ?: 'cn',
                    'featured'    => NotionClient::getCheckbox($P['推荐'] ?? []),
                    'weight'      => (int)NotionClient::getNumber($P['权重'] ?? []),
                    'tags'        => NotionClient::getMultiSelect($P['标签'] ?? []),
                    'reason'      => NotionClient::getRichText($P['推荐理由'] ?? []),
                    'status'      => NotionClient::getSelect($P['状态'] ?? []) ?: 'published',
                    'hits'        => 0,
                    'logo'        => '',
                    '_notion_id'  => $id,
                ];
            case 'articles':
                return [
                    'id'         => '',
                    'title'      => NotionClient::getTitle($page),
                    'slug'       => NotionClient::getRichText($P['Slug'] ?? []),
                    'category'   => NotionClient::getSelect($P['分类'] ?? []),
                    'status'     => NotionClient::getSelect($P['状态'] ?? []) ?: 'draft',
                    'seo_title'  => NotionClient::getRichText($P['SEO Title'] ?? []),
                    'seo_desc'   => NotionClient::getRichText($P['SEO Desc'] ?? []),
                    'tags'       => NotionClient::getMultiSelect($P['标签'] ?? []),
                    '_notion_id' => $id,
                ];
            case 'courses':
                return [
                    'id'         => '',
                    'title'      => NotionClient::getTitle($page),
                    'slug'       => NotionClient::getRichText($P['Slug'] ?? []),
                    'type'       => NotionClient::getSelect($P['类型'] ?? []),
                    'status'     => NotionClient::getSelect($P['状态'] ?? []) ?: 'draft',
                    'price'      => NotionClient::getNumber($P['价格'] ?? []),
                    'instructor' => NotionClient::getRichText($P['讲师'] ?? []),
                    'difficulty' => NotionClient::getSelect($P['难度'] ?? []),
                    'rating'     => NotionClient::getNumber($P['评分'] ?? []),
                    'tags'       => NotionClient::getMultiSelect($P['标签'] ?? []),
                    '_notion_id' => $id,
                ];
            case 'events':
                return [
                    'id'         => '',
                    'title'      => NotionClient::getTitle($page),
                    'slug'       => NotionClient::getRichText($P['Slug'] ?? []),
                    'type'       => NotionClient::getSelect($P['类型'] ?? []),
                    'date'       => NotionClient::getDateStart($P['日期'] ?? []),
                    'location'   => NotionClient::getRichText($P['地点'] ?? []),
                    'capacity'   => (int)NotionClient::getNumber($P['容量'] ?? []),
                    'status'     => NotionClient::getSelect($P['状态'] ?? []) ?: 'draft',
                    '_notion_id' => $id,
                ];
            case 'pages':
                return [
                    'slug'       => NotionClient::getRichText($P['Slug'] ?? []),
                    'title'      => NotionClient::getTitle($page),
                    'template'   => NotionClient::getSelect($P['模板'] ?? []) ?: 'default',
                    'status'     => NotionClient::getSelect($P['状态'] ?? []) ?: 'draft',
                    'desc'       => NotionClient::getRichText($P['描述'] ?? []),
                    '_notion_id' => $id,
                ];
            case 'skills':
                return [
                    'id'          => NotionClient::getRichText($P['ID'] ?? []),
                    'title'       => NotionClient::getTitle($page),
                    'name'        => NotionClient::getTitle($page),
                    'type'        => NotionClient::getSelect($P['类型'] ?? []) ?: 'prompt',
                    'status'      => NotionClient::getSelect($P['状态'] ?? []) ?: 'published',
                    'price'       => NotionClient::getNumber($P['价格'] ?? []),
                    'tags'        => NotionClient::getMultiSelect($P['标签'] ?? []),
                    'description' => NotionClient::getRichText($P['描述'] ?? []),
                    '_notion_id'  => $id,
                ];
            default: return [];
        }
    }

    // ─── 获取/创建 Notion 数据库 ───

    public static function ensureDatabase(NotionClient $nc, string $type): ?string {
        $cfg = self::config();
        $dbId = $cfg['databases'][$type] ?? '';
        if ($dbId) {
            // 验证数据库存在
            $r = $nc->getDatabase($dbId);
            if ($r['ok']) return $dbId;
        }
        // 自动创建：需要 parent_page_id
        $parentPageId = $cfg['parent_page_id'] ?? '';
        if (!$parentPageId) return null;
        $schema = self::dbSchema($type);
        if (!$schema) return null;
        $titles = [
            'navigation' => '🧭 导航站', 'articles' => '📝 文章', 'courses' => '📚 课程',
            'events' => '📅 活动', 'pages' => '📄 落地页', 'skills' => '⚡ 技能',
        ];
        $r = $nc->createDatabase($parentPageId, $titles[$type] ?? $type, $schema);
        if (!($r['ok'] ?? false)) return null;
        $newDbId = $r['data']['id'] ?? '';
        if ($newDbId) {
            $cfg['databases'][$type] = $newDbId;
            self::saveConfig($cfg);
        }
        return $newDbId ?: null;
    }

    // ─── 推送（OpenFlow → Notion）───

    /**
     * 推送所有数据到 Notion（增量：仅新增/变更）
     */
    public static function pushAll(string $type): array {
        $nc = self::client();
        if (!$nc) return ['ok' => false, 'error' => 'Notion 未配置'];
        $dbId = self::ensureDatabase($nc, $type);
        if (!$dbId) return ['ok' => false, 'error' => '无法创建数据库，请配置 parent_page_id'];
        $items = self::loadOpenFlowData($type);
        if (!$items) return ['ok' => false, 'error' => '无数据'];
        $state = self::state();
        $created = 0; $updated = 0; $errors = 0; $skipped = 0;
        foreach ($items as $item) {
            $ofId = $item['id'] ?? $item['slug'] ?? '';
            if (!$ofId) continue;
            $notionPageId = $state[$type][$ofId] ?? '';
            $props = self::toNotion($type, $item);
            // 清除 null 属性
            $props = array_filter($props, fn($v) => $v !== null);
            if ($notionPageId) {
                // 更新
                $r = $nc->updatePage($notionPageId, $props);
                if ($r['ok']) $updated++; else { $errors++; }
            } else {
                // 创建
                $r = $nc->createPage($dbId, $props);
                if ($r['ok']) {
                    $newId = $r['data']['id'] ?? '';
                    if ($newId) $state[$type][$ofId] = $newId;
                    $created++;
                } else {
                    $errors++;
                    if ($errors > 10) break; // 连续错误过多停止
                }
            }
            // 每 50 条保存一次状态
            if (($created + $updated) % 50 === 0) self::saveState($state);
        }
        self::saveState($state);
        return ['ok' => true, 'created' => $created, 'updated' => $updated, 'errors' => $errors, 'total' => count($items)];
    }

    // ─── 拉取（Notion → OpenFlow）───

    /**
     * 从 Notion 拉取数据到 OpenFlow
     */
    public static function pullAll(string $type): array {
        $nc = self::client();
        if (!$nc) return ['ok' => false, 'error' => 'Notion 未配置'];
        $cfg = self::config();
        $dbId = $cfg['databases'][$type] ?? '';
        if (!$dbId) return ['ok' => false, 'error' => '数据库未配置'];
        $r = $nc->queryDatabase($dbId);
        if (!($r['ok'] ?? false)) return ['ok' => false, 'error' => $r['error'] ?? '查询失败'];
        $pages = $r['results'] ?? [];
        $state = self::state();
        $created = 0; $updated = 0; $skipped = 0;
        foreach ($pages as $page) {
            $notionId = $page['id'] ?? '';
            if (!$notionId) continue;
            $item = self::fromNotion($type, $page);
            // 查找已有 OpenFlow 数据（通过 _notion_id 映射或 slug/id 匹配）
            $ofId = self::findOpenFlowId($type, $notionId, $item, $state);
            if ($ofId) {
                // 更新已有数据
                self::updateOpenFlowItem($type, $ofId, $item);
                $updated++;
            } else {
                // 创建新数据
                $newId = self::createOpenFlowItem($type, $item);
                if ($newId) {
                    $state[$type][$newId] = $notionId;
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }
        self::saveState($state);
        return ['ok' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'total' => count($pages)];
    }

    // ─── 数据加载 ───

    public static function loadOpenFlowData(string $type): array {
        switch ($type) {
            case 'navigation':
                $nav = json_read(DATA_DIR . '/navigation.json');
                return array_filter($nav['sites'] ?? [], fn($s) => ($s['status'] ?? 'published') === 'published');
            case 'articles': return get_articles();
            case 'courses':  return json_read(DATA_DIR . '/courses/index.json') ?: [];
            case 'events':   return json_read(DATA_DIR . '/events.json') ?: [];
            case 'pages':    return json_read(DATA_DIR . '/site-pages.json') ?: [];
            case 'skills':   return json_read(DATA_DIR . '/skills/index.json') ?: [];
            default: return [];
        }
    }

    // ─── OpenFlow 数据读写 ───

    private static function findOpenFlowId(string $type, string $notionId, array $item, array $state): ?string {
        // 1. 通过 state 映射
        foreach ($state[$type] ?? [] as $ofId => $nId) {
            if ($nId === $notionId) return $ofId;
        }
        // 2. 通过 slug 匹配
        $slug = $item['slug'] ?? '';
        if ($slug) {
            $items = self::loadOpenFlowData($type);
            foreach ($items as $it) {
                if (($it['slug'] ?? '') === $slug) return $it['id'] ?? $it['slug'] ?? null;
            }
        }
        // 3. 通过 name/url 匹配（导航站）
        $url = $item['url'] ?? '';
        if ($type === 'navigation' && $url) {
            $items = self::loadOpenFlowData($type);
            foreach ($items as $it) {
                if (($it['url'] ?? '') === $url) return $it['id'] ?? null;
            }
        }
        return null;
    }

    private static function createOpenFlowItem(string $type, array $item): ?string {
        switch ($type) {
            case 'navigation':
                $nav = json_read(DATA_DIR . '/navigation.json');
                $item['id'] = $item['id'] ?: 'site_' . substr(md5($item['url'] . microtime()), 0, 10);
                $item['created_at'] = date('Y-m-d H:i:s');
                $nav['sites'][] = $item;
                json_write(DATA_DIR . '/navigation.json', $nav);
                return $item['id'];
            case 'articles':
                $articles = json_read(DATA_DIR . '/articles/index.json');
                $item['id'] = $item['id'] ?: 'art_' . substr(md5($item['title'] . microtime()), 0, 10);
                $item['content'] = $item['content'] ?? '';
                $item['created_at'] = date('Y-m-d H:i:s');
                $item['updated_at'] = date('Y-m-d H:i:s');
                $articles[] = $item;
                json_write(DATA_DIR . '/articles/index.json', $articles);
                return $item['id'];
            case 'courses':
                $courses = json_read(DATA_DIR . '/courses/index.json');
                $item['id'] = $item['id'] ?: 'course_' . substr(md5($item['title'] . microtime()), 0, 10);
                $item['chapters'] = $item['chapters'] ?? [];
                $courses[] = $item;
                json_write(DATA_DIR . '/courses/index.json', $courses);
                return $item['id'];
            case 'events':
                $events = json_read(DATA_DIR . '/events.json');
                $item['id'] = $item['id'] ?: 'evt_' . substr(md5($item['title'] . microtime()), 0, 10);
                $events[] = $item;
                json_write(DATA_DIR . '/events.json', $events);
                return $item['id'];
            case 'pages':
                $pages = json_read(DATA_DIR . '/site-pages.json');
                $pages[] = $item;
                json_write(DATA_DIR . '/site-pages.json', $pages);
                return $item['slug'] ?? null;
            case 'skills':
                $skills = json_read(DATA_DIR . '/skills/index.json');
                $item['id'] = $item['id'] ?: 'sk_' . substr(md5($item['title'] . microtime()), 0, 10);
                $skills[] = $item;
                json_write(DATA_DIR . '/skills/index.json', $skills);
                return $item['id'];
        }
        return null;
    }

    private static function updateOpenFlowItem(string $type, string $ofId, array $newData): void {
        switch ($type) {
            case 'navigation':
                $nav = json_read(DATA_DIR . '/navigation.json');
                foreach ($nav['sites'] as &$s) {
                    if (($s['id'] ?? '') === $ofId) {
                        foreach (['name','name_en','url','category','cat','sub','description','desc','region','featured','weight','tags','reason','status'] as $k) {
                            if (isset($newData[$k]) && $newData[$k] !== '' && $newData[$k] !== []) $s[$k] = $newData[$k];
                        }
                        break;
                    }
                }
                json_write(DATA_DIR . '/navigation.json', $nav);
                break;
            case 'articles':
                $articles = json_read(DATA_DIR . '/articles/index.json');
                foreach ($articles as &$a) {
                    if (($a['id'] ?? '') === $ofId || ($a['slug'] ?? '') === $ofId) {
                        foreach (['title','slug','category','status','seo_title','seo_desc','tags'] as $k) {
                            if (isset($newData[$k]) && $newData[$k] !== '') $a[$k] = $newData[$k];
                        }
                        $a['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                json_write(DATA_DIR . '/articles/index.json', $articles);
                break;
            case 'courses':
                $courses = json_read(DATA_DIR . '/courses/index.json');
                foreach ($courses as &$c) {
                    if (($c['id'] ?? '') === $ofId) {
                        foreach (['title','slug','type','status','price','instructor','difficulty','rating','tags'] as $k) {
                            if (isset($newData[$k]) && $newData[$k] !== '') $c[$k] = $newData[$k];
                        }
                        break;
                    }
                }
                json_write(DATA_DIR . '/courses/index.json', $courses);
                break;
            case 'events':
                $events = json_read(DATA_DIR . '/events.json');
                foreach ($events as &$e) {
                    if (($e['id'] ?? '') === $ofId) {
                        foreach (['title','slug','type','date','location','capacity','status'] as $k) {
                            if (isset($newData[$k]) && $newData[$k] !== '') $e[$k] = $newData[$k];
                        }
                        break;
                    }
                }
                json_write(DATA_DIR . '/events.json', $events);
                break;
            case 'pages':
                $pages = json_read(DATA_DIR . '/site-pages.json');
                foreach ($pages as &$p) {
                    if (($p['slug'] ?? '') === $ofId) {
                        foreach (['title','template','status','desc'] as $k) {
                            if (isset($newData[$k]) && $newData[$k] !== '') $p[$k] = $newData[$k];
                        }
                        break;
                    }
                }
                json_write(DATA_DIR . '/site-pages.json', $pages);
                break;
            case 'skills':
                $skills = json_read(DATA_DIR . '/skills/index.json');
                foreach ($skills as &$s) {
                    if (($s['id'] ?? '') === $ofId) {
                        foreach (['title','name','type','status','price','tags','description'] as $k) {
                            if (isset($newData[$k]) && $newData[$k] !== '') $s[$k] = $newData[$k];
                        }
                        break;
                    }
                }
                json_write(DATA_DIR . '/skills/index.json', $skills);
                break;
        }
    }

    // ─── 同步状态概览 ───

    public static function status(): array {
        $cfg = self::config();
        $state = self::state();
        $nc = self::client();
        $types = ['navigation', 'articles', 'courses', 'events', 'pages', 'skills'];
        $result = [];
        foreach ($types as $t) {
            $items = self::loadOpenFlowData($t);
            $dbId = $cfg['databases'][$t] ?? '';
            $mapped = count($state[$t] ?? []);
            $result[$t] = [
                'local_count'   => count($items),
                'notion_db'     => $dbId ? '已配置' : '未配置',
                'mapped_count'  => $mapped,
                'sync_ready'    => !empty($dbId) && $nc !== null,
            ];
        }
        return $result;
    }
}
