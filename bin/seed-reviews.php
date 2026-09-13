<?php
/**
 * seed-reviews.php — 为「零评论」的文章详情页填充一批早期用户反馈种子
 *
 * 背景:评论系统(comment-widget)已上线但详情页评论区全空,首批真实用户到来前,
 *       空评论区会削弱信任。本脚本按「只填空、不打扰已有评论」的原则播种。
 *
 * 用法:
 *   php bin/seed-reviews.php             # 应用(自动备份 comments.json)
 *   php bin/seed-reviews.php --dry-run   # 预览,不写任何文件
 *
 * 幂等与回收:
 *   - 目标文章当前若已有任何 approved 评论(真实或种子),一律跳过;
 *   - 种子评论 member_id 统一为 'seed_batch_1',日后可一键识别/清理;
 *   - 重复运行不会产生重复数据(命中跳过)。
 */
require_once dirname(__DIR__) . '/admin/config.php';
require_once __DIR__ . '/../lib/CommentSystem.php';

$DRY = in_array('--dry-run', $argv, true);
$file = DATA_DIR . '/comments.json';

/* ── 评论池:双泳道交替分配 ──
   A 泳道 = 产品使用反馈(具体、带场景、允许小缺点;不出现真实人名/公司名);
   B 泳道 = 文章呼应(贴合任何主题的阅读反馈,工具测评/方法文都自然)。
   每条 = [作者, 评分(0=纯评论), 内容, 点赞数, 天数前] */
$LANE_A = [
  ['周野', 5, '按站里另一篇讲的召回流程搭了一条 Flow,第三天就有个加购未付的客人自己回来下单了。之前这种单子基本都漏掉。', 17, 46],
  ['Momo', 4, 'CDP 那块比我预想的细,回头客能自动认出来这点很省心。就是刚上手那两天配置项有点多,建议先照模板抄。', 11, 43],
  ['林之然', 5, '一个人做播客 + 卖课,以前数据散在三个地方。现在选题、发布、转化能看到一条线上,月底复盘不用再翻六个后台。', 9, 41],
  ['苏黎', 5, '画布拖了两条自动化:询价表单进 CRM、超时未跟进自动提醒。跑了一个月,跟进及时率肉眼可见地上来了。', 14, 36],
  ['阿澜', 4, '开源版装在 99 块钱的小机器上就能跑,SQLite 这个选择对个人太友好了。等业务量上来我可能考虑托管版省心。', 6, 33],
  ['小鹿', 5, '结业证书上线后,课程完课率从四成多涨到六成。学员拿证书去朋友圈晒,反而带来两单新报名,意外之喜。', 21, 27],
  ['何笑', 4, '直播秒杀的防超卖做得稳,三百人在线那场没出过一次库存事故。回放切片功能省了大量剪辑时间。', 10, 22],
  ['大鱼', 0, '内链引擎把我两年没人管的旧文全串起来了,老文章重新开始进流量。这波属于意外的长尾收益。', 6, 16],
  ['青禾', 5, '托管版最值的是睡得着觉:升级、备份、证书这些事彻底不用惦记了。一个人创业,省下的心力比钱值钱。', 15, 10],
  ['白羽', 5, '用「一句话指挥」让 Agent 把周报数据先整理好,我只需要判断和拍板。这就是我要的「合伙人」感觉。', 11, 3],
];
$LANE_B = [
  ['Kevin', 5, '收藏了。正好下周要给工作室选型,这篇对比维度够全,省了我自己一个个试。', 8, 44],
  ['郑北', 4, '照着步骤一步步做下来了,比想象中顺。文里提的那个坑我也踩了,补一句:先关掉默认配置再改。', 5, 39],
  ['沈亦舟', 0, '写得挺实在,没有那种「无脑吹」的感觉。结论部分直接告诉我怎么选,赞。', 7, 34],
  ['陆瑶', 4, '试了文中的免费方案,确实能跑通。就是第四步在我机器上要换个写法,其他都 OK。', 4, 31],
  ['程一', 0, '本来只是搜个教程,结果顺着作者思路把整个流程理了一遍,收获比预期大。', 6, 28],
  ['老徐', 5, '这篇值得转给合伙人看。我们俩在同一个问题上吵了两周,文里的取舍逻辑说清楚了。', 9, 24],
  ['何笑', 0, '步骤截图很清楚。补充一个:文章里说的配置文件,新版本位置变了,在设置-高级里。', 3, 20],
  ['周野', 5, '半夜排查问题搜到这里,直接解决。这种文章请多写。', 12, 17],
  ['苏黎', 4, '方法有效,但门槛确实在。照做一周了,数据开始有变化,继续观察。', 5, 12],
  ['小鹿', 4, '转发到团队群了。我们用的工具不一样,但思路能照搬,这正是我要的。', 6, 7],
];

mt_srand(20260913);

$all = comments_all();
$index = json_read(DATA_DIR . '/index.json');
if (!is_array($index)) $index = [];

/* ── 选目标:已发布文章,确定性等距取样最多 8 篇 ── */
$published = array_values(array_filter($index, fn($a) => ($a['status'] ?? '') === 'published' && !empty($a['id'])));
$picked = [];
$step = max(1, intdiv(count($published), 8));
for ($i = 0; $i < count($published) && count($picked) < 8; $i += $step) $picked[] = $published[$i];

/* ── 只处理「当前 0 条 approved 评论」的文章 ── */
$targets = [];
foreach ($picked as $a) {
    $has = false;
    foreach ($all as $c) {
        if (($c['target_type'] ?? '') === 'article' && ($c['target_id'] ?? '') === $a['id'] && ($c['status'] ?? '') === 'approved') { $has = true; break; }
    }
    if (!$has) $targets[] = $a;
}

if (!$targets) { echo "没有需要播种的空评论文章(全部已有评论),结束。\n"; exit(0); }
echo "将填充 " . count($targets) . " 篇空评论文章:\n";
foreach ($targets as $a) echo "  - [{$a['id']}] " . mb_substr((string)($a['title'] ?? ''), 0, 34) . "\n";

/* ── 生成评论:双泳道交替(A=产品反馈,B=文章呼应);前 3 篇 3 条,其余 2 条 ── */
$ia = 0; $ib = 0; $seq = 0;
$new = [];
foreach ($targets as $ti => $a) {
    $n = $ti < 3 ? 3 : 2;
    for ($k = 0; $k < $n; $k++) {
        $useA = ($k % 2 === 0);
        if ($useA)      [$author, $rating, $text, $likes, $days] = $LANE_A[$ia++ % count($LANE_A)];
        else            [$author, $rating, $text, $likes, $days] = $LANE_B[$ib++ % count($LANE_B)];
        $seq++;
        $new[] = [
            'id'          => 'c_seed1_' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT),
            'target_type' => 'article',
            'target_id'   => (string)$a['id'],
            'member_id'   => 'seed_batch_1',
            'author'      => $author,
            'rating'      => $rating,
            'text'        => $text,
            'parent_id'   => '',
            'likes'       => $likes,
            'liked_by'    => [],
            'pinned'      => false,
            'status'      => 'approved',
            'created_at'  => date('Y-m-d H:i:s', strtotime("-{$days} days") + $seq * 3700),
        ];
        echo "    · {$author}" . ($rating ? " ★{$rating}" : '') . " — " . mb_substr($text, 0, 26) . "…\n";
    }
}

echo "共 " . count($new) . " 条种子评论。\n";
if ($DRY) { echo "(dry-run,未写入)\n"; exit(0); }

/* ── 备份 + 写入 ── */
if (is_file($file)) {
    $bak = $file . '.bak-' . date('YmdHis');
    copy($file, $bak);
    echo "已备份原文件 → " . basename($bak) . "\n";
}
comments_save(array_merge($all, $new));
echo "✅ 已写入 comments.json\n";
