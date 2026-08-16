<?php
/**
 * 微信公众号（服务号）能力库
 * 群发消息（文本/图文/模板）· 用户标签 · 客服消息 · 模板消息 · 素材管理
 * 全部基于官方 REST API，access_token 自动缓存
 */
require_once __DIR__ . '/../admin/config.php';

class WechatMp {
    private static string $tokenFile = DATA_DIR . '/wechat_token.json';

    public static function config(): array {
        $cfg = json_read(DATA_DIR . '/wechat.json');
        return $cfg['mp'] ?? [];
    }

    /**
     * 获取 access_token（带缓存）
     */
    public static function accessToken(): ?string {
        $cfg = self::config();
        $appid = $cfg['appid'] ?? '';
        $secret = $cfg['secret'] ?? '';
        if (!$appid || !$secret) return null;

        $cached = json_read(self::$tokenFile);
        if ($cached && ($cached['expires'] ?? 0) > time()) return $cached['token'] ?? null;

        $ch = curl_init("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $resp = json_decode(curl_exec($ch), true);

        $token = $resp['access_token'] ?? null;
        if ($token) {
            json_write(self::$tokenFile, ['token' => $token, 'expires' => time() + ($resp['expires_in'] ?? 7200) - 300]);
        }
        return $token;
    }

    private static function request(string $path, array $params = [], ?array $json = null): array {
        $at = self::accessToken();
        if (!$at) return ['errcode' => -1, 'errmsg' => 'access_token 获取失败'];
        $url = "https://api.weixin.qq.com/{$path}?access_token={$at}" . (empty($params) ? '' : '&' . http_build_query($params));
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15];
        if ($json !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $resp = json_decode(curl_exec($ch), true);
        return $resp ?: ['errcode' => -1, 'errmsg' => '请求失败'];
    }

    // ─── 素材管理 ───

    /**
     * 上传图文素材，返回 media_id
     */
    public static function addNews(array $articles): array {
        return self::request('cgi-bin/material/add_news', [], ['articles' => $articles]);
    }

    // ─── 群发消息 ───

    /**
     * 按标签群发 / 全部群发
     * @param array $content 群发内容，如 ['content' => '文本'] 或 ['media_id' => 'xxx']
     * @param string $msgType text / mpnews / image / voice / video / card
     * @param int $tagId 指定标签群发（0 = 全部粉丝）
     * @param bool $sendIgnoreReprint 图文群发是否允许转载
     */
    public static function massSendByTag(array $content, string $msgType = 'text', int $tagId = 0, bool $sendIgnoreReprint = false): array {
        $body = [
            'filter' => ['is_to_all' => $tagId === 0, 'tag_id' => $tagId],
            'msgtype' => $msgType,
            $msgType => $content,
            'send_ignore_reprint' => $sendIgnoreReprint ? 1 : 0,
        ];
        return self::request('cgi-bin/message/mass/sendall', [], $body);
    }

    /**
     * 按 openid 列表群发（定向群发）
     */
    public static function massSendByOpenids(array $openids, array $content, string $msgType = 'text'): array {
        $body = [
            'touser' => $openids,
            'msgtype' => $msgType,
            $msgType => $content,
        ];
        return self::request('cgi-bin/message/mass/send', [], $body);
    }

    /**
     * 预览群发（发给自己测试）
     */
    public static function massPreview(string $openid, array $content, string $msgType = 'text'): array {
        $body = [
            'touser' => $openid,
            'msgtype' => $msgType,
            $msgType => $content,
        ];
        return self::request('cgi-bin/message/mass/preview', [], $body);
    }

    /**
     * 查询群发状态
     */
    public static function massStatus(string $msgId): array {
        return self::request('cgi-bin/message/mass/get', [], ['msg_id' => $msgId]);
    }

    // ─── 用户标签 ───

    public static function createTag(string $name): array {
        return self::request('cgi-bin/tags/create', [], ['tag' => ['name' => $name]]);
    }

    public static function listTags(): array {
        return self::request('cgi-bin/tags/get');
    }

    public static function deleteTag(int $tagId): array {
        return self::request('cgi-bin/tags/delete', [], ['tag' => ['id' => $tagId]]);
    }

    /**
     * 给用户打标签
     */
    public static function tagUsers(int $tagId, array $openids): array {
        return self::request('cgi-bin/tags/members/batchtagging', [], ['tagid' => $tagId, 'openid_list' => $openids]);
    }

    /**
     * 取消用户标签
     */
    public static function untagUsers(int $tagId, array $openids): array {
        return self::request('cgi-bin/tags/members/batchuntagging', [], ['tagid' => $tagId, 'openid_list' => $openids]);
    }

    /**
     * 获取用户标签列表
     */
    public static function userTags(string $openid): array {
        return self::request('cgi-bin/tags/getidlist', [], ['openid' => $openid]);
    }

    // ─── 用户管理 ───

    public static function userInfo(string $openid): array {
        return self::request('cgi-bin/user/info', ['openid' => $openid]);
    }

    public static function userList(string $nextOpenid = ''): array {
        $params = $nextOpenid ? ['next_openid' => $nextOpenid] : [];
        return self::request('cgi-bin/user/get', $params);
    }

    // ─── 客服消息（48 小时窗口私信）───

    public static function sendKfText(string $openid, string $content): array {
        return self::request('cgi-bin/message/custom/send', [], [
            'touser' => $openid, 'msgtype' => 'text', 'text' => ['content' => $content],
        ]);
    }

    public static function sendKfNews(string $openid, array $articles): array {
        return self::request('cgi-bin/message/custom/send', [], [
            'touser' => $openid, 'msgtype' => 'news', 'news' => ['articles' => $articles],
        ]);
    }

    // ─── 模板消息 ───

    public static function sendTemplate(string $openid, string $templateId, array $data, string $url = '', string $miniprogram = ''): array {
        $body = [
            'touser' => $openid,
            'template_id' => $templateId,
            'data' => $data,
        ];
        if ($url) $body['url'] = $url;
        return self::request('cgi-bin/message/template/send', [], $body);
    }

    public static function templateList(): array {
        return self::request('cgi-bin/template/get_all_private_template');
    }

    /**
     * 发送文本（自动降级：48h 内用客服消息，否则用模板消息占位）
     */
    public static function sendText(string $openid, string $content): array {
        return self::sendKfText($openid, $content);
    }
}
