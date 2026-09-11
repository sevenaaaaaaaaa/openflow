#!/usr/bin/env python3
"""批量截取导航站收录站点的首页截图（存 assets/images/nav-sites/{id}.png）
并发 6，失败跳过；已存在则跳过（可增量重跑）"""
import asyncio, json, os, sys
from playwright.async_api import async_playwright

OUT = "assets/images/nav-sites"
CONCURRENCY = 6

async def shot(sem, browser, sid, url):
    if os.path.exists(f"{OUT}/{sid}.png"):
        return ("skip", sid)
    async with sem:
        page = await browser.new_page(viewport={"width": 1440, "height": 900}, device_scale_factor=1.5)
        try:
            await page.goto(url, wait_until="domcontentloaded", timeout=20000)
            await page.wait_for_timeout(2500)
            await page.screenshot(path=f"{OUT}/{sid}.png")
            return ("ok", sid)
        except Exception as e:
            return ("fail", sid, str(e)[:80])
        finally:
            await page.close()

async def main():
    os.makedirs(OUT, exist_ok=True)
    nav = json.load(open("data/navigation.json"))
    sites = [(s["id"], s["url"]) for s in nav.get("sites", []) if s.get("url")]
    print(f"total sites: {len(sites)}")
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        sem = asyncio.Semaphore(CONCURRENCY)
        tasks = [shot(sem, browser, sid, url) for sid, url in sites]
        ok = fail = skip = 0
        for fut in asyncio.as_completed(tasks):
            r = await fut
            if r[0] == "ok": ok += 1
            elif r[0] == "fail": fail += 1; print("[fail]", r[1], r[2])
            else: skip += 1
            if (ok + fail + skip) % 25 == 0:
                print(f"progress: ok={ok} fail={fail} skip={skip} / {len(sites)}")
        await browser.close()
        print(f"DONE ok={ok} fail={fail} skip={skip}")

asyncio.run(main())
