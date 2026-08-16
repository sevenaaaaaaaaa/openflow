# PHPUnit + Composer 引入说明

## 什么是 Composer？

Composer 是 PHP 的依赖管理工具（类似 npm for JS）。

```
composer require phpunit/phpunit    # 安装 PHPUnit
composer require monolog/monolog    # 安装日志库
composer require guzzlehttp/guzzle # 安装 HTTP 客户端
```

**解决的问题：**
- 不再手动 copy 类库文件
- 版本锁定，团队环境一致
- 自动加载，不再 `require_once` 一堆文件
- 一键更新所有依赖

## 什么是 PHPUnit？

PHPUnit 是 PHP 最流行的单元测试框架。

**当前状态：** 我们已有 27 个自定义测试，运行器在 `tests/run.php`

**引入 PHPUnit 后：**

| 能力 | 自定义 runner | PHPUnit |
|------|--------------|---------|
| 基本断言 | ✓ | ✓ (200+ 种) |
| 数据提供器 (data providers) | ✗ | ✓ |
| 测试覆盖率报告 | ✗ | ✓ (行/分支/函数) |
| Mock/Stub 对象 | ✗ | ✓ |
| 代码覆盖率上传 (Codecov) | ✗ | ✓ |
| IDE 集成 (PhpStorm/VSCode) | 有限 | 完美 |
| CI/CD 集成 | 需自定义 | 原生支持 |
| 参数化测试 | ✗ | ✓ (@dataProvider) |
| 分组测试 | ✗ | ✓ (@group) |
| 测试排序 | ✗ | ✓ |

## 引入后的好处

### 1. CI/CD 集成
```yaml
# GitHub Actions
- run: vendor/bin/phpunit --coverage-text
- run: vendor/bin/phpunit --coverage-clover=coverage.xml
```

### 2. 覆盖率报告
```bash
vendor/bin/phpunit --coverage-html=coverage/
# 生成 HTML 报告，精确到每一行代码
```

### 3. Mock 外部依赖
```php
// 不需要真的发邮件/调 API
$mailer = $this->createMock(Mailer::class);
$mailer->expects($this->once())->method('send');
```

### 4. 数据提供器（参数化测试）
```php
/** @dataProvider passwordProvider */
public function testPasswordValidation($input, $expected) {
    $this->assertEquals($expected, validatePassword($input));
}
public function passwordProvider() {
    return [
        ['abc', false],      // 太短
        ['abcdefgh', true],  // 合格
        ['', false],         // 空
    ];
}
```

### 5. 自动加载
```php
// 不再需要
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ProgressSystem.php';

// Composer 自动处理
use App\MemberSystem;
use App\ProgressSystem;
```

## 迁移计划

### Phase 1: 引入 Composer（无破坏性）
```bash
composer init              # 生成 composer.json
composer require phpunit/phpunit --dev
```

### Phase 2: 重构现有测试
- 将 `tests/test_*.php` 迁移为 PHPUnit TestCase 类
- 保留现有测试逻辑

### Phase 3: 添加新能力
- 覆盖率报告
- Mock 外部服务
- 集成测试（HTTP 请求）
- 数据库事务回滚

## 推荐的 composer.json

```json
{
  "name": "openflow/xmp",
  "description": "AI时代的网站增长操作系统",
  "type": "project",
  "require": {
    "php": ">=8.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "phpunit/php-code-coverage": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "lib/"
    }
  },
  "scripts": {
    "test": "vendor/bin/phpunit",
    "test:coverage": "vendor/bin/phpunit --coverage-html=coverage/"
  }
}
```

## 推荐的 phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         verbose="true">
    <testsuites>
        <testsuite name="OpenFlow Tests">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <report>
            <html outputDirectory="coverage/"/>
        </report>
    </coverage>
</phpunit>
```

## 结论

**引入 PHPUnit + Composer 的核心价值：**

1. **专业测试工具** — 不再自己造轮子
2. **覆盖率可视化** — 知道哪些代码没测到
3. **CI/CD 就绪** — push 后自动跑测试
4. **依赖管理** — 版本锁定，一键安装
5. **IDE 支持** — 点击跳转测试，一键运行
6. **团队协作** — 标准化测试流程

**适合引入的时机：**
- 当测试数量超过 50 个
- 当需要 CI/CD 集成
- 当需要覆盖率报告
- 当需要 Mock 外部服务（支付、邮件、短信）
