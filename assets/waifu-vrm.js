/* ─────────────────────────────────────────────────────────────────────────
 * OpenFlow · waifu-vrm.js — 3D VRM 看板娘渲染器
 *
 * 由 admin-waifu.js（主壳）按需加载：仅当用户选择 3D 形态时才会请求本文件
 * 与运行库 bundle（three r170 + @pixiv/three-vrm v3，单文件 ESM，懒 import）。
 *
 * mount(canvas, opts) → Promise<controller>
 *   opts: { width, height, onProgress(pct) }
 *   controller: {
 *     react(group)      — 'TapBody' 点头/歪头小动作；'Idle' 微调姿
 *     focus(pageX, pageY) — 视线跟随（页面绝对坐标）
 *     setModel(file)    — 换 3D 形象（相对 assets/vendor/vrm/model/ 的路径）
 *     destroy()         — 释放 WebGL 资源与渲染循环
 *   }
 *
 * 程序化生命感：呼吸 / 拧头待机 / 自动眨眼 / 视线跟随 / 点按小动作 /
 * happy 表情衰减；头发与机械臂物理由 VRM SpringBone 自带（vrm.update）。
 * 标签页隐藏时暂停渲染循环；DPR 上限 2 防 4K 屏过载。
 * ─────────────────────────────────────────────────────────────────────── */
(function () {
  if (window.OFWaifuVRM) return;
  var BUNDLE = '/assets/vendor/vrm/three-vrm.bundle.min.mjs';
  var BASE = '/assets/vendor/vrm/model/';

  var libPromise = null;
  function lib() {
    if (!libPromise) libPromise = import(BUNDLE);
    return libPromise;
  }

  function mount(canvas, opts) {
    opts = opts || {};
    var W = opts.width || 230, H = opts.height || 330;
    var token = {};                       // 防 destroy 后异步回调复活
    var renderer, scene, camera, vrm = null, em = null;
    var head, spine, chest, hips, baseHipsY;
    var lookTarget = null, loader = null;
    var raf = 0, t = 0, last = 0;
    var nextBlink = 1.4, blinkT = -1;
    var emote = null, happyW = 0;

    function pct(p) { try { opts.onProgress && opts.onProgress(p); } catch (e) {} }
    function setExpr(name, v) { if (em) { try { em.setValue(name, v); } catch (e) {} } }
    function hasExpr(name) { try { return !!(em && em.getExpression(name)); } catch (e) { return false; } }

    /* ── 初始化 three 场景（一次）── */
    return lib().then(function (m) {
      if (token.dead) return null;
      renderer = new m.THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
      renderer.setClearColor(0x000000, 0);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      renderer.setSize(W, H, false);
      renderer.outputColorSpace = m.THREE.SRGBColorSpace;

      scene = new m.THREE.Scene();
      camera = new m.THREE.PerspectiveCamera(18, W / H, 0.05, 20);
      camera.position.set(0.03, 1.16, 1.42);
      camera.lookAt(0, 1.15, 0);

      scene.add(new m.THREE.AmbientLight(0xffffff, 0.75));
      var key = new m.THREE.DirectionalLight(0xffffff, 1.15);
      key.position.set(1.5, 2.4, 2.2); scene.add(key);
      var rim = new m.THREE.DirectionalLight(0xcfe0ff, 0.5);
      rim.position.set(-2.2, 1.8, -1.0); scene.add(rim);

      lookTarget = new m.THREE.Object3D();
      lookTarget.position.set(0, 1.16, camera.position.z - 0.9);
      scene.add(lookTarget);

      loader = new m.GLTFLoader ? new m.GLTFLoader() : null;
      if (!loader) throw new Error('bundle export missing');
      loader.register(function (p) { return new m.VRMLoaderPlugin(p); });

      /* ── 模型加载 + 装配 ── */
      function loadModel(file) {
        return new Promise(function (res, rej) {
          loader.load(BASE + file, function (gltf) {
            if (token.dead) return;
            var nv = gltf.userData.vrm;
            if (!nv) { rej(new Error('not a vrm')); return; }
            m.VRMUtils.rotateVRM0(nv);          // VRM0 朝向修正，VRM1 无操作
            if (vrm) {                          // 换形象：释放旧模型
              scene.remove(vrm.scene);
              try { m.VRMUtils.deepDispose(vrm.scene); } catch (e) {}
            }
            vrm = nv;
            scene.add(vrm.scene);
            em = vrm.expressionManager || null;
            var hu = vrm.humanoid;
            head = hu && hu.getNormalizedBoneNode('head');
            spine = hu && hu.getNormalizedBoneNode('spine');
            chest = hu && hu.getNormalizedBoneNode('chest');
            hips = hu && hu.getNormalizedBoneNode('hips');
            baseHipsY = hips ? hips.position.y : 0;
            if (vrm.lookAt) vrm.lookAt.target = lookTarget;
            // 构图：半身像，头顶留 8% 边距、下沿到胸腹（对齐 2D 立绘的满幅观感）
            try {
              var bb = new m.THREE.Box3().setFromObject(vrm.scene);
              var headY = head ? head.getWorldPosition(new m.THREE.Vector3()).y : bb.max.y * 0.93;
              var span = 0.62;                  // 画面竖向覆盖
              var centerY = headY + 0.1 - span * 0.42;
              var dist = (span / 2) / Math.tan((camera.fov / 2) * Math.PI / 180);
              camera.position.set(0.03, centerY, dist);
              camera.lookAt(0.0, centerY, 0);
              lookTarget.position.set(0, centerY - 0.02, dist - 0.9);
            } catch (e) {}
            if (!raf) tick(0);
            res(vrm);
          }, function (xhr) {
            if (xhr && xhr.total) pct(Math.min(100, Math.round(xhr.loaded / xhr.total * 100)));
          }, rej);
        });
      }

      /* ── 渲染循环 ── */
      function tick(now) {
        raf = requestAnimationFrame(tick);
        var dt = last ? Math.min((now - last) / 1000, 0.05) : 0.016;
        last = now; t += dt;
        if (!vrm) return;

        // 待机：呼吸 + 头部缓摆
        var br = Math.sin(t * 1.7);
        if (chest) chest.rotation.x = br * 0.022;
        if (spine) spine.rotation.x = br * 0.012;
        if (head) {
          head.rotation.set(
            Math.sin(t * 1.05) * 0.016 + Math.sin(t * 0.4) * 0.012,
            Math.sin(t * 0.55) * 0.05,
            Math.sin(t * 0.33 + 1.3) * 0.02
          );
        }
        if (hips) hips.position.y = baseHipsY + br * 0.004;

        // 点按小动作（0→1→0 包络）
        if (emote) {
          var k = Math.sin(Math.min(emote.t / emote.dur, 1) * Math.PI);
          if (emote.type === 'nod' && head) head.rotation.x += k * 0.17;
          if (emote.type === 'tilt' && head) head.rotation.z += k * 0.15;
          if (emote.type === 'bounce' && hips) hips.position.y = baseHipsY + k * 0.02;
          emote.t += dt;
          if (emote.t >= emote.dur) emote = null;
        }

        // 自动眨眼
        if (t > nextBlink) { blinkT = 0; nextBlink = t + 2.2 + Math.random() * 3.8; }
        if (blinkT >= 0) {
          blinkT += dt;
          var p = blinkT / 0.14;
          if (p >= 1) { setExpr('blink', 0); blinkT = -1; }
          else setExpr('blink', Math.sin(p * Math.PI));
        }

        // happy 表情衰减（点按/回复时点亮）
        if (happyW > 0) {
          setExpr('happy', happyW);
          happyW *= Math.pow(0.015, dt);
          if (happyW < 0.02) { happyW = 0; setExpr('happy', 0); }
        }

        vrm.update(dt);
        renderer.render(scene, camera);
      }
      raf = requestAnimationFrame(tick);

      var controller = {
        react: function (group) {
          if (group === 'TapBody') {
            var pool = ['nod', 'tilt', 'bounce'];
            emote = { type: pool[Math.floor(Math.random() * pool.length)], t: 0, dur: 0.55 };
            if (hasExpr('happy')) happyW = 0.85;
          }
          // 'Idle' 无需处理：待机是连续程序动画
        },
        focus: function (pageX, pageY) {
          if (!lookTarget || !canvas.isConnected) return;
          var r = canvas.getBoundingClientRect();
          var nx = ((pageX - r.left) / r.width) * 2 - 1;
          var ny = 1 - ((pageY - r.top) / r.height) * 2;
          nx = Math.max(-3.2, Math.min(3.2, nx));
          ny = Math.max(-2.6, Math.min(2.6, ny));
          lookTarget.position.x += ((camera.position.x + nx * 0.5) - lookTarget.position.x) * 0.2;
          lookTarget.position.y += ((camera.position.y + ny * 0.32 + 0.08) - lookTarget.position.y) * 0.2;
          lookTarget.position.z = camera.position.z - 0.9;
        },
        setModel: loadModel,
        destroy: function () {
          token.dead = true;
          cancelAnimationFrame(raf); raf = 0;
          if (vrm) { try { scene.remove(vrm.scene); m.VRMUtils.deepDispose(vrm.scene); } catch (e) {} }
          try { renderer.dispose(); } catch (e) {}
        }
      };

      // 预热表情存在性（happy 不存在时上面自动跳过）
      return { ctrl: controller, load: loadModel };
    });
  }

  window.OFWaifuVRM = {
    mount: function (canvas, opts) {
      return mount(canvas, opts).then(function (r) {
        if (!r) throw new Error('dead');
        return r.load(opts.file || 'seed/Seed-san.vrm').then(function () { return r.ctrl; });
      });
    }
  };
})();
