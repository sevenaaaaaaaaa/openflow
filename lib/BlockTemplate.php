<?php
/**
 * BlockTemplate — 模块组合模板（一个模板 = 一组成套区块，一次插入）
 *
 * 补上模块工厂最后一块：变体是"单个模块换个皮"，组合模板是"把多个区块按版式成套插入"。
 * 内置若干业务模板（课程招生页/活动报名页/产品发布页/预约诊断页）开箱即用，
 * 也支持把现有落地页的区块另存为模板、导出导入。
 */

require_once __DIR__ . '/BuilderPages.php';

function btpl_file(): string { return DATA_DIR . '/block-templates.json'; }

function btpl_stored(): array {
    $d = json_read(btpl_file());
    return is_array($d) ? array_values($d) : [];
}

function btpl_all(): array {
    // 内置种子 + 用户模板
    return array_merge(btpl_seeds(), btpl_stored());
}

function btpl_get(string $id): ?array {
    foreach (btpl_all() as $t) if (($t['id'] ?? '') === $id) return $t;
    return null;
}

function btpl_is_seed(string $id): bool { return strpos($id, 'seed_') === 0; }

function btpl_save(array $t): array {
    $name = trim((string)($t['name'] ?? ''));
    $blocks = array_values((array)($t['blocks'] ?? []));
    if ($name === '') return ['ok' => false, 'error' => '模板名必填'];
    if (!$blocks) return ['ok' => false, 'error' => '至少包含一个区块'];
    $id = preg_replace('/[^a-z0-9_-]/', '', (string)($t['id'] ?? ''));
    if ($id === '' || btpl_is_seed($id)) $id = 'tpl_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 4);
    $row = ['id' => $id, 'name' => mb_substr($name, 0, 60), 'category' => mb_substr(trim((string)($t['category'] ?? '')), 0, 30),
            'description' => mb_substr(trim((string)($t['description'] ?? '')), 0, 120),
            'blocks' => $blocks, 'updated_at' => date('Y-m-d H:i:s')];
    $list = btpl_stored();
    $found = false;
    foreach ($list as &$x) if (($x['id'] ?? '') === $id) { $x = $row; $found = true; break; }
    unset($x);
    if (!$found) $list[] = $row;
    json_write(btpl_file(), $list);
    return ['ok' => true, 'template' => $row];
}

function btpl_delete(string $id): bool {
    if (btpl_is_seed($id)) return false;
    $list = array_values(array_filter(btpl_stored(), fn($t) => ($t['id'] ?? '') !== $id));
    return json_write(btpl_file(), $list);
}

/** 把模板区块插入到落地页末尾（新 key，幂等不适用——每次插入都是新增） */
function btpl_apply(string $pageId, string $tplId): array {
    $tpl = btpl_get($tplId);
    if (!$tpl) return ['ok' => false, 'error' => '模板不存在'];
    $page = builder_page_get($pageId);
    if (!$page) return ['ok' => false, 'error' => '页面不存在'];
    $blocks = (array)($page['blocks'] ?? []);
    foreach ((array)$tpl['blocks'] as $b) {
        if (!is_array($b)) continue;
        $b['_key'] = function_exists('block_new_key') ? block_new_key() : ('b_' . bin2hex(random_bytes(5)));
        $blocks[] = $b;
    }
    $page['blocks'] = $blocks;
    $id = save_builder_page($pageId, $page);
    return $id !== '' ? ['ok' => true, 'added' => count($tpl['blocks'])] : ['ok' => false, 'error' => '写入失败'];
}

/** 内置业务模板 */
function btpl_seeds(): array {
    $b = fn(string $type, array $f = []) => array_merge(['_type' => $type], $f);
    return [
        ['id' => 'seed_course', 'name' => '课程招生页', 'category' => '课程', 'description' => 'Hero + 亮点 + 数据 + 证言 + 价格 + FAQ + CTA', 'blocks' => [
            $b('hero', ['title' => '{课程名}｜系统学完就能上手', 'subtitle' => '课程', 'content' => '从 0 到 1 掌握，配套练习与答疑。', 'button_text' => '立即报名', 'button_url' => '#enroll']),
            $b('features', ['title' => '你会学到', 'content' => '<ul><li>核心方法</li><li>实战案例</li><li>可复用模板</li></ul>']),
            $b('stats', ['title' => '往期成果', 'items' => '学员 1200+|完课率 68%|好评 4.9']),
            $b('testimonials', ['title' => '学员怎么说', 'content' => '“跟着做，第一次就拿到了结果。”']),
            $b('pricing', ['title' => '报名价格', 'content' => '限时优惠，支持分期']),
            $b('faq', ['title' => '常见问题', 'content' => '适合零基础吗？|会更新吗？|能开发票吗？']),
            $b('cta', ['title' => '现在就加入', 'content' => '名额有限，先到先得。', 'button_text' => '立即报名', 'button_url' => '#enroll']),
        ]],
        ['id' => 'seed_event', 'name' => '活动报名页', 'category' => '活动', 'description' => 'Hero + 介绍 + 图集 + 报名 + FAQ + CTA', 'blocks' => [
            $b('hero', ['title' => '{活动名}', 'subtitle' => '活动', 'content' => '时间 · 地点 · 嘉宾', 'button_text' => '立即报名', 'button_url' => '#register']),
            $b('text', ['title' => '活动介绍', 'content' => '这场活动你会收获什么。']),
            $b('gallery', ['title' => '往期回顾', 'content' => '']),
            $b('form', ['title' => '报名信息', 'content' => '留下联系方式，我们拉你进群。']),
            $b('faq', ['title' => '常见问题', 'content' => '可以退款吗？|需要带什么？']),
            $b('cta', ['title' => '别错过', 'button_text' => '立即报名', 'button_url' => '#register']),
        ]],
        ['id' => 'seed_product', 'name' => '产品发布页', 'category' => '产品', 'description' => 'Hero + 图文 + 功能 + 数据 + 证言 + CTA', 'blocks' => [
            $b('hero', ['title' => '{产品名} 正式发布', 'subtitle' => '全新版本', 'content' => '一句话说清它解决什么问题。', 'button_text' => '免费试用', 'button_url' => '#try']),
            $b('image-text', ['title' => '它是什么', 'content' => '产品价值与使用场景。', 'image' => '']),
            $b('features', ['title' => '核心能力', 'content' => '<ul><li>能力一</li><li>能力二</li><li>能力三</li></ul>']),
            $b('stats', ['title' => '数据', 'items' => '用户 5000+|节省 60% 时间|NPS 62']),
            $b('testimonials', ['title' => '用户在说', 'content' => '“终于不用来回复制了。”']),
            $b('cta', ['title' => '现在开始', 'button_text' => '免费试用', 'button_url' => '#try']),
        ]],
        ['id' => 'seed_consult', 'name' => '预约诊断页', 'category' => '服务', 'description' => 'Hero + 痛点 + 证言 + 表单 + FAQ + CTA', 'blocks' => [
            $b('hero', ['title' => '免费诊断你的网站增长', 'subtitle' => '1v1', 'content' => '30 分钟，找出最影响转化的 3 个问题。', 'button_text' => '预约诊断', 'button_url' => '#book']),
            $b('features', ['title' => '我们会帮你看', 'content' => '<ul><li>流量结构</li><li>转化漏斗</li><li>内容与 SEO</li></ul>']),
            $b('testimonials', ['title' => '客户反馈', 'content' => '“一次诊断就定位到了问题。”']),
            $b('contact', ['title' => '留下联系方式', 'content' => '']),
            $b('faq', ['title' => '常见问题', 'content' => '收费吗？|需要准备什么？']),
            $b('cta', ['title' => '预约你的诊断', 'button_text' => '预约诊断', 'button_url' => '#book']),
        ]],
    ];
}
