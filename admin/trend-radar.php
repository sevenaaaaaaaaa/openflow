<?php
/**
 * 热点雷达 — 多源抓取 → AI 聚类热度 → 转选题（接内容链路）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/TrendRadar.php';
require_login();
require_perm('settings');

$s = trend_settings();
$radar = trend_radar();
$clusters = array_values((array)($radar['clusters'] ?? []));
$items = array_values((array)($radar['items'] ?? []));
admin_header('热点雷达');
?>
<div class="admin-layout">
  <?php admin_sidebar('trend-radar'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>热点雷达</h1><p class="v-sub">Hacker News / GitHub / RSS 多源抓取 → 去重 → AI 聚类成热点话题并给热度 → 一键转成 GEO 选题，进入内容链路。</p></div>
      <div class="v-actions"><span class="note mono" style="margin:0" id="trMsg"></span><button class="btn btn-p btn-sm" id="trRun" onclick="trRun()">▶ 立即扫描</button><a class="btn btn-s btn-sm" href="/xmp/geo">GEO 选题库</a></div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <h2 style="font-size:15px">⚙️ 抓取设置</h2>
      <div class="field"><label>关键词 <span class="hint">· 逗号或换行分隔</span></label><input class="inp" id="trKw" value="<?=htmlspecialchars(implode(', ', (array)$s['keywords']))?>"></div>
      <div class="field"><label>RSS 源 <span class="hint">· 每行一个（可选）</span></label><textarea class="inp" id="trRss" rows="3" placeholder="https://example.com/feed"><?=htmlspecialchars(implode("\n", (array)$s['rss']))?></textarea></div>
      <div class="field-row">
        <div class="field"><label>HN 最低热度分</label><input class="inp" type="number" id="trMin" value="<?=(int)$s['min_points']?>"></div>
        <div class="field"><label>时间窗口（天）</label><input class="inp" type="number" id="trDays" value="<?=(int)$s['max_age_days']?>" min="1" max="30"></div>
      </div>
      <label style="display:block;font-size:13px;margin:6px 0 10px"><input type="checkbox" id="trEnabled" <?=!empty($s['enabled'])?'checked':''?>> 启用（cron 每日自动扫描）</label>
      <button class="btn btn-s btn-sm" onclick="trSave()">保存设置</button>
    </div>

    <div class="card" style="margin-bottom:16px">
      <h2 style="font-size:15px">🔥 热点话题 <span class="hint"><?=!empty($radar['generated_at']) ? ('· 上次扫描 ' . htmlspecialchars($radar['generated_at']) . ' · ' . (int)$radar['item_count'] . ' 条') : '· 还没扫描过'?></span></h2>
      <?php if (!$clusters): ?><div class="empty" style="padding:20px">点右上角「立即扫描」，雷达会抓取并聚类出热点话题。</div><?php endif; ?>
      <?php foreach ($clusters as $i => $c): ?>
      <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--border-soft,var(--border))">
        <div style="flex:none;width:44px;text-align:center">
          <div style="font-size:20px;font-weight:800;color:var(--accent)"><?=(int)($c['heat'] ?? 0)?></div>
          <div class="text-xs text-muted">热度</div>
        </div>
        <div style="flex:1;min-width:0">
          <b><?=htmlspecialchars($c['title'])?></b>
          <?php if (!empty($c['angle'])): ?><div class="text-sm text-muted">角度：<?=htmlspecialchars($c['angle'])?></div><?php endif; ?>
          <?php if (!empty($c['why'])): ?><div class="text-sm text-muted"><?=htmlspecialchars($c['why'])?></div><?php endif; ?>
          <div class="text-xs text-muted" style="margin-top:4px"><?=count((array)($c['item_urls'] ?? []))?> 条来源：<?php foreach (array_slice((array)($c['item_urls'] ?? []), 0, 3) as $u): ?><a href="<?=htmlspecialchars($u)?>" target="_blank" rel="noopener nofollow" style="color:var(--accent)"><?=htmlspecialchars(parse_url($u, PHP_URL_HOST) ?: $u)?></a> <?php endforeach; ?></div>
        </div>
        <button class="btn btn-s btn-sm" onclick='trPromote(<?=json_encode($c['title'], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)?>,<?=json_encode($c['angle'] ?? '', JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)?>,<?=json_encode($c['why'] ?? '', JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)?>,this)'>转选题</button>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($items): ?>
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:16px 16px 0;font-size:14px">原始条目（Top <?=min(30, count($items))?>）</h2>
      <table>
        <thead><tr><th>标题</th><th>来源</th><th>热度</th><th>日期</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($items, 0, 30) as $it): ?>
          <tr>
            <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a href="<?=htmlspecialchars($it['url'])?>" target="_blank" rel="noopener nofollow"><?=htmlspecialchars($it['title'])?></a></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($it['source'])?></td>
            <td class="mono"><?=(int)($it['points'] ?? 0)?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($it['published'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
const TR_CSRF = <?=json_encode(csrf_token())?>;
function trMsg(t){ document.getElementById('trMsg').textContent = t; }
async function trPost(payload, form) {
  const opt = {method:'POST', headers:{'X-CSRF-Token':TR_CSRF}};
  if (form) opt.body = payload; else { opt.headers['Content-Type']='application/json'; opt.body = JSON.stringify(payload); }
  return (await fetch('/api/trend-radar.php', opt)).json();
}
async function trSave(){
  trMsg('保存中…');
  const r = await trPost({action:'settings_save', keywords:document.getElementById('trKw').value, rss:document.getElementById('trRss').value, min_points:document.getElementById('trMin').value, max_age_days:document.getElementById('trDays').value, enabled:document.getElementById('trEnabled').checked?1:0});
  trMsg(r.ok?'设置已保存':(r.error||'失败'));
}
async function trRun(){
  const b=document.getElementById('trRun'); b.disabled=true; b.textContent='扫描中…'; trMsg('多源抓取 + 聚类中…');
  const r = await trPost({action:'run'});
  b.disabled=false; b.textContent='▶ 立即扫描';
  if(!r.ok){ trMsg(r.error||'失败'); return; }
  trMsg('抓到 '+r.items+' 条 → '+r.clusters+' 个话题，刷新中…'); setTimeout(()=>location.reload(),600);
}
async function trPromote(title, angle, why, btn){
  btn.disabled=true; trMsg('转选题中…');
  const r = await trPost({action:'promote', title, angle, why});
  if(r.ok){ btn.textContent='已转→GEO'; trMsg('已加入 GEO 选题库'); } else { trMsg(r.error||'失败'); btn.disabled=false; }
}
</script>
<?php admin_footer(); ?>
