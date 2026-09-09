#!/usr/bin/env python3
"""拍真实产品界面截图，作为产品页证据素材（存 assets/images/product/）"""
import asyncio, os
from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8890"
OUT = "assets/images/product"
SID = "ofbatcha20260910"

SHOTS = [
    ("workspace",   "/xmp/workspace", "工作台：KPI 与待办一览"),
    ("studio",      "/xmp/studio", "自动化编排画布"),
    ("audience",    "/xmp/audience", "CDP 用户画像与分群"),
    ("content-hub", "/xmp/content-hub", "内容中心"),
]

async def main():
    os.makedirs(OUT, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page(viewport={"width": 1520, "height": 950}, device_scale_factor=1.5)
        await page.context.add_cookies([{"name": "PHPSESSID", "value": SID, "url": BASE + "/"}])
        # 验证会话生效
        resp = await page.goto(BASE + "/xmp/workspace", wait_until="domcontentloaded")
        print("[debug] workspace status:", resp.status, "final url:", page.url)
        for name, url, label in SHOTS:
            try:
                await page.goto(BASE + url, wait_until="networkidle", timeout=25000)
            except Exception as e:
                print(f"[warn] {name}: {e}")
            await page.wait_for_timeout(1200)
            await page.screenshot(path=f"{OUT}/{name}.png")
            print(f"[ok] {name}.png — {label}")
        await browser.close()

asyncio.run(main())
