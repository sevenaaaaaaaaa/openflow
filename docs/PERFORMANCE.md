# CDP 事件表性能：先量后治

> 复现：`php tests/events_index_bench.php 520000`

## 结论先说

D1 原本的方案是「给 CDP 加 Redis 分层缓存」。这个方案的前提在当前代码里
**已经不成立**，真正的瓶颈也不在缓存层。改成补索引之后，工作台首屏
从约 500ms 降到约 80ms，没引入任何新组件。

## 为什么不是 Redis

任务清单写 D1 的时候，假设是「CdpSystem 把整表读进内存」。翻代码：

```php
// lib/CdpSystem.php:117
public static function allEvents(int $limit = 10000): array
```

它走的是 SQLite + `ORDER BY id DESC LIMIT ?` + 进程内静态缓存，不是全表
加载。缓存这一层已经有了。再叠一层 Redis，缓存的是同一批查询结果，
而**每一次缓存未命中，底下那条查询仍然是 52 万行全表扫描**——峰值和
冷启动的体验一点没变，还多了一个要运维的组件、一份要处理的缓存失效。

真正的问题在 schema。`events` 是全库最大的表，建表语句里只有一条索引：

```php
// lib/Database.php:99
CREATE UNIQUE INDEX idx_events_message ON events(message_id) WHERE message_id != ''
```

那是给事件去重用的。**所有分析查询没有一条能用上索引。**
对照之下，`coupons`、`addresses`、`shipments` 这些几百行的小表反倒都建了索引。

## 实测

同构表灌 52 万行，跑的是代码里真实存在的 12 条查询（每条取 3 次最好成绩）：

| 查询 | 加索引前 | 加索引后 | |
|---|---:|---:|---|
| 工作台 · 30 天 UV/PV | 85.5 ms | 19.1 ms | 快 4.5× |
| 工作台 · DAU | 64.4 ms | 0.1 ms | 快 724× |
| 工作台 · MAU | 84.7 ms | 24.8 ms | 快 3.4× |
| 工作台 · 热门页面 | 190.6 ms | 35.4 ms | 快 5.4× |
| 工作台 · 分时段 | 75.7 ms | 2.7 ms | 快 28× |
| 归因 · UTM 落地页 | 60.5 ms | 54.9 ms | 持平 |
| 热力图 · 点击事件 | 0.6 ms | 1.7 ms | **慢 3×** |
| 画像 · 单访客事件流 | 75.5 ms | 0.1 ms | 快 514× |
| 流程 · 事件名分布 | 221.2 ms | 37.0 ms | 快 6.0× |
| 流程 · 已识别用户数 | 60.0 ms | 0.7 ms | 快 80× |
| CDP · 最近事件分页 | 7.8 ms | 7.8 ms | 持平（走主键倒序，本来就快） |
| 留存清理 · 过期删除 | 66.5 ms | 12.6 ms | 快 5.3× |

工作台首屏一次要跑其中五六条，加起来的差别就是 500ms 与 80ms。

## 建了哪五条，为什么

```sql
CREATE INDEX idx_events_event_created ON events(event, created_at);
CREATE INDEX idx_events_created       ON events(created_at);
CREATE INDEX idx_events_uid           ON events(uid, id);
CREATE INDEX idx_events_event_page    ON events(event, page);
CREATE INDEX idx_events_member        ON events(member_id) WHERE member_id != '';
```

- `(event, created_at)`：`event=? AND created_at>=?` 是最常见的谓词形状。
  最左前缀让只按 `event` 过滤的查询也能用上，一条顶两条。
- `(created_at)`：DAU/WAU/MAU 和留存清理只按时间过滤，用不到 event 列。
- `(uid, id)`：画像页按访客取事件流，还要 `ORDER BY id DESC`——把 id 放进
  索引，排序直接走索引顺序，不用回表再排。
- `(event, page)`：热门页面是 `event=? GROUP BY page`，前一条索引帮不了
  GROUP BY，SQLite 得建临时 B 树。这条把分组也变成索引顺序扫描。
- `(member_id) WHERE member_id != ''`：`!=` 是不等值，普通索引用不上；
  部分索引只收非空行，生产里约 1/9 的事件带 member_id，索引也就只有
  整表的 1/9 大。

## 代价，以及一个反直觉的发现

- **磁盘**：76 MB → 134 MB，索引占 57.8 MB（+76%）。
- **写入**：带索引写 5000 条约 37ms，合 0.01 ms/条。埋点是写多读少的场景，
  这个开销可以忽略。
- **首次部署**：`Database::migrate()` 每次请求都跑，但 `IF NOT EXISTS` 之后
  只是查一次目录。**第一个**请求要等索引建完，本地 52 万行约 2.2 秒，
  生产磁盘慢的话可能十几秒。建议低峰期部署，或先手工建好索引再上代码。

反直觉的一条：**加索引让「热力图」慢了 3 倍**（0.6ms → 1.7ms）。
原因是那条查询是 `ORDER BY id DESC LIMIT 500`，本来从表尾倒着扫 500 行
就够了，加索引后优化器改走 event 索引，反而要多做一次排序。
绝对值只有 1ms，不值得为它做什么，但它说明**索引不是越多越好**。

同样的事在 `(event, page)` 上也发生过：加它之前「归因 · UTM 落地页」是
20.9ms，加完变成 54.9ms——优化器为那条查询挑了不合适的索引。
但「热门页面」同时从 190ms 降到 35ms，两条加起来 225ms → 90ms，
所以还是留着。**这是个权衡，不是免费的午餐**，写在这里是为了下次有人
想再加索引时，知道要先量一遍全套，而不是只量自己关心的那条。

## 什么时候才真的需要 Redis

索引解决的是「单条查询扫太多行」。等下面这些情况出现，再谈缓存层不迟：

- 事件量到千万级，`COUNT(DISTINCT uid)` 这类聚合即便走索引也要秒级；
- 工作台并发高到 SQLite 的单写锁成为瓶颈；
- 需要跨进程共享计算结果（当前进程内静态缓存只在单次请求内有效）。

到那一步，更该先考虑的其实是**预聚合日表**（按天把 UV/PV 汇总进一张小表），
而不是缓存原始查询——聚合表既解决速度，又解决并发，还顺便解决了
`allEvents()` 的 10000 条上限带来的统计偏差。
