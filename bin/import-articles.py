#!/usr/bin/env python3
"""
import-articles.py — Website-Ready 文章批量处理
从 Daily/Cluster/Tags 目录提取 → 清洗 → 分类 → slug → HTML → 质量审核 → 去重
输出 data/articles-to-import.json
"""
import os, re, json, hashlib, html as html_mod
import unicodedata, shutil, glob
from pathlib import Path

# ─── 配置 ───
BASE = Path(__file__).resolve().parent.parent
DRAFTS = Path('/Users/seveno/Knowledge/Obsidian/MindRe/1-Project/Lovart MFlow/1-3 GenFlow/Content Distribution/Drafts/Website-Ready')
UPLOADS = BASE / 'uploads' / 'articles'
OUTPUT = BASE / 'data' / 'articles-to-import.json'
CATEGORIES_FILE = BASE / 'data' / 'article-categories.json'

AUTHOR = 'Gana'
MIN_BODY_LEN = 300  # 最少正文字符数
MAX_SLUG_WORDS = 10

# ─── 加载分类规则 ───
CATS = json.loads(CATEGORIES_FILE.read_text(encoding='utf-8'))

def build_keyword_map():
    """构建 keyword → (一级, 二级) 映射"""
    km = {}
    for cat_slug, cat in CATS.items():
        for sub_slug, sub in cat.get('sub', {}).items():
            for kw in sub.get('keywords', []):
                km[kw.lower()] = (cat_slug, sub_slug)
    return km

KW_MAP = build_keyword_map()

# ─── 标题提取 ───

def extract_title(content: str, source_type: str, filename: str = '') -> str:
    """从内容提取标题"""
    lines = content.strip().split('\n')
    
    # 1. frontmatter title
    if lines[0].strip().startswith('---'):
        for line in lines[1:]:
            if line.strip().startswith('---'):
                break
            m = re.match(r'^title:\s*["\']?(.+?)["\']?\s*$', line.strip())
            if m:
                return clean_title(m.group(1), source_type)
    
    # 2. H1
    for line in lines[:5]:
        m = re.match(r'^#\s+(.+)$', line.strip())
        if m:
            return clean_title(m.group(1), source_type)
    
    # 3. 目录名
    if filename:
        name = re.sub(r'^\d+[-_]', '', filename).replace('-', ' ').replace('_', ' ').strip()
        if len(name) > 5:
            return name
    
    return ''

def clean_title(title: str, source_type: str) -> str:
    """清理标题"""
    title = title.strip()
    # 去掉 A姐/NowX 后缀
    title = re.sub(r'\s*[-–—|]\s*A姐分享\s*$', '', title)
    title = re.sub(r'\s*_\d+_\d+\s*$', '', title)
    # 去掉 T2/T3 tagline（单行 blockquote）
    title = re.sub(r'\s*$', '', title)
    # 去掉多余引号
    title = title.strip('"\'')
    return title

# ─── Markdown → HTML ───

try:
    import markdown as md_lib
    def md_to_html(text: str) -> str:
        """Markdown → HTML"""
        # 去掉 frontmatter
        text = re.sub(r'^---\n.*?\n---\n', '', text, flags=re.DOTALL)
        # 去掉第一行 H1（已提取为标题）
        text = re.sub(r'^#\s+.+\n?', '', text, count=1)
        # 去掉 T2/T3 tagline
        text = re.sub(r'^>\s*T[23]\s+.*\n?', '', text, flags=re.MULTILINE)
        # 去掉作者 persona 介绍段（通常是前3行中以"我"开头的段落）
        lines = text.strip().split('\n')
        skip = 0
        for i, l in enumerate(lines[:6]):
            if l.strip().startswith('>') or l.strip().startswith('#') or l.strip() == '':
                continue
            if re.match(r'^我[是在有].*营销|增长|运营|产品|工作', l.strip()):
                skip = i + 1
                # 跳过后续空行
                while skip < len(lines) and lines[skip].strip() == '':
                    skip += 1
                break
        if skip > 0:
            text = '\n'.join(lines[skip:])
        
        # 处理图片路径
        text = process_image_refs(text)
        
        # Markdown → HTML
        html_content = md_lib.markdown(text, extensions=['tables', 'fenced_code', 'nl2br'])
        return html_content
except ImportError:
    def md_to_html(text: str) -> str:
        # 简单转换
        text = re.sub(r'^---\n.*?\n---\n', '', text, flags=re.DOTALL)
        text = re.sub(r'^#\s+(.+)$', r'<h1>\1</h1>', text, flags=re.MULTILINE)
        text = re.sub(r'^##\s+(.+)$', r'<h2>\1</h2>', text, flags=re.MULTILINE)
        text = re.sub(r'^###\s+(.+)$', r'<h3>\1</h3>', text, flags=re.MULTILINE)
        text = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', text)
        text = re.sub(r'\*(.+?)\*', r'<em>\1</em>', text)
        text = re.sub(r'\n\n+', '</p><p>', text)
        text = '<p>' + text + '</p>'
        return text

# ─── 图片处理 ───

def process_image_refs(text: str) -> str:
    """处理 Markdown 中的图片引用"""
    def replace_img(m):
        alt = m.group(1)
        src = m.group(2)
        if src.startswith('http://') or src.startswith('https://'):
            # 外部 URL：保持原样（后续可批量下载）
            return f'![{alt}]({src})'
        if src.startswith('data:'):
            return ''  # 去掉 data URI
        # 相对路径：标记需要复制
        return f'![{alt}]({src})'
    return re.sub(r'!\[([^\]]*)\]\(([^)]+)\)', replace_img, text)

def copy_article_images(src_dir: Path, content: str) -> str:
    """复制文章引用的图片到 uploads/articles/，更新路径"""
    UPLOADS.mkdir(parents=True, exist_ok=True)
    
    def replace_img(m):
        alt = m.group(1)
        src = m.group(2)
        if src.startswith('http') or src.startswith('data:'):
            return m.group(0)
        
        # 相对路径 → 绝对路径
        img_path = src_dir / src.lstrip('./')
        if not img_path.exists():
            # 尝试 images/ 子目录
            img_path = src_dir / 'images' / os.path.basename(src)
        
        if img_path.exists() and img_path.is_file():
            # 复制到 uploads/articles/
            ext = img_path.suffix.lower()
            if ext not in ('.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'):
                return m.group(0)
            
            new_name = f"{hashlib.md5(str(img_path).encode()).hexdigest()[:12]}_{img_path.name}"
            new_path = UPLOADS / new_name
            if not new_path.exists():
                shutil.copy2(img_path, new_path)
            return f'![{alt}](uploads/articles/{new_name})'
        
        return m.group(0)  # 找不到就保持原样
    
    return re.sub(r'!\[([^\]]*)\]\(([^)]+)\)', replace_img, content)

# ─── 分类 ───

def classify_article(title: str, content: str, cluster_category: str = '', tags_category: str = '', dir_name: str = '') -> tuple:
    """根据标题+内容+来源+目录名分类 → (一级, 二级)"""
    text = (title + ' ' + re.sub(r'<[^>]+>', '', content)[:300]).lower()
    title_lower = title.lower()
    dir_lower = dir_name.lower()
    
    # 1. Cluster 目录直接映射（强信号）
    cluster_map = {
        'AI工具推荐': ('trend', 'guide'),
        'AI设计': ('ai-create', 'design'),
        'AI视频生成': ('ai-create', 'video'),
        '热点话题': ('trend', 'news'),
        '文生图图生图': ('ai-create', 'image'),
        'Agentic-AI': ('agent', 'tools'),
        '内容创作社媒': ('ai-marketing', 'social'),
        'AI赚钱副业': ('ai-sell', 'monetization'),
    }
    if cluster_category and cluster_category in cluster_map:
        return cluster_map[cluster_category]
    
    # 2. Daily 目录名信号（权重高，因为目录名通常包含明确领域关键词）
    dir_signals = {
        ('ai-create', 'video'):    ['视频', 'video', '剪辑', '字幕', '配音', '动画', '漫剧', '数字人', 'seedance', 'libtv', 'runway', 'kling', 'sora', 'veo', 'cogvideo', 'pika', 'opencut', 'freecut', 'comecut', 'losslesscut', 'toonflow', 'deep-printfilm'],
        ('ai-create', 'design'):   ['设计', 'design', 'ui', 'figma', 'sketch', '配色', 'logo', '品牌', '排版', '原型', 'canva', 'excalidraw', 'penpot', 'presenton', 'brat', 'font', '字体', 'color', 'caesium', 'pixtrim'],
        ('ai-create', 'image'):    ['文生图', '图生图', '图片', 'image', '背景', '放大', '去水印', 'midjourney', 'stable', 'dall', 'rembg', 'real-esrgan', 'nano-banana', 'seedream', 'gpt-image'],
        ('ai-create', 'writing'):  ['写作', 'writing', '文案', '翻译', '摘要', 'pdf', 'ppt', '录屏', 'ocr', 'readpo', 'text2video', 'tldw'],
        ('ai-create', 'audio'):    ['音频', 'audio', '语音', '播客', 'tts', '音乐', '转录', 'whisper', 'voice', 'podcast', 'suno', 'buzz', 'mioSub'],
        ('ai-code', 'agent'):      ['agent', '智能体', 'mcp', 'langchain', 'refly', 'craft-agent', 'claude-code', 'codex', 'moltbot', 'skills'],
        ('ai-code', 'ide'):        ['ide', 'cursor', 'copilot', 'bolt', 'windsurf', 'replit', 'pi-web', 'deepseek-gui'],
        ('ai-ops', 'automation'):  ['自动化', 'automation', '工作流', 'workflow', 'n8n', 'dify', 'zapier', 'postiz', 'n8n', 'geoflow'],
        ('ai-build', 'nocode'):    ['建站', 'site-builder', '无代码', 'wordpress', 'webflow', 'landing', 'flink', 'bolt.diy', 'morphix'],
        ('ai-marketing', 'seo'):   ['seo', 'geo', '收录', 'indexnow', 'sitemap', 'geoflow'],
        ('ai-sell', 'monetization'): ['赚钱', '变现', '副业', 'roi', 'monetize', 'openbidkit'],
        ('ai-data', 'analytics'):  ['数据', 'data', '看板', 'dashboard', '可视化', 'quantagent'],
        ('ai-user', 'cdp'):        ['用户', 'user', '画像', 'cdp', '分群', 'crm', '隐私', 'privacy'],
        ('agent', 'tools'):        ['llm', '大模型', 'chatgpt', 'claude', 'deepseek', 'openai', 'anythingllm', 'gpt4all', 'lobe-chat'],
    }
    dir_scores = {}
    for (cat, sub), keywords in dir_signals.items():
        for kw in keywords:
            if kw in dir_lower:
                dir_scores[(cat, sub)] = dir_scores.get((cat, sub), 0) + 5  # 目录名权重高
            elif kw in title_lower:
                dir_scores[(cat, sub)] = dir_scores.get((cat, sub), 0) + 2
    
    if dir_scores:
        best = max(dir_scores.items(), key=lambda x: x[1])
        if best[1] >= 3:
            return best[0]
    
    # 3. 关键词匹配（正文 + 标题，阈值提高到 3）
    scores = {}
    for kw, (cat, sub) in KW_MAP.items():
        if kw in text:
            key = (cat, sub)
            scores[key] = scores.get(key, 0) + (2 if kw in title_lower else 1)
    
    if scores:
        best = max(scores.items(), key=lambda x: x[1])
        if best[1] >= 3:
            return best[0]
    
    # 4. Tags 旧分类映射
    tags_map = {
        'AI工具': ('agent', 'tools'),
        '在线工具': ('ai-ops', 'efficiency'),
    }
    if tags_category and tags_category in tags_map:
        return tags_map[tags_category]
    
    # 5. 默认
    return ('trend', 'guide')
    # 4. 默认
    return ('trend', 'guide')

# ─── Slug 生成 ───

# 英文关键词映射（高频中文→英文）
SLUG_MAP = {
    '深度测评': 'deep-review', '深度评测': 'deep-review', '深度拆解': 'deep-analysis',
    '选型': 'selection', '横评': 'comparison', '对比': 'vs', '评测': 'review',
    '教程': 'tutorial', '指南': 'guide', '入门': 'getting-started',
    '工具': 'tools', '推荐': 'top-picks', '排行': 'ranking', '清单': 'checklist',
    '工作流': 'workflow', '自动化': 'automation', '效率': 'efficiency',
    '设计': 'design', '视频': 'video', '图片': 'image', '写作': 'writing',
    '音频': 'audio', '翻译': 'translate', '建站': 'site-builder',
    '营销': 'marketing', '增长': 'growth', 'SEO': 'seo', '内容': 'content',
    '社交': 'social', '媒体': 'media', '运营': 'operations',
    '赚钱': 'monetization', '副业': 'side-hustle', '变现': 'monetize',
    '编程': 'coding', '开发': 'development', '开源': 'open-source',
    '数据': 'data', '分析': 'analytics', '可视化': 'visualization',
    '用户': 'user', '画像': 'persona', '留存': 'retention',
    '数字人': 'digital-human', '品牌': 'branding', '配色': 'color-palette',
    '字体': 'font', '排版': 'typography', 'Logo': 'logo', '图标': 'icon',
    'AI': 'ai', 'Agent': 'agent', 'LLM': 'llm', 'GPT': 'gpt',
    '播客': 'podcast', '字幕': 'subtitle', '配音': 'voiceover',
    '截图': 'screenshot', '录屏': 'screen-recording', '压缩': 'compress',
    '下载': 'download', '搜索': 'search', '浏览器': 'browser',
    '手机': 'mobile', '电脑': 'desktop', 'Mac': 'mac', 'Windows': 'windows',
    '免费': 'free', '开源': 'open-source', '付费': 'paid', '价格': 'pricing',
    '怎么选': 'how-to-choose', '怎么用': 'how-to-use', '好不好': 'is-it-good',
    '值得': 'worth-it', '平替': 'alternative', '替代': 'alternative',
    '大全': 'collection', '合集': 'collection', '合辑': 'collection',
    '新手': 'beginner', '进阶': 'advanced', '实战': 'practical',
    '原理': 'principles', '逻辑': 'logic', '方法论': 'methodology',
    '风险': 'risks', '注意事项': 'notes', 'FAQ': 'faq',
    '插件': 'plugin', '扩展': 'extension', '模板': 'template',
    '提示词': 'prompts', 'Prompt': 'prompt',
    '抖音': 'douyin', '小红书': 'xiaohongshu', '公众号': 'wechat-official',
    'B站': 'bilibili', '知乎': 'zhihu', '微信': 'wechat',
    'YouTube': 'youtube', 'GitHub': 'github', 'Notion': 'notion',
    '电商': 'ecommerce', '跨境': 'cross-border', '淘宝': 'taobao', 'Shopify': 'shopify',
    '一人公司': 'one-person-company', '超级个体': 'super-individual',
    '2026': '2026', '2025': '2025',
}

def generate_slug(title: str, dir_name: str = '') -> str:
    """从标题生成英文 slug"""
    slug_parts = []
    
    # 提取已有英文单词
    english_words = re.findall(r'[A-Za-z][A-Za-z0-9._-]+', title)
    for w in english_words:
        w = w.strip('.-_').lower()
        if len(w) >= 2 and w not in ('com', 'org', 'net', 'io', 'the', 'and', 'for', 'with'):
            slug_parts.append(w)
    
    # 中文关键词翻译
    for zh, en in SLUG_MAP.items():
        if zh in title and en not in slug_parts:
            slug_parts.append(en)
    
    # 如果 slug 太短，尝试从目录名提取
    if len(slug_parts) < 3 and dir_name:
        dir_words = re.findall(r'[A-Za-z][A-Za-z0-9_-]+', dir_name)
        for w in dir_words:
            w = w.strip('-_').lower()
            if len(w) >= 2 and w not in slug_parts and w not in ('https', 'http', 'www', 'com'):
                slug_parts.append(w)
    
    # 去重保持顺序
    seen = set()
    unique = []
    for p in slug_parts:
        if p not in seen:
            seen.add(p)
            unique.append(p)
    
    # 限制长度
    if len(unique) > MAX_SLUG_WORDS:
        unique = unique[:MAX_SLUG_WORDS]
    
    if not unique:
        unique = ['article', hashlib.md5(title.encode()).hexdigest()[:8]]
    
    slug = '-'.join(unique)
    slug = re.sub(r'[^a-z0-9-]', '', slug)
    slug = re.sub(r'-+', '-', slug).strip('-')
    return slug

# ─── 质量审核 ───

BANNED_PATTERNS = [
    r'nownexts\.com', r'A姐', r'NowX', r'ahhhhfs',
    r'赌博', r'博彩', r'色情', r'成人', r'裸聊',
    r'待补充', r'占位', r'placeholder', r'lorem ipsum',
]

def quality_check(title: str, content_html: str, content_md: str) -> tuple:
    """质量审核 → (pass: bool, reason: str)"""
    # 字数检查
    plain = re.sub(r'<[^>]+>', '', content_html)
    plain = re.sub(r'\s+', '', plain)
    if len(plain) < MIN_BODY_LEN:
        return False, f'字数不足({len(plain)}<{MIN_BODY_LEN})'
    
    # 占位符检查
    if len(plain) < 500 and ('待补充' in content_md or '占位' in content_md):
        return False, '占位stub'
    
    # 违禁内容
    text_lower = (title + content_md).lower()
    for pat in BANNED_PATTERNS:
        if re.search(pat, text_lower, re.IGNORECASE):
            if pat in ('nownexts\.com', 'A姐', 'NowX', 'ahhhhfs'):
                return True, f'旧站痕迹({pat})'  # 标记但不排除
            return False, f'违禁内容({pat})'
    
    # 旧站域名（链接）
    if re.search(r'https?://(?:www\.)?nownexts\.com', content_md):
        return True, '含旧站链接'  # 标记但不排除
    
    return True, 'OK'

# ─── 主流程 ───

def process_file(filepath: Path, source_type: str, cluster_cat: str = '', tags_cat: str = '') -> dict:
    """处理单个文件 → 文章 dict"""
    content = filepath.read_text(encoding='utf-8', errors='ignore')
    
    # 标题
    dir_name = filepath.parent.name if source_type != 'tags' else filepath.stem
    title = extract_title(content, source_type, dir_name)
    if not title:
        return None
    
    # MD → HTML
    html_content = md_to_html(content)
    
    # 复制图片
    html_content = copy_article_images(filepath.parent, html_content)
    
    # 质量审核
    ok, reason = quality_check(title, html_content, content)
    if not ok:
        return {'_rejected': True, '_reason': reason, '_title': title}
    
    # 分类
    dir_name = filepath.parent.name
    cat, sub = classify_article(title, content, cluster_cat, tags_cat, dir_name)
    
    # Slug
    slug = generate_slug(title, filepath.parent.name)
    
    # Excerpt
    plain = re.sub(r'<[^>]+>', '', html_content)
    plain = re.sub(r'\s+', ' ', plain).strip()
    excerpt = plain[:150].rsplit(' ', 1)[0] if len(plain) > 150 else plain
    
    # SEO
    seo_title = f'{title} | OpenFlow'
    seo_desc = excerpt[:120] if len(excerpt) > 120 else excerpt
    
    # Tags
    tags = []
    for kw, (c, s) in KW_MAP.items():
        if kw in title.lower() and kw not in tags:
            tags.append(kw)
    tags = tags[:5]
    if not tags:
        tags = [cat.replace('-', ' ')]
    
    # Article ID
    art_id = f'art-{hashlib.md5((title + slug).encode()).hexdigest()[:10]}'
    
    return {
        'id': art_id,
        'title': title,
        'slug': slug,
        'content': html_content,
        'excerpt': excerpt,
        'status': 'draft',  # 后续改为 published
        'author': AUTHOR,
        'category': f'{cat}/{sub}',
        'tags': tags,
        'seo_title': seo_title,
        'seo_desc': seo_desc,
        'created_at': '2026-08-23 00:00:00',
        'updated_at': '2026-08-23 00:00:00',
        '_source': str(filepath),
        '_source_type': source_type,
        '_quality': reason,
    }

def scan_daily() -> list:
    """扫描 Daily 220 篇"""
    articles = []
    base = DRAFTS / '01-Daily-新稿' / '未分类'
    if not base.exists():
        print(f'  ⚠️ Daily 目录不存在: {base}')
        return articles
    
    for d in sorted(base.iterdir()):
        if not d.is_dir():
            continue
        md_file = d / 'article.md'
        if not md_file.exists():
            # 尝试其他 .md 文件
            mds = list(d.glob('*.md'))
            if mds:
                md_file = mds[0]
            else:
                continue
        
        result = process_file(md_file, 'daily')
        if result:
            articles.append(result)
    
    return articles

def scan_cluster() -> list:
    """扫描 Cluster 207 篇"""
    articles = []
    base = DRAFTS / '02-Cluster-专题'
    if not base.exists():
        print(f'  ⚠️ Cluster 目录不存在: {base}')
        return articles
    
    for cat_dir in sorted(base.iterdir()):
        if not cat_dir.is_dir():
            continue
        cat_name = cat_dir.name
        # 去掉序号前缀
        cat_display = re.sub(r'^\d+[-_]', '', cat_name)
        
        for article_dir in sorted(cat_dir.iterdir()):
            if not article_dir.is_dir():
                continue
            md_file = article_dir / 'article.md'
            if not md_file.exists():
                mds = list(article_dir.glob('*.md'))
                if mds:
                    md_file = mds[0]
                else:
                    continue
            
            result = process_file(md_file, 'cluster', cluster_cat=cat_display)
            if result:
                articles.append(result)
    
    return articles

def scan_tags() -> list:
    """扫描 Tags-cleaned 103 篇"""
    articles = []
    base = DRAFTS / '03-Tags-历史稿-已清洗'
    if not base.exists():
        print(f'  ⚠️ Tags 目录不存在: {base}')
        return articles
    
    for cat_dir in sorted(base.iterdir()):
        if not cat_dir.is_dir():
            continue
        cat_display = re.sub(r'^\d+[-_]', '', cat_dir.name)
        
        for item in sorted(cat_dir.iterdir()):
            if item.is_dir():
                # 子目录中的 .md
                for md_file in item.glob('*.md'):
                    result = process_file(md_file, 'tags', tags_cat='AI工具')
                    if result:
                        articles.append(result)
            elif item.suffix == '.md':
                result = process_file(item, 'tags', tags_cat='AI工具')
                if result:
                    articles.append(result)
    
    return articles

def dedup(articles: list) -> list:
    """标题去重"""
    seen = {}
    unique = []
    for a in articles:
        if a.get('_rejected'):
            unique.append(a)
            continue
        key = re.sub(r'\s+', '', a['title'].lower())
        if key not in seen:
            seen[key] = True
            unique.append(a)
    return unique

def main():
    print('╔══════════════════════════════════════════╗')
    print('║  Website-Ready 文章批量处理               ║')
    print('╚══════════════════════════════════════════╝')
    
    # 1. 扫描
    print('\n📂 扫描文章...')
    daily = scan_daily()
    print(f'  Daily: {len(daily)} 篇')
    cluster = scan_cluster()
    print(f'  Cluster: {len(cluster)} 篇')
    tags = scan_tags()
    print(f'  Tags: {len(tags)} 篇')
    
    all_articles = daily + cluster + tags
    print(f'  总计: {len(all_articles)} 篇')
    
    # 2. 质量统计
    rejected = [a for a in all_articles if a.get('_rejected')]
    passed = [a for a in all_articles if not a.get('_rejected')]
    print(f'\n🔍 质量审核: 通过 {len(passed)}, 拒绝 {len(rejected)}')
    for r in rejected[:10]:
        print(f'  ❌ {r["_title"][:40]}: {r["_reason"]}')
    if len(rejected) > 10:
        print(f'  ... 还有 {len(rejected)-10} 篇')
    
    # 3. 去重
    deduped = dedup(passed)
    print(f'\n🔗 去重后: {len(deduped)} 篇')
    
    # 4. 分类统计
    cat_counts = {}
    for a in deduped:
        cat = a['category'].split('/')[0]
        cat_counts[cat] = cat_counts.get(cat, 0) + 1
    print(f'\n📊 分类分布:')
    for cat, count in sorted(cat_counts.items(), key=lambda x: -x[1]):
        icon = CATS.get(cat, {}).get('icon', '📦')
        name = CATS.get(cat, {}).get('name', cat)
        print(f'  {icon} {name}: {count} 篇')
    
    # 5. 输出
    # 去掉内部字段
    output = []
    for a in deduped:
        if a.get('_rejected'):
            continue
        clean = {k: v for k, v in a.items() if not k.startswith('_')}
        output.append(clean)
    
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(output, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'\n✅ 输出: {OUTPUT} ({len(output)} 篇)')
    
    # 6. Slug 唯一性检查
    slugs = [a['slug'] for a in output]
    dup_slugs = [s for s in slugs if slugs.count(s) > 1]
    if dup_slugs:
        print(f'\n⚠️ 重复 slug: {set(dup_slugs)}')
        # 自动修复：加后缀
        slug_counts = {}
        for a in output:
            s = a['slug']
            if s in slug_counts:
                slug_counts[s] += 1
                a['slug'] = f'{s}-{slug_counts[s]}'
            else:
                slug_counts[s] = 1
        OUTPUT.write_text(json.dumps(output, ensure_ascii=False, indent=2), encoding='utf-8')
        print(f'  已自动修复并重新写入')
    
    print('\n🎉 完成!')

if __name__ == '__main__':
    main()
