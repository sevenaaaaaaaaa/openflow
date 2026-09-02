<?php
/**
 * 调研系统 — 组织架构管理（公司/部门/成员/角色）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/survey-lib.php';
require_login();
require_perm('settings');

$org = survey_get_org();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save_company'])) {
        $companies = [];
        foreach (($_POST['company_name'] ?? []) as $i => $cn) {
            if (empty(trim($cn))) continue;
            $companies[] = [
                'id' => ($_POST['company_id'][$i] ?? '') ?: 'comp_' . substr(bin2hex(random_bytes(4)), 0, 6),
                'name' => trim($cn),
            ];
        }
        $org['companies'] = $companies;
        survey_save_org($org);
        $message = '公司信息已保存';
    }
    if (isset($_POST['save_department'])) {
        $departments = [];
        foreach (($_POST['dept_name'] ?? []) as $i => $dn) {
            if (empty(trim($dn))) continue;
            $departments[] = [
                'id' => ($_POST['dept_id'][$i] ?? '') ?: 'dept_' . substr(bin2hex(random_bytes(4)), 0, 6),
                'company_id' => $_POST['dept_company'][$i] ?? '',
                'name' => trim($dn),
            ];
        }
        $org['departments'] = $departments;
        survey_save_org($org);
        $message = '部门信息已保存';
    }
    if (isset($_POST['save_member'])) {
        $members = [];
        foreach (($_POST['member_username'] ?? []) as $i => $mu) {
            if (empty(trim($mu))) continue;
            $members[] = [
                'username' => trim($mu),
                'name' => $_POST['member_name'][$i] ?? '',
                'email' => $_POST['member_email'][$i] ?? '',
                'company' => $_POST['member_company'][$i] ?? '',
                'department' => $_POST['member_department'][$i] ?? '',
                'role' => $_POST['member_role'][$i] ?? 'employee',
            ];
        }
        $org['members'] = $members;
        survey_save_org($org);
        $message = '成员信息已保存';
    }
    // 生成员工查询链接
    if (isset($_POST['gen_token'])) {
        $memberUsername = $_POST['gen_token'] ?? '';
        $memberFound = null;
        foreach ($org['members'] ?? [] as $m) if (($m['username'] ?? '') === $memberUsername) { $memberFound = $m; break; }
        if ($memberFound) {
            $tokensFile = DATA_DIR . '/survey/employee-tokens.json';
            $tokens = json_read($tokensFile);
            // 已有 token 则复用
            $existing = null;
            foreach ($tokens as $t) if (($t['username'] ?? '') === $memberUsername) { $existing = $t; break; }
            if (!$existing) {
                $existing = [
                    'username' => $memberUsername,
                    'name' => $memberFound['name'] ?? '',
                    'email' => $memberFound['email'] ?? '',
                    'token' => 'emp_' . bin2hex(random_bytes(16)),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $tokens[] = $existing;
                json_write($tokensFile, $tokens);
            }
            flash('success', '员工查询链接已生成/更新');
            header('Location: /xmp/survey-org?token_user=' . urlencode($memberUsername));
            exit;
        }
    }
    $org = survey_get_org();
}

$roleLabels = ['company_admin' => '公司管理员', 'department_admin' => '部门管理员', 'hr' => 'HR', 'employee' => '员工'];

// 员工查询 token
$empTokens = json_read(DATA_DIR . '/survey/employee-tokens.json');
$tokenByUser = [];
foreach ($empTokens as $t) $tokenByUser[$t['username'] ?? ''] = $t['token'] ?? '';
$focusTokenUser = $_GET['token_user'] ?? '';
$siteBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

admin_header('调研组织架构');
?>
<div class="admin-layout">
  <?php admin_sidebar('survey-org'); ?>
  <div class="main">
    <h1> 调研组织架构</h1>
    <p class="sub">配置公司、部门、成员与调研角色 · 决定各角色能看到的统计范围</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <a href="survey" class="btn btn-ghost">📋 问卷管理</a>
      <a href="survey-stats.php" class="btn btn-ghost">📊 统计查看</a>
      <a href="survey-org.php" class="btn btn-primary">🏢 组织架构</a>
    </div>

    <!-- 角色说明 -->
    <div class="card" style="padding:16px">
      <h2 style="font-size:15px">角色权限说明</h2>
      <table style="font-size:13px">
        <tr><td><strong>公司管理员</strong></td><td>查看全公司所有调研结果与统计</td></tr>
        <tr><td><strong>部门管理员</strong></td><td>仅查看本部门的调研结果与统计</td></tr>
        <tr><td><strong>HR</strong></td><td>查看全公司统计（不含个人敏感回答）</td></tr>
        <tr><td><strong>员工</strong></td><td>仅查看自己的调研结果</td></tr>
      </table>
    </div>

    <!-- 公司 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🏢 公司列表</h2>
        <div id="companyList">
          <?php foreach ($org['companies'] ?? [] as $ci => $c): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <input type="hidden" name="company_id[]" value="<?=htmlspecialchars($c['id'])?>">
            <input type="text" name="company_name[]" value="<?=htmlspecialchars($c['name'])?>" placeholder="公司名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addCompany()">+ 添加公司</button>
        <div style="margin-top:12px"><button type="submit" name="save_company" class="btn btn-primary">保存公司</button></div>
      </div>
    </form>

    <!-- 部门 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🏬 部门列表</h2>
        <div id="departmentList">
          <?php foreach ($org['departments'] ?? [] as $di => $d): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <input type="hidden" name="dept_id[]" value="<?=htmlspecialchars($d['id'])?>">
            <select name="dept_company[]" style="width:160px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <option value="">— 所属公司 —</option>
              <?php foreach ($org['companies'] ?? [] as $c): ?>
              <option value="<?=htmlspecialchars($c['id'])?>" <?=($d['company_id']??'')===$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="dept_name[]" value="<?=htmlspecialchars($d['name'])?>" placeholder="部门名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addDepartment()">+ 添加部门</button>
        <div style="margin-top:12px"><button type="submit" name="save_department" class="btn btn-primary">保存部门</button></div>
      </div>
    </form>

    <!-- 成员 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>👥 成员与角色</h2>
        <p class="text-sm text-muted mb-4">为后台用户分配调研角色。通过用户名（登录名）匹配，员工只填 username + 公司部门即可（name/email 可选）。</p>
        <div id="memberList">
          <?php foreach ($org['members'] ?? [] as $mi => $m): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
            <input type="text" name="member_username[]" value="<?=htmlspecialchars($m['username'] ?? '')?>" placeholder="登录名" style="width:110px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <input type="text" name="member_name[]" value="<?=htmlspecialchars($m['name'] ?? '')?>" placeholder="姓名" style="width:100px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
            <select name="member_company[]" style="width:140px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <option value="">— 公司 —</option>
              <?php foreach ($org['companies'] ?? [] as $c): ?>
              <option value="<?=htmlspecialchars($c['id'])?>" <?=($m['company']??'')===$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
            <select name="member_department[]" style="width:140px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <option value="">— 部门 —</option>
              <?php foreach ($org['departments'] ?? [] as $d): ?>
              <option value="<?=htmlspecialchars($d['id'])?>" <?=($m['department']??'')===$d['id']?'selected':''?>><?=htmlspecialchars($d['name'])?></option>
              <?php endforeach; ?>
            </select>
            <select name="member_role[]" style="width:130px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
              <?php foreach ($roleLabels as $rk => $rl): ?>
              <option value="<?=$rk?>" <?=($m['role']??'')===$rk?'selected':''?>><?=$rl?></option>
              <?php endforeach; ?>
            </select>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="gen_token" value="<?=htmlspecialchars($m['username'] ?? '')?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="生成员工查询链接">🔗 查询链接</button>
            </form>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
            <?php if (!empty($m['username']) && isset($tokenByUser[$m['username']])): ?>
            <div style="flex-basis:100%;font-size:12px;color:var(--text-3);padding:4px 0" id="tok-<?=htmlspecialchars($m['username'])?>">
              <?php if ($focusTokenUser === $m['username']): ?><span class="badge badge-green" style="font-size:10px">已生成</span> <?php endif; ?>
              员工查询链接：<code style="background:var(--surface);padding:2px 8px;border-radius:4px;font-size:11px"><?=$siteBase?>/survey-my.php?token=<?=htmlspecialchars($tokenByUser[$m['username']])?></code>
              <button type="button" class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px" onclick="copyToken('<?=htmlspecialchars($tokenByUser[$m['username']])?>')">复制</button>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addMember()">+ 添加成员</button>
        <div style="margin-top:12px"><button type="submit" name="save_member" class="btn btn-primary">保存成员</button></div>
      </div>
    </form>
  </div>
</div>

<script>
var ORG = <?=json_encode(['companies' => $org['companies'] ?? [], 'departments' => $org['departments'] ?? []], JSON_UNESCAPED_UNICODE)?>;
function companyOpts() {
  return ORG.companies.map(function(c) { return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');
}
function departmentOpts() {
  return ORG.departments.map(function(d) { return '<option value="' + d.id + '">' + d.name + '</option>'; }).join('');
}
function addCompany() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  d.innerHTML = '<input type="hidden" name="company_id[]" value="comp_' + Date.now() + '"><input type="text" name="company_name[]" placeholder="公司名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('companyList').appendChild(d);
}
function addDepartment() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  d.innerHTML = '<input type="hidden" name="dept_id[]" value="dept_' + Date.now() + '"><select name="dept_company[]" style="width:160px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="">— 所属公司 —</option>' + companyOpts() + '</select><input type="text" name="dept_name[]" placeholder="部门名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('departmentList').appendChild(d);
}
function addMember() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap';
  d.innerHTML =
    '<input type="text" name="member_username[]" placeholder="登录名" style="width:110px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">' +
    '<input type="text" name="member_name[]" placeholder="姓名" style="width:100px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">' +
    '<select name="member_company[]" style="width:140px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="">— 公司 —</option>' + companyOpts() + '</select>' +
    '<select name="member_department[]" style="width:140px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="">— 部门 —</option>' + departmentOpts() + '</select>' +
    '<select name="member_role[]" style="width:130px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><option value="employee">员工</option><option value="hr">HR</option><option value="department_admin">部门管理员</option><option value="company_admin">公司管理员</option></select>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('memberList').appendChild(d);
}
function copyToken(token) {
  var url = window.location.origin + '/survey-my.php?token=' + token;
  navigator.clipboard.writeText(url).then(function() {
    if (window.fcToast) window.fcToast('员工查询链接已复制', 'success');
    else ofAlert('已复制链接');
  });
}
</script>
<?php admin_footer(); ?>
