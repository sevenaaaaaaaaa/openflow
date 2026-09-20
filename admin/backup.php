<?php
/**
 * 备份管理界面
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/BackupSystem.php';
require_once __DIR__ . '/../lib/OffsiteBackup.php';
require_login();
require_perm('settings');

$action = $_GET['action'] ?? '';

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create_backup') {
        $name = trim($_POST['name'] ?? '');
        $backupFile = BackupSystem::createFullBackup($name);
        flash('success', '备份已创建：' . basename($backupFile));
        header('Location: /xmp/backup');
        exit;
    } elseif ($postAction === 'restore') {
        $name = $_POST['backup_name'] ?? '';
        if ($name) {
            // 创建当前状态的备份再恢复
            BackupSystem::createFullBackup('pre_restore_' . date('Y-m-d_His'));
            $result = BackupSystem::restore($name);
            if ($result) {
                flash('success', "已从备份 {$name} 恢复");
            } else {
                flash('error', '恢复失败');
            }
        }
        header('Location: /xmp/backup');
        exit;
    } elseif ($postAction === 'delete') {
        $name = $_POST['backup_name'] ?? '';
        if ($name) {
            BackupSystem::deleteBackup($name);
            flash('success', '备份已删除');
        }
        header('Location: /xmp/backup');
        exit;
    } elseif ($postAction === 'save_schedule') {
        // 以前这个分支不存在 → 「定时备份」是死表单（配置存不下来、也没有执行者）
        $cfg = backup_schedule_set([
            'enabled'   => isset($_POST['enabled']),
            'frequency' => $_POST['frequency'] ?? 'daily',
            'keep'      => $_POST['keep'] ?? 7,
        ]);
        flash('success', '定时备份已' . ($cfg['enabled'] ? '开启' : '保存') . '：' . $cfg['frequency'] . ' · 保留 ' . $cfg['keep'] . ' 份（由 /api/cron.php 执行）');
        header('Location: /xmp/backup');
        exit;
    } elseif ($postAction === 'cloud_upload') {
        // 异地备份：打包这一份并推到已配置的对象存储（S3 / R2 兼容）
        $name = (string)($_POST['backup_name'] ?? '');
        $r = offsite_sync($name);
        if (($r['status'] ?? '') === 'done') {
            flash('success', '已上传到异地：' . ($r['key'] ?? '') . '（' . number_format(((int)($r['bytes'] ?? 0)) / 1048576, 1) . ' MB）');
        } elseif (($r['status'] ?? '') === 'disabled') {
            flash('error', '异地备份未开启：先在「☁️ 异地备份」里配置并勾选开启');
        } else {
            flash('error', '上传失败：' . ($r['detail'] ?? '未知错误'));
        }
        header('Location: /xmp/backup');
        exit;
    } elseif ($postAction === 'save_offsite') {
        $cfg = offsite_config_set([
            'enabled'    => isset($_POST['offsite_enabled']),
            'endpoint'   => $_POST['endpoint'] ?? '',
            'bucket'     => $_POST['bucket'] ?? '',
            'region'     => $_POST['region'] ?? 'auto',
            'prefix'     => $_POST['prefix'] ?? 'openflow-backups',
            'access_key' => $_POST['access_key'] ?? '',
            'secret_key' => $_POST['secret_key'] ?? '',
        ]);
        $problem = offsite_config_problem($cfg);
        flash($problem === '' ? 'success' : 'error',
              $problem === '' ? '异地备份已保存（下次自动备份完成后会推送一份）' : '已保存，但还不能用：' . $problem);
        header('Location: /xmp/backup');
        exit;
    } elseif ($postAction === 'test_offsite') {
        // 探针：传一个几十字节的对象，把"配错了"和"传大文件超时"区分开
        $r = offsite_probe();
        flash(!empty($r['ok']) ? 'success' : 'error',
              !empty($r['ok']) ? '连通正常：已写入 ' . $r['key'] : '连不通：' . $r['error']);
        header('Location: /xmp/backup');
        exit;
    }
}

// 获取备份列表
$backups = BackupSystem::listBackups();
$offCfg = offsite_config();
$offStatus = offsite_status();
$offProblem = offsite_config_problem($offCfg);
$schedCfg = backup_schedule_get();
$totalSize = array_sum(array_map(fn($b) => $b['size'], $backups));

admin_header('备份管理');
?>
<style>
.backup-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;align-items:center;gap:16px;transition:.15s}
.backup-card:hover{border-color:var(--accent)}
.backup-icon{width:48px;height:48px;border-radius:10px;display:grid;place-items:center;font-size:20px;flex-shrink:0}
.backup-info{flex:1}
.backup-name{font-weight:600;font-size:14px}
.backup-meta{font-size:12px;color:var(--muted);margin-top:2px}
.backup-actions{display:flex;gap:6px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0"> 备份管理</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=count($backups)?> 个备份</span>
        <span class="badge badge-gray"><?=number_format($totalSize / 1024 / 1024, 1)?> MB</span>
      </div>
    </div>
    <p class="sub">创建备份 · 恢复数据 · 异地备份 · 定时备份</p>

    <?php
      // 备份的真问题不是"有没有备份"，而是"备份在不在另一块盘上"。所以这条状态放在最显眼处。
      if (!$offCfg['enabled']):
        $tone = 'warn'; $txt = '异地备份未开启 —— 当前备份与站点在同一块盘，整机故障会一起丢失。';
      elseif ($offProblem !== ''):
        $tone = 'err'; $txt = '异地备份已开启但配置不完整：' . $offProblem;
      elseif ($offStatus['last_ok'] === ''):
        $tone = 'warn'; $txt = '异地备份已配置，但还没有成功上传过。可以点「测试连通」先验证。';
      elseif ($offStatus['error'] !== ''):
        $tone = 'err'; $txt = '最近一次上传失败（' . $offStatus['error'] . '），上次成功：' . $offStatus['last_ok'];
      else:
        $tone = 'ok'; $txt = '异地备份正常，最近一次成功：' . $offStatus['last_ok']
             . '（' . htmlspecialchars($offStatus['key']) . '，' . number_format($offStatus['bytes'] / 1048576, 1) . ' MB）';
      endif;
      $color = $tone === 'ok' ? 'var(--ok)' : ($tone === 'err' ? 'var(--danger,#e5484d)' : 'var(--warn,#f5a524)');
    ?>
    <div class="card mb-4" style="border-left:3px solid <?= $color ?>">
      <div class="flex items-center gap-4" style="flex-wrap:wrap">
        <span style="flex:1;min-width:240px"><?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8', false) ?></span>
        <button onclick="document.getElementById('cloudDialog').style.display='flex'" class="btn btn-primary btn-sm">配置异地备份</button>
      </div>
    </div>

    <!-- 操作栏 -->
    <div class="card mb-4">
      <div class="flex items-center gap-4" style="flex-wrap:wrap">
        <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary btn-sm">+ 创建备份</button>
        <button onclick="document.getElementById('cloudDialog').style.display='flex'" class="btn btn-ghost btn-sm">☁️ 异地备份</button>
        <button onclick="document.getElementById('scheduleDialog').style.display='flex'" class="btn btn-ghost btn-sm">⏰ 定时备份</button>
        <div style="margin-left:auto">
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_backup">
            <input type="hidden" name="name" value="quick_<?=date('Y-m-d_His')?>">
            <button type="submit" class="btn btn-ghost btn-sm">⚡ 快速备份</button>
          </form>
        </div>
      </div>
    </div>

    <!-- 备份列表 -->
    <?php if (empty($backups)): ?>
    <div class="card">
      <div class="empty" style="padding:40px">
        <div style="font-size:48px;margin-bottom:12px">💾</div>
        <p>暂无备份</p>
        <p class="text-sm text-muted">创建第一个备份以保护您的数据</p>
        <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary" style="margin-top:16px">创建备份</button>
      </div>
    </div>
    <?php else: ?>
    <div style="display:grid;gap:12px">
      <?php foreach ($backups as $backup): ?>
      <div class="backup-card">
        <div class="backup-icon" style="background:var(--surface-2)">📦</div>
        <div class="backup-info">
          <div class="backup-name"><?=htmlspecialchars($backup['name'])?></div>
          <div class="backup-meta">
            <?=date('Y-m-d H:i:s', strtotime($backup['created_at']))?>
            · <?=number_format($backup['size'] / 1024 / 1024, 1)?> MB
          </div>
        </div>
        <div class="backup-actions">
          <form method="post" style="display:inline" data-confirm="确认从此备份恢复？当前数据将被备份后覆盖">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="backup_name" value="<?=htmlspecialchars($backup['name'])?>">
            <button type="submit" class="btn btn-ghost btn-sm">🔄 恢复</button>
          </form>
          <a href="?download=<?=urlencode($backup['name'])?>" class="btn btn-ghost btn-sm">📥 下载</a>
          <form method="post" style="display:inline" data-confirm="确认删除此备份?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="backup_name" value="<?=htmlspecialchars($backup['name'])?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">🗑️</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 云服务状态 -->
    <div class="card" style="margin-top:20px">
      <h2>☁️ 云同步状态</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:12px">
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <div class="flex items-center gap-2">
            <span style="color:var(--ok)">●</span>
            <strong>WebDAV</strong>
          </div>
          <div class="text-sm text-muted" style="margin-top:4px">未配置</div>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <div class="flex items-center gap-2">
            <span style="color:var(--faint)">●</span>
            <strong>Dropbox</strong>
          </div>
          <div class="text-sm text-muted" style="margin-top:4px">未配置</div>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <div class="flex items-center gap-2">
            <span style="color:var(--faint)">●</span>
            <strong>Google Drive</strong>
          </div>
          <div class="text-sm text-muted" style="margin-top:4px">未配置</div>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <div class="flex items-center gap-2">
            <span style="color:var(--faint)">●</span>
            <strong>百度网盘</strong>
          </div>
          <div class="text-sm text-muted" style="margin-top:4px">未配置</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 创建备份对话框 -->
<div id="createDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:90%;max-width:400px">
    <h2 style="margin-bottom:16px">创建备份</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_backup">
      <div class="field">
        <label>备份名称</label>
        <input type="text" name="name" value="<?=date('Y-m-d_His')?>" placeholder="可选">
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">创建</button>
      </div>
    </form>
  </div>
</div>

<!-- 云同步设置对话框 -->
<div id="cloudDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:90%;max-width:500px">
    <h2 style="margin-bottom:6px">☁️ 异地备份</h2>
    <p class="sub" style="margin-bottom:16px">S3 兼容对象存储（Cloudflare R2 / 阿里云 OSS / MinIO 均可）。自动备份完成后会自动推送一份。</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_offsite">
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="offsite_enabled" value="1" <?= $offCfg['enabled'] ? 'checked' : '' ?>> 开启异地备份
        </label>
      </div>
      <div class="field">
        <label>Endpoint</label>
        <input type="text" name="endpoint" value="<?= htmlspecialchars($offCfg['endpoint']) ?>" placeholder="https://&lt;账号ID&gt;.r2.cloudflarestorage.com">
      </div>
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
        <div class="field">
          <label>Bucket</label>
          <input type="text" name="bucket" value="<?= htmlspecialchars($offCfg['bucket']) ?>" placeholder="openflow-backups">
        </div>
        <div class="field">
          <label>Region</label>
          <input type="text" name="region" value="<?= htmlspecialchars($offCfg['region']) ?>" placeholder="auto">
        </div>
      </div>
      <div class="field">
        <label>对象前缀</label>
        <input type="text" name="prefix" value="<?= htmlspecialchars($offCfg['prefix']) ?>" placeholder="openflow-backups">
      </div>
      <div class="field">
        <label>Access Key <span class="text-muted">（留空 = 不修改）</span></label>
        <input type="text" name="access_key" value="" autocomplete="off" placeholder="<?= $offCfg['access_key'] !== '' ? '已配置，留空则不改' : '未配置' ?>">
      </div>
      <div class="field">
        <label>Secret Key <span class="text-muted">（留空 = 不修改）</span></label>
        <input type="password" name="secret_key" value="" autocomplete="new-password" placeholder="<?= $offCfg['secret_key'] !== '' ? '已配置，留空则不改' : '未配置' ?>">
      </div>
      <p class="sub" style="margin:0 0 12px">更推荐用环境变量注入密钥：<code>OF_OFFSITE_KEY</code> / <code>OF_OFFSITE_SECRET</code>（优先级高于这里，且不落到 data/）。</p>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">保存</button>
      </div>
    </form>
    <form method="post" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test_offsite">
      <button type="submit" class="btn btn-ghost btn-sm">测试连通（上传一个探针对象）</button>
    </form>
  </div>
</div>

<!-- 定时备份对话框 -->
<div id="scheduleDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:90%;max-width:400px">
    <h2 style="margin-bottom:16px">⏰ 定时备份</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_schedule">
      <div class="field">
        <label>备份频率</label>
        <select name="frequency">
          <option value="daily" <?= $schedCfg['frequency'] === 'daily' ? 'selected' : '' ?>>每天</option>
          <option value="weekly" <?= $schedCfg['frequency'] === 'weekly' ? 'selected' : '' ?>>每周</option>
          <option value="monthly" <?= $schedCfg['frequency'] === 'monthly' ? 'selected' : '' ?>>每月</option>
        </select>
      </div>
      <div class="field">
        <label>保留份数</label>
        <input type="number" name="keep" value="<?= (int) $schedCfg['keep'] ?>" min="1" max="30">
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="enabled" value="1" <?= $schedCfg['enabled'] ? 'checked' : '' ?>> 开启定时备份
        </label>
        <p class="sub" style="margin:6px 0 0">异地推送在「☁️ 异地备份」里单独配置，开启后每次自动备份完成会自动推一份。</p>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
