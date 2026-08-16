<?php
/**
 * Page field groups — maps field names to named sections for the editor UI.
 * Each entry: [section_key => ['label' => 'Section Name', 'fields' => ['field1','field2']]]
 */
function page_field_groups(string $page): array {
    $groups = [
        'index' => [
            'hero' => ['label' => 'Hero 区域', 'icon' => '🏠', 'fields' => ['hero_title', 'hero_title_highlight', 'hero_subtitle', 'hero_chip', 'hero_trust_text', 'hero_trust_brands']],
            'problem' => ['label' => '问题陈述', 'icon' => '⚠️', 'fields' => ['problem_title', 'problem_description', 'problem_stat_1_num', 'problem_stat_1_desc', 'problem_stat_2_num', 'problem_stat_2_desc', 'problem_stat_3_num', 'problem_stat_3_desc']],
            'capability' => ['label' => '核心能力', 'icon' => '⚡', 'fields' => ['capability_title', 'capability_subtitle', 'capability_card_1_title', 'capability_card_1_desc', 'capability_card_2_title', 'capability_card_2_desc', 'capability_card_3_title', 'capability_card_3_desc']],
            'solutions' => ['label' => '分层方案', 'icon' => '📋', 'fields' => ['solutions_title', 'solutions_subtitle']],
            'cases' => ['label' => '客户案例', 'icon' => '📁', 'fields' => ['cases_title', 'cases_subtitle']],
            'data' => ['label' => '数据指标', 'icon' => '📊', 'fields' => ['data_count_1', 'data_label_1', 'data_count_2', 'data_label_2', 'data_count_3', 'data_label_3', 'data_count_4', 'data_label_4']],
            'vision' => ['label' => '愿景使命', 'icon' => '🎯', 'fields' => ['vision_title', 'vision_mission', 'vision_values']],
            'cta' => ['label' => 'CTA 行动号召', 'icon' => '📞', 'fields' => ['cta_title', 'cta_description', 'cta_phone']],
        ],
        'about' => [
            'founder' => ['label' => '创始人寄语', 'icon' => '👤', 'fields' => ['founder_quote', 'founder_intro', 'founder_middle', 'founder_approach', 'founder_belief', 'founder_vision', 'founder_name', 'founder_title']],
            'company' => ['label' => '公司简介', 'icon' => '🏢', 'fields' => ['company_intro', 'company_detail']],
            'vision' => ['label' => '使命愿景价值观', 'icon' => '🎯', 'fields' => ['mission', 'vision_1', 'vision_2', 'values']],
        ],
        'capability' => [
            'banner' => ['label' => '页面横幅', 'icon' => '📌', 'fields' => ['banner_title', 'banner_subtitle']],
            'content' => ['label' => '内容引擎 + SEO/GEO', 'icon' => '📝', 'fields' => ['content_title', 'content_subtitle', 'content_desc']],
            'courses' => ['label' => '课程体系', 'icon' => '📚', 'fields' => ['courses_title', 'courses_subtitle', 'courses_desc']],
            'platform' => ['label' => '智能平台', 'icon' => '💻', 'fields' => ['platform_title', 'platform_subtitle', 'platform_desc']],
        ],
        'courses' => [
            'banner' => ['label' => '页面横幅', 'icon' => '📌', 'fields' => ['banner_title', 'banner_subtitle']],
            'executive' => ['label' => '高管层', 'icon' => '👔', 'fields' => ['executive_title', 'executive_desc']],
            'leadership' => ['label' => '管理层', 'icon' => '👥', 'fields' => ['leadership_title', 'leadership_desc']],
            'employee' => ['label' => '员工层', 'icon' => '👤', 'fields' => ['employee_title', 'employee_desc']],
            'newcomer' => ['label' => '新员工', 'icon' => '🌟', 'fields' => ['newcomer_title', 'newcomer_desc']],
        ],
        'flow-community' => [
            'hero' => ['label' => 'Hero 区域', 'icon' => '🌐', 'fields' => ['hero_title', 'hero_subtitle']],
        ],
    ];
    return $groups[$page] ?? [];
}
