<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$settingsFile = DATA_DIR . '/settings.json';
$settings = json_read($settingsFile);

    $defaults = [
    'staging_mode' => false,
    'staging_banner' => '🧪 测试环境 · 数据不会永久保存',
    'multilang_enabled' => false,
    'multilang_default' => 'zh-CN',
    'multilang_languages' => 'zh-CN,zh-TW,en,ja,ko,ru,es,pt,ar,fr,de',
    'site_name' => 'OpenFlow',
    'site_url' => 'https://example.com',
    'company_name' => 'OpenFlow 科技有限公司',
    'company_year' => '2021',
    'phone' => '13800000000',
    'email' => 'admin@example.com',
    'address' => '上海市',
    'beian' => '',
    'site_slogan' => 'AI 时代的网站增长操作系统',
    'site_desc' => '专注于以 AI 驱动网站增长、营销与运营的解决方案提供商',
    'site_keywords' => '网站增长, 营销自动化, AI Agent, SEO, GEO, 线索管理',
    'site_logo' => '',
    'footer_about' => 'AI 时代的网站增长操作系统',
    'footer_contact' => '',
    'copyright_text' => '',
    'smtp_host' => '',
    'smtp_port' => '465',
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => 'no-reply@example.com',
    'smtp_from_name' => 'OpenFlow 官网',
    'ga_id' => '',
    'baidu_id' => '',
    'redis_host' => '127.0.0.1',
    'redis_port' => '6379',
    'redis_password' => '',
    'redis_database' => '0',
    'cors_origins' => 'https://one.example.com',
];
    $settings = array_merge($defaults, $settings);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $settings = array_intersect_key($_POST['settings'] ?? [], $defaults);
    // Handle checkboxes (not sent when unchecked)
    $settings['staging_mode'] = isset($_POST['settings']['staging_mode']);
    $settings['multilang_enabled'] = isset($_POST['settings']['multilang_enabled']);
    // 多语言复选框数组 → 逗号分隔
    if (isset($_POST['settings']['multilang_languages']) && is_array($_POST['settings']['multilang_languages'])) {
        $settings['multilang_languages'] = implode(',', array_values(array_filter(array_map('trim', $_POST['settings']['multilang_languages']))));
    }
    json_write($settingsFile, $settings);
    $message = '设置已保存';
}

admin_header('系统设置');
?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <h1>系统设置</h1>
    <p class="sub">站点基本信息与集成配置</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>站点信息</h2>
        <div class="field-row">
          <div class="field"><label>站点名称</label><input type="text" name="settings[site_name]" value="<?=htmlspecialchars($settings['site_name'])?>"></div>
          <div class="field"><label>站点 URL</label><input type="text" name="settings[site_url]" value="<?=htmlspecialchars($settings['site_url'])?>"></div>
        </div>
        <div class="field">
          <label>公司名称</label>
          <input type="text" name="settings[company_name]" value="<?=htmlspecialchars($settings['company_name'])?>">
        </div>
        <div class="field-row">
          <div class="field"><label>联系电话</label><input type="text" name="settings[phone]" value="<?=htmlspecialchars($settings['phone'])?>"></div>
          <div class="field"><label>联系邮箱</label><input type="text" name="settings[email]" value="<?=htmlspecialchars($settings['email'])?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>公司地址</label><input type="text" name="settings[address]" value="<?=htmlspecialchars($settings['address'])?>"></div>
          <div class="field"><label>ICP 备案号</label><input type="text" name="settings[beian]" value="<?=htmlspecialchars($settings['beian'])?>"></div>
        </div>
      </div>

      <div class="card">
        <h2>品牌设置 <span class="hint" style="font-weight:400">· 所有前端页面的品牌信息均由此配置驱动</span></h2>
        <div class="field-row">
          <div class="field"><label>站点标语 <span class="hint">· Slogan</span></label><input type="text" name="settings[site_slogan]" value="<?=htmlspecialchars($settings['site_slogan'] ?? 'AI 时代的网站增长操作系统')?>"></div>
          <div class="field"><label>Logo URL <span class="hint">· 空则用默认</span></label><input type="text" name="settings[site_logo]" value="<?=htmlspecialchars($settings['site_logo'] ?? '')?>" placeholder="assets/images/logo.png"></div>
        </div>
        <div class="field"><label>站点描述 <span class="hint">· SEO meta description</span></label><input type="text" name="settings[site_desc]" value="<?=htmlspecialchars($settings['site_desc'] ?? '')?>"></div>
        <div class="field"><label>SEO 关键词 <span class="hint">· 逗号分隔</span></label><input type="text" name="settings[site_keywords]" value="<?=htmlspecialchars($settings['site_keywords'] ?? '')?>"></div>
        <div class="field"><label>Footer 简介 <span class="hint">· footer 品牌区文案</span></label><input type="text" name="settings[footer_about]" value="<?=htmlspecialchars($settings['footer_about'] ?? 'AI 时代的网站增长操作系统')?>"></div>
        <div class="field"><label>Footer 联系信息 <span class="hint">· 每行一条</span></label><textarea name="settings[footer_contact]" rows="3" placeholder="OpenFlow 科技有限公司&#10;成立于 2021 年 · 上海"><?=htmlspecialchars($settings['footer_contact'] ?? '')?></textarea></div>
        <div class="field"><label>自定义版权文案 <span class="hint">· 空则自动生成「© 年份 公司名 备案号」</span></label><input type="text" name="settings[copyright_text]" value="<?=htmlspecialchars($settings['copyright_text'] ?? '')?>"></div>
      </div>

      <div class="card">
        <h2>邮件配置 (SMTP)</h2>
        <p class="text-sm text-muted mb-4">用于发送表单通知邮件。配置后系统将使用 SMTP 代替 PHP mail()</p>
        <div class="field-row">
          <div class="field"><label>SMTP 服务器</label><input type="text" name="settings[smtp_host]" value="<?=htmlspecialchars($settings['smtp_host'])?>" placeholder="smtp.example.com"></div>
          <div class="field"><label>端口</label><input type="text" name="settings[smtp_port]" value="<?=htmlspecialchars($settings['smtp_port'])?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>用户名</label><input type="text" name="settings[smtp_user]" value="<?=htmlspecialchars($settings['smtp_user'])?>"></div>
          <div class="field"><label>密码</label><input type="password" name="settings[smtp_pass]" value="<?=htmlspecialchars($settings['smtp_pass'])?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>发件人地址</label><input type="text" name="settings[smtp_from]" value="<?=htmlspecialchars($settings['smtp_from'])?>"></div>
          <div class="field"><label>发件人名称</label><input type="text" name="settings[smtp_from_name]" value="<?=htmlspecialchars($settings['smtp_from_name'])?>"></div>
        </div>
      </div>

      <!-- Staging Mode -->
      <div class="card">
        <h2>🧪 测试环境</h2>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[staging_mode]" value="1" <?=$settings['staging_mode']?'checked':''?> style="width:18px;height:18px">启用测试环境模式</label></div>
        <div class="field"><label>顶部提示文字</label><input type="text" name="settings[staging_banner]" value="<?=htmlspecialchars($settings['staging_banner'])?>"></div>
        <div class="msg msg-info" style="margin-top:8px">启用后前台顶部将显示提示条，告知访客当前为测试环境</div>
      </div>

      <!-- Multi-language -->
      <div class="card">
        <h2>🌐 多语言</h2>
        <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[multilang_enabled]" value="1" <?=$settings['multilang_enabled']?'checked':''?> style="width:18px;height:18px">启用多语言版本</label></div>
        <div class="field"><label>默认语言</label>
          <select name="settings[multilang_default]" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px">
            <?php require_once __DIR__ . '/../lib/I18n.php'; foreach (i18n_languages() as $code => $meta): ?>
            <option value="<?=$code?>" <?=$settings['multilang_default']===$code?'selected':''?>><?=htmlspecialchars($meta['native'])?><?=$code!=='zh-CN'?' (' . $code . ')':''?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>启用语言 <span class="hint">勾选可在前台切换的语言</span></label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
            <?php $langEnabled = array_flip(array_values(array_filter(array_map('trim', explode(',', $settings['multilang_languages'])))));
            foreach (i18n_languages() as $code => $meta): ?>
            <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1.5px solid <?=isset($langEnabled[$code])?'var(--accent)':'var(--border)'?>;border-radius:10px;cursor:pointer;font-size:13px;background:<?=isset($langEnabled[$code])?'var(--accent-soft)':'transparent'?>">
              <input type="checkbox" name="settings[multilang_languages][]" value="<?=$code?>" <?=isset($langEnabled[$code])?'checked':''?>> <?=htmlspecialchars($meta['native'])?><?=$meta['rtl']?' <span style="font-size:11px;color:var(--faint)">RTL</span>':''?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="msg msg-info" style="margin-top:8px">启用后前台出现语言切换器，URL 以 /en/、/ja/、/zh-TW/ 等前缀区分；翻译包在 <a href="languages.php">翻译管理</a> 中维护。</div>
      </div>

      <!-- Redis 配置 -->
      <div class="card">
        <h2>Redis 缓存</h2>
        <p class="text-sm text-muted mb-4">配置 Redis 连接以启用高性能缓存</p>
        <?php
        require_once __DIR__ . '/../lib/Cache.php';
        $redisStatus = Cache::testRedis();
        ?>
        <?php if ($redisStatus['ok']): ?>
        <div class="msg msg-success" style="margin-bottom:12px">Redis 已连接: v<?=$redisStatus['version']?> (<?=$redisStatus['host']?>:<?=$redisStatus['port']?>)</div>
        <?php else: ?>
        <div class="msg msg-info" style="margin-bottom:12px">Redis 未连接: <?=$redisStatus['error']?></div>
        <?php endif; ?>
        <div class="field-row">
          <div class="field"><label>主机</label><input type="text" name="settings[redis_host]" value="<?=htmlspecialchars($settings['redis_host'])?>" placeholder="127.0.0.1"></div>
          <div class="field"><label>端口</label><input type="text" name="settings[redis_port]" value="<?=htmlspecialchars($settings['redis_port'])?>" placeholder="6379"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>密码 <span class="hint">留空无密码</span></label><input type="password" name="settings[redis_password]" value="<?=htmlspecialchars($settings['redis_password'])?>" placeholder="可选"></div>
          <div class="field"><label>数据库编号</label><input type="text" name="settings[redis_database]" value="<?=htmlspecialchars($settings['redis_database'])?>" placeholder="0"></div>
        </div>
      </div>

      <!-- CORS 跨域 -->
      <div class="card">
        <h2>🌐 跨域埋点 (CORS) <span class="hint" style="font-weight:400">· 允许外部站点埋点到 CDP</span></h2>
        <div class="field"><label>允许的来源 <span class="hint">· 逗号分隔，用于监控其他站点（如 WordPress）</span></label><input type="text" name="settings[cors_origins]" value="<?=htmlspecialchars($settings['cors_origins'])?>" placeholder="https://one.example.com"></div>
        <p class="text-sm text-muted">在这些站点的页面引入 <code>&lt;script src="https://你的域名/api/sdk.php" data-api="https://你的域名/api/cdp.php"&gt;&lt;/script&gt;</code> 即可跨域埋点。</p>
      </div>

      <!-- 功能开关 -->
      <div class="card">
        <h2>⚙️ 功能开关 <span class="hint" style="font-weight:400">· 全局功能启停</span></h2>
        <div class="msg msg-info" style="margin-top:0">各模块内部开关（直播/咨询/订阅/商城/社区楼层等）在各模块设置页单独配置。</div>
        <div class="field-row">
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[enable_comments]" value="1" <?=!empty($settings['enable_comments'])?'checked':''?> style="width:18px;height:18px">启用评论系统</label></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[enable_ads]" value="1" <?=!empty($settings['enable_ads'])?'checked':''?> style="width:18px;height:18px">启用广告位</label></div>
        </div>
        <div class="field-row">
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[enable_sharing]" value="1" <?=!empty($settings['enable_sharing'])?'checked':''?> style="width:18px;height:18px">启用分享追踪</label></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[enable_waterfall]" value="1" <?=!empty($settings['enable_waterfall'])?'checked':''?> style="width:18px;height:18px">启用文章瀑布流</label></div>
        </div>
        <div class="field-row">
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[enable_newsletter]" value="1" <?=!empty($settings['enable_newsletter'])?'checked':''?> style="width:18px;height:18px">启用 Newsletter 订阅</label></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="settings[enable_activation]" value="1" <?=!empty($settings['enable_activation'])?'checked':''?> style="width:18px;height:18px">启用激活码体系</label></div>
        </div>
      </div>

      <div class="card">
        <h2>搜索引擎与统计</h2>
        <div class="field-row">
          <div class="field"><label>Google Analytics ID</label><input type="text" name="settings[ga_id]" value="<?=htmlspecialchars($settings['ga_id'])?>" placeholder="G-XXXXXXXXXX"></div>
          <div class="field"><label>百度统计 ID</label><input type="text" name="settings[baidu_id]" value="<?=htmlspecialchars($settings['baidu_id'])?>" placeholder="HMACCOUNT"></div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">保存所有设置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
