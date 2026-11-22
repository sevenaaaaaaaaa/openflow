# 客户方案 deck（pitch/）

> 一份可以拿去 pitch 客户的 23 页方案。位置：\`pitch/index.html\`（纯静态，不依赖 PHP）。
> 部署后可访问：\`https://nownexts.com/pitch/\`。
>
> 设计语言沿用站内既有的 deck 体系（\`gtm-deck.html\` + \`lib/DeckThemes.php\` 的编辑式风格）：
> 纸张底色、衬线标题、等宽元数据的页脚、单一强调色。字体取自 \`assets/fonts/\`（Newsreader /
> JetBrains Mono / Space Grotesk），与站点同源，不请求 Google Fonts。

---

## 一、它和别的 pitch deck 有什么不同

**每一页页脚都有一行证据出处。** 这不是装饰——deck 里所有数字都能在代码仓库里指出来：

| 页面 | 论点 | 数字 / 说法的出处 |
|---|---|---|
| P03 处境 | 五个工具五份工作 | \`docs/GTM.md\` §1.2 |
| P05 范式 | Flow 手动挡 / Loop 自动挡 | \`docs/VISION.md\` |
| P06 矩阵 | 三层十二件，本册九件 | \`docs/NAMING.md\` §2、\`docs/PRODUCT-MATRIX.md\` §0 |
| P07 架构 | 拔掉任何一个其余照常 | \`docs/PRODUCT-MATRIX.md\` §1.2（MatrixTicket） |
| P08 底座 | 197 / 121 / 23 / 170 | \`php scripts/metrics.php\`（2026-11-22） |
| P09 MFlow | 22 源 / 10 语言 / 3 轮 | \`mflow/README.md\` · VERSION 1.2.0 |
| P10 inFlow | 12 源 / 8 模型 / 6 适配器 / 33 REST / 10 MCP / 800+ 测试 | \`inflow/README.md\` |
| P11 UserLoop | 0.6.2 / 209 测试 / 8 阶段 / 5 模板 | \`userloop/README.md\` · VERSION |
| P12 WebsFlow | 44 模块 / 13 模板 / 4.42.0 | \`websflow/VERSION\` · README |
| P13 PayFlow | 1.0.7 / 2026-09-17 起生产 / 4 通道 / 137 自检 | \`payflow/README.md\` |
| P14 LearnFlow | 1.0.0 / 154 回归 / 42 工具 / R.B.E 训练营 | \`learnflow/README.md\` |
| P15 Studio | Litmus 0.34.1、ConFlow 30 主题 | \`gh release list\`、\`V2HTML/README.md\` |
| P17 证据 | 四个可核验交付 + 三个「没有」 | \`docs/FACTBASE-2026-11.md\` §3 |
| P18 规划 | 现在 / 90 天 / 要你拍板 | \`CHANGELOG.md\` Unreleased、各仓库 release |

完整的核验清单（含口径冲突与缺口）在 [FACTBASE-2026-11.md](FACTBASE-2026-11.md)。

---

## 二、放映与导出

| 操作 | 键 |
|---|---|
| 下一页 / 上一页 | \`→\` \`←\` \`空格\` \`PgUp/PgDn\` |
| 首页 / 末页 | \`Home\` / \`End\` |
| 全屏 | \`F\` |
| 导出 PDF | \`P\`（浏览器打印 → 另存为 PDF） |
| 深链某一页 | \`/pitch/#12\` |

- 打印版式已写好：\`@page { size: 1600px 900px }\`，一页一张 slide，背景色不丢（\`print-color-adjust: exact\`）。
- 窄屏（≤900px）自动改为纵向滚动阅读，便于微信里直接发链接。
- 除首屏外，所有动画都可被 \`prefers-reduced-motion\` 关掉。

**已预生成的 PDF**：`pitch/understory-pitch-2026-11.pdf`（23 页，1600×900 等比页面 / MediaBox 1200×675pt，字体已内嵌，1.9 MB）。
重新生成（改了 HTML 之后）：

\`\`\`bash
# 任选其一
# 1) 浏览器打开 pitch/index.html，按 P → 另存为 PDF（最稳）
# 2) 有 Chrome / Chromium 时：
chrome --headless=new --disable-gpu --no-pdf-header-footer \
  --print-to-pdf=pitch/林下客户方案-2026-11.pdf \
  "file://$PWD/pitch/index.html"
\`\`\`

---

## 三、部署

deck 是纯静态文件，走既有唯一入口即可：

\`\`\`bash
bash deploy/sync.sh -m "docs(pitch): 客户方案 deck + 史实底座" \
  pitch/index.html docs/PITCH-DECK.md docs/FACTBASE-2026-11.md
\`\`\`

- Apache 的 \`DirectoryIndex\` 是 \`index.php index.html\`；\`pitch/\` 下没有 php，所以 \`/pitch/\` 直接命中 \`index.html\`。
- 字体走相对路径 \`../assets/fonts/\`，与站点共用，不额外上传。
- 想挂到 \`/deck/{id}\` 放映系统也可以：把每页的 kind / 文案搬进 \`data/slide-decks.json\` 即可（但那套版式没有证据页脚，建议保留本文件为主版本）。

---

## 四、改内容时的纪律

1. **先改 [FACTBASE-2026-11.md](FACTBASE-2026-11.md)，再改 deck。** 任何数字只允许有一个来源。
2. 新增说法先问：客户能不能自己打开仓库核验？不能核验的，换成可核验的差异，或直接删。
3. 禁用词（沿用 \`docs/BRAND-VOICE.md\`）：唯一 / 首个 / 最强 / 领先 / 提升 X% / 增长 Y 倍 / 400+ 连接器。
4. 客户 logo、合同金额、ROI 百分比：**没有授权就不放**，这是这一版最硬的一条。

---

## 五、已知缺口

- **PDF 的打印版式与屏幕版式不完全一致**：打印时内容靠上（浏览器 paged media 下的 flex 居中表现不同），页数、分页、字号都正确；正式外发前建议在浏览器里按一次 P 目检。
- **无客户 logo 墙**：等第一个可署名客户（含授权书）。
- **无价格页**：商务页只给计价口径，不给数字；要有价目表需先做产品定价决策（见 FACTBASE §5）。
- **ConFlow 主题数**：正文写 30、徽章写 32，deck 取 30；两边统一后再改。
- **第 18 页右列**是「要你拍板」的事项，不是承诺——对外讲之前先确认口径。
