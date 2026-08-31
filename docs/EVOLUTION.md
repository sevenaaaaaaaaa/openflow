# OpenFlow 演进路线（一人公司 → 中型 → 大型）

> **核心认知**：Tier 1/2/3 不是三种产品，是同一系统的三个规模档位。
> 演进 = 换底座（存储 + 运行方式），不换楼（TIPS 逻辑 + PI 智能层 + 后台）。
> 每一档是上一档的自然生长，不是推倒重来。

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
| 存储 | JSON + SQLite | SQLite/DuckDB 为主 | PostgreSQL + ClickHouse |
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
| 存储分层 | 热(7天)→Redis；温→SQLite；冷→DuckDB 分区 | events 索引已完成；分层缓存已有规划 |
| CDP 分析 | SQLite → **DuckDB**（嵌入式列式，单文件，零运维） | 事件写入走统一抽象后可直接替代 |
| 队列/调度 | cron → **Redis/Valkey**（延迟队列、去重、限流） | `Cache.php` 已原生支持 Redis（自动探测）|
| 实时触达 | 无 → **Cloudflare Workers**（WebSocket/SSE 网关） | 站点已在 CF 后面，加 Worker 即得实时层 |
| 团队协作 | 单管理员 → 多用户角色（roles.json 已支持） | 角色/权限矩阵已就位 |
| 多站点 | 单站 → 多域名/子域名 | `.htaccess` 已支持多域 |

**中型公司核心形态**：仍是单机部署（宝塔 + 8GB 内存即可），但存储从"全 JSON"演进为"SQLite/DuckDB 为主 + JSON 保留配置"。全部仍是零外部服务依赖。

---

## 五、🔴 Tier 3 升级点（大型/平台）

| 升级点 | 方案 |
|---|---|
| 多租户 SaaS | 每租户独立 `data/<tenant_id>/` 目录 + 独立 SQLite → 天然隔离 |
| 事件管道 | 前端先打 CF 边缘 → Worker 批量聚合 → 写入 ClickHouse |
| 主数据库 | SQLite → PostgreSQL（`Database.php` 是唯一切换点）|
| 向量检索 | SQLite FTS → pgvector（知识库量级到 10 万+ 时）|
| 服务拆分 | 单进程 → 可拆分（采集/分析/触达/后台各一服务）|
| 高可用 | 单机 → 主从 + 只读副本 |

**关键**：Tier 3 需要的每一个能力，在 Tier 1/2 阶段都要有"抽象缝"，但不提前实现。

---

## 六、地基一：DataStore 数据访问抽象层（设计先行文档）

> 目的：收敛 87 个直接读写 JSON 的调用点，让底层存储可替换。
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

## 七、地基二：EventSink 事件写入层（设计先行文档）

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
| Tier2 | 热层 Redis + 温层 SQLite + 冷层 DuckDB | 冷数据定期归档 |
| Tier3 | CF Worker 批量聚合 → ClickHouse | 亿级秒级聚合 |

---

## 八、量化演进触发器（何时升档）

> 避免"想升级就升级"（过度工程）或"该升级不升级"（撞墙才救）。

| 指标 | 触发 Tier 2 | 触发 Tier 3 |
|---|---|---|
| events 表行数 | > 500 万 | > 10 亿 |
| 月活跃用户 | > 1 万 | > 100 万 |
| SQLite 写锁频率 | 每周 > 3 次 | 每日持续 |
| 团队规模 | > 3 人协作 | > 30 人 |
| 部署形态需求 | 需要多站点 | 需要 SaaS 多租户 |

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
| CDP 行为分析 | SQLite（<500万事件） | DuckDB（500万-1亿） | ClickHouse（10亿+） |
| 实时触达（WebSocket/SSE） | 无/SSE 直连 | CF Workers | CF Workers + 消息总线 |
| 团队协作 | 单管理员 | 多角色 | 租户隔离 + SSO |
| 部署 | 宝塔一键 | 宝塔 + Redis | Docker/K8s + 托管服务 |

---

## 十、演进原则（守则）

1. **换底座不换楼**：产品逻辑与后台永不因存储升级而重写
2. **抽象先行，实现后置**：DataStore/EventSink 先有接口契约，按需实现
3. **量化触发**：升档由数据指标触发，不凭感觉
4. **零依赖优先**：Tier 1/2 保持零外部服务依赖，这是入口档的差异化
5. **数据永远可导出**：任何档位，用户数据可一键导出（已有导出功能）
6. **每一档都是完整产品**：不是"先凑合，等大了再重写"，每档都自洽可用
