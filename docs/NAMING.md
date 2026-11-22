# 命名规范 v1（2026-11-23）

> 唯一目的：同一个产品在任何地方都叫同一个名字。品牌名已经存在（见下表），本文件只做「定名 + 用在哪」。
> 任何新页面/文案/仓库描述先查本表；表里没有的名字，先加表再用。

## 一、主品牌

- **林下 Understory**：品牌与矩阵之名（站点、社区、课程、方法论）。
- **OpenFlow**：开源底座 / 入口层产品；代码、仓库、技术文档用它。
- 旧名 **「芭乐派」作废**，不再出现在任何对外描述里。

## 二、官方名（12 件，中文名 = 品牌名，拉丁名 = 产品名）

| 层 | 中文品牌名 | 拉丁产品名 | 产品页 slug | 仓库 | 应用路径 |
|---|---|---|---|---|---|
| 入口层 | 开务 | **OpenFlow** | `/product/openflow` | `openflow` | `/` |
| 进阶层 | 风媒 | **MFlow** | `/product/mflow` | `mflow` | `/mflow/` |
| 进阶层 | 魔块 | **WebsFlow** | `/product/webs-flow` | `websflow` | `/webflow/` |
| 进阶层 | 触级 | **UserLoop** | `/product/userloop` | `userloop` | `/userloop/` |
| 进阶层 | 洞澜 | **inFlow** | `/product/inflow` | `inflow` | `/inflow/` |
| 进阶层 | 芭乐 Pay | **PayFlow** | `/product/payflow` | `payflow` | `/payflow/` |
| 进阶层 | 林下学院 | **LearnFlow** | `/product/learnflow` | `learnflow` | `/learnflow/` |
| 工作台层 | 鹿蕊 | **Litmus** | `/product/litmus` | `litmus` | `/litmus/` |
| 工作台层 | 松萝 | **Liana** | `/product/liana` | `liana` | 无（下载） |
| 工作台层 | 成章 | **ConFlow** | `/product/conflow` | `V2HTML`（引擎仓库） | `/VTH/`（经 Litmus 加工） |
| 工作台层 | 林可兔 | **LinkTo** | `/product/linkto` | `linkto` | 无（下载） |
| 工作台层 | 零真 | **ZeroZen** | `/product/zerozen` | `zerozen` | 无（下载） |

## 三、使用规则

1. **首次出现写全**：`风媒 MFlow`、`魔块 WebsFlow`……之后同一页只用拉丁名（`MFlow`）。
2. **代码/仓库/技术文档**：只用拉丁名（OpenFlow、MFlow、Litmus…），不写中文别名。
3. **站点产品页 H1 / title**：沿用现有拉丁名 + 必要中文（如「WebsFlow 魔块」），不新造别名。
4. **拼写唯一**：
   - WebsFlow 的页面 slug 是 `webs-flow`；`/product/websflow` 是 404，不得再用。
   - 应用路径是 `/webflow/`，不是 `/webs-flow/`。
   - inFlow 的包名 `insflow` 仅内部保留，对外一律 `inFlow`。
5. **ConFlow 归属**：对外品牌 `ConFlow（成章）`，引擎仓库 `V2HTML`；运行入口在 Litmus「加工」，产品页按 A 方案保留并标注「引擎已并入 Litmus」。
6. **GitHub 仓库描述**：统一以 `林下 Understory 产品矩阵` 结尾，禁止出现「芭乐派」。
7. **版本表述**：产品版本以仓库根 `VERSION` 或 Cargo/package 版本为准；发布时 tag 与版本一致（详见各复盘卡）。

## 四、当前落点（维护清单）

- `README.md` 产品矩阵：中文别名 + 拉丁名的唯一“全写”位置。
- `docs/PRODUCT-MATRIX.md`：定位共识，命名以本文件为准。
- GitHub 12 个仓库 description：2026-11-23 已按本表更新（旧「芭乐派」清零）。
- 站点 12 个 `/product/*` 页：产品页名与上表一致；`/product/websflow` 保持 404。

## 五、待办（不在本文件职责内）

- 产品页正文若出现旧别名，按 B9/A2 行动项逐页替换（改生产 `builder-pages`，需走后台）。
- `docs/NAMING.md` 变更后，README 与 PRODUCT-MATRIX 的指向不需要改（都指向本文件）。
