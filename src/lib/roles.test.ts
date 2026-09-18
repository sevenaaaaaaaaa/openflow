import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  isInternalHref,
  resolveRole,
  readStoredRole,
  writeStored,
  roleLanding,
  ctaBindings,
  bandLabels,
  nextRole,
  quickCardId,
  shouldShowPicker,
  defaultRole,
  parseQuery,
  isAdChannel,
  inferRoleFromUtm,
} from './roles.ts';

const ROLES = { beginner: { label: '新手' }, power: { label: '达人' } };

test('resolveRole：合法才返回，非法/空返回 null', () => {
  assert.equal(resolveRole('beginner', ROLES), 'beginner');
  assert.equal(resolveRole('ghost', ROLES), null);
  assert.equal(resolveRole('', ROLES), null);
  assert.equal(resolveRole(null, ROLES), null);
  assert.equal(resolveRole('beginner', undefined), null);
});

test('resolveRole：不误吞原型链上的键', () => {
  assert.equal(resolveRole('toString', ROLES), null);
  assert.equal(resolveRole('constructor', ROLES), null);
});

test('readStoredRole / writeStored：storage 抛错时静默', () => {
  const ok = { getItem: () => 'power', setItem: () => {} };
  assert.equal(readStoredRole(ok, 'of_role', ROLES), 'power');
  const boom = {
    getItem: () => { throw new Error('denied'); },
    setItem: () => { throw new Error('denied'); },
  };
  assert.equal(readStoredRole(boom, 'of_role', ROLES), null);
  assert.equal(writeStored(boom, 'of_role', 'power'), false);
  assert.equal(writeStored(ok, 'of_role', 'power'), true);
});

test('isInternalHref：排除外链，允许站内与锚点', () => {
  assert.equal(isInternalHref('/product'), true);
  assert.equal(isInternalHref('#contact'), true);
  assert.equal(isInternalHref('https://github.com/x'), false);
  assert.equal(isInternalHref('http://a.com'), false);
  assert.equal(isInternalHref(''), false);
  assert.equal(isInternalHref(undefined), false);
});

test('roleLanding：取第一个站内入口；首个是外链则留在原页', () => {
  assert.equal(roleLanding({ qs: [{ href: '/courses' }, { href: '/docs' }] }), '/courses');
  assert.equal(roleLanding({ qs: [{ href: 'https://github.com/x' }] }), null);
  assert.equal(roleLanding({ qs: [] }), null);
  assert.equal(roleLanding(null), null);
});

test('ctaBindings：三个文案 + 前两个站内 href（外链置 null）', () => {
  const r = ctaBindings({
    hero: { cta1: 'A', cta2: 'B', cta3: 'C' },
    qs: [{ href: 'https://ext' }, { href: '/courses' }],
  });
  assert.deepEqual(r.labels, ['A', 'B', 'C']);
  assert.deepEqual(r.hrefs, [null, '/courses']);
});

test('bandLabels：可选第三按钮', () => {
  assert.deepEqual(bandLabels({ band: { btn1: 'x', btn2: 'y' } }), ['x', 'y', undefined]);
});

test('nextRole：循环，未知当前值从第一个开始', () => {
  const order = ['beginner', 'power', 'dev', 'enterprise'];
  assert.equal(nextRole(order, 'beginner'), 'power');
  assert.equal(nextRole(order, 'enterprise'), 'beginner');
  assert.equal(nextRole(order, null), 'beginner');
  assert.equal(nextRole(order, 'ghost'), 'beginner');
  assert.equal(nextRole([], 'x'), '');
});

test('quickCardId：稳定且只含安全字符', () => {
  assert.equal(quickCardId('/docs#api'), 'quick-role-docsapi');   // 与迁移前实现一致
  assert.equal(quickCardId(''), 'quick-role-');
});

test('shouldShowPicker：老访客/禁用/已有浮层/有弹窗都不显示', () => {
  assert.equal(shouldShowPicker({ storedRole: null }), true);
  assert.equal(shouldShowPicker({ storedRole: 'power' }), false);
  assert.equal(shouldShowPicker({ storedRole: null, disabled: true }), false);
  assert.equal(shouldShowPicker({ storedRole: null, hasOverlay: true }), false);
  assert.equal(shouldShowPicker({ storedRole: null, dialogOpen: true }), false);
});

test('defaultRole：候选非法回落第一个', () => {
  const order = ['beginner', 'power'];
  assert.equal(defaultRole('power', order), 'power');
  assert.equal(defaultRole('ghost', order), 'beginner');
  assert.equal(defaultRole(undefined, order), 'beginner');
  assert.equal(defaultRole('x', []), 'power');
});

test('parseQuery：正常解析，异常输入返回空对象', () => {
  assert.deepEqual(parseQuery('?utm_source=github&a=1'), { utm_source: 'github', a: '1' });
  assert.deepEqual(parseQuery(''), {});
  assert.equal(typeof parseQuery('%%%'), 'object');   // 不抛错即可（怪异输入原样解析）
});

test('isAdChannel：只认非空广告参数', () => {
  assert.equal(isAdChannel({ utm_source: 'github' }), true);
  assert.equal(isAdChannel({ gclid: 'x' }), true);
  assert.equal(isAdChannel({ utm_source: '' }), false);
  assert.equal(isAdChannel({ a: '1' }), false);
});

test('inferRoleFromUtm：渠道归属正确且只返回已上线角色', () => {
  const order = ['beginner', 'power', 'dev', 'enterprise'];
  assert.equal(inferRoleFromUtm(parseQuery('?utm_source=linkedin'), order), 'enterprise');
  assert.equal(inferRoleFromUtm(parseQuery('?utm_campaign=developer-launch'), order), 'dev');
  assert.equal(inferRoleFromUtm(parseQuery('?utm_source=zhihu'), order), 'power');
  assert.equal(inferRoleFromUtm(parseQuery('?utm_source=newsletter'), order), null);
});

test('inferRoleFromUtm：目标角色未上线时返回 null（不产生脏数据）', () => {
  assert.equal(inferRoleFromUtm(parseQuery('?utm_source=github'), ['beginner', 'power']), null);
});
