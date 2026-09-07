/**
 * 画布真流程图渲染（原生 SVG 贝塞尔 + Pointer 拖拽，零依赖）
 * 与 admin/canvas.php 配合：
 *   - 读取 .canvas-flow[data-edges] + 各 .canvas-node[data-x][data-y]
 *   - 首次无坐标时自动分层布局
 *   - 节点可拖拽（更新 x/y + 隐藏 input node_x[]/node_y[]），实时重画连线
 */
(function () {
  (function init() {
    var flow = document.getElementById('canvasFlow');
    if (!flow) return;
    var svg = document.getElementById('canvasLinks');
    var nodes = Array.prototype.slice.call(flow.querySelectorAll('.canvas-node'));

    // ── 坐标归一化：无坐标的节点自动分层布局（仅初始加载的节点） ──
    if (nodes.length) {
      var hasPos = nodes.some(function (n) { return n.getAttribute('data-x') > 0 || n.getAttribute('data-y') > 0; });
      if (!hasPos) autoLayout(nodes);
      else clampIntoView(nodes, flow);
    }

    // ── 画连线（贝塞尔） ──
    var edges = [];
    try { edges = JSON.parse(flow.getAttribute('data-edges') || '[]'); } catch (e) {}
    function draw() {
      var byId = {};
      flow.querySelectorAll('.canvas-node').forEach(function (n) { byId[n.getAttribute('data-id')] = n; });
      svg.innerHTML = '';
      edges.forEach(function (e) {
        var from = byId[e.from], to = byId[e.to];
        if (!from || !to) return;
        var x1 = from.offsetLeft + from.offsetWidth / 2, y1 = from.offsetTop + 16;
        var x2 = to.offsetLeft + to.offsetWidth / 2, y2 = to.offsetTop + 16;
        var mid = Math.max(40, Math.abs(x2 - x1) / 2);
        var d = 'M ' + x1 + ' ' + y1 + ' C ' + (x1 + mid) + ' ' + y1 + ', ' + (x2 - mid) + ' ' + y2 + ', ' + x2 + ' ' + y2;
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', d);
        svg.appendChild(p);
        // 边标签（condition/variant）
        if (e.condition || e.variant) {
          var t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
          t.setAttribute('x', (x1 + x2) / 2);
          t.setAttribute('y', (y1 + y2) / 2 - 4);
          t.setAttribute('text-anchor', 'middle');
          t.textContent = e.condition || e.variant;
          svg.appendChild(t);
        }
      });
    }

    // ── 拖拽（Pointer Events，统一 register 处理初始+新增节点） ──
    var drag = null;
    nodes.forEach(function (n) {
      register(n);
    });

    // ── 新增节点也支持拖拽（addNode 创建后注册，自动分层布局，避免叠在原点） ──
    var origAddNode = window.addNode;
    window.addNode = function (type) {
      if (origAddNode) origAddNode(type);
      setTimeout(function () {
        var added = flow.querySelectorAll('.canvas-node');
        var last = added[added.length - 1];
        if (last) {
          placeNode(last);
          register(last);
          draw();
        }
      }, 0);
    };

    function register(n) {
      if (!n || n.__pg) return; n.__pg = true;
      n.setAttribute('draggable', 'false');   // 统一用 Pointer 拖拽，去掉页面版 HTML5 DnD
      n.removeAttribute('ondragstart'); n.removeAttribute('ondragover'); n.removeAttribute('ondrop');
      n.addEventListener('pointerdown', function (ev) {
        if (ev.target.closest('input,select,textarea,button')) return;
        drag = { node: n, ox: ev.clientX - n.offsetLeft, oy: ev.clientY - n.offsetTop };
        n.classList.add('dragging'); n.setPointerCapture && n.setPointerCapture(ev.pointerId); ev.preventDefault();
      });
      n.addEventListener('pointermove', function (ev) {
        if (!drag || drag.node !== n) return;
        var x = Math.max(0, Math.round(ev.clientX - drag.ox - flow.offsetLeft + flow.scrollLeft));
        var y = Math.max(0, Math.round(ev.clientY - drag.oy - flow.offsetTop + flow.scrollTop));
        n.style.left = x + 'px'; n.style.top = y + 'px';
        n.setAttribute('data-x', x); n.setAttribute('data-y', y);
        var hx = n.querySelector('input[name="node_x[]"]'); if (hx) hx.value = x;
        var hy = n.querySelector('input[name="node_y[]"]'); if (hy) hy.value = y;
        draw();
      });
      n.addEventListener('pointerup', function () { drag = null; n.classList.remove('dragging'); });
      n.addEventListener('pointercancel', function () { drag = null; n.classList.remove('dragging'); });
    }

    // 给新节点一个不重叠的初始位置：参考 autoLayout 网格，放在已有节点之后
    function placeNode(n) {
      var count = flow.querySelectorAll('.canvas-node').length - 1;  // 不含本节点
      var x = 30 + (count % 3) * 280;
      var y = 30 + Math.floor(count / 3) * 130;
      // 若坐标已是 0（页面版 addNode 默认 0），才重新定位
      if ((+n.getAttribute('data-x') || 0) === 0 && (+n.getAttribute('data-y') || 0) === 0) {
        n.style.left = x + 'px'; n.style.top = y + 'px';
        n.setAttribute('data-x', x); n.setAttribute('data-y', y);
        var hx = n.querySelector('input[name="node_x[]"]'); if (hx) hx.value = x;
        var hy = n.querySelector('input[name="node_y[]"]'); if (hy) hy.value = y;
      }
    }

    function autoLayout(arr) {
      arr.forEach(function (n, i) {
        var x = 30 + (i % 3) * 280, y = 30 + Math.floor(i / 3) * 130;
        n.style.left = x + 'px'; n.style.top = y + 'px';
        n.setAttribute('data-x', x); n.setAttribute('data-y', y);
      });
    }
    function clampIntoView(arr, flow) {
      arr.forEach(function (n) {
        var x = +n.getAttribute('data-x') || 0, y = +n.getAttribute('data-y') || 0;
        n.style.left = x + 'px'; n.style.top = y + 'px';
      });
    }

    // 初始画线（DOM ready 后节点有尺寸）
    setTimeout(draw, 50);

    // ── 全屏编辑（把画布容器撑满视口，便于拖拽/布局） ──
    var fsBtn = document.getElementById('canvasFullscreen');
    if (fsBtn) {
      fsBtn.addEventListener('click', function () {
        var card = flow.closest('.card') || flow.parentElement;
        var isFs = card.classList.contains('canvas-fs');
        if (!isFs) {
          if (card.requestFullscreen) { card.requestFullscreen(); }
          card.classList.add('canvas-fs');
          fsBtn.textContent = '↩ 退出全屏';
        } else {
          if (document.exitFullscreen) { document.exitFullscreen(); }
          card.classList.remove('canvas-fs');
          fsBtn.textContent = '⛶ 全屏';
        }
      });
      // 监听 Esc 退出全屏
      document.addEventListener('fullscreenchange', function () {
        var card = flow.closest('.card') || flow.parentElement;
        if (!document.fullscreenElement) {
          card.classList.remove('canvas-fs');
          if (fsBtn) fsBtn.textContent = '⛶ 全屏';
        }
      });
    }
  })();
})();
