// 扫描第1课新结构所有页面，检测内容溢出
const task = await taskSpace("deck overflow scan");
const page = task.page("p1");
// Course deck sources (local, gitignored): $OF_DECKS_SRC, default ./ops/decks/ (run from the repo root)
const DECKS_SRC = process.env.OF_DECKS_SRC || process.cwd() + '/ops/decks';
const url = 'file://' + encodeURI(DECKS_SRC.replace(/\/?$/, '/') + '第1课·AI红利出现！一人公司冷启动诀窍.html');
await page.goto(url);
await page.waitForTimeout(2600);
const n = await page.evaluate(() => document.querySelectorAll('.slide').length);
let bad = 0;
for (let i = 0; i < n; i++) {
  await page.evaluate((idx) => window.go ? window.go(idx) : null, i);
  await page.waitForTimeout(350);
  const r = await page.evaluate((idx) => {
    const s = document.querySelectorAll('.slide')[idx];
    const card = s.querySelector('.canvas-card');
    if (!card) return { idx, ok: true };
    const cr = card.getBoundingClientRect();
    let worst = null, over = 0;
    card.querySelectorAll('*').forEach(el => {
      const er = el.getBoundingClientRect();
      if (er.height === 0) return;
      const dy = er.bottom - (cr.bottom - 4);
      if (dy > over) { over = dy; worst = el.className || el.tagName; }
    });
    return { idx, ok: over <= 2, over: Math.round(over), worst, title: s.dataset.title };
  }, i);
  if (!r.ok) { bad++; console.log(`✗ p${i} [${r.title}] 溢出 ${r.over}px ← .${r.worst}`); }
}
console.log(bad === 0 ? `ALL CLEAN (${n} pages)` : `${bad} pages overflow`);