#!/usr/bin/env bash
# 工程质量门禁：一条命令跑完「测试 + 故事线审计 + 设计护栏」
#
# 用法：
#   bash scripts/ci.sh              # 全套（含可选的设备审计，若 python-playwright 可用）
#   bash scripts/ci.sh --fast       # 跳过设备审计（CI 上更快）
#
# 退出码：任一环节失败即非零（可直接挂 CI / pre-commit）

set -uo pipefail
cd "$(dirname "$0")/.." || exit 2

FAST=0
[ "${1:-}" = "--fast" ] && FAST=1

red() { printf '\033[31m%s\033[0m\n' "$1"; }
grn() { printf '\033[32m%s\033[0m\n' "$1"; }
hdr() { printf '\n\033[1m── %s ──\033[0m\n' "$1"; }

FAIL=0

hdr "0/5 前端：类型检查 + TS 单测 + 构建产物是否最新"
if [ -d node_modules ]; then
  if npm run --silent typecheck >/tmp/of_ci_tsc.log 2>&1; then grn "✓ typecheck"; else red "✗ typecheck"; tail -8 /tmp/of_ci_tsc.log; FAIL=1; fi
  if npm test --silent >/tmp/of_ci_vitest.log 2>&1; then
    grep -E '^ℹ (tests|pass|fail)' /tmp/of_ci_vitest.log | tr '\n' ' '; echo; grn "✓ TS 单测"
  else red "✗ TS 单测"; tail -8 /tmp/of_ci_vitest.log; FAIL=1; fi
  if npm run --silent build >/tmp/of_ci_build.log 2>&1; then
    if git diff --quiet -- assets/site-shell.js; then grn "✓ 构建产物已是最新"
    else red "✗ assets/site-shell.js 与 src/ 不一致（请 npm run build 后提交）"; git diff --stat -- assets/site-shell.js; FAIL=1; fi
  else red "✗ 构建失败"; tail -5 /tmp/of_ci_build.log; FAIL=1; fi
else
  echo "（跳过：未安装 node_modules，运行 npm install 启用前端检查）"
fi

hdr "0.5/5 运维脚本：deploy.py 纯逻辑单测"
if python3 -m unittest discover -s scripts/tests >/tmp/of_ci_py.log 2>&1; then
  tail -3 /tmp/of_ci_py.log | tr '\n' ' '; echo; grn "✓ deploy.py 单测"
else red "✗ deploy.py 单测"; tail -12 /tmp/of_ci_py.log; FAIL=1; fi

hdr "1/5 契约与单元测试（tests/*_test.php）"
P=0; F=0
for t in tests/*_test.php; do
  if php "$t" >/tmp/of_ci_test.log 2>&1; then P=$((P+1)); else F=$((F+1)); red "FAIL $t"; tail -3 /tmp/of_ci_test.log; fi
done
echo "PASS=$P FAIL=$F"
[ "$F" -eq 0 ] && grn "✓ 测试通过" || FAIL=1

hdr "2/5 页面故事线审计（本体序列 / 缺必需 / 收口顺序 / CTA）"
if php scripts/storyline-audit.php > /tmp/of_ci_audit.txt 2>&1; then
  tail -1 /tmp/of_ci_audit.txt; grn "✓ 故事线审计通过（0 ERROR）"
else
  red "✗ 故事线审计有 ERROR"; grep -E '^(✗|    ·)' /tmp/of_ci_audit.txt | head -20; FAIL=1
fi

hdr "3/5 设计护栏（新增 class 必须已在 CSS 定义）"
if php scripts/design-guard.php --all > /tmp/of_ci_guard.txt 2>&1; then
  tail -1 /tmp/of_ci_guard.txt; grn "✓ 无新增未定义样式"
else
  red "✗ 有新增且未定义的 class（= 新造视觉）"; grep -A1 '风险' /tmp/of_ci_guard.txt | head -20; FAIL=1
fi

if [ "$FAST" -eq 0 ] && python3 -c "import playwright" >/dev/null 2>&1; then
  hdr "4/5 多设备 + 弹窗审计（Playwright，真实测量）"
  if python3 scripts/audit-devices.py > /tmp/of_ci_dev.txt 2>&1; then
    OVER=$(grep -c '\*\*+' /tmp/of_ci_dev.txt || true)
    MODAL_BAD=$(awk -F'|' '/^\| (登录|个人中心|命令面板|首次)/ { if ($0 ~ /False/) n++ } END { print n+0 }' /tmp/of_ci_dev.txt)
    echo "横向溢出命中：$OVER · 弹窗异常项：$MODAL_BAD"
    if [ "$OVER" -eq 0 ] && [ "$MODAL_BAD" -eq 0 ]; then grn "✓ 多设备与弹窗无异常"; else red "✗ 见 /tmp/of_ci_dev.txt"; FAIL=1; fi
  else
    red "✗ 设备审计脚本执行失败"; tail -5 /tmp/of_ci_dev.txt; FAIL=1
  fi
else
  hdr "4/5 多设备 + 弹窗审计（已跳过：--fast 或未安装 playwright）"
fi

if [ "$FAIL" -eq 0 ]; then grn "\n全部通过 ✓"; else red "\n有失败项 ✗"; fi
exit "$FAIL"
