#!/bin/sh
# OpenFlow 容器入口：把数据卷属主交还 www-data，再启动 Apache。
# 【为什么需要它】宿主机 bind mount（./data、./uploads）会以宿主 UID 进容器，
# Apache 子进程以 www-data 运行，属主不对时后台保存会「看起来成功、数据没变」
# ——与生产服务器 ssh root 写过 data/ 后必须 chown www 是同一类坑。
set -e
mkdir -p /var/www/html/data /var/www/html/uploads
chown -R www-data:www-data /var/www/html/data /var/www/html/uploads 2>/dev/null || true
exec "$@"
