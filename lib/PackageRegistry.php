<?php
/**
 * PackageRegistry —— 版本 / 依赖 / 兼容管理（AUDIT-06 / BACKLOG T2-11）
 *
 * 【为什么】生态一旦有量，"这个插件要求什么版本、和另一个冲不冲、升级会不会炸"
 * 就成了硬需求。没有它，用户装一个坏一个，生态口碑就没了。
 *
 * 【范围】语义化版本比较、版本区间约束(^ ~ >= 等)、依赖解析与缺失/冲突检测、
 * 与平台版本的兼容判定。纯函数，无外部依赖，可完整单测。
 */

if (!function_exists('semver_parse')) {

    /** 解析 x.y.z（多余部分忽略，缺位补 0）。 */
    function semver_parse(string $v): array {
        $v = trim(ltrim(trim($v), 'vV'));
        $parts = explode('.', explode('-', $v)[0]);
        return [(int)($parts[0] ?? 0), (int)($parts[1] ?? 0), (int)($parts[2] ?? 0)];
    }

    /** 比较：a<b 返回 -1，相等 0，a>b 返回 1。 */
    function semver_cmp(string $a, string $b): int {
        $pa = semver_parse($a); $pb = semver_parse($b);
        for ($i = 0; $i < 3; $i++) {
            if ($pa[$i] !== $pb[$i]) return $pa[$i] < $pb[$i] ? -1 : 1;
        }
        return 0;
    }

    /**
     * 版本是否满足约束。支持：* / ^1.2.3 / ~1.2.3 / >=1.2 / >1.2 / <=1.2 / <1.2 / =1.2.3
     * 多个约束用空格分隔（与逻辑）。
     */
    function semver_satisfies(string $version, string $constraint): bool {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') return true;
        foreach (preg_split('/\s+/', $constraint) as $c) {
            if ($c === '') continue;
            if (!_semver_one($version, $c)) return false;
        }
        return true;
    }

    function _semver_one(string $version, string $c): bool {
        if (preg_match('/^\^(.+)$/', $c, $m)) {
            // ^1.2.3 → >=1.2.3 且 <2.0.0（主版本不变）
            $base = semver_parse($m[1]);
            if (semver_cmp($version, $m[1]) < 0) return false;
            return semver_parse($version)[0] === $base[0];
        }
        if (preg_match('/^~(.+)$/', $c, $m)) {
            // ~1.2.3 → >=1.2.3 且 <1.3.0（次版本不变）
            $base = semver_parse($m[1]);
            if (semver_cmp($version, $m[1]) < 0) return false;
            $v = semver_parse($version);
            return $v[0] === $base[0] && $v[1] === $base[1];
        }
        if (preg_match('/^(>=|<=|>|<|=)?\s*(.+)$/', $c, $m)) {
            $op = $m[1] ?: '=';
            $cmp = semver_cmp($version, $m[2]);
            switch ($op) {
                case '>=': return $cmp >= 0;
                case '<=': return $cmp <= 0;
                case '>':  return $cmp > 0;
                case '<':  return $cmp < 0;
                default:   return $cmp === 0;
            }
        }
        return false;
    }

    /**
     * 依赖检查：给定要装的包 + 已安装清单 + 平台版本。
     * $pkg: ['id','version','requires'=>['pkgId'=>'^1.0'],'platform'=>'>=2.0','conflicts'=>['pkgId']]
     * $installed: ['pkgId'=>'version', ...]
     * 返回 ['ok'=>bool,'missing'=>[],'outdated'=>[],'conflicts'=>[],'platform_ok'=>bool,'reasons'=>[]]
     */
    function pkg_check(array $pkg, array $installed = [], string $platformVersion = '1.0.0'): array {
        $missing = []; $outdated = []; $conflicts = []; $reasons = [];

        foreach ((array)($pkg['requires'] ?? []) as $dep => $range) {
            $dep = (string)$dep; $range = (string)$range;
            if (!isset($installed[$dep])) {
                $missing[] = ['id' => $dep, 'need' => $range];
                $reasons[] = "缺少依赖 {$dep} {$range}";
                continue;
            }
            if (!semver_satisfies((string)$installed[$dep], $range)) {
                $outdated[] = ['id' => $dep, 'have' => (string)$installed[$dep], 'need' => $range];
                $reasons[] = "{$dep} 当前 {$installed[$dep]}，需要 {$range}";
            }
        }

        foreach ((array)($pkg['conflicts'] ?? []) as $c) {
            $c = (string)$c;
            if (isset($installed[$c])) { $conflicts[] = $c; $reasons[] = "与已安装的 {$c} 冲突"; }
        }

        $platformOk = true;
        $pc = trim((string)($pkg['platform'] ?? ''));
        if ($pc !== '' && !semver_satisfies($platformVersion, $pc)) {
            $platformOk = false;
            $reasons[] = "需要平台版本 {$pc}，当前 {$platformVersion}";
        }

        return [
            'ok' => empty($missing) && empty($outdated) && empty($conflicts) && $platformOk,
            'missing' => $missing, 'outdated' => $outdated, 'conflicts' => $conflicts,
            'platform_ok' => $platformOk, 'reasons' => $reasons,
        ];
    }

    /**
     * 升级影响评估：从旧版到新版是否破坏性（主版本变化即破坏性）。
     * 同时列出哪些已安装包依赖它、会不会被升挂。
     */
    function pkg_upgrade_impact(string $id, string $from, string $to, array $installedPkgs = []): array {
        $breaking = semver_parse($from)[0] !== semver_parse($to)[0];
        $affected = [];
        foreach ($installedPkgs as $p) {
            $need = (string)(($p['requires'] ?? [])[$id] ?? '');
            if ($need === '') continue;
            $stillOk = semver_satisfies($to, $need);
            $affected[] = ['id' => (string)($p['id'] ?? ''), 'need' => $need, 'ok_after' => $stillOk];
        }
        $willBreak = array_values(array_filter($affected, fn($a) => !$a['ok_after']));
        return [
            'breaking' => $breaking,
            'direction' => semver_cmp($to, $from),
            'affected' => $affected,
            'will_break' => $willBreak,
            'safe' => empty($willBreak),
        ];
    }
}
