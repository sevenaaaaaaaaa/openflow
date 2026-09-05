<?php
/**
 * CdpDefaults —— CDP 默认规则真源（P0：让深引擎开箱即用）
 *
 * 【为什么有它】CdpSystem 的深规则引擎（分群/自动打标/评分/生命周期）已经写好，
 * 但 `data/cdp/{segments,tag_rules,scoring_rules,lifecycle}.json` 全部不存在/为空，
 * 导致 `evaluateUserSegments` 因 empty() 直接返回、autoTag 空转、getHealthScore 走回退——
 * 即「引擎很深但配置全空，开箱即用不生效」。
 *
 * 本文件提供**内置默认规则**：引擎读取到空/缺文件时回退到这里，任何新部署都开箱即用；
 * 运营在后台配置后，data 文件覆盖默认（语义仍是「空则默认，有则自定义」）。
 *
 * 与 data/cdp/*.json 的 schema 完全一致（见 CdpSystem::evaluateUserSegments / autoTag /
 * getHealthScore / applyLifecycle），此处只是它们的「默认值」。
 */

if (!function_exists('cdp_default_lifecycle')) {

/** 默认生命周期阈值（applyLifecycle 读取结构：new_days/active_days/dormant_days/churned_days） */
function cdp_default_lifecycle(): array {
    return [
        'new_days'    => 7,
        'active_days' => 7,
        'dormant_days'=> 30,
        'churned_days'=> 90,
        'description' => '生命周期阶段：new(≤7天) / active(7天内活跃) / dormant(≤30天) / at_risk(≤90天) / churned(>90天)',
    ];
}

/** 默认自动打标规则（autoTag 读取结构：rid => {enabled, tag, when:{type,event,field,operator,value}}） */
function cdp_default_tag_rules(): array {
    return [
        'high_intent'   => ['enabled'=>true, 'tag'=>'高意向',
            'when'=>['type'=>'event', 'event'=>'pricing_view', 'operator'=>'gte', 'value'=>1],
            'description'=>'看过定价页 → 打「高意向」标签（可配置触发事件）'],
        'return_visitor'=> ['enabled'=>true, 'tag'=>'回访客',
            'when'=>['type'=>'property', 'field'=>'return_visit', 'operator'=>'gt', 'value'=>0],
            'description'=>'有回访行为 → 打「回访客」标签'],
        'form_submitter'=> ['enabled'=>true, 'tag'=>'留资',
            'when'=>['type'=>'event', 'event'=>'form_submitted', 'operator'=>'gte', 'value'=>1],
            'description'=>'提交过表单 → 打「留资」标签'],
        'purchaser'     => ['enabled'=>true, 'tag'=>'已付费',
            'when'=>['type'=>'summary', 'field'=>'purchase_amount_total', 'operator'=>'gt', 'value'=>0],
            'description'=>'累计消费>0 → 打「已付费」标签'],
        'heavy_user'    => ['enabled'=>true, 'tag'=>'重度用户',
            'when'=>['type'=>'summary', 'field'=>'page_views_30d', 'operator'=>'gte', 'value'=>30],
            'description'=>'30天浏览≥30次 → 打「重度用户」标签'],
        'sleeping'      => ['enabled'=>true, 'tag'=>'沉睡',
            'when'=>['type'=>'lifecycle', 'field'=>'stage', 'operator'=>'in', 'value'=>['dormant','churned']],
            'description'=>'进入沉睡/流失阶段 → 打「沉睡」标签'],
        'high_value'    => ['enabled'=>true, 'tag'=>'高价值',
            'when'=>['type'=>'summary', 'field'=>'purchase_amount_total', 'operator'=>'gte', 'value'=>1000],
            'description'=>'累计消费≥1000 → 打「高价值」标签'],
    ];
}

/**
 * 默认评分规则（getHealthScore 读取结构：{health:{cap, recency:{buckets:[{lte_days,points,else}]},
 *   frequency:{buckets:[{gte_events,points,else}]}, tags:{tags:[{tag,points}]}}）
 * 同时供 RFM 用（getRFMAnalysis 读同一文件）。
 */
function cdp_default_scoring_rules(): array {
    return [
        'health' => [
            'cap' => 100,
            // 近度分桶：近 1 天 40，7 天 25，30 天 10，否则 0
            'recency' => ['buckets' => [
                ['lte_days'=>1,  'points'=>40, 'else'=>0],
                ['lte_days'=>7,  'points'=>25, 'else'=>0],
                ['lte_days'=>30, 'points'=>10, 'else'=>0],
            ]],
            // 频率分桶：事件 ≥30 得 40，≥10 得 25，≥1 得 10，否则 0
            'frequency' => ['buckets' => [
                ['gte_events'=>30, 'points'=>40, 'else'=>0],
                ['gte_events'=>10, 'points'=>25, 'else'=>0],
                ['gte_events'=>1,  'points'=>10, 'else'=>0],
            ]],
            // 标签加成
            'tags' => ['tags' => [
                ['tag'=>'已付费',   'points'=>10],
                ['tag'=>'高价值',   'points'=>10],
                ['tag'=>'重度用户', 'points'=>10],
            ]],
        ],
        // RFM 评分（修「恒=1」的 bug：此前无 rfm 键，scoreRecency/Frequency/Monetary 全走 else→1）
        'rfm' => [
            // R：近度（lte_days 越小分越高）
            'r' => [
                ['lte_days'=>1,  'score'=>5],
                ['lte_days'=>7,  'score'=>4],
                ['lte_days'=>30, 'score'=>3],
                ['lte_days'=>90, 'score'=>2],
                ['else'=>1],
            ],
            // F：频率（累计事件数 gte 越大分越高）
            'f' => [
                ['gte'=>100, 'score'=>5],
                ['gte'=>50,  'score'=>4],
                ['gte'=>20,  'score'=>3],
                ['gte'=>5,   'score'=>2],
                ['else'=>1],
            ],
            // M：金额（累计消费 gte 越大分越高）
            'm' => [
                ['gte'=>1000, 'score'=>5],
                ['gte'=>500,  'score'=>4],
                ['gte'=>100,  'score'=>3],
                ['gte'=>10,   'score'=>2],
                ['else'=>1],
            ],
        ],
    ];
}

/**
 * 默认预设分群（evaluateUserSegments 读取结构：{id, name, description, rules:[{type,field,operator,value,event,window}], operator}）
 * 用 CdpSystem 深规则 schema（type: property/event/summary/lifecycle/tag/segment）。
 */
function cdp_default_segments(): array {
    $now = date('Y-m-d H:i:s');
    return [
        ['id'=>'seg_high_intent', 'name'=>'高意向线索', 'description'=>'看过定价页且有留资行为的高意向用户',
         'operator'=>'and', 'rules'=>[
            ['type'=>'event', 'event'=>'pricing_view', 'operator'=>'gte', 'value'=>1, 'window'=>0],
            ['type'=>'event', 'event'=>'form_submitted', 'operator'=>'gte', 'value'=>1, 'window'=>0],
         ], 'created_at'=>$now, 'updated_at'=>$now],
        ['id'=>'seg_active_30', 'name'=>'近30天活跃', 'description'=>'近30天有访问行为的用户',
         'operator'=>'and', 'rules'=>[
            ['type'=>'last_seen', 'operator'=>'lte', 'value'=>30],
         ], 'created_at'=>$now, 'updated_at'=>$now],
        ['id'=>'seg_high_value', 'name'=>'高价值用户', 'description'=>'累计消费≥1000的付费用户',
         'operator'=>'and', 'rules'=>[
            ['type'=>'summary', 'field'=>'purchase_amount_total', 'operator'=>'gte', 'value'=>1000],
         ], 'created_at'=>$now, 'updated_at'=>$now],
        ['id'=>'seg_risk_churn', 'name'=>'流失风险', 'description'=>'30天未活跃但曾多次访问的用户',
         'operator'=>'and', 'rules'=>[
            ['type'=>'last_seen', 'operator'=>'gt', 'value'=>30],
            ['type'=>'summary', 'field'=>'sessions_count', 'operator'=>'gte', 'value'=>3],
         ], 'created_at'=>$now, 'updated_at'=>$now],
        ['id'=>'seg_new_user', 'name'=>'新用户', 'description'=>'注册/首访不超过7天',
         'operator'=>'and', 'rules'=>[
            ['type'=>'first_seen', 'operator'=>'lte', 'value'=>7],
         ], 'created_at'=>$now, 'updated_at'=>$now],
        ['id'=>'seg_has_purchased', 'name'=>'已付费用户', 'description'=>'有成交记录的用户',
         'operator'=>'and', 'rules'=>[
            ['type'=>'summary', 'field'=>'purchase_amount_total', 'operator'=>'gt', 'value'=>0],
         ], 'created_at'=>$now, 'updated_at'=>$now],
    ];
}

/** 全部默认配置（传给引擎/seed 用） */
function cdp_defaults_all(): array {
    return [
        'lifecycle'     => cdp_default_lifecycle(),
        'tag_rules'     => cdp_default_tag_rules(),
        'scoring_rules' => cdp_default_scoring_rules(),
        'segments'      => cdp_default_segments(),
    ];
}

}
