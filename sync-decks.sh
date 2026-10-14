#!/bin/bash
# 同步四课 PPT：Obsidian 源目录 → 服务器 /decks/
# 修改流程：在 Obsidian 里改 v2 目录下的 PPT → 跑本脚本 → 线上更新
set -e

V2="/Users/seveno/Knowledge/Obsidian/MindRe/1-Project/PSPI/1-2 Insight/Knowledge Base/基石内容/v2"
LOCAL="$(cd "$(dirname "$0")" && pwd)/decks"
SERVER="root@172.96.253.73"
PORT="28766"
REMOTE="/www/wwwroot/nownexts_com/decks"

mkdir -p "$LOCAL"

echo "==> 1/3 从 Obsidian 拷贝并改英文名..."
cp "$V2/第1课·AI红利出现！一人公司冷启动诀窍.html" "$LOCAL/lesson-1.html"
cp "$V2/第2课·加速主义来袭！核心增长模型.html" "$LOCAL/lesson-2.html"
cp "$V2/第3课·AI是灵药也是毒药！精算体系.html" "$LOCAL/lesson-3.html"
cp "$V2/第4课·超频你的增长！知识管理.html" "$LOCAL/lesson-4.html"
if [ -f "$V2/一人公司入门四课·PPT.html" ]; then
  cp "$V2/一人公司入门四课·PPT.html" "$LOCAL/all-in-one.html"
fi

echo "==> 2/3 生成索引页..."
python3 - <<'PYEOF'
import pathlib, datetime
local = pathlib.Path(__file__).parent if False else pathlib.Path('decks')
lessons = [
    ('lesson-1.html', 'LESSON 01 · 凭什么', '第 1 课 · AI 红利出现！一人公司冷启动诀窍'),
    ('lesson-2.html', 'LESSON 02 · 怎么跑', '第 2 课 · 加速主义来袭！核心增长模型'),
    ('lesson-3.html', 'LESSON 03 · 怎么算', '第 3 课 · AI 是灵药也是毒药！精算体系'),
    ('lesson-4.html', 'LESSON 04 · 怎么记', '第 4 课 · 超频你的增长！知识管理'),
    ('all-in-one.html', 'FULL DECK · 合集', '一人公司入门四课 · 完整路演版'),
]
cards = []
for i, (f, meta, title) in enumerate(lessons):
    src = (local / f).read_text()
    n = src.count('<section class="slide')
    cards.append(f'''<a class="card" href="/decks/{f}" style="--d:{i}">
  <div class="meta">{meta}</div>
  <div class="t">{title}</div>
  <div class="ft"><span>{n} 页</span><span class="go">打开 →</span></div>
</a>''')
now = datetime.datetime.now().strftime('%Y-%m-%d %H:%M')
html = f'''<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>一人公司入门四课 · 幻灯片预览</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;600&family=JetBrains+Mono:wght@400;500&family=Noto+Sans+SC:wght@200;300;400&display=swap" rel="stylesheet">
<style>
*{{box-sizing:border-box;margin:0;padding:0}}
body{{background:#fafaf8;color:#0a0a0a;font-family:Inter,"PingFang SC","Noto Sans SC",sans-serif;min-height:100vh;display:flex;flex-direction:column;padding:6vh 6vw}}
.meta{{font-family:"JetBrains Mono",monospace;font-size:11px;letter-spacing:.16em;text-transform:uppercase;opacity:.6}}
h1{{font-weight:200;letter-spacing:-.03em;font-size:min(5vw,8vh);margin:1.6vh 0 .8vh}}
.sub{{font-weight:300;font-size:15px;opacity:.7}}
.grid{{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1px;background:#d4d4d2;margin-top:5vh;border:1px solid #d4d4d2}}
.card{{background:#fafaf8;padding:28px 24px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:14px;transition:background .25s,color .25s}}
.card:hover{{background:#002FA7;color:#fff}}
.card:hover .meta{{opacity:.75}}
.card .t{{font-size:20px;font-weight:300;line-height:1.5;flex:1}}
.card .ft{{display:flex;justify-content:space-between;font-family:"JetBrains Mono",monospace;font-size:11px;letter-spacing:.1em;opacity:.6}}
footer{{margin-top:auto;padding-top:4vh;font-family:"JetBrains Mono",monospace;font-size:10px;letter-spacing:.14em;opacity:.45;display:flex;justify-content:space-between}}
</style></head><body>
<div class="meta">INTERNAL PREVIEW · 内部预览</div>
<h1>一人公司入门四课</h1>
<p class="sub">你要的是系统，不是工具 · 直播版幻灯片（←→ 翻页 · ESC 索引）· 已对齐产品矩阵 v2</p>
<div class="grid">{''.join(cards)}</div>
<footer><span>同步时间 {now} · 改 Obsidian 源文件后跑 bash sync-decks.sh 更新</span><span>NOWNEXTS</span></footer>
</body></html>'''
(local / 'index.html').write_text(html)
print('index.html generated')
PYEOF

echo "==> 3/3 上传到服务器..."
rsync -az --delete -e "ssh -p ${PORT} -o StrictHostKeyChecking=no" "$LOCAL/" "${SERVER}:${REMOTE}/"
ssh -p ${PORT} -o StrictHostKeyChecking=no "${SERVER}" "chown -R www:www ${REMOTE} && echo 'decks 已上线 ✔'"
echo ""
echo "预览地址：https://nownexts.com/decks/"