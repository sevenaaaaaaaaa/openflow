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
    .fc-comment-widget{font-family:inherit}
    .fc-cw-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
    .fc-cw-title{font-size:18px;font-weight:700}
    .fc-cw-rating-big{font-size:15px;color:#b45309;font-weight:600}
    .fc-cw-box{background:#fff;border:1px solid #e2dfd2;border-radius:14px;padding:16px;margin-bottom:16px}
    .fc-cw-stars{display:flex;gap:4px;font-size:26px;cursor:pointer;user-select:none}
    .fc-cw-stars span{color:#d1d5db;transition:.1s}
    .fc-cw-stars span.on{color:#f59e0b}
    .fc-cw-stars span.sel{color:#f59e0b}
    .fc-cw-input{width:100%;border:1.5px solid #e2dfd2;border-radius:10px;padding:10px 14px;font-size:14px;font-family:inherit;min-height:80px;resize:vertical;outline:none}
    .fc-cw-input:focus{border-color:#2b5f7e}
    .fc-cw-submit{background:#1e1e1e;color:#ddff0e;border:none;border-radius:10px;padding:9px 20px;font-weight:600;font-size:13px;cursor:pointer}
    .fc-cw-submit:disabled{opacity:.5;cursor:not-allowed}
    .fc-cw-item{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid #f0eee6}
    .fc-cw-item:last-child{border-bottom:none}
    .fc-cw-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#7dd3fc,#86efac);display:grid;place-items:center;font-size:15px;flex-shrink:0}
    .fc-cw-name{font-size:13px;font-weight:700}
    .fc-cw-meta{font-size:11px;color:#9a94ac;margin-left:6px}
    .fc-cw-stars-sm{color:#f59e0b;font-size:12px;letter-spacing:1px}
    .fc-cw-text{font-size:14px;line-height:1.7;color:#3d3a4a;margin-top:4px;word-break:break-word}
    .fc-cw-like{background:none;border:none;color:#9a94ac;font-size:12px;cursor:pointer;margin-top:8px;padding:0}
    .fc-cw-like:hover{color:#2b5f7e}
    .fc-cw-empty{color:#9a94ac;font-size:13px;padding:20px 0;text-align:center}
    .fc-cw-msg{font-size:13px;margin-top:8px}
  </style>

  <div class="fc-cw-header">
    <span class="fc-cw-title">💬 <?=htmlspecialchars($title)?><?php if ($ratingEnabled): ?>点评<?php endif; ?></span>
    <?php if ($ratingEnabled): ?>
    <span class="fc-cw-rating-big">⭐ <?=$summary['avg'] > 0 ? number_format($summary['avg'], 1) : '—'?> <span style="color:#9a94ac;font-weight:400">(<?=$summary['count']?> 人评分)</span></span>
    <?php endif; ?>
    <span class="fc-cw-rating-big" style="color:#9a94ac;font-weight:400"><?=$summary['total']?> 条</span>
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
      <span class="fc-cw-msg" style="color:#9a94ac"><?php if ($member): ?>登录为 <?=htmlspecialchars($member['name'] ?? '')?><?php else: ?><a href="/member.php?view=login" style="color:#2b5f7e">登录</a>后可评论<?php endif; ?></span>
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
              (c.pinned ? '<span class="fc-cw-meta" style="color:#b45309">📌 置顶</span>' : '') +
              (c.rating ? '<div class="fc-cw-stars-sm">' + '★'.repeat(c.rating) + '☆'.repeat(5-c.rating) + '</div>' : '') +
            '</div>' +
            '<div class="fc-cw-text">' + (c.text||'').replace(/[<>&"]/g,function(x){return{'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[x];}) + '</div>' +
            '<button class="fc-cw-like" onclick="fcCommentLike(\'' + c.id + '\', this)">👍 <span>' + (c.likes||0) + '</span></button>' +
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
