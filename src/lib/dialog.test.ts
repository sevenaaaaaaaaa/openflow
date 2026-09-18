import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createDialogRegistry, type DialogHandle, type ClickLike } from './dialog.ts';

function fakeHandle(id: string, selfIsScrim = true) {
  const calls = { open: 0, close: 0 };
  const h: DialogHandle = {
    id,
    el: { id },
    selfIsScrim,
    open: () => { calls.open++; },
    close: () => { calls.close++; },
  };
  return { h, calls };
}

function clickOn(targetEl: unknown, el: unknown): ClickLike {
  return {
    target: targetEl,
    contains: (node, t) => node === el && (t === el || t === 'child'),
  };
}

test('打开/关闭：调用原函数 + 锁滚动成对', () => {
  const locks: boolean[] = [];
  const reg = createDialogRegistry({ onLock: (l) => locks.push(l) });
  const { h, calls } = fakeHandle('auth');
  reg.register(h);

  assert.equal(reg.open('auth'), true);
  assert.equal(calls.open, 1);
  assert.deepEqual(locks, [true]);
  assert.equal(reg.close('auth'), true);
  assert.equal(calls.close, 1);
  assert.deepEqual(locks, [true, false]);
});

test('同时只允许一个弹窗：打开新的会先关旧的', () => {
  const reg = createDialogRegistry();
  const a = fakeHandle('a');
  const b = fakeHandle('b');
  reg.register(a.h); reg.register(b.h);

  reg.open('a');
  reg.open('b');
  assert.equal(a.calls.close, 1);
  assert.equal(b.calls.open, 1);
  assert.equal(reg.current()?.id, 'b');
});

test('ESC 关闭当前；无弹窗时不消费', () => {
  const reg = createDialogRegistry();
  const { h, calls } = fakeHandle('x');
  reg.register(h);
  assert.equal(reg.handleEscape(), false);
  reg.open('x');
  assert.equal(reg.handleEscape(), true);
  assert.equal(calls.close, 1);
  assert.equal(reg.current(), null);
});

test('点击全屏遮罩本体 → 关闭', () => {
  const reg = createDialogRegistry();
  const { h, calls } = fakeHandle('modal', true);
  reg.register(h); reg.open('modal');
  assert.equal(reg.handleClick(clickOn(h.el, h.el)), true);
  assert.equal(calls.close, 1);
});

test('点击弹窗内容 → 不关', () => {
  const reg = createDialogRegistry();
  const { h, calls } = fakeHandle('modal', true);
  reg.register(h); reg.open('modal');
  assert.equal(reg.handleClick(clickOn('child', h.el)), false);
  assert.equal(calls.close, 0);
  assert.equal(reg.current()?.id, 'modal');
});

test('点击弹窗之外 → 关闭（命令面板场景）', () => {
  const reg = createDialogRegistry();
  const { h, calls } = fakeHandle('palette', false);
  reg.register(h); reg.open('palette');
  assert.equal(reg.handleClick(clickOn('outside', h.el)), true);
  assert.equal(calls.close, 1);
});

test('非遮罩浮层：点自身本体不关（点面板空白不等于点遮罩）', () => {
  const reg = createDialogRegistry();
  const { h, calls } = fakeHandle('palette', false);
  reg.register(h); reg.open('palette');
  assert.equal(reg.handleClick(clickOn(h.el, h.el)), false);
  assert.equal(calls.close, 0);
});

test('重复 open 同一个不重复锁/不重复调 open', () => {
  const locks: boolean[] = [];
  const reg = createDialogRegistry({ onLock: (l) => locks.push(l) });
  const { h, calls } = fakeHandle('a');
  reg.register(h);
  reg.open('a'); reg.open('a');
  assert.equal(calls.open, 1);
  assert.deepEqual(locks, [true]);
});

test('广播 open/close 事件（供首次浮层让位）', () => {
  const events: string[] = [];
  const reg = createDialogRegistry({ onBroadcast: (k, h) => events.push(`${k}:${h.id}`) });
  const { h } = fakeHandle('auth');
  reg.register(h);
  reg.open('auth'); reg.close('auth');
  assert.deepEqual(events, ['open:auth', 'close:auth']);
});
