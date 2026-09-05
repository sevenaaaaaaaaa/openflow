<?php
/** Flow/Skill capability metadata, safe promotion and shareable template export. */
require_once __DIR__ . '/DomainContract.php';

if (!function_exists('workflow_library_capability')) {
    function workflow_library_capability(array $definition): array {
        $validation=domain_contract_validate('FlowDefinition',$definition);if(!$validation['ok'])return ['ok'=>false,'errors'=>$validation['errors']];
        return ['ok'=>true,'id'=>'cap_'.$definition['id'],'flow_id'=>$definition['id'],'version'=>$definition['version'],'name'=>$definition['name'],'risk_level'=>$definition['risk_level'],'permissions'=>$definition['permissions'],'input_schema'=>$definition['input_schema'],'output_schema'=>$definition['output_schema'],'idempotency'=>'tenant_id + flow_id + input key','rollback'=>$definition['risk_level']==='low'?'由领域动作声明':'需要领域动作明确回滚','status'=>$definition['status']==='active'?'available':'unavailable'];
    }
    function workflow_library_promote_loop(array $loopDefinition,array $flowDefinition,string $name=''):array{
        $cap=workflow_library_capability($flowDefinition);if(!$cap['ok'])return $cap;if(($flowDefinition['status']??'')!=='active')return ['ok'=>false,'error'=>'flow_not_active'];
        return ['ok'=>true,'template'=>['kind'=>'flow_from_loop','version'=>1,'name'=>$name?:($flowDefinition['name'].' · Loop 固化'),'loop_definition_id'=>$loopDefinition['id']??'','flow_id'=>$flowDefinition['id'],'flow_version'=>$flowDefinition['version'],'tips_stages'=>$loopDefinition['tips_stages']??[],'risk_level'=>$cap['risk_level'],'permissions'=>$cap['permissions'],'compatibility'=>['contract_version'=>domain_contract_version(),'requires'=>['FlowDefinition','Policy']]]];
    }
    function workflow_library_redact(mixed $value,string $key=''):mixed{
        $sensitive=['token','secret','password','authorization','api_key','apikey','cookie','email','phone','webhook'];
        if(in_array(strtolower($key),$sensitive,true))return '[REDACTED]';if(is_array($value)){foreach($value as $k=>$v)$value[$k]=workflow_library_redact($v,(string)$k);}return $value;
    }
    function workflow_library_export_template(array $template):array{
        $safe=workflow_library_redact($template);$safe['shared_at']=date('c');$safe['share_safety']=['credentials_removed'=>true,'permissions_redeclaration_required'=>true,'compatibility_recheck_required'=>true];return $safe;
    }
    function workflow_library_compare(array $baseline,array $candidate):array{
        $fields=['sample_size','conversion_rate','cost','wrong_contact_rate'];$delta=[];foreach($fields as $f)$delta[$f]=round((float)($candidate[$f]??0)-(float)($baseline[$f]??0),4);return ['baseline'=>$baseline,'candidate'=>$candidate,'delta'=>$delta,'candidate_better'=>($delta['conversion_rate']>=0&&$delta['cost']<=0&&$delta['wrong_contact_rate']<=0)];
    }
}
