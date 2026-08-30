<?php
/**
 * T1-14 验收：参与者工作台 OIA（BuilderWorkspace）
 *   php tests/builder_workspace_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-bw-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/BuilderWorkspace.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$plain  = ['id'=>'m1','name'=>'张三','email'=>'z@t.com'];                  // 普通会员，未申请开发者
$banned = ['id'=>'m2','name'=>'坏人','email'=>'b@t.com','status'=>'banned'];
$blocked= ['id'=>'m3','name'=>'受限','email'=>'c@t.com','contrib_blocked'=>true];

echo "\n── 1. OIA：普通会员即参与者（不需申请）──\n";
check('普通会员可贡献', builder_can_contribute($plain) === true);
check('未登录不可', builder_can_contribute(null) === false);
check('封禁不可（安全护栏）', builder_can_contribute($banned) === false);
check('被限制贡献不可', builder_can_contribute($blocked) === false);

echo "\n── 2. 一次加入拿到三种能力 ──\n";
$caps = builder_capabilities($plain);
check('三种能力齐全', count($caps) === 3 && isset($caps['write'],$caps['build'],$caps['sell']));
check('普通会员全部启用', count(array_filter($caps, fn($c)=>$c['enabled'])) === 3);
check('封禁者全部关闭', count(array_filter(builder_capabilities($banned), fn($c)=>$c['enabled'])) === 0);
check('做工具强调不用写代码', strpos($caps['build']['desc'], '不用会写代码') !== false);

echo "\n── 3. 我的贡献跨三类聚合（多字段匹配）──\n";
$inject = [
    'articles' => [
        ['id'=>'a1','author'=>'张三','status'=>'published'],
        ['id'=>'a2','author_id'=>'m1','status'=>'draft'],
        ['id'=>'a3','author'=>'别人','status'=>'published'],
    ],
    'skills' => [['id'=>'s1','submitter'=>'m1','status'=>'approved'], ['id'=>'s2','submitter'=>'other']],
    'products' => [['id'=>'p1','author_name'=>'Z@T.com','status'=>'active']],   // 邮箱大小写不敏感
];
$c = builder_contributions($plain, $inject);
check('文章命中 2 篇(名字+id)', count($c['articles']) === 2, json_encode(array_column($c['articles'],'id')));
check('不含别人的', count(array_filter($c['articles'], fn($a)=>$a['id']==='a3')) === 0);
check('技能命中 1', count($c['skills']) === 1);
check('商品邮箱大小写不敏感命中', count($c['products']) === 1);
check('总数 4', $c['total'] === 4);
check('未登录返回空结构', builder_contributions(null)['total'] === 0);

echo "\n── 4. 贡献者档案 ──\n";
$p = builder_profile($plain, $c);
check('是参与者', $p['is_builder'] === true);
check('分类计数', $p['counts']['article'] === 2 && $p['counts']['skill'] === 1 && $p['counts']['product'] === 1);
check('已发布计数(1文章+1技能+1商品)', $p['published'] === 3, (string)$p['published']);
check('名字透出', $p['name'] === '张三');

echo "\n── 5. 下一步引导（用满三种能力）──\n";
$none = ['articles'=>[],'skills'=>[],'products'=>[],'total'=>0];
check('零贡献→先写内容', strpos(builder_next_step($none), '第一篇内容') !== false);
check('有文章无技能→做技能', strpos(builder_next_step(['articles'=>[1],'skills'=>[],'products'=>[],'total'=>1]), '技能') !== false);
check('有文章技能无商品→上架', strpos(builder_next_step(['articles'=>[1],'skills'=>[1],'products'=>[],'total'=>2]), '上架') !== false);
check('三样齐全→看买家', strpos(builder_next_step(['articles'=>[1],'skills'=>[1],'products'=>[1],'total'=>3]), '我的买家') !== false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
