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
  OF_DEPLOY_HOST / OF_DEPLOY_PORT / OF_DEPLOY_ROOT / OF_DEPLOY_USER   部署目标（必填：无默认值，缺失即报错）
  OF_DEPLOY_SUDO=1      远端写操作走 sudo -n（NAS 上 agent 无站点根写权限）
  CF_TOKEN + OF_CF_ZONE                               启用 Cloudflare purge
  R2_KEY / R2_SECRET / R2_ENDPOINT / R2_BUCKET        启用 sync-r2.py
退出码：0 成功；1 失败（含 md5 不一致）；2 参数/前置检查失败
"""

from __future__ import annotations

import argparse
import json
import os
import shlex
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEPLOY_DIR = ROOT / ".deploy"
BACKUP_DIR = DEPLOY_DIR / "backups"
MANIFEST_DIR = DEPLOY_DIR / "manifests"


def _load_dotenv(path: Path) -> None:
    """把仓库根 .env（gitignored，sync.sh 也会加载）载入环境变量；真实环境变量优先。"""
    try:
        text = path.read_text(encoding="utf-8")
    except OSError:
        return
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        if key.startswith("export "):
            key = key[len("export "):].strip()
        val = val.strip()
        if len(val) >= 2 and val[0] == val[-1] and val[0] in "\"'":
            val = val[1:-1]
        if key and key not in os.environ:
            os.environ[key] = val


def _load_keychain(service: str = "openflow-deploy") -> None:
    """macOS 兜底：没有 .env 时从钥匙串读部署凭据（真实环境变量优先）。

    条目约定：security find-generic-password -a <变量名> -s <service> -w
    """
    if sys.platform != "darwin":
        return
    keys = ("CF_TOKEN", "OF_CF_ZONE", "R2_ENDPOINT", "R2_KEY", "R2_SECRET", "R2_BUCKET")
    if all(os.environ.get(k) for k in keys):
        return
    for key in keys:
        if os.environ.get(key):
            continue
        try:
            found = subprocess.run(
                ["security", "find-generic-password", "-a", key, "-s", service, "-w"],
                capture_output=True, text=True, timeout=5)
        except (OSError, subprocess.SubprocessError):
            return
        if found.returncode == 0 and found.stdout.strip():
            os.environ[key] = found.stdout.strip()


_load_dotenv(ROOT / ".env")
_load_keychain()

HOST = os.environ.get("OF_DEPLOY_HOST", "")
PORT = os.environ.get("OF_DEPLOY_PORT", "22")
USER = os.environ.get("OF_DEPLOY_USER", "")
REMOTE_ROOT = os.environ.get("OF_DEPLOY_ROOT", "")
SITE = os.environ.get("OF_SITE_URL", "https://nownexts.com")

# NAS 上站点根属主 501:games，agent 只读；写操作统一 sudo -n，并用 install 指定属主。
# data/ 必须保持 www-data(712:712)，否则 PHP 写不进去（后台保存会静默失败）。
USE_SUDO = os.environ.get("OF_DEPLOY_SUDO", "1") == "1"
STAGING = os.environ.get("OF_DEPLOY_STAGING", "/tmp/openflow-deploy")
SITE_OWNER = os.environ.get("OF_DEPLOY_OWNER", "501:games")
DATA_OWNER = os.environ.get("OF_DEPLOY_DATA_OWNER", "712:712")

SSH_OPTS = ["-o", "StrictHostKeyChecking=no", "-o", "ConnectTimeout=10", "-o", "BatchMode=yes"]

# 允许部署的顶层路径（相对仓库根）
ALLOW_PREFIXES = ("admin/", "api/", "lib/", "includes/", "assets/", "plugins/", "scripts/", "deploy/",
                   "legacy/")   # legacy/：老页面纪念快照（静态 HTML，noindex）
ALLOW_ROOT_FILES = {".htaccess", "robots.txt", "sitemap.php",
                    "VERSION", "CHANGELOG.md"}   # version single source + changelog (read by changelog.php / docs.php)
# 永不部署
DENY_SUBSTRINGS = ("node_modules/", "/src/", "src/", ".git/", ".deploy/", "tests/", "deploy/sync.sh",
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
    return (True, "根文件白名单") if rel in ALLOW_ROOT_FILES or rel.endswith(".php") else (False, "根目录仅允许 *.php/.htaccess/robots.txt/sitemap.php/VERSION/CHANGELOG.md")


def remote_path(rel: str) -> str:
    return f"{REMOTE_ROOT.rstrip('/')}/{norm(rel)}"


# 上传后统一权限：scp/rsync 会沿用本机文件的 mode。本机有些文件是 0600（编辑器/工具原子写入
# 留下的），曾以 root:root 0600 落到服务器——Apache/PHP（www 用户）读不了 → 全站 500。
# 所以每次上传后显式 chmod：文件 644、所在目录 755（只动本次涉及的路径，不递归整站）。
REMOTE_FILE_MODE = "644"
REMOTE_DIR_MODE = "755"


def perms_script(files: list[str]) -> str:
    """生成把本次上传的文件设为 644、其所在目录（站点根以下）设为 755 的远端命令；无文件时返回空串。"""
    rels = sorted({norm(f) for f in files if norm(f)})
    if not rels:
        return ""
    root = REMOTE_ROOT.rstrip("/")
    dirs: set[str] = set()
    for rel in rels:
        parent = os.path.dirname(rel)
        while parent:
            dirs.add(f"{root}/{parent}")
            parent = os.path.dirname(parent)
    parts = ["chmod " + REMOTE_FILE_MODE + " " + " ".join(shlex.quote(remote_path(r)) for r in rels)]
    if dirs:
        parts.append("chmod " + REMOTE_DIR_MODE + " " + " ".join(shlex.quote(d) for d in sorted(dirs)))
    return " && ".join(parts)


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
    return run(["ssh", "-p", PORT, *SSH_OPTS, f"{USER}@{HOST}", script])


def ssh_root(script: str) -> subprocess.CompletedProcess:
    """远端写操作：以 agent 登录，按需 sudo -n 提权（NAS 默认开）。"""
    return ssh(script if not USE_SUDO else "sudo -n " + script)


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
    cp = ssh_root(f"md5sum {quoted} 2>/dev/null || true")
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
        ssh_root("install -d -m 755 " + " ".join(f"'{d}'" for d in dirs))


def normalize_remote_perms(files: list[str]) -> str | None:
    """上传后把文件/目录权限归一（644/755）。成功返回 None，失败返回错误信息。"""
    script = perms_script(files)
    if not script:
        return None
    cp = ssh_root(script)
    return None if cp.returncode == 0 else (cp.stderr or cp.stdout or "chmod 失败").strip()[:300]


def _owner_for(rel: str) -> str:
    return DATA_OWNER if norm(rel).startswith("data/") else SITE_OWNER


def _staging_root() -> str:
    return f"{STAGING}/{os.getpid()}"


def prepare_staging() -> str:
    root = _staging_root()
    cp = ssh(f"mkdir -p '{root}'")
    if cp.returncode != 0:
        raise RuntimeError(f"无法创建远端暂存目录 {root}：{(cp.stderr or '').strip()[:200]}")
    return root


def scp_upload_path(src: Path, target_rel: str) -> None:
    """先 scp 到 /tmp 暂存，再 sudo install 到站点根（644 + 预期属主）。"""
    target_rel = norm(target_rel)
    staging = f"{_staging_root()}/{target_rel}"
    parent = os.path.dirname(staging)
    run(["ssh", "-p", PORT, *SSH_OPTS, f"{USER}@{HOST}", f"mkdir -p '{parent}'"])
    cp = run(["scp", "-P", PORT, *SSH_OPTS, str(src), f"{USER}@{HOST}:{staging}"])
    if cp.returncode != 0:
        raise RuntimeError(f"scp 失败：{src} → {staging}\n{cp.stderr.strip()[:300]}")
    owner, group = _owner_for(target_rel).split(":")
    cp = ssh_root(f"install -D -m 644 -o {owner} -g {group} '{staging}' '{remote_path(target_rel)}'")
    if cp.returncode != 0:
        raise RuntimeError(f"install 失败：{target_rel}\n{(cp.stderr or cp.stdout or '').strip()[:300]}")


def scp_upload(rel: str, dest_rel: str | None = None) -> None:
    scp_upload_path(ROOT / rel, dest_rel or rel)


def backup_remote(rel: str, stamp: str) -> Path | None:
    """替换前把远端当前版本抓回本地，供回滚。返回本地备份路径（远端不存在则不备份）。"""
    dest = BACKUP_DIR / stamp / rel
    dest.parent.mkdir(parents=True, exist_ok=True)
    cp = run(["scp", "-P", PORT, *SSH_OPTS, f"{USER}@{HOST}:{remote_path(rel)}", str(dest)])
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
        scp_upload_path(src, rel)
        got = md5_remote([rel])[rel]
        want = md5_local(src)
        flag = "✓" if got == want else "✗"
        if got != want:
            bad += 1
        print(f"  {flag} {rel} {want[:8]} → 远端 {(got or 'MISSING')[:8]}")
    perm_err = normalize_remote_perms(files)
    if perm_err:
        bad += 1
        print(f"  ✗ 权限归一失败（文件可能对 Web 用户不可读）：{perm_err}", file=sys.stderr)
    return 1 if bad else 0


def init_env(target: Path) -> int:
    """把缺失的部署目标一次性写进 .env（gitignored）：只补不覆盖。

    取值来源：.deploy/env-bootstrap.json（一次性迁移文件）或终端交互输入。
    """
    keys = ["OF_DEPLOY_HOST", "OF_DEPLOY_USER", "OF_DEPLOY_ROOT", "OF_DEPLOY_PORT", "OF_DEPLOY_OPS_ROOT"]
    seed: dict[str, str] = {}
    bootstrap = ROOT / ".deploy" / "env-bootstrap.json"
    if bootstrap.is_file():
        try:
            seed = {str(k): str(v) for k, v in json.loads(bootstrap.read_text(encoding="utf-8")).items()}
        except (OSError, ValueError):
            print("⚠ 无法解析 .deploy/env-bootstrap.json，改为交互输入", file=sys.stderr)

    lines = target.read_text(encoding="utf-8").splitlines() if target.is_file() else []
    index: dict[str, int] = {}
    drop: set[int] = set()
    for i, raw in enumerate(lines):
        s = raw.strip()
        if not s or s.startswith("#") or "=" not in s:
            continue
        k, _, v = s.partition("=")
        k = k.strip()
        if k in keys:
            if k not in index:
                index[k] = i
            else:
                drop.add(i)   # 同一个键出现多次时只保留第一条，避免 dotenv/shell 读取顺序打架

    missing = [k for k in keys if k not in index or not lines[index[k]].partition("=")[2].strip().strip('"').strip("'")]
    if not missing:
        print(f"✓ {target.name} 已包含全部部署目标，未改动")
        return 0

    appends: list[str] = []
    changed: list[str] = []
    for k in missing:
        val = seed.get(k, "")
        if not val and sys.stdin.isatty():
            val = input(f"{k} = ").strip()
        if not val:
            print(f"✗ {k} 没有取值：先写 .deploy/env-bootstrap.json，或在终端里交互输入", file=sys.stderr)
            return 2
        if k in index:
            lines[index[k]] = f"{k}={val}"
        else:
            appends.append(f"{k}={val}")
        changed.append(k)

    if appends:
        if lines and lines[-1].strip():
            lines.append("")
        if not any(l.strip() == "# 部署目标（本机私有）" for l in lines):
            lines.append("# 部署目标（本机私有；由 deploy.py --init-env 写入）")
        lines.extend(appends)

    if drop:
        lines = [l for i, l in enumerate(lines) if i not in drop]
    target.write_text("\n".join(lines) + "\n", encoding="utf-8")
    target.chmod(0o600)
    print(f"✓ 已写入 {target.name}：{' / '.join(changed)}（权限 600，gitignored）")
    return 0


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
    ap.add_argument("--allow-incomplete", action="store_true",
                    help="R2/purge 未闭环时仍以 0 退出（默认视为部署失败，退出码 3）")
    ap.add_argument("--init-env", action="store_true",
                    help="把缺失的部署目标一次性写进 .env（只补不覆盖，权限 600）")
    ap.add_argument("--env-file", metavar="PATH", default="",
                    help="配合 --init-env 指定目标文件（默认仓库根 .env）")
    args = ap.parse_args()

    if args.init_env:
        return init_env(Path(args.env_file) if args.env_file else ROOT / ".env")

    # 部署目标是本机私有配置：仓库与示例文件不得出现真实值，缺失即失败（不回落任何默认主机）。
    missing_target = [k for k, v in (("OF_DEPLOY_HOST", HOST), ("OF_DEPLOY_USER", USER), ("OF_DEPLOY_ROOT", REMOTE_ROOT)) if not v]
    if missing_target:
        print("✗ 缺少部署目标：" + " / ".join(missing_target)
              + "（先跑 python3 scripts/deploy.py --init-env 一次，或手动写进仓库根 .env；模板见 .env.example）", file=sys.stderr)
        return 2

    # 属主随目标机而定：未显式配置时用部署器内的默认值，但要提示一次，避免"以为配了其实没配"。
    if not os.environ.get("OF_DEPLOY_OWNER") or not os.environ.get("OF_DEPLOY_DATA_OWNER"):
        print("⚠ 未设置 OF_DEPLOY_OWNER / OF_DEPLOY_DATA_OWNER，将使用部署器默认属主（建议写进本机配置）", file=sys.stderr)

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
        print(f"✗ 无法 ssh 到 {USER}@{HOST}:{PORT}", file=sys.stderr)
        return 2
    missing = [f for f in files if not (ROOT / f).is_file()]
    if missing:
        print("✗ 本地文件不存在：" + ", ".join(missing), file=sys.stderr)
        return 2

    remote_md5 = md5_remote(files)
    pairs = [(f, md5_local(ROOT / f), remote_md5.get(f)) for f in files]
    plan = build_plan(pairs, args.with_data)

    print(f"目标 {USER}@{HOST}:{REMOTE_ROOT}")
    for rel, why in plan.refused:
        print(f"  ⊘ 拒绝 {rel} —— {why}")
    for e in plan.entries:
        tag = "↑ 上传" if e.changed else "= 跳过"
        print(f"  {tag} {e.rel:34s} 本地 {e.local_md5[:8]}  远端 {(e.remote_md5 or 'MISSING')[:8]}")
    if not plan.uploads:
        print("✓ 远端已是最新，无需上传")
        return 0
    print(f"计划上传 {len(plan.uploads)} 个文件" + ("（dry-run）" if args.dry_run else ""))
    if args.dry_run:
        return 0

    stamp = time.strftime("%Y%m%d-%H%M%S")
    ensure_remote_dirs([e.rel for e in plan.uploads])

    # 1) 备份 + 上传（并发 6）→ 2) 一次批量 md5 断言
    #    注：逐文件 ssh 校验会让 200+ 文件变成 400+ 次远程调用（曾导致部署超时）
    def push(entry: Entry) -> tuple[str, str | None]:
        try:
            backup_remote(entry.rel, stamp)
            scp_upload(entry.rel)
            return entry.rel, None
        except RuntimeError as err:
            return entry.rel, str(err)

    workers = min(6, max(1, len(plan.uploads)))
    print(f"→ 并行上传（{workers} 并发）…")
    failed: list[str] = []
    done = 0
    with ThreadPoolExecutor(max_workers=workers) as pool:
        for rel, err in pool.map(push, plan.uploads):
            done += 1
            if err:
                print(f"  ✗ {err}", file=sys.stderr)
                failed.append(rel)
            elif done % 25 == 0 or done == len(plan.uploads):
                print(f"  … {done}/{len(plan.uploads)}")

    rels = [e.rel for e in plan.uploads]
    # 权限归一：不论本机 mode 如何，远端一律 644/755（防 0600 → 全站 500）
    perm_err = normalize_remote_perms([r for r in rels if r not in failed])
    if perm_err:
        print(f"  ✗ 权限归一失败（文件可能对 Web 用户不可读）：{perm_err}", file=sys.stderr)
        failed.extend([r for r in rels if r not in failed])
    else:
        print(f"  ✓ 权限归一：文件 {REMOTE_FILE_MODE} / 目录 {REMOTE_DIR_MODE}")
    got_all = md5_remote(rels)
    for e in plan.uploads:
        if e.rel in failed:
            continue
        got = got_all.get(e.rel)
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

    # data/ 归属体检：ssh 以 root 跑脚本/种子会把 data 里的文件写成 root 所有，
    # 而 Web 用户（www）写不进去 → 后台保存悄悄失败（分享统计、任务写入都踩过）。
    try:
        chk = ssh_root(f"cd {REMOTE_ROOT} && find data -user root -maxdepth 3 2>/dev/null | head -3")
        owners = (chk.stdout or "").strip()
        if owners:
            print("⚠ data/ 里有 root 所有的文件（Web 用户写不进去，后台保存会静默失败）：")
            for line in owners.splitlines():
                print("    " + line)
            print("  修正：ssh <host> \"cd <站点目录> && find data -user root -exec chown www:www {} +\"")
    except Exception:
        pass

    # 版本提醒：改动了 assets/ 但没动版本号
    touched_version = VERSION_FILE in files
    if needs_r2([e.rel for e in plan.uploads]) and not touched_version:
        print(f"⚠ 本次改了 assets/ 但未改 {VERSION_FILE}（OF_SHELL_VER）——浏览器可能仍命中旧缓存")

    # 4) 服务端缓存
    if not args.no_cache:
        cp = ssh_root(f"rm -f {REMOTE_ROOT}/data/cache/*.cache 2>/dev/null; echo ok")
        print("✓ 已清 data/cache" if cp.returncode == 0 else "⚠ 清缓存失败（可忽略）")

    # 5) R2 同步（assets 有变更时）——漏同步会让 CDN 长期停在旧内容，因此不再静默跳过
    incomplete: list[str] = []
    if needs_r2([e.rel for e in plan.uploads]):
        if args.no_r2:
            print("（--no-r2：跳过 R2 同步）")
        elif not all(os.environ.get(k) for k in ("R2_KEY", "R2_SECRET", "R2_ENDPOINT", "R2_BUCKET")):
            print("✗ assets 有变更但缺少 R2_* 环境变量：R2 未同步，CDN 不会更新", file=sys.stderr)
            incomplete.append("R2 同步未执行：缺少 R2_KEY/R2_SECRET/R2_ENDPOINT/R2_BUCKET（写仓库根 .env）")
        else:
            print("→ 同步 R2（assets 变更）…")
            cp = run([sys.executable, str(ROOT / "deploy" / "sync-r2.py")])
            if cp.returncode == 0:
                print("✓ R2 同步完成")
            else:
                print("✗ R2 同步失败（assets 已上线但 R2 未更新）", file=sys.stderr)
                incomplete.append("R2 同步失败：手动重跑 python3 deploy/sync-r2.py")

    # 6) Cloudflare purge
    if not args.no_purge:
        token, zone = os.environ.get("CF_TOKEN"), os.environ.get("OF_CF_ZONE", "")
        if not token:
            # 兼容旧路径：本地凭据文件（gitignored）。新配置请写 .env；这里命中时提示一次。
            print("⚠ 使用 data/cloudflare.json 回退凭据（建议迁移到本机配置）", file=sys.stderr)
            try:
                creds = json.loads((ROOT / "data" / "cloudflare.json").read_text(encoding="utf-8"))
                token = creds.get("token") or None
                zone = os.environ.get("OF_CF_ZONE") or creds.get("zone_id") or zone
            except (OSError, ValueError):
                pass
        urls = sorted({u for u in (url_for(e.rel) for e in plan.uploads) if u})
        if not token:
            print("✗ 缺少 CF_TOKEN，未执行 purge（CDN 可能仍是旧内容）", file=sys.stderr)
            if urls:
                incomplete.append("CF purge 未执行：缺少 CF_TOKEN（写仓库根 .env；token 需 Zone.Cache Purge 权限）")
        elif not zone:
            print("✗ 缺少 OF_CF_ZONE，未执行 purge（CDN 可能仍是旧内容）", file=sys.stderr)
            if urls:
                incomplete.append("CF purge 未执行：缺少 OF_CF_ZONE（写仓库根 .env）")
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
            if ok:
                print(f"✓ purge 成功（{len(urls)} 个 URL）")
            else:
                print(f"✗ purge 失败（{len(urls)} 个 URL）：检查 CF_TOKEN 是否有效/有 Cache Purge 权限", file=sys.stderr)
                incomplete.append("CF purge 失败：CF_TOKEN 无效或无 Cache Purge 权限")

    if incomplete:
        print("\n✗ 部署未完整完成：代码已上服务器，但 CDN 侧未闭环：", file=sys.stderr)
        for msg in incomplete:
            print("  • " + msg, file=sys.stderr)
        if args.allow_incomplete:
            print("  （--allow-incomplete：按警告处理，退出码 0）", file=sys.stderr)
            return 0
        print("  补好凭据后重跑：python3 scripts/deploy.py <本次文件>", file=sys.stderr)
        return 3

    return 0


if __name__ == "__main__":
    sys.exit(main())
