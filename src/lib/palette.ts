/**
 * 命令面板（⌘K）的纯逻辑：过滤 + 高亮位移。
 *
 * 与 DOM 无关，可在 Node 里直接测；由 src/shell/index.js 负责渲染与事件绑定。
 * 背景：此前输入框只有 placeholder「搜索页面或命令…」却没有任何过滤逻辑（输入无效）。
 */

export interface CommandItem {
  /** 显示文案 */
  label: string;
  /** 跳转地址（也参与匹配，便于用 "/product" 命中） */
  href: string;
  /** 可选：额外关键词（中英别名、拼音首字母等） */
  keywords?: string[];
  /** 可选：命令式条目（无 href 时点击执行） */
  run?: () => void;
}

/** 归一化：小写 + 去空白，便于中英混排匹配 */
function norm(s: string): string {
  return (s || '').toLowerCase().replace(/\s+/g, '');
}

/**
 * 过滤并按「匹配质量」排序：
 *  3 = label 前缀命中（最强）
 *  2 = label 包含
 *  1 = href / keywords 命中
 *  0 = 不匹配（剔除）
 * 空查询时保持传入顺序（等价于"全部"）。
 */
export function filterCommands<T extends CommandItem>(items: readonly T[], query: string): T[] {
  const q = norm(query);
  if (!q) return items.slice();

  const scored: Array<{ item: T; score: number; idx: number }> = [];
  items.forEach((item, idx) => {
    const label = norm(item.label);
    const href = norm(item.href);
    const keys = (item.keywords ?? []).map(norm);
    let score = 0;
    if (label.startsWith(q)) score = 3;
    else if (label.includes(q)) score = 2;
    else if (href.includes(q) || keys.some((k) => k.includes(q))) score = 1;
    if (score > 0) scored.push({ item, score, idx });
  });

  scored.sort((a, b) => (b.score - a.score) || (a.idx - b.idx));
  return scored.map((s) => s.item);
}

/** 高亮位移：在 [0, len) 内循环；len 为 0 时返回 -1 */
export function moveIndex(current: number, len: number, delta: number): number {
  if (len <= 0) return -1;
  const next = (current + delta) % len;
  return next < 0 ? next + len : next;
}

/** 把高亮项滚进可视区（供 DOM 层调用；这里只算方向，便于测试） */
export function shouldScrollIntoView(index: number, visibleFrom: number, visibleTo: number): -1 | 0 | 1 {
  if (index < visibleFrom) return -1;
  if (index >= visibleTo) return 1;
  return 0;
}
