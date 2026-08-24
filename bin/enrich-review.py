#!/usr/bin/env python3
"""
enrich-review.py — 补全 58 篇复核稿件
从 GitHub README 生成完整文章（遵循 Daily/Cluster 生产规范）
"""
import os, re, json, hashlib, time, urllib.request, urllib.error
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
REVIEW_DIR = Path('/Users/seveno/Knowledge/Obsidian/MindRe/1-Project/Lovart MFlow/1-3 GenFlow/Content Distribution/Drafts/Website-Ready/04-Tags-需人工复核')
OUTPUT = BASE / 'data' / 'review-enriched.json'
CATEGORIES_FILE = BASE / 'data' / 'article-categories.json'

AUTHOR = 'Gana'
MIN_BODY_LEN = 300

# ─── 分类规则（复用 import-articles.py 的逻辑） ───
CATS = json.loads(CATEGORIES_FILE.read_text(encoding='utf-8'))

SLUG_MAP = {
    '深度测评': 'deep-review', '评测': 'review', '教程': 'tutorial',
    '工具': 'tools', '推荐': 'top-picks', '工作流': 'workflow',
    '自动化': 'automation', '设计': 'design', '视频': 'video',
    '图片': 'image', '写作': 'writing', '音频': 'audio',
    '营销': 'marketing', '增长': 'growth', '内容': 'content',
    '编程': 'coding', '开源': 'open-source', '效率': 'efficiency',
    'AI': 'ai', 'Agent': 'agent', '免费': 'free', 'Mac': 'mac',
    'Windows': 'windows', '浏览器': 'browser', '插件': 'plugin',
    '搜索': 'search', '下载': 'download', '翻译': 'translate',
    '赚钱': 'monetization', '副业': 'side-hustle',
    '怎么选': 'how-to-choose', '值得': 'worth-it', '替代': 'alternative',
    '大全': 'collection', '入门': 'getting-started', '实战': 'practical',
    '2026': '2026',
}

# ─── GitHub API ───

def fetch_github_readme(repo: str) -> dict:
    """获取 GitHub repo README + 元数据"""
    result = {'readme': '', 'stars': 0, 'license': '', 'topics': [], 'desc': '', 'lang': ''}
    try:
        # Repo info
        url = f'https://api.github.com/repos/{repo}'
        req = urllib.request.Request(url, headers={'User-Agent': 'OpenFlow-Bot'})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read())
            result['stars'] = data.get('stargazers_count', 0)
            result['license'] = (data.get('license') or {}).get('spdx_id', '')
            result['desc'] = data.get('description', '')
            result['lang'] = data.get('language', '')
            result['topics'] = data.get('topics', [])[:8]
    except Exception as e:
        print(f'  ⚠️ repo info 失败: {e}')

    try:
        # README
        url = f'https://api.github.com/repos/{repo}/readme'
        req = urllib.request.Request(url, headers={'User-Agent': 'OpenFlow-Bot', 'Accept': 'application/vnd.github.v3.raw'})
        with urllib.request.urlopen(req, timeout=10) as resp:
            result['readme'] = resp.read().decode('utf-8', errors='ignore')[:5000]
    except Exception as e:
        print(f'  ⚠️ README 失败: {e}')

    return result

def search_github(query: str) -> str:
    """搜索 GitHub 找 repo slug"""
    try:
        url = f'https://api.github.com/search/repositories?q={urllib.parse.quote(query)}&sort=stars&per_page=1'
        req = urllib.request.Request(url, headers={'User-Agent': 'OpenFlow-Bot'})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read())
            items = data.get('items', [])
            if items:
                return items[0]['full_name']
    except:
        pass
    return ''

# ─── 文章生成 ───

def classify_from_content(title: str, readme: str, dir_name: str) -> tuple:
    """从内容分类"""
    text = (title + ' ' + readme[:500]).lower()
    dir_lower = dir_name.lower()
    
    dir_signals = {
        ('ai-create', 'video'): ['视频', 'video', '剪辑', '字幕', '配音', '动画', '数字人'],
        ('ai-create', 'design'): ['设计', 'design', 'ui', 'figma', '配色', 'logo', '品牌'],
        ('ai-create', 'image'): ['图片', 'image', '文生图', '背景', '放大', '去水印'],
        ('ai-create', 'writing'): ['写作', 'writing', '文案', '翻译', '摘要', 'pdf'],
        ('ai-create', 'audio'): ['音频', 'audio', '语音', '播客', 'tts', '配音', '音乐'],
        ('ai-code', 'agent'): ['agent', '智能体', 'mcp', 'skills', 'langchain'],
        ('ai-code', 'ide'): ['ide', 'cursor', 'copilot', '编码', '编程', '代码'],
        ('ai-ops', 'automation'): ['自动化', 'automation', '工作流', 'workflow', 'n8n'],
        ('ai-build', 'nocode'): ['建站', 'site-builder', '无代码', 'wordpress'],
        ('ai-sell', 'monetization'): ['赚钱', '变现', '副业', 'roi'],
        ('ai-data', 'analytics'): ['数据', 'data', '分析', '看板', '可视化'],
        ('ai-user', 'cdp'): ['用户', '画像', 'cdp', '分群', '隐私'],
        ('agent', 'tools'): ['llm', '大模型', 'chatgpt', 'claude', 'deepseek'],
        ('trend', 'guide'): ['入门', '教程', '指南', '推荐', '清单'],
    }
    
    scores = {}
    for (cat, sub), keywords in dir_signals.items():
        for kw in keywords:
            if kw in dir_lower:
                scores[(cat, sub)] = scores.get((cat, sub), 0) + 5
            elif kw in text:
                scores[(cat, sub)] = scores.get((cat, sub), 0) + 1
    
    if scores:
        best = max(scores.items(), key=lambda x: x[1])
        if best[1] >= 2:
            return best[0]
    
    return ('trend', 'guide')

def generate_slug(title: str, dir_name: str = '') -> str:
    slug_parts = []
    for w in re.findall(r'[A-Za-z][A-Za-z0-9._-]+', title):
        w = w.strip('.-_').lower()
        if len(w) >= 2 and w not in ('com', 'org', 'net', 'io', 'the', 'and', 'for'):
            slug_parts.append(w)
    for zh, en in SLUG_MAP.items():
        if zh in title and en not in slug_parts:
            slug_parts.append(en)
    if len(slug_parts) < 3 and dir_name:
        for w in re.findall(r'[A-Za-z][A-Za-z0-9_-]+', dir_name):
            w = w.strip('-_').lower()
            if len(w) >= 2 and w not in slug_parts and w not in ('https', 'http'):
                slug_parts.append(w)
    seen = set()
    unique = []
    for p in slug_parts:
        if p not in seen:
            seen.add(p)
            unique.append(p)
    if len(unique) > 10:
        unique = unique[:10]
    if not unique:
        unique = ['article', hashlib.md5(title.encode()).hexdigest()[:8]]
    slug = '-'.join(unique)
    slug = re.sub(r'[^a-z0-9-]', '', slug)
    slug = re.sub(r'-+', '-', slug).strip('-')
    return slug

def build_article_html(title: str, readme: str, gh: dict) -> str:
    """从 README 生成 HTML 文章内容"""
    # 清理 README
    readme = re.sub(r'!\[([^\]]*)\]\([^)]+\)', '', readme)  # 去图片
    readme = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', r'\1', readme)  # 链接变文本
    readme = re.sub(r'^#+\s+', '## ', readme, flags=re.MULTILINE)  # 统一为 h2
    readme = readme.strip()
    
    # 提取核心段落（前 2000 字）
    paragraphs = [p.strip() for p in readme.split('\n\n') if p.strip() and len(p.strip()) > 20]
    core = '\n\n'.join(paragraphs[:8])
    
    # 构建 HTML
    html_parts = []
    
    # 开头
    html_parts.append(f'<h1>{html_mod.escape(title)}</h1>')
    
    tagline = []
    if gh['stars']:
        tagline.append(f'{gh["stars"]:,} Stars')
    if gh['license']:
        tagline.append(gh['license'])
    if gh['lang']:
        tagline.append(gh['lang'])
    if tagline:
        html_parts.append(f'<blockquote>{" | ".join(tagline)}</blockquote>')
    
    # README 内容转 HTML
    if core:
        for block in core.split('\n\n'):
            block = block.strip()
            if not block:
                continue
            if block.startswith('## '):
                html_parts.append(f'<h2>{html_mod.escape(block[3:])}</h2>')
            elif block.startswith('# '):
                html_parts.append(f'<h2>{html_mod.escape(block[2:])}</h2>')
            elif block.startswith('- ') or block.startswith('* '):
                items = [l.strip('- * ') for l in block.split('\n') if l.strip()]
                html_parts.append('<ul>' + ''.join(f'<li>{html_mod.escape(i)}</li>' for i in items[:10]) + '</ul>')
            elif block.startswith('```'):
                code = re.sub(r'^```\w*\n?', '', block).rstrip('`').strip()
                html_parts.append(f'<pre><code>{html_mod.escape(code[:500])}</code></pre>')
            elif block.startswith('|'):
                # 简单表格
                rows = [r.strip() for r in block.split('\n') if r.strip() and not re.match(r'^\|[-\s|]+\|$', r.strip())]
                if rows:
                    html_parts.append('<table>' + ''.join(f'<tr>{"".join("<td>"+html_mod.escape(c.strip())+"</td>" for c in row.split("|")[1:-1])}</tr>' for row in rows[:8]) + '</table>')
            else:
                html_parts.append(f'<p>{html_mod.escape(block[:500])}</p>')
    
    # 链接
    if gh.get('desc'):
        html_parts.append(f'<h2>简介</h2><p>{html_mod.escape(gh["desc"])}</p>')
    
    if gh['topics']:
        html_parts.append(f'<h2>相关标签</h2><p>{" · ".join(html_mod.escape(t) for t in gh["topics"])}</p>')
    
    return '\n'.join(html_parts)

import html as html_mod

def enrich_article(filepath: Path, dir_name: str) -> dict:
    """补全单篇文章"""
    content = filepath.read_text(encoding='utf-8', errors='ignore')
    
    # 提取 frontmatter
    fm = {}
    body = content
    if content.startswith('---'):
        parts = content.split('---', 2)
        if len(parts) >= 3:
            for line in parts[1].strip().split('\n'):
                m = re.match(r'^(\w+):\s*["\']?(.+?)["\']?\s*$', line.strip())
                if m:
                    fm[m.group(1)] = m.group(2)
            body = parts[2].strip()
    
    title = fm.get('title', '') or filepath.stem
    source = fm.get('source', '')
    existing_slug = fm.get('slug', '')
    existing_tags = fm.get('tags', '')
    
    # 解析 tags
    if isinstance(existing_tags, str):
        existing_tags = [t.strip() for t in existing_tags.strip('[]').split(',') if t.strip()]
    
    # 提取 GitHub repo
    gh_repo = ''
    if 'github.com/' in source:
        parts = source.split('github.com/')
        if len(parts) > 1:
            gh_repo = parts[1].strip('/').split('/')[0:2]
            gh_repo = '/'.join(gh_repo)
    
    # 如果没有 source，搜索 GitHub
    if not gh_repo:
        tool_name = re.sub(r'[:：].*$', '', title).strip()
        tool_name = re.sub(r'\s*(深度测评|评测|深度拆解|介绍)\s*', '', tool_name).strip()
        if tool_name:
            print(f'  🔍 搜索 GitHub: {tool_name}')
            gh_repo = search_github(tool_name)
            if gh_repo:
                source = f'https://github.com/{gh_repo}'
    
    # 获取 GitHub 数据
    gh = {'readme': '', 'stars': 0, 'license': '', 'topics': [], 'desc': '', 'lang': ''}
    if gh_repo:
        print(f'  📦 GitHub: {gh_repo}')
        gh = fetch_github_readme(gh_repo)
        time.sleep(0.5)  # 速率限制
    
    # 生成文章 HTML
    if gh['readme']:
        html_content = build_article_html(title, gh['readme'], gh)
    else:
        # 无 README，用已有 body（清理占位符）
        body = re.sub(r'（待补充[^）]*）', '', body)
        body = re.sub(r'##\s+(安装|核心用法|注意事项|与.*关系|FAQ)\s*\n\s*$', '', body, flags=re.MULTILINE)
        if len(body) < MIN_BODY_LEN:
            return {'_rejected': True, '_title': title, '_reason': '无GitHub数据且内容不足'}
        html_content = f'<h1>{html_mod.escape(title)}</h1><p>{html_mod.escape(body[:2000])}</p>'
    
    # 质量检查
    plain = re.sub(r'<[^>]+>', '', html_content)
    if len(plain) < MIN_BODY_LEN:
        return {'_rejected': True, '_title': title, '_reason': f'生成内容不足({len(plain)}字)'}
    
    # 分类
    cat, sub = classify_from_content(title, gh.get('readme', ''), dir_name)
    
    # Slug
    slug = existing_slug or generate_slug(title, dir_name)
    
    # Tags
    tags = existing_tags if existing_tags else (gh['topics'][:5] or [cat.replace('-', ' ')])
    
    # Excerpt
    excerpt = plain[:150].rsplit(' ', 1)[0] if len(plain) > 150 else plain
    
    art_id = f'art-{hashlib.md5((title + slug).encode()).hexdigest()[:10]}'
    
    return {
        'id': art_id,
        'title': title,
        'slug': slug,
        'content': html_content,
        'excerpt': excerpt,
        'status': 'draft',
        'author': AUTHOR,
        'category': f'{cat}/{sub}',
        'tags': tags[:5],
        'seo_title': f'{title} | OpenFlow',
        'seo_desc': excerpt[:120],
        'source': source,
        'created_at': '2026-08-23 00:00:00',
        'updated_at': '2026-08-23 00:00:00',
        '_source': str(filepath),
        '_quality': 'github-enriched' if gh['readme'] else 'template-only',
    }

def main():
    print('╔═══════════════════════════════════════╗')
    print('║  58 篇复核稿件 AI 补全                 ║')
    print('╚═══════════════════════════════════════╝')
    
    if not REVIEW_DIR.exists():
        print(f'❌ 目录不存在: {REVIEW_DIR}')
        return
    
    articles = []
    rejected = []
    
    for cat_dir in sorted(REVIEW_DIR.iterdir()):
        if not cat_dir.is_dir():
            continue
        cat_display = re.sub(r'^\d+[-_]', '', cat_dir.name)
        
        for item in sorted(cat_dir.iterdir()):
            if item.is_dir():
                for md in item.glob('*.md'):
                    result = enrich_article(md, cat_display)
                    if result:
                        if result.get('_rejected'):
                            rejected.append(result)
                        else:
                            articles.append(result)
            elif item.suffix == '.md':
                result = enrich_article(item, cat_display)
                if result:
                    if result.get('_rejected'):
                        rejected.append(result)
                    else:
                        articles.append(result)
    
    print(f'\n📊 结果:')
    print(f'  成功: {len(articles)} 篇')
    print(f'  拒绝: {len(rejected)} 篇')
    for r in rejected:
        print(f'    ❌ {r["_title"][:40]}: {r["_reason"]}')
    
    # 分类统计
    cat_counts = {}
    for a in articles:
        cat = a['category'].split('/')[0]
        cat_counts[cat] = cat_counts.get(cat, 0) + 1
    print(f'\n分类分布:')
    for cat, count in sorted(cat_counts.items(), key=lambda x: -x[1]):
        icon = CATS.get(cat, {}).get('icon', '📦')
        print(f'  {icon} {cat}: {count} 篇')
    
    # 输出
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(articles, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'\n✅ 输出: {OUTPUT} ({len(articles)} 篇)')

if __name__ == '__main__':
    import urllib.parse
    main()
