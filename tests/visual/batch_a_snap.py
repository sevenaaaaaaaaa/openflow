#!/usr/bin/env python3
"""批A 视觉验证：封面 v2 / 文章详情横幅 / 导航详情 / 对比页 / 后台抽卡"""
import asyncio, os, sys
from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8890"
OUT = "/tmp/batch-a"
SID = "ofbatcha20260910"

PAGES = [
    ("articles-list",  "/articles", False),
    ("article-detail", "/article/website-growth-value-compound", False),
    ("courses",        "/courses", False),
    ("nav-detail-gh",  "/navigation/site_04c2ea75", False),   # litellm（GitHub 仓库）
    ("nav-compare",    "/navigation/compare?ids=site_04c2ea75,site_2f48be63,site_55b495a6", False),
    ("admin-nav",      "/xmp/navigation", True),
    ("admin-cover",    "/xmp/article-edit?id=ai_draft_20260814_200407_9cf05", True),
]

async def main():
    os.makedirs(OUT, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page(viewport={"width": 1440, "height": 960})
        # 伪造后台登录会话
        await page.context.add_cookies([{"name": "PHPSESSID", "value": SID, "domain": "127.0.0.1", "path": "/"}])
        errors = []
        page.on("pageerror", lambda e: errors.append(str(e)))
        for name, url, is_admin in PAGES:
            try:
                await page.goto(BASE + url, wait_until="networkidle", timeout=25000)
            except Exception as e:
                print(f"[warn] {name} goto: {e}")
            await page.wait_for_timeout(900)
            await page.screenshot(path=f"{OUT}/{name}.png", full_page=False)
            print(f"[ok] {name}.png")
        if errors:
            print("JS errors:", *errors[:5], sep="\n  ")
        await browser.close()

asyncio.run(main())
