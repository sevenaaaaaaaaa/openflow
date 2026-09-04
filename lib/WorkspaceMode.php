<?php
/** User-scoped workspace presentation preference. Never changes business state. */
if (!function_exists('workspace_mode_current')) {
    function workspace_mode_file(): string { return DATA_DIR . '/ui-preferences.json'; }
    function workspace_mode_user_key(?string $user = null): string {
        $name = trim((string)($user ?? ($_SESSION['admin_user'] ?? '')));
        return $name === '' ? '' : hash('sha256', mb_strtolower($name));
    }
    function workspace_mode_all(): array {
        $rows = function_exists('json_read') ? json_read(workspace_mode_file()) : [];
        return is_array($rows) ? $rows : [];
    }
    function workspace_mode_current(?string $user = null): string {
        $key = workspace_mode_user_key($user);
        $mode = $key === '' ? 'flow' : (string)(workspace_mode_all()[$key]['mode'] ?? 'flow');
        return in_array($mode, ['flow','loop'], true) ? $mode : 'flow';
    }
    function workspace_mode_set(string $mode, ?string $user = null): array {
        if (!in_array($mode, ['flow','loop'], true)) return ['ok'=>false,'error'=>'invalid_mode'];
        $key = workspace_mode_user_key($user);
        if ($key === '') return ['ok'=>false,'error'=>'missing_user'];
        $rows = workspace_mode_all();
        $rows[$key] = ['mode'=>$mode,'updated_at'=>date('c')];
        if (!function_exists('json_write')) return ['ok'=>false,'error'=>'storage_unavailable'];
        json_write(workspace_mode_file(), $rows);
        return ['ok'=>true,'mode'=>$mode];
    }
}
