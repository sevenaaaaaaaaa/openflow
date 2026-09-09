#!/usr/bin/env python3
"""
外壳几何契约测试（docs/DESIGN-SYSTEM.md 第二节 #shell）—— 真浏览器量出来的硬约束：

  1. top：顶栏通栏、侧栏从其下方开始、导航居中于视口
  2. docked：顶栏左边界让出侧栏并成为药丸、侧栏仅向上延伸、导航居中于正文
  3. 滚动前后正文横向几何与侧栏宽度不变；品牌区不压导航
  4. mega 面板贴在当前顶栏下方、落在顶栏宽度内，鼠标/键盘行为不回归
  5. 窄屏不启用双阶段几何，侧栏仍从底部升起

  用法：php -S 127.0.0.1:8890 -t . &   然后   python3 tests/visual/shell_geom.py [page.php]
"""
import os, sys
from playwright.sync_api import sync_playwright

BASE = os.environ.get("OF_BASE", "http://127.0.0.1:8890")
PAGE = sys.argv[1] if len(sys.argv) > 1 else "about.php"
fails = []
def check(ok, msg):
    if not ok: fails.append(msg); print("  ✗", msg)

MEASURE = """()=>{const r=e=>e.getBoundingClientRect();
  const c=document.getElementById('chrome'),t=document.getElementById('tabs'),m=document.getElementById('main'),s=document.getElementById('sidebar'),bs=c.querySelector('.bar-start');
  const cr=r(c),tr=r(t),mr=r(m),sr=r(s),ms=getComputedStyle(m);
  const mainL=mr.left+parseFloat(ms.paddingLeft),mainR=mr.right-parseFloat(ms.paddingRight);
  return {phase:document.body.dataset.shellPhase,cl:Math.round(cr.left),crr:Math.round(cr.right),
    ct:Math.round(cr.top),ch:Math.round(cr.height),radius:parseFloat(getComputedStyle(c).borderTopLeftRadius),
    st:Math.round(sr.top),sw:Math.round(sr.width),mainX:Math.round(mr.left),mainW:Math.round(mr.width),
    tabsOn:getComputedStyle(t).display!=='none',tabsC:(tr.left+tr.right)/2,mainC:(mainL+mainR)/2,
    brandR:r(bs).right,tabsL:tr.left,viewportC:innerWidth/2}}"""

with sync_playwright() as p:
    b = p.chromium.launch()
    print("== 桌面：双阶段几何 / 正文稳定 / 不压字 ==")
    for vw in (1440, 1280, 1024):
        for sb in ("full", "rail", "closed"):
            ctx = b.new_context(viewport={"width": vw, "height": 800}, reduced_motion="reduce")
            ctx.add_init_script("try{localStorage.setItem('of_role','beginner');localStorage.setItem('openflow-site-v3',JSON.stringify({theme:'light',sb:'%s'}))}catch(e){}" % sb)
            pg = ctx.new_page(); pg.goto(f"{BASE}/{PAGE}", wait_until="networkidle")
            snaps = {}
            for y in (0, 400):
                pg.evaluate(f"window.scrollTo(0,{y})"); pg.wait_for_timeout(400)
                snaps[y] = pg.evaluate(MEASURE)
                m = snaps[y]
                tag = f"{vw}/{sb}/y={y}"
                if m["tabsOn"]:
                    check(m["brandR"] <= m["tabsL"] + 1, f"{tag} 品牌区压到导航 {m['brandR']-m['tabsL']:.0f}px")
            a, c = snaps[0], snaps[400]
            tag = f"{vw}/{sb}"
            check(a["phase"] == "top", f"{tag} 顶部不是 top 阶段：{a['phase']}")
            check(abs(a["cl"]) <= 1 and abs(a["crr"] - vw) <= 1, f"{tag} top 顶栏未通栏 {a['cl']}~{a['crr']}")
            check(a["st"] == a["ch"] + 10, f"{tag} top 侧栏顶距 {a['st']}，应为 {a['ch']+10}")
            if a["tabsOn"]:
                check(abs(a["tabsC"] - a["viewportC"]) <= 2, f"{tag} top 导航偏离视口中心 {a['tabsC']-a['viewportC']:.1f}px")
            check(c["phase"] == "docked", f"{tag} 滚动后不是 docked 阶段：{c['phase']}")
            check(c["cl"] > a["cl"] and c["crr"] < a["crr"], f"{tag} 顶栏没有横向收成药丸")
            check(c["ct"] == 10 and c["radius"] >= 20, f"{tag} docked 顶栏 top/radius 异常 {c['ct']}/{c['radius']}")
            check(c["st"] == 10 and c["st"] < a["st"], f"{tag} 侧栏没有只向上延伸 {a['st']}→{c['st']}")
            check((a["mainX"], a["mainW"]) == (c["mainX"], c["mainW"]), f"{tag} 滚动改变正文横向几何")
            check(a["sw"] == c["sw"], f"{tag} 滚动改变侧栏宽度 {a['sw']}→{c['sw']}")
            if c["tabsOn"]:
                check(abs(c["tabsC"] - c["mainC"]) <= 2, f"{tag} docked 导航偏离正文中心 {c['tabsC']-c['mainC']:.1f}px")
            ctx.close()

    print("== 动态顶部占位 ==")
    ctx = b.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="reduce")
    ctx.add_init_script("try{localStorage.setItem('openflow-site-v3',JSON.stringify({theme:'light',sb:'full'}))}catch(e){}")
    pg = ctx.new_page(); pg.goto(f"{BASE}/{PAGE}", wait_until="networkidle")
    pg.evaluate("document.body.style.setProperty('--external-top-inset','36px')")
    pg.wait_for_timeout(200)
    ext_top = pg.evaluate("""()=>{const c=document.getElementById('chrome').getBoundingClientRect(),
      s=document.getElementById('sidebar').getBoundingClientRect(),first=document.querySelector('#main>section').getBoundingClientRect();
      return {ct:Math.round(c.top),cb:Math.round(c.bottom),st:Math.round(s.top),first:Math.round(first.top)}}""")
    check(ext_top["ct"] == 36, f"顶部条占位后 top 顶栏位置异常 {ext_top['ct']}")
    check(ext_top["st"] == 102, f"顶部条占位后侧栏位置异常 {ext_top['st']}")
    check(ext_top["first"] >= ext_top["cb"] + 28, f"顶部条占位后正文被顶栏遮挡 {ext_top}")
    pg.evaluate("window.scrollTo(0,400)"); pg.wait_for_timeout(250)
    ext_docked = pg.evaluate("""()=>{const c=document.getElementById('chrome').getBoundingClientRect(),
      s=document.getElementById('sidebar').getBoundingClientRect();return {ct:Math.round(c.top),st:Math.round(s.top)}}""")
    check(ext_docked == {"ct": 46, "st": 46}, f"顶部条占位后 docked 外壳位置异常 {ext_docked}")
    ctx.close()

    print("== 自然过渡 / 减少动效 ==")
    ctx = b.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="no-preference")
    ctx.add_init_script("try{localStorage.setItem('openflow-site-v3',JSON.stringify({theme:'light',sb:'full'}))}catch(e){}")
    pg = ctx.new_page(); pg.goto(f"{BASE}/{PAGE}", wait_until="networkidle")
    start = pg.evaluate("()=>({cl:document.getElementById('chrome').getBoundingClientRect().left,st:document.getElementById('sidebar').getBoundingClientRect().top})")
    pg.evaluate("document.documentElement.style.scrollBehavior='auto';window.scrollTo(0,400)")
    pg.wait_for_function("document.body.dataset.shellPhase==='docked'")
    pg.wait_for_timeout(140)
    middle = pg.evaluate("()=>({cl:document.getElementById('chrome').getBoundingClientRect().left,st:document.getElementById('sidebar').getBoundingClientRect().top})")
    pg.wait_for_timeout(500)
    finish = pg.evaluate("()=>({cl:document.getElementById('chrome').getBoundingClientRect().left,st:document.getElementById('sidebar').getBoundingClientRect().top})")
    check(start["cl"] < middle["cl"] < finish["cl"], f"顶栏没有自然横向过渡 {start['cl']:.1f}→{middle['cl']:.1f}→{finish['cl']:.1f}")
    check(start["st"] > middle["st"] > finish["st"], f"侧栏没有自然纵向过渡 {start['st']:.1f}→{middle['st']:.1f}→{finish['st']:.1f}")
    ctx.close()

    ctx = b.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="reduce")
    pg = ctx.new_page(); pg.goto(f"{BASE}/{PAGE}", wait_until="networkidle")
    check(pg.evaluate("document.documentElement.classList.contains('rm')"), "系统减少动效偏好未映射到 html.rm")
    pg.evaluate("window.scrollTo(0,400)"); pg.wait_for_timeout(100)
    check(pg.evaluate("getComputedStyle(document.getElementById('chrome')).transitionDuration") == "0s", "减少动效下外壳仍有 transition")
    ctx.close()

    print("== 顶部通知条协议 ==")
    ctx = b.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="reduce")
    ctx.route("**/api/conversion.php*", lambda route: route.fulfill(
        status=200, content_type="application/json",
        body='{"ok":true,"conversion":{"top_bar":{"enabled":true,"text":"测试通知","dismissible":true}}}'
    ))
    pg = ctx.new_page(); pg.goto(f"{BASE}/docs.php", wait_until="networkidle")
    pg.wait_for_selector("#fc-top-bar")
    bar_state = pg.evaluate("""()=>{const bar=document.getElementById('fc-top-bar'),c=document.getElementById('chrome').getBoundingClientRect();
      return {h:bar.offsetHeight,inset:getComputedStyle(document.body).getPropertyValue('--external-top-inset').trim(),ct:Math.round(c.top)}}""")
    check(bar_state["inset"] == f"{bar_state['h']}px", f"顶部通知条没有报告真实高度 {bar_state}")
    check(bar_state["ct"] == bar_state["h"], f"顶部通知条没有推开 top 顶栏 {bar_state}")
    pg.click("#fc-top-bar > span"); pg.wait_for_timeout(100)
    check(pg.evaluate("getComputedStyle(document.body).getPropertyValue('--external-top-inset').trim()") == "0px", "关闭顶部通知条后 inset 未归零")
    ctx.close()

    print("== mega 菜单 ==")
    for sb, y in (("full", 0), ("closed", 400), ("rail", 400)):
        ctx = b.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="reduce")
        ctx.add_init_script("try{localStorage.setItem('of_role','beginner');localStorage.setItem('openflow-site-v3',JSON.stringify({theme:'light',sb:'%s'}))}catch(e){}" % sb)
        pg = ctx.new_page(); pg.goto(f"{BASE}/{PAGE}", wait_until="networkidle")
        pg.evaluate(f"window.scrollTo(0,{y})"); pg.wait_for_timeout(400)
        tab = pg.query_selector('#tabs a.tab')
        if not tab: print("  （导航里没有带 mega 的项，跳过）"); ctx.close(); continue
        bb = tab.bounding_box()
        pg.mouse.move(bb["x"] + bb["width"] / 2, bb["y"] + bb["height"] / 2); pg.wait_for_timeout(400)
        m = pg.evaluate("""()=>{const t=document.querySelector('#tabs a.tab.mega-open'),mg=document.getElementById('mega'),pn=mg.querySelector('.mg-panel');
          const tb=t.getBoundingClientRect(),mb=pn.getBoundingClientRect(),cb=document.getElementById('chrome').getBoundingClientRect();
          return {op:getComputedStyle(mg).opacity,gap:mb.top-cb.bottom,inside:mb.left>=cb.left&&mb.right<=cb.right,tabC:(tb.left+tb.right)/2,mL:mb.left,mR:mb.right,exp:t.getAttribute('aria-expanded')}}""")
        tag = f"mega/{sb}/y={y}"
        check(m["op"] == "1", f"{tag} hover 后没打开")
        check(abs(m["gap"] - 8) <= 2, f"{tag} 面板与胶囊间隙 {m['gap']:.0f}px（应为 8）")
        check(m["inside"], f"{tag} 面板超出胶囊宽度")
        check(m["mL"] - 1 <= m["tabC"] <= m["mR"] + 1, f"{tag} 面板没覆盖触发它的 tab（tab 中心 {m['tabC']:.0f} 不在 {m['mL']:.0f}~{m['mR']:.0f}）")
        check(m["exp"] == "true", f"{tag} aria-expanded 未置 true")
        pg.mouse.move(bb["x"] + bb["width"] / 2, bb["y"] + bb["height"] + 5); pg.wait_for_timeout(260)
        check(pg.evaluate("getComputedStyle(document.getElementById('mega')).opacity") == "1", f"{tag} 鼠标经过空隙时菜单关闭了")
        pg.mouse.move(20, 700); pg.wait_for_timeout(450)
        check(pg.evaluate("getComputedStyle(document.getElementById('mega')).opacity") == "0", f"{tag} 移开后没关闭")
        tab.focus(); pg.keyboard.press("ArrowDown"); pg.wait_for_timeout(300)
        check(pg.evaluate("document.activeElement.closest('#mega')!==null"), f"{tag} ↓ 键没把焦点送进面板")
        pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
        check(pg.evaluate("document.activeElement.classList.contains('tab')"), f"{tag} Esc 没把焦点还给 tab")
        ctx.close()

    print("== 窄屏抽屉 ==")
    ctx = b.new_context(viewport={"width": 390, "height": 844}, reduced_motion="reduce")
    ctx.add_init_script("try{localStorage.setItem('of_role','beginner');localStorage.setItem('openflow-site-v3',JSON.stringify({theme:'light'}))}catch(e){}")
    pg = ctx.new_page(); pg.goto(f"{BASE}/{PAGE}", wait_until="networkidle")
    check(pg.evaluate("getComputedStyle(document.getElementById('tabs')).display") == "none", "390px 下导航 tabs 应隐藏")
    pg.evaluate("window.scrollTo(0,400)"); pg.wait_for_timeout(300)
    check(pg.evaluate("document.body.dataset.shellPhase") == "top", "390px 下不应进入 docked 几何")
    pg.click("#btn-menu"); pg.wait_for_timeout(500)
    d = pg.evaluate("(()=>{const s=document.getElementById('sidebar').getBoundingClientRect();return {top:s.top,bottom:s.bottom,left:s.left,right:s.right,sb:document.body.dataset.sb}})()")
    check(d["sb"] == "drawer", "点菜单后未进入 drawer 态")
    check(0 <= d["left"] and d["right"] <= 390 and abs(d["bottom"] - 844) <= 2 and d["top"] > 100, f"抽屉位置异常 {d}")
    ctx.close()
    b.close()

print(f"\n{'全部通过' if not fails else str(len(fails)) + ' 项失败'}")
sys.exit(1 if fails else 0)
