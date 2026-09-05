/**
 * 画布真流程图渲染（原生 SVG 贝塞尔 + Pointer 拖拽，零依赖）
 * 与 admin/canvas.php 配合：
 *   - 读取 .canvas-flow[data-edges] + 各 .canvas-node[data-x][data-y]
 *   - 首次无坐标时自动分层布局
 *   - 节点可拖拽（更新 x/y + 隐藏 input node_x[]/node_y[]），实时重画连线
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var flow = document.getElementById('canvasFlow');
    if (!flow) return;
    var svg = document.getElementById('canvasLinks');
    var nodes = Array.prototype.slice.call(flow.querySelectorAll('.canvas-node'));
    if (!nodes.length) return;

    // ── 坐标归一化：无坐标的节点自动分层布局 ──
    var hasPos = nodes.some(function (n) { return n.getAttribute('data-x') > 0 || n.getAttribute('data-y') > 0; });
    if (!hasPos) autoLayout(nodes);
    else clampIntoView(nodes, flow);

    // ── 画连线（贝塞尔） ──
    var edges = [];
    try { edges = JSON.parse(flow.getAttribute('data-edges') || '[]'); } catch (e) {}
    function draw() {
      var byId = {};
      nodes.forEach(function (n) { byId[n.getAttribute('data-id')] = n; });
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

    // ── 拖拽（Pointer Events） ──
    var drag = null;
    nodes.forEach(function (n) {
      n.setAttribute('draggable', 'false');   // 禁用 HTML5 DnD(与Pointer拖拽冲突)
      n.addEventListener('pointerdown', function (ev) {
        if (ev.target.closest('input,select,textarea,button')) return;   // 表单元件不拖
        drag = { node: n, ox: ev.clientX - n.offsetLeft, oy: ev.clientY - n.offsetTop };
        n.classList.add('dragging');
        n.setPointerCapture && n.setPointerCapture(ev.pointerId);
        ev.preventDefault();
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
    });

    // ── 新增节点也支持拖拽（addNode 创建后注册） ──
    var origAddNode = window.addNode;
    window.addNode = function (type) {
      if (origAddNode) origAddNode(type);
      setTimeout(function () {
        var added = flow.querySelectorAll('.canvas-node');
        register(added[added.length - 1]);
        var last = added[added.length - 1];
        if (last && !last.getAttribute('data-x')) { last.style.left = (40 + Math.random() * 100) + 'px'; last.style.top = (40 + Math.random() * 100) + 'px'; }
      }, 0);
    };

    function register(n) {
      if (!n || n.__pg) return; n.__pg = true;
      n.setAttribute('draggable', 'false');
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
  });
})();
