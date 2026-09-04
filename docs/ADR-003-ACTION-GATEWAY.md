# ADR-003：Action Gateway 与 dry-run

- 状态：实施中（V1 只读）
- 日期：2026-09-05

## 决策

所有未来 Loop 写动作通过统一 Action Gateway 描述，但 Gateway 不复制领域执行器。V1 只为 `add_tag` 提供 dry-run：读取目标、展示预计变更、复用 AutonomyGuard、计算确定性 ActionProposal ID，并声明权限、成本、风险和执行器。

## 当前边界

- `production_enabled=false`，没有执行入口；
- dry-run 永远返回 `would_execute=false`，即使策略检查允许；
- 不存在的目标、缺失幂等键和未知动作安全失败；
- 不调用 `cdp_add_tag()`，不写 Approval/Execution，不更改现有 Flow；
- 后续只有在 Approval/Execution 影子证据稳定后，才单独评审受控执行入口。

## 验收

同一请求在 Flow/Loop 视图产生同一提议身份；目标、预计变化、权限、成本、风险与审批要求可见；任何 dry-run 都不产生业务副作用。
