#!/usr/bin/env python3
"""补拍：CRM / 自动化 / GitHub 仓库页（能力页 Tab 证据图）"""
import asyncio, os
from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8890"
OUT = "assets/images/product"
SID = "ofbatcha20260910"

async def main():
    os.makedirs(OUT, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page(viewport={"width": 1520, "height": 950}, device_scale_factor=1.5)
        await page.context.add_cookies([{"name": "PHPSESSID", "value": SID, "url": BASE + "/"}])
        for name, url in [("crm", "/xmp/crm"), ("automation", "/xmp/automation")]:
            try:
                await page.goto(BASE + url, wait_until="networkidle", timeout=25000)
            except Exception as e:
                print(f"[warn] {name}: {e}")
            await page.wait_for_timeout(1000)
            await page.screenshot(path=f"{OUT}/{name}.png")
            print(f"[ok] {name}.png")
        # GitHub 仓库页（开源证据）
        try:
            await page.goto("https://github.com/sevenaaaaaaaaa/openflow", wait_until="domcontentloaded", timeout=30000)
            await page.wait_for_timeout(2500)
            await page.screenshot(path=f"{OUT}/github.png")
            print("[ok] github.png")
        except Exception as e:
            print(f"[warn] github: {e}")
        await browser.close()

asyncio.run(main())
