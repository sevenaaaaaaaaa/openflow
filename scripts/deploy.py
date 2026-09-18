#!/usr/bin/env python3
"""
deploy.py —— 幂等、可核验的部署器（替代 shell + rsync）

为什么不用 rsync：本机 rsync 是 Apple openrsync，实测对子目录文件会出现
「报告成功、远端未变」的静默失败（见 docs/DEPLOY-CONVENTIONS.md）。
本工具改用**逐文件 scp + 双向 md5 断言**，并在替换前留远端备份，支持一键回滚。

用法：
  python3 scripts/deploy.py --dry-run index.php assets/modules.css     # 只打印计划
  python3 scripts/deploy.py index.php assets/modules.css               # 部署（自动：清缓存 / R2 / purge）
  python3 scripts/deploy.py --from-git HEAD                            # 部署上一个提交改动的可部署文件
  python3 scripts/deploy.py --rollback 20260918-2231                   # 回滚到某次部署前
  python3 scripts/deploy.py --with-data data/nav.json                  # 显式允许部署 data/（默认拒绝）

环境变量（可选）：
  OF_DEPLOY_HOST / OF_DEPLOY_PORT / OF_DEPLOY_ROOT   覆盖默认目标
  CF_TOKEN + OF_CF_ZONE                               启用 Cloudflare purge
  R2_KEY / R2_SECRET / R2_ENDPOINT / R2_BUCKET        启用 sync-r2.py
退出码：0 成功；1 失败（含 md5 不一致）；2 参数/前置检查失败
"""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import time
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEPLOY_DIR = ROOT / ".deploy"
BACKUP_DIR = DEPLOY_DIR / "backups"
MANIFEST_DIR = DEPLOY_DIR / "manifests"

HOST = os.environ.get("OF_DEPLOY_HOST", "172.96.253.73")
PORT = os.environ.get("OF_DEPLOY_PORT", "28766")
REMOTE_ROOT = os.environ.get("OF_DEPLOY_ROOT", "/www/wwwroot/nownexts_com")
SITE = os.environ.get("OF_SITE_URL", "https://nownexts.com")

SSH_OPTS = ["-o", "StrictHostKeyChecking=no", "-o", "ConnectTimeout=10", "-o", "BatchMode=yes"]

# 允许部署的顶层路径（相对仓库根）
ALLOW_PREFIXES = ("admin/", "api/", "lib/", "includes/", "assets/", "plugins/", "scripts/", "deploy/")
ALLOW_ROOT_FILES = {".htaccess", "robots.txt", "sitemap.php"}
# 永不部署
DENY_SUBSTRINGS = ("node_modules/", "/src/", "src/", ".git/", ".deploy/", "tests/",
                   ".bak", ".old-", ".tmp", "~", ".DS_Store", ".env")
ASSET_TRIGGERS = ("assets/",)          # 触发 R2 同步
VERSION_FILE = "includes/site-nav.php"  # 触发「版本号是否 bump」提醒


# ────────────────────────── 纯函数（可单测） ──────────────────────────

def norm(rel: str) -> str:
    """归一化相对路径：只剥离开头的 './'，保留 .htaccess 这类点文件（lstrip('./') 会误伤）。"""
    rel = (rel or "").strip().replace("\\", "/")
    while rel.startswith("./"):
        rel = rel[2:]
    return rel


def is_deployable(rel: str, with_data: list[str] | None = None) -> tuple[bool, str]:
    """判断相对路径是否允许部署；返回 (ok, 原因)。"""
    rel = norm(rel)
    if not rel:
        return False, "空路径"
    if rel.startswith("/") or ".." in Path(rel).parts:
        return False, "必须是仓库内相对路径"
    for bad in DENY_SUBSTRINGS:
        if bad in rel:
            return False, f"命中排除规则 {bad!r}"
    if rel.startswith("data/"):
        if with_data and rel in with_data:
            return True, "data/ 已显式允许"
        return False, "data/ 默认不部署（需 --with-data <file> 显式允许）"
    if "/" in rel:
        return (True, "目录白名单") if rel.startswith(ALLOW_PREFIXES) else (False, "不在允许的目录白名单内")
    return (True, "根文件白名单") if rel in ALLOW_ROOT_FILES or rel.endswith(".php") else (False, "根目录仅允许 *.php/.htaccess/robots.txt/sitemap.php")


def remote_path(rel: str) -> str:
    return f"{REMOTE_ROOT.rstrip('/')}/{norm(rel)}"


def url_for(rel: str, site: str = SITE) -> str | None:
    """把文件路径映射为需要 purge 的 URL（静态资产/data 没有页面 URL）。"""
    rel = norm(rel)
    if rel.startswith("assets/"):
        return f"{site}/{rel}"
    if rel == "index.php":
        return f"{site}/"
    if "/" in rel:
        return None          # 子目录 PHP（lib/、admin/…）不对应页面 URL，无需 purge
    if rel == "sitemap.php":
        return f"{site}/sitemap.xml"
    if rel.endswith(".php"):
        return f"{site}/" + rel[:-4]
    if rel == ".htaccess":
        return f"{site}/"
    return None


def needs_r2(files: list[str]) -> bool:
    return any(norm(f).endswith((".css", ".js", ".woff2", ".png", ".webp", ".svg")) and
               norm(f).startswith(ASSET_TRIGGERS) for f in files)


@dataclass
class Entry:
    rel: str
    local_md5: str
    remote_md5: str | None = None
    action: str = "upload"          # upload | skip | refuse
    reason: str = ""

    @property
    def changed(self) -> bool:
        return self.action == "upload"


@dataclass
class Plan:
    entries: list[Entry] = field(default_factory=list)
    refused: list[tuple[str, str]] = field(default_factory=list)

    @property
    def uploads(self) -> list[Entry]:
        return [e for e in self.entries if e.action == "upload"]

    @property
    def skips(self) -> list[Entry]:
        return [e for e in self.entries if e.action == "skip"]


def build_plan(pairs: list[tuple[str, str, str | None]], with_data: list[str] | None = None) -> Plan:
    """pairs: [(rel, local_md5, remote_md5)]；纯函数，便于测试。"""
    plan = Plan()
    for rel, lmd5, rmd5 in pairs:
        ok, why = is_deployable(rel, with_data)
        if not ok:
            plan.refused.append((rel, why))
            continue
        action = "skip" if (rmd5 and rmd5 == lmd5) else "upload"
        plan.entries.append(Entry(rel=rel, local_md5=lmd5, remote_md5=rmd5, action=action, reason=why))
    return plan


# ────────────────────────── shell/ssh 封装 ──────────────────────────

def run(cmd: list[str], *, capture: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, capture_output=capture, text=True)


def ssh(script: str) -> subprocess.CompletedProcess:
    return run(["ssh", "-p", PORT, *SSH_OPTS, f"root@{HOST}", script])


def md5_local(path: Path) -> str:
    out = run(["md5", "-q", str(path)])
    if out.returncode != 0 or not out.stdout.strip():
        raise RuntimeError(f"无法计算本地 md5：{path}")
    return out.stdout.strip()


def md5_remote(files: list[str]) -> dict[str, str | None]:
    """一次 ssh 批量取远端 md5；缺失文件返回 None。"""
    if not files:
        return {}
    quoted = " ".join(f"'{remote_path(f)}'" for f in files)
    cp = ssh(f"md5sum {quoted} 2>/dev/null || true")
    result: dict[str, str | None] = {f: None for f in files}
    for line in (cp.stdout or "").splitlines():
        parts = line.split()
        if len(parts) < 2:
            continue
        digest, rpath = parts[0], parts[-1]
        for f in files:
            if remote_path(f) == rpath:
                result[f] = digest
    return result


def ensure_remote_dirs(files: list[str]) -> None:
    dirs = sorted({os.path.dirname(remote_path(f)) for f in files})
    if dirs:
        ssh("mkdir -p " + " ".join(f"'{d}'" for d in dirs))


def scp_upload(rel: str, dest_rel: str | None = None) -> None:
    src = ROOT / rel
    dst = f"root@{HOST}:{remote_path(dest_rel or rel)}"
    cp = run(["scp", "-P", PORT, *SSH_OPTS, str(src), dst])
    if cp.returncode != 0:
        raise RuntimeError(f"scp 失败：{rel} → {dst}\n{cp.stderr.strip()[:300]}")


def backup_remote(rel: str, stamp: str) -> Path | None:
    """替换前把远端当前版本抓回本地，供回滚。返回本地备份路径（远端不存在则不备份）。"""
    dest = BACKUP_DIR / stamp / rel
    dest.parent.mkdir(parents=True, exist_ok=True)
    cp = run(["scp", "-P", PORT, *SSH_OPTS, f"root@{HOST}:{remote_path(rel)}", str(dest)])
    return dest if cp.returncode == 0 and dest.exists() else None


# ────────────────────────── 流程编排 ──────────────────────────

def files_from_git(repo_rel: str = "HEAD") -> list[str]:
    cp = run(["git", "-C", str(ROOT), "diff", "--name-only", f"{repo_rel}^", repo_rel])
    if cp.returncode != 0:
        cp = run(["git", "-C", str(ROOT), "show", "--name-only", "--pretty=format:", repo_rel])
    out: list[str] = []
    for rel in (cp.stdout or "").splitlines():
        rel = rel.strip()
        if rel and (ROOT / rel).is_file():
            ok, _ = is_deployable(rel)
            if ok:
                out.append(rel)
    return out


def save_manifest(stamp: str, plan: Plan) -> Path:
    MANIFEST_DIR.mkdir(parents=True, exist_ok=True)
    path = MANIFEST_DIR / f"{stamp}.json"
    path.write_text(json.dumps({
        "stamp": stamp, "host": HOST, "root": REMOTE_ROOT,
        "entries": [{"rel": e.rel, "local_md5": e.local_md5, "remote_md5": e.remote_md5} for e in plan.uploads],
    }, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return path


def do_rollback(stamp: str) -> int:
    backup = BACKUP_DIR / stamp
    if not backup.exists():
        print(f"✗ 找不到备份：{backup}", file=sys.stderr)
        return 2
    files = [str(p.relative_to(backup)) for p in backup.rglob("*") if p.is_file()]
    if not files:
        print("✗ 备份为空", file=sys.stderr)
        return 2
    print(f"回滚 {len(files)} 个文件 ← {backup}")
    ensure_remote_dirs(files)
    bad = 0
    for rel in files:
        src = backup / rel
        cp = run(["scp", "-P", PORT, *SSH_OPTS, str(src), f"root@{HOST}:{remote_path(rel)}"])
        got = md5_remote([rel])[rel]
        want = md5_local(src)
        flag = "✓" if got == want else "✗"
        if got != want:
            bad += 1
        print(f"  {flag} {rel} {want[:8]} → 远端 {(got or 'MISSING')[:8]}")
    return 1 if bad else 0


def main() -> int:
    ap = argparse.ArgumentParser(description="幂等 + md5 核验的部署器")
    ap.add_argument("files", nargs="*", help="要部署的仓库相对路径")
    ap.add_argument("--from-git", metavar="REF", help="部署该提交改动的可部署文件（如 HEAD）")
    ap.add_argument("--with-data", action="append", default=[], metavar="FILE", help="显式允许部署某个 data/ 文件（可多次）")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--rollback", metavar="STAMP")
    ap.add_argument("--no-r2", action="store_true", help="跳过 R2 同步")
    ap.add_argument("--no-purge", action="store_true", help="跳过 Cloudflare purge")
    ap.add_argument("--no-cache", action="store_true", help="跳过服务端缓存清理")
    args = ap.parse_args()

    if args.rollback:
        return do_rollback(args.rollback)

    files = list(args.files)
    if args.from_git:
        files += files_from_git(args.from_git)
    # 去重保序
    seen: set[str] = set()
    files = [f for f in files if not (f in seen or seen.add(f))]
    if not files:
        print("没有要部署的文件（给出路径，或用 --from-git HEAD）", file=sys.stderr)
        return 2

    # 前置检查
    if ssh("true").returncode != 0:
        print(f"✗ 无法 ssh 到 root@{HOST}:{PORT}", file=sys.stderr)
        return 2
    missing = [f for f in files if not (ROOT / f).is_file()]
    if missing:
        print("✗ 本地文件不存在：" + ", ".join(missing), file=sys.stderr)
        return 2

    remote_md5 = md5_remote(files)
    pairs = [(f, md5_local(ROOT / f), remote_md5.get(f)) for f in files]
    plan = build_plan(pairs, args.with_data)

    print(f"目标 root@{HOST}:{REMOTE_ROOT}")
    for rel, why in plan.refused:
        print(f"  ⊘ 拒绝 {rel} —— {why}")
    for e in plan.entries:
        tag = "↑ 上传" if e.changed else "= 跳过"
        print(f"  {tag} {e.rel:34s} 本地 {e.local_md5[:8]}  远端 {(e.remote_md5 or 'MISSING')[:8]}")
    if not plan.uploads:
        print("✓ 远端已是最新，无需上传")
    else:
        print(f"计划上传 {len(plan.uploads)} 个文件" + ("（dry-run）" if args.dry_run else ""))
    if args.dry_run:
        return 0

    stamp = time.strftime("%Y%m%d-%H%M%S")
    ensure_remote_dirs([e.rel for e in plan.uploads])

    # 1) 备份 → 2) 上传 → 3) 逐个 md5 断言
    failed: list[str] = []
    for e in plan.uploads:
        backup_remote(e.rel, stamp)
        try:
            scp_upload(e.rel)
        except RuntimeError as err:
            print(f"  ✗ {err}", file=sys.stderr)
            failed.append(e.rel)
            continue
        got = md5_remote([e.rel])[e.rel]
        if got != e.local_md5:
            print(f"  ✗ md5 不一致 {e.rel}：期望 {e.local_md5[:8]} 实际 {(got or 'MISSING')[:8]}", file=sys.stderr)
            failed.append(e.rel)
        else:
            print(f"  ✓ {e.rel} {e.local_md5[:8]}")

    if failed:
        print(f"✗ 有 {len(failed)} 个文件未通过核验：{', '.join(failed)}", file=sys.stderr)
        print(f"  可用回滚：python3 scripts/deploy.py --rollback {stamp}", file=sys.stderr)
        return 1

    manifest = save_manifest(stamp, plan)
    print(f"✓ 部署完成（manifest: {manifest.relative_to(ROOT)}）")

    # 版本提醒：改动了 assets/ 但没动版本号
    touched_version = VERSION_FILE in files
    if needs_r2([e.rel for e in plan.uploads]) and not touched_version:
        print(f"⚠ 本次改了 assets/ 但未改 {VERSION_FILE}（OF_SHELL_VER）——浏览器可能仍命中旧缓存")

    # 4) 服务端缓存
    if not args.no_cache:
        cp = ssh(f"rm -f {REMOTE_ROOT}/data/cache/*.cache 2>/dev/null; echo ok")
        print("✓ 已清 data/cache" if cp.returncode == 0 else "⚠ 清缓存失败（可忽略）")

    # 5) R2 同步（assets 有变更时）
    if needs_r2([e.rel for e in plan.uploads]) and not args.no_r2:
        r2_ok = all(os.environ.get(k) for k in ("R2_KEY", "R2_SECRET", "R2_ENDPOINT", "R2_BUCKET"))
        if not r2_ok:
            print("⚠ assets 有变更但缺少 R2_* 环境变量，已跳过 sync-r2.py（务必补跑）")
        else:
            print("→ 同步 R2（assets 变更）…")
            cp = run([sys.executable, str(ROOT / "sync-r2.py")])
            if cp.returncode == 0:
                print("✓ R2 同步完成")
            else:
                print("⚠ R2 同步失败，请手动重跑 sync-r2.py", file=sys.stderr)

    # 6) Cloudflare purge
    if not args.no_purge:
        token, zone = os.environ.get("CF_TOKEN"), os.environ.get("OF_CF_ZONE", "8135597542c2723a06a91a7e14a6e747")
        urls = sorted({u for u in (url_for(e.rel) for e in plan.uploads) if u})
        if not token:
            print("⚠ 缺少 CF_TOKEN，已跳过 purge（列表：" + ", ".join(urls[:4]) + ("…" if len(urls) > 4 else "") + "）")
        elif urls:
            payload = json.dumps({"files": urls})
            cp = run(["curl", "-s", "-X", "POST",
                      "-H", f"Authorization: Bearer {token}",
                      "-H", "Content-Type: application/json",
                      "--data", payload,
                      f"https://api.cloudflare.com/client/v4/zones/{zone}/purge_cache"])
            ok = False
            try:
                ok = bool(json.loads(cp.stdout or "{}").get("success"))
            except Exception:
                ok = False
            print(("✓ purge 成功 " if ok else "⚠ purge 失败 ") + f"（{len(urls)} 个 URL）")

    return 0


if __name__ == "__main__":
    sys.exit(main())
