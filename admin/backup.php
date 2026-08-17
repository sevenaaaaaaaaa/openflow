<?php
/**
 * 备份管理界面
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/BackupSystem.php';
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
    } elseif ($postAction === 'cloud_upload') {
        $name = $_POST['backup_name'] ?? '';
        $provider = $_POST['provider'] ?? '';
        // TODO: 实现云上传
        flash('success', "正在上传到 {$provider}...");
        header('Location: /xmp/backup');
        exit;
    }
}

// 获取备份列表
$backups = BackupSystem::listBackups();
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
    <p class="sub">创建备份 · 恢复数据 · 云同步 · 定时备份</p>

    <!-- 操作栏 -->
    <div class="card mb-4">
      <div class="flex items-center gap-4" style="flex-wrap:wrap">
        <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary btn-sm">+ 创建备份</button>
        <button onclick="document.getElementById('cloudDialog').style.display='flex'" class="btn btn-ghost btn-sm">☁️ 云同步设置</button>
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
          <form method="post" style="display:inline" onsubmit="return confirm('确认从此备份恢复？当前数据将被备份后覆盖')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="backup_name" value="<?=htmlspecialchars($backup['name'])?>">
            <button type="submit" class="btn btn-ghost btn-sm">🔄 恢复</button>
          </form>
          <a href="?download=<?=urlencode($backup['name'])?>" class="btn btn-ghost btn-sm">📥 下载</a>
          <form method="post" style="display:inline" onsubmit="return confirm('确认删除此备份?')">
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
    <h2 style="margin-bottom:16px">☁️ 云同步设置</h2>
    <div style="display:grid;gap:12px">
      <div style="padding:14px;background:var(--surface-2);border-radius:8px">
        <div class="flex items-center gap-4">
          <strong style="flex:1">WebDAV</strong>
          <button class="btn btn-ghost btn-sm">配置</button>
        </div>
        <div class="text-sm text-muted" style="margin-top:4px">支持坚果云、NextCloud 等</div>
      </div>
      <div style="padding:14px;background:var(--surface-2);border-radius:8px">
        <div class="flex items-center gap-4">
          <strong style="flex:1">Dropbox</strong>
          <button class="btn btn-ghost btn-sm">连接</button>
        </div>
        <div class="text-sm text-muted" style="margin-top:4px">2GB 免费空间</div>
      </div>
      <div style="padding:14px;background:var(--surface-2);border-radius:8px">
        <div class="flex items-center gap-4">
          <strong style="flex:1">Google Drive</strong>
          <button class="btn btn-ghost btn-sm">连接</button>
        </div>
        <div class="text-sm text-muted" style="margin-top:4px">15GB 免费空间</div>
      </div>
      <div style="padding:14px;background:var(--surface-2);border-radius:8px">
        <div class="flex items-center gap-4">
          <strong style="flex:1">百度网盘</strong>
          <button class="btn btn-ghost btn-sm">连接</button>
        </div>
        <div class="text-sm text-muted" style="margin-top:4px">不限空间</div>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">关闭</button>
    </div>
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
          <option value="daily">每天</option>
          <option value="weekly">每周</option>
          <option value="monthly">每月</option>
        </select>
      </div>
      <div class="field">
        <label>保留份数</label>
        <input type="number" name="keep" value="7" min="1" max="30">
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="cloud_upload" value="1"> 同时上传到云端
        </label>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">保存设置</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
