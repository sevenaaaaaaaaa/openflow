<?php
/**
 * 生态市场管理 — 技能发布/管理 + 资产统计 + 一键安装
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/SkillSystem.php';
require_once __DIR__ . '/../lib/MarketplaceSystem.php';
require_login();
require_perm('settings');

$skills = skills_all();
$message = '';
$stats = mkt_stats();

// 保存 skill
if (isset($_POST['save_skill'])) {
    $id = trim($_POST['skill_id'] ?? '');
    $data = [
        'id' => $id,
        'type' => $_POST['type'] ?? 'prompt',
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'author' => trim($_POST['author'] ?? ($_SESSION['admin_name'] ?? 'OpenFlow')),
        'author_type' => $_POST['author_type'] ?? 'official',
        'icon' => trim($_POST['icon'] ?? '⚡'),
        'tags' => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
        'content' => $_POST['content'] ?? '',
        'steps' => [],  // workflow 简化为 textarea 分步
        'status' => $_POST['status'] ?? 'published',
        'version' => trim($_POST['version'] ?? '1.0.0'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($_POST['type'] === 'workflow') {
        foreach (array_filter(array_map('trim', explode("\n", $_POST['workflow_steps'] ?? ''))) as $line) {
            $data['steps'][] = ['title' => $line, 'desc' => ''];
        }
    }
    skill_publish($data);
    $message = '技能已保存';
    $skills = skills_all();
}
// 删除 skill
if (isset($_GET['del_skill'])) {
    skill_delete($_GET['del_skill']);
    flash('success', '技能已删除');
    header('Location: /xmp/marketplace');
    exit;
}
// 复制 skill（基于示例）
if (isset($_GET['dup_skill'])) {
    $src = skill_get($_GET['dup_skill']);
    if ($src) {
        unset($src['id']);
        $src['title'] = $src['title'] . '（副本）';
        skill_publish($src);
        $message = '已创建副本';
        $skills = skills_all();
    }
}
// 远程市场设置/同步
if (isset($_POST['save_remote'])) {
    mkt_save_remote_settings(['remote_url' => trim($_POST['remote_url'] ?? '')]);
    $message = '远程市场配置已保存';
}
if (isset($_POST['sync_remote'])) {
    $r = mkt_sync_remote();
    $message = $r['ok'] ? "远程同步完成：导入 {$r['imported']} 个技能" : '❌ ' . ($r['error'] ?? '同步失败');
    $skills = skills_all();
}
$remoteUrl = mkt_remote_url();

$tabs = $_GET['tab'] ?? 'skills';
$skillTypes = skill_types();

admin_header('生态市场');
?>
<div class="admin-layout">
  <?php admin_sidebar('marketplace'); ?>
  <div class="main">
    <h1> 生态市场</h1>
    <p class="sub">插件 / 技能 / 主题 · 发布管理 · 生态统计 · 前台 /marketplace.php</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
      <div class="card" style="border-left:4px solid #7dd3fc"><div class="text-sm text-muted">🧩 插件</div><div style="font-size:24px;font-weight:800"><?=$stats['plugins']?></div></div>
      <div class="card" style="border-left:4px solid #f59e0b"><div class="text-sm text-muted">⚡ 技能</div><div style="font-size:24px;font-weight:800"><?=$stats['skills']?></div></div>
      <div class="card" style="border-left:4px solid #b45309"><div class="text-sm text-muted">🎨 主题</div><div style="font-size:24px;font-weight:800"><?=$stats['themes']?></div></div>
      <div class="card"><div class="text-sm text-muted">📦 全部资产</div><div style="font-size:24px;font-weight:800"><?=$stats['total']?></div></div>
    </div>

    <div class="tabs" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <a href="?tab=skills" class="btn <?=$tabs==='skills'?'btn-primary':'btn-ghost'?> btn-sm">⚡ 技能管理 <?=count($skills)?></a>
      <a href="?tab=new" class="btn <?=$tabs==='new'?'btn-primary':'btn-ghost'?> btn-sm">➕ 发布技能</a>
      <a href="?tab=remote" class="btn <?=$tabs==='remote'?'btn-primary':'btn-ghost'?> btn-sm">🌍 远程市场</a>
      <a href="?tab=assets" class="btn <?=$tabs==='assets'?'btn-primary':'btn-ghost'?> btn-sm">📦 全部资产</a>
      <a href="/marketplace" target="_blank" class="btn btn-ghost btn-sm">🌐 查看前台市场</a>
    </div>

    <?php if ($tabs === 'skills'): ?>
    <!-- AI 生成插件 -->
    <div class="card" style="margin-bottom:16px;background:linear-gradient(135deg,var(--surface),rgba(125,211,252,.1))">
      <h2 style="font-size:15px">🧩 AI 生成插件</h2>
      <p class="text-sm text-muted mb-3">描述插件功能，AI 生成带 hooks 的 PHP 插件并直接写入 plugins/ 目录</p>
      <div style="display:flex;gap:8px">
        <input type="text" id="plgDesc" placeholder="如：文章阅读量展示插件，在文章页显示阅读数" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
        <button type="button" class="btn btn-primary btn-sm" onclick="aiGeneratePlugin()" id="plgBtn">✨ 生成插件</button>
      </div>
      <div id="plgMsg" class="text-sm" style="margin-top:6px"></div>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>技能</th><th>类型</th><th>作者</th><th>安装</th><th>评分</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($skills)): ?><tr><td colspan="7" class="empty">暂无技能，点击「发布技能」创建</td></tr><?php endif; ?>
          <?php foreach ($skills as $s): ?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:8px"><span style="font-size:20px"><?=htmlspecialchars($s['icon'] ?? '⚡')?></span><div><strong><?=htmlspecialchars($s['title'] ?? '')?></strong><div class="text-sm text-muted"><?=htmlspecialchars(mb_substr($s['description'] ?? '', 0, 40))?></div></div></div></td>
            <td><span class="badge" style="background:var(--surface-2);padding:2px 8px;border-radius:999px;font-size:11px"><?=$skillTypes[$s['type']]['name'] ?? $s['type']?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($s['author'] ?? '')?><?=($s['author_type'] ?? '') === 'user' ? ' <span style="color:#2e6b4f">(UGC)</span>' : ''?></td>
            <td><?=$s['installs'] ?? 0?></td>
            <td><?=($s['rating_count'] ?? 0) > 0 ? number_format((float)$s['rating'], 1) . ' ⭐' : '—'?></td>
            <td><?=($s['status'] ?? '') === 'published' ? '<span class="text-sm" style="color:var(--ok)">已发布</span>' : '<span class="text-sm" style="color:var(--warn)">草稿</span>'?></td>
            <td>
              <a href="?tab=new&edit=<?=urlencode($s['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="?dup_skill=<?=urlencode($s['id'])?>" class="btn btn-ghost btn-sm">复制</a>
              <a href="?del_skill=<?=urlencode($s['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除？')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tabs === 'new'): ?>
    <?php
      $editId = $_GET['edit'] ?? '';
      $s = $editId ? skill_get($editId) : null;
    ?>
    <div class="card" style="max-width:760px">
      <h2><?=$s ? '✏️ 编辑技能' : '➕ 发布技能'?></h2>
      <p class="text-sm text-muted mb-4">技能 = 可复用的 AI/Agent 能力包，比插件更轻，用户也可创作（UGC）</p>

      <!-- AI 生成 -->
      <div style="padding:14px;background:linear-gradient(135deg,rgba(221,255,14,.1),var(--surface-2));border-radius:12px;margin-bottom:18px">
        <div class="text-sm font-bold mb-1">🤖 AI 生成技能</div>
        <div class="text-xs text-muted mb-2">描述你想要的技能，AI 自动生成标题、提示词或工作流步骤</div>
        <div style="display:flex;gap:8px">
          <input type="text" id="aiDesc" placeholder="如：为公众号文章生成吸引人的开头" style="flex:1;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <button type="button" class="btn btn-primary btn-sm" onclick="aiGenerateSkill()" id="aiGenBtn">✨ 生成</button>
        </div>
        <div id="aiMsg" class="text-sm" style="margin-top:6px"></div>
      </div>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_skill" value="1">
        <input type="hidden" name="skill_id" value="<?=htmlspecialchars($s['id'] ?? '')?>">
        <div class="field-row">
          <div class="field"><label>名称</label><input type="text" name="title" required value="<?=htmlspecialchars($s['title'] ?? '')?>" placeholder="如：SEO 标题生成器"></div>
          <div class="field"><label>类型</label>
            <select name="type" onchange="toggleSkillType(this.value)">
              <?php foreach ($skillTypes as $tk => $tv): ?>
              <option value="<?=$tk?>" <?=($s['type'] ?? 'prompt')===$tk?'selected':''?>><?=$tv['icon']?> <?=$tv['name']?> — <?=$tv['desc']?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>图标</label><input type="text" name="icon" value="<?=htmlspecialchars($s['icon'] ?? '⚡')?>" style="width:90px"></div>
          <div class="field"><label>作者</label><input type="text" name="author" value="<?=htmlspecialchars($s['author'] ?? ($_SESSION['admin_name'] ?? 'OpenFlow'))?>"></div>
          <div class="field"><label>作者类型</label><select name="author_type"><option value="official" <?=($s['author_type']??'official')==='official'?'selected':''?>>官方 PGC</option><option value="user" <?=($s['author_type']??'')==='user'?'selected':''?>>用户 UGC</option><option value="developer" <?=($s['author_type']??'')==='developer'?'selected':''?>>开发者</option></select></div>
          <div class="field"><label>版本</label><input type="text" name="version" value="<?=htmlspecialchars($s['version'] ?? '1.0.0')?>" style="width:90px"></div>
        </div>
        <div class="field"><label>描述</label><textarea name="description" rows="2" placeholder="这个技能做什么？"><?=htmlspecialchars($s['description'] ?? '')?></textarea></div>
        <div class="field"><label>标签 <span class="hint">· 逗号分隔</span></label><input type="text" name="tags" value="<?=htmlspecialchars(implode(',', $s['tags'] ?? []))?>" placeholder="SEO, 写作, 营销"></div>

        <div id="skillContentField" class="field"><label>🤖 提示词内容 <span class="hint">· 用 {变量} 做占位符</span></label><textarea name="content" rows="6" placeholder="你是一个 SEO 专家，请为以下主题生成标题：&#10;主题：{topic}"><?=htmlspecialchars($s['content'] ?? '')?></textarea></div>
        <div id="skillStepsField" class="field" style="display:none"><label>🔄 工作流步骤 <span class="hint">· 每行一步</span></label><textarea name="workflow_steps" rows="4"><?=htmlspecialchars(implode("\n", array_map(fn($st) => $st['title'] ?? '', $s['steps'] ?? [])))?></textarea></div>

        <div class="field-row">
          <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="status" value="published" <?=($s['status'] ?? 'published')==='published'?'checked':''?> style="width:16px;height:16px"> 发布到市场</label></div>
        </div>
        <button type="submit" class="btn btn-primary">保存技能</button>
      </form>
    </div>
    <script>
    function toggleSkillType(t) { document.getElementById('skillContentField').style.display = t==='workflow'?'none':'block'; document.getElementById('skillStepsField').style.display = t==='workflow'?'block':'none'; }
    function aiGenerateSkill() {
      var desc = document.getElementById('aiDesc').value.trim();
      var msg = document.getElementById('aiMsg');
      if (!desc) { msg.innerHTML = '<span style="color:var(--danger)">请先描述你想创建的能力</span>'; return; }
      var btn = document.getElementById('aiGenBtn');
      btn.disabled = true; btn.textContent = '⏳ 生成中…';
      msg.innerHTML = '<span style="color:#6b6580">AI 正在设计技能，请稍候…</span>';
      var body = new FormData(); body.append('description', desc);
      fetch('/api/marketplace.php?action=ai_generate', { method: 'POST', body: body })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d.ok) { msg.innerHTML = '<span style="color:var(--danger)">😅 ' + (d.error || '生成失败') + '</span>'; btn.disabled = false; btn.textContent = '✨ 生成'; return; }
          var s = d.skill;
          var form = document.querySelector('form[method="post"]');
          var nameInput = form.querySelector('input[name="title"]');
          nameInput.value = s.title || '';
          form.querySelector('select[name="type"]').value = s.type || 'prompt';
          toggleSkillType(s.type || 'prompt');
          form.querySelector('input[name="icon"]').value = s.icon || '⚡';
          form.querySelector('textarea[name="description"]').value = s.description || '';
          form.querySelector('input[name="tags"]').value = (s.tags || []).join(',');
          form.querySelector('textarea[name="content"]').value = s.content || '';
          if (s.type === 'workflow') {
            form.querySelector('textarea[name="workflow_steps"]').value = (s.steps || []).map(function(x){ return x.title || ''; }).join('\n');
          }
          msg.innerHTML = '<span style="color:var(--ok)">✅ 已生成！请检查并保存</span>';
          btn.disabled = false; btn.textContent = '✨ 生成';
        }).catch(function(){ msg.innerHTML = '<span style="color:var(--danger)">网络异常</span>'; btn.disabled = false; btn.textContent = '✨ 生成'; });
    }
    function aiGeneratePlugin() {
      var desc = document.getElementById('plgDesc').value.trim();
      var msg = document.getElementById('plgMsg');
      if (!desc) { msg.innerHTML = '<span style="color:var(--danger)">请先描述插件功能</span>'; return; }
      var btn = document.getElementById('plgBtn');
      btn.disabled = true; btn.textContent = '⏳ 生成中…';
      msg.innerHTML = '<span style="color:#6b6580">AI 正在编写插件代码，请稍候…</span>';
      var body = new FormData(); body.append('description', desc);
      fetch('/api/marketplace.php?action=ai_plugin', { method: 'POST', body: body })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d.ok) { msg.innerHTML = '<span style="color:var(--danger)">😅 ' + (d.error || '生成失败') + '</span>'; btn.disabled = false; btn.textContent = '✨ 生成插件'; return; }
          msg.innerHTML = '<span style="color:var(--ok)">✅ 插件已生成：' + (d.plugin_id || '') + '，请到「插件管理」启用</span>';
          btn.disabled = false; btn.textContent = '✨ 生成插件';
        }).catch(function(){ msg.innerHTML = '<span style="color:var(--danger)">网络异常</span>'; btn.disabled = false; btn.textContent = '✨ 生成插件'; });
    }
    </script>

    <?php elseif ($tabs === 'remote'): ?>
    <div class="card" style="max-width:640px">
      <h2>🌍 远程市场</h2>
      <p class="text-sm text-muted mb-4">配置远程 marketplace.json 地址，一键同步外部生态的技能资产（标记为 🌍 远程）。</p>
      <form method="post" class="mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="save_remote" value="1">
        <div class="field"><label>远程市场 URL <span class="hint">· marketplace.json</span></label><input type="url" name="remote_url" value="<?=htmlspecialchars($remoteUrl)?>" placeholder="https://your-domain/marketplace.json"></div>
        <button type="submit" class="btn btn-primary">保存配置</button>
      </form>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="sync_remote" value="1">
        <button type="submit" class="btn btn-ghost">🔄 立即同步</button>
      </form>
      <div class="text-sm text-muted mt-4">远程 JSON 格式：<code style="background:var(--surface-2);padding:2px 6px;border-radius:6px">{"skills": [{"id":"x","title":"...","type":"prompt","content":"..."}]}</code></div>
    </div>

    <?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📦 全部资产（前台市场可见）</h2>
      <table>
        <thead><tr><th>资产</th><th>类型</th><th>作者</th><th>安装</th><th>评分</th><th>前台入口</th></tr></thead>
        <tbody>
          <?php foreach (mkt_assets() as $a): ?>
          <tr>
            <td><span style="font-size:18px"><?=htmlspecialchars($a['icon'])?></span> <strong><?=htmlspecialchars($a['title'])?></strong></td>
            <td><span class="badge" style="background:var(--surface-2);padding:2px 8px;border-radius:999px;font-size:11px"><?=htmlspecialchars($a['type'])?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($a['author'] ?? '')?></td>
            <td><?=$a['installs'] ?? 0?></td>
            <td><?=($a['rating_count'] ?? 0) > 0 ? number_format((float)$a['rating'], 1) . ' ⭐' : '—'?></td>
            <td><a href="<?=htmlspecialchars($a['url'])?>" target="_blank" class="btn btn-ghost btn-sm">查看</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
