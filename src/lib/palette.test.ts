import { test } from 'node:test';
import assert from 'node:assert/strict';
import { filterCommands, moveIndex, shouldScrollIntoView, type CommandItem } from './palette.ts';

const ITEMS: CommandItem[] = [
  { label: '产品', href: '/product' },
  { label: '能力', href: '/capability' },
  { label: '课程', href: '/courses', keywords: ['course', 'kc'] },
  { label: '学院', href: '/academy' },
  { label: '定价', href: '/pricing', keywords: ['price'] },
];

test('空查询返回全部且保持原顺序', () => {
  assert.deepEqual(filterCommands(ITEMS, '').map((i) => i.label), ['产品', '能力', '课程', '学院', '定价']);
});

test('label 前缀命中排最前', () => {
  const r = filterCommands(ITEMS, '课');
  assert.equal(r.length, 1);
  assert.equal(r[0]?.label, '课程');
});

test('href 与 keywords 也能命中（英文/拼音）', () => {
  assert.equal(filterCommands(ITEMS, 'product')[0]?.label, '产品');
  assert.equal(filterCommands(ITEMS, 'price')[0]?.label, '定价');
  assert.equal(filterCommands(ITEMS, 'kc')[0]?.label, '课程');
});

test('匹配质量排序：前缀 > 包含 > 其他', () => {
  const items: CommandItem[] = [
    { label: '生态开放', href: '/open' },      // 仅包含（不是前缀）
    { label: '开源', href: '/oss' },            // 前缀命中
  ];
  assert.equal(filterCommands(items, '开')[0]?.label, '开源');
  // label 包含（2 分）优先于 href/keywords（1 分）
  const items2: CommandItem[] = [
    { label: '文档中心', href: '/x', keywords: ['docs'] },  // 关键词命中(1)
    { label: 'Docs 导航', href: '/y' },                     // label 包含(2)
  ];
  assert.equal(filterCommands(items2, 'docs')[0]?.label, 'Docs 导航');
  // 同为 1 分时保持原始顺序（href 与 keywords 等价）
  const items3: CommandItem[] = [
    { label: '甲', href: '/docs' },
    { label: '乙', href: '/z', keywords: ['docs'] },
  ];
  assert.deepEqual(filterCommands(items3, 'docs').map((i) => i.label), ['甲', '乙']);
});

test('大小写与空白不敏感', () => {
  assert.equal(filterCommands(ITEMS, ' PRODUCT ')[0]?.label, '产品');
});

test('无命中返回空数组', () => {
  assert.deepEqual(filterCommands(ITEMS, 'zzz'), []);
});

test('moveIndex 循环位移，空列表返回 -1', () => {
  assert.equal(moveIndex(0, 5, 1), 1);
  assert.equal(moveIndex(4, 5, 1), 0);
  assert.equal(moveIndex(0, 5, -1), 4);
  assert.equal(moveIndex(-1, 5, 1), 0);
  assert.equal(moveIndex(0, 0, 1), -1);
});

test('shouldScrollIntoView 只在越界时给方向', () => {
  assert.equal(shouldScrollIntoView(0, 0, 5), 0);
  assert.equal(shouldScrollIntoView(-1, 0, 5), -1);
  assert.equal(shouldScrollIntoView(5, 0, 5), 1);
});
