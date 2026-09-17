#!/usr/bin/env python3
"""把硬编码的 CSS 版本号统一到共享 head（of_head_assets），消除重复加载与版本漂移。
用法: python3 scripts/unify-head-assets.py [--apply]

做法：单次正则替换 [主题脚本 + 注释 + 3 条 link] → 共享调用，避免索引错位。
"""
import re, sys, pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
APPLY = '--apply' in sys.argv

CALL = "<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>\n"

# 主题早绑定脚本（各页逐字相同）
THEME = re.escape("<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>")
LINK = r'[ \t]*<link rel="stylesheet"[^>]*/assets/(?:fonts/fonts|tokens|modules)\.css\?v=[^>]*>[ \t]*\r?\n'
LINK_RE = re.compile(LINK)

# 单次替换：主题脚本(可有可无) + 共享契约注释(可有可无) + 1-3 条 link
BLOCK_RE = re.compile(
    r'(?:' + THEME + r'\r?\n)?'
    r'(?:[ \t]*<!--\s*共享外壳样式契约[^>]*-->\r?\n)?'
    r'(?:' + LINK + r'){1,3}'
)

GROUP_A = ['index.php','product.php','capability.php','courses.php','about.php','academy.php','community.php',
           'marketplace.php','events.php','navigation.php','pricing.php','article.php','articles.php',
           'enterprise.php','nav-submit.php']
GROUP_B = ['cart.php','newsletter.php','topics.php']

def migrate(name):
    p = ROOT / name
    src = p.read_text(encoding='utf-8')
    n_links = len(LINK_RE.findall(src))
    if n_links == 0:
        return name, 'SKIP(无硬编码链接)', None
    if name in GROUP_B:
        return name, f'删除 {n_links} 条重复链接', LINK_RE.sub('', src)
    m = BLOCK_RE.search(src)
    if not m:
        return name, 'SKIP(未匹配到块模式)', None
    once = BLOCK_RE.sub(CALL, src, count=1)
    if once == src:
        return name, 'SKIP(替换未生效)', None
    return name, f'迁移 {n_links} 条链接 → of_head_assets()', once

changed = 0
for name in GROUP_A + GROUP_B:
    n, msg, out = migrate(name)
    if out is None:
        print(f"⚠️  {n}: {msg}")
        continue
    print(f"{'✅' if APPLY else '·'} {n}: {msg}")
    if APPLY:
        (ROOT / n).write_text(out, encoding='utf-8')
    changed += 1
print(f"\n{'已应用' if APPLY else '干跑'}：{changed} 个文件")
