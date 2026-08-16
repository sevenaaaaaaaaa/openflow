# OpenFlow XMP 平台 — 开发者文档

一个基于 PHP + JSON/SQLite 的**网站增长与营销运营平台（XMP）**，从内容管理（CMS）到 SEO/GEO、AI Agent、线索管理（CRM）、营销自动化（MA）、舆情监测的一体化系统。

## 架构总览

```
openflow-site-upload/
├── admin/          后台管理（页面、API 入口）
│   ├── config.php  核心配置 + RBAC + 通知 + 侧边栏
│   ├── *.php       各功能管理页
│   └── seo-functions.php / review-lib.php / ma-sync-lib.php / survey-lib.php / nps-lib.php
├── api/            前端/第三方 HTTP API（34 个）
├── lib/            核心业务库（领域逻辑）
│   ├── MemberSystem.php    前台用户体系
│   ├── ShopSystem.php      商城 + 虎皮椒支付
│   ├── SubscriptionSystem.php  付费订阅
│   ├── AutomationSystem.php    营销自动化
│   ├── CanvasSystem.php        画布流程引擎
│   ├── SentimentSystem.php     舆情引擎
│   ├── GeoSystem.php           GEO 话题监控
│   ├── AnalyticsSystem.php     运营分析（漏斗/RFM）
│   ├── DashboardSystem.php     经营驾驶舱
│   ├── ProfilingSystem.php     用户画像/标签
│   ├── SeoConsole.php          SEO 站长工具
│   ├── NotifyChannels.php      企业微信/飞书/WhatsApp 通知
│   ├── Gamification.php        积分等级
│   ├── PluginSystem.php        插件引擎
│   └── Database.php            SQLite 数据库层
├── data/           JSON 数据（flat-file）
│   ├── db/openflow.db   SQLite（高频/关联数据）
│   └── */*.json          JSON（业务数据）
├── bin/of           CLI 入口
├── mcp-server.php   MCP Server（AI 接入）
├── assets/inject.js 前端注入器（埋点/脚本/AB测试）
└── *.php / *.html   前端公开页
```

## 数据分层

| 层 | 存储 | 用途 |
|---|---|---|
| 业务数据 | `data/*.json` | 文章、订单、会员、订阅、社区（低频读写） |
| 高频数据 | `data/db/openflow.db` (SQLite) | 行为事件、舆情结果（高并发写入） |

## CLI 使用

```bash
php bin/of articles list       # 列出文章
php bin/of article get <id>    # 查看文章
php bin/of article publish <id> # 发布文章
php bin/of members list        # 会员列表
php bin/of orders revenue      # 收入统计
php bin/of sentiment <topicId> # 舆情采集
php bin/of cron run            # 执行定时任务
```

## MCP Server（AI 接入）

支持 **stdio** 和 **HTTP** 两种传输：

```bash
# stdio（Claude Code / Codex 子进程调用）
php mcp-server.php

# HTTP/SSE
php -S 0.0.0.0:8080 mcp-server.php --http
```

内置 10 个工具：`articles_list`、`article_get`、`article_create`、`article_publish`、`members_list`、`leads_count`、`orders_revenue`、`search`、`sentiment_scan`、`sentiment_topics`

### 接入 Claude Code

```json
// .mcp.json
{
  "mcpServers": {
    "openflow": {
      "command": "php",
      "args": ["/path/to/openflow/mcp-server.php"]
    }
  }
}
```

### 接入 opencode

```json
// opencode.json
{
  "mcp": {
    "openflow": {
      "type": "stdio",
      "command": "php",
      "args": ["/path/to/openflow/mcp-server.php"]
    }
  }
}
```

## 插件开发指南

插件通过 `PluginSystem` 的 hooks/filters 扩展。

### 目录结构

```
plugins/my-plugin/
├── plugin.json   # 清单
└── plugin.php    # 入口（注册 hooks）
```

### plugin.json

```json
{
  "id": "my-plugin",
  "name": "我的插件",
  "version": "1.0.0"
}
```

### plugin.php

```php
<?php
// 文章保存后触发
PluginSystem::add_action('article_saved', function($id, $article) {
    // 你的逻辑
});

// 文章保存前修改数据
PluginSystem::add_filter('article_save_before', function($article) {
    $article['custom_field'] = 'value';
    return $article;
});

// 侧边栏加菜单
PluginSystem::add_action('admin_sidebar_menu', function($current) {
    echo '<a href="my-plugin-page.php">我的插件</a>';
});
```

### 可用 Hooks

| Hook | 类型 | 触发点 |
|---|---|---|
| `plugin_loaded` | action | 插件加载完成 |
| `article_save_before` | filter | 文章保存前 |
| `article_saved` | action | 文章保存后 |
| `page_save_before` | filter | 页面保存前 |
| `page_saved` | action | 页面保存后 |
| `form_submitted` | action | 表单提交后 |
| `articles_list_before_output` | filter | 文章列表输出前 |
| `article_output_before` | filter | 单篇文章输出前 |
| `admin_sidebar_menu` | action | 侧边栏渲染 |

### 插件管理器

后台「系统 → 插件管理」支持：安装（GitHub/URL）、启用/停用、卸载。

## HTTP API 概览

34 个 API 在 `api/` 目录，主要分组：
- 内容：`articles.php`、`pages.php`、`import-article.php`
- 用户：`member.php`、`mp-login.php`
- 商城：`shop.php`、`ambassador.php`
- 调研：`survey-submit.php`、`nps-submit.php`
- 营销：`form-submit.php`、`track.php`、`ab-event.php`
- 工具：`search.php`、`batch-import.php`、`stock.php`、`sentiment.php`（舆情）、`community.php`
