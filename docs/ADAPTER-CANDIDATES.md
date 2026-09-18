# 适配候选首批清单（按功能能力类）

> 生成：`python3 scripts/screen-adapters.py`（`--no-github` 可离线跑）。
> 评分：类目契合 30 + 可映射面 15 + 许可证 25 + 活跃 15 + 证据 15；**已接入的通道 −40**（保留可见，供判断是否要第二通道）。
> 已排除：框架 / SDK / 脚手架 / 教程 / awesome 聚合清单（我们要的是「能当插件的产品」）。
> 首批 **28** 个。

## 内容分发渠道（一稿多发到更多平台）

落点：`publish`　已接入：wordpress、ghost、telegram、discord、mastodon

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [praw-dev/praw](https://github.com/praw-dev/praw) | 种子 | bsd-2-clause | 4257 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 2 | [Directus](https://github.com/directus/directus) | 策展 | noassertion | 37931 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE）；许可证待核验（intake 读仓库 LICENSE） |
| 3 | [django-cms/django-cms](https://github.com/django-cms/django-cms) | 种子 | noassertion | 10668 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE） |
| 4 | [Chocobozzz/PeerTube](https://github.com/Chocobozzz/PeerTube) | 种子 | agpl-3.0 | 15320 | 2026-09-17 | 64 | B | 许可证 agpl-3.0 传染性，需法务确认 |

## 触达/通知通道（站内信之外的多通道提醒）

落点：`publish`　已接入：feishu、wecom、whatsapp、smtp、slack

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [binwiederhier/ntfy](https://github.com/binwiederhier/ntfy) | 种子 | apache-2.0 | 34298 | 2026-09-15 | 89 | B | 待 intake 核验 |
| 2 | [caronc/apprise](https://github.com/caronc/apprise) | 种子 | bsd-2-clause | 17344 | 2026-09-17 | 89 | B | 待 intake 核验 |
| 3 | [dgtlmoon/changedetection.io](https://github.com/dgtlmoon/changedetection.io) | 种子 | apache-2.0 | 34324 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 4 | [Finb/Bark](https://github.com/Finb/Bark) | 种子 | mit | 9117 | 2026-09-18 | 84 | B | 待 intake 核验 |

## 数据入湖（事件/行为/订单 → 我们的 CDP）

落点：`ingest`　已接入：—

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [apache/superset](https://github.com/apache/superset) | 种子 | apache-2.0 | 74827 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 2 | [umami-software/umami](https://github.com/umami-software/umami) | 种子 | mit | 38897 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 3 | [PostHog/posthog](https://github.com/PostHog/posthog) | 种子 | noassertion | 39843 | 2026-09-18 | 75 | A | 许可证待核验（intake 读仓库 LICENSE） |
| 4 | [metabase/metabase](https://github.com/metabase/metabase) | 种子 | noassertion | 49330 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE） |

## 情报源（趋势/舆情/竞品 → inFlow 式选题）

落点：`ingest`　已接入：search console

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [Composio](https://github.com/ComposioHQ/composio) | 策展 | mit | 30228 | 2026-09-18 | 90 | A | 许可证待核验（intake 读仓库 LICENSE） |
| 2 | [RAGFlow](https://github.com/infiniflow/ragflow) | 策展 | apache-2.0 | 90936 | 2026-09-18 | 84 | B | 许可证待核验（intake 读仓库 LICENSE） |
| 3 | [unclecode/crawl4ai](https://github.com/unclecode/crawl4ai) | 种子 | apache-2.0 | 83813 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 4 | [Crawlee](https://github.com/apify/crawlee) | 策展 | apache-2.0 | 25832 | 2026-09-17 | 84 | B | 许可证待核验（intake 读仓库 LICENSE） |

## 线索/CRM 双向同步（我们不重复造 CRM）

落点：`api_route`　已接入：—

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [krayin/laravel-crm](https://github.com/krayin/laravel-crm) | 种子 | mit | 23896 | 2026-09-14 | 84 | B | 待 intake 核验 |
| 2 | [twentyhq/twenty](https://github.com/twentyhq/twenty) | 种子 | noassertion | 56986 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE） |
| 3 | [chatwoot/chatwoot](https://github.com/chatwoot/chatwoot) | 种子 | noassertion | 36929 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE） |
| 4 | [erxes/erxes](https://github.com/erxes/erxes) | 种子 | noassertion | 4081 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE） |

## 收款/订阅通道（PayFlow 之外的补充渠道）

落点：`api_route`　已接入：stripe、wechat、alipay、虎皮椒

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [juspay/hyperswitch](https://github.com/juspay/hyperswitch) | 种子 | apache-2.0 | 43621 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 2 | [killbill/killbill](https://github.com/killbill/killbill) | 种子 | apache-2.0 | 5747 | 2026-09-14 | 84 | B | 待 intake 核验 |
| 3 | [solidusio/solidus](https://github.com/solidusio/solidus) | 种子 | bsd-3-clause | 5328 | 2026-09-17 | 84 | B | 待 intake 核验 |
| 4 | [medusajs/medusa](https://github.com/medusajs/medusa) | 种子 | noassertion | 36365 | 2026-09-18 | 69 | B | 许可证待核验（intake 读仓库 LICENSE） |

## 工作流/Agent 能力（补我们的编排，不重复实现）

落点：`api_route`　已接入：—

| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |
|---|------|------|--------|------|----------|----|------|-----------|
| 1 | [nanobot](https://github.com/HKUDS/nanobot) | 策展 | mit | 48314 | 2026-09-18 | 95 | A | 许可证待核验（intake 读仓库 LICENSE） |
| 2 | [langflow-ai/langflow](https://github.com/langflow-ai/langflow) | 种子 | mit | 154966 | 2026-09-18 | 84 | B | 待 intake 核验 |
| 3 | [browser-use](https://github.com/browser-use/browser-use) | 策展 | mit | 115085 | 2026-09-15 | 84 | B | 许可证待核验（intake 读仓库 LICENSE） |
| 4 | [MetaGPT](https://github.com/FoundationAgents/MetaGPT) | 策展 | mit | 70484 | 2026-01-21 | 84 | B | 许可证待核验（intake 读仓库 LICENSE） |

