<?php
/** Read-only Flow workspace projection. It composes existing stores; it never owns execution or storage. */
require_once __DIR__ . '/DomainContract.php';
require_once __DIR__ . '/EvidenceProjection.php';

if (!function_exists('flow_workspace_build')) {
    function flow_workspace_build(array $automations, array $canvases, array $projection, string $selectedId = ''): array {
        $definitions = [];
        foreach (array_values($automations) as $flow) {
            if (!is_array($flow) || empty($flow['id'])) continue;
            $definitions[] = domain_flow_definition($flow, 'automation', 'flow');
        }
        foreach (array_values($canvases) as $flow) {
            if (!is_array($flow) || empty($flow['id'])) continue;
            $definitions[] = domain_flow_definition($flow, 'canvas', 'flow');
        }
        usort($definitions, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));
        $byId = array_column($definitions, null, 'id');
        if ($selectedId === '' || !isset($byId[$selectedId])) $selectedId = (string)($definitions[0]['id'] ?? '');
        $definition = $byId[$selectedId] ?? null;
        $objects = (array)($projection['objects'] ?? []);
        $runs = array_values(array_filter((array)($objects['FlowRun'] ?? []), fn($r) => ($r['flow_id'] ?? '') === $selectedId));
        usort($runs, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        $runIds = array_fill_keys(array_column($runs, 'id'), true);
        $executions = array_values(array_filter((array)($objects['Execution'] ?? []), fn($e) => isset($runIds[$e['flow_run_id'] ?? ''])));
        $executionByAction = []; foreach ($executions as $e) $executionByAction[(string)($e['action_id'] ?? '')] = $e;
        $approvals = array_column((array)($objects['Approval'] ?? []), null, 'subject_id');
        $evaluations = array_column((array)($objects['Evaluation'] ?? []), null, 'action_id');
        $actions = array_column((array)($objects['ActionProposal'] ?? []), null, 'id');
        $chains = [];
        foreach ($executionByAction as $actionId => $execution) {
            $chains[] = ['action'=>$actions[$actionId] ?? null, 'approval'=>$approvals[$actionId] ?? null, 'execution'=>$execution, 'evaluation'=>$evaluations[$actionId] ?? null];
        }
        return [
            'mode'=>'read_only','write_enabled'=>false,'definitions'=>$definitions,'selected_id'=>$selectedId,'definition'=>$definition,
            'runs'=>$runs,'chains'=>$chains,'counts'=>['definitions'=>count($definitions),'runs'=>count($runs),'chains'=>count($chains),'evaluated'=>count(array_filter($chains, fn($c)=>$c['evaluation']!==null))],
            // Legacy rows have no trustworthy Flow identity, so they remain visible as global evidence gaps.
            'projection_gaps'=>array_values((array)($projection['gaps'] ?? [])),
        ];
    }

    function flow_workspace_current(string $selectedId = ''): array {
        $automations = function_exists('automation_get') ? automation_get() : [];
        $canvases = function_exists('canvas_get') ? canvas_get() : [];
        return flow_workspace_build(is_array($automations) ? $automations : [], is_array($canvases) ? $canvases : [], evidence_project_current(), $selectedId);
    }
}
