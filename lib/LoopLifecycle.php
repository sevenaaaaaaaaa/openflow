<?php
/** Persistent, fail-closed lifecycle store for Loop definitions and runs. No business executor is called here. */
require_once __DIR__ . '/DomainContract.php';

if (!function_exists('loop_lifecycle_file')) {
    function loop_lifecycle_file(): string { return DATA_DIR . '/loops/registry.json'; }
    function loop_lifecycle_blank(): array { return ['definitions'=>[],'runs'=>[],'updated_at'=>'']; }
    function loop_lifecycle_read(): array { $d=json_read(loop_lifecycle_file()); return is_array($d)&&isset($d['definitions'],$d['runs'])?$d:loop_lifecycle_blank(); }
    function loop_lifecycle_write(array $data): bool { $data['updated_at']=date('c'); @mkdir(dirname(loop_lifecycle_file()),0775,true); return json_write(loop_lifecycle_file(),$data); }
    function loop_lifecycle_definition_save(array $source): array {
        $definition=domain_loop_definition($source); $validation=domain_contract_validate('LoopDefinition',$definition); if(!$validation['ok'])return ['ok'=>false,'errors'=>$validation['errors']];
        $data=loop_lifecycle_read();$data['definitions'][$definition['id']]=$definition; return loop_lifecycle_write($data)?['ok'=>true,'definition'=>$definition]:['ok'=>false,'errors'=>['write_failed']];
    }
    function loop_lifecycle_start(string $definitionId, string $key, array $checkpoint=[]): array {
        $data=loop_lifecycle_read();$definition=$data['definitions'][$definitionId]??null;if(!$definition)return ['ok'=>false,'error'=>'definition_not_found'];if(($definition['status']??'')!=='active')return ['ok'=>false,'error'=>'definition_not_active'];
        $run=domain_loop_run(['tenant_id'=>$definition['tenant_id'],'definition_id'=>$definitionId,'goal_id'=>$definition['goal_id'],'idempotency_key'=>$key,'max_iterations'=>(int)($definition['budgets']['max_iterations']??1),'created_at'=>date('c'),'updated_at'=>date('c')]);
        if(isset($data['runs'][$run['id']]))return ['ok'=>true,'idempotent'=>true,'run'=>$data['runs'][$run['id']]];
        $next=domain_loop_run_transition($run,'observing',['updated_at'=>date('c')]+$checkpoint);if(!$next['ok'])return $next;$data['runs'][$run['id']]=$next['run'];return loop_lifecycle_write($data)?['ok'=>true,'idempotent'=>false,'run'=>$next['run']]:['ok'=>false,'error'=>'write_failed'];
    }
    function loop_lifecycle_transition(string $runId,string $nextStatus,array $checkpoint=[]): array {
        $data=loop_lifecycle_read();$run=$data['runs'][$runId]??null;if(!$run)return ['ok'=>false,'error'=>'run_not_found'];$next=domain_loop_run_transition($run,$nextStatus,['updated_at'=>date('c')]+$checkpoint);if(!$next['ok'])return $next;$data['runs'][$runId]=$next['run'];return loop_lifecycle_write($data)?['ok'=>true,'run'=>$next['run']]:['ok'=>false,'error'=>'write_failed'];
    }
    function loop_lifecycle_pause(string $runId,string $reason=''):array{return loop_lifecycle_transition($runId,'paused',['pause_reason'=>$reason]);}
    function loop_lifecycle_resume(string $runId):array{return loop_lifecycle_transition($runId,'observing',['resumed'=>true]);}
    function loop_lifecycle_cancel(string $runId,string $reason=''):array{return loop_lifecycle_transition($runId,'cancelled',['cancel_reason'=>$reason]);}
}
