<?php
/**
 * 调研系统共享库 — 数据模型 + 权限 + 统计
 */

// ─── 数据文件路径 ───
function survey_org_file(): string { return DATA_DIR . '/survey/org.json'; }
function survey_surveys_file(): string { return DATA_DIR . '/survey/surveys.json'; }
function survey_responses_dir(): string { return DATA_DIR . '/survey/responses'; }

// ─── 组织架构 ───
function survey_get_org(): array {
    return json_read(survey_org_file());
}
function survey_save_org(array $org): bool {
    if (!is_dir(dirname(survey_org_file()))) mkdir(dirname(survey_org_file()), 0755, true);
    return json_write(survey_org_file(), $org);
}

// ─── 问卷定义 ───
function survey_get_surveys(): array {
    return json_read(survey_surveys_file());
}
function survey_save_surveys(array $surveys): bool {
    if (!is_dir(dirname(survey_surveys_file()))) mkdir(dirname(survey_surveys_file()), 0755, true);
    return json_write(survey_surveys_file(), $surveys);
}
function survey_get_survey(string $id): ?array {
    foreach (survey_get_surveys() as $s) if ($s['id'] === $id) return $s;
    return null;
}

// ─── 回收数据 ───
function survey_get_responses(string $surveyId): array {
    $f = survey_responses_dir() . '/' . $surveyId . '.json';
    return json_read($f);
}
function survey_add_response(string $surveyId, array $resp): bool {
    if (!is_dir(survey_responses_dir())) mkdir(survey_responses_dir(), 0755, true);
    $all = survey_get_responses($surveyId);
    $all[] = $resp;
    return json_write(survey_responses_dir() . '/' . $surveyId . '.json', $all);
}

// ─── 当前登录用户（后台会话）对应的调研角色 ───
// 映射：CMS 用户 → 调研组织成员。通过 email/username 匹配 org.members
function survey_current_user(): array {
    $user = $_SESSION['admin_user'] ?? '';
    $email = $_SESSION['admin_email'] ?? '';
    $name = $_SESSION['admin_name'] ?? '';
    $org = survey_get_org();
    foreach ($org['members'] ?? [] as $m) {
        if (($m['username'] ?? '') === $user || ($m['email'] ?? '') === $email || ($m['name'] ?? '') === $name) return $m;
    }
    // 未匹配则返回超级权限（admin 角色）
    if (($_SESSION['admin_role'] ?? '') === 'admin') {
        return ['username' => $user, 'name' => $name ?: '管理员', 'role' => 'company_admin', 'company' => '', 'department' => ''];
    }
    return ['username' => $user, 'name' => $name ?: $user, 'role' => 'employee', 'company' => '', 'department' => ''];
}

// ─── 可见范围过滤 ───
// 返回: ['type' => 'all'|'company'|'department'|'self', 'company' => '', 'department' => '']
function survey_scope(array $member): array {
    $role = $member['role'] ?? 'employee';
    switch ($role) {
        case 'company_admin':
        case 'hr':
            return ['type' => 'company', 'company' => $member['company'] ?? ''];
        case 'department_admin':
            return ['type' => 'department', 'company' => $member['company'] ?? '', 'department' => $member['department'] ?? ''];
        default:
            return ['type' => 'self', 'company' => $member['company'] ?? '', 'department' => $member['department'] ?? ''];
    }
}

// 过滤回收记录到当前角色可见范围
function survey_filter_responses(string $surveyId, array $member): array {
    $responses = survey_get_responses($surveyId);
    $scope = survey_scope($member);
    if ($scope['type'] === 'all') return $responses;
    return array_values(array_filter($responses, function ($r) use ($scope) {
        $rc = $r['company'] ?? '';
        $rd = $r['department'] ?? '';
        if ($scope['type'] === 'company') return $rc === $scope['company'];
        if ($scope['type'] === 'department') return $rc === $scope['company'] && $rd === $scope['department'];
        if ($scope['type'] === 'self') return ($r['email'] ?? '') === ($_SESSION['admin_email'] ?? '') || ($r['name'] ?? '') === ($_SESSION['admin_name'] ?? '');
        return true;
    }));
}

// ─── 简单统计 ───
// 对单选/多选/评分题做计数统计；文本题跳过
function survey_compute_stats(array $survey, array $responses): array {
    $stats = [];
    foreach ($survey['questions'] ?? [] as $q) {
        $qid = $q['id'];
        $type = $q['type'] ?? 'text';
        $stat = ['question' => $q['title'] ?? '', 'type' => $type, 'total' => count($responses), 'distribution' => [], 'avg' => null, 'min' => null, 'max' => null];
        if (in_array($type, ['single', 'multi', 'dropdown'])) {
            $counts = [];
            foreach ($responses as $r) {
                $val = $r['answers'][$qid] ?? null;
                $vals = is_array($val) ? $val : [$val];
                foreach ($vals as $v) {
                    if ($v === null || $v === '') continue;
                    $counts[(string)$v] = ($counts[(string)$v] ?? 0) + 1;
                }
            }
            $stat['distribution'] = $counts;
        } elseif ($type === 'rating') {
            $sum = 0; $n = 0; $min = 10; $max = 0;
            foreach ($responses as $r) {
                $v = (float)($r['answers'][$qid] ?? 0);
                if ($v <= 0) continue;
                $sum += $v; $n++; $min = min($min, $v); $max = max($max, $v);
            }
            if ($n > 0) { $stat['avg'] = round($sum / $n, 2); $stat['min'] = $min; $stat['max'] = $max; $stat['count'] = $n; }
        }
        $stats[] = $stat;
    }
    return $stats;
}
