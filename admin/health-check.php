<?php
/**
 * 站点健康检测 — 一键体检：Bug 扫描 + 健康评分
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

header('Content-Type: text/html; charset=utf-8');

// ─────────────────────────────────────────────
// 检测引擎：返回 [分类 => [检测项...]]
// 每项: status = pass|warn|fail, weight, title, detail, fix, fix_url
// ─────────────────────────────────────────────
function run_health_checks(): array {
    $checks = [];

    // ═══ 一、系统环境 ═══
    $sys = [];
    $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
    $sys[] = [
        'status' => $phpOk ? 'pass' : 'fail', 'weight' => 3,
        'title' => 'PHP 版本 ' . PHP_VERSION,
        'detail' => $phpOk ? 'PHP ' . PHP_VERSION . '（要求 ≥ 7.4）' : 'PHP 版本过低，建议升级至 7.4+ 以保证安全性与函数兼容',
        'fix' => '升级服务器 PHP 到 7.4+', 'fix_url' => 'settings.php',
    ];
    $needExt = ['json' => 'JSON', 'curl' => 'cURL', 'fileinfo' => 'Fileinfo', 'mbstring' => 'Mbstring'];
    foreach ($needExt as $ext => $label) {
        $has = extension_loaded($ext);
        $sys[] = [
            'status' => $has ? 'pass' : 'fail', 'weight' => 2,
            'title' => $label . ' 扩展',
            'detail' => $has ? $label . ' 已加载' : '缺少 ' . $label . ' 扩展，部分功能（图片/编码/网络请求）会失效',
            'fix' => '在 php.ini 中启用 ' . $ext . '.so/.dll', 'fix_url' => 'settings.php',
        ];
    }
    foreach ([['data', DATA_DIR], ['uploads', UPLOAD_DIR]] as [$label, $dir]) {
        $w = is_writable($dir);
        $sys[] = [
            'status' => $w ? 'pass' : 'fail', 'weight' => 3,
            'title' => $label . ' 目录可写',
            'detail' => $w ? $label . ' 目录可写' : $label . ' 目录不可写（' . $dir . '），无法保存内容',
            'fix' => '执行 chmod -R 775 ' . $dir, 'fix_url' => 'devops.php',
        ];
    }
    // 备份目录
    $backupDir = DATA_DIR . '/backups';
    if (!is_dir($backupDir)) { @mkdir($backupDir, 0755, true); }
    $backups = glob($backupDir . '/*');
    $lastBackup = '';
    $backupAge = 0;
    if ($backups) {
        usort($backups, fn($a, $b) => filemtime($b) - filemtime($a));
        $lastBackup = date('Y-m-d', filemtime($backups[0]));
        $backupAge = (time() - filemtime($backups[0])) / 86400;
    }
    if (!$backups) {
        $sys[] = ['status' => 'warn', 'weight' => 2, 'title' => '数据备份', 'detail' => '尚未创建任何备份', 'fix' => '前往运维工具创建一次备份', 'fix_url' => 'devops.php'];
    } elseif ($backupAge > 7) {
        $sys[] = ['status' => 'warn', 'weight' => 2, 'title' => '数据备份', 'detail' => '最近备份：' . $lastBackup . '（已超过 7 天）', 'fix' => '创建新备份，建议每周至少一次', 'fix_url' => 'devops.php'];
    } else {
        $sys[] = ['status' => 'pass', 'weight' => 2, 'title' => '数据备份', 'detail' => '最近备份：' . $lastBackup . '（' . (int)$backupAge . ' 天前）'];
    }
    $checks['系统环境'] = $sys;

    // ═══ 二、安全 ═══
    $sec = [];
    // data 目录防访问
    $htData = file_exists(DATA_DIR . '/.htaccess');
    $sec[] = [
        'status' => $htData ? 'pass' : 'fail', 'weight' => 3,
        'title' => 'data 目录访问保护',
        'detail' => $htData ? '已配置 .htaccess 拒绝访问' : 'data/ 缺少 .htaccess，敏感 JSON 可能被直接下载',
        'fix' => '创建 data/.htaccess 并写入 Deny from all', 'fix_url' => 'devops.php',
    ];
    $htApi = file_exists(__DIR__ . '/../api/.htaccess');
    if ($htApi) {
        $sec[] = ['status' => 'pass', 'weight' => 1, 'title' => 'API 密钥保护', 'detail' => 'api/ 目录已配置访问保护'];
    }
    // 默认密码检测
    $users = json_read(DATA_DIR . '/users.json');
    $weak = [];
    foreach ($users as $un => $u) {
        $ph = $u['password_hash'] ?? $u['password'] ?? '';
        $isDefault = $ph !== '' && ($ph === 'openflow2024' || $ph === md5('openflow2024') || password_verify('openflow2024', $ph));
        if ($isDefault) $weak[] = $un;
    }
    $sec[] = [
        'status' => empty($weak) ? 'pass' : 'fail', 'weight' => 4,
        'title' => '默认密码',
        'detail' => empty($weak) ? '未发现默认密码' : '以下账号仍在使用默认密码：' . implode(', ', $weak),
        'fix' => '修改为强密码', 'fix_url' => 'users.php',
    ];
    // 批量导入密钥
    $secret = json_read(DATA_DIR . '/import_secret.json');
    $skey = $secret['key'] ?? '';
    $sec[] = [
        'status' => (!empty($skey) && $skey !== 'change-me') ? 'pass' : 'fail', 'weight' => 3,
        'title' => '批量导入密钥',
        'detail' => !empty($skey) && $skey !== 'change-me' ? '已配置独立密钥' : '批量导入接口仍使用默认密钥，存在被滥用风险',
        'fix' => '重新生成密钥', 'fix_url' => 'api-batch.php',
    ];
    // PHP 错误显示（生产环境应关闭）
    $errDisp = (int)ini_get('display_errors');
    $sec[] = [
        'status' => $errDisp ? 'warn' : 'pass', 'weight' => 2,
        'title' => '错误信息显示',
        'detail' => $errDisp ? 'display_errors 已开启，生产环境可能泄露路径与数据库信息' : 'display_errors 已关闭（生产环境安全）',
        'fix' => '生产环境设置 display_errors = Off', 'fix_url' => 'settings.php',
    ];
    $checks['安全'] = $sec;

    // ═══ 三、内容健康 ═══
    $con = [];
    $articles = get_articles();
    $total = count($articles);
    $con[] = ['status' => 'pass', 'weight' => 1, 'title' => '文章总数', 'detail' => "共 {$total} 篇文章"];
    // 重复 slug
    $slugMap = [];
    $dupSlugs = [];
    foreach ($articles as $a) { $s = trim($a['slug'] ?? ''); if ($s !== '') { if (isset($slugMap[$s])) $dupSlugs[] = $s; else $slugMap[$s] = 1; } }
    $con[] = [
        'status' => empty($dupSlugs) ? 'pass' : 'warn', 'weight' => 3,
        'title' => 'Slug 唯一性',
        'detail' => empty($dupSlugs) ? '所有 Slug 唯一' : '存在重复 Slug：' . implode(', ', array_unique($dupSlugs)) . '（可能导致 URL 冲突）',
        'fix' => '编辑文章修改重复 Slug', 'fix_url' => 'articles.php',
    ];
    // 缺失内容
    $emptyContent = [];
    $noSeo = [];
    $noCover = [];
    $noCategory = [];
    $noTitle = [];
    foreach ($articles as $a) {
        $t = trim(strip_tags($a['content'] ?? ''));
        if ($t === '') $emptyContent[] = $a['title'] ?? $a['id'];
        if (empty(trim($a['seo_title'] ?? '')) || empty(trim($a['seo_desc'] ?? ''))) $noSeo[] = $a['title'] ?? $a['id'];
        if (empty(trim($a['cover'] ?? ''))) $noCover[] = $a['title'] ?? $a['id'];
        if (empty(trim($a['category'] ?? ''))) $noCategory[] = $a['title'] ?? $a['id'];
        if (empty(trim($a['title'] ?? ''))) $noTitle[] = $a['id'];
    }
    $con[] = [
        'status' => empty($emptyContent) ? 'pass' : 'warn', 'weight' => 4,
        'title' => '正文为空',
        'detail' => empty($emptyContent) ? '无空正文文章' : count($emptyContent) . ' 篇文章正文为空',
        'fix' => '补全正文', 'fix_url' => 'articles.php',
    ];
    $con[] = [
        'status' => empty($noTitle) ? 'pass' : 'fail', 'weight' => 4,
        'title' => '标题缺失',
        'detail' => empty($noTitle) ? '所有文章均有标题' : count($noTitle) . ' 篇文章缺少标题',
        'fix' => '补充标题', 'fix_url' => 'articles.php',
    ];
    $con[] = [
        'status' => empty($noSeo) ? 'pass' : 'warn', 'weight' => 3,
        'title' => 'SEO 标题/描述',
        'detail' => empty($noSeo) ? '所有文章已配置 SEO' : count($noSeo) . ' 篇文章缺少 SEO 标题或描述（影响搜索收录）',
        'fix' => '使用批量 SEO 策略补齐', 'fix_url' => 'seo-batch.php',
    ];
    $con[] = [
        'status' => empty($noCover) ? 'pass' : 'warn', 'weight' => 2,
        'title' => '文章封面图',
        'detail' => empty($noCover) ? '所有文章已配置封面' : count($noCover) . ' 篇文章缺少封面图（影响分享与列表展示）',
        'fix' => '为文章设置封面', 'fix_url' => 'articles.php',
    ];
    // 断链检测（文章正文内部链接）
    $slugs = [];
    foreach ($articles as $a) $slugs[$a['slug'] ?? ''] = 1;
    $brokenLinks = [];
    foreach ($articles as $a) {
        if (preg_match_all('#href=["\']/article/([^"\']+)#', $a['content'] ?? '', $m)) {
            foreach ($m[1] as $s) if (!isset($slugs[trim($s)])) $brokenLinks[] = ($a['title'] ?? $a['id']) . ' → /article/' . $s;
        }
    }
    $con[] = [
        'status' => empty($brokenLinks) ? 'pass' : 'warn', 'weight' => 3,
        'title' => '内部链接完整性',
        'detail' => empty($brokenLinks) ? '未发现失效内链' : count($brokenLinks) . ' 条失效内链：' . implode('；', array_slice($brokenLinks, 0, 5)),
        'fix' => '修正失效的文章链接', 'fix_url' => 'articles.php',
    ];
    // 封面图文件是否存在
    $brokenImgs = [];
    foreach ($articles as $a) {
        $cv = trim($a['cover'] ?? '');
        if ($cv !== '' && strpos($cv, 'http') !== 0) {
            $rel = ltrim(str_replace(SITE_URL, '', $cv), '/');
            $p = __DIR__ . '/../' . $rel;
            if (!file_exists($p)) $brokenImgs[] = ($a['title'] ?? $a['id']) . ' → ' . $cv;
        }
    }
    $con[] = [
        'status' => empty($brokenImgs) ? 'pass' : 'warn', 'weight' => 3,
        'title' => '封面图文件存在',
        'detail' => empty($brokenImgs) ? '封面图文件均存在' : count($brokenImgs) . ' 处封面图文件缺失：' . implode('；', array_slice($brokenImgs, 0, 5)),
        'fix' => '重新上传或修正封面路径', 'fix_url' => 'media.php',
    ];
    $checks['内容健康'] = $con;

    // ═══ 四、SEO ═══
    $seo = [];
    $root = __DIR__ . '/..';
    foreach (['sitemap.php' => 'Sitemap', 'robots.php' => 'Robots', 'llms.php' => 'LLMs', 'feed.php' => 'RSS'] as $file => $label) {
        $exists = file_exists($root . '/' . $file);
        $seo[] = [
            'status' => $exists ? 'pass' : 'warn', 'weight' => 2,
            'title' => $label,
            'detail' => $exists ? $label . ' 文件存在（' . $file . '）' : $label . ' 文件缺失（' . $file . '），影响搜索引擎/AI 抓取',
            'fix' => '上传或生成 ' . $file, 'fix_url' => 'seo-tools.php',
        ];
    }
    $idx = json_read(DATA_DIR . '/indexnow.json');
    $idxOk = !empty($idx['key']) && !empty($idx['host']);
    $seo[] = [
        'status' => $idxOk ? 'pass' : 'warn', 'weight' => 3,
        'title' => 'IndexNow 实时推送',
        'detail' => $idxOk ? '已配置（host: ' . htmlspecialchars($idx['host']) . '）' : '未配置 IndexNow，发布文章不会实时推送搜索引擎',
        'fix' => '生成 IndexNow 密钥', 'fix_url' => 'seo-tools.php',
    ];
    $stDataCount = 0;
    foreach (glob(DATA_DIR . '/structured/*/*.json') as $stFile) { if (json_read($stFile) !== []) $stDataCount++; }
    $seo[] = [
        'status' => 'pass', 'weight' => 1,
        'title' => '结构化数据',
        'detail' => '已配置 ' . $stDataCount . ' 条结构化数据（文章/页面/专题 JSON-LD）',
    ];
    $checks['SEO'] = $seo;

    // ═══ 五、数据完整性 ═══
    $dat = [];
    $jsonFiles = glob(DATA_DIR . '/*.json');
    $badJson = [];
    foreach ($jsonFiles as $f) {
        if (json_read($f) === null) $badJson[] = basename($f);
    }
    $dat[] = [
        'status' => empty($badJson) ? 'pass' : 'fail', 'weight' => 4,
        'title' => 'JSON 数据完整性',
        'detail' => empty($badJson) ? '全部 ' . count($jsonFiles) . ' 个数据文件可正常解析' : '损坏的 JSON：' . implode(', ', $badJson),
        'fix' => '检查并修复损坏的数据文件', 'fix_url' => 'devops.php',
    ];
    // 表单引用
    $forms = json_read(DATA_DIR . '/forms/index.json');
    $formIds = [];
    foreach ($forms as $f) $formIds[$f['id']] = 1;
    $subs = json_read(DATA_DIR . '/submissions/index.json');
    $orphanSubs = 0;
    foreach ($subs as $s) if (!isset($formIds[$s['form_id'] ?? ''])) $orphanSubs++;
    $dat[] = [
        'status' => $orphanSubs === 0 ? 'pass' : 'warn', 'weight' => 2,
        'title' => '表单引用完整性',
        'detail' => $orphanSubs === 0 ? '提交记录均关联有效表单' : $orphanSubs . ' 条提交记录关联的表单已不存在',
        'fix' => '清理孤儿提交记录', 'fix_url' => 'submissions.php',
    ];
    // 回收站
    $trash = json_read(DATA_DIR . '/trash.json');
    $trashCnt = count($trash);
    $dat[] = [
        'status' => $trashCnt === 0 ? 'pass' : 'warn', 'weight' => 1,
        'title' => '回收站',
        'detail' => $trashCnt === 0 ? '回收站为空' : "回收站中有 {$trashCnt} 篇文章（定期清空可释放空间）",
        'fix' => '清空回收站', 'fix_url' => 'articles.php?trash=1',
    ];
    $checks['数据完整性'] = $dat;

    // ═══ 六、媒体资产 ═══
    $med = [];
    $allFiles = [];
    foreach (glob(UPLOAD_DIR . '/*/*', GLOB_NOSORT) as $fp) { if (is_file($fp)) $allFiles[] = basename($fp); }
    $med[] = [
        'status' => 'pass', 'weight' => 1,
        'title' => '媒体资产',
        'detail' => count($allFiles) . ' 个媒体文件（含子目录）',
    ];
    $orphanMed = [];
    $allContent = '';
    foreach ($articles as $a) $allContent .= ($a['content'] ?? '') . ($a['cover'] ?? '');
    foreach (glob(UPLOAD_DIR . '/*/*', GLOB_NOSORT) as $fp) {
        if (!is_file($fp)) continue;
        $bn = basename($fp);
        $ext = strtolower(pathinfo($bn, PATHINFO_EXTENSION));
        if (strpos($allContent, $bn) === false && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $orphanMed[] = $bn;
        }
    }
    $med[] = [
        'status' => count($orphanMed) <= 10 ? 'pass' : 'warn', 'weight' => 1,
        'title' => '未引用媒体',
        'detail' => count($orphanMed) . ' 张图片未在任何文章中被引用' . (count($orphanMed) > 10 ? '（可清理以减小备份体积）' : ''),
        'fix' => '清理未使用的媒体文件', 'fix_url' => 'media.php',
    ];
    $checks['媒体资产'] = $med;

    return $checks;
}

// ─────────────────────────────────────────────
// 打分
// ─────────────────────────────────────────────
function compute_score(array $checks): array {
    $totalW = 0; $scoreW = 0; $stat = ['pass' => 0, 'warn' => 0, 'fail' => 0];
    foreach ($checks as $cat => $items) {
        foreach ($items as $it) {
            $w = $it['weight'] ?? 1;
            $totalW += $w;
            $stat[$it['status']] = ($stat[$it['status']] ?? 0) + 1;
            if ($it['status'] === 'pass') $scoreW += $w;
            elseif ($it['status'] === 'warn') $scoreW += $w * 0.6;
        }
    }
    $score = $totalW > 0 ? round($scoreW / $totalW * 100) : 100;
    $grade = $score >= 90 ? '健康' : ($score >= 75 ? '良好' : ($score >= 60 ? '一般' : ($score >= 40 ? '需关注' : '危险')));
    return ['score' => $score, 'grade' => $grade, 'totalW' => $totalW, 'stat' => $stat];
}

// 收集待修复项（有 fix_url 的非 pass 项）
function collect_fix_items(array $checks): array {
    $items = [];
    foreach ($checks as $cat => $list) {
        foreach ($list as $it) {
            if ($it['status'] !== 'pass' && !empty($it['fix_url'])) {
                $items[] = ['cat' => $cat, 'status' => $it['status'], 'title' => $it['title'], 'detail' => $it['detail'], 'fix' => $it['fix'] ?? '', 'fix_url' => $it['fix_url']];
            }
        }
    }
    return $items;
}

$checks = run_health_checks();
$scoreInfo = compute_score($checks);
$fixItems = collect_fix_items($checks);

$statusCss = ['pass' => 'var(--ok)', 'warn' => 'var(--warn)', 'fail' => 'var(--danger)'];
$statusIco = ['pass' => '✓', 'warn' => '!', 'fail' => '✕'];

if (!defined('OF_EMBED')) admin_header('健康检测');
?>
<style>
.health-hero{display:flex;gap:32px;align-items:center;flex-wrap:wrap;margin-bottom:24px}
.score-ring{width:140px;height:140px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;position:relative}
.score-ring .val{font-size:40px;font-weight:800;letter-spacing:-.02em}
.score-ring .val small{font-size:14px;font-weight:600;color:var(--text-3)}
.score-info{flex:1;min-width:240px}
.score-info .grade{font-size:24px;font-weight:700;margin-bottom:6px}
.score-info .grade-desc{font-size:13px;color:var(--text-3);line-height:1.6}
.stat-chips{display:flex;gap:12px;margin-top:14px;flex-wrap:wrap}
.stat-chips .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;background:var(--surface-2)}
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:24px}
.cat-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px}
.cat-card .cat-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.cat-card .cat-name{font-weight:600;font-size:14px}
.cat-card .cat-score{font-size:18px;font-weight:700}
.cat-card .bar{height:6px;background:var(--surface-2);border-radius:99px;overflow:hidden}
.cat-card .bar i{display:block;height:100%;border-radius:99px;transition:width .4s}
.cat-card .cat-detail{font-size:12px;color:var(--text-3);margin-top:8px}
.item-row{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);align-items:flex-start}
.item-row:last-child{border-bottom:0}
.item-status{width:22px;height:22px;border-radius:50%;color:#fff;display:grid;place-items:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:2px}
.item-title{font-weight:600;font-size:14px}
.item-detail{font-size:13px;color:var(--text-2);margin-top:2px;line-height:1.5}
.item-fix{font-size:12px;color:var(--text-3);margin-top:6px}
.item-fix a{color:var(--accent)}
.empty-fix{text-align:center;padding:32px;color:var(--text-3)}
</style>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('health-check'); ?>
  <div class="main">
<?php endif; ?>
<?php
// B3：浅 CRUD 页归并为本页的子 tab
require_once __DIR__ . '/_subtabs.php';
$SUBTABS = ['self' => ['健康检测', '', 'health-check'],
            'stor' => ['存储', 'storage.php', 'storage']];
$__sub = of_subtab_begin($SUBTABS);
if ($__sub === 'self'):
?>
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 站点健康检测</h1>
      <a href="health-check.php" class="btn btn-primary btn-sm ml-auto" style="margin-left:auto">↻ 重新检测</a>
    </div>
    <p class="sub">一键扫描常见 Bug、安全风险与内容健康度，并给出修复建议</p>

    <?php
    $ringColor = $scoreInfo['score'] >= 90 ? 'var(--ok)' : ($scoreInfo['score'] >= 75 ? '#65a30d' : ($scoreInfo['score'] >= 60 ? 'var(--warn)' : 'var(--danger)'));
    ?>
    <!-- 评分 -->
    <div class="health-hero">
      <div class="score-ring" style="background:conic-gradient(<?=$ringColor?> <?=$scoreInfo['score']*3.6?>deg, var(--surface-2) 0deg)">
        <div style="width:112px;height:112px;border-radius:50%;background:var(--surface);display:grid;place-items:center;text-align:center">
          <div>
            <div class="val"><?=$scoreInfo['score']?><small> / 100</small></div>
          </div>
        </div>
      </div>
      <div class="score-info">
        <div class="grade" style="color:<?=$ringColor?>"><?=$scoreInfo['grade']?></div>
        <div class="grade-desc">
          综合健康评分。共检测 <?=$scoreInfo['totalW']?> 项加权指标，
          <?=$scoreInfo['stat']['pass']?> 项通过，<?=$scoreInfo['stat']['warn']?> 项需注意，<?=$scoreInfo['stat']['fail']?> 项存在问题。
        </div>
        <div class="stat-chips">
          <span class="chip" style="color:var(--ok)">✓ <?=$scoreInfo['stat']['pass']?> 正常</span>
          <span class="chip" style="color:var(--warn)">! <?=$scoreInfo['stat']['warn']?> 提醒</span>
          <span class="chip" style="color:var(--danger)">✕ <?=$scoreInfo['stat']['fail']?> 问题</span>
          <?php if (!empty($fixItems)): ?><span class="chip" style="color:var(--accent)">🔧 <?=count($fixItems)?> 条修复建议</span><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 分类评分 -->
    <div class="cat-grid">
      <?php foreach ($checks as $cat => $items):
        $catScore = compute_score([$cat => $items]);
        $c = $catScore['score'];
        $cc = $c >= 90 ? 'var(--ok)' : ($c >= 75 ? '#65a30d' : ($c >= 60 ? 'var(--warn)' : 'var(--danger)'));
      ?>
      <div class="cat-card">
        <div class="cat-head">
          <span class="cat-name"><?=htmlspecialchars($cat)?></span>
          <span class="cat-score" style="color:<?=$cc?>"><?=$c?></span>
        </div>
        <div class="bar"><i style="width:<?=$c?>%;background:<?=$cc?>"></i></div>
        <div class="cat-detail">
          <?=$catScore['stat']['pass']?> 正常 · <?=$catScore['stat']['warn']?> 提醒 · <?=$catScore['stat']['fail']?> 问题
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 待修复清单 -->
    <div class="card">
      <h2>🔧 需要处理的事项</h2>
      <?php if (empty($fixItems)): ?>
        <div class="empty-fix">🎉 未发现需要处理的问题，网站运行健康</div>
      <?php else: ?>
        <?php foreach ($fixItems as $it): ?>
        <div class="item-row">
          <div class="item-status" style="background:<?=$statusCss[$it['status']]?>"><?=$statusIco[$it['status']]?></div>
          <div style="flex:1">
            <div class="item-title"><?=htmlspecialchars($it['title'])?> <span class="badge badge-gray" style="font-size:11px"><?=htmlspecialchars($it['cat'])?></span></div>
            <div class="item-detail"><?=htmlspecialchars($it['detail'])?></div>
            <div class="item-fix">
              💡 <?=htmlspecialchars($it['fix'])?>
              → <a href="<?=htmlspecialchars($it['fix_url'])?>">前往处理</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
<?php else: of_subtab_include($SUBTABS, $__sub); endif; ?>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
