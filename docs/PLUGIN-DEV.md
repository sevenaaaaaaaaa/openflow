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
- 选项：`['auth' => true]` 要求后台登录，`['roles' => ['admin']]` 限定角色。
- 插件未启用 → 404；回调抛异常 → 500（不会拖垮整站）。

## 4. 后台菜单 + 设置页（v2 新增）

```php
PluginSystem::register_admin_menu(['id' => 'my-plugin', 'label' => '我的插件', 'order' => 50]);
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

间隔：`minutely` / `five_minutes` / `hourly` / `daily` / `weekly`。执行状态存 `data/cron-state.json`。

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
