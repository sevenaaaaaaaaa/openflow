<?php
/**
 * 演示空间（Demo Space）—— 只读演示的开通与生命周期
 *
 * 设计：元数据存一个 JSON 文件；每个空间一个独立目录，便于整目录归档/删除。
 * 只读门禁不在这里实现（需要请求层拦截），本类只负责"开通/续期/冻结/回收"。
 */
declare(strict_types=1);

class DemoSpace
{
    public function __construct(
        private string $storeFile,
        private string $spacesRoot,
        private int $defaultDays = 90
    ) {
    }

    public static function defaults(string $root): self
    {
        $data = getenv('OF_DATA_DIR') ?: $root . '/data';
        return new self(
            getenv('OF_DEMO_STORE') ?: $data . '/demo-spaces.json',
            getenv('OF_DEMO_ROOT') ?: $data . '/demo-spaces',
            (int) (getenv('OF_DEMO_DAYS') ?: 90)
        );
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if (!is_file($this->storeFile)) {
            return [];
        }
        $raw = @file_get_contents($this->storeFile);
        $data = is_string($raw) ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }

    /** 同一邮箱域名只允许一个空间。 */
    public function findByEmail(string $email): ?array
    {
        $domain = strtolower(substr(strrchr('@' . ltrim($email, '@'), '@') ?: '', 1));
        foreach ($this->all() as $space) {
            if (strtolower((string) ($space['email_domain'] ?? '')) === $domain && $domain !== '') {
                return $space;
            }
        }
        return null;
    }

    /** @return array{space: array, code: string} */
    public function create(string $slug, string $email): array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,30}$/', $slug)) {
            throw new InvalidArgumentException('slug 需为 3-31 位小写字母、数字或连字符');
        }
        if ($this->findByEmail($email) !== null) {
            throw new RuntimeException('该邮箱域名已有演示空间');
        }
        $all = $this->all();
        if (isset($all[$slug])) {
            throw new RuntimeException('slug 已存在');
        }
        $code = strtoupper(bin2hex(random_bytes(5)));
        $now = time();
        $all[$slug] = [
            'slug' => $slug,
            'email' => $email,
            'email_domain' => strtolower((string) substr(strrchr($email, '@') ?: '', 1)),
            'created_at' => $now,
            'expires_at' => $now + $this->defaultDays * 86400,
            'status' => 'active',
            'code_hash' => hash('sha256', $code),
        ];
        $this->save($all);
        $dir = $this->spacesRoot . '/' . $slug;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return ['space' => $all[$slug], 'code' => $code];
    }

    public function renew(string $slug, int $days): array
    {
        $all = $this->all();
        if (!isset($all[$slug])) {
            throw new RuntimeException('空间不存在');
        }
        $all[$slug]['expires_at'] = max((int) $all[$slug]['expires_at'], time()) + $days * 86400;
        $all[$slug]['status'] = 'active';
        $this->save($all);
        return $all[$slug];
    }

    public function freeze(string $slug): array
    {
        $all = $this->all();
        if (!isset($all[$slug])) {
            throw new RuntimeException('空间不存在');
        }
        $all[$slug]['status'] = 'frozen';
        $this->save($all);
        return $all[$slug];
    }

    /** 归档到 <spacesRoot>/../demo-archive/<slug>-<ts>.tar.gz 后删除目录与记录。 */
    public function delete(string $slug): bool
    {
        $all = $this->all();
        if (!isset($all[$slug])) {
            return false;
        }
        $dir = $this->spacesRoot . '/' . $slug;
        if (is_dir($dir)) {
            $archiveDir = dirname($this->spacesRoot) . '/demo-archive';
            if (!is_dir($archiveDir)) {
                mkdir($archiveDir, 0755, true);
            }
            $archive = $archiveDir . '/' . $slug . '-' . date('Ymd-His') . '.tar.gz';
            $cmd = 'tar -czf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($this->spacesRoot) . ' ' . escapeshellarg($slug);
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                throw new RuntimeException('归档失败，未删除空间');
            }
            $this->removeDir($dir);
        }
        unset($all[$slug]);
        $this->save($all);
        return true;
    }

    /** 按到期时间推导状态（active → frozen 需人工/任务触发，这里只报告）。 */
    public function effectiveStatus(array $space): string
    {
        if (($space['status'] ?? '') !== 'active') {
            return (string) ($space['status'] ?? 'unknown');
        }
        return time() >= (int) ($space['expires_at'] ?? 0) ? 'expired' : 'active';
    }

    private function save(array $all): void
    {
        $dir = dirname($this->storeFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->storeFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function removeDir(string $dir): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
