<?php
/**
 * 导航站收录申请页 — /navigation/submit
 * 后端 api/nav-submit.php 早已就绪（待审核 + 去重 + 后台通知），这是它的前台表单。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$categories = $nav['categories'] ?? [];
$siteName = site_config_get('site_name', 'OpenFlow');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>提交收录 · 增长导航 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="提交你正在用的增长工具，审核通过后收录进导航站。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260911a">
<style>
/* 收录申请页独有：表单卡。其余全部来自 modules.css。 */
.submit-wrap{max-width:640px;margin:0 auto}
.submit-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:clamp(22px,4vw,36px);backdrop-filter:blur(16px) saturate(150%);display:flex;flex-direction:column;gap:16px}
.submit-card label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
.submit-card label .hint{font-weight:400;color:var(--faint);font-size:12px}
.submit-card .inp,.submit-card select,.submit-card textarea{width:100%}
.submit-card textarea{min-height:96px;resize:vertical}
#submitMsg{font-size:13.5px;min-height:20px}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('navigation'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="nav-submit-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">提交收录</span>
      <h1>你正在用的好工具，<br><i class="si">值得被更多一人公司看到</i></h1>
      <p class="lead">收录标准：有真实用户、持续更新、定价透明。不接受付费置顶——推荐位由编辑评定。审核通过后上架展示。</p>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="nav-submit-form">
    <div class="submit-wrap">
      <form class="submit-card" id="submitForm">
        <div>
          <label for="fName">工具名称 *</label>
          <input class="inp" id="fName" name="name" required maxlength="60" placeholder="例如：Notion">
        </div>
        <div>
          <label for="fUrl">官网网址 *</label>
          <input class="inp" id="fUrl" name="url" type="url" required placeholder="https://…">
        </div>
        <div>
          <label for="fCat">建议分类 <span class="hint">· 编辑可能调整</span></label>
          <select id="fCat" name="category" class="inp">
            <option value="">— 不确定 —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars(($c['icon'] ?? '') . ' ' . $c['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="fDesc">一句话推荐 <span class="hint">· 它帮你解决了什么（200 字内）</span></label>
          <textarea class="inp" id="fDesc" name="description" maxlength="200" placeholder="例：用它的模板库把周报时间从 2 小时压到 10 分钟"></textarea>
        </div>
        <div>
          <label for="fContact">联系方式 <span class="hint">· 选填，审核有问题时联系</span></label>
          <input class="inp" id="fContact" name="contact" maxlength="60" placeholder="邮箱 / 微信">
        </div>
        <div id="submitMsg" role="status"></div>
        <button class="btn primary" type="submit" style="align-self:flex-start">提交收录申请 →</button>
      </form>
      <p class="note" style="text-align:center;margin-top:16px"><a href="/navigation" style="color:var(--accent)">← 返回导航站</a></p>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
document.getElementById('submitForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var msg = document.getElementById('submitMsg');
  var btn = this.querySelector('button[type=submit]');
  btn.disabled = true; msg.textContent = '提交中…'; msg.style.color = 'var(--muted)';
  fetch('/api/nav-submit', { method: 'POST', body: new FormData(this) })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      msg.textContent = d.ok ? ('✅ ' + (d.message || '提交成功')) : ('⚠️ ' + (d.error || '提交失败'));
      msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)';
      btn.disabled = false;
      if (d.ok) document.getElementById('submitForm').reset();
    })
    .catch(function () { msg.textContent = '⚠️ 网络错误，请稍后再试'; msg.style.color = 'var(--danger)'; btn.disabled = false; });
});
</script>
</body>
</html>
