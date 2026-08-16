<?php
/**
 * 企业微信（WeCom）能力库
 * 客户标签/分组 · 定向私信（应用消息）· 群发助手 · 客户群
 * 基于企业微信 API：自建应用 + 客户联系
 */
require_once __DIR__ . '/../admin/config.php';

class Wecom {
    private static string $tokenFile = DATA_DIR . '/wecom_token.json';

    public static function config(): array {
        $cfg = json_read(DATA_DIR . '/wechat.json');
        return $cfg['wecom'] ?? [];
    }

    /**
     * 获取企业微信 access_token（带缓存）
     */
    public static function accessToken(): ?string {
        $cfg = self::config();
        $corpId = $cfg['corp_id'] ?? '';
        $secret = $cfg['secret'] ?? '';
        if (!$corpId || !$secret) return null;

        $cached = json_read(self::$tokenFile);
        if ($cached && ($cached['expires'] ?? 0) > time()) return $cached['token'] ?? null;

        $ch = curl_init("https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpId}&corpsecret={$secret}");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $resp = json_decode(curl_exec($ch), true);

        $token = $resp['access_token'] ?? null;
        if ($token) {
            json_write(self::$tokenFile, ['token' => $token, 'expires' => time() + 7100]);
        }
        return $token;
    }

    private static function request(string $path, array $params = [], ?array $body = null): array {
        $at = self::accessToken();
        if (!$at) return ['errcode' => -1, 'errmsg' => 'access_token 获取失败'];
        $url = "https://qyapi.weixin.qq.com/{$path}?access_token={$at}" . (empty($params) ? '' : '&' . http_build_query($params));
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15];
        if ($body !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $resp = json_decode(curl_exec($ch), true);
        return $resp ?: ['errcode' => -1, 'errmsg' => '请求失败'];
    }

    // ─── 成员/部门 ───

    public static function departmentList(): array {
        return self::request('cgi-bin/department/list');
    }

    public static function userList(int $deptId = 1, bool $fetchChild = true): array {
        return self::request('cgi-bin/user/simplelist', ['department_id' => $deptId, 'fetch_child' => $fetchChild ? 1 : 0]);
    }

    // ─── 客户标签（客户联系）───

    /**
     * 获取客户标签
     */
    public static function customerTags(): array {
        return self::request('cgi-bin/externalcontact/get_corp_tag_list', [], []);
    }

    /**
     * 添加客户标签
     */
    public static function addCustomerTag(string $groupName, array $tagNames): array {
        return self::request('cgi-bin/externalcontact/add_corp_tag', [], [
            'group_name' => $groupName,
            'tag' => array_map(fn($n) => ['name' => $n], $tagNames),
        ]);
    }

    /**
     * 给客户打标签
     */
    public static function tagCustomer(string $userId, string $externalUserId, array $tagIds): array {
        return self::request('cgi-bin/externalcontact/mark_tag', [], [
            'userid' => $userId,
            'external_userid' => $externalUserId,
            'add_tag' => $tagIds,
        ]);
    }

    // ─── 客户列表 ───

    /**
     * 获取成员的客户列表
     */
    public static function customerList(string $userId): array {
        return self::request('cgi-bin/externalcontact/list', ['userid' => $userId]);
    }

    // ─── 应用消息（定向私信）───

    /**
     * 发送应用消息（定向私信给成员/客户）
     * @param array $to 接收人：['touser' => ['user1','user2']] 或 ['toparty' => [1]] 或 ['totag' => [tagid]]
     * @param string $msgType text / textcard / news
     */
    public static function sendAppMessage(array $to, string $msgType, array $content, string $agentId = ''): array {
        $cfg = self::config();
        $body = array_merge($to, [
            'msgtype' => $msgType,
            'agentid' => $agentId ?: ($cfg['agent_id'] ?? ''),
            $msgType => $content,
        ]);
        return self::request('cgi-bin/message/send', [], $body);
    }

    /**
     * 发送文本消息（最常用）
     */
    public static function sendText(array $to, string $content, string $agentId = ''): array {
        return self::sendAppMessage($to, 'text', ['content' => $content], $agentId);
    }

    /**
     * 发送图文卡片消息
     */
    public static function sendNews(array $to, array $articles, string $agentId = ''): array {
        return self::sendAppMessage($to, 'news', ['articles' => $articles], $agentId);
    }

    /**
     * 文本卡片消息（带跳转）
     */
    public static function sendTextCard(array $to, string $title, string $desc, string $url, string $agentId = ''): array {
        return self::sendAppMessage($to, 'textcard', [
            'title' => $title, 'description' => $desc, 'url' => $url, 'btntxt' => '查看',
        ], $agentId);
    }

    // ─── 群发助手（客户联系）───

    /**
     * 添加群发任务（给外部客户批量群发）
     */
    public static function addMsgTemplate(array $externalUserids, string $text = '', array $attachments = [], string $sender = ''): array {
        return self::request('cgi-bin/externalcontact/add_msg_template', [], [
            'chat_type' => 'single',
            'external_userid' => $externalUserids,
            'sender' => $sender,
            'text' => ['content' => $text],
            'attachments' => $attachments,
        ]);
    }

    /**
     * 客户群群发
     */
    public static function addGroupMsgTemplate(array $chatIds, string $text = ''): array {
        return self::request('cgi-bin/externalcontact/add_msg_template', [], [
            'chat_type' => 'group',
            'chat_id_list' => $chatIds,
            'text' => ['content' => $text],
        ]);
    }

    /**
     * 获取群发记录
     */
    public static function groupMsgList(string $status = '', int $limit = 20): array {
        $body = ['limit' => $limit];
        if ($status) $body['status'] = $status;
        return self::request('cgi-bin/externalcontact/get_groupmsg_list_v2', [], $body);
    }

    // ─── 客户群 ───

    public static function groupChatList(int $limit = 100): array {
        return self::request('cgi-bin/externalcontact/groupchat/list', [], ['limit' => $limit]);
    }

    /**
     * 是否已配置
     */
    public static function configured(): bool {
        $cfg = self::config();
        return !empty($cfg['corp_id']) && !empty($cfg['secret']) && !empty($cfg['agent_id']);
    }
}
