/*! role-switch.js · bundled from src/ by esbuild — 请勿手改；改 src/ 后运行 npm run build */
"use strict";
(() => {
  // src/roles/content.ts
  var ROLE_ORDER = ["beginner", "power", "dev", "enterprise"];
  var ROLES = {
    beginner: {
      key: "beginner",
      label: "我是新手",
      emoji: "🌱",
      desc: "刚开始做一人公司/内容，想快速上手",
      hero: {
        kicker: "芭乐派 · 帮一人公司设计 Agent 能跑的增长系统",
        title1: "不操作你的系统，",
        title2: "设计你的系统",
        lead: "你不缺「怎么做」的工具，你缺「该做什么」的系统。OpenFlow 自动爬取行业信号、生成内容草稿、主动触达转化——让 Agent 跑流程，你只做判断。",
        cta1: "免费开始",
        cta2: "看看怎么用",
        cta3: "免费诊断",
        trust: "一人公司首选 · 核心能力永久开源 · 装完即用"
      },
      qs: [
        { href: "/tools", t: "增长工具箱", d: "SEO检查、文案生成等免费工具", icon: "bolt" },
        { href: "/courses", t: "新手课程", d: "New-1 开始学增长系统", icon: "book" },
        { href: "/docs", t: "使用指南", d: "一步步带你上手", icon: "doc" },
        { href: "/academy", t: "入门文章", d: "看得懂的增长知识", icon: "doc" },
        { href: "/community", t: "增长社区", d: "有人帮你答疑", icon: "users" },
        { href: "#contact", t: "免费诊断", d: "顾问帮你看看增长瓶颈", icon: "info" }
      ],
      steps: [
        { h: "连接增长信号", p: "接入舆情、搜索热点、CDP 事件——OpenFlow 替你盯住市场变化。" },
        { h: "设计你的系统", p: "把增长漏斗拆成 Agent 可执行的任务图，四引擎协同推进。" },
        { h: "主动驱动增长", p: "自生长引擎按你设的周期推一轮，从 Marketing 到 Sales 全闭环。" }
      ],
      band: {
        kicker: "新手友好",
        title: "不用从零学起，跟着 New-1 做就行",
        p: "免费课稿一步步带你搭，照着做就能让增长系统开始转起来。",
        btn1: "开始学习",
        btn2: "看看教程"
      }
    },
    dev: {
      key: "dev",
      label: "资深开发者",
      emoji: "💻",
      desc: "懂技术，要 API、可扩展、可部署",
      hero: {
        kicker: "芭乐派 · OpenFlow 开放平台",
        title1: "为开发者打造的",
        title2: "Agent 增长基础设施",
        lead: "开放 API、Webhook、Task Graph 编排、数据连接器——把增长系统嵌进你的技术栈。自托管、私有化、完全可控，核心能力永久开源。",
        cta1: "查看 API 文档",
        cta2: "开发者中心",
        cta3: "部署文档",
        trust: "开放 API · 永久开源 · 自托管 · 鱼与渔结合"
      },
      qs: [
        { href: "/docs#api", t: "API 文档", d: "REST API · Webhook · 鉴权", icon: "doc" },
        { href: "/marketplace", t: "插件市场", d: "Skill/插件/主题 生态", icon: "box" },
        { href: "/docs", t: "开发者文档", d: "架构、扩展点、部署", icon: "book" },
        { href: "/docs", t: "路线图", d: "平台演进规划", icon: "bolt" },
        { href: "/community", t: "开发者社区", d: "提问、贡献、讨论", icon: "users" },
        { href: "https://github.com/sevenaaaaaaaaa/openflow", t: "GitHub", d: "开源仓库 · Star", icon: "info" }
      ],
      steps: [
        { h: "接入数据", p: "前端埋点、服务端事件、Webhook 回调，多通道接入你的数据。" },
        { h: "调用能力", p: "内容、CDP、自动化、SEO 全能力开放为 REST API。" },
        { h: "扩展与部署", p: "插件系统扩展能力，自托管或容器化部署完全可控。" }
      ],
      band: {
        kicker: "开发者优先",
        title: "API 优先，一切皆可编程",
        p: "从数据接入到能力调用再到部署，全链路开放，任你组合。",
        btn1: "查看文档",
        btn2: "获取 API Key"
      }
    },
    power: {
      key: "power",
      label: "增长/运营达人",
      emoji: "🚀",
      desc: "要效率、要自动化、要数据驱动",
      hero: {
        kicker: "芭乐派 · 帮一人公司设计 Agent 能跑的增长系统",
        title1: "让增长动作",
        title2: "主动发生",
        lead: "自生长 AI Engine 按你设的周期推一轮：爬取信号 → AI 洞察 → 生成草稿 → 主动触达。从 Marketing 到 Sales 全闭环，把增长交给系统，你专注策略判断。",
        cta1: "免费开始",
        cta2: "查看平台演示",
        cta3: "预约诊断",
        trust: "主动驱动 · 数据洞察 · 自生长引擎"
      },
      qs: [
        { href: "/tools", t: "增长工具箱", d: "SEO/文案/LTV 等专业工具", icon: "bolt" },
        { href: "/marketplace", t: "Skill 市场", d: "开箱即用的增长技能", icon: "box" },
        { href: "/courses", t: "R.B.E 训练营", d: "New-1~4 + 八周系统设计", icon: "book" },
        { href: "/academy", t: "实践文章", d: "增长案例与技巧", icon: "doc" },
        { href: "/community", t: "增长社区", d: "分享与交流", icon: "users" },
        { href: "#contact", t: "增长诊断", d: "深度评估你的增长体系", icon: "info" }
      ],
      steps: [
        { h: "连接增长信号", p: "内容、SEO、CDP、自动化、微信生态，一个平台全打通。" },
        { h: "设计你的系统", p: "把增长漏斗拆成 Agent 可执行的任务图，四引擎协同。" },
        { h: "主动驱动增长", p: "AI 洞察发现机会，A/B 测试验证，持续迭代。" }
      ],
      band: {
        kicker: "为增长而生",
        title: "从内容到转化，全链路 Agent 化",
        p: "你的增长动作不再是孤立的点，而是一条自动运转的流水线。",
        btn1: "开始使用",
        btn2: "查看能力"
      }
    },
    enterprise: {
      key: "enterprise",
      label: "企业用户",
      emoji: "🏢",
      desc: "要方案、要安全、要落地服务",
      hero: {
        kicker: "芭乐派 · 企业级增长系统",
        title1: "企业的增长",
        title2: "由引擎驱动",
        lead: "从内容到客户数据再到转化变现，一套打通的 Agent 原生增长系统。私有化部署、数据自主可控、专业顾问陪跑落地。",
        cta1: "预约企业演示",
        cta2: "解决方案",
        cta3: "联系顾问",
        trust: "私有化部署 · 数据自主 · 企业级服务"
      },
      qs: [
        { href: "#contact", t: "预约演示", d: "30分钟了解企业方案", icon: "info" },
        { href: "/about", t: "关于我们", d: "团队与使命", icon: "info" },
        { href: "/docs", t: "能力清单", d: "全部功能模块", icon: "doc" },
        { href: "/community", t: "客户社区", d: "案例与讨论", icon: "users" },
        { href: "/courses", t: "团队培训", d: "员工成长体系", icon: "book" },
        { href: "#contact", t: "咨询落地", d: "诊断→方案→实施", icon: "bolt" }
      ],
      steps: [
        { h: "诊断现状", p: "专业顾问评估你网站的内容、获客、转化现状与机会。" },
        { h: "定制方案", p: "基于你的业务场景，规划内容体系、数据中台与自动化流程。" },
        { h: "落地陪跑", p: "从部署到运营，顾问全程陪跑，确保方案真正生效。" }
      ],
      band: {
        kicker: "企业级",
        title: "从诊断到落地，我们陪你把增长做起来",
        p: "私有化部署、数据安全、专业服务团队，为企业增长保驾护航。",
        btn1: "预约演示",
        btn2: "联系顾问"
      }
    }
  };
  if (typeof window !== "undefined") {
    window.OF_ROLES = ROLES;
    window.OF_ROLE_ORDER = ROLE_ORDER;
  }

  // src/lib/roles.ts
  function isInternalHref(href) {
    return typeof href === "string" && href.length > 0 && !/^https?:\/\//i.test(href);
  }
  function resolveRole(stored, roles) {
    if (typeof stored !== "string" || !stored) return null;
    if (!roles || !Object.prototype.hasOwnProperty.call(roles, stored)) return null;
    return stored;
  }
  function readStoredRole(storage, key, roles) {
    try {
      return resolveRole(storage == null ? void 0 : storage.getItem(key), roles);
    } catch (e) {
      return null;
    }
  }
  function writeStored(storage, key, value) {
    try {
      storage == null ? void 0 : storage.setItem(key, value);
      return true;
    } catch (e) {
      return false;
    }
  }
  function roleLanding(content) {
    var _a, _b;
    const first = (_b = (_a = content == null ? void 0 : content.qs) == null ? void 0 : _a[0]) == null ? void 0 : _b.href;
    return isInternalHref(first) ? first : null;
  }
  function ctaBindings(content) {
    var _a, _b, _c, _d, _e, _f;
    const qs = (_a = content == null ? void 0 : content.qs) != null ? _a : [];
    const internal = qs.map((q) => isInternalHref(q == null ? void 0 : q.href) ? q.href : null).slice(0, 2);
    return {
      labels: [(_b = content == null ? void 0 : content.hero) == null ? void 0 : _b.cta1, (_c = content == null ? void 0 : content.hero) == null ? void 0 : _c.cta2, (_d = content == null ? void 0 : content.hero) == null ? void 0 : _d.cta3],
      hrefs: [(_e = internal[0]) != null ? _e : null, (_f = internal[1]) != null ? _f : null]
    };
  }
  function bandLabels(content) {
    var _a, _b, _c;
    return [(_a = content == null ? void 0 : content.band) == null ? void 0 : _a.btn1, (_b = content == null ? void 0 : content.band) == null ? void 0 : _b.btn2, (_c = content == null ? void 0 : content.band) == null ? void 0 : _c.btn3];
  }
  function nextRole(order, current) {
    var _a, _b;
    if (!order.length) return "";
    const idx = current ? order.indexOf(current) : -1;
    return (_b = (_a = order[(idx + 1) % order.length]) != null ? _a : order[0]) != null ? _b : "";
  }
  function quickCardId(href) {
    return "quick-role-" + String(href != null ? href : "").replace(/[^a-z0-9]/gi, "");
  }
  function shouldShowPicker(opts) {
    if (opts.disabled) return false;
    if (opts.storedRole) return false;
    if (opts.hasOverlay) return false;
    if (opts.dialogOpen) return false;
    return true;
  }
  function defaultRole(candidate, order) {
    var _a;
    if (typeof candidate === "string" && order.includes(candidate)) return candidate;
    return (_a = order[0]) != null ? _a : "power";
  }
  var AD_PARAMS = ["utm_source", "utm_medium", "utm_campaign", "gclid", "fbclid", "msclkid", "bd_vid"];
  function parseQuery(search) {
    const out = {};
    try {
      new URLSearchParams(search || "").forEach((v, k) => {
        out[k] = v;
      });
    } catch (e) {
    }
    return out;
  }
  function isAdChannel(q) {
    return AD_PARAMS.some((k) => typeof q[k] === "string" && q[k] !== "");
  }
  function pick(role, order) {
    return order.includes(role) ? role : null;
  }
  function inferRoleFromUtm(q, order) {
    var _a, _b;
    const src = ((_a = q["utm_source"]) != null ? _a : "").toLowerCase();
    const camp = ((_b = q["utm_campaign"]) != null ? _b : "").toLowerCase();
    const has = (hay, needles) => needles.some((n) => hay.includes(n));
    if (has(src, ["linkedin", "b2b"]) || has(camp, ["enterprise"])) return pick("enterprise", order);
    if (has(src, ["github", "dev", "hacker"]) || has(camp, ["developer"])) return pick("dev", order);
    if (has(src, ["zhihu", "xiaohongshu", "wechat"]) || has(camp, ["growth", "marketing"])) return pick("power", order);
    return null;
  }

  // src/roles/switch.js
  (function() {
    var ROLES_DATA = typeof window !== "undefined" && window.OF_ROLES || ROLES;
    if (!ROLES_DATA) return;
    var STORE_KEY = "of_role";
    var ORDER = typeof window !== "undefined" && window.OF_ROLE_ORDER && window.OF_ROLE_ORDER.length ? window.OF_ROLE_ORDER : ROLE_ORDER;
    function icon(n) {
      try {
        if (window.OF_IC) return window.OF_IC(n);
        if (window.ic) return window.ic(n);
        if (window.OF_ICONS && window.OF_ICONS[n]) return '<span class="ic">' + window.OF_ICONS[n] + "</span>";
      } catch (e) {
      }
      return '<span class="ic" style="width:20px;height:20px;border-radius:6px;background:rgba(120,120,120,.18);display:inline-block"></span>';
    }
    function getRole() {
      try {
        return readStoredRole(localStorage, STORE_KEY, ROLES_DATA);
      } catch (e) {
        return null;
      }
    }
    function setRole(r) {
      try {
        writeStored(localStorage, STORE_KEY, r);
      } catch (e) {
      }
      document.documentElement.setAttribute("data-role", r);
      try {
        writeStored(localStorage, "of_member_role", r);
      } catch (e) {
      }
    }
    function trackRole(r, silent) {
      try {
        if (window.CDP && CDP.track) CDP.track(silent ? "role_inferred" : "role_selected", { role: r, page: "home" });
      } catch (e) {
      }
    }
    function roleLanding2(r) {
      try {
        return roleLanding(ROLES_DATA[r]);
      } catch (e) {
        return null;
      }
    }
    function dismissPicker(ov) {
      if (!ov) return;
      if (ov.__ofTimer) clearTimeout(ov.__ofTimer);
      if (ov.__ofYield) clearInterval(ov.__ofYield);
      if (window.OFDialog) window.OFDialog.lock(false);
      ov.classList.remove("open");
      ov.style.transition = "opacity .45s, transform .45s";
      ov.style.opacity = "0";
      ov.style.transform = "scale(1.02)";
      setTimeout(function() {
        if (document.body.contains(ov)) ov.remove();
      }, 460);
    }
    function autoDefaultRole(ov) {
      if (!ov || !document.body.contains(ov)) return;
      var rk = defaultRole(window.OF_DEFAULT_ROLE, ORDER);
      setRole(rk);
      try {
        if (window.CDP && CDP.track) CDP.track("role_auto_selected", { role: rk, timeout: 5, page: "home" });
      } catch (e) {
      }
      applyRole(rk);
      var tip = document.createElement("div");
      tip.style.cssText = "position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:10000;background:#1e1e1e;color:#ddff0e;font-size:13px;font-weight:600;padding:10px 18px;border-radius:999px;box-shadow:0 6px 20px rgba(0,0,0,.25)";
      tip.textContent = "已为你默认选择「" + (ROLES_DATA[rk] ? ROLES_DATA[rk].label : rk) + "」 · 点右下角可切换";
      document.body.appendChild(tip);
      setTimeout(function() {
        try {
          tip.remove();
        } catch (e) {
        }
      }, 3500);
      dismissPicker(ov);
    }
    function applyRole(r) {
      var c = ROLES_DATA[r];
      if (!c) return;
      document.documentElement.setAttribute("data-role", r);
      var h1 = document.querySelector("#page-home .hero h1");
      if (h1) {
        var txt1 = document.createTextNode(c.hero.title1 + " ");
        h1.textContent = "";
        h1.appendChild(txt1);
        var i = document.createElement("i");
        i.className = "si";
        i.textContent = c.hero.title2;
        h1.appendChild(i);
      }
      var kicker = document.querySelector("#page-home .hero .kicker");
      if (kicker) kicker.textContent = c.hero.kicker;
      var lead = document.querySelector("#page-home .hero .lead");
      if (lead) lead.textContent = c.hero.lead;
      var trust = document.querySelector("#page-home .hero .trust");
      if (trust && c.hero.trust) trust.innerHTML = '<span class="dot"></span>' + c.hero.trust;
      var ctaRow = document.querySelector("#page-home .hero .cta-row");
      if (ctaRow) {
        var btns = ctaRow.querySelectorAll(".btn");
        var bind = ctaBindings(c);
        var labels = bind.labels;
        var hrefs = bind.hrefs;
        btns.forEach(function(b, i2) {
          if (labels[i2] === void 0) return;
          var tn = document.createTextNode(labels[i2]);
          b.childNodes.forEach(function(n) {
            if (n.nodeType === 3) n.remove();
          });
          b.insertBefore(tn, b.firstChild);
          if (hrefs[i2] && b.tagName === "A") b.setAttribute("href", hrefs[i2]);
        });
      }
      var secHeads = document.querySelectorAll("#page-home .sec-head h2");
      if (secHeads[0]) secHeads[0].textContent = "为你准备的入口";
      if (secHeads[0]) secHeads[0].parentElement.querySelector("p") && (secHeads[0].parentElement.querySelector("p").textContent = "根据你的角色，推荐最适合的开始路径。");
      var grid = document.getElementById("qGrid");
      if (grid && c.qs) {
        grid.innerHTML = "";
        c.qs.forEach(function(q) {
          var el = document.createElement("a");
          el.className = "q-card";
          el.href = q.href;
          el.dataset.odId = quickCardId(q.href);
          el.innerHTML = icon(q.icon) + '<div class="qt">' + q.t + '</div><div class="qd">' + q.d + '</div><div class="go">去看看 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></div>';
          grid.appendChild(el);
        });
      }
      var steps = document.querySelectorAll("#page-home .steps .step");
      if (steps.length >= 3 && c.steps) {
        steps.forEach(function(s, i2) {
          if (c.steps[i2]) {
            var h = s.querySelector("h3");
            if (h) h.textContent = c.steps[i2].h;
            var p2 = s.querySelector("p");
            if (p2) p2.textContent = c.steps[i2].p;
          }
        });
      }
      var ctaBand = document.querySelector('#page-home .band[data-od-id="home-cta-band"]');
      if (ctaBand && c.band) {
        var h2 = ctaBand.querySelector("h2");
        if (h2) h2.textContent = c.band.title;
        var p = ctaBand.querySelector("p");
        if (p) p.textContent = c.band.p;
        var btns = ctaBand.querySelectorAll(".btn");
        var blabels = bandLabels(c);
        btns.forEach(function(b, i2) {
          if (blabels[i2] === void 0) return;
          var tn = document.createTextNode(blabels[i2]);
          b.childNodes.forEach(function(n) {
            if (n.nodeType === 3) n.remove();
          });
          b.insertBefore(tn, b.firstChild);
        });
      }
    }
    function showRolePicker() {
      if (!shouldShowPicker({
        storedRole: getRole(),
        hasOverlay: !!document.getElementById("of-role-overlay"),
        dialogOpen: !!document.querySelector("[data-of-dialog].open")
      })) return;
      var ov = document.createElement("div");
      ov.id = "of-role-overlay";
      ov.style.cssText = "position:fixed;inset:0;z-index:91;background:rgba(20,20,24,.72);backdrop-filter:blur(14px);display:flex;align-items:center;justify-content:center;padding:24px;";
      var box = document.createElement("div");
      box.style.cssText = "max-width:720px;width:100%;text-align:center;animation:ofRoleIn .6s cubic-bezier(.22,1,.36,1)";
      var style = document.createElement("style");
      style.textContent = "@keyframes ofRoleIn{from{opacity:0;transform:translateY(28px) scale(.96)}to{opacity:1;transform:none}}@keyframes ofRoleCard{from{opacity:0;transform:translateY(16px) scale(.92)}to{opacity:1;transform:none}}";
      document.head.appendChild(style);
      box.innerHTML = '<div style="color:#fff;font-size:13px;font-weight:600;letter-spacing:.12em;opacity:.7;margin-bottom:8px">WELCOME · 选择你的角色</div><div style="color:#fff;font-size:30px;font-weight:800;margin-bottom:6px">欢迎来到 OpenFlow</div><div style="color:rgba(255,255,255,.75);font-size:14px;margin-bottom:28px">选择最适合你的身份，我们为你推荐最合适的路径</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">' + ROLE_ORDER.map(function(rk) {
        var rc = ROLES_DATA[rk];
        return '<button class="of-role-card" data-role="' + rk + '" style="animation:ofRoleCard .5s cubic-bezier(.22,1,.36,1);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:16px;padding:22px 14px;cursor:pointer;color:#fff;transition:all .2s"><div style="font-size:32px;margin-bottom:8px">' + rc.emoji + '</div><div style="font-size:15px;font-weight:700;margin-bottom:4px">' + rc.label + '</div><div style="font-size:12px;color:rgba(255,255,255,.6);line-height:1.5">' + rc.desc + "</div></button>";
      }).join("") + '</div><button id="of-role-skip" style="margin-top:20px;background:none;border:none;color:rgba(255,255,255,.45);cursor:pointer;font-size:12.5px">暂不选择，先随便看看 →</button>';
      ov.appendChild(box);
      document.body.appendChild(ov);
      var autoTimer = setTimeout(function() {
        autoDefaultRole(ov);
      }, 5e3);
      ov.__ofTimer = autoTimer;
      ov.querySelectorAll(".of-role-card").forEach(function(c) {
        c.addEventListener("mouseenter", function() {
          this.style.background = "rgba(255,255,255,.16)";
          this.style.borderColor = "#ddff0e";
          this.style.transform = "translateY(-3px)";
        });
        c.addEventListener("mouseleave", function() {
          this.style.background = "rgba(255,255,255,.08)";
          this.style.borderColor = "rgba(255,255,255,.18)";
          this.style.transform = "none";
        });
        c.addEventListener("click", function() {
          clearTimeout(autoTimer);
          var rk = this.dataset.role;
          setRole(rk);
          trackRole(rk);
          applyRole(rk);
          syncRoleToMember(rk);
          var target = roleLanding2(rk);
          ov.style.transition = "opacity .45s, transform .45s";
          ov.style.opacity = "0";
          ov.style.transform = "scale(1.04)";
          if (target) {
            setTimeout(function() {
              window.location.href = target;
            }, 420);
          } else {
            setTimeout(function() {
              ov.remove();
            }, 460);
          }
          var main = document.getElementById("main");
          if (main) {
            main.style.transition = "transform .45s";
            main.style.transform = "scale(.995)";
            setTimeout(function() {
              main.style.transform = "none";
            }, 460);
          }
        });
      });
      var skipPicker = function() {
        dismissPicker(ov);
      };
      document.getElementById("of-role-skip").addEventListener("click", skipPicker);
      if (window.OFDialog) {
        window.OFDialog.register(ov, "onboard", { close: skipPicker, open: function() {
          ov.classList.add("open");
        } });
        if (ov.__ofOpen) ov.__ofOpen();
      }
      ov.__ofYield = setInterval(function() {
        if (document.querySelector("[data-of-dialog].open:not(#of-role-overlay)")) skipPicker();
      }, 700);
    }
    function addRoleSwitcher() {
      if (document.getElementById("of-role-switch")) return;
      var btn = document.createElement("button");
      btn.id = "of-role-switch";
      btn.title = "切换角色视角";
      btn.style.cssText = "position:fixed;right:16px;bottom:16px;z-index:999;background:rgba(30,30,30,.85);color:#ddff0e;border:none;border-radius:999px;padding:8px 14px;font-size:12px;font-weight:600;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.18);backdrop-filter:blur(6px)";
      btn.textContent = "👤 切换视角";
      btn.addEventListener("click", function() {
        var next = nextRole(ORDER, getRole());
        setRole(next);
        trackRole(next);
        applyRole(next);
        syncRoleToMember(next);
        var label = (ROLES_DATA[next] || {}).label || next;
        btn.textContent = "✓ " + label + " · 再点切换";
        setTimeout(function() {
          btn.textContent = "👤 切换视角";
        }, 1800);
        var main = document.getElementById("main");
        if (main) {
          main.style.transition = "opacity .3s";
          main.style.opacity = ".5";
          setTimeout(function() {
            main.style.opacity = "1";
          }, 180);
        }
      });
      document.body.appendChild(btn);
    }
    function syncRoleToMember(rk) {
      try {
        if (typeof sess === "undefined" || !sess) return;
        var fd = new FormData();
        fd.append("action", "update_profile");
        fd.append("role", rk);
        fetch("/api/member.php", { method: "POST", body: fd, credentials: "same-origin" });
      } catch (e) {
      }
    }
    function isAdChannel2() {
      try {
        return isAdChannel(parseQuery(window.location.search));
      } catch (e) {
        return false;
      }
    }
    function inferRoleFromUtm2() {
      try {
        return inferRoleFromUtm(parseQuery(window.location.search), ORDER);
      } catch (e) {
        return null;
      }
    }
    function memberRole() {
      try {
        return readStoredRole(localStorage, "of_member_role", ROLES_DATA);
      } catch (e) {
        return null;
      }
    }
    function init() {
      if (!window.CDP) {
        var s = document.createElement("script");
        s.src = "/assets/cdp-track.js";
        s.setAttribute("data-api", "/api/cdp.php");
        document.head.appendChild(s);
      }
      var isLoggedIn = typeof sess !== "undefined" && sess;
      var saved = getRole();
      if (isLoggedIn) {
        var mRole = memberRole() || saved;
        if (mRole) {
          applyRole(mRole);
          document.documentElement.setAttribute("data-role", mRole);
        }
        setTimeout(addRoleSwitcher, 600);
        return;
      }
      if (isAdChannel2()) {
        var utmRole = inferRoleFromUtm2();
        var finalRole = utmRole || saved || defaultRole(window.OF_DEFAULT_ROLE, ORDER);
        setRole(finalRole);
        trackRole(finalRole, true);
        applyRole(finalRole);
        document.documentElement.setAttribute("data-role", finalRole);
        setTimeout(addRoleSwitcher, 800);
        return;
      }
      if (saved) {
        applyRole(saved);
        document.documentElement.setAttribute("data-role", saved);
        setTimeout(addRoleSwitcher, 600);
      } else {
        setTimeout(showRolePicker, 900);
        setTimeout(addRoleSwitcher, 3e3);
      }
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
  })();
})();
