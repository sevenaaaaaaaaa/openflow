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


class TestPerms(unittest.TestCase):
    """上传后必须显式 chmod：本机 0600 文件曾原样落到服务器 → 全站 500。"""

    def test_files_644_and_parent_dirs_755(self):
        script = deploy.perms_script(["lib/A.php", "plugins/x/view.php", "index.php"])
        root = deploy.REMOTE_ROOT.rstrip("/")
        file_part, dir_part = script.split(" && ")
        self.assertTrue(file_part.startswith("chmod 644 "))
        for rel in ["lib/A.php", "plugins/x/view.php", "index.php"]:
            self.assertIn(f"{root}/{rel}", file_part)
        self.assertTrue(dir_part.startswith("chmod 755 "))
        for d in ["lib", "plugins", "plugins/x"]:
            self.assertIn(f"{root}/{d}", dir_part)
        self.assertNotIn(f"{root} ", dir_part + " ")   # 不碰站点根目录本身

    def test_root_files_only_no_dir_chmod(self):
        script = deploy.perms_script([".htaccess"])
        self.assertTrue(script.startswith("chmod 644 "))
        self.assertNotIn("chmod 755", script)

    def test_empty(self):
        self.assertEqual(deploy.perms_script([]), "")

    def test_paths_are_shell_quoted(self):
        script = deploy.perms_script(["assets/a b.css"])
        self.assertIn("'", script)


if __name__ == "__main__":
    unittest.main(verbosity=2)
