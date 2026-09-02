<?php
/**
 * 前台用户中心 — 注册/登录/个人中心
 *
 * v7（2026-09-01）：从 tailwind + standalone.css + 自带顶栏 迁到 站点外壳 + tokens + modules。
 * 布局 = g-main-aside.aside-left（左侧账户导航 + 右侧面板卡），表单 = form-grid / field / inp / btn，
 * 状态 = badge / pill。业务逻辑、API 调用、各 tab 的数据准备原样保留。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/Gamification.php';
require_once __DIR__ . '/lib/SubscriptionSystem.php';
require_once __DIR__ . '/lib/MembershipSystem.php';
require_once __DIR__ . '/lib/MessageSystem.php';
require_once __DIR__ . '/lib/OrgSystem.php';

$view = $_GET['view'] ?? (member_current() ? 'dashboard' : 'login');
$member = member_current();
$next = $_GET['next'] ?? '';

// 登录后跳转
// 未登录访问任何非公开视图（我的课程 / 订单 / 会员…）→ 登录页并带回跳；原先只拦 dashboard，其它视图渲染成一张空白页
if (!$member && !in_array($view, ['login', 'register', 'reset-password'], true)) {
    $next = $next ?: ($_SERVER['REQUEST_URI'] ?? '');
    header('Location: member.php?view=login' . ($next ? '&next=' . urlencode($next) : ''));
    exit;
}
// 已登录还访问登录 / 注册页 → 直接进个人中心（原先会渲染成空面板）
if ($member && in_array($view, ['login', 'register'], true)) {
    header('Location: ' . ($next ?: 'member.php?view=dashboard'));
    exit;
}

$orders = shop_all_orders();
$myOrders = $member ? array_values(array_filter($orders, fn($o) => ($o['member_id'] ?? '') === $member['id'])) : [];

$pageTitle = ['login' => '登录', 'register' => '注册', 'dashboard' => '个人中心', 'profile' => '个人资料', 'password' => '修改密码', 'reset-password' => '重置密码'][$view] ?? '用户中心';

// 状态词 → 共享 badge / pill（颜色只来自 token）
function acct_tag(string $kind): string {
    return ['green' => 'badge ok', 'orange' => 'badge warn', 'red' => 'badge danger', 'gray' => 'pill neutral'][$kind] ?? 'pill neutral';
}
// 统计瓦片
function acct_tile(string $n, string $label, string $tone = ''): string {
    return '<div class="tile"><b' . ($tone ? ' class="' . $tone . '"' : '') . '>' . $n . '</b><span>' . htmlspecialchars($label) . '</span></div>';
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$pageTitle?> | <?=site_config_get("site_name")?></title>
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 用户中心独有：账户导航、面板头、统计瓦片、简表、计划卡、消息条。其余全部来自 modules.css。 */
.acct.g-main-aside{grid-template-columns:240px minmax(0,1fr)}
.acct>aside .card{padding:12px;position:sticky;top:20px}
.who{padding:10px 12px 14px;border-bottom:1px solid var(--border-soft);margin-bottom:8px;display:flex;flex-direction:column;gap:4px}
.who b{font-size:15px;font-weight:800}.who .em{font-size:12.5px;color:var(--muted);word-break:break-all}.who .lv{font-size:13px;font-weight:600;margin-top:4px}.who .bd{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.an{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;color:var(--muted);transition:background .12s,color .12s}
.an .ic{width:16px;height:16px;flex:0 0 auto}.an .ic svg{width:16px;height:16px}.an:hover{background:var(--hover);color:var(--fg)}.an.on{background:var(--accent);color:var(--on-accent);font-weight:600}
.an .cnt{margin-left:auto;background:var(--danger);color:var(--on-accent);border-radius:999px;padding:1px 7px;font-size:11px;font-weight:700}
.an-sep{border-top:1px solid var(--border-soft);margin:8px 0}
.panel{display:flex;flex-direction:column;gap:20px}
.panel+.panel{margin-top:20px}
.ph{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.ph .ic{width:44px;height:44px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto}.ph .ic svg{width:20px;height:20px}
.ph h2{font-size:20px;font-weight:800;letter-spacing:-.01em}.ph p{font-size:13px;color:var(--muted);margin-top:2px}.ph .r{margin-left:auto}
.panel h3{font-size:15px;font-weight:700}
.panel h3 small{font-weight:400;font-size:12px;color:var(--muted)}
.panel p.d{font-size:13.5px;color:var(--muted);line-height:1.75}
.tiles{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))}
.tile{padding:16px;border-radius:12px;background:var(--bg-soft);border:1px solid var(--border-soft);display:flex;flex-direction:column;gap:4px}
.tile b{font-family:var(--font-display);font-size:24px;font-weight:700;letter-spacing:-.01em;line-height:1.1}.tile b.ok{color:var(--ok)}.tile b.ac{color:var(--accent)}.tile b.wn{color:var(--warn)}.tile span{font-size:12px;color:var(--muted)}
.box{padding:18px;border-radius:12px;background:var(--bg-soft);border:1px solid var(--border-soft);display:flex;flex-direction:column;gap:10px}
.box.dash{background:var(--surface);border-style:dashed;border-color:var(--border-strong)}
.box.ok{background:var(--ok-soft);border-color:transparent;color:var(--ok)}.box.warn{background:var(--warn-soft);border-color:transparent;color:var(--warn)}.box.danger{background:var(--danger-soft);border-color:transparent}
.tbl{width:100%;font-size:13.5px;border-collapse:collapse}
.tbl th{text-align:left;font-weight:600;color:var(--muted);padding:8px 10px;border-bottom:1px solid var(--border);font-size:12.5px}
.tbl td{padding:10px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
.tbl .mu{color:var(--muted)}.tbl .ok{color:var(--ok);font-weight:600}.tbl .dn{color:var(--danger)}.tbl .wn{color:var(--warn)}
.tbl-wrap{border:1px solid var(--border);border-radius:12px;overflow:auto}
.tbl-wrap .tbl th{background:var(--bg-soft)}
.plan{padding:20px;border-radius:14px;border:2px solid var(--border);display:flex;flex-direction:column;gap:6px;background:var(--surface)}
.plan.cur{border-color:var(--accent);background:var(--accent-soft)}
.plan .pn{font-size:16px;font-weight:800}.plan .pp{font-family:var(--font-display);font-size:24px;font-weight:700;color:var(--ok)}.plan .pp small{font-family:var(--font-body);font-size:12px;font-weight:400;color:var(--muted)}
.plan ul{list-style:none;padding:0;font-size:12.5px;color:var(--muted);line-height:1.8}
.plan .btn{margin-top:auto}
.msg{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600}
.msg.ok{background:var(--ok-soft);color:var(--ok)}.msg.err{background:var(--danger-soft);color:var(--danger)}
.row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.item{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px}
.item .t{flex:1;min-width:0}.item .t b{display:block;font-size:14px}.item .t span{display:block;font-size:12px;color:var(--muted);margin-top:2px}
.bars{display:flex;gap:3px;align-items:flex-end;height:70px}.bars i{flex:1;background:var(--ok);border-radius:3px 3px 0 0;display:block}
.inp.sm,select.inp.sm{min-height:42px;padding:9px 12px;font-size:13.5px}
.btn.sm{height:40px;padding:0 18px;font-size:14px}
.auth{width:min(440px,100%);margin:0 auto}
@media (max-width:1080px){.acct.g-main-aside{grid-template-columns:1fr}.acct>aside .card{position:static;display:flex;flex-wrap:wrap;gap:4px;align-items:center}.acct .who{width:100%;border-bottom:0;margin:0 0 4px}.acct .an{padding:8px 12px;font-size:13px;border:1px solid var(--border-soft)}.acct .an .cnt{margin-left:4px}.acct .an-sep{display:none}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('account'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
<?php if (!$member && in_array($view, ['login','register','reset-password'])): ?>
  <!-- 登录 / 注册 / 密码重置 -->
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="acct-auth">
    <div class="auth">
    <?php if ($view === 'reset-password'): ?>
      <?php include_member_reset_password(); ?>
    <?php else: ?>
      <div class="form-card panel">
        <div class="sec-head center" style="gap:8px"><span class="kicker"><?=$view==='login'?'SIGN IN':'SIGN UP'?></span><h2 style="font-size:24px"><?=$view==='login'?'欢迎回来':'创建账号'?></h2><p class="lead" style="font-size:14px"><?=$view==='login'?'登录你的 OpenFlow 账号':'注册后可购买课程、成为讲师'?></p></div>
        <?php if ($view === 'login'): ?>
        <form onsubmit="memberLogin(event)" class="form-grid">
          <div class="field"><label for="l_account">邮箱或手机号</label><input class="inp" type="text" name="account" id="l_account" required placeholder="you@example.com 或手机号"></div>
          <div class="field"><label for="l_password">密码</label><input class="inp" type="password" name="password" id="l_password" required placeholder="••••••"></div>
          <button type="submit" class="btn primary" style="width:100%">登录</button>
          <p class="note" style="text-align:center;margin:0">还没有账号？<a href="member.php?view=register<?=$next?'&next='.urlencode($next):''?>" style="color:var(--accent);font-weight:600">立即注册</a> · <a href="member.php?view=reset-password" style="color:var(--accent);font-weight:600">忘记密码</a></p>
        </form>
        <?php else: ?>
        <form onsubmit="memberRegister(event)" class="form-grid">
          <div class="field"><label for="r_name">姓名</label><input class="inp" type="text" name="name" id="r_name" required placeholder="你的真实姓名"></div>
          <?php $__needCaptcha = member_captcha_required('probe@example.com'); // 验证码只经邮件送达；邮件未配置时不向用户索要收不到的验证码 ?>
          <div class="field"><label for="r_email">邮箱</label><div style="display:flex;gap:8px"><input class="inp" type="email" name="email" id="r_email" required placeholder="you@example.com" style="flex:1"><?php if ($__needCaptcha): ?><button type="button" class="btn ghost" style="flex:0 0 auto" onclick="memberSendCaptcha(document.getElementById('r_email').value)">发验证码</button><?php endif; ?></div></div>
          <?php if ($__needCaptcha): ?><div class="field"><label for="r_captcha">邮箱验证码</label><input class="inp" type="text" name="captcha" id="r_captcha" required inputmode="numeric" placeholder="6 位验证码"></div><?php endif; ?>
          <div class="field"><label for="r_phone">手机号 <span class="hint">· 选填</span></label><input class="inp" type="tel" name="phone" id="r_phone" placeholder="11 位手机号"></div>
          <div class="field"><label for="r_password">密码</label><input class="inp" type="password" name="password" id="r_password" required minlength="6" placeholder="至少 6 位"></div>
          <?php if (!empty($_GET['ref'])): ?><input type="hidden" name="referral" value="<?=htmlspecialchars($_GET['ref'])?>"><?php endif; ?>
          <button type="submit" class="btn primary" style="width:100%">注册</button>
          <p class="note" style="text-align:center;margin:0">已有账号？<a href="member.php?view=login<?=$next?'&next='.urlencode($next):''?>" style="color:var(--accent);font-weight:600">直接登录</a></p>
        </form>
        <?php endif; ?>
        <div id="memberMsg"></div>
      </div>
    <?php endif; ?>
    </div>
  </section>

<?php elseif ($member): ?>
  <!-- 个人中心 -->
  <section id="top" class="sec reveal in" data-od-anchor data-od-id="acct-main">
    <div class="g-main-aside aside-left acct">
      <aside>
        <div class="card">
          <div class="who">
            <b><?=htmlspecialchars($member['name'])?></b>
            <span class="em"><?=htmlspecialchars($member['email'])?></span>
            <?php $mLevel = gamification_level_of($member['points'] ?? 0); $mEnt = member_entitlements($member); ?>
            <span class="lv"><?=$mLevel['icon']?> <?=htmlspecialchars($mLevel['name'])?> <span class="note" style="margin:0;display:inline">· <?=$member['points']??0?> 积分</span></span>
            <div class="bd">
              <span class="badge ok"><?=$mEnt['icon']?> <?=htmlspecialchars($mEnt['tier_name'])?></span>
              <?php if (sub_is_active($member['id'])): ?><span class="badge warn">订阅中</span><?php endif; ?>
              <?php if (!empty($member['ambassador'])): ?><span class="badge ok">推荐大使</span><?php endif; ?>
            </div>
          </div>
          <?php
          $I = fn(string $p) => '<span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg></span>';
          $navItems = [
            ['dashboard', 'member.php?view=dashboard', '个人中心', '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>'],
            ['membership', 'member.php?view=membership', '会员中心', '<path d="M6 3h12l4 6-10 12L2 9l4-6Z"/><path d="M2 9h20M9 3 7 9l5 12M15 3l2 6-5 12"/>'],
            ['level', 'member.php?view=level', '我的等级', '<path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M10 14h4v3h-4zM12 17v3M8 21h8"/>'],
            ['subscribe', 'member.php?view=subscribe', '付费订阅', '<path d="m2 8 9-5 9 5-9 5-9-5Z"/><path d="M2 8v8l9 5 9-5V8M11 13v8"/>'],
            ['orders', 'member.php?view=orders', '我的订单', '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>'],
            ['courses', 'member.php?view=courses', '我的课程', '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/>'],
            ['teacher', 'member.php?view=teacher', '成为讲师', '<path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/>'],
            ['submit', 'member.php?view=submit', '投稿文章', '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>'],
            ['consult', '/consultation?view=my', '我的 1v1 咨询', '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/>'],
            ['org', 'member.php?view=org', '企业控制台', '<path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10"/>'],
            ['developer', 'member.php?view=developer', '开发者中心', '<path d="m8 9-3 3 3 3M13 15h4"/><path d="M7 4h13a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/>'],
            ['distribution', 'member.php?view=distribution', '分销中心', '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
          ];
          foreach ($navItems as [$k, $href, $label, $path]): ?>
          <a class="an<?=$view===$k?' on':''?>" href="<?=$href?>"><?=$I($path)?><?=$label?></a>
          <?php endforeach; $msgUnread = inbox_unread($member); ?>
          <a class="an" href="/messages.php"><?=$I('<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/>')?>站内信<?php if ($msgUnread): ?><span class="cnt"><?=$msgUnread?></span><?php endif; ?></a>
          <div class="an-sep"></div>
          <a class="an<?=$view==='profile'?' on':''?>" href="member.php?view=profile"><?=$I('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>')?>个人资料</a>
          <a class="an<?=$view==='password'?' on':''?>" href="member.php?view=password"><?=$I('<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>')?>修改密码</a>
          <a class="an<?=$view==='addresses'?' on':''?>" href="member.php?view=addresses"><?=$I('<path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>')?>收货地址</a>
          <a class="an<?=$view==='privacy'?' on':''?>" href="member.php?view=privacy"><?=$I('<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>')?>隐私中心</a>
          <div class="an-sep"></div>
          <a class="an" href="javascript:memberLogout()" style="color:var(--danger)"><?=$I('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>')?>退出登录</a>
        </div>
      </aside>

      <div>
        <?php $tab = $_GET['view'] ?? 'dashboard'; ?>
        <?php if ($tab === 'dashboard'): ?>
        <div class="card panel">
          <div class="ph"><div><h2>个人中心</h2><p>你好，<?=htmlspecialchars($member['name'])?>。从「我的课程」开始你的学习之旅。</p></div></div>
          <div class="tiles">
            <?=acct_tile((string)count(array_filter($myOrders, fn($o)=>$o['status']==='paid')), '已购课程')?>
            <?=acct_tile((string)count($myOrders), '全部订单')?>
            <?php if (!empty($member['ambassador'])): ?><?=acct_tile('¥' . ($member['balance']??0), '佣金余额', 'ok')?><?php endif; ?>
          </div>
          <?php
          // 个性化推荐（基于 CDP 画像）
          try {
              require_once __DIR__ . '/lib/Personalizer.php';
              $pref = Personalizer::buildProfile($_COOKIE['fc_uid'] ?? '', $member['id'], $member['email'] ?? '');
              $recProducts = Personalizer::recommendProducts($pref, 4);
              $recCourses = Personalizer::recommendCourses($pref, 3);
          } catch (Throwable $e) { $recProducts = []; $recCourses = []; }
          if (!empty($recProducts) || !empty($recCourses)): ?>
          <div class="panel" style="border-top:1px solid var(--border-soft);padding-top:20px;gap:12px">
            <h3>为你推荐 <small>根据你的行为画像智能推荐 · 进入分群 / 点击商品 / 浏览课程都会更新</small></h3>
            <?php if (!empty($recProducts)): ?>
            <span class="kicker" style="font-size:11px">生态工具</span>
            <div class="grid g3" style="gap:10px">
              <?php foreach (array_keys($recProducts) as $rpid): $rp = CommerceSystem::getProduct($rpid); if (!$rp) continue; ?>
              <a href="/<?=urlencode($rp['type'] ?? 'skill')?>/<?=urlencode($rp['asset_id'] ?? $rpid)?>" class="item"><div class="t"><b><?=htmlspecialchars($rp['title'])?></b><span>¥<?=number_format((float)($rp['pricing']['price'] ?? 0),0)?> · 即买即用</span></div></a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($recCourses)): ?>
            <span class="kicker" style="font-size:11px">相关课程</span>
            <div class="grid g3" style="gap:10px">
              <?php foreach (array_keys($recCourses) as $rcid): $rc = null; foreach (json_read(DATA_DIR . '/courses/index.json') as $cc) { if ($cc['id'] === $rcid) { $rc = $cc; break; } } if (!$rc) continue; ?>
              <a href="/course/<?=urlencode($rcid)?>?id=<?=urlencode($rcid)?>" class="item"><div class="t"><b><?=htmlspecialchars($rc['title'])?></b><span><?=htmlspecialchars($rc['type'] ?? '课程')?> · <?=count($rc['chapters'] ?? [])?> 章</span></div></a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php elseif ($tab === 'membership'): include_member_membership($member); ?>
        <?php elseif ($tab === 'orders'): include_member_orders($myOrders); ?>
        <?php elseif ($tab === 'level'): include_member_level($member); ?>
        <?php elseif ($tab === 'subscribe'): include_member_subscribe($member); ?>
        <?php elseif ($tab === 'courses'): include_member_courses($member); ?>
        <?php elseif ($tab === 'ambassador'): include_member_ambassador($member); ?>
        <?php elseif ($tab === 'teacher'): include_member_teacher($member); ?>
        <?php elseif ($tab === 'submit'): include_member_submit($member); ?>
        <?php elseif ($tab === 'profile'): include_member_profile($member); ?>
        <?php elseif ($tab === 'password'): include_member_password($member); ?>
        <?php elseif ($tab === 'addresses'): include_member_addresses($member); ?>
        <?php elseif ($tab === 'privacy'): include_member_privacy($member); ?>
        <?php elseif ($tab === 'org'): include_member_org($member); ?>
        <?php elseif ($tab === 'developer'): include_member_developer($member); ?>
        <?php elseif ($tab === 'distribution'): include_member_distribution($member); ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
function msgHtml(html, ok) { return '<div class="msg ' + (ok ? 'ok' : 'err') + '">' + html + '</div>'; }
function memberMsg(html, isErr) {
  document.getElementById('memberMsg').innerHTML = msgHtml(html, !isErr);
}
/* 登录 / 注册成功后回到来时的页面（?next=），以前一律落到个人中心，「登录后报名」这类流程走到一半就断了 */
var MEMBER_NEXT = <?=json_encode((isset($next) && is_string($next) && preg_match('#^/(?!/)#', $next)) ? $next : '')?>;
function memberAfterAuth() { location.href = MEMBER_NEXT || '/account'; }
function memberLogin(e) {
  e.preventDefault();
  var fd = new FormData();
  fd.append('action','login');
  fd.append('account', document.getElementById('l_account').value);
  fd.append('password', document.getElementById('l_password').value);
  fetch('/api/member.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) memberAfterAuth(); else memberMsg(d.error, true); });
}
function memberRegister(e) {
  e.preventDefault();
  var fd = new FormData(e.target);
  fd.append('action','register');
  fetch('/api/member.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) memberAfterAuth(); else memberMsg(d.error, true); });
}
function memberSendCaptcha(target) {
  if (!target) { memberMsg('请先填写邮箱', true); return; }
  var fd = new FormData();
  fd.append('action','send_captcha');
  fd.append('target', target);
  fetch('/api/member.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ memberMsg(d.ok ? d.message : d.error, !d.ok); });
}
function memberLogout() {
  var fd = new FormData(); fd.append('action','logout');
  fetch('/api/member.php', { method:'POST', body: fd }).then(function(){ location.href = '/'; });
}
function subscribePlan(planId) {
  if (!confirm('确认订阅该计划？将跳转支付。')) return;
  var fd = new FormData();
  fd.append('action','create_subscription');
  fd.append('plan_id', planId);
  fetch('/api/shop.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { alert(d.error); return; }
      var form = document.createElement('form');
      form.method = 'POST'; form.action = d.payment.gateway;
      Object.keys(d.payment.params).forEach(function(k){
        var input = document.createElement('input'); input.type='hidden'; input.name=k; input.value=d.payment.params[k];
        form.appendChild(input);
      });
      document.body.appendChild(form); form.submit();
    });
}
</script>
<?php
// ─── 各 tab 内容（内联函数）───
function include_member_membership($member): void {
    $e = member_entitlements($member);
    $lists = member_benefit_list($member);
    $plans = mem_plans();
    $current = $e['tier'];
    ?>
    <div class="card panel">
      <div class="ph">
        <div class="ic" style="width:56px;height:56px;border-radius:50%;font-size:26px"><?=$e['icon']?></div>
        <div><h2><?=htmlspecialchars($e['tier_name'])?></h2><p><?=$e['points']?> 积分 · <?=htmlspecialchars($e['level']['name'] ?? '')?> 等级<?php if ($e['subscription']): ?> · ⭐ 订阅中<?php endif; ?></p></div>
        <?php if ($current === 'free'): ?><a href="#shop-plans" class="btn primary sm r">开通会员 →</a><?php endif; ?>
      </div>

      <!-- 权益总览 -->
      <?php foreach ($lists as $cat => $items): ?>
      <div class="panel" style="gap:10px">
        <h3><?=htmlspecialchars($cat)?></h3>
        <div class="grid g3" style="gap:8px">
          <?php foreach ($items as $it): $locked = strpos($it['状态'],'🔒')!==false; ?>
          <div class="item" style="padding:10px 14px"><div class="t"><b style="font-size:13px"><?=htmlspecialchars($it['权益'])?></b><span style="color:<?=$locked?'var(--faint)':'var(--ok)'?>"><?=htmlspecialchars($it['状态'])?></span></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- 会员计划 -->
      <div class="panel" style="gap:12px" id="shop-plans">
        <h3>商品会员计划</h3>
        <?php $shopPlan = member_shop_plan($member); $quota = member_quota_usage($member); ?>
        <?php if ($shopPlan): ?>
        <div class="box ok" style="font-size:13px">当前：<b><?=htmlspecialchars($shopPlan['name'])?></b> · 今日已用 <?=$quota['used']?>/<?=$quota['daily']?> 单 · 剩余 <b><?=$quota['left']?></b> 单免费<?php if (($shopPlan['period'] ?? '') === 'year'): ?> · <?=date('Y-m-d', strtotime($member['membership_expires']))?> 到期<?php endif; ?></div>
        <?php endif; ?>
        <div class="grid g3" style="gap:14px">
          <?php foreach ($plans as $p): if (in_array($p['id'], ['annual','lifetime'])): $isCurrent = ($shopPlan['id'] ?? '') === $p['id']; ?>
          <div class="plan<?=$isCurrent?' cur':''?>">
            <div style="font-size:24px"><?=htmlspecialchars($p['icon'] ?? '')?></div>
            <div class="pn"><?=htmlspecialchars($p['name'] ?? '')?></div>
            <div class="pp">¥<?=$p['price']?><small><?=$p['period']==='year'?' /年':' /永久'?></small></div>
            <span class="kicker" style="font-size:11px">每天免费 <?=$p['quota_per_day']?> 个任意商品</span>
            <ul><?php foreach ($p['benefits'] ?? [] as $b): ?><li>✓ <?=htmlspecialchars($b)?></li><?php endforeach; ?></ul>
            <?php if ($isCurrent): ?><span class="badge ok" style="align-self:flex-start">当前会员</span>
            <?php else: ?><button type="button" onclick="buyMembership('<?=htmlspecialchars($p['id'])?>')" class="btn primary sm"><?=$p['price']>0?'立即开通':'免费'?> →</button><?php endif; ?>
          </div>
          <?php endif; endforeach; ?>
        </div>
        <p class="note" style="margin:0;line-height:1.7">会员商品仅限本人账号使用：不得二次开发、转售或打包分发，擅自魔改/复用构成侵权。免费下单按自然日重置。</p>
      </div>
    </div>
    <script>
    function buyMembership(planId) {
      // 找到对应会员商品并下单
      var assetId = planId;
      var fd = new FormData();
      fd.append('action', 'purchase');
      fd.append('id', assetId);
      fetch('/api/marketplace.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.ok && d.payment && d.payment.ok) {
            var form = document.createElement('form');
            form.method = 'POST'; form.action = d.payment.gateway; form.target = '_blank';
            Object.keys(d.payment.params).forEach(function(k){ var i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=d.payment.params[k]; form.appendChild(i); });
            document.body.appendChild(form); form.submit();
          } else if (d.ok && d.order) {
            alert('支付需配置支付渠道，请联系管理员');
          } else {
            alert(d.error || '下单失败');
          }
        });
    }
    </script>
    <?php
}

function include_member_level($member): void {
    $points = $member['points'] ?? 0;
    $levels = gamification_levels();
    $current = gamification_level_of($points);
    // 下一级
    $next = null;
    foreach ($levels as $l) if ($l['min_points'] > $points) { $next = $l; break; }
    echo '<div class="card panel"><div class="ph"><div><h2>我的等级</h2></div></div>';
    echo '<div class="box">';
    echo '<div style="font-family:var(--font-display);font-size:28px;font-weight:700">' . $current['icon'] . ' ' . htmlspecialchars($current['name']) . '</div>';
    echo '<div class="note" style="margin:0">当前积分：<strong>' . $points . '</strong></div>';
    if ($next) {
        $need = $next['min_points'] - $points;
        echo '<div style="height:8px;background:var(--border);border-radius:99px;overflow:hidden"><div style="height:100%;width:' . min(100, round($points/$next['min_points']*100)) . '%;background:linear-gradient(90deg,var(--ok),var(--accent))"></div></div>';
        echo '<div class="note" style="margin:0">距 ' . $next['icon'] . ' ' . htmlspecialchars($next['name']) . ' 还需 <strong>' . $need . '</strong> 积分</div>';
    } else { echo '<div class="note" style="margin:0;color:var(--ok)">已达最高等级</div>'; }
    echo '</div>';
    // 等级权益
    echo '<h3>等级权益</h3><div class="grid g4" style="gap:10px">';
    foreach ($levels as $l) {
        $isCur = $l['key'] === $current['key'];
        echo '<div class="plan' . ($isCur ? ' cur' : '') . '" style="padding:14px;gap:2px">' .
            '<div style="font-weight:700;font-size:14px">' . $l['icon'] . ' ' . htmlspecialchars($l['name']) . '</div>' .
            '<div class="note" style="margin:0">' . $l['min_points'] . ' 积分起</div>' .
            '<div class="note" style="margin:0">' . implode('、', array_map(fn($p)=>['post'=>'发帖','comment'=>'评论','vote'=>'投票','no_review'=>'免审核','featured'=>'推荐位'][$p]??$p, $l['perms'])) . '</div>' .
            '</div>';
    }
    echo '</div>';
    // 积分记录
    echo '<h3>积分记录</h3>';
    $log = $member['points_log'] ?? [];
    if (empty($log)) echo '<div class="empty">暂无积分记录</div>';
    else {
        echo '<table class="tbl"><thead><tr><th>积分</th><th>原因</th><th>时间</th></tr></thead><tbody>';
        foreach (array_slice(array_reverse($log),0,20) as $pl) {
            echo '<tr><td class="' . ($pl['points']>=0?'ok':'dn') . '">' . ($pl['points']>=0?'+':'') . $pl['points'] . '</td><td>' . htmlspecialchars($pl['reason']) . '</td><td class="mu mono">' . htmlspecialchars(substr($pl['time']??'',0,16)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}
function include_member_subscribe($member): void {
    $settings = sub_settings();
    $plans = array_values(array_filter(sub_get_plans(), fn($p) => !empty($p['enabled'])));
    $mySub = sub_get_member($member['id']);
    $active = sub_is_active($member['id']);
    echo '<div class="card panel"><div class="ph"><div><h2>付费订阅</h2></div></div>';
    if ($active) {
        echo '<div class="box ok">你已是订阅会员，有效期至 <strong>' . htmlspecialchars($mySub['expires_at'] ?? '') . '</strong></div>';
    }
    if (empty($settings['enabled'])) {
        echo '<div class="empty">订阅暂未开放，敬请期待。</div>';
    } elseif (empty($plans)) {
        echo '<div class="empty">暂无可订阅计划。</div>';
    } else {
        echo '<div class="grid g3" style="gap:14px">';
        foreach ($plans as $p) {
            $period = ($p['period'] ?? 'month') === 'month' ? '/月' : '/年';
            echo '<div class="plan">' .
                '<div class="pn">' . htmlspecialchars($p['name']) . '</div>' .
                '<div class="pp" style="color:var(--fg)">¥' . number_format($p['price']??0,2) . '<small>' . $period . '</small></div>' .
                '<p class="d" style="min-height:40px">' . htmlspecialchars($p['description'] ?? '') . '</p>' .
                '<button type="button" onclick="subscribePlan(\'' . htmlspecialchars($p['id']) . '\')" class="btn primary sm">订阅</button>' .
                '</div>';
        }
        echo '</div>';
        echo '<p class="note" style="margin:0">海外用户：' . ($settings['ghost_enabled'] ? '<a href="' . htmlspecialchars($settings['ghost_api_url']) . '/#/portal" target="_blank" rel="noopener" style="color:var(--accent)">通过 Ghost 订阅 →</a>' : '未开启 Ghost 海外订阅') . '</p>';
    }
    echo '</div>';
}
function include_member_orders(array $orders): void {
    echo '<div class="card panel"><div class="ph"><div><h2>我的订单</h2></div></div>';
    if (empty($orders)) { echo '<div class="empty">暂无订单，去逛逛课程吧 → <a href="/courses" style="color:var(--accent);font-weight:600">浏览课程</a></div>'; }
    else {
        echo '<table class="tbl"><thead><tr><th>订单号</th><th>课程</th><th>金额</th><th>状态</th><th>时间</th></tr></thead><tbody>';
        foreach ($orders as $o) {
            $statusTag = ['paid'=>'已支付','pending'=>'待支付','cancelled'=>'已取消','refunded'=>'已退款'][$o['status']] ?? $o['status'];
            $tagCls = $o['status']==='paid'?'green':($o['status']==='pending'?'orange':'gray');
            echo '<tr><td class="mu mono">' . htmlspecialchars(substr($o['id'],-10)) . '</td><td>' . htmlspecialchars($o['course_title']) . '</td><td>¥' . number_format($o['amount']??0,2) . '</td><td><span class="' . acct_tag($tagCls) . '">' . $statusTag . '</span></td><td class="mu mono">' . htmlspecialchars(substr($o['created_at']??'',0,10)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}
function include_member_courses($member): void {
    require_once __DIR__ . '/lib/ProgressSystem.php';
    $courseIds = shop_course_ids_for_member($member['id']);
    $courses = json_read(DATA_DIR . '/courses/index.json');
    echo '<div class="card panel"><div class="ph"><div><h2>我的课程</h2></div></div>';
    if (empty($courseIds)) { echo '<div class="empty">你还没有购买课程，去逛逛 → <a href="/courses" style="color:var(--accent);font-weight:600">浏览课程</a></div>'; }
    else {
        echo '<div class="grid g3" style="gap:14px">';
        foreach ($courses as $c) {
            if (!in_array($c['id'], $courseIds)) continue;
            $s = progress_summary($member['id'], $c['id'], $c);
            echo '<a href="course-player.php?id=' . urlencode($c['id']) . '" class="plan" style="color:inherit">' .
                '<div class="pn" style="font-size:15px">' . htmlspecialchars($c['title']) . '</div>' .
                ($s['percent'] > 0 ?
                    '<div style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;margin-top:6px"><div style="height:100%;width:' . $s['percent'] . '%;background:linear-gradient(90deg,var(--ok),var(--accent))"></div></div>' .
                    '<div class="note" style="margin:0">已学 ' . $s['done'] . '/' . $s['total'] . ' 节 · ' . $s['percent'] . '%</div>'
                    : '<div class="note" style="margin:0">' . count($c['chapters']??[]) . ' 章 · 点击开始学习 →</div>') .
                '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}
function include_member_ambassador($member): void {
    if (empty($member['ambassador'])) {
        echo '<div class="card panel"><div class="gate-box"><span class="kicker">AMBASSADOR</span><h2>成为推荐大使</h2><p>分享你的专属链接，好友通过链接购买课程，你将获得佣金。</p><a href="api/ambassador.php?action=apply" class="btn primary">立即申请成为大使</a></div></div>';
    } else {
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST']??'');
        echo '<div class="card panel"><div class="ph"><div><h2>我的推广</h2></div></div>' .
            '<div class="field"><label>我的专属推广链接</label>' .
            '<div style="display:flex;gap:8px"><input class="inp sm mono" readonly value="' . $base . '/member.php?view=register&ref=' . htmlspecialchars($member['referral_code']) . '" style="flex:1"><button type="button" class="btn ghost sm" onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(()=>alert(\'已复制\'))">复制</button></div></div>' .
            '<div class="tiles">' .
            acct_tile((string)($member['ambassador_stats']['clicks']??0), '点击') .
            acct_tile((string)($member['ambassador_stats']['orders']??0), '成交') .
            acct_tile('¥' . number_format($member['balance']??0,2), '佣金余额', 'ok') . '</div>' .
            '<p class="d">佣金规则由管理员在后台「分销设置」中配置。可在后台查看提现记录。</p></div>';
    }
}
function include_member_teacher($member): void {
    $status = $member['teacher_status'] ?? 'none';
    echo '<div class="card panel"><div class="ph"><div><h2>成为讲师</h2></div></div>';
    if ($status === 'approved') echo '<div class="box ok">你已成为讲师，可以在「投稿文章」中发布内容了。</div>';
    elseif ($status === 'pending') echo '<div class="box warn">申请审核中，请耐心等待。</div>';
    else {
        echo '<p class="d">分享你的专业经验，成为 OpenFlow 认证讲师。提交申请后由管理员审核。</p>';
        if ($status === 'rejected') echo '<div class="box danger" style="color:var(--danger)">上次申请未通过，可重新提交。</div>';
        echo '<form onsubmit="return false" id="teacherForm" class="form-grid">' .
            '<div class="field"><label for="t_intro">讲师简介</label><textarea id="t_intro" class="inp" name="intro" rows="3" placeholder="介绍你的专业领域和经验"></textarea></div>' .
            '<div class="field"><label for="t_exp">擅长方向</label><input id="t_exp" class="inp" name="expertise" placeholder="如：SEO/GEO、内容策略、AI 运营"></div>' .
            '<div><button type="button" class="btn primary" onclick="submitTeacher()">提交申请</button></div></form>';
    }
    echo '</div>';
}
function include_member_submit($member): void {
    echo '<div class="card panel"><div class="ph"><div><h2>投稿文章</h2><p>提交后由管理员审核，审核通过后发布。</p></div></div>' .
        '<form onsubmit="return false" id="submitForm" class="form-grid">' .
        '<div class="field"><label for="s_title">文章标题</label><input id="s_title" class="inp" name="title" placeholder="标题"></div>' .
        '<div class="grid g2" style="gap:14px">' .
        '<div class="field"><label for="s_cat">分类</label><select id="s_cat" class="inp" name="category"><option value="insight">增长洞察</option><option value="leadership">内容与 SEO</option><option value="ai_ops">AI 运营</option><option value="industry">行业实践</option></select></div>' .
        '<div class="field"><label for="s_ex">摘要</label><input id="s_ex" class="inp" name="excerpt" placeholder="一句话摘要"></div></div>' .
        '<div class="field"><label for="s_body">正文</label><textarea id="s_body" class="inp" name="content" rows="8" placeholder="文章正文"></textarea></div>' .
        '<div><button type="button" class="btn primary" onclick="submitArticle()">提交投稿</button></div></form>' .
        '<div id="submitMsg"></div></div>';
}

// ─── 个人资料编辑 ───
function include_member_profile($member): void {
    ?>
    <div class="card panel">
      <div class="ph"><div><h2>个人资料</h2></div></div>
      <form onsubmit="return updateProfile(event)" class="form-grid">
        <div class="grid g2" style="gap:14px">
          <div class="field"><label>昵称</label><input class="inp" type="text" name="nickname" value="<?=htmlspecialchars($member['nickname'] ?? '')?>" placeholder="你的昵称"></div>
          <div class="field"><label>头像 URL</label><input class="inp" type="url" name="avatar" value="<?=htmlspecialchars($member['avatar'] ?? '')?>" placeholder="https://example.com/avatar.jpg"></div>
          <div class="field"><label>手机号</label><input class="inp" type="tel" name="phone" value="<?=htmlspecialchars($member['phone'] ?? '')?>" placeholder="手机号码"></div>
          <div class="field"><label>个人网站</label><input class="inp" type="url" name="website" value="<?=htmlspecialchars($member['website'] ?? '')?>" placeholder="https://yoursite.com"></div>
          <div class="field"><label>公司</label><input class="inp" type="text" name="company" value="<?=htmlspecialchars($member['company'] ?? '')?>" placeholder="公司名称"></div>
          <div class="field"><label>职位</label><input class="inp" type="text" name="job_title" value="<?=htmlspecialchars($member['job_title'] ?? '')?>" placeholder="职位名称"></div>
        </div>
        <div class="field"><label>个人简介</label><textarea class="inp" name="bio" rows="3" placeholder="介绍一下自己"><?=htmlspecialchars($member['bio'] ?? '')?></textarea></div>
        <div class="cta-row"><button type="submit" class="btn primary">保存修改</button><a href="member.php?view=password" class="btn ghost">修改密码</a></div>
        <div id="profileMsg"></div>
      </form>
    </div>
    <?php
}

// ─── 修改密码 ───
function include_member_password($member): void {
    ?>
    <div class="card panel">
      <div class="ph"><div><h2>修改密码</h2></div></div>
      <form onsubmit="return changePassword(event)" class="form-grid" style="max-width:480px">
        <div class="field"><label>当前密码</label><input class="inp" type="password" name="old_password" required placeholder="输入当前密码"></div>
        <div class="field"><label>新密码</label><input class="inp" type="password" name="new_password" required placeholder="至少 8 位" minlength="8"></div>
        <div class="field"><label>确认新密码</label><input class="inp" type="password" name="confirm_password" required placeholder="再次输入新密码" minlength="8"></div>
        <div><button type="submit" class="btn primary">修改密码</button></div>
        <div id="passwordMsg"></div>
      </form>
    </div>
    <?php
}

// ─── 收货地址管理 ───
function include_member_addresses($member): void {
    $addr = json_read(DATA_DIR . '/addresses.json');
    $mine = array_values(array_filter((array)$addr, fn($a) => ($a['member_id'] ?? '') === $member['id']));
    ?>
    <div class="card panel">
      <div class="ph"><div><h2>收货地址</h2></div></div>
      <div id="addrList" class="grid g3" style="gap:12px">
        <?php if (empty($mine)): ?><div class="empty" style="grid-column:1/-1">还没有收货地址，添加一个用于商品寄送。</div><?php endif; ?>
        <?php foreach ($mine as $a): ?>
        <div class="plan" style="padding:14px;gap:6px">
          <div class="row" style="justify-content:space-between"><b style="font-size:14px"><?=htmlspecialchars($a['name'])?> <span style="color:var(--muted);font-weight:400"><?=htmlspecialchars($a['phone'])?></span></b><?php if (!empty($a['is_default'])): ?><span class="badge ok">默认</span><?php endif; ?></div>
          <div class="note" style="margin:0;line-height:1.6"><?=htmlspecialchars(implode(' ', array_filter([$a['province']??'', $a['city']??'', $a['district']??'', $a['address']??''])))?></div>
          <div><button type="button" class="btn subtle" style="height:32px;padding:0 10px;font-size:12.5px;color:var(--danger)" onclick="delAddr('<?=htmlspecialchars($a['id'])?>')">删除</button></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="box">
        <h3>新增地址</h3>
        <form onsubmit="return saveAddr(event)" class="form-grid" style="gap:10px">
          <div class="grid g2" style="gap:10px"><input class="inp sm" type="text" name="name" placeholder="收货人姓名" required><input class="inp sm" type="text" name="phone" placeholder="手机号" required></div>
          <div class="grid g3" style="gap:10px"><input class="inp sm" type="text" name="province" placeholder="省"><input class="inp sm" type="text" name="city" placeholder="市"><input class="inp sm" type="text" name="district" placeholder="区/县"></div>
          <input class="inp sm" type="text" name="address" placeholder="详细地址" required>
          <label class="note" style="display:flex;align-items:center;gap:6px;margin:0;font-size:12.5px"><input type="checkbox" name="is_default" value="1" style="width:15px;height:15px;accent-color:var(--accent)"> 设为默认地址</label>
          <div><button class="btn primary sm">保存地址</button></div>
          <div id="addrMsg" class="note" style="margin:0"></div>
        </form>
      </div>
    </div>
    <script>
    function saveAddr(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('addrMsg');
      var fd = new FormData(); fd.append('action','save');
      ['name','phone','province','city','district','address'].forEach(function(k){ fd.append(k, f.querySelector('[name="'+k+'"]').value); });
      fd.append('is_default', f.querySelector('[name=is_default]').checked ? '1' : '');
      fetch('/api/address.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 900); });
      return false;
    }
    function delAddr(id) {
      if (!confirm('确认删除该地址？')) return;
      var fd = new FormData(); fd.append('action','delete'); fd.append('id', id);
      fetch('/api/address.php', { method:'POST', body: fd }).then(function(){ location.reload(); });
    }
    </script>
    <?php
}

// ─── 隐私中心（数据导出/注销）───
function include_member_privacy($member): void {
    require_once __DIR__ . '/lib/PrivacySystem.php';
    $msg = '';
    if (isset($_GET['export'])) {
        $r = privacy_export_member($member['id']);
        if ($r['ok']) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="my-data-' . date('Ymd') . '.json"');
            echo json_encode($r['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        $msg = $r['error'] ?? '导出失败';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
        if ($_POST['confirm'] === 'DELETE') {
            $r = privacy_delete_member($member['id']);
            if ($r['ok']) { header('Location: /'); exit; }
            $msg = $r['error'] ?? '注销失败';
        } else { $msg = '请输入 DELETE 确认注销'; }
    }
    ?>
    <div class="card panel">
      <div class="ph"><div><h2>隐私中心</h2><p>依据《个人信息保护法》，你可随时导出自己的数据或申请删除账号。</p></div></div>
      <?php if ($msg): ?><div class="msg err"><?=htmlspecialchars($msg)?></div><?php endif; ?>

      <div class="box">
        <h3>导出我的数据</h3>
        <p class="d">下载你的完整数据（个人资料、订单、消息、评论、学习进度），JSON 格式。</p>
        <div><a href="?view=privacy&export=1" class="btn primary sm">下载数据</a></div>
      </div>

      <div class="box danger">
        <h3 style="color:var(--danger)">注销账号</h3>
        <p class="d">注销后你的个人资料将清除，不可恢复。输入 <b>DELETE</b> 确认。</p>
        <form method="post" class="row">
          <input class="inp sm mono" type="text" name="confirm" placeholder="输入 DELETE" style="width:200px">
          <button type="submit" name="delete_account" class="btn primary sm" style="background:var(--danger);box-shadow:none">确认注销</button>
        </form>
      </div>
    </div>
    <?php
}

// ─── 企业控制台（ToB）───
function include_member_org($member): void {
    $org = org_by_member($member['id'] ?? '');
    $statuses = org_statuses();
    $plans = org_plans();
    ?>
    <div class="card panel">
      <?php if ($org): $status = $statuses[$org['status']]['label'] ?? $org['status']; ?>
        <div class="ph">
          <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10"/></svg></div>
          <div><h2><?=htmlspecialchars($org['name'])?></h2><p><?=htmlspecialchars($org['industry'])?> / <?=htmlspecialchars($org['size'])?></p></div>
          <span class="<?=acct_tag($org['status']==='active'?'green':($org['status']==='lead'?'orange':'gray'))?> r"><?=htmlspecialchars($status)?></span>
        </div>

        <div class="tiles">
          <?=acct_tile(org_plan_label($org['plan_type']), '合作方案')?>
          <?=acct_tile((string)count((array)($org['members'] ?? [])), '团队成员')?>
          <?=acct_tile(htmlspecialchars($org['budget'] ?: '—'), '预算区间')?>
        </div>

        <div class="panel" style="gap:0">
          <h3 style="margin-bottom:6px">团队成员</h3>
          <?php foreach ((array)($org['members'] ?? []) as $mid): $m = member_get($mid); ?>
          <div class="row" style="padding:10px 0;border-bottom:1px solid var(--border-soft)">
            <div style="width:34px;height:34px;border-radius:50%;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-weight:700;font-size:13px;flex:0 0 auto"><?=strtoupper(mb_substr(($m['name'] ?? ($m['email'] ?? '?')),0,1))?></div>
            <div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:600"><?=htmlspecialchars($m['name'] ?? '')?></div><div class="note" style="margin:0"><?=htmlspecialchars($m['email'] ?? '')?></div></div>
            <?php if (($org['admin_member_id'] ?? '') === $mid): ?><span class="badge ok">管理员</span><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <p class="note" style="margin:0">更多成员由商务顾问在合作确认后邀请加入。企业专属支持与部署进度将在此展示。</p>
      <?php else: ?>
        <div class="gate-box">
          <span class="ic" style="width:44px;height:44px;color:var(--accent)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10"/></svg></span>
          <h2>你的企业还没有商业版申请</h2>
          <p>为团队申请 OpenFlow 商业发行版（SaaS 订阅 / 私有化部署 / 定制开发），一个平台撑起整条增长链。</p>
          <a href="/enterprise" class="btn primary">申请商业版 →</a>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
// ─── 开发者中心（入驻/开发套件/我的产品/提交） ───
function include_member_developer($member): void {
    $devStatus = $member['developer_status'] ?? 'none';
    $types = skill_types();
    $mine = skill_by_author($member['id']);
    // 参与者工作台（BACKLOG T1-14 · OIA）：一次加入即拥有三种能力
    $bw = ['profile' => null, 'caps' => []];
    try {
        require_once __DIR__ . '/lib/BuilderWorkspace.php';
        $bw['caps'] = builder_capabilities($member);
        $bw['profile'] = builder_profile($member);
    } catch (Throwable $e) {}
    $devTag = ['none'=>'gray','pending'=>'orange','approved'=>'green','rejected'=>'red'][$devStatus] ?? 'gray';
    ?>
    <div class="card panel">
      <div class="ph">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 9-3 3 3 3M13 15h4"/><path d="M7 4h13a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg></div>
        <div><h2>参与者工作台</h2><p>One is All — 你同时是作者、开发者、创作者。写内容 / 做工具 / 上架卖，一处全给。</p></div>
        <span class="<?=acct_tag($devTag)?> r"><?=['none'=>'未申请','pending'=>'审核中','approved'=>'已认证开发者','rejected'=>'申请被拒'][$devStatus] ?? '未申请'?></span>
      </div>

      <?php if (!empty($bw['caps'])): $bp = $bw['profile']; ?>
      <!-- OIA 三能力：不再需要"先申请开发者" -->
      <div class="grid g3" style="gap:12px">
        <?php foreach ($bw['caps'] as $ck => $cap): ?>
        <div class="box" style="gap:4px">
          <div class="row" style="gap:8px"><b style="font-size:14px"><?=htmlspecialchars($cap['label'])?></b><span class="<?=$cap['enabled']?'badge ok':'pill neutral'?>" style="height:22px;font-size:11px"><?=$cap['enabled']?'已开通':'暂不可用'?></span></div>
          <p class="d" style="font-size:12.5px"><?=htmlspecialchars($cap['desc'])?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- 描述即造（BACKLOG T1-15）：把开发降到"描述"，三道护栏后存草稿 -->
      <div class="box">
        <h3>描述即造 <small>不用会写代码</small></h3>
        <p class="d" style="font-size:12.5px">用一句话说你想要什么工具，AI 帮你生成。生成物会先过安全审查，通过后存为草稿，由你确认再发布。</p>
        <div class="row" style="gap:8px">
          <input id="bsDesc" class="inp sm" placeholder="例如：帮我把文章标题改写得更吸引点击" style="flex:1;min-width:220px">
          <button type="button" class="btn primary sm" id="bsGo">生成</button>
        </div>
        <div id="bsOut" style="font-size:13px"></div>
      </div>

      <?php if ($bp): ?>
      <div class="box dash" style="gap:4px">
        <div style="font-size:13px">我的贡献：文章 <strong><?=$bp['counts']['article']?></strong> · 技能 <strong><?=$bp['counts']['skill']?></strong> · 商品 <strong><?=$bp['counts']['product']?></strong> <span style="color:var(--muted)">（已上线 <?=$bp['published']?>）</span></div>
        <div style="font-size:12.5px;color:var(--accent);font-weight:600">→ <?=htmlspecialchars($bp['next_step'])?></div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($devStatus === 'approved'):
        // 作者收益看板：余额 + 累计销售分成
        $devBalance = 0;
        try { $bq = Database::query("SELECT balance FROM members WHERE id = ?", [$member['id']]); $devBalance = (float)($bq[0]['balance'] ?? 0); } catch (Exception $e) {}
        $devSales = 0; $devComms = [];
        try {
            $devOrders = Database::query("SELECT * FROM orders WHERE author = ? AND status = 'paid'", [$member['id']]);
            foreach ($devOrders as $do) { $devSales += (float)($do['amount'] ?? 0); }
        } catch (Exception $e) { $devOrders = []; }
        ?>
      <div class="tiles">
        <?=acct_tile('¥' . number_format($devBalance,2), '我的余额（可提现）', 'ok')?>
        <?=acct_tile((string)count($devOrders), '卖出产品')?>
        <?=acct_tile('¥' . number_format($devSales,0), '累计销售额', 'ac')?>
        <?=acct_tile(round(($member['distributor_rate'] ?? 0)) . '%', '默认佣金比例', 'wn')?>
      </div>
      <?php
        // 我的买家画像 + 本周增长动作（BACKLOG T1-11）：把"收银台"升级为"增长伙伴"
        $cg = ['stats' => [], 'actions' => []];
        try { require_once __DIR__ . '/lib/CreatorGrowth.php'; $cg = creator_dashboard($member['id'], $devOrders ?: []); } catch (Throwable $e) {}
        $cs = $cg['stats'];
      ?>
      <?php if (!empty($cs)): ?>
      <div class="panel" style="gap:12px">
        <h3>我的买家</h3>
        <div class="tiles">
          <?=acct_tile((string)(int)($cs['buyers']??0), '买家数')?>
          <?=acct_tile((int)($cs['repeat_rate']??0) . '%', '复购率')?>
          <?=acct_tile('¥' . number_format((float)($cs['avg_order']??0),0), '客单价')?>
          <?=acct_tile(($cs['last_sale_days']??9999)>=9999?'—':(string)(int)$cs['last_sale_days'], '天前最近成交')?>
        </div>
        <h3>本周该做的三件事</h3>
        <div class="panel" style="gap:8px">
          <?php foreach (($cg['actions'] ?? []) as $ai => $act): ?>
          <div class="item"><div class="t"><b><?=($ai+1)?>. <?=htmlspecialchars($act['title'])?></b><span><?=htmlspecialchars($act['why'])?></span></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="box dash" style="font-size:12.5px;color:var(--muted)">
        <?php
          $__rate = 10;
          try { require_once __DIR__ . '/lib/CommissionPolicy.php'; $__rate = round(commission_platform_rate() * 100); } catch (Throwable $e) {}
        ?>
        收益规则：平台抽 <?=$__rate?>% 覆盖支付手续费，分销者按产品佣金比例分成，剩余归你（作者）。余额可在 <a href="member.php?view=distribution" style="color:var(--accent);font-weight:600">分销中心</a> 提现。
      </div>
      <?php endif; ?>

      <?php if ($devStatus === 'none'): ?>
      <!-- 申请成为开发者 -->
      <div class="box">
        <h3>成为开发者，上传你的第一个产品</h3>
        <p class="d">认证后即可提交 Skill（AI 指令 / 工具 / 工作流）和主题模板。审核通过后上架市场，供所有用户启用。</p>
        <form onsubmit="return applyDev(event)" class="form-grid">
          <div class="field"><label>开发者简介 *（至少 10 字，介绍你会做什么）</label><textarea class="inp" name="bio" rows="2" required placeholder="如：专注增长类 Skill，擅长小红书文案与 SEO"></textarea></div>
          <div class="grid g2" style="gap:12px">
            <div class="field"><label>擅长的技能方向</label><input class="inp" type="text" name="skills" placeholder="SEO / 文案 / 自动化…"></div>
            <div class="field"><label>个人/团队主页（选填）</label><input class="inp" type="text" name="website" placeholder="https://…"></div>
          </div>
          <div><button type="submit" class="btn primary">提交申请</button></div>
          <div id="applyDevMsg" class="note" style="margin:0"></div>
        </form>
      </div>
      <?php elseif ($devStatus === 'pending'): ?>
      <div class="gate-box"><span class="kicker">PENDING</span><h2>申请审核中</h2><p>管理员审核通过后，你就可以上传产品了。</p></div>
      <?php elseif ($devStatus === 'rejected'): ?>
      <div class="box danger"><h3 style="color:var(--danger)">申请未通过</h3><p class="d">可完善简介后重新提交，或联系管理员。</p></div>
      <?php elseif ($devStatus === 'approved'): ?>
      <!-- 发布课程 -->
      <div class="box" style="background:var(--surface)">
        <h3>发布课程 <small>审核后上架，售出享分成</small></h3>
        <p class="d" style="font-size:12.5px">收益：平台抽 10%，分销者按比例分成，剩余归你。提交后管理员审核。</p>
        <form id="courseForm" onsubmit="return submitCourse(event)" class="form-grid">
          <div class="grid g3" style="gap:12px;grid-template-columns:2fr 1fr 1fr">
            <div class="field"><label>课程标题 *</label><input class="inp sm" type="text" name="title" required placeholder="如：AI 增长实操课"></div>
            <div class="field"><label>分类</label><select class="inp sm" name="category"><option>课程</option><option>专栏</option><option>认证课</option><option>系列课</option></select></div>
            <div class="field"><label>封面图 URL</label><input class="inp sm" type="text" name="cover" placeholder="https://…"></div>
          </div>
          <div class="field"><label>课程简介</label><textarea class="inp" name="description" rows="2" placeholder="这门课讲什么、适合谁…"></textarea></div>
          <div id="chaptersBox" class="panel" style="gap:10px"></div>
          <div><button type="button" class="btn ghost sm" style="border-style:dashed" onclick="addChapter()">+ 添加章节</button></div>
          <div class="row"><button type="submit" class="btn primary">提交课程</button><span id="courseMsg" class="note" style="margin:0"></span></div>
        </form>
      </div>
      <!-- 我的产品 -->
      <div class="panel" style="gap:10px">
        <h3>我的产品（<?=count($mine)?>）</h3>
        <?php if (empty($mine)): ?><div class="empty">还没有产品，用下面的表单提交第一个。</div>
        <?php else: ?>
        <div class="panel" style="gap:8px">
          <?php foreach ($mine as $s): $stMap = ['pending'=>'待审核','published'=>'已上架','rejected'=>'被拒','draft'=>'草稿']; $stCls = ['pending'=>'orange','published'=>'green','rejected'=>'red','draft'=>'gray']; ?>
          <div class="item">
            <span class="ic" style="width:36px;height:36px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:18px"><?=htmlspecialchars($s['icon'] ?? '⚡')?></span>
            <div class="t"><b><?=htmlspecialchars($s['title'])?></b><span><?=htmlspecialchars($types[$s['type']]['name'] ?? $s['type'])?> · 安装 <?=$s['installs']??0?> · 评分 <?=$s['rating']??0?></span></div>
            <span class="<?=acct_tag($stCls[$s['status']]??'gray')?>"><?=$stMap[$s['status']]??$s['status']?></span>
            <?php if (($s['status'] ?? '') !== 'published'): ?><button type="button" class="btn subtle" style="height:32px;padding:0 10px;font-size:12.5px;color:var(--danger)" onclick="delProduct('<?=htmlspecialchars($s['id'])?>')">删除</button><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 我的组合包 + 创建组合包 -->
      <div class="box">
        <h3>组合包 <small>Skills 包 / 主题包 / 大组合</small></h3>
        <p class="d" style="font-size:12.5px">把你的多个产品打包成一个套装售卖：多个 Skills、多个主题、或混合大组合（Skills + 主题 + 功能）。可嵌套组合包。</p>
        <?php $publishedMine = array_values(array_filter($mine, fn($s) => ($s['status'] ?? '') === 'published')); ?>
        <?php if (empty($publishedMine)): ?>
        <p class="note" style="margin:0">需要至少 1 个已上架的产品才能创建组合包。提交的产品审核通过后即可打包。</p>
        <?php else: ?>
        <form onsubmit="return createBundleForm(event)" class="form-grid">
          <div class="field"><label>组合包名称 *</label><input class="inp sm" type="text" name="bundle_title" required placeholder="如：SEO 增长全家桶"></div>
          <div class="field"><label>选择要打包的产品（可多选）</label>
            <div class="panel" style="gap:6px;max-height:200px;overflow-y:auto;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--surface)">
              <?php foreach ($publishedMine as $s): ?>
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer"><input type="checkbox" name="bundle_items[]" value="skill:<?=htmlspecialchars($s['id'])?>" style="width:15px;height:15px;accent-color:var(--accent)"> <?=htmlspecialchars($s['icon'] ?? '⚡')?> <?=htmlspecialchars($s['title'])?> <span class="note" style="margin:0">Skill</span></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="grid g2" style="gap:12px">
            <div class="field"><label>组合包售价 ¥</label><input class="inp sm" type="number" name="bundle_price" min="1" step="1" value="99"></div>
            <div class="field"><label>分销者佣金 %</label><input class="inp sm" type="number" name="bundle_dist_rate" min="5" max="80" step="5" value="30"></div>
          </div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13.5px"><input type="checkbox" name="bundle_dist_enabled" value="1" checked style="width:16px;height:16px;accent-color:var(--accent)"> 允许分销</label>
          <div><button type="submit" class="btn primary">创建组合包</button></div>
          <div id="bundleMsg" class="note" style="margin:0"></div>
        </form>
        <?php endif; ?>
      </div>

      <!-- 提交新产品 -->
      <div class="box">
        <h3>提交新产品</h3>
        <p class="d" style="font-size:12.5px">填写表单，自动生成标准 Skill 产品。审核通过后上架市场。</p>
        <form onsubmit="return submitSkill(event)" class="form-grid">
          <div class="grid g2" style="gap:12px">
            <div class="field"><label>产品名称 *</label><input class="inp sm" type="text" name="title" required placeholder="如：小红书爆款文案"></div>
            <div class="field"><label>类型</label><select class="inp sm" name="type" id="devSkillType">
              <?php foreach ($types as $k => $t): ?><option value="<?=$k?>"><?=$t['icon']?> <?=$t['name']?> — <?=$t['desc']?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="field"><label>一句话描述</label><input class="inp sm" type="text" name="description" placeholder="产品能做什么？"></div>
          <div class="field"><label>标签（逗号分隔）</label><input class="inp sm" type="text" name="tags" placeholder="SEO, 文案, 增长"></div>
          <div class="grid g2" style="gap:12px">
            <div class="field"><label>售价 ¥（0 = 免费）</label><input class="inp sm" type="number" name="price" min="0" step="1" value="0"></div>
            <div class="field"><label>分销者佣金比例 %（5-80）</label><input class="inp sm" type="number" name="distributor_rate" min="5" max="80" step="5" value="30"></div>
          </div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13.5px"><input type="checkbox" name="distribution_enabled" value="1" checked style="width:16px;height:16px;accent-color:var(--accent)"> 允许分销：任何人可帮你卖，佣金归分销者</label>
          <p class="note" style="margin:0">分成结构：平台抽 10%（覆盖支付手续费）→ 分销者拿上比例 → 你拿剩余（约 <?=100-10-30?>%）。一级分销，不设多级。</p>
          <div class="field"><label>内容 / 指令模板 *（AI 指令 / 工具说明，用 {topic} 等占位符）</label><textarea class="inp mono" name="content" rows="6" required placeholder="你是…请为「{topic}」…"></textarea></div>
          <p id="devSkillTip" class="note" style="margin:0">开发套件：参考市场里的官方 Skill 结构。AI 指令用 <code class="mono">{topic}</code> 等变量占位，工作流可多段描述。</p>
          <div><button type="submit" class="btn primary">提交审核</button></div>
          <div id="submitSkillMsg" class="note" style="margin:0"></div>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <script>
    // 描述即造（T1-15）：生成 → 显示审查结论 → 通过才允许存草稿
    (function () {
      var go = document.getElementById('bsGo');
      if (!go) return;
      go.addEventListener('click', function () {
        var d = (document.getElementById('bsDesc') || {}).value || '';
        var out = document.getElementById('bsOut');
        if (d.trim().length < 5) { out.textContent = '描述太短，说清你想要什么。'; return; }
        out.textContent = '生成中…';
        var fd = new FormData(); fd.append('description', d); fd.append('save', '1');
        fetch('/api/build-skill.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (!j.ok) { out.innerHTML = '<span style="color:var(--danger)">✋ ' + (j.error || '生成失败') + '</span>'; return; }
            var s = j.skill || {};
            var perms = (s.permissions || []).length ? ('　需要权限：' + s.permissions.join('、')) : '';
            var tag = j.verdict === 'safe' ? '<span style="color:var(--ok)">✅ 通过安全审查</span>'
                                           : '<span style="color:var(--warn)">⚠️ 需人工确认</span>';
            out.innerHTML = tag + '　已存为草稿：<strong>' + (s.title || '') + '</strong>' + perms
                          + '<div style="color:var(--muted);font-size:12px;margin-top:4px">' + (s.description || '') + '</div>';
          })
          .catch(function () { out.textContent = '网络错误，稍后再试。'; });
      });
    })();
    function applyDev(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('applyDevMsg');
      var fd = new FormData(f); fd.append('action', 'apply_developer');
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1200); });
      return false;
    }
    function submitSkill(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('submitSkillMsg');
      var fd = new FormData(f); fd.append('action', 'submit_skill');
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1200); });
      return false;
    }
    function delProduct(id) {
      if (!confirm('确认删除该产品？')) return;
      var fd = new FormData(); fd.append('action','delete_product'); fd.append('id', id);
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d.ok) location.reload(); else alert(d.error); });
    }
    function addChapter() {
      var box = document.getElementById('chaptersBox');
      var ci = box.children.length;
      var ch = document.createElement('div');
      ch.className = 'chapter-card box';
      ch.style.cssText = 'gap:8px;background:var(--surface)';
      ch.innerHTML = '<div class="row" style="gap:8px"><input type="text" placeholder="章节名称" class="ch-title inp sm" style="flex:1"><button type="button" class="btn ghost" style="height:36px;padding:0 12px;font-size:12.5px" onclick="addLesson(this)">+课时</button><button type="button" class="mx" aria-label="删除章节" onclick="this.closest(\'.chapter-card\').remove()">✕</button></div><div class="lessons-box panel" style="gap:6px" data-ci="' + ci + '"></div>';
      box.appendChild(ch);
    }
    function addLesson(btn) {
      var box = btn.closest('.chapter-card').querySelector('.lessons-box');
      var li = document.createElement('div');
      li.style.cssText = 'display:grid;grid-template-columns:1fr 90px 1fr auto;gap:6px;align-items:center';
      li.innerHTML = '<input type="text" placeholder="课时标题" class="l-title inp sm">' +
        '<select class="l-type inp sm"><option value="video">视频</option><option value="text">图文</option><option value="quiz">测验</option></select>' +
        '<input type="text" placeholder="视频URL/内容" class="l-video inp sm">' +
        '<button type="button" class="mx" aria-label="删除课时" onclick="this.parentElement.remove()">✕</button>';
      box.appendChild(li);
    }
    function submitCourse(e) {
      e.preventDefault();
      var f = document.getElementById('courseForm'), msg = document.getElementById('courseMsg');
      var chapters = [];
      f.querySelectorAll('.chapter-card').forEach(function(ch){
        var lessons = [];
        ch.querySelectorAll('.lessons-box > div').forEach(function(l){
          var t = l.querySelector('.l-title').value.trim();
          if (!t) return;
          lessons.push({ title: t, type: l.querySelector('.l-type').value, video: l.querySelector('.l-video').value.trim() });
        });
        var ct = ch.querySelector('.ch-title').value.trim();
        if (ct && lessons.length) chapters.push({ title: ct, lessons: lessons });
      });
      var fd = new FormData();
      fd.append('action', 'submit_course');
      fd.append('title', f.querySelector('[name=title]').value);
      fd.append('category', f.querySelector('[name=category]').value);
      fd.append('cover', f.querySelector('[name=cover]').value);
      fd.append('description', f.querySelector('[name=description]').value);
      fd.append('chapters', JSON.stringify(chapters));
      msg.style.color='var(--muted)'; msg.textContent='提交中…';
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1200); });
      return false;
    }
    function createBundleForm(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('bundleMsg');
      var items = Array.from(f.querySelectorAll('input[name="bundle_items[]"]:checked')).map(function(i){ return i.value; });
      if (!items.length) { msg.style.color='var(--danger)'; msg.textContent='请至少选择一个产品'; return; }
      var fd = new FormData();
      fd.append('action', 'create_bundle');
      fd.append('title', f.querySelector('[name=bundle_title]').value);
      fd.append('price', f.querySelector('[name=bundle_price]').value);
      fd.append('distributor_rate', f.querySelector('[name=bundle_dist_rate]').value);
      fd.append('distribution_enabled', f.querySelector('[name=bundle_dist_enabled]').checked ? '1' : '');
      fd.append('items', JSON.stringify(items));
      msg.style.color='var(--muted)'; msg.textContent='创建中…';
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1000); });
      return false;
    }
    </script>
    <?php
}
// ─── 分销中心（一级分销） ───
function include_member_distribution($member): void {
    $stats = commerce_distributor_stats($member['id']);
    $refCode = $member['referral_code'] ?? ('of' . substr(md5($member['id']), 0, 8));
    $siteUrl = site_config_get('site_url');
    // 状态筛选 + CSV 导出
    $fst = $_GET['dstatus'] ?? 'all';
    $details = $stats['details'] ?? [];
    if ($fst === 'paid') $details = array_values(array_filter($details, fn($d) => $d['status'] === 'paid'));
    if ($fst === 'pending') $details = array_values(array_filter($details, fn($d) => $d['status'] !== 'paid'));
    if (isset($_GET['export']) && $_GET['export'] === '1') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="commission-' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF商品,成交额,佣金,状态,时间\n";
        foreach ($details as $d) {
            echo implode(',', [mb_convert_encoding($d['title'], 'GBK', 'UTF-8'), $d['amount'], $d['commission'], $d['status'] === 'paid' ? '已结算' : '待支付', mb_convert_encoding(substr($d['time'] ?? '', 0, 16), 'GBK', 'UTF-8')]) . "\n";
        }
        exit;
    }
    // 可推广的商品（已发布且允许分销）+ 有价课程（课程分销）
    $distProducts = array_values(array_filter(CommerceSystem::allPublished(), fn($p) => !empty($p['distribution_enabled']) && (float)($p['pricing']['price'] ?? 0) > 0));
    try {
        $shopCfg = shop_settings();
        foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
            if (($c['status'] ?? '') !== 'published') continue;
            $cp = (float)($shopCfg['course_prices'][$c['id']] ?? 0);
            if ($cp <= 0) continue;
            $distProducts[] = [
                'id' => $c['id'], 'title' => $c['title'],
                'category' => '课程', 'course' => true,
                'pricing' => ['price' => $cp],
                'distributor_rate' => (float)($shopCfg['commission_rate'] ?? 20),
            ];
        }
    } catch (Exception $e) {}
    ?>
    <div class="card panel">
      <div class="ph">
        <div class="ic" style="background:var(--ok-soft);color:var(--ok)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div><h2>分销中心</h2><p>帮你推广平台上的 Skill 产品，卖出即赚佣金（一级分销，平台抽 10% 覆盖支付手续费）</p></div>
      </div>

      <div class="tiles">
        <?=acct_tile('¥' . number_format($stats['balance'],2), '账户余额（可提现）', 'ok')?>
        <?=acct_tile('¥' . number_format($stats['total_commission'],2), '累计佣金', 'ok')?>
        <?=acct_tile((string)$stats['total_orders'], '带来的订单')?>
        <?=acct_tile('¥' . number_format($stats['pending_commission'],2), '待结算（未支付）', 'wn')?>
        <?=acct_tile('<span class="mono">' . htmlspecialchars($refCode) . '</span>', '我的分销码', 'ac')?>
      </div>

      <div class="box dash">
        <h3>复制推广链接 <small>分享给任何人，他购买你拿佣金</small></h3>
        <p class="d" style="font-size:12.5px">平台抽 10% 覆盖支付手续费；分销者拿产品配置的佣金比例；作者拿剩余。</p>
        <div class="row" style="gap:8px">
          <input type="text" id="refBase" class="inp sm mono" value="<?=htmlspecialchars($siteUrl)?>/marketplace?ref=<?=htmlspecialchars($refCode)?>" readonly style="flex:1;min-width:220px">
          <button type="button" class="btn primary sm" onclick="var i=document.getElementById('refBase');i.select();document.execCommand('copy');alert('已复制推广链接')">复制</button>
        </div>
      </div>

      <?php $board = commerce_leaderboard($member['id'], 30, 10); if (!empty($board['top'])): ?>
      <div class="box" style="background:linear-gradient(135deg,var(--accent-soft),var(--surface))">
        <div class="row"><h3>分销之星 · 近30天佣金 TOP<?=count($board['top'])?></h3><?php if ($board['self']): ?><span class="note" style="margin:0 0 0 auto">我的排名 <b style="color:var(--accent)">#<?=$board['self']['rank']?></b> / <?=$board['total']?></span><?php endif; ?></div>
        <div style="display:flex;gap:10px;overflow-x:auto;padding-bottom:4px">
          <?php foreach ($board['top'] as $i => $b): $isSelf = $b['member_id'] === $member['id']; ?>
          <div class="plan<?=$isSelf?' cur':''?>" style="min-width:120px;flex:1;padding:14px;text-align:center;gap:2px;align-items:center">
            <div style="font-family:var(--font-display);font-size:22px;font-weight:700;color:<?=$i<3?'var(--warn)':'var(--muted)'?>"><?=($i<3)?['🥇','🥈','🥉'][$i]:'#' . ($i+1)?></div>
            <div style="font-size:13px;font-weight:600;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($b['name'])?><?=$isSelf?'（我）':''?></div>
            <div class="note" style="margin:0"><?=$b['orders']?>单</div>
            <div style="font-size:14px;font-weight:700;color:var(--ok)">¥<?=number_format($b['commission'],1)?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="panel" style="gap:10px">
        <h3>推广商品 <small>每商品专属链接</small></h3>
        <?php if (empty($distProducts)): ?><div class="empty">暂无开放分销的商品。</div>
        <?php else: ?>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>商品</th><th>售价</th><th>佣金比例</th><th>我的链接</th></tr></thead>
            <tbody>
              <?php foreach ($distProducts as $dp): ?>
              <tr>
                <td><b><?=htmlspecialchars($dp['title'])?></b><div class="note" style="margin:0"><?=htmlspecialchars($dp['category'] ?? 'Skill')?></div></td>
                <td>¥<?=number_format((float)($dp['pricing']['price'] ?? 0),0)?></td>
                <td style="color:var(--accent);font-weight:600"><?=round((float)($dp['distributor_rate'] ?? 30))?>%</td>
                <td>
                  <div class="row" style="gap:6px;flex-wrap:nowrap">
                    <input type="text" class="inp sm mono" value="<?=htmlspecialchars($siteUrl)?><?=!empty($dp['course'])?('/course/' . urlencode($dp['id']) . '?id=' . urlencode($dp['id']) . '&ref='):('/marketplace?ref=')?><?=htmlspecialchars($refCode)?>" readonly style="flex:1;min-width:170px;min-height:34px;padding:5px 8px;font-size:11.5px">
                    <button type="button" class="btn ghost" style="height:34px;padding:0 12px;font-size:12px" onclick="var i=this.previousElementSibling;i.select();document.execCommand('copy');this.textContent='✓';setTimeout(function(){this.textContent='复制'}.bind(this),1200)">复制</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="panel" style="gap:10px">
        <h3>推广数据 <small>近30天订单</small></h3>
        <?php if (empty($stats['daily_trend'] ?? [])): ?><div class="empty">暂无推广订单。</div>
        <?php else: ?>
        <div class="bars">
          <?php $maxT = max($stats['daily_trend']) ?: 1; foreach ($stats['daily_trend'] as $d => $n): ?>
          <i title="<?=$d?> · <?=$n?>单" style="opacity:<?=max(0.25,$n/$maxT)?>;height:<?=$n>0?max(3,round($n/$maxT*70)):2?>px"></i>
          <?php endforeach; ?>
        </div>
        <div class="note mono" style="display:flex;justify-content:space-between;margin:0;font-size:10.5px"><span><?=htmlspecialchars(array_key_first($stats['daily_trend'])?:'')?></span><span><?=htmlspecialchars(array_key_last($stats['daily_trend'])?:'')?></span></div>
        <?php if (!empty($stats['product_stats'])): ?>
        <div class="note" style="margin:0">商品业绩：<?php foreach ($stats['product_stats'] as $pt => $ps): ?><span style="margin-right:12px"><?=htmlspecialchars($pt)?> · <?=$ps['orders']?>单 · <b style="color:var(--ok)">¥<?=number_format($ps['commission'],1)?></b></span><?php endforeach; ?></div>
        <?php endif; endif; ?>
      </div>

      <div class="panel" style="gap:10px">
        <h3>佣金明细</h3>
        <div class="row" style="gap:8px">
          <div class="tab-bar dense" style="border-bottom:0;padding-bottom:0;justify-content:flex-start;gap:6px">
          <?php $fst = $_GET['dstatus'] ?? 'all'; foreach (['all'=>'全部','paid'=>'已结算','pending'=>'待支付'] as $fk => $fl): ?>
          <a href="?view=distribution&dstatus=<?=$fk?>" class="tab-p" aria-selected="<?=$fst===$fk?'true':'false'?>"><?=$fl?></a>
          <?php endforeach; ?>
          </div>
          <?php if (!empty($details)): ?><a href="?view=distribution&dstatus=<?=$fst?>&export=1" class="btn ghost" style="height:34px;padding:0 14px;font-size:12.5px;margin-left:auto">⬇ 导出 CSV</a><?php endif; ?>
        </div>
        <?php if (empty($details)): ?><div class="empty"><?= $fst==='all' ? '还没有佣金记录，分享推广链接后产生。' : '该状态下暂无佣金记录。' ?></div>
        <?php else: ?>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>商品</th><th>成交额</th><th>我的佣金</th><th>状态</th><th>时间</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($details,0,50) as $d): ?>
              <tr>
                <td><?=htmlspecialchars($d['title'])?></td>
                <td>¥<?=number_format($d['amount'],2)?></td>
                <td class="ok">¥<?=number_format($d['commission'],2)?></td>
                <td><span class="<?=$d['status']==='paid'?'badge ok':'badge warn'?>"><?=$d['status']==='paid'?'已结算':'待支付'?></span></td>
                <td class="mu mono" style="font-size:12px"><?=htmlspecialchars(substr($d['time']??'',0,16))?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- 提现 -->
      <div class="box">
        <h3>提现</h3>
        <div class="grid g2" style="gap:18px">
          <div class="panel" style="gap:10px">
            <p class="note" style="margin:0">余额 <b style="color:var(--ok)">¥<?=number_format($stats['balance'],2)?></b> · 最低提现按后台配置（当前 <?=number_format((shop_settings()['min_withdraw'] ?? 100),0)?> 元）</p>
            <form onsubmit="return submitWithdraw(event)" class="form-grid" style="gap:10px">
              <input class="inp sm" type="number" name="wd_amount" min="1" step="0.01" placeholder="提现金额 ¥" required>
              <select class="inp sm" name="wd_method"><option value="wechat">微信收款</option><option value="alipay">支付宝</option><option value="bank">银行卡</option></select>
              <input class="inp sm" type="text" name="wd_account" placeholder="收款账户（微信号/支付宝/卡号）" required>
              <div><button class="btn primary sm">申请提现</button></div>
              <div id="wdMsg" class="note" style="margin:0"></div>
            </form>
          </div>
          <div class="panel" style="gap:8px">
            <b style="font-size:13px">提现记录</b>
            <?php
              $wdList = array_values(array_filter((array)json_read(DATA_DIR . '/shop/withdrawals.json'), fn($w) => ($w['member_id'] ?? '') === $member['id']));
              $wdMap = ['pending'=>'待审核','paid'=>'已打款','rejected'=>'已驳回'];
              $wdTag = ['pending'=>'badge warn','paid'=>'badge ok','rejected'=>'badge danger'];
            ?>
            <?php if (empty($wdList)): ?><div class="empty">暂无提现记录</div>
            <?php else: ?>
            <div class="panel" style="gap:6px;max-height:180px;overflow-y:auto">
              <?php foreach (array_slice(array_reverse($wdList),0,10) as $w): ?>
              <div class="item" style="padding:8px 10px;background:var(--surface)"><div class="t"><b style="color:var(--ok);font-size:13px">¥<?=number_format($w['amount'],2)?></b><span class="mono"><?=substr($w['created_at']??'',0,16)?></span></div><span class="<?=$wdTag[$w['status']]??'pill neutral'?>" style="height:22px;font-size:11px"><?=$wdMap[$w['status']]??$w['status']?></span></div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="panel" style="gap:10px">
        <h3>可推广的产品（<?=count($distProducts)?>）</h3>
        <?php if (empty($distProducts)): ?>
        <div class="box dash">
          <b style="font-size:13px">当前暂无可推广的付费产品</b>
          <p class="d" style="font-size:12.5px">官方 Skill 目前免费开放（无分销佣金）。开发者将付费产品上架并开启分销后，你就能推广赚钱了——先复制推广链接占位，产品上架即可用。</p>
        </div>
        <?php else: ?>
        <div class="grid g3" style="gap:12px">
          <?php foreach (array_slice($distProducts, 0, 12) as $p): ?>
          <div class="plan" style="padding:14px;gap:6px">
            <b style="font-size:14px"><?=htmlspecialchars($p['title'] ?? '')?></b>
            <p class="d" style="font-size:12px"><?=htmlspecialchars(mb_substr($p['description'] ?? '', 0, 50))?></p>
            <div class="row" style="justify-content:space-between"><b style="color:var(--ok)">¥<?=number_format($p['pricing']['price'] ?? 0,0)?></b><span class="pill hl" style="height:22px;font-size:11px">分销佣 <?=round((float)($p['distributor_rate'] ?? 30))?>%</span></div>
            <div><button type="button" class="btn ghost" style="height:34px;padding:0 12px;font-size:12.5px" onclick="copyDistLink('<?=htmlspecialchars($refCode)?>','<?=htmlspecialchars($p['id'])?>')">复制专属链接</button></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <script>
    function copyDistLink(ref, productId) {
      var url = '<?=htmlspecialchars($siteUrl)?>/marketplace?ref=' + ref + '&product=' + productId;
      navigator.clipboard.writeText(url).then(function(){ alert('已复制该产品的推广链接'); });
    }
    function submitWithdraw(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('wdMsg');
      var fd = new FormData();
      fd.append('action', 'withdraw');
      fd.append('amount', f.querySelector('[name=wd_amount]').value);
      fd.append('pay_method', f.querySelector('[name=wd_method]').value);
      fd.append('pay_account', f.querySelector('[name=wd_account]').value);
      msg.style.color='var(--muted)'; msg.textContent='提交中…';
      fetch('/api/ambassador.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1200); });
      return false;
    }
    </script>
    <?php
}

// ─── 密码重置（未登录）───
function include_member_reset_password(): void {
    $step = $_GET['step'] ?? 'request';
    ?>
    <div class="form-card panel">
      <?php if ($step === 'request'): ?>
      <div class="sec-head center" style="gap:8px"><span class="kicker">RESET</span><h2 style="font-size:24px">重置密码</h2><p class="lead" style="font-size:14px">输入你的邮箱或手机号，我们将发送验证码</p></div>
      <form onsubmit="return requestReset(event)" class="form-grid">
        <div class="field"><label>邮箱或手机号</label><input class="inp" type="text" name="account" required placeholder="you@example.com 或手机号"></div>
        <button type="submit" class="btn primary" style="width:100%">发送验证码</button>
        <div id="resetMsg"></div>
      </form>
      <?php elseif ($step === 'verify'): ?>
      <div class="sec-head center" style="gap:8px"><span class="kicker">VERIFY</span><h2 style="font-size:24px">验证身份</h2><p class="lead" style="font-size:14px">请输入收到的验证码</p></div>
      <form onsubmit="return verifyReset(event)" class="form-grid">
        <input type="hidden" name="token" value="<?=htmlspecialchars($_GET['token'] ?? '')?>">
        <div class="field"><label>验证码</label><input class="inp mono" type="text" name="code" required placeholder="6 位验证码" maxlength="6"></div>
        <button type="submit" class="btn primary" style="width:100%">验证</button>
        <div id="verifyMsg"></div>
      </form>
      <?php elseif ($step === 'newpassword'): ?>
      <div class="sec-head center" style="gap:8px"><span class="kicker">NEW PASSWORD</span><h2 style="font-size:24px">设置新密码</h2><p class="lead" style="font-size:14px">请输入你的新密码</p></div>
      <form onsubmit="return resetPassword(event)" class="form-grid">
        <input type="hidden" name="token" value="<?=htmlspecialchars($_GET['token'] ?? '')?>">
        <div class="field"><label>新密码</label><input class="inp" type="password" name="new_password" required placeholder="至少 8 位" minlength="8"></div>
        <div class="field"><label>确认新密码</label><input class="inp" type="password" name="confirm_password" required placeholder="再次输入新密码" minlength="8"></div>
        <button type="submit" class="btn primary" style="width:100%">重置密码</button>
        <div id="newPasswordMsg"></div>
      </form>
      <?php endif; ?>
      <p class="note" style="text-align:center;margin:0"><a href="member.php?view=login" style="color:var(--accent);font-weight:600">← 返回登录</a></p>
    </div>
    <?php
}
?>
<script>
function submitTeacher() {
  var f = document.getElementById('teacherForm');
  var fd = new FormData(); fd.append('action','apply_teacher');
  fd.append('intro', f.querySelector('textarea').value);
  fd.append('expertise', f.querySelector('input[name=expertise]').value);
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ location.href = '/member.php?view=teacher'; });
}
function submitArticle() {
  var f = document.getElementById('submitForm');
  var fd = new FormData(); fd.append('action','submit_article');
  fd.append('title', f.querySelector('input[name=title]').value);
  fd.append('category', f.querySelector('select[name=category]').value);
  fd.append('excerpt', f.querySelector('input[name=excerpt]').value);
  fd.append('content', f.querySelector('textarea').value);
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ document.getElementById('submitMsg').innerHTML = msgHtml(d.message||d.error, d.ok); });
}
// 个人资料更新
function updateProfile(e) {
  e.preventDefault();
  var f = e.target;
  var fd = new FormData(f); fd.append('action','update_profile');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ document.getElementById('profileMsg').innerHTML = msgHtml(d.message||'资料已更新', d.ok); });
  return false;
}
// 修改密码
function changePassword(e) {
  e.preventDefault();
  var f = e.target;
  var np = f.querySelector('input[name=new_password]').value;
  var cp = f.querySelector('input[name=confirm_password]').value;
  if (np !== cp) {
    document.getElementById('passwordMsg').innerHTML = msgHtml('两次输入的密码不一致', false);
    return false;
  }
  var fd = new FormData(f); fd.append('action','change_password');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      document.getElementById('passwordMsg').innerHTML = msgHtml(d.message||'密码已修改', d.ok);
      if (d.ok) f.reset();
    });
  return false;
}
// 请求重置密码
function requestReset(e) {
  e.preventDefault();
  var fd = new FormData(e.target); fd.append('action','request_reset');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('resetMsg');
      if (d.ok) {
        box.innerHTML = msgHtml('验证码已发送，请查收', true);
        setTimeout(function(){ location.href = '/member.php?view=reset-password&step=verify&token=' + d.token; }, 1500);
      } else {
        box.innerHTML = msgHtml(d.error, false);
      }
    });
  return false;
}
// 验证重置码
function verifyReset(e) {
  e.preventDefault();
  var fd = new FormData(e.target); fd.append('action','verify_reset');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('verifyMsg');
      if (d.ok) {
        box.innerHTML = msgHtml('验证成功', true);
        setTimeout(function(){ location.href = '/member.php?view=reset-password&step=newpassword&token=' + d.token; }, 1000);
      } else {
        box.innerHTML = msgHtml(d.error, false);
      }
    });
  return false;
}
// 重置密码
function resetPassword(e) {
  e.preventDefault();
  var f = e.target;
  var np = f.querySelector('input[name=new_password]').value;
  var cp = f.querySelector('input[name=confirm_password]').value;
  if (np !== cp) {
    document.getElementById('newPasswordMsg').innerHTML = msgHtml('两次输入的密码不一致', false);
    return false;
  }
  var fd = new FormData(f); fd.append('action','reset_password');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('newPasswordMsg');
      if (d.ok) {
        box.innerHTML = msgHtml('密码重置成功，正在跳转登录...', true);
        setTimeout(function(){ location.href = '/member.php?view=login'; }, 1500);
      } else {
        box.innerHTML = msgHtml(d.error, false);
      }
    });
  return false;
}
</script>
</body>
</html>
