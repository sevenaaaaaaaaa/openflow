// 扫描第1课新结构所有页面，检测内容溢出
const task = await taskSpace("deck overflow scan");
const page = task.page("p1");
const url = 'file:///Users/seveno/Knowledge/Obsidian/MindRe/1-Project/PSPI/1-2%20Insight/Knowledge%20Base/%E5%9F%BA%E7%9F%B3%E5%86%85%E5%AE%B9/v2/%E7%AC%AC1%E8%AF%BE%C2%B7AI%E7%BA%A2%E5%88%A9%E5%87%BA%E7%8E%B0%EF%BC%81%E4%B8%80%E4%BA%BA%E5%85%AC%E5%8F%B8%E5%86%B7%E5%90%AF%E5%8A%A8%E8%AF%80%E7%AA%8D.html';
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