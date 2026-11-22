# 待办与执行顺序（TASKS）

> 用途：让执行者（人或 AI 会话）按队列推进，不需要每次等指令。状态：`[x]` 完成 / `[~]` 进行中 / `[ ]` 待做 / `[!]` 阻塞。
> 计划正文见 `docs/PLAN-NAS-CONFIG-AND-GITHUB-ONBOARDING.md`；每完成一项，同步改这里与 todo 列表。

## 已完成

- [x] P0 工作树脱敏：16 个文件占位符化；6 个脚本缺参即报错；新增 `scripts/check-public-surface.sh` 并接入 CI。
- [x] P0 配套：`--init-env` 引导、`.env.example` 补齐占位。
- [x] P1 配置真源：`docs/CONFIG-SOURCES.md`、`docs/ENV-MATRIX.md`。
- [x] P2 文档替换：AGENT-DEPLOY 重构为「现行 + 历史附录」；CONVENTIONS/INFRA/SERVICE/MCP 对齐；HANDOFF 归档。
- [x] P3 代码收敛：PHP/Python 两份 `.env` 解析对齐；属主与 `data/cloudflare.json` 回退改为显式告警。
- [x] P4 冷启动回放：`docs/VERIFY-REPLAY.md`（干净副本起站、三条探针、两条文案偏差）。
- [x] G1 第一批：`docs/guides/README.md`、`quickstart.md`、`go-live-checklist.md`；README 快速开始改指。
- [x] G1 第二批：`docs/guides/troubleshooting.md`（故障 Top10）、`docs/guides/upgrade-rollback.md`（升级与回滚）。
- [x] G2 Demo 设计：`docs/DEMO-ACCESS.md`（只读与模拟边界、90 天冻结回收、单客户一空间、管理入口 IA、配额）。
- [x] G2 表单扩展说明：字段映射写进 DEMO-ACCESS 附录（intent、demo_use、域名去重、线索打标）。
- [x] G3 平台指南：`docs/guides/nas.md`、`docs/guides/baota-1panel.md`。
- [x] G3 分发规划：`docs/DISTRIBUTION.md`（四种交付、版本标签、发布流程、校验与回滚）。
- [x] G4 新手引导文案：`docs/guides/first-run.md`（8 项体检、前三个动作、第一周）。
- [x] 已提交并推送：`71b392e`（公开面脱敏 + 配置真源 + 指南 + 看板）。

## 进行中

- [~] 等待代码窗口：G2 实现（演示空间开通/只读门禁/回收）与 G4 向导体检页，均需在无并发编辑时落地。

## 待做（按顺序）

## 阻塞（有明确触发条件，不空等）

- [!] P0 历史重写：需要 ①停部署窗口 30–60 分钟 ②另一会话把 `sitemap.php`、`lib/ArticleStats.php` 提交或停手。备份已就绪（`~/openflow-backups/pre-rewrite-20261123-0105.{git,bundle}`），触发后按八步执行。
- [!] 本机 `.env` 补齐：命令已就绪（`python3 scripts/deploy.py --init-env`），需在你自己终端跑一次（安全策略禁止我改 `.env`）。
- [!] P4 回滚演练：需要一次可用部署窗口与目标机访问。
- [!] G5 外部试跑：需要 3 名真实外部用户。

## 每项完成的验收口径

- 文档类：内容可执行（有命令/现象/判定），无内部地址与账号信息（守卫 exit=0）。
- 脚本类：语法检查 + 单测通过 + 干净副本回放不回归。
- 全部完成后：更新 `docs/PLAN-...` 的阶段状态与本节勾选。
