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
| 3. 映射与合成 | `plugins/_drafts/<id>/{plugin.json,plugin.php}` + 契约测试 | ⏳ 待建 `lib/AdapterForge.php` |
| 4. 验证闸门与报告 | `verification-report.json` + 徽章 | ⏳ 待建 `lib/AdapterVerify.php` |
| 5. 人审与上架 | 草稿进 `data/ecosystem/review-queue.json` → 市场 | ⏳ 待建 `admin/ecosystem.php` |

**第一步只做通 1→2→4（合成可先用模板 + 人工补）**，验证闸门先行，避免"AI 写完没人验"。
