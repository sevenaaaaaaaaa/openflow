# 把整个项目托管到 Cloudflare R2：可行性研究（2026-10）

> 结论先行：**「整个项目」托管到 R2 不可行**——R2 是对象存储，不是文件系统，PHP 运行时
> 每一次请求都在做"随机读写 + 加锁 + 原子替换 + 包含/列出目录"这类 POSIX 操作，
> R2 语义上做不到。但"资源托管到 R2"**已经部分完成**，且还有两块能安全迁移。
> 本文用数据说明边界，并给出真正可行的"脱离单机"路线。

---

## 一、当前架构的事实（线上实测）

| 项 | 值 |
|---|---|
| 代码 | PHP 8.3.33 + Apache，`/www/wwwroot/nownexts_com`，3292 个 PHP 文件 |
| 数据 | SQLite `data/db/openflow.db` **37.9 MB**（另有备份 4.6 MB）+ JSON 数据文件 |
| data/ 总量 | **1.2 GB**，其中 backups 841M · articles 189M · db 43M · revisions 33M · 日志 13M |
| uploads/ | 267 个文件 · 9.7 MB（用户/站点上传的媒体） |
| assets/ | 625 个文件 · 200 MB —— **已经在 R2**（620+ 文件，CDN 分发） |
| 会话 | PHP 默认文件会话（服务器磁盘） |

**每请求的文件系统行为（代码里真实触点，全仓统计）**

| 触点 | 次数 | 说明 |
|---|---|---|
| json_read() | 1095 | 读 data/*.json——**几乎每个页面渲染都要读若干个** |
| json_write() | 717 | 写 data/*.json——提交表单、计数、状态、埋点都在写 |
| file_get/put_contents | 742 | 配置、日志、快照 |
| rename/mkdir/unlink | 539 | **原子写**（写临时文件再 rename）与目录管理 |
| fopen/flock | 42 | 计数与并发锁（flock 原子自增） |
| SQLite | 34 处引用 | `data/db/openflow.db`，随机读写 + 事务 |
| $_FILES | 41 | 上传（move_uploaded_file 只能落到本地盘） |

这些不是"可以避免的偶发调用"，而是**运行模型本身**：每个请求打开多个 JSON、
按天聚合的计数器用 flock 加锁、写操作靠"临时文件 + rename"保证不写坏。

---

## 二、R2 能做什么、不能做什么（语义差距）

R2 是对象存储（S3 语义）：

| 能力 | POSIX 文件系统 | R2 对象存储 |
|---|---|---|
| `include()`/`stat()` 源码 | ✓ | ✗（只能按 key 整读，PHP 不能从它执行代码） |
| 随机小读写（毫秒级） | ✓ | ✗（每读一次是一次 HTTP 请求，~50-150ms） |
| flock 文件锁 | ✓ | ✗（**没有锁**；并发计数/抢单会互相覆盖） |
| 原子写（tmp + rename） | ✓ | ✗（只能整对象 PUT，先读后写会丢并发写） |
| 目录列表/删除 | ✓ | ✗（"目录"是 key 前缀，list 有分页与最终一致性窗口） |
| SQLite 文件 | ✓ | ✗（数据库需要可 seek 的块设备/本地盘） |
| 读写放大成本 | 磁盘 IO | 每次读写都计费（A 类/B 类操作 $4.5/百万） |

**结论**：把 `data/`（1.2GB、几千个小 JSON、flock 计数、SQLite）放上 R2，
等于把一个依赖文件语义的应用放到没有这些语义的存储上——不是慢，是**会坏**
（计数丢失、并发写覆盖、会话错乱、DB 直接无法打开）。

---

## 三、可行的迁移目标（按真实收益排序）

### 方案 A（推荐）：应用留服务器，"重资源"上 R2 —— 已完成 60%
现状：`assets/` 200MB 已在 R2（200 个文件 + CDN）✓。
还能补两块：

1. **uploads/ → R2**（9.7MB，267 个文件，只读为主）
   媒体类上传改成"上传即推 R2，站点只存元数据"，图片/附件由 CDN 直出。
   改动点集中在 `move_uploaded_file` 的 4 处 + 媒体读取 URL 生成。
2. **data/backups → R2**（841MB！data 的大头）——**已经做好了**：
   `lib/OffsiteBackup.php`（自签 SigV4、流式）+ `scripts/offsite-backup.php`，
   配上密钥后自动把每份备份推 R2。推完后可把服务器上的旧备份清掉，
   data/ 从 1.2GB 降到 ~370MB。
   **这一步同时就是"异地容灾"**（备份不再与站点同盘）。

收益：站点盘占用 -72%、媒体走 CDN 更快、备份真异地。风险：低。

### 方案 B：Docker 化（已经落地）—— 解决"单机依赖"而非"换存储"
远端已进：`Dockerfile` / compose / `entrypoint` / `router.php`（本地路由器）。
这把"运行环境"从"一台宝塔机器"变成可复制镜像——真正的**可迁移性**来自这里：
任何有 POSIX 文件系统的主机（VPS/家用机/NAS）都能跑。
**注意**：跑在 Docker 里时，`data/` 必须**挂卷**（volume），不能放进镜像层。

### 方案 C：真·全托管 Cloudflare（Workers + D1 + R2）
把 PHP 重写成 TS/JS：数据进 **D1**（SQLite 兼容的 Serverless DB）、
静态进 R2、计算进 Workers。
- 不是"迁移"，是**重写**：3292 个 PHP 文件、23 个 MCP 工具、116 个 API、
  226 个后台页、全部 lib——工作量以"月"计，且期间两套系统并行。
- 收益：免服务器运维、全球边缘、按量付费。
- 现实判断：**现在不值**——单机方案稳定、成本可控、无 SLA 压力；
  这个重写应该等"真有托管服务的商业需求"再做。

### 方案 D（不推荐）：R2 当"网盘"挂载（s3fs/rclone mount）
技术上有，但每次 stat/open 都是网络往返、没有 flock/原子性、延迟高且容易超时——
稳定性显著变差，等于主动引入故障。只适合冷数据，不适合运行时。

---

## 四、稳定性影响评估（若做方案 A）

| 项 | 影响 | 缓解 |
|---|---|---|
| 页面渲染 | **无影响**（页面读的是 data/，不上 R2） | — |
| 图片/媒体 | 更快（CDN 边缘）+ 更稳（无源站磁盘故障风险） | 上传双写一段时间（本地 + R2），验证后切读 |
| 数据安全 | **提升**：备份异地化（已建好机制，只差配密钥） | 恢复演练一次 |
| 成本 | R2 免出口流量费；存储 ~$0.015/GB/月（1GB 级 ≈ 忽略不计） | 无明显成本风险 |
| 复杂度 | 新增一条上传链路（失败重试/密钥管理） | 用现成 OffsiteBackup 的 SigV4 代码 |

---

## 五、建议的执行顺序

1. ✅ **配好异地备份**（2026-10-08 已完成）：
   - 服务器 `data/offsite-backup.json` 已配置（www 属主、600 权限，指向 nownexts-static 桶 `openflow-backups/` 前缀）
   - 5 份备份全部推 R2：3 份自动备份（各 ~72.8MB zip）+ 2 份 demo-cleanup
   - **恢复演练通过**：SigV4 下载最新备份 72.8MB，`unzip -t` 无损
   - 已删服务器旧备份 4 份（删前逐份 HEAD 200 验证在档），本地保留最新一份
   - **data/ 1.2GB → 574MB**；此后每日自动备份会经 `backup_run_if_due()` → `offsite_sync()` 自动上 R2
   - 安全注记：备份与公开资产同桶（nownexts-static），但 CDN 映射 `/assets/<路径>` → 桶内 `assets/` 前缀 key，备份 key `openflow-backups/…` **结构上不可达**（公开 URL 实测 404）。CF token 无 WAF 写权限（10405），未能加显式封禁规则——**建议后续把备份迁到独立桶**（S3 CreateBucket 即可）以获得硬隔离
2. ✅ **uploads/ 上 R2**（2026-10-09 已完成，双写阶段）：
   - `lib/MediaMirror.php`：零依赖 SigV4 PUT（对齐 r2-put.php），配置 `data/media-mirror.json`（600/www，密钥不入库）
   - 挂接 3 个上传点：media-upload（主文件+尺寸变体）/ dam / media；data-sync 的 CSV 是临时导入，不镜像
   - **新上传的响应 URL 直出 CDN**：主文件镜像成功才用 `/assets/uploads/<相对路径>`；失败回退 `/uploads/`（行为与历史一致）
   - 存量回填：265 文件全部 PUT 成功；抽验两轮（10+15）**md5 全部一致**；对象键 = `assets/uploads/<相对 uploads 路径>`（与站点资源同一映射，探针实测）
   - 未做：R2-only 切换（删本地）——需等双写观察期
   - **老图复活（同日完成）**：源站 vhost 有图片灭杀规则（`RewriteRule .(jpg|…) /404.html`，图片从不经源站出），
     老内容烙的 `/uploads/<X>` 原本是死链。新增 `uploads-r2` Worker（路由 `/uploads/*`，键映射
     `assets/uploads/<X>`，复用 r2-media 的 Range/206 支持），部署后老 URL 全部从 R2 直出——
     实测 200 + 真实字节 + image/jpeg（本地与服务器侧双复核）。注意：2025/11、2021/07 等日期目录
     的图在服务器上本就不存在（WP 迁移没带图），属于真死链，无法复活
3. ✅ **清理 data/backups 旧档**（已并入步骤 1，-640MB）
4. ⬜ Docker 镜像跑通一次演练（不动生产），作为"脱离单机"的备案
5. ⬜ 方案 C（Workers+D1 重写）列为远期，等有真实商业驱动

---

## 六、本研究的证据清单

- 文件系统触点统计：全仓 3292 个 PHP 文件的 json_read/write、flock、rename、SQLite 引用计数（§一）
- R2 语义对照：对象存储无锁/无原子 rename/无 POSIX（§二）
- 线上实测：data/ 1.2GB 构成、DB 37.9MB、uploads 9.7MB、assets 200MB 已在 R2（§一）
- 既有代码基础：OffsiteBackup（SigV4+流式+34 项测试）、MediaUpload（上传链路）、sync-r2.py
- 方案 B 的落地证据：仓库内 Dockerfile / router.php / vercel.json（远端 36bd11d 提交）
