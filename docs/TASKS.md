# OpenFlow 开发任务清单（Claude 执行）

> 本文档包含可直接交给 Claude 执行的开发任务。每个任务有明确的范围、文件路径、验收标准。
> 执行顺序：P0 先行，P1 次之，P2 末位。

---

## P0-A：系统联动（打通 CRM ↔ MA ↔ CDP）

### 任务 A1：CRM 事件接入 FlowSystem

**背景**：当前 FlowSystem（`lib/FlowSystem.php`）只连接 行为事件→CDP→MA，**CRM 线索阶段变化不会触发 MA 流程，也不会写入 CDP 标签**。

**目标**：CRM 线索状态变化（新建/跟进/赢单/输单）→ 自动触发 MA 流程 + 写入 CDP 标签。

**涉及文件**：
- `lib/FlowSystem.php` — 统一事件总线（~237 行）
- `lib/CrmSystem.php` — CRM 系统（~259 行），有 `crm_update_lead()` 函数
- `lib/ShopSystem.php` — 商城系统，有订单状态变化

**实现方案**：
1. 在 `CrmSystem.php` 的 `crm_update_lead()` 函数末尾，当 `stage` 字段变化时，调用 `FlowSystem::emit('crm_stage_change', ...)` 发射事件
2. 在 `FlowSystem.php` 的 `emit()` 函数里，新增对 `crm_stage_change` 事件的处理：
   - 写入 CDP 标签（如 `stage:跟进` → 给用户加"跟进中"标签）
   - 触发匹配的自动化流程（MA 中配置的触发器）
3. 在 `ShopSystem.php` 的订单状态变化（支付成功/退款）时，同样发射 `order_status_change` 事件

**验收标准**：
- 修改线索阶段后，对应 CDP 用户自动获得新标签
- MA 画布中可用"CRM 线索阶段变化"作为触发器
- 不影响现有 CRM 功能

---

### 任务 A2：MA 流程可读 CRM 字段（条件节点）

**背景**：营销自动化画布（`lib/CanvasSystem.php`）的条件节点只能判断 CDP 事件属性，**无法读取 CRM 字段**（如线索阶段、客户等级、最近跟进时间）。

**目标**：MA 条件分支节点支持"CRM 字段"判断。

**涉及文件**：
- `lib/CanvasSystem.php` — 画布引擎（~147 行）
- `lib/AutomationSystem.php` — 自动化系统（~274 行）
- `admin/canvas.php` — 画布编辑器

**实现方案**：
1. 在 `CanvasSystem.php` 的条件评估函数中，扩展条件类型：除了事件属性外，支持 `crm.stage`、`crm.score`、`crm.last_followup`、`crm.customer_level` 等字段
2. 条件值从 CRM 系统读取（`CrmSystem::get_lead()` 或类似）
3. 在 `admin/canvas.php` 的条件节点 UI 中，下拉列表新增"CRM 字段"选项

**验收标准**：
- 画布条件节点可选择"CRM 字段"作为判断条件
- 条件判断结果正确（基于 CRM 实时数据）
- 不影响现有条件判断逻辑

---

### 任务 A3：CDP 分群 → CRM 批量操作

**背景**：CDP 分群（`admin/segments.php`）创建了用户分群后，**只能导出 CSV，无法直接批量操作**（转线索/分配销售/发邮件）。

**目标**：CDP 分群结果一键"转 CRM 线索"或"批量发邮件"。

**涉及文件**：
- `admin/segments.php` — 分群管理页
- `lib/CrmSystem.php` — CRM 系统
- `lib/CdpSystem.php` — CDP 系统

**实现方案**：
1. 在 `admin/segments.php` 的分群详情页，新增"批量操作"按钮组：
   - "转为 CRM 线索" → 调用 `CrmSystem::bulk_create_leads(user_ids)`
   - "分配给销售" → 弹窗选择销售员 → 调用 `CrmSystem::bulk_assign(leads, sales_id)`
2. `CrmSystem.php` 新增 `bulk_create_leads()` 函数（支持批量，有去重逻辑）
3. UI 反馈：操作后显示"已创建 N 条线索"等结果

**验收标准**：
- 分群详情页有"转为 CRM 线索"按钮
- 点击后自动创建 CRM 线索（去重：已有线索的用户不重复创建）
- 操作有进度反馈

---

## P0-B：后台统一（合并碎片页面）

### 任务 B1：统一 SEO 中心

**背景**：当前 SEO 功能分散在 8 个独立页面，没有统一入口。用户要管理 SEO 需要在多个页面间跳转。

**目标**：合并为单一"SEO 中心"页面，用 tab 切换各子功能。

**涉及文件**：
- `admin/seo.php` — 页面级 SEO（~59 行）
- `admin/seo-tools.php` — 工具（sitemap/robots/verification，~114 行）
- `admin/seo-batch.php` — 批量策略（~183 行）
- `admin/seo-console.php` — 站长工具（~157 行）
- `admin/structured-data.php` — 结构化数据
- `admin/redirects.php` — 301 重定向
- `admin/config.php` — 侧栏入口（合并为一个）

**实现方案**：
1. 新建 `admin/seo-center.php`，顶部有 tab 切换：概览 / 页面 SEO / 工具 / 批量策略 / 站长工具 / 结构化数据 / 重定向
2. 各 tab 内容 include 原有页面的核心逻辑（去除重复的 admin_header/footer）
3. 侧栏入口合并为一个 `SEO 中心`，删除原有 8 个入口
4. 旧页面保留（做 301 重定向到新页面对应 tab）

**验收标准**：
- `/xmp/seo-center` 显示统一的 SEO 管理页面
- 各 tab 功能与原页面一致
- 侧栏只有 1 个"SEO 中心"入口
- 旧 URL（`/xmp/seo`、`/xmp/seo-tools` 等）重定向到新页面

---

### 任务 B2：统一内容中心

**背景**：文章管理（`articles.php`）、页面管理（`pages-list.php`）、下载资源（`downloads.php`）、播客（`podcasts.php`）是 4 个独立页面，内容管理散落。

**目标**：合并为"内容中心"，统一入口。

**涉及文件**：
- `admin/articles.php`
- `admin/pages-list.php`
- `admin/downloads.php`
- `admin/podcasts.php`
- `admin/config.php`

**实现方案**：
1. 新建 `admin/content-hub.php`，左侧 tab 切换：文章 / 页面 / 下载 / 播客
2. 各 tab 复用原有页面的列表逻辑（搜索/筛选/批量操作）
3. 侧栏合并为 1 个"内容中心"入口

**验收标准**：
- 统一入口可管理所有内容类型
- 各 tab 功能完整
- 侧栏精简

---

### 任务 B3：浅 CRUD 合并

**目标**：将以下浅 CRUD 页面合并到父级页面（作为 tab 或内嵌面板）。

| 当前页面 | 合并到 | 方式 |
|---|---|---|
| `admin/page-categories.php` | `admin/pages-list.php` | 侧边筛选栏 |
| `admin/tags.php` | `admin/pages-list.php` 或 `admin/articles.php` | 共享标签管理面板 |
| `admin/payment-settings.php` | `admin/shop-settings.php` | 内嵌 tab |
| `admin/mail-settings.php` | `admin/email.php` | 内嵌设置面板 |
| `admin/redirects.php` | `admin/seo-center.php` | tab（B1 中包含） |
| `admin/footer-links.php` | `admin/site-builder.php` | 内嵌设置 |
| `admin/storage.php` | `admin/health-check.php` | tab |
| `admin/activity.php` | `admin/audit-log.php` | tab |

**验收标准**：
- 合并后页面功能完整，原数据不丢失
- 侧栏入口减少 8 个
- 旧 URL 301 重定向到新位置

---

## P0-C：开发者生态基础

### 任务 C1：PluginSystem hooks 扩展（30+ hooks）

**背景**：当前只有 9 个 hooks，覆盖面太窄。开发者无法监听/拦截核心业务事件。

**目标**：扩展到 30+ hooks，覆盖 CDP/CRM/MA/内容/SEO/支付/社区。

**涉及文件**：
- `lib/PluginSystem.php` — 插件系统（~187 行）
- 各业务系统 lib/*.php — 在关键位置插入 `do_action()` / `apply_filters()`

**新增 hooks 列表**：

```
CDP 相关：
  cdp_event_received    — 行为事件接收后（可修改/丢弃事件）
  cdp_profile_updated  — 画像更新后
  cdp_segment_enter    — 用户进入分群
  cdp_segment_exit     — 用户退出分群

CRM 相关：
  crm_lead_created     — 线索创建
  crm_stage_changed    — 线索阶段变化
  crm_deal_won         — 成交
  crm_deal_lost        — 失败
  crm_followup_added   — 跟进记录添加

MA 相关：
  ma_flow_triggered   — 自动化流程触发
  ma_flow_completed   — 流程执行完成
  ma_email_sent       — 邮件发送后
  ma_sms_sent         — 短信发送后

内容相关：
  content_published    — 文章/页面发布后
  content_updated      — 内容更新后
  content_deleted      — 内容删除后
  comment_added        — 评论添加后

支付相关：
  payment_success      — 支付成功
  payment_refund       — 退款
  course_enrolled      — 课程报名
  course_completed     — 课程完成

社区相关：
  forum_post_created   — 帖子创建
  review_added         — 点评添加

系统相关：
  settings_changed     — 设置变更
  user_registered      — 用户注册
  user_login           — 用户登录
```

**验收标准**：
- `lib/PluginSystem.php` 的 `do_action()` / `apply_filters()` 调用点从 9 个增加到 30+
- 每个 hook 有文档说明（PHPDoc 注释，含触发时机、参数格式）
- 不影响现有业务逻辑（hook 是旁路，不影响主流程）

---

### 任务 C2：插件 SDK（`lib/PluginSDK.php`）

**目标**：为插件开发者提供统一的工具库，简化插件开发。

**涉及文件**：
- 新建 `lib/PluginSDK.php`
- `lib/PluginSystem.php` — 可能需要扩展

**SDK 功能**：
```php
class PluginSDK {
    // 配置
    static function get_config(string $key, $default = null);
    static function set_config(string $key, $value);
    
    // 数据访问
    static function get_articles(array $filter = []);
    static function get_leads(array $filter = []);
    static function get_cdp_profile(string $user_id);
    
    // 事件发射
    static function emit(string $event, array $data);
    
    // UI 注入
    static function inject_admin_page(string $slug, string $title, callable $renderer);
    static function inject_sidebar(string $icon, string $label, string $href);
    static function inject_settings_tab(string $label, callable $renderer);
    
    // 日志
    static function log(string $level, string $message, array $context = []);
    
    // HTTP 请求（外部 API 调用）
    static function http_get(string $url, array $headers = []);
    static function http_post(string $url, array $data, array $headers = []);
}
```

**验收标准**：
- SDK 可通过 `require_once __DIR__ . '/lib/PluginSDK.php'` 使用
- 有完整的 PHPDoc
- 提供 3 个官方示例插件（见任务 C3）

---

### 任务 C3：官方示例插件（3 个）

**目标**：提供 3 个完整示例插件，展示插件开发模式。

**示例插件清单**：

**插件 1：SEO 增强插件**（`plugins/seo-enhancer/`）
- 功能：文章发布时自动检查 SEO 质量（标题长度、描述长度、关键词密度）
- hooks 使用：`content_published` → 检查 → 注入 admin 通知
- 展示：数据访问（读文章）、事件监听、admin UI 注入

**插件 2：邮件模板插件**（`plugins/email-templates/`）
- 功能：预设 3 套邮件模板（欢迎邮件/课程通知/促销）
- hooks 使用：`ma_email_sent` → 替换模板变量
- 展示：配置管理、模板注入、事件拦截

**插件 3：数据看板小部件**（`plugins/dashboard-widget/`）
- 功能：在后台仪表盘注入一个"今日关键指标"小部件
- hooks 使用：`admin_dashboard_render`（新增 hook）
- 展示：admin UI 注入、数据访问、配置管理

**验收标准**：
- 每个插件有完整的 `plugin.json` + `plugin.php`
- 插件可安装/卸载/启停
- 有 README 说明

---

## P1：CDP 性能优化

### 任务 D1：CDP 事件分层缓存

**背景**：`CdpSystem::allEvents()` 每次请求加载全量事件 JSON（可能 MB 级），内存/CPU 要求高。

**目标**：热数据（最近 7 天）用 Redis/CF KV 缓存，冷数据用 JSON/SQLite。

**涉及文件**：
- `lib/CdpSystem.php`（~1,590 行）
- `lib/Cache.php`（Redis 缓存层）

**实现方案**：
1. `CdpSystem::allEvents()` 改为分层：
   - 最近 7 天事件 → 从 Redis/CF KV 读取
   - 7 天以上 → 从 JSON 文件读取
   - 合并返回
2. 事件写入时同步更新 Redis/CF KV（热层）
3. cron 每小时清理热层过期数据

**验收标准**：
- 页面加载时间下降（特别是 CDP 详情页/漏斗分析）
- 数据一致性：新事件即时可查，历史事件完整

---

## 执行优先级

| 优先级 | 任务 | 预计工作量 | 依赖 |
|---|---|---|---|
| P0-A1 | CRM 事件接入 FlowSystem | 中 | 无 |
| P0-A2 | MA 条件读 CRM | 中 | A1 |
| P0-C1 | PluginSystem 30+ hooks | 中 | 无 |
| P0-B1 | 统一 SEO 中心 | 中 | 无 |
| P0-B2 | 统一内容中心 | 中 | 无 |
| P0-B3 | 浅 CRUD 合并 | 小 | B1/B2 |
| P0-A3 | CDP→CRM 批量操作 | 小 | A1 |
| P0-C2 | 插件 SDK | 中 | C1 |
| P0-C3 | 官方示例插件 | 中 | C1/C2 |
| P1-D1 | CDP 事件分层缓存 | 大 | 无 |

---

## 注意事项

1. **所有改动必须通过 `php -l` 语法检查**
2. **不要修改 `data/` 目录下的 JSON 文件**（用户数据，只改代码）
3. **新增 hooks 是旁路操作**（不影响主流程，失败不影响业务）
4. **admin 页面样式用现有 admin_header/admin_sidebar**（不引入新框架）
5. **API 端点需加到 `admin/config.php` 的权限列表**
6. **完成一个任务后用 `git commit` 提交，消息格式：`feat(模块): 描述`**
