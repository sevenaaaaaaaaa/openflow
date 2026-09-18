/**
 * 角色逻辑（纯函数）—— 抽自 assets/role-switch.js，便于单测。
 *
 * 只做「判断与计算」，不碰 DOM；DOM 副作用留在 src/roles/switch.js。
 */

export interface QuickEntryLike {
  href: string;
  t?: string;
}

export interface RoleLike {
  label?: string;
  qs?: QuickEntryLike[];
  hero?: { cta1?: string; cta2?: string; cta3?: string };
  band?: { btn1?: string; btn2?: string; btn3?: string };
}

/** 仅站内路径（非 http(s) 外链）*/
export function isInternalHref(href: unknown): href is string {
  return typeof href === 'string' && href.length > 0 && !/^https?:\/\//i.test(href);
}

/** 校验存储里的角色：非法 / 缺失 → null */
export function resolveRole(stored: unknown, roles: Record<string, unknown> | undefined): string | null {
  if (typeof stored !== 'string' || !stored) return null;
  if (!roles || !Object.prototype.hasOwnProperty.call(roles, stored)) return null;
  return stored;
}

/** 语义化 storage 读取（storage 可为 localStorage 或测试替身）*/
export function readStoredRole(
  storage: { getItem(k: string): string | null } | undefined,
  key: string,
  roles?: Record<string, unknown>
): string | null {
  try {
    return resolveRole(storage?.getItem(key), roles);
  } catch {
    return null;
  }
}

/** 写入（失败静默）*/
export function writeStored(
  storage: { setItem(k: string, v: string): void } | undefined,
  key: string,
  value: string
): boolean {
  try {
    storage?.setItem(key, value);
    return true;
  } catch {
    return false;
  }
}

/** 点击角色后是否自动跳转：取第一个「站内」入口，外链/锚点留在原页 */
export function roleLanding(content: RoleLike | undefined | null): string | null {
  const first = content?.qs?.[0]?.href;
  return isInternalHref(first) ? first : null;
}

/** CTA 按钮文案与绑定（仅站内 href 会覆盖按钮链接）*/
export function ctaBindings(content: RoleLike | undefined | null): { labels: (string | undefined)[]; hrefs: (string | null)[] } {
  const qs = content?.qs ?? [];
  const internal = qs.map((q) => (isInternalHref(q?.href) ? q.href : null)).slice(0, 2);
  return {
    labels: [content?.hero?.cta1, content?.hero?.cta2, content?.hero?.cta3],
    hrefs: [internal[0] ?? null, internal[1] ?? null],
  };
}

/** 底部 CTA band 的按钮文案 */
export function bandLabels(content: RoleLike | undefined | null): (string | undefined)[] {
  return [content?.band?.btn1, content?.band?.btn2, content?.band?.btn3];
}

/** 角色顺序循环（右下角「切换视角」按钮）*/
export function nextRole(order: readonly string[], current: string | null | undefined): string {
  if (!order.length) return '';
  const idx = current ? order.indexOf(current) : -1;
  return order[(idx + 1) % order.length] ?? order[0] ?? '';
}

/** 快速入口卡片的稳定 id（用于埋点选择器）*/
export function quickCardId(href: string): string {
  return 'quick-role-' + String(href ?? '').replace(/[^a-z0-9]/gi, '');
}

/**
 * 是否展示「首次访问角色选择浮层」：
 *  - 已存过角色 → 否（老访客不再打扰）
 *  - 页面显式禁用（window.OF_NO_ROLE_PICKER）→ 否
 *  - 已有浮层在页面上 → 否（幂等）
 *  - 已有弹窗打开 → 否（避免抢层级，稍后由事件/轮询让位逻辑处理）
 */
export function shouldShowPicker(opts: {
  storedRole: string | null;
  disabled?: boolean;
  hasOverlay?: boolean;
  dialogOpen?: boolean;
}): boolean {
  if (opts.disabled) return false;
  if (opts.storedRole) return false;
  if (opts.hasOverlay) return false;
  if (opts.dialogOpen) return false;
  return true;
}

/** 默认角色（超时未选择时使用）：非法则回落第一个 */
export function defaultRole(candidate: unknown, order: readonly string[]): string {
  if (typeof candidate === 'string' && order.includes(candidate)) return candidate;
  return order[0] ?? 'power';
}

/* ── 投放渠道识别与角色推断（纯逻辑，便于单测） ── */

export const AD_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid', 'msclkid', 'bd_vid'] as const;

/** 解析 query（失败返回空对象，绝不抛） */
export function parseQuery(search: string): Record<string, string> {
  const out: Record<string, string> = {};
  try {
    new URLSearchParams(search || '').forEach((v, k) => { out[k] = v; });
  } catch {
    /* ignore */
  }
  return out;
}

/** 是否投放渠道来源（带任一广告参数） */
export function isAdChannel(q: Record<string, string>): boolean {
  return AD_PARAMS.some((k) => typeof q[k] === 'string' && q[k] !== '');
}

/** 仅当角色存在于 order 时才返回，避免推断出未上线角色 */
function pick(role: string, order: readonly string[]): string | null {
  return order.includes(role) ? role : null;
}

/**
 * 从 UTM 渠道预判角色（未命中返回 null，由调用方回落）：
 *  - linkedin / b2b / enterprise 活动 → enterprise
 *  - github / dev / hacker / developer 活动 → dev
 *  - zhihu / 小红书 / wechat / growth / marketing 活动 → power
 */
export function inferRoleFromUtm(q: Record<string, string>, order: readonly string[]): string | null {
  const src = (q['utm_source'] ?? '').toLowerCase();
  const camp = (q['utm_campaign'] ?? '').toLowerCase();
  const has = (hay: string, needles: string[]): boolean => needles.some((n) => hay.includes(n));

  if (has(src, ['linkedin', 'b2b']) || has(camp, ['enterprise'])) return pick('enterprise', order);
  if (has(src, ['github', 'dev', 'hacker']) || has(camp, ['developer'])) return pick('dev', order);
  if (has(src, ['zhihu', 'xiaohongshu', 'wechat']) || has(camp, ['growth', 'marketing'])) return pick('power', order);
  return null;
}
