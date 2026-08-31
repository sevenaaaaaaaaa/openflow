<?php
/**
 * 调研系统 — 付费官方 Agent 咨询
 * 付费后可对话官方 AI Agent 获得调研结果解读 / 组织健康建议
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/survey-lib.php';
require_login();
require_perm('settings');

$agentFile = DATA_DIR . '/survey/agent.json';
$agent = json_read($agentFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    csrf_verify();
    $agent['paid'] = true;
    $agent['paid_at'] = date('Y-m-d H:i:s');
    $agent['conversations'] = $agent['conversations'] ?? [];
    json_write($agentFile, $agent);
    $message = '已开通 Agent 咨询';
}

// 发送对话
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    csrf_verify();
    $agent['conversations'] = $agent['conversations'] ?? [];
    $userMsg = trim($_POST['msg'] ?? '');
    if (!empty($userMsg)) {
        $agent['conversations'][] = ['role' => 'user', 'content' => $userMsg, 'time' => date('Y-m-d H:i:s')];
        // 统一走 AiCenter：记账 + 额度闸门 + 分档超时。
        // 原来这里又自建了一份 curl（60 秒超时、零记账、漏 Claude 分支）。
        require_once __DIR__ . '/../lib/AiCenter.php';
        $systemPrompt = "你是 OpenFlow 的官方网站增长顾问 Agent，负责解读调研数据并给出增长建议。请专业、结构化地回答问题，涉及数据时提醒用户参考后台统计。";

        if (!AiCenter::isConfigured()) {
            $reply = '检测到尚未配置 AI 供应商。请先在「系统 → AI Agent」中启用一个供应商并填写 API Key。';
        } else {
            $r = AiCenter::chat($systemPrompt, $userMsg, [
                'max_tokens' => 1500, 'feature' => 'survey_agent', 'tier' => 'admin',
            ]);
            $reply = !empty($r['ok']) && ($r['text'] ?? '') !== ''
                ? (string)$r['text']
                : ('抱歉，暂时无法获取回复：' . mb_substr((string)($r['error'] ?? '未知原因'), 0, 80));
        }

        $agent['conversations'][] = ['role' => 'assistant', 'content' => $reply, 'time' => date('Y-m-d H:i:s')];
        $agent['conversations'] = array_slice($agent['conversations'], -30);
        json_write($agentFile, $agent);
    }
    // 重定向避免重复提交
    header('Location: /xmp/survey-agent');
    exit;
}

$conversations = $agent['conversations'] ?? [];
$isPaid = !empty($agent['paid']);

admin_header('官方 Agent 咨询');
?>
<div class="admin-layout">
  <?php admin_sidebar('survey-agent'); ?>
  <div class="main">
    <h1> 官方 Agent 咨询</h1>
    <p class="sub">付费后与官方 Agent 对话，获得调研结果解读与组织健康建议</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <a href="survey" class="btn btn-ghost">📋 问卷管理</a>
      <a href="survey-stats.php" class="btn btn-ghost">📊 统计查看</a>
      <a href="survey-org.php" class="btn btn-ghost">🏢 组织架构</a>
      <a href="survey-agent.php" class="btn btn-primary" style="margin-left:auto">🤖 官方 Agent</a>
    </div>

    <?php if (!$isPaid): ?>
    <!-- 付费墙 -->
    <div class="card" style="text-align:center;padding:48px 24px">
      <div style="font-size:52px">🔒</div>
      <h2 style="margin-top:16px">解锁官方 Agent 咨询</h2>
      <p class="text-muted" style="max-width:440px;margin:12px auto 0;line-height:1.8">付费后可随时与官方 Agent 对话：<br>解读调研数据 · 获取增长改善建议 · 定制化问题咨询</p>
      <div style="display:flex;gap:24px;justify-content:center;margin-top:28px;flex-wrap:wrap;align-items:center">
        <div>
          <p class="text-sm text-muted" style="margin-bottom:8px">扫码支付 ¥299 / 次</p>
          <div style="width:160px;height:160px;border:1px dashed var(--border);border-radius:12px;display:grid;place-items:center;color:var(--text-3);background:var(--surface-2)">收款二维码<br>（对接支付后展示）</div>
        </div>
        <div style="text-align:left;max-width:260px">
          <p class="text-sm" style="font-weight:600">支付说明</p>
          <ul style="font-size:13px;color:var(--text-2);line-height:2;padding-left:18px">
            <li>对接微信/支付宝支付后自动开通</li>
            <li>当前为演示版：管理员可手动开通</li>
            <li>支付问题联系 admin@example.com</li>
          </ul>
          <form method="post" style="margin-top:16px">
            <?= csrf_field() ?>
            <button type="submit" name="mark_paid" class="btn btn-primary">✅ 我已支付，开通（演示）</button>
          </form>
        </div>
      </div>
    </div>
    <?php else: ?>
    <!-- 对话界面 -->
    <div class="card" style="display:flex;flex-direction:column;height:calc(100vh - 240px);min-height:480px;padding:0;overflow:hidden">
      <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,#1a1625,#2b5f7e);color:#fff">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(160deg,#7dd3fc,#7dd3fc 55%,#38bdf8);display:grid;place-items:center;font-size:18px">🤖</div>
        <div>
          <div style="font-weight:700;font-size:14px">OpenFlow 官方顾问</div>
          <div style="font-size:11px;opacity:.8">付费用户 · 已开通 <?=htmlspecialchars($agent['paid_at'] ?? '')?></div>
        </div>
        <span style="margin-left:auto;font-size:12px;background:rgba(255,255,255,.15);padding:4px 10px;border-radius:999px">¥299 已解锁</span>
      </div>
      <div id="agentBody" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;background:#faf9f4">
        <div class="agent-msg" style="max-width:80%;align-self:flex-start;background:#fff;border:1px solid var(--border);padding:12px 16px;border-radius:14px;border-bottom-left-radius:4px;font-size:13.5px;line-height:1.7;white-space:pre-wrap">
          你好！我是 OpenFlow 官方增长顾问。你可以问我：<br>• 「帮我解读这份调研的平均分反映了什么」<br>• 「网站转化率偏低，应该怎么改善？」<br>• 「如何设计一次有效的增长调研？」
        </div>
        <?php foreach ($conversations as $c): ?>
        <div class="agent-msg" style="max-width:80%;align-self:<?=$c['role']==='user'?'flex-end':'flex-start'?>;background:<?=$c['role']==='user'?'#1a1625':'#fff'?>;color:<?=$c['role']==='user'?'#fff':'inherit'?>;border:1px solid <?=$c['role']==='user'?'#1a1625':'var(--border)'?>;padding:12px 16px;border-radius:14px;<?=$c['role']==='user'?'border-bottom-right-radius:4px':'border-bottom-left-radius:4px'?>;font-size:13.5px;line-height:1.7;white-space:pre-wrap">
          <?=htmlspecialchars($c['content'])?>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="post" style="display:flex;gap:8px;padding:12px;border-top:1px solid var(--border);background:#fff">
        <?= csrf_field() ?>
        <input type="text" name="msg" required placeholder="输入你的问题，如：如何解读这份调研结果？" style="flex:1;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;outline:none">
        <button type="submit" name="send_msg" class="btn btn-primary">发送</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
// 滚动到底部
var body = document.getElementById('agentBody');
if (body) body.scrollTop = body.scrollHeight;
</script>
<?php admin_footer(); ?>
