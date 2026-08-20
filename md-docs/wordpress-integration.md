# WordPress 站点接入 OpenFlow CDP 埋点

> 目标：让 one.example.com 等 WordPress 站点的访客行为实时进入 OpenFlow CDP 分析
> 方式：通过 WordPress 插件（Code Snippets / WPCode / 自定义插件）注入 SDK 脚本
> 前提：OpenFlow 后台已配置 `cors_origins` 包含该 WordPress 域名（设置 → 跨域埋点）

---

## 1. 前置确认

在 WordPress 侧操作前，先确认 OpenFlow 主站已开启跨域：

1. 登录 OpenFlow 后台 → **设置** → 找到 **「🌐 跨域埋点 (CORS)」**
2. 确认「允许的来源」包含你的 WordPress 域名，例如：
   ```
   https://one.example.com
   ```
   （多个域名用英文逗号分隔）
3. 保存

> 若你用的是子域名，需与后台配置完全一致（含 `https://`，不含结尾 `/`）。

---

## 2. 方式 B：插件注入（推荐，门槛最低）

以下两种插件任选其一，**无需改主题代码、无需 FTP**。

### 2.1 使用 Code Snippets 插件（免费）

1. WordPress 后台 → **插件 → 安装插件** → 搜索 `Code Snippets` → 安装并启用
2. 左侧菜单出现 **Code Snippets** → **添加代码片段**
3. 类型选 **「PHP 代码」**，标题写 `OpenFlow CDP 埋点`，代码填：

```php
add_action('wp_head', function () {
  echo '<script src="https://example.com/api/sdk.php" data-api="https://example.com/api/cdp.php" data-autotrack="1"></script>';
}, 1);
```

4. 把 `example.com` 替换成你的 OpenFlow 主站实际域名
5. **保存并激活**（勾选「只在站点前端运行」）

### 2.2 使用 WPCode 插件（含 Insert Headers and Footers）

1. WordPress 后台 → **插件 → 安装插件** → 搜索 `WPCode` → 安装并启用
2. 左侧 **Code Snippets → 页眉 & 页脚**（或 `+ 添加代码片段`）
3. 在 **页眉 (Header)** 区域粘贴：

```html
<script src="https://example.com/api/sdk.php" data-api="https://example.com/api/cdp.php" data-autotrack="1"></script>
```

4. 把 `example.com` 替换成你的 OpenFlow 主站实际域名
5. 保存

### 2.3 自定义插件（专业做法）

新建 `wp-content/plugins/openflow-cdp/openflow-cdp.php`：

```php
<?php
/**
 * Plugin Name: OpenFlow CDP 埋点
 * Description: 注入 OpenFlow CDP 追踪 SDK
 * Version: 1.0.0
 */
add_action('wp_head', function () {
  echo '<script src="https://example.com/api/sdk.php" data-api="https://example.com/api/cdp.php" data-autotrack="1"></script>';
}, 1);
```

在 WordPress 后台 → 插件 → 启用即可。

---

## 3. 参数说明

| 参数 | 说明 |
|------|------|
| `src` | SDK 脚本地址（用 `/api/sdk.php` 支持版本化/灰度；也可直接用 `/assets/cdp-track.js`） |
| `data-api` | 上报接口（必须指向 OpenFlow 主站的 `/api/cdp.php`） |
| `data-autotrack` | `1` 开启自动采集（页面/滚动/点击/表单/渠道） |
| `data-privacy` | `none` 完全禁用追踪（可选） |
| `data-batch-size` | 批量上报条数（可选，默认 5） |

---

## 4. 验证是否生效

### 4.1 浏览器控制台验证
1. 打开你的 WordPress 站点，按 `F12` → **Network** 面板
2. 刷新页面，过滤 `cdp`，应看到：
   - 请求 `sdk.php`（返回 JS）
   - 请求 `cdp.php`（POST，body 是 `action=track_batch` 或 `action=track`）
3. 若 `cdp.php` 请求的响应头包含 `Access-Control-Allow-Origin: https://one.example.com`，说明跨域成功

### 4.2 OpenFlow 后台验证
1. 打开 OpenFlow 后台 → **实时数据** → 刷新
2. 应看到来自该站点的 `page_view` 事件增长
3. 打开 **CDP → 维度分析** → 渠道分布，确认有新访客数据

### 4.3 常见问题排查
| 现象 | 原因 | 解决 |
|------|------|------|
| 控制台 `Access-Control-Allow-Origin` 错误 | 域名没加进 CORS 白名单 | 后台设置确认域名（含 https://） |
| 看不到 cdp.php 请求 | 主题启用了 CSP | 在 CSP 的 `script-src`/`connect-src` 加主站域名 |
| 只有匿名访客 | WordPress 用户未绑定 | 在登录后页面调用 `CDP.identify({email})` |
| `sdk.php` 404 | 主站域名错了 | 确认用 OpenFlow 实际部署域名 |

---

## 5. 高级：WordPress 用户身份打通

默认采集的是匿名访客。若要让 WP 用户与 CDP 画像关联，在 WP 的 `functions.php` 或插件里注入识别脚本：

```php
add_action('wp_footer', function () {
  if (is_user_logged_in()) {
    $u = wp_get_current_user();
    echo "<script>if (window.CDP) CDP.identify({ email: '" . esc_js($u->user_email) . "', name: '" . esc_js($u->display_name) . "' });</script>";
  }
});
```

这样 WP 登录用户会以 `email` 作为身份标识并入 CDP 画像，跨站跨设备打通。

---

## 6. 多站点监控

后台 `cors_origins` 支持逗号分隔，可同时监控多个站点：
```
https://one.example.com, https://two.example.com, https://blog.example.org
```
每个站点的访客都会进入同一个 CDP，可在维度分析/渠道归因中按 `referrer_domain` 或 `utm_source` 区分来源站点。
