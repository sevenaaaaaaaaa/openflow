# MCP 接入指南（把 OpenFlow 接到 AI Agent）

> OpenFlow 内置 MCP server，让 Claude / 其他支持 MCP 的 Agent 直接读写你的站点内容与增长数据。
> 协议：JSON-RPC 2.0 · 传输：stdio 或 HTTP SSE · 鉴权：Bearer / X-Api-Key

---

## 一、两种接入方式

### 方式 A：HTTP SSE（推荐，适合远程/多客户端）

- **端点**：`https://nownexts.com/mcp`
- **鉴权**：`Authorization: Bearer <API_KEY>` 或 `X-Api-Key: <API_KEY>`
- **握手**：先 `initialize`，再 `tools/list` / `tools/call`

```bash
# 列出可用工具
curl -s https://nownexts.com/mcp \
  -H "Authorization: Bearer $MCP_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'

# 调用一个工具（例：列最近文章）
curl -s https://nownexts.com/mcp \
  -H "Authorization: Bearer $MCP_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call",
       "params":{"name":"articles_list","arguments":{"status":"published"}}}'
```

> ⚠️ 服务器 Apache 必须保留 `.htaccess` 里的 `CGIPassAuth On`，否则 `Authorization` 头会被剥掉 → 401。

### 方式 B：stdio（本地子进程，适合 Claude Desktop / CLI）

在客户端配置里把 `mcp-server.php` 作为子进程启动：

```json
{
  "mcpServers": {
    "openflow": {
      "command": "/www/server/php/83/bin/php",
      "args": ["/www/wwwroot/nownexts_com/mcp-server.php"],
      "env": { "OF_MCP_TRANSPORT": "stdio" }
    }
  }
}
```

> stdio 模式下进程由你本机启动，**视同管理员**（无网络鉴权层），请只在本机可信环境使用。

---

## 二、能力速览

**23 个工具**（完整清单见 `API-INVENTORY.md` 第三节）：

| 类别 | 工具 |
|---|---|
| 内容 | `articles_list` `article_get` `article_create` `article_publish` |
| 搜索 | `search` |
| 会员/线索/收入 | `members_list` `leads_count` `orders_revenue` |
| 舆情 | `sentiment_scan` `sentiment_topics` |
| 技能生态 | `skills_list` `skill_execute` `contributions_list` `contributions_recommend` |
| 增长大脑 | `growth_next_best_action` `growth_goal_status` `growth_conversion_truth` `growth_ask_data` |
| 执行 | `flow_run` `automation_run` `cdp_add_tag` `email_send` `lead_create` |

**4 个 Prompt 技能包**（`prompts/list`）：`weekly_growth_review`、`content_idea_from_trends`、`lead_nurture_plan`、`site_growth_audit`

---

## 三、鉴权与安全

| 项 | 说明 |
|---|---|
| 工具级鉴权 | `lib/McpGuard.php` 逐工具校验；未登记的工具一律拦截 |
| 读/写分级 | 查询类（`*_list`/`*_get`/`count`/`revenue`…）只读；写类（`article_create`、`email_send`、`lead_create`、`cdp_add_tag`…）需写权限 |
| 审计留痕 | 每次调用记审计（谁、何时、调了什么） |
| 保险丝 | AI 调用受 `AiBudget` 额度闸门约束；额度尽则降级不重试 |
| 建议做法 | 给 Agent 单独签发 API Key，最小权限；在后台可随时吊销 |

---

## 四、常见用法

1. **让 Agent 写内容**：`article_create` → 人审 → `article_publish`
2. **让 Agent 做周复盘**：`prompts/get weekly_growth_review` → Agent 依次调 `growth_goal_status`、`growth_conversion_truth`、`growth_next_best_action`
3. **让 Agent 接管线索**：`leads_count` 看积压 → `lead_create` / `email_send` 跟进
4. **与矩阵产品协作**：Agent 通过 MCP 读 OpenFlow 数据，再经 HTTP API 推给 MFlow / inFlow / UserLoop（见 `SERVICE-INTEGRATION.md`）

---

## 五、排障

| 症状 | 处理 |
|---|---|
| 401 | 检查 Key；确认 `.htaccess` 有 `CGIPassAuth On` |
| `tools/call` 返回“工具未登记” | 该工具不在注册表（`lib/McpGuard.php`），或当前 Key 无权限 |
| stdio 起不来 | 用绝对路径调 PHP；确认 `OF_MCP_TRANSPORT=stdio` |
| 结果为空但无报错 | 多数查询工具有默认过滤（如只返回 `published`），显式传参 |
