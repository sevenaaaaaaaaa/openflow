<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('users');

$message = '';
$users = get_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update' && isset($_POST['username'])) {
        $u = $_POST['username'];
        if (isset($users[$u])) {
            $users[$u]['name'] = $_POST['name'] ?? $users[$u]['name'];
            $users[$u]['role'] = $_POST['role'] ?? $users[$u]['role'];
            if (!empty($_POST['password'])) {
                $users[$u]['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            save_users($users);
            $message = '用户已更新';
        }
    }

    if ($action === 'add') {
        $u = trim($_POST['new_username'] ?? '');
        if ($u && !isset($users[$u])) {
            $newPassword = $_POST['new_password'] ?? bin2hex(random_bytes(8));
            $users[$u] = [
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'role' => $_POST['new_role'] ?? 'marketing',
                'name' => $_POST['new_name'] ?? $u,
            ];
            save_users($users);
            $message = "用户已添加，初始密码: {$newPassword}";
        } else {
            $message = '用户名已存在或无效';
        }
    }

    if ($action === 'delete' && isset($_POST['username'])) {
        $u = $_POST['username'];
        if ($u !== 'admin' && isset($users[$u])) {
            unset($users[$u]);
            save_users($users);
            $message = '用户已删除';
        } else {
            $message = '不能删除 admin 账号';
        }
    }
    $users = get_users();
}

$roleLabels = ['admin' => '超级管理员', 'marketing' => '市场总监', 'sales' => '销售总监'];

admin_header('权限管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('users'); ?>
  <div class="main">
    <h1>权限管理</h1>
    <p class="sub">管理后台用户账号与角色权限</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <h2>角色权限说明</h2>
      <table>
        <thead><tr><th>角色</th><th>页面管理</th><th>文章管理</th><th>分类/标签</th><th>SEO</th><th>媒体</th><th>线索</th><th>导出</th><th>设置</th><th>用户</th></tr></thead>
        <tbody>
          <tr><td><strong>超级管理员</strong></td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
          <tr><td><strong>市场总监</strong></td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
          <tr><td><strong>销售总监</strong></td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>✓</td><td>—</td><td>—</td><td>—</td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>用户列表</h2>
      <table>
        <thead><tr><th>用户名</th><th>显示名称</th><th>角色</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($users as $uk => $uv): ?>
          <tr>
            <td><code><?=htmlspecialchars($uk)?></code></td>
            <td><?=htmlspecialchars($uv['name'])?></td>
            <td><span class="badge badge-<?=$uv['role']==='admin'?'green':($uv['role']==='marketing'?'yellow':'gray')?>"><?=htmlspecialchars($roleLabels[$uv['role']] ?? $uv['role'])?></span></td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick="editUser('<?=htmlspecialchars($uk)?>','<?=htmlspecialchars($uv['name'])?>','<?=$uv['role']?>')">编辑</button>
              <?php if ($uk !== 'admin'): ?>
              <form method="post" style="display:inline" data-confirm="确认删除用户 <?=htmlspecialchars($uk)?>？">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="username" value="<?=htmlspecialchars($uk)?>">
                <button type="submit" class="btn btn-danger btn-sm">删除</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Edit Modal -->
    <div class="card" id="editForm" style="display:none">
      <h2>编辑用户</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="username" id="edit_username">
        <div class="field-row">
          <div class="field"><label>显示名称</label><input type="text" name="name" id="edit_name" required></div>
          <div class="field"><label>角色</label><select name="role" id="edit_role"><?php foreach (array_keys(role_perms()) as $r): ?><option value="<?=htmlspecialchars($r)?>"><?=htmlspecialchars(role_label($r))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field"><label>新密码 <span class="hint">留空则不修改</span></label><input type="password" name="password" placeholder="输入新密码"></div>
        <button type="submit" class="btn btn-primary">保存</button>
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editForm').style.display='none'">取消</button>
      </form>
    </div>

    <div class="card">
      <h2>添加新用户</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field-row">
          <div class="field"><label>用户名</label><input type="text" name="new_username" required></div>
          <div class="field"><label>显示名称</label><input type="text" name="new_name" required></div>
        </div>
        <div class="field-row">
          <div class="field"><label>密码 <span class="hint">留空则自动生成</span></label><input type="text" name="new_password" placeholder="留空自动生成随机密码"></div>
          <div class="field"><label>角色</label><select name="new_role"><?php foreach (array_keys(role_perms()) as $r): ?><option value="<?=htmlspecialchars($r)?>"<?=$r==='marketing'?' selected':''?>><?=htmlspecialchars(role_label($r))?></option><?php endforeach; ?></select></div>
        </div>
        <button type="submit" class="btn btn-primary">添加用户</button>
      </form>
    </div>
  </div>
</div>

<script>
function editUser(username, name, role) {
  document.getElementById('edit_username').value = username;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_role').value = role;
  document.getElementById('editForm').style.display = 'block';
  document.getElementById('editForm').scrollIntoView({behavior:'smooth'});
}
</script>
<?php admin_footer(); ?>
