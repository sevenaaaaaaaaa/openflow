<?php
/** Read-only action approval projection for the dedicated governance workspace. */
require_once __DIR__ . '/EvidenceProjection.php';

if (!function_exists('action_approval_view')) {
    function action_approval_view(array $projection): array {
        $objects = (array)($projection['objects'] ?? []);
        $actions = array_values((array)($objects['ActionProposal'] ?? []));
        $approvals = array_values((array)($objects['Approval'] ?? []));
        $executions = array_values((array)($objects['Execution'] ?? []));
        $evaluations = array_values((array)($objects['Evaluation'] ?? []));
        $approvalByAction=[];$executionByAction=[];$evaluationByAction=[];
        foreach($approvals as $row) if(is_array($row)&&!empty($row['subject_id']))$approvalByAction[$row['subject_id']]=$row;
        foreach($executions as $row) if(is_array($row)&&!empty($row['action_id']))$executionByAction[$row['action_id']]=$row;
        foreach($evaluations as $row) if(is_array($row)&&!empty($row['action_id']))$evaluationByAction[$row['action_id']]=$row;

        $rows=[];$actionIds=[];
        foreach($actions as $action){
            if(!is_array($action)||empty($action['id']))continue;
            $id=(string)$action['id'];$actionIds[$id]=true;
            $approval=$approvalByAction[$id]??null;$execution=$executionByAction[$id]??null;$evaluation=$evaluationByAction[$id]??null;
            $state='proposed';
            if($approval)$state=($approval['decision']??'')==='approved'?'approved':'rejected';
            if($execution)$state=in_array($execution['status']??'', ['succeeded','failed','cancelled'],true)?'completed':'executing';
            if($evaluation)$state='evaluated';
            $issues=[];
            if($execution&&!$approval)$issues[]='execution_without_approval';
            if($evaluation&&!$execution)$issues[]='evaluation_without_execution';
            $rows[]=['action'=>$action,'approval'=>$approval,'execution'=>$execution,'evaluation'=>$evaluation,'state'=>$state,'issues'=>$issues];
        }
        usort($rows,fn($a,$b)=>strcmp((string)($b['action']['created_at']??''),(string)($a['action']['created_at']??'')));

        $orphans=['approvals'=>0,'executions'=>0,'evaluations'=>0];
        foreach($approvalByAction as $id=>$row)if(!isset($actionIds[$id]))$orphans['approvals']++;
        foreach($executionByAction as $id=>$row)if(!isset($actionIds[$id]))$orphans['executions']++;
        foreach($evaluationByAction as $id=>$row)if(!isset($actionIds[$id]))$orphans['evaluations']++;
        $counts=['total'=>count($rows),'proposed'=>0,'approved'=>0,'executing'=>0,'completed'=>0,'evaluated'=>0,'rejected'=>0];
        foreach($rows as $row)if(isset($counts[$row['state']]))$counts[$row['state']]++;
        return [
            'mode'=>'read_only','write_enabled'=>false,'rows'=>$rows,'counts'=>$counts,'orphans'=>$orphans,
            'projection_gaps'=>array_values((array)($projection['gaps']??[])),
            'integrity_ok'=>array_sum($orphans)===0 && empty($projection['gaps']),
        ];
    }
}
