/**
 * 弹窗状态机（纯逻辑，无 DOM）。
 *
 * 抽自 assets/site-shell.js 里已上线的通用弹窗行为层；DOM 适配层
 * （src/shell/index.js）注入 open/close 实现与副作用（锁滚动、焦点）。
 *
 * 不变量（有测试）：
 *  - 同时最多一个弹窗处于 open（打开新的会先关掉旧的）
 *  - ESC 关闭当前弹窗
 *  - 点击「弹窗之外」→ 关闭（命令面板靠这条）
 *  - 点击「全屏遮罩本体」→ 关闭；点击「内容」→ 不关
 *  - 打开锁滚动、关闭解锁，成对不重复
 */

export interface DialogHandle {
  id: string;
  /** 弹窗根节点（DOM 适配层传入 Element；这里只当作不透明节点） */
  el: unknown;
  /** 根节点是否就是全屏遮罩（点它=点空白处）。命令面板这类浮层传 false */
  selfIsScrim: boolean;
  open: () => void;
  close: () => void;
}

export interface RegistryEffects {
  onLock?: (locked: boolean) => void;
  onOpened?: (handle: DialogHandle) => void;
  onClosed?: (handle: DialogHandle) => void;
  /** 打开/关闭时广播（用于让首次角色浮层让位等） */
  onBroadcast?: (kind: 'open' | 'close', handle: DialogHandle) => void;
}

export interface ClickLike {
  target: unknown;
  /** DM 适配层用 node.contains(target) 实现 */
  contains: (node: unknown, target: unknown) => boolean;
}

export interface DialogRegistry {
  register: (handle: DialogHandle) => void;
  open: (id: string) => boolean;
  close: (id: string) => boolean;
  closeCurrent: () => boolean;
  current: () => DialogHandle | null;
  /** ESC：返回是否消费了该按键 */
  handleEscape: () => boolean;
  /** 点击：返回是否因此关闭了弹窗 */
  handleClick: (e: ClickLike) => boolean;
}

export function createDialogRegistry(effects: RegistryEffects = {}): DialogRegistry {
  const handles = new Map<string, DialogHandle>();
  let current: DialogHandle | null = null;
  let locked = false;

  function setLock(next: boolean): void {
    if (locked === next) return;
    locked = next;
    effects.onLock?.(next);
  }

  function activate(h: DialogHandle): void {
    current = h;
    setLock(true);
    h.open();
    effects.onBroadcast?.('open', h);
    effects.onOpened?.(h);
  }

  function deactivate(h: DialogHandle): void {
    h.close();
    current = null;
    setLock(false);
    effects.onBroadcast?.('close', h);
    effects.onClosed?.(h);
  }

  const registry: DialogRegistry = {
    register(handle) {
      handles.set(handle.id, handle);
    },

    open(id) {
      const h = handles.get(id);
      if (!h) return false;
      if (current === h) return true;
      if (current) {
        const prev = current;
        prev.close();
        current = null;
        // 不关闭锁：接着打开新弹窗，锁保持
        effects.onBroadcast?.('close', prev);
        effects.onClosed?.(prev);
      }
      activate(h);
      return true;
    },

    close(id) {
      const h = handles.get(id);
      if (!h || current !== h) return false;
      deactivate(h);
      return true;
    },

    closeCurrent() {
      return current ? registry.close(current.id) : false;
    },

    current: () => current,

    handleEscape() {
      if (!current) return false;
      registry.close(current.id);
      return true;
    },

    handleClick(e) {
      const h = current;
      if (!h) return false;
      const inside = e.contains(h.el, e.target);
      if (!inside) {                       // 点在弹窗之外
        registry.close(h.id);
        return true;
      }
      if (e.target === h.el && h.selfIsScrim) {   // 点在全屏遮罩空白处
        registry.close(h.id);
        return true;
      }
      return false;                        // 点在内容里 / 浮层本体 → 不关
    },
  };

  return registry;
}
