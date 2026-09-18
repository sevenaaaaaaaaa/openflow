# 适配流水线批跑报告（Adapter Forge Run）

> 生成：2026-09-19T01:14:12+08:00 · `php scripts/forge-batch.php --per-class=1`（跳过 PHPStan）

**通过率：100%**（passed 3 / needs-review 0 / blocked 0 / skipped 0 / error 0，共 3）

| 候选 | 能力类 | 状态 | 许可证 | 版本 pin | 落点 | 待补 TODO | 失败项 |
|---|---|---|---|---|---|---|---|
| binwiederhier/ntfy | notify_reach | passed | apache-2.0 | v2.28.0 | api_route/publish | 3 处 | — |
| umami-software/umami | data_ingest | passed | mit | v3.4.0 | api_route/mcp_tool | 1 处 | — |
| krayin/laravel-crm | crm_leads | passed | mit | v2.2.6 | api_route | 2 处 | — |

## 失败归因

（无）

> 说明：`passed` 只代表**结构合法 + 可静态分析 + 契约自测通过**；真实接口调用仍以 `TODO(适配)` 标记，需人审/AI 第二轮补齐。待补 TODO 数即剩余工作量。

## 观察

- 草稿目录：`plugins/_drafts/<id>/`（含 `verification-report.json`；徽章只由闸门写入）
- 失败项集中在许可证/版本 pin 时，说明「上游信息不全」而非生成质量差 → 需补 intake 的 releases/OpenAPI 读取
