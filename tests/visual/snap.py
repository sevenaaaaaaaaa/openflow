#!/usr/bin/env python3
"""
视觉快照工具 —— 把前端页在 4 种状态下拍成整页截图，供改前/改后逐像素对比。

  状态：亮色 / 暗色 × 桌面 1280 / 手机 390
  用法：
    php -S 127.0.0.1:8890 -t . &          # 先起本地站
    python3 tests/visual/snap.py OUT_DIR index.php about.php ...
    python3 tests/visual/snap.py --diff DIR_A DIR_B     # 比较两次快照

  为了让截图可复现（否则 diff 全是噪音）：
    - 关掉动效（reduced-motion + 强制 .reveal 可见）
    - 预置 localStorage：跳过首页角色浮层、固定主题
    - 等 networkidle 后再等 400ms 让字体/布局稳定
  这些处理改前改后一致，所以 diff 仍然有效。
"""
import sys, os, json
from pathlib import Path

BASE = os.environ.get("OF_BASE", "http://127.0.0.1:8890")
STATES = [  # (name, theme, width, height)
    ("light-desktop", "light", 1280, 800),
    ("dark-desktop",  "dark",  1280, 800),
    ("light-mobile",  "light",  390, 844),
    ("dark-mobile",   "dark",   390, 844),
]
FREEZE_CSS = """
  .reveal,.hero-stagger{opacity:1!important;transform:none!important;transition:none!important}
  *,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}
  .prog::after{transform:none!important}
"""

def snap(out_dir: Path, pages):
    from playwright.sync_api import sync_playwright
    out_dir.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        b = p.chromium.launch()
        for name, theme, w, h in STATES:
            ctx = b.new_context(viewport={"width": w, "height": h},
                                reduced_motion="reduce", device_scale_factor=1)
            ctx.add_init_script(f"""
              try {{
                localStorage.setItem('of_role','beginner');
                localStorage.setItem('of_member_role','beginner');
                localStorage.setItem('openflow-site-v3', JSON.stringify({{theme:'{theme}'}}));
              }} catch(e){{}}
            """)
            for pg in pages:
                page = ctx.new_page()
                page.goto(f"{BASE}/{pg}", wait_until="networkidle", timeout=45000)
                page.add_style_tag(content=FREEZE_CSS)
                page.evaluate("document.documentElement.dataset.theme=%s" % json.dumps(theme))
                # 滚一遍触发所有懒加载/观察者，再回顶
                page.evaluate("""async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,40));} window.scrollTo(0,0);}""")
                page.wait_for_timeout(400)
                fn = out_dir / f"{pg.replace('.php','')}__{name}.png"
                page.screenshot(path=str(fn), full_page=True)
                hgt = page.evaluate("document.documentElement.scrollHeight")
                print(f"  {fn.name:44} {w}x{hgt}")
                page.close()
            ctx.close()
        b.close()

def diff(a: Path, b: Path):
    from PIL import Image, ImageChops
    names = sorted(set(p.name for p in a.glob("*.png")) & set(p.name for p in b.glob("*.png")))
    bad = 0
    for n in names:
        ia, ib = Image.open(a / n).convert("RGB"), Image.open(b / n).convert("RGB")
        if ia.size != ib.size:
            print(f"  ✗ {n:44} 尺寸不同 {ia.size} → {ib.size}"); bad += 1; continue
        d = ImageChops.difference(ia, ib)
        bbox = d.getbbox()
        if bbox is None:
            print(f"  ✓ {n:44} 逐像素一致")
        else:
            import numpy as np
            px = int((np.asarray(d.convert("L")) > 8).sum())
            pct = 100.0 * px / (ia.size[0] * ia.size[1])
            print(f"  ✗ {n:44} 差异像素 {px} ({pct:.3f}%)  区域 {bbox}")
            d.point(lambda v: 255 if v > 8 else 0).save(b / f"DIFF__{n}")
            bad += 1
    print(f"\n{len(names)-bad}/{len(names)} 一致" + ("" if bad == 0 else f"，{bad} 张有差异（差异图已存到 {b}/DIFF__*.png）"))
    return bad

if __name__ == "__main__":
    args = sys.argv[1:]
    if not args:
        print(__doc__); sys.exit(2)
    if args[0] == "--diff":
        sys.exit(1 if diff(Path(args[1]), Path(args[2])) else 0)
    snap(Path(args[0]), args[1:])
