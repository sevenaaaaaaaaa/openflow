# 一键部署指南

## 一、Cloudflare Pages (推荐)

### 优势
- 免费无限流量
- 全球 CDN
- 自动 HTTPS
- 预览部署

### 部署步骤

1. **推送到 GitHub**
```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/youruser/openflow-xmp.git
git push -u origin main
```

2. **连接 Cloudflare Pages**
   - 登录 Cloudflare Dashboard
   - Pages > Create a project
   - 连接 GitHub 仓库
   - 构建设置：
     ```
     Build command: (留空)
     Build output directory: /
     ```

3. **环境变量**
```
PHP_VERSION=8.0
```

4. **自定义域名**
   - Pages > Settings > Custom domains
   - 添加你的域名
   - 配置 DNS

### 注意事项
- Cloudflare Pages 不支持 PHP，需要搭配 Workers
- 或使用 Cloudflare Pages + PHP Worker 组合

## 二、Vercel (推荐)

### 优势
- 免费额度充足
- 自动部署
- 预览环境
- 服务器函数

### 部署步骤

1. **安装 Vercel CLI**
```bash
npm i -g vercel
```

2. **配置 vercel.json**
```json
{
  "version": 2,
  "builds": [
    {
      "src": "**/*.php",
      "use": "@vercel/php"
    }
  ],
  "routes": [
    {
      "src": "/(.*)",
      "dest": "/$1"
    }
  ]
}
```

3. **部署**
```bash
vercel
```

4. **生产部署**
```bash
vercel --prod
```

### 环境变量
在 Vercel Dashboard 中设置：
```
DATA_DIR=/tmp/data
UPLOAD_DIR=/tmp/uploads
```

## 三、GitHub Pages (静态版本)

### 适用场景
- 纯静态站点
- 文档站点
- 博客

### 部署步骤

1. **GitHub Actions 配置**
```yaml
# .github/workflows/deploy.yml
name: Deploy to GitHub Pages

on:
  push:
    branches: [ main ]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
      - name: Build static site
        run: |
          php build-static.php
      - name: Deploy to GitHub Pages
        uses: peaceiris/actions-gh-pages@v3
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_dir: ./public
```

2. **启用 GitHub Pages**
   - Settings > Pages
   - Source: GitHub Actions

## 四、Railway (全栈)

### 优势
- 完整 PHP 支持
- 数据库支持
- 自动扩展

### 部署步骤

1. **创建 Dockerfile**
```dockerfile
FROM php:8.1-apache

# 安装扩展
RUN docker-php-ext-install pdo pdo_sqlite

# 启用 Apache 模块
RUN a2enmod rewrite

# 复制代码
COPY . /var/www/html/

# 配置权限
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html/data
RUN chmod -R 755 /var/www/html/uploads

EXPOSE 80
```

2. **创建 railway.json**
```json
{
  "build": {
    "builder": "DOCKERFILE"
  },
  "deploy": {
    "startCommand": "apache2-foreground",
    "healthcheckPath": "/"
  }
}
```

3. **部署**
```bash
railway login
railway init
railway up
```

## 五、Docker 通用部署

### docker-compose.yml
```yaml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "8080:80"
    volumes:
      - ./data:/var/www/html/data
      - ./uploads:/var/www/html/uploads
    environment:
      - PHP_VERSION=8.1
      - SITE_NAME=OpenFlow
    restart: unless-stopped

  db:
    image: sqlite:latest
    volumes:
      - ./data/db:/data
    restart: unless-stopped
```

### 环境变量
```env
# .env
SITE_NAME=OpenFlow
SITE_URL=https://your-domain.com
DATA_DIR=/var/www/html/data
UPLOAD_DIR=/var/www/html/uploads
```

## 六、部署后配置

### 1. 域名配置
```php
// admin/config.php
define('SITE_URL', 'https://your-domain.com');
```

### 2. SSL 证书
- Cloudflare: 自动
- Vercel: 自动
- Let's Encrypt: 免费

### 3. 性能优化
```bash
# 启用 OPcache
php -d opcache.enable=1 -d opcache.memory_consumption=128

# 优化 SQLite
sqlite3 data/db/openflow.db "PRAGMA optimize;"
```

### 4. 备份配置
```bash
# 定时备份
0 2 * * * /usr/bin/php /path/to/backup.php
```

## 七、常见问题

### Q: PHP 不支持怎么办？
A: 使用 Vercel/Railway，或配置 Cloudflare Workers

### Q: 文件上传失败？
A: 检查 `uploads` 目录权限：
```bash
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

### Q: 数据库锁定？
A: 检查 SQLite WAL 模式：
```bash
sqlite3 data/db/openflow.db "PRAGMA journal_mode=WAL;"
```
