#!/usr/bin/env python3
"""帮助中心截图：后台关键页面（存 assets/images/help/）"""
import asyncio, os
from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8899"
OUT = "assets/images/help"
SID = "ofhelp20260911"

SHOTS = [
    ("workspace",        "/xmp/workspace",        "工作台"),
    ("create",           "/xmp/create",           "创作台"),
    ("article-edit",     "/xmp/article-edit",     "文章编辑器"),
    ("content-calendar", "/xmp/content-calendar", "内容日历"),
    ("automation",       "/xmp/automation",       "营销自动化"),
    ("canvas",           "/xmp/canvas",           "自动化画布"),
    ("live-admin",       "/xmp/live",             "直播管理"),
    ("dashboard",        "/xmp/dashboard",        "经营驾驶舱"),
    ("audience",         "/xmp/audience",         "CDP 画像"),
    ("crm",              "/xmp/crm",              "CRM 线索"),
    ("publish",          "/xmp/publish",          "内容分发"),
    ("product-scout",    "/xmp/product-scout",    "产品发现 Loop"),
    ("mail-settings",    "/xmp/mail-settings",    "邮件设置"),
    ("dynamic-content",  "/xmp/dynamic-content",  "动态内容"),
    ("help-admin",       "/xmp/help-center",      "帮助中心管理"),
    ("ai-config",        "/xmp/ai-config",        "AI 配置"),
]

async def main():
    os.makedirs(OUT, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page(viewport={"width": 1520, "height": 950}, device_scale_factor=1.5)
        await page.context.add_cookies([{"name": "PHPSESSID", "value": SID, "url": BASE + "/"}])
        resp = await page.goto(BASE + "/xmp/workspace", wait_until="domcontentloaded")
        print("[debug] status:", resp.status, "url:", page.url)
        if "/login" in page.url:
            print("[fatal] 会话未生效"); return
        for name, url, label in SHOTS:
            try:
                await page.goto(BASE + url, wait_until="networkidle", timeout=25000)
            except Exception as e:
                print(f"[warn] {name}: {e}")
            await page.wait_for_timeout(1400)
            await page.screenshot(path=f"{OUT}/{name}.png")
            print(f"[ok] {name}.png — {label}")
        await browser.close()

asyncio.run(main())
