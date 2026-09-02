<?php
/**
 * 角色与权限 —— 自定义角色 + 细粒度授权
 *
 * 此前权限只有硬编码的 admin / marketing / sales 三档，团队一大就不够用。
 * 这里把权限矩阵挪到 data/roles.json，可新建任意角色、勾选到具体模块。
 *
 * 安全约束：
 *   - admin 恒为全量、只读，永远不能被削弱（防止把自己关在门外）。
 *   - 删除角色前必须没有用户还挂在它上面。
 *   - CSRF/审计由 require_login 统一收口，无需本页手写。
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('users');

$message = ''; $error = '';
$customFile = DATA_DIR . '/roles.json';

// 权限分组（仅影响编辑器展示；未列到的自动归「其他」）
$groups = [
    '内容'       => ['pages','articles','ingest','categories','tags','topics','landing','downloads','podcasts','media','dam','knowledge','version-diff'],
    'SEO / GEO'  => ['seo','seo-tools','seo-batch','seo-console','redirects','structured','geo','sentiment'],
    'CDP / 数据' => ['cdp','profiling','segments','tracking','analytics','insights','dashboard','conversion','export','evolution'],
    '营销自动化' => ['campaigns','automation','flow','canvas','ma-sync','channels','notify-channels','email','sms','forms','submissions','utm-builder','abtests'],
    '销售 / CRM' => ['crm','leads','consultation','wechat-mp','social'],
    '电商'       => ['commerce','shop-settings','marketplace','subscription','membership','coupons','orders'],
    '社区 / 内容运营' => ['community-config','community-mod','moderation','reviews','approvals','events','tasks','survey','nps','live','featured','bookmarks','follows','messages'],
    '站点 / 系统' => ['themes','navigation','site-builder','plugins','scripts','settings','devops','users','security','storage','activity','qr','ai-config'],
];
$all = of_perm_registry();
// 收拢未分组的
$grouped = array_merge(...array_values($groups));
$ungrouped = array_values(array_diff($all, $grouped));
if ($ungrouped) $groups['其他'] = $ungrouped;

$custom = of_custom_roles();

// ── 保存（新建或编辑）──
if (($_POST['action'] ?? '') === 'save') {
    $slug  = strtolower(trim($_POST['slug'] ?? ''));
    $label = trim($_POST['label'] ?? '');
    $perms = array_values(array_intersect($all, (array)($_POST['perms'] ?? [])));
    $slug  = preg_replace('/[^a-z0-9_-]/', '', $slug);
    if ($slug === '' || $label === '') {
        $error = '角色标识和名称都要填。';
    } elseif (in_array($slug, ['admin'], true)) {
        $error = 'admin 是内置超级管理员，不可覆盖。';
    } elseif (!$perms) {
        $error = '至少勾选一个权限。';
    } else {
        $custom[$slug] = ['label' => $label, 'perms' => $perms, 'updated_at' => date('Y-m-d H:i:s')];
        json_write($customFile, $custom);
        audit('保存角色 ' . $slug, 'users', ['perms' => count($perms)]);
        $message = "角色「{$label}」已保存，授予 " . count($perms) . " 项权限。";
    }
}

// ── 删除 ──
if (isset($_GET['delete'])) {
    $slug = preg_replace('/[^a-z0-9_-]/', '', (string)$_GET['delete']);
    $users = get_users();
    $inUse = array_filter($users, fn($u) => ($u['role'] ?? '') === $slug);
    if (in_array($slug, ['admin','marketing','sales'], true)) {
        $error = '内置角色不可删除（可编辑其权限）。';
    } elseif ($inUse) {
        $error = '还有 ' . count($inUse) . ' 个用户在用这个角色，请先在「权限管理」里改掉他们的角色。';
    } else {
        unset($custom[$slug]);
        json_write($customFile, $custom);
        audit('删除角色 ' . $slug, 'users');
        $message = '角色已删除。';
    }
}

// 编辑目标（内置 marketing/sales 也可编辑其权限；admin 只读）
$editSlug = preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['edit'] ?? ''));
$editing = null;
if ($editSlug !== '') {
    $rp = role_perms();
    if (isset($rp[$editSlug])) {
        $editing = [
            'slug'  => $editSlug,
            'label' => role_label($editSlug),
            'perms' => $rp[$editSlug],
            'builtin' => in_array($editSlug, ['admin','marketing','sales'], true),
            'readonly' => $editSlug === 'admin',
        ];
    }
}

$allRoles = role_perms();
$userCounts = [];
foreach (get_users() as $u) { $r = $u['role'] ?? ''; $userCounts[$r] = ($userCounts[$r] ?? 0) + 1; }

admin_header('角色与权限');
?>
<div class="admin-layout">
  <?php admin_sidebar('users'); ?>
  <div class="main">
    <h1>角色与权限</h1>
    <p class="sub">按模块给角色授权。admin 恒为全量且只读；内置角色可改权限、不可删除；可新建任意自定义角色。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="card" style="padding:0;overflow-x:auto">
      <table class="table">
        <thead><tr><th>角色</th><th>标识</th><th>权限数</th><th>用户数</th><th style="width:1%">操作</th></tr></thead>
        <tbody>
        <?php foreach ($allRoles as $slug => $perms): $builtin = in_array($slug, ['admin','marketing','sales'], true); ?>
          <tr>
            <td><b><?=htmlspecialchars(role_label($slug))?></b> <?php if ($builtin): ?><span class="badge">内置</span><?php endif; ?></td>
            <td class="mono" style="font-size:12px"><?=htmlspecialchars($slug)?></td>
            <td><?=$slug==='admin'?'全部':count($perms)?></td>
            <td><?=$userCounts[$slug] ?? 0?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?=urlencode($slug)?>" class="btn btn-ghost btn-sm"><?=$slug==='admin'?'查看':'编辑'?></a>
              <?php if (!$builtin): ?>
                <a href="?delete=<?=urlencode($slug)?>" class="btn btn-danger btn-sm" data-confirm="删除角色「<?=htmlspecialchars(role_label($slug))?>」?">删除</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h2 style="margin-top:28px"><?= $editing ? '编辑角色：' . htmlspecialchars($editing['label']) : '新建自定义角色' ?></h2>
    <?php if ($editing && $editing['readonly']): ?>
      <div class="card"><p class="sub" style="margin:0">超级管理员拥有全部权限，且不可修改——这是防止误操作把自己锁在门外的安全底线。</p></div>
    <?php else: ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
        <div class="field" style="margin:0">
          <label>角色标识 <span class="hint">· 英文/数字，创建后作为内部 key</span></label>
          <input type="text" name="slug" value="<?=htmlspecialchars($editing['slug'] ?? '')?>" placeholder="如 editor / finance" <?=$editing && $editing['builtin'] ? 'readonly' : ''?> required>
        </div>
        <div class="field" style="margin:0">
          <label>显示名称</label>
          <input type="text" name="label" value="<?=htmlspecialchars($editing['label'] ?? '')?>" placeholder="如 内容编辑 / 财务" required>
        </div>
      </div>

      <?php $sel = $editing['perms'] ?? []; ?>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:10px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.perm-cb').forEach(c=>c.checked=true)">全选</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.perm-cb').forEach(c=>c.checked=false)">全不选</button>
      </div>
      <?php foreach ($groups as $gname => $perms): if (!$perms) continue; ?>
        <fieldset style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:12px">
          <legend style="padding:0 6px;font-size:13px;color:var(--muted)"><?=htmlspecialchars($gname)?></legend>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px 14px">
            <?php foreach ($perms as $p): ?>
              <label style="display:flex;gap:7px;align-items:center;font-size:13px">
                <input type="checkbox" class="perm-cb" name="perms[]" value="<?=htmlspecialchars($p)?>" <?=in_array($p, $sel, true)?'checked':''?>>
                <?=htmlspecialchars($p)?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>

      <div style="display:flex;gap:8px;margin-top:8px">
        <button class="btn btn-primary"><?= $editing ? '保存修改' : '创建角色' ?></button>
        <?php if ($editing): ?><a href="/xmp/roles" class="btn btn-ghost">取消</a><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
