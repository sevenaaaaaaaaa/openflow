# API 端点与 MCP 能力清单

> 由 `api/*.php`（116 个）与 `mcp-server.php`（MCP 协议）自动盘点整理。
> 鉴权档位见 `docs/SECURITY-API.md`（`public` / `member` / `admin`，实现于 `lib/ApiPolicy.php`）。

---

## 一、路由机制

| 形式 | 规则 | 说明 |
|---|---|---|
| 常规 | `/api/<name>` → `api/<name>.php` | `.htaccess`：`RewriteRule ^api/([a-z0-9_-]+)/?$ api/$1.php [L,QSA]` |
| 插件 | `/api/plugin/{插件ID}/{路径}` → `api/plugin.php` | 插件自带路由分发 |
| 文档 | `/api/v1/docs`（Swagger UI）· `/api/v1/docs.json`（OpenAPI 3.0） | 由 `lib/ApiDocs.php` 生成 |
| MCP | `/mcp`（HTTP SSE）或 stdio | JSON-RPC 2.0；Bearer / X-Api-Key 鉴权 |

**统一关卡**：每个 `api/*.php` 首行都是 `require_once admin/config.php` → 权限与限流在这里统一执行。

---

## 二、端点清单（按域分组）

### 2.1 内容 / CMS
| 端点 | 用途 | 档位 |
|---|---|---|
| `articles` | 文章列表/详情/分类 | public |
| `public-content` | 公开内容 API（SSR/外部前端渲染，统一 articles/courses/products/events） | public |
| `article-stats` / `article-stats-batch` | 阅读/点赞/收藏/分享 | public |
| `article-export` | 文章导出/分享 | public |
| `import-article` | 从 URL 导入（网页/公众号） | admin |
| `rss-import` / `batch-import` | RSS/批量导入为草稿 | admin |
| `ingest` | 统一内容导入连接器（飞书/Notion/Obsidian/印象笔记） | admin |
| `internal-links` | 内链扫描 / 一键插链 | admin |
| `assign-covers` | 批量随机封面 | admin |
| `pages` / `landing` / `builder-preview` | 页面 / 落地页 / 构建器实时预览 | public/admin |
| `templates` | 模板 save/load/list | admin |
| `calendar` | 内容日历（拖拽改发布日期） | admin |
| `cover-preview` | 生成式封面预览（「换一张」） | admin |
| `push-article` / `publish-adapters` | 推送到分发渠道 / 适配器配置 | admin |
| `download` / `downloads` | 资料下载 + 线索表单 | public |
| `data-export` | 数据仓库出向导出 | admin |

### 2.2 搜索 / SEO / GEO
| 端点 | 用途 | 档位 |
|---|---|---|
| `search` / `search-public` | 全局搜索 / 前台搜索 | public |
| `seo` | 输出完整 SEO head HTML | public |
| `indexnow-key` | IndexNow 验证文件（动态） | public |
| `sitemap`（见 `.htaccess`） | 站点地图 | public |
| `geo-citable` | GEO 可引用改写 | admin |

### 2.3 用户 / 会员 / 账户
| 端点 | 用途 | 档位 |
|---|---|---|
| `member` | 注册/登录/登出/验证码 | public |
| `sso` | SSO 统一登录（与已有产品站共享用户体系） | public |
| `mp-login` | 微信小程序登录（code2session） | public |
| `consent` | 同意管理 | public |
| `address` | 收货地址簿 | member |
| `bookmark` / `follow` | 收藏 / 关注 | member |
| `message` / `notifications` | 站内信 / 通知 | member |
| `unsubscribe` | 邮件退订（HMAC token） | public |
| `mail-track` | 邮件打开/点击统计 | public |

### 2.4 数据 / CDP / 增长
| 端点 | 用途 | 档位 |
|---|---|---|
| `cdp` | CDP 行为追踪 | public |
| `track` / `sdk` / `scripts` | 统一埋点 / SDK 交付 / 页面脚本+AB 分流 | public |
| `cdp-insight` | CDP AI 洞察 | admin |
| `segments` | 分群即服务（`?key=<API_KEY>`） | key |
| `segment-manage` | 分群管理 | admin |
| `realtime` | 实时数据 | admin |
| `reports` / `report` / `report-manage` | 报表 / 举报 | admin/public |
| `dashboard`（内）· `ask-data-bi` | 仪表盘 / 对话式 BI | admin |
| `trend-radar` | 热点雷达 | admin |
| `growth-signal` | 生长信号上报 | public |
| `evolution-report` | 前端遥测（JS 错误/性能/404） | public |
| `recommend` | 个性化推荐 | public |
| `dynamic-content` | 动态内容规则 + 曝光/点击追踪 | public |
| `conversion` | 转化组件配置 | public |
| `ab-event` | A/B 测试事件 | public |
| `click-tracks` | 圈选埋点定义（只读） | public |
| `share` / `nav-click` | 分享追踪 / 导航站跳转统计 | public |

### 2.5 营销 / 触达
| 端点 | 用途 | 档位 |
|---|---|---|
| `newsletter` | 订阅 | public |
| `promo` | 站内营销投放（通知条/弹窗） | public |
| `ads` | 广告位供前台拉取 | public |
| `form-submit` | 统一表单提交 | public |
| `leads` | 线索 | admin |
| `consultation` | 1v1 咨询（报名+支付+回调） | public |
| `tob-apply` | ToB 企业申请（SaaS/私有化/定制） | public |
| `wechat` / `wecom` | 公众号 / 企业微信（验证+消息接收） | public |
| `campaigns` / `automation` | 活动 / 自动化 | admin |

### 2.6 商业 / 交易
| 端点 | 用途 | 档位 |
|---|---|---|
| `cart` | 购物车 | public |
| `shop` | 商城下单 + 支付回调（虎皮椒） | public |
| `mall` | 实体商品下单 / 积分兑换 | public |
| `marketplace` | 生态市场（安装技能/打分） | public/member |
| `activation` | 激活码 | public |
| `stock` | 免费图库代理（Pexels/Unsplash/Pixabay） | admin |
| `subscription`（内） | 订阅计费（cron） | — |

### 2.7 社区 / 活动 / 课程
| 端点 | 用途 | 档位 |
|---|---|---|
| `community` / `community-config` | 社区（发帖/评论/点赞/投票）/ 首页配置 | public |
| `comment` | 评论/点评 | public |
| `event-register` | 活动报名/取消 | public |
| `course` / `course-progress` | 课程学员端 / 学习进度 | member |
| `survey-submit` / `nps-submit` | 调研 / NPS 提交 | public |
| `survey-ai` / `survey-import` | 问卷 AI 生成 / 文档解析 | admin |
| `help-feedback` | 帮助中心反馈 | public |
| `nav-submit` | 导航站收录申请 | public |

### 2.8 AI / Agent
| 端点 | 用途 | 档位 |
|---|---|---|
| `assistant` | 全局 AI 小助手（多轮） | admin |
| `ai-generate` / `ai-article` / `ai-business` | 内容生成 / 编辑器助手 / 业务助手 | admin |
| `ai-landing` / `ai-block` | AI 生成落地页 / 组件工厂造模块 | admin |
| `ai-canvas` / `ai-form` / `ai-repurpose` | 画布 / 表单 AI / 一写多编译 | admin |
| `mainline-ai` | 控制台 AI 判断 | admin |
| `agent` / `agent-posts` / `site-agent` | Agent 运行时 / AI 岗位 / 站点 Agent | admin/public |
| `morning-briefing` | 晨会简报 + 利润推演（briefing/sensitivity/whatif） | admin |
| `create` / `build-skill` | 创作台 AI / 描述即造 | member |
| `assistant`（见上） | — | — |

### 2.9 站点 / 运维 / 集成
| 端点 | 用途 | 档位 |
|---|---|---|
| `site-structure` | 全局导航/页脚/自定义页面配置 | public |
| `site-health` | URL 巡检（403/404/5xx） | admin |
| `theme` / `lang` | 主题 CSS 变量 / 语言包 | public |
| `provision` | 自动装配 | admin |
| `plugin` | 插件 API 路由分发 | 插件自定 |
| `developer` | 开发者生态（入驻/提交产品） | member |
| `oauth-callback` | OAuth2 回调 | public |
| `webhook` | Webhook 接收 | public |
| `cron` | 定时任务入口 | token |
| `indexnow-key`（见上） | — | — |
| `sdk`（见上） | — | — |

> 说明：上表为**按用途归类**，个别端点的档位以 `lib/ApiPolicy.php` 实际配置为准（`docs/SECURITY-API.md` 有判定记录）。

---

## 三、MCP 能力（供 AI Agent 调用）

**协议**：JSON-RPC 2.0；`initialize` / `tools/list` / `tools/call` / `prompts/list` / `prompts/get` / `ping`
**传输**：stdio（视同管理员）· HTTP SSE（`/mcp`，`Authorization: Bearer <key>` 或 `X-Api-Key`）
**鉴权**：`lib/McpGuard.php` 逐工具鉴权 + 审计留痕

### 23 个工具

| 工具 | 作用 |
|---|---|
| `articles_list` / `article_get` | 列文章 / 取详情 |
| `article_create` / `article_publish` | 建文章 / 发布 |
| `members_list` | 列会员 |
| `leads_count` | 统计表单线索 |
| `orders_revenue` | 统计订单收入 |
| `search` | 全局搜索（文章/课程/页面） |
| `sentiment_scan` / `sentiment_topics` | 舆情采集 / 监控主题 |
| `skills_list` / `skill_execute` | 列技能 / 执行技能（prompt 或 tool） |
| `contributions_list` / `contributions_recommend` | 社区贡献物 / 按兴趣推荐 |
| `growth_next_best_action` | 增长大脑：最该动的人 + 下一动作 + 理由（只提议不执行） |
| `growth_goal_status` | 增长目标与进度 |
| `growth_conversion_truth` | 成交真相（按收入排） |
| `growth_ask_data` | 自然语言问站点数据（基于真实指标） |
| `flow_run` / `automation_run` | 触发流程事件 / 手动跑自动化 |
| `cdp_add_tag` | 给用户打标签 |
| `email_send` | 发邮件 |
| `lead_create` | 建 CRM 线索 |

### 4 个 Prompt 技能包
| Prompt | 用途 |
|---|---|
| `weekly_growth_review` | 周增长复盘：拉数据→对比目标→找瓶颈→下周一件事 |
| `content_idea_from_trends` | 从热点与讨论中挑 3 个选题并说明理由 |
| `lead_nurture_plan` | 为高意向线索制定 7 天培育计划 |
| `site_growth_audit` | 整站增长体检（流量→内容→转化→保留） |

---

## 四、对外集成推荐入口（外部/别的产品该用哪个）

| 目的 | 用这个 |
|---|---|
| 取内容渲染自己的前端 | `public-content`（分页/筛选）、`landing` |
| 取 SEO 注入 | `seo` |
| 推送行为事件进来 | `track` / `cdp` / `ingest` |
| 按 API Key 拉分群 | `segments?key=` |
| AI Agent 读写内容 | **MCP**（`/mcp`） |
| 共享用户体系 | `sso` |
| 插件形式扩展 | `/api/plugin/{id}/...` |
