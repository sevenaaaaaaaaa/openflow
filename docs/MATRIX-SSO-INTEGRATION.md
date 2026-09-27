# 芭乐派产品矩阵 · 账号互通接入指南(给各项目开发会话的提示词)

> OpenFlow 是账号真源。本文档每节是一段可直接粘贴给对应项目 AI 会话的提示词。
> 前置:先到 OpenFlow 后台 `/xmp/matrix`(矩阵互通) 为对应应用「签发 secret 并启用」,
> 记下 secret 与回跳地址,填进提示词的 `【】` 处。

---

## 通用协议(所有项目一致,先读这段)

```
接入「芭乐派矩阵账号互通」v1 协议,OpenFlow 是账号真源:

1. 用户从 OpenFlow 点击进入本产品时,会跳转到:
   {本产品地址}/auth/matrix?ticket={一次性短票}&next={目标路径}

2. 本产品新增 GET 路由 /auth/matrix:
   - 校验 ticket 非空,否则 302 到本产品登录页;
   - 后端立刻发起兑换(服务端对服务端,secret 绝不出现在浏览器):
     POST https://nownexts.com/api/matrix.php?action=redeem
     Content-Type: application/json
     X-Client-Secret: 【MATRIX_CLIENT_SECRET】
     请求体: {"ticket": "<URL decode 后的票>", "client_id": "【CLIENT_ID】"}
   - 成功响应: {"ok":true,"user":{"id","email","name","level","iss":"openflow","iat"}}
   - 失败响应: {"ok":false,"error":"票据已被使用|票据已过期|..."}

3. 兑换成功后:
   - 按本产品自己的用户模型建档或更新(email 为唯一键;level 映射本产品权限);
   - 建立本产品自己的会话(session/JWT 均可,此后与 OpenFlow 无在线依赖);
   - 302 到 next 参数指定的路径(校验只允许本站相对路径,防开放跳转)。

4. 安全要求(全部强制):
   - 票一次性:兑换成功后 OpenFlow 端即作废,本产品无需(也不能)二次兑换;
   - 兑换只发生在后端;ticket 不落日志、不进 localStorage;
   - next 只接受本站相对路径;
   - 本产品登出不调用 OpenFlow(会话相互独立)。

5. 登录页加一个入口:「用 OpenFlow 账号登录」→
   跳转 https://nownexts.com/member.php?view=login&next=
     https%3A%2F%2Fnownexts.com%2Fapi%2Fmatrix.php%3Faction%3Denter%26app%3D【CLIENT_ID】
   (即:先去 OpenFlow 登录,登录后回到 enter,enter 签票跳回本产品。)
```

---

## MFlow(Python · 内容生产与分发)

```
在 MFlow 中实现矩阵账号互通(OpenFlow 账号真源),严格按以下协议与步骤:

【环境】MATRIX_CLIENT_ID=mflow · MATRIX_CLIENT_SECRET=【粘贴】 · OPENFLOW_BASE=https://nownexts.com
【已有基础】OpenFlow 账号可直接登录的说法已在 README 承诺;本次把承诺变成真实实现。

任务:
1. 新增 GET /auth/matrix 路由(FastAPI/Flask 按现有框架惯例),按下方"通用协议"实现兑换流程。
2. 兑换用 httpx(异步项目用 AsyncClient),超时 10s,失败重试 1 次;失败一律 302 本产品登录页并在
   flash 里提示「OpenFlow 互通登录失败」,不暴露错误细节。
3. 用户建档:以 email 唯一键 upsert 本产品 users 表;openflow_id 存 user.id 便于后续 API 互通;
   level 映射:free→free,pro/paid→pro(后续按实际等级表调整)。
4. 会话:沿用 MFlow 现有 session 机制;session 里记 openflow_id 便于联动 API 调用。
5. 登录页加「用 OpenFlow 账号登录」按钮,链接按"通用协议"第 5 条构造。
6. 测试(参照 tests/ 现有风格):票兑换成功建档 / 重放拒绝 / secret 错拒绝 / next 相对路径校验 /
   未配置 secret 时路由直接 302 登录页。≥5 条。
7. 文档:README 的「OpenFlow 账号可直接登录」一句加链接指向 docs/matrix-auth.md(把协议抄进去)。

约束:不改任何现有内容生产/分发逻辑;不引入新依赖(除 httpx 已有);所有新增代码带中文注释说明"为什么"。
```

## inFlow(Python · 情报增长)

```
在 inFlow 中接入矩阵账号互通(OpenFlow 账号真源)。

【环境】MATRIX_CLIENT_ID=inflow · MATRIX_CLIENT_SECRET=【粘贴】 · OPENFLOW_BASE=https://nownexts.com
【定位约束】inFlow 是独立产品,互通是"增益不是依赖":不配 MATRIX_CLIENT_SECRET 时一切照旧。

任务:
1. 按上方"通用协议"实现 GET /auth/matrix + 后端兑换 + 本产品会话(参照 inFlow 现有 auth 模块风格)。
2. 登录页加「用 OpenFlow 账号登录」入口(未配置 secret 时隐藏)。
3. 用户模型:email 唯一键 upsert;记录 openflow_id;level 映射本产品的订阅档位(没有则全 free)。
4. 附带(本期只留桩):用户登录后,把 openflow_id 写入"情报回填"客户端上下文——
   后续 inFlow 情报回填 OpenFlow 选题库时用同一身份,实现"数据同主人"。
5. 测试 ≥5 条(兑换/重放/错密钥/未配置降级/next 校验),文档 docs/matrix-auth.md。

约束:互通代码独立成模块(auth/matrix.py),可整体删除而不影响其余功能——这是"拔掉任何一个闭环不中断"的矩阵原则。
```

## UserLoop(Python · 全域用户数据中枢)

```
在 UserLoop 中接入矩阵账号互通(OpenFlow 账号真源)。

【环境】MATRIX_CLIENT_ID=userloop · MATRIX_CLIENT_SECRET=【粘贴】 · OPENFLOW_BASE=https://nownexts.com
【定位约束】UserLoop 自有 CDP,互通只是让登录免注册;绝不把 OpenFlow 的用户数据当作数据源写入 CDP。

任务:
1. 按上方"通用协议"实现 GET /auth/matrix + 后端兑换 + 本产品会话(参照现有 auth)。
2. 用户建档走 UserLoop 自己的 identity 归一(email 键);openflow_id 作为该 identity 的一个外部别名
   (identity_aliases 表),这正是 UserLoop 多源归一能力的自用示范。
3. 登录页加「用 OpenFlow 账号登录」;未配置 secret 隐藏。
4. 测试 ≥5 条;文档 docs/matrix-auth.md。
约束:互通模块可整体删除;CDP 数据层零改动。
```

## PayFlow(PHP · 商业变现引擎)

```
在 PayFlow 中接入矩阵账号互通(OpenFlow 账号真源)。

【环境】MATRIX_CLIENT_ID=payflow · MATRIX_CLIENT_SECRET=【粘贴】 · OPENFLOW_BASE=https://nownexts.com
【定位约束】PayFlow "一行嵌入任何页面",账号互通同样不能强绑 OpenFlow:未配置时完全无感。

任务:
1. 按上方"通用协议"实现 GET /auth/matrix.php + 后端兑换(cURL,10s 超时,失败 302 登录页)。
   代码风格对齐 PayFlow 现有 config/app.php 版本管理与 API v1 惯例;新增逻辑注明"为什么"。
2. 用户建档:email 唯一键;openflow_id 存用户元数据;level 映射 PayFlow 订阅档位。
3. 登录页加「用 OpenFlow 账号登录」;未配置 secret 隐藏。
4. 兑换成功后发一个本地钩子事件(如 matrix_user_login),方便后续扩展,但本期不做任何自动建单/发券。
5. 测试 ≥5 条(参照 tests/ 现有风格);文档 docs/matrix-auth.md。
```

## LearnFlow(PHP · 知识交付)

```
在 LearnFlow 中接入矩阵账号互通(OpenFlow 账号真源)。

【环境】MATRIX_CLIENT_ID=learnflow · MATRIX_CLIENT_SECRET=【粘贴】 · OPENFLOW_BASE=https://nownexts.com
【定位约束】LearnFlow 有自己的学员体系;互通让"OpenFlow 买课的人"免注册进入学习,身份仍然是 LearnFlow 的。

任务:
1. 按上方"通用协议"实现 GET /auth/matrix.php + 后端兑换 + 本产品学员会话。
2. 学员建档:email 唯一键;openflow_id 存元数据;level → 学员档位映射写在 docs/matrix-auth.md。
3. 登录页加「用 OpenFlow 账号登录」;未配置 secret 隐藏。
4. 预留(不实现):/course/enroll 时若用户带 openflow_id,后续可跨系统验证证书——本期只留设计说明。
5. 测试 ≥5 条;文档 docs/matrix-auth.md。
约束:交付/进度/证书逻辑零改动。
```

---

## OpenFlow 侧配套(已实现,无需再开发)

- 后台 `/xmp/matrix`:为各应用签发/轮换 secret、登记回跳地址(明文只显示一次);
- 前台入口:`/api/matrix.php?action=apps` 返回已启用应用及其 enter 链接,可挂到个人中心;
- 全部实现与测试见 OpenFlow 仓库:`lib/MatrixTicket.php`、`api/matrix.php`、`tests/matrix_ticket_test.php`。
