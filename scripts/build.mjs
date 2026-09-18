/**
 * 前端构建：把 src/ 下的入口打包到 assets/ 原路径
 *
 * 设计约束（与站点资产纪律对齐）：
 *  - 产物写回**原路径** → HTML 引用不变、?v=OF_SHELL_VER 版本机制不变
 *  - 产物入库（运行时/服务器不需要 Node），CI 用 `git diff --exit-code` 防止忘记构建
 *  - format=iife：与原来的普通 <script> 完全等价，零 HTML 改动
 *
 * 入口表：新增 TS/JS 迁移时，在 ENTRIES 里加一行即可。
 */
import { build } from 'esbuild';
import { statSync } from 'node:fs';

const ENTRIES = [
  { in: 'src/shell/index.js', out: 'assets/site-shell.js' },
  { in: 'src/roles/switch.js', out: 'assets/role-switch.js' },
  { in: 'src/roles/content.ts', out: 'assets/role-content.js' },
];

/* banner 保持**确定性**（不含日期/commit）：否则每次提交都会让产物"过期"，
   让 CI 的 `git diff --exit-code` 校验失去意义。源码归属看 src/ 与 git 历史即可。 */
let total = 0;
let inputs = 0;

for (const e of ENTRIES) {
  const res = await build({
    entryPoints: [e.in],
    outfile: e.out,
    bundle: true,
    format: 'iife',
    target: ['es2018'],
    charset: 'utf8',
    legalComments: 'none',
    banner: { js: `/*! ${e.out.split('/').pop()} · bundled from src/ by esbuild — 请勿手改；改 src/ 后运行 npm run build */` },
    minify: false,          // 先不压缩：便于线上排错与 diff 审阅
    sourcemap: false,
    metafile: true,
    logLevel: 'warning',
  });
  const size = statSync(e.out).size;
  const n = Object.keys(res.metafile.inputs).length;
  total += size; inputs += n;
  console.log(`  ${e.out.padEnd(28)} ${(size / 1024).toFixed(1).padStart(7)} KB · 模块 ${n}`);
}
console.log(`✓ 共 ${ENTRIES.length} 个产物 · ${(total / 1024).toFixed(1)} KB · 输入模块 ${inputs}`);
