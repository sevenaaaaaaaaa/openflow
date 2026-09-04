# ADR-002：共享证据写路径集成

- 状态：实施中（仅单节点 `add_tag` 影子日志）
- 日期：2026-09-05
- 前置：只读证据投影稳定运行

## 问题

现有 `GrowthAction`、自动化日志和成交账本来自不同阶段：动作没有标准批准/执行回执，日志没有 run 边界，成交账本只有聚合结果。直接改写路径会扩大回归面，也可能把历史“完成”误当成可验证执行。

## 拟议顺序

1. 先运行 `EvidenceProjection`，量化每类 `gap`，不修改任何数据；
2. 为自动化日志增加可选 `run_id/status/idempotency_key/trigger`，旧字段原样保留；
3. 为新动作追加可选 `approval/execution` 信封，不回填历史记录；
4. 成交事件先保存逐笔 `events`，再从事件计算现有聚合账本；
5. 只有完整关联 action、execution、goal 和事实来源时才生成 Evaluation；
6. 观察一个发布周期后，再决定是否将新字段设为必填。

## 兼容与回滚

- 所有新字段先可选，现有读取器继续读取旧结构；
- 新写入采用双写：旧字段/聚合仍是服务现有界面的事实源；
- 投影器保持只读，可随时停止；
- 每一步独立提交、独立开关、独立回滚；
- 禁止批量伪造历史 Approval、Execution 或 Evaluation。

## 进入实施的门槛

- 投影器覆盖率和 gap 分类可重复；
- 阻断 CI 与 AITIPS Flow 基线持续通过；
- 明确每个写入点的权限、事务、幂等、审计和失败降级；
- 先选择一个低风险 Flow 做影子双写，不改变其执行结果。

## 第一批实施

- 仅当自动化 Flow 恰好包含一个 `add_tag` 节点，且上下文包含稳定事件键时启用；
- 旧日志的 `time/flow/level/message` 字段保持不变，结构化字段只追加在影子行；
- run ID 由共享契约确定生成，同一事件的 `running/succeeded/failed` 使用同一 ID；
- 调用现有 `cdp_add_tag()` 后重新读取标签，只有读取到目标标签才写 `succeeded`；
- 无稳定事件键、多节点 Flow、邮件、通知和其他动作全部不进入本批影子双写；
- 不新增数据库，不改变动作执行顺序和返回值。
- 行为入口接受可选 `event_id/message_id/idempotency_key`，缺失时为当次事件生成 ID，并经 `flow_handle` 原样透传；旧函数签名和无 ID 时的触发载荷保持兼容；
- 重复事件 ID 生成同一 FlowRun ID，但本批不借此改变既有业务执行次数，避免把影子观测升级成未经评审的执行去重。

## 第一批观测口径

- `bin/observe-shadow-runs.php` 只读分析 `automation-log.json`，也可传入日志文件路径生成离线报告；
- 结构字段完整率：完整结构化候选行 / 全部影子候选行，旧日志不进入分母；
- 终态率：已出现 `succeeded/failed/cancelled` 的 run / 可识别 run；
- 生命周期完整率：同时存在 `running` 和终态的 run / 可识别 run；
- 异常单列缺字段、无起始终态、冲突终态、run 身份碰撞、超过一小时未终结及投影失败；
- 无样本时比率返回 `null`，不以 100% 制造健康假象；观测器不写文件、不调用执行器，也不改变 Flow 写路径。

在获得足够真实样本并确认上述口径稳定前，不扩展第二个动作。第二个动作的选择必须基于实际异常分布、可回读验证能力和回滚成本另行评审。

## Approval / Execution 第一批影子信封

- 仍只覆盖已启用的单节点 `add_tag` Flow，不扩大动作范围；
- Flow 的启用配置作为确定性策略批准，`actor_type=policy`，明确记录 `enabled_flow_configuration` 与 `flow-definition:<id>:enabled`，不伪称某位人工在运行时批准；
- 每次标签调用生成 Execution，引用同一 Approval 与 FlowRun；运行态无结果，只有标签回读成功后才带 `result_ref` 进入成功态；
- Approval/Execution 只作为自动化日志的可选嵌套字段追加，旧四字段、原执行顺序、返回值和 GrowthAction 存储均不改变；
- `EvidenceProjection` 按 ID 合并运行态与终态，非法信封报告 gap，不补造证据。
