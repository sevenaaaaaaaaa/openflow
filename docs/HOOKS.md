# OpenFlow 插件钩子参考

> 34 个钩子，覆盖 CDP / CRM / 营销自动化 / 内容 / 支付 / 社区 / 系统。
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
| `crm_leads_bulk_imported` | action | 批量导入线索落盘后，整批发一次 | `$stat, $opts` |

批量导入（`crm_bulk_create_leads()` / `crm_leads_from_segment()`）会为**每条新线索**
照常发 `crm_lead_created`，再额外发一次 `crm_leads_bulk_imported` 汇总。
`$stat` 形如 `['created'=>10,'updated'=>2,'skipped'=>5,'no_email'=>1,'segment'=>'高价值用户']`。
批量导入默认**不**逐条触发出站 webhook——那是同步 HTTP，几千条会把请求拖死；
需要时传 `['fire_webhooks' => true]` 显式打开。

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
| `payment_refund` | action | 订单退款完成 | `$orderId, $order, $refundAmount, $reason` |
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

`payment_refund` 原本因「无退款功能」缺席，退款功能已实现，该钩子现已可用。

---

## 测试

```
php tests/crm_flow_hooks_test.php        # 17 项：CRM 阶段变化 + 旁路契约
php tests/content_payment_hooks_test.php # 18 项：内容/支付/评论
php tests/cdp_ma_hooks_test.php          # 12 项：filter 改写/丢弃/异常隔离
php tests/canvas_crm_condition_test.php  # 27 项：画布条件节点读 CRM
php tests/refund_test.php                # 36 项：退款金额/积分对称回滚
php tests/hub_merge_test.php             # 167 项：后台合并契约
php tests/bulk_leads_test.php            # 42 项：分群→CRM 批量建线索
php tests/plugin_sdk_test.php            # 52 项：PluginSDK + 三个官方示例插件
php tests/render_smoke_test.php          # 24 个页面：中心页与各 tab 真实渲染
php tests/qa_full.php                    # 全仓质检（跑上面全部 + 结构性检查）
php tests/events_index_bench.php         # events 索引实测（不进必跑集，见 PERFORMANCE.md）
```

## 插件开发

新插件建议用 `lib/PluginSDK.php`，它把配置读写、日志、侧栏入口、出站 HTTP
和配置页表单都收成了一个对象。三个官方示例分别示范 filter 改写、
多动作监听、以及唯一能丢数据的 `cdp_event_received` 该怎么用得安全：

```
plugins/seo-enhancer/     filter 补全 SEO 字段 + action 推送收录
plugins/deal-notifier/    成交/支付/退款/批量导入播报到群
plugins/event-firewall/   埋点入库前拦爬虫与噪音（fail-open 示范）
```
