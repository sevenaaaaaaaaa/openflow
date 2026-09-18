#!/usr/bin/env python3
"""
多设备兼容 + 弹窗行为审计（Playwright，真实测量）

用法：
  python3 scripts/audit-devices.py                 # 全量：响应式溢出 + 弹窗行为
  python3 scripts/audit-devices.py --only overflow # 只跑响应式
  python3 scripts/audit-devices.py --only modals   # 只跑弹窗
  python3 scripts/audit-devices.py --base http://localhost:8080

输出：markdown 报告到 stdout（同时写 /tmp/audit-devices.json）
"""
import argparse
import json

from playwright.sync_api import sync_playwright

WIDTHS = [360, 390, 414, 768, 1024, 1280, 1440]
PAGES = [
    ("/", "首页"), ("/product", "产品"), ("/capability", "能力"),
    ("/demo/home-v2", "首页v2"), ("/demo/products", "聚合页"), ("/demo/capabilities", "能力全景"),
    ("/articles", "文章列表"), ("/topic/ai-create", "专题枢纽"),
    ("/pricing", "定价"), ("/navigation", "导航"), ("/marketplace", "市场"),
    ("/downloads", "下载"), ("/courses", "课程"), ("/academy", "学院"), ("/live", "直播"),
]

OVERFLOW_JS = """
() => {
  const de = document.documentElement, w = window.innerWidth;
  const out = [];
  document.querySelectorAll('body *').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return;
    const cs = getComputedStyle(el);
    if (cs.position === 'fixed' && cs.visibility === 'hidden') return;
    const over = Math.round(r.right - w);
    if (over > 2) out.push({ tag: el.tagName.toLowerCase(), cls: (el.className||'').toString().slice(0,48), over });
  });
  out.sort((a,b) => b.over - a.over);
  return { scrollW: de.scrollWidth, innerW: w, offenders: out.slice(0, 5) };
}
"""

def check_overflow(browser, base):
    rows = []
    for path, name in PAGES:
        for w in WIDTHS:
            ctx = browser.new_context(viewport={"width": w, "height": 900}, device_scale_factor=1,
                                      is_mobile=(w <= 414), has_touch=(w <= 414))
            pg = ctx.new_page()
            try:
                pg.goto(base + path, wait_until="domcontentloaded", timeout=20000)
                pg.wait_for_timeout(400)
                r = pg.evaluate(OVERFLOW_JS)
                rows.append({"page": name, "path": path, "w": w,
                             "overflow": r["scrollW"] - r["innerW"], "offenders": r["offenders"]})
            except Exception as e:
                rows.append({"page": name, "path": path, "w": w, "overflow": None, "error": str(e)[:80]})
            ctx.close()
    return rows

MODAL_PROBES = [
    # (名称, 触发选择器)
    ("登录弹窗", '[data-act="login"], [data-act="start"]'),
    ("个人中心", '[data-act="profile"], .brand, #userBtn'),
    ("命令面板", 'body'),   # 用快捷键打开
]

def check_modals(browser, base):
    out = []
    ctx = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
    ctx.add_init_script("try{localStorage.setItem('of_role','power')}catch(e){}")  # 跳过首次角色浮层
    pg = ctx.new_page()
    pg.goto(base + "/", wait_until="domcontentloaded", timeout=20000)
    pg.wait_for_timeout(600)

    def probe(name, js_open, cls):
        info = {"name": name, "cls": cls}
        try:
            pg.evaluate(js_open)
            pg.wait_for_timeout(350)
            info["opened"] = pg.evaluate(
                "cls => !!document.querySelector(cls) && getComputedStyle(document.querySelector(cls)).opacity !== '0'", cls)
            info["aria_modal"] = pg.evaluate("cls => { const e=document.querySelector(cls); return e ? e.getAttribute('aria-modal') : null }", cls)
            info["role"] = pg.evaluate("cls => { const e=document.querySelector(cls); return e ? e.getAttribute('role') : null }", cls)
            info["scroll_locked"] = pg.evaluate("() => getComputedStyle(document.body).overflow === 'hidden' || document.body.classList.contains('shop-open') || document.body.style.overflow === 'hidden'")
            info["active_in_modal"] = pg.evaluate("cls => { const e=document.querySelector(cls); return !!(e && e.contains(document.activeElement)) }", cls)
            # ESC 关闭
            pg.keyboard.press("Escape"); pg.wait_for_timeout(250)
            info["esc_closes"] = not pg.evaluate(
                "cls => { const e=document.querySelector(cls); return e && getComputedStyle(e).opacity !== '0' }", cls)
            # 遮罩点击关闭（重开）
            pg.evaluate(js_open); pg.wait_for_timeout(300)
            box = pg.evaluate("cls => { const e=document.querySelector(cls); if(!e) return null; const r=e.getBoundingClientRect(); return {x: 10, y: Math.round(r.y + Math.min(r.height/2, 300))} }", cls)
            if box:
                pg.mouse.click(box["x"], box["y"]); pg.wait_for_timeout(350)
                info["backdrop_closes"] = not pg.evaluate(
                    "cls => { const e=document.querySelector(cls); return e && getComputedStyle(e).opacity !== '0' }", cls)
            pg.evaluate("() => { const e=document.querySelector('.modal.open,.palette.open'); if(e) e.classList.remove('open'); document.body.style.overflow=''; }")
        except Exception as e:
            info["error"] = str(e)[:100]
        out.append(info)

    # 真实入口：走 OFShell / OFShellDialogs（避免"手动加 class"造成假阴性）
    probe("登录弹窗", "() => (window.OFShellDialogs||window.OFShell).openAuth()", "#authModal")
    probe("个人中心", "() => (window.OFShellDialogs||window.OFShell).openProfile()", "#profileModal")
    probe("命令面板", "() => (window.OFShellDialogs||window.OFShell).openPalette()", "#palette")

    # ── 命令面板过滤 + 键盘导航（此前输入框无效）──
    pal = {"name": "面板过滤", "cls": "#palette"}
    try:
        pg.evaluate("() => (window.OFShellDialogs||window.OFShell).openPalette()")
        pg.wait_for_timeout(300)
        pal["all_items"] = len(pg.query_selector_all("#palList .p-item"))
        pg.fill("#palInput", "产品")
        pg.wait_for_timeout(250)
        pal["filtered_items"] = len(pg.query_selector_all("#palList .p-item"))
        pal["first_hit"] = pg.evaluate("() => { const e=document.querySelector('#palList .p-item'); return e ? e.textContent.trim() : null }")
        pg.keyboard.press("ArrowDown"); pg.wait_for_timeout(200)
        pal["active"] = pg.evaluate("() => { const e=document.querySelector('#palList .p-item.on'); return e ? e.textContent.trim() : null }")
        pg.fill("#palInput", "zzzz"); pg.wait_for_timeout(250)
        pal["empty_state"] = pg.evaluate("() => !!document.querySelector('#palList .p-none')")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(200)
    except Exception as e:
        pal["error"] = str(e)[:90]
    out.append(pal)
    ctx.close()

    # ── 首次角色浮层：出现 / ESC / 遮罩 / 与弹窗互斥 ──
    ctx2 = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
    pg2 = ctx2.new_page()
    pg2.goto(base + "/", wait_until="domcontentloaded", timeout=20000)
    pg2.wait_for_timeout(3600)
    onboard = {"name": "首次角色浮层", "cls": "#of-role-overlay"}
    onboard["opened"] = pg2.evaluate("() => !!document.getElementById('of-role-overlay')")
    onboard["role"] = pg2.evaluate("() => { const e=document.getElementById('of-role-overlay'); return e ? e.getAttribute('role') : null }")
    onboard["scroll_locked"] = pg2.evaluate("() => document.body.style.overflow === 'hidden'")
    pg2.keyboard.press("Escape"); pg2.wait_for_timeout(600)
    onboard["esc_closes"] = pg2.evaluate("() => !document.getElementById('of-role-overlay')")
    # 互斥：先开弹窗，等浮层计时器到点，浮层不应出现 / 或立即让位
    ctx3 = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
    pg3 = ctx3.new_page()
    pg3.goto(base + "/", wait_until="domcontentloaded", timeout=20000)
    pg3.wait_for_timeout(500)
    pg3.evaluate("() => (window.OFShellDialogs||window.OFShell).openAuth()")
    pg3.wait_for_timeout(3200)
    onboard["yields_to_dialog"] = pg3.evaluate("() => { const o=document.getElementById('of-role-overlay'); const a=document.getElementById('authModal'); const aOpen=a && a.classList.contains('open'); const oVis=o && getComputedStyle(o).opacity!=='0'; return !!aOpen && !oVis }")
    ctx2.close(); ctx3.close()
    out.append(onboard)
    return out

ROLES_JS = """
() => ({
  role: document.documentElement.getAttribute('data-role'),
  picker: !!document.getElementById('of-role-overlay'),
  switcher: !!document.getElementById('of-role-switch')
})
"""

def check_roles(browser, base):
    """角色系统：首次浮层 / 记忆角色 / UTM 推断 / 切换按钮"""
    out = {}

    def new_ctx(storage=None):
        c = browser.new_context(viewport={"width": 1280, "height": 900})
        if storage:
            c.add_init_script(f"try{{localStorage.setItem('of_role','{storage}')}}catch(e){{}}")
        return c

    # 1) 首次访问：应出现浮层
    c1 = new_ctx(); pg = c1.new_page()
    pg.goto(base + "/", wait_until="domcontentloaded", timeout=25000)
    for _ in range(24):
        pg.wait_for_timeout(250)
        if pg.evaluate(ROLES_JS)["picker"]:
            break
    out["fresh_picker"] = pg.evaluate(ROLES_JS)["picker"]
    # 选第一个角色 → 应写入 data-role（可能伴随跳转）
    try:
        pg.click(".of-role-card", timeout=3000)      # 选择后会跳转到该角色推荐落地页
        pg.wait_for_timeout(1600)
        # 在同一个 context 里开新标签读 localStorage（避开跳转时的上下文销毁竞态）
        pg2 = c1.new_page()
        pg2.goto(base + "/", wait_until="domcontentloaded", timeout=25000)
        out["after_pick_role"] = pg2.evaluate("() => { try { return localStorage.getItem('of_role') } catch(e) { return null } }")
        pg2.close()
    except Exception as e:
        out["after_pick_role"] = "ERR " + str(e)[:40]
    c1.close()

    # 2) 老访客：有记忆 → 不弹浮层，直接应用
    c2 = new_ctx("power"); pg = c2.new_page()
    pg.goto(base + "/", wait_until="domcontentloaded", timeout=25000); pg.wait_for_timeout(2600)
    st = pg.evaluate(ROLES_JS)
    out["returning_role"] = st["role"]; out["returning_picker"] = st["picker"]
    out["switcher_present"] = st["switcher"]
    # 切换按钮 → 下一个角色（power → dev）
    try:
        pg.click("#of-role-switch", timeout=3000); pg.wait_for_timeout(500)
        out["after_switch_role"] = pg.evaluate("() => document.documentElement.getAttribute('data-role')")
    except Exception as e:
        out["after_switch_role"] = "ERR " + str(e)[:40]
    c2.close()

    # 3) UTM 渠道：预判 dev，不弹浮层
    c3 = new_ctx(); pg = c3.new_page()
    pg.goto(base + "/?utm_source=github", wait_until="domcontentloaded", timeout=25000); pg.wait_for_timeout(2600)
    st = pg.evaluate(ROLES_JS)
    out["utm_role"] = st["role"]; out["utm_picker"] = st["picker"]
    c3.close()
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="https://nownexts.com")
    ap.add_argument("--only", default="all", choices=["all", "overflow", "modals", "roles"])
    a = ap.parse_args()
    res = {}
    with sync_playwright() as p:
        b = p.chromium.launch()
        if a.only in ("all", "overflow"):
            res["overflow"] = check_overflow(b, a.base)
        if a.only in ("all", "modals"):
            res["modals"] = check_modals(b, a.base)
        if a.only in ("all", "roles"):
            res["roles"] = check_roles(b, a.base)
        b.close()
    json.dump(res, open("/tmp/audit-devices.json", "w"), ensure_ascii=False, indent=1)

    if "overflow" in res:
        print("## 响应式横向溢出（超宽 > 2px 记为问题）\n")
        print("| 页面 | " + " | ".join(f"{w}" for w in WIDTHS) + " |")
        print("|---" * (len(WIDTHS) + 1) + "|")
        for path, name in PAGES:
            cells = []
            for w in WIDTHS:
                r = next((x for x in res["overflow"] if x["path"] == path and x["w"] == w), None)
                if not r: cells.append("?"); continue
                if r.get("overflow") is None: cells.append("ERR")
                elif r["overflow"] <= 2: cells.append("✓")
                else: cells.append(f"**+{r['overflow']}**")
            print(f"| {name} | " + " | ".join(cells) + " |")
        print("\n最严重元素：")
        worst = sorted([r for r in res["overflow"] if (r.get("overflow") or 0) > 2], key=lambda x: -x["overflow"])[:10]
        for r in worst:
            ofm = ", ".join(f"{o['tag']}.{o['cls']}(+{o['over']})" for o in r["offenders"][:2])
            print(f"  - {r['page']} @{r['w']}px: +{r['overflow']}px ← {ofm}")

    print_roles(res)

    if "modals" in res:
        print("\n## 弹窗行为（390px 移动端）\n")
        keys = ["opened", "role", "aria_modal", "scroll_locked", "active_in_modal", "esc_closes", "backdrop_closes", "error"]
        print("| 弹窗 | " + " | ".join(keys) + " |")
        print("|---" * (len(keys) + 1) + "|")
        for m in res["modals"]:
            if m["name"] == "面板过滤":
                continue
            print("| " + m["name"] + " | " + " | ".join(str(m.get(k, "-")) for k in keys) + " |")
        pal = next((m for m in res["modals"] if m["name"] == "面板过滤"), None)
        if pal:
            print("\n### 命令面板（⌘K）过滤与键盘导航\n")
            print(f"- 全部条目：{pal.get('all_items')}")
            print(f"- 输入「产品」后：{pal.get('filtered_items')} 条，首条 = {pal.get('first_hit')}")
            print(f"- ↓ 键高亮项：{pal.get('active')}")
            print(f"- 无命中时空状态：{pal.get('empty_state')}")

def print_roles(res):
    r = res.get("roles")
    if not r:
        return
    print("\n## 角色系统（首次浮层 / 记忆 / UTM / 切换）\n")
    checks = [
        ("首次访问出现角色浮层", r.get("fresh_picker") is True),
        ("选择角色后已写入偏好（localStorage）", bool(r.get("after_pick_role")) and "ERR" not in str(r.get("after_pick_role"))),
        ("老访客直接应用记忆角色", r.get("returning_role") == "power"),
        ("老访客不再弹浮层", r.get("returning_picker") is False),
        ("切换按钮存在", r.get("switcher_present") is True),
        ("切换按钮轮换角色（power→dev）", r.get("after_switch_role") == "dev"),
        ("UTM=github 推断为 dev", r.get("utm_role") == "dev"),
        ("UTM 来源不弹浮层", r.get("utm_picker") is False),
    ]
    for name, ok in checks:
        print(f"- {'✓' if ok else '✗'} {name}" + ("" if ok else f"  （实测：{r}）"))


if __name__ == "__main__":
    main()
