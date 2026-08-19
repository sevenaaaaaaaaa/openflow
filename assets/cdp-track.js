/**
 * OpenFlow CDP 统一追踪 SDK v2.0
 * 对标 GA4 / 神策 / 热云 / 百度统计
 *
 * 使用：
 *   <script src="/assets/cdp-track.js" data-api="/api/cdp.php" data-autotrack="1"></script>
 *
 * 手动追踪：
 *   CDP.track('event_name', { key: 'value' });
 *   CDP.identify({ email, name, phone });
 *   CDP.setUserProperties({ level: 'vip' });
 *   CDP.pageView({ custom: 1 });
 *
 * 自动采集：页面浏览 / 滚动 / 点击 / 表单 / 站外点击 / 停留时长 / JS错误 / 搜索
 * 公共属性：设备环境 / 渠道归因 / 会话 / 来源
 * 批量上报：5条或5秒合并为一次请求
 */
(function() {
  if (window.CDP && window.CDP.loaded) return;

  var config = {
    api: '/api/cdp.php',
    autoTrack: true,
    trackClicks: true,
    trackForms: true,
    trackScroll: true,
    trackOutbound: true,
    trackErrors: true,
    trackSearch: true,
    trackTimeOnPage: true,
    batchSize: 5,
    batchInterval: 5000,
    sessionTimeout: 1800000, // 30 分钟
    privacy: false,
  };

  // 从 script 标签读取配置
  var scriptTag = document.querySelector('script[data-api]');
  if (scriptTag) {
    config.api = scriptTag.getAttribute('data-api') || config.api;
    if (scriptTag.getAttribute('data-autotrack') === '0') config.autoTrack = false;
    if (scriptTag.getAttribute('data-privacy') === 'none') config.privacy = true;
    if (scriptTag.getAttribute('data-batch-size')) config.batchSize = parseInt(scriptTag.getAttribute('data-batch-size'), 10);
  }

  // 尊重 Do Not Track
  try { if (navigator.doNotTrack === '1' || navigator.doNotTrack === true) config.privacy = true; } catch(e){}

  // 访客 ID（持久化）
  var visitorId = localStorage.getItem('cdp_vid');
  if (!visitorId) {
    visitorId = 'vid_' + Math.random().toString(36).substr(2, 16) + Date.now().toString(36);
    localStorage.setItem('cdp_vid', visitorId);
  }

  // ─── 设备/环境检测（对标神策公共属性） ───
  function detectEnv() {
    var ua = navigator.userAgent;
    var env = { ua: ua, language: navigator.language || '', screen_width: screen.width, screen_height: screen.height };
    // 操作系统
    if (ua.indexOf('Windows') !== -1) env.os = 'Windows';
    else if (ua.indexOf('Mac OS X') !== -1 || ua.indexOf('Macintosh') !== -1) env.os = 'macOS';
    else if (ua.indexOf('Android') !== -1) env.os = 'Android';
    else if (ua.indexOf('iPhone') !== -1 || ua.indexOf('iPad') !== -1) env.os = 'iOS';
    else if (ua.indexOf('Linux') !== -1) env.os = 'Linux';
    else env.os = 'Unknown';
    // 浏览器
    if (ua.indexOf('Edg/') !== -1) env.browser = 'Edge';
    else if (ua.indexOf('Chrome') !== -1) env.browser = 'Chrome';
    else if (ua.indexOf('Safari') !== -1) env.browser = 'Safari';
    else if (ua.indexOf('Firefox') !== -1) env.browser = 'Firefox';
    else if (ua.indexOf('MSIE') !== -1 || ua.indexOf('Trident') !== -1) env.browser = 'IE';
    else env.browser = 'Unknown';
    // 设备类型
    env.device = /Mobi|Android|iPhone|iPad|iPod/.test(ua) ? 'Mobile' : 'Desktop';
    // 版本（粗略）
    var m = ua.match(/(Chrome|Safari|Firefox|Edg)\/([\d.]+)/);
    if (m) env.browser_version = m[2];
    return env;
  }

  // ─── 渠道归因（对标 GA4 + 热云） ───
  function getChannel() {
    var qs = new URLSearchParams(window.location.search);
    var utm = {};
    ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','fbclid','msclkid','bd_vid'].forEach(function(k){
      var v = qs.get(k); if (v) utm[k] = v;
    });
    // 归一化渠道
    var ch = '直接访问';
    if (utm.utm_source) {
      var s = utm.utm_source.toLowerCase();
      if (s.indexOf('google') !== -1 || s.indexOf('bing') !== -1 || s.indexOf('baidu') !== -1) ch = '搜索引擎';
      else if (s.indexOf('wechat') !== -1 || s.indexOf('weibo') !== -1 || s.indexOf('zhihu') !== -1 || s.indexOf('xiaohongshu') !== -1 || s.indexOf('douyin') !== -1 || s.indexOf('bilibili') !== -1) ch = '社媒';
      else if (s.indexOf('linkedin') !== -1 || s.indexOf('fb') !== -1 || s.indexOf('facebook') !== -1 || s.indexOf('twitter') !== -1 || s.indexOf('x') !== -1) ch = '海外社媒';
      else if (s.indexOf('email') !== -1 || s.indexOf('newsletter') !== -1) ch = '邮件';
      else ch = '广告渠道';
    } else if (utm.gclid || utm.fbclid || utm.msclkid) {
      ch = '广告渠道';
    } else if (document.referrer) {
      try {
        var refHost = new URL(document.referrer).hostname;
        if (refHost && refHost !== location.hostname) {
          if (refHost.indexOf('google.') !== -1 || refHost.indexOf('baidu.com') !== -1 || refHost.indexOf('bing.com') !== -1) ch = '搜索引擎';
          else if (refHost.indexOf('weixin.qq.com') !== -1 || refHost.indexOf('zhihu.com') !== -1 || refHost.indexOf('xiaohongshu') !== -1) ch = '社媒';
          else ch = '外链';
        }
      } catch(e){}
    }
    utm.channel = ch;
    utm.is_ad_channel = (ch === '广告渠道') ? 1 : 0;
    return utm;
  }

  // ─── 会话（对标 GA4 Session，30分钟） ───
  function getSession() {
    var now = Date.now();
    var sid = localStorage.getItem('cdp_sid');
    var sStart = parseInt(localStorage.getItem('cdp_sstart') || '0', 10);
    var visitCount = parseInt(localStorage.getItem('cdp_vcount') || '0', 10);
    if (!sid || (now - sStart) > config.sessionTimeout) {
      sid = 'ses_' + Math.random().toString(36).substr(2, 12) + Date.now().toString(36);
      sStart = now; visitCount = 0;
      localStorage.setItem('cdp_sid', sid);
      localStorage.setItem('cdp_sstart', String(sStart));
    }
    visitCount++;
    localStorage.setItem('cdp_vcount', String(visitCount));
    return { session_id: sid, is_new_session: (visitCount === 1) ? 1 : 0, session_visit_count: visitCount };
  }

  // ─── 批量上报队列 ───
  var batchQueue = [];
  var batchTimer = null;
  function flushBatch() {
    if (!batchQueue.length) return;
    var events = batchQueue.slice();
    batchQueue = [];
    var fd = new FormData();
    fd.append('action', 'track_batch');
    fd.append('events', JSON.stringify(events));
    fd.append('visitor_id', visitorId);
    try {
      navigator.sendBeacon ? navigator.sendBeacon(config.api, fd) :
        fetch(config.api, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true });
    } catch(e){}
  }
  function queue(event, data) {
    if (config.privacy) return;
    var env = detectEnv();
    var utm = getChannel();
    var sess = getSession();
    // 合并公共属性 + 事件属性
    var props = Object.assign({}, env, utm, sess, {
      referrer: document.referrer,
      referrer_domain: (function(){ try { return document.referrer ? new URL(document.referrer).hostname : ''; } catch(e){ return ''; } })(),
      url_path: location.pathname,
      url_query: location.search,
      landing_page: location.pathname,
      timestamp: new Date().toISOString(),
    }, data || {});
    batchQueue.push({ event: event, properties: props, visitor_id: visitorId });
    if (batchQueue.length >= config.batchSize) flushBatch();
    if (!batchTimer) {
      batchTimer = setTimeout(function(){ batchTimer = null; flushBatch(); }, config.batchInterval);
    }
  }

  // ─── 核心追踪对象 ───
  var CDP = {
    loaded: true,
    visitorId: visitorId,

    track: function(event, data) { queue(event, data); },

    identify: function(properties) { this.track('identify', properties); },

    setUserProperties: function(props) { this.track('$user_update', props); },

    pageView: function(data) {
      this.track('page_view', Object.assign({
        title: document.title,
        path: location.pathname,
      }, data || {}));
    },

    click: function(el, data, ev) {
      var d = {
        tag: el.tagName,
        text: (el.textContent || '').trim().substr(0, 100),
        href: el.href || '',
        selector: (function(){
          var s = el.id ? '#' + el.id : (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ').slice(0,2).join('.') : el.tagName.toLowerCase());
          return s;
        })(),
        is_outbound: (el.href && el.hostname !== location.hostname) ? 1 : 0,
      };
      // 热力图坐标（相对文档）
      if (ev) {
        d.x = Math.round((ev.pageX || ev.clientX) + (window.pageXOffset || 0));
        d.y = Math.round((ev.pageY || ev.clientY) + (window.pageYOffset || 0));
        d.vw = window.innerWidth; d.vh = window.innerHeight;
        d.dh = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
      }
      this.track('element_click', Object.assign(d, data || {}));
    },

    formSubmit: function(form, data) {
      var formData = {};
      var inputs = form.querySelectorAll('input, textarea, select');
      for (var i = 0; i < inputs.length; i++) {
        var name = inputs[i].name || inputs[i].id;
        if (name && inputs[i].value && !/password/i.test(inputs[i].type || '')) {
          formData[name] = String(inputs[i].value).substr(0, 200);
        }
      }
      this.track('form_submit', Object.assign({
        form_id: form.id || '',
        form_name: form.name || form.getAttribute('data-form-name') || '',
        fields: formData,
      }, data || {}));
    },

    scrollDepth: function(percent) { this.track('scroll_depth', { percent: percent }); },

    // 预置事件（业务模板）
    articleView: function(data) { this.track('article_view', data); },
    articleShare: function(data) { this.track('article_share', data); },
    courseView: function(data) { this.track('course_view', data); },
    courseEnroll: function(data) { this.track('course_enroll', data); },
    purchase: function(data) { this.track('purchase', data); },
    leadCreated: function(data) { this.track('lead_created', data); },
    toolUse: function(data) { this.track('tool_use', data); },
    roleSelect: function(role, page) { this.track('role_selected', { role: role, page: page || 'home' }); },
  };
  window.CDP = CDP;

  // ─── 自动采集 ───
  if (config.autoTrack) {
    // 页面浏览（含 SPA 简单支持）
    CDP.pageView();

    // 点击
    if (config.trackClicks) {
      document.addEventListener('click', function(e) {
        var el = e.target.closest('a, button, [data-track-click]');
        if (el) CDP.click(el, null, e);
      });
    }

    // 表单
    if (config.trackForms) {
      document.addEventListener('submit', function(e) {
        if (e.target.tagName === 'FORM') CDP.formSubmit(e.target);
      });
    }

    // 滚动深度
    if (config.trackScroll) {
      var maxScroll = 0;
      var thresholds = [25, 50, 75, 100];
      window.addEventListener('scroll', function() {
        var pct = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
        if (pct > maxScroll) {
          maxScroll = pct;
          thresholds.forEach(function(t){
            if (pct >= t) { CDP.scrollDepth(t); thresholds = thresholds.filter(function(x){ return x !== t; }); }
          });
        }
      });
    }

    // 站外点击（outbound）
    if (config.trackOutbound) {
      document.addEventListener('click', function(e) {
        var a = e.target.closest('a[href]');
        if (a && a.hostname && a.hostname !== location.hostname) {
          CDP.track('outbound_click', { href: a.href, domain: a.hostname, text: (a.textContent || '').substr(0, 60) });
        }
      });
    }

    // JS 错误
    if (config.trackErrors) {
      window.addEventListener('error', function(e) {
        CDP.track('js_error', { message: String(e.message || '').substr(0, 200), source: e.filename || '', lineno: e.lineno || 0 });
      });
    }

    // 站内搜索
    if (config.trackSearch) {
      var isSearchPage = /search|s=/.test(location.pathname + location.search);
      if (isSearchPage) {
        var q = new URLSearchParams(location.search).get('q') || '';
        if (q) CDP.track('site_search', { keyword: q, path: location.pathname });
      }
    }

    // 停留时长（beacon 上报）
    if (config.trackTimeOnPage) {
      var startTime = Date.now();
      window.addEventListener('pagehide', function() {
        var dur = Math.round((Date.now() - startTime) / 1000);
        if (dur >= 3) queue('time_on_page', { duration_sec: dur, path: location.pathname });
        flushBatch();
      });
      // 30s 心跳（活跃用户）
      setInterval(function(){ queue('heartbeat', { duration_sec: Math.round((Date.now() - startTime) / 1000) }); }, 30000);
    }
  }

  // 页面卸载时冲刷队列
  window.addEventListener('pagehide', flushBatch);
  window.addEventListener('beforeunload', flushBatch);
})();
