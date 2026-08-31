# OpenFlow 演进路线（一人公司 → 中型 → 大型）

> **核心认知**：Tier 1/2/3 不是三种产品，是同一系统的三个规模档位。
> 演进 = 换底座（存储 + 运行方式），不换楼（TIPS 逻辑 + PI 智能层 + 后台）。
> 每一档是上一档的自然生长，不是推倒重来。

---

## ⚠️ 2026-08-31 修订：轴已定为「细胞式」，本文第五、六、八章相应改写

初版这份文档在 Tier 3 里混写了两条互相打架的路：
一行说"每租户独立 `data/<tenant_id>/` 天然隔离"（横向），
紧接着几行说"主库换 PostgreSQL、事件走 ClickHouse"（纵向）。**只能留一条。**

**已定：走横向（细胞式）。** 理由不是技术偏好，是与三条既定主张一致：

| | A · 纵向（已删） | **B · 细胞式（采用）** |
|---|---|---|
| 形状 | 1 个实例长到 10 亿行 | 1 万个租户，每个 10 万行 |
| 像什么 | 盖一栋 100 层的楼 | 盖一万栋一样的小房子 |
| 存储 | PostgreSQL + ClickHouse | 每租户一个 SQLite |
| "数据 100% 本地可控" | 破功（都进一个大库） | 保住 |
| 目标用户 | 一家大公司 | **一万个一人公司** |
| `FederatedGrowth` | 没有存在必要 | **就是核心机制** |

> 面向"一人公司 / 超级个体 / 创作者"的产品，**一万个他们加起来才叫大**。
> 一个一人公司永远不会有 10 亿条数据。
> 完整论证见 `docs/ROADMAP.md` 第二部分第 3 节。

**地基已落地（2026-08-31）**：`admin/config.php` 里加了 `OF_MULTI_TENANT` 开关，
按域名解析数据目录，默认关闭、开启后天然隔离。全仓 861 处用 `DATA_DIR` 的地方一处没改。
验收见 `tests/multitenant_test.php`。

**另需修正的两条事实**（实测，见 `docs/AUDIT-08-EVOLUTION-REVIEW.md`）：

1. 第一章说"约束是数据访问散落各处"——**诊断偏了一层**。真正的约束是
   「整批读进内存 → 改一条 → 整批写回」这个**访问模式**，跟存储选型无关：
   事件表早就在 SQLite 上，单条埋点仍要 134ms / 26MB。已修，现在 0.34ms。
2. 第四章说"多用户角色（roles.json 已支持）"——`roles.json` **不存在**；
   角色映射写在代码里（`role_perms()`）。后台权限覆盖 169/191 页，做得不差；
   真正的缺口在 API 层（92 个端点只有 1 个做权限判断）。

---

## 一、核心理念

**当前架构（PHP + SQLite + JSON）不是"上限"，而是最轻量的一档。**

真正的工程约束只有一个：**数据访问方式散落各处**——
- 87 个 lib 文件直接读写 JSON
- 14 个 lib 文件走 Database 层（SQLite）
- 178 个 admin 页面、88 个 API 端点

演进的关键不是换技术，而是**把数据访问收敛到可替换的抽象层**。
抽象层立住了，底层从 SQLite 换成 DuckDB/Postgres/ClickHouse 只是换驱动的事。

```
三样筹码不因规模变化而失去：
  1. CDP 实时画像（身份合并 + 行为实时可查）
  2. 常驻 AI 增长引擎（GrowthEngine 每日自动选题/成文/巡检/优化）
  3. MCP / 知识底座（内容与能力可被 AI Agent 直接调用）

这些是护城河，任何规模档位都保留。
```

---

## 二、三档规模画像

| 维度 | 🟢 Tier 1 一人公司（当前） | 🟡 Tier 2 中型公司 | 🔴 Tier 3 大型/平台 |
|---|---|---|---|
| 目标用户 | 超级个体 / 创作者 / 无团队 | 3-30 人增长/市场团队 | SaaS / 平台 / 大型企业 |
| 事件量 | < 100 万 | 100 万 - 1 亿 | 10 亿+ |
| 活跃用户 | < 1 万 | 1 万 - 100 万 | 100 万+ |
| 部署形态 | 宝塔一键 / 单机 | 单机增强 / 多站点 | 分布式 / 多租户 SaaS |
| 存储 | JSON + SQLite | SQLite/MySQL/DuckDB 为主（事件已切 MySQL） | PostgreSQL + ClickHouse |
| 运行 | Apache + PHP-FPM + cron | + Redis + CF Workers | 服务拆分 + 高可用 |
| 团队协作 | 单管理员 | 多用户角色 | 租户隔离 |

**关键原则**：每一档仍保持"数据可控、可导出、可自部署"，只是从"零依赖"逐步升级为"按需依赖"。

---

## 三、演进架构总览

```
┌──────────────────────────────────────────────────────────────┐
│  产品逻辑层（三层，全档位保留）                                  │
│  Layer 3 开发者生态 · Layer 2 TIPS 框架 · Layer 1 PI 智能层    │
├──────────────────────────────────────────────────────────────┤
│  数据访问抽象层 DataStore（演进地基，先行设计）                  │
│  DataStore::get / query / stream · 存储适配器可替换             │
├──────────────────────────────────────────────────────────────┤
│  ┌─────────┬──────────┬────────────┬──────────────┐           │
│  │ Tier 1  │ Tier 2   │ Tier 3     │ Tier 3       │           │
│  │ JSON    │ SQLite   │ DuckDB     │ PostgreSQL + │           │
│  │ 文件     │ (已有)   │ (分析冷层)  │ ClickHouse   │           │
│  └─────────┴──────────┴────────────┴──────────────┘           │
└──────────────────────────────────────────────────────────────┘
```

---

## 四、🟡 Tier 2 升级点（中型公司）

**触发信号**：事件量 > 500 万 · SQLite 写锁每周 > 3 次 · 团队 > 3 人协作 · 需要多站点

| 升级点 | 方案 | 现状准备 |
|---|---|---|
| 存储分层 | 热(7天)→Redis；温→SQLite/MySQL；冷→DuckDB 分区 | ✅ **事件已切 MySQL**（`EventStore` 双驱动）；热层/冷层待 Redis/DuckDB |
| CDP 分析 | SQLite/MySQL → **DuckDB**（嵌入式列式，单文件，零运维） | 事件写入已走统一 EventStore，DuckDB 可直接作为冷层接入 |
| 队列/调度 | cron → **Redis/Valkey**（延迟队列、去重、限流） | `Cache.php` 已原生支持 Redis（自动探测）|
| 实时触达 | 无 → **Cloudflare Workers**（WebSocket/SSE 网关） | 站点已在 CF 后面，加 Worker 即得实时层 |
| 团队协作 | 单管理员 → 多用户角色（roles.json 已支持） | 角色/权限矩阵已就位 |
| 多站点 | 单站 → 多域名/子域名 | `.htaccess` 已支持多域 |

**中型公司核心形态**：仍是单机部署（宝塔 + 8GB 内存即可），但存储从"全 JSON"演进为"SQLite/MySQL/DuckDB 为主 + JSON 保留配置"。事件表已可跑 MySQL（Tier 1.5），分析冷层接 DuckDB 即可到 Tier 2。

---

## 五、🔴 Tier 3 升级点（大型 = 很多个小租户，不是一个大客户）

> 已按「细胞式」改写。原来的纵向方案（PostgreSQL 主库 / ClickHouse 事件管道 /
> 服务拆分 / 主从副本）**已整段删除**——那条路要求把所有租户的数据倒进一个大库，
> 与"数据 100% 本地可控"直接冲突，而且面向一人公司的产品永远撞不到那个规模。

| 升级点 | 方案 | 状态 |
|---|---|---|
| 多租户隔离 | 每租户独立 `data/<tenant>/` + 独立 SQLite，按域名解析 | ✅ **已落地**（`OF_MULTI_TENANT`）|
| 租户开通 | 建目录 = 开租户；配套开通/停用/导出/销毁的一套动作 | ⬜ 等第二个租户 |
| 每租户成本核算 | AI 用量已按功能记账，再按租户维度切一刀 | 🟡 电表已有（`AiBudget`），差按租户切 |
| 跨租户智能 | 匿名聚合 + k-匿名，各站互相变聪明 | 🟡 骨架已有（`FederatedGrowth`）|
| 单租户体量 | 仍是 SQLite；真有个别大租户再单独给它上 DuckDB 冷层 | ⬜ 按需 |
| 高可用 | 租户之间天然隔离，一个租户出问题不影响别人；<br>横向扩展 = 多机分租户，不是主从复制 | ⬜ 按需 |

**关键**：细胞式的难点不在数据库，在**供给编排**——开通流程、资源隔离、
成本归集、控制面。这些都可以等真有第二个租户再建，不必提前。

---

## 六、地基一：DataStore 数据访问抽象层（⏸ 暂缓，形状待换）

> **2026-08-31：这一章暂缓执行。** 三个原因（实测见 `AUDIT-08` 第四节）：
>
> 1. **工作量低估了一个数量级**：不是 87 个文件，是 **1389 处调用点**
>    （lib 603 · admin 392 · api 160 · 其它 234）。
> 2. **它治不了真正的病**。事件表早就在 SQLite 上了，单条埋点仍要 134ms——
>    因为病在"整批读写"这个访问模式，不在存储选型。把它包进
>    `DataStore::query()`，134ms 一毫秒不少；底下换 ClickHouse 只会更慢。
> 3. **签名形状不对**。`query($type, $filter, $opts)` 要同时盖住 JSON 文件和 SQL，
>    只有两个结局：取最小公分母（表达不了 join/聚合/FTS5，复杂模块绕过它，散落原样回来），
>    或把 `$filter` 长成查询语言（那就是 ORM，也就是框架——而"无框架单体"是本项目的身份认同）。
>
> **该建什么**：不是一个泛化 DataStore，而是
> - **`EventSink`**（第七章，形状是对的）——只写、追加，接口是 `record()` 不是 `query($filter)`，
>   所以它**真能**跨 SQLite→DuckDB→ClickHouse。
> - **按数据域的 Repository**——`ProfileRepo::topByLtv(10)`、`OrderRepo::unsettled()`，
>   方法名说人话，内部随便用全量 SQL/FTS5。可按模块逐个迁、永远长不成 ORM。
>
> 下面的原始设计保留作参考，不作为施工依据。

> 目的：收敛直接读写 JSON 的调用点，让底层存储可替换。
> **现在只设计接口，不写实现**。新代码从第一天走抽象，旧代码按模块迁移。

### 接口定义（PHPDoc 级契约）

```php
<?php
/**
 * DataStore — 统一数据访问层
 * 目标：上层业务不关心数据存 JSON 还是 SQLite/DuckDB/PG
 *
 * 演进路径：
 *   Tier1: 读 JSON 文件（向后兼容现状）
 *   Tier2: 读写 SQLite / DuckDB（分析）
 *   Tier3: 读写 PostgreSQL / ClickHouse（适配器替换）
 */
class DataStore {

    /**
     * 读取单条记录
     * @param string $type   数据域（articles / cdp / orders / ...）
     * @param string $id     主键
     * @return array|null
     */
    public static function get(string $type, string $id): ?array;

    /**
     * 按条件查询
     * @param string $type   数据域
     * @param array  $filter 过滤条件（字段 => 值 或 表达式）
     * @param array  $opts   排序/分页（['order'=>..., 'limit'=>..., 'offset'=>...]）
     * @return array
     */
    public static function query(string $type, array $filter = [], array $opts = []): array;

    /**
     * 写入/更新记录
     * @param string $type 数据域
     * @param string $id   主键
     * @param array  $data 完整记录
     */
    public static function put(string $type, string $id, array $data): void;

    /**
     * 大表流式遍历（CDP 全量分析用，避免一次载入内存）
     * @param string   $type      数据域
     * @param callable $callback  每批回调 fn(array $rows)
     * @param array    $filter    过滤条件
     */
    public static function stream(string $type, callable $callback, array $filter = []): void;
}
```

### 存储适配器（可替换）

```
lib/datastore/
├── AdapterInterface.php    // get/query/put/stream 契约
├── JsonAdapter.php         // Tier1: 读 JSON 文件（兼容现状）
├── SqliteAdapter.php       // Tier2: SQLite（复用现有 Database.php）
├── DuckDBAdapter.php       // Tier2: 分析冷层（事件/日志）
├── PostgresAdapter.php     // Tier3: 主数据库
└── ClickHouseAdapter.php   // Tier3: 事件管道
```

### 迁移策略

1. **新增代码全部走 DataStore**（新功能/新 API/新后台页）
2. 旧代码按模块迁移：每迁移一个模块，该模块获得"可换存储"能力
3. 优先级：先迁移高风险模块（CDP/事件/内容），后迁移稳定模块
4. `DataStore` 内部维持现有 `json_read/json_write` 与 `Database::query` 作为 Tier1 实现

### 与现有层的关系

```
业务层（CdpSystem / ArticleSystem / ...）
    ↓ 调用
DataStore  ←→  现有 Database.php（SQLite，已有）
    ↓
适配器：JsonAdapter / SqliteAdapter / DuckDBAdapter / ...
    ↓
物理存储：JSON 文件 / openflow.db / analysis.db / PG / ClickHouse
```

---

## 七、地基二：EventSink 事件写入层（✅ 已落地为 EventStore）

> **2026-08-31：本设计已落地，实现为 `lib/EventStore.php`。**
> 事件写入/读取已统一到 EventStore，且支持 MySQL 切换（分层存储第一步）。
> 下面的设计作为契约参考，实际实现见 `lib/EventStore.php`。

> 目的：埋点写入是全系统第一个会撞墙的瓶颈，需要统一的写入插槽。

### 现状问题

`CdpSystem.php` 直接 `json_write($eventsFile, array_slice($events, -10000))`——
**写满就丢最旧数据**，且全表扫描。

### 接口定义

```php
<?php
/**
 * EventSink — 事件写入层
 * 演进路径：
 *   Tier1: 写 SQLite events 表（已有，带索引）
 *   Tier2: 7天内 Redis 热层 + SQLite 温层 + DuckDB 冷层
 *   Tier3: 写 CF Worker → ClickHouse
 */
class EventSink {

    /**
     * 记录单个事件
     * @param array $event ['event'=>..., 'uid'=>..., 'page'=>..., 'props'=>..., 'created_at'=>...]
     */
    public static function record(array $event): void;

    /**
     * 批量记录（前端埋点聚合后批量上报）
     */
    public static function recordBatch(array $events): void;

    /**
     * 分析查询（走适配器，Tier2 起指向分析引擎）
     */
    public static function analyze(string $sql, array $params = []): array;
}
```

### 分档实现

| 档位 | 实现 | 说明 |
|---|---|---|
| Tier1 | 写 SQLite events 表（复用现有 Database.php） | 已有索引，查询 1-7ms |
| Tier1.5 | **写 MySQL events 表（✅ 已落地）** | `EventStore` 双驱动：settings.json 配 `mysql_*` 则写 MySQL，否则 SQLite |
| Tier2 | 热层 Redis + 温层 SQLite + 冷层 DuckDB | 冷数据定期归档 |
| Tier3 | CF Worker 批量聚合 → ClickHouse | 亿级秒级聚合 |

### ✅ 落地记录：EventStore（2026-08-31）

**做了什么**：events 表从"固定 SQLite"升级为"SQLite/MySQL 双驱动可切换"。

**文件**：
- `lib/EventStore.php`（新增）：事件统一读写层
- `lib/Database.php`（改）：`query()/execute()` 自动路由 events 表 SQL 到 EventStore
- `lib/CdpSystem.php`（改）：事件写入/读取走 EventStore

**配置**（`data/settings.json`，不写死代码）：
```json
{
  "mysql_enabled": true,
  "mysql_host": "localhost",
  "mysql_port": 3306,
  "mysql_dbname": "openflow",
  "mysql_user": "openflow",
  "mysql_pass": "****"
}
```

**MySQL 侧**：
- 库：`openflow`（utf8mb4）；用户 `openflow`（仅 localhost）
- events 表：`message_id` 唯一索引（`uk_message`）用于去重；`event/created_at/uid/page/member_id` 分析索引
- 索引前缀策略：MySQL 5.7 不支持部分索引 → 用 `UNIQUE KEY uk_message(message_id)` 替代 SQLite 的部分唯一索引

**关键经验**：
1. **`INSERT OR IGNORE`（SQLite）↔ `ON DUPLICATE KEY UPDATE`（MySQL）**：两条 SQL 按驱动分支，不能共用
2. **MySQL 严格模式**：`GROUP BY` 需符合 `ONLY_FULL_GROUP_BY`；SQLite 宽松行为不兼容（如 `SELECT substr()...GROUP BY d` 需改写）
3. **SQLite 3.7 部分索引降级**：`CREATE UNIQUE INDEX ... WHERE` 在 SQLite<3.8 失败 → 降级时**不要**建普通唯一索引（空 message_id 会冲突），去重由代码层生成唯一 message_id 保证
4. **CLI 调试坑**：`CdpSystem.php` require `admin/config.php`，后者在 CLI 下输出错误页 → 调试事件读写应直接用 `lib/EventStore.php` 或模拟 Web 环境
5. **历史数据**：生产 63 万行事件（多为 heartbeat/scroll 无意义数据）已全部清空，MySQL 从零开始；画像（cdp_profiles 419 条）保留在 SQLite 未动

---

## 八、量化演进触发器（何时升档）

> 避免"想升级就升级"（过度工程）或"该升级不升级"（撞墙才救）。

> **2026-08-31 重标**：原表用"events 表行数"当主要触发器，量错了对象。
> 写放大的开销**不随表长增长**（读窗口固定），所以它不会先给你预警再撞墙——
> 从第一万条事件起就一直是满额的错。该量的不是"表多大"，是下面这三个。

| 指标 | 说明 | 触发 |
|---|---|---|
| **单次写入摊了多少读** | 一次写要顺带读多少行。健康值 ≈ 1 | > 10 就该查，别等表变大 |
| **p95 埋点耗时** | `tests/track_e2e_bench.php` 可测 | > 20 ms |
| **每租户月度 AI 成本** | `/xmp/ai-usage` 直接看 | 超出单租户毛利即需干预 |
| 月活跃用户 | 单租户 | > 1 万 → 考虑该租户单独优化 |
| 团队规模 | 需要多人分权 | > 3 人 → 补 API 层权限矩阵 |
| 部署形态 | 需要多站点 / 多租户 | 开 `OF_MULTI_TENANT` 即可 |

---

## 九、三档能力对照表（产品功能全保留）

| 产品能力 | Tier 1 | Tier 2 | Tier 3 |
|---|---|---|---|
| TIPS 四层（Touch/Insight/Personalize/Sell） | ✅ | ✅ | ✅ |
| Platform Intelligence（自我进化/协同修复/健康检测/AI 配置） | ✅ | ✅ | ✅ |
| 开发者生态（插件/Skills/MCP/SDK） | ✅ | ✅ | ✅ |
| 178 个 admin 页面 | ✅ | ✅ | ✅ |
| 88 个 API 端点 | ✅ | ✅ | ✅ |
| 多语言 11 种 | ✅ | ✅ | ✅ |
| 数据 100% 本地可控 | ✅ | ✅ | ✅ |
| CDP 行为分析 | SQLite | SQLite（个别大租户可加 DuckDB 冷层） | 每租户各自 SQLite；跨租户走联邦聚合 |
| 实时触达（WebSocket/SSE） | 无/SSE 直连 | CF Workers | CF Workers + 消息总线 |
| 团队协作 | 单管理员 | 多角色 | 租户隔离 + SSO |
| 部署 | 宝塔一键 | 宝塔 + Redis | 多机分租户（不是主从复制）|

---

## 十、演进原则（守则）

1. **换底座不换楼**：产品逻辑与后台永不因存储升级而重写
2. **抽象先行，实现后置**：DataStore/EventSink 先有接口契约，按需实现
3. **量化触发**：升档由数据指标触发，不凭感觉
4. **零依赖优先**：Tier 1/2 保持零外部服务依赖，这是入口档的差异化
5. **数据永远可导出**：任何档位，用户数据可一键导出（已有导出功能）
6. **每一档都是完整产品**：不是"先凑合，等大了再重写"，每档都自洽可用
