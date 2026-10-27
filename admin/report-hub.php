<?php
/**
 * 报告中心 — 托管静态/交互报告，带版本、权限、分享链接
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ReportHub.php';
require_login();
require_perm('settings');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'create') {
            $r = rh_create_project(
                strtolower(trim($_POST['slug'] ?? '')),
                trim($_POST['name'] ?? ''),
                trim($_POST['description'] ?? ''),
                $_POST['visibility'] ?? 'members',
                $_SESSION['admin_user'] ?? 'admin'
            );
            if ($r['ok']) $msg = '项目已创建'; else $err = $r['error'];
        } elseif ($act === 'publish') {
            $slug = $_POST['slug'] ?? '';
            $src = trim($_POST['src'] ?? '');
            // zip 上传优先
            if (!empty($_FILES['zip']['tmp_name']) && is_uploaded_file($_FILES['zip']['tmp_name'])) {
                $tmp = DATA_DIR . '/reports-inbox/' . bin2hex(random_bytes(6));
                mkdir($tmp, 0755, true);
                $zip = new ZipArchive();
                if ($zip->open($_FILES['zip']['tmp_name']) === true) {
                    $zip->extractTo($tmp);
                    $zip->close();
                    // 解压后若只有一个顶层目录，进去
                    $items = array_values(array_diff(scandir($tmp) ?: [], ['.', '..']));
                    $src = (count($items) === 1 && is_dir($tmp . '/' . $items[0])) ? $tmp . '/' . $items[0] : $tmp;
                } else { $err = 'zip 解压失败'; }
            }
            if ($err === '') {
                $r = rh_publish($slug, $src, trim($_POST['note'] ?? ''), $_SESSION['admin_user'] ?? 'admin');
                if ($r['ok']) $msg = "已发布 v{$r['version']}（{$r['files']} 个文件，" . number_format($r['bytes'] / 1024) . ' KB）';
                else $err = $r['error'];
            }
        } elseif ($act === 'activate') {
            $p = rh_project($_POST['slug'] ?? '');
            if ($p && rh_activate((int)$p['id'], (int)($_POST['version'] ?? 0))) $msg = '已切换版本';
            else $err = '切换失败';
        } elseif ($act === 'visibility') {
            $p = rh_project($_POST['slug'] ?? '');
            if ($p && rh_set_visibility((int)$p['id'], $_POST['visibility'] ?? 'members', (string)($_POST['password'] ?? ''))) $msg = '权限已更新';
            else $err = '更新失败';
        } elseif ($act === 'share') {
            $t = rh_make_share($_POST['slug'] ?? '', trim($_POST['share_note'] ?? ''));
            if ($t) { $msg = '分享链接：' . rtrim(SITE_URL, '/') . '/r/' . $_POST['slug'] . '?t=' . $t; }
            else $err = '生成失败';
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$projects = rh_projects();
admin_header('报告中心');
?>
<div class="page-head">
  <h1>报告中心</h1>
  <p class="text-muted">托管报告与交互页：版本可回滚、权限可控、链接可分享。</p>
</div>

<?php if ($msg): ?><div style="margin-bottom:14px;padding:10px 14px;border-radius:10px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if ($err): ?><div style="margin-bottom:14px;padding:10px 14px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div class="card" style="margin-bottom:18px">
  <h2 style="margin-top:0">新建项目</h2>
  <form method="post" class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="create">
    <div class="field"><label>Slug（URL 用）</label><input name="slug" placeholder="openflow-report" required></div>
    <div class="field"><label>名称</label><input name="name" placeholder="OpenFlow 渠道洞察" required></div>
    <div class="field"><label>可见性</label>
      <select name="visibility">
        <option value="members">members（登录可见）</option>
        <option value="link">link（凭分享链接）</option>
        <option value="password">password（口令）</option>
        <option value="public">public（公开）</option>
      </select>
    </div>
    <div><button class="btn btn-primary">创建</button></div>
  </form>
</div>

<?php foreach ($projects as $p): $vs = rh_versions((int)$p['id']); $act = rh_active_version((int)$p['id']); ?>
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div>
      <h2 style="margin:0"><?=htmlspecialchars((string)$p['name'])?>
        <span class="badge"><?=htmlspecialchars((string)$p['visibility'])?></span>
        <?php if ($act): ?><span class="badge">v<?=(int)$act['version']?> active</span><?php endif; ?>
      </h2>
      <div class="text-muted text-sm" style="margin-top:4px">
        /r/<?=htmlspecialchars((string)$p['slug'])?> · 更新 <?=htmlspecialchars((string)$p['updated_at'])?>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-sm" href="<?=htmlspecialchars(rtrim(SITE_URL,'/'))?>/r/<?=htmlspecialchars((string)$p['slug'])?>" target="_blank" rel="noopener">打开</a>
    </div>
  </div>

  <details style="margin-top:12px">
    <summary style="cursor:pointer">发布新版本</summary>
    <form method="post" enctype="multipart/form-data" style="margin-top:10px;display:grid;gap:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="publish">
      <input type="hidden" name="slug" value="<?=htmlspecialchars((string)$p['slug'])?>">
      <div class="field"><label>服务器上的目录/文件路径</label>
        <input name="src" placeholder="/www/ops/openflow/report/pages">
        <div class="text-muted text-sm">和下面的 zip 二选一；zip 优先。</div>
      </div>
      <div class="field"><label>或上传 zip</label><input type="file" name="zip" accept=".zip"></div>
      <div class="field"><label>版本说明</label><input name="note" placeholder="2026-10-27 每日构建"></div>
      <div><button class="btn btn-primary btn-sm">发布</button></div>
    </form>
  </details>

  <details style="margin-top:8px">
    <summary style="cursor:pointer">权限与分享</summary>
    <form method="post" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="visibility">
      <input type="hidden" name="slug" value="<?=htmlspecialchars((string)$p['slug'])?>">
      <div class="field"><label>可见性</label>
        <select name="visibility">
          <?php foreach (['members','link','password','public'] as $v): ?>
          <option value="<?=$v?>" <?=$p['visibility']===$v?'selected':''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>设为口令（留空不改）</label><input name="password" type="password" placeholder="新口令"></div>
      <div><button class="btn btn-sm">保存</button></div>
    </form>
    <form method="post" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="share">
      <input type="hidden" name="slug" value="<?=htmlspecialchars((string)$p['slug'])?>">
      <div class="field"><label>分享备注</label><input name="share_note" placeholder="给投资人的链接"></div>
      <div><button class="btn btn-sm">生成分享链接</button></div>
    </form>
    <?php $sh = rh_shares((int)$p['id']); if ($sh): ?>
    <table style="margin-top:10px">
      <thead><tr><th>链接</th><th>备注</th><th>创建</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($sh, 0, 8) as $s): ?>
        <tr>
          <td class="text-sm"><code>/r/<?=htmlspecialchars((string)$p['slug'])?>?t=<?=htmlspecialchars((string)$s['token'])?></code></td>
          <td class="text-sm"><?=htmlspecialchars((string)$s['note'])?></td>
          <td class="text-sm text-muted"><?=htmlspecialchars((string)$s['created_at'])?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </details>

  <?php if ($vs): ?>
  <table style="margin-top:12px">
    <thead><tr><th>版本</th><th>说明</th><th>文件</th><th>大小</th><th>状态</th><th>时间</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($vs as $v): ?>
      <tr>
        <td>v<?=(int)$v['version']?></td>
        <td class="text-sm"><?=htmlspecialchars((string)$v['note'])?></td>
        <td class="text-sm"><?=(int)$v['file_count']?></td>
        <td class="text-sm"><?=number_format(((int)$v['bytes'])/1024, 0)?> KB</td>
        <td><span class="badge"><?=htmlspecialchars((string)$v['status'])?></span></td>
        <td class="text-sm text-muted"><?=htmlspecialchars((string)$v['created_at'])?></td>
        <td>
          <?php if ($v['status'] !== 'active'): ?>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="activate">
            <input type="hidden" name="slug" value="<?=htmlspecialchars((string)$p['slug'])?>">
            <input type="hidden" name="version" value="<?=(int)$v['version']?>">
            <button class="btn btn-sm">回滚到此版本</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="text-muted text-sm" style="margin-top:10px">还没有发布过版本。</p>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php admin_footer(); ?>
