# OpenFlow 插件开发指南（v2）

> 面向第三方开发者。OpenFlow 插件 = `plugins/{插件ID}/` 目录下的一个 `plugin.php`。
> 启用后每次请求自动加载，通过 `PluginSystem` 注册钩子 / 菜单 / API / 插槽 / 定时任务。

## 1. 插件结构

```
plugins/
└── my-plugin/
    ├── plugin.php      ← 必须，入口（每次请求加载）
    ├── plugin.json     ← 可选，元信息（name/version/author/description）
    └── view.php        ← 可选，自定义页面
```

`plugin.json` 示例：

```json
{ "name": "我的插件", "version": "1.0.0", "author": "you", "description": "做什么用" }
```

在后台「系统 → 插件管理」启用后生效；**禁用即全部失效**（含 API 路由）。

## 2. 钩子（v1 已有）

```php
PluginSystem::add_filter('article_save_before', function ($article) {
    $article['tags'][] = '自动标签';
    return $article;   // filter 必须返回修改后的值
});

PluginSystem::add_action('article_saved', function ($id, $article) {
    // 副作用：发通知、写日志、推 Webhook
});
```

常用钩子：`article_save_before` / `article_saved` / `form_submitted` / `admin_sidebar_menu`。

## 3. 插件 API 路由（v2 新增）

```php
PluginSystem::register_api_route('my-plugin', 'GET', 'stats', function () {
    return PluginApiResponse::json(['count' => 42]);
});
```

- 端点：`GET /api/plugin/my-plugin/stats`
- 回调返回 `PluginApiResponse::json($data)` 自动输出 JSON；返回 `null` 则自己输出。
- 选项：`['auth' => 'admin']` 要求后台登录（**默认值**，未声明 auth 的路由一律需要登录——安全从严），`['auth' => 'member']` 要求会员登录，`['auth' => 'none']` 显式公开（只读端点才用）。
- 插件未启用 → 404；回调抛异常 → 500（不会拖垮整站）。

## 4. 后台菜单 + 设置页（v2 新增）

```php
PluginSystem::register_admin_menu(['id' => 'my-plugin', 'label' => '我的插件', 'sort' => 50]);
PluginSystem::register_admin_page('my-plugin', function ($pluginId) {
    echo '<div class="card"><h2>设置</h2>...</div>';
});
```

- 菜单出现在后台侧栏「插件」区，链接到 `/xmp/plugin/my-plugin`。
- 设置页自带登录保护 + 侧栏外壳，回调只管输出内容。
- 可用后台组件类：`card` / `btn` / `field` / `msg()` / `csrf_field()`。

## 5. 前台插槽与资产（v2 新增）

```php
PluginSystem::register_front_slot('head', fn() => print('<meta name="x" content="y">'));
PluginSystem::register_front_slot('article_after', function ($ctx) {
    echo '<p>文章 ID：' . ($ctx['article']['id'] ?? '') . '</p>';
});
PluginSystem::register_front_asset('css', '/plugins/my-plugin/style.css');
PluginSystem::register_front_asset('js', '/plugins/my-plugin/app.js', ['defer' => true]);
```

可用插槽：`head`（</head> 前）、`footer_before`（页脚上方）、`body_end`（页脚后）、`article_after`（文章正文后，带 `$ctx['article']`）。

## 6. 定时任务（v2 新增）

```php
PluginSystem::register_schedule('my-cleanup', 'daily', function () {
    // 每天跑一次（由 api/cron.php 驱动，需服务器 cron 每分钟请求 /api/cron.php?key=...）
});
```

间隔：`every`（每次 cron）/ `5min` / `15min` / `hourly` / `6hours` / `daily` / `weekly`。执行状态存 `data/plugin-schedule.json`。

## 7. 工具函数速查

| 函数 | 用途 |
|---|---|
| `json_read($path)` / `json_write($path, $data)` | 读写 JSON 数据（插件数据建议存 `data/plugins/{id}/`） |
| `site_config_get($key, $default)` | 读站点配置 |
| `notify_channels_send($title, $body, $link)` | 发通知到已配置渠道 |
| `htmlspecialchars()` | 输出转义（必须） |

## 8. 注意事项

- **安全**：所有用户输入必须转义输出；写操作必须 `csrf_verify()`。
- **性能**：`plugin.php` 每次请求都加载，只做注册，别在顶层跑重逻辑。
- **隔离**：回调异常被捕获，不影响主站；但请自己处理好边界。
- **参考**：`plugins/example-plugin/plugin.php` 是覆盖全部 API 的活示例。

---

## 9. 从 0 写一个插件（实操）

以「文章保存时自动打标签 + 提供一个只读 API + 后台一个小设置页」为例。

### 9.1 建目录与元信息

```bash
mkdir -p plugins/auto-tagger
```

`plugins/auto-tagger/plugin.json`：

```json
{
  "id": "auto-tagger",
  "name": "自动打标",
  "version": "1.0.0",
  "author": "you",
  "description": "文章保存时按关键词自动补标签；提供 /auto-tagger/tags 只读接口",
  "enabled_by_default": false,
  "permissions": ["hooks", "api", "log"]
}
```

> `permissions` 决定插件能调用哪些能力（`hooks`/`api`/`http`/`log`/`config`/`schedule`/`slot`）。
> 权限在 `PluginSystem::plugin_can()` 处强制，插件越权会被拒绝并记日志。

### 9.2 入口 `plugins/auto-tagger/plugin.php`

```php
<?php
/**
 * 自动打标插件 —— 演示：filter 钩子 + 只读 API + 后台设置页
 */
if (!class_exists('PluginSystem')) return;

$PLUGIN_ID = 'auto-tagger';

// ① filter：文章保存前补标签（必须 return 修改后的值）
PluginSystem::add_filter('article_save_before', function (array $article) {
    $kw = ['增长', '转化', '留存'];
    $tags = $article['tags'] ?? [];
    $text = ($article['title'] ?? '') . ($article['content'] ?? '');
    foreach ($kw as $k) {
        if (mb_strpos($text, $k) !== false && !in_array($k, $tags, true)) $tags[] = $k;
    }
    $article['tags'] = array_values(array_unique($tags));
    return $article;
});

// ② 只读 API：GET /api/plugin/auto-tagger/tags
PluginSystem::register_api_route($PLUGIN_ID, 'GET', 'tags', function () {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'keywords' => ['增长', '转化', '留存']], JSON_UNESCAPED_UNICODE);
});

// ③ 后台设置页（出现在「系统 → 插件管理」该插件下）
PluginSystem::register_admin_page($PLUGIN_ID, function () {
    echo '<h2>自动打标</h2><p class="sub">关键词可在代码中调整；命中即自动补标签。</p>';
});
```

### 9.3 启用与自检

1. 后台「系统 → 插件管理」找到该插件 → **启用**
2. 编辑一篇文章保存 → 检查标签是否自动补上
3. `curl https://nownexts.com/api/plugin/auto-tagger/tags` → 应返回 JSON
4. 禁用插件 → 上述行为应全部消失（**禁用即全部失效**是契约）

### 9.4 可用的扩展点（速查）

| 能力 | API |
|---|---|
| 事件钩子 | `add_action($hook, $cb)` / `add_filter($hook, $value, ...)` |
| API 路由 | `register_api_route($id, 'GET\|POST', 'path', $cb)` → `/api/plugin/{id}/path` |
| 后台菜单/页面 | `register_admin_menu([...])` / `register_admin_page($id, $cb)` |
| 前台插槽 | `register_front_slot('head\|body_end', $cb)` + `register_front_assets('css\|js')` |
| 定时任务 | `register_schedule(...)`（由 `/api/cron.php` 驱动） |
| 区块类型 | `register_block($pluginId, ['name'=>..., 'render'=>callable])`（进建站模块面板） |

### 9.5 参考实现（本仓现有 6 个插件）

| 插件 | 适合学什么 |
|---|---|
| `plugins/insight-flow/` | HMAC 出站推送 + 回读 API + 定时任务（**旁路 + 超时 + 失败静默**的范例） |
| `plugins/userloop-tracker/` | 前台插槽注入脚本（`body_end`）+ 权限声明 |
| `plugins/seo-enhancer/` | 内容后处理（filter） |
| `plugins/deal-notifier/` | 事件 → 通知渠道 |
| `plugins/event-firewall/` | 请求侧拦截 |
| `plugins/example-plugin/` | 最小骨架 |

### 9.6 发布到生态市场

1. 把插件目录打包（含 `plugin.json`）
2. 后台「生态市场」或 `admin/plugins.php` 上传安装（`PluginSystem::install_plugin()`）
3. 上架后其他人可在 `/marketplace?type=plugin` 看到并安装

### 9.7 调试

| 手段 | 说明 |
|---|---|
| `PluginSystem::plugin_can($id, 'perm')` | 排查权限不足 |
| `plugin_backups($id)` / `rollback_plugin($id)` | 安装/升级前自动备份，可回滚 |
| 日志 | 声明了 `log` 权限的插件写日志；失败路径要显式记录 |
| 关掉插件对照 | 行为消失即确认是插件所致（契约） |
