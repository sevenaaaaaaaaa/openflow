# NAS 部署（群晖 / 威联通 / fnOS）

> 适用：家里或小团队有一台 NAS，想在上面跑 OpenFlow。本质是 Docker，但套件的权限与路径最容易踩坑。

## 1. 前置

- NAS 已装容器套件：群晖 Container Manager / 威联通 Container Station / fnOS 应用中心。
- 准备一个共享目录，例如 `/docker/openflow`，下面建两个子目录：`data`、`uploads`。
- 内网能访问 NAS 的 IP 与端口；要外网访问再配 DDNS 或 Cloudflare Tunnel。
- 不需要装 PHP、MySQL、Redis——全部在容器里。

## 2. 部署（三种套件的共同步骤）

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git
cd openflow
docker compose up -d
```

套件界面里等价操作：新建项目/应用 → 选择 compose 文件 → 把 `./data` 与 `./uploads` 映射到共享目录 → 端口映射到 NAS 的某个空闲端口。

## 3. 属主（NAS 上最关键的一步）

容器里 Apache 以 `www-data` 运行，宿主机共享目录的属主却可能是你的管理员账号。属主不对的典型症状：**后台保存看起来成功，数据没变**。

- 群晖：给共享目录授权 `www-data`（或容器内 UID 33）读写；或用套件的"环境变量 PUID/PGID"能力对齐。
- 威联通：在共享文件夹权限里加 www-data 等价用户组。
- fnOS：用文件管理器改属主，或 `ssh` 后 `chown -R 33:33 <目录>`。

完成信号：容器内写一个文件到 `data/`，宿主机能在共享目录看到它。

## 4. 反向代理与 HTTPS

- 群晖：控制面板 → 登录门户 → 反向代理，来源 https，目标 `127.0.0.1:<端口>`。
- 威联通：控制台 → 应用程序 → 反向代理。
- 通配证书可用套件自带的 Let's Encrypt，或前面套 Cloudflare Tunnel。

## 5. 定时任务

NAS 的 cron 入口各不相同（任务计划 / 计划任务），加一条每分钟请求 `/api/cron.php` 的任务；或用套件的"计划任务"执行 `curl`。

完成信号：任务日志能看到每分钟一次的成功记录。

## 6. 常见坑

- **把路径写成容器内路径**：宿主机映射要写 `/volume1/docker/openflow/data` 这类真实路径。
- **端口冲突**：NAS 常占用 80/443，容器换端口再反代。
- **升级覆盖数据**：升级只重建容器，不要重建共享目录。
- **休眠**：NAS 硬盘休眠会导致定时任务延迟，按需调整。
