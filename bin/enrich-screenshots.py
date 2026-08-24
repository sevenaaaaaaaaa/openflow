#!/usr/bin/env python3
"""
enrich-screenshots.py — 文章插图：mshots 网站截图批量抓取
从文章中提取外部 URL → mshots 截图 → 下载 → 插入文章 HTML
"""
import os, re, json, hashlib, time, urllib.request, urllib.parse, sys
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
UPLOADS = BASE / 'uploads' / 'articles'
INDEX = BASE / 'data' / 'articles' / 'index.json'

MSTHUMBS = 'https://s.wordpress.com/mshots/v1/'
MAX_SCREENSHOTS_PER_ARTICLE = 2
SCREENSHOT_WIDTH = 720
REQUEST_DELAY = 2  # mshots 速率限制

def extract_urls(html: str, source: str = '') -> list:
    """从文章 HTML 和 source 字段提取外部 URL"""
    urls = []
    # HTML 链接
    for m in re.finditer(r'href="(https?://[^"]+)"', html):
        url = m.group(1)
        if 'nownexts.com' not in url and 'wordpress.com' not in url:
            urls.append(url)
    # source 字段
    if source and source.startswith('http'):
        urls.insert(0, source)
    # 去重
    seen = set()
    unique = []
    for u in urls:
        key = re.sub(r'[?#].*', '', u)
        if key not in seen:
            seen.add(key)
            unique.append(u)
    return unique

def download_screenshot(url: str, save_path: Path) -> bool:
    """下载 mshots 截图"""
    mshot_url = MSTHUMBS + urllib.parse.quote(url, safe='') + f'?w={SCREENSHOT_WIDTH}'
    try:
        req = urllib.request.Request(mshot_url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=30) as resp:
            data = resp.read()
            if len(data) < 5000:  # 太小可能是错误页面
                return False
            save_path.parent.mkdir(parents=True, exist_ok=True)
            save_path.write_bytes(data)
            return True
    except Exception as e:
        print(f'    ⚠️ 截图失败: {e}')
        return False

def main():
    print('╔═══════════════════════════════════════╗')
    print('║  文章插图：mshots 截图批量抓取          ║')
    print('╚═══════════════════════════════════════╝')
    
    articles = json.loads(INDEX.read_text(encoding='utf-8'))
    UPLOADS.mkdir(parents=True, exist_ok=True)
    
    updated = 0
    skipped = 0
    failed = 0
    total_screenshots = 0
    
    for i, article in enumerate(articles):
        content = article.get('content', '')
        source = article.get('source', '')
        slug = article.get('slug', '')
        
        # 检查是否已有截图
        if 'mshots' in content or 'screenshot' in content.lower():
            skipped += 1
            continue
        
        urls = extract_urls(content, source)
        if not urls:
            skipped += 1
            continue
        
        # 只取前 N 个 URL
        urls = urls[:MAX_SCREENSHOTS_PER_ARTICLE]
        article_updated = False
        
        for url in urls:
            # 文件名
            url_hash = hashlib.md5(url.encode()).hexdigest()[:8]
            filename = f'screenshot-{slug}-{url_hash}.jpeg'
            save_path = UPLOADS / filename
            
            if save_path.exists():
                # 已有截图，只插入引用
                if filename not in content:
                    insert_img = f'\n<figure><img src="uploads/articles/{filename}" alt="{article.get("title", "")} 界面预览" loading="lazy" style="max-width:100%;border-radius:12px;margin:16px 0"><figcaption style="font-size:12px;color:var(--muted);text-align:center">{article.get("title", "")} 界面预览</figcaption></figure>\n'
                    article['content'] = content + insert_img
                    article_updated = True
                continue
            
            print(f'  [{i+1}/{len(articles)}] {slug}: {url[:60]}...')
            time.sleep(REQUEST_DELAY)
            
            if download_screenshot(url, save_path):
                insert_img = f'\n<figure><img src="uploads/articles/{filename}" alt="{article.get("title", "")} 界面预览" loading="lazy" style="max-width:100%;border-radius:12px;margin:16px 0"><figcaption style="font-size:12px;color:var(--muted);text-align:center">{article.get("title", "")} 界面预览</figcaption></figure>\n'
                article['content'] = article.get('content', '') + insert_img
                article_updated = True
                total_screenshots += 1
                print(f'    ✅ {filename}')
            else:
                failed += 1
        
        if article_updated:
            updated += 1
    
    # 保存
    INDEX.write_text(json.dumps(articles, ensure_ascii=False, indent=2), encoding='utf-8')
    
    print(f'\n📊 结果:')
    print(f'  文章更新: {updated}')
    print(f'  截图数: {total_screenshots}')
    print(f'  跳过: {skipped}')
    print(f'  失败: {failed}')
    print(f'\n✅ 完成')

if __name__ == '__main__':
    main()
