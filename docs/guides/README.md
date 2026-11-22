# 部署指南（对外）

> 面向从 GitHub 下载并自行部署的用户。内部运维口径见 `docs/AGENT-DEPLOY.md`，两边不要混用。

## 先选形态

| 形态 | 适用 | 前置 | 备注 |
|---|---|---|---|
| Docker Compose | 有 Docker 的 Linux / NAS | Docker + Compose | 推荐默认；数据落两个宿主机目录 |
| Apache（含面板） | 传统 VPS、与已有站点共存 | Apache + PHP 8.1+、AllowOverride | 仓库根即站点根 |
| Nginx + php-fpm | 已有 Nginx 体系 | 伪静态规则、`deploy/nginx.site.conf` | 规则需与 .htaccess 对齐 |
| NAS（群晖/威联通/fnOS） | 家用或小团队 NAS | 容器管理套件、共享目录 | UID/属主是最常见坑 |
| 宝塔 / 1Panel | 想少敲命令 | 面板 + 可配 PHP 版本 | 伪静态要手动补 |
| 本地 php -S | 只看效果 | 本机 PHP | 不是生产 |
| Vercel | 演示 | — | 数据不持久，勿当生产 |
| 共享主机 | — | — | 暂不承诺 |

## 接入层（叠加，不单独成立）

- Cloudflare：DNS / 缓存 / Tunnel；不配 TLS 就别对外开后台。
- 反向代理 + 自动证书：Nginx / Caddy / 面板均可。
- 对象存储：静态资产可走 R2/S3。
- 邮件：发信域名的 SPF/DKIM 在邮件服务商侧配置，与网站 A 记录无关。
- 定时任务：不配 cron，定时发布与自动化都不会转。

## 交付形态

| 形态 | 适合 | 注意 |
|---|---|---|
| git clone | 会改代码 | 分支/tag 要与版本对应 |
| Release 包 | 不装 git | 升级时不要覆盖 data/ 与 uploads/ |
| 容器镜像 | NAS / 想省事 | 卷与端口映射、健康检查 |
| 一键脚本 | 零基础 | 目前先支持 Ubuntu/Debian，其余待验证 |

## 三步到能打开

```bash
git clone https://github.com/sevenaaaaaaaaa/openflow.git && cd openflow
docker compose up -d          # 或按所选形态部署
# 打开 http://127.0.0.1:8080/setup-wizard.php 完成安装
```

然后按 [上线验收清单](go-live-checklist.md) 逐项确认，五项全绿才算上线。

## 目录

- [快速开始（主线）](quickstart.md)
- [上线验收清单](go-live-checklist.md)
- [安装完成后的第一小时](first-run.md)
- [故障排查 Top 10](troubleshooting.md)
- [升级与回滚](upgrade-rollback.md)
- [NAS 部署](nas.md)
- [面板部署（宝塔 / 1Panel）](baota-1panel.md)
