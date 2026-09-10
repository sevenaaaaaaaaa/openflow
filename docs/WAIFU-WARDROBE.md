# 看板娘衣橱系统（E4 · 2026-09-10）

后台右下角的 Live2D 助手支持多形象换装。右键角色 →「换装 · 衣橱」即时切换，
选择记 localStorage（每人每浏览器独立）；全站默认在「设置 → 系统设置 → 看板娘默认形象」。

## 当前阵容

| ID | 名字 | 风格 | 语气 |
|---|---|---|---|
| `mao`（默认） | 玛奥 | 成熟御姐 · 魔女 | 干练自信（"有事直说"） |
| `ren` | 莲 | 中性青年 | 冷静简洁 |
| `natori` | 名取 | 西装男性 | 商务专业 |
| `mark` | 马克 | 休闲男性 | 随和直爽 |
| `hiyori` | 日和 | 经典少女 | 活泼可爱 |

全部为 Live2D 官方免费示例模型（Live2D Free Material License，可商用），
自托管于 `assets/vendor/live2d/model/`，无外部依赖。

## 想换成「黑丝女武神」等定制造型？

官方示例库里没有完全匹配的造型，需要约稿或购买成品模型（推荐渠道）：

- **nizima**（Live2D 官方市场，nizima.com）—— 搜「女武神」「戦士」「メイド」等关键词，
  买「汎用モデル」授权即可商用，价格约 ¥3,000–30,000 日元
- **Booth**（booth.pm）—— 大量独立模型师作品，注意筛选「Live2D Cubism 4.x / 5.x」格式
- **约稿定制** —— 完全贴合人设（身材/服装/黑丝/武器配件），预算约 ¥5,000–50,000 人民币

### 接入步骤（拿到模型后 5 分钟上线）

1. 模型目录（含 `*.model3.json` / `*.moc3` / 纹理 / 动作）整个放进
   `assets/vendor/live2d/model/valkyrie/`（目录名小写）
2. 在 `assets/admin-waifu.js` 的 `WARDROBE` 注册一行：

   ```js
   valkyrie: {
     name: '瓦尔基里', file: 'valkyrie/Valkyrie.model3.json', tag: '御姐 · 女武神',
     hello: '战斗准备完毕。', tapChat: '下令吧。', tapOnly: '嗯？',
     thinking: '分析中…', done: '任务完成。', fail: '出现阻碍。',
     hidden: '随时待命。'
   },
   ```

   再把 `'valkyrie'` 加进 `ORDER` 数组。
3. 完成。右键衣橱和设置页下拉会自动出现新形象（设置页选项在
   `admin/settings.php` 的 `waifu_model` 下拉里补一行）。

### 技术要求

- 必须是 **Cubism 4.x/5.x** 导出的 `.model3.json`（Cubism 2 的 `.model.json` 不支持）
- 动作组建议含 `Idle` 和 `TapBody`（没有也能跑，只是点击/待机动作不播）
- 模型原始尺寸不限，系统按画布高度自适应缩放
- 纹理建议 ≤2048px，整目录 ≤10MB（后台页面懒加载，太大影响首次出现速度）

## 文件清单

- `assets/admin-waifu.js` — 衣橱注册表 + 换装逻辑 + 性格化气泡
- `assets/vendor/live2d/model/{hiyori,mao,mark,natori,ren}/` — 模型文件
- `admin/config.php` — `.of-waifu-menu` 菜单样式 + `OF_WAIFU_CONFIG` 默认值注入
- `admin/settings.php` — 全站默认形象设置项
