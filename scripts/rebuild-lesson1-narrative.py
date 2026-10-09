#!/usr/bin/env python3
"""第一课直播叙述逻辑重构：五幕剥洋葱结构
幕一·身边现象(3新页) → 幕二·时代信号 → 幕三·新活法亮相 → 幕四·系统道理 → 幕五·收口预告
"""
import re, pathlib, sys

DECKS = pathlib.Path('/Users/seveno/OpenFlow Dev/decks')
V2 = pathlib.Path('/Users/seveno/Knowledge/Obsidian/MindRe/1-Project/PSPI/1-2 Insight/Knowledge Base/基石内容/v2')
src = (DECKS / 'lesson-1.html').read_text()

HEAD = src.split('<div id="deck">')[0]
SCRIPT = '</div>\n' + src.split('<!-- /SLIDES -->')[1]
body = src.split('<div id="deck">')[1].split('<!-- /SLIDES -->')[0]

secs = re.findall(r'<section class="slide[^"]*" data-title="[^"]*">.*?</section>', body, re.S)
meta = []
for i, x in enumerate(secs):
    cm = re.search(r'<div class="l">([^<]*)</div>', x)
    meta.append((i, re.search(r'data-title="([^"]*)"', x).group(1), cm.group(1).strip()))

# 索引映射（当前成品顺序）
idx = {t.split(' · ')[0] if ' · ' in t else t: i for i, t in [(m[0], m[1]) for m in meta]}
def page(marker):
    for i, t, c in meta:
        if t.startswith(marker) or marker in t:
            return secs[i]
    sys.exit(f'page not found: {marker}')

P = {
    'cover':   secs[0],   # 封面
    'pos':     secs[3],   # 系统定位
    'host':    secs[1],   # M1
    'agenda':  secs[2],   # M2
    'cta':     secs[-1],  # CTA
    'phenom':  page('怪现象'),
    'history': page('红利史'),
    'brain':   page('原因'),
    'judge':   page('判断'),
    'define':  page('定义'),
    'border':  page('边界'),
    'cases':   page('案例'),
    'manifest':page('宣言'),
    'formula': page('核心'),
    'calc':    page('算账'),
    'cogn':    page('认知差'),
    'trap':    page('陷阱'),
    'shift':   page('暴论'),
    'paths':   page('路径'),
    'knot':    page('死结'),
    'loop':    page('方法'),
    'plays':   page('打法'),
    'pit':     page('天坑'),
}
# 其余营销页按原位置取
M3 = secs[15]      # 福利 · 模板直接给你
B1  = secs[23]     # BRIDGE
M4, M5, M6, M7, M8 = secs[24], secs[25], secs[26], secs[27], secs[28]

# ══════════ 新增三页 ══════════

NEW0 = '''<!-- 开场 · 身边现象 -->
<section class="slide" data-title="开场 · 你身边正在发生的事">
  <div class="canvas-card">
    <div class="chrome-min"><div class="l">LESSON 01 · 开场</div><div class="r"><span class="pg"></span></div></div>
    <div data-anim="1" style="display:flex;flex-direction:column;gap:1.2vh"><div class="t-meta">别急着记笔记 · 先打开你自己的朋友圈看看</div><h2 class="big-light" style="font-size:min(4.2vw,7.6vh)">这半年，你身边是不是也这样？</h2></div>
    <div class="spec-grid" style="grid-template-columns:repeat(2,1fr);margin-top:3.4vh">
      <div data-anim="2"><div class="t-meta">信号一</div><div style="font-size:min(1.7vw,3vh);font-weight:200;margin:1.4vh 0 .8vh">老同学开始发广告了</div><p class="mini">那个以前只发娃和旅游的同学，朋友圈悄悄开始卖货——而且卖得挺认真。</p></div>
      <div data-anim="3"><div class="t-meta">信号二</div><div style="font-size:min(1.7vw,3vh);font-weight:200;margin:1.4vh 0 .8vh">被优化的前同事，接单了</div><p class="mini">他们没再投简历，开始接单、做账号、做咨询——看起来比上班还忙。</p></div>
      <div data-anim="4"><div class="t-meta">信号三</div><div style="font-size:min(1.7vw,3vh);font-weight:200;margin:1.4vh 0 .8vh">楼下老板娘，直播了</div><p class="mini">关店的人没闲着，转身在抖音卖货——销量比坐店的时候还好。</p></div>
      <div data-anim="5" style="background:var(--accent);color:#fff"><div class="t-meta" style="color:rgba(255,255,255,.75)">信号四</div><div style="font-size:min(1.7vw,3vh);font-weight:200;margin:1.4vh 0 .8vh">然后，你刷到了这场直播</div><p class="mini" style="color:rgba(255,255,255,.88)">想给自己多开一条路的人，比两年前多了一倍——你不是个例，是潮流的一部分。</p></div>
    </div>
    <div class="foot"><span>这不是段子 · 是正在发生的迁移</span><span class="nb">SIGNAL</span></div>
  </div>
</section>'''

NEW1 = '''<!-- 开场 · 三个问题 -->
<section class="slide grey" data-title="提问 · 弹幕扣个数字">
  <div class="canvas-card">
    <div class="chrome-min"><div class="l">LESSON 01 · 互动</div><div class="r"><span class="pg"></span></div></div>
    <div data-anim="1" style="display:flex;flex-direction:column;gap:1.2vh"><div class="t-meta">先做个小测试 · 别不好意思</div><h2 class="big-light" style="font-size:min(4.2vw,7.6vh)">中招的，弹幕扣个数字。</h2></div>
    <div style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:2.4vh;margin-top:1vh">
      <div data-anim="2" style="display:flex;gap:1.6vw;align-items:baseline"><span class="big-light" style="font-size:min(3vw,5.4vh);color:var(--accent);min-width:1.4em">01</span><div><div style="font-size:min(1.9vw,3.4vh);font-weight:300">收藏过一堆「搞钱教程」，一个都没做完的——扣 <b>1</b></div><p class="mini" style="margin-top:.6vh">硬盘里躺着三个剪辑课、两个 AI 课，进度条都停在第 3 集。</p></div></div>
      <div data-anim="3" style="display:flex;gap:1.6vw;align-items:baseline"><span class="big-light" style="font-size:min(3vw,5.4vh);color:var(--accent);min-width:1.4em">02</span><div><div style="font-size:min(1.9vw,3.4vh);font-weight:300">学过 AI 工具，但没换来一分钱的——扣 <b>2</b></div><p class="mini" style="margin-top:.6vh">会聊天、会画图、会做视频，然后呢？工具一大堆，收入没变化。</p></div></div>
      <div data-anim="4" style="display:flex;gap:1.6vw;align-items:baseline"><span class="big-light" style="font-size:min(3vw,5.4vh);color:var(--accent);min-width:1.4em">03</span><div><div style="font-size:min(1.9vw,3.4vh);font-weight:300">道理都懂、日子却没变的——扣 <b>3</b></div><p class="mini" style="margin-top:.6vh">收藏、学习、点头、划走。三个月后回头看，什么都没变。</p></div></div>
    </div>
    <div data-anim="5" style="padding:1.4vh 1.2vw;background:var(--paper);border-left:3px solid var(--accent);font-size:min(1.3vw,2.3vh);font-weight:300">别不好意思——我当年三样全占。今晚我们就聊透两件事：<b>为什么会这样，以及怎么办。</b></div>
    <div class="foot"><span>扣 1 · 扣 2 · 扣 3 · 我都看得见</span><span class="nb">CHECK</span></div>
  </div>
</section>'''

NEW2 = '''<!-- 症结 · 漏水的桶 -->
<section class="slide" data-title="症结 · 你的努力在漏水">
  <div class="canvas-card">
    <div class="chrome-min"><div class="l">LESSON 01 · 症结</div><div class="r"><span class="pg"></span></div></div>
    <div data-anim="1" style="display:flex;flex-direction:column;gap:1.2vh"><div class="t-meta">三个信号指向同一个病根</div><h2 class="big-light" style="font-size:min(4.2vw,7.6vh)">你不是不努力，<br/>是装努力的桶，在漏水。</h2></div>
    <div class="spec-grid" style="grid-template-columns:repeat(3,1fr);margin-top:3vh">
      <div data-anim="2"><div class="t-meta">桶底漏了</div><div style="font-size:min(1.6vw,2.9vh);font-weight:200;margin:1.3vh 0 .8vh">卖时间的活法</div><p class="mini">干就有、停就没有——天花板是你一天只有 24 小时。你拼命，桶也装不满。</p></div>
      <div data-anim="3"><div class="t-meta">桶壁漏了</div><div style="font-size:min(1.6vw,2.9vh);font-weight:200;margin:1.3vh 0 .8vh">每次重启都清零</div><p class="mini">去年攒的粉丝、经验、素材，换个平台、换个赛道，全部作废重来。努力不积累。</p></div>
      <div data-anim="4"><div class="t-meta">桶口漏了</div><div style="font-size:min(1.6vw,2.9vh);font-weight:200;margin:1.3vh 0 .8vh">学而不做、做不复盘</div><p class="mini">收藏夹 500 条，动手 0 次。输入满桶，输出为零——水从来没进过桶。</p></div>
    </div>
    <p data-anim="5" class="lead" style="margin-top:2.8vh;max-width:60ch">问题从来不是你不够拼，是<b>装努力的方式</b>错了。今晚要给你的，不是又一个教程、又一个工具——是换桶的思路。</p>
    <div class="foot"><span>诊断完毕 · 开始剥洋葱</span><span class="nb">DIAGNOSIS</span></div>
  </div>
</section>'''

# ══════════ 五幕组装 ══════════
act1 = [NEW0, NEW1, NEW2]                                              # 幕一·身边现象与症结
act2 = [P['phenom'], P['history'], P['brain']]                          # 幕二·时代信号：怪现象/红利史/脑力下沉
act3 = [P['judge'], P['define'], P['border'], P['cases'], P['manifest']]  # 幕三·新活法：判断/定义/边界/案例/宣言
act4 = [P['formula'], P['calc'], P['cogn'], P['trap'], P['shift'], P['paths']]  # 幕四·系统道理：公式/算账/认知/陷阱/暴论/路径
act5 = [P['knot'], P['loop'], P['plays'], P['pit']]                     # 幕五·收口：死结/Loop/打法/天坑

part_a = act1 + act2 + act3 + [P['formula'], P['calc']]  # 引流前 13 页
part_b = [P['cogn'], P['trap'], P['shift'], P['paths']] + act5

pages = [P['cover'], P['host'], P['agenda'], P['pos']] + part_a + [M3] + part_b + [B1, M4, M5, M6, M7, M8, P['cta']]

out = HEAD + '<div id="deck">\n<!-- SLIDES -->\n\n' + '\n'.join(pages) + '\n\n<!-- /SLIDES -->' + SCRIPT
(V2 / '第1课·AI红利出现！一人公司冷启动诀窍.html').write_text(out)
print(f'pages: {out.count("<section class=" + chr(34) + "slide")}')
titles = re.findall(r'data-title="([^"]*)"', out)
for i, t in enumerate(titles):
    print(f'{i:2d} {t}')