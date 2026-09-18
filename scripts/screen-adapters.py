#!/usr/bin/env python3
"""
适配候选筛选（Adapter Candidate Screening）

目标：为「AI 自动适配成插件」挑选**首批上游候选**——不是越多越好，而是：
  - 有可核验的机器接口（REST / Webhook / MCP / CLI / 数据导出）
  - 许可证允许（MIT/Apache/BSD 等宽松；GPL/AGPL/无证 → 降级或剔除）
  - 活跃（近 6 个月有提交）
  - 与基座互补（能落到 hook / api_route / schedule / block / ingest / publish 之一）
  - 不与自家 7 个产品重叠

输入（都可复现）：
  A. data/navigation.json —— 我们自己已策展的 415 条（含 category/sub/tags/reason）
  B. GitHub Search（需 gh 已登录）—— 按扩展点需求定向检索

输出：
  data/ecosystem/candidates.json   机器可读（供 AdapterForge 消费）
  docs/ADAPTER-CANDIDATES.md       人工可读的首批清单与评分

用法：
  python3 scripts/screen-adapters.py                # 全量：本地策展 + GitHub 检索
  python3 scripts/screen-adapters.py --no-github     # 只用本地策展（离线可跑）
  python3 scripts/screen-adapters.py --limit 20      # 首批输出条数
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
NAV = ROOT / "data" / "navigation.json"
OUT_JSON = ROOT / "data" / "ecosystem" / "candidates.json"
OUT_MD = ROOT / "docs" / "ADAPTER-CANDIDATES.md"

# ── 基座扩展点（适配的落点）──
SURFACES = {
    "hook": "事件入站（webhook/事件回调）",
    "api_route": "API 出站调用（读它的数据/触发它的动作）",
    "schedule": "定时同步（定期拉取/回写）",
    "block": "前台展示（区块/组件）",
    "ingest": "数据入湖（内容/线索/订单导入）",
    "publish": "内容/消息分发（发到它的渠道）",
}

# ── 关键词 → 扩展点证据 ──
API_HINTS = {
    "api_route": ["api", "rest", "graphql", "sdk", "openapi", "swagger"],
    "hook": ["webhook", "callback", "events", "subscribe", "hooks"],
    "schedule": ["cron", "schedule", "sync", "polling", "定时"],
    "block": ["widget", "embed", "component", "iframe", "embed-code"],
    "ingest": ["import", "export", "csv", "etl", "connector", "digest"],
    "publish": ["publish", "post", "send", "notify", "bot", "publish-to"],
}
MCP_HINTS = ["mcp", "model context protocol", "model-context-protocol"]

PERMISSIVE = {"mit", "apache-2.0", "bsd-2-clause", "bsd-3-clause", "isc", "mpl-2.0", "unlicense"}
COPYLEFT = {"gpl-2.0", "gpl-3.0", "agpl-3.0", "lgpl-2.1", "lgpl-3.0", "sspl-1.0", "bsl-1.1"}

# 与自家 7 个产品重叠 → 不作为适配对象（避免自家生态打架）
OUR_OVERLAP = ["openflow", "mflow", "webs flow", "webs-flow", "userloop", "inflow", "payflow", "learnflow", "芭乐派"]


@dataclass
class Candidate:
    id: str
    name: str
    url: str
    source: str                      # nav | github
    category: str = ""
    description: str = ""
    license: str = ""
    stars: int = 0
    pushed_at: str = ""
    surfaces: list[str] = field(default_factory=list)
    evidence: str = ""               # 命中的关键词/依据
    evidence_grade: str = "C"        # A=官方 API/MCP 可核验, B=README 声明, C=仅描述
    score: int = 0
    already_connected: bool = False
    risks: list[str] = field(default_factory=list)


DEV_INFRA_HINTS = ["vllm", "inference", "embedding", "fine-tun", "training", "dataset", "cuda", "gpu",
                   "model-serving", "text-to-speech", "tts", "asr", "speech-recognition", "tokenizer",
                   "vector-database", "llm-serving", "pretrained", "benchmark", "compiler", "runtime"]


def is_dev_infra(name: str, desc: str, topics: list[str] | None = None) -> bool:
    text = f"{name} {desc} {' '.join(topics or [])}".lower()
    return any(h in text for h in DEV_INFRA_HINTS)


def is_product_like(name: str, desc: str, topics: list[str] | None = None) -> tuple[bool, str]:
    """排除框架/SDK/脚手架/教程/聚合清单——我们要的是「能当插件的产品」"""
    text = f"{name} {desc} {' '.join(topics or [])}".lower()
    for hint in NON_PRODUCT_HINTS:
        if kw_hit(text, hint):
            return False, f"含非产品信号 {hint!r}"
    return True, ""


def classify(name: str, desc: str, topics: list[str] | None = None) -> tuple[str | None, str]:
    """归入功能能力类；关键词须出现在「名称 + 描述前 120 字」（tags 噪音大，只做辅助）"""
    text = f"{name} {(desc or '')[:120]} {' '.join(topics or [])}".lower()
    best: tuple[str | None, str] = (None, "")
    for key, spec in CAPABILITY_CLASSES.items():
        for kw in spec["kwargs"]:
            if kw_hit(text, kw):
                if any(kw_hit(text, x) for x in spec["exclude"]):     # 该类明确排除的技术性信号
                    continue
                return key, kw
        if best[0] is None and not best[1]:
            best = (best[0], best[1])
    return best[0], best[1]


def norm_license(v: str) -> str:
    return (v or "").strip().lower().replace(" ", "-")


def kw_hit(text: str, kw: str) -> bool:
    """ASCII 关键词按词边界匹配（避免 'bi' 命中 'billing'），中文用子串"""
    kw = kw.lower()
    if not kw:
        return False
    if kw.isascii():
        return re.search(r"(?<![a-z0-9])" + re.escape(kw) + r"(?![a-z0-9])", text) is not None
    return kw in text


def detect_surfaces(text: str) -> tuple[list[str], str]:
    """从文本里找扩展点证据；返回（落点列表, 命中依据）"""
    t = (text or "").lower()
    hits: list[str] = []
    ev: list[str] = []
    for surface, kws in API_HINTS.items():
        for kw in kws:
            if kw_hit(t, kw):
                hits.append(surface)
                ev.append(kw)
                break
    if any(kw_hit(t, k) for k in MCP_HINTS):
        hits.append("hook")          # MCP 既是出站也是事件面，暂记 hook 侧
        ev.append("mcp")
    return sorted(set(hits)), ", ".join(sorted(set(ev))[:6])


def score(c: Candidate) -> int:
    """确定性评分（0-100）：类目契合 30 + 可映射面 15 + 许可证 25 + 活跃 15 + 证据 15（已接 −40）"""
    spec = CAPABILITY_CLASSES.get(c.category)
    s = 30 if spec else 0
    s += min(15, len(c.surfaces) * 5)

    lic = norm_license(c.license)
    if lic in PERMISSIVE:
        s += 25
    elif lic in COPYLEFT:
        s += 5
        c.risks.append(f"许可证 {lic} 传染性，需法务确认")
    else:
        s += 10
        c.risks.append("许可证待核验（intake 读仓库 LICENSE）")

    if c.pushed_at:
        y = c.pushed_at[:4]
        s += 15 if y >= "2026" else (8 if y >= "2025" else 0)
    elif c.source == "nav":
        s += 6

    s += {"A": 15, "B": 9, "C": 3}.get(c.evidence_grade, 3)

    # 与已接入通道重复 → 标注并大幅降权（保留可见，方便判断是否要「第二通道」）
    if spec:
        text = f"{c.name} {c.url} {c.description}".lower()
        if any(a in text for a in spec.get("already", [])):
            c.already_connected = True
            c.risks.append("该通道已接入基座")
            s -= 40

    c.score = max(0, min(100, s))
    return c.score


# ── 采集 A：本地策展 ──

def from_navigation(limit: int | None = None) -> list[Candidate]:
    data = json.loads(NAV.read_text(encoding="utf-8"))
    out: list[Candidate] = []
    # nav 是「链接目录」，不是插件候选池：只收带明确机器接口证据的条目
    INTEGRABLE = ["api", "webhook", "openapi", "rest", "sdk", "mcp", "integration", "integrate",
                  "zapier", "连接", "接口", "集成", "自动化", "同步"]
    for s in data.get("sites", []):
        if (s.get("status") or "published") != "published":
            continue
        text = " ".join(filter(None, [s.get("name"), s.get("description"), s.get("reason"), s.get("sub"), " ".join(s.get("tags") or [])]))
        if is_dev_infra(str(s.get("name") or ""), text, s.get("tags") or []):
            continue
        ok, _why = is_product_like(str(s.get("name") or ""), text, s.get("tags") or [])
        if not ok:
            continue
        url_l = str(s.get("url") or "").lower()
        if "github.com" not in url_l and not any(k in text.lower() for k in INTEGRABLE):
            continue
        cls, kw = classify(str(s.get("name") or ""), text, s.get("tags") or [])
        if cls is None:
            continue
        surfaces, ev = detect_surfaces(text)
        if not surfaces:
            surfaces = [CAPABILITY_CLASSES[cls]["surface"]]
        url = str(s.get("url") or "")
        grade = "B" if s.get("reason") else "C"
        if "github.com" in url:
            grade = "B"
        c = Candidate(
            id=str(s.get("id") or s.get("name")),
            name=str(s.get("name") or ""),
            url=url,
            source="nav",
            category=cls,
            description=str(s.get("description") or "")[:200],
            surfaces=surfaces,
            evidence=f"{kw} / {ev}" if ev else f"类目:{cls}",
            evidence_grade=grade,
        )
        if str(s.get("featured")) in ("1", "true"):
            c.evidence_grade = "B"
        if any(k in text.lower() for k in MCP_HINTS):
            c.evidence_grade = "A"
        out.append(c)
    out.sort(key=score, reverse=True)
    return out[:limit] if limit else out


# ── 采集 B：GitHub 定向检索 ──

# ── 我们基座真正需要的「功能能力类」（候选必须归入其一，否则不入首批）──
# already = 已接入的通道（重复的不再收录，只做「已接」标注）
CAPABILITY_CLASSES: dict[str, dict] = {
    "content_channel": {
        "need": "内容分发渠道（一稿多发到更多平台）",
        "surface": "publish",
        "kwargs": ["blog", "blogging", "publisher", "cms", "headless-cms", "social-media", "cross-post", "newsletter", "publish"],
        "exclude": ["sdk", "api-client", "wrapper", "framework"],
        "already": ["wordpress", "ghost", "telegram", "discord", "mastodon"],
        "queries": [
            "topic:cross-posting stars:>200 archived:false",
            "topic:social-media stars:>1000 archived:false",
            "topic:headless-cms stars:>1500 archived:false",
        ],
    },
    "notify_reach": {
        "need": "触达/通知通道（站内信之外的多通道提醒）",
        "surface": "publish",
        "kwargs": ["notification", "notify", "alert", "push", "message", "chatops", "email", "relay", "webhook"],
        "exclude": ["sdk", "api-client", "framework", "library"],
        "already": ["feishu", "wecom", "whatsapp", "smtp", "slack"],
        "queries": [
            "topic:notifications stars:>2000 archived:false",
            "topic:push-notifications stars:>2000 archived:false",
        ],
    },
    "data_ingest": {
        "need": "数据入湖（事件/行为/订单 → 我们的 CDP）",
        "surface": "ingest",
        "kwargs": ["analytics", "event", "telemetry", "tracking", "cdp", "etl", "pipeline", "collector", "bi"],
        "exclude": ["sdk", "client", "framework", "instrumentation"],
        "already": [],
        "queries": [
            "topic:web-analytics stars:>1500 archived:false",
            "topic:product-analytics stars:>800 archived:false",
        ],
    },
    "intel_source": {
        "need": "情报源（趋势/舆情/竞品 → inFlow 式选题）",
        "surface": "ingest",
        "kwargs": ["rss", "feed", "reader", "trend", "monitor", "scraping", "crawler", "news", "search"],
        "exclude": ["sdk", "client", "framework", "awesome"],
        "already": ["search console"],
        "queries": [
            "topic:rss-reader stars:>1000 archived:false",
            "topic:rss stars:>800 archived:false",
            "topic:web-scraping stars:>2000 archived:false",
        ],
    },
    "crm_leads": {
        "need": "线索/CRM 双向同步（我们不重复造 CRM）",
        "surface": "api_route",
        "kwargs": ["crm", "sales", "lead", "contact", "customer", "support", "helpdesk", "ticketing"],
        "exclude": ["sdk", "client", "framework", "template"],
        "already": [],
        "queries": [
            "topic:crm stars:>1000 archived:false",
            "topic:helpdesk stars:>800 archived:false",
        ],
    },
    "payment_billing": {
        "need": "收款/订阅通道（PayFlow 之外的补充渠道）",
        "surface": "api_route",
        "kwargs": ["payment", "billing", "subscription", "invoice", "paywall", "checkout"],
        "exclude": ["sdk", "client", "library", "sample"],
        "already": ["stripe", "wechat", "alipay", "虎皮椒"],
        "queries": [
            "topic:billing stars:>800 archived:false",
            "topic:subscription-management stars:>500 archived:false",
        ],
    },
    "workflow_agent": {
        "need": "工作流/Agent 能力（补我们的编排，不重复实现）",
        "surface": "api_route",
        "kwargs": ["workflow", "automation", "agent", "orchestration", "mcp", "llm", "rag", "no-code"],
        "exclude": ["sdk", "framework", "boilerplate", "tutorial", "awesome"],
        "already": [],
        "queries": [
            "topic:workflow-automation stars:>2000 archived:false",
            "topic:mcp-server stars:>500 archived:false",
        ],
    },
}

# ── 按能力类点名的种子候选（真实产品；脚本会逐条用 gh api 核验许可证/活跃/归档）──
SEED_REPOS: dict[str, list[str]] = {
    "notify_reach": [
        "caronc/apprise", "novuhq/novu", "knadh/listmonk", "resend/resend-php", "binwiederhier/ntfy",
        "gotify/server", "Finb/bark", "easychen/pushdeer", "dgtlmoon/changedetection.io",
    ],
    "content_channel": [
        "TryGhost/Ghost", "writefreely/writefreely", "MicroPyramid/django-blog-zinnia", "Typecho/Typecho",
        "praw-dev/praw", "mastodon/mastodon", "MisskeyIO/misskey", "pixelfed/pixelfed",
        "Chocobozzz/PeerTube", "friendica/friendica", "joomla/joomla-cms", "django-cms/django-cms",
    ],
    "data_ingest": [
        "plausible/analytics", "umami-software/umami", "PostHog/posthog", "matomo-org/matomo",
        "airbytehq/airbyte", "metabase/metabase", "openobserve/openobserve", "apache/superset",
    ],
    "intel_source": [
        "DIYgod/RSSHub", "FreshRSS/FreshRSS", "miniflux/v2", "searxng/searxng", "dgtlmoon/changedetection.io",
        "mendableai/firecrawl", "Unclecode/crawl4ai", "gocolly/colly",
    ],
    "crm_leads": [
        "twentyhq/twenty", "espocrm/espocrm", "krayin/laravel-crm", "chatwoot/chatwoot", "erxes/erxes",
        "zammad/zammad", "frappe/crm",
    ],
    "payment_billing": [
        "getlago/lago", "killbill/killbill", "juspay/hyperswitch", "invoiceplane/invoiceplane",
        "solidusio/solidus", "medusajs/medusa", "wechatpay-apiv3/wechatpay-php",
    ],
    "workflow_agent": [
        "n8n-io/n8n", "activepieces/activepieces", "windmill-labs/windmill", "langgenius/dify",
        "FlowiseAI/Flowise", "langflow-ai/langflow", "makeplane/plane", "nocodb/nocodb",
        "baserow/baserow", "appwrite/appwrite",
    ],
}

# 明确排除的“非产品”信号（框架/SDK/脚手架/教程/聚合清单）
NON_PRODUCT_HINTS = ["sdk", "framework", "boilerplate", "starter", "template", "awesome-", "tutorial",
                     "example", "demo", "playground", "docs", "cookbook", "cheatsheet", "learning",
                     "course", "book", "api-client", "wrapper", "bindings", "adapter-library"]

def gh_search(query: str, class_key: str, per_page: int = 10) -> list[Candidate]:
    cp = subprocess.run(
        ["gh", "api", "-X", "GET", "search/repositories", "-f", f"q={query}", "-f", f"per_page={per_page}",
         "-f", "sort=stars", "--jq", ".items[] | {full_name, html_url, description, stargazers_count, pushed_at, license: .license.spdx_id, topics}"],
        capture_output=True, text=True)
    if cp.returncode != 0:
        print(f"  ⚠ gh 检索失败：{query} → {cp.stderr.strip()[:80]}", file=sys.stderr)
        return []
    out: list[Candidate] = []
    for line in cp.stdout.splitlines():
        try:
            r = json.loads(line)
        except json.JSONDecodeError:
            continue
        topics = r.get("topics") or []
        ok, why = is_product_like(r.get("full_name") or "", r.get("description") or "", topics)
        if not ok:
            continue
        cls, kw = classify(r.get("full_name") or "", r.get("description") or "", topics)
        if cls is None:
            cls, kw = class_key, "检索类目"
        text = " ".join(filter(None, [r.get("description") or "", " ".join(topics)]))
        surfaces, ev = detect_surfaces(text)
        if not surfaces:
            surfaces, ev = [CAPABILITY_CLASSES[cls]["surface"]], f"类目:{cls}"
        c = Candidate(
            id=re.sub(r"[^a-z0-9]+", "-", (r.get("full_name") or "").lower()),
            name=r.get("full_name") or "",
            url=r.get("html_url") or "",
            source="github",
            category=cls,
            description=(r.get("description") or "")[:200],
            license=norm_license(r.get("license") or ""),
            stars=int(r.get("stargazers_count") or 0),
            pushed_at=str(r.get("pushed_at") or "")[:10],
            surfaces=surfaces,
            evidence=f"{kw} / {ev}",
            evidence_grade="A" if any(k in text.lower() for k in MCP_HINTS) else "B",
        )
        out.append(c)
    return out


GH_REPO_RE = re.compile(r"github\.com/([^/\s]+/[^/\s#?]+)")


def repo_slug(url: str) -> str | None:
    m = GH_REPO_RE.search(url or "")
    if not m:
        return None
    return m.group(1).removesuffix(".git")


def enrich_github(items: list[Candidate], cap: int = 25) -> None:
    """给指向 GitHub 仓库的候选补 license / stars / pushed_at（真实证据，一次一个 API 调用）"""
    done = 0
    for c in items:
        if done >= cap:
            break
        slug = repo_slug(c.url)
        if not slug or c.source != "nav":
            continue
        cp = subprocess.run(
            ["gh", "api", f"repos/{slug}", "--jq",
             "{license: .license.spdx_id, stars: .stargazers_count, pushed_at: .pushed_at, description: .description, topics: .topics, archived: .archived}"],
            capture_output=True, text=True)
        done += 1
        if cp.returncode != 0:
            c.risks.append(f"GitHub 仓库不可达（{slug}）")
            continue
        try:
            r = json.loads(cp.stdout)
        except json.JSONDecodeError:
            continue
        c.license = norm_license(r.get("license") or "")
        c.stars = int(r.get("stars") or 0)
        c.pushed_at = str(r.get("pushed_at") or "")[:10]
        if r.get("description"):
            c.description = str(r["description"])[:200]
        if r.get("archived"):
            c.risks.append("上游仓库已归档")
        # 用真实 README/描述重算落点
        text = " ".join(filter(None, [c.description, " ".join(r.get("topics") or [])]))
        cls2, kw2 = classify(c.name, text, r.get("topics") or [])
        if cls2:
            c.category = cls2
        surfaces, ev = detect_surfaces(text)
        if surfaces:
            c.surfaces = surfaces
        c.evidence = f"{kw2 or 'repo'} / {ev}" if ev else f"repo:{c.category}"
        c.evidence_grade = "A" if any(k in text.lower() for k in MCP_HINTS) else "B"


def verify_seeds() -> list[Candidate]:
    """按能力类点名核验种子产品：许可证 / 星级 / 活跃 / 是否归档（真实数据）"""
    out: list[Candidate] = []
    total = sum(len(v) for v in SEED_REPOS.values())
    print(f"C) 核验点名种子 {total} 个仓库…")
    for key, slugs in SEED_REPOS.items():
        for slug in slugs:
            cp = subprocess.run(
                ["gh", "api", f"repos/{slug}", "--jq",
                 "{full_name, html_url, description, stargazers_count, pushed_at, archived, license: .license.spdx_id, topics, homepage}"],
                capture_output=True, text=True)
            if cp.returncode != 0:
                out.append(Candidate(id=slug, name=slug, url=f"https://github.com/{slug}", source="seed",
                                     category=key, risks=["仓库不可达/已改名"], evidence_grade="C"))
                continue
            try:
                r = json.loads(cp.stdout)
            except json.JSONDecodeError:
                continue
            topics = r.get("topics") or []
            text = " ".join(filter(None, [r.get("description") or "", " ".join(topics)]))
            surfaces, ev = detect_surfaces(text)
            if not surfaces:
                surfaces = [CAPABILITY_CLASSES[key]["surface"]]
            c = Candidate(
                id=re.sub(r"[^a-z0-9]+", "-", (r.get("full_name") or slug).lower()),
                name=r.get("full_name") or slug,
                url=r.get("html_url") or f"https://github.com/{slug}",
                source="seed",
                category=key,
                description=(r.get("description") or "")[:200],
                license=norm_license(r.get("license") or ""),
                stars=int(r.get("stargazers_count") or 0),
                pushed_at=str(r.get("pushed_at") or "")[:10],
                surfaces=surfaces,
                evidence=f"点名种子 / {ev}" if ev else "点名种子",
                evidence_grade="A" if any(k in text.lower() for k in MCP_HINTS) else "B",
            )
            if r.get("archived"):
                c.risks.append("上游已归档")
            out.append(c)
    return out


def dedupe(items: list[Candidate]) -> list[Candidate]:
    seen: set[str] = set()
    out: list[Candidate] = []
    for c in items:
        k = (c.url or c.name).rstrip("/").lower()
        if k in seen:
            continue
        seen.add(k)
        out.append(c)
    return out


# ── 输出 ──

def write_outputs(batch: list[Candidate]) -> None:
    OUT_JSON.parent.mkdir(parents=True, exist_ok=True)
    OUT_JSON.write_text(json.dumps({
        "generated_by": "scripts/screen-adapters.py",
        "classes": {k: {"need": v["need"], "surface": v["surface"]} for k, v in CAPABILITY_CLASSES.items()},
        "count": len(batch),
        "candidates": [c.__dict__ for c in batch],
    }, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    lines = [
        "# 适配候选首批清单（按功能能力类）",
        "",
        "> 生成：`python3 scripts/screen-adapters.py`（`--no-github` 可离线跑）。",
        "> 评分：类目契合 30 + 可映射面 15 + 许可证 25 + 活跃 15 + 证据 15；**已接入的通道 −40**（保留可见，供判断是否要第二通道）。",
        "> 已排除：框架 / SDK / 脚手架 / 教程 / awesome 聚合清单（我们要的是「能当插件的产品」）。",
        f"> 首批 **{len(batch)}** 个。",
        "",
    ]
    by_class: dict[str, list[Candidate]] = {}
    for c in batch:
        by_class.setdefault(c.category or "unclassified", []).append(c)
    for key, spec in CAPABILITY_CLASSES.items():
        items = by_class.get(key, [])
        if not items:
            continue
        lines += [f"## {spec['need']}", "",
                  f"落点：`{spec['surface']}`　已接入：{'、'.join(spec['already']) or '—'}", "",
                  "| # | 候选 | 来源 | 许可证 | 星级 | 最近提交 | 分 | 证据 | 状态/风险 |",
                  "|---|------|------|--------|------|----------|----|------|-----------|"]
        for i, c in enumerate(items, 1):
            status = "已接入（第二通道？）" if c.already_connected else ("；".join(c.risks) or "待 intake 核验")
            src_label = {"seed": "种子", "nav": "策展", "github": "检索"}.get(c.source, c.source)
            lines.append(f"| {i} | [{c.name}]({c.url}) | {src_label} | {c.license or '—'} | {c.stars or '—'} | {c.pushed_at or '—'} | {c.score} | {c.evidence_grade} | {status} |")
        lines.append("")
    OUT_MD.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--discover", action="store_true", help="额外用 GitHub 检索补充（默认只用点名种子+本地策展）")
    ap.add_argument("--limit", type=int, default=20)
    args = ap.parse_args()

    pool = verify_seeds()
    nav = from_navigation()
    enrich_github(nav)
    print(f"A) 本地策展：{len(nav)} 个归入能力类（已为其中 GitHub 仓库补元数据）")
    pool += nav
    if args.discover:
        for key, spec in CAPABILITY_CLASSES.items():
            for q in spec["queries"]:
                got = gh_search(q, key)
                print(f"B) [{key}] 「{q}」→ {len(got)}")
                pool += got
    pool = dedupe(pool)
    for c in pool:
        score(c)
    pool.sort(key=lambda c: (not c.already_connected, c.score, c.stars), reverse=True)
    per_class_cap = max(3, args.limit // max(1, len(CAPABILITY_CLASSES)))
    kept: list[Candidate] = []
    used: dict[str, int] = {}
    for c in pool:
        if c.score < 40 and not c.already_connected:
            continue
        if used.get(c.category, 0) >= per_class_cap:
            continue
        used[c.category] = used.get(c.category, 0) + 1
        kept.append(c)
    batch = kept[: args.limit]
    write_outputs(batch)
    print(f"\n✅ 首批 {len(batch)} 个 → {OUT_MD.relative_to(ROOT)} · {OUT_JSON.relative_to(ROOT)}")
    from collections import Counter
    print("  类目分布:", dict(Counter(c.category for c in batch)))
    for c in batch[:12]:
        flag = " [已接]" if c.already_connected else ""
        print(f"  [{c.score:3d}] {c.category:16s} {c.name[:40]:42s}{flag}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
