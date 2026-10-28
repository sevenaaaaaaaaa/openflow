# 交接说明（2026-10-20）

## 环境
- 本地仓库：`/Users/sevenaaaaaaa/OpenFlowDev`
- 本地预览：`php -S 127.0.0.1:8080 router-dev.php`（**新增 router-dev.php 复刻 .htaccess 重写；用 index.php 当 router 会让 /article/* 全落到首页**）
- 线上：`https://nownexts.com`
- 服务器：`root@172.96.253.73 -p 28766`，key `.ssh/172.96.253.73_id_ed25519`，站点根 `/www/wwwroot/nownexts_com`
- 部署：`bash bin/deploy.sh --live`（rsync；不同步 data/）
- 数据源：`data/articles/index.json`（4727 篇，已发布 502）。服务器上的这份是当前线上真源。

## 已完成且已上线（已验证）
3 篇 P0 重写，服务器 index.json 与线上页面均为新版（无「先说结论/测评人背景」母版词）：
| slug | 字数 | 图 | 表 |
|---|---|---|---|
| `ai-automation-content` | 2730 | 1（本地 n8n.webp） | 2 |
| `quickrecorder-macos-screencapture-kit-deep-review-screen-recording` | 2457 | 1（本地 screenrec/obs.webp） | 1 |
| `duix-avatar-deep-review-tools-development-open-source-digital-human-worth-it` | 2527 | 0（无合适本地图，已移除） | 1 |

线上验证：`/article/ai-automation-content` 页面含新内容、无母版词。

## 47 篇重写：已全部在线（已核对，无需重做）
47 篇（清单见 `data/list47-status.md`）**均已发布且为新版**，线上页面无母版词。本地 `index.json` 被写坏并未影响线上。唯一残留是 `whisper-content` 正文里一句「先说结论：」，已去掉并同步本地+服务器，线上复查 0。
合计：47 篇，异常 0 篇。

## 仍为旧版（不在 47 清单内，需单独重写）
- `shopify-ai-workflow-taobao`：3112 字，**仍含母版词**（旧版）
- `ai-5`（AI 短剧）：3380 字，**仍含母版词**（旧版）
→ 这两篇是本次会话新改的，未成功发布；需重写并**直接改服务器数据**（或本地改完 scp 上去）。

## 图片问题（已解决 ✅）
已做：
- 服务器脚本 `bin/fix-ext-images.php`：把 94 篇文章的外链图下载到 `/www/wwwroot/nownexts_com/assets/articles/_ext/`（成功 415，失败 47 已删 figure），并改写 src。
- 3 篇重写改用站内图：`/assets/articles/n8n/n8n.webp`、`/assets/articles/screenrec/obs.webp`。

**根因**：服务器 **防盗链**（hotlink protection）不允许空 Referer。
- Apache `nownexts.com.conf` 两处：`RewriteCond %{HTTP_REFERER} !nownexts.com [NC]` + `RewriteRule .(jpg|jpeg|gif|png|webp) /404.html [R=404]`，缺少「允许空 Referer」。
- nginx `nownexts.com.conf`：`valid_referers nownexts.com;` 也只认站内 Referer。

**修复**（已生效，配置已备份为 `*.bak-hotlink-*`）：
- Apache 两处各加 `RewriteCond %{HTTP_REFERER} !^$`，`httpd -t` OK，已 reload。
- nginx 改为 `valid_referers none blocked server_names nownexts.com www.nownexts.com *.nownexts.com;`。
- 验证：`/assets/articles/n8n/n8n.webp`、`/assets/articles/_ext/*.jpg`、`/assets/moodio/*.webp`、`/uploads/articles/*.jpeg` 公网全部 **200**（无 Referer 也 200）。

> 说明：外链图已全部改为站内 `/assets/articles/_ext/` 本地文件；本地 `index.json` 已从服务器同步回，与服务端一致。

## 已知事故与修复
**根因**：`mb_strpos`（字符偏移）+ `substr`（字节偏移）混用，截断中文多字节字符 → 非法 UTF-8 → `json_encode` 返回 false → `file_put_contents` 写入空串 → `index.json` 变 0 字节。
**已修**：改用 `strpos`；写入前 `json_encode` 校验 + `json_decode` 回读校验 + 写 `.tmp` 再 `rename`；改动前 `cp` 备份。

## 待办（2026-10-26 更新）

1. ~~重做丢失的 47 篇 + `shopify-ai-workflow-taobao`~~ → 47 篇已核对在线（见上文）；两篇旧版已于 2026-10-26 重写上线（见 `HANDOFF-for-cursor.md` §3）。
2. ~~解决线上 `/assets/articles/` 404~~ → 已修复（防盗链配置，见上文「图片问题」，与 `HANDOFF-for-cursor.md` §6 一致）。
3. 继续 P0 剩余约 20 篇（postiz、visioncull、n8n、ai-tools-roi 等）→ 最新清单与全量欠账见 `docs/handbook/PRODUCT-DEBT.md`。
4. 每改完一批：更新 `data/articles/index.json` → 推送服务器 → 线上抽查。标准命令：`python3 scripts/deploy.py --with-data data/articles/index.json data/articles/index.json`（部署后确认属主为 `www:www`）。

## 写作标准（已写入 skill）
`docs/handbook/gana-tool-writing-skill.md`：
- 禁流水账：工作流/教程文不许写「痛点/做法/关键细节」步骤清单，要按真实案例串（问题→归因→工具→翻车）。
- 长文分次写：写不透就分 2–3 次，不为「一次成型」降质。
