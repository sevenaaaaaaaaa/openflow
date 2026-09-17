<?php
/**
 * 顶栏导航编辑 —— data/nav.json 的可视化编辑器
 *
 * 为什么有这个页：顶栏导航由 data/nav.json 驱动（includes/site-nav.php 注入 window.OF_NAV），
 * 但后台一直没有编辑入口，只能人工/脚本改 JSON。这个页面补上：
 *   · 顶级项：改标签 / 链接 / 图标，上移下移、删除、新增
 *   · 下拉项：每个菜单的栏目（cols）与条目（含描述）可编辑
 *   · 保存前校验（id/标签/链接），保存时先备份旧版本
 * 说明：mega 的 title/blurb 等未在界面暴露的字段会原样保留（合并写回，不丢数据）。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/site-nav.php';
require_login();
require_perm('site-builder');

$file = DATA_DIR . '/nav.json';
$nav  = json_read($file);
if (empty($nav['items']) && isset($nav[0])) $nav = ['items' => $nav];   // 兼容裸数组
$items = $nav['items'] ?? [];

$msg = ''; $err = '';

/* ── 保存 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $posted = $_POST['nav'] ?? [];
    $out = [];
    $problems = [];

    foreach ($posted as $i => $row) {
        $label = trim((string)($row['label'] ?? ''));
        $href  = trim((string)($row['href'] ?? ''));
        $icon  = trim((string)($row['icon'] ?? ''));
        if ($label === '' && $href === '') continue;                  // 空行视为删除
        if ($label === '') { $problems[] = "第 " . ((int)$i + 1) . " 项缺少标签"; continue; }
        if ($href === '' || (!str_starts_with($href, '/') && !str_starts_with($href, 'http'))) {
            $problems[] = "「{$label}」的链接必须是 / 开头或 http(s)://"; continue;
        }

        $orig = $items[$i] ?? [];
        $item = $orig;                                                // 保留未暴露字段
        $item['id']    = $orig['id'] ?? preg_replace('/[^a-z0-9-]/', '-', strtolower($label));
        $item['label'] = $label;
        $item['href']  = $href;
        if ($icon !== '') $item['icon'] = $icon; else unset($item['icon']);

        // 下拉（mega）：栏目与条目
        if (!empty($row['mega'])) {
            $mega = $orig['mega'] ?? [];
            $colsOut = [];
            foreach (($row['mega']['cols'] ?? []) as $ci => $col) {
                $head = trim((string)($col['head'] ?? ''));
                $citems = [];
                foreach (($col['items'] ?? []) as $k => $mi) {
                    $t  = trim((string)($mi['t'] ?? ''));
                    $h  = trim((string)($mi['href'] ?? ''));
                    $d  = trim((string)($mi['d'] ?? ''));
                    if ($t === '' || $h === '') continue;
                    if (!str_starts_with($h, '/') && !str_starts_with($h, 'http')) { $problems[] = "「{$t}」的链接不合法"; continue; }
                    $oc = $orig['mega']['cols'][$ci]['items'][$k] ?? [];
                    $newItem = $oc; $newItem['t'] = $t; $newItem['href'] = $h;
                    if ($d !== '') $newItem['d'] = $d; else unset($newItem['d']);
                    $citems[] = $newItem;
                }
                $ocol = $orig['mega']['cols'][$ci] ?? [];
                if ($head === '' && !$citems) continue;
                $newCol = $ocol; $newCol['head'] = $head !== '' ? $head : ($ocol['head'] ?? '栏目');
                $newCol['items'] = $citems;
                $colsOut[] = $newCol;
            }
            if ($colsOut) {
                $mega['cols'] = $colsOut;
                $mega['title'] = $mega['title'] ?? $label;
                $item['mega'] = $mega;
            } else {
                unset($item['mega']);
            }
        }

        // 动作：上移 / 下移 / 删除
        $out[] = ['item' => $item, 'act' => (string)($row['_act'] ?? '')];
    }

    // 处理移动与删除（在数组层面）
    $final = [];
    foreach ($out as $i => $wrap) {
        if ($wrap['act'] === 'delete') continue;
        $final[] = $wrap;
    }
    foreach ($final as $i => &$wrap) {
        if ($wrap['act'] === 'up' && $i > 0) { $tmp = $final[$i - 1]; $final[$i - 1] = $wrap; $wrap = $tmp; }
    }
    unset($wrap);
    foreach ($final as $i => &$wrap) {
        if ($wrap['act'] === 'down' && $i < count($final) - 1) { $tmp = $final[$i + 1]; $final[$i + 1] = $wrap; $wrap = $tmp; }
    }
    unset($wrap);

    $itemsNew = array_map(fn($w) => $w['item'], $final);

    if ($problems) {
        $err = '未保存：' . implode('；', array_slice($problems, 0, 3));
        $items = $posted;   // 回显用户输入（不落盘）
    } else {
        // 备份旧版本，再写入
        @copy($file, DATA_DIR . '/nav.backup-' . date('Ymd-His') . '.json');
        $nav['items'] = $itemsNew;
        $nav['version'] = (int)($nav['version'] ?? 1) + 1;
        $nav['updated'] = date('Y-m-d H:i');
        if (json_write($file, $nav)) {
            $items = $itemsNew;
            $msg = '已保存（v' . $nav['version'] . '，旧版本已备份到 data/nav.backup-*.json）';
        } else {
            $err = '写入失败：data/ 目录不可写？';
        }
    }
}

$navIcons = ['home', 'box', 'bolt', 'book', 'doc', 'users', 'search', 'info', 'gear', 'cart', 'reply', 'star'];

admin_header('顶栏导航');
?>
<style>
.nv-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px;margin-bottom:12px}
.nv-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.nv-head input{padding:7px 10px;border:1px solid var(--border);border-radius:8px;font-size:13.5px}
.nv-col{border:1px dashed var(--border);border-radius:10px;padding:10px;margin:8px 0}
.nv-row{display:grid;grid-template-columns:1fr 1.4fr 1.2fr auto;gap:6px;margin:6px 0;align-items:center}
.nv-row input{padding:6px 9px;border:1px solid var(--border);border-radius:7px;font-size:13px;width:100%}
.nv-mini{font-size:11px;color:var(--faint)}
.nv-actions{display:flex;gap:6px;align-items:center}
</style>
<div class="admin-layout">
  <?php admin_sidebar('nav-editor'); ?>
  <div class="main">
    <div class="flex items-center gap-3 mb-3">
      <h1 style="margin:0">顶栏导航</h1>
      <span class="sub" style="margin:0">data/nav.json · v<?=(int)($nav['version'] ?? 1)?> · 更新于 <?=htmlspecialchars((string)($nav['updated'] ?? '—'))?></span>
      <a class="btn btn-ghost btn-sm" style="margin-left:auto" href="/" target="_blank">查看前台 →</a>
    </div>
    <?php if ($msg): ?><div class="msg msg-success" style="margin-bottom:12px"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg msg-error" style="margin-bottom:12px"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <p class="sub" style="margin-top:0">顶级项顺序即前台导航顺序；下拉栏目留空即不显示下拉。链接必须是 <code>/</code> 开头或 <code>http(s)://</code>。</p>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($items as $i => $it): $mega = $it['mega'] ?? null; ?>
      <div class="nv-card">
        <div class="nv-head">
          <input name="nav[<?=$i?>][label]" value="<?=htmlspecialchars((string)($it['label'] ?? ''))?>" placeholder="标签" style="width:120px" required>
          <input name="nav[<?=$i?>][href]" value="<?=htmlspecialchars((string)($it['href'] ?? ''))?>" placeholder="/path 或 https://…" style="flex:1;min-width:200px" required>
          <input name="nav[<?=$i?>][icon]" value="<?=htmlspecialchars((string)($it['icon'] ?? ''))?>" placeholder="icon" style="width:90px" list="nvIcons">
          <span class="nv-mini">id: <?=htmlspecialchars((string)($it['id'] ?? '—'))?></span>
          <span class="nv-actions" style="margin-left:auto">
            <button class="btn btn-ghost btn-sm" name="nav[<?=$i?>][_act]" value="up">↑</button>
            <button class="btn btn-ghost btn-sm" name="nav[<?=$i?>][_act]" value="down">↓</button>
            <button class="btn btn-ghost btn-sm" name="nav[<?=$i?>][_act]" value="delete" data-confirm="删除「<?=htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES)?>」？保存后生效">✕</button>
          </span>
        </div>

        <?php if ($mega): ?>
          <div class="nv-mini" style="margin:4px 0 2px">下拉菜单（title: <?=htmlspecialchars((string)($mega['title'] ?? ''))?>）</div>
          <?php foreach (($mega['cols'] ?? []) as $ci => $col): ?>
          <div class="nv-col">
            <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][head]" value="<?=htmlspecialchars((string)($col['head'] ?? ''))?>" placeholder="栏目标题" style="width:200px;padding:6px 9px;border:1px solid var(--border);border-radius:7px;font-size:13px">
            <?php foreach (($col['items'] ?? []) as $k => $mi): ?>
            <div class="nv-row">
              <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][items][<?=$k?>][t]" value="<?=htmlspecialchars((string)($mi['t'] ?? ''))?>" placeholder="条目名" required>
              <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][items][<?=$k?>][href]" value="<?=htmlspecialchars((string)($mi['href'] ?? ''))?>" placeholder="/path" required>
              <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][items][<?=$k?>][d]" value="<?=htmlspecialchars((string)($mi['d'] ?? ''))?>" placeholder="描述（可选）">
              <span class="nv-mini">清空条目名即删除</span>
            </div>
            <?php endforeach; ?>
            <?php // 新增条目：留三个空位 ?>
            <?php for ($k = count($col['items'] ?? []); $k < count($col['items'] ?? []) + 1; $k++): ?>
            <div class="nv-row">
              <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][items][<?=$k?>][t]" placeholder="+ 新增条目">
              <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][items][<?=$k?>][href]" placeholder="/path">
              <input name="nav[<?=$i?>][mega][cols][<?=$ci?>][items][<?=$k?>][d]" placeholder="描述（可选）">
              <span></span>
            </div>
            <?php endfor; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php // 新增顶级项的空行 ?>
      <div class="nv-card" style="border-style:dashed">
        <div class="nv-head">
          <input name="nav[<?=count($items)?>][label]" placeholder="+ 新增顶级项（标签）" style="width:160px">
          <input name="nav[<?=count($items)?>][href]" placeholder="/path 或 https://…" style="flex:1;min-width:200px">
          <input name="nav[<?=count($items)?>][icon]" placeholder="icon" style="width:90px" list="nvIcons">
        </div>
      </div>

      <datalist id="nvIcons"><?php foreach ($navIcons as $ic): ?><option value="<?=$ic?>"><?php endforeach; ?></datalist>
      <div style="position:sticky;bottom:0;background:var(--bg);padding:12px 0;display:flex;gap:10px;align-items:center;border-top:1px solid var(--border)">
        <button class="btn btn-primary">保存导航</button>
        <span class="sub" style="margin:0">保存前自动备份旧版本；保存后清页面缓存即生效</span>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
