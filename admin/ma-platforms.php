<?php
/**
 * 广告平台对接 —— 转化回传连接管理（P1-3）
 *
 * 维护 data/ad_platforms.json：每个平台一条（platform/enabled/pixel_id|customer_id|advertiser_id/
 * token/event_name_map），供 ConversionApi::conv_process 按 platform 走对应 provider 回传。
 * 依赖外部 API 凭据（pixel_id/access_token 等），只能做到「配置正确 + 请求构造」，
 * 真实回传需在平台后台配好凭据后验证。
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('cdp');

function ma_file(): string { return DATA_DIR . '/ad_platforms.json'; }
function ma_read(): array { $r = json_read(ma_file()); return is_array($r) ? $r : []; }
function ma_platform_names(): array { return ['meta'=>'Meta CAPI','google'=>'Google Ads','douyin'=>'巨量/TikTok']; }

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $all = ma_read();
    if ($act === 'save') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id === '') $id = 'plat_' . bin2hex(random_bytes(4));
        $platform = in_array($_POST['platform'] ?? '', ['meta','google','douyin'], true) ? $_POST['platform'] : 'meta';
        // event_name_map：文本行 k=v 或逗号分隔
        $map = [];
        foreach (preg_split('/[\r\n,]+/', (string)($_POST['event_name_map'] ?? '')) as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            if (str_contains($line, '=')) { [$k, $v] = explode('=', $line, 2); $map[trim($k)] = trim($v); }
            else $map[$line] = $line;
        }
        $all[$id] = ['id'=>$id, 'name'=>trim((string)($_POST['name'] ?? '')), 'platform'=>$platform,
            'enabled'=>isset($_POST['enabled']), 'pixel_id'=>$_POST['pixel_id'] ?? '',
            'customer_id'=>$_POST['customer_id'] ?? '', 'conversion_action_id'=>$_POST['conversion_action_id'] ?? '',
            'advertiser_id'=>$_POST['advertiser_id'] ?? '', 'pixel_code'=>$_POST['pixel_code'] ?? '',
            'access_token'=>$_POST['access_token'] ?? '', 'developer_token'=>$_POST['developer_token'] ?? '',
            'event_source_url'=>$_POST['event_source_url'] ?? '', 'event_name_map'=>$map,
            'created_at'=>date('c')];
        json_write(ma_file(), $all);
        audit('保存广告平台连接 ' . ($all[$id]['name'] ?? $id), 'cdp');
        header('Location: /xmp/ma-platforms?ok=1'); exit;
    } elseif ($act === 'delete') {
        unset($all[$_POST['id'] ?? '']); json_write(ma_file(), $all);
        header('Location: /xmp/ma-platforms'); exit;
    } elseif ($act === 'toggle') {
        $id = (string)($_POST['id'] ?? '');
        if (isset($all[$id])) { $all[$id]['enabled'] = !empty($all[$id]['enabled']) ? false : true; json_write(ma_file(), $all); }
        header('Location: /xmp/ma-platforms'); exit;
    }
}

$all = ma_read();
$editId = trim((string)($_GET['edit'] ?? ''));
$edit = $editId !== '' ? ($all[$editId] ?? null) : null;
$names = ma_platform_names();

admin_header('广告平台对接');
?>
<div style="max-width:960px">
  <h1 style="margin:0 0 4px">📤 广告平台对接</h1>
  <p class="v-sub" style="margin:0 0 16px">配置转化回传连接（Meta CAPI / Google Ads / 巨量）。<b style="color:var(--warn)">需在平台后台配好凭据后，真实回传才会生效</b>——此处只保证配置与请求构造正确。</p>
  <?php if (isset($_GET['ok'])): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a">连接已保存。</div><?php endif; ?>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:300px">
      <div style="font-weight:700;margin-bottom:8px">连接（<?=count($all)?>）</div>
      <?php if (!$all): ?><div class="v-sub" style="font-size:13px">还没有。右侧新建一个。</div><?php endif; ?>
      <?php foreach ($all as $id => $p): ?>
      <div class="card" style="padding:12px 14px;margin-bottom:8px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <div><strong><?=htmlspecialchars($p['name'] ?? $id)?></strong>
            <span style="font-size:11px;padding:1px 6px;border-radius:999px;background:<?=!empty($p['enabled'])?'#dcfce7':'#f1f5f9'?>;color:<?=!empty($p['enabled'])?'#166534':'#64748b'?>"><?=!empty($p['enabled'])?'启用':'停用'?></span>
            <span style="font-size:12px;color:var(--faint)"><?=htmlspecialchars($names[$p['platform']] ?? $p['platform'])?></span>
          </div>
          <div style="display:flex;gap:6px">
            <a href="/xmp/ma-platforms?edit=<?=urlencode($id)?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=htmlspecialchars($id)?>"><button class="btn btn-ghost btn-sm"><?=!empty($p['enabled'])?'停用':'启用'?></button></form>
            <form method="post" data-confirm="删除?" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($id)?>"><button class="btn btn-ghost btn-sm" style="color:#dc2626">删除</button></form>
          </div>
        </div>
        <div style="font-size:12px;color:var(--faint);margin-top:4px">
          <?php if ($p['platform'] === 'meta') echo 'pixel_id: ' . htmlspecialchars($p['pixel_id'] ?? '');
          elseif ($p['platform'] === 'google') echo 'customer_id: ' . htmlspecialchars($p['customer_id'] ?? '');
          elseif ($p['platform'] === 'douyin') echo 'advertiser_id: ' . htmlspecialchars($p['advertiser_id'] ?? ''); ?>
          <?php if (!empty($p['event_name_map'])): ?> · 映射 <?=count($p['event_name_map'])?> 项<?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="flex:1;min-width:340px">
      <div class="card" style="padding:16px">
        <div style="font-weight:700;margin-bottom:10px"><?=$edit?'编辑连接':'新建连接'?></div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>"><?php endif; ?>
          <input name="name" placeholder="名称，如 巨量转化回传" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required style="width:100%;margin-bottom:8px">
          <select name="platform" id="pf" onchange="pfChange()" style="width:100%;padding:8px;margin-bottom:8px">
            <?php foreach ($names as $k=>$v): ?><option value="<?=$k?>" <?=($edit['platform']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
          </select>
          <div class="pf-meta" style="display:flex;gap:8px;margin-bottom:8px">
            <input type="text" name="pixel_id" id="f_pixel" placeholder="Meta pixel_id" value="<?=htmlspecialchars($edit['pixel_id']??'')?>" style="flex:1;padding:8px">
            <input type="text" name="customer_id" id="f_customer" placeholder="Google customer_id" value="<?=htmlspecialchars($edit['customer_id']??'')?>" style="flex:1;padding:8px;display:none">
            <input type="text" name="advertiser_id" id="f_advertiser" placeholder="巨量 advertiser_id" value="<?=htmlspecialchars($edit['advertiser_id']??'')?>" style="flex:1;padding:8px;display:none">
          </div>
          <input type="text" name="conversion_action_id" placeholder="Google conversion_action_id" value="<?=htmlspecialchars($edit['conversion_action_id']??'')?>" style="width:100%;padding:8px;margin-bottom:8px">
          <input type="text" name="pixel_code" placeholder="巨量 pixel_code" value="<?=htmlspecialchars($edit['pixel_code']??'')?>" style="width:100%;padding:8px;margin-bottom:8px">
          <input type="password" name="access_token" placeholder="access_token / Bearer" value="<?=htmlspecialchars($edit['access_token']??'')?>" style="width:100%;padding:8px;margin-bottom:8px">
          <input type="text" name="developer_token" placeholder="Google developer_token" value="<?=htmlspecialchars($edit['developer_token']??'')?>" style="width:100%;padding:8px;margin-bottom:8px">
          <input type="text" name="event_source_url" placeholder="event_source_url（选填）" value="<?=htmlspecialchars($edit['event_source_url']??'')?>" style="width:100%;padding:8px;margin-bottom:8px">
          <input type="text" name="event_name_map" placeholder="事件名映射（每行 k=v，如 purchase=Purchase）" value="<?=htmlspecialchars(is_array($edit['event_name_map']??null)?implode("\n", array_map(fn($k,$v)=>$k.'='.$v,array_keys($edit['event_name_map']),array_values($edit['event_name_map']))):'')?>" style="width:100%;padding:8px;margin-bottom:8px;font-family:monospace;font-size:13px">
          <label style="font-size:13px;display:block;margin-bottom:10px"><input type="checkbox" name="enabled" <?=empty($edit)||!empty($edit['enabled'])?'checked':''?>> 启用</label>
          <button class="btn btn-primary btn-sm"><?=$edit?'更新':'创建'?></button>
          <?php if ($edit): ?><a href="/xmp/ma-platforms" class="btn btn-ghost btn-sm">取消</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
function pfChange() {
  var p = document.getElementById('pf').value;
  document.getElementById('f_pixel').style.display = p === 'meta' ? 'block' : 'none';
  document.getElementById('f_customer').style.display = p === 'google' ? 'block' : 'none';
  document.getElementById('f_advertiser').style.display = p === 'douyin' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', pfChange);
</script>
<?php admin_footer(); ?>
