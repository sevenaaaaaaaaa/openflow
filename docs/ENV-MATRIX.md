# 环境变量矩阵（ENV-MATRIX）

> 与 `docs/CONFIG-SOURCES.md` 配套：那份回答"配置在哪"，这份回答"每个变量是谁在读、缺了会怎样、能不能公开"。
> 只登记变量名与语义；真实值只在两类地方——本机 `.env` 或系统钥匙串。

---

## 0. 三条规则

1. **优先级**：真实环境变量 > 仓库根 `.env` > 设置文件（`data/settings.json`）> 代码默认。逐项确认，不要一句话带过。
2. **部署目标类不设默认值**：缺失即失败，错误信息指向 `python3 scripts/deploy.py --init-env`。
3. **对外可见性**：标"公开"的变量可以出现在示例文件里（只放占位）；标"仅本机"的绝不出现在仓库、文档、Issue 模板。

## 1. 运行时（L2）

| 变量 | 读取方 | 默认 | 缺失行为 | 可见性 |
|---|---|---|---|---|
| `OF_ENV` | `admin/config.php` | 按 Host 是否 localhost 判定 | 自动判定，不报错 | 公开（名称） |
| `SITE_URL` | `admin/config.php`、页面 canonical | `data/settings.json` 的 `site_url` | 回落请求 Host | 公开（名称） |
| `OF_DATA_DIR` | `admin/config.php`、备份/导出 | `<项目根>/data` | 用默认目录 | 公开 |
| `OF_UPLOAD_DIR` | `admin/config.php`、媒体镜像 | `<项目根>/uploads` | 用默认目录 | 公开 |
| `OF_ROOT` | i18n/运维脚本 | 脚本上一级目录 | 用默认目录 | 公开 |
| `OF_MULTI_TENANT` | `admin/config.php` | 关闭 | 单站模式 | 公开 |
| `OF_TENANT_ROOT` | 同上 | `<data>/tenants` | 用默认目录 | 公开 |
| `OF_TENANT_UPLOAD_ROOT` | 同上 | 租户目录下 `uploads` | 用默认目录 | 公开 |
| `OF_OFFSITE_KEY` / `OF_OFFSITE_SECRET` | 离线备份 | 回退 `data/offsite-backup.json` | 提示"未配置" | 仅本机（值） |
| `GITHUB_TOKEN` / `API_KEY` | 部分集成 | 无 | 相关功能报未配置 | 仅本机（值） |

## 2. 部署（L1）

| 变量 | 读取方 | 默认 | 缺失行为 | 可见性 |
|---|---|---|---|---|
| `OF_DEPLOY_HOST` | `deploy.py`、`sync.sh` | **无** | 报错退出（码 2） | 仅本机 |
| `OF_DEPLOY_USER` | 同上 | **无** | 报错退出 | 仅本机 |
| `OF_DEPLOY_ROOT` | 同上 | **无** | 报错退出 | 仅本机 |
| `OF_DEPLOY_PORT` | 同上 | 22 | 用 22 | 仅本机 |
| `OF_DEPLOY_OPS_ROOT` | `sync.sh`（`ops/`） | **无** | 有 `ops/` 内容时报错 | 仅本机 |
| `OF_DEPLOY_SUDO` | `deploy.py` | 1（启用） | 用 1 | 仅本机 |
| `OF_DEPLOY_OWNER` / `OF_DEPLOY_DATA_OWNER` | `deploy.py` | 代码内按目标机设定 | 用默认属主 | 仅本机 |
| `OF_DEPLOY_STAGING` | `deploy.py` | 临时目录 | 用默认 | 仅本机 |
| `OF_SITE_URL` | `deploy.py`（缓存/purge 目标） | 站点公开地址 | 用默认 | 公开 |

## 3. 凭据与第三方（L3）

| 变量 | 读取方 | 默认 | 缺失行为 | 可见性 |
|---|---|---|---|---|
| `CF_TOKEN` | `deploy.py` purge、Worker 脚本 | 回退 `data/cloudflare.json` | 以退出码 3 失败（除非 `--allow-incomplete`） | 仅本机 |
| `OF_CF_ZONE` | purge、Worker 脚本 | 无 | purge 跳过并报错 | 仅本机 |
| `OF_CF_ACCOUNT_ID` | 两个 R2 Worker 部署脚本 | 无 | 脚本拒绝运行 | 仅本机 |
| `R2_ENDPOINT` | 资产同步、上传脚本 | 无 | 同步失败/退出码 3 | 仅本机 |
| `R2_KEY` / `R2_SECRET` | 同上 | 无 | 同上 | 仅本机 |
| `R2_BUCKET` | 同上 | 无 | 同上 | 仅本机 |
| `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` | 资产同步（别名） | 无 | 与上等价 | 仅本机 |
| `OF_GIT_PROXY` | `sync.sh` push | 无 | 直连 push | 仅本机 |
| `OF_GIT_PUSH_RETRIES` / `OF_GIT_PUSH_DELAY` | 同上 | 3 / 3 秒 | 用默认重试 | 公开 |
| `OF_UTM_TOOL_HTML` | `admin/utm-builder.php` | 仓库自带 `utm-tool.html` | 用仓库文件 | 仅本机 |

## 4. 工具与一次性（脚本级）

| 变量 | 读取方 | 默认 | 说明 | 可见性 |
|---|---|---|---|---|
| `OF_OPS_DIR` | 种子/运营脚本 | `<项目根>/ops` | 运营产物目录（gitignored） | 公开 |
| `OF_TOOL_INVENTORY_DIR` | 导航清单脚本 | 无 | 一次性导入目录 | 公开 |
| `OF_TEST_REPORT` | 契约测试 | 临时文件 | 报告输出路径 | 公开 |
| `OF_FLOW_BASELINE_REPORT` | Flow 基线 | `artifacts/` 下 | 报告输出路径 | 公开 |

## 5. 遗留（仅历史脚本）

| 变量 | 读取方 | 默认 | 缺失行为 |
|---|---|---|---|
| `OF_LEGACY_HOST` / `OF_LEGACY_ROOT` | `sync-decks.sh`（退役主机） | 无 | 直接退出并提示 |
| `OF_LEGACY_PORT` | 同上 | 22 | 用 22 |

## 6. 新增变量的流程

1. 先判断属于哪一层；同一语义只能有一个变量名。
2. 决定缺失行为：能安全降级才给默认值；涉及目标/凭据一律**缺失即失败**。
3. 更新本文与 `.env.example`（只放占位与注释，不放值）。
4. 如果变量会影响公开面，先问"它会不会把内部拓扑带进仓库"；会，就不能有默认值。
5. 在 `docs/CONFIG-SOURCES.md` 的核对手段里补一条验证方式。
