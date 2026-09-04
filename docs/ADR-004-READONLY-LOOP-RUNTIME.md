# ADR-004：只读 Observe → TIPS Plan 单轮运行时

状态：已实施（第一切片）

## 决策

Loop Runtime 首先作为确定性的只读编排器落地。输入必须包含有效的 `LoopDefinition`、`Goal`、`Policy` 和现有证据投影；输出固定包含 Touch、Insight、Personalize、Sell 四段业务计划。

候选行动只能进入 Action Gateway dry-run。这个切片不调用大模型，不调用 Flow、Skill 或领域执行器，不审批行动，不持久化 Loop 状态，也不修改任何客户、内容、订单或营销数据。

## 原因

先固定证据、目标、策略、TIPS 计划和行动建议之间的可审计关系，才能安全引入模型与多轮循环。模型未来可以增强策略质量，但不能改变 Runtime 的权限边界、确定性身份、预算和状态机约束。

## 安全失败

- 契约无效、目标或策略不匹配时拒绝规划；
- Definition、Goal 或 Policy 非 active 时拒绝规划；
- 缺少幂等键时拒绝规划；
- 非法和不可验证证据不进入证据引用；
- 单轮上限在运行时被强制为 1；
- dry-run 的 ready 只表示“可以提交审核”，不表示已经执行。
