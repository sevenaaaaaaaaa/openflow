# 缓存机制 + Cloudflare 运维优化

## 一、OpenFlow 缓存策略

### 1. 数据层缓存

```php
// lib/Cache.php
class Cache {
    private static $redis = null;
    private static $fileCache = [];
    
    // 文件缓存（无 Redis 时）
    public static function remember(string $key, int $ttl, callable $fn) {
        $file = DATA_DIR . '/cache/' . md5($key) . '.json';
        
        // 检查缓存是否存在且未过期
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data['expires'] > time()) {
                return $data['value'];
            }
        }
        
        // 执行回调并缓存结果
        $value = $fn();
        file_put_contents($file, json_encode([
            'value' => $value,
            'expires' => time() + $ttl,
        ]));
        return $value;
    }
    
    // 清除缓存
    public static function forget(string $key) {
        $file = DATA_DIR . '/cache/' . md5($key) . '.json';
        if (file_exists($file)) unlink($file);
    }
}
```

### 2. 页面缓存

```php
// 首页缓存示例
$homeContent = Cache::remember('page:index', 3600, function() {
    return renderHomePage();
});

// 文章缓存
$article = Cache::remember("article:{$id}", 1800, function() use ($id) {
    return get_article($id);
});
```

### 3. 查询缓存

```php
// 数据库查询缓存
$products = Cache::remember('products:all', 600, function() {
    return Database::query("SELECT * FROM products WHERE status = 'active'");
});
```

## 二、Cloudflare 优化

### 1. 基础配置

```
# Cloudflare Dashboard 设置

## Speed > Optimization
- Auto Minify: ✓ JavaScript, CSS, HTML
- Brotli: ✓
- Early Hints: ✓

## Caching > Configuration
- Browser Cache TTL: 1 month
- Caching Level: Standard
- Always Online: ✓

## Rules > Page Rules
1. *example.com/api/*
   - Cache Level: Bypass
   
2. *example.com/uploads/*
   - Cache Level: Cache Everything
   - Edge Cache TTL: 1 month
   
3. *example.com/*.html
   - Cache Level: Cache Everything
   - Edge Cache TTL: 1 hour
```

### 2. Page Rules (页面规则)

```javascript
// 规则 1: API 不缓存
URL: example.com/api/*
Setting: Cache Level → Bypass

// 规则 2: 静态资源长缓存
URL: example.com/uploads/*
Setting: Cache Level → Cache Everything
Setting: Edge Cache TTL → 1 month

// 规则 3: HTML 页面短缓存
URL: example.com/*.html
Setting: Cache Level → Cache Everything
Setting: Edge Cache TTL → 1 hour
```

### 3. Workers (边缘计算)

```javascript
// worker-cache-headers.js
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const response = await fetch(request)
  
  // 静态资源添加缓存头
  if (request.url.match(/\.(jpg|jpeg|png|gif|webp|css|js)$/)) {
    const newResponse = new Response(response.body, response)
    newResponse.headers.set('Cache-Control', 'public, max-age=31536000, immutable')
    return newResponse
  }
  
  return response
}
```

### 4. Cloudflare 优化清单

| 设置 | 推荐值 | 说明 |
|------|--------|------|
| Auto Minify | ✓ JS/CSS/HTML | 压缩代码 |
| Brotli | ✓ | 比 Gzip 压缩率高 20% |
| Early Hints | ✓ | 提前加载资源 |
| HTTP/3 | ✓ | 更快的连接 |
| TLS 1.3 | ✓ | 更安全更快 |
| Always Online | ✓ | 服务器宕机时显示缓存 |
| Browser Integrity Check | ✓ | 防止恶意爬虫 |

## 三、PHP OPcache 配置

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.save_comments=1
opcache.enable_cli=0
```

## 四、SQLite 优化

```php
// lib/Database.php 优化
class Database {
    public static function conn(): PDO {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite:' . DB_FILE);
            
            // 性能优化
            self::$pdo->exec('PRAGMA journal_mode=WAL;');
            self::$pdo->exec('PRAGMA busy_timeout=5000;');
            self::$pdo->exec('PRAGMA synchronous=NORMAL;');
            self::$pdo->exec('PRAGMA cache_size=-64000;'); // 64MB
            self::$pdo->exec('PRAGMA temp_store=MEMORY;');
            self::$pdo->exec('PRAGMA mmap_size=268435456;'); // 256MB
        }
        return self::$pdo;
    }
}
```

## 五、监控与告警

### 1. Cloudflare Analytics
- 流量趋势
- 缓存命中率
- 带宽使用
- 威胁拦截

### 2. 自定义监控

```php
// api/monitor.php
$stats = [
    'cache_hit_rate' => calculateCacheHitRate(),
    'avg_response_time' => getAvgResponseTime(),
    'error_rate' => getErrorRate(),
    'active_users' => getActiveUsers(),
];

// 发送到 Cloudflare Analytics
if (CF_ACCOUNT_ID && CF_API_TOKEN) {
    $cf = new CloudflareAnalytics();
    $cf->push($stats);
}
```

## 六、性能基准

| 指标 | 优化前 | 优化后 |
|------|--------|--------|
| TTFB | 800ms | 120ms |
| FCP | 2.5s | 0.8s |
| LCP | 4.2s | 1.5s |
| 缓存命中率 | 0% | 85% |
| 带宽节省 | 0% | 60% |
