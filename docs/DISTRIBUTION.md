# 分发与发布（DISTRIBUTION）

> 目标：外部用户能用四种方式拿到同一版本；每次发布都有校验和与回滚路径。现状：仓库只有 clone，GitHub 上 3 个 Release 仍是 **Draft**。

## 1. 四种交付

| 形态 | 面向 | 产物 | 升级方式 |
|---|---|---|---|
| git clone | 会改代码的人 | 仓库 | git fetch/checkout tag |
| Release 包 | 不装 git | 源码压缩包 + 校验和 | 解压覆盖代码，跳过 data/uploads |
| 容器镜像 | NAS / 想省事 | GHCR 镜像（amd64 + arm64） | 拉新 tag，重建容器 |
| 一键脚本 | 零基础 | install.sh | 重跑脚本（幂等） |

## 2. 版本与标签

- 版本号唯一真源：仓库根的 VERSION 文件；发布说明来自 CHANGELOG。
- 标签格式 vX.Y.Z，与 VERSION 一致；镜像 tag 同步为 vX.Y.Z，另设 latest 指向最新稳定版。
- 预发布用 vX.Y.Z-rc.N，不进 latest。

## 3. Release 包

内容：源码（不含 .git、vendor、node_modules、data、uploads、.env、ops）。
附带：`SHA256SUMS`（逐文件或整包校验）。
升级规则写进包内 README：只覆盖代码；data 与 uploads 原样保留；升级前先备份。

## 4. 容器镜像

- 仓库：GHCR（与 GitHub 同账号，无需另建凭据）。
- 架构：amd64 + arm64（NAS 多为 arm）。
- 运行约定：数据卷挂两个目录；环境变量按 ENV-MATRIX；健康检查打首页。
- 镜像不内置 data/uploads，也不内置 .env。

## 5. 一键脚本

范围：先支持 Ubuntu/Debian；其他系统标"待验证"。
行为：检测 Docker → 拉镜像或 clone → 起服务 → 打印安装向导地址；重复执行幂等；提供 `--dry-run` 只打印计划。
失败处理：任一步失败即停止并打印"下一步该看什么"，不做静默降级。

## 6. 发布流程（把 Draft 变正式）

1. **前置**：CI 全绿（lint / PHPUnit / 契约 / 守卫）。
2. **版本**：更新 VERSION 与 CHANGELOG；确认 `OF_SHELL_VER` 已 bump（若前端有改动）。
3. **打包**：生成 Release 包与校验和；构建并推送镜像（两个架构）。
4. **发布**：把 Draft Release 补齐说明并发布；确认 tag 指向正确提交。
5. **验证**：从 Release 页下载包解压能起站；`docker pull` 新 tag 能起容器；两条各跑一次首页 200。
6. **公告**：README 的版本徽章更新；必要时在 CHANGELOG 顶部加"升级注意"。

## 7. 校验与回滚

- 下载后校验：`sha256sum -c SHA256SUMS`；镜像用 digest 固定。
- 回滚：切回上一个 tag 的包或镜像；data/uploads 不动；按 docs/guides/upgrade-rollback.md 验证。

## 8. 待验证

- CentOS / Alpine / Windows(WSL) 的一键脚本。
- 镜像在多架构 NAS 上的实际启动（需要真机）。
- Release 包在无 git 环境下的完整升级路径。

## 9. 验收

- 四种交付各有一个可下载/可拉取的产物，版本号一致。
- 新用户按 README 的任一方式能在 10 分钟内看到首页。
- 发布流程走通一次：从打 tag 到 Release 正式可见，全程有校验和与回滚说明。
