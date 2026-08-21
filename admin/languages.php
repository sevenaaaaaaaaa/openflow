<?php
/**
 * 翻译管理 — 多语言语言包维护
 * 以默认语言为源，其他语言逐 key 翻译；支持新增 key
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/I18n.php';
require_login();
require_perm('settings');

$langDir = DATA_DIR . '/lang/';
$supported = i18n_supported();
$source = i18n_default_locale();
$current = $_GET['locale'] ?? $source;
if (!in_array($current, $supported, true)) $current = $source;

$sourceDict = is_file($langDir . $source . '.json') ? (json_read($langDir . $source . '.json') ?: []) : [];
$currentDict = is_file($langDir . $current . '.json') ? (json_read($langDir . $current . '.json') ?: []) : [];
$keys = array_unique(array_merge(array_keys($sourceDict), array_keys($currentDict)));
sort($keys);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newDict = [];
    foreach (($_POST['trans'] ?? []) as $k => $v) {
        $k = trim((string)$k);
        if ($k === '') continue;
        $v = trim((string)$v);
        if ($v !== '') $newDict[$k] = $v;
    }
    if (!empty($_POST['new_key'])) {
        $nk = trim($_POST['new_key']);
        if ($nk !== '' && !isset($newDict[$nk])) {
            $nv = trim($_POST['new_value'] ?? '');
            if ($nv !== '') $newDict[$nk] = $nv;
        }
    }
    // 若编辑的是源语言，同步更新源
    if ($current === $source) $sourceDict = $newDict;
    ksort($newDict);
    @mkdir($langDir, 0755, true);
    if (json_write($langDir . $current . '.json', $newDict)) {
        $message = '「' . i18n_native($current) . '」翻译已保存';
        $currentDict = $newDict;
    } else {
        $message = '保存失败（检查目录写入权限）';
    }
    $keys = array_unique(array_merge(array_keys($sourceDict), array_keys($currentDict)));
    sort($keys);
}

// 缺失率统计
$untranslated = 0;
foreach ($keys as $k) if (($sourceDict[$k] ?? '') !== '' && !isset($currentDict[$k])) $untranslated++;
$total = count($keys);
$rate = $total > 0 ? round(($total - $untranslated) / $total * 100) : 100;

admin_header('翻译管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('languages'); ?>
  <div class="main">
    <h1>翻译管理</h1>
    <p class="sub">维护 <?=$total?> 个翻译键 · 以「<?=htmlspecialchars(i18n_native($source))?>」为源语言</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 语言切换 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach ($supported as $l): ?>
      <a href="?locale=<?=urlencode($l)?>" style="padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid <?=$l===$current?'var(--accent)':'var(--border)'?>;background:<?=$l===$current?'var(--accent)':''?>;color:<?=$l===$current?'var(--on-accent)':'var(--muted)'?>">
        <?=htmlspecialchars(i18n_native($l))?>
        <?php if ($l !== $source): ?><span style="opacity:.75">· <?=$rate?>%</span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- 概览 -->
    <div class="card" style="margin-bottom:16px;padding:14px 18px">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <span>键总数：<b><?=$total?></b></span>
        <span>已翻译：<b style="color:var(--ok)"><?=$total-$untranslated?></b></span>
        <span>未翻译：<b style="color:<?=$untranslated?'var(--warn)':'var(--ok)'?>"><?=$untranslated?></b></span>
        <span>完成度：<b><?=$rate?>%</b></span>
        <?php if ($rate < 100): ?><span style="font-size:12px;color:var(--faint)">未翻译的键将显示源语言（<?=htmlspecialchars(i18n_native($source))?>）</span><?php endif; ?>
      </div>
    </div>

    <!-- 编辑表单 -->
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="locale" value="<?=htmlspecialchars($current)?>">
      <div style="display:flex;align-items:center;margin-bottom:12px">
        <h2 style="margin:0">「<?=htmlspecialchars(i18n_native($current))?>」翻译包</h2>
        <button class="btn btn-primary btn-sm" style="margin-left:auto">保存全部</button>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead><tr style="color:var(--text-3);text-align:left">
            <th style="padding:8px;border-bottom:1px solid var(--border);width:24%">翻译键</th>
            <th style="padding:8px;border-bottom:1px solid var(--border)"><?=htmlspecialchars(i18n_native($source))?>（源）</th>
            <th style="padding:8px;border-bottom:1px solid var(--border);width:34%"><?=htmlspecialchars(i18n_native($current))?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($keys as $k):
            $src = $sourceDict[$k] ?? '';
            $cur = $currentDict[$k] ?? '';
            $missing = $src !== '' && $cur === '';
          ?>
            <tr>
              <td style="padding:6px 8px;border-bottom:1px solid var(--border);color:var(--text-2)"><code style="font-size:12px"><?=htmlspecialchars($k)?></code></td>
              <td style="padding:6px 8px;border-bottom:1px solid var(--border);color:var(--text-3)"><?=htmlspecialchars($src)?></td>
              <td style="padding:6px 8px;border-bottom:1px solid var(--border)">
                <input type="text" name="trans[<?=htmlspecialchars($k)?>]" value="<?=htmlspecialchars($cur)?>" placeholder="<?=$missing?'（未翻译，将显示源语言）':''?>" style="width:100%;padding:7px 10px;border:1.5px solid <?=$missing?'var(--warn)':'var(--border)'?>;border-radius:8px;font-size:13px;background:<?=$missing?'color-mix(in oklab,var(--warn) 8%,transparent)':'transparent'?>">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="display:flex;gap:8px;margin-top:14px;align-items:center">
        <button class="btn btn-primary">保存全部</button>
        <span style="font-size:12px;color:var(--faint)">编辑「<?=htmlspecialchars(i18n_native($source))?>」会同步源语言值</span>
      </div>
    </form>

    <!-- 新增 key -->
    <form method="post" class="card" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="locale" value="<?=htmlspecialchars($current)?>">
      <h2>➕ 新增翻译键</h2>
      <div class="field-row">
        <div class="field"><label>Key <span class="hint">如 nav.home</span></label><input type="text" name="new_key" placeholder="nav.home"></div>
        <div class="field"><label><?=htmlspecialchars(i18n_native($current))?> 翻译</label><input type="text" name="new_value" placeholder="Home"></div>
      </div>
      <button class="btn">新增</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
