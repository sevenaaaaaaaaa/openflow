<?php
/**
 * 开发者文档门户 —— 钩子 / SDK / 沙箱能力 / 版本约束 一处可查（BACKLOG T2-11）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ArtifactSandbox.php';
require_once __DIR__ . '/../lib/PackageRegistry.php';
require_login();
require_perm('plugins');

// 钩子清单：从 HOOKS.md 提取（已实现的部分）
$hooks = [];
$hookFile = dirname(__DIR__) . '/docs/HOOKS.md';
if (is_file($hookFile)) {
    $md = (string)file_get_contents($hookFile);
    $cut = mb_strpos($md, '尚无插入点');
    if ($cut !== false) $md = mb_substr($md, 0, $cut);
    if (preg_match_all('/`([a-z0-9_]{3,40})`/', $md, $m)) {
        $hooks = array_values(array_unique($m[1]));
    }
}
$caps = sandbox_capabilities();
admin_header('开发者文档');
?>
<div style="max-width:920px">
  <h1 style="margin:0 0 4px">📚 开发者文档</h1>
  <p class="v-sub" style="margin:0 0 18px">造东西之前先看这里：能挂哪些钩子、沙箱里能调什么、版本怎么写、依赖怎么声明。</p>

  <div class="card" style="padding:18px;margin-bottom:14px">
    <div style="font-weight:700;margin-bottom:8px">🪝 可用钩子（<?=count($hooks)?>）</div>
    <div class="v-sub" style="margin-bottom:10px">用 <code>PluginSystem::add_action('钩子名', fn)</code> 挂载；带 filter 的用 <code>apply_filters</code>。</div>
    <?php if (!$hooks): ?><div style="color:var(--faint);font-size:13px">未找到钩子文档（docs/HOOKS.md）。</div><?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($hooks as $h): ?><code style="font-size:12px;padding:2px 8px;border:1px solid var(--border);border-radius:6px"><?=htmlspecialchars($h)?></code><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card" style="padding:18px;margin-bottom:14px">
    <div style="font-weight:700;margin-bottom:8px">🧰 沙箱能力白名单</div>
    <div class="v-sub" style="margin-bottom:10px">「描述即造」的生成物只能调用这些；<strong>且必须在 permissions 里声明过</strong>，未声明即拒（最小权限）。</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <?php foreach ($caps as $k => $v): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:7px 0;width:180px"><code><?=htmlspecialchars($k)?></code></td>
        <td style="padding:7px 0;color:var(--faint)"><?=htmlspecialchars($v)?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="padding:18px;margin-bottom:14px">
    <div style="font-weight:700;margin-bottom:8px">📦 版本与依赖</div>
    <div class="v-sub" style="margin-bottom:10px">版本用语义化 <code>主.次.修</code>；依赖写版本区间，平台会在安装前做兼容检查。</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <?php foreach ([
        ['^1.2.3', '≥1.2.3 且主版本仍是 1（推荐：接受兼容更新）'],
        ['~1.2.3', '≥1.2.3 且次版本仍是 1.2（更保守）'],
        ['>=2.0',  '不低于 2.0'],
        ['*',      '任意版本（不建议）'],
      ] as [$c, $d]): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:7px 0;width:180px"><code><?=htmlspecialchars($c)?></code></td>
        <td style="padding:7px 0;color:var(--faint)"><?=htmlspecialchars($d)?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <pre style="margin-top:12px;background:var(--surface,#fafafa);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;overflow:auto">{
  "id": "my-plugin",
  "version": "1.0.0",
  "platform": "&gt;=1.0",
  "requires": { "seo-enhancer": "^1.2" },
  "conflicts": ["old-seo-plugin"],
  "permissions": ["text.template"]
}</pre>
  </div>

  <div class="card" style="padding:18px">
    <div style="font-weight:700;margin-bottom:8px">🚦 上架流程</div>
    <ol style="margin:0;padding-left:20px;font-size:13px;line-height:2;color:var(--text-soft,#475569)">
      <li>写内容 / 描述即造 → 生成物自动过三道护栏（权限声明、危险模式、AI 预审）</li>
      <li>判为「可发布」→ 存草稿；判为「需人工」→ 进审核队列等管理员裁决</li>
      <li>发布后自动三通：进知识库 · 暴露为 MCP 能力 · 纳入平台分发</li>
      <li>被别人调用即计入复利分，功劳算给你</li>
    </ol>
  </div>
</div>
<?php admin_footer(); ?>
