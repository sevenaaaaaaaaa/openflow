<?php
/** Golden Loop sandbox: synthetic high-intent lead -> sale scenario, no production I/O. */

require_once __DIR__ . '/LoopRuntime.php';

if (!function_exists('golden_lead_sandbox_run')) {
    function golden_lead_sandbox_weights(): array {
        return [
            'pricing_view'=>30, 'consultation_request'=>50, 'form_submit'=>35,
            'return_visit'=>15, 'email_click'=>10, 'case_study_view'=>20,
        ];
    }

    function golden_lead_sandbox_score(array $lead): array {
        $score = 0; $evidence = [];
        foreach (golden_lead_sandbox_weights() as $signal=>$weight) {
            $count = max(0, (int)($lead['signals'][$signal] ?? 0));
            if ($count < 1) continue;
            $contribution = min($weight * $count, $weight * 2);
            $score += $contribution;
            $evidence[] = ['signal'=>$signal, 'count'=>$count, 'weight'=>$weight, 'contribution'=>$contribution];
        }
        $consent = ($lead['consent'] ?? '') === 'granted';
        $suppressed = (bool)($lead['suppressed'] ?? false);
        $eligible = $consent && !$suppressed;
        return ['score'=>min(100, $score), 'eligible'=>$eligible, 'evidence'=>$evidence,
            'blocked_reason'=>!$consent ? 'consent_missing' : ($suppressed ? 'suppressed' : '')];
    }

    function golden_lead_sandbox_fixture(): array {
        return [
            'dataset'=>['kind'=>'synthetic', 'label'=>'GOLDEN_LOOP_SANDBOX_V1', 'production_data'=>false],
            'leads'=>[
                ['id'=>'SYNTH-HIGH-001','consent'=>'granted','suppressed'=>false,'signals'=>['pricing_view'=>2,'return_visit'=>2,'consultation_request'=>1],'expected_high_intent'=>true,'expected_sale'=>true],
                ['id'=>'SYNTH-WARM-002','consent'=>'granted','suppressed'=>false,'signals'=>['case_study_view'=>1,'email_click'=>1],'expected_high_intent'=>false,'expected_sale'=>false],
                ['id'=>'SYNTH-BLOCKED-003','consent'=>'granted','suppressed'=>true,'signals'=>['pricing_view'=>2,'form_submit'=>1],'expected_high_intent'=>false,'expected_sale'=>false],
                ['id'=>'SYNTH-HIGH-004','consent'=>'granted','suppressed'=>false,'signals'=>['pricing_view'=>1,'form_submit'=>1,'return_visit'=>1],'expected_high_intent'=>true,'expected_sale'=>false],
            ],
        ];
    }

    /** Deterministic scenario evaluation. No reads or writes outside the passed fixture. */
    function golden_lead_sandbox_run(?array $fixture = null, int $threshold = 70): array {
        $fixture = $fixture ?? golden_lead_sandbox_fixture();
        if (($fixture['dataset']['kind'] ?? '') !== 'synthetic' || ($fixture['dataset']['production_data'] ?? true) !== false) {
            return ['ok'=>false, 'mode'=>'sandbox', 'error'=>'synthetic_dataset_required', 'side_effects'=>false];
        }
        $threshold = max(1, min(100, $threshold));
        $rows=[]; $tp=0;$fp=0;$fn=0;$tn=0;$sales=0;$eligibleHigh=0;
        foreach (array_values((array)($fixture['leads'] ?? [])) as $lead) {
            $scored = golden_lead_sandbox_score($lead);
            $predicted = $scored['eligible'] && $scored['score'] >= $threshold;
            $expected = (bool)($lead['expected_high_intent'] ?? false);
            if($predicted&&$expected)$tp++;elseif($predicted&&!$expected)$fp++;elseif(!$predicted&&$expected)$fn++;else$tn++;
            if($predicted)$eligibleHigh++;
            if($predicted && ($lead['expected_sale']??false))$sales++;
            $rows[]=[
                'subject_id'=>(string)($lead['id']??''), 'score'=>$scored['score'], 'threshold'=>$threshold,
                'eligible'=>$scored['eligible'], 'predicted_high_intent'=>$predicted,
                'blocked_reason'=>$scored['blocked_reason'], 'evidence'=>$scored['evidence'],
                'proposed_action'=>$predicted ? ['action_type'=>'add_tag','params'=>['tag'=>'高意向'],'status'=>'sandbox_only','requires_review'=>true] : null,
            ];
        }
        $precision=($tp+$fp)>0?$tp/($tp+$fp):null; $recall=($tp+$fn)>0?$tp/($tp+$fn):null;
        $conversion=$eligibleHigh>0?$sales/$eligibleHigh:null;
        return [
            'ok'=>true, 'mode'=>'sandbox', 'side_effects'=>false, 'dataset'=>$fixture['dataset'],
            'scenario'=>'high_intent_lead_to_sale', 'threshold'=>$threshold, 'subjects'=>$rows,
            'tips'=>[
                'Touch'=>['rule'=>'读取价格页、案例、回访、点击、表单和咨询等合成触点'],
                'Insight'=>['rule'=>'按公开权重计算意向分；保留逐项证据，不使用模型自评'],
                'Personalize'=>['rule'=>'仅为达到阈值且具备同意、未被抑制的对象准备高意向标签'],
                'Sell'=>['rule'=>'只生成待审核销售跟进建议；沙盘不触达、不报价、不成交记账'],
            ],
            'metrics'=>[
                'sample_size'=>count($rows),'true_positive'=>$tp,'false_positive'=>$fp,'false_negative'=>$fn,'true_negative'=>$tn,
                'precision'=>$precision,'recall'=>$recall,'simulated_conversion_rate'=>$conversion,
                'wrong_contact_rate'=>count($rows)>0?$fp/count($rows):0,
            ],
            'stop'=>['reached'=>true,'reason'=>'single_sandbox_iteration_complete'],
            'production_write_attempts'=>0,
        ];
    }
}
