# 看板娘衣橱系统（v3 · 2D + 3D）

后台右下角助手支持两种形态，右键角色 →「换装 · 衣橱」随时切换：

- **2D 看板娘（Live2D）**：六位形象秒开，默认形态
- **3D 助手（VRM）**：three.js + @pixiv/three-vrm，立体渲染，首载约 12MB（运行库 0.9MB + 模型 10.9MB，仅选 3D 时才下载）

个人选择（形态 / 2D 形象 / 3D 形象）记 localStorage，随全站 ver 失效；
全站默认（形态 + 2D 形象）在「设置 → 品牌设置」，改动保存后覆盖所有浏览器的个人选择。

## 当前阵容

| 形态 | ID | 名字 | 风格 | 语气 |
|---|---|---|---|---|
| 3D | `seed` | 希德 | 科技 · 少年（Seed-san） | 极客简洁（"系统就绪。今天从哪开始？"） |
| 2D（默认） | `rice` | 璃丝 | 成熟御姐 · 黑裙 | 从容撩人 |
| 2D | `mao` | 玛奥 | 猫娘 · 少女 | 慵懒俏皮 |
| 2D | `ren` | 莲 | 中性青年 | 冷静简洁 |
| 2D | `natori` | 名取 | 西装男性 | 商务专业 |
| 2D | `mark` | 马克 | 休闲男性 | 随和直爽 |
| 2D | `hiyori` | 日和 | 经典少女 | 活泼可爱 |

### 3D 形象的授权（重要）

**Seed-san** © VirtualCast, Inc.，[VRM Public License 1.0](https://vrm.dev/licenses/1.0/)。
模型内嵌 meta 明确允许：**法人商用、修改、再分发**；**要求署名**（creditNotation: required）。
署名展示位置：右键菜单底部小字（3D 形态时）+ `assets/vendor/vrm/model/seed/CREDITS.md`。
来源：[vrm-c/vrm-specification · samples](https://github.com/vrm-c/vrm-specification/tree/master/samples/Seed-san)（VRM 官方样例库）。

## 3D 技术要点

- 运行库：`assets/vendor/vrm/three-vrm.bundle.min.mjs`（853KB，three r170 + @pixiv/three-vrm v3，
  esbuild 单文件 ESM bundle，懒 `import()`，2D 用户零下载）
- 渲染器：`assets/waifu-vrm.js`（`window.OFWaifuVRM.mount(canvas, opts)`，与 2D 同接口）
- 程序化生命感：呼吸 / 头部缓摆 / 自动眨眼 / 视线跟随鼠标 / 点按点头歪头 / happy 表情衰减；
  头发与机械臂物理由 VRM SpringBone 自带
- 性能：DPR 上限 2；标签页隐藏暂停渲染循环；换形态完整释放 WebGL 上下文
- 降级：与 2D 相同（窄屏 / 减少动态 / 无 WebGL / 会话内隐藏 → 旧圆形 FAB）

## 想 3D 加形象？

把任何 **VRM 0.x / 1.0** 的 `.vrm` 文件放进 `assets/vendor/vrm/model/<id>/`（目录小写），
并在 `assets/admin-waifu.js` 的 `VRM_WARDROBE` 注册一行、`VORDER` 加 ID（参照 seed）。
模型需自带 humanoid 骨架；表情/眨眼自动探测，有则用、无则跳过。**注意确认模型的商用授权**
（VRM Public License 的商用/再分发权限逐模型不同，meta 内嵌的 licenseUrl 可查）。

2D（Live2D）加形象的方式不变：Cubism 4.x 模型目录放进 `assets/vendor/live2d/model/`，
`WARDROBE` 注册 + `ORDER` 加 ID + `admin/settings.php` 下拉补一行。购买渠道见 nizima / Booth。

## 文件清单

- `assets/admin-waifu.js` — 主壳：衣橱注册表 + 形态切换 + 菜单 + 性格化气泡 + 降级
- `assets/waifu-vrm.js` — 3D 渲染器（懒加载）
- `assets/vendor/vrm/` — 运行库 bundle + 模型（R2 静态资产，发布走 `sync-r2.py`）
- `assets/vendor/live2d/` — 2D 运行库 + 六个模型
- `admin/config.php` — `OF_WAIFU_CONFIG`（default/mode/ver）注入 + 菜单样式 + `OF_ADMIN_UI_VER`
- `admin/settings.php` — 全站默认形态 + 2D 默认形象设置项
- `tests/visual/waifu-preview.html` — 预览页（`?m=vrm` 看 3D 转体/表情清单，`?m=<file>` 看 2D）
