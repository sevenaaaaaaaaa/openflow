<?php
/**
 * 主题管理 — 预设主题（Notion/Claude/Apple/Google/Linear）+ 自定义
 * 主题 = 一套 oklch CSS 变量 + 布局偏好（圆角/字体/玻璃/动效），完全兼容所有前后端功能
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$message = '';
$error = '';

// ─── 操作 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = trim($_POST['theme_id'] ?? '');
    // 激活主题
    if (isset($_POST['activate'])) {
        if (ThemeSystem::activate($id)) {
            $message = '已切换到「' . (ThemeSystem::get($id)['name'] ?? $id) . '」，全站立即生效。';
            try { (new FileCache())->flush(); } catch (\Throwable $e) {}
        } else $error = '主题不存在';
        header('Location: /xmp/themes');
        exit;
    }
    // 保存自定义（基于某预设复制 + 修改变量）
    if (isset($_POST['save_custom'])) {
        $base = $_POST['base'] ?? 'default';
        $baseTheme = ThemeSystem::get($base);
        if ($baseTheme) {
            $custom = $baseTheme;
            $custom['name'] = trim($_POST['theme_name'] ?? '自定义主题');
            $custom['desc'] = '基于 ' . ($baseTheme['name'] ?? $base) . ' 的自定义主题';
            // 覆盖可编辑的变量
            foreach (['bg', 'fg', 'accent', 'border', 'surface'] as $v) {
                $val = trim($_POST['var_' . $v] ?? '');
                if ($val && $val !== '') {
                    $custom['light'][$v] = $val;
                }
            }
            // 圆角
            if (!empty($_POST['var_radius'])) $custom['layout']['r-lg'] = $_POST['var_radius'];
            // 玻璃
            if (!empty($_POST['var_glass'])) $custom['layout']['glass'] = $_POST['var_glass'];
            ThemeSystem::saveCustom($id ?: ('custom_' . substr(bin2hex(random_bytes(4)), 0, 6)), $custom);
            $message = '自定义主题已保存';
        } else $error = '基础主题不存在';
        header('Location: /xmp/themes');
        exit;
    }
}

// 删除自定义
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if (!ThemeSystem::isPreset($id)) {
        ThemeSystem::deleteCustom($id);
        $message = '已删除自定义主题';
    } else {
        $error = '预设主题不可删除';
    }
    header('Location: /xmp/themes');
    exit;
}

$themes = ThemeSystem::all();
$activeId = ThemeSystem::activeId();

admin_header('主题管理');
?>
<style>
  .theme-card{border:2px solid var(--border);border-radius:14px;padding:16px;cursor:pointer;transition:.15s;position:relative;background:var(--surface)}
  .theme-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm);border-color:var(--border-strong)}
  .theme-card.active{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
  .theme-preview{height:70px;border-radius:10px;margin-bottom:10px;position:relative;overflow:hidden}
  .theme-preview .tp-bar{position:absolute;top:8px;left:8px;right:8px;height:14px;border-radius:7px;opacity:.9}
  .theme-preview .tp-accent{position:absolute;bottom:8px;left:8px;width:40%;height:8px;border-radius:4px}
  .theme-preview .tp-card{position:absolute;right:8px;bottom:8px;width:30%;height:26px;border-radius:4px;opacity:.8}
  .badge-preset{position:absolute;top:8px;right:8px;font-size:10px;padding:2px 8px;border-radius:999px;background:var(--accent-soft);color:var(--accent)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('themes'); ?>
  <div class="main">
    <h1> 主题管理</h1>
    <p class="sub">主题不是换皮，而是不同的视觉 + 交互 + 布局，且完全兼容所有前后端功能。切换即全局生效。</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 主题网格 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px">
      <?php foreach ($themes as $tid => $th): ?>
      <div class="theme-card <?=$tid===$activeId?'active':''?>">
        <?php if (ThemeSystem::isPreset($tid)): ?><span class="badge-preset">预设</span><?php endif; ?>
        <!-- 预览块：用主题自身变量渲染 -->
        <div class="theme-preview" style="background:<?=$th['light']['bg'] ?? '#fff'?>">
          <div class="tp-bar" style="background:<?=$th['light']['surface-strong'] ?? '#fff'?>;border:1px solid <?=$th['light']['border'] ?? '#ccc'?>"></div>
          <div class="tp-accent" style="background:<?=$th['light']['accent'] ?? '#000'?>"></div>
          <div class="tp-card" style="background:<?=$th['light']['surface'] ?? '#fff'?>;border:1px solid <?=$th['light']['border'] ?? '#ccc'?>"></div>
        </div>
        <div style="font-weight:700;font-size:14px"><?=htmlspecialchars($th['name'])?></div>
        <div class="text-xs text-muted" style="margin:4px 0 8px;line-height:1.5;min-height:32px"><?=htmlspecialchars($th['desc'] ?? '')?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <span class="text-xs" style="color:var(--faint)">圆角 <?=htmlspecialchars($th['layout']['r-lg'] ?? '')?></span>
          <span class="text-xs" style="color:var(--faint)">· <?=htmlspecialchars(['none'=>'无玻璃','medium'=>'玻璃','strong'=>'强玻璃'][$th['layout']['glass-strength'] ?? 'medium'] ?? '')?></span>
        </div>
        <div style="display:flex;gap:6px;margin-top:10px">
          <?php if ($tid === $activeId): ?>
          <span class="btn btn-primary btn-sm" style="opacity:.6;pointer-events:none">✓ 当前</span>
          <?php else: ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="theme_id" value="<?=htmlspecialchars($tid)?>"><button class="btn btn-primary btn-sm" name="activate" value="1">启用</button></form>
          <?php endif; ?>
          <?php if (!ThemeSystem::isPreset($tid)): ?>
          <a href="?delete=<?=urlencode($tid)?>" class="btn btn-ghost btn-sm" data-confirm="删除此自定义主题？">删除</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 自定义主题 -->
    <div class="card">
      <h2 style="margin-bottom:12px">🔧 基于预设创建自定义主题</h2>
      <p class="text-sm text-muted mb-4">选一个预设作为基础，微调几个关键变量，生成你的专属主题。全部变量都兼容现有前后端功能。</p>
      <form method="post" class="grid gap-3" style="max-width:640px">
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field"><label>名称</label><input type="text" name="theme_name" placeholder="我的主题"></div>
          <div class="field"><label>ID（英文）</label><input type="text" name="theme_id" placeholder="my_theme"></div>
        </div>
        <div class="field"><label>基于预设</label><select name="base">
          <?php foreach ($themes as $tid => $th): if (!ThemeSystem::isPreset($tid)) continue; ?>
          <option value="<?=htmlspecialchars($tid)?>"><?=htmlspecialchars($th['name'])?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="field-row">
          <div class="field"><label>背景色（oklch）</label><input type="text" name="var_bg" placeholder="留空则继承预设，如 oklch(100% 0 0)"></div>
          <div class="field"><label>主题色</label><input type="text" name="var_accent" placeholder="oklch(52% .17 258)"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>圆角</label><input type="text" name="var_radius" placeholder="如 8px"></div>
          <div class="field"><label>玻璃强度</label><select name="var_glass"><option value="">继承</option><option value="none">无玻璃</option><option value="medium">中等</option><option value="strong">强玻璃</option></select></div>
        </div>
        <button class="btn btn-primary" name="save_custom" value="1">创建自定义主题</button>
      </form>
    </div>
  </div>
</div>
<?php admin_footer();
