#!/usr/bin/env python3
"""
外壳几何契约测试（docs/DESIGN-SYSTEM.md 第二节 #shell）—— 真浏览器量出来的硬约束：

  1. 导航中心 == 正文中心（|Δ| ≤ 2px），任何视口 × 侧栏三态 × 滚动位置
  2. 滚动不改顶栏几何：y=0 与 y=400 的 left / right / top / height 完全相同
  3. 品牌区不压导航（bar-start 右沿 ≤ tabs 左沿）
  4. mega 面板：贴在胶囊下方 8px（±2）、落在胶囊宽度之内、鼠标经过空隙不关闭、Esc 关闭并把焦点还给 tab
  5. 窄屏：抽屉从底部升起并落在视口内

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
  const c=document.getElementById('chrome'),t=document.getElementById('tabs'),m=document.getElementById('main'),bs=c.querySelector('.bar-start');
  const cr=r(c),tr=r(t),mr=r(m),ms=getComputedStyle(m);
  const mainL=mr.left+parseFloat(ms.paddingLeft),mainR=mr.right-parseFloat(ms.paddingRight);
  return {cl:Math.round(cr.left),crr:Math.round(cr.right),ct:Math.round(cr.top),ch:Math.round(cr.height),
    tabsOn:getComputedStyle(t).display!=='none',tabsC:(tr.left+tr.right)/2,mainC:(mainL+mainR)/2,brandR:r(bs).right,tabsL:tr.left}}"""

with sync_playwright() as p:
    b = p.chromium.launch()
    print("== 桌面：导航居中 / 滚动不改几何 / 不压字 ==")
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
                    check(abs(m["tabsC"] - m["mainC"]) <= 2, f"{tag} 导航中心偏离正文中心 {m['tabsC']-m['mainC']:.1f}px")
                    check(m["brandR"] <= m["tabsL"] + 1, f"{tag} 品牌区压到导航 {m['brandR']-m['tabsL']:.0f}px")
            a, c = snaps[0], snaps[400]
            check((a["cl"], a["crr"], a["ct"], a["ch"]) == (c["cl"], c["crr"], c["ct"], c["ch"]), f"{vw}/{sb} 滚动改变了顶栏几何 {(a['cl'],a['crr'],a['ct'],a['ch'])} → {(c['cl'],c['crr'],c['ct'],c['ch'])}")
            ctx.close()

    print("== mega 菜单 ==")
    for sb, y in (("full", 0), ("closed", 400), ("rail", 200)):
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
    pg.click("#btn-menu"); pg.wait_for_timeout(500)
    d = pg.evaluate("(()=>{const s=document.getElementById('sidebar').getBoundingClientRect();return {top:s.top,bottom:s.bottom,left:s.left,right:s.right,sb:document.body.dataset.sb}})()")
    check(d["sb"] == "drawer", "点菜单后未进入 drawer 态")
    check(0 <= d["left"] and d["right"] <= 390 and abs(d["bottom"] - 844) <= 2 and d["top"] > 100, f"抽屉位置异常 {d}")
    ctx.close()
    b.close()

print(f"\n{'全部通过' if not fails else str(len(fails)) + ' 项失败'}")
sys.exit(1 if fails else 0)
