<?php
/**
 * 标签管理 —— CDP 自动打标规则（P0：让「标签」从手打散字符串变成规则引擎驱动）
 *
 * 维护 data/cdp/tag_rules.json：每条规则 = 打什么标签(tag) + 什么条件下触发(when)。
 * 命中后 CdpSystem::autoTag 自动给访问者打上标签（内部存 {tag: {type:'auto', rule_id, at}}）。
 * 条件类型：event(事件)/summary(行为摘要)/lifecycle(生命周期)/property(属性)。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CdpDefaults.php';
require_login();
require_perm('segments');

function tag_rules_file(): string { return DATA_DIR . '/cdp/tag_rules.json'; }
function tag_rules_read(): array {
    $r = json_read(tag_rules_file());
    if (empty($r) && function_exists('cdp_default_tag_rules')) return cdp_default_tag_rules();
    return is_array($r) ? $r : [];
}
function tag_rules_names(): array { return ['event'=>'事件','summary'=>'行为摘要','lifecycle'=>'生命周期','property'=>'属性']; }

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $rules = tag_rules_read();
    if ($act === 'save') {
        $rid = trim((string)($_POST['id'] ?? ''));
        if ($rid === '') $rid = 'rule_' . bin2hex(random_bytes(4));
        $tag = trim((string)($_POST['tag'] ?? ''));
        $type = (string)($_POST['when_type'] ?? 'event');
        if ($tag === '' || !in_array($type, ['event','summary','lifecycle','property'], true)) {
            $err = '标签名必填，条件类型不合法'; goto render;
        }
        $when = ['type' => $type, 'operator' => $_POST['when_op'] ?? 'gte', 'value' => $_POST['when_value'] ?? '1'];
        if ($type === 'event') $when['event'] = trim((string)($_POST['when_event'] ?? ''));
        if ($type === 'summary' || $type === 'property') $when['field'] = trim((string)($_POST['when_field'] ?? ''));
        $rules[$rid] = ['enabled' => isset($_POST['enabled']), 'tag' => $tag, 'when' => $when,
            'description' => trim((string)($_POST['description'] ?? ''))];
        json_write(tag_rules_file(), $rules);
        audit('保存标签规则 ' . $tag, 'cdp');
        header('Location: /xmp/tag-rules?ok=1'); exit;
    } elseif ($act === 'toggle') {
        $rid = (string)($_POST['id'] ?? '');
        if (isset($rules[$rid])) { $rules[$rid]['enabled'] = !empty($rules[$rid]['enabled']) ? false : true; json_write(tag_rules_file(), $rules); }
        header('Location: /xmp/tag-rules'); exit;
    } elseif ($act === 'delete') {
        $rid = (string)($_POST['id'] ?? '');
        unset($rules[$rid]); json_write(tag_rules_file(), $rules);
        audit('删除标签规则 ' . $rid, 'cdp');
        header('Location: /xmp/tag-rules'); exit;
    }
}
render:
$rules = tag_rules_read();
$editId = trim((string)($_GET['edit'] ?? ''));
$edit = $editId !== '' ? ($rules[$editId] ?? null) : null;
$typeNames = tag_rules_names();

admin_header('标签管理');
?>
<div style="max-width:960px">
  <h1 style="margin:0 0 4px">🏷️ 标签管理</h1>
  <p class="v-sub" style="margin:0 0 16px">配置自动打标规则——访问者满足条件时，系统自动给他打标签。命中一次即可用「标签」建分群、做定向。</p>
  <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <?php if (isset($_GET['ok'])): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a">标签规则已保存。</div><?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:320px">
      <div style="font-weight:700;margin-bottom:8px">自动打标规则（<?=count($rules)?>）</div>
      <?php if (!$rules): ?><div class="v-sub" style="font-size:13px">还没有规则。右侧新建一个。</div><?php endif; ?>
      <?php foreach ($rules as $rid => $r): ?>
      <div class="card" style="padding:12px 14px;margin-bottom:8px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <div>
            <strong><?=htmlspecialchars($r['tag'] ?? $rid)?></strong>
            <span style="font-size:11px;padding:1px 6px;border-radius:999px;background:<?=!empty($r['enabled'])?'#dcfce7':'#f1f5f9'?>;color:<?=!empty($r['enabled'])?'#166534':'#64748b'?>"><?=!empty($r['enabled'])?'启用':'停用'?></span>
            <span style="font-size:12px;color:var(--faint)"><?=htmlspecialchars($typeNames[$r['when']['type'] ?? 'event'] ?? $r['when']['type'] ?? '')?> </span>
          </div>
          <div style="display:flex;gap:6px">
            <a href="/xmp/tag-rules?edit=<?=urlencode($rid)?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=htmlspecialchars($rid)?>"><button class="btn btn-ghost btn-sm"><?=!empty($r['enabled'])?'停用':'启用'?></button></form>
            <form method="post" data-confirm="删除规则?" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($rid)?>"><button class="btn btn-ghost btn-sm" style="color:#dc2626">删除</button></form>
          </div>
        </div>
        <div style="font-size:12px;color:var(--faint);margin-top:4px">
          <?php $w = $r['when'] ?? []; $td = ($w['type']??'');
          if ($td === 'event') echo '触发事件：' . htmlspecialchars($w['event'] ?? '') . '（' . htmlspecialchars($w['operator'] ?? 'gte') . ' ' . htmlspecialchars($w['value'] ?? '1') . ' 次）';
          elseif ($td === 'summary' || $td === 'property') echo '字段：' . htmlspecialchars($w['field'] ?? '') . ' ' . htmlspecialchars($w['operator'] ?? '') . ' ' . htmlspecialchars($w['value'] ?? '');
          elseif ($td === 'lifecycle') echo '生命周期 ' . htmlspecialchars($w['operator'] ?? 'eq') . ' ' . htmlspecialchars($w['value'] ?? '');
          else echo htmlspecialchars($r['description'] ?? '');
          ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="flex:1;min-width:320px">
      <div class="card" style="padding:16px">
        <div style="font-weight:700;margin-bottom:10px"><?=$edit?'编辑规则':'新建规则'?></div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?=htmlspecialchars($editId)?>"><?php endif; ?>
          <input name="tag" placeholder="要打的标签，如 高意向" value="<?=htmlspecialchars($edit['tag'] ?? '')?>" required style="width:100%;margin-bottom:8px">
          <div style="display:flex;gap:8px;margin-bottom:8px">
            <select name="when_type" id="wt" onchange="wtChange()" style="flex:1;padding:6px"><option value="">条件类型…</option><?php foreach ($typeNames as $k=>$v): ?><option value="<?=$k?>" <?=($edit['when']['type']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select>
            <input type="text" name="when_event" id="we" placeholder="事件名(pricing_view)" value="<?=htmlspecialchars($edit['when']['event'] ?? '')?>" style="flex:1;padding:6px;display:none">
            <input type="text" name="when_field" id="wf" placeholder="字段(page_views_30d/purchase_amount_total)" value="<?=htmlspecialchars($edit['when']['field'] ?? '')?>" style="flex:1;padding:6px;display:none">
            <select name="when_op" id="wo" style="flex:0 0 90px;padding:6px">
              <?php foreach (['gte'=>'≥','gt'=>'>','lte'=>'≤','lt'=>'<','eq'=>'=','contains'=>'包含'] as $ok=>$ov): ?><option value="<?=$ok?>" <?=($edit['when']['operator']??'gte')===$ok?'selected':''?>><?=$ov?></option><?php endforeach; ?>
            </select>
            <input type="text" name="when_value" placeholder="值" value="<?=htmlspecialchars($edit['when']['value'] ?? '1')?>" style="flex:0 0 70px;padding:6px">
          </div>
          <input type="text" name="description" placeholder="描述（可选）" value="<?=htmlspecialchars($edit['description'] ?? '')?>" style="width:100%;margin-bottom:8px">
          <label style="font-size:13px;display:block;margin-bottom:10px"><input type="checkbox" name="enabled" <?=empty($edit)||!empty($edit['enabled'])?'checked':''?>> 启用</label>
          <button class="btn btn-primary btn-sm"><?=$edit?'更新':'创建'?></button>
          <?php if ($edit): ?><a href="/xmp/tag-rules" class="btn btn-ghost btn-sm">取消</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
function wtChange() {
  var t = document.getElementById('wt').value;
  document.getElementById('we').style.display = (t === 'event') ? 'block' : 'none';
  document.getElementById('wf').style.display = (t === 'summary' || t === 'property') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', wtChange);
</script>
<?php admin_footer(); ?>
