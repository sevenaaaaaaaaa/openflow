# 页面内容多语言（分段字典）

支持语言：zh-CN（源）、zh-TW、en、ja、ko。机制见 `lib/I18nContent.php`。

## 原理
营销页是 HTML 区块。运行时把页面输出切成「文本节点 + 少数属性值」，逐段查 `data/lang/content-{locale}.json`
（`{ 中文原文: 译文 }`）；命中即替换，未命中保持中文。导航菜单（nav.json）与页脚走同一套字典，站内链接自动补 `/{locale}` 前缀。

页面要「声明」才会翻译：`builder-pages.json` 里 `i18n_locales: ["zh-TW","en","ja","ko"]`（由 `scripts/seed-studio-pages.php` 的 A4 段写入）。
声明了的页面才输出 hreflang、`<html lang>` 才跟随语言；其余语言前缀 → 中文内容 + canonical 指回默认地址。

## 文件
- `segments.json`   待译中文片段全集（`extract` 生成）
- `content.{en,ja,ko}.json`  人工译文（受版本控制，**以中文原文为 key**）
- `content.zh-TW.json`       `make-zhtw.py` 用 zhconv + 台湾用语表生成；可直接手改个别条目（重跑不覆盖已有条目）
- `lang-patch.json`          各语言 `data/lang/{locale}.json` 的键级补丁（页脚简介等），由 `scripts/i18n-lang-patch.php` 合并

## 改了页面文案之后
```bash
php -S 127.0.0.1:8777 router.php &                                   # 本地起服务
php scripts/i18n-content.php extract --base http://127.0.0.1:8777    # 重新抽取
php scripts/i18n-content.php todo en      # 看某语言哪些新片段没翻（同理 ja / ko）
# 把译文补进 content.<locale>.json；zh-TW：python3 scripts/i18n/make-zhtw.py
php scripts/i18n-content.php check        # 必须 0 漏译
php scripts/i18n-content.php build        # → data/lang/content-*.json，部署时 --with-data 带上
```

## 新增一张要多语言的页面
1. 把 slug 加进 `scripts/i18n-content.php` 的 `$PAGES` 与 seed 脚本 A4 段；2. 按上面流程补译文。

## 约定
品牌：林下 Understory → en/ko 写 `Understory`，ja/zh-TW 写 `林下 Understory`；产品名统一拉丁写法（Litmus / Liana / LinkTo / ConFlow / ZeroZen）；
层级：入口层 / Entry layer / エントリー層 / 입구층；进阶层 / Advanced layer / 発展層 / 심화층；工作台层 / Studio layer / Studio層 / Studio층。
