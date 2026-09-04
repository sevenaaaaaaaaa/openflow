<?php
/** Read-only health metrics for optional structured automation shadow logs. */

require_once __DIR__ . '/EvidenceProjection.php';

if (!function_exists('shadow_run_observe')) {
    function shadow_run_ratio(int $part, int $total): ?float {
        return $total > 0 ? round($part / $total, 4) : null;
    }

    /** Pure observation: never persists, executes, or changes a Flow. */
    function shadow_run_observe(array $logs, ?int $now = null, int $staleAfterSeconds = 3600): array {
        $now ??= time();
        $required = ['run_id', 'flow', 'trigger', 'status', 'idempotency_key', 'tenant_id', 'time'];
        $terminalStatuses = ['succeeded', 'failed', 'cancelled'];
        $candidates = [];
        $anomalies = [];

        foreach (array_values($logs) as $index => $row) {
            if (!is_array($row)) continue;
            $isCandidate = str_starts_with((string)($row['message'] ?? ''), '影子运行：')
                || array_key_exists('run_id', $row);
            if (!$isCandidate) continue;
            $missing = [];
            foreach ($required as $field) {
                if (!isset($row[$field]) || trim((string)$row[$field]) === '') $missing[] = $field;
            }
            $candidates[] = ['index'=>$index, 'row'=>$row, 'complete'=>$missing === []];
            if ($missing) {
                $anomalies[] = ['type'=>'incomplete_structured_row', 'index'=>$index, 'missing'=>$missing];
            }
        }

        $completeRows = array_values(array_filter($candidates, fn(array $item): bool => $item['complete']));
        $runs = [];
        foreach ($completeRows as $item) {
            $row = $item['row'];
            $id = (string)$row['run_id'];
            $runs[$id] ??= ['statuses'=>[], 'rows'=>[], 'identity'=>[]];
            $runs[$id]['statuses'][] = (string)$row['status'];
            $runs[$id]['rows'][] = $row;
            $runs[$id]['identity'][] = implode('|', [(string)$row['tenant_id'], (string)$row['flow'], (string)$row['idempotency_key']]);
        }

        $terminalRuns = 0;
        $lifecycleCompleteRuns = 0;
        foreach ($runs as $runId => $run) {
            $statuses = array_values(array_unique($run['statuses']));
            $terminals = array_values(array_intersect($statuses, $terminalStatuses));
            $hasRunning = in_array('running', $statuses, true);
            if ($terminals) $terminalRuns++;
            if ($hasRunning && $terminals) $lifecycleCompleteRuns++;
            if (!$hasRunning && $terminals) $anomalies[] = ['type'=>'terminal_without_running', 'run_id'=>$runId];
            if (count($terminals) > 1) $anomalies[] = ['type'=>'conflicting_terminal_status', 'run_id'=>$runId, 'statuses'=>$terminals];
            if (count(array_unique($run['identity'])) > 1) $anomalies[] = ['type'=>'run_identity_collision', 'run_id'=>$runId];

            if (!$terminals) {
                $timestamps = array_filter(array_map(fn(array $row): int|false => strtotime((string)$row['time']), $run['rows']));
                $latest = $timestamps ? max($timestamps) : false;
                if ($latest !== false && $now - $latest > $staleAfterSeconds) {
                    $anomalies[] = ['type'=>'stale_running_run', 'run_id'=>$runId, 'age_seconds'=>$now - $latest];
                }
            }
        }

        $projection = evidence_project([], array_column($completeRows, 'row'), []);
        foreach ($projection['gaps'] as $gap) {
            if (str_starts_with((string)$gap['reason'], 'invalid_structured_run:')) {
                $anomalies[] = ['type'=>'projection_failure', 'index'=>$gap['index'], 'reason'=>$gap['reason']];
            }
        }
        $byType = array_count_values(array_column($anomalies, 'type'));
        ksort($byType);

        $candidateCount = count($candidates);
        $runCount = count($runs);
        return [
            'mode'=>'read_only',
            'observed_at'=>date('c', $now),
            'thresholds'=>['stale_after_seconds'=>$staleAfterSeconds],
            'counts'=>[
                'log_rows'=>count($logs), 'shadow_candidate_rows'=>$candidateCount,
                'complete_structured_rows'=>count($completeRows), 'runs'=>$runCount,
                'terminal_runs'=>$terminalRuns, 'lifecycle_complete_runs'=>$lifecycleCompleteRuns,
                'projected_runs'=>count($projection['objects']['FlowRun']), 'anomalies'=>count($anomalies),
            ],
            'rates'=>[
                'structured_field_completeness'=>shadow_run_ratio(count($completeRows), $candidateCount),
                'terminal_rate'=>shadow_run_ratio($terminalRuns, $runCount),
                'lifecycle_completeness'=>shadow_run_ratio($lifecycleCompleteRuns, $runCount),
            ],
            'anomalies_by_type'=>$byType,
            'anomalies'=>$anomalies,
        ];
    }
}
