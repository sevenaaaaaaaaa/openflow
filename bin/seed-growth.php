<?php
/**
 * OpenFlow 增长主题种子数据
 * 补充空模块：课程 / 播客 / 活动 / 咨询师（网站增长方向）
 *
 * 用法：php bin/seed-growth.php [--force]
 * 安全：模块已有数据时跳过，除非 --force
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ConsultationSystem.php';

$force = in_array('--force', $argv ?? [], true);

/* ─── 课程 ─── */
$coursesFile = DATA_DIR . '/courses/index.json';
$courses = json_read($coursesFile);
if (empty($courses) || $force) {
    $now = date('Y-m-d H:i:s');
    $courses = [
        [
            'id' => 'course_growth_os', 'title' => '网站增长操作系统 · 大师课', 'slug' => 'growth-os-masterclass',
            'type' => '大课程', 'status' => 'published', 'description' => '从 0 到 1 构建自动增长体系：内容、获客、转化、洞察全链路。',
            'category' => 'big', 'cover' => '', 'price' => 299, 'original_price' => 499,
            'duration' => '12 小时', 'instructor' => 'Seven', 'difficulty' => 'beginner', 'mode' => 'recorded',
            'rating' => 4.8, 'students' => 128, 'tags' => ['增长', '系统', '入门'],
            'chapters' => [
                ['id' => 'ch1', 'title' => '第一章 · 增长的本质', 'lessons' => [
                    ['id' => 'l1', 'title' => '什么是增长操作系统', 'duration' => '18:00'],
                    ['id' => 'l2', 'title' => '增长飞轮模型', 'duration' => '22:00'],
                    ['id' => 'l3', 'title' => '设定增长目标', 'duration' => '15:00'],
                ]],
                ['id' => 'ch2', 'title' => '第二章 · 内容引擎', 'lessons' => [
                    ['id' => 'l4', 'title' => '选题方法论', 'duration' => '20:00'],
                    ['id' => 'l5', 'title' => 'SEO 友好写作', 'duration' => '25:00'],
                ]],
            ],
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 'course_content_growth', 'title' => '内容驱动增长 · 系列课', 'slug' => 'content-driven-growth',
            'type' => '系列课', 'status' => 'published', 'description' => '选题、创作、分发、复盘四讲，把内容变成增长引擎。',
            'category' => 'series', 'cover' => '', 'price' => 129, 'original_price' => 199,
            'duration' => '6 小时', 'instructor' => 'Seven', 'difficulty' => 'intermediate', 'mode' => 'recorded',
            'rating' => 4.6, 'students' => 86, 'tags' => ['内容', '写作', '增长'],
            'chapters' => [
                ['id' => 'ch1', 'title' => '第一讲 · 选题', 'lessons' => [
                    ['id' => 'l1', 'title' => '找到用户的真实问题', 'duration' => '20:00'],
                    ['id' => 'l2', 'title' => '关键词选题法', 'duration' => '18:00'],
                ]],
                ['id' => 'ch2', 'title' => '第二讲 · 创作', 'lessons' => [
                    ['id' => 'l3', 'title' => '结构化的表达', 'duration' => '22:00'],
                ]],
            ],
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 'course_cdp_live', 'title' => 'CDP 实战 · 直播训练营', 'slug' => 'cdp-practice-live',
            'type' => '直播课', 'status' => 'published', 'description' => '直播 + 实操，导师带练 CDP 用户画像与分群。',
            'category' => 'live', 'cover' => '', 'price' => 199, 'original_price' => 299,
            'duration' => '4 小时', 'instructor' => '增长教练', 'difficulty' => 'intermediate', 'mode' => 'live',
            'rating' => 4.7, 'students' => 54, 'tags' => ['CDP', '直播', '数据'],
            'chapters' => [],
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 'course_free_start', 'title' => '免费资源 · 网站增长入门', 'slug' => 'free-growth-starter',
            'type' => '课程', 'status' => 'published', 'description' => '免费入门课：了解网站增长的基本概念与工具。',
            'category' => 'free', 'cover' => '', 'price' => 0, 'original_price' => 0,
            'duration' => '1.5 小时', 'instructor' => 'OpenFlow', 'difficulty' => 'beginner', 'mode' => 'recorded',
            'rating' => 4.5, 'students' => 210, 'tags' => ['免费', '入门', '增长'],
            'chapters' => [
                ['id' => 'ch1', 'title' => '增长是什么', 'lessons' => [
                    ['id' => 'l1', 'title' => '增长 ≠ 营销', 'duration' => '12:00'],
                    ['id' => 'l2', 'title' => '三件套：内容/获客/转化', 'duration' => '15:00'],
                ]],
            ],
            'created_at' => $now, 'updated_at' => $now,
        ],
    ];
    json_write($coursesFile, $courses);
    echo "✅ 已生成 " . count($courses) . " 门课程\n";
} else {
    echo "⏭️  课程已有数据（" . count($courses) . " 门），跳过\n";
}

/* ─── 播客 ─── */
$podFile = DATA_DIR . '/podcasts.json';
$pods = json_read($podFile);
if (empty($pods['items']) || $force) {
    $now = date('Y-m-d');
    $pods = [
        'categories' => ['增长访谈', '实战案例', '工具评测', 'AI 前沿'],
        'items' => [
            ['id' => 'pod_001', 'title' => '第 1 期：网站增长的底层逻辑', 'type' => 'audio', 'file' => '', 'cover' => '',
             'description' => '聊聊增长飞轮：内容、获客、转化如何互相咬合。', 'category' => '增长访谈',
             'duration' => '35:20', 'pub_date' => $now, 'featured' => true, 'status' => 'published', 'episode' => 1,
             'tags' => ['增长', '方法论'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 'pod_002', 'title' => '第 2 期：SEO 从入门到排名', 'type' => 'audio', 'file' => '', 'cover' => '',
             'description' => '一位独立站长的 SEO 实战复盘。', 'category' => '实战案例',
             'duration' => '42:10', 'pub_date' => $now, 'featured' => true, 'status' => 'published', 'episode' => 2,
             'tags' => ['SEO', '案例'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 'pod_003', 'title' => '第 3 期：CDP 工具横评', 'type' => 'video', 'file' => '', 'cover' => '',
             'description' => '主流 CDP 工具对比，帮你选对工具。', 'category' => '工具评测',
             'duration' => '28:45', 'pub_date' => $now, 'featured' => false, 'status' => 'published', 'episode' => 3,
             'tags' => ['CDP', '工具'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
        ],
    ];
    json_write($podFile, $pods);
    echo "✅ 已生成 " . count($pods['items']) . " 期播客\n";
} else {
    echo "⏭️  播客已有数据（" . count($pods['items'] ?? []) . " 期），跳过\n";
}

/* ─── 活动 ─── */
$eventsFile = DATA_DIR . '/events/index.json';
$events = json_read($eventsFile);
if (empty($events) || $force) {
    $now = date('Y-m-d H:i:s');
    $events = [
        ['id' => 'evt_1', 'title' => '网站增长工作坊 · 线上', 'slug' => 'growth-workshop-online',
         'description' => '半天线上工作坊，带你跑通增长飞轮。', 'content' => '',
         'start_date' => date('Y-m-d H:i:s', time() + 7 * 86400), 'end_date' => date('Y-m-d H:i:s', time() + 7 * 86400 + 4 * 3600),
         'location' => '线上直播', 'location_url' => '', 'speakers' => [], 'gallery' => [], 'video_url' => '', 'cover' => '',
         'registration_form' => '', 'registration_url' => '', 'status' => 'published',
         'seo_title' => '', 'seo_desc' => '', 'created_at' => $now],
        ['id' => 'evt_2', 'title' => 'AI 增长工具 · 直播演示', 'slug' => 'ai-growth-tools-demo',
         'description' => '演示如何用 AI 工具提升内容生产效率。', 'content' => '',
         'start_date' => date('Y-m-d H:i:s', time() + 14 * 86400), 'end_date' => date('Y-m-d H:i:s', time() + 14 * 86400 + 2 * 3600),
         'location' => '线上直播', 'location_url' => '', 'speakers' => [], 'gallery' => [], 'video_url' => '', 'cover' => '',
         'registration_form' => '', 'registration_url' => '', 'status' => 'published',
         'seo_title' => '', 'seo_desc' => '', 'created_at' => $now],
    ];
    json_write($eventsFile, $events);
    echo "✅ 已生成 " . count($events) . " 场活动\n";
} else {
    echo "⏭️  活动已有数据（" . count($events) . " 场），跳过\n";
}

/* ─── 咨询师 ─── */
$mentors = con_mentors();
if (empty($mentors) || $force) {
    $mentorData = [
        ['id' => 'mentor_seven', 'name' => 'Seven', 'title' => '网站增长顾问', 'avatar' => '',
         'intro' => '专注网站增长 8 年，服务过 50+ 内容与 SaaS 站点。', 'specialties' => ['增长策略', '内容运营', 'SEO'],
         'price' => 500, 'duration' => '60 分钟', 'rep_courses' => ['course_growth_os'], 'available' => true, 'sort' => 1,
         'stats' => ['sessions' => 32, 'rating' => 4.9, 'rating_count' => 20]],
        ['id' => 'mentor_lin', 'name' => '林老师', 'title' => '转化率优化专家', 'avatar' => '',
         'intro' => 'CRO 专家，擅长落地页与漏斗优化。', 'specialties' => ['转化优化', 'A/B 测试', '落地页'],
         'price' => 600, 'duration' => '60 分钟', 'rep_courses' => ['course_content_growth'], 'available' => true, 'sort' => 2,
         'stats' => ['sessions' => 18, 'rating' => 4.8, 'rating_count' => 12]],
        ['id' => 'mentor_wang', 'name' => '王老师', 'title' => '数据分析顾问', 'avatar' => '',
         'intro' => 'CDP 与数据分析专家，帮你读懂用户。', 'specialties' => ['CDP', '数据分析', '用户画像'],
         'price' => 450, 'duration' => '60 分钟', 'rep_courses' => ['course_cdp_live'], 'available' => true, 'sort' => 3,
         'stats' => ['sessions' => 25, 'rating' => 4.7, 'rating_count' => 15]],
    ];
    foreach ($mentorData as $m) con_mentor_save($m);
    echo "✅ 已生成 " . count($mentorData) . " 位咨询师\n";
} else {
    echo "⏭️  咨询师已有数据（" . count($mentors) . " 位），跳过\n";
}

echo "\n🎉 增长主题种子数据完成！\n";
