# 适配规范（Adapter Spec v2）

> 定位：**我们只维护基座与适配契约；第三方只需"愿意"，AI Agent 负责把它的能力编译成经过验证的插件。**
> 配套：[`ADAPTER-CANDIDATES.md`](./ADAPTER-CANDIDATES.md)（首批候选）· `scripts/screen-adapters.py`（筛选）
> · `lib/AdapterIntake.php`（源接入）· `lib/PluginSystem.php`（扩展点）· `docs/PLUGIN-DEV.md`（手写插件指南）

---

## 一、三层与责任边界

| 层 | 谁维护 | 只做这些 | 明确不做 |
|----|--------|----------|----------|
| **基座 Core** | 我们 | 能力编排、接口、流程、Loop、权限与审计、CDP/内容/触达/销售四条主干 | 不追每个垂直工具的自研 |
| **适配层 Adapter** | AI 生成 + 人审 | 把上游能力翻译成基座扩展点；声明权限与来源；带契约测试 | 不复制上游的全部功能；不绕过上游官方 API |
| **适配代理 Agent** | 我们（工具） | intake → 能力画像 → 映射 → 合成代码与测试 → 自证 → 出报告 | 不替人做上架决策 |

---

## 二、适配契约 v2（`plugin.json` 扩展字段）

在现有字段（`id/name/version/description/author/enabled_by_default/permissions`）之上新增四个块：

```jsonc
{
  "id": "notify-ntfy",
  "name": "ntfy 触达通道",
  "version": "0.1.0",
  "description": "把站内事件推到 ntfy 主题（自托管/公共实例均可）",
  "author": "OpenFlow Agent",
  "enabled_by_default": false,
  "permissions": ["hooks", "http", "log", "config"],   // 必须 ⊇ surfaces 所需

  "source": {                                          // 上游来源（可追溯）
    "kind": "github", "repo": "binwiederhier/ntfy",
    "version": "v2.11.0", "license": "apache-2.0"
  },
  "surfaces": {                                        // 声明用了哪些扩展点
    "publish": ["notify_push"],
    "hook": ["event_forward"],
    "schedule": []
  },
  "capabilities": {                                    // 最小能力集（沙箱按此授权）
    "network": ["ntfy.sh", "self-hosted"],
    "secrets": ["topic_url"],
    "data": ["read:events"]
  },
  "compat": { "openflow": ">=2.1 <3" },

  "verification": {                                    // 由 CI/AdapterVerify 生成，人工不得手改
    "status": "passed", "badge": "verified",
    "tests": "tests/plugin_notify_ntfy_contract_test.php",
    "checked_at": "2026-09-18T12:00:00+08:00"
  }
}
```

**字段规则**

| 规则 | 说明 |
|------|------|
| `permissions ⊇ surfaces` | 声明用了 `block` 就必须有 `block` 权限；缺一即验证失败 |
| `capabilities` 最小集 | 只列真正需要的域名/密钥/数据范围；多声明即降级为 `needs-review` |
| `source.license` 白名单 | MIT / Apache-2.0 / BSD / ISC / MPL-2.0 通过；GPL/AGPL/SSPL 为 `needs-review`；无许可证 = `blocked` |
| `verification` 只读 | 人审只能改 `status` 的 `needs-review → passed`，不得手写 `passed` |
| 版本 pin | `source.version` 必填（tag/commit），用于 Loop 检测上游变更 |

---

## 三、能力 → 落点映射表

> 由首批筛选（7 个功能能力类）反推，`AdapterForge` 按此表决策生成哪类代码。

| 上游特征 | 我们需要的功能 | 落点（扩展点） | 生成物要点 | 不做什么 |
|----------|----------------|----------------|------------|----------|
| 官方 REST/GraphQL 读接口 | 拉数据入湖 | `ingest` + `schedule` | 增量游标、字段映射到 CDP 事件、限速退避 | 不落库对方禁止留存的数据 |
| Webhook/事件回调 | 事件入站触发流程 | `hook` | 签名校验、幂等键、失败重投 | 不把上游原始 payload 全量存库 |
| 有"发消息/发内容"接口 | 触达/分发 | `publish` | 通道适配器 + 抑制名单 + 归因参数 | 不做群发轰炸；遵守平台频控 |
| 可作为数据源（RSS/API/抓取） | 情报与选题 | `ingest` + `api_route` | 去重、热度打分、条目规范化 | 不抓取 robots 禁止/需登录的页面 |
| 有支付/订阅接口 | 收款补充通道 | `api_route` | 下单/回调/对账三段，状态机对齐 PayFlow | 不替代 PayFlow 主账本 |
| CRM/工单系统 | 线索双向同步 | `api_route` + `hook` | 双向 mapping、冲突策略（以我方为准） | 不复制对方的 CRM 功能 |
| 工作流/Agent 引擎 | 能力编排补充 | `api_route` + `mcp_tool` | 以 MCP/HTTP 暴露为可调用工具 | 不把它的运行时嵌进基座 |

---

## 四、验证闸门（上架前必过，全部自动化）

| # | 检查项 | 工具/依据 | 不通过时 |
|---|--------|-----------|----------|
| 1 | manifest 合法（字段齐全、`permissions ⊇ surfaces`） | `lib/AdapterIntake.php` + 契约测试 | `blocked` |
| 2 | 许可证白名单 | intake 读取仓库 LICENSE（spdx） | 非白名单 `needs-review`；无证 `blocked` |
| 3 | 契约测试（清单/路由形状/错误处理/幂等） | `tests/plugin_<id>_contract_test.php` | `blocked` |
| 4 | 静态分析（插件目录） | PHPStan 基线机制 | 有新增错误 `blocked` |
| 5 | 沙箱能力试跑 | `ArtifactSandbox::sandbox_check_permissions()` / `sandbox_run()` | 越权 `blocked` |

产出：`verification-report.json` + 市场详情页徽章（`verified` / `needs-review` / `blocked`）。
**原则：AI 可以写代码，但不能给自己发徽章——徽章只由闸门生成。**

---

## 五、合规红线（硬约束）

1. 只用**官方公开 API / 官方 MCP / 官方 SDK**；不逆向私有接口
2. 遵守上游 ToS、rate limit 与 robots；触发限速即退避并记录
3. 凭据只存基座密钥区（`capabilities.secrets` 声明），**绝不写进插件代码或日志**
4. 不代理用户凭据；需要用户授权的走 OAuth/令牌交换
5. 上游要求署名/链接的，展示时保留

---

## 六、生命周期 Loop（上游变更怎么办）

```
上游发版/改接口
   ↓ 检测：release tag / CHANGELOG / OpenAPI diff（schedule 任务）
   ↓ 判定：breaking / minor / patch
   ↓ 重生成：AdapterForge 局部重跑（只改受影响落点）
   ↓ 回归：契约测试 + 沙箱试跑 + 静态分析
   ↓ 灰度：enabled_by_default=false 的实例先跑，观察 48h
   ↓ 提升：verified 徽章刷新；breaking 变更在市场页提示升级
```

---

## 七、纵向切片：`GitHub repo → 适配草稿 → 验证报告 → 待上架`

| 步骤 | 产物 | 现状 |
|------|------|------|
| 1. 筛选候选 | `data/ecosystem/candidates.json` + `ADAPTER-CANDIDATES.md` | ✅ 已完成（7 类 × 4） |
| 2. 源接入与能力画像 | `capability-profile.json` | ✅ `lib/AdapterIntake.php` |
| 3. 映射与合成 | `plugins/_drafts/<id>/{plugin.json,plugin.php}` + 契约测试 | ✅ `lib/AdapterForge.php` |
| 4. 验证闸门与报告 | `verification-report.json` + 徽章 | ✅ `lib/AdapterVerify.php` |
| 0. 入口 | `data/ecosystem/submissions.json`（第三方自助 + 候选池灌入） | ✅ `lib/AdapterSubmission.php`（见 §八） |
| 3.5 编排 | intake→forge→闸门 的顺序只写一处 | ✅ `lib/AdapterPipeline.php`（CLI 与 worker 共用） |
| 5. 人审与上架 | 草稿进 `data/ecosystem/review-queue.json` → 市场 | ✅ `admin/ecosystem.php`（`/xmp/ecosystem`）+ `scripts/review-adapters.php` |

### 7.1 第二轮：AI 补全真实调用（已跑通）

```bash
php scripts/forge-adapter.php binwiederhier/ntfy --complete
```

**试点结果（ntfy）**：模板骨架 3 处 `TODO(适配)` → AI 依据官方文档补全 → **TODO 归零**，
代码使用 ntfy 官方 publish API（`POST /<topic>`，字段 topic/message/title/priority/tags/click）、
连接超时 5s / 总超时 15s（可配）、仅网络错误与 5xx 指数退避重试（4xx 快速失败）、
凭据只经 `plugin_config()`（Bearer/Basic），**零硬编码密钥** → 通过 level 5 静态分析与契约测试 → `passed / verified`。

**安全设计（三道闸门，缺一不可）**

| 闸门 | 作用 | 不过时 |
|------|------|--------|
| 安全栅栏 `adapter_forge_guard()` | 拒绝硬编码密钥/带凭据 URL；拒绝新增未声明落点；拒绝删除既有注册 | 不落盘 |
| 重新验证前撤章 | 旧 `verified` 会让 `no-self-claim` 误判，故重验前必须置回 `pending` | — |
| 闸门复核 + 回滚 | 新代码必须过 5 项闸门；否则从 `plugin.php.prev.bak` 回滚 | 回到调用前版本 |

**留档**：`plugin.template.php.bak`（模板，供对比）、`plugin.php.prev.bak`（本次调用前快照，回滚用）、
`plugin.ai.php`（AI 原始产物，**即使被拦下也保留**供人审）。

### 7.2 批跑实测（6 类 7 个候选，含一次自动修复重试）

| 候选 | 首轮 | 修复后 | 说明 |
|------|------|--------|------|
| ntfy / krayin / apprise / nanobot | ✓ | ✓ | 一次成功（第 1~2 次尝试） |
| hyperswitch | ✗ 语法错 | ✓ | 第 2 次尝试修好 |
| umami | ✗ 越权加 schedule | ✓ | 提示词改为 `@adapter-suggest` 注释后修好 |
| praw | ✗ 幻觉用 WordPress 函数 | ✓ | 系统提示词写明运行环境后修好 |

**结论（如实记录）**：AI 补全**首轮成功率约 50~70%，且结果不稳定**（同一候选换一次跑可能成或败）；
加一次修复重试后 **7/7 均可跑通**，但多数需要 2 次。因此：
- **不能假设一次成功**：流水线必须有多轮重试 + 人工兜底
- **闸门是底线**：失败一律自动回滚，坏代码不会留在草稿里（`plugin.ai.php` 留档供人审）

**首轮失败四类归因与对应修复（都已落地）**
| 失败形态 | 根因 | 修复 |
|----------|------|------|
| 花括号不平衡 / 语法错 | `max_tokens` 不足被截断 | 额度 3000→8000；加花括号平衡检测 + `php -l` 语法前置 |
| 越权新增落点（hook/schedule/block） | 提示词未明确禁止 | 明确要求改写成 `@adapter-suggest: <surface> 理由` 注释 |
| PHPStan 报错 | 错误信息被截断（120 字）无法自修 | 回喂最多 6 条「文件:行 错误」；修复轮据此改 |
| 用框架专属函数（`wp_remote_get` 等） | 未声明运行环境 | 系统提示词写明：PHP 8.3 + 自研 PluginSystem，非 WordPress/Laravel，HTTP 用 curl_* |

### 7.3 人审上架闭环（已完成）

```
草稿(plugins/_drafts/<id>) → 队列(data/ecosystem/review-queue.json)
   → 后台 /xmp/ecosystem 或 CLI（批准 / 拒绝 / 上架）
   → plugins/<id> + 注册表 data/plugins.json（enabled=false）
   → 生态市场以「官方适配 · 免费」展示
```

**队列规则**：`blocked` 不可上架；`needs-review` 需人工批准；`passed` 可直接上架；同名目标拒绝；
上架后 **默认不启用**，需到后台「插件」页开启（安装即执行第三方代码，交给显式动作）。

**诚信规则（新增）**：骨架里还有 `TODO(适配)` → 闸门只给 `needs-review`，**不得 verified**；
队列读取时对历史草稿同样按 TODO 降级。上架页会拦并提示。

**官方适配 vs 社区投稿**：两者走**同一套插件能力与验证闸门**，区别只在这三处——
① 来源：官方适配由上架流程写入 `adapter: true` / `official: true`（清单自描述，市场据此展示徽章）；
② 定价：官方适配 `price: 0`（免费）；
③ 责任：官方适配带上游来源与许可证标注（详情页可见），社区投稿作者自负其责。

**已验证**：`binwiederhier/ntfy`（Apache-2.0，v2.28.0，AI 补全 0 TODO，99 行）上架 →
市场列表出现「官方适配」徽章、详情页显示"官方主动适配"与上游链接、后台「已上架适配」表格可跳到插件页启用。

---

## 八、自助入驻（2026-09-20 新增）

> 在此之前，整条流水线只有**我们**能触发（`php scripts/forge-adapter.php <repo>`）。
> 于是生态市场的供给上限 = 我们自己的手速：28 个候选 → 人审队列 4 → 实际上架 1。
> 「生态」这个词要成立，入口必须对外开放——**他们来入驻**，而不是我们去适配。

### 8.1 两段队列

```
第三方（/developers#submit）──┐
                             ├─▶ 提交队列 submissions.json ──cron──▶ 流水线 ──▶ 人审队列 review-queue.json ──▶ 上架
候选池（seed，运维触发）──────┘        （限流·防重·重试）      （intake→forge→闸门）      （批准/拒绝/上架）
```

**关键取舍：提交与执行解耦。** 提交是毫秒级的写队列，在 Web 请求里完成；
跑流水线要联网抓仓库、可能调模型，几十秒起步——绝不能放在 Web 请求里，必然超时。

存量候选池的「批量吞吐」与第三方的「自助入驻」**走同一条队列、同一条流水线、同一个后台**，
而不是两套平行实现——否则两边会各自漂移。

### 8.2 入口

| 通道 | 地址 | 鉴权 |
|---|---|---|
| 前台表单 | `/developers#submit` | 需登录会员 |
| API 提交 | `POST /api/developer`（`action=submit_adapter`，字段 `source` / `note`） | 需登录会员 |
| API 查进度 | `GET /api/developer?action=adapter_status&ticket=…` | **无需登录**（上游维护者不必先注册才能看进度） |
| 后台 | `/xmp/ecosystem` 顶部「自助提交队列」 | `plugins` 权限 |
| 命令行 | `php scripts/adapter-queue.php list\|add\|seed\|run\|requeue\|reject\|config` | 服务器本机 |

### 8.3 防滥用（入口一开就可能被刷，AI 额度是真金白银）

| 闸门 | 默认值 | 位置 |
|---|---|---|
| 总开关 | 开 | `data/ecosystem/intake-config.json` |
| 每人每日 | 3 | 同上（`per_submitter_daily`） |
| 全站每日 | 20 | 同上（`global_daily`） |
| 单轮 cron 处理 | 1 条 | 同上（`per_tick`） |
| 失败重试上限 | 2 | 同上（`max_attempts`） |
| 同仓库防重 | 排队中/进行中/已处理的不再受理，返回原受理编号 | `adapter_submit()` |
| 已有草稿 / 已上架 | 直接拒绝 | `adapter_submit()` |
| 无许可证短路 | 在 intake 后即 `blocked`，**不进入合成**（不白花额度） | `adapter_pipeline_run()` |

### 8.4 状态机

```
queued ──claim(原子)──▶ running ──┬─ 闸门跑完（passed 或未过）──▶ done    草稿保留，转人审
                                  └─ 异常（解析/拉取/合成失败）──▶ 未达上限则回 queued 自动重试
                                                                 达上限则 failed
任何状态 ──人工──▶ rejected ／ requeue（**重置重试次数**）
```

> 注意两个刻意的设计：
> ① **没过闸门不算失败**——那是结论，不是故障；草稿保留给人审看失败项。
>   只有连闸门都没跑到才算 `failed`。
> ② **人工 requeue 会重置 attempts**——否则一条用满重试的记录点「重排」后会立刻又变 failed，
>   操作者看到的是"点了没反应"。

### 8.5 隐私

公开状态接口（`adapter_sub_public()`）只返回仓库、状态、闸门结论、失败项与展示名，
**不返回提交者邮箱或会员 ID**——凭受理编号就能查的接口，不能顺带泄露提交人。

### 8.6 相关文件

| 文件 | 职责 |
|---|---|
| `lib/AdapterSubmission.php` | 提交队列：校验 / 防重 / 限流 / 状态机 / worker / 候选池灌入 |
| `lib/AdapterPipeline.php` | **流水线编排的单一来源**（CLI、cron worker、后台「立刻跑一条」共用） |
| `api/developer.php` | `submit_adapter` / `adapter_status` |
| `api/cron.php` | worker tick（按配额逐条跑） |
| `admin/ecosystem.php` | 后台两段队列与配额设置 |
| `scripts/adapter-queue.php` | 命令行 |
| `tests/adapter_submission_test.php` | 契约测试（全程离线：网络与 AI 都是注入的假实现） |
