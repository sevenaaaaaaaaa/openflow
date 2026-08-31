<?php
/**
 * API 权限矩阵 —— 92 个接口谁能调（docs/ROADMAP.md 阶段三）
 *
 * 后台页面的权限一直做得不差（169/191 页有 require_perm），但 API 层几乎是空的。
 * 这一页把 92 个端点摆在一张表里，逐个可调；执行发生在统一入口（admin/config.php
 * 末尾），端点文件一个都不用改。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ApiPolicy.php';
require_login();
require_perm('security');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['set_mode'])) {
        api_policy_set_mode((string)($_POST['mode'] ?? 'enforce'));
        audit('切换 API 权限矩阵模式：' . ($_POST['mode'] ?? ''), 'security');
        $msg = '模式已切换。';
    }
    if (isset($_POST['save_one'])) {
        api_policy_save((string)($_POST['slug'] ?? ''), (string)($_POST['tier'] ?? 'public'), (string)($_POST['perm'] ?? ''));
        audit('调整 API 权限：' . ($_POST['slug'] ?? ''), 'security');
        $msg = '已保存。改动立即生效，不需要重启。';
    }
    if (isset($_POST['clear_log'])) {
        @unlink(api_policy_log_file());
        $msg = '记录已清空。';
    }
}

$mode     = api_policy_mode();
$tiers    = api_policy_tiers();
$policies = api_policy_all();
$defaults = api_policy_defaults();
$perms    = of_perm_registry();
sort($perms);

// 扫出所有端点，没在策略表里的就是 public
$endpoints = [];
foreach (glob(__DIR__ . '/../api/*.php') as $f) $endpoints[] = basename($f, '.php');
sort($endpoints);

$log = json_read(api_policy_log_file());
$log = array_reverse(array_slice($log, -80));

$counts = ['public' => 0, 'member' => 0, 'admin' => 0, 'token' => 0];
foreach ($endpoints as $e) {
    $t = $policies[$e]['tier'] ?? 'public';
    if (isset($counts[$t])) $counts[$t]++;
}

$tierColor = ['public' => '#6b7280', 'member' => '#2c6a4c', 'admin' => '#8a4b1e', 'token' => '#3b5b8c'];

admin_header('API 权限矩阵');
?>
<div style="max-width:1080px">
  <h1 style="margin:0 0 4px">🔑 API 权限矩阵</h1>
  <p style="color:#666;margin:0 0 6px">
    后台页面的权限一直是有的（<strong>169 / 191</strong> 页做了判断），
    但 API 层此前<strong>只有 1 / 92</strong> 个端点做权限判断——这一页补的就是这段。
  </p>
  <p style="color:#666;margin:0 0 18px;font-size:13px">
    执行发生在<strong>统一入口</strong>（所有 <code>api/*.php</code> 都会经过的那道关卡），
    所以端点文件一个都没改，也就不存在"漏改了一个"。改了下面任何一行，<strong>立即生效</strong>。
  </p>
  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- 模式 -->
  <h2 style="font-size:16px;margin:22px 0 8px">执行模式</h2>
  <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
    <?= csrf_field() ?>
    <select name="mode" style="min-width:260px">
      <option value="enforce" <?= $mode === 'enforce' ? 'selected' : '' ?>>强制 —— 真的拦（推荐）</option>
      <option value="observe" <?= $mode === 'observe' ? 'selected' : '' ?>>观察 —— 只记录会拦谁，不真拦</option>
      <option value="off"     <?= $mode === 'off' ? 'selected' : '' ?>>关闭 —— 整个机制不生效</option>
    </select>
    <button class="btn btn-s" name="set_mode" value="1">切换</button>
    <span style="font-size:12px;color:#888">
      当前：<strong style="color:<?= $mode === 'enforce' ? '#2c6a4c' : ($mode === 'observe' ? '#c87f0a' : '#a2382a') ?>">
      <?= ['enforce' => '强制', 'observe' => '观察', 'off' => '关闭'][$mode] ?></strong>
    </span>
  </form>
  <p style="font-size:12px;color:#888;margin:0 0 20px">
    <strong>不放心就先切「观察」跑两天</strong>：它不拦任何请求，只把"本来会被拦的"记到下面的表里。
    确认没有误伤自己的前台，再切回强制。这是改访问控制的标准做法——先看，再拦。
  </p>

  <!-- 概览 -->
  <div style="display:flex;gap:1px;background:#e5e7eb;border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;margin-bottom:20px">
    <?php foreach ($tiers as $k => $label): ?>
      <div style="background:#fff;padding:10px 16px;flex:1">
        <div style="font-size:11px;color:#888;letter-spacing:.05em"><?= htmlspecialchars(explode(' · ', $label)[0]) ?></div>
        <div style="font-size:20px;font-weight:600;color:<?= $tierColor[$k] ?>"><?= $counts[$k] ?></div>
        <div style="font-size:11px;color:#aaa"><?= htmlspecialchars(explode(' · ', $label)[1] ?? '') ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- 矩阵 -->
  <h2 style="font-size:16px;margin:24px 0 8px">端点清单（共 <?= count($endpoints) ?> 个）</h2>
  <p style="color:#666;margin:0 0 10px;font-size:13px">
    默认只把<strong>明确是后台工具</strong>的端点标成「后台」——每一条都逐个查过调用方，
    确认只有 <code>admin/</code> 下的页面在调。其余一律「公开」，也就是<strong>跟改之前一模一样</strong>。
  </p>
  <div style="overflow-x:auto">
  <table class="table" style="font-size:13px">
    <tr>
      <th style="width:170px">端点</th>
      <th style="width:150px">档位</th>
      <th style="width:150px">需要的权限</th>
      <th>为什么这样定</th>
      <th style="width:70px"></th>
    </tr>
    <?php foreach ($endpoints as $e):
      $p = $policies[$e] ?? ['tier' => 'public', 'perm' => '', 'note' => ''];
      $t = $p['tier'];
      $isDefault = isset($defaults[$e]) && ($defaults[$e]['tier'] ?? '') === $t && ($defaults[$e]['perm'] ?? '') === ($p['perm'] ?? '');
      $isCustom  = !$isDefault && $t !== 'public';
      $isCustomPublic = !isset($defaults[$e]) ? false : ($t === 'public');
    ?>
    <tr<?= $t !== 'public' ? ' style="background:#fbfaf7"' : '' ?>>
      <td>
        <code><?= htmlspecialchars($e) ?></code>
        <?php if ($isCustom || $isCustomPublic): ?>
          <span style="font-size:10px;color:#c87f0a;display:block">已自定义</span>
        <?php endif; ?>
      </td>
      <form method="post" style="display:contents">
      <?= csrf_field() ?>
      <input type="hidden" name="slug" value="<?= htmlspecialchars($e) ?>">
      <td>
        <select name="tier" style="width:100%;font-size:12px">
          <?php foreach ($tiers as $k => $label): ?>
            <option value="<?= $k ?>" <?= $t === $k ? 'selected' : '' ?>><?= htmlspecialchars(explode(' · ', $label)[0]) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td>
        <select name="perm" style="width:100%;font-size:12px">
          <option value="">（不要求具体权限）</option>
          <?php foreach ($perms as $pm): ?>
            <option value="<?= htmlspecialchars($pm) ?>" <?= ($p['perm'] ?? '') === $pm ? 'selected' : '' ?>><?= htmlspecialchars($pm) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="color:#777;font-size:12px;line-height:1.6"><?= htmlspecialchars($p['note'] ?? '') ?></td>
      <td><button class="btn btn-s btn-sm" name="save_one" value="1">保存</button></td>
      </form>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <!-- 记录 -->
  <h2 style="font-size:16px;margin:28px 0 8px">拦截 / 观察记录（最近 80 条）</h2>
  <p style="color:#666;margin:0 0 10px;font-size:13px">
    观察模式下这里记的是"<strong>本来会被拦</strong>"；强制模式下记的是"<strong>已经拦了</strong>"。
    如果看到自己前台正常功能出现在这里，说明那个端点的档位定高了，回上面调低即可。
  </p>
  <?php if (!$log): ?>
    <p style="color:#999">还没有记录。</p>
  <?php else: ?>
    <table class="table" style="font-size:12px">
      <tr><th>时间</th><th>端点</th><th>档位</th><th>原因</th><th>结果</th><th>来源</th></tr>
      <?php foreach ($log as $l): ?>
        <tr>
          <td style="white-space:nowrap"><?= htmlspecialchars($l['at'] ?? '') ?></td>
          <td><code><?= htmlspecialchars($l['endpoint'] ?? '') ?></code></td>
          <td><?= htmlspecialchars($l['tier'] ?? '') ?></td>
          <td><?= htmlspecialchars(['need_login' => '未登录后台', 'need_perm' => '角色无此权限', 'need_member' => '未登录会员'][$l['reason'] ?? ''] ?? ($l['reason'] ?? '')) ?></td>
          <td><?= !empty($l['blocked']) ? '<span style="color:#a2382a">已拦</span>' : '<span style="color:#c87f0a">仅记录</span>' ?></td>
          <td style="color:#999"><?= htmlspecialchars(mb_substr((string)($l['ref'] ?? ''), 0, 46)) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" style="margin-top:8px"><?= csrf_field() ?>
      <button class="btn btn-s btn-sm" name="clear_log" value="1">清空记录</button>
    </form>
  <?php endif; ?>

  <p style="font-size:12px;color:#999;margin-top:26px;line-height:1.8">
    <strong>四个档位的意思：</strong><br>
    <strong>公开</strong>——访客可访问。前台渲染、埋点、订阅、退订这些本来就该开放。<br>
    <strong>会员</strong>——需要前台会员登录。<br>
    <strong>后台</strong>——需要后台登录；还可以再要求某个具体权限，那样 marketing / sales 角色也会被按权限区分。<br>
    <strong>机器</strong>——机器对机器调用，端点自带密钥或签名校验（如微信服务器验证要求可匿名访问），
    本层<strong>不重复拦</strong>，只做登记，免得两套校验互相打架。
  </p>
</div>
<?php admin_footer(); ?>
