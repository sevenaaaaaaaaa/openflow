<?php
/**
 * 历史数据迁移助手 — 老系统 → OpenFlow
 * 导入文章/线索/用户/评论，字段映射适配，预览 + 冲突处理 + 报告
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MigrationSystem.php';
require_login();
require_perm('settings');

$types = [
    'articles' => ['label' => '文章 / CMS 内容', 'icon' => '📝', 'desc' => '从旧 CMS 导入文章（标题/正文/分类/标签/SEO）'],
    'leads' => ['label' => '线索 / 潜在客户', 'icon' => '📥', 'desc' => '导入历史线索（姓名/邮箱/手机/公司/阶段）'],
    'members' => ['label' => '用户 / 会员', 'icon' => '👥', 'desc' => '导入老系统注册用户（迁移后需重置密码登录）'],
    'comments' => ['label' => '评论', 'icon' => '💬', 'desc' => '导入历史评论（按文章标识关联）'],
];

$step = $_GET['step'] ?? 'select';
$report = null;

// 步骤1：选择类型 + 上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $type = $_POST['type'] ?? 'articles';
    if (empty($_FILES['file']['tmp_name'])) { flash('error', '请选择文件'); header('Location: /xmp/migrate'); exit; }
    [$header, $rows] = migrate_parse_file($_FILES['file']['tmp_name']);
    if (empty($header) || empty($rows)) { flash('error', '文件为空或格式不正确（需 CSV 或 JSON）'); header('Location: /xmp/migrate'); exit; }
    // 存临时数据到 session，进入步骤2（映射）
    $_SESSION['migrate'] = ['type' => $type, 'header' => $header, 'rows' => array_slice($rows, 0, 50), 'total' => count($rows), 'filename' => $_FILES['file']['name']];
    header('Location: /xmp/migrate?step=map');
    exit;
}

// 步骤2：映射 + 导入
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $mig = $_SESSION['migrate'] ?? null;
    if (!$mig) { header('Location: /xmp/migrate'); exit; }
    $map = $_POST['map'] ?? [];  // new_field => old_column
    // 重新解析完整文件（从上传的原始行）
    $fullRows = isset($_SESSION['migrate_full']) ? $_SESSION['migrate_full'] : $mig['rows'];
    $mapped = migrate_apply_map($fullRows, $map);
    $type = $mig['type'];
    $fn = 'migrate_import_' . $type;
    if (function_exists($fn)) {
        $report = $fn($mapped);
        $report['type'] = $types[$type]['label'];
        $report['total'] = count($mapped);
    }
    unset($_SESSION['migrate'], $_SESSION['migrate_full']);
    $step = 'done';
}

// 从 session 恢复映射步骤数据
$mig = $_SESSION['migrate'] ?? null;
if ($mig && $step === 'map') $fields = migrate_fields($mig['type']);

admin_header('数据迁移');
?>
<style>
.mig-step{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.mig-step .st{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:6px 14px;border-radius:999px;border:1px solid var(--border);color:var(--muted)}
.mig-step .st.on{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
.type-card{border:1px solid var(--border);border-radius:14px;padding:18px;background:var(--surface);cursor:pointer;transition:border-color .2s}
.type-card:hover{border-color:var(--accent)}
.type-card.sel{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.map-row{display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-soft)}
.map-row .target{font-size:13px;font-weight:600}
.map-row select{padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;background:var(--surface);font-size:13px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('migrate'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>数据迁移助手</h1><p class="v-sub">把老系统（CMS/CRM/会员/评论）的历史数据迁移到 OpenFlow，字段映射适配，完成新老系统切换。</p></div>
    </div>

    <div class="mig-step">
      <span class="st <?=$step==='select'||$step==='done'?'on':''?>">1 · 选择类型与文件</span>
      <span class="st <?=$step==='map'?'on':''?>">2 · 字段映射</span>
      <span class="st <?=$step==='done'?'on':''?>">3 · 完成</span>
    </div>

    <?php if ($step === 'select'): ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="upload" value="1">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">① 选择要迁移的数据类型</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:20px" id="typeGrid">
        <?php $first = true; foreach ($types as $k => $t): ?>
        <label class="type-card <?=$first?'sel':''?>">
          <input type="radio" name="type" value="<?=$k?>" <?=$first?'checked':''?> onchange="document.querySelectorAll('.type-card').forEach(function(c){c.classList.remove('sel')});this.closest('.type-card').classList.add('sel')" style="display:none">
          <div style="font-size:24px"><?=$t['icon']?></div>
          <div style="font-weight:700;margin-top:8px"><?=htmlspecialchars($t['label'])?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px"><?=htmlspecialchars($t['desc'])?></div>
        </label>
        <?php $first = false; endforeach; ?>
      </div>
      <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">② 上传文件（CSV 或 JSON）</h3>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="file" name="file" accept=".csv,.json,text/csv,application/json" required style="font-size:13px">
        <button class="btn btn-p">解析并预览 →</button>
      </div>
      <p style="font-size:12px;color:var(--faint);margin-top:12px">CSV 需含表头；JSON 支持数组 <code>[{...},{...}]</code> 或 <code>{"data":[...]}</code>。上传后进入字段映射，把老系统的列对应到 OpenFlow 字段。</p>
    </form>

    <?php elseif ($step === 'map' && $mig): ?>
    <form method="post">
      <input type="hidden" name="import" value="1">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:8px">字段映射 — <?=htmlspecialchars($types[$mig['type']]['label'])?></h3>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">文件「<?=htmlspecialchars($mig['filename'])?>」共 <b><?=$mig['total']?></b> 行，检测到列：<b><?=htmlspecialchars(implode(' / ', $mig['header']))?></b>。把左侧目标字段对应到文件里的列：</p>

      <div class="card" style="padding:8px 20px;margin-bottom:16px">
        <?php foreach ($fields as $newField => $label): ?>
        <div class="map-row">
          <span class="target"><?=htmlspecialchars($label)?></span>
          <span style="color:var(--faint)">←</span>
          <select name="map[<?=$newField?>]">
            <option value="">— 不导入 —</option>
            <?php foreach ($mig['header'] as $col): ?>
            <option value="<?=htmlspecialchars($col)?>" <?=mb_strtolower($col) === $newField ? 'selected' : ''?>><?=htmlspecialchars($col)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>
      </div>

      <h3 style="font-size:13px;font-weight:700;margin-bottom:8px">数据预览（前 5 行）</h3>
      <div class="card" style="padding:0;overflow:auto;margin-bottom:16px">
        <table>
          <thead><tr><?php foreach ($mig['header'] as $c): ?><th><?=htmlspecialchars($c)?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach (array_slice($mig['rows'], 0, 5) as $r): ?>
            <tr><?php foreach ($mig['header'] as $c): ?><td style="font-size:12px"><?=htmlspecialchars(mb_substr($r[$c] ?? '', 0, 40))?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button class="btn btn-p">开始导入 <?=$mig['total']?> 行 →</button>
    </form>

    <?php elseif ($step === 'done' && $report): ?>
    <div class="card" style="padding:30px;text-align:center">
      <div style="font-size:40px;margin-bottom:10px">✅</div>
      <h2 style="font-size:20px;font-weight:800">迁移完成</h2>
      <p style="font-size:13px;color:var(--muted);margin-top:6px"><?=htmlspecialchars($report['type'])?> · 共 <?=$report['total'] ?? 0?> 条</p>
      <div style="display:flex;gap:16px;justify-content:center;margin:20px 0">
        <div style="padding:16px 24px;border-radius:14px;background:var(--ok-soft)"><div style="font-size:26px;font-weight:800;color:var(--ok)"><?=$report['imported']??0?></div><div style="font-size:12px;color:var(--muted)">成功导入</div></div>
        <div style="padding:16px 24px;border-radius:14px;background:var(--hover)"><div style="font-size:26px;font-weight:800;color:var(--muted)"><?=$report['skipped']??0?></div><div style="font-size:12px;color:var(--muted)">跳过(缺失/重复)</div></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:center">
        <a href="/xmp/migrate" class="btn btn-s">继续导入</a>
        <?php if (in_array($report['type'] ?? '', ['文章 / CMS 内容','评论'])): ?><a href="/xmp/articles" class="btn btn-p">查看文章 →</a><?php endif; ?>
        <?php if (($report['type'] ?? '') === '线索 / 潜在客户'): ?><a href="/xmp/crm" class="btn btn-p">查看线索 →</a><?php endif; ?>
        <?php if (($report['type'] ?? '') === '用户 / 会员'): ?><a href="/xmp/users" class="btn btn-p">查看用户 →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
