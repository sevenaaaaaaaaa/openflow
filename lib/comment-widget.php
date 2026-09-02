<?php
/**
 * 评论/点评 前端组件（复用）
 * 用法：fc_comment_widget('article', $articleId, ['rating' => true]);
 * 依赖：/api/comment.php
 */
require_once __DIR__ . '/CommentSystem.php';

function fc_comment_widget(string $type, string $targetId, array $opts = []): void {
    $ratingEnabled = $opts['rating'] ?? false;
    $title = $opts['title'] ?? ($ratingEnabled ? '点评' : '评论');
    $summary = comment_rating_summary($type, $targetId);
    $member = member_current();
?>
<!-- ═══ 评论/点评 组件 ═══ -->
<div class="fc-comment-widget" data-type="<?=htmlspecialchars($type)?>" data-target="<?=htmlspecialchars($targetId)?>" style="margin-top:40px">
  <style>
    /* v8：颜色全部走 token（原先是一套 #ddff0e / #1e1e1e 的旧调色板，与站点其它零件不一致） */
    .fc-comment-widget{font-family:inherit}
    .fc-cw-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
    .fc-cw-title{font-size:18px;font-weight:800;letter-spacing:-.01em;display:inline-flex;align-items:center;gap:8px}
    .fc-cw-title svg{width:18px;height:18px;color:var(--accent)}
    .fc-cw-rating-big{font-size:14px;color:var(--warn);font-weight:700;display:inline-flex;align-items:center;gap:6px}
    .fc-cw-rating-big .mu{color:var(--faint);font-weight:400;font-size:13px}
    .fc-cw-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:18px;margin-bottom:16px}
    .fc-cw-stars{display:flex;gap:4px;font-size:26px;cursor:pointer;user-select:none;margin-bottom:10px}
    .fc-cw-stars span{color:var(--border-strong);transition:color .1s}
    .fc-cw-stars span.on,.fc-cw-stars span.sel{color:var(--warn)}
    .fc-cw-input{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:12px 14px;font-size:14px;font-family:inherit;min-height:84px;resize:vertical;outline:none;background:var(--surface);color:var(--fg);transition:border-color .2s,box-shadow .2s}
    .fc-cw-input:focus{border-color:var(--accent);box-shadow:0 0 0 4px color-mix(in oklab,var(--accent),transparent 88%)}
    .fc-cw-submit{background:var(--accent);color:var(--on-accent);border:none;border-radius:10px;height:40px;padding:0 20px;font-weight:700;font-size:13.5px;cursor:pointer;transition:background .2s}
    .fc-cw-submit:hover{background:var(--accent-strong)}
    .fc-cw-submit:disabled{opacity:.5;cursor:not-allowed}
    .fc-cw-item{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid var(--border-soft)}
    .fc-cw-item:last-child{border-bottom:none}
    .fc-cw-avatar{width:36px;height:36px;border-radius:50%;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-size:14px;font-weight:700;flex-shrink:0}
    .fc-cw-name{font-size:13.5px;font-weight:700}
    .fc-cw-meta{font-size:11.5px;color:var(--faint);margin-left:6px;font-family:var(--font-mono)}
    .fc-cw-stars-sm{color:var(--warn);font-size:12px;letter-spacing:1px}
    .fc-cw-text{font-size:14px;line-height:1.7;color:var(--fg);margin-top:4px;word-break:break-word}
    .fc-cw-like{background:none;border:none;color:var(--faint);font-size:12.5px;cursor:pointer;margin-top:8px;padding:0;display:inline-flex;align-items:center;gap:4px}
    .fc-cw-like svg{width:13px;height:13px}
    .fc-cw-like:hover{color:var(--accent)}
    .fc-cw-empty{color:var(--faint);font-size:13.5px;padding:24px;text-align:center;border:1px dashed var(--border-strong);border-radius:var(--r-md)}
    .fc-cw-msg{font-size:13px;margin-top:8px;color:var(--faint)}
    .fc-cw-msg a{color:var(--accent);font-weight:600}
  </style>

  <div class="fc-cw-header">
    <span class="fc-cw-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg><?=htmlspecialchars($title)?></span>
    <?php if ($ratingEnabled): ?>
    <span class="fc-cw-rating-big">★ <?=$summary['avg'] > 0 ? number_format($summary['avg'], 1) : '—'?> <span class="mu">(<?=$summary['count']?> 人评分)</span></span>
    <?php endif; ?>
    <span class="fc-cw-rating-big mu"><?=$summary['total']?> 条</span>
  </div>

  <!-- 打分 + 输入 -->
  <div class="fc-cw-box">
    <?php if ($ratingEnabled): ?>
    <div class="fc-cw-stars" id="fcCwStars">
      <?php for ($i = 1; $i <= 5; $i++): ?><span data-v="<?=$i?>">★</span><?php endfor; ?>
    </div>
    <?php endif; ?>
    <textarea class="fc-cw-input" id="fcCwInput" placeholder="<?=htmlspecialchars($ratingEnabled ? '写下你的体验与打分理由…' : '写下你的看法…')?>"></textarea>
    <div style="display:flex;align-items:center;gap:12px;margin-top:10px">
      <button class="fc-cw-submit" id="fcCwSubmit" onclick="fcCommentSubmit()">发布</button>
      <span class="fc-cw-msg"><?php if ($member): ?>登录为 <?=htmlspecialchars($member['name'] ?? '')?><?php else: ?><a href="/member.php?view=login">登录</a>后可评论<?php endif; ?></span>
    </div>
  </div>

  <div id="fcCwList"></div>
</div>

<script>
(function() {
  var w = document.querySelector('.fc-comment-widget[data-type="<?=htmlspecialchars($type)?>"][data-target="<?=htmlspecialchars($targetId)?>"]');
  if (!w) return;
  w.FC_TYPE = '<?=htmlspecialchars($type)?>';
  w.FC_TARGET = '<?=htmlspecialchars($targetId)?>';
  w.FC_RATING = <?=$ratingEnabled ? 'true' : 'false'?>;
  var starVal = 0;
  var stars = document.getElementById('fcCwStars');
  if (stars) {
    stars.querySelectorAll('span').forEach(function(s) {
      s.addEventListener('mouseover', function(){ stars.querySelectorAll('span').forEach(function(x){ x.classList.toggle('on', parseInt(x.dataset.v) <= parseInt(s.dataset.v)); }); });
      s.addEventListener('mouseleave', function(){ stars.querySelectorAll('span').forEach(function(x){ x.classList.toggle('on', parseInt(x.dataset.v) <= starVal); }); });
      s.addEventListener('click', function(){ starVal = parseInt(s.dataset.v); stars.querySelectorAll('span').forEach(function(x){ x.classList.toggle('sel', parseInt(x.dataset.v) <= starVal); }); });
    });
  }
  w.fcLoad = function() {
    fetch('/api/comment.php?action=list&type=' + encodeURIComponent(w.FC_TYPE) + '&target_id=' + encodeURIComponent(w.FC_TARGET))
      .then(function(r){ return r.json(); })
      .then(function(d) {
        var box = document.getElementById('fcCwList');
        if (!d.ok) { box.innerHTML = ''; return; }
        if (!d.comments.length) { box.innerHTML = '<div class="fc-cw-empty">还没有<?=htmlspecialchars($ratingEnabled ? '点评' : '评论')?>，来抢沙发</div>'; return; }
        box.innerHTML = d.comments.map(function(c) {
          return '<div class="fc-cw-item">' +
            '<div class="fc-cw-avatar">' + (c.author || '匿').slice(0,1) + '</div>' +
            '<div style="flex:1;min-width:0">' +
              '<div><span class="fc-cw-name">' + (c.author||'').replace(/[<>&"]/g,function(x){return{'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[x];}) + '</span>' +
              '<span class="fc-cw-meta">' + (c.created_at||'').replace(' ',' &nbsp;') + '</span>' +
              (c.pinned ? '<span class="fc-cw-meta" style="color:var(--warn)">置顶</span>' : '') +
              (c.rating ? '<div class="fc-cw-stars-sm">' + '★'.repeat(c.rating) + '☆'.repeat(5-c.rating) + '</div>' : '') +
            '</div>' +
            '<div class="fc-cw-text">' + (c.text||'').replace(/[<>&"]/g,function(x){return{'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[x];}) + '</div>' +
            '<button class="fc-cw-like" onclick="fcCommentLike(\'' + c.id + '\', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11v9M3 11h4l4-8a2 2 0 0 1 3 2v4h5a2 2 0 0 1 2 2l-1.5 7a2 2 0 0 1-2 2H7"/></svg><span>' + (c.likes||0) + '</span></button>' +
          '</div></div>';
        }).join('');
      });
  };
  window.fcCommentSubmit = function() {
    var input = document.getElementById('fcCwInput');
    var text = input.value.trim();
    if (!text) { alert('请输入内容'); return; }
    var btn = document.getElementById('fcCwSubmit');
    btn.disabled = true;
    var body = new FormData();
    body.append('type', w.FC_TYPE);
    body.append('target_id', w.FC_TARGET);
    body.append('text', text);
    if (w.FC_RATING && starVal) body.append('rating', starVal);
    fetch('/api/comment.php?action=add', { method: 'POST', body: body })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { input.value = ''; starVal = 0; w.fcLoad(); }
        else alert(d.error || '提交失败');
        btn.disabled = false;
      }).catch(function(){ alert('网络异常'); btn.disabled = false; });
  };
  window.fcCommentLike = function(id, el) {
    var body = new FormData(); body.append('comment_id', id);
    fetch('/api/comment.php?action=like', { method: 'POST', body: body })
      .then(function(r){ return r.json(); })
      .then(function(d){ if (d.ok) el.querySelector('span').textContent = d.likes; });
  };
  w.fcLoad();
})();
</script>
<?php
}
