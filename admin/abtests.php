<?php
/**
 * A/B 测试管理 — 分流显示
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$abFile = DATA_DIR . '/abtests.json';
$tests = json_read($abFile);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $error = '实验名称不能为空';
    } else {
        $id = $_POST['id'] ?? '';
        $data = [
            'name' => $name,
            'page_scope' => $_POST['page_scope'] ?? 'all',
            'page_paths' => $_POST['page_paths'] ?? '',
            'traffic_b' => max(0, min(100, (int)($_POST['traffic_b'] ?? 50))),
            'variant_a_label' => $_POST['variant_a_label'] ?? '方案 A（对照组）',
            'variant_b_label' => $_POST['variant_b_label'] ?? '方案 B（实验组）',
            'css_a' => $_POST['css_a'] ?? '',
            'css_b' => $_POST['css_b'] ?? '',
            'js_a' => $_POST['js_a'] ?? '',
            'js_b' => $_POST['js_b'] ?? '',
            'url_a' => $_POST['url_a'] ?? '',
            'url_b' => $_POST['url_b'] ?? '',
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'enabled' => isset($_POST['enabled']),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($id)) {
            $data['id'] = 'ab_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
            $data['created_at'] = date('Y-m-d H:i:s');
            $tests[] = $data;
        } else {
            foreach ($tests as &$t) if ($t['id'] === $id) { $t = array_merge($t, $data); break; }
            unset($t);
        }
        json_write($abFile, $tests);
        flash('success', 'A/B 实验已保存');
        header('Location: /xmp/abtests');
        exit;
    }
}

if (isset($_GET['delete'])) {
    $tests = array_values(array_filter($tests, fn($t) => $t['id'] !== $_GET['delete']));
    json_write($abFile, $tests);
    flash('success', '实验已删除');
    header('Location: /xmp/abtests');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $edit = ['id' => '', 'name' => '', 'page_scope' => 'all', 'page_paths' => '', 'traffic_b' => 50,
                 'variant_a_label' => '方案 A（对照组）', 'variant_b_label' => '方案 B（实验组）',
                 'css_a' => '', 'css_b' => '', 'js_a' => '', 'js_b' => '', 'url_a' => '', 'url_b' => '',
                 'start_date' => '', 'end_date' => '', 'enabled' => false];
    } else {
        foreach ($tests as $t) if ($t['id'] === $_GET['edit']) { $edit = $t; break; }
    }
}

admin_header('A/B 测试');
?>
<div class="admin-layout">
  <?php admin_sidebar('abtests'); ?>
  <div class="main">
    <h1>🧪 A/B 测试</h1>
    <p class="sub">对特定页面做分流测试 · 基于用户唯一标识确定性分流 · 支持 CSS/JS 变体与重定向</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="flex items-center gap-4 mb-4">
      <h2 style="margin-bottom:0">全部实验</h2>
      <a href="abtests.php?edit=new" class="btn btn-primary btn-sm ml-auto">➕ 新建实验</a>
    </div>

    <?php if ($edit): ?>
    <!-- 编辑表单 -->
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'] ?? '')?>">
      <div class="card">
        <h2><?=empty($edit['id'])?'➕ 新建 A/B 实验':'✏️ 编辑实验：'.htmlspecialchars($edit['name'])?></h2>
        <div class="field-row">
          <div class="field"><label>实验名称 <span class="hint">· 必填</span></label><input type="text" name="name" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required placeholder="如：首页 Hero 标题测试"></div>
          <div class="field"><label>B 流量占比</label><input type="number" name="traffic_b" value="<?=htmlspecialchars($edit['traffic_b'] ?? 50)?>" min="0" max="100"> <span class="text-sm text-muted">% 用户进入 B 方案</span></div>
        </div>
        <div class="field-row">
          <div class="field"><label>页面范围</label><select name="page_scope"><option value="all" <?=($edit['page_scope']??'all')==='all'?'selected':''?>>全部页面</option><option value="specific" <?=($edit['page_scope']??'')==='specific'?'selected':''?>>指定页面</option></select></div>
          <div class="field"><label>页面路径</label><input type="text" name="page_paths" value="<?=htmlspecialchars($edit['page_paths'] ?? '')?>" placeholder="/index.html 或 /article"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>开始日期</label><input type="date" name="start_date" value="<?=htmlspecialchars($edit['start_date'] ?? '')?>"></div>
          <div class="field"><label>结束日期</label><input type="date" name="end_date" value="<?=htmlspecialchars($edit['end_date'] ?? '')?>"></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:24px"><input type="checkbox" name="enabled" value="1" <?=($edit['enabled']??false)?'checked':''?> style="width:16px;height:16px">启用实验</label></div>
        </div>
      </div>

      <div class="card">
        <h2>方案 A <span class="text-sm text-muted">· 对照组</span></h2>
        <div class="field"><label>方案 A 标签</label><input type="text" name="variant_a_label" value="<?=htmlspecialchars($edit['variant_a_label'] ?? '方案 A（对照组）')?>"></div>
        <div class="field-row">
          <div class="field"><label>CSS 覆盖</label><textarea name="css_a" rows="4" placeholder=".hero-title { font-size: 28px; }"><?=htmlspecialchars($edit['css_a'] ?? '')?></textarea></div>
          <div class="field"><label>JS 覆盖</label><textarea name="js_a" rows="4" placeholder="document.querySelector('.hero h1').textContent = '旧标题';"><?=htmlspecialchars($edit['js_a'] ?? '')?></textarea></div>
        </div>
        <div class="field"><label>重定向 URL <span class="hint">· 可选</span></label><input type="text" name="url_a" value="<?=htmlspecialchars($edit['url_a'] ?? '')?>" placeholder="留空不重定向"></div>
      </div>

      <div class="card" style="border-left:3px solid var(--accent)">
        <h2>方案 B <span class="text-sm text-muted">· 实验组</span></h2>
        <div class="field"><label>方案 B 标签</label><input type="text" name="variant_b_label" value="<?=htmlspecialchars($edit['variant_b_label'] ?? '方案 B（实验组）')?>"></div>
        <div class="field-row">
          <div class="field"><label>CSS 覆盖</label><textarea name="css_b" rows="4" placeholder=".hero-title { font-size: 34px; color: #2e6b4f; }"><?=htmlspecialchars($edit['css_b'] ?? '')?></textarea></div>
          <div class="field"><label>JS 覆盖</label><textarea name="js_b" rows="4" placeholder="document.querySelector('.hero h1').textContent = '新标题'; document.body.setAttribute('data-variant','B');"><?=htmlspecialchars($edit['js_b'] ?? '')?></textarea></div>
        </div>
        <div class="field"><label>重定向 URL <span class="hint">· 可选</span></label><input type="text" name="url_b" value="<?=htmlspecialchars($edit['url_b'] ?? '')?>" placeholder="留空不重定向"></div>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存实验</button>
      <a href="abtests.php" class="btn btn-ghost">取消</a>
    </form>

    <?php else: ?>
    <!-- 列表 -->
    <div class="card" style="padding:0;overflow-x:auto">
      <table>
        <thead><tr><th>实验</th><th>页面</th><th>B 流量</th><th>方案</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($tests)): ?>
          <tr><td colspan="7" class="empty">暂无 A/B 实验，点击右上角创建</td></tr>
          <?php endif; ?>
          <?php foreach ($tests as $t): ?>
          <tr>
            <td><strong><?=htmlspecialchars($t['name'])?></strong></td>
            <td class="text-sm text-muted"><?=($t['page_scope']??'all')==='all'?'全部':htmlspecialchars($t['page_paths'])?></td>
            <td><?=htmlspecialchars($t['traffic_b'] ?? 50)?>%</td>
            <td style="font-size:12px"><span class="badge badge-gray">A</span> <?=htmlspecialchars(mb_substr($t['variant_a_label']??'方案A',0,12))?><br><span class="badge badge-yellow">B</span> <?=htmlspecialchars(mb_substr($t['variant_b_label']??'方案B',0,12))?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($t['start_date']??'',0,10))?> → <?=htmlspecialchars(substr($t['end_date']??'',0,10))?></td>
            <td><span class="badge <?=($t['enabled']??false)?'badge-green':'badge-gray'?>" style="padding:4px 10px;font-size:12px"><?=($t['enabled']??false)?'🟢 进行中':'⏸ 已暂停'?></span></td>
            <td style="white-space:nowrap">
              <a href="abtests-stats.php?id=<?=urlencode($t['id'])?>" class="btn btn-ghost btn-sm">📊 统计</a>
              <a href="abtests.php?edit=<?=urlencode($t['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="abtests.php?delete=<?=urlencode($t['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除该实验?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
