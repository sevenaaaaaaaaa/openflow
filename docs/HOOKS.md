# OpenFlow 插件钩子参考

> 32 个钩子，覆盖 CDP / CRM / 营销自动化 / 内容 / 支付 / 社区 / 系统。
>
> **旁路契约**：所有钩子回调的异常都会被 `PluginSystem` 捕获并写入
> `data/plugin-errors.log`，绝不冒泡到业务代码。插件写坏不会让主流程挂掉。

## 用法

```php
// 动作：监听，无返回值
PluginSystem::add_action('crm_deal_won', function ($email, $lead) {
    // 成交时干点什么
}, 10);                     // 第三参为优先级，数字小的先执行

// 过滤器：可改写数据，必须 return
PluginSystem::add_filter('cdp_event_received', function ($event) {
    if ($event['event'] === 'heartbeat') return null;  // 返回 null/false 丢弃
    return $event;
});
```

---

## CRM

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `crm_lead_created` | action | 新线索首次创建 | `$email, $lead` |
| `crm_stage_changed` | action | 线索阶段发生变化（同值不触发） | `$email, $oldStage, $newStage, $lead` |
| `crm_deal_won` | action | 阶段变为 `won` | `$email, $lead` |
| `crm_deal_lost` | action | 阶段变为 `lost` | `$email, $lead` |
| `crm_followup_added` | action | 添加跟进记录 | `$email, $entry, $lead` |

阶段取值见 `crm_stages()`：`new / contacted / qualified / opportunity / won / lost`。

## CDP

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `cdp_event_received` | **filter** | 行为事件入库**前** | `$entry` → 返回改写后的事件；返回 `null`/`false` 丢弃 |
| `cdp_profile_updated` | action | 画像更新后 | `$visitorId, $memberId, $event, $data` |
| `cdp_segment_enter` | action | 用户进入分群 | `$segmentId, $profile, $segment` |
| `cdp_segment_exit` | action | 用户退出分群 | `$segmentId, $profile, $segment` |

`cdp_event_received` 是全站唯一能丢弃数据的钩子，用它做采集降噪时请谨慎。

## 营销自动化

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `ma_flow_triggered` | action | 流程匹配成功、执行**前** | `$flowId, $trigger, $context, $flow` |
| `ma_flow_completed` | action | 流程执行**后** | `$flowId, $trigger, $context, $flow` |
| `ma_email_sent` | action | 邮件发出后 | `$email, $subject, $content, $flowId, $provider` |

`$provider` 为 `mautic` 或 `billionmail`。

## 内容

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `content_published` | action | 状态**变为** published（重复保存不触发） | `$type, $id, $article` |
| `content_updated` | action | 任意保存 | `$type, $id, $after, $before` |
| `content_deleted` | action | 删除成功后 | `$type, $id, $removed` |
| `article_save_before` | filter | 后台编辑保存前 | `$article` |
| `article_saved` | action | 后台编辑保存后 | `$id, $article` |
| `article_output_before` | filter | API 输出单篇前 | `$article, $slug` |
| `articles_list_before_output` | filter | API 输出列表前 | `$articles` |
| `page_save_before` | filter | 页面保存前 | `$data, $page` |
| `page_saved` | action | 页面保存后 | `$page, $data` |

内容三钩挂在 `save_article()` / `delete_article()`，**所有写入路径**（后台、API、批量导入脚本）统一生效。`$type` 目前恒为 `'article'`，为将来扩展页面/下载预留。

## 支付与课程

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `payment_success` | action | 订单标记已支付 | `$orderId, $order, $method` |
| `course_enrolled` | action | 支付的订单含课程 | `$memberId, $courseId, $order` |

## 社区

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `comment_added` | action | 评论落库后（含待审） | `$type, $targetId, $comment` |
| `review_added` | action | 评论带评分（1-5）时额外触发 | `$type, $targetId, $rating, $comment` |
| `form_submitted` | action | 表单提交 | `$formId, $formType, $formData, $submission` |

## 系统

| 钩子 | 类型 | 触发时机 | 参数 |
|---|---|---|---|
| `user_registered` | action | 注册成功 | `$memberId, $email, $member` |
| `user_login` | action | 登录成功 | `$memberId, $email, $member` |
| `settings_changed` | action | 系统设置保存后 | `$after, $before` |
| `plugin_loaded` | action | 单个插件加载完成 | `$pluginId, $meta` |
| `admin_sidebar_menu` | action | 后台侧栏渲染 | `$current` |
| `admin_dashboard_render` | action | 后台工作台横幅下方 | 无 |

---

## 尚无插入点

以下钩子在任务清单里，但对应功能当前不存在，未实现：

| 钩子 | 原因 |
|---|---|
| `ma_sms_sent` | 全仓无短信发送实现 |
| `payment_refund` | `ShopSystem` 无退款分支（已单独立项） |

---

## 测试

```
php tests/crm_flow_hooks_test.php        # 17 项：CRM 阶段变化 + 旁路契约
php tests/content_payment_hooks_test.php # 18 项：内容/支付/评论
php tests/cdp_ma_hooks_test.php          # 12 项：filter 改写/丢弃/异常隔离
```
