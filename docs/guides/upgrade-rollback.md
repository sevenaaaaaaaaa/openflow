# 升级与回滚

> 原则：**代码可以换，数据不能丢**。升级只动代码；`data/` 与 `uploads/` 永远保留、单独备份。

## 0. 升级前（5 分钟，必做）

```bash
tar czf backup-$(date +%F-%H%M).tgz data uploads   # 业务数据全在这两个目录
```

再记下三件事：当前版本号、部署形态（Docker/原生/面板）、上次能正常打开的页面 URL。
完成信号：备份文件能解开；版本号写在你的记事里。

## 1. 按形态升级

Docker：

```bash
git fetch --tags && git checkout <新版本 tag>
docker compose up -d --build
```

原生 / 面板：

```bash
git fetch --tags && git checkout <新版本 tag>
# 面板用户：按站点目录覆盖代码；原生用户：确保 Web 根指向仓库根
```

Release 包：解压新包后**只覆盖代码**，跳过 `data/` 与 `uploads/`。

## 2. 升级后验证（四条）

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://你的域名/       # 200
curl -s https://你的域名/ | grep -o '<title>[^<]*' | head -1    # 标题正确
```

- 后台能登录，且能打开一个内容列表页。
- 定时任务仍会跑（看一轮日志）。

完成信号：四条都过；有一条不过就进第 3 节。

## 3. 回滚

Docker：

```bash
git checkout <上一个版本 tag>
docker compose up -d --build
```

原生：切回旧 tag（或恢复旧代码目录），然后清一次页面缓存。

数据：一般不需要动；只有确认是升级写坏了数据，才解压备份覆盖 `data/`，并重新核对属主。

完成信号：页面恢复、后台能登录、数据与升级前一致。

## 4. 常见坑

- **只备份了代码没备份数据**：升级失败时无法回到"数据也对"的状态。
- **覆盖了整个目录**：把 `data/` 一起覆盖，等于丢数据。升级只覆盖代码。
- **忘记清缓存 / 版本号**：升级成功了但浏览器仍是旧页面，误判为失败。
- **属主被改**：用 root 解压或覆盖后，`data/` 属主要交还 Web 用户，否则保存静默失败。
