<?php
/**
 * 站内营销投放 —— 统一后台
 *
 * 把通知条 / 弹窗 / 内嵌模块收进一个入口：一种类型可有任意多条，
 * 每条都能定 页面 × 位置 × 人群 × 定时 × 频次，并看展示/点击/关闭。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/PromoSystem.php';
require_login();
require_perm('promos');

$message = ''; $error = '';
$types = promo_types();
$slots = promo_slots();

if (($_POST['action'] ?? '') === 'save') {
    $r = promo_save([
        'id'=>$_POST['id'] ?? '', 'name'=>$_POST['name'] ?? '', 'type'=>$_POST['type'] ?? 'bar',
        'enabled'=>!empty($_POST['enabled']), 'priority'=>$_POST['priority'] ?? 0,
        'title'=>$_POST['title'] ?? '', 'body'=>$_POST['body'] ?? '', 'image'=>$_POST['image'] ?? '',
        'cta_text'=>$_POST['cta_text'] ?? '', 'cta_link'=>$_POST['cta_link'] ?? '', 'color'=>$_POST['color'] ?? '',
        'dismissible'=>!empty($_POST['dismissible']),
        'position'=>$_POST['position'] ?? '', 'trigger'=>$_POST['trigger'] ?? 'immediate',
        'trigger_delay'=>$_POST['trigger_delay'] ?? 5, 'scroll_pct'=>$_POST['scroll_pct'] ?? 50, 'slot'=>$_POST['slot'] ?? 'article_top',
        'page_mode'=>$_POST['page_mode'] ?? 'all',
        'page_paths'=>array_filter(array_map('trim', explode("\n", $_POST['page_paths'] ?? ''))),
        'page_types'=>array_filter(array_map('trim', explode(',', $_POST['page_types'] ?? ''))),
        'aud_login'=>$_POST['aud_login'] ?? 'any', 'aud_visitor'=>$_POST['aud_visitor'] ?? 'any',
        'aud_segment'=>$_POST['aud_segment'] ?? '', 'aud_utm'=>$_POST['aud_utm'] ?? '',
        'start'=>$_POST['start'] ?? '', 'end'=>$_POST['end'] ?? '', 'frequency'=>$_POST['frequency'] ?? 'session',
        'created_at'=>$_POST['created_at'] ?? '',
    ]);
    if ($r['ok']) { $message = "投放「{$r['promo']['name']}」已保存。"; audit('保存投放 ' . $r['promo']['name'], 'content'); }
    else $error = $r['error'];
}
if (($_POST['action'] ?? '') === 'toggle') {
    $p = promo_get($_POST['id'] ?? '');
    if ($p) { $p['enabled'] = empty($p['enabled']); promo_save($p); $message = $p['enabled'] ? '已启用。' : '已停用。'; }
}
if (isset($_GET['delete'])) { promo_delete((string)$_GET['delete']); audit('删除投放 ' . $_GET['delete'], 'content'); $message = '投放已删除。'; }

$promos = promo_all();
usort($promos, fn($a, $b) => ((int)($b['priority'] ?? 0)) <=> ((int)($a['priority'] ?? 0)));
$editId = trim($_GET['edit'] ?? '');
$e = $editId !== '' ? promo_get($editId) : null;

$pageModes = ['all'=>'全部页面','include'=>'仅这些页面','exclude'=>'除这些页面','type'=>'按页面类型'];
$audLogin  = ['any'=>'不限','in'=>'已登录','out'=>'未登录'];
$audVisitor= ['any'=>'不限','new'=>'新访客','return'=>'老访客'];
$freqs     = ['always'=>'每次都展示','session'=>'每会话一次','daily'=>'每天一次','once'=>'仅一次'];
$positions = ['bar'=>['top'=>'页面顶部','bottom'=>'页面底部'], 'popup'=>['center'=>'居中','corner'=>'右下角']];
$triggers  = ['immediate'=>'立即','delay'=>'延时','scroll'=>'滚动到','exit'=>'离开意图'];

admin_header('站内营销投放');
?>
<div class="admin-layout">
  <?php admin_sidebar('promos'); ?>
  <div class="main">
    <h1>站内营销投放</h1>
    <p class="sub">通知条 / 弹窗 / 内嵌模块统一在这里投放：定 页面 × 位置 × 人群 × 定时 × 频次。这是站内营销，与 MA 的触发流程不同。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="card" style="padding:0;overflow-x:auto">
      <table class="table">
        <thead><tr><th>名称</th><th>类型</th><th>位置</th><th>页面</th><th>人群</th><th>展示/点击</th><th>状态</th><th style="width:1%">操作</th></tr></thead>
        <tbody>
          <?php if (!$promos): ?><tr><td colspan="8" class="empty">还没有投放，下面新建一个</td></tr><?php endif; ?>
          <?php foreach ($promos as $p):
            $imp = (int)($p['impressions'] ?? 0); $clk = (int)($p['clicks'] ?? 0);
            $ctr = $imp > 0 ? round($clk / $imp * 100, 1) : 0;
            $pos = $p['type'] === 'inline' ? ($slots[$p['slot'] ?? ''] ?? '') : (($positions[$p['type']] ?? [])[$p['position'] ?? ''] ?? ($p['position'] ?? ''));
            $pageDesc = ['all'=>'全部','include'=>'指定页','exclude'=>'排除页','type'=>'按类型'][$p['page_mode'] ?? 'all'] ?? '全部';
            $audBits = [];
            if (($p['aud_login'] ?? 'any') !== 'any') $audBits[] = $audLogin[$p['aud_login']];
            if (($p['aud_visitor'] ?? 'any') !== 'any') $audBits[] = $audVisitor[$p['aud_visitor']];
            if (!empty($p['aud_segment'])) $audBits[] = '分群';
            if (!empty($p['aud_utm'])) $audBits[] = 'UTM';
          ?>
            <tr>
              <td><a href="?edit=<?=urlencode($p['id'])?>" style="color:var(--accent);text-decoration:none"><?=htmlspecialchars($p['name'])?></a><?php if (!empty($p['start'])||!empty($p['end'])): ?><div class="sub" style="font-size:11px">⏰ <?=htmlspecialchars(($p['start']?:'…').' ~ '.($p['end']?:'…'))?></div><?php endif; ?></td>
              <td><span class="badge"><?=htmlspecialchars($types[$p['type']] ?? $p['type'])?></span></td>
              <td class="sub" style="font-size:12px"><?=htmlspecialchars($pos)?></td>
              <td class="sub" style="font-size:12px"><?=htmlspecialchars($pageDesc)?></td>
              <td class="sub" style="font-size:12px"><?=$audBits ? htmlspecialchars(implode('·', $audBits)) : '不限'?></td>
              <td class="sub" style="font-size:12px"><?=$imp?> / <?=$clk?> <?php if ($ctr): ?><span style="color:var(--ok)">(<?=$ctr?>%)</span><?php endif; ?></td>
              <td>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>">
                  <button class="badge <?=!empty($p['enabled'])?'ok':''?>" style="border:none;cursor:pointer"><?=!empty($p['enabled'])?'启用中':'已停用'?></button></form>
              </td>
              <td style="white-space:nowrap">
                <a href="?edit=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
                <a href="?delete=<?=urlencode($p['id'])?>" class="btn btn-danger btn-sm" data-confirm="删除?">删</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h2 style="margin-top:26px"><?= $e ? '编辑投放：' . htmlspecialchars($e['name']) : '新建投放' ?></h2>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?=htmlspecialchars($e['id'] ?? '')?>">
      <input type="hidden" name="created_at" value="<?=htmlspecialchars($e['created_at'] ?? '')?>">

      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="flex:2;min-width:180px"><label>名称</label><input type="text" name="name" value="<?=htmlspecialchars($e['name'] ?? '')?>" required></div>
        <div class="field" style="min-width:130px"><label>类型</label>
          <select name="type" id="ptype" onchange="ptypeChange()">
            <?php foreach ($types as $k=>$v): ?><option value="<?=$k?>" <?=($e['type'] ?? 'bar')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="width:90px"><label>优先级</label><input type="number" name="priority" value="<?=(int)($e['priority'] ?? 0)?>"></div>
        <label style="display:flex;gap:6px;align-items:center;padding-bottom:10px"><input type="checkbox" name="enabled" value="1" <?=!empty($e['enabled'])?'checked':''?>> 启用</label>
      </div>

      <fieldset style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:12px">
        <legend style="padding:0 6px;font-size:13px;color:var(--muted)">内容</legend>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="flex:2;min-width:200px"><label>标题 / 文案</label><input type="text" name="title" value="<?=htmlspecialchars($e['title'] ?? '')?>"></div>
          <div class="field" style="flex:1;min-width:120px"><label>按钮文字</label><input type="text" name="cta_text" value="<?=htmlspecialchars($e['cta_text'] ?? '')?>" placeholder="立即领取"></div>
          <div class="field" style="flex:1;min-width:160px"><label>按钮链接</label><input type="text" name="cta_link" value="<?=htmlspecialchars($e['cta_link'] ?? '')?>" placeholder="/lp/xxx"></div>
        </div>
        <div class="pf" data-for="popup,inline"><div class="field"><label>正文</label><textarea name="body" rows="2"><?=htmlspecialchars($e['body'] ?? '')?></textarea></div></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field pf" data-for="popup,inline" style="flex:2;min-width:180px"><label>配图 URL</label><input type="text" name="image" value="<?=htmlspecialchars($e['image'] ?? '')?>"></div>
          <div class="field pf" data-for="bar" style="min-width:120px"><label>背景色</label><input type="text" name="color" value="<?=htmlspecialchars($e['color'] ?? '')?>" placeholder="#1e1e1e"></div>
          <label style="display:flex;gap:6px;align-items:center;padding-bottom:10px"><input type="checkbox" name="dismissible" value="1" <?=(!isset($e['dismissible'])||!empty($e['dismissible']))?'checked':''?>> 可关闭</label>
        </div>
      </fieldset>

      <fieldset style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:12px">
        <legend style="padding:0 6px;font-size:13px;color:var(--muted)">位置</legend>
        <div class="pf" data-for="bar,popup" style="display:inline-block;margin-right:16px">
          <div class="field" style="min-width:140px"><label>位置</label>
            <select name="position">
              <?php foreach (array_merge($positions['bar'], $positions['popup']) as $k=>$v): ?><option value="<?=$k?>" <?=($e['position'] ?? '')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="pf" data-for="popup" style="display:inline-flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="min-width:130px"><label>触发</label><select name="trigger"><?php foreach ($triggers as $k=>$v): ?><option value="<?=$k?>" <?=($e['trigger'] ?? 'immediate')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
          <div class="field" style="width:110px"><label>延时(秒)</label><input type="number" name="trigger_delay" value="<?=(int)($e['trigger_delay'] ?? 5)?>"></div>
          <div class="field" style="width:120px"><label>滚动到(%)</label><input type="number" name="scroll_pct" value="<?=(int)($e['scroll_pct'] ?? 50)?>"></div>
        </div>
        <div class="pf" data-for="inline">
          <div class="field" style="min-width:160px"><label>版位</label><select name="slot"><?php foreach ($slots as $k=>$v): ?><option value="<?=$k?>" <?=($e['slot'] ?? 'article_top')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
        </div>
      </fieldset>

      <fieldset style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:12px">
        <legend style="padding:0 6px;font-size:13px;color:var(--muted)">页面定向 × 人群 × 定时</legend>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="min-width:150px"><label>页面范围</label><select name="page_mode"><?php foreach ($pageModes as $k=>$v): ?><option value="<?=$k?>" <?=($e['page_mode'] ?? 'all')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
          <div class="field" style="flex:1;min-width:200px"><label>页面路径 <span class="hint">每行一个，支持 /article/* 通配</span></label><textarea name="page_paths" rows="2" placeholder="/&#10;/article/*"><?=htmlspecialchars(implode("\n", (array)($e['page_paths'] ?? [])))?></textarea></div>
          <div class="field" style="min-width:140px"><label>页面类型 <span class="hint">逗号分隔</span></label><input type="text" name="page_types" value="<?=htmlspecialchars(implode(',', (array)($e['page_types'] ?? [])))?>" placeholder="article,landing"></div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="min-width:120px"><label>登录态</label><select name="aud_login"><?php foreach ($audLogin as $k=>$v): ?><option value="<?=$k?>" <?=($e['aud_login'] ?? 'any')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
          <div class="field" style="min-width:120px"><label>访客</label><select name="aud_visitor"><?php foreach ($audVisitor as $k=>$v): ?><option value="<?=$k?>" <?=($e['aud_visitor'] ?? 'any')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
          <div class="field" style="min-width:120px"><label>CDP 分群 ID <span class="hint">选填</span></label><input type="text" name="aud_segment" value="<?=htmlspecialchars($e['aud_segment'] ?? '')?>"></div>
          <div class="field" style="min-width:120px"><label>UTM 来源 <span class="hint">选填</span></label><input type="text" name="aud_utm" value="<?=htmlspecialchars($e['aud_utm'] ?? '')?>" placeholder="weibo"></div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="field" style="min-width:170px"><label>开始</label><input type="datetime-local" name="start" value="<?=htmlspecialchars(str_replace(' ', 'T', $e['start'] ?? ''))?>"></div>
          <div class="field" style="min-width:170px"><label>结束</label><input type="datetime-local" name="end" value="<?=htmlspecialchars(str_replace(' ', 'T', $e['end'] ?? ''))?>"></div>
          <div class="field" style="min-width:150px"><label>频次</label><select name="frequency"><?php foreach ($freqs as $k=>$v): ?><option value="<?=$k?>" <?=($e['frequency'] ?? 'session')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
        </div>
      </fieldset>

      <div style="display:flex;gap:8px">
        <button class="btn btn-primary"><?= $e ? '保存' : '创建' ?></button>
        <?php if ($e): ?><a href="/xmp/promos" class="btn btn-ghost">取消</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<script>
function ptypeChange() {
  var t = document.getElementById('ptype').value;
  document.querySelectorAll('.pf').forEach(function(el){
    var forT = (el.getAttribute('data-for') || '').split(',');
    el.style.display = forT.indexOf(t) >= 0 ? '' : 'none';
  });
}
ptypeChange();
</script>
<?php admin_footer(); ?>
