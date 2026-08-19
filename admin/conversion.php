<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$convFile = DATA_DIR . '/conversion.json';
$conv = json_read($convFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $tab = $_POST['tab'] ?? 'top_bar';
    if ($tab === 'top_bar') {
        $conv['top_bar'] = [
            'enabled' => isset($_POST['enabled']),
            'text' => $_POST['text'] ?? '',
            'link' => $_POST['link'] ?? '',
            'bg_color' => $_POST['bg_color'] ?? '#000000',
            'text_color' => $_POST['text_color'] ?? '#ffffff',
            'dismissible' => isset($_POST['dismissible']),
        ];
    }
    if ($tab === 'bottom_cta') {
        $conv['bottom_cta'] = [
            'enabled' => isset($_POST['enabled']),
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'button_text' => $_POST['button_text'] ?? '',
            'button_url' => $_POST['button_url'] ?? '',
            'bg_color' => $_POST['bg_color'] ?? '#1e1e1e',
            'text_color' => $_POST['text_color'] ?? '#ffffff',
        ];
    }
    if ($tab === 'popup') {
        $conv['popup'] = [
            'enabled' => isset($_POST['enabled']),
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'position' => $_POST['position'] ?? 'center',
            'trigger' => $_POST['trigger'] ?? 'time',
            'trigger_delay' => (int)($_POST['trigger_delay'] ?? 10),
            'frequency' => $_POST['frequency'] ?? 'once_per_session',
            'page_scope' => $_POST['page_scope'] ?? 'all',
            'page_paths' => $_POST['page_paths'] ?? '',
            'form_slug' => $_POST['form_slug'] ?? '',
            'image' => $_POST['image'] ?? '',
            'width' => $_POST['width'] ?? '500px',
            'bg_color' => $_POST['bg_color'] ?? '#ffffff',
            // A/B 变体：B 变体标题/内容/按钮
            'ab_enabled' => isset($_POST['ab_enabled']),
            'ab_variant_b' => [
                'title' => $_POST['ab_title'] ?? '',
                'content' => $_POST['ab_content'] ?? '',
                'button_text' => $_POST['ab_button_text'] ?? '',
                'button_url' => $_POST['ab_button_url'] ?? '',
                'image' => $_POST['ab_image'] ?? '',
            ],
        ];
    }
    if ($tab === 'inline_cta') {
        $conv['inline_cta'] = [
            'enabled' => isset($_POST['enabled']),
            'default_template' => $_POST['default_template'] ?? '',
            'default_title' => $_POST['default_title'] ?? '',
            'default_description' => $_POST['default_description'] ?? '',
            'default_button' => $_POST['default_button'] ?? '',
            'default_url' => $_POST['default_url'] ?? '',
        ];
    }
    json_write($convFile, $conv);
    $message = '配置已保存';
    $conv = json_read($convFile);
}

$forms = json_read(DATA_DIR . '/forms/index.json');
$activeTab = $_GET['tab'] ?? 'top_bar';

admin_header('转化组件');
?>
<style>
.conv-tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:24px}
.conv-tabs a{padding:10px 18px;font-size:14px;font-weight:500;color:var(--text-2);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px}
.conv-tabs a:hover{color:var(--text)}
.conv-tabs a.active{color:var(--text);border-bottom-color:var(--accent);font-weight:600}
.preview-box{border:2px dashed var(--border-2);border-radius:12px;padding:24px;text-align:center;margin-top:16px;background:var(--surface)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('conversion'); ?>
  <div class="main">
    <h1>在线转化组件</h1>
    <p class="sub">全局通知条 · 底部 CTA · 弹窗 · 文中 CTA · 位置/时机/频次/页面范围策略</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="conv-tabs">
      <a href="?tab=top_bar" class="<?=$activeTab==='top_bar'?'active':''?>">📢 顶部通知条</a>
      <a href="?tab=bottom_cta" class="<?=$activeTab==='bottom_cta'?'active':''?>">⬇️ 底部 CTA</a>
      <a href="?tab=popup" class="<?=$activeTab==='popup'?'active':''?>">💬 弹窗</a>
      <a href="?tab=inline_cta" class="<?=$activeTab==='inline_cta'?'active':''?>">📝 文中 CTA</a>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save" value="1">
      <input type="hidden" name="tab" value="<?=$activeTab?>">

      <?php if ($activeTab === 'top_bar'): $c = $conv['top_bar']; ?>
      <div class="card">
        <h2>📢 全局顶部通知条</h2>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=$c['enabled']?'checked':''?> style="width:18px;height:18px">启用顶部通知条</label></div>
        <div class="field-row">
          <div class="field"><label>通知文字</label><input type="text" name="text" value="<?=htmlspecialchars($c['text'])?>" placeholder="🎯 限时免费获取报告"></div>
          <div class="field"><label>链接</label><input type="text" name="link" value="<?=htmlspecialchars($c['link'])?>" placeholder="/landing-page"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>背景色</label><input type="text" name="bg_color" value="<?=htmlspecialchars($c['bg_color'])?>"></div>
          <div class="field"><label>文字色</label><input type="text" name="text_color" value="<?=htmlspecialchars($c['text_color'])?>"></div>
        </div>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="dismissible" value="1" <?=$c['dismissible']?'checked':''?> style="width:18px;height:18px">可关闭</label></div>
        <div class="preview-box" style="background:<?=htmlspecialchars($c['bg_color'])?>;color:<?=htmlspecialchars($c['text_color'])?>"><?=htmlspecialchars($c['text'])?> <?=$c['dismissible']?'[✕]':''?></div>
      </div>
      <?php endif; ?>

      <?php if ($activeTab === 'bottom_cta'): $c = $conv['bottom_cta']; ?>
      <div class="card">
        <h2>⬇️ 全局底部 CTA</h2>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=$c['enabled']?'checked':''?> style="width:18px;height:18px">启用底部 CTA</label></div>
        <div class="field-row">
          <div class="field"><label>标题</label><input type="text" name="title" value="<?=htmlspecialchars($c['title'])?>"></div>
          <div class="field"><label>描述</label><input type="text" name="description" value="<?=htmlspecialchars($c['description'])?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>按钮文字</label><input type="text" name="button_text" value="<?=htmlspecialchars($c['button_text'])?>"></div>
          <div class="field"><label>按钮链接</label><input type="text" name="button_url" value="<?=htmlspecialchars($c['button_url'])?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>背景色</label><input type="text" name="bg_color" value="<?=htmlspecialchars($c['bg_color'])?>"></div>
          <div class="field"><label>文字色</label><input type="text" name="text_color" value="<?=htmlspecialchars($c['text_color'])?>"></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($activeTab === 'popup'): $c = $conv['popup']; ?>
      <div class="card">
        <h2>💬 弹窗</h2>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=$c['enabled']?'checked':''?> style="width:18px;height:18px">启用弹窗</label></div>
        <div class="field"><label>标题</label><input type="text" name="title" value="<?=htmlspecialchars($c['title'])?>"></div>
        <div class="field"><label>内容 (HTML)</label><textarea name="content" rows="3" style="font-family:var(--mono)"><?=htmlspecialchars($c['content'])?></textarea></div>
        <div class="field-row">
          <div class="field"><label>弹出位置</label><select name="position"><?php foreach ($conv['popup_positions'] ?? ['center'] as $pos): ?><option value="<?=$pos?>" <?=$c['position']===$pos?'selected':''?>><?=htmlspecialchars($pos)?></option><?php endforeach; ?></select></div>
          <div class="field"><label>触发方式</label><select name="trigger"><?php foreach ($conv['popup_triggers'] ?? ['time'] as $tr): ?><option value="<?=$tr?>" <?=$c['trigger']===$tr?'selected':''?>><?=htmlspecialchars($tr)?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>触发延迟 (秒)</label><input type="number" name="trigger_delay" value="<?=htmlspecialchars($c['trigger_delay'] ?? 10)?>" min="0" max="300"></div>
          <div class="field"><label>弹出频次</label><select name="frequency"><?php foreach ($conv['popup_frequencies'] ?? ['once_per_session'] as $freq): ?><option value="<?=$freq?>" <?=$c['frequency']===$freq?'selected':''?>><?=htmlspecialchars($freq)?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>页面范围</label><select name="page_scope" onchange="document.getElementById('pagePaths').style.display=this.value==='specific'?'block':'none'"><option value="all" <?=$c['page_scope']==='all'?'selected':''?>>全部页面</option><option value="home" <?=$c['page_scope']==='home'?'selected':''?>>仅首页</option><option value="article" <?=$c['page_scope']==='article'?'selected':''?>>仅文章页</option><option value="specific" <?=$c['page_scope']==='specific'?'selected':''?>>指定路径</option></select></div>
          <div class="field"><label>宽度</label><input type="text" name="width" value="<?=htmlspecialchars($c['width'])?>" placeholder="500px"></div>
        </div>
        <div class="field" id="pagePaths" style="display:<?=$c['page_scope']==='specific'?'block':'none'?>"><label>指定路径 <span class="hint">一行一个</span></label><textarea name="page_paths" rows="2"><?=htmlspecialchars($c['page_paths'])?></textarea></div>
        <div class="field-row">
          <div class="field"><label>关联表单 <span class="hint">弹窗内嵌表单</span></label><select name="form_slug"><option value="">无</option><?php foreach ($forms as $f): ?><option value="<?=htmlspecialchars($f['slug'])?>" <?=$c['form_slug']===$f['slug']?'selected':''?>><?=htmlspecialchars($f['title'])?></option><?php endforeach; ?></select></div>
          <div class="field"><label>背景图</label><input type="text" name="image" value="<?=htmlspecialchars($c['image'])?>" placeholder="图片路径"></div>
        </div>
        <div style="margin-top:14px;padding-top:14px;border-top:1px dashed var(--border)">
          <label style="display:flex;gap:8px;align-items:center;font-size:13px;font-weight:700;margin-bottom:8px"><input type="checkbox" name="ab_enabled" value="1" <?=!empty($c['ab_enabled'])?'checked':''?> style="width:15px;height:15px"> 🧪 启用弹窗 A/B 测试（B 变体）</label>
          <p style="font-size:11px;color:var(--muted);margin-bottom:10px">50% 流量看到 A（上方配置），50% 看到 B。通过 fcTrackAB 上报转化，在 A/B 测试统计查看。</p>
          <?php $ab = $c['ab_variant_b'] ?? []; ?>
          <div class="field-row">
            <div class="field"><label>B 标题</label><input type="text" name="ab_title" value="<?=htmlspecialchars($ab['title'] ?? '')?>" placeholder="B 变体标题"></div>
            <div class="field"><label>B 内容 (HTML)</label><input type="text" name="ab_content" value="<?=htmlspecialchars($ab['content'] ?? '')?>" placeholder="B 变体内容"></div>
          </div>
          <div class="field-row">
            <div class="field"><label>B 按钮文字</label><input type="text" name="ab_button_text" value="<?=htmlspecialchars($ab['button_text'] ?? '')?>" placeholder="如：立即领取"></div>
            <div class="field"><label>B 按钮链接</label><input type="text" name="ab_button_url" value="<?=htmlspecialchars($ab['button_url'] ?? '')?>" placeholder="https://…"></div>
            <div class="field"><label>B 背景图</label><input type="text" name="ab_image" value="<?=htmlspecialchars($ab['image'] ?? '')?>" placeholder="图片路径"></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($activeTab === 'inline_cta'): $c = $conv['inline_cta']; ?>
      <div class="card">
        <h2>📝 文中 CTA 组件</h2>
        <p class="text-sm text-muted mb-4">提供默认模板，文章编辑器中可插入使用。支持变量替换。</p>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=$c['enabled']?'checked':''?> style="width:18px;height:18px">启用文中 CTA</label></div>
        <div class="field-row">
          <div class="field"><label>默认标题</label><input type="text" name="default_title" value="<?=htmlspecialchars($c['default_title'])?>"></div>
          <div class="field"><label>默认描述</label><input type="text" name="default_description" value="<?=htmlspecialchars($c['default_description'])?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>默认按钮文字</label><input type="text" name="default_button" value="<?=htmlspecialchars($c['default_button'])?>"></div>
          <div class="field"><label>默认链接</label><input type="text" name="default_url" value="<?=htmlspecialchars($c['default_url'])?>"></div>
        </div>
        <div class="field"><label>HTML 模板 <span class="hint">支持 {{title}} {{description}} {{button}} {{url}}</span></label>
          <textarea name="default_template" rows="6" style="font-family:var(--mono);font-size:13px"><?=htmlspecialchars($c['default_template'])?></textarea>
        </div>
        <div class="preview-box">
          <?php
          $preview = str_replace(['{{title}}','{{description}}','{{button}}','{{url}}'],
              [$c['default_title'] ?? '标题', $c['default_description'] ?? '描述', $c['default_button'] ?? '按钮', $c['default_url'] ?? '#'],
              $c['default_template'] ?? '');
          echo $preview;
          ?>
        </div>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">保存配置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
