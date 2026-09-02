<?php
/**
 * 人群激活 · Destinations —— 把 CDP 人群推到外部（BACKLOG T0-6）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DestinationSystem.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_login();
require_perm('segments');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $r = dest_save([
            'id' => $_POST['id'] ?? '', 'name' => $_POST['name'] ?? '', 'type' => $_POST['type'] ?? 'webhook',
            'segment_id' => $_POST['segment_id'] ?? '', 'url' => $_POST['url'] ?? '', 'token' => $_POST['token'] ?? '',
            'trigger' => $_POST['trigger'] ?? 'realtime', 'enabled' => isset($_POST['enabled']), 'field_map' => $_POST['field_map'] ?? '',
        ]);
        if ($r['ok']) { audit('保存人群目的地 ' . $r['dest']['id'], 'cdp'); header('Location: /xmp/destinations?ok=1'); exit; }
        $err = $r['error'];
    } elseif ($act === 'delete') {
        dest_delete($_POST['id'] ?? ''); header('Location: /xmp/destinations'); exit;
    } elseif ($act === 'sync') {
        $r = dest_sync_full($_POST['id'] ?? '');
        $msg = $r['ok'] ? "全量同步完成：成功 {$r['synced']}、失败 {$r['failed']}。" : ($r['error'] ?? '同步失败');
    }
}

$segments = CdpSystem::allSegments();
$segName = [];
foreach ($segments as $s) $segName[$s['id'] ?? ''] = $s['name'] ?? ($s['id'] ?? '');
$dests = dest_all();
$edit = !empty($_GET['edit']) ? dest_get($_GET['edit']) : null;

admin_header('人群激活');
?>
<div style="max-width:960px">
  <h1 style="margin:0 0 4px">📡 人群激活 · Destinations</h1>
  <p class="v-sub" style="margin:0 0 16px">把 CDP 圈好的人群推到外部——广告受众 / 邮件列表 / Webhook。进群实时触发，或手动全量同步。补上"圈了推不出去"的最后一公里。</p>
  <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:300px">
      <div style="font-weight:700;margin-bottom:8px">目的地（<?=count($dests)?>）</div>
      <?php if (!$dests): ?><div class="v-sub" style="font-size:13px">还没有。右侧新建一个。</div><?php endif; ?>
      <?php foreach ($dests as $d): ?>
      <div class="card" style="padding:12px 14px;margin-bottom:8px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <div><strong><?=htmlspecialchars($d['name'])?></strong>
            <span style="font-size:11px;padding:1px 6px;border-radius:999px;background:<?=!empty($d['enabled'])?'#dcfce7':'#f1f5f9'?>;color:<?=!empty($d['enabled'])?'#166534':'#64748b'?>"><?=!empty($d['enabled'])?'启用':'停用'?></span>
            <span style="font-size:12px;color:var(--faint)"><?=htmlspecialchars(dest_types()[$d['type']] ?? $d['type'])?> · <?=($d['trigger']??'')==='realtime'?'实时':'手动'?></span>
          </div>
          <div style="display:flex;gap:6px">
            <a href="/xmp/destinations?edit=<?=urlencode($d['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><input type="hidden" name="id" value="<?=htmlspecialchars($d['id'])?>"><button class="btn btn-ghost btn-sm">全量同步</button></form>
          </div>
        </div>
        <div style="font-size:12px;color:var(--faint);margin-top:4px">人群：<?=htmlspecialchars($segName[$d['segment_id']] ?? '(未选)')?>
          <?php if (!empty($d['stats'])): ?> · 上次同步 <?=$d['stats']['synced']?> 成功 / <?=$d['stats']['failed']?> 失败<?php endif; ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="flex:1;min-width:320px">
      <div class="card" style="padding:16px">
        <div style="font-weight:700;margin-bottom:10px"><?=$edit?'编辑目的地':'新建目的地'?></div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>"><?php endif; ?>
          <input name="name" placeholder="名称，如 高意向-Google广告受众" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required style="width:100%;margin-bottom:8px">
          <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap">
            <select name="type" style="flex:1;min-width:120px"><?php foreach (dest_types() as $k=>$v): ?><option value="<?=$k?>" <?=($edit['type']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select>
            <select name="segment_id" style="flex:1;min-width:120px"><option value="">选择人群…</option><?php foreach ($segments as $s): ?><option value="<?=htmlspecialchars($s['id']??'')?>" <?=($edit['segment_id']??'')===($s['id']??'')?'selected':''?>><?=htmlspecialchars($s['name']??$s['id']??'')?></option><?php endforeach; ?></select>
            <select name="trigger" style="width:120px"><option value="realtime" <?=($edit['trigger']??'')==='realtime'?'selected':''?>>进群实时</option><option value="manual" <?=($edit['trigger']??'')==='manual'?'selected':''?>>仅手动</option></select>
          </div>
          <input name="url" placeholder="Webhook/接收 URL（capi 可留空走内置）" value="<?=htmlspecialchars($edit['url'] ?? '')?>" style="width:100%;margin-bottom:8px">
          <input name="token" placeholder="Bearer Token（选填）" value="<?=htmlspecialchars($edit['token'] ?? '')?>" style="width:100%;margin-bottom:8px">
          <label style="display:block;font-size:12px;color:var(--faint);margin-bottom:2px">字段映射（每行 目标字段=画像路径，如 email=properties.email）</label>
          <textarea name="field_map" rows="3" placeholder="留空用默认(visitor_id/member_id/email/tags)" style="width:100%;margin-bottom:8px;font-family:monospace;font-size:13px"><?php
            if (!empty($edit['field_map'])) { $lines=[]; foreach ($edit['field_map'] as $k=>$v) $lines[]="$k=$v"; echo htmlspecialchars(implode("\n",$lines)); }
          ?></textarea>
          <label style="font-size:13px;display:block;margin-bottom:10px"><input type="checkbox" name="enabled" <?=!empty($edit['enabled'])||!$edit?'checked':''?>> 启用</label>
          <button class="btn btn-primary btn-sm"><?=$edit?'更新':'创建'?></button>
          <?php if ($edit): ?>
          <a href="/xmp/destinations" class="btn btn-ghost btn-sm">取消</a>
          <form method="post" data-confirm="删除?" style="display:inline;margin-left:8px"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>"><button class="btn btn-ghost btn-sm" style="color:#dc2626">删除</button></form>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
