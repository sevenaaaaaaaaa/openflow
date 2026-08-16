<?php
/**
 * Marketing Automation 融合同步库
 * 负责：表单提交 → Mautic 联系人/标签/Campaign + BillionMail 列表
 */

function ma_sync_config(): array {
    return json_read(DATA_DIR . '/ma-sync.json');
}

// 写同步日志
function ma_sync_log(string $level, string $msg, string $target = '', ?array $retry = null): string {
    $file = DATA_DIR . '/ma-sync-log.json';
    $log = json_read($file);
    $entry = [
        'id' => 'log_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'time' => date('Y-m-d H:i:s'),
        'level' => $level, // success / error / info
        'target' => $target, // mautic / billionmail / none
        'message' => $msg,
    ];
    if ($retry !== null) $entry['retry'] = $retry; // 失败时附带重试所需数据
    $log[] = $entry;
    // 保留最近 200 条
    $log = array_slice($log, -200);
    json_write($file, $log);
    return $entry['id'];
}

// 重试一条失败的同步
function ma_retry(string $logId): array {
    $file = DATA_DIR . '/ma-sync-log.json';
    $log = json_read($file);
    foreach ($log as $entry) {
        if ($entry['id'] === $logId && !empty($entry['retry'])) {
            $retry = $entry['retry'];
            if (($retry['kind'] ?? '') === 'mautic') {
                $r = ma_sync_to_mautic($retry['data'], $retry['form_type'], $retry['form_title']);
                return ['ok' => $r['ok'], 'msg' => $r['ok'] ? 'Mautic 重试成功' : '重试失败：' . ($r['error'] ?? '未知')];
            }
            if (($retry['kind'] ?? '') === 'billionmail') {
                $r = ma_sync_to_billionmail($retry['data'], $retry['list_id'] ?? '');
                return ['ok' => $r['ok'], 'msg' => $r['ok'] ? 'BillionMail 重试成功' : '重试失败'];
            }
            return ['ok' => false, 'msg' => '未知重试类型'];
        }
    }
    return ['ok' => false, 'msg' => '未找到可重试的记录'];
}

/**
 * 从表单数据提取联系人字段
 */
function ma_extract_contact(array $formData): array {
    $map = [
        'email' => ['email', 'mail', 'contact'],
        'firstname' => ['firstname', 'name', '姓名'],
        'lastname' => ['lastname'],
        'company' => ['company', 'company_name', '企业', '企业名称'],
        'phone' => ['phone', 'mobile', 'tel', '手机', '电话'],
        'position' => ['title', 'position', '职位'],
        'city' => ['city', '城市'],
    ];
    $contact = [];
    foreach ($map as $field => $keys) {
        foreach ($keys as $k) {
            if (!empty($formData[$k])) { $contact[$field] = $formData[$k]; break; }
        }
    }
    return $contact;
}

/**
 * 同步单条线索到 Mautic（联系人 + 标签 + Campaign）
 * @return array ['ok' => bool, 'contact_id' => ?, 'error' => ?]
 */
function ma_sync_to_mautic(array $formData, string $formType, string $formTitle): array {
    $cfg = ma_sync_config();
    if (empty($cfg['enabled']) || empty($cfg['mautic_enabled'])) {
        ma_sync_log('info', 'Mautic 同步未启用，跳过', 'mautic');
        return ['ok' => false, 'skipped' => true];
    }
    $email = trim($formData['email'] ?? '');
    if (empty($email)) {
        ma_sync_log('error', "「{$formTitle}」无邮箱，跳过 Mautic 同步", 'mautic');
        return ['ok' => false, 'error' => '无邮箱'];
    }

    $m = Mautic::fromConfig();
    if (!$m) {
        ma_sync_log('error', 'Mautic 实例创建失败，请检查 mautic.json 配置', 'mautic');
        return ['ok' => false, 'error' => 'Mautic 未配置'];
    }

    $contact = ma_extract_contact($formData);
    if (empty($contact)) $contact['email'] = $email;

    $result = $m->createContact($email, $contact);
    if (!$result) {
        ma_sync_log('error', "Mautic 创建联系人失败: {$email}", 'mautic', [
            'kind' => 'mautic', 'data' => $formData, 'form_type' => $formType, 'form_title' => $formTitle,
        ]);
        return ['ok' => false, 'error' => 'Mautic 创建联系人失败'];
    }

    $contactId = $result['contact']['id'] ?? null;
    $msg = "Mautic 联系人已同步: {$email}" . ($contactId ? " (ID {$contactId})" : '');

    // 打标签
    $tags = [];
    if (!empty($cfg['tags'][$formType])) $tags = (array)$cfg['tags'][$formType];
    if (!empty($formData['focus'])) $tags[] = '关注:' . $formData['focus'];
    if ($formType === 'download' && !empty($formData['download_title'])) $tags[] = '下载:' . $formData['download_title'];

    if ($tags) {
        $tagStr = implode(',', array_unique($tags));
        if ($contactId) {
            $tr = $m->addTags($contactId, array_unique($tags));
            $msg .= $tr ? " · 已打标签: {$tagStr}" : " · 标签失败";
        } else {
            ma_sync_log('info', "Mautic 标签待处理: {$tagStr}（无联系人 ID）", 'mautic');
        }
    }

    // 进 Campaign
    if ($contactId && !empty($cfg['campaigns'][$formType])) {
        $cid = (int)$cfg['campaigns'][$formType];
        if ($cid > 0) {
            $cr = $m->addToCampaign($cid, $contactId);
            $msg .= $cr ? " · 已入 Campaign {$cid}" : " · Campaign 加入失败";
        }
    }

    ma_sync_log('success', $msg, 'mautic');
    return ['ok' => true, 'contact_id' => $contactId];
}

/**
 * 同步订阅者到 BillionMail 列表 + 欢迎信
 */
function ma_sync_to_billionmail(array $formData, string $listId): array {
    $cfg = ma_sync_config();
    if (empty($cfg['enabled']) || empty($cfg['bm_enabled'])) {
        ma_sync_log('info', 'BillionMail 同步未启用，跳过', 'billionmail');
        return ['ok' => false, 'skipped' => true];
    }
    $email = trim($formData['email'] ?? '');
    if (empty($email)) {
        ma_sync_log('error', '订阅无邮箱，跳过 BillionMail', 'billionmail');
        return ['ok' => false, 'error' => '无邮箱'];
    }

    $bm = BillionMail::fromConfig();
    if (!$bm) {
        ma_sync_log('error', 'BillionMail 未配置', 'billionmail');
        return ['ok' => false, 'error' => 'BillionMail 未配置'];
    }

    // 发送欢迎信（attribs 带 list_id 由 BillionMail 服务端加入列表）
    $welcomeSubject = $cfg['bm_welcome_subject'] ?? '欢迎订阅 OpenFlow Newsletter';
    $welcomeHtml = $cfg['bm_welcome_html'] ?? '<h1>感谢订阅</h1><p>欢迎加入 OpenFlow 社区，我们会定期发送网站增长洞察与行业实践。</p>';
    $res = $bm->send($email, $welcomeSubject, $welcomeHtml, [
        'list_id' => $listId,
        'name' => $formData['name'] ?? '',
    ]);

    $ok = ($res['code'] ?? 0) >= 200 && ($res['code'] ?? 0) < 300;
    if ($ok) {
        ma_sync_log('success', "BillionMail 订阅已加入列表 {$listId}: {$email}", 'billionmail');
    } else {
        ma_sync_log('error', "BillionMail 订阅失败 {$email}: " . json_encode($res), 'billionmail', [
            'kind' => 'billionmail', 'data' => $formData, 'list_id' => $listId,
        ]);
    }
    return ['ok' => $ok, 'response' => $res];
}

/**
 * 统一入口：表单提交后同步
 */
function ma_sync_form_submission(array $form, array $formData): void {
    $cfg = ma_sync_config();
    if (empty($cfg['enabled'])) {
        ma_sync_log('info', 'MA 融合同步总开关关闭', 'none');
        return;
    }
    $type = $form['type'] ?? 'lead';

    // 1. 同步到 Mautic（lead / newsletter / download 都同步，除非关闭）
    if (!empty($cfg['mautic_enabled']) && in_array($type, ['lead', 'newsletter', 'download'])) {
        ma_sync_to_mautic($formData, $type, $form['title'] ?? '');
    }

    // 2. newsletter 同步 BillionMail 列表
    if ($type === 'newsletter' && !empty($cfg['bm_enabled']) && !empty($form['newsletter_list_id'])) {
        ma_sync_to_billionmail($formData, $form['newsletter_list_id']);
    }
}
