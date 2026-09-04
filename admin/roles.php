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

      <?php $sel = $editing['perms'] ?? [];
        // 权限键 → 人话：用导航树里的条目名（同 key），没有的保留原 key
        require_once dirname(__DIR__) . '/includes/admin-nav.php';
        $__lbl = ['pages'=>'页面','articles'=>'文章','leads'=>'线索','social'=>'社媒','security'=>'账号安全','activity'=>'操作记录','export'=>'数据导出','submissions'=>'表单提交','structured'=>'结构化数据','seo'=>'SEO 基础','seo-tools'=>'SEO 工具','seo-batch'=>'SEO 批量','seo-console'=>'站长工具','redirects'=>'301 重定向','landing'=>'落地页','flow'=>'业务链路总览','dashboard'=>'经营驾驶舱','cdp'=>'CDP 数据中台','media'=>'媒体库','tags'=>'标签','categories'=>'分类','downloads'=>'资料下载','podcasts'=>'播客视频','ingest'=>'外部导入','settings'=>'系统设置','users'=>'后台用户','themes'=>'主题','navigation'=>'站点结构','plugins'=>'插件','scripts'=>'脚本','devops'=>'运维','storage'=>'存储','qr'=>'二维码','ai-config'=>'模型与 AI 配置','tasks'=>'内容生产任务','messages'=>'站内信','events'=>'活动报名','live'=>'直播','nps'=>'NPS','survey'=>'问卷','reviews'=>'评价','approvals'=>'内容与资质审核','featured'=>'推荐位','bookmarks'=>'收藏','follows'=>'关注','commerce'=>'商业中心','orders'=>'订单','coupons'=>'优惠券','membership'=>'会员','subscription'=>'付费订阅','marketplace'=>'生态市场','shop-settings'=>'商城设置','crm'=>'CRM','consultation'=>'咨询','wechat-mp'=>'公众号','campaigns'=>'活动 / CRO','automation'=>'营销自动化','canvas'=>'画布流程','ma-sync'=>'MA 融合同步','channels'=>'分发渠道','notify-channels'=>'通知渠道','email'=>'邮件','sms'=>'短信','forms'=>'表单','utm-builder'=>'UTM 生成器','abtests'=>'A/B 测试','profiling'=>'用户画像','segments'=>'用户分群','tracking'=>'埋点','analytics'=>'运营分析','insights'=>'营销洞察','conversion'=>'转化组件','evolution'=>'系统体检','geo'=>'GEO 话题监控','sentiment'=>'舆情监测','topics'=>'专题','dam'=>'数字资产','knowledge'=>'知识库','version-diff'=>'版本对比','community-config'=>'社区配置','community-mod'=>'社区管理','moderation'=>'内容审核','site-builder'=>'站点搭建'];
        // 没在上表里的：用导航树里 id 等于权限键的条目名
        foreach (admin_nav_tree() as $__a) foreach ($__a['groups'] as $__g) foreach ($__g['items'] as $__it) { if (!isset($__lbl[$__it['id']])) $__lbl[$__it['id']] = $__it['label']; }
      ?>
      <div class="rl-tools">
        <span class="rl-count"><b id="rlN"><?=count($sel)?></b> / <?=count($all)?> 项已授权</span>
        <button type="button" class="btn btn-ghost btn-sm" onclick="rlAll(true)">全选</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="rlAll(false)">全不选</button>
      </div>
      <?php foreach ($groups as $gname => $perms): if (!$perms) continue; ?>
        <fieldset class="rl-group">
          <legend><label class="rl-glab"><input type="checkbox" class="rl-gcb" aria-label="全选本组"> <?=htmlspecialchars($gname)?> <span class="rl-gn"></span></label></legend>
          <div class="rl-grid">
            <?php foreach ($perms as $p): ?>
              <label class="rl-item" title="<?=htmlspecialchars($p)?>">
                <input type="checkbox" class="perm-cb" name="perms[]" value="<?=htmlspecialchars($p)?>" <?=in_array($p, $sel, true)?'checked':''?>>
                <span><?=htmlspecialchars($__lbl[$p] ?? $p)?><?php if (isset($__lbl[$p])): ?><code><?=htmlspecialchars($p)?></code><?php endif; ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <style>
      .rl-tools{display:flex;align-items:center;gap:8px;margin-bottom:10px}
      .rl-count{margin-right:auto;font-size:12.5px;color:var(--muted)}.rl-count b{font-family:var(--font-mono);color:var(--fg)}
      .rl-group{border:1px solid var(--border);border-radius:12px;padding:10px 14px 12px;margin-bottom:10px;background:var(--surface-strong)}
      .rl-group legend{padding:0 6px}
      .rl-glab{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;cursor:pointer}
      .rl-glab input{width:15px;height:15px;margin:0}
      .rl-gn{font-family:var(--font-mono);font-size:11px;color:var(--faint);font-weight:500}
      .rl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:4px 12px}
      .rl-item{display:flex;gap:8px;align-items:center;font-size:13px;padding:5px 6px;border-radius:8px;cursor:pointer}
      .rl-item:hover{background:var(--hover)}
      .rl-item input{width:15px;height:15px;margin:0;flex:0 0 auto}
      .rl-item span{display:flex;flex-direction:column;line-height:1.25;min-width:0}
      .rl-item code{font-size:10.5px;color:var(--faint);background:none;padding:0}
      </style>
      <script>
      function rlSync(){var all=document.querySelectorAll('.perm-cb'),n=document.querySelectorAll('.perm-cb:checked').length;document.getElementById('rlN').textContent=n;
        document.querySelectorAll('.rl-group').forEach(function(g){var cbs=g.querySelectorAll('.perm-cb'),c=g.querySelectorAll('.perm-cb:checked').length,gc=g.querySelector('.rl-gcb');gc.checked=c===cbs.length&&cbs.length>0;gc.indeterminate=c>0&&c<cbs.length;g.querySelector('.rl-gn').textContent=c+'/'+cbs.length;});}
      function rlAll(v){document.querySelectorAll('.perm-cb').forEach(function(c){c.checked=v});rlSync();}
      document.addEventListener('change',function(e){if(e.target.classList.contains('rl-gcb')){e.target.closest('.rl-group').querySelectorAll('.perm-cb').forEach(function(c){c.checked=e.target.checked});rlSync();}else if(e.target.classList.contains('perm-cb'))rlSync();});
      rlSync();
      </script>

      <div style="display:flex;gap:8px;margin-top:8px">
        <button class="btn btn-primary"><?= $editing ? '保存修改' : '创建角色' ?></button>
        <?php if ($editing): ?><a href="/xmp/roles" class="btn btn-ghost">取消</a><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
