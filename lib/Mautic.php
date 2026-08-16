<?php
/**
 * Mautic PHP SDK — 联系人管理 & 邮件发送 & 营销活动
 * 依赖 Composer: composer require mautic/api-library
 *
 * 用法:
 *   $m = Mautic::fromConfig();
 *   $contact = $m->createContact('user@ex.com', ['firstname' => '张']);
 *   $m->sendEmail(1, $contact['contact']['id']);
 */
class Mautic {
    private ?object $api = null;
    private ?object $auth = null;
    private string $baseUrl;
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
    }

    /**
     * 从配置文件加载实例
     */
    public static function fromConfig(): ?self {
        $cfg = json_read(__DIR__ . '/../data/mautic.json');
        if (empty($cfg['base_url']) || empty($cfg['enabled'])) return null;
        return new self($cfg);
    }

    /**
     * 初始化 Mautic API 连接（需已安装 mautic/api-library）
     */
    private function init(): bool {
        if ($this->api !== null) return true;

        if (!class_exists('\\Mautic\\MauticApi')) {
            // Try to load via Composer autoload
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            if (!class_exists('\\Mautic\\MauticApi')) return false;
        }

        try {
            $settings = [
                'baseUrl' => $this->baseUrl,
                'version' => $this->config['version'] ?? 'BasicAuth',
            ];

            if ($settings['version'] === 'BasicAuth') {
                $settings['userName'] = $this->config['username'] ?? '';
                $settings['password'] = $this->config['password'] ?? '';
            } else {
                $settings['clientKey'] = $this->config['client_key'] ?? '';
                $settings['clientSecret'] = $this->config['client_secret'] ?? '';
                $settings['callback'] = $this->baseUrl . '/api';
            }

            $initAuth = new \Mautic\Auth\ApiAuth();
            $this->auth = $initAuth->newAuth($settings);
            $this->api = new \Mautic\MauticApi();

            return true;
        } catch (\Exception $e) {
            error_log('[Mautic] Init failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建或更新联系人
     *
     * @param string $email 邮箱（唯一标识）
     * @param array $data 字段数据（firstname, lastname, company, phone 等）
     * @return array|null
     */
    public function createContact(string $email, array $data = []): ?array {
        if (!$this->init()) return null;
        try {
            $api = $this->api->newApi('contacts', $this->auth, $this->baseUrl . '/api');
            $data['email'] = $email;
            return $api->create($data);
        } catch (\Exception $e) {
            error_log('[Mautic] createContact failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 发送邮件给指定联系人
     *
     * @param int $emailId Mautic 中的邮件 ID
     * @param int $contactId 联系人 ID
     * @param array $tokens 自定义 token 替换
     * @return array|null
     */
    public function sendEmail(int $emailId, int $contactId, array $tokens = []): ?array {
        if (!$this->init()) return null;
        try {
            $api = $this->api->newApi('emails', $this->auth, $this->baseUrl . '/api');
            return $api->sendToContact($emailId, $contactId, ['tokens' => $tokens]);
        } catch (\Exception $e) {
            error_log('[Mautic] sendEmail failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 将联系人加入营销活动
     *
     * @param int $campaignId 活动 ID
     * @param int $contactId 联系人 ID
     * @return array|null
     */
    public function addToCampaign(int $campaignId, int $contactId): ?array {
        if (!$this->init()) return null;
        try {
            $api = $this->api->newApi('campaigns', $this->auth, $this->baseUrl . '/api');
            return $api->addContact($campaignId, $contactId);
        } catch (\Exception $e) {
            error_log('[Mautic] addToCampaign failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 搜索联系人
     *
     * @param string $search 关键词（邮箱、姓名等）
     * @return array|null
     */
    public function searchContacts(string $search): ?array {
        if (!$this->init()) return null;
        try {
            $api = $this->api->newApi('contacts', $this->auth, $this->baseUrl . '/api');
            return $api->getList($search, 0, 20);
        } catch (\Exception $e) {
            error_log('[Mautic] searchContacts failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 给联系人打标签
     *
     * @param int $contactId 联系人 ID
     * @param string|array $tags 标签（字符串或数组，逗号分隔）
     * @return array|null
     */
    public function addTags(int $contactId, $tags): ?array {
        if (!$this->init()) return null;
        try {
            if (is_array($tags)) $tags = implode(',', $tags);
            $api = $this->api->newApi('contacts', $this->auth, $this->baseUrl . '/api');
            return $api->edit($contactId, ['tags' => $tags], true);
        } catch (\Exception $e) {
            error_log('[Mautic] addTags failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取联系人列表
     *
     * @param int $limit
     * @return array|null
     */
    public function getContacts(int $limit = 20): ?array {
        if (!$this->init()) return null;
        try {
            $api = $this->api->newApi('contacts', $this->auth, $this->baseUrl . '/api');
            return $api->getList('', 0, $limit);
        } catch (\Exception $e) {
            error_log('[Mautic] getContacts failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 测试连接
     */
    public function ping(): bool {
        $ch = curl_init($this->baseUrl . '/api/ping');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $code === 200;
    }
}
