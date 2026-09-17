# 服务对接手册（OpenFlow ↔ 产品矩阵）

> 面向：需要把两个服务接起来的开发者/Agent。
> 原则：**只通过 API 说话；不共享数据库；互通是增益不是依赖**（拔掉任何一个，其余照常跑）。

---

## 一、矩阵服务地图（现状）

| 服务 | 形态 | 技术栈 | 部署位置 | 对外入口 |
|---|---|---|---|---|
| **OpenFlow** | 主站（表现层 + CMS + 后台） | PHP 8.3 | `/www/wwwroot/nownexts_com` | `nownexts.com` |
| **PayFlow** | 独立应用 | PHP | `/www/wwwroot/payflow`（Alias `/payflow`） | `nownexts.com/payflow`、`payflow.nownexts.com` |
| **LearnFlow** | 独立应用 | （开发中） | `/www/wwwroot/learnflow`（Alias `/learnflow`） | `nownexts.com/learnflow` |
| **Webs Flow** | 产品站 | JS/TS | `/www/wwwroot/websflow`（Alias `/webflow`） | `nownexts.com/webflow` |
| **UserLoop** | 独立服务 | Python | `/www/wwwroot/userloop`（systemd `userloop`） | `nownexts.com/userloop` |
| **MFlow** | 独立服务 | Python | systemd `mflow-console` | `nownexts.com/mflow` |
| **inFlow** | 独立服务 | Python | （对外/自部署） | 情报 API |

> 主域共享、路径/子域区分，是刻意的选择（便于管理与数据切割）；对外建议用子域名（见 `DEPLOY-CONVENTIONS.md`）。

---

## 二、现有已打通的通道（照着用）

### 2.1 行为事件 → UserLoop（网页端）
- **方式**：`plugins/userloop-tracker/` 在前台 `body_end` 插槽注入 `…/userloop/track.js`
- **数据**：`page_view` / `element_click` 批量上报
- **流向**：浏览器 → UserLoop `POST /userloop/api/v1/ingest` → CDP 建档 → 旅程

### 2.2 业务事件 → inFlow（服务端，HMAC 签名）
- **方式**：`plugins/insight-flow/` 挂在 OpenFlow 业务钩子上
- **出站事件**：`cdp_event_received`（filter）、`cdp_segment_enter/exit`、`payment_success`、`crm_deal_won`、`form_submitted`、`user_registered`
- **纪律**：旁路推送、**5 秒超时、失败静默**——绝不拖慢主请求
- **入站回读**：`register_api_route`，把 inFlow 洞察回给后台卡片与 GrowthBrain
- **定时**：`register_schedule` 每日拉 IF 周报摘要进通知流

### 2.3 分群即服务（按 API Key 拉）
- **端点**：`GET /api/segments?key=<API_KEY>`
- **用途**：任何服务（含第三方 MA）按 key 拉分群/人群

### 2.4 分享用户体系
- **端点**：`/api/sso`
- **用途**：与已有产品站共享登录态

### 2.5 AI Agent 通道（MCP）
- **端点**：`/mcp`（HTTP SSE）或 stdio
- **鉴权**：`Authorization: Bearer <key>` / `X-Api-Key`
- **能力**：23 个工具 + 4 个 Prompt（见 `API-INVENTORY.md` 第三节）
- **纪律**：只提议/读数据的工具不产生副作用；写工具逐项鉴权 + 审计留痕

### 2.6 内容外供（无头 CMS）
- `GET /api/public-content?type=articles|courses|products|events`（分页/筛选）
- `GET /api/landing?slug=xxx`
- `GET /api/seo?page=...`

---

## 三、新增对接的标准做法

### 3.1 选通道
| 需求 | 用 |
|---|---|
| 事件从 A 到 B（异步、允许丢） | **Webhook + HMAC**（参考 insight-flow 插件的 `httpPost`） |
| B 主动拉 A 的数据 | **API Key**（参考 `segments?key=`） |
| 用户体系打通 | `sso` |
| AI 需要读写 | **MCP** |

### 3.2 契约模板
```http
POST https://<target>/api/v1/<action>
X-Signature: sha256=<HMAC(body, secret)>
X-Timestamp: 1730000000
Content-Type: application/json

{"idempotency_key":"<uuid>","event":"...","data":{...}}
```

### 3.3 硬性要求（评审会卡这几条）
1. **超时** 2–5 秒；失败**旁路**，绝不影响主链路
2. **幂等**（`idempotency_key`）
3. **不共享数据库**：只调 API
4. **不双向强依赖**：单向事件流优先；需要双向时各自提供只读 API
5. **可观测**：失败要有日志/计数（便于排障）

---

## 四、数据归属（谁是谁的源）

| 数据 | 归属（唯一真源） | 其他服务怎么拿 |
|---|---|---|
| 文章/页面/课程内容 | OpenFlow | `public-content` |
| 订单/订阅/佣金 | PayFlow | PayFlow API |
| 全域用户行为/分群 | UserLoop | `segments?key=` / API |
| 外部情报（趋势/舆情） | inFlow | inFlow API（回读） |
| 学习进度/证书 | LearnFlow | LearnFlow API |
| 落地页内容 | Webs Flow / OpenFlow 页面 | 各自 API |

**禁止**：跨服务直接读对方数据库或 `data/*.json`。

---

## 五、排障速查

| 症状 | 排查 |
|---|---|
| 事件没到对端 | 看插件日志（`plugins/*/`）+ 对端接收日志；确认 HMAC 时间戳未过期 |
| 401/403 | 检查 API Key / HMAC 签名 / Apache `Authorization` 头（`CGIPassAuth On`） |
| 超时拖慢页面 | 确认调用是旁路 + 短超时；不要在主请求里同步等外部服务 |
| 数据不一致 | 先确认"真源"是谁，再决定谁该改成拉取 |
