/* OpenFlow 首页角色切换
 * - 首次访问：显示角色选择浮层（奇幻切换）
 * - 选择后：替换首页文字内容（不改变视觉）
 * - 记住偏好（localStorage）+ 埋点到 CDP
 */
(function(){
  if (typeof window.OF_ROLES === 'undefined') return;

  var STORE_KEY = 'of_role';
  var ROLE_ORDER = window.OF_ROLE_ORDER || ['beginner','power','dev','enterprise'];

  /* 图标：优先用全局暴露的 I/ic，退化为占位圆点 */
  function icon(n){
    try {
      if (window.OF_IC) return window.OF_IC(n);
      if (window.ic) return window.ic(n);
      if (window.OF_ICONS && window.OF_ICONS[n]) return '<span class="ic">' + window.OF_ICONS[n] + '</span>';
    } catch(e){}
    return '<span class="ic" style="width:20px;height:20px;border-radius:6px;background:rgba(120,120,120,.18);display:inline-block"></span>';
  }

  function getRole(){
    try { var r = localStorage.getItem(STORE_KEY); if (window.OF_ROLES[r]) return r; } catch(e){}
    return null;
  }
  function setRole(r){
    try { localStorage.setItem(STORE_KEY, r); } catch(e){}
    document.documentElement.setAttribute('data-role', r);
    // 同步到会员角色缓存
    try { localStorage.setItem('of_member_role', r); } catch(e){}
  }
  function trackRole(r, silent){
    try {
      if (window.CDP && CDP.track) CDP.track(silent ? 'role_inferred' : 'role_selected', { role: r, page: 'home' });
    } catch(e){}
  }

  /* ── 角色的推荐落地页（用于选择后自动跳转） ── */
  function roleLanding(r){
    try {
      var c = window.OF_ROLES[r];
      if (!c) return null;
      var qs = c.qs || [];
      if (!qs.length) return null;
      var href = qs[0].href;
      // 只跳站内路径；外部/锚点留在原页
      if (/^https?:\/\//.test(href)) return null;
      return href || null;
    } catch(e){ return null; }
  }

  /* ── 5s 未选择自动默认通用角色 ── */
  function autoDefaultRole(ov){
    if (!ov || !document.body.contains(ov)) return;
    var rk = window.OF_DEFAULT_ROLE || 'power';
    setRole(rk);
    try { if (window.CDP && CDP.track) CDP.track('role_auto_selected', { role: rk, timeout: 5, page: 'home' }); } catch(e){}
    applyRole(rk);
    // 显示已默认选择的提示
    var tip = document.createElement('div');
    tip.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:10000;background:#1e1e1e;color:#ddff0e;font-size:13px;font-weight:600;padding:10px 18px;border-radius:999px;box-shadow:0 6px 20px rgba(0,0,0,.25)';
    tip.textContent = '已为你默认选择「' + (window.OF_ROLES[rk] ? window.OF_ROLES[rk].label : rk) + '」 · 点右下角可切换';
    document.body.appendChild(tip);
    setTimeout(function(){ try { tip.remove(); } catch(e){} }, 3500);
    // 淡出浮层（不跳转）
    ov.style.transition = 'opacity .45s, transform .45s';
    ov.style.opacity = '0';
    ov.style.transform = 'scale(1.02)';
    setTimeout(function(){ if (document.body.contains(ov)) ov.remove(); }, 460);
  }

  /* ── 内容替换：只换文字，不动结构 ── */
  function applyRole(r){
    var c = window.OF_ROLES[r];
    if (!c) return;
    document.documentElement.setAttribute('data-role', r);

    // Hero
    var h1 = document.querySelector('#page-home .hero h1');
    if (h1) {
      // 保留 <i> 高亮结构，只改文字
      var txt1 = document.createTextNode(c.hero.title1 + ' ');
      h1.textContent = '';
      h1.appendChild(txt1);
      var i = document.createElement('i'); i.className = 'si'; i.textContent = c.hero.title2;
      h1.appendChild(i);
    }
    var kicker = document.querySelector('#page-home .hero .kicker');
    if (kicker) kicker.textContent = c.hero.kicker;
    var lead = document.querySelector('#page-home .hero .lead');
    if (lead) lead.textContent = c.hero.lead;
    var trust = document.querySelector('#page-home .hero .trust');
    if (trust && c.hero.trust) trust.innerHTML = '<span class="dot"></span>' + c.hero.trust;
    // Hero CTA
    var ctaRow = document.querySelector('#page-home .hero .cta-row');
    if (ctaRow) {
      var btns = ctaRow.querySelectorAll('.btn');
      var labels = [c.hero.cta1, c.hero.cta2, c.hero.cta3];
      // CTA1/CTA2 绑定到该角色的推荐落地页 qs[0]/qs[1]（仅站内路径）
      var hrefs = (c.qs || []).slice(0, 2).map(function(q){ return q && q.href; });
      btns.forEach(function(b, i){
        if (labels[i] === undefined) return;
        // 保留按钮内结构，只替换文本内容
        var tn = document.createTextNode(labels[i]);
        b.childNodes.forEach(function(n){ if (n.nodeType === 3) n.remove(); });
        b.insertBefore(tn, b.firstChild);
        if (hrefs[i] && b.tagName === 'A' && !/^https?:\/\//.test(hrefs[i])) b.setAttribute('href', hrefs[i]);
      });
    }

    // 快速开始标题
    var secHeads = document.querySelectorAll('#page-home .sec-head h2');
    if (secHeads[0]) secHeads[0].textContent = '为你准备的入口';
    if (secHeads[0]) secHeads[0].parentElement.querySelector('p') &&
      (secHeads[0].parentElement.querySelector('p').textContent = '根据你的角色，推荐最适合的开始路径。');

    // Quick Grid
    var grid = document.getElementById('qGrid');
    if (grid && c.qs) {
      grid.innerHTML = '';
      c.qs.forEach(function(q){
        var el = document.createElement('a');
        el.className = 'q-card';
        el.href = q.href;
        el.dataset.odId = 'quick-role-' + q.href.replace(/[^a-z0-9]/g,'');
        el.innerHTML = icon(q.icon) + '<div class="qt">' + q.t + '</div><div class="qd">' + q.d + '</div><div class="go">去看看 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></div>';
        grid.appendChild(el);
      });
    }

    // 三步
    var steps = document.querySelectorAll('#page-home .steps .step');
    if (steps.length >= 3 && c.steps) {
      steps.forEach(function(s, i){
        if (c.steps[i]) {
          var h = s.querySelector('h3'); if (h) h.textContent = c.steps[i].h;
          var p = s.querySelector('p'); if (p) p.textContent = c.steps[i].p;
        }
      });
    }

    // 底部 CTA band
    var ctaBand = document.querySelector('#page-home .band[data-od-id="home-cta-band"]');
    if (ctaBand && c.band) {
      var h2 = ctaBand.querySelector('h2'); if (h2) h2.textContent = c.band.title;
      var p = ctaBand.querySelector('p'); if (p) p.textContent = c.band.p;
      var btns = ctaBand.querySelectorAll('.btn');
      var blabels = [c.band.btn1, c.band.btn2, c.band.btn3];
      btns.forEach(function(b, i){
        if (blabels[i] === undefined) return;
        var tn = document.createTextNode(blabels[i]);
        b.childNodes.forEach(function(n){ if (n.nodeType === 3) n.remove(); });
        b.insertBefore(tn, b.firstChild);
      });
    }
  }

  /* ── 角色选择浮层（首次访问） ── */
  function showRolePicker(){
    if (document.getElementById('of-role-overlay')) return;
    var ov = document.createElement('div');
    ov.id = 'of-role-overlay';
    ov.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(20,20,24,.72);backdrop-filter:blur(14px);display:flex;align-items:center;justify-content:center;padding:24px;';
    var box = document.createElement('div');
    box.style.cssText = 'max-width:720px;width:100%;text-align:center;animation:ofRoleIn .6s cubic-bezier(.22,1,.36,1)';
    var style = document.createElement('style');
    style.textContent = '@keyframes ofRoleIn{from{opacity:0;transform:translateY(28px) scale(.96)}to{opacity:1;transform:none}}@keyframes ofRoleCard{from{opacity:0;transform:translateY(16px) scale(.92)}to{opacity:1;transform:none}}';
    document.head.appendChild(style);

    box.innerHTML =
      '<div style="color:#fff;font-size:13px;font-weight:600;letter-spacing:.12em;opacity:.7;margin-bottom:8px">WELCOME · 选择你的角色</div>' +
      '<div style="color:#fff;font-size:30px;font-weight:800;margin-bottom:6px">欢迎来到 OpenFlow</div>' +
      '<div style="color:rgba(255,255,255,.75);font-size:14px;margin-bottom:28px">选择最适合你的身份，我们为你推荐最合适的路径</div>' +
      '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">' +
        ROLE_ORDER.map(function(rk){
          var rc = window.OF_ROLES[rk];
          return '<button class="of-role-card" data-role="'+rk+'" style="animation:ofRoleCard .5s cubic-bezier(.22,1,.36,1);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:16px;padding:22px 14px;cursor:pointer;color:#fff;transition:all .2s">' +
            '<div style="font-size:32px;margin-bottom:8px">' + rc.emoji + '</div>' +
            '<div style="font-size:15px;font-weight:700;margin-bottom:4px">' + rc.label + '</div>' +
            '<div style="font-size:12px;color:rgba(255,255,255,.6);line-height:1.5">' + rc.desc + '</div></button>';
        }).join('') +
      '</div>' +
      '<button id="of-role-skip" style="margin-top:20px;background:none;border:none;color:rgba(255,255,255,.45);cursor:pointer;font-size:12.5px">暂不选择，先随便看看 →</button>';

    ov.appendChild(box);
    document.body.appendChild(ov);

    // 5s 未选择 → 自动默认通用角色
    var autoTimer = setTimeout(function(){ autoDefaultRole(ov); }, 5000);

    // 卡片 hover
    ov.querySelectorAll('.of-role-card').forEach(function(c){
      c.addEventListener('mouseenter', function(){ this.style.background='rgba(255,255,255,.16)'; this.style.borderColor='#ddff0e'; this.style.transform='translateY(-3px)'; });
      c.addEventListener('mouseleave', function(){ this.style.background='rgba(255,255,255,.08)'; this.style.borderColor='rgba(255,255,255,.18)'; this.style.transform='none'; });
      c.addEventListener('click', function(){
        clearTimeout(autoTimer);
        var rk = this.dataset.role;
        setRole(rk); trackRole(rk); applyRole(rk);
        syncRoleToMember(rk);
        var target = roleLanding(rk);
        // 奇幻切换：淡出浮层 + 内容上浮
        ov.style.transition = 'opacity .45s, transform .45s';
        ov.style.opacity = '0';
        ov.style.transform = 'scale(1.04)';
        // 选择后自动跳转到该角色推荐路径
        if (target) {
          setTimeout(function(){ window.location.href = target; }, 420);
        } else {
          setTimeout(function(){ ov.remove(); }, 460);
        }
        // 内容闪烁强调
        var main = document.getElementById('main');
        if (main) { main.style.transition='transform .45s'; main.style.transform='scale(.995)'; setTimeout(function(){ main.style.transform='none'; }, 460); }
      });
    });
    document.getElementById('of-role-skip').addEventListener('click', function(){
      clearTimeout(autoTimer);
      ov.style.transition='opacity .4s'; ov.style.opacity='0';
      setTimeout(function(){ ov.remove(); }, 400);
    });
  }

  /* ── 常驻角色切换（页脚小按钮） ── */
  function addRoleSwitcher(){
    if (document.getElementById('of-role-switch')) return;
    var btn = document.createElement('button');
    btn.id = 'of-role-switch';
    btn.title = '切换角色视角';
    btn.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:999;background:rgba(30,30,30,.85);color:#ddff0e;border:none;border-radius:999px;padding:8px 14px;font-size:12px;font-weight:600;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.18);backdrop-filter:blur(6px)';
    btn.textContent = '👤 切换视角';
    btn.addEventListener('click', function(){
      var cur = getRole() || 'beginner';
      var idx = ROLE_ORDER.indexOf(cur);
      var next = ROLE_ORDER[(idx + 1) % ROLE_ORDER.length];
      setRole(next); trackRole(next); applyRole(next); syncRoleToMember(next);
      var label = window.OF_ROLES[next].label;
      btn.textContent = '✓ ' + label + ' · 再点切换';
      setTimeout(function(){ btn.textContent = '👤 切换视角'; }, 1800);
      // 内容切换动效
      var main = document.getElementById('main');
      if (main) { main.style.transition='opacity .3s'; main.style.opacity='.5'; setTimeout(function(){ main.style.opacity='1'; }, 180); }
    });
    document.body.appendChild(btn);
  }

  /* ── 登录用户：同步角色到会员档案 ── */
  function syncRoleToMember(rk){
    try {
      if (typeof sess === 'undefined' || !sess) return; // 未登录不同步
      var fd = new FormData();
      fd.append('action', 'update_profile');
      fd.append('role', rk);
      fetch('/api/member.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch(e){}
  }

  /* ── 判断是否投放渠道来源（带 UTM 参数） ── */
  function isAdChannel(){
    try {
      var p = new URLSearchParams(window.location.search);
      return p.has('utm_source') || p.has('utm_medium') || p.has('utm_campaign') || p.has('gclid') || p.has('fbclid') || p.has('msclkid') || p.has('bd_vid');
    } catch(e){ return false; }
  }

  /* ── 从 UTM 渠道推断角色 ── */
  function inferRoleFromUtm(){
    try {
      var p = new URLSearchParams(window.location.search);
      var src = (p.get('utm_source') || '').toLowerCase();
      var camp = (p.get('utm_campaign') || '').toLowerCase();
      // 渠道预判
      if (src.indexOf('linkedin') !== -1 || src.indexOf('b2b') !== -1 || camp.indexOf('enterprise') !== -1) return 'enterprise';
      if (src.indexOf('github') !== -1 || src.indexOf('dev') !== -1 || src.indexOf('hacker') !== -1 || camp.indexOf('developer') !== -1) return 'dev';
      if (src.indexOf('zhihu') !== -1 || src.indexOf('xiaohongshu') !== -1 || src.indexOf('wechat') !== -1 || camp.indexOf('growth') !== -1 || camp.indexOf('marketing') !== -1) return 'power';
      return null;
    } catch(e){ return null; }
  }

  /* ── 判断登录用户是否已有角色 ── */
  function memberRole(){
    // 前端无法同步获取会员 role，从 localStorage 读取（登录时可能已缓存）
    try {
      var r = localStorage.getItem('of_member_role');
      if (r && window.OF_ROLES[r]) return r;
    } catch(e){}
    return null;
  }

  /* ── 初始化 ── */
  function init(){
    // 加载 CDP 追踪（若未加载）
    if (!window.CDP) {
      var s = document.createElement('script');
      s.src = '/assets/cdp-track.js';
      s.setAttribute('data-api', '/api/cdp.php');
      document.head.appendChild(s);
    }

    var isLoggedIn = (typeof sess !== 'undefined' && sess);
    var saved = getRole();

    // 规则 1：已登录用户 → 不弹选择，用其角色
    if (isLoggedIn) {
      var mRole = memberRole() || saved;
      if (mRole) { applyRole(mRole); document.documentElement.setAttribute('data-role', mRole); }
      // 不弹浮层，但提供切换按钮
      setTimeout(addRoleSwitcher, 600);
      return;
    }

    // 规则 2：投放渠道来的 → 不弹选择，用 UTM 预判角色
    if (isAdChannel()) {
      var utmRole = inferRoleFromUtm();
      var finalRole = utmRole || saved || 'power';
      setRole(finalRole); trackRole(finalRole, true); applyRole(finalRole);
      document.documentElement.setAttribute('data-role', finalRole);
      setTimeout(addRoleSwitcher, 800);
      return;
    }

    // 规则 3：普通新访客 → 弹角色选择
    if (saved) {
      applyRole(saved);
      document.documentElement.setAttribute('data-role', saved);
      setTimeout(addRoleSwitcher, 600);
    } else {
      setTimeout(showRolePicker, 900);
      setTimeout(addRoleSwitcher, 3000);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
