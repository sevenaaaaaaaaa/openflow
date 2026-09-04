# ADR-001：Flow 与 Loop 共享领域契约

- 状态：提议
- 日期：2026-09-05
- 决策范围：领域边界，不含数据库迁移和 UI

## 背景

现有 AITIPS 已经拥有 FlowSystem、CanvasSystem、AutomationSystem、GrowthGoal、GrowthAction、GrowthMemory、DecisionTrace 和 SkillSystem。建设 Loop 时如果重新创建内容、客户、动作或流程模型，将产生两套状态、权限和审计，直接威胁现有 Flow 的稳定性。

## 决策

Flow 与 Loop 使用同一套领域身份和执行入口。模式只改变“谁制订策略、如何展示”，不改变业务事实。

### 共享对象

| 对象 | 责任 | 现有来源 | 阶段 1 处理 |
|---|---|---|---|
| `Goal` | 指标、基线、周期、预算与边界 | `GrowthGoal` | 扩展契约，不复制存储 |
| `FlowDefinition` | 人批准的确定性节点、边与版本 | `CanvasSystem` / `AutomationSystem` | 统一身份与版本元数据 |
| `FlowRun` | 一次触发和执行的状态 | 现有触发与队列记录 | 补统一 run id 与结果 |
| `SkillDefinition` | 有契约的可复用能力 | `SkillSystem` | 补 TIPS、权限、成本、幂等声明 |
| `LoopDefinition` | 目标驱动循环的策略和边界 | 尚无 | 新增，但只引用共享对象 |
| `LoopRun` | 一次循环的持久状态 | 尚无 | 新增，不承载业务数据副本 |
| `ActionProposal` | 尚未执行的结构化建议 | `GrowthAction` | 将建议、审批、执行拆开 |
| `Approval` | 谁在何时批准什么版本 | 现有审批与审计能力 | 统一关联 action/run/version |
| `Execution` | 确定性业务动作及结果 | 各领域模块 | 经统一网关调用原实现 |
| `Evaluation` | 基线、样本、成本和业务结果 | A/B、归因、报表 | 统一最小结果契约 |
| `Memory` | 已发生事实、结果与人的纠正 | `GrowthMemory` | 只追加、可追溯来源 |
| `Policy` | 权限、同意、频控、预算和风险 | 现有 guard 系统 | 组合现有守卫，不再造权限系统 |

### 不变量

1. 每个对象有稳定 ID、版本、创建者、时间和租户范围。
2. `ActionProposal` 不等于 `Execution`；模型输出永远不能伪装成已执行结果。
3. 所有写操作调用现有领域函数，并继续经过权限、事务、审计和幂等检查。
4. Flow 和 Loop 引用同一个客户、内容、订单、流程和动作 ID。
5. 人工接管不复制状态；Loop 暂停后可以在 Flow 中继续处理原对象。
6. `Evaluation` 的业务数字来自系统事实，模型只能解释，不能生成。
7. `Memory` 保存事实和纠正，不保存无法追溯来源的“模型结论”。
8. Schema 演进向后兼容；现有 Flow 不需要迁移即可继续运行。

## 暂不决定

- 具体表结构和 Repository 形状；
- Loop Runtime 的调度技术；
- 双工作台页面布局；
- 自治等级的最终产品命名；
- 跨租户学习与工作流市场。

这些内容必须在现有对象调用点和数据迁移成本核查后分别形成 ADR。

## 验收条件

进入阶段 1 实现前，需要为每个共享对象补齐：字段契约、所有者模块、状态机、权限、幂等键、审计事件和兼容策略。第一项实现必须证明同一动作在 Flow 与 Loop 侧具有相同 ID、状态和执行结果。
