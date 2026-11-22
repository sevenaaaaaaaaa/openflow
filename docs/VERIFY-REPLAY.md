# 冷启动回放记录（P4 · 2026-11-23）

> 目的：验证「新克隆 + 按文档操作」能否跑通；只记录实际观察。
> 环境：macOS，PHP CLI 内置服务器，副本不含 `.git` / `data/` / `uploads/` / vendor。

## 1. 方法

```bash
rsync -a --exclude .git --exclude data --exclude uploads --exclude vendor ./ /tmp/of-replay/
mkdir -p /tmp/of-replay/data /tmp/of-replay/uploads
cd /tmp/of-replay && php -S 127.0.0.1:8099 router.php
```

## 2. 结果

| 探针 | 期望 | 实际 | 判定 |
|---|---|---|---|
| GET / | 200，站点首页 | 200，标题为「林下 Understory · OpenFlow 增长操作系统」 | 通过 |
| GET /setup-wizard.php | 200，显示环境检查 | 200，页面含「环境检查」 | 通过 |
| GET /xmp/login | 200，后台登录页 | 200，标题为「登录 / OpenFlow 运营台」 | 通过 |
| bash deploy/sync.sh --help | 退出 0 | 退出 0 | 通过 |
| python3 scripts/deploy.py --help | 退出 0 | 退出 0 | 通过 |
| 无本机配置时 deploy.py --dry-run | 报缺目标并退出 2 | 退出 2，提示指向 --init-env | 通过（修正后） |

## 3. 偏差与处理

1. 未安装时 GET / 不跳向导：干净副本没有 settings.json，访问 / 仍返回 200 首页，而不是跳安装向导。文档「首次访问进入安装向导」不准确。处理：G1 改写为「访问 /setup-wizard.php 开始安装」；是否在产品层强制跳转留待决策。
2. 向导首屏只有环境检查：站点设置与管理员创建要等环境检查通过后才出现，文档未写这个前置。处理：G1 的 quickstart 补一句。
3. 告警顺序：无本机配置时原先先打印属主告警再报缺目标，读起来像属主是主因。处理：已改为先报缺目标（退出 2），属主告警只在目标齐全后出现；复测通过。

## 4. 结论

文档主线（取代码 → 起服务 → 走向导 → 后台登录）在干净副本里可跑通；两条文案偏差转入 G1，一条交互顺序问题已修复。历史重写与本机配置补齐不属本次回放范围，仍在 P0 待办。
