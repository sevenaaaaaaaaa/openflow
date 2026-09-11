#!/usr/bin/env python3
"""质量复检：找出近乎空白的导航站截图并用更长等待重截
判定：近白像素占比 > 92% 且唯一颜色数 < 400 → 视为未渲染成功
重截策略：networkidle + 6s 等待 + 模拟真实 UA"""
import asyncio, json, os, sys
from PIL import Image
from playwright.async_api import async_playwright

OUT = "assets/images/nav-sites"
CONCURRENCY = 4
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"

def is_blank(path):
    try:
        im = Image.open(path).convert("RGB").resize((360, 225))
        px = list(im.getdata())
        white = sum(1 for r, g, b in px if r > 240 and g > 240 and b > 240)
        return (white / len(px) > 0.92) and len(set(px)) < 400
    except Exception:
        return False

async def shot(sem, browser, sid, url):
    async with sem:
        ctx = await browser.new_context(viewport={"width": 1440, "height": 900},
                                        device_scale_factor=1.5, user_agent=UA)
        page = await ctx.new_page()
        try:
            await page.goto(url, wait_until="networkidle", timeout=30000)
            await page.wait_for_timeout(6000)
            await page.screenshot(path=f"{OUT}/{sid}.png")
            return ("ok", sid)
        except Exception as e:
            return ("fail", sid, str(e)[:80])
        finally:
            await ctx.close()

async def main():
    nav = json.load(open("data/navigation.json"))
    sites = {s["id"]: s["url"] for s in nav.get("sites", []) if s.get("url")}
    blanks = [sid for sid in sites
              if os.path.exists(f"{OUT}/{sid}.png") and is_blank(f"{OUT}/{sid}.png")]
    print(f"blank suspects: {len(blanks)}")
    if not blanks:
        print("DONE nothing to redo"); return
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        sem = asyncio.Semaphore(CONCURRENCY)
        ok = fail = 0
        for fut in asyncio.as_completed([shot(sem, browser, sid, sites[sid]) for sid in blanks]):
            r = await fut
            if r[0] == "ok": ok += 1
            else: fail += 1; print("[fail]", r[1], r[2])
            if (ok + fail) % 10 == 0: print(f"progress: ok={ok} fail={fail} / {len(blanks)}")
        await browser.close()
        still = [sid for sid in blanks if is_blank(f"{OUT}/{sid}.png")]
        print(f"DONE ok={ok} fail={fail} still_blank={len(still)}")
        for sid in still: print("[still]", sid, sites[sid])

asyncio.run(main())
