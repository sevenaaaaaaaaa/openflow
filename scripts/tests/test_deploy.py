"""deploy.py 纯逻辑单测（不触网）：python3 -m unittest discover -s scripts/tests"""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
import deploy  # noqa: E402


class TestAllowlist(unittest.TestCase):
    def test_allows_whitelisted_dirs(self):
        for rel in ["admin/config.php", "api/member.php", "lib/SeoHead.php",
                    "includes/site-nav.php", "assets/modules.css", "plugins/x/plugin.php"]:
            ok, why = deploy.is_deployable(rel)
            self.assertTrue(ok, f"{rel} 应允许：{why}")

    def test_allows_root_php_and_htaccess(self):
        for rel in ["index.php", "product.php", ".htaccess", "sitemap.php", "robots.txt"]:
            self.assertTrue(deploy.is_deployable(rel)[0], rel)

    def test_refuses_data_by_default_but_allows_when_opted_in(self):
        self.assertFalse(deploy.is_deployable("data/nav.json")[0])
        self.assertTrue(deploy.is_deployable("data/nav.json", ["data/nav.json"])[0])

    def test_refuses_sources_tests_backups_and_traversal(self):
        for rel in ["src/shell/index.js", "tests/x_test.php", "assets/a.bak-tmp",
                    "includes/x.old-2.php", "node_modules/x/index.js", "../etc/passwd",
                    "/abs/path.php", "package.json", "docs/GTM.md"]:
            self.assertFalse(deploy.is_deployable(rel)[0], f"{rel} 应被拒绝")

    def test_refuses_empty(self):
        self.assertFalse(deploy.is_deployable("   ")[0])


class TestPathsAndUrls(unittest.TestCase):
    def test_remote_path(self):
        self.assertEqual(deploy.remote_path("assets/modules.css"),
                         "/www/wwwroot/nownexts_com/assets/modules.css")

    def test_url_mapping(self):
        self.assertEqual(deploy.url_for("index.php"), "https://nownexts.com/")
        self.assertEqual(deploy.url_for("assets/modules.css"), "https://nownexts.com/assets/modules.css")
        self.assertEqual(deploy.url_for("sitemap.php"), "https://nownexts.com/sitemap.xml")
        self.assertEqual(deploy.url_for("product.php"), "https://nownexts.com/product")
        self.assertEqual(deploy.url_for(".htaccess"), "https://nownexts.com/")
        self.assertIsNone(deploy.url_for("lib/SeoHead.php"))

    def test_needs_r2_only_for_assets(self):
        self.assertTrue(deploy.needs_r2(["assets/site-shell.js"]))
        self.assertFalse(deploy.needs_r2(["index.php", "lib/X.php"]))


class TestPlan(unittest.TestCase):
    def test_plan_skips_identical_and_uploads_changed(self):
        plan = deploy.build_plan([
            ("index.php", "aaa", "aaa"),        # 一致 → skip
            ("assets/modules.css", "bbb", "ccc"),  # 不同 → upload
            ("product.php", "ddd", None),       # 远端缺失 → upload
        ])
        self.assertEqual([e.rel for e in plan.uploads], ["assets/modules.css", "product.php"])
        self.assertEqual([e.rel for e in plan.skips], ["index.php"])

    def test_plan_collects_refusals(self):
        plan = deploy.build_plan([("data/x.json", "a", None), ("src/a.ts", "b", None)])
        self.assertEqual(len(plan.refused), 2)
        self.assertEqual(sorted(r[0] for r in plan.refused), ["data/x.json", "src/a.ts"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
