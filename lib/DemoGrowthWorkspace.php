<?php
/** Isolated, deterministic demo workspace. Never reads or writes production customer stores. */
require_once __DIR__ . '/GrowthBrain.php';
require_once __DIR__ . '/GrowthSignal.php';
require_once __DIR__ . '/GoldenLeadLoopSandbox.php';
require_once __DIR__ . '/DemoLoopSandbox.php';

if (!function_exists('demo_growth_default_dataset')) {
    function demo_growth_default_dataset(): array {
        return [
            'dataset'=>['kind'=>'demo','dataset_id'=>'DEMO-GROWTH-V1','label'=>'高意向线索 → 成交演示','version'=>1,'production_data'=>false],
            'goal'=>['metric'=>'conversion_rate','label'=>'提升高意向线索成交率'],
            'profiles'=>[
                ['id'=>'DEMO-LEAD-001','name'=>'演示客户·林','email'=>'lin@demo.example','score'=>88,'tags'=>['价格页访客'],'channel'=>'搜索','last_seen'=>'2026-09-04 10:00:00','props'=>['won_count'=>0],'consent'=>'granted','suppressed'=>false,'signals'=>['pricing_view'=>2,'return_visit'=>2,'consultation_request'=>1],'expected_high_intent'=>true,'expected_sale'=>true],
                ['id'=>'DEMO-LEAD-002','name'=>'演示客户·陈','email'=>'chen@demo.example','score'=>46,'tags'=>['案例读者'],'channel'=>'内容','last_seen'=>'2026-09-03 10:00:00','props'=>['won_count'=>0],'consent'=>'granted','suppressed'=>false,'signals'=>['case_study_view'=>1,'email_click'=>1],'expected_high_intent'=>false,'expected_sale'=>false],
                ['id'=>'DEMO-LEAD-003','name'=>'演示客户·周','email'=>'zhou@demo.example','score'=>82,'tags'=>['请勿触达'],'channel'=>'活动','last_seen'=>'2026-09-02 10:00:00','props'=>['won_count'=>0],'consent'=>'granted','suppressed'=>true,'signals'=>['pricing_view'=>2,'form_submit'=>1],'expected_high_intent'=>false,'expected_sale'=>false],
                ['id'=>'DEMO-LEAD-004','name'=>'演示客户·吴','email'=>'wu@demo.example','score'=>76,'tags'=>['表单线索'],'channel'=>'搜索','last_seen'=>'2026-09-01 10:00:00','props'=>['won_count'=>0],'consent'=>'granted','suppressed'=>false,'signals'=>['pricing_view'=>1,'form_submit'=>1,'return_visit'=>1],'expected_high_intent'=>true,'expected_sale'=>false],
                ['id'=>'DEMO-CUSTOMER-005','name'=>'演示老客·赵','email'=>'zhao@demo.example','score'=>55,'tags'=>['已成交'],'channel'=>'伙伴推荐','last_seen'=>'2026-06-01 10:00:00','lifetime_value'=>6800,'props'=>['won_count'=>2,'won_value_total'=>6800,'last_won_source'=>'伙伴推荐','last_won_segment'=>'企业客户'],'consent'=>'granted','suppressed'=>false,'signals'=>['return_visit'=>1],'expected_high_intent'=>false,'expected_sale'=>true],
            ],
            'conversion_ledger'=>[
                'sources'=>['伙伴推荐'=>['count'=>4,'revenue'=>16800],'搜索'=>['count'=>2,'revenue'=>7200]],
                'segments'=>['企业客户'=>['count'=>4,'revenue'=>16800],'成长客户'=>['count'=>2,'revenue'=>7200]],
                'total'=>['count'=>6,'revenue'=>24000],'updated_at'=>'2026-09-01 12:00:00',
            ],
        ];
    }

    function demo_growth_validate(array $data): array {
        if (($data['dataset']['kind'] ?? '') !== 'demo' || ($data['dataset']['production_data'] ?? true) !== false) return ['ok'=>false,'error'=>'demo_dataset_required'];
        if (empty($data['profiles']) || !is_array($data['profiles'])) return ['ok'=>false,'error'=>'demo_profiles_required'];
        foreach ($data['profiles'] as $p) {
            if (!str_starts_with((string)($p['id'] ?? ''), 'DEMO-')) return ['ok'=>false,'error'=>'demo_identity_required'];
            $email = strtolower((string)($p['email'] ?? ''));
            if ($email !== '' && !str_ends_with($email, '.example')) return ['ok'=>false,'error'=>'demo_email_required'];
        }
        return ['ok'=>true];
    }

    function demo_growth_file(): string { return DATA_DIR . '/demo/growth-workspace.json'; }

    function demo_growth_read(): array {
        $file = demo_growth_file();
        if (!is_file($file)) return ['installed'=>false,'data'=>demo_growth_default_dataset()];
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || !demo_growth_validate($data)['ok']) return ['installed'=>false,'data'=>demo_growth_default_dataset(),'warning'=>'invalid_demo_file'];
        return ['installed'=>true,'data'=>$data];
    }

    function demo_growth_install(): array {
        $data = demo_growth_default_dataset();
        $check = demo_growth_validate($data); if (!$check['ok']) return $check;
        $file = demo_growth_file(); @mkdir(dirname($file), 0775, true);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        $ok = file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false && @rename($tmp, $file);
        if (!$ok) { @unlink($tmp); return ['ok'=>false,'error'=>'demo_write_failed']; }
        return ['ok'=>true,'file'=>$file,'profiles'=>count($data['profiles'])];
    }

    function demo_growth_save(array $data): array {
        $check=demo_growth_validate($data); if(!$check['ok']) return $check;
        $file=demo_growth_file(); @mkdir(dirname($file),0775,true);
        $tmp=$file.'.tmp-'.bin2hex(random_bytes(4)); $ok=file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)!==false&&@rename($tmp,$file);
        if(!$ok){@unlink($tmp);return ['ok'=>false,'error'=>'demo_write_failed'];} return ['ok'=>true];
    }

    function demo_growth_command(string $command): array {
        $state=demo_growth_read(); $data=$state['data'];
        $result=match($command){'start'=>demo_loop_start($data),'approve'=>demo_loop_approve($data),'execute'=>demo_loop_execute($data),default=>['ok'=>false,'error'=>'unknown_demo_command','data'=>$data]};
        if(!$result['ok']) return $result;
        $save=demo_growth_save($result['data']); return $save['ok'] ? $result : $save;
    }

    /** Run both decision engines over the exact same demo profiles. Pure after input validation. */
    function demo_growth_compare(array $data): array {
        $check = demo_growth_validate($data);
        if (!$check['ok']) return $check + ['side_effects'=>false,'production_write_attempts'=>0];
        $truth = growth_conversion_truth($data['conversion_ledger'] ?? []);
        $brainRows = growth_brain_digest($data['profiles'], $truth, 50, $data['goal'] ?? null);
        $brainById=[]; foreach ($brainRows as $r) $brainById[$r['profile']['id']]=$r['best'];
        $leads=[]; foreach ($data['profiles'] as $p) $leads[]=['id'=>$p['id'],'consent'=>$p['consent']??'unknown','suppressed'=>(bool)($p['suppressed']??false),'signals'=>$p['signals']??[],'expected_high_intent'=>(bool)($p['expected_high_intent']??false),'expected_sale'=>(bool)($p['expected_sale']??false)];
        $loop = golden_lead_sandbox_run(['dataset'=>$data['dataset'],'leads'=>$leads]);
        $profiles=[]; foreach ($data['profiles'] as $p) $profiles[$p['id']]=$p;
        $rows=[]; foreach ($loop['subjects'] as $s) { $id=$s['subject_id']; $rows[]=['subject_id'=>$id,'name'=>$profiles[$id]['name']??$id,'brain'=>$brainById[$id]??null,'loop'=>$s]; }
        return ['ok'=>true,'mode'=>'demo','dataset'=>$data['dataset'],'goal'=>$data['goal']??[],'truth'=>$truth,'brain'=>$brainRows,'loop'=>$loop,'rows'=>$rows,'side_effects'=>false,'production_write_attempts'=>0];
    }
}
