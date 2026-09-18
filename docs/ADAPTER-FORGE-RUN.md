# 适配流水线批跑报告（Adapter Forge Run）

> 生成：2026-09-19T00:20:03+08:00 · `php scripts/forge-batch.php --per-class=2`（含 PHPStan）

**通过率：79%**（passed 11 / needs-review 0 / blocked 3 / skipped 0 / error 0，共 14）

| 候选 | 能力类 | 状态 | 许可证 | 版本 pin | 落点 | 待补 TODO | 失败项 |
|---|---|---|---|---|---|---|---|
| nanobot | workflow_agent | passed | mit | v0.3.5 | api_route/schedule/publish/mcp_tool | 5 处 | — |
| langflow-ai/langflow | workflow_agent | passed | mit | v1.12.2 | api_route/hook/block/ingest/mcp_tool | 4 处 | — |
| Composio | intel_source | blocked | mit | @composio/vercel@0.12.0 | api_route/schedule/ingest/mcp_tool | 3 处 | source:version |
| RAGFlow | intel_source | passed | apache-2.0 | v0.27.2 | api_route | 2 处 | — |
| binwiederhier/ntfy | notify_reach | passed | apache-2.0 | v2.28.0 | api_route/publish | 3 处 | — |
| caronc/apprise | notify_reach | passed | bsd-2-clause | v1.13.1 | api_route/hook/publish | 4 处 | — |
| apache/superset | data_ingest | passed | apache-2.0 | 6.1.0 | api_route | 2 处 | — |
| umami-software/umami | data_ingest | passed | mit | v3.4.0 | api_route/mcp_tool | 1 处 | — |
| juspay/hyperswitch | payment_billing | passed | apache-2.0 | v1.126.0 | api_route/ingest | 3 处 | — |
| killbill/killbill | payment_billing | passed | apache-2.0 | killbill-0.24.21 | api_route | 2 处 | — |
| krayin/laravel-crm | crm_leads | passed | mit | v2.2.6 | api_route | 2 处 | — |
| twentyhq/twenty | crm_leads | blocked | noassertion | twenty/v2.41.0 | api_route/ingest/publish | 5 处 | source:version, source:license |
| praw-dev/praw | content_channel | passed | bsd-2-clause | v8.0.3 | api_route/ingest | 3 处 | — |
| Directus | content_channel | blocked | noassertion | v12.3.1 | api_route/hook/mcp_tool | 2 处 | source:license |

## 失败归因

- `source:version`：2 个
- `source:license`：2 个

> 说明：`passed` 只代表**结构合法 + 可静态分析 + 契约自测通过**；真实接口调用仍以 `TODO(适配)` 标记，需人审/AI 第二轮补齐。待补 TODO 数即剩余工作量。

## 观察

- 草稿目录：`plugins/_drafts/<id>/`（含 `verification-report.json`；徽章只由闸门写入）
- 失败项集中在许可证/版本 pin 时，说明「上游信息不全」而非生成质量差 → 需补 intake 的 releases/OpenAPI 读取
