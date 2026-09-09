#!/usr/bin/env python3
"""批B 改动页面截图验证"""
import asyncio, os, sys
from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8890"
OUT = "/tmp/batch-b"
SID = "ofbatcha20260910"

PAGES = [
    ("product", "/product.php", False),
    ("capability", "/capability.php", False),
    ("about", "/about.php", False),
    ("courses", "/courses.php", False),
    ("events", "/events.php", False),
    ("navigation", "/navigation.php", False),
    ("community", "/community.php", False),
    ("marketplace", "/marketplace.php", False),
    ("academy", "/academy.php", False),
    ("article", "/article.php?slug=website-growth-value-compound", False),
    ("event", "/event.php?slug=growth-workshop-online", False),
    ("course", "/course-player.php?id=course_growth_os", False),
]

async def main():
    os.makedirs(OUT, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page(viewport={"width": 1440, "height": 960})
        await page.context.add_cookies([{"name": "PHPSESSID", "value": SID, "url": BASE + "/"}])
        errors = []
        page.on("console", lambda m: errors.append(f"{m.type}: {m.text[:150]}") if m.type == "error" else None)
        page.on("pageerror", lambda e: errors.append(f"pageerror: {str(e)[:150]}"))
        for name, url, full in PAGES:
            errors.clear()
            try:
                resp = await page.goto(BASE + url, wait_until="networkidle", timeout=25000)
                status = resp.status if resp else 0
            except Exception as e:
                print(f"[FAIL] {name}: {e}")
                continue
            await page.wait_for_timeout(900)
            await page.screenshot(path=f"{OUT}/{name}.png", full_page=False)
            print(f"[{'ok' if status==200 else 'HTTP'+str(status)}] {name} ({url})" + (f"  JS错误: {errors[:2]}" if errors else ""))
        await browser.close()

asyncio.run(main())
