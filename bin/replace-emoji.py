#!/usr/bin/env python3
"""OpenFlow 全站 emoji 图标位 → SVG 批量替换（仅 HTML 文本节点，跳过 PHP 块与数据 emoji）
安全规则：
  1. 只处理 HTML 文本节点（> ... < 之间），PHP 块 <?php ... ?> 完全跳过
  2. 评分/状态类（★☆⭐✅✓👁）保留 —— 属于数据语义，不是功能图标
  3. 替换产物：<span class="ic emj">SVG</span>，尺寸继承行内
"""
import re, os, sys

ROOT = "/Users/seveno/OpenFlow Dev"

# 功能 emoji → SVG（单色描边 24 视图，与首页图标库同风格）
EMOJI_SVG = {
    '📄': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>',
    '📰': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h13v14H4zM17 8h3v9a2 2 0 0 1-2 2h-1"/><path d="M7 9h7M7 12h7M7 15h4"/></svg>',
    '📚': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg>',
    '🎙': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>',
    '🎬': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg>',
    '🎵': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    '🎧': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14v-2a9 9 0 0 1 18 0v2"/><rect x="3" y="14" width="4" height="6" rx="2"/><rect x="17" y="14" width="4" height="6" rx="2"/></svg>',
    '🧰': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4L15 12l-3-3 2.7-2.7Z"/><path d="m15 3 6 6"/></svg>',
    '💬': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg>',
    '🔥': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4.4 0 7-2.8 7-6.5 0-3.2-2-5.5-3.5-7.5C14 6 13 4 12 2c0 0-1 4-3 6-1.5 1.5-3 3.6-3 6.5C6 19.2 7.6 22 12 22Z"/></svg>',
    '🎯': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg>',
    '📑': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M9 13h6M9 17h4"/></svg>',
    '🎓': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg>',
    '📡': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12a15 15 0 0 1 20 0M5 15a10 10 0 0 1 14 0M8.5 18a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1.2" fill="currentColor"/></svg>',
    '🛒': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M3 3h2l2.4 12.4a1.5 1.5 0 0 0 1.5 1.2h7.7a1.5 1.5 0 0 0 1.5-1.2L20 7H6"/></svg>',
    '📦': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg>',
    '🎁': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4"/><path d="M5 12v8h14v-8M12 8v12M12 8s-1-5-4-5c-2 0-2.5 1.5-1 3 1.5 1.5 5 2 5 2ZM12 8s1-5 4-5c2 0 2.5 1.5 1 3-1.5 1.5-5 2-5 2Z"/></svg>',
    '🔒': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    '🕒': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    '📌': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 4 11 11-4 4L5 9l4-5ZM14 10l-7 7M12 19l-3 3"/></svg>',
    '🌐': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
    '✍': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>',
    '⚡': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg>',
    '🚀': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2-.7-3 0Z"/><path d="M12 15l-3-3c2-5.5 5-9 9-9s3 6-1 11l-5 1Z"/><path d="M9 12c-2.5 1-4 3-4.5 5M15 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>',
    '🏆': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M10 14h4v3h-4zM12 17v3M8 21h8"/></svg>',
    '💎': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9l4-6Z"/><path d="M2 9h20M9 3 7 9l5 12M15 3l2 6-5 12"/></svg>',
    '🛍': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1.5 13.5a1 1 0 0 1-1 1.1H5.5a1 1 0 0 1-1-1.1L6 7Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>',
    '🏅': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 8 5.5-3 5.5 3-2-8"/></svg>',
    '📋': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9V4ZM9 10h6M9 14h4"/></svg>',
    '📅': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
    '📝': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>',
    '🧩': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg>',
    '🔌': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v6M15 3v6M7 9h10v4a5 5 0 0 1-10 0V9Z"/><path d="M12 18v3"/></svg>',
    '📖': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg>',
    '📊': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"/><path d="M7 20v-7M12 20V5M17 20v-11"/></svg>',
    '📥': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>',
    '🪜': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v18M8 3v18M5 8h3M8 13h3M5 18h3M13 5l6 14M15.5 9.5l1.5-1M17 13l1.5-1"/></svg>',
    '🏫': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/></svg>',
    '😅': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01M15 9h.01"/></svg>',
    '🎪': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18l-2 12H5L3 7Z"/><path d="M5 7l1.5-3h11L19 7M12 7v12"/></svg>',
    '👥': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.2 2.7-5 6-5s6 1.8 6 5"/><path d="M16 4.5a3.2 3.2 0 0 1 0 6.5M18 15.5c2 .8 3 2.3 3 4.5"/></svg>',
    '🎉': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 20 8-8M9 4l.5 3.5L13 8l-3.5.5L9 12l-.5-3.5L5 8l3.5-.5L9 4ZM16 6l.4 2.6L19 9l-2.6.4L16 12l-.4-2.6L13 9l2.6-.4L16 6ZM20 13l.3 2 2 .3-2 .3-.3 2-.3-2-2-.3 2-.3.3-2Z"/></svg>',
    '📢': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9v6h3l5 4V5L7 9H4Z"/><path d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg>',
    '💡': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 1 3.5 10.9c-.8.6-1.5 1.4-1.5 2.6h-4c0-1.2-.7-2-1.5-2.6A6 6 0 0 1 12 3Z"/></svg>',
    '🎨': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 1.5-2s0-2 1.5-2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"/><circle cx="8" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg>',
    '🤖': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4m-3 1 3 3 3-3"/><circle cx="9" cy="13.5" r="1.2" fill="currentColor"/><circle cx="15" cy="13.5" r="1.2" fill="currentColor"/><path d="M9.5 17h5"/></svg>',
}

# 保留的语义字符（数据/评分/状态，非功能图标）
KEEP = set('★☆⭐✅✓👁️©®™')

def replace_html_text(src):
    """仅在 HTML 文本节点内替换 emoji；PHP 块跳过。"""
    out = []
    i = 0
    n = len(src)
    while i < n:
        if src.startswith('<?php', i):
            j = src.find('?>', i)
            if j < 0: j = n
            out.append(src[i:j+2]); i = j + 2
            continue
        # 标签或文本
        if src[i] == '<':
            # 找标签结束
            j = src.find('>', i)
            if j < 0: j = n
            out.append(src[i:j+1]); i = j + 1
            continue
        # 文本节点：替换 emoji
        j = src.find('<', i)
        if j < 0: j = n
        text = src[i:j]
        # 逐 emoji 替换
        def repl(m):
            em = m.group(0)
            # 变体选择符剥掉
            em2 = em.replace('\ufe0f', '')
            if em2 in KEEP or em2 not in EMOJI_SVG:
                return em
            return '<span class="ic emj">' + EMOJI_SVG[em2] + '</span>'
        text2 = re.sub(r'[\U0001F300-\U0001FAFF\u2600-\u27BF\u2B00-\u2BFF]\ufe0f?', repl, text)
        out.append(text2)
        i = j
    return ''.join(out)

if __name__ == '__main__':
    targets = sys.argv[1:] or ["academy.php","community.php","marketplace.php","docs.php","podcasts.php","tools.php","downloads.php","shop.php","consultation.php","member.php","thank-you.php","reviews.php","live.php","category.php","author.php","search.php","course-player.php","topics.php","event.php","nps.php","survey-my.php","survey.php","landing.php"]
    total = 0
    for p in targets:
        fp = os.path.join(ROOT, p)
        if not os.path.exists(fp): continue
        src = open(fp, encoding="utf-8").read()
        before = len(re.findall(r'[\U0001F300-\U0001FAFF\u2600-\u27BF\u2B00-\u2BFF]', src))
        new = replace_html_text(src)
        after = len(re.findall(r'[\U0001F300-\U0001FAFF\u2600-\u27BF\u2B00-\u2BFF]', new))
        if before != after:
            open(fp, "w", encoding="utf-8").write(new)
            print(f"{p}: emoji {before}→{after}（替换 {before-after}）")
            total += before - after
        else:
            print(f"{p}: 无变化（{before} 保留）")
    print(f"\n总计替换 {total} 处")
