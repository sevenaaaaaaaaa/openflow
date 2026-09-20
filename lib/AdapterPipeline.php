<?php
declare(strict_types=1);
/**
 * 适配流水线编排（单一来源）
 *
 * 【为什么抽出来】此前"源接入 → 合成 → 落盘 → 闸门 → 盖章"这套顺序只存在于
 * `scripts/forge-adapter.php` 里。现在自助提交入口、cron worker、后台重跑都要跑同一条流水线——
 * 再抄一遍就是三份会各自漂移的实现。编排只写这一处，CLI 与 worker 都调它。
 *
 * 【可测性】网络访问通过 $opts['fetch'] 注入（默认 adapter_intake_github），
 * AI 通过 $opts['ai'] 注入（默认 null = 模板兜底）。两者都可替换，因此这条流水线
 * **不依赖网络和模型额度也能完整跑通并断言**（见 tests/adapter_submission_test.php）。
 */

require_once __DIR__ . '/AdapterIntake.php';
require_once __DIR__ . '/AdapterForge.php';
require_once __DIR__ . '/AdapterVerify.php';

/**
 * 跑一条完整流水线。
 *
 * @param string $source owner/repo 或 GitHub URL
 * @param array{out_dir?:string, ai?:callable|null, complete?:bool, fetch?:callable,
 *              skip_phpstan?:bool} $opts
 * @return array{ok:bool, stage:string, slug:string, id:string, dir:string,
 *               status:string, badge:string, checks:list<array<string,mixed>>,
 *               failed:list<string>, todos:int, generated_by:string,
 *               license:string, class:string, error:string, steps:list<string>}
 */
function adapter_pipeline_run(string $source, array $opts = []): array
{
    $out = [
        'ok' => false, 'stage' => 'parse', 'slug' => '', 'id' => '', 'dir' => '',
        'status' => '', 'badge' => '', 'checks' => [], 'failed' => [], 'todos' => 0,
        'generated_by' => '', 'license' => '', 'class' => '', 'error' => '', 'steps' => [],
    ];
    $step = static function (string $s) use (&$out): void { $out['steps'][] = $s; };

    // ① 解析来源
    $src = adapter_parse_source($source);
    if (!($src['ok'] ?? false)) { $out['error'] = (string) $src['error']; return $out; }
    if (($src['kind'] ?? '') !== 'github') { $out['error'] = '目前只支持 GitHub 来源（收到 ' . $src['kind'] . '）'; return $out; }
    $out['slug'] = (string) $src['slug'];
    $step("来源解析：{$out['slug']}");

    // ② 源接入（元数据 + README）
    $out['stage'] = 'intake';
    $fetch = $opts['fetch'] ?? null;
    $meta = is_callable($fetch) ? $fetch($out['slug']) : adapter_intake_github($out['slug']);
    if (!is_array($meta) || !($meta['ok'] ?? false)) {
        $out['error'] = '拉取仓库元数据失败：' . (is_array($meta) ? (string) ($meta['error'] ?? '') : '返回格式错误');
        return $out;
    }
    $out['license'] = (string) ($meta['license'] ?? '');
    $readme = (string) ($meta['readme'] ?? '') !== '' ? (string) $meta['readme'] : (string) ($meta['description'] ?? '');
    $profile = adapter_intake_profile($meta, $readme, '');
    $out['class'] = (string) ($profile['class_need'] ?? '');
    $gate = (array) ($profile['license_gate'] ?? []);
    $step("能力画像：类={$out['class']} · 许可证闸门={$gate['level']}");

    // 无许可证 / 不可判定 → 规范里就是 blocked（docs/ADAPTER-SPEC.md §二），直接短路：
    // 没必要花 AI 额度去合成一个注定不能上架的东西。
    // 注意 adapter_license_gate() 对这种情况返回的 level 是 'unknown'，不是 'blocked'。
    // copyleft / other 只是 needs-review，仍然值得出草稿交给人审，所以不短路。
    if (($gate['level'] ?? '') === 'unknown') {
        $out['stage'] = 'license';
        $out['status'] = 'blocked';
        $out['badge'] = 'blocked';
        $out['failed'] = ['license'];
        $out['error'] = (string) ($gate['note'] ?? '许可证不可用');
        return $out;
    }

    // ③ 合成草稿
    $out['stage'] = 'forge';
    $ai = $opts['ai'] ?? null;
    $forged = adapter_forge_generate($profile, is_callable($ai) ? $ai : null);
    $manifest = (array) ($forged['manifest'] ?? []);
    $out['id'] = (string) ($manifest['id'] ?? '');
    $out['generated_by'] = (string) ($forged['generated_by'] ?? '');
    if ($out['id'] === '') { $out['error'] = '合成失败：manifest 没有 id'; return $out; }
    $step("合成草稿：id={$out['id']} · 方式={$out['generated_by']}");

    // ④ 落盘
    $out['stage'] = 'write';
    $outDir = rtrim((string) ($opts['out_dir'] ?? (dirname(__DIR__) . '/plugins/_drafts')), '/');
    $dir = $outDir . '/' . $out['id'];
    $w = adapter_forge_write((array) ($forged['files'] ?? []), $dir);
    if (!($w['ok'] ?? false)) { $out['error'] = '落盘失败：' . (string) $w['error']; return $out; }
    $out['dir'] = $dir;
    $step('落盘：' . implode(', ', (array) $w['written']));

    // ⑤ 验证闸门 + 盖章（徽章唯一合法来源）
    $out['stage'] = 'verify';
    $vopts = [];
    if (!empty($opts['skip_phpstan'])) $vopts['skip_phpstan'] = true;
    $report = adapter_verify_gate($dir, $manifest, $vopts);
    adapter_verify_stamp($dir, $report);

    // ⑥ 可选第二轮：AI 补全真实调用（失败会自动回滚，见 adapter_forge_complete）
    if (!empty($opts['complete']) && is_callable($ai)) {
        $out['stage'] = 'complete';
        $done = adapter_forge_complete($profile, $dir, $ai);
        $step('AI 补全：' . (!empty($done['applied']) ? '已应用' : '未应用') . ' — ' . (string) ($done['note'] ?? ''));
        if (!empty($done['applied']) && is_array($done['report'] ?? null)) {
            $report = (array) $done['report'];
            adapter_verify_stamp($dir, $report);
        }
    }

    $out['status'] = (string) ($report['status'] ?? '');
    $out['badge'] = (string) ($report['badge'] ?? '');
    $out['checks'] = (array) ($report['checks'] ?? []);
    foreach ($out['checks'] as $c) {
        if (empty($c['ok'])) $out['failed'][] = (string) ($c['id'] ?? '?');
    }
    $out['todos'] = is_file($dir . '/plugin.php')
        ? substr_count((string) @file_get_contents($dir . '/plugin.php'), 'TODO(适配)') : 0;
    $step("闸门：{$out['status']}（徽章 {$out['badge']}）");

    $out['stage'] = 'done';
    $out['ok'] = $out['status'] === 'passed';
    return $out;
}
