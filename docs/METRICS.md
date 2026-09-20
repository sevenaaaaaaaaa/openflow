# 项目口径指标（单一来源）

> 这一页的数字由 `php scripts/metrics.php` 算出来，不手写。
> 文档里写成 `<!‑‑m:指标名‑‑>值<!‑‑/m‑‑>`（示例用了非 ASCII 连字符，避免被自己扫到），`--sync` 刷新，`tests/metrics_contract_test.php` 盯住。

---

## 为什么需要这一页

2026-09 同一周内，同一批数字在不同文档里出现过不同版本：

| 指标 | 出现过的说法 | 真值 |
|---|---|---|
| 后台页数 | 226（POSITIONING）/ 222（文件数）/ 192（真实页面） | 按口径拆成三个数才说得清 |
| API 端点 | 113（README）/ 116（POSITIONING） | 取决于算不算 `api/v1/` |
| MCP 工具 | 31（按文本 grep 误算） | 23 |

数字一旦能各说各话，**基于数字的结论就不可信**——而这个项目的整个方法就是"先算清楚，再谈改什么"。
所以口径必须只有一个来源。

---

## 当前值

| 指标 | 值 | 口径 |
|---|---|---|
| 后台真实页面 | <!--m:admin_pages-->193<!--/m--> | `admin/*.php` 中调用了 `admin_header()`/`admin_footer()`，且不是 301 别名或片段 |
| 301 旧地址别名 | <!--m:admin_alias_301-->19<!--/m--> | `.htaccess` 里 `^xmp/<name>/?$ → [R=301]`，与父页是同一屏的两个地址 |
| 片段/工具文件 | <!--m:admin_fragments-->15<!--/m--> | `_` 开头、`config/login/logout`、以及被 include 的非页面文件 |
| API 端点 | <!--m:api_endpoints-->118<!--/m--> | `api/` 下全部 `*.php`（含 `api/v1/`） |
| MCP 工具 | <!--m:mcp_tools-->23<!--/m--> | `lib/McpTools.php` 注册表长度（`mcp-server.php` 与对外页面同源引用） |
| 核心类库文件 | <!--m:lib_files-->232<!--/m--> | `lib/*.php` |
| PHP 测试 | <!--m:php_tests-->158<!--/m--> | `tests/*_test.php` |
| 插件 | <!--m:plugins-->8<!--/m--> | `plugins/` 下的目录 |
| 前台页面 | <!--m:frontend_pages-->77<!--/m--> | 仓库根目录的 `*.php` |

---

## 用法

```bash
php scripts/metrics.php              # 人读
php scripts/metrics.php --json       # 机器读
php scripts/metrics.php --sync       # 把纳管文档里的数字刷成当前值
php scripts/metrics.php --check      # 校验一致性（CI 用；不一致退出码 1）
php tests/metrics_contract_test.php  # 契约测试（同时校验"同一个数只有一个来源"）
```

**纳管的文档**（在 `scripts/metrics.php` 的 `of_metric_docs()` 里维护）：
`README.md` · `docs/POSITIONING.md` · `docs/USABILITY-DIAGNOSIS.md` · 本页。
新文档要引用这些数字，加到那个列表里即可。

---

## 规则

1. **对外文档里的规模数字一律用标记**，不手写。手写的数字下一次改代码就会变成谎话。
2. **口径说明和数字一起交付**。`193 个后台页` 没有意义，`193 个真实页面（不含 19 个 301 别名与 15 个片段）` 才有。
3. **一个数只能有一个来源**。后台页数由 `lib/AdminInventory.php` 计算，
   可用性审计、使用埋点、本脚本全部引用它；契约测试会校验三者一致。
4. **测试红了不要改期望值**，跑 `--sync` 并确认这个变化是你有意造成的。

---

## 已知仍不由本页纳管的数字

| 数字 | 为什么不纳管 |
|---|---|
| 支持语言数 | `i18n_supported()` 是**站点配置**（`languages` 设置项），不同部署不一样，不是代码常量 |
| 代码行数 | 随时波动、对读者无信息量；需要时看 `docs/ENGINEERING-AUDIT.md` 的体检快照 |
| CI 门禁道数 | 在 `scripts/ci.sh` 里是人读的分节标题，没有结构化来源；改动频率低，暂不纳管 |
