<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$aiFile = DATA_DIR . '/ai-config.json';
$ai = json_read($aiFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save_providers'])) {
        $providers = [];
        foreach (($_POST['provider_id'] ?? []) as $i => $pid) {
            if (empty(trim($pid))) continue;
            $providers[] = [
                'id' => $pid,
                'name' => $_POST['provider_name'][$i] ?? '',
                'api_key' => $_POST['provider_key'][$i] ?? '',
                'model' => $_POST['provider_model'][$i] ?? '',
                'api_url' => rtrim($_POST['provider_url'][$i] ?? '', '/'),
                'enabled' => isset($_POST['provider_enabled'][$i]),
            ];
        }
        $ai['providers'] = $providers;
        $ai['default_provider'] = $_POST['default_provider'] ?? 'openai';
        $ai['default_model'] = $_POST['default_model'] ?? 'gpt-4o';
        $ai['temperature'] = (float)($_POST['temperature'] ?? 0.7);
        $ai['assistant_avatar'] = trim($_POST['assistant_avatar'] ?? '');
        json_write($aiFile, $ai);
        $message = 'AI 供应商配置已保存';
    }
    if (isset($_POST['save_prompts'])) {
        $prompts = [];
        foreach (($_POST['prompt_id'] ?? []) as $i => $pid) {
            if (empty(trim($pid))) continue;
            $prompts[] = [
                'id' => $pid,
                'name' => $_POST['prompt_name'][$i] ?? '',
                'prompt' => $_POST['prompt_text'][$i] ?? '',
            ];
        }
        $ai['global_prompts'] = $prompts;
        json_write($aiFile, $ai);
        $message = '全局提示词已保存';
    }
    $ai = json_read($aiFile);
}

admin_header('AI Agent 配置');
?>
<div class="admin-layout">
  <?php admin_sidebar('ai-config'); ?>
  <div class="main">
    <h1>AI Agent 配置</h1>
    <p class="sub">管理 AI 供应商 · 全局提示词 · 文章/页面编辑器中将显示 AI 辅助按钮</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- Providers -->
    <div class="card">
      <h2>🤖 AI 供应商</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div id="providerList">
          <?php foreach ($ai['providers'] as $i => $p): ?>
          <div class="provider-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;padding:8px;background:var(--surface-2);border-radius:8px">
            <input type="hidden" name="provider_id[]" value="<?=htmlspecialchars($p['id'])?>">
            <input type="text" name="provider_name[]" value="<?=htmlspecialchars($p['name'])?>" placeholder="名称" style="width:120px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
            <input type="password" name="provider_key[]" value="<?=htmlspecialchars($p['api_key'])?>" placeholder="API Key" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
            <input type="text" name="provider_model[]" value="<?=htmlspecialchars($p['model'])?>" placeholder="模型" style="width:140px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
            <input type="text" name="provider_url[]" value="<?=htmlspecialchars($p['api_url'])?>" placeholder="API URL" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="provider_enabled[<?=$i?>]" value="1" <?=$p['enabled']?'checked':''?>>启用</label>
            <button type="button" class="btn btn-ghost btn-sm" onclick="testProvider('<?=htmlspecialchars($p['id'])?>', this)">测试</button>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="field-row" style="margin-top:12px">
          <div class="field"><label>默认供应商</label><select name="default_provider"><?php foreach ($ai['providers'] as $p): ?><option value="<?=htmlspecialchars($p['id'])?>" <?=($ai['default_provider']??'')===$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?></select></div>
          <div class="field"><label>默认模型</label><input type="text" name="default_model" value="<?=htmlspecialchars($ai['default_model'] ?? 'gpt-4o')?>"></div>
          <div class="field"><label>温度</label><input type="number" name="temperature" value="<?=htmlspecialchars($ai['temperature'] ?? 0.7)?>" min="0" max="2" step="0.1"></div>
        </div>
        <div class="field"><label>后台小助手形象 <span class="hint">· 二次元头像图片 URL，留空使用默认形象</span></label><input type="text" name="assistant_avatar" value="<?=htmlspecialchars($ai['assistant_avatar'] ?? '')?>" placeholder="https://.../avatar.png"></div>
        <div class="flex gap-2" style="flex-wrap:wrap;margin-bottom:12px">
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('deepseek','DeepSeek','https://api.deepseek.com/v1','deepseek-chat')">+ DeepSeek</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('mimo','小米 MiMo','https://api.xiaomimimo.com/v1','MiMo-7B')">+ 小米 MiMo</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('kimi','Moonshot Kimi','https://api.moonshot.cn/v1','moonshot-v1-8k')">+ Kimi</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('glm','智谱 GLM','https://open.bigmodel.cn/api/paas/v4','glm-4-flash')">+ 智谱 GLM</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('minimax','MiniMax','https://api.minimax.chat/v1','abab6.5s-chat')">+ MiniMax</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('qwen','阿里通义 Qwen','https://dashscope.aliyuncs.com/compatible-mode/v1','qwen-plus')">+ 通义 Qwen</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('doubao','字节豆包','https://ark.cn-beijing.volces.com/api/v3','doubao-pro-32k')">+ 豆包 Doubao</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('openrouter','OpenRouter','https://openrouter.ai/api/v1','openai/gpt-4o-mini')">+ OpenRouter</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('openclaude','OpenClaude','','')">+ OpenClaude</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('openai','OpenAI','https://api.openai.com/v1','gpt-4o')">+ OpenAI</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addProvider('','自定义','','')">+ 自定义</button>
        </div>
        <button type="submit" name="save_providers" class="btn btn-primary">保存供应商配置</button>
      </form>
    </div>

    <!-- AI 用量看板 -->
    <div class="card" style="margin-bottom:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="margin:0">📊 AI 用量看板</h2>
        <button type="button" class="btn btn-ghost btn-sm" onclick="loadAiUsage()">🔄 刷新</button>
      </div>
      <div id="aiUsageBox">
        <div class="text-sm text-muted" style="padding:16px;text-align:center">加载中…</div>
      </div>
    </div>

    <!-- Global Prompts -->
    <div class="card">
      <h2>📝 全局提示词</h2>
      <p class="text-sm text-muted mb-4">在文章/页面编辑器中使用 AI 时将显示这些提示词选项</p>
      <form method="post">
        <?= csrf_field() ?>
        <div id="promptList">
          <?php foreach ($ai['global_prompts'] as $i => $pr): ?>
          <div class="prompt-row" style="border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
              <input type="hidden" name="prompt_id[]" value="<?=htmlspecialchars($pr['id'])?>">
              <input type="text" name="prompt_name[]" value="<?=htmlspecialchars($pr['name'])?>" placeholder="提示词名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.prompt-row').remove()">✕</button>
            </div>
            <textarea name="prompt_text[]" rows="3" placeholder="提示词内容" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)"><?=htmlspecialchars($pr['prompt'])?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="flex gap-2" style="flex-wrap:wrap;margin-bottom:12px">
          <button type="button" class="btn btn-ghost btn-sm" onclick="addPrompt('summary','总结摘要','请用中文总结以下文章的核心要点，控制在200字以内。')">+ 总结</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addPrompt('rewrite','优化改写','请优化以下文本的表达，使其更专业流畅。')">+ 改写</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addPrompt('seo','生成SEO标题','请根据以下文章内容，生成5个SEO友好的中文标题。')">+ SEO标题</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addPrompt('','自定义','')">+ 自定义</button>
        </div>
        <button type="submit" name="save_prompts" class="btn btn-primary">保存提示词</button>
      </form>
    </div>

    <!-- Usage -->
    <div class="card">
      <h2>💡 使用方式</h2>
      <p class="text-sm text-muted">配置完成后，在以下位置将出现 AI 辅助按钮：</p>
      <table><thead><tr><th>位置</th><th>功能</th></tr></thead>
      <tbody>
        <tr><td>文章编辑器</td><td>「🤖 AI 辅助」按钮 → 总结 / 改写 / SEO 标题/描述 / 扩写 / 翻译</td></tr>
        <tr><td>页面编辑器</td><td>「🤖 AI 辅助」同上</td></tr>
      </tbody></table>
      <p class="text-sm text-muted mt-4">API 调用端点: <code>POST /api/ai-generate.php</code></p>
    </div>
  </div>
</div>

<script>
function addProvider(id, name, url, model) {
  var div = document.createElement('div');
  div.className = 'provider-row';
  div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;padding:8px;background:var(--surface-2);border-radius:8px';
  var idx = document.querySelectorAll('#providerList .provider-row').length;
  div.innerHTML =
    '<input type="hidden" name="provider_id[]" value="' + id + '">' +
    '<input type="text" name="provider_name[]" value="' + name + '" placeholder="名称" style="width:120px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
    '<input type="password" name="provider_key[]" value="" placeholder="API Key" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
    '<input type="text" name="provider_model[]" value="' + model + '" placeholder="模型" style="width:140px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
    '<input type="text" name="provider_url[]" value="' + url + '" placeholder="API URL" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' +
    '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="provider_enabled[' + idx + ']" value="1" checked>启用</label>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('providerList').appendChild(div);
}
function addPrompt(id, name, text) {
  var div = document.createElement('div');
  div.className = 'prompt-row';
  div.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)';
  div.innerHTML =
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
      '<input type="hidden" name="prompt_id[]" value="' + id + '">' +
      '<input type="text" name="prompt_name[]" value="' + name + '" placeholder="提示词名称" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.prompt-row\').remove()">✕</button>' +
    '</div>' +
    '<textarea name="prompt_text[]" rows="3" placeholder="提示词内容" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--mono)">' + text + '</textarea>';
  document.getElementById('promptList').appendChild(div);
}
// 测试供应商连接
function testProvider(id, btn) {
  var original = btn.textContent;
  btn.textContent = '测试中…'; btn.disabled = true;
  fetch('../api/ai-business.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'test_provider', provider_id: id})
  }).then(function(r){return r.json();}).then(function(d){
    btn.disabled = false;
    if (d.ok) { btn.textContent = '✅ 连接成功'; btn.style.color = 'var(--ok)'; }
    else { btn.textContent = '❌ 失败'; btn.style.color = 'var(--danger)'; alert(d.error || '连接失败'); }
    setTimeout(function(){ btn.textContent = original; btn.style.color = ''; }, 3000);
  }).catch(function(){ btn.disabled = false; btn.textContent = '❌ 网络异常'; });
}
// AI 用量看板
function loadAiUsage() {
  var box = document.getElementById('aiUsageBox');
  fetch('../api/ai-business.php?action=ai_usage', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: '{}'})
    .then(function(r){return r.json();})
    .then(function(d){
      if (!d.ok) { box.innerHTML = '<div class="text-sm text-muted">暂无用数据</div>'; return; }
      var h = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">' +
        '<div class="stat-card"><div class="num">' + d.total + '</div><div class="label">最近调用</div></div>' +
        '<div class="stat-card"><div class="num" style="color:' + (d.errors > 0 ? 'var(--danger)' : 'var(--ok)') + '">' + d.errors + '</div><div class="label">失败</div></div>';
      Object.keys(d.by_provider || {}).forEach(function(p){
        h += '<div class="stat-card"><div class="num" style="color:var(--accent)">' + d.by_provider[p] + '</div><div class="label">' + p + '</div></div>';
      });
      h += '</div>';
      box.innerHTML = h;
    });
}
document.addEventListener('DOMContentLoaded', function(){ loadAiUsage(); });
</script>
<?php admin_footer(); ?>
