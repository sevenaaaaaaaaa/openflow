# ═══════════════════════════════════════════════════════════════
# OpenFlow · Docker 镜像（Apache + PHP 8.3 + SQLite/JSON，零外部服务）
#
# 构建：docker build -t openflow .
# 运行：docker compose up -d        （见 docker-compose.yml，数据卷持久化）
# 说明：仓库根目录即 Web 根目录，路由完全由自带 .htaccess 承担，
#       与生产服务器（Apache）行为一致；无需 composer（lib/ 有 spl_autoload 兜底）。
# ═══════════════════════════════════════════════════════════════
FROM php:8.3-apache

# PHP 扩展：pdo_sqlite / sqlite3 / mbstring / curl / openssl / fileinfo
# 官方镜像已自带，只有 gd 需要编译（品牌图/分享卡/封面生成依赖）
RUN apt-get update && apt-get install -y --no-install-recommends \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd opcache \
    && rm -rf /var/lib/apt/lists/*

# Apache：.htaccess 需要 rewrite/headers/expires + AllowOverride All（镜像默认 None）
RUN a2enmod rewrite headers expires \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
        /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# PHP 生产配置（时区由应用自行设为 Asia/Shanghai）
RUN { \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'upload_max_filesize=32M'; \
      echo 'post_max_size=32M'; \
    } > /usr/local/etc/php/conf.d/zz-openflow.ini

COPY deploy/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# .dockerignore 决定进镜像的内容（排除 .git/tests/开发配置/文档等）
COPY . /var/www/html/

# Web 根整体属主 www-data（与生产一致；form-handler 的根目录 leads.csv 兜底写入
# 也依赖可写）。数据卷挂载后由 entrypoint 重新交还属主。
RUN mkdir -p /var/www/html/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/") === false ? 1 : 0);'

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
