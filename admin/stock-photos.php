<?php
/**
 * 免费图库 — Pexels / Unsplash / Pixabay
 * 配置 API Key + 搜索浏览 + 一键下载到本地
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('media');

$cfgFile = DATA_DIR . '/stock.json';
$cfg = json_read($cfgFile);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $cfg = [
        'pexels_key' => trim($_POST['pexels_key'] ?? ''),
        'unsplash_key' => trim($_POST['unsplash_key'] ?? ''),
        'pixabay_key' => trim($_POST['pixabay_key'] ?? ''),
    ];
    json_write($cfgFile, $cfg);
    $message = '图库配置已保存';
}

admin_header('免费图库');
?>
<style>
.stock-layout{display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start}
.stock-panel{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px}
.stock-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.stock-item{position:relative;border-radius:10px;overflow:hidden;aspect-ratio:4/3;background:var(--surface-2);cursor:pointer;transition:transform .15s;border:2px solid transparent}
.stock-item:hover{transform:scale(1.02);border-color:var(--accent)}
.stock-item img{width:100%;height:100%;object-fit:cover}
.stock-item .badge{position:absolute;left:6px;top:6px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:rgba(0,0,0,.55);color:#fff;backdrop-filter:blur(3px)}
.stock-item .dl{position:absolute;right:6px;bottom:6px;font-size:11px;font-weight:600;padding:5px 10px;border-radius:7px;background:var(--accent);color:#1e1e1e;opacity:0;transition:opacity .15s}
.stock-item:hover .dl{opacity:1}
.stock-item .credit{position:absolute;left:6px;bottom:6px;font-size:10px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.7);max-width:70%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stock-searchbar{display:flex;gap:8px;margin-bottom:14px}
.stock-searchbar input{flex:1;padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--surface)}
.platform-tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
.platform-tabs button{padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);font-size:13px;font-weight:600;cursor:pointer;color:var(--text-2)}
.platform-tabs button.active{background:var(--accent);border-color:var(--accent);color:#1e1e1e}
.empty-tip{text-align:center;padding:48px 20px;color:var(--text-3);font-size:14px}
.spinner{text-align:center;padding:40px;color:var(--text-3)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('media'); ?>
  <div class="main">
    <h1> 免费图库</h1>
    <p class="sub">接入 Pexels · Unsplash · Pixabay 官方 API · 搜索免费可商用素材，一键下载到媒体库</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stock-layout">
      <!-- 配置面板 -->
      <div class="stock-panel">
        <h2 style="font-size:15px;margin-bottom:12px">API 配置</h2>
        <p class="text-sm text-muted mb-4" style="font-size:12px;line-height:1.7">
          在下方平台注册开发者账号，免费获取 API Key，填入后即可搜索。<br><br>
          <a href="https://www.pexels.com/api/" target="_blank" rel="noopener" style="color:var(--accent)">Pexels API →</a><br>
          <a href="https://unsplash.com/developers" target="_blank" rel="noopener" style="color:var(--accent)">Unsplash API →</a><br>
          <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener" style="color:var(--accent)">Pixabay API →</a>
        </p>
        <form method="post" id="stockForm">
          <?= csrf_field() ?>
          <div class="field"><label>Pexels Key</label><input type="password" name="pexels_key" value="<?=htmlspecialchars($cfg['pexels_key'] ?? '')?>" placeholder="Pexels API Key"></div>
          <div class="field"><label>Unsplash Key</label><input type="password" name="unsplash_key" value="<?=htmlspecialchars($cfg['unsplash_key'] ?? '')?>" placeholder="Access Key"></div>
          <div class="field"><label>Pixabay Key</label><input type="password" name="pixabay_key" value="<?=htmlspecialchars($cfg['pixabay_key'] ?? '')?>" placeholder="Pixabay API Key"></div>
          <button type="submit" name="save" class="btn btn-primary" style="width:100%">保存配置</button>
        </form>
        <p class="text-sm text-muted mt-4" style="font-size:11px">🔒 Key 仅存于服务器 data/，前端不暴露。</p>
      </div>

      <!-- 搜索浏览 -->
      <div class="stock-panel">
        <div class="platform-tabs">
          <button class="active" data-plat="pexels">Pexels</button>
          <button data-plat="unsplash">Unsplash</button>
          <button data-plat="pixabay">Pixabay</button>
        </div>
        <div class="stock-searchbar">
          <input type="text" id="stockQ" placeholder="输入关键词，如 AI growth content marketing ..." onkeydown="if(event.key==='Enter'){doStockSearch(1)}">
          <button class="btn btn-primary" onclick="doStockSearch(1)">搜索</button>
        </div>
        <div id="stockResults">
          <div class="empty-tip">👆 选择平台并输入关键词搜索（如 office / team / meeting）</div>
        </div>
        <div id="stockPager" style="display:none;margin-top:14px;display:flex;gap:8px;justify-content:center">
          <button class="btn btn-ghost btn-sm" id="prevPage" onclick="changePage(-1)">‹ 上一页</button>
          <span class="text-sm text-muted" id="pageInfo" style="align-self:center"></span>
          <button class="btn btn-ghost btn-sm" id="nextPage" onclick="changePage(1)">下一页 ›</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var STOCK = { platform: 'pexels', page: 1, perPage: 12, query: '' };

document.querySelectorAll('.platform-tabs button').forEach(function(b) {
  b.addEventListener('click', function() {
    document.querySelectorAll('.platform-tabs button').forEach(function(x) { x.classList.remove('active'); });
    b.classList.add('active');
    STOCK.platform = b.dataset.plat;
  });
});

function doStockSearch(page) {
  STOCK.query = document.getElementById('stockQ').value.trim();
  STOCK.page = page || 1;
  if (!STOCK.query) { ofAlert('请输入搜索关键词'); return; }
  var box = document.getElementById('stockResults');
  box.innerHTML = '<div class="spinner">⏳ 正在从 ' + STOCK.platform + ' 搜索「' + STOCK.query + '」...</div>';

  fetch('../api/stock.php?action=search&platform=' + STOCK.platform + '&q=' + encodeURIComponent(STOCK.query) + '&page=' + STOCK.page + '&per_page=' + STOCK.perPage)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) { box.innerHTML = '<div class="empty-tip">❌ ' + (d.error || '搜索失败') + '</div>'; return; }
      if (!d.photos.length) { box.innerHTML = '<div class="empty-tip">未找到相关图片，换个关键词试试</div>'; return; }
      box.innerHTML = '<div class="stock-grid">' + d.photos.map(function(p, i) {
        return '<div class="stock-item" onclick="downloadStock(' + i + ')">' +
          '<img src="' + p.thumb + '" alt="" loading="lazy">' +
          '<span class="badge">' + p.platform + '</span>' +
          '<span class="credit">' + p.photographer + '</span>' +
          '<span class="dl">下载 ↓</span>' +
          '</div>';
      }).join('') + '</div>';
      window.STOCK_PHOTOS = d.photos;
      var pager = document.getElementById('stockPager');
      pager.style.display = 'flex';
      document.getElementById('pageInfo').textContent = '第 ' + STOCK.page + ' 页 · 共 ' + d.total + ' 张';
      document.getElementById('prevPage').disabled = STOCK.page <= 1;
      document.getElementById('nextPage').disabled = STOCK.page * STOCK.perPage >= d.total;
    })
    .catch(function() { box.innerHTML = '<div class="empty-tip">请求失败，请检查网络或 API 配置</div>'; });
}

function changePage(delta) {
  doStockSearch(STOCK.page + delta);
}

async function downloadStock(idx) {
  var p = window.STOCK_PHOTOS[idx];
  if (!p) return;
  if (!await ofConfirm({ title: '下载到本地媒体库', message: '存入文章封面目录。来源：' + p.platform + ' · 作者：' + p.photographer, okText: '下载' })) return;
  fetch('../api/stock.php?action=download', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ url: p.full, dir: 'articles' })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.ok) {
      ofAlert('✅ 已下载到媒体库：' + d.path + '\n可在文章编辑页封面选择中选用');
      var grid = document.querySelectorAll('.stock-item')[idx];
      if (grid) grid.querySelector('.dl').textContent = '✓ 已下载';
    } else {
      ofAlert('❌ ' + (d.error || '下载失败'));
    }
  })
  .catch(function() { ofAlert('❌ 下载失败'); });
}
</script>
<?php admin_footer(); ?>
