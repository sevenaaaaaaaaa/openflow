<?php
/**
 * BillionMail PHP SDK — 邮件发送 & 联系人管理
 * 部署后实例化即可使用
 *
 * 用法:
 *   $bm = new BillionMail('https://mail.yourdomain.com', 'your-api-key');
 *   $bm->send('user@example.com', 'Hello', '<h1>内容</h1>');
 */
class BillionMail {
    private string $apiUrl;
    private string $apiKey;
    private string $defaultSender;
    private string $defaultSenderName;

    public function __construct(
        string $apiUrl,
        string $apiKey,
        string $defaultSender = '',
        string $defaultSenderName = 'OpenFlow'
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->defaultSender = $defaultSender;
        $this->defaultSenderName = $defaultSenderName;
    }

    /**
     * 从配置文件加载实例
     */
    public static function fromConfig(): ?self {
        $cfg = json_read(__DIR__ . '/../data/billionmail.json');
        if (empty($cfg['api_url']) || empty($cfg['api_key']) || empty($cfg['enabled'])) return null;
        return new self(
            $cfg['api_url'],
            $cfg['api_key'],
            $cfg['default_sender'] ?? '',
            $cfg['default_sender_name'] ?? 'OpenFlow'
        );
    }

    /**
     * 发送单封邮件
     *
     * @param string $recipient 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $htmlContent HTML 内容
     * @param array $attribs 额外变量
     * @return array {code, data, error}
     */
    public function send(string $recipient, string $subject, string $htmlContent, array $attribs = []): array {
        return $this->request('/api/batch_mail/api/send', [
            'recipient' => $recipient,
            'addresser' => $this->defaultSender,
            'attribs' => array_merge([
                'subject' => $subject,
                'content' => $htmlContent,
                'sender_name' => $this->defaultSenderName,
            ], $attribs),
        ]);
    }

    /**
     * 批量发送相同内容
     *
     * @param array $recipients 收件人邮箱数组
     * @param string $subject 邮件主题
     * @param string $htmlContent HTML 内容
     * @return array
     */
    public function batchSend(array $recipients, string $subject, string $htmlContent): array {
        return $this->request('/api/batch_mail/api/batch_send', [
            'recipients' => $recipients,
            'addresser' => $this->defaultSender,
            'attribs' => [
                'subject' => $subject,
                'content' => $htmlContent,
                'sender_name' => $this->defaultSenderName,
            ],
        ]);
    }

    /**
     * 使用模板发送
     *
     * @param string $recipient 收件人
     * @param string $templateId 模板 ID
     * @param array $attribs 模板变量
     * @return array
     */
    public function sendTemplate(string $recipient, string $templateId, array $attribs = []): array {
        return $this->request('/api/batch_mail/api/send', [
            'recipient' => $recipient,
            'addresser' => $this->defaultSender,
            'attribs' => $attribs,
            'template_id' => $templateId,
        ]);
    }

    /**
     * 查询发送统计
     */
    public function getStats(): array {
        return $this->request('/api/v1/statistics');
    }

    /**
     * 测试连接
     */
    public function ping(): bool {
        $res = $this->request('/api/batch_mail/api/send', [
            'recipient' => 'ping@test.com',
        ]);
        return $res['code'] >= 200 && $res['code'] < 500;
    }

    private function request(string $path, array $data = []): array {
        $ch = curl_init($this->apiUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        return [
            'code' => $code,
            'data' => $resp ? json_decode($resp, true) : null,
            'error' => $error ?: null,
        ];
    }
}
