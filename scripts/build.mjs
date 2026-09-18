/**
 * 前端构建：把 src/shell/index.js（+ src/lib/*.ts）打成单文件 assets/site-shell.js
 *
 * 设计约束（与站点资产纪律对齐）：
 *  - 产物写回**原路径** assets/site-shell.js → HTML 引用不变、?v=OF_SHELL_VER 版本机制不变
 *  - 产物入库（运行时/服务器不需要 Node），CI 用 `git diff --exit-code` 防止忘记构建
 *  - format=iife：与原来的普通 <script> 完全等价，不是 ESM，零 HTML 改动
 */
import { build } from 'esbuild';
import { execSync } from 'node:child_process';
import { statSync, readFileSync } from 'node:fs';

const OUT = 'assets/site-shell.js';
const pkg = JSON.parse(readFileSync('package.json', 'utf8'));

function gitHash() {
  try { return execSync('git rev-parse --short HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim(); }
  catch { return 'nogit'; }
}

const banner = `/*! site-shell.js · built by esbuild @ ${new Date().toISOString().slice(0, 10)} · rev ${gitHash()} */`;

const result = await build({
  entryPoints: ['src/shell/index.js'],
  outfile: OUT,
  bundle: true,
  format: 'iife',
  target: ['es2018'],
  charset: 'utf8',
  legalComments: 'none',
  banner: { js: banner },
  minify: false,          // 先不压缩：便于线上排错与 diff 审阅（体积可控）
  sourcemap: false,
  metafile: true,
  logLevel: 'info',
});

const size = statSync(OUT).size;
const inputs = Object.keys(result.metafile.inputs).length;
console.log(`✓ ${OUT}  ${(size / 1024).toFixed(1)} KB · 输入模块 ${inputs} 个 · 版本 ${pkg.version ?? 'n/a'}`);
