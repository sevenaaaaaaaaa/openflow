<?php
/**
 * Dynamic robots.txt — 标准爬虫 + AI 爬虫友好规则
 * 参考：Google AI、OpenAI GPTBot、ClaudeBot、PerplexityBot、Cohere、Meta AI 等
 */
header('Content-Type: text/plain; charset=utf-8');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host;
?>
# ─── OpenFlow robots.txt ───────────────────────────
# 标准搜索引擎：完全开放（内容站需要被收录）
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /data/
Disallow: /api/
Disallow: /uploads/
Disallow: /member
Disallow: /login
Disallow: /thank-you
Disallow: /cart
Disallow: /checkout
Disallow: /*?view=
Disallow: /*sort=
Disallow: /*filter=

# 主搜索引擎（显式声明，保证优先权）
User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /

User-agent: Baiduspider
Allow: /

User-agent: Sogou web spider
Allow: /

User-agent: 360Spider
Allow: /

# ─── AI 爬虫：允许抓取（内容值得被 AI 引用） ───
# OpenAI GPT / ChatGPT
User-agent: GPTBot
Allow: /

# Anthropic Claude
User-agent: ClaudeBot
Allow: /
User-agent: anthropic-ai
Allow: /

# Perplexity
User-agent: PerplexityBot
Allow: /

# Google AI（Gemini / AI Overviews）
User-agent: Google-Extended
Allow: /

# Cohere
User-agent: cohere-ai
Allow: /

# Meta AI
User-agent: meta-externalagent
Allow: /

# Amazon / Alexa AI
User-agent: Amazonbot
Allow: /

# Apple
User-agent: Applebot
Allow: /
User-agent: Applebot-Extended
Allow: /

# Microsoft Copilot
User-agent: CopilotBot
Allow: /
User-agent: Bingbot-Extended
Allow: /

# xAI / Grok
User-agent: Xbot
Allow: /

# ByteDance 豆包 / 抖音搜索
User-agent: Bytespider
Allow: /

# 知乎 / 其他中文 AI
User-agent: zhihu-crawler
Allow: /

# ─── Sitemap ───────────────────────────────────────
Sitemap: <?=$base?>/sitemap.xml

# LLMs.txt（给 LLM 的知识索引）
# 见 <?=$base?>/llms.txt
