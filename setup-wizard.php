<?php
/**
 * 安装向导 — 首次部署引导
 * 步骤：环境检查 → 基础设置 → 管理员创建 → 完成
 */
require_once __DIR__ . '/admin/config.php';

// 已安装则跳转
if (is_installed() && !isset($_GET['force'])) {
    header('Location: /');
    exit;
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);

    if ($step === 2) {
        // 基础设置
        $siteName = trim($_POST['site_name'] ?? '');
        $siteUrl = trim($_POST['site_url'] ?? '');
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';
        $adminEmail = trim($_POST['admin_email'] ?? '');

        if (empty($siteName)) { $error = '请输入站点名称'; $step = 2; }
        elseif (empty($adminUser)) { $error = '请输入管理员用户名'; $step = 2; }
        elseif (strlen($adminPass) < 6) { $error = '密码至少 6 位'; $step = 2; }
        elseif ($adminPass !== $adminPass2) { $error = '两次密码不一致'; $step = 2; }
        else {
            // 写入站点配置
            $settingsFile = DATA_DIR . '/settings.json';
            $settings = json_read($settingsFile);
            $settings['site_name'] = $siteName;
            $settings['site_url'] = $siteUrl ?: '/';
            $settings['installed_at'] = date('Y-m-d H:i:s');
            $settings['installed'] = true;
            $settings['version'] = '2.0.0';
            json_write($settingsFile, $settings);

            // 创建管理员
            $usersFile = DATA_DIR . '/users.json';
            $users = json_read($usersFile);
            if (empty($users)) {
                $users['admin'] = [
                    'name' => $adminUser,
                    'username' => 'admin',
                    'password' => password_hash($adminPass, PASSWORD_DEFAULT),
                    'email' => $adminEmail,
                    'role' => 'admin',
                    'permissions' => array_keys(PERMISSIONS),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                json_write($usersFile, $users);
            }

            // 创建必要目录
            foreach (['articles', 'uploads', 'uploads/articles', 'uploads/documents', 'leads', 'members', 'courses', 'crm'] as $dir) {
                $d = DATA_DIR . '/' . $dir;
                if (!is_dir($d)) mkdir($d, 0755, true);
            }

            // 初始化数据文件
            foreach (['articles' => [], 'leads' => [], 'members' => [], 'products' => [], 'orders' => [], 'courses' => []] as $file => $default) {
                $f = DATA_DIR . '/' . $file . '/index.json';
                if (!file_exists($f)) json_write($f, $default);
            }

            $success = '基础设置完成';
            $step = 3;
        }
    }

    if ($step === 3 && isset($_POST['migration_type'])) {
        // 数据迁移
        $migrationType = $_POST['migration_type'] ?? '';
        $migrationResults = [];

        if ($migrationType === 'json' && !empty($_FILES['migration_file']['tmp_name'])) {
            $content = file_get_contents($_FILES['migration_file']['tmp_name']);
            $data = json_decode($content, true);
            if ($data && is_array($data)) {
                foreach ($data as $key => $items) {
                    if (is_array($items)) {
                        $file = DATA_DIR . '/' . $key . '/index.json';
                        $dir = dirname($file);
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        json_write($file, $items);
                        $migrationResults[] = "✓ 导入 {$key}: " . count($items) . " 条";
                    }
                }
            }
        } elseif ($migrationType === 'csv' && !empty($_FILES['migration_file']['tmp_name']) && !empty($_POST['csv_type'])) {
            $csvType = $_POST['csv_type'];
            if (($fp = fopen($_FILES['migration_file']['tmp_name'], 'r')) !== false) {
                $header = fgetcsv($fp);
                $imported = 0;
                while (($row = fgetcsv($fp)) !== false) {
                    $data = array_combine($header, array_pad($row, count($header), ''));
                    if ($csvType === 'articles') {
                        $id = 'article_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
                        $article = ['id'=>$id,'title'=>$data['title']??'','slug'=>$data['slug']??'','content'=>$data['content']??'','status'=>$data['status']??'draft','category'=>$data['category']??'','tags'=>array_filter(explode(',',$data['tags']??'')),'cover'=>$data['cover']??'','created_at'=>$data['created_at']??date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
                        $articles = json_read(DATA_DIR.'/articles/index.json'); $articles[]=$article; json_write(DATA_DIR.'/articles/index.json',$articles); $imported++;
                    } elseif ($csvType === 'members') {
                        $id = 'mem_'.bin2hex(random_bytes(8));
                        $member = ['id'=>$id,'name'=>$data['name']??'','email'=>$data['email']??'','phone'=>$data['phone']??'','created_at'=>$data['created_at']??date('Y-m-d H:i:s')];
                        $members = json_read(DATA_DIR.'/members/index.json'); $members[]=$member; json_write(DATA_DIR.'/members/index.json',$members); $imported++;
                    }
                }
                fclose($fp);
                $migrationResults[] = "✓ 导入 {$csvType}: {$imported} 条";
            }
        } elseif ($migrationType === 'wordpress' && !empty($_FILES['migration_file']['tmp_name'])) {
            $xml = @simplexml_load_file($_FILES['migration_file']['tmp_name']);
            if ($xml) {
                $imported = 0;
                foreach ($xml->channel->item as $item) {
                    $postType = (string)$item->children('wp',true)->post_type ?? 'post';
                    if ($postType !== 'post') continue;
                    $id = 'article_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(4)),0,8);
                    $article = ['id'=>$id,'title'=>(string)$item->title,'slug'=>basename((string)$item->link),'content'=>(string)($item->children('content',true)->encoded??$item->description),'status'=>'published','category'=>'','tags'=>[],'cover'=>'','created_at'=>(string)($item->pubDate??date('Y-m-d H:i:s')),'updated_at'=>date('Y-m-d H:i:s')];
                    $articles = json_read(DATA_DIR.'/articles/index.json'); $articles[]=$article; json_write(DATA_DIR.'/articles/index.json',$articles); $imported++;
                }
                $migrationResults[] = "✓ 从 WordPress 导入: {$imported} 篇文章";
            }
        }

        if ($migrationType === 'skip') $migrationResults[] = '跳过数据迁移';
        $_SESSION['migration_results'] = $migrationResults;
        $step = 4;
    }

    if ($step === 3 && !isset($_POST['migration_type'])) {
        // 从 step 2 进入 migration 步骤
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OpenFlow 安装向导</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;color:var(--fg)}
.wizard{width:100%;max-width:520px;padding:20px}
.wizard-card{background:var(--surface);border-radius:20px;padding:40px 36px;box-shadow:0 8px 32px rgba(0,0,0,.06)}
.step-bar{display:flex;gap:8px;margin-bottom:28px}
.step-dot{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;background:var(--border);color:var(--faint);transition:.2s}
.step-dot.active{background:var(--accent);color:var(--on-accent)}
.step-dot.done{background:var(--ok);color:var(--surface)}
h1{font-size:22px;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:24px}
.field{margin-bottom:16px}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:4px}
.field input,.field select{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;background:var(--bg);transition:.15s}
.field input:focus,.field select:focus{border-color:var(--fg);outline:none;background:var(--surface)}
.field .hint{font-size:11px;color:var(--faint);margin-top:2px}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 28px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s}
.btn-primary{background:var(--accent);color:var(--on-accent)}
.btn-primary:hover{background:#333}
.btn-ghost{background:transparent;color:var(--fg);border:1.5px solid var(--border)}
.btn-ghost:hover{background:var(--bg)}
.error{background:#fee2e2;color:var(--danger);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.success{background:#dcfce7;color:var(--ok);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.check-list{list-style:none;margin:16px 0}
.check-list li{padding:8px 0;font-size:13px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border)}
.check-list li:last-child{border:none}
.check-ok{color:var(--ok);font-weight:700}
.check-fail{color:#dc2626;font-weight:700}
.done-icon{font-size:48px;text-align:center;margin-bottom:16px}
.done-title{font-size:20px;font-weight:800;text-align:center;margin-bottom:8px}
.done-sub{text-align:center;color:var(--muted);font-size:13px;margin-bottom:24px}
.flex-between{display:flex;justify-content:space-between;align-items:center;margin-top:24px}
</style>
</head>
<body>
<div class="wizard">
  <div class="wizard-card">
    <!-- Step bar -->
    <div class="step-bar">
      <div class="step-dot <?=max(1,$step)>=1?(($step>1)?'done':'active'):''?>">1</div>
      <div class="step-dot <?=max(2,$step)>=2?(($step>2)?'done':($step>=2?'active':'')):''?>">2</div>
      <div class="step-dot <?=max(3,$step)>=3?(($step>3)?'done':($step>=3?'active':'')):''?>">3</div>
      <div class="step-dot <?=max(4,$step)>=4?'active':''?>">4</div>
    </div>

    <?php if ($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?=htmlspecialchars($success)?></div><?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- Step 1: 环境检查 -->
    <h1>🚀 环境检查</h1>
    <p class="sub">确认服务器环境满足运行要求</p>
    <?php
    $checks = [
        ['PHP 版本 ≥ 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')],
        ['SQLite3 扩展', extension_loaded('sqlite3')],
        ['JSON 扩展', extension_loaded('json')],
        ['Mbstring 扩展', extension_loaded('mbstring')],
        ['session 已启用', session_status() === PHP_SESSION_ACTIVE || ini_get('session.auto_start') !== '0' || true],
        ['DATA_DIR 可写', is_writable(DATA_DIR)],
        ['UPLOAD_DIR 可写', is_writable(UPLOAD_DIR)],
    ];
    $allPass = true;
    foreach ($checks as $c) { if (!$c[1]) $allPass = false; }
    ?>
    <ul class="check-list">
      <?php foreach ($checks as $label => $passed): ?>
      <li><span class="<?=$passed?'check-ok':'check-fail'?>"><?=$passed?'✓':'✗'?></span> <?=$label?></li>
      <?php endforeach; ?>
    </ul>
    <div class="flex-between">
      <span></span>
      <?php if ($allPass): ?>
      <form method="post"><input type="hidden" name="step" value="2"><button class="btn btn-primary">下一步 →</button></form>
      <?php else: ?>
      <span style="color:#dc2626;font-size:13px">请先修复以上问题</span>
      <?php endif; ?>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- Step 2: 基础设置 -->
    <h1>⚙️ 基础设置</h1>
    <p class="sub">配置站点信息和管理员账号</p>
    <form method="post">
      <input type="hidden" name="step" value="2">
      <div class="field"><label>站点名称</label><input type="text" name="site_name" value="<?=htmlspecialchars($_POST['site_name'] ?? 'OpenFlow')?>" required></div>
      <div class="field"><label>站点 URL</label><input type="text" name="site_url" value="<?=htmlspecialchars($_POST['site_url'] ?? '')?>" placeholder="https://example.com"><div class="hint">留空则使用默认</div></div>
      <div class="field"><label>管理员用户名</label><input type="text" name="admin_user" value="<?=htmlspecialchars($_POST['admin_user'] ?? 'admin')?>" required></div>
      <div class="field"><label>管理员密码</label><input type="password" name="admin_pass" required minlength="6"></div>
      <div class="field"><label>确认密码</label><input type="password" name="admin_pass2" required></div>
      <div class="field"><label>管理员邮箱</label><input type="email" name="admin_email" value="<?=htmlspecialchars($_POST['admin_email'] ?? '')?>" placeholder="可选"></div>
      <div class="flex-between">
        <a href="?step=1" class="btn btn-ghost">← 上一步</a>
        <button type="submit" class="btn btn-primary">下一步 →</button>
      </div>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- Step 3: 数据迁移 -->
    <h1>📦 数据迁移</h1>
    <p class="sub">从现有系统导入数据（可跳过）</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="step" value="3">
      <div style="display:grid;gap:12px;margin-bottom:20px">
        <label style="display:flex;gap:12px;padding:14px;background:var(--surface-2);border-radius:10px;cursor:pointer;border:2px solid transparent;transition:.15s" class="migration-option">
          <input type="radio" name="migration_type" value="json" style="margin-top:2px">
          <div><strong>JSON 备份文件</strong><div class="text-sm text-muted">从 OpenFlow 备份的 JSON 文件导入所有数据</div></div>
        </label>
        <label style="display:flex;gap:12px;padding:14px;background:var(--surface-2);border-radius:10px;cursor:pointer;border:2px solid transparent;transition:.15s" class="migration-option">
          <input type="radio" name="migration_type" value="csv" style="margin-top:2px">
          <div><strong>CSV 文件</strong><div class="text-sm text-muted">导入文章或会员数据（需选择类型）</div></div>
        </label>
        <label style="display:flex;gap:12px;padding:14px;background:var(--surface-2);border-radius:10px;cursor:pointer;border:2px solid transparent;transition:.15s" class="migration-option">
          <input type="radio" name="migration_type" value="wordpress" style="margin-top:2px">
          <div><strong>WordPress 导出 (WXR)</strong><div class="text-sm text-muted">从 WordPress 导出的 XML 文件导入文章</div></div>
        </label>
        <label style="display:flex;gap:12px;padding:14px;background:var(--surface-2);border-radius:10px;cursor:pointer;border:2px solid transparent;transition:.15s" class="migration-option">
          <input type="radio" name="migration_type" value="skip" checked style="margin-top:2px">
          <div><strong>跳过迁移</strong><div class="text-sm text-muted">稍后再导入数据</div></div>
        </label>
      </div>
      <div id="csvTypeField" style="display:none;margin-bottom:16px">
        <div class="field"><label>CSV 数据类型</label><select name="csv_type"><option value="articles">文章</option><option value="members">会员</option></select></div>
      </div>
      <div id="fileField" style="display:none;margin-bottom:16px">
        <div class="field"><label>选择文件</label><input type="file" name="migration_file" accept=".json,.csv,.xml"></div>
      </div>
      <div class="flex-between">
        <a href="?step=2" class="btn btn-ghost">← 上一步</a>
        <button type="submit" class="btn btn-primary">下一步 →</button>
      </div>
    </form>
    <script>
    document.querySelectorAll('.migration-option input[type="radio"]').forEach(function(radio) {
      radio.addEventListener('change', function() {
        var v = this.value;
        document.getElementById('fileField').style.display = v === 'skip' ? 'none' : '';
        document.getElementById('csvTypeField').style.display = v === 'csv' ? '' : 'none';
        document.querySelectorAll('.migration-option').forEach(function(el) {
          el.style.borderColor = el.querySelector('input').checked ? 'var(--accent)' : 'transparent';
        });
      });
    });
    </script>

    <?php elseif ($step === 4): ?>
    <!-- Step 4: 完成 -->
    <div class="done-icon">🎉</div>
    <div class="done-title">安装完成</div>
    <div class="done-sub">OpenFlow 已就绪，可以开始使用了</div>
    <?php
    $migResults = $_SESSION['migration_results'] ?? [];
    unset($_SESSION['migration_results']);
    if (!empty($migResults)):
    ?>
    <div style="margin-bottom:20px;padding:14px;background:#dcfce7;border-radius:10px;font-size:13px">
      <strong>迁移结果：</strong><br>
      <?php foreach ($migResults as $r): ?><?=htmlspecialchars($r)?><br><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="text-align:center">
      <a href="/admin/" class="btn btn-primary" style="margin-right:8px">进入后台</a>
      <a href="/" class="btn btn-ghost">查看前台</a>
    </div>
    <div style="margin-top:24px;padding:16px;background:var(--bg);border-radius:10px;font-size:12px;color:var(--muted)">
      <strong>安全提醒：</strong>建议安装完成后删除或重命名 <code>setup-wizard.php</code>，并修改 <code>data/users.json</code> 中的管理员密码为强密码。
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
