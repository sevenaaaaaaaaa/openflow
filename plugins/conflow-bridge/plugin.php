<?php
/**
 * ConFlow 桥接插件（for OpenFlow PluginSystem v2）
 *
 * 安装：复制本目录到 openflow 的 plugins/conflow-bridge/，后台「系统 → 插件管理」启用
 *      （manifest 已设 enabled_by_default=false，需手动开启）。
 * 配置：后台 → ConFlow 菜单 → 填 ConFlow 服务器地址（如 https://nownexts.com/VTH）与
 *       API Token（ConFlow 服务器 .env 的 CONFLOW_TOKEN）。
 * 职责边界：本插件只做「提交任务 + 查看进度 + 深链产物」；成品文章由 ConFlow 服务端经其
 *       内置 openflow 推送适配器（幂等覆盖、写前备份）写入内容库——发布链路复用已验证路径。
 * AI 岗位工具：/api/plugin/conflow-bridge/{health|submit|jobs} 即工具节点，
 *       schema 见 ConFlow 仓库 docs/bridge/ai-tool-schema.json。
 */

define('CONFLOW_BRIDGE_CFG', DATA_DIR . '/plugins/conflow-bridge/config.json');

function conflow_bridge_cfg(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = is_file(CONFLOW_BRIDGE_CFG) ? (json_read(CONFLOW_BRIDGE_CFG) ?: []) : [];
    }
    return $cfg;
}

function conflow_bridge_api(string $method, string $path, ?array $body = null): array {
    $cfg = conflow_bridge_cfg();
    if (empty($cfg['server']) || empty($cfg['token'])) {
        return ['ok' => false, 'error' => '未配置 ConFlow 服务器地址或 Token'];
    }
    $url = rtrim($cfg['server'], '/') . $path . (str_contains($path, '?') ? '&' : '?') . 'cb=' . time();
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $cfg['token'], 'Accept: application/json'];
    if ($body !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)]);
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code,
            'data' => json_decode((string) $raw, true)];
}

/* --------------------------------------------------------- 菜单与页面 ---- */

PluginSystem::register_admin_menu(['id' => 'conflow-bridge', 'label' => 'ConFlow', 'order' => 55]);

PluginSystem::register_admin_page('conflow-bridge', function ($pluginId) {
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conflow_server'])) {
        json_write(CONFLOW_BRIDGE_CFG, [
            'server'      => rtrim(trim((string) $_POST['conflow_server']), '/'),
            'token'       => trim((string) ($_POST['conflow_token'] ?? '')),
            'doc_type'    => in_array($_POST['conflow_doc_type'] ?? '', ['auto', 'tutorial', 'science', 'commentary', 'other'], true) ? $_POST['conflow_doc_type'] : 'auto',
            'language'    => preg_match('/^[a-z]{2}$/', (string) ($_POST['conflow_language'] ?? '')) ? $_POST['conflow_language'] : 'zh',
            'want_script' => !empty($_POST['conflow_script']),
        ]);
        $msg = '已保存';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['conflow_url'])) {
        $cfg = conflow_bridge_cfg();
        $r = conflow_bridge_api('POST', '/api/jobs', [
            'url'      => trim((string) $_POST['conflow_url']),
            'doc_type' => $cfg['doc_type'] ?? 'auto',
            'language' => $cfg['language'] ?? 'zh',
            'script'   => !empty($cfg['want_script']),
        ]);
        $msg = ($r['ok'] && !empty($r['data']['id']))
            ? '任务已提交：' . htmlspecialchars((string) $r['data']['id'])
            : '提交失败：' . htmlspecialchars(mb_substr(json_encode($r['data'] ?? ($r['error'] ?? ''), JSON_UNESCAPED_UNICODE), 0, 160));
    }

    $cfg = conflow_bridge_cfg();
    $jobs = conflow_bridge_api('GET', '/api/jobs')['data'] ?? [];
    ob_start(); ?>
    <div>
      <h1>ConFlow — 视频 → 内容供给</h1>
      <?php if ($msg): ?><div class="ok"><?= $msg ?></div><?php endif; ?>

      <div class="panel"><h2>配置</h2>
        <form method="post">
          <label>服务器地址</label>
          <input name="conflow_server" value="<?= htmlspecialchars($cfg['server'] ?? '') ?>" placeholder="https://nownexts.com/VTH" style="width:420px">
          <label>API Token</label>
          <input name="conflow_token" type="password" value="<?= htmlspecialchars($cfg['token'] ?? '') ?>" style="width:420px">
          <label>默认文体</label>
          <select name="conflow_doc_type">
            <?php foreach (['auto' => '自动判定', 'tutorial' => '教程', 'science' => '科普', 'commentary' => '评论', 'other' => '其他'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($cfg['doc_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <label>产出语言</label>
          <select name="conflow_language">
            <?php foreach (['zh' => '中文', 'en' => 'English', 'ja' => '日本語'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($cfg['language'] ?? 'zh') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <label style="display:block;margin-top:10px">
            <input type="checkbox" name="conflow_script" <?= !empty($cfg['want_script']) ? 'checked' : '' ?>>
            每个任务追加 45–90s 短视频口播脚本
          </label>
          <p><button class="btn">保存配置</button></p>
        </form>
      </div>

      <div class="panel"><h2>提交视频任务</h2>
        <form method="post">
          <input name="conflow_url" style="width:520px" placeholder="https://www.youtube.com/watch?v=xxxx（频道 / 播放列表亦可）" required>
          <button class="btn">提交</button>
        </form>
        <p class="hint" style="color:var(--muted);margin-top:8px">成品由 ConFlow 自动推送进内容库（草稿态），到「内容中心」审核发布。</p>
      </div>

      <div class="panel"><h2>最近任务</h2>
        <table class="t">
          <tr><th>状态</th><th>标题</th><th>文体</th><th>语言</th><th>产物</th></tr>
          <?php foreach ((array) $jobs as $j): ?>
          <tr>
            <td><?= htmlspecialchars((string) ($j['status'] ?? '')) ?></td>
            <td><?= htmlspecialchars(mb_substr((string) ($j['title'] ?? $j['url']), 0, 44)) ?></td>
            <td><?= htmlspecialchars((string) ($j['doc_type'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($j['language'] ?? 'zh')) ?></td>
            <td><?php if (($j['status'] ?? '') === 'done' && !empty($j['video_id'])): ?>
              <a href="<?= htmlspecialchars(($cfg['server'] ?? '') . '/output/' . $j['video_id'] . '/slides.html') ?>" target="_blank">幻灯片</a> ·
              <a href="<?= htmlspecialchars(($cfg['server'] ?? '') . '/output/' . $j['video_id'] . '/doc.md') ?>" target="_blank">文章</a>
              <?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($jobs)): ?><tr><td colspan="5">暂无任务 — 保存配置后提交一条视频链接</td></tr><?php endif; ?>
        </table>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/* ---------------------------------------------------- AI 岗位工具 API ---- */
// /api/plugin/conflow-bridge/{health|submit|jobs}；schema 见 docs/bridge/ai-tool-schema.json

PluginSystem::register_api_route('conflow-bridge', 'GET', 'health', function () {
    $r = conflow_bridge_api('GET', '/api/health');
    echo json_encode(['ok' => $r['ok'], 'configured' => !empty(conflow_bridge_cfg()['server']),
                      'llm' => $r['data']['llm_configured'] ?? null], JSON_UNESCAPED_UNICODE);
});

PluginSystem::register_api_route('conflow-bridge', 'POST', 'submit', function () {
    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_REQUEST;
    $r = conflow_bridge_api('POST', '/api/jobs', [
        'url'      => (string) ($input['url'] ?? ''),
        'doc_type' => (string) ($input['doc_type'] ?? (conflow_bridge_cfg()['doc_type'] ?? 'auto')),
        'language' => (string) ($input['language'] ?? (conflow_bridge_cfg()['language'] ?? 'zh')),
        'script'   => !empty($input['script']),
    ]);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
});

PluginSystem::register_api_route('conflow-bridge', 'GET', 'jobs', function () {
    echo json_encode(conflow_bridge_api('GET', '/api/jobs')['data'] ?? [], JSON_UNESCAPED_UNICODE);
});
