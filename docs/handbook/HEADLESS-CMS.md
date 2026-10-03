# 无头 CMS 对接指南

> 把 OpenFlow 当内容后台，用自己的前端框架（React / Vue / Next.js / Nuxt 等）渲染内容。

---

## 一、架构概览

OpenFlow 支持两种使用方式：

| 模式 | 说明 | 适合场景 |
|------|------|----------|
| **传统 CMS** | PHP 直出 HTML（当前默认） | 快速上线、SEO 友好、无前端工程 |
| **无头 CMS** | 通过 API 取 JSON 数据，前端自渲染 | 自定义设计、SPA/SSR、多端复用 |

无头模式下，后台负责内容管理、用户系统、SEO、AI 生成、营销自动化等，前端只管取数据和渲染。

```
┌─────────────────┐      JSON API       ┌──────────────────┐
│   OpenFlow 后台  │ ◄─────────────────► │  你的前端应用     │
│  (内容/用户/AI)  │   /api/public-content│  React/Vue/...   │
└─────────────────┘   /api/landing      └──────────────────┘
        │              /api/articles             │
        ▼              /api/pages                ▼
  data/*.json         /api/search          自定义组件渲染
  data/articles/      /api/seo
```

---

## 二、API 端点速查

### 2.1 内容获取（无需登录）

| 端点 | 方法 | 用途 | 关键参数 |
|------|------|------|----------|
| `/api/public-content` | GET | 统一内容接口 | `type`, `limit`, `page`, `category`, `search` |
| `/api/articles` | GET | 文章列表/详情 | `type=list/get`, `slug`, `category`, `tag`, `search` |
| `/api/landing` | GET | 落地页（含区块） | `slug` |
| `/api/pages` | GET | 固定页面 | `page=index/product/capability/courses/about` |
| `/api/search` | GET | 全局搜索 | `q` |
| `/api/site-structure` | GET | 导航/页脚配置 | — |
| `/api/seo` | GET | SEO meta 标签 | `page` 或 `type=article&id=` |

### 2.2 用户交互（需登录或带凭证）

| 端点 | 方法 | 用途 |
|------|------|------|
| `/api/member` | POST | 注册/登录/登出（`action=register/login/logout`） |
| `/api/comment` | POST | 评论 |
| `/api/bookmark` | POST | 收藏 |
| `/api/follow` | POST | 关注 |
| `/api/cart` | GET/POST | 购物车 |
| `/api/course` | POST | 课程操作（收藏/评分/笔记） |

### 2.3 AI / 增长

| 端点 | 方法 | 用途 |
|------|------|------|
| `/api/ai-generate` | POST | AI 内容生成 |
| `/api/ai-landing` | POST | AI 一键生成落地页 |
| `/api/ai-repurpose` | POST | 一写多编译 |
| `/api/mcp` | POST | MCP 协议（AI Agent 接入） |

---

## 三、数据结构

### 3.1 文章

```json
{
  "id": "ai_draft_20260814_200407_9cf05",
  "title": "网站增长的核心洞察",
  "slug": "website-growth-value-compound",
  "content": "<h1>标题</h1><p>正文 HTML...</p>",
  "excerpt": "纯文本摘要",
  "status": "published",
  "author": "AI 增长引擎",
  "category": "ai-create",
  "tags": ["AI", "增长"],
  "cover": "uploads/cover.webp",
  "seo_title": "SEO 标题",
  "seo_desc": "SEO 描述",
  "views": 1234,
  "created_at": "2026-08-14 20:04:07",
  "updated_at": "2026-08-14 20:04:07"
}
```

**content 字段说明**：正文是 HTML 字符串，可能包含短代码（如 `[card type="course" id="xxx"]`）。前端可以直接渲染 HTML，或用正则剥离短代码。

### 3.2 落地页区块

落地页数据通过 `/api/landing?slug=xxx` 获取，返回页面配置 + 区块数组：

```json
{
  "ok": true,
  "page": {
    "id": "landing_demo_001",
    "slug": "content-overview",
    "title": "内容引擎总览",
    "seo_title": "...",
    "seo_desc": "...",
    "aggregate_tags": ["内容", "SEO"],
    "layout": "grid"
  },
  "articles": [
    { "id": "...", "title": "...", "slug": "...", "excerpt": "...", "cover": "..." }
  ]
}
```

模块化建站页面（`/b/{slug}`）的区块结构：

```json
{
  "blocks": [
    {
      "_type": "hero",
      "_key": "a1b2c3d4",
      "title": "一人公司的增长打法",
      "subtitle": "都在这里",
      "content": "描述文本",
      "button_text": "开始学习",
      "button_url": "/courses",
      "bg_color": "#fff"
    },
    {
      "_type": "features",
      "_key": "e5f6g7h8",
      "columns": [
        { "title": "内容引擎", "content": "AI 自动爬热点...", "icon": "📝" },
        { "title": "数据洞察", "content": "CDP 用户画像...", "icon": "📊" }
      ]
    }
  ]
}
```

### 3.3 18 种内置区块类型

| _type | 核心字段 | 说明 |
|-------|----------|------|
| `hero` | title, subtitle, content, button_text, button_url, bg_color | Hero 大标题区 |
| `features` | columns[{title, content, icon}], layout | 功能列表 |
| `cta` | title, subtitle, button_text, button_url | 行动号召 |
| `text` | content (HTML) | 文本段落 |
| `image-text` | image_url, image_alt, content, image_position | 图文混排 |
| `stats` | items[{value, label}] | 数据指标 |
| `testimonials` | items[{quote, author, role, avatar}] | 客户证言 |
| `faq` | items[{question, answer}] | 常见问题 |
| `pricing` | columns[{name, price, features[], highlighted}] | 价格表 |
| `form` | form_slug | 表单嵌入 |
| `newsletter` | title, subtitle | 订阅表单 |
| `video` | video_url, caption | 视频嵌入 |
| `contact` | title, subtitle | 联系表单 |
| `gallery` | images[{url, alt}] | 图片画廊 |
| `timeline` | items[{date, title, content}] | 时间线 |
| `comparison` | columns[{title, rows[{label, value}]}] | 对比表 |
| `logo-wall` | items[{name, logo_url}] | Logo 墙 |
| `module` | module_id | 引用可复用模块 |

---

## 四、前端对接示例

### 4.1 Vanilla JS — 取文章列表

```javascript
// 取最新 12 篇文章
const res = await fetch('https://nownexts.com/api/public-content?type=articles&limit=12');
const { items, total, page } = await res.json();

items.forEach(article => {
  console.log(article.title, article.slug, article.cover);
});
```

### 4.2 Vanilla JS — 取单篇文章

```javascript
const res = await fetch('https://nownexts.com/api/articles?type=get&slug=website-growth-value-compound');
const { article } = await res.json();

// article.content 是 HTML，直接插入 DOM
document.getElementById('content').innerHTML = article.content;
```

### 4.3 React — 文章列表组件

```jsx
import { useState, useEffect } from 'react';

function ArticleList({ category, limit = 12 }) {
  const [articles, setArticles] = useState([]);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    const params = new URLSearchParams({ type: 'articles', limit, ...(category && { category }) });
    fetch(`/api/public-content?${params}`)
      .then(r => r.json())
      .then(data => { setArticles(data.items); setTotal(data.total); });
  }, [category, limit]);

  return (
    <div className="article-grid">
      {articles.map(a => (
        <a key={a.id} href={`/article/${a.slug}`} className="article-card">
          {a.cover && <img src={a.cover} alt={a.title} />}
          <h3>{a.title}</h3>
          <span className="category">{a.category}</span>
          <time>{a.created_at?.slice(0, 10)}</time>
        </a>
      ))}
    </div>
  );
}
```

### 4.4 React — 落地页区块渲染

```jsx
const BlockMap = {
  hero: ({ title, subtitle, button_text, button_url }) => (
    <section className="hero">
      <h1>{title}</h1>
      <p>{subtitle}</p>
      {button_text && <a href={button_url}>{button_text}</a>}
    </section>
  ),

  features: ({ columns }) => (
    <section className="features">
      {columns?.map((col, i) => (
        <div key={i} className="feature-card">
          <span className="icon">{col.icon}</span>
          <h3>{col.title}</h3>
          <p>{col.content}</p>
        </div>
      ))}
    </section>
  ),

  cta: ({ title, subtitle, button_text, button_url }) => (
    <section className="cta">
      <h2>{title}</h2>
      <p>{subtitle}</p>
      <a href={button_url} className="btn">{button_text}</a>
    </section>
  ),

  faq: ({ items }) => (
    <section className="faq">
      {items?.map((item, i) => (
        <details key={i}>
          <summary>{item.question}</summary>
          <p>{item.answer}</p>
        </details>
      ))}
    </section>
  ),

  stats: ({ items }) => (
    <section className="stats">
      {items?.map((item, i) => (
        <div key={i} className="stat">
          <strong>{item.value}</strong>
          <span>{item.label}</span>
        </div>
      ))}
    </section>
  ),

  // ... 其他区块类型
};

function LandingPage({ slug }) {
  const [page, setPage] = useState(null);

  useEffect(() => {
    fetch(`/api/landing?slug=${slug}`)
      .then(r => r.json())
      .then(data => setPage(data.page));
  }, [slug]);

  if (!page) return <div>Loading...</div>;

  return (
    <main>
      {(page.blocks || []).map(block => {
        const Component = BlockMap[block._type];
        return Component
          ? <Component key={block._key} {...block} />
          : <div key={block._key}>未知区块: {block._type}</div>;
      })}
    </main>
  );
}
```

### 4.5 Vue 3 — 文章详情

```vue
<template>
  <article v-if="article">
    <h1>{{ article.title }}</h1>
    <div class="meta">
      <span>{{ article.author }}</span>
      <time>{{ article.created_at?.slice(0, 10) }}</time>
      <span class="category">{{ article.category }}</span>
    </div>
    <img v-if="article.cover" :src="article.cover" :alt="article.title" />
    <div class="content" v-html="article.content"></div>
    <div class="tags">
      <span v-for="tag in article.tags" :key="tag" class="tag">{{ tag }}</span>
    </div>
  </article>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const article = ref(null);

onMounted(async () => {
  const res = await fetch(`/api/articles?type=get&slug=${route.params.slug}`);
  const data = await res.json();
  article.value = data.article;
});
</script>
```

### 4.6 Next.js — SSG 生成静态页面

```javascript
// pages/article/[slug].js
export async function getStaticPaths() {
  const res = await fetch('https://nownexts.com/api/public-content?type=articles&limit=100');
  const { items } = await res.json();
  return {
    paths: items.map(a => ({ params: { slug: a.slug } })),
    fallback: 'blocking',
  };
}

export async function getStaticProps({ params }) {
  const res = await fetch(`https://nownexts.com/api/articles?type=get&slug=${params.slug}`);
  const { article } = await res.json();
  if (!article) return { notFound: true };
  return { props: { article }, revalidate: 3600 }; // ISR: 每小时重新验证
}

export default function ArticlePage({ article }) {
  return (
    <>
      <Head>
        <title>{article.seo?.title || article.title}</title>
        <meta name="description" content={article.seo?.desc || article.excerpt} />
      </Head>
      <article>
        <h1>{article.title}</h1>
        <div dangerouslySetInnerHTML={{ __html: article.content }} />
      </article>
    </>
  );
}
```

---

## 五、搜索与筛选

```javascript
// 按分类筛选
fetch('/api/public-content?type=articles&category=ai-create&limit=20');

// 按关键词搜索
fetch('/api/search?q=增长引擎');

// 按标签筛选
fetch('/api/articles?type=list&tag=AI');

// 分页
fetch('/api/public-content?type=articles&limit=12&page=2');
```

---

## 六、SEO 对接

前端渲染时需要注入 SEO meta 标签。有两种方式：

### 方式一：从内容 API 自带的 seo 字段取

```javascript
const { article } = await fetch('/api/articles?type=get&slug=xxx').then(r => r.json());
// article.seo_title, article.seo_desc
```

### 方式二：用 SEO API 取完整的 meta HTML

```javascript
const res = await fetch('/api/seo?type=article&id=xxx');
const { meta_html } = await res.json();
// meta_html 包含 <title>, <meta>, <link canonical>, JSON-LD 等完整标签
document.head.insertAdjacentHTML('beforeend', meta_html);
```

---

## 七、MCP 接入（AI Agent）

如果你的前端需要让 AI Agent 读写内容，走 MCP 协议：

```javascript
// HTTP SSE 模式连接
const response = await fetch('https://nownexts.com/mcp', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_API_KEY',
  },
  body: JSON.stringify({
    jsonrpc: '2.0',
    id: 1,
    method: 'tools/list',
  }),
});
```

可用工具：`articles_list`, `article_get`, `article_create`, `article_publish`, `search`, `growth_next_best_action` 等 23 个。

---

## 八、CORS 跨域

所有 `/api/*` 端点已配置 CORS 头，支持跨域请求：

```
Access-Control-Allow-Origin: [动态匹配请求 Origin]
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, X-CSRF-Token
Access-Control-Allow-Credentials: true
```

前端直接 `fetch` 即可，无需额外处理。

---

## 九、常见问题

**Q: 文章 content 里的短代码怎么处理？**
A: 短代码格式 `[card type="course" id="xxx"]`，前端可以用正则剥离或替换为自定义组件。如果不需要短代码，直接用 `content` 渲染 HTML 即可。

**Q: 落地页区块没有 blocks 字段？**
A: `/api/landing` 返回的是标签聚合落地页（按标签拉文章），不含 blocks。模块化建站页面的区块需要直接读 `data/builder-pages.json` 或用后台 API。

**Q: 如何让前端内容实时更新？**
A: API 默认无缓存（每次请求读最新数据）。如果前端用了 ISR/SSG，设置合理的 `revalidate` 时间即可。

**Q: 图片 URL 怎么拼？**
A: `cover` 字段可能是相对路径（如 `uploads/cover.webp`），前端拼上站点域名 `https://nownexts.com/` 即可。如果是完整 URL 则直接使用。
