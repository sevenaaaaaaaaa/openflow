# OpenFlow 统一埋点方案 v1.0

> 对标：神策数据(SensorsData) / 热云(HotCloud) / Google Analytics 4 / 百度统计
> 目标：一套贯穿「采集 → 存储 → 画像 → 分析 → 触达」的完整埋点体系

---

## 1. 埋点设计原则（对标行业标准）

### 1.1 三层事件模型（GA4 + 神策共识）

```
① 自动采集事件（前端自动上报，无需手动）
② 推荐/预置事件（按业务场景预置模板，开发者配置即用）
③ 自定义事件（业务方自由定义，走统一规范）
```

### 1.2 事件四要素（神策标准）

每个事件由 4 部分组成：
```
事件名 (event)    如 page_view / article_view / form_submit
事件属性 (props)  该事件自身的参数，如 article_id / form_name
公共属性 (common) 每个事件都带的环境信息，如 os / browser / device / utm
用户属性 (user)   识别用户的画像字段，如 member_id / email / level / role
```

### 1.3 与 GA4 的映射

| GA4 概念 | OpenFlow 对应 | 说明 |
|---------|--------------|------|
| automatic events | 自动采集事件 | page_view/scroll/click 等 |
| recommended events | 推荐/预置事件 | article_view/form_submit/purchase 等 |
| custom events | 自定义事件 | 业务扩展 |
| user properties | 用户属性 | CDP 画像 |
| event parameters | 事件属性 | props |
| session | 会话 | 30 分钟超时 |
| conversion events | 转化事件 | 可标记 purchase/subscribe 等 |

---

## 2. 事件字典（完整清单）

### 2.1 自动采集事件（前端自动上报）

| 事件名 | 触发时机 | 关键属性 | 来源 |
|--------|---------|---------|------|
| `$page_view` | 页面加载/SPA路由切换 | path, title, referrer, url | 自动 |
| `$scroll_depth` | 滚动达 25/50/75/100% | percent, path | 自动 |
| `$element_click` | 点击 a/button | tag, text, href, selector, path | 自动 |
| `$form_submit` | 表单提交 | form_id, form_name, fields | 自动 |
| `$impression` | 元素进入视口(50%) | selector, path | 自动 |
| `$time_on_page` | 页面离开 | duration_sec, path | 自动（beacon） |
| `$stay_duration` | 每30s心跳 | duration_sec | 自动 |
| `$utm_landing` | 带UTM参数落地 | utm_* 全量 | 自动 |
| `$outbound_click` | 点击站外链接 | href, domain | 自动 |
| `$search` | 站内搜索 | keyword, result_count | 自动 |
| `$error` | 前端JS错误 | message, source, lineno | 自动 |
| `$first_visit` | 首次访问 | — | 自动（按vid） |

### 2.2 推荐/预置事件（业务场景，预置模板）

**内容类**
| 事件名 | 触发 | 关键属性 |
|--------|------|---------|
| `article_view` | 文章浏览 | article_id, slug, category, tags, author |
| `article_read_time` | 文章停留 | article_id, duration_sec, percent |
| `article_share` | 文章分享 | article_id, platform, channel |
| `article_like` | 点赞 | article_id |
| `article_bookmark` | 收藏 | article_id |
| `article_download` | 资料下载 | download_id, title, type |

**课程类**
| 事件名 | 触发 | 关键属性 |
|--------|------|---------|
| `course_view` | 课程浏览 | course_id, title, category |
| `course_enroll` | 报名 | course_id, price |
| `lesson_start` | 开始课时 | course_id, lesson_id, chapter |
| `lesson_complete` | 完成课时 | course_id, lesson_id |
| `course_complete` | 学完整课 | course_id, percent |

**转化类（可标记为 conversion）**
| 事件名 | 触发 | 关键属性 |
|--------|------|---------|
| `form_submit` | 表单提交 | form_id, form_type, source |
| `lead_created` | 线索生成 | lead_id, email, source |
| `purchase` | 订单支付 | order_id, product_id, amount, currency |
| `subscribe` | 订阅/升级 | plan_id, tier, amount, period |
| `activation` | 激活码使用 | code, product |
| `consultation_booked` | 预约咨询 | consultation_id, type |

**社区类**
| 事件名 | 触发 | 关键属性 |
|--------|------|---------|
| `post_view` | 帖子浏览 | post_id, topic |
| `post_create` | 发帖 | post_id, topic |
| `comment_create` | 评论 | post_id, content_len |
| `post_like` | 点赞 | post_id |
| `post_share` | 分享 | post_id, platform |

**用户类**
| 事件名 | 触发 | 关键属性 |
|--------|------|---------|
| `user_register` | 注册 | method(email/phone/wx), referral |
| `user_login` | 登录 | method |
| `user_logout` | 登出 | — |
| `user_profile_update` | 更新资料 | fields |
| `role_selected` | 角色选择 | role, page |

**增长工具类**
| 事件名 | 触发 | 关键属性 |
|--------|------|---------|
| `tool_use` | 使用工具箱 | tool_id, tool_name |
| `tool_result` | 工具出结果 | tool_id, success |
| `seo_check` | SEO体检 | tool_id, score |

### 2.3 自定义事件
业务方用 `CDP.track('任意事件', {属性})` 自由上报，后端统一承接。

---

## 3. 公共属性（每个事件自动附带，对标 GA4 的自动参数）

### 3.1 设备/环境（前端自动注入）
| 属性 | 说明 |
|------|------|
| `os` | 操作系统（神策标准：iOS/Android/Windows/macOS 等） |
| `os_version` | 系统版本 |
| `browser` | 浏览器 |
| `browser_version` | 浏览器版本 |
| `device` | 设备类型（Desktop/Mobile/Tablet） |
| `screen_width/height` | 屏幕尺寸 |
| `language` | 语言 |
| `ua` | User-Agent |
| `referrer` | 来源页 |
| `referrer_domain` | 来源域名 |
| `landing_page` | 落地页 |
| `url_path` | 当前路径 |
| `url_query` | 查询参数 |

### 3.2 渠道归因（对标 GA4 + 热云）
| 属性 | 说明 |
|------|------|
| `utm_source` | 来源 |
| `utm_medium` | 媒介 |
| `utm_campaign` | 活动 |
| `utm_term` | 关键词 |
| `utm_content` | 内容 |
| `channel` | 归一化渠道（直接/搜索/社媒/广告/外链/邮件） |
| `gclid/fbclid/msclkid` | 广告平台ID |
| `is_ad_channel` | 是否广告渠道 |

### 3.3 会话信息（对标 GA4 Session）
| 属性 | 说明 |
|------|------|
| `session_id` | 会话ID（30分钟超时滚动） |
| `session_start` | 是否新会话 |
| `session_visit_count` | 本会话浏览页数 |

---

## 4. 用户属性（写入 CDP 画像）

对标神策 `profile_set` / GA4 `user_properties`：

| 用户属性 | 来源 |
|---------|------|
| `member_id` | 登录 |
| `email` | 登录/表单 |
| `name/nickname` | 注册 |
| `phone` | 注册/表单 |
| `company` | 表单 |
| `role` | 角色选择/后台 |
| `member_level` | 会员体系 |
| `total_spent` | 订单同步 |
| `first_seen/last_seen` | 自动 |
| `tags` | 自动+人工 |
| `source/channel` | 首次归因 |
| `wx_openid` | 微信授权 |

---

## 5. 数据流架构

```
前端 cdp-track.js（自动采集+批量缓冲）
        │  每5条或5s批量上报
        ▼
API: /api/cdp.php（统一入口，CORS+批量）
        │
        ▼
CdpSystem::track（事件落盘 JSON + 画像更新）
   ├─ 身份解析 IdentityResolver（合并匿名→会员→跨设备）
   ├─ 自动打标签（autoTag）
   ├─ 画像属性更新
   └─ 触发自动化（行为触发）
        │
        ▼
CDP 分析（漏斗/留存/RFM/路径/归因）+ AI 洞察
```

---

## 6. 埋点完整性检查清单（本次要补的）

| 能力 | 现状 | 待补 |
|------|------|------|
| 自动采集 | ✅ 有基础 | +os/browser/device 属性、+time_on_page、+outbound、+error、+search |
| 预置事件 | ⚠️ 部分 | +课程/社区/转化/工具 事件模板 |
| 公共属性 | ❌ 前端无 | +设备环境/渠道/会话 |
| 批量上报 | ❌ 每条一次请求 | +批量缓冲（5条/5s） |
| 渠道归一化 | ⚠️ 有UTM | +channel 归一化、+广告ID |
| 会话 | ❌ 无 | +session_id 30分钟 |
| 事件字典 | ❌ 无 | +admin 可视化字典 |
| 属性补全 | ⚠️ 部分 | +os/browser/device 等 |

---

## 7. 隐私合规

- 敏感字段（手机号/邮箱）前端默认脱敏，仅登录后完整上报
- 支持 `data-privacy="none"` 完全禁用追踪
- 支持 Do Not Track（`navigator.doNotTrack`）尊重
- Cookie 策略：first-party only
